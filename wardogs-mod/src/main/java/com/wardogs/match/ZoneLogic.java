package com.wardogs.match;

import com.wardogs.balance.RewardsDef;

/** Pure zone/score rules (no Minecraft types, unit-testable). */
public final class ZoneLogic {
    private ZoneLogic() {}

    /** @return the faction with strictly the most players, or -1 when nobody is inside or the top is tied. */
    public static int leader(int[] counts) {
        int best = -1;
        int bestCount = 0;
        boolean tie = false;
        for (int i = 0; i < counts.length; i++) {
            if (counts[i] > bestCount) {
                best = i;
                bestCount = counts[i];
                tie = false;
            } else if (counts[i] == bestCount && bestCount > 0) {
                tie = true;
            }
        }
        return tie ? -1 : best;
    }

    public static boolean inCircle(double px, double pz, double cx, double cz, double radius) {
        double dx = px - cx, dz = pz - cz;
        return dx * dx + dz * dz <= radius * radius;
    }

    /** Hot Zone pay per tick: base, plus a bonus that grows with each consecutive tick up to a cap. */
    public static long hotTickPay(RewardsDef r, int consecutive) {
        long bonus = Math.min(r.hotBonusStep() * Math.max(consecutive - 1, 0), r.hotBonusCap());
        return r.zoneHotTick() + bonus;
    }

    /** Points for a scoring tick: 2 when the leading faction also leads the Hot Zone, else 1. */
    public static int pointsFor(int controlLeader, int hotLeader) {
        return controlLeader >= 0 && controlLeader == hotLeader ? 2 : 1;
    }

    /** Faction indexes sorted by score, best first (ties keep faction order). */
    public static int[] ranking(int[] scores) {
        Integer[] idx = new Integer[scores.length];
        for (int i = 0; i < idx.length; i++) {
            idx[i] = i;
        }
        java.util.Arrays.sort(idx, (a, b) -> Integer.compare(scores[b], scores[a]));
        int[] out = new int[idx.length];
        for (int i = 0; i < out.length; i++) {
            out[i] = idx[i];
        }
        return out;
    }
}
