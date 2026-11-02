package com.wardogs.world;

import com.wardogs.decor.DecorBlocks;
import net.minecraft.core.Direction;
import net.minecraft.world.level.block.Blocks;
import net.minecraft.world.level.block.state.BlockState;

/**
 * What stands inside a generated house: partition walls with doorways, then furniture by room type (living room with table, chairs,
 * sofa and TV; kitchen along a wall; bedrooms with cots, wardrobes and dressers) and a ceiling lamp in every room. Pure functions of
 * the building and the cell, so a chunk can fill in only its own slice. Cells are (i, j) of the building's rotated footprint, kk is the
 * height inside the storey (1 = floor level, 3 = just under the ceiling) and storey counts from 0.
 */
public final class Interiors {
    /** Partition spacing of wide buildings (apartments): a cross wall every 7 cells. */
    private static final int ROOM_W = 7;

    private Interiors() {}

    private static int h(BakuraniTerrain.Building b, int a, int c, int salt) {
        long x = (long) (b.x() * 7919) * 31 + (long) (b.z() * 104729) * 17 + a * 73856093L + c * 19349663L + salt * 83492791L;
        x ^= x >>> 29; x *= 0xBF58476D1CE4E5B9L; x ^= x >>> 32;
        return (int) (x & 0xFFFF);
    }

    private static BlockState decor(BakuraniTerrain.Building b, String id, Direction face) {
        return DecorBlocks.state("decor_" + id, BuildingGen.rot(b, face));
    }

    private static final BlockState AIR = Blocks.AIR.defaultBlockState();

    /** The room containing cell (i, j): x and y bounds (inclusive, inside the walls) and the room index. */
    private record Room(int x0, int x1, int y0, int y1, int index) {}

    private static int lo(BakuraniTerrain.Building b) {
        return (int) Math.ceil(BuildingGen.wallThickness(b));
    }

    /** Partition row (a wall running along the width) or -1 when the house is too shallow for two rows of rooms. */
    private static int partitionJ(BakuraniTerrain.Building b) {
        int l = lo(b);
        return b.d() - 2 * l >= 8 ? b.d() / 2 : -1;
    }

    /** True if cross walls at multiples of 7 are used (wide buildings). */
    private static boolean crossWalls(BakuraniTerrain.Building b) {
        return b.w() >= 15;
    }

    private static Room roomOf(BakuraniTerrain.Building b, int i, int j) {
        int l = lo(b), pj = partitionJ(b);
        int y0 = l, y1 = b.d() - 1 - l;
        int row = 0;
        if (pj >= 0) {
            if (j < pj) {
                y1 = pj - 1;
            } else {
                y0 = pj + 1;
                row = 1;
            }
        }
        int x0 = l, x1 = b.w() - 1 - l, col = 0;
        if (crossWalls(b)) {
            col = i / ROOM_W;
            x0 = Math.max(l, col * ROOM_W + (col == 0 ? 0 : 1));
            x1 = Math.min(b.w() - 1 - l, (col + 1) * ROOM_W - 1);
            if (b.w() - 1 - l - x1 < 3) {
                x1 = b.w() - 1 - l; // do not leave a closet at the end
            }
        }
        return new Room(x0, x1, y0, y1, col * 2 + row);
    }

    /** The block at an interior cell of a storey: partition wall, doorway, furniture, lamp or air. */
    public static BlockState at(BakuraniTerrain.Building b, int i, int j, int kk, int storey) {
        if (b.kind() == 2) {
            return shed(b, i, j, kk);
        }
        int pj = partitionJ(b);
        boolean xWall = crossWalls(b) && i % ROOM_W == 0 && i > 0 && i < b.w() - 1 - lo(b) - 2;
        // partition rows and cross walls with doorways
        if (pj >= 0 && j == pj) {
            boolean door = (crossWalls(b) ? i % ROOM_W == 3 : i == b.w() / 3) && kk <= 2;
            return door ? AIR : BuildingGen.partitionWall(b);
        }
        if (xWall) {
            boolean door = (j == lo(b) + 2 || j == b.d() - lo(b) - 3) && kk <= 2;
            return door ? AIR : BuildingGen.partitionWall(b);
        }
        Room r = roomOf(b, i, j);
        if (r.x1 - r.x0 < 2 || r.y1 - r.y0 < 2) {
            return AIR;
        }
        if (kk == 3) {
            int cx = (r.x0 + r.x1) / 2, cy = (r.y0 + r.y1) / 2;
            return i == cx && j == cy ? decor(b, "ceiling_lamp", Direction.NORTH) : AIR;
        }
        if (kk != 1) {
            return AIR;
        }
        // keep the entrance, the stairs and the cell in front of every doorway free
        if (storey == 0 && j >= b.d() - 1 - lo(b) - 1 && Math.abs(i - b.w() / 2) <= 1) {
            return AIR;
        }
        if (b.floors() > 1 && i <= lo(b) + 1 && j <= lo(b) + 1) {
            return AIR;
        }
        if (pj >= 0 && Math.abs(j - pj) == 1 && (crossWalls(b) ? i % ROOM_W == 3 : i == b.w() / 3)) {
            return AIR;
        }
        if (xWall(b, i + 1) || xWall(b, i - 1)) {
            if ((j == lo(b) + 2 || j == b.d() - lo(b) - 3)) {
                return AIR;
            }
        }
        BlockState f = furniture(b, r, i, j, storey);
        return f == null ? AIR : f;
    }

