package com.wardogs.world;

import com.wardogs.WardogsMod;
import com.wardogs.item.ModItems;
import com.wardogs.vendor.VendorEntities;
import com.wardogs.vendor.VendorEntity;
import net.minecraft.core.BlockPos;
import net.minecraft.core.Direction;
import net.minecraft.server.level.ServerLevel;
import net.minecraft.world.entity.MobSpawnType;
import net.minecraft.world.level.block.Blocks;
import net.minecraft.world.level.block.ChestBlock;
import net.minecraft.world.level.block.LadderBlock;
import net.minecraft.world.level.block.entity.BlockEntity;
import net.minecraft.world.level.block.entity.ChestBlockEntity;
import net.minecraft.world.level.block.state.BlockState;
import net.minecraft.world.level.chunk.status.ChunkStatus;

import java.util.HashMap;
import java.util.Map;

/** Builds the things Bakurani needs besides terrain: loot towers and vendors at their real positions. Idempotent. */
public final class BakuraniSetup {
    public static final int TOWER_HEIGHT = 12;

    private static final Map<String, Long> LAST_ROLL = new HashMap<>();

    /** Where the ground is. Defaults to the Bakurani terrain; tests supply the height of their own flat arena. */
    @FunctionalInterface
    public interface Ground {
        int surfaceY(double x, double z);
    }

    public static final Ground TERRAIN = BakuraniWorld::surfaceY;

    private BakuraniSetup() {}

    public record Result(int towers, int vendors) {}

    private static void load(ServerLevel level, double x, double z) {
        level.getChunkSource().getChunk((int) Math.floor(x) >> 4, (int) Math.floor(z) >> 4, ChunkStatus.FULL, true);
    }

    public static Result buildAll(ServerLevel level) {
        int towers = 0, vendors = 0;
        for (BakuraniWorld.Site t : BakuraniWorld.sites().towers()) {
            if (buildTower(level, t)) {
                towers++;
            }
        }
        for (BakuraniWorld.Site v : BakuraniWorld.sites().vendors()) {
            if (placeVendor(level, v)) {
                vendors++;
            }
        }
        for (BakuraniWorld.Site c : BakuraniWorld.sites().loot()) {
            placeCache(level, c, TERRAIN);
        }
        for (BakuraniWorld.Site b : BakuraniWorld.sites().boards()) {
            placeBoard(level, b, TERRAIN);
        }
        Landmarks.buildAll(level, TERRAIN);
        return new Result(towers, vendors);
    }

    /** Builds a deployment board: two posts carrying a plank board, a lectern in front. @return true if newly built. */
    public static boolean placeBoard(ServerLevel level, BakuraniWorld.Site b, Ground g) {
        load(level, b.x(), b.z());
        int bx = (int) Math.floor(b.x()), bz = (int) Math.floor(b.z());
        int y = g.surfaceY(bx + 0.5, bz + 0.5) + 1;
        BlockPos lectern = new BlockPos(bx, y, bz + 1);
        if (level.getBlockState(lectern).is(Blocks.LECTERN)) {
            return false;
        }
        BlockState post = Blocks.SPRUCE_LOG.defaultBlockState(), plank = Blocks.SPRUCE_PLANKS.defaultBlockState();
        for (int dx = -2; dx <= 2; dx++) {
            level.setBlock(new BlockPos(bx + dx, y + 2, bz), plank, 2);
            level.setBlock(new BlockPos(bx + dx, y + 3, bz), plank, 2);
        }
        for (int dy = 0; dy <= 3; dy++) {
            level.setBlock(new BlockPos(bx - 2, y + dy, bz), post, 2);
            level.setBlock(new BlockPos(bx + 2, y + dy, bz), post, 2);
        }
        level.setBlock(new BlockPos(bx, y + 1, bz), Blocks.AIR.defaultBlockState(), 2);
        level.setBlock(lectern, Blocks.LECTERN.defaultBlockState().setValue(net.minecraft.world.level.block.LecternBlock.FACING, Direction.NORTH), 2);
        return true;
    }

    /** Position of a loot cache chest: one block above the ground at the marker. */
    public static BlockPos cachePos(BakuraniWorld.Site c, Ground g) {
        int bx = (int) Math.floor(c.x()), bz = (int) Math.floor(c.z());
        return new BlockPos(bx, g.surfaceY(bx + 0.5, bz + 0.5) + 1, bz);
    }

