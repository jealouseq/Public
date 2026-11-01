package com.wardogs.medic;

import com.wardogs.balance.RewardsDef;

/** Pure revive-payout formula. zoneLevel: 0 = open ground, 1 = Control Zone, 2 = Hot Zone. */
public final class ReviveReward {
    private ReviveReward() {}

    public static final int MAX_REPEAT_STEPS = 4;

    public static long compute(RewardsDef r, int zoneLevel, boolean defibrillator, int repeatCount) {
        long[] table = defibrillator ? r.defibBy() : r.reviveBy();
        double base = table[Math.max(0, Math.min(zoneLevel, table.length - 1))];
        int steps = Math.min(Math.max(repeatCount, 0), MAX_REPEAT_STEPS);
        return Math.round(base * Math.max(0.0, 1.0 - r.reviveRepeatPenalty() * steps));
    }
}
