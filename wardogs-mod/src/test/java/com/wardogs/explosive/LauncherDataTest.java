package com.wardogs.explosive;

import com.wardogs.balance.Balance;
import com.wardogs.balance.LauncherDef;
import com.wardogs.balance.WeaponDef;
import org.junit.jupiter.api.Test;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertTrue;

class LauncherDataTest {
    private final Balance b = Balance.get();

    @Test
    void launchersAreWeaponsWithProjectileBehaviour() {
        for (String id : new String[]{"rpg7", "maaws", "mgl40", "verba"}) {
            WeaponDef w = b.weapon(id);
            LauncherDef l = b.launcher(id);
            assertTrue(w != null && l != null, id);
            assertEquals("launcher", w.category());
            assertTrue(l.speed() > 0 && l.blast() > 0, id);
        }
    }

    @Test
    void everyLauncherAndGunHasAnItemModel() throws Exception {
        for (WeaponDef w : b.weapons()) {
            if (w.id().equals("compound_bow")) {
                continue; // the bow keeps its flat icon
            }
            try (var in = getClass().getResourceAsStream("/assets/wardogs/models/item/" + w.id() + ".json")) {
                assertTrue(in != null, "missing item model for " + w.id());
                String json = new String(in.readAllBytes());
                assertTrue(json.contains("\"parent\"") || json.contains("\"elements\""), w.id() + " has neither a parent nor elements");
            }
        }
    }

    @Test
    void sourcedFiguresAreKept() {
        assertEquals(110.02, b.weapon("rpg7").chest(), 1e-9);
        assertEquals(101.01, b.weapon("maaws").chest(), 1e-9);
        assertEquals(100.02, b.weapon("mgl40").chest(), 1e-9);
        assertEquals(6, b.weapon("mgl40").magazine());
        assertEquals(1, b.weapon("rpg7").magazine());
    }

    @Test
    void onlyVerbaHomesAndAntiTankRocketsHitVehiclesHard() {
        assertTrue(b.launcher("verba").homing());
        assertTrue(!b.launcher("rpg7").homing());
        assertTrue(b.launcher("rpg7").vehicleMult() > b.launcher("mgl40").vehicleMult());
        assertEquals(LauncherDef.Kind.GRENADE, b.launcher("mgl40").kind());
    }

    @Test
    void mortarFlightTimeGrowsWithRange() {
        assertTrue(com.wardogs.build.Artillery.flightTicks(300) > com.wardogs.build.Artillery.flightTicks(50));
        assertTrue(com.wardogs.build.Artillery.flightTicks(0) > 0, "there is always a launch delay");
    }
}
