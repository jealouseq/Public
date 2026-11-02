package com.wardogs.world;

import com.wardogs.decor.DecorBlocks;
import net.minecraft.core.BlockPos;
import net.minecraft.core.Direction;
import net.minecraft.server.level.ServerLevel;
import net.minecraft.world.level.block.Blocks;
import net.minecraft.world.level.block.state.BlockState;
import net.minecraft.world.level.chunk.status.ChunkStatus;

import java.util.ArrayList;
import java.util.List;

/**
 * The faction bases and outposts of Bakurani, built around the real vendor, spawn and board markers: a garage hangar for every garage
 * vendor, a market stall for every equipment vendor, a tent camp at every spawn, a supply depot, a concrete perimeter wall with barbed
 * wire, floodlights, corner watchtowers and a flag in the faction's colour. Idempotent: a marker block at the flagpole base says a
 * cluster is done. Everything uses the same {@link BakuraniSetup.Ground} the other setup code uses.
 */
public final class Outposts {
    /** Markers closer than this belong to the same outpost. */
    public static final double CLUSTER_RADIUS = 125;

    private Outposts() {}

    public record Point(String name, double x, double z) {}

    public record Cluster(List<Point> points, int faction) {
        public double cx() { return points.stream().mapToDouble(Point::x).average().orElse(0); }
        public double cz() { return points.stream().mapToDouble(Point::z).average().orElse(0); }
    }

    // --- clustering ------------------------------------------------------------------------------

    /** Faction index from a marker name: Alpha = Lonestar (0), Bravo = Valkyra (1), Charlie = Manticore (2), anything else -1. */
    static int factionOf(String name) {
        String n = name.toLowerCase();
        return n.contains("alpha") ? 0 : n.contains("bravo") ? 1 : n.contains("charlie") ? 2 : -1;
    }

    public static List<Cluster> clusters() {
        var sites = BakuraniWorld.sites();
        List<Point> all = new ArrayList<>();
        sites.vendors().forEach(s -> all.add(new Point(s.name(), s.x(), s.z())));
        sites.spawns().forEach(s -> all.add(new Point(s.name(), s.x(), s.z())));
        sites.boards().forEach(s -> all.add(new Point(s.name(), s.x(), s.z())));
        sites.bases().forEach((k, s) -> all.add(new Point("Base (" + switch (k) { case "lonestar" -> "Alpha"; case "valkyra" -> "Bravo"; default -> "Charlie"; } + ")", s.x(), s.z())));
        List<List<Point>> groups = new ArrayList<>();
        boolean[] used = new boolean[all.size()];
        for (int i = 0; i < all.size(); i++) {
            if (used[i]) {
                continue;
            }
            List<Point> g = new ArrayList<>();
            java.util.ArrayDeque<Integer> q = new java.util.ArrayDeque<>();
            q.add(i);
            used[i] = true;
            while (!q.isEmpty()) {
                int a = q.poll();
                g.add(all.get(a));
                for (int j = 0; j < all.size(); j++) {
                    if (!used[j] && Math.hypot(all.get(a).x() - all.get(j).x(), all.get(a).z() - all.get(j).z()) < CLUSTER_RADIUS) {
                        used[j] = true;
                        q.add(j);
                    }
                }
            }
            groups.add(g);
        }
        List<Cluster> out = new ArrayList<>();
        for (List<Point> g : groups) {
            int[] votes = new int[3];
            for (Point p : g) {
                int f = factionOf(p.name());
                if (f >= 0) {
                    votes[f]++;
                }
            }
            int best = -1, bv = 0;
            for (int f = 0; f < 3; f++) {
                if (votes[f] > bv) {
                    bv = votes[f];
                    best = f;
                }
            }
            out.add(new Cluster(g, best));
        }
        return out;
    }

    // --- block helpers -----------------------------------------------------------------------------

    private static final class B {
        final ServerLevel level;
        final BakuraniSetup.Ground g;
        final BlockPos.MutableBlockPos mp = new BlockPos.MutableBlockPos();

        B(ServerLevel level, BakuraniSetup.Ground g) {
            this.level = level;
            this.g = g;
        }

        void load(int x, int z) {
            level.getChunkSource().getChunk(x >> 4, z >> 4, ChunkStatus.FULL, true);
        }

        void loadArea(int x0, int z0, int x1, int z1) {
            for (int x = x0 >> 4; x <= x1 >> 4; x++) {
                for (int z = z0 >> 4; z <= z1 >> 4; z++) {
                    level.getChunkSource().getChunk(x, z, ChunkStatus.FULL, true);
                }
            }
        }

