package com.wardogs.world;

import net.minecraft.core.Direction;
import net.minecraft.world.level.block.Blocks;
import net.minecraft.world.level.block.DoorBlock;
import net.minecraft.world.level.block.state.properties.DoubleBlockHalf;
import net.minecraft.world.level.block.LadderBlock;
import net.minecraft.world.level.block.StairBlock;
import net.minecraft.world.level.block.state.BlockState;

/**
 * Procedural houses for Bakurani. {@link #at} answers "which block belongs at footprint cell (i, j), height k above the ground floor",
 * so a chunk can fill in only its own slice of a building. Each storey is 3 blocks of clear height plus one slab; houses with a short
 * side up to 15 blocks get a gabled roof, bigger ones a flat roof with a parapet. Multi-storey houses have a ladder in the north-west
 * corner, windows every third block and a doorway in the middle of the south wall.
 */
public final class BuildingGen {
    /** How far below the ground floor the foundation reaches. */
    public static final int FOUNDATION = 6;

    private BuildingGen() {}

    private static final BlockState[] PLASTER_RED = {Blocks.WHITE_TERRACOTTA.defaultBlockState(), Blocks.SMOOTH_SANDSTONE.defaultBlockState(),
            Blocks.WHITE_TERRACOTTA.defaultBlockState()};
    private static final BlockState[] PLASTER_GREY = {Blocks.LIGHT_GRAY_CONCRETE.defaultBlockState(), Blocks.STONE_BRICKS.defaultBlockState(),
            Blocks.LIGHT_GRAY_CONCRETE.defaultBlockState()};

    public static int height(BakuraniTerrain.Building b) {
        int top = 4 * b.floors();
        int shortSide = Math.min(b.w(), b.d());
        return top + (shortSide > 15 ? 1 : (Math.max(1, (Math.min(b.w(), b.d()) - 1) / 2)) + 3);
    }

    private static int style(BakuraniTerrain.Building b) {
        return Math.abs((int) (b.x() * 31 + b.z() * 17));
    }

    /** Red-roofed houses of up to two storeys that hash into the chalet style are timber-framed on a stone base. */
    private static boolean chalet(BakuraniTerrain.Building b) {
        return b.kind() == 1 && b.floors() <= 2 && style(b) % 2 == 0;
    }

    private static BlockState wall(BakuraniTerrain.Building b) {
        if (b.kind() == 2) {
            return (style(b) % 2 == 0 ? Blocks.SPRUCE_PLANKS : Blocks.DARK_OAK_PLANKS).defaultBlockState();
        }
        return (b.kind() == 1 ? PLASTER_RED : PLASTER_GREY)[style(b) % 3];
    }

    /** Wall block at a given height: chalets use a stone base, spruce planks above and log corner posts. */
    private static BlockState wallAt(BakuraniTerrain.Building b, int i, int j, int k) {
        if (!chalet(b)) {
            return wall(b);
        }
        boolean corner = (i == 0 || i == b.w() - 1) && (j == 0 || j == b.d() - 1);
        if (corner) {
            return Blocks.STRIPPED_SPRUCE_LOG.defaultBlockState();
        }
        return k <= 1 ? Blocks.COBBLESTONE.defaultBlockState() : Blocks.SPRUCE_PLANKS.defaultBlockState();
    }

    /** Where a world position falls in a building's footprint: cell (i, j) and whether it lies in the wall band. */
    public record Cell(int i, int j, boolean edge) {}

    /** Wall thickness in blocks: 1 for an axis-aligned house, up to about 1.4 on a diagonal so no gaps open between diagonal blocks. */
    public static double wallThickness(BakuraniTerrain.Building b) {
        return 1.0 + 0.42 * Math.abs(Math.sin(Math.toRadians(2.0 * b.angle())));
    }

    /** The footprint cell under the block centre (px, pz), or {@code null} outside the rotated rectangle (the doorstep row counts as inside). */
    public static Cell cell(BakuraniTerrain.Building b, double px, double pz) {
        double th = Math.toRadians(b.angle()), c = Math.cos(th), s = Math.sin(th);
        double rx = px - b.x(), rz = pz - b.z();
        double fu = rx * c + rz * s + b.w() / 2.0, fv = -rx * s + rz * c + b.d() / 2.0;
        int i = (int) Math.floor(fu), j = (int) Math.floor(fv);
        if (i < 0 || i >= b.w() || j < 0 || j > b.d()) {
            return null;
        }
        double t = wallThickness(b);
        boolean edge = fu < t || fu > b.w() - t || fv < t || (j < b.d() && fv > b.d() - t);
        return new Cell(i, j, edge);
    }

    /** The block at cell (i, j) of the footprint and height k above the ground floor; {@code null} leaves the existing block. */
    public static BlockState at(BakuraniTerrain.Building b, int i, int j, int k) {
        return at(b, i, j, k, i == 0 || j == 0 || i == b.w() - 1 || j == b.d() - 1);
    }

