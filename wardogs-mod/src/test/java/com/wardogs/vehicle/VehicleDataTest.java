package com.wardogs.vehicle;

import com.wardogs.balance.Balance;
import com.wardogs.balance.VehicleDef;
import org.junit.jupiter.api.Test;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertNotNull;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertTrue;

class VehicleDataTest {
    private final Balance b = Balance.get();

    @Test
    void allVehiclesFromTheResearchArePresent() {
        assertEquals(25, b.vehicles().size());
        for (String id : new String[]{"bobcat", "kodiak", "wolf_110", "humvee", "ural", "l2a6", "t72b3", "flakpanzer_gepard", "sph2",
                "mh6", "ah6m", "ah6r", "uh1y", "z20_lakota", "havoc", "dune_buggy"}) {
            assertNotNull(b.vehicle(id), id);
        }
    }

    @Test
    void pricesAndUnlocksFollowTheSources() {
        assertEquals(500, b.vehicle("bobcat").price());
        assertEquals(2500, b.vehicle("kodiak").price());
        assertEquals(14000, b.vehicle("l2a6").price());
        assertEquals(16000, b.vehicle("t72b3").price());
        assertEquals(250_000, b.vehicle("t72b3").unlockCost());
        assertEquals("driver", b.vehicle("t72b3").unlockRole());
        assertEquals(20, b.vehicle("t72b3").unlockLevel());
        assertEquals(18000, b.vehicle("havoc").price());
        assertEquals(650_000, b.vehicle("havoc").unlockCost());
        assertEquals("pilot", b.vehicle("havoc").unlockRole());
        assertEquals(350_000, b.vehicle("sph2").unlockCost());
    }

    @Test
    void destructionRewardsFromTheSources() {
        assertEquals(7000, b.vehicle("l2a6").killReward());
        assertEquals(14000, b.vehicle("sph2").killReward());
        assertEquals(9000, b.vehicle("ah6m").killReward());
        assertEquals(9000, b.vehicle("havoc").killReward());
        assertEquals(300, b.vehicle("dune_buggy").killReward());
    }

    @Test
    void armedVariantsHaveGunsAndPlainOnesDoNot() {
        assertNotNull(b.vehicle("humvee_m249").gun());
        assertNotNull(b.vehicle("wolf_110_m2").gun());
        assertNotNull(b.vehicle("l2a6").gun());
        assertTrue(b.vehicle("l2a6").gun().blast() > 0, "a tank fires explosive shells");
        assertNull(b.vehicle("humvee").gun());
        assertNull(b.vehicle("bobcat").gun());
    }

    @Test
    void armorOrderingIsSensible() {
        VehicleDef car = b.vehicle("kodiak");
        VehicleDef tank = b.vehicle("l2a6");
        assertTrue(tank.smallArms() < car.smallArms(), "small arms hurt a tank less than a car");
        assertTrue(tank.hp() > car.hp());
        assertTrue(car.antiTank() > car.explosive(), "anti-tank weapons beat grenades against vehicles");
        assertTrue(b.vehicle("havoc").flying() && b.vehicle("mh6").flying() && !b.vehicle("humvee").flying());
    }

    @Test
    void transportPayMatchesTheSources() {
        assertEquals(500, b.transportPay(false));
        assertEquals(150, b.transportPay(true));
    }
}
