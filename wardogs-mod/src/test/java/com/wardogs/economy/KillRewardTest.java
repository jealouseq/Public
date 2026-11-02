package com.wardogs.economy;

import com.wardogs.balance.Balance;
import com.wardogs.balance.RewardsDef;
import org.junit.jupiter.api.Test;

import static org.junit.jupiter.api.Assertions.assertEquals;

class KillRewardTest {
    private final RewardsDef r = Balance.get().rewards();

    private static KillReward.Context ctx(double base, boolean head, boolean targetOnly, boolean sniper, double dist,
                                          double shooterZone, double targetZone, int streak, int repeats, boolean revenge, boolean shutdown) {
        return new KillReward.Context(base, head, targetOnly, sniper, dist, shooterZone, targetZone, streak, repeats, revenge, shutdown);
    }

    private static KillReward.Context rifle(boolean head, double shooterZone, double targetZone) {
        return ctx(400, head, false, false, 20, shooterZone, targetZone, 1, 0, false, false);
    }

    @Test
    void openFieldKillIsTheClassBase() {
        assertEquals(400, KillReward.compute(r, rifle(false, 1, 1)));
        assertEquals(450, KillReward.compute(r, rifle(true, 1, 1)));
        assertEquals(300, KillReward.compute(r, ctx(300, false, false, false, 20, 1, 1, 1, 0, false, false)));
    }

    @Test
    void zonesCountHalfForTheShooterAndHalfForTheVictim() {
        // Season 2 calculator: shooter outside + victim in the Hot Zone = (1 + 10) / 2 = 5.5x
        assertEquals(Math.round(400 * 5.5), KillReward.compute(r, rifle(false, 1, 10)));
        assertEquals(4000, KillReward.compute(r, rifle(false, 10, 10)), "both in the Hot Zone");
        assertEquals(2000, KillReward.compute(r, rifle(false, 5, 5)), "both in the Control Zone");
        assertEquals(Math.round(400 * 3.0), KillReward.compute(r, rifle(false, 1, 5)));
    }

    @Test
    void sniperExplosiveAndArtilleryKillsWeighTheVictimAlone() {
        assertEquals(6000, KillReward.compute(r, ctx(600, false, true, true, 20, 1, 10, 1, 0, false, false)), "sniper in the Hot Zone from close range");
        assertEquals(600 * 10 * 2, KillReward.compute(r, ctx(600, false, true, true, 400, 1, 10, 1, 0, false, false)), "400 m doubles it");
        assertEquals(300 * 5, KillReward.compute(r, ctx(300, false, true, false, 10, 10, 5, 1, 0, false, false)), "an explosive ignores where the shooter stands");
    }

    @Test
    void distanceTiersOnlyApplyToSniperKills() {
        assertEquals(1.0, KillReward.distanceMult(r, 299.9));
        assertEquals(1.5, KillReward.distanceMult(r, 300));
        assertEquals(4.0, KillReward.distanceMult(r, 1200));
        assertEquals(400, KillReward.compute(r, ctx(400, false, false, false, 800, 1, 1, 1, 0, false, false)), "a long rifle kill is not scaled");
    }

    @Test
    void streakBonusIsFiftyPerKillUpToFive() {
        assertEquals(400, KillReward.compute(r, ctx(400, false, false, false, 5, 1, 1, 1, 0, false, false)));
        assertEquals(450, KillReward.compute(r, ctx(400, false, false, false, 5, 1, 1, 2, 0, false, false)));
        assertEquals(650, KillReward.compute(r, ctx(400, false, false, false, 5, 1, 1, 6, 0, false, false)));
        assertEquals(650, KillReward.compute(r, ctx(400, false, false, false, 5, 1, 1, 40, 0, false, false)), "capped at five bonus kills");
    }

    @Test
    void repeatVictimLosesAboutAQuarterEachTimeFourDeep() {
        assertEquals(Math.round(400 * 0.76), KillReward.compute(r, ctx(400, false, false, false, 5, 1, 1, 1, 1, false, false)));
        assertEquals(Math.round(400 * 0.04), KillReward.compute(r, ctx(400, false, false, false, 5, 1, 1, 1, 4, false, false)));
        assertEquals(Math.round(400 * 0.04), KillReward.compute(r, ctx(400, false, false, false, 5, 1, 1, 1, 9, false, false)));
    }

    @Test
    void revengeDoublesAndShutdownAddsHalf() {
        assertEquals(800, KillReward.compute(r, ctx(400, false, false, false, 5, 1, 1, 1, 0, true, false)));
        assertEquals(600, KillReward.compute(r, ctx(400, false, false, false, 5, 1, 1, 1, 0, false, true)));
    }

    @Test
    void classesFollowTheWeaponCategory() {
        assertEquals("assault", KillReward.classify("m4"));
        assertEquals("assault", KillReward.classify("m1911"));
        assertEquals("small", KillReward.classify("mp5"));
        assertEquals("small", KillReward.classify("m249"));
        assertEquals("sniper", KillReward.classify("sv98"));
        assertEquals("sniper", KillReward.classify("svd"));
        assertEquals("explosive", KillReward.classify("rpg7"));
        assertEquals("explosive", KillReward.classify("m67_frag"));
        assertEquals("artillery", KillReward.classify("mortar_shell"));
        assertEquals("tank", KillReward.classify("veh_shell"));
        assertTrue(KillReward.targetOnly("sniper") && !KillReward.targetOnly("assault"));
    }

    private static void assertTrue(boolean b) {
        org.junit.jupiter.api.Assertions.assertTrue(b);
    }
}
