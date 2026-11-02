package com.wardogs.world;

import com.wardogs.decor.DecorBlocks;
import net.minecraft.core.BlockPos;
import net.minecraft.core.Direction;
import net.minecraft.world.level.block.Blocks;
import net.minecraft.world.level.block.CropBlock;
import net.minecraft.world.level.block.FenceBlock;
import net.minecraft.world.level.block.state.BlockState;
import net.minecraft.world.level.chunk.ChunkAccess;
import net.minecraft.world.level.levelgen.Heightmap;

import java.util.ArrayList;
import java.util.HashSet;
import java.util.List;
import java.util.Set;

/**
 * Everything that makes Bakurani look lived in, placed while a chunk is generated and deterministic per position: yards around houses
 * (benches, mailboxes, bins, firewood, gardens, fences with gates), industrial yards around big buildings, sheds, street lamps along
 * surfaced roads, benches in town, wooden power poles along dirt tracks, boulders and undergrowth. Props only go on free ground
 * (air above a solid block, off the roads and out of the water) and never cross a chunk border: each chunk places only what falls in it.
 */
public final class Decorations {
    private Decorations() {}

    // --- helpers -----------------------------------------------------------------------------

    static double rnd(long a, long b, int salt) {
        long h = a * 73856093L ^ b * 19349663L ^ salt * 83492791L ^ 0x9E3779B97F4A7C15L;
        h ^= h >>> 33; h *= 0xFF51AFD7ED558CCDL; h ^= h >>> 33; h *= 0xC4CEB9FE1A85EC53L; h ^= h >>> 33;
        return (h & 0xFFFFFFL) / (double) 0x1000000;
    }

    private static double rndB(BakuraniTerrain.Building b, int salt) {
        return rnd(Math.round(b.x() * 8), Math.round(b.z() * 8), salt);
    }

    /** World position of local frame point (u along the width, v along the depth; the door side is +v). */
    static double[] toWorld(BakuraniTerrain.Building b, double u, double v) {
        double th = Math.toRadians(b.angle()), c = Math.cos(th), s = Math.sin(th);
        return new double[]{b.x() + u * c - v * s, b.z() + u * s + v * c};
    }

    private static final class Ctx {
        final ChunkAccess chunk;
        final BakuraniTerrain t;
        final Heightmap surface;
        final int baseX, baseZ;
        final BlockPos.MutableBlockPos pos = new BlockPos.MutableBlockPos();

        Ctx(ChunkAccess chunk, BakuraniTerrain t, Heightmap surface) {
            this.chunk = chunk; this.t = t; this.surface = surface;
            this.baseX = chunk.getPos().getMinBlockX(); this.baseZ = chunk.getPos().getMinBlockZ();
        }

        boolean in(int x, int z) {
            return x >= baseX && x < baseX + 16 && z >= baseZ && z < baseZ + 16;
        }

        BlockState get(int x, int y, int z) {
            return chunk.getBlockState(pos.set(x, y, z));
        }

        /** y of the first free block above the ground at a column (or Integer.MIN_VALUE when the column is not free ground). */
        int freeY(int x, int z) {
            if (!in(x, z) || !BakuraniTerrain.inside(x + 0.5, z + 0.5)) {
                return Integer.MIN_VALUE;
            }
            int cov = t.cover(x + 0.5, z + 0.5);
            if (cov == BakuraniTerrain.COVER_WATER || t.roadClass(x + 0.5, z + 0.5) > 0) {
                return Integer.MIN_VALUE;
            }
            // the ocean-floor heightmap ignores trees and water: y is the first block above the ground
            int y = chunk.getHeight(Heightmap.Types.OCEAN_FLOOR_WG, x & 15, z & 15);
            BlockState at = get(x, y, z);
            if (!(at.isAir() || at.canBeReplaced() && !at.is(Blocks.WATER))) {
                return Integer.MIN_VALUE;
            }
            BlockState below = get(x, y - 1, z);
            if (below.isAir() || below.is(Blocks.WATER) || below.is(net.minecraft.tags.BlockTags.LEAVES) || below.is(net.minecraft.tags.BlockTags.LOGS)) {
                return Integer.MIN_VALUE;
            }
            return y;
        }

