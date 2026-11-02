package com.wardogs.nature;

import it.unimi.dsi.fastutil.longs.Long2ByteOpenHashMap;
import net.minecraft.core.BlockPos;

import java.util.SplittableRandom;

/**
 * Procedural trees for Bakurani. Every species has its own silhouette instead of the vanilla block clump:
 * <ul>
 *   <li><b>fir</b> - a tall tapering spire, foliage in drooping tiers with bare branch tips showing, a leader at the top;</li>
 *   <li><b>pine</b> - a long bare, slightly bent trunk and a few flat umbrellas of needles on spreading branches;</li>
 *   <li><b>birch / aspen</b> - slender bent white trunks and light ragged crowns made of several small clumps with hanging twigs;</li>
 *   <li><b>oak</b> - a thick forked trunk with root flare, long limbs and a wide crown of overlapping lobes;</li>
 *   <li><b>beech</b> - a taller, narrower oak with coppery foliage.</li>
 * </ul>
 * A tree is planned into a {@link Plan} (position to material code) that depends only on its position, so a tree whose centre
 * lies in the next chunk plans the same blocks and the chunk writes just the part inside itself.
 */
public final class Trees {
    private Trees() {}

    /** Codes stored in a plan: species ordinal * 4 + form. */
    public static final int LEAF = 0, LOG_Y = 1, LOG_X = 2, LOG_Z = 3;

    public static int code(NatureBlocks.Species s, int form) {
        return s.ordinal() * 4 + form;
    }

    public static NatureBlocks.Species speciesOf(int code) {
        return NatureBlocks.Species.values()[code >> 2];
    }

    public static int formOf(int code) {
        return code & 3;
    }

    /** What a finished tree is made of. */
    public static final class Plan {
        public final Long2ByteOpenHashMap blocks = new Long2ByteOpenHashMap();
        public int logs, leaves;
        private final int seed;

        Plan(int seed) {
            this.seed = seed;
        }

        void leaf(NatureBlocks.Species s, int x, int y, int z) {
            if (!blocks.containsKey(BlockPos.asLong(x, y, z))) {
                blocks.put(BlockPos.asLong(x, y, z), (byte) code(s, LEAF));
                leaves++;
            }
        }

        void log(NatureBlocks.Species s, int x, int y, int z, int form) {
            long k = BlockPos.asLong(x, y, z);
            byte old = blocks.getOrDefault(k, (byte) -1);
            if (old < 0 || formOf(old) == LEAF) {
                if (old >= 0) {
                    leaves--;
                }
                logs++;
            }
            blocks.put(k, (byte) code(s, form));
        }

        double h(int x, int y, int z, int salt) {
            return hash3(x, y, z, seed + salt);
        }

        /** A rough ellipsoid of foliage; {@code rough} dents the shell so lobes are not smooth. */
        void blob(NatureBlocks.Species s, int cx, int cy, int cz, double rx, double ry, double rz, double rough) {
            int ix = (int) Math.ceil(rx + 1), iy = (int) Math.ceil(ry + 1), iz = (int) Math.ceil(rz + 1);
            for (int dx = -ix; dx <= ix; dx++) {
                for (int dy = -iy; dy <= iy; dy++) {
                    for (int dz = -iz; dz <= iz; dz++) {
                        double d = (dx / rx) * (dx / rx) + (dy / ry) * (dy / ry) + (dz / rz) * (dz / rz);
                        if (d <= 1.0 + rough * (h(cx + dx, cy + dy, cz + dz, 11) - 0.5) * 1.5) {
                            leaf(s, cx + dx, cy + dy, cz + dz);
                        }
                    }
                }
            }
        }

