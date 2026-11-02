package com.wardogs.world;

import net.minecraft.util.RandomSource;
import org.junit.jupiter.api.Test;

import java.util.HashMap;
import java.util.Map;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertTrue;

class TowerLootTest {
    @Test
    void tableMatchesTheMarkerData() {
        Map<String, Double> w = new HashMap<>();
        TowerLoot.TABLE.forEach(e -> w.put(e.itemId(), e.weight()));
        assertEquals(0.5, w.get("fob_kit"));
        assertEquals(0.8, w.get("sv98"));
        assertEquals(1.0, w.get("m249"));
        assertEquals(0.8, w.get("ifak"));
        assertEquals(1.0, w.get("m500"));
        assertEquals(0.5, TowerLoot.SPAWN_CHANCE);
    }

    @Test
    void pickFollowsTheWeights() {
        double total = TowerLoot.TABLE.stream().mapToDouble(TowerLoot.Entry::weight).sum();
        assertEquals("fob_kit", TowerLoot.pick(0.0));
        assertEquals("wrench", TowerLoot.pick(0.9999));
        // every item appears with probability weight/total (checked on a fine grid)
        Map<String, Integer> n = new HashMap<>();
        int steps = 10_000;
        for (int i = 0; i < steps; i++) {
            n.merge(TowerLoot.pick((i + 0.5) / steps), 1, Integer::sum);
        }
        for (TowerLoot.Entry e : TowerLoot.TABLE) {
            assertEquals(e.weight() / total, n.get(e.itemId()) / (double) steps, 0.002, e.itemId());
        }
    }

    @Test
    void aboutHalfOfTheRollsProduceNothing() {
        RandomSource r = RandomSource.create(1234);
        int items = 0, trials = 20_000;
        for (int i = 0; i < trials; i++) {
            if (TowerLoot.roll(r) != null) {
                items++;
            }
        }
        double share = items / (double) trials;
        assertTrue(Math.abs(share - 0.5) < 0.02, "share of rolls that spawn loot: " + share);
    }
}