        void set(int x, int y, int z, BlockState s) {
            if (!in(x, z) || y <= 1 || y >= BakuraniChunkGenerator.HEIGHT) {
                return;
            }
            chunk.setBlockState(pos.set(x, y, z), s, false);
            surface.update(x & 15, y, z & 15, s);
        }

        /** Places a block on free ground at the column; {@code up} raises it (stacking). */
        boolean onGround(int x, int z, BlockState s, int up) {
            int y = freeY(x, z);
            if (y == Integer.MIN_VALUE) {
                return false;
            }
            set(x, y + up, z, s);
            return true;
        }
    }

    // --- entry point ---------------------------------------------------------------------------

    public static void place(ChunkAccess chunk, BakuraniTerrain t, Heightmap surface) {
        Ctx c = new Ctx(chunk, t, surface);
        for (BakuraniTerrain.Building b : t.buildingsIn(c.baseX - 16, c.baseZ - 16, c.baseX + 31, c.baseZ + 31)) {
            if (b.kind() == 2) {
                continue;
            }
            if (b.w() >= 16 || b.floors() >= 3) {
                industrialYard(c, b);
            } else {
                houseYard(c, b);
            }
        }
        roadProps(c);
        wilderness(c);
    }

    /**
     * Small sheds beside about half of the houses: a 5 x 4 hut (kind 2) in the house's own frame, dropped when it would touch another
     * building, a road or the water. Returns the sheds to add to the building list.
     */
    public static List<BakuraniTerrain.Building> sheds(BakuraniTerrain t, List<BakuraniTerrain.Building> houses) {
        List<BakuraniTerrain.Building> out = new ArrayList<>();
        for (BakuraniTerrain.Building b : houses) {
            if (b.kind() == 2 || b.w() >= 16 || b.floors() >= 3 || rndB(b, 41) >= 0.5) {
                continue;
            }
            double side = rndB(b, 42) < 0.5 ? -1 : 1;
            double[] w = toWorld(b, side * (b.w() / 2.0 + 7), -b.d() / 2.0 + 3);
            BakuraniTerrain.Building shed = new BakuraniTerrain.Building((float) w[0], (float) w[1], 5, 4, 1, 2, b.angle());
            boolean clash = t.roadClass(w[0], w[1]) > 0 || t.cover(w[0], w[1]) == BakuraniTerrain.COVER_WATER || !BakuraniTerrain.inside(w[0], w[1]);
            for (BakuraniTerrain.Building o : t.buildingsIn((int) w[0] - 12, (int) w[1] - 12, (int) w[0] + 12, (int) w[1] + 12)) {
                if (Math.hypot(o.x() - w[0], o.z() - w[1]) < o.radius() + 5) {
                    clash = true;
                    break;
                }
            }
            for (BakuraniTerrain.Building o : out) {
                if (Math.hypot(o.x() - w[0], o.z() - w[1]) < 8) {
                    clash = true;
                    break;
                }
            }
            if (!clash) {
                out.add(shed);
            }
        }
        return out;
    }

    // --- yards -----------------------------------------------------------------------------------

    private static void houseYard(Ctx c, BakuraniTerrain.Building b) {
        double hw = b.w() / 2.0, hd = b.d() / 2.0;
        boolean rural = b.kind() == 1;
        // plot boundary: a fence with a gate in front, for about half of the houses
        if (rndB(b, 1) < 0.55 && b.w() <= 16) {
            fence(c, b, hw + 5, hd + 6, hd + 7);
        }
        if (rndB(b, 2) < 0.5) {
            prop(c, b, hw - 1.5 + (rndB(b, 21) < 0.5 ? -4 : 3), hd + 2.5, "bench_park", Direction.NORTH);
        }
        if (rndB(b, 3) < 0.7) {
            prop(c, b, -hw - 2.5, hd + 5, "mailbox", Direction.SOUTH);
        }
        if (rndB(b, 4) < 0.55) {
            prop(c, b, hw + 1.5, -hd + 1, "trash_bin", Direction.SOUTH);
        }
        if (rndB(b, 5) < 0.55) {
            prop(c, b, -hw - 1.5, 0, "firewood", Direction.EAST);
            if (rndB(b, 51) < 0.5) {
                prop(c, b, -hw - 1.5, 4, "firewood", Direction.EAST);
            }
        }
        if (rndB(b, 6) < (rural ? 0.35 : 0.1)) {
            prop(c, b, hw + 2.5, -hd - 2, "hay_bale_round", Direction.NORTH);
        }
        if (rndB(b, 7) < 0.25) {
            prop(c, b, hw + 2.5, 1, "water_trough", Direction.EAST);
        }
        if (rndB(b, 8) < 0.12) {
            prop(c, b, hw + 3, -hd + 3, "beehive_box", Direction.SOUTH);
        }
        if (rndB(b, 9) < 0.45) {
            garden(c, b, hw, hd);
        }
    }