    /** Puts a loot crate (a barrel) at a loot marker and fills it. @return true if one was placed. */
    public static boolean placeCache(ServerLevel level, BakuraniWorld.Site c, Ground g) {
        load(level, c.x(), c.z());
        BlockPos pos = cachePos(c, g);
        if (level.getBlockState(pos).is(Blocks.BARREL)) {
            return false;
        }
        level.setBlock(pos, Blocks.BARREL.defaultBlockState(), 2);
        refillCache(level, c, g);
        return true;
    }

    /** Fills an empty cache with what its marker says (bandages, a defibrillator, a weapon, a vest or a helmet). */
    public static void refillCache(ServerLevel level, BakuraniWorld.Site c, Ground g) {
        if (!(level.getBlockEntity(cachePos(c, g)) instanceof net.minecraft.world.level.block.entity.BarrelBlockEntity barrel) || !barrel.isEmpty()) {
            return;
        }
        String id = LootCaches.itemFor(c.name(), level.random);
        var stack = id == null ? null : ModItems.stack(id);
        if (stack != null) {
            stack.setCount(LootCaches.countFor(id));
            barrel.setItem(0, stack);
        }
        LAST_ROLL.put("cache:" + c.name() + c.x(), level.getGameTime());
    }

    /** Ground level the tower stands on: the highest terrain point under its 5x5 footprint. */
    static int baseY(double x, double z, Ground g) {
        int y = Integer.MIN_VALUE;
        for (int dx = -2; dx <= 2; dx++) {
            for (int dz = -2; dz <= 2; dz++) {
                y = Math.max(y, g.surfaceY(Math.floor(x) + dx + 0.5, Math.floor(z) + dz + 0.5));
            }
        }
        return y;
    }

    public static BlockPos chestPos(BakuraniWorld.Site t) {
        return chestPos(t, TERRAIN);
    }

    public static BlockPos chestPos(BakuraniWorld.Site t, Ground g) {
        int bx = (int) Math.floor(t.x()), bz = (int) Math.floor(t.z());
        return new BlockPos(bx - 1, baseY(t.x(), t.z(), g) + TOWER_HEIGHT + 1, bz - 1);
    }

    /** @return true if a tower was built (false if one already stands there). */
    public static boolean buildTower(ServerLevel level, BakuraniWorld.Site t) {
        return buildTower(level, t, TERRAIN);
    }

    public static boolean buildTower(ServerLevel level, BakuraniWorld.Site t, Ground g) {
        int bx = (int) Math.floor(t.x()), bz = (int) Math.floor(t.z());
        for (int dx = -2; dx <= 2; dx += 2) {
            for (int dz = -2; dz <= 2; dz += 2) {
                load(level, bx + dx, bz + dz);
            }
        }
        BlockPos chest = chestPos(t, g);
        if (level.getBlockState(chest).is(Blocks.CHEST)) {
            return false;
        }
        int base = baseY(t.x(), t.z(), g);
        BlockState log = Blocks.SPRUCE_LOG.defaultBlockState(), planks = Blocks.SPRUCE_PLANKS.defaultBlockState();
        BlockState fence = Blocks.SPRUCE_FENCE.defaultBlockState(), rock = Blocks.COBBLESTONE.defaultBlockState();
        for (int dx = -2; dx <= 2; dx++) {
            for (int dz = -2; dz <= 2; dz++) {
                int ground = g.surfaceY(bx + dx + 0.5, bz + dz + 0.5);
                for (int y = ground; y <= base; y++) {
                    level.setBlock(new BlockPos(bx + dx, y, bz + dz), rock, 2); // foundation up to the common base level
                }
                for (int y = base + 1; y <= base + TOWER_HEIGHT + 5; y++) {
                    level.setBlock(new BlockPos(bx + dx, y, bz + dz), Blocks.AIR.defaultBlockState(), 2); // clear the volume
                }
            }
        }
        for (int sx : new int[]{-2, 2}) {
            for (int sz : new int[]{-2, 2}) {
                for (int y = base + 1; y <= base + TOWER_HEIGHT + 4; y++) {
                    level.setBlock(new BlockPos(bx + sx, y, bz + sz), log, 2);
                }
            }
        }
        for (int dx = -2; dx <= 2; dx++) {
            for (int dz = -2; dz <= 2; dz++) {
                boolean hatch = dx == 0 && dz == 1; // opening above the ladder
                if (!hatch) {
                    level.setBlock(new BlockPos(bx + dx, base + TOWER_HEIGHT, bz + dz), planks, 2);
                }
                boolean edge = Math.abs(dx) == 2 || Math.abs(dz) == 2;
                boolean corner = Math.abs(dx) == 2 && Math.abs(dz) == 2;
                if (edge && !corner) {
                    level.setBlock(new BlockPos(bx + dx, base + TOWER_HEIGHT + 1, bz + dz), fence, 2);
                }
                level.setBlock(new BlockPos(bx + dx, base + TOWER_HEIGHT + 5, bz + dz), Blocks.SPRUCE_SLAB.defaultBlockState(), 2);
            }
        }
        // ladder up the south side: a wall column at z+2 with the ladder on its inner face
        for (int y = base + 1; y <= base + TOWER_HEIGHT; y++) {
            level.setBlock(new BlockPos(bx, y, bz + 2), planks, 2);
            level.setBlock(new BlockPos(bx, y, bz + 1), Blocks.LADDER.defaultBlockState().setValue(LadderBlock.FACING, Direction.NORTH), 2);
        }
        level.setBlock(chest, Blocks.CHEST.defaultBlockState().setValue(ChestBlock.FACING, Direction.SOUTH), 2);
        refill(level, t, g);
        return true;
    }