    private static boolean xWall(BakuraniTerrain.Building b, int i) {
        return crossWalls(b) && i % ROOM_W == 0 && i > 0 && i < b.w() - 1 - lo(b) - 2;
    }

    private static BlockState shed(BakuraniTerrain.Building b, int i, int j, int kk) {
        if (kk != 1 || j >= b.d() - 1) {
            return AIR;
        }
        int v = h(b, i, j, 1) % 8;
        if (j == 1 && i == 1) {
            return decor(b, "workbench", Direction.SOUTH);
        }
        if (j == 1 && i == b.w() - 2) {
            return decor(b, v < 4 ? "crate_stack" : "supply_crate", Direction.SOUTH);
        }
        if (i == b.w() - 2 && j == 2 && v < 4) {
            return decor(b, "jerrycan", Direction.WEST);
        }
        if (i == 1 && j == 2 && v >= 4) {
            return decor(b, "tire_stack", Direction.SOUTH);
        }
        return AIR;
    }

    /** Furniture for one cell of a room, chosen by the room's type and the building's style. */
    private static BlockState furniture(BakuraniTerrain.Building b, Room r, int i, int j, int storey) {
        int style = h(b, 0, 0, 7);
        int w = r.x1 - r.x0 + 1, d = r.y1 - r.y0 + 1;
        int cx = (r.x0 + r.x1) / 2, cy = (r.y0 + r.y1) / 2;
        // which kind of room: living rooms and kitchens on the ground floor, bedrooms elsewhere
        boolean ground = storey == 0;
        boolean front = partitionJ(b) < 0 || r.index() % 2 == 1;
        boolean living = ground && front;
        boolean kitchenRoom = ground && front && (style % 3 != 0 || partitionJ(b) < 0 && w > 4) && w >= 5;
        boolean atN = j == r.y0, atS = j == r.y1, atW = i == r.x0, atE = i == r.x1;
        int v = h(b, i, j, storey + 3) % 100;
        if (living) {
            // kitchen run along the east wall (or west, by style)
            if (kitchenRoom && d >= 4) {
                boolean kitchenEast = style % 2 == 0;
                int wallI = kitchenEast ? r.x1 : r.x0;
                Direction face = kitchenEast ? Direction.WEST : Direction.EAST;
                if (i == wallI) {
                    int n = j - r.y0;
                    if (n == 0) return decor(b, "fridge", face);
                    if (n == 1) return decor(b, "kitchen_counter", face);
                    if (n == 2) return decor(b, "stove", face);
                    if (n == 3) return decor(b, "sink", face);
                    if (n == 4 && d >= 7) return decor(b, "kitchen_counter", face);
                }
            }
            if (i == cx && j == cy && w >= 5 && d >= 5) return decor(b, "table", Direction.NORTH);
            if (w >= 5 && d >= 5) {
                if (i == cx - 1 && j == cy) return decor(b, "chair", Direction.EAST);
                if (i == cx + 1 && j == cy) return decor(b, "chair", Direction.WEST);
                if (i == cx && j == cy - 1) return decor(b, "chair_cushion", Direction.SOUTH);
                if (i == cx && j == cy + 1) return decor(b, "chair_cushion", Direction.NORTH);
                if (Math.abs(i - cx) == 1 && Math.abs(j - cy) == 1) return decor(b, "rug", Direction.NORTH);
            }
            if (atN && (i == cx || i == cx + 1) && !(kitchenRoom && style % 2 == 1 && i == r.x0)) return decor(b, style % 2 == 0 ? "sofa_red" : "sofa_blue", Direction.SOUTH);
            if (atW && j == cy && !(kitchenRoom && style % 2 == 1) && w > 5) return decor(b, "tv_set", Direction.EAST);
            if (atN && i == r.x1 - (style % 2 == 0 ? 0 : 1) && w >= 5) return decor(b, "bookshelf_tall", Direction.SOUTH);
            if (atS && i == r.x0 && (r.y1 != b.d() - 2 || i > b.w() / 2 + 1 || true)) return decor(b, "lamp_floor", Direction.NORTH);
            if (atN && atW) return decor(b, "potted_plant", Direction.SOUTH);
            if (atS && atE && v < 55) return decor(b, "potted_plant", Direction.NORTH);
            if (atE && !kitchenRoom && j == cy) return decor(b, "armchair", Direction.WEST);
            return null;
        }
        // bedrooms (and every room above the ground floor)
        if (atN && (i == r.x0 + 1 || (w >= 6 && i == r.x1 - 1))) {
            return decor(b, storey > 0 && v < 40 ? "bed_bunk" : "cot", Direction.NORTH);
        }
        if (atW && j == cy) return decor(b, "wardrobe", Direction.EAST);
        if (atE && j == cy && v < 70) return decor(b, "dresser", Direction.WEST);
        if (atE && j == cy + 1 && v < 45) return decor(b, "lamp_table", Direction.WEST);
        if (atW && j == r.y1 && v < 60) return decor(b, "bookshelf_tall", Direction.EAST);
        if (i == cx && j == cy && w >= 5 && d >= 5) return decor(b, "rug", Direction.NORTH);
        if (atS && atE && v < 50) return decor(b, "potted_plant", Direction.NORTH);
        if (atS && i == cx && w >= 5 && v < 30 && !(storey == 0 && Math.abs(i - b.w() / 2) <= 1)) return decor(b, "coffee_table", Direction.NORTH);
        return null;
    }
}