    private static final String[] YARD_STUFF = {"crate_stack", "supply_pallet", "oil_drum", "tire_stack", "shipping_container_block", "jerrycan", "sack_pile", "fire_barrel"};

    private static void industrialYard(Ctx c, BakuraniTerrain.Building b) {
        double hw = b.w() / 2.0, hd = b.d() / 2.0;
        // lamps at the front corners and benches by the door
        for (int side = -1; side <= 1; side += 2) {
            lamppost(c, b, side * (hw + 2), hd + 2.5);
        }
        prop(c, b, 3.5, hd + 2, "bench_park", Direction.NORTH);
        prop(c, b, -3.5, hd + 2, "bench_park", Direction.NORTH);
        prop(c, b, hw + 1.5, hd - 1, "trash_bin", Direction.SOUTH);
        int n = 4 + (int) (rndB(b, 11) * 5);
        for (int k = 0; k < n; k++) {
            double r1 = rnd(Math.round(b.x() * 8), Math.round(b.z() * 8), 100 + k), r2 = rnd(Math.round(b.x() * 8), Math.round(b.z() * 8), 200 + k);
            double u, v;
            switch (k % 3) {
                case 0 -> { u = (r1 < 0.5 ? -1 : 1) * (hw + 2 + r2 * 5); v = (r1 * 2 % 1 - 0.5) * (b.d() + 6); }
                case 1 -> { u = (r1 - 0.5) * (b.w() + 6); v = -hd - 2 - r2 * 5; }
                default -> { u = (r1 - 0.5) * (b.w() + 6); v = hd + 3 + r2 * 4; }
            }
            String id = YARD_STUFF[(int) (rnd(Math.round(b.x() * 8), Math.round(b.z() * 8), 300 + k) * YARD_STUFF.length)];
            Direction d = Direction.from2DDataValue((int) (r2 * 4));
            prop(c, b, u, v, id, d);
            if (id.equals("shipping_container_block")) {
                double[] w = toWorld(b, u, v);
                int x = (int) Math.floor(w[0]), z = (int) Math.floor(w[1]);
                c.onGround(x, z, DecorBlocks.state("decor_shipping_container_block", d), 1);
            }
        }
    }

    private static void lamppost(Ctx c, BakuraniTerrain.Building b, double u, double v) {
        double[] w = toWorld(b, u, v);
        lamp(c, (int) Math.floor(w[0]), (int) Math.floor(w[1]), Direction.NORTH);
    }

    /** One prop at a local position, only if the ground there is free and not inside another building. */
    private static void prop(Ctx c, BakuraniTerrain.Building b, double u, double v, String id, Direction face) {
        double[] w = toWorld(b, u, v);
        int x = (int) Math.floor(w[0]), z = (int) Math.floor(w[1]);
        if (!c.in(x, z) || c.t.nearBuilding(x, z, 1) && BuildingGen.cell(b, x + 0.5, z + 0.5) != null) {
            return;
        }
        if (inOtherBuilding(c, b, x, z)) {
            return;
        }
        c.onGround(x, z, DecorBlocks.state("decor_" + id, face), 0);
    }

    private static boolean inOtherBuilding(Ctx c, BakuraniTerrain.Building self, int x, int z) {
        for (BakuraniTerrain.Building o : c.t.buildingsIn(x - 1, z - 1, x + 1, z + 1)) {
            if (BuildingGen.cell(o, x + 0.5, z + 0.5) != null) {
                return true;
            }
        }
        return false;
    }

