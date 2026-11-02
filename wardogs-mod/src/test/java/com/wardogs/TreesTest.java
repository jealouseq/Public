package com.wardogs;

import com.wardogs.nature.Trees;
import net.minecraft.core.BlockPos;
import org.junit.jupiter.api.Test;

import java.util.ArrayDeque;
import java.util.HashSet;
import java.util.Set;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertTrue;

class TreesTest {
    private static boolean isLog(int code) {
        return Trees.formOf(code) != Trees.LEAF;
    }

    @Test
    void everyKindGrowsAConnectedTrunkWithFoliage() {
        for (Trees.Kind kind : Trees.Kind.values()) {
            for (int i = 0; i < 40; i++) {
                int x = 100 + i * 7, z = -300 + i * 13;
                var plan = Trees.plan(kind, x, 60, z, i / 39.0);
                boolean bush = kind.name().startsWith("BUSH");
                assertTrue(plan.leaves > 4, kind + " has foliage");
                if (bush) {
                    assertEquals(0, plan.logs, "a bush has no trunk");
                    continue;
                }
                assertTrue(plan.logs >= 6, kind + " has a trunk");
                // every log block is reachable from the base through face-adjacent logs
                Set<Long> logs = new HashSet<>();
                plan.blocks.forEach((k, v) -> {
                    if (isLog(v)) {
                        logs.add(k);
                    }
                });
                long start = BlockPos.asLong(x, 61, z);
                assertTrue(logs.contains(start), kind + " trunk starts on the ground at " + x + "," + z);
                Set<Long> seen = new HashSet<>();
                ArrayDeque<Long> todo = new ArrayDeque<>();
                todo.add(start);
                seen.add(start);
                while (!todo.isEmpty()) {
                    BlockPos p = BlockPos.of(todo.poll());
                    for (var d : net.minecraft.core.Direction.values()) {
                        long n = p.relative(d).asLong();
                        if (logs.contains(n) && seen.add(n)) {
                            todo.add(n);
                        }
                    }
                }
                assertEquals(logs.size(), seen.size(), kind + " #" + i + " has a detached limb");
            }
        }
    }

    @Test
    void treesStayInsideTheReach() {
        for (Trees.Kind kind : Trees.Kind.values()) {
            for (int i = 0; i < 60; i++) {
                int x = 33 * i, z = 17 * i;
                var plan = Trees.plan(kind, x, 80, z, (i % 10) / 9.0);
                for (long k : plan.blocks.keySet()) {
                    assertTrue(Math.abs(BlockPos.getX(k) - x) <= 10 && Math.abs(BlockPos.getZ(k) - z) <= 10,
                            kind + " reaches too far: " + (BlockPos.getX(k) - x) + "," + (BlockPos.getZ(k) - z));
                    assertTrue(BlockPos.getY(k) > 80 - 1, kind + " grows under the ground");
                }
            }
        }
    }

    @Test
    void plansDependOnlyOnPosition() {
        var a = Trees.plan(Trees.Kind.OAK, 1234, 70, -987, 0.6);
        var b = Trees.plan(Trees.Kind.OAK, 1234, 70, -987, 0.6);
        assertEquals(a.blocks, b.blocks);
    }

    @Test
    void treesHaveDifferentHeights() {
        int lo = Integer.MAX_VALUE, hi = 0;
        for (Trees.Kind kind : new Trees.Kind[]{Trees.Kind.FIR, Trees.Kind.PINE, Trees.Kind.BIRCH, Trees.Kind.OAK}) {
            for (double s : new double[]{0, 1}) {
                var plan = Trees.plan(kind, 10, 0, 10, s);
                int max = 0;
                for (long k : plan.blocks.keySet()) {
                    max = Math.max(max, BlockPos.getY(k));
                }
                lo = Math.min(lo, max);
                hi = Math.max(hi, max);
            }
        }
        assertTrue(hi - lo >= 8, "tree heights vary: " + lo + ".." + hi);
    }
}
