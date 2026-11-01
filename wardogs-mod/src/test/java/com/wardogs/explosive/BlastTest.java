package com.wardogs.explosive;

import com.wardogs.balance.Balance;
import com.wardogs.balance.ExplosiveDef;
import org.junit.jupiter.api.Test;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertTrue;

class BlastTest {
    @Test
    void falloffIsLinearAndZeroAtTheEdge() {
        assertEquals(150.0, Blast.falloff(150, 6, 0), 1e-9);
        assertEquals(75.0, Blast.falloff(150, 6, 3), 1e-9);
        assertEquals(0.0, Blast.falloff(150, 6, 6), 1e-9);
        assertEquals(0.0, Blast.falloff(150, 6, 20), 1e-9);
    }

    @Test
    void catalogMatchesResearchPrices() {
        Balance b = Balance.get();
        assertEquals(200, b.explosive("m67_frag").price());
        assertEquals(250, b.explosive("c4").price());
        assertEquals(300, b.explosive("ied").price());
        assertEquals(650, b.explosive("at_mine").price());
        assertEquals(900, b.explosive("claymore").price());
        assertEquals(100, b.explosive("smoke_white").price());
        assertEquals(50, b.explosive("signal_hostile").price());
    }

    @Test
    void throwablesAndPlaceablesAreClassifiedCorrectly() {
        Balance b = Balance.get();
        assertTrue(b.explosive("m67_frag").throwable());
        assertTrue(b.explosive("smoke_black").throwable());
        assertTrue(b.explosive("claymore").placeable());
        assertTrue(!b.explosive("remote_detonator").placeable() && !b.explosive("remote_detonator").throwable());
        assertEquals(ExplosiveDef.Kind.AT_MINE, b.explosive("at_mine").kind());
    }

    @Test
    void aFragKillsAtPointBlankButNotAtTheEdge() {
        ExplosiveDef frag = Balance.get().explosive("m67_frag");
        assertTrue(Blast.falloff(frag.damage(), frag.radius(), 0.5) >= Balance.get().playerHealth(), "a grenade at your feet must be lethal");
        assertTrue(Blast.falloff(frag.damage(), frag.radius(), frag.radius() - 0.5) < 20, "the edge of the radius only scratches");
    }
}
