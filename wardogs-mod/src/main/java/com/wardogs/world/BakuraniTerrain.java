package com.wardogs.world;

import java.io.IOException;
import java.nio.ByteBuffer;
import java.nio.ByteOrder;
import java.nio.file.Files;
import java.nio.file.Path;

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
        Path r = dir.resolve("roads_4096_u8.bin");
        byte[] roads = Files.isRegularFile(r) ? Files.readAllBytes(r) : new byte[0];
        return new BakuraniTerrain(hs, height, (int) Math.round(Math.sqrt(mat.length)), mat,
                (int) Math.round(Math.sqrt(roads.length)), roads, verticalScale, yOffset);
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