        int ground(int x, int z) {
            return g.surfaceY(x + 0.5, z + 0.5);
        }

        void set(int x, int y, int z, BlockState s) {
            level.setBlock(mp.set(x, y, z), s, 2);
        }

        BlockState get(int x, int y, int z) {
            return level.getBlockState(mp.set(x, y, z));
        }

        void box(int x0, int y0, int z0, int x1, int y1, int z1, BlockState s) {
            for (int x = Math.min(x0, x1); x <= Math.max(x0, x1); x++) {
                for (int y = Math.min(y0, y1); y <= Math.max(y0, y1); y++) {
                    for (int z = Math.min(z0, z1); z <= Math.max(z0, z1); z++) {
                        set(x, y, z, s);
                    }
                }
            }
        }

        /** Removes trees, plants and props above the natural ground in an area. */
        void clear(int x0, int z0, int x1, int z1, int height) {
            BlockState air = Blocks.AIR.defaultBlockState();
            for (int x = x0; x <= x1; x++) {
                for (int z = z0; z <= z1; z++) {
                    int gy = ground(x, z);
                    for (int y = gy + 1; y <= gy + height; y++) {
                        if (!get(x, y, z).isAir()) {
                            set(x, y, z, air);
                        }
                    }
                }
            }
        }

        /** Levels an area to the median ground height with the given top block; returns the floor's y (the top block's level). */
        int pad(int x0, int z0, int x1, int z1, BlockState top, BlockState fill) {
            List<Integer> hs = new ArrayList<>();
            for (int x = x0; x <= x1; x += 2) {
                for (int z = z0; z <= z1; z += 2) {
                    hs.add(ground(x, z));
                }
            }
            java.util.Collections.sort(hs);
            int floor = hs.get(hs.size() / 2);
            BlockState air = Blocks.AIR.defaultBlockState();
            for (int x = x0; x <= x1; x++) {
                for (int z = z0; z <= z1; z++) {
                    int gy = ground(x, z);
                    for (int y = floor + 1; y <= Math.max(gy, floor) + 14; y++) {
                        if (!get(x, y, z).isAir()) {
                            set(x, y, z, air);
                        }
                    }
                    for (int y = gy + 1; y < floor; y++) {
                        set(x, y, z, fill);
                    }
                    set(x, floor, z, top);
                }
            }
            return floor;
        }
    }

    private static BlockState decor(String id, Direction face) {
        return DecorBlocks.state("decor_" + id, face);
    }

    private static BlockState wool(int faction) {
        return (faction == 0 ? Blocks.BLUE_WOOL : faction == 1 ? Blocks.RED_WOOL : faction == 2 ? Blocks.GREEN_WOOL : Blocks.LIGHT_GRAY_WOOL).defaultBlockState();
    }

    private static final BlockState CONCRETE = Blocks.LIGHT_GRAY_CONCRETE.defaultBlockState();
    private static final BlockState DARK = Blocks.GRAY_CONCRETE.defaultBlockState();
    private static final BlockState AIR = Blocks.AIR.defaultBlockState();

    private static double rnd(long a, long b, int salt) {
        return Decorations.rnd(a, b, salt);
    }

    // --- modules -------------------------------------------------------------------------------------

