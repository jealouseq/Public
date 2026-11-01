package com.wardogs.balance;

import org.junit.jupiter.api.Test;

import static org.junit.jupiter.api.Assertions.*;

class BalanceTest {
    @Test
    void loadsAllWeapons() {
        assertEquals(34, Balance.get().weapons().size());
    }

    @Test
    void m4MatchesMeasuredValues() {
        WeaponDef m4 = Balance.get().weapon("m4");
        assertEquals(30.82, m4.chest(), 1e-9);
        assertEquals(65.82, m4.head(), 1e-9);
        assertEquals(786, m4.rpm(), 1e-9);
        assertEquals(2800, m4.price());
        assertEquals(1200.0 / 786.0, m4.shotIntervalTicks(), 1e-9);
    }

    @Test
    void everyWeaponIsSane() {
        for (WeaponDef w : Balance.get().weapons()) {
            assertTrue(w.chest() > 0, w.id());
            assertTrue(w.head() >= w.chest(), w.id() + " head must not be weaker than chest");
            assertTrue(w.rpm() > 0 && w.rpm() <= 1200, w.id() + " rpm must allow <=1 shot per tick");
            assertTrue(w.magazine() > 0, w.id());
            assertTrue(w.range() > 0, w.id());
        }
    }

    @Test
    void damageScalesToMinecraftHealth() {
        // 100 WARDOGS health == 20 Minecraft health points
        assertEquals(6.164f, Balance.get().toMinecraftDamage(30.82), 1e-3f);
        assertEquals(20f, Balance.get().toMinecraftDamage(100), 1e-6f);
    }
}
