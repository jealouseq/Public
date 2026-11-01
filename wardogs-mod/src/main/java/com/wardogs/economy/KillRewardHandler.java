package com.wardogs.economy;

import com.wardogs.WardogsMod;
import com.wardogs.balance.Balance;
import com.wardogs.combat.Ballistics;
import net.minecraft.ChatFormatting;
import net.minecraft.network.chat.Component;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.world.entity.player.Player;
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

    /** Zone multiplier provider; replaced by the Control Zone system in a later milestone. */
    public interface ZoneMultiplier {
        double at(net.minecraft.world.level.Level level, net.minecraft.world.phys.Vec3 pos);
    }

    public static volatile ZoneMultiplier ZONES = (level, pos) -> 1.0;

    private KillRewardHandler() {}

    @SubscribeEvent
    public static void onDeath(LivingDeathEvent e) {
        Ballistics.Hit hit = Ballistics.current();
        if (hit == null || !(e.getSource().getEntity() instanceof ServerPlayer killer)) {
            return;
        }
        var victim = e.getEntity();
        UUID victimId = victim.getUUID();
        UUID killerId = killer.getUUID();

        int streak = STREAK.merge(killerId, 1, Integer::sum);
        boolean revenge = victim instanceof Player && killerId.equals(LAST_KILLED_BY.get(victimId));
        String pair = killerId + ">" + victimId;
        int repeats = REPEAT.getOrDefault(pair, 0);
        REPEAT.put(pair, repeats + 1);
        if (victim instanceof Player) {
            STREAK.remove(victimId);
            LAST_KILLED_BY.put(victimId, killerId);
        }

        double zone = ZONES.at(killer.level(), victim.position());
        long reward = KillReward.compute(Balance.get().rewards(),
                new KillReward.Context(hit.headshot(), hit.distance(), zone, streak, repeats, revenge));
        Wallet.add(killer, reward);
        killer.displayClientMessage(Component.literal("+$" + reward + (hit.headshot() ? "  HEADSHOT" : "") + String.format("  %.0fm", hit.distance()))
                .withStyle(ChatFormatting.GOLD), false);
    }

    /** Clears per-round state (call when a round ends). */
    public static void reset() {
        STREAK.clear();
        LAST_KILLED_BY.clear();
        REPEAT.clear();
    }
}