        /** A straight limb from one point to another, each block turned along the direction it mostly runs in. */
        void limb(NatureBlocks.Species s, int x0, int y0, int z0, int x1, int y1, int z1) {
            int n = Math.max(Math.abs(x1 - x0), Math.max(Math.abs(y1 - y0), Math.abs(z1 - z0)));
            int ax = Math.abs(x1 - x0), ay = Math.abs(y1 - y0), az = Math.abs(z1 - z0);
            int form = ay >= ax && ay >= az ? LOG_Y : ax >= az ? LOG_X : LOG_Z;
            int cx = x0, cy = y0, cz = z0;
            log(s, cx, cy, cz, form);
            for (int i = 1; i <= n; i++) {
                double f = (double) i / n;
                int tx = (int) Math.round(x0 + (x1 - x0) * f), ty = (int) Math.round(y0 + (y1 - y0) * f), tz = (int) Math.round(z0 + (z1 - z0) * f);
                while (cx != tx || cy != ty || cz != tz) { // one face step at a time, so a limb is a connected chain
                    if (cx != tx) {
                        cx += Integer.signum(tx - cx);
                    } else if (cz != tz) {
                        cz += Integer.signum(tz - cz);
                    } else {
                        cy += Integer.signum(ty - cy);
                    }
                    log(s, cx, cy, cz, form);
                }
            }
        }
    }

    public static double hash3(int x, int y, int z, int salt) {
        long h = x * 0x9E3779B1L ^ y * 0x85EBCA77L ^ z * 0xC2B2AE3DL ^ salt * 0x27D4EB2FL;
        h ^= h >>> 15;
        h *= 0x2C1B3C6DL;
        h ^= h >>> 12;
        h *= 0x297A2D39L;
        h ^= h >>> 15;
        return (h & 0xFFFFFF) / (double) 0x1000000;
    }

    /** The shapes a tree can take. */
    public enum Kind {
        FIR(NatureBlocks.Species.FIR), PINE(NatureBlocks.Species.PINE), BIRCH(NatureBlocks.Species.BIRCH),
        ASPEN(NatureBlocks.Species.ASPEN), OAK(NatureBlocks.Species.OAK), BEECH(NatureBlocks.Species.BEECH),
        BUSH_OAK(NatureBlocks.Species.OAK), BUSH_FIR(NatureBlocks.Species.FIR), BUSH_BIRCH(NatureBlocks.Species.BIRCH);

        public final NatureBlocks.Species species;

        Kind(NatureBlocks.Species s) {
            species = s;
        }

        public boolean conifer() {
            return this == FIR || this == PINE;
        }
    }

    /** Plans one tree with its trunk base standing on the ground block at {@code (x, g, z)}; {@code size} is 0..1 within the kind's height range. */
    public static Plan plan(Kind kind, int x, int g, int z, double size) {
        int seed = (int) (x * 7919L + z * 104729L);
        Plan p = new Plan(seed);
        SplittableRandom r = new SplittableRandom(x * 0x9E3779B97F4A7C15L + z * 0xC2B2AE3D27D4EB4FL);
        switch (kind) {
            case FIR -> fir(p, x, g, z, r, 8 + (int) Math.round(size * 12));
            case PINE -> pine(p, x, g, z, r, 11 + (int) Math.round(size * 10));
            case BIRCH -> birch(p, NatureBlocks.Species.BIRCH, x, g, z, r, 7 + (int) Math.round(size * 6));
            case ASPEN -> birch(p, NatureBlocks.Species.ASPEN, x, g, z, r, 9 + (int) Math.round(size * 6));
            case OAK -> oak(p, NatureBlocks.Species.OAK, x, g, z, r, 8 + (int) Math.round(size * 6), 1.0);
            case BEECH -> oak(p, NatureBlocks.Species.BEECH, x, g, z, r, 11 + (int) Math.round(size * 6), 0.78);
            case BUSH_OAK -> bush(p, NatureBlocks.Species.OAK, x, g, z, r, size);
            case BUSH_FIR -> bush(p, NatureBlocks.Species.FIR, x, g, z, r, size);
            case BUSH_BIRCH -> bush(p, NatureBlocks.Species.BIRCH, x, g, z, r, size);
        }
        return p;
    }

