package com.wardogs.world;

import org.junit.jupiter.api.Assumptions;
import org.junit.jupiter.api.Test;

import java.nio.file.Files;
import java.nio.file.Path;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertTrue;

class BakuraniTerrainTest {
    private static BakuraniTerrain flat(int raw) {
        short[] h = new short[4];
        java.util.Arrays.fill(h, (short) raw);
        return new BakuraniTerrain(2, h, 0, new byte[0], 0, new byte[0], 0.5, 16);
    }

    @Test
    void elevationIsTheFittedLinearFunctionOfTheRawValue() {
        BakuraniTerrain t = flat(4000);
        double expected = 0.25993 * 4000 - 1008.34;
        assertEquals(expected, t.elevation(0, 0), 1e-6);
        assertEquals(expected, t.elevation(-5000, 3000), 1e-6, "flat data is flat everywhere");
    }

    @Test
    void verticalScaleCompressesReliefAboveTheBaseLevel() {
        BakuraniTerrain t = flat(4000);
        int y = t.surfaceY(0, 0);
        double elev = 0.25993 * 4000 - 1008.34;
        assertEquals(16 + Math.round((elev + 1008.34) * 0.5), y);
        BakuraniTerrain lower = flat(0);
        assertTrue(lower.surfaceY(0, 0) < y);
        assertEquals(16, lower.surfaceY(0, 0), "the lowest ground sits on the base level");
    }

    @Test
    void bilinearSamplingInterpolatesBetweenTexels() {
        short[] h = {0, 4000, 0, 4000}; // 2x2: left column 0, right column 4000
        BakuraniTerrain t = new BakuraniTerrain(2, h, 0, new byte[0], 0, new byte[0], 1.0, 0);
        double left = t.elevation(-8187 + 1, 0), right = t.elevation(8187 - 1, 0);
        assertTrue(right > left + 800, "elevation rises to the right: " + left + " -> " + right);
        double mid = t.elevation(0, 0);
        assertTrue(mid > left && mid < right);
    }

    @Test
    void markerCoordinatesMapToTheDocumentedPixels() {
        // Control Zone marker (-219, y_north -1025) -> mc z = +1025
        assertEquals((-219 + 8187) / 0.99902, BakuraniTerrain.pixelX(-219), 1e-9);
        assertEquals((8191 + 1025) / 0.99983, BakuraniTerrain.pixelY(1025), 1e-9);
        assertTrue(BakuraniTerrain.inside(0, 0));
        assertFalse(BakuraniTerrain.inside(9000, 0));
        assertFalse(BakuraniTerrain.inside(0, -9000));
    }

    @Test
    void missingLayersFallBackSafely() {
        BakuraniTerrain t = flat(100);
        assertEquals(BakuraniTerrain.GRASS, t.material(10, 10));
        assertFalse(t.isRoad(10, 10));
    }

    /** Calibration against the community marker data: marker z should match the terrain under it. Needs the local map data. */
    @Test
    void realDataMatchesMarkerElevations() throws Exception {
        Path dir = Path.of(System.getProperty("wardogs.mapdir", "local-data/bakurani"));
        Assumptions.assumeTrue(Files.isRegularFile(dir.resolve("height_2048_u16.bin")), "local map data not installed");
        BakuraniTerrain t = BakuraniTerrain.load(dir, 1.0, 0);
        // {x, z (south-positive), marker elevation}
        double[][] pts = {{544, 4938, -841.3}, {3680, 1101, -850.9}, {-4242, 424, -733.6}, {-2881, 1665, -875.4},
                {177, 907, -901.2}, {-478, 848, -911.5}, {-472, 1188, -912.8}, {32, 1345, -910.0}, {-145, 1207, -895.8},
                {1857, 385, -608.0}, {-2501, 1733, -832.4}, {630, 4099, -703.5}};
        double sum = 0;
        for (double[] p : pts) {
            double err = Math.abs(t.elevation(p[0], p[1]) - p[2]);
            sum += err;
            assertTrue(err < 45, "marker at (" + p[0] + ", " + p[1] + ") expected " + p[2] + " got " + t.elevation(p[0], p[1]));
        }
        assertTrue(sum / pts.length < 15, "mean elevation error " + sum / pts.length + " m");
        assertTrue(t.maxSurfaceY() > 400);
    }
}
