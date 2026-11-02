package com.wardogs.match;

import net.minecraft.server.level.ServerPlayer;

import java.util.ArrayList;
import java.util.Comparator;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.ConcurrentHashMap;

/** Per-player numbers for the scoreboard of the running match: kills, deaths and money earned. In memory only; reset at match start. */
public final class MatchStats {
    public record Row(String name, int faction, int kills, int deaths, long earned) {}

    private static final class Stat {
        String name;
        int faction;
        int kills, deaths;
        long earned;
    }

    private static final Map<UUID, Stat> STATS = new ConcurrentHashMap<>();

    private MatchStats() {}

    private static Stat of(ServerPlayer p) {
        Stat s = STATS.computeIfAbsent(p.getUUID(), k -> new Stat());
        s.name = p.getGameProfile().getName();
        s.faction = Factions.index(p);
        return s;
    }

    public static void kill(ServerPlayer killer, long reward) {
        Stat s = of(killer);
        s.kills++;
        s.earned += reward;
    }

    public static void death(ServerPlayer victim) {
        of(victim).deaths++;
    }

    public static void reset() {
        STATS.clear();
    }

    /** Like {@link #rows()} but every online player with a faction is listed, even before their first kill or death. */
    public static List<Row> rows(java.util.Collection<ServerPlayer> online) {
        for (ServerPlayer p : online) {
            if (Factions.index(p) >= 0) {
                of(p);
            }
        }
        return rows();
    }

    /** Rows sorted by faction, then by kills (highest first), then by money earned. */
    public static List<Row> rows() {
        List<Row> out = new ArrayList<>();
        for (Stat s : STATS.values()) {
            out.add(new Row(s.name, s.faction, s.kills, s.deaths, s.earned));
        }
        out.sort(Comparator.comparingInt(Row::faction).thenComparing(Comparator.comparingInt(Row::kills).reversed())
                .thenComparing(Comparator.comparingLong(Row::earned).reversed()));
        return out;
    }
}