    /** A hangar for the garage vendor at (vx, vz): back and side walls, a roof on pillars, an open front toward the south. @return true if built. */
    public static boolean buildHangar(ServerLevel level, BakuraniSetup.Ground g, int vx, int vz, int faction) {
        B b = new B(level, g);
        int x0 = vx - 11, x1 = vx + 11, z0 = vz - 3, z1 = vz + 12;
        b.loadArea(x0 - 2, z0 - 2, x1 + 2, z1 + 2);
        if (b.get(x0, b.ground(x0, z0) + 1, z0).is(CONCRETE.getBlock())) {
            return false; // already built
        }
        int floor = b.pad(x0, z0, x1, z1, DARK, Blocks.GRAVEL.defaultBlockState());
        int roof = floor + 8;
        for (int x = x0; x <= x1; x++) { // back wall with high windows
            for (int y = floor + 1; y <= roof; y++) {
                boolean window = y == floor + 6 && (x - x0) % 4 >= 1 && (x - x0) % 4 <= 2 && x > x0 && x < x1;
                b.set(x, y, z0, window ? Blocks.LIGHT_BLUE_STAINED_GLASS.defaultBlockState() : (y % 4 == 0 ? DARK : CONCRETE));
            }
        }
        for (int z = z0; z <= z1; z++) { // side walls, with a wide opening toward the front
            for (int y = floor + 1; y <= roof; y++) {
                if (z - z0 >= 9 && y < roof - 1) {
                    continue; // the front part of the sides is open, only a beam stays at the top
                }
                b.set(x0, y, z, CONCRETE);
                b.set(x1, y, z, CONCRETE);
            }
        }
        for (int x = x0; x <= x1; x++) { // roof plates with a ridge beam and stripes in the faction colour
            for (int z = z0; z <= z1; z++) {
                BlockState s = (x - x0) % 6 == 0 ? wool(faction) : Blocks.SMOOTH_STONE.defaultBlockState();
                b.set(x, roof, z, s);
            }
        }
        for (int x = x0; x <= x1; x += 5) { // front pillars
            for (int y = floor + 1; y < roof; y++) {
                b.set(x, y, z1, Blocks.STONE_BRICKS.defaultBlockState());
            }
            b.set(x, floor + 5, z1 + 1, decor("floodlight", Direction.SOUTH));
        }
        for (int x = x0 + 3; x <= x1 - 3; x += 6) { // ceiling lamps
            for (int z = z0 + 3; z <= z1 - 3; z += 6) {
                b.set(x, roof - 1, z, decor("ceiling_lamp", Direction.NORTH));
            }
        }
        // the workshop along the back wall and the sides
        String[] stuff = {"workbench", "tire_stack", "oil_drum", "jerrycan", "supply_pallet", "crate_stack", "workbench", "tire_stack"};
        for (int i = 0, x = x0 + 2; x <= x1 - 2; x += 3, i++) {
            if (Math.abs(x - vx) <= 2) {
                continue;
            }
            b.set(x, floor + 1, z0 + 1, decor(stuff[i % stuff.length], Direction.SOUTH));
        }
        for (int z = z0 + 3; z <= z0 + 8; z += 3) {
            b.set(x0 + 1, floor + 1, z, decor("tire_stack", Direction.EAST));
            b.set(x1 - 1, floor + 1, z, decor(z % 2 == 0 ? "crate_stack" : "oil_drum", Direction.WEST));
        }
        b.set(x1 - 1, floor + 1, z0 + 11, decor("fire_barrel", Direction.NORTH));
        return true;
    }

    /** A market stall for the equipment vendor at (ex, ez): walls on three sides, a counter in front, an awning striped in the faction colour. */
    public static boolean buildStall(ServerLevel level, BakuraniSetup.Ground g, int ex, int ez, int faction) {
        B b = new B(level, g);
        int x0 = ex - 5, x1 = ex + 5, z0 = ez - 3, z1 = ez + 4;
        b.loadArea(x0 - 2, z0 - 2, x1 + 2, z1 + 2);
        if (b.get(x0, b.ground(x0, z0) + 1, z0).is(Blocks.GREEN_TERRACOTTA)) {
            return false; // already built
        }
        int floor = b.pad(x0 - 1, z0 - 1, x1 + 1, z1 + 1, DARK, Blocks.GRAVEL.defaultBlockState());
        BlockState wall = Blocks.GREEN_TERRACOTTA.defaultBlockState();
        for (int y = floor + 1; y <= floor + 4; y++) {
            for (int x = x0; x <= x1; x++) {
                b.set(x, y, z0, y == floor + 3 && x % 3 == 0 ? Blocks.LIGHT_BLUE_STAINED_GLASS.defaultBlockState() : wall);
            }
            for (int z = z0; z <= ez + 1; z++) {
                b.set(x0, y, z, wall);
                b.set(x1, y, z, wall);
            }
        }
        for (int x = x0 - 1; x <= x1 + 1; x++) { // flat roof and the striped awning over the front
            for (int z = z0 - 1; z <= z1; z++) {
                BlockState s = z > ez + 1 ? (x % 2 == 0 ? wool(faction) : Blocks.WHITE_WOOL.defaultBlockState()) : DARK;
                b.set(x, floor + 5, z, s);
            }
        }
        for (int x : new int[]{x0 - 1, x1 + 1}) { // awning posts
            for (int y = floor + 1; y <= floor + 4; y++) {
                b.set(x, y, z1, Blocks.SPRUCE_FENCE.defaultBlockState());
            }
        }
        for (int x = x0 + 1; x <= x1 - 1; x++) { // the counter
            b.set(x, floor + 1, ez + 2, decor("kiosk_counter", Direction.SOUTH));
        }
        b.set(x0 + 1, floor + 1, z0 + 1, decor("weapon_rack", Direction.SOUTH));
        b.set(x0 + 3, floor + 1, z0 + 1, decor("weapon_rack", Direction.SOUTH));
        b.set(x1 - 1, floor + 1, z0 + 1, decor("ammo_crate", Direction.SOUTH));
        b.set(x1 - 3, floor + 1, z0 + 1, decor("crate_stack", Direction.SOUTH));
        b.set(x0 + 1, floor + 1, ez, decor("supply_crate", Direction.EAST));
        b.set(x1 - 1, floor + 1, ez, decor("radio_table", Direction.WEST));
        b.set(ex, floor + 4, ez - 1, decor("ceiling_lamp", Direction.NORTH));
        b.set(ex - 4, floor + 4, ez + 3, decor("ceiling_lamp", Direction.NORTH));
        b.set(ex + 4, floor + 4, ez + 3, decor("ceiling_lamp", Direction.NORTH));
        b.set(ex + 7, floor + 1, ez + 3, decor("sandbags", Direction.SOUTH));
        b.set(ex - 7, floor + 1, ez + 3, decor("sandbags", Direction.SOUTH));
        return true;
    }