    private static void garden(Ctx c, BakuraniTerrain.Building b, double hw, double hd) {
        int crop = (int) (rndB(b, 31) * 3);
        BlockState plant = (crop == 0 ? Blocks.CARROTS : crop == 1 ? Blocks.POTATOES : Blocks.WHEAT).defaultBlockState().setValue(CropBlock.AGE, 6);
        for (double u = -hw + 0.5; u <= hw - 0.5; u += 1.0) {
            for (double v = -hd - 5.5; v <= -hd - 2.5; v += 1.0) {
                double[] w = toWorld(b, u, v);
                int x = (int) Math.floor(w[0]), z = (int) Math.floor(w[1]);
                if (!c.in(x, z) || inOtherBuilding(c, b, x, z)) {
                    continue;
                }
                int y = c.freeY(x, z);
                if (y == Integer.MIN_VALUE) {
                    continue;
                }
                boolean pathRow = ((int) Math.floor(v + hd + 6)) % 3 == 0;
                c.set(x, y - 1, z, pathRow ? Blocks.COARSE_DIRT.defaultBlockState() : Blocks.FARMLAND.defaultBlockState());
                if (!pathRow) {
                    c.set(x, y, z, plant);
                } else {
                    c.set(x, y, z, Blocks.AIR.defaultBlockState());
                }
            }
        }
    }

    /** A fence ring around the plot with a gate in front; connections are set from the ring itself. */
    private static void fence(Ctx c, BakuraniTerrain.Building b, double rx, double rzBack, double rzFront) {
        Set<Long> cells = new HashSet<>();
        List<int[]> order = new ArrayList<>();
        double step = 0.5;
        for (double u = -rx; u <= rx; u += step) {
            addFence(b, cells, order, u, -rzBack);
            addFence(b, cells, order, u, rzFront);
        }
        for (double v = -rzBack; v <= rzFront; v += step) {
            addFence(b, cells, order, -rx, v);
            addFence(b, cells, order, rx, v);
        }
        double[] gateW = toWorld(b, 0, rzFront);
        int gx = (int) Math.floor(gateW[0]), gz = (int) Math.floor(gateW[1]);
        for (int[] p : order) {
            int x = p[0], z = p[1];
            if (!c.in(x, z)) {
                continue;
            }
            boolean gate = Math.abs(x - gx) <= 1 && Math.abs(z - gz) <= 1;
            if (gate) {
                continue; // an opening in front of the door
            }
            if (inOtherBuilding(c, b, x, z) || c.t.nearBuilding(x, z, 0) && BuildingGen.cell(b, x + 0.5, z + 0.5) != null) {
                continue;
            }
            BlockState s = Blocks.SPRUCE_FENCE.defaultBlockState()
                    .setValue(FenceBlock.NORTH, cells.contains(key(x, z - 1))).setValue(FenceBlock.SOUTH, cells.contains(key(x, z + 1)))
                    .setValue(FenceBlock.WEST, cells.contains(key(x - 1, z))).setValue(FenceBlock.EAST, cells.contains(key(x + 1, z)));
            c.onGround(x, z, s, 0);
        }
    }

    private static long key(int x, int z) {
        return ((long) x << 32) ^ (z & 0xFFFFFFFFL);
    }

    private static void addFence(BakuraniTerrain.Building b, Set<Long> cells, List<int[]> order, double u, double v) {
        double[] w = toWorld(b, u, v);
        int x = (int) Math.floor(w[0]), z = (int) Math.floor(w[1]);
        if (cells.add(key(x, z))) {
            order.add(new int[]{x, z});
        }
    }

    // --- roads -----------------------------------------------------------------------------------

    private static boolean nearRoad(BakuraniTerrain t, int x, int z, int minClass, int reach) {
        return t.roadClass(x + 0.5 + reach, z + 0.5) >= minClass || t.roadClass(x + 0.5 - reach, z + 0.5) >= minClass
                || t.roadClass(x + 0.5, z + 0.5 + reach) >= minClass || t.roadClass(x + 0.5, z + 0.5 - reach) >= minClass;
    }

