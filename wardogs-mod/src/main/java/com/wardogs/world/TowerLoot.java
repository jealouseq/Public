package com.wardogs.world;

import net.minecraft.util.RandomSource;

import java.util.List;

/**
 * What a Bakurani tower chest holds. The marker data says "spawn 50%, respawns" with weights: FOB kit 0.5, SV98 0.8, M249 1.0,
 * standard medkit 0.8, M500 1.0, heavy repair drill 0.5 (the drill is not in the mod yet, so a wrench stands in for it).
 */
public final class TowerLoot {
    public record Entry(String itemId, double weight) {}

    public static final double SPAWN_CHANCE = 0.5;

    public static final List<Entry> TABLE = List.of(
            new Entry("fob_kit", 0.5), new Entry("sv98", 0.8), new Entry("m249", 1.0),
            new Entry("ifak", 0.8), new Entry("m500", 1.0), new Entry("wrench", 0.5));

    private TowerLoot() {}

    /** @return an item id, or {@code null} when the roll produces nothing (the 50% case). */
    public static String roll(RandomSource rnd) {
        if (rnd.nextDouble() >= SPAWN_CHANCE) {
            return null;
        }
        return pick(rnd.nextDouble());
    }

    /** Weighted pick for a uniform value in [0,1). */
    public static String pick(double u) {
        double total = 0;
        for (Entry e : TABLE) {
            total += e.weight();
        }
        double x = u * total;
        for (Entry e : TABLE) {
            x -= e.weight();
            if (x < 0) {
                return e.itemId();
            }
        }
        return TABLE.get(TABLE.size() - 1).itemId();
    }
}