    /** A tent: 5 wide, 6 long, ridge 3 high, open at the front (south end); blocks are wool in the given colour. */
    static void tent(B b, int cx, int cz, int floor, BlockState wool, boolean ridgeAlongZ, int faction) {
        for (int u = -2; u <= 2; u++) {
            for (int v = 0; v <= 5; v++) {
                int x = ridgeAlongZ ? cx + u : cx + v, z = ridgeAlongZ ? cz + v : cz + u;
                int h = 3 - Math.abs(u) + (Math.abs(u) == 2 ? -0 : 0);
                if (Math.abs(u) == 2) {
                    b.set(x, floor + 1, z, wool);
                } else {
                    b.set(x, floor + 1 + (Math.abs(u) == 1 ? 1 : 2), z, wool);
                    if (v == 0) {
                        b.set(x, floor + 1, z, wool);
                        if (u == 0) {
                            b.set(x, floor + 2, z, wool);
                        }
                    }
                }
            }
        }
        for (int u : new int[]{-1, 1}) {
            int x = ridgeAlongZ ? cx + u : cx + 2, z = ridgeAlongZ ? cz + 2 : cz + u;
            b.set(x, floor + 1, z, decor("cot", ridgeAlongZ ? Direction.NORTH : Direction.EAST));
        }
        b.set(ridgeAlongZ ? cx : cx + 4, floor + 3, ridgeAlongZ ? cz + 4 : cz, decor("ceiling_lamp", Direction.NORTH));
    }

    /** A tent camp around a spawn: four tents, fire barrels, tables, benches and sandbag rings; the spawn point itself stays open. */
    public static void buildCamp(ServerLevel level, BakuraniSetup.Ground g, int sx, int sz, int faction) {
        B b = new B(level, g);
        b.loadArea(sx - 24, sz - 24, sx + 24, sz + 24);
        int floor = b.pad(sx - 20, sz - 20, sx + 20, sz + 20, Blocks.COARSE_DIRT.defaultBlockState(), Blocks.DIRT.defaultBlockState());
        BlockState w = wool(faction);
        int[][] tents = {{-17, -9, 1}, {10, -9, 1}, {-17, 8, 0}, {11, 8, 0}};
        for (int[] t : tents) {
            tent(b, sx + t[0], sz + t[1], floor, w, t[2] == 1, faction);
        }
        for (int[] p : new int[][]{{-9, 0}, {9, 0}, {0, -10}, {0, 10}}) {
            b.set(sx + p[0], floor + 1, sz + p[1], decor("fire_barrel", Direction.NORTH));
        }
        b.set(sx - 6, floor + 1, sz - 6, decor("picnic_table", Direction.NORTH));
        b.set(sx + 6, floor + 1, sz + 6, decor("picnic_table", Direction.EAST));
        b.set(sx - 6, floor + 1, sz + 7, decor("bench_park", Direction.NORTH));
        b.set(sx + 6, floor + 1, sz - 7, decor("bench_park", Direction.SOUTH));
        b.set(sx + 12, floor + 1, sz, decor("sandbag_wall", Direction.NORTH));
        b.set(sx - 12, floor + 1, sz, decor("sandbag_wall", Direction.NORTH));
        b.set(sx, floor + 1, sz - 13, decor("sandbag_wall", Direction.EAST));
        b.set(sx, floor + 1, sz + 13, decor("sandbag_wall", Direction.EAST));
    }

