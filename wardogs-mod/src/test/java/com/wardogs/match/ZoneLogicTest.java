package com.wardogs.match;

import com.wardogs.balance.Balance;
import com.wardogs.balance.RewardsDef;
import org.junit.jupiter.api.Test;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertTrue;

class ZoneLogicTest {
    private final RewardsDef r = Balance.get().rewards();

    @Test
    void leaderNeedsStrictMajority() {
        assertEquals(1, ZoneLogic.leader(new int[]{1, 3, 2}));
        assertEquals(-1, ZoneLogic.leader(new int[]{0, 0, 0}));
        assertEquals(-1, ZoneLogic.leader(new int[]{2, 2, 1}), "a tie for first scores nothing");
        assertEquals(2, ZoneLogic.leader(new int[]{0, 0, 1}));
    }

    @Test
    void circleContains() {
        assertTrue(ZoneLogic.inCircle(3, 4, 0, 0, 5));
        assertFalse(ZoneLogic.inCircle(3.1, 4, 0, 0, 5));
    }

    @Test
    void hotZonePayGrowsWithConsecutiveTicksUpToCap() {
        assertEquals(320, ZoneLogic.hotTickPay(r, 1));
        assertEquals(370, ZoneLogic.hotTickPay(r, 2));
        assertEquals(320 + 500, ZoneLogic.hotTickPay(r, 100));
    }

    @Test
    void hotZoneLeaderGetsDoublePoints() {
        assertEquals(2, ZoneLogic.pointsFor(0, 0));
        assertEquals(1, ZoneLogic.pointsFor(0, 1));
        assertEquals(1, ZoneLogic.pointsFor(0, -1));
    }

    @Test
    void rankingSortsByScore() {
        assertEquals(1, ZoneLogic.ranking(new int[]{3, 9, 5})[0]);
        assertEquals(2, ZoneLogic.ranking(new int[]{3, 9, 5})[1]);
        assertEquals(0, ZoneLogic.ranking(new int[]{3, 9, 5})[2]);
    }

    @Test
    void configuredRewardsMatchResearch() {
        assertEquals(250, r.zoneEnter());
        assertEquals(160, r.zoneControlTick());
        assertEquals(22, r.zoneTickSeconds());
        assertEquals(30, r.scoreSeconds());
        assertEquals(100, r.winScore());
        assertEquals(1000, r.placeBonus()[0]);
    }
}