    /** As {@link #at(BakuraniTerrain.Building, int, int, int)} with the wall band decided by the caller (thicker on rotated houses). */
    public static BlockState at(BakuraniTerrain.Building b, int i, int j, int k, boolean edge) {
        int w = b.w(), d = b.d(), floors = b.floors(), top = 4 * floors;
        if (b.kind() == 2 && k == 0 && !edge && j < d) {
            return Blocks.DARK_OAK_PLANKS.defaultBlockState();
        }
        if (j == d) { // a three-wide doorstep in front of the door, level with the floor, with a little porch roof on two posts and a lantern
            if (Math.abs(i - w / 2) > 1) {
                return null;
            }
            if (k == 0) {
                return Blocks.STONE_BRICK_SLAB.defaultBlockState().setValue(
                        net.minecraft.world.level.block.SlabBlock.TYPE, net.minecraft.world.level.block.state.properties.SlabType.TOP);
            }
            if (b.kind() == 2) {
                return null;
            }
            if ((k == 1 || k == 2) && Math.abs(i - w / 2) == 1) {
                return Blocks.SPRUCE_FENCE.defaultBlockState();
            }
            if (k == 3) {
                return Blocks.SPRUCE_SLAB.defaultBlockState().setValue(net.minecraft.world.level.block.SlabBlock.TYPE,
                        net.minecraft.world.level.block.state.properties.SlabType.BOTTOM);
            }
            if (k == 2 && i == w / 2) {
                return Blocks.LANTERN.defaultBlockState().setValue(net.minecraft.world.level.block.LanternBlock.HANGING, true);
            }
            return null;
        }
        if (i < 0 || i >= w || j < 0 || j > d) {
            return null;
        }
        if (k < -FOUNDATION) {
            return null;
        }
        if (k < 0) {
            return edge ? Blocks.STONE_BRICKS.defaultBlockState() : Blocks.DIRT.defaultBlockState();
        }
        if (k == 0) {
            return edge ? Blocks.STONE_BRICKS.defaultBlockState() : Blocks.SPRUCE_PLANKS.defaultBlockState();
        }
        boolean ladderCell = floors > 1 && i == 1 && j == 1;
        if (k < top) {
            if (k % 4 == 0) { // intermediate floor slab
                if (edge) {
                    return wallAt(b, i, j, k);
                }
                return ladderCell ? ladder(b) : Blocks.SPRUCE_PLANKS.defaultBlockState();
            }
            if (!edge) {
                return ladderCell ? ladder(b) : Interiors.at(b, i, j, k % 4, k / 4);
            }
            int kk = k % 4;
            if (k < 4 && j == d - 1 && i == w / 2 && kk <= 2) {
                return Blocks.OAK_DOOR.defaultBlockState().setValue(DoorBlock.FACING, rot(b, Direction.SOUTH))
                        .setValue(DoorBlock.HALF, kk == 1 ? DoubleBlockHalf.LOWER : DoubleBlockHalf.UPPER);
            }
            if (b.kind() == 2) {
                return kk == 2 && j == 0 && i == w / 2 ? Blocks.GLASS_PANE.defaultBlockState() : wallAt(b, i, j, k);
            }
            if (kk == 2) {
                boolean alongX = j == 0 || j == d - 1;
                int pos = alongX ? i : j, len = alongX ? w : d;
                if (pos >= 2 && pos <= len - 3 && pos % 3 == 1) {
                    return Blocks.GLASS.defaultBlockState();
                }
            }
            return wallAt(b, i, j, k);
        }
        // roof
        int shortSide = Math.min(w, d);
        if (shortSide > 15) { // flat roof with a parapet
            if (k == top) {
                return Blocks.SMOOTH_STONE.defaultBlockState();
            }
            return k == top + 1 && edge ? wall(b) : null;
        }
        boolean ridgeAlongX = w >= d;
        int c = ridgeAlongX ? j : i, len = ridgeAlongX ? d : w;
        int r = Math.min(c, len - 1 - c);
        int rel = k - top;
        BlockState stair = b.kind() == 1 ? Blocks.BRICK_STAIRS.defaultBlockState()
                : b.kind() == 2 ? Blocks.SPRUCE_STAIRS.defaultBlockState() : Blocks.COBBLESTONE_STAIRS.defaultBlockState();
        BlockState full = b.kind() == 1 ? Blocks.BRICKS.defaultBlockState()
                : b.kind() == 2 ? Blocks.SPRUCE_PLANKS.defaultBlockState() : Blocks.COBBLESTONE.defaultBlockState();
        int along = ridgeAlongX ? i : j;
        int alongLen = ridgeAlongX ? w : d;
        if (b.kind() != 2 && along == 2 && c == (len - 1) / 2 && alongLen > 6 && rel > r && rel <= r + 2) {
            return Blocks.BRICKS.defaultBlockState(); // chimney
        }
        if (rel < r) {
            return wall(b); // attic fill below the roof surface
        }
        if (rel > r) {
            return null;
        }
        if (r == (len - 1) / 2) {
            return full;
        }
        boolean risingToPositive = c < len - 1 - c;
        Direction face = ridgeAlongX ? (risingToPositive ? Direction.SOUTH : Direction.NORTH) : (risingToPositive ? Direction.EAST : Direction.WEST);
        return stair.setValue(StairBlock.FACING, rot(b, face));
    }

    /** A direction given in the building's own frame (door side south), turned to the world: whole quarter turns of the building's angle. */
    public static Direction rot(BakuraniTerrain.Building b, Direction local) {
        int k = Math.floorMod((int) Math.round(b.angle() / 90.0), 4);
        return Direction.from2DDataValue((local.get2DDataValue() + k) % 4);
    }

    /** An inner wall between rooms. */
    public static BlockState partitionWall(BakuraniTerrain.Building b) {
        return (b.kind() == 1 ? Blocks.WHITE_TERRACOTTA : Blocks.SMOOTH_QUARTZ).defaultBlockState();
    }

    private static BlockState ladder(BakuraniTerrain.Building b) {
        return Blocks.LADDER.defaultBlockState().setValue(LadderBlock.FACING, rot(b, Direction.SOUTH));
    }
}
