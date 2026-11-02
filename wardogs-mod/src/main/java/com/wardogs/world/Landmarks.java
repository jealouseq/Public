package com.wardogs.world;

import net.minecraft.core.BlockPos;
import net.minecraft.core.Direction;
import net.minecraft.server.level.ServerLevel;
import net.minecraft.world.level.block.Blocks;
import net.minecraft.world.level.block.state.BlockState;
import net.minecraft.world.level.chunk.status.ChunkStatus;

/** Hand-built places of the Bakurani map: the shooting range and the flags that mark the Control Zone centres. Idempotent. */
public final class Landmarks {
    /** Distances (metres) of the target lines on the range, in blocks along the lane. */
    public static final int[] TARGET_DISTANCES = {25, 50, 75, 100};
    public static final int LANES = 3;

    private Landmarks() {}

    private static void load(ServerLevel level, int x, int z) {
        level.getChunkSource().getChunk(x >> 4, z >> 4, ChunkStatus.FULL, true);
    }

    public static int buildAll(ServerLevel level, BakuraniSetup.Ground g) {
        int n = 0;
        for (BakuraniWorld.Landmark l : BakuraniWorld.sites().landmarks()) {
            boolean built = switch (l.type()) {
                case "range" -> buildRange(level, (int) Math.floor(l.x()), (int) Math.floor(l.z()), g);
                case "flag" -> buildFlag(level, (int) Math.floor(l.x()), (int) Math.floor(l.z()), g);
                default -> false;
            };
            if (built) {
                n++;
            }
        }
        return n;
    }

    /** A flagpole 14 blocks tall on a stone footing with a banner, so the Control Zone centre can be seen from afar. */
    public static boolean buildFlag(ServerLevel level, int x, int z, BakuraniSetup.Ground g) {
        load(level, x, z);
        int y = g.surfaceY(x + 0.5, z + 0.5);
        BlockPos top = new BlockPos(x, y + 14, z);
        if (level.getBlockState(top).is(Blocks.IRON_BARS)) {
            return false;
        }
        for (int dx = -1; dx <= 1; dx++) {
            for (int dz = -1; dz <= 1; dz++) {
                level.setBlock(new BlockPos(x + dx, y, z + dz), Blocks.STONE_BRICKS.defaultBlockState(), 2);
            }
        }
        for (int dy = 1; dy <= 14; dy++) {
            level.setBlock(new BlockPos(x, y + dy, z), Blocks.IRON_BARS.defaultBlockState(), 2);
        }
        level.setBlock(top, Blocks.IRON_BARS.defaultBlockState(), 2);
        for (int dy = 9; dy <= 13; dy++) { // the flag: a 4 x 5 panel hanging east of the pole
            for (int dx = 1; dx <= 4; dx++) {
                level.setBlock(new BlockPos(x + dx, y + dy, z), Blocks.WHITE_WOOL.defaultBlockState(), 2);
            }
        }
        for (int dx = 1; dx <= 4; dx++) {
            level.setBlock(new BlockPos(x + dx, y + 11, z), Blocks.RED_WOOL.defaultBlockState(), 2);
        }
        level.setBlock(new BlockPos(x, y + 15, z), Blocks.WHITE_BANNER.defaultBlockState(), 2);
        return true;
    }

    /** A shooting range: three lanes with a firing line, target boards every 25 m out to 100 m and a back wall. */
    public static boolean buildRange(ServerLevel level, int x, int z, BakuraniSetup.Ground g) {
        int length = TARGET_DISTANCES[TARGET_DISTANCES.length - 1] + 6;
        for (int dz = 0; dz <= length; dz += 8) {
            load(level, x, z - dz);
        }
        int base = g.surfaceY(x + 0.5, z + 0.5);
        BlockPos marker = new BlockPos(x, base + 1, z + 1);
        if (level.getBlockState(marker).is(Blocks.SPRUCE_SIGN) || level.getBlockState(marker).is(Blocks.LECTERN)) {
            return false;
        }
        BlockState floor = Blocks.SMOOTH_STONE.defaultBlockState(), fence = Blocks.SPRUCE_FENCE.defaultBlockState();
        int width = LANES * 3 + 1;
        int x0 = x - width / 2;
        // firing line: a stone strip with a low wall behind it
        for (int dx = 0; dx < width; dx++) {
            level.setBlock(new BlockPos(x0 + dx, g.surfaceY(x0 + dx + 0.5, z + 0.5), z), floor, 2);
            level.setBlock(new BlockPos(x0 + dx, g.surfaceY(x0 + dx + 0.5, z + 1.5), z + 1), Blocks.STONE_BRICK_WALL.defaultBlockState(), 2);
        }
        level.setBlock(marker, Blocks.LECTERN.defaultBlockState().setValue(net.minecraft.world.level.block.LecternBlock.FACING, Direction.NORTH), 2);
        // lanes run north (toward -z); targets stand at each distance
        for (int lane = 0; lane < LANES; lane++) {
            int lx = x0 + 2 + lane * 3;
            for (int d : TARGET_DISTANCES) {
                int tz = z - d;
                int ty = g.surfaceY(lx + 0.5, tz + 0.5);
                for (int dy = 1; dy <= 2; dy++) {
                    level.setBlock(new BlockPos(lx, ty + dy, tz), fence, 2);
                }
                level.setBlock(new BlockPos(lx, ty + 3, tz), Blocks.TARGET.defaultBlockState(), 2);
                level.setBlock(new BlockPos(lx, ty + 4, tz), Blocks.TARGET.defaultBlockState(), 2);
                level.setBlock(new BlockPos(lx - 1, ty + 3, tz), Blocks.TARGET.defaultBlockState(), 2);
                level.setBlock(new BlockPos(lx + 1, ty + 3, tz), Blocks.TARGET.defaultBlockState(), 2);
            }
        }
        return true;
    }
}
