package com.wardogs.economy;

import com.wardogs.balance.Balance;
import com.wardogs.balance.RewardsDef;
import org.junit.jupiter.api.Test;

import static org.junit.jupiter.api.Assertions.assertEquals;

class KillRewardTest {
    private final RewardsDef r = Balance.get().rewards();

    private static KillReward.Context ctx(boolean head, double dist, double zone, int streak, int repeats, boolean revenge) {
        return new KillReward.Context(head, dist, zone, streak, repeats, revenge);
    }

    @Test
    void hotZoneKillFrom400mIs6000() {
        // The worked example from the community guide: rifle kill into the Hot Zone from 400 m.
        assertEquals(6000, KillReward.compute(r, ctx(false, 400, 10, 1, 0, false)));
    }

    @Test
    void openFieldCloseKillIsBase() {
        assertEquals(300, KillReward.compute(r, ctx(false, 20, 1, 1, 0, false)));
        assertEquals(350, KillReward.compute(r, ctx(true, 20, 1, 1, 0, false)));
    }

    @Test
    void distanceTiers() {
        assertEquals(1.0, KillReward.distanceMult(r, 299.9));
        assertEquals(1.5, KillReward.distanceMult(r, 300));
        assertEquals(2.5, KillReward.distanceMult(r, 599));
        assertEquals(4.0, KillReward.distanceMult(r, 1200));
    }

    @Test
    void controlZoneMultiplier() {
        assertEquals(1500, KillReward.compute(r, ctx(false, 10, 5, 1, 0, false)));
    }

    @Test
    void streakBonusIsCapped() {
        assertEquals(350, KillReward.compute(r, ctx(false, 10, 1, 2, 0, false)));
        assertEquals(550, KillReward.compute(r, ctx(false, 10, 1, 40, 0, false)));
    }

    @Test
    void repeatVictimLosesAQuarterEachTimeUpToFourSteps() {
        assertEquals(225, KillReward.compute(r, ctx(false, 10, 1, 1, 1, false)));
        assertEquals(0, KillReward.compute(r, ctx(false, 10, 1, 1, 4, false)));
        assertEquals(0, KillReward.compute(r, ctx(false, 10, 1, 1, 9, false)));
    }

    @Test
    void revengeDoubles() {
        assertEquals(600, KillReward.compute(r, ctx(false, 10, 1, 1, 0, true)));
    }
}
