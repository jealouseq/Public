package com.wardogs.world;

import java.io.IOException;
import java.nio.ByteBuffer;
import java.nio.ByteOrder;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

/**
 * Terrain of the Bakurani map as pure data: elevation, surface material and roads sampled in Minecraft coordinates
 * (x east, z south, 1 block = 1 m, origin = the map centre). No Minecraft types, so it is unit-testable.
 *
 * Source rasters (see research/DATA_SOURCES.md): a 2048 px elevation grid (R*256+G, 7.97 m/texel), a 2048 px material-class grid
 * and a 4096 px road mask. Elevation in metres = {@code ELEV_SCALE * raw - ELEV_OFFSET}.
 */
public final class BakuraniTerrain {
    /** Marker coordinate -> image pixel (16384-px space): px = (x + 8187) / 0.99902, py = (8191 - y_north) / 0.99983. */
    public static final double PX_PER_M_X = 1.0 / 0.99902, X_OFFSET = 8187.0;
    public static final double PY_PER_M_Z = 1.0 / 0.99983, Z_OFFSET = 8191.0;
    public static final double ELEV_SCALE = 0.25993, ELEV_OFFSET = 1008.34;
    private static final int IMAGE = 16384;

    public static final int GRASS = 4, ROCK = 5, GRAVEL = 7, SNOW = 9, FOREST = 10;

    private final int heightSize;
    private final short[] height; // raw16 values stored as unsigned shorts
    private final int matSize;
    private final byte[] material;
    private final int roadSize;
    private final byte[] roads;
    private final double verticalScale;
    private final int yOffset;
    private int coverSize;
    private byte[] cover = new byte[0];
    private final Map<Long, List<Building>> buildingIndex = new HashMap<>();
    private static final int INDEX_CELL = 64;

    /** Land cover classes of the baked cover grid. */
    public static final int COVER_NONE = 0, COVER_WATER = 1, COVER_FOREST = 2, COVER_URBAN = 3, COVER_FIELD = 4;

    /** A procedural building: centre and footprint in metres, storeys, kind 0 = grey/concrete, 1 = red roof. */
    public record Building(float x, float z, int w, int d, int floors, int kind) {
        public int minX() { return (int) Math.floor(x - w / 2.0); }
        public int minZ() { return (int) Math.floor(z - d / 2.0); }
    }

    public BakuraniTerrain(int heightSize, short[] height, int matSize, byte[] material, int roadSize, byte[] roads,
                           double verticalScale, int yOffset) {
        this.heightSize = heightSize;
        this.height = height;
        this.matSize = matSize;
        this.material = material;
        this.roadSize = roadSize;
        this.roads = roads;
        this.verticalScale = verticalScale;
        this.yOffset = yOffset;
    }

    /** Loads the baked files from a directory, or returns {@code null} if the elevation file is missing. */
    public static BakuraniTerrain load(Path dir, double verticalScale, int yOffset) throws IOException {
        Path h = dir.resolve("height_2048_u16.bin");
        if (!Files.isRegularFile(h)) {
            return null;
        }
        ByteBuffer hb = ByteBuffer.wrap(Files.readAllBytes(h)).order(ByteOrder.LITTLE_ENDIAN);
        int hs = (int) Math.round(Math.sqrt(hb.remaining() / 2.0));
        short[] height = new short[hs * hs];
        hb.asShortBuffer().get(height);
        Path m = dir.resolve("material_2048_u8.bin");
        byte[] mat = Files.isRegularFile(m) ? Files.readAllBytes(m) : new byte[0];
        Path r = Files.isRegularFile(dir.resolve("roads_clean_4096_u8.bin")) ? dir.resolve("roads_clean_4096_u8.bin") : dir.resolve("roads_4096_u8.bin");
        byte[] roads = Files.isRegularFile(r) ? Files.readAllBytes(r) : new byte[0];
        BakuraniTerrain t = new BakuraniTerrain(hs, height, (int) Math.round(Math.sqrt(mat.length)), mat,
                (int) Math.round(Math.sqrt(roads.length)), roads, verticalScale, yOffset);
        Path c = dir.resolve("cover_4096_u8.bin");
        Path b = dir.resolve("buildings.bin");
        byte[] cover = Files.isRegularFile(c) ? Files.readAllBytes(c) : new byte[0];
        List<Building> buildings = new ArrayList<>();
        if (Files.isRegularFile(b)) {
            ByteBuffer bb = ByteBuffer.wrap(Files.readAllBytes(b)).order(ByteOrder.LITTLE_ENDIAN);
            int n = bb.getInt();
            for (int i = 0; i < n; i++) {
                buildings.add(new Building(bb.getFloat(), bb.getFloat(), bb.get() & 0xFF, bb.get() & 0xFF, bb.get() & 0xFF, bb.get() & 0xFF));
            }
        }
        return t.withCover(cover, buildings);
    }

    /** Adds the land-cover grid and building list (both optional); returns this. */
    public BakuraniTerrain withCover(byte[] cover, List<Building> buildings) {
        this.cover = cover;
        this.coverSize = (int) Math.round(Math.sqrt(cover.length));
        buildingIndex.clear();
        for (Building b : buildings) {
            int x0 = Math.floorDiv(b.minX(), INDEX_CELL), x1 = Math.floorDiv(b.minX() + b.w(), INDEX_CELL);
            int z0 = Math.floorDiv(b.minZ(), INDEX_CELL), z1 = Math.floorDiv(b.minZ() + b.d(), INDEX_CELL);
            for (int cx = x0; cx <= x1; cx++) {
                for (int cz = z0; cz <= z1; cz++) {
                    buildingIndex.computeIfAbsent(key(cx, cz), k -> new ArrayList<>()).add(b);
                }
            }
        }
        return this;
    }

