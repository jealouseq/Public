package com.wardogs.progress;

import com.wardogs.balance.Balance;
import com.wardogs.balance.ProgressionDef;
import org.junit.jupiter.api.Test;

import java.util.Set;

import static org.junit.jupiter.api.Assertions.assertEquals;

class ProgressionTest {
    private final ProgressionDef curve = Balance.get().progression();

    @Test
    void curvePassesThroughTheSeasonTwoCheckpoints() {
        assertEquals(25_000, curve.xpFor(10));
        assertEquals(54_750, curve.xpFor(15));
        assertEquals(318_200, curve.xpFor(30));
        assertEquals(1_150_000, curve.xpFor(50));
    }

    @Test
    void levelForIsTheInverseOfXpFor() {
        assertEquals(0, curve.levelFor(0));
        assertEquals(9, curve.levelFor(24_999));
        assertEquals(10, curve.levelFor(25_000));
        assertEquals(30, curve.levelFor(318_200));
        assertEquals(curve.maxLevel(), curve.levelFor(Long.MAX_VALUE / 2));
        for (int l = 1; l <= curve.maxLevel(); l++) {
            assertEquals(l, curve.levelFor(curve.xpFor(l)), "round trip for level " + l);
            assertEquals(l - 1, curve.levelFor(curve.xpFor(l) - 1), "one XP short of level " + l);
        }
    }

    @Test
    void unlockRuleStates() {
        // Support level 25 + $75 000 (AT mine)
        assertEquals(UnlockRule.State.LEVEL_TOO_LOW, UnlockRule.check("at_mine", Role.SUPPORT, 25, 75_000, Set.of(), r -> 24));
        assertEquals(UnlockRule.State.PAYABLE, UnlockRule.check("at_mine", Role.SUPPORT, 25, 75_000, Set.of(), r -> 25));
        assertEquals(UnlockRule.State.OWNED, UnlockRule.check("at_mine", Role.SUPPORT, 25, 75_000, Set.of("at_mine"), r -> 0));
        // level-gated but free once reached (AK74 at Assault 3)
        assertEquals(UnlockRule.State.OWNED, UnlockRule.check("ak74", Role.ASSAULT, 3, 0, Set.of(), r -> 3));
        assertEquals(UnlockRule.State.LEVEL_TOO_LOW, UnlockRule.check("ak74", Role.ASSAULT, 3, 0, Set.of(), r -> 2));
        // open to everyone
        assertEquals(UnlockRule.State.OWNED, UnlockRule.check("m67_frag", null, 0, 0, Set.of(), r -> 0));
    }

    @Test
    void progressDataAccumulatesAndRoundTripsThroughItsCodec() {
        ProgressData d = ProgressData.EMPTY.withXp(Role.MEDIC, 100).withXp(Role.MEDIC, 50).withUnlocked("defibrillator");
        assertEquals(150, d.xpOf(Role.MEDIC));
        assertEquals(0, d.xpOf(Role.RECON));
        var json = ProgressData.CODEC.encodeStart(com.mojang.serialization.JsonOps.INSTANCE, d).getOrThrow();
        ProgressData back = ProgressData.CODEC.parse(com.mojang.serialization.JsonOps.INSTANCE, json).getOrThrow();
        assertEquals(d, back);
    }
}