    /** A flagpole 15 blocks tall on an iron block (the marker that says this outpost is done), a flag in the faction colour on it. */
    public static void buildFlag(ServerLevel level, BakuraniSetup.Ground g, int x, int z, int faction) {
        B b = new B(level, g);
        b.load(x, z);
        int y = b.ground(x, z);
        b.box(x - 1, y, z - 1, x + 1, y, z + 1, Blocks.STONE_BRICKS.defaultBlockState());
        b.set(x, y + 1, z, Blocks.IRON_BLOCK.defaultBlockState());
        for (int k = 2; k <= 16; k++) {
            b.set(x, y + k, z, Blocks.IRON_BARS.defaultBlockState());
        }
        for (int dy = 11; dy <= 15; dy++) {
            for (int dx = 1; dx <= 6; dx++) {
                b.set(x + dx, y + dy, z, dy == 13 ? Blocks.WHITE_WOOL.defaultBlockState() : wool(faction));
            }
        }
    }

    /** The marker block of a flag: true if this outpost has been built. */
    static boolean built(ServerLevel level, BakuraniSetup.Ground g, int x, int z) {
        B b = new B(level, g);
        b.load(x, z);
        return b.get(x, b.ground(x, z) + 1, z).is(Blocks.IRON_BLOCK);
    }

    /** A depot of crates, pallets, drums and tyres, scattered on a loose grid and kept off the given points. */
    static void buildDepot(B b, int cx, int cz, int faction, List<Point> avoid, int rx, int rz) {
        String[] stuff = {"supply_pallet", "crate_stack", "oil_drum", "tire_stack", "sack_pile", "jerrycan", "generator", "ammo_crate", "supply_crate", "fire_barrel"};
        for (int x = cx - rx; x <= cx + rx; x += 3) {
            for (int z = cz - rz; z <= cz + rz; z += 3) {
                boolean near = false;
                for (Point p : avoid) {
                    if (Math.hypot(p.x() - x, p.z() - z) < 9) {
                        near = true;
                        break;
                    }
                }
                if (near || rnd(x, z, 71) > 0.42) {
                    continue;
                }
                int gy = b.ground(x, z);
                if (!b.get(x, gy + 1, z).isAir() && !b.get(x, gy + 1, z).canBeReplaced()) {
                    continue;
                }
                String id = stuff[(int) (rnd(x, z, 72) * stuff.length)];
                b.set(x, gy + 1, z, decor(id, Direction.from2DDataValue((int) (rnd(x, z, 73) * 4))));
                if (id.equals("crate_stack") || id.equals("supply_pallet")) {
                    b.set(x, gy + 2, z, decor("sack_pile", Direction.NORTH));
                }
            }
        }
    }

    /** The concrete perimeter: a 3-high wall with barbed wire on top, openings wherever a road crosses it and at the middle of each side. */
    static void buildWall(B b, BakuraniTerrain t, int x0, int z0, int x1, int z1, int faction) {
        BlockState wall = Blocks.LIGHT_GRAY_CONCRETE.defaultBlockState();
        for (int x = x0; x <= x1; x++) {
            for (int z = z0; z <= z1; z++) {
                boolean edge = x == x0 || x == x1 || z == z0 || z == z1;
                if (!edge) {
                    continue;
                }
                boolean gate = (x == x0 || x == x1) && Math.abs(z - (z0 + z1) / 2) <= 4 || (z == z0 || z == z1) && Math.abs(x - (x0 + x1) / 2) <= 4;
                if (gate || t != null && t.roadClass(x + 0.5, z + 0.5) > 0 || t != null && t.cover(x + 0.5, z + 0.5) == BakuraniTerrain.COVER_WATER) {
                    continue;
                }
                int gy = b.ground(x, z);
                for (int y = gy + 1; y <= gy + 3; y++) {
                    b.set(x, y, z, y == gy + 3 ? Blocks.SMOOTH_STONE.defaultBlockState() : wall);
                }
                // barbed wire on top (a lamp post or two follow below)
                b.set(x, gy + 4, z, decor("barbed_wire", (x == x0 || x == x1) ? Direction.EAST : Direction.NORTH));
            }
        }
        // floodlights on poles just inside the wall, every 20 blocks
        for (int x = x0 + 8; x <= x1 - 8; x += 20) {
            floodlight(b, x, z0 + 2, Direction.SOUTH);
            floodlight(b, x, z1 - 2, Direction.NORTH);
        }
        for (int z = z0 + 8; z <= z1 - 8; z += 20) {
            floodlight(b, x0 + 2, z, Direction.EAST);
            floodlight(b, x1 - 2, z, Direction.WEST);
        }
    }

