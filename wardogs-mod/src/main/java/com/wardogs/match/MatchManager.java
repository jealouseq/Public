package com.wardogs.match;

import com.wardogs.WardogsMod;
import com.wardogs.balance.Balance;
import com.wardogs.balance.RewardsDef;
import com.wardogs.economy.KillRewardHandler;
import com.wardogs.economy.Wallet;
import com.wardogs.medic.Downed;
import com.wardogs.progress.Progression;
import com.wardogs.progress.Role;
import net.minecraft.ChatFormatting;
import net.minecraft.network.chat.Component;
import net.minecraft.server.level.ServerLevel;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.world.level.Level;
import net.minecraft.world.phys.Vec3;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.event.tick.LevelTickEvent;

import java.util.Map;
import java.util.UUID;
import java.util.concurrent.ConcurrentHashMap;

/** Control Zone / Hot Zone match loop: presence, money ticks, scoring, round end. */
@EventBusSubscriber(modid = WardogsMod.MOD_ID)
public final class MatchManager {
    /** Re-entering the zone only pays again after this many ticks (anti-farming). */
    private static final int ENTER_COOLDOWN = 20 * 60;
    /** The Hot Zone relocates this often. */
    private static final int HOT_MOVE_TICKS = 20 * 60 * 5;

    private static final Map<UUID, Boolean> INSIDE = new ConcurrentHashMap<>();
    private static final Map<UUID, Integer> HOT_STREAK = new ConcurrentHashMap<>();
    private static final Map<UUID, Long> ENTER_PAID_AT = new ConcurrentHashMap<>();

    private MatchManager() {}

    // --- queries -----------------------------------------------------------------------------

    /** Kill-reward multiplier at a position: Hot Zone x10, Control Zone x5, elsewhere x1. */
    public static double multiplierAt(Level level, Vec3 pos) {
        if (!(level instanceof ServerLevel sl)) {
            return 1.0;
        }
        MatchData d = MatchData.get(sl);
        if (!d.active || !d.isHere(sl)) {
            return 1.0;
        }
        RewardsDef r = Balance.get().rewards();
        if (d.inHot(pos.x, pos.z)) {
            return r.hotZoneMult();
        }
        return d.inControl(pos.x, pos.z) ? r.controlZoneMult() : 1.0;
    }

    /** 0 = open ground, 1 = Control Zone, 2 = Hot Zone (open ground when no match is running). */
    public static int zoneLevel(Level level, Vec3 pos) {
        if (!(level instanceof ServerLevel sl)) {
            return 0;
        }
        MatchData d = MatchData.get(sl);
        if (!d.active || !d.isHere(sl)) {
            return 0;
        }
        return d.inHot(pos.x, pos.z) ? 2 : d.inControl(pos.x, pos.z) ? 1 : 0;
    }

    // --- control -----------------------------------------------------------------------------

    public static void start(ServerLevel overworld) {
        MatchData d = MatchData.get(overworld);
        d.dim = overworld.dimension().location().toString();
        // Without PvP vanilla ignores all player-vs-player damage, which would make a match meaningless.
        overworld.getServer().setPvpAllowed(true);
        d.active = true;
        java.util.Arrays.fill(d.scores, 0);
        RewardsDef r = Balance.get().rewards();
        d.scoreTimer = r.scoreSeconds() * 20;
        d.payTimer = r.zoneTickSeconds() * 20;
        relocateHot(overworld, d);
        resetPresence();
        KillRewardHandler.reset();
        Downed.reset();
        d.setDirty();
        broadcast(overworld, Component.literal("Match started. First faction to " + r.winScore() + " points wins.").withStyle(ChatFormatting.GOLD));
    }

    public static void stop(ServerLevel overworld) {
        MatchData d = MatchData.get(overworld);
        d.active = false;
        d.setDirty();
        resetPresence();
        broadcast(overworld, Component.literal("Match stopped.").withStyle(ChatFormatting.GRAY));
    }

    public static void setZone(ServerLevel overworld, double x, double z, double radius) {
        MatchData d = MatchData.get(overworld);
        d.zoneX = x;
        d.zoneZ = z;
        d.zoneRadius = radius;
        d.hotRadius = Math.max(4, radius * 0.3);
        relocateHot(overworld, d);
        d.setDirty();
    }

    private static void resetPresence() {
        INSIDE.clear();
        HOT_STREAK.clear();
        ENTER_PAID_AT.clear();
    }

    private static void relocateHot(ServerLevel ow, MatchData d) {
        double max = Math.max(0, d.zoneRadius - d.hotRadius);
        double ang = ow.random.nextDouble() * Math.PI * 2;
        double dist = Math.sqrt(ow.random.nextDouble()) * max;
        d.hotX = d.zoneX + Math.cos(ang) * dist;
        d.hotZ = d.zoneZ + Math.sin(ang) * dist;
        d.hotTimer = HOT_MOVE_TICKS;
    }

    // --- loop --------------------------------------------------------------------------------

