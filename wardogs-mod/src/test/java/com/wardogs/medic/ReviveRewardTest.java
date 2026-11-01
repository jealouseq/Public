package com.wardogs.medic;

import com.wardogs.balance.Balance;
import com.wardogs.balance.RewardsDef;
import org.junit.jupiter.api.Test;

import static org.junit.jupiter.api.Assertions.assertEquals;

class ReviveRewardTest {
    private final RewardsDef r = Balance.get().rewards();

    @Test
    void handRevivePaysByZone() {
        assertEquals(400, ReviveReward.compute(r, 0, false, 0));
        assertEquals(800, ReviveReward.compute(r, 1, false, 0));
        assertEquals(1600, ReviveReward.compute(r, 2, false, 0));
    }

    @Test
    void defibrillatorPaysMore() {
        assertEquals(600, ReviveReward.compute(r, 0, true, 0));
        assertEquals(1200, ReviveReward.compute(r, 1, true, 0));
        assertEquals(2400, ReviveReward.compute(r, 2, true, 0));
    }

    @Test
    void repeatRevivesOfTheSameMatePayAQuarterLessEachTime() {
        assertEquals(300, ReviveReward.compute(r, 0, false, 1));
        assertEquals(200, ReviveReward.compute(r, 0, false, 2));
        assertEquals(0, ReviveReward.compute(r, 0, false, 4));
        assertEquals(0, ReviveReward.compute(r, 0, false, 50));
    }

    @Test
    void medicalCatalogMatchesResearchPrices() {
        assertEquals(425, Balance.get().medical("field_resuscitator").price());
        assertEquals(1600, Balance.get().medical("defibrillator").price());
        assertEquals(1800, Balance.get().medical("medical_bag").price());
        assertEquals(150, Balance.get().medical("battery").price());
    }
}