    private static void fir(Plan p, int x, int g, int z, SplittableRandom r, int h) {
        NatureBlocks.Species s = NatureBlocks.Species.FIR;
        int top = g + h;
        for (int y = g + 1; y <= top; y++) {
            p.log(s, x, y, z, LOG_Y);
        }
        if (h >= 14) { // root flare
            for (int[] d : new int[][]{{1, 0}, {-1, 0}, {0, 1}, {0, -1}}) {
                if (r.nextDouble() < 0.65) {
                    p.log(s, x + d[0], g + 1, z + d[1], LOG_Y);
                }
            }
        }
        int y0 = g + 2 + (h >= 14 ? 3 : h >= 10 ? 2 : 1);
        int span = top - y0;
        double rmax = (h >= 16 ? 4.2 : h >= 12 ? 3.5 : 2.7) * (0.9 + 0.2 * r.nextDouble());
        for (int y = y0; y <= top + 1; y++) {
            double t = (double) (y - y0) / (span + 1);
            int tier = (y - y0) % 3;
            double rad = rmax * Math.pow(1 - Math.min(1, t), 0.85) + (tier == 0 ? 0.9 : tier == 1 ? 0.15 : -0.7);
            if (y >= top) {
                rad = y == top ? 0.9 : 0;
            }
            int ir = (int) Math.ceil(Math.max(rad, 0));
            for (int ox = -ir; ox <= ir; ox++) {
                for (int oz = -ir; oz <= ir; oz++) {
                    double d2 = ox * ox + oz * oz;
                    if (d2 > rad * rad + 0.4) {
                        continue;
                    }
                    if (d2 > (rad - 1) * (rad - 1) && p.h(x + ox, y, z + oz, 3) < 0.22) { // a ragged rim
                        continue;
                    }
                    p.leaf(s, x + ox, y, z + oz);
                    if (tier == 0 && d2 > (rad - 1.2) * (rad - 1.2) && p.h(x + ox, y, z + oz, 5) < 0.45) { // needles droop below the skirt
                        p.leaf(s, x + ox, y - 1, z + oz);
                    }
                }
            }
            if (tier == 0 && rad >= 2.4 && y < top - 2) { // bare branch tips poke out of the lowest skirts
                int len = (int) Math.round(rad);
                for (int[] d : new int[][]{{1, 0}, {-1, 0}, {0, 1}, {0, -1}}) {
                    if (p.h(x, y, z, 17 + d[0] * 2 + d[1]) < 0.7) {
                        for (int i = 1; i <= len; i++) {
                            p.log(s, x + d[0] * i, y, z + d[1] * i, d[0] != 0 ? LOG_X : LOG_Z);
                        }
                    }
                }
            }
        }
        p.leaf(s, x, top + 1, z);
        p.leaf(s, x, top + 2, z);
        for (int y = g + 2; y < y0 - 1; y++) { // dead stubs on the bare lower trunk
            if (p.h(x, y, z, 23) < 0.14) {
                int[] d = new int[][]{{1, 0}, {-1, 0}, {0, 1}, {0, -1}}[r.nextInt(4)];
                p.log(s, x + d[0], y, z + d[1], d[0] != 0 ? LOG_X : LOG_Z);
            }
        }
    }

