package com.wardogs.economy;

import com.wardogs.WardogsMod;
import com.wardogs.balance.Balance;
import com.wardogs.balance.RewardsDef;
import com.wardogs.combat.Ballistics;
import com.wardogs.match.Factions;
import com.wardogs.progress.Progression;
import com.wardogs.progress.Role;
import com.wardogs.net.HudPayload;
import net.minecraft.ChatFormatting;
import net.minecraft.network.chat.Component;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.world.entity.player.Player;
import com.wardogs.medic.Downed;
import net.neoforged.bus.api.EventPriority;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.event.entity.living.LivingDeathEvent;

import java.util.Map;
import java.util.UUID;
import java.util.concurrent.ConcurrentHashMap;

/** Pays money for kills made with bullets and tracks streaks, repeat victims and revenge. */
@EventBusSubscriber(modid = WardogsMod.MOD_ID)
public final class KillRewardHandler {
    private static final Map<UUID, Integer> STREAK = new ConcurrentHashMap<>();
    private static final Map<UUID, UUID> LAST_KILLED_BY = new ConcurrentHashMap<>();
    private static final Map<String, Integer> REPEAT = new ConcurrentHashMap<>();
    /** A victim on a kill streak of at least this many kills counts as a shutdown when ended (the source gives no threshold). */
    private static final int SHUTDOWN_STREAK = 3;

    /** Zone multiplier provider; replaced by the Control Zone system in a later milestone. */
    public interface ZoneMultiplier {
        double at(net.minecraft.world.level.Level level, net.minecraft.world.phys.Vec3 pos);
    }

    public static volatile ZoneMultiplier ZONES = (level, pos) -> 1.0;

    private KillRewardHandler() {}

    /** Remembers what a kill paid so a revive of the victim can claw it back. */
    private record Paid(UUID killer, long amount) {}

    private static final Map<UUID, Paid> PAID = new ConcurrentHashMap<>();

    /** Runs before the downed system cancels the death, so the kill is paid when the victim goes down. */
    @SubscribeEvent(priority = EventPriority.HIGH)
    public static void onDeath(LivingDeathEvent e) {
        Ballistics.Hit hit = Ballistics.current();
        if (hit == null || !(e.getSource().getEntity() instanceof ServerPlayer killer)) {
            return;
        }
        var victim = e.getEntity();
        if (victim instanceof com.wardogs.vehicle.Vehicle) {
            return; // vehicles pay their own destruction reward
        }
        if (victim instanceof Player downedVictim && Downed.is(downedVictim)) {
            PAID.remove(downedVictim.getUUID()); // finishing off a downed player: the kill was already paid
            return;
        }
        RewardsDef rd = Balance.get().rewards();
        UUID victimId = victim.getUUID();
        UUID killerId = killer.getUUID();
        String killClass = KillReward.classify(hit.weaponId());
        double base = rd.killBaseByClass().getOrDefault(killClass, rd.killBase());
        boolean targetOnly = KillReward.targetOnly(killClass);
        boolean sniperDistance = killClass.equals("sniper");
        double shooterZone = ZONES.at(killer.level(), killer.position());
        double targetZone = ZONES.at(killer.level(), victim.position());

        if (victim instanceof Player vp && Factions.sameTeam(killer, vp)) {
            // Teamkill: the shooter pays a multiple of what the kill would have been worth.
            long worth = KillReward.compute(rd, new KillReward.Context(base, hit.headshot(), targetOnly, sniperDistance, hit.distance(),
                    shooterZone, targetZone, 1, 0, false, false));
            long cost = Math.round(worth * rd.teamkillCostMult());
            Wallet.add(killer, -cost);
            com.wardogs.net.RewardPayload.send(killer, "TEAM KILL", -cost);
            HudPayload.sendTo(killer);
            return;
        }

        int streak = STREAK.merge(killerId, 1, Integer::sum);
        boolean revenge = victim instanceof Player && killerId.equals(LAST_KILLED_BY.get(victimId));
        boolean shutdown = victim instanceof Player && STREAK.getOrDefault(victimId, 0) >= SHUTDOWN_STREAK;
        String pair = killerId + ">" + victimId;
        int repeats = REPEAT.getOrDefault(pair, 0);
        REPEAT.put(pair, repeats + 1);
        if (victim instanceof Player) {
            STREAK.remove(victimId);
            LAST_KILLED_BY.put(victimId, killerId);
        }

        long reward = KillReward.compute(rd, new KillReward.Context(base, hit.headshot(), targetOnly, sniperDistance, hit.distance(),
                shooterZone, targetZone, streak, repeats, revenge, shutdown));
        Wallet.add(killer, reward);
        com.wardogs.match.MatchStats.kill(killer, reward);
        if (victim instanceof ServerPlayer deadPlayer) {
            com.wardogs.match.MatchStats.death(deadPlayer);
        }
        Progression.awardXp(killer, roleOf(hit.weaponId()), rd.roleXp());
        if (victim instanceof Player) {
            PAID.put(victimId, new Paid(killerId, reward));
        }
        String victimName = victim instanceof ServerPlayer vp2 ? vp2.getGameProfile().getName() : victim.getName().getString();
        var wdef = Balance.get().weapon(hit.weaponId());
        com.wardogs.net.KillFeedPayload.broadcast(killer.server, new com.wardogs.net.KillFeedPayload(killer.getGameProfile().getName(),
                Factions.index(killer), victimName, victim instanceof Player vp3 ? Factions.index(vp3) : -1,
                wdef == null ? hit.weaponId() : wdef.name(), hit.headshot()));
        com.wardogs.net.RewardPayload.send(killer, hit.headshot() ? "HEADSHOT KILL  " + String.format("%.0f m", hit.distance()) : "KILL  " + String.format("%.0f m", hit.distance()), reward);
        HudPayload.sendTo(killer);
    }

    /** A downed player was revived: the killer keeps only a sliver of the kill's value. */
    public static void onVictimRevived(net.minecraft.server.MinecraftServer server, UUID victim) {
        Paid p = PAID.remove(victim);
        if (p == null) {
            return;
        }
        long back = Math.round(p.amount() * (1.0 - Balance.get().rewards().revivedKeep()));
        ServerPlayer killer = server.getPlayerList().getPlayer(p.killer());
        if (killer != null && back > 0) {
            Wallet.add(killer, -back);
            com.wardogs.net.RewardPayload.send(killer, "VICTIM REVIVED", -back);
            HudPayload.sendTo(killer);
        }
    }

    /** The progression branch a weapon trains: its role (assault, medic ...), or none for open items. */
    private static Role roleOf(String itemId) {
        var w = Balance.get().weapon(itemId);
        if (w != null) {
            Role r = Role.byKey(w.role());
            return r != null ? r : (w.category().equals("pistol") ? Role.ASSAULT : null);
        }
        return Balance.get().explosive(itemId) != null || itemId.equals("mortar_shell") ? Role.SUPPORT : null;
    }

    /** Clears per-round state (call when a round ends). */
    public static void reset() {
        PAID.clear();
        STREAK.clear();
        LAST_KILLED_BY.clear();
        REPEAT.clear();
    }
}