    @SubscribeEvent
    public static void onLevelTick(LevelTickEvent.Post e) {
        if (!(e.getLevel() instanceof ServerLevel ow)) {
            return;
        }
        MatchData d = MatchData.get(ow);
        if (!d.active || !d.isHere(ow)) {
            return;
        }
        long now = ow.getGameTime();
        d.scoreTimer--;
        d.payTimer--;
        d.hotTimer--;

        if (now % 20 == 0) {
            trackEntries(ow, d, now);
        }
        if (now % 600 == 0 && ow.dimension().equals(com.wardogs.world.BakuraniWorld.DIMENSION)) {
            com.wardogs.world.BakuraniSetup.tickTowers(ow);
        }
        if (d.payTimer <= 0) {
            d.payTimer = Balance.get().rewards().zoneTickSeconds() * 20;
            payTick(ow, d);
        }
        if (d.scoreTimer <= 0) {
            d.scoreTimer = Balance.get().rewards().scoreSeconds() * 20;
            scoreTick(ow, d);
        }
        if (d.hotTimer <= 0) {
            relocateHot(ow, d);
            broadcast(ow, Component.literal("The Hot Zone has moved.").withStyle(ChatFormatting.RED));
        }
        if (now % 100 == 0) {
            d.setDirty();
        }
    }

    private static boolean eligible(ServerPlayer p) {
        return p.isAlive() && !p.isSpectator() && Factions.index(p) >= 0 && !Downed.is(p);
    }

    private static void trackEntries(ServerLevel ow, MatchData d, long now) {
        long enterPay = Balance.get().rewards().zoneEnter();
        for (ServerPlayer p : ow.players()) {
            if (!eligible(p)) {
                INSIDE.remove(p.getUUID());
                continue;
            }
            boolean inside = d.inControl(p.getX(), p.getZ());
            boolean was = INSIDE.getOrDefault(p.getUUID(), false);
            INSIDE.put(p.getUUID(), inside);
            if (inside && !was) {
                long last = ENTER_PAID_AT.getOrDefault(p.getUUID(), Long.MIN_VALUE / 2);
                if (now - last >= ENTER_COOLDOWN) {
                    ENTER_PAID_AT.put(p.getUUID(), now);
                    Wallet.add(p, enterPay);
                    Progression.award(p, Role.WARDOG, enterPay);
                    p.displayClientMessage(Component.literal("+$" + enterPay + "  Control Zone").withStyle(ChatFormatting.GOLD), true);
                }
            }
        }
    }

    private static void payTick(ServerLevel ow, MatchData d) {
        RewardsDef r = Balance.get().rewards();
        for (ServerPlayer p : ow.players()) {
            if (!eligible(p) || !d.inControl(p.getX(), p.getZ())) {
                HOT_STREAK.remove(p.getUUID());
                continue;
            }
            long pay;
            if (d.inHot(p.getX(), p.getZ())) {
                int streak = HOT_STREAK.merge(p.getUUID(), 1, Integer::sum);
                pay = ZoneLogic.hotTickPay(r, streak);
            } else {
                HOT_STREAK.remove(p.getUUID());
                pay = r.zoneControlTick();
            }
            Wallet.add(p, pay);
            Progression.award(p, Role.WARDOG, pay);
            p.displayClientMessage(Component.literal("+$" + pay + "  zone").withStyle(ChatFormatting.GOLD), true);
        }
    }

    /** One scoring tick: the faction leading the Control Zone gets a point (two if it also leads the Hot Zone). */
    public static void scoreTick(ServerLevel ow, MatchData d) {
        int n = Faction.values().length;
        int[] control = new int[n];
        int[] hot = new int[n];
        for (ServerPlayer p : ow.players()) {
            int f = Factions.index(p);
            if (!eligible(p) || !d.inControl(p.getX(), p.getZ())) {
                continue;
            }
            control[f]++;
            if (d.inHot(p.getX(), p.getZ())) {
                hot[f]++;
            }
        }
        int leader = ZoneLogic.leader(control);
        if (leader < 0) {
            return;
        }
        int pts = ZoneLogic.pointsFor(leader, ZoneLogic.leader(hot));
        d.scores[leader] += pts;
        d.setDirty();
        Faction f = Faction.byIndex(leader);
        broadcast(ow, Component.literal(f.display + " +" + pts + "  (" + d.scores[leader] + "/" + Balance.get().rewards().winScore() + ")").withStyle(f.color));
        if (d.scores[leader] >= Balance.get().rewards().winScore()) {
            endRound(ow, d);
        }
    }

    private static void endRound(ServerLevel ow, MatchData d) {
        RewardsDef r = Balance.get().rewards();
        int[] rank = ZoneLogic.ranking(d.scores);
        Faction winner = Faction.byIndex(rank[0]);
        broadcast(ow, Component.literal(winner.display + " wins the round!").withStyle(winner.color, ChatFormatting.BOLD));
        for (ServerPlayer p : ow.getServer().getPlayerList().getPlayers()) {
            int f = Factions.index(p);
            for (int place = 0; place < rank.length && place < r.placeBonus().length; place++) {
                if (rank[place] == f) {
                    Wallet.add(p, r.placeBonus()[place]);
                    p.sendSystemMessage(Component.literal("Place " + (place + 1) + ": +$" + r.placeBonus()[place]).withStyle(ChatFormatting.GOLD));
                }
            }
        }
        d.active = false;
        resetPresence();
        KillRewardHandler.reset();
        d.setDirty();
    }

    private static void broadcast(ServerLevel ow, Component c) {
        ow.getServer().getPlayerList().broadcastSystemMessage(c, false);
    }

    public static String status(ServerLevel ow) {
        MatchData d = MatchData.get(ow);
        StringBuilder sb = new StringBuilder(d.active ? "ACTIVE" : "idle");
        sb.append(String.format("  zone (%.0f, %.0f) r=%.0f  hot (%.0f, %.0f) r=%.0f  scores:", d.zoneX, d.zoneZ, d.zoneRadius, d.hotX, d.hotZ, d.hotRadius));
        for (Faction f : Faction.values()) {
            sb.append(' ').append(f.display).append('=').append(d.scores[f.ordinal()]);
        }
        return sb.toString();
    }
}
