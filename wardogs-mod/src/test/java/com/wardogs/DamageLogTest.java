package com.wardogs;

import com.wardogs.combat.DamageLog;
import org.junit.jupiter.api.Test;

import java.util.UUID;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertTrue;

class DamageLogTest {
    @Test
    void newestFirstExpiringAndCapped() {
        UUID v = UUID.randomUUID();
        DamageLog.record(v, "A", "ak74", 30, false, 100);
        DamageLog.record(v, "B", "m4", 55, true, 200);
        var l = DamageLog.recent(v, 210);
        assertEquals(2, l.size());
        assertEquals("B", l.get(0).attacker());
        assertTrue(l.get(0).head());
        // the first hit is older than the window
        assertEquals(1, DamageLog.recent(v, 100 + DamageLog.WINDOW + 5).size());
        for (int i = 0; i < 20; i++) {
            DamageLog.record(v, "C" + i, "ak74", 1, false, 300);
        }
        assertTrue(DamageLog.recent(v, 300).size() <= 8, "the log is capped");
        DamageLog.clear(v);
        assertTrue(DamageLog.recent(v, 300).isEmpty());
    }
}