    private static void pine(Plan p, int x, int g, int z, SplittableRandom r, int h) {
        NatureBlocks.Species s = NatureBlocks.Species.PINE;
        int top = g + h;
        int cx = x, cz = z;
        int bend1 = g + (int) (h * 0.35), bend2 = g + (int) (h * 0.7);
        int[] lean = r.nextBoolean() ? new int[]{r.nextBoolean() ? 1 : -1, 0} : new int[]{0, r.nextBoolean() ? 1 : -1};
        int[] cxAt = new int[h + 2], czAt = new int[h + 2];
        for (int y = g + 1; y <= top; y++) {
            if (y == bend1 + 1 || (y == bend2 + 1 && r.nextDouble() < 0.6)) {
                p.log(s, cx, y, cz, LOG_Y); // the trunk steps sideways at this height, so both columns have a block here
                cx += lean[0];
                cz += lean[1];
            }
            p.log(s, cx, y, cz, LOG_Y);
            cxAt[y - g] = cx;
            czAt[y - g] = cz;
        }
        int crownY = g + Math.max(5, (int) Math.round(h * 0.62));
        int branches = 3 + r.nextInt(3);
        for (int i = 0; i < branches; i++) {
            double ang = (i + r.nextDouble() * 0.7) * Math.PI * 2 / branches;
            int by = Math.min(top - 1, crownY + (int) (i * (top - crownY) / (double) branches));
            double len = 2.4 + r.nextDouble() * 2.4 + (h >= 16 ? 0.8 : 0);
            int bx = cxAt[by - g] + (int) Math.round(Math.cos(ang) * len), bz = czAt[by - g] + (int) Math.round(Math.sin(ang) * len);
            int ey = by + 1 + r.nextInt(2);
            p.limb(s, cxAt[by - g], by, czAt[by - g], bx, ey, bz);
            double rr = 2.0 + r.nextDouble() * 1.4;
            p.blob(s, bx, ey + 1, bz, rr, 1.0 + r.nextDouble() * 0.7, rr, 0.5);
        }
        double rt = 2.0 + r.nextDouble() * 1.0;
        p.blob(s, cx, top, cz, rt, 1.7, rt, 0.5);
        p.leaf(s, cx, top + 2, cz);
        for (int y = g + 2; y < crownY - 1; y += 1) { // a few dead stubs
            if (p.h(x, y, z, 29) < 0.1) {
                int[] d = new int[][]{{1, 0}, {-1, 0}, {0, 1}, {0, -1}}[r.nextInt(4)];
                p.log(s, cxAt[y - g] + d[0], y, czAt[y - g] + d[1], d[0] != 0 ? LOG_X : LOG_Z);
            }
        }
    }

    private static void birch(Plan p, NatureBlocks.Species s, int x, int g, int z, SplittableRandom r, int h) {
        int top = g + h;
        int cx = x, cz = z;
        int bend = g + (int) (h * (0.45 + r.nextDouble() * 0.2));
        int[] lean = r.nextBoolean() ? new int[]{r.nextBoolean() ? 1 : -1, 0} : new int[]{0, r.nextBoolean() ? 1 : -1};
        int[] cxAt = new int[h + 2], czAt = new int[h + 2];
        for (int y = g + 1; y <= top; y++) {
            if (y == bend + 1) {
                p.log(s, cx, y, cz, LOG_Y);
                cx += lean[0];
                cz += lean[1];
            }
            p.log(s, cx, y, cz, LOG_Y);
            cxAt[y - g] = cx;
            czAt[y - g] = cz;
        }
        double rx = (s == NatureBlocks.Species.ASPEN ? 1.7 : 2.1) + r.nextDouble() * 0.7;
        double ry = 2.4 + r.nextDouble() * 0.8;
        p.blob(s, cx, top - 1, cz, rx, ry, rx, 0.9);
        int lobes = 1 + r.nextInt(3);
        for (int i = 0; i < lobes; i++) {
            double ang = r.nextDouble() * Math.PI * 2;
            int by = top - 3 - r.nextInt(3);
            int bx = cxAt[by - g] + (int) Math.round(Math.cos(ang) * 2.4), bz = czAt[by - g] + (int) Math.round(Math.sin(ang) * 2.4);
            p.limb(s, cxAt[by - g], by, czAt[by - g], bx, by + 1, bz);
            p.blob(s, bx, by + 1, bz, 1.6 + r.nextDouble() * 0.6, 1.4 + r.nextDouble() * 0.5, 1.6 + r.nextDouble() * 0.6, 0.9);
        }
        int hang = (int) Math.ceil(rx);
        for (int ox = -hang; ox <= hang; ox++) { // weeping twigs under the crown
            for (int oz = -hang; oz <= hang; oz++) {
                if (p.blocks.containsKey(BlockPos.asLong(cx + ox, top - 3, cz + oz)) && !p.blocks.containsKey(BlockPos.asLong(cx + ox, top - 4, cz + oz))
                        && p.h(cx + ox, top, cz + oz, 31) < 0.35) {
                    p.leaf(s, cx + ox, top - 4, cz + oz);
                    if (p.h(cx + ox, top, cz + oz, 37) < 0.4) {
                        p.leaf(s, cx + ox, top - 5, cz + oz);
                    }
                }
            }
        }
        for (int y = g + 2; y < top - 4; y++) {
            if (p.h(x, y, z, 41) < 0.08) {
                int[] d = new int[][]{{1, 0}, {-1, 0}, {0, 1}, {0, -1}}[r.nextInt(4)];
                p.log(s, cxAt[y - g] + d[0], y, czAt[y - g] + d[1], d[0] != 0 ? LOG_X : LOG_Z);
            }
        }
    }

