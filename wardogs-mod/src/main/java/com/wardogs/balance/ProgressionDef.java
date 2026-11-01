package com.wardogs.balance;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;

/** XP curve: piecewise-linear through (level, total XP) checkpoints. */
public record ProgressionDef(double xpPerDollar, int[] levels, long[] xp) {
    public static ProgressionDef parse(JsonObject o) {
        JsonArray c = o.getAsJsonArray("checkpoints");
        int[] lv = new int[c.size()];
        long[] xp = new long[c.size()];
        for (int i = 0; i < c.size(); i++) {
            lv[i] = c.get(i).getAsJsonArray().get(0).getAsInt();
            xp[i] = c.get(i).getAsJsonArray().get(1).getAsLong();
        }
        return new ProgressionDef(o.get("xp_per_dollar").getAsDouble(), lv, xp);
    }

    public int maxLevel() {
        return levels[levels.length - 1];
    }

    /** Total XP needed to reach {@code level}. */
    public long xpFor(int level) {
        if (level <= 0) {
            return 0;
        }
        if (level >= maxLevel()) {
            return xp[xp.length - 1];
        }
        for (int i = 1; i < levels.length; i++) {
            if (level <= levels[i]) {
                double t = (level - levels[i - 1]) / (double) (levels[i] - levels[i - 1]);
                return Math.round(xp[i - 1] + t * (xp[i] - xp[i - 1]));
            }
        }
        return xp[xp.length - 1];
    }

    /** Highest level whose threshold is at most {@code totalXp}. */
    public int levelFor(long totalXp) {
        int lo = 0, hi = maxLevel();
        while (lo < hi) {
            int mid = (lo + hi + 1) >>> 1;
            if (xpFor(mid) <= totalXp) {
                lo = mid;
            } else {
                hi = mid - 1;
            }
        }
        return lo;
    }
}