    /** Puts a fresh roll of loot into an empty tower chest. */
    public static void refill(ServerLevel level, BakuraniWorld.Site t, Ground g) {
        BlockEntity be = level.getBlockEntity(chestPos(t, g));
        if (!(be instanceof ChestBlockEntity chest) || !chest.isEmpty()) {
            return;
        }
        String id = TowerLoot.roll(level.random);
        if (id != null) {
            var stack = ModItems.stack(id);
            if (stack != null) {
                chest.setItem(0, stack);
            }
        }
        LAST_ROLL.put(t.name(), level.getGameTime());
    }

    /** Ticks tower respawns: an empty chest rolls again every five minutes. */
    public static void tickTowers(ServerLevel level) {
        long now = level.getGameTime();
        for (BakuraniWorld.Site t : BakuraniWorld.sites().towers()) {
            BlockPos cp = chestPos(t);
            if (!level.isLoaded(cp)) {
                continue;
            }
            if (now - LAST_ROLL.getOrDefault(t.name(), Long.MIN_VALUE / 2) >= 20 * 60 * 5) {
                refill(level, t, TERRAIN);
            }
        }
    }

    /** Loot caches respawn like tower chests, every five minutes when empty. */
    public static void tickCaches(ServerLevel level) {
        long now = level.getGameTime();
        for (BakuraniWorld.Site c : BakuraniWorld.sites().loot()) {
            BlockPos cp = cachePos(c, TERRAIN);
            if (level.isLoaded(cp) && level.getBlockState(cp).is(Blocks.BARREL)
                    && now - LAST_ROLL.getOrDefault("cache:" + c.name() + c.x(), Long.MIN_VALUE / 2) >= 20 * 60 * 5) {
                refillCache(level, c, TERRAIN);
            }
        }
    }

    /** @return true if a vendor was placed (false if one already stands there). */
    public static boolean placeVendor(ServerLevel level, BakuraniWorld.Site v) {
        return placeVendor(level, v, TERRAIN);
    }

    public static boolean placeVendor(ServerLevel level, BakuraniWorld.Site v, Ground g) {
        load(level, v.x(), v.z());
        var box = new net.minecraft.world.phys.AABB(v.x() - 1.5, level.getMinBuildHeight(), v.z() - 1.5, v.x() + 1.5, level.getMaxBuildHeight(), v.z() + 1.5);
        VendorEntity.Kind wanted = VendorEntity.Kind.fromSiteName(v.name());
        if (level.getEntitiesOfClass(VendorEntity.class, box).stream().anyMatch(e -> e.kind() == wanted)) {
            return false; // already there (two vendors of different kinds may share a spot)
        }
        VendorEntity e = VendorEntities.VENDOR.get().create(level);
        if (e == null) {
            return false;
        }
        e.setKind(VendorEntity.Kind.fromSiteName(v.name()));
        e.moveTo(v.x(), g.surfaceY(v.x(), v.z()) + 1.0, v.z(), 0f, 0f);
        e.finalizeSpawn(level, level.getCurrentDifficultyAt(e.blockPosition()), MobSpawnType.STRUCTURE, null);
        e.setKind(VendorEntity.Kind.fromSiteName(v.name()));
        level.addFreshEntity(e);
        WardogsMod.LOGGER.debug("Vendor {} placed at {}, {}", v.name(), v.x(), v.z());
        return true;
    }
}