    private static long key(int cx, int cz) {
        return ((long) cx << 32) ^ (cz & 0xFFFFFFFFL);
    }

    /** Land cover class at a block position ({@link #COVER_NONE} without a cover grid). */
    public int cover(double x, double z) {
        if (cover.length == 0) {
            return COVER_NONE;
        }
        int ix = (int) Math.floor(pixelX(x) / IMAGE * coverSize);
        int iz = (int) Math.floor(pixelY(z) / IMAGE * coverSize);
        if (ix < 0 || iz < 0 || ix >= coverSize || iz >= coverSize) {
            return COVER_NONE;
        }
        return cover[iz * coverSize + ix] & 0xFF;
    }

    /** Buildings whose indexed cells overlap the block rectangle (may include a few that merely touch the cells). */
    public List<Building> buildingsIn(int minX, int minZ, int maxX, int maxZ) {
        List<Building> out = new ArrayList<>();
        for (int cx = Math.floorDiv(minX, INDEX_CELL); cx <= Math.floorDiv(maxX, INDEX_CELL); cx++) {
            for (int cz = Math.floorDiv(minZ, INDEX_CELL); cz <= Math.floorDiv(maxZ, INDEX_CELL); cz++) {
                for (Building b : buildingIndex.getOrDefault(key(cx, cz), List.of())) {
                    if (!out.contains(b)) {
                        out.add(b);
                    }
                }
            }
        }
        return out;
    }

    /** True if a block column lies within {@code margin} blocks of a building footprint. */
    public boolean nearBuilding(int x, int z, int margin) {
        for (Building b : buildingsIn(x - margin, z - margin, x + margin, z + margin)) {
            if (x >= b.minX() - margin && x < b.minX() + b.w() + margin && z >= b.minZ() - margin && z < b.minZ() + b.d() + margin) {
                return true;
            }
        }
        return false;
    }

    /** Replaces the building list, keeping the cover grid. */
    public void rebuildBuildings(List<Building> buildings) {
        withCover(cover, buildings);
    }

    public int buildingCount() {
        return (int) buildingIndex.values().stream().flatMap(List::stream).distinct().count();
    }

    // --- coordinate transform ----------------------------------------------------------------

    /** Image pixel (16384 space) of a Minecraft block position. */
    public static double pixelX(double x) {
        return (x + X_OFFSET) * PX_PER_M_X;
    }

    public static double pixelY(double z) {
        // z is south-positive, the marker transform uses north-positive y = -z
        return (Z_OFFSET + z) * PY_PER_M_Z;
    }

    public static boolean inside(double x, double z) {
        double px = pixelX(x), py = pixelY(z);
        return px >= 0 && py >= 0 && px < IMAGE && py < IMAGE;
    }

    // --- sampling ----------------------------------------------------------------------------

    private double rawAt(int ix, int iz) {
        ix = Math.max(0, Math.min(heightSize - 1, ix));
        iz = Math.max(0, Math.min(heightSize - 1, iz));
        return height[iz * heightSize + ix] & 0xFFFF;
    }

    /** Bilinear elevation in metres (marker-z units) at a block position. */
    public double elevation(double x, double z) {
        double fx = pixelX(x) / IMAGE * heightSize - 0.5;
        double fz = pixelY(z) / IMAGE * heightSize - 0.5;
        int x0 = (int) Math.floor(fx), z0 = (int) Math.floor(fz);
        double tx = fx - x0, tz = fz - z0;
        double raw = rawAt(x0, z0) * (1 - tx) * (1 - tz) + rawAt(x0 + 1, z0) * tx * (1 - tz)
                + rawAt(x0, z0 + 1) * (1 - tx) * tz + rawAt(x0 + 1, z0 + 1) * tx * tz;
        return ELEV_SCALE * raw - ELEV_OFFSET;
    }

    /** Top solid block's y for a column: elevation scaled vertically above a base level. */
    public int surfaceY(double x, double z) {
        return yOffset + (int) Math.round((elevation(x, z) + ELEV_OFFSET) * verticalScale);
    }

    /** Material class (0..11) at a block position, or {@link #GRASS} if there is no material grid. */
    public int material(double x, double z) {
        if (material.length == 0) {
            return GRASS;
        }
        int ix = (int) Math.floor(pixelX(x) / IMAGE * matSize);
        int iz = (int) Math.floor(pixelY(z) / IMAGE * matSize);
        ix = Math.max(0, Math.min(matSize - 1, ix));
        iz = Math.max(0, Math.min(matSize - 1, iz));
        return material[iz * matSize + ix] & 0xFF;
    }

    public boolean isRoad(double x, double z) {
        if (roads.length == 0) {
            return false;
        }
        int ix = (int) Math.floor(pixelX(x) / IMAGE * roadSize);
        int iz = (int) Math.floor(pixelY(z) / IMAGE * roadSize);
        if (ix < 0 || iz < 0 || ix >= roadSize || iz >= roadSize) {
            return false;
        }
        return roads[iz * roadSize + ix] != 0;
    }

    public int maxSurfaceY() {
        int max = Short.MIN_VALUE;
        for (short s : height) {
            max = Math.max(max, s & 0xFFFF);
        }
        return yOffset + (int) Math.round(((ELEV_SCALE * max - ELEV_OFFSET) + ELEV_OFFSET) * verticalScale);
    }
}