    private static void oak(Plan p, NatureBlocks.Species s, int x, int g, int z, SplittableRandom r, int h, double spread) {
        int top = g + h;
        int forkY = g + Math.max(3, (int) Math.round(h * 0.5));
        boolean thick = h >= 10 && s == NatureBlocks.Species.OAK;
        for (int y = g + 1; y <= top - 2; y++) {
            p.log(s, x, y, z, LOG_Y);
            if (thick && y <= forkY) {
                p.log(s, x + 1, y, z, LOG_Y);
                p.log(s, x, y, z + 1, LOG_Y);
                p.log(s, x + 1, y, z + 1, LOG_Y);
            }
        }
        if (s == NatureBlocks.Species.OAK) { // root flare
            for (int[] d : new int[][]{{-1, 0}, {1, 0}, {0, -1}, {0, 1}, {2, 1}, {-1, 1}, {1, 2}, {1, -1}}) {
                if (!thick && d[0] != 0 && d[1] != 0 || !thick && Math.abs(d[0]) + Math.abs(d[1]) > 1) { // a slim trunk flares only to its four sides
                    continue;
                }
                if (r.nextDouble() < 0.55) {
                    p.log(s, x + d[0], g + 1, z + d[1], LOG_Y);
                }
            }
        }
        int n = 3 + r.nextInt(3);
        double base = r.nextDouble() * Math.PI * 2;
        for (int i = 0; i < n; i++) {
            double ang = base + i * Math.PI * 2 / n + (r.nextDouble() - 0.5) * 0.6;
            double len = (3.0 + r.nextDouble() * 2.2) * spread;
            int by = forkY + r.nextInt(2);
            int bx = x + (int) Math.round(Math.cos(ang) * len), bz = z + (int) Math.round(Math.sin(ang) * len);
            int ey = by + 1 + r.nextInt(3);
            p.limb(s, x, by, z, bx, ey, bz);
            double rr = (2.5 + r.nextDouble() * 1.1) * spread;
            p.blob(s, bx, ey + 1, bz, rr, 1.9 + r.nextDouble() * 0.8, rr, 0.7);
        }
        double rc = (3.2 + r.nextDouble() * 1.2) * spread;
        p.log(s, x, top - 1, z, LOG_Y);
        p.blob(s, x, top, z, rc, 2.3 + r.nextDouble() * 0.8, rc, 0.7);
    }

    private static void bush(Plan p, NatureBlocks.Species s, int x, int g, int z, SplittableRandom r, double size) {
        double rr = 1.2 + size * 1.2;
        p.blob(s, x, g + 1, z, rr, s == NatureBlocks.Species.FIR ? 1.6 : 1.0, rr, 0.8);
        if (r.nextDouble() < 0.6) {
            p.blob(s, x + (r.nextBoolean() ? 1 : -1), g + 1, z + (r.nextBoolean() ? 1 : -1), rr * 0.7, 0.9, rr * 0.7, 0.8);
        }
    }

    /** Smooth value noise in [0,1) over {@code cell}-block cells, so tree stands have soft edges. */
    public static double stand(double x, double z, int cell, int salt) {
        double fx = x / cell, fz = z / cell;
        int ix = (int) Math.floor(fx), iz = (int) Math.floor(fz);
        double tx = fx - ix, tz = fz - iz;
        tx = tx * tx * (3 - 2 * tx);
        tz = tz * tz * (3 - 2 * tz);
        double a = hash3(ix, 0, iz, salt), b = hash3(ix + 1, 0, iz, salt), c = hash3(ix, 0, iz + 1, salt), d = hash3(ix + 1, 0, iz + 1, salt);
        return a + (b - a) * tx + (c - a) * tz + (a - b - c + d) * tx * tz;
    }
}
