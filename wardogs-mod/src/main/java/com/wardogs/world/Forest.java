package com.wardogs.world;

import com.wardogs.nature.Trees;
import com.wardogs.nature.Trees.Kind;

/**
 * Decides where Bakurani's trees and bushes grow and which species stand there. The decision is a pure function of the column, so a
 * tree whose trunk lies in a neighbouring chunk is found (and planned identically) by every chunk its crown reaches.
 */
public final class Forest {
    private Forest() {}

    /** A tree or bush to plant: its kind, 0..1 size inside the kind's range, and the ground height of its trunk. */
    public record Pick(Kind kind, double size, int groundY) {}

    /** Blocks around a chunk within which a trunk can still put a block into it (the widest crown plus its limbs). */
    public static final int REACH = 10;

    private static final double MAX_DENSITY = 1.0 / 14;

    /** The tree or bush rooted in column {@code (wx, wz)}, or null. */
    public static Pick pick(BakuraniTerrain t, int wx, int wz) {
        double hv = BakuraniChunkGenerator.hash(wx, wz);
        double hb = BakuraniChunkGenerator.hash(wx + 911, wz + 577);
        if (hv >= MAX_DENSITY && hb >= 1.0 / 30) {
            return null;
        }
        double x = wx + 0.5, z = wz + 0.5;
        if (!BakuraniTerrain.inside(x, z) || t.isRoad(x, z)) {
            return null;
        }
        int mat = t.material(x, z);
        int cov = t.cover(x, z);
        if (mat == BakuraniTerrain.SNOW || cov == BakuraniTerrain.COVER_WATER || cov == BakuraniTerrain.COVER_FIELD
                || cov == BakuraniTerrain.COVER_URBAN) {
            return null;
        }
        boolean forest = cov == BakuraniTerrain.COVER_FOREST;
        double conif = Trees.stand(x, z, 70, 1);
        double grove = Trees.stand(x, z, 52, 5);
        double density;
        double bushDensity;
        if (forest) {
            density = mat == BakuraniTerrain.ROCK ? 1.0 / 34 : 1.0 / 26;
            bushDensity = 1.0 / 36;
        } else if (mat == BakuraniTerrain.FOREST) {
            density = 1.0 / 32;
            bushDensity = 1.0 / 50;
        } else if (mat == BakuraniTerrain.GRASS) {
            density = grove > 0.66 ? 1.0 / 44 : 1.0 / 340; // birch and oak copses on the meadows, lone trees between
            bushDensity = grove > 0.5 ? 1.0 / 60 : 1.0 / 220;
        } else {
            return null;
        }
        if (t.nearBuilding(wx, wz, 9)) {
            return null;
        }
        int sy = t.surfaceY(x, z);
        if (BakuraniChunkGenerator.slopeAt(t, x, z, sy) >= BakuraniChunkGenerator.ROCK_SLOPE) {
            return null;
        }
        double mix = BakuraniChunkGenerator.hash(wx * 3 + 1, wz * 5 + 2);
        double size = BakuraniChunkGenerator.hash(wz + 17, wx + 31);
        if (BakuraniChunkGenerator.hash(wx + 5, wz + 71) < 0.2) {
            size *= 0.4; // young trees among the old
        }
        if (hv < density) {
            boolean crowded = false;
            for (int ox = -2; ox <= 2 && !crowded; ox++) {
                for (int oz = -2; oz <= 2; oz++) {
                    if ((ox != 0 || oz != 0) && ox * ox + oz * oz <= 6 && BakuraniChunkGenerator.hash(wx + ox, wz + oz) < Math.min(hv, density)) {
                        crowded = true; // the neighbour with the smaller roll wins, which is the same answer in every chunk
                        break;
                    }
                }
            }
            if (!crowded) {
                return new Pick(species(forest, conif, mix), size, sy);
            }
        }
        if (hb < bushDensity && !(forest && conif > 0.38 && mix < 0.5)) {
            return new Pick(mix < 0.45 ? Kind.BUSH_OAK : mix < 0.75 ? Kind.BUSH_BIRCH : Kind.BUSH_FIR, size, sy);
        }
        return null;
    }

    static Kind species(boolean forest, double conif, double mix) {
        if (forest) {
            if (conif > 0.38) { // a conifer stand: fir with pines on the drier ground, a few broadleaves among them
                return mix < 0.72 ? Kind.FIR : mix < 0.92 ? Kind.PINE : mix < 0.96 ? Kind.BIRCH : Kind.ASPEN;
            }
            return mix < 0.28 ? Kind.BIRCH : mix < 0.48 ? Kind.ASPEN : mix < 0.78 ? Kind.OAK : mix < 0.9 ? Kind.BEECH : Kind.FIR;
        }
        return mix < 0.3 ? Kind.BIRCH : mix < 0.5 ? Kind.ASPEN : mix < 0.8 ? Kind.OAK : mix < 0.92 ? Kind.BEECH : Kind.PINE;
    }
}