    private static void floodlight(B b, int x, int z, Direction toward) {
        int gy = b.ground(x, z);
        for (int k = 1; k <= 5; k++) {
            b.set(x, gy + k, z, decor("street_pole", Direction.NORTH));
        }
        b.set(x, gy + 6, z, decor("floodlight", toward));
    }

    // --- the whole outpost ---------------------------------------------------------------------------

    /** Builds every outpost cluster that is not built yet. @return how many were built. */
    public static int buildAll(ServerLevel level, BakuraniSetup.Ground g) {
        int n = 0;
        for (Cluster c : clusters()) {
            if (build(level, g, c)) {
                n++;
            }
        }
        return n;
    }

    public static boolean build(ServerLevel level, BakuraniSetup.Ground g, Cluster c) {
        B b = new B(level, g);
        int cx = (int) Math.round(c.cx()), cz = (int) Math.round(c.cz());
        // the flag stands at the base marker if there is one, else at the centre
        int fx = cx, fz = cz;
        for (Point p : c.points()) {
            if (p.name().startsWith("Base")) {
                fx = (int) Math.round(p.x());
                fz = (int) Math.round(p.z()) + 6;
            }
        }
        if (built(level, g, fx, fz)) {
            return false;
        }
        double minX = Double.MAX_VALUE, maxX = -Double.MAX_VALUE, minZ = Double.MAX_VALUE, maxZ = -Double.MAX_VALUE;
        for (Point p : c.points()) {
            minX = Math.min(minX, p.x()); maxX = Math.max(maxX, p.x()); minZ = Math.min(minZ, p.z()); maxZ = Math.max(maxZ, p.z());
        }
        int wx0 = (int) minX - 26, wx1 = (int) maxX + 26, wz0 = (int) minZ - 24, wz1 = (int) maxZ + 24;
        b.loadArea(wx0 - 4, wz0 - 4, wx1 + 4, wz1 + 4);
        BakuraniTerrain t = BakuraniWorld.terrain();
        // clear the natural trees and props inside the walls, then build the modules
        b.clear(wx0, wz0, wx1, wz1, 16);
        List<Point> garages = new ArrayList<>(), stalls = new ArrayList<>(), spawns = new ArrayList<>();
        for (Point p : c.points()) {
            String n = p.name().toLowerCase();
            if (n.contains("garage") || n.contains("vehicle")) {
                garages.add(p);
            } else if (n.contains("equipment")) {
                stalls.add(p);
            } else if (n.contains("spawn")) {
                spawns.add(p);
            }
        }
        List<Point> hangars = new ArrayList<>();
        for (Point p : garages) { // vendors within 25 m share one hangar
            boolean merged = false;
            for (int i = 0; i < hangars.size(); i++) {
                Point h = hangars.get(i);
                if (Math.hypot(h.x() - p.x(), h.z() - p.z()) < 25) {
                    hangars.set(i, new Point(h.name(), (h.x() + p.x()) / 2, (h.z() + p.z()) / 2));
                    merged = true;
                }
            }
            if (!merged) {
                hangars.add(p);
            }
        }
        for (Point h : hangars) {
            buildHangar(level, g, (int) Math.round(h.x()), (int) Math.round(h.z()), c.faction());
        }
        for (Point s : stalls) {
            buildStall(level, g, (int) Math.round(s.x()), (int) Math.round(s.z()), c.faction());
        }
        for (Point s : spawns) {
            buildCamp(level, g, (int) Math.round(s.x()), (int) Math.round(s.z()), c.faction());
        }
        List<Point> avoid = new ArrayList<>(c.points());
        buildDepot(b, cx, cz, c.faction(), avoid, 30, 18);
        buildWall(b, t, wx0, wz0, wx1, wz1, c.faction());
        for (int[] corner : new int[][]{{wx0 + 3, wz0 + 3}, {wx1 - 3, wz0 + 3}, {wx0 + 3, wz1 - 3}, {wx1 - 3, wz1 - 3}}) {
            BakuraniSetup.buildTowerShell(level, corner[0], corner[1], g);
        }
        buildFlag(level, g, fx, fz, c.faction());
        return true;
    }
}
