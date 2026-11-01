package com.wardogs.economy;

import com.wardogs.balance.RewardsDef;

/** Pure kill-reward formula (no Minecraft types, unit-testable). */
public final class KillReward {
    private KillReward() {}

    public record Context(boolean headshot, double distance, double zoneMult, int streak, int repeatCount, boolean revenge) {}

    public static double distanceMult(RewardsDef r, double distance) {
        double m = 1.0;
        for (double[] tier : r.distanceMult()) {
            if (distance >= tier[0]) {
                m = tier[1];
            }
        }
        return m;
    }

    /**
     * reward = (headshot ? 350 : 300) x zone x distance [x revenge] x repeat-victim factor
     *          + min(streakBonus x (streak-1), cap)
     * Example from the research: rifle kill into the Hot Zone from 400 m = 300 x 10 x 2 = 6000.
     */
    public static long compute(RewardsDef r, Context c) {
        double v = c.headshot() ? r.killHeadshot() : r.killBase();
        v *= c.zoneMult();
        v *= distanceMult(r, c.distance());
        if (c.revenge()) {
            v *= r.revengeMult();
        }
        int steps = Math.min(Math.max(c.repeatCount(), 0), r.repeatSteps());
        v *= Math.max(0.0, 1.0 - r.repeatPenalty() * steps);
        v += Math.min(r.streakBonus() * Math.max(c.streak() - 1, 0), r.streakCap());
        return Math.round(v);
    }
}