    /** Direction from a shoulder column toward the road (the lamp arm and the bench face it). */
    private static Direction towardRoad(BakuraniTerrain t, int x, int z, int minClass) {
        for (int r = 1; r <= 3; r++) {
            if (t.roadClass(x + 0.5, z + 0.5 - r) >= minClass) return Direction.NORTH;
            if (t.roadClass(x + 0.5, z + 0.5 + r) >= minClass) return Direction.SOUTH;
            if (t.roadClass(x + 0.5 + r, z + 0.5) >= minClass) return Direction.EAST;
            if (t.roadClass(x + 0.5 - r, z + 0.5) >= minClass) return Direction.WEST;
        }
        return Direction.NORTH;
    }

    private static boolean candidate(BakuraniTerrain t, int x, int z, int minClass, int reach) {
        return BakuraniTerrain.inside(x + 0.5, z + 0.5) && t.roadClass(x + 0.5, z + 0.5) == 0 && t.cover(x + 0.5, z + 0.5) != BakuraniTerrain.COVER_WATER
                && nearRoad(t, x, z, minClass, reach) && !t.nearBuilding(x, z, 2);
    }

    /** True if (x, z) is the best-ranked candidate within {@code radius}: the one with the lowest hash that passes the gate. */
    private static boolean spaced(BakuraniTerrain t, int x, int z, int salt, double gate, int radius, int minClass, int reach) {
        double me = rnd(x, z, salt);
        if (me >= gate) {
            return false;
        }
        for (int dx = -radius; dx <= radius; dx++) {
            for (int dz = -radius; dz <= radius; dz++) {
                if ((dx != 0 || dz != 0)) {
                    double hn = rnd(x + dx, z + dz, salt);
                    if ((hn < me || hn == me && (dx < 0 || dx == 0 && dz < 0)) && hn < gate && candidate(t, x + dx, z + dz, minClass, reach)) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    static void lamp(Ctx c, int x, int z, Direction toward) {
        int y = c.freeY(x, z);
        if (y == Integer.MIN_VALUE) {
            return;
        }
        for (int k = 0; k < 4; k++) {
            c.set(x, y + k, z, DecorBlocks.state("decor_street_pole", Direction.NORTH));
        }
        c.set(x, y + 4, z, DecorBlocks.state("decor_street_lamp", toward));
    }

    private static void roadProps(Ctx c) {
        BakuraniTerrain t = c.t;
        for (int dx = 0; dx < 16; dx++) {
            for (int dz = 0; dz < 16; dz++) {
                int x = c.baseX + dx, z = c.baseZ + dz;
                if (!BakuraniTerrain.inside(x + 0.5, z + 0.5) || t.roadClass(x + 0.5, z + 0.5) > 0) {
                    continue;
                }
                // street lamps along surfaced roads, about every 22 blocks
                if (rnd(x, z, 11) < 0.06 && candidate(t, x, z, 2, 2) && spaced(t, x, z, 11, 0.06, 9, 2, 2)) {
                    lamp(c, x, z, towardRoad(t, x, z, 2));
                    continue;
                }
                // benches and bins in town
                if (t.cover(x + 0.5, z + 0.5) == BakuraniTerrain.COVER_URBAN && rnd(x, z, 12) < 0.03 && candidate(t, x, z, 2, 3) && spaced(t, x, z, 12, 0.03, 12, 2, 3)) {
                    Direction toward = towardRoad(t, x, z, 2);
                    c.onGround(x, z, DecorBlocks.state("decor_bench_park", toward.getOpposite()), 0);
                    // the bench faces away from the road toward the buildings
                    continue;
                }
                // wooden power poles along dirt tracks, about every 40 blocks
                if (rnd(x, z, 13) < 0.02 && candidate(t, x, z, 1, 2) && !candidate(t, x, z, 2, 4) && spaced(t, x, z, 13, 0.02, 18, 1, 2)) {
                    powerPole(c, x, z, towardRoad(t, x, z, 1));
                }
            }
        }
    }

    private static void powerPole(Ctx c, int x, int z, Direction toward) {
        int y = c.freeY(x, z);
        if (y == Integer.MIN_VALUE) {
            return;
        }
        BlockState log = Blocks.STRIPPED_SPRUCE_LOG.defaultBlockState();
        for (int k = 0; k < 9; k++) {
            c.set(x, y + k, z, log);
        }
        // crossarm perpendicular to the wire, which runs along the road
        boolean armAlongX = toward.getAxis() == Direction.Axis.X;
        BlockState plank = Blocks.SPRUCE_PLANKS.defaultBlockState();
        for (int k = -2; k <= 2; k++) {
            c.set(armAlongX ? x + k : x, y + 8, armAlongX ? z : z + k, plank);
        }
        for (int k : new int[]{-2, 0, 2}) {
            c.set(armAlongX ? x + k : x, y + 9, armAlongX ? z : z + k, Blocks.LIGHTNING_ROD.defaultBlockState());
        }
    }

    // --- wilderness -------------------------------------------------------------------------------

    private static void wilderness(Ctx c) {
        BakuraniTerrain t = c.t;
        BlockState[] rocks = {Blocks.COBBLESTONE.defaultBlockState(), Blocks.MOSSY_COBBLESTONE.defaultBlockState(), Blocks.ANDESITE.defaultBlockState(),
                Blocks.STONE.defaultBlockState(), Blocks.COBBLESTONE.defaultBlockState()};
        for (int dx = 0; dx < 16; dx++) {
            for (int dz = 0; dz < 16; dz++) {
                int x = c.baseX + dx, z = c.baseZ + dz;
                if (!BakuraniTerrain.inside(x + 0.5, z + 0.5)) {
                    continue;
                }
                int mat = t.material(x + 0.5, z + 0.5), cov = t.cover(x + 0.5, z + 0.5);
                double r = rnd(x, z, 21);
                // undergrowth under the trees
                if (cov == BakuraniTerrain.COVER_FOREST && mat != BakuraniTerrain.SNOW && r < 0.10) {
                    BlockState s = r < 0.012 ? Blocks.SWEET_BERRY_BUSH.defaultBlockState().setValue(net.minecraft.world.level.block.SweetBerryBushBlock.AGE, 3)
                            : r < 0.06 ? Blocks.FERN.defaultBlockState() : Blocks.SHORT_GRASS.defaultBlockState();
                    int y = c.freeY(x, z);
                    if (y != Integer.MIN_VALUE && c.get(x, y, z).isAir() && !t.nearBuilding(x, z, 1)) {
                        c.set(x, y, z, s);
                    }
                }
                // fallen logs in the forest
                if (cov == BakuraniTerrain.COVER_FOREST && rnd(x, z, 22) < 0.0015 && dx > 3 && dx < 12 && dz > 3 && dz < 12) {
                    boolean alongX = rnd(x, z, 23) < 0.5;
                    BlockState log = Blocks.OAK_LOG.defaultBlockState().setValue(net.minecraft.world.level.block.RotatedPillarBlock.AXIS, alongX ? Direction.Axis.X : Direction.Axis.Z);
                    for (int k = 0; k < 4; k++) {
                        c.onGround(alongX ? x + k : x, alongX ? z : z + k, log, 0);
                    }
                }
                // boulders on rock and gravel, a few on the meadows
                double boulder = mat == BakuraniTerrain.ROCK ? 0.006 : mat == BakuraniTerrain.GRAVEL ? 0.004 : mat == BakuraniTerrain.GRASS ? 0.0004 : 0;
                if (boulder > 0 && rnd(x, z, 24) < boulder && dx > 2 && dx < 13 && dz > 2 && dz < 13 && cov != BakuraniTerrain.COVER_WATER && !t.nearBuilding(x, z, 4)) {
                    int radius = 1 + (int) (rnd(x, z, 25) * 2.2);
                    for (int ox = -radius; ox <= radius; ox++) {
                        for (int oz = -radius; oz <= radius; oz++) {
                            for (int oy = 0; oy < radius; oy++) {
                                if (ox * ox + oz * oz + oy * oy * 2 <= radius * radius + 1 && rnd(x + ox, z + oz, 26 + oy) < 0.92) {
                                    int y = c.freeY(x + ox, z + oz);
                                    if (y != Integer.MIN_VALUE) {
                                        c.set(x + ox, y + oy, z + oz, rocks[(int) (rnd(x + ox, z + oz, 27) * rocks.length)]);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
