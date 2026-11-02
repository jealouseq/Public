package com.wardogs.world;

import com.mojang.serialization.MapCodec;
import com.mojang.serialization.codecs.RecordCodecBuilder;
import net.minecraft.core.BlockPos;
import net.minecraft.server.level.WorldGenRegion;
import net.minecraft.world.level.BlockGetter;
import net.minecraft.world.level.LevelHeightAccessor;
import net.minecraft.world.level.NoiseColumn;
import net.minecraft.world.level.StructureManager;
import net.minecraft.world.level.biome.BiomeManager;
import net.minecraft.world.level.biome.BiomeSource;
import net.minecraft.world.level.block.Blocks;
import net.minecraft.world.level.block.state.BlockState;
import net.minecraft.world.level.chunk.ChunkAccess;
import net.minecraft.world.level.chunk.ChunkGenerator;
import net.minecraft.world.level.levelgen.GenerationStep;
import net.minecraft.world.level.levelgen.Heightmap;
import net.minecraft.world.level.levelgen.RandomState;
import net.minecraft.world.level.levelgen.blending.Blender;

import java.util.List;
import java.util.concurrent.CompletableFuture;

/** Builds the Bakurani valley from elevation, material and road data; a flat plain when no data is installed. */
public class BakuraniChunkGenerator extends ChunkGenerator {
    public static final MapCodec<BakuraniChunkGenerator> CODEC = RecordCodecBuilder.mapCodec(i -> i.group(
            BiomeSource.CODEC.fieldOf("biome_source").forGetter(g -> g.biomeSource)
    ).apply(i, BakuraniChunkGenerator::new));

    public static final int MIN_Y = 0;
    public static final int HEIGHT = 704;
    private static final int BEDROCK_BELOW = 2;

    public BakuraniChunkGenerator(BiomeSource biomeSource) {
        super(biomeSource);
    }

    @Override
    protected MapCodec<? extends ChunkGenerator> codec() {
        return CODEC;
    }

    /** The block palette for a material class (top block, filler below it). */
    static BlockState top(int material) {
        return switch (material) {
            case BakuraniTerrain.SNOW -> Blocks.SNOW_BLOCK.defaultBlockState();
            case BakuraniTerrain.ROCK -> Blocks.STONE.defaultBlockState();
            case BakuraniTerrain.GRAVEL -> Blocks.GRAVEL.defaultBlockState();
            case BakuraniTerrain.FOREST -> Blocks.PODZOL.defaultBlockState();
            case 1, 2, 3, 6, 8, 11 -> Blocks.COARSE_DIRT.defaultBlockState();
            default -> Blocks.GRASS_BLOCK.defaultBlockState();
        };
    }

    static BlockState filler(int material) {
        return switch (material) {
            case BakuraniTerrain.ROCK, BakuraniTerrain.SNOW -> Blocks.STONE.defaultBlockState();
            case BakuraniTerrain.GRAVEL -> Blocks.GRAVEL.defaultBlockState();
            default -> Blocks.DIRT.defaultBlockState();
        };
    }

    @Override
    public CompletableFuture<ChunkAccess> fillFromNoise(Blender blender, RandomState random, StructureManager structures, ChunkAccess chunk) {
        BakuraniTerrain t = BakuraniWorld.terrain();
        Heightmap oceanFloor = chunk.getOrCreateHeightmapUnprimed(Heightmap.Types.OCEAN_FLOOR_WG);
        Heightmap surface = chunk.getOrCreateHeightmapUnprimed(Heightmap.Types.WORLD_SURFACE_WG);
        BlockPos.MutableBlockPos pos = new BlockPos.MutableBlockPos();
        int baseX = chunk.getPos().getMinBlockX(), baseZ = chunk.getPos().getMinBlockZ();
        BlockState stone = Blocks.STONE.defaultBlockState();
        BlockState bedrock = Blocks.BEDROCK.defaultBlockState();
        for (int dx = 0; dx < 16; dx++) {
            for (int dz = 0; dz < 16; dz++) {
                double wx = baseX + dx + 0.5, wz = baseZ + dz + 0.5;
                int sy = t == null ? 64 : (BakuraniTerrain.inside(wx, wz) ? t.surfaceY(wx, wz) : 24);
                int mat = t == null ? BakuraniTerrain.GRASS : t.material(wx, wz);
                int cov = t == null ? BakuraniTerrain.COVER_NONE : t.cover(wx, wz);
                boolean road = t != null && t.isRoad(wx, wz) && mat != BakuraniTerrain.SNOW;
                boolean inside = t != null && BakuraniTerrain.inside(wx, wz);
                BlockState top = road ? (t.roadClass(wx, wz) >= 2 ? Blocks.GRAY_CONCRETE.defaultBlockState() : Blocks.DIRT_PATH.defaultBlockState())
                        : top(mat);
                BlockState fill = filler(mat);
                if (inside && !road && (mat == BakuraniTerrain.GRASS || mat == BakuraniTerrain.FOREST || mat == BakuraniTerrain.GRAVEL)
                        && slopeAt(t, wx, wz, sy) >= ROCK_SLOPE) {
                    top = hash(baseX + dx, baseZ + dz) < 0.35 ? Blocks.ANDESITE.defaultBlockState() : Blocks.STONE.defaultBlockState();
                    fill = Blocks.STONE.defaultBlockState();
                }
                int water = -1;
                boolean crop = false;
                if (inside && !road) {
                    switch (cov) {
                        case BakuraniTerrain.COVER_WATER -> {
                            if (mat != BakuraniTerrain.SNOW) {
                                water = waterLevel(t, wx, wz, sy);
                                sy = water - 2;
                                top = Blocks.GRAVEL.defaultBlockState();
                                fill = Blocks.GRAVEL.defaultBlockState();
                            }
                        }
                        case BakuraniTerrain.COVER_FOREST -> {
                            if (mat == BakuraniTerrain.GRASS || mat == BakuraniTerrain.FOREST) {
                                top = Blocks.PODZOL.defaultBlockState();
                            }
                        }
                        case BakuraniTerrain.COVER_URBAN -> {
                            if (isDirtClass(mat)) {
                                top = pavement(baseX + dx, baseZ + dz);
                            }
                        }
                        case BakuraniTerrain.COVER_FIELD -> {
                            if (mat == BakuraniTerrain.GRASS) {
                                if (((baseZ + dz) & 3) == 0) {
                                    top = Blocks.COARSE_DIRT.defaultBlockState();
                                } else {
                                    top = Blocks.FARMLAND.defaultBlockState();
                                    crop = true;
                                }
                            }
                        }
                        default -> { }
                    }
                }
                for (int y = MIN_Y; y <= sy; y++) {
                    BlockState s = y < BEDROCK_BELOW ? bedrock : y == sy ? top : y >= sy - 3 ? fill : stone;
                    pos.set(baseX + dx, y, baseZ + dz);
                    chunk.setBlockState(pos, s, false);
                    oceanFloor.update(dx, y, dz, s);
                    surface.update(dx, y, dz, s);
                }
                BlockState water0 = Blocks.WATER.defaultBlockState();
                for (int y = sy + 1; y <= water; y++) {
                    pos.set(baseX + dx, y, baseZ + dz);
                    chunk.setBlockState(pos, water0, false);
                    surface.update(dx, y, dz, water0);
                }
                if (crop) {
                    double hv = hash(baseX + dx * 7, baseZ + dz * 13);
                    BlockState above = hv < 0.004 ? Blocks.HAY_BLOCK.defaultBlockState()
                            : Blocks.WHEAT.defaultBlockState().setValue(net.minecraft.world.level.block.CropBlock.AGE, 3 + (int) (hv * 40) % 5);
                    pos.set(baseX + dx, sy + 1, baseZ + dz);
                    chunk.setBlockState(pos, above, false);
                    surface.update(dx, sy + 1, dz, above);
                }
            }
        }
        if (t != null) {
            placeBuildings(chunk, t, oceanFloor, surface);
        }
        if (t != null) {
            plantTrees(chunk, t, surface);
        }
        return CompletableFuture.completedFuture(chunk);
    }

    /** Cheap deterministic hash of a column, uniform in [0,1). */
    static double hash(int x, int z) {
        long h = x * 73856093L ^ z * 19349663L ^ 0x9E3779B97F4A7C15L;
        h ^= h >>> 33;
        h *= 0xFF51AFD7ED558CCDL;
        h ^= h >>> 33;
        return (h & 0xFFFFFFL) / (double) 0x1000000;
    }

    /** Paving in patches of 4 x 4 blocks: mostly andesite, some stone, gravel and cobblestone, so a plaza is not one flat colour. */
    public static BlockState pavement(int x, int z) {
        double v = hash(Math.floorDiv(x, 4), Math.floorDiv(z, 4));
        if (v < 0.62) {
            return Blocks.ANDESITE.defaultBlockState();
        }
        if (v < 0.80) {
            return Blocks.STONE.defaultBlockState();
        }
        if (v < 0.92) {
            return Blocks.GRAVEL.defaultBlockState();
        }
        return Blocks.COBBLESTONE.defaultBlockState();
    }

    /** Steepness (blocks of height change per block) at which grass gives way to bare rock. */
    static final double ROCK_SLOPE = 0.5;

    /** Largest height difference to the neighbours three blocks away, per block. */
    static double slopeAt(BakuraniTerrain t, double x, double z, int sy) {
        int worst = 0;
        for (int[] o : new int[][]{{3, 0}, {-3, 0}, {0, 3}, {0, -3}}) {
            worst = Math.max(worst, Math.abs(t.surfaceY(x + o[0], z + o[1]) - sy));
        }
        return worst / 3.0;
    }

    private static boolean isDirtClass(int mat) {
        return mat == 1 || mat == 2 || mat == 3 || mat == 6 || mat == 8 || mat == 11;
    }

    /** Water stands at the lowest ground level within 12 m, so a river never hangs above its banks. */
    private static int waterLevel(BakuraniTerrain t, double x, double z, int sy) {
        int level = sy;
        for (int ox = -12; ox <= 12; ox += 6) {
            for (int oz = -12; oz <= 12; oz += 6) {
                level = Math.min(level, t.surfaceY(x + ox, z + oz));
            }
        }
        return Math.max(level, 4);
    }

    /** Levels the ground under every house that overlaps this chunk and builds the slice that falls inside it. */
    private void placeBuildings(ChunkAccess chunk, BakuraniTerrain t, Heightmap oceanFloor, Heightmap surface) {
        BlockPos.MutableBlockPos pos = new BlockPos.MutableBlockPos();
        int baseX = chunk.getPos().getMinBlockX(), baseZ = chunk.getPos().getMinBlockZ();
        for (BakuraniTerrain.Building b : t.buildingsIn(baseX, baseZ, baseX + 15, baseZ + 15)) {
            int x0 = b.minX(), z0 = b.minZ();
            int gy = groundFloor(t, b);
            int height = BuildingGen.height(b);
            for (int dx = 0; dx < 16; dx++) {
                for (int dz = 0; dz < 16; dz++) {
                    int i = baseX + dx - x0, j = baseZ + dz - z0;
                    if (i < 0 || j < 0 || i >= b.w() || j > b.d()) {
                        continue;
                    }
                    for (int k = -BuildingGen.FOUNDATION; k <= height; k++) {
                        BlockState s = BuildingGen.at(b, i, j, k);
                        if (s == null) {
                            continue;
                        }
                        int y = gy + k;
                        if (y <= 1 || y >= HEIGHT) {
                            continue;
                        }
                        pos.set(baseX + dx, y, baseZ + dz);
                        chunk.setBlockState(pos, s, false);
                        oceanFloor.update(dx, y, dz, s);
                        surface.update(dx, y, dz, s);
                    }
                    // clear anything left of the old terrain above the roof line (hills on the footprint)
                    for (int y = gy + height + 1; y <= gy + height + 6; y++) {
                        pos.set(baseX + dx, y, baseZ + dz);
                        if (!chunk.getBlockState(pos).isAir()) {
                            chunk.setBlockState(pos, Blocks.AIR.defaultBlockState(), false);
                        }
                    }
                }
            }
        }
    }

    /** Ground-floor level of a house: the highest ground under its corners and centre, so no corner hangs in the air. */
    static int groundFloor(BakuraniTerrain t, BakuraniTerrain.Building b) {
        int x0 = b.minX(), z0 = b.minZ();
        int y = t.surfaceY(b.x(), b.z());
        y = Math.max(y, t.surfaceY(x0, z0));
        y = Math.max(y, t.surfaceY(x0 + b.w(), z0));
        y = Math.max(y, t.surfaceY(x0, z0 + b.d()));
        y = Math.max(y, t.surfaceY(x0 + b.w(), z0 + b.d()));
        return y;
    }

    /** Spruces on forest-floor ground (dense) and sparsely on meadows. Trees stay inside their chunk so no leaf is cut at an edge. */
    private void plantTrees(ChunkAccess chunk, BakuraniTerrain t, Heightmap surface) {
        BlockPos.MutableBlockPos pos = new BlockPos.MutableBlockPos();
        BlockState log = Blocks.SPRUCE_LOG.defaultBlockState();
        BlockState leaves = Blocks.SPRUCE_LEAVES.defaultBlockState().setValue(net.minecraft.world.level.block.LeavesBlock.PERSISTENT, true);
        int baseX = chunk.getPos().getMinBlockX(), baseZ = chunk.getPos().getMinBlockZ();
        for (int dx = 3; dx <= 12; dx++) {
            for (int dz = 3; dz <= 12; dz++) {
                int wx = baseX + dx, wz = baseZ + dz;
                if (!BakuraniTerrain.inside(wx + 0.5, wz + 0.5) || t.isRoad(wx + 0.5, wz + 0.5)) {
                    continue;
                }
                int mat = t.material(wx + 0.5, wz + 0.5);
                int cov = t.cover(wx + 0.5, wz + 0.5);
                double density;
                if (mat == BakuraniTerrain.SNOW || cov == BakuraniTerrain.COVER_WATER || cov == BakuraniTerrain.COVER_FIELD
                        || cov == BakuraniTerrain.COVER_URBAN) {
                    density = 0;
                } else if (cov == BakuraniTerrain.COVER_FOREST) {
                    density = mat == BakuraniTerrain.ROCK ? 1.0 / 45 : 1.0 / 20;
                } else {
                    density = mat == BakuraniTerrain.FOREST ? 1.0 / 24 : mat == BakuraniTerrain.GRASS ? 1.0 / 900 : 0;
                }
                if (density > 0 && (t.nearBuilding(wx, wz, 3) || slopeAt(t, wx + 0.5, wz + 0.5, t.surfaceY(wx + 0.5, wz + 0.5)) >= ROCK_SLOPE)) {
                    density = 0;
                }
                double hv = hash(wx, wz);
                if (hv >= density) {
                    continue;
                }
                int sy = t.surfaceY(wx + 0.5, wz + 0.5);
                int trunk = 5 + (int) (hash(wz, wx) * 5);
                if (sy + trunk + 3 >= HEIGHT) {
                    continue;
                }
                for (int y = 1; y <= trunk; y++) {
                    pos.set(wx, sy + y, wz);
                    chunk.setBlockState(pos, log, false);
                    surface.update(dx, sy + y, dz, log);
                }
                for (int layer = 0; layer < 6; layer++) {
                    int y = sy + trunk - 4 + layer + 1;
                    int radius = layer >= 5 ? 0 : layer >= 3 ? 1 : 2;
                    for (int ox = -radius; ox <= radius; ox++) {
                        for (int oz = -radius; oz <= radius; oz++) {
                            if (Math.abs(ox) + Math.abs(oz) > radius + 1 || (ox == 0 && oz == 0 && layer < 5 && y <= sy + trunk)) {
                                continue;
                            }
                            pos.set(wx + ox, y, wz + oz);
                            if (chunk.getBlockState(pos).isAir()) {
                                chunk.setBlockState(pos, leaves, false);
                                surface.update(dx + ox, y, dz + oz, leaves);
                            }
                        }
                    }
                }
            }
        }
    }

    @Override
    public void applyCarvers(WorldGenRegion region, long seed, RandomState random, BiomeManager biomes, StructureManager structures,
                             ChunkAccess chunk, GenerationStep.Carving step) {
    }

    @Override
    public void buildSurface(WorldGenRegion region, StructureManager structures, RandomState random, ChunkAccess chunk) {
    }

    @Override
    public void spawnOriginalMobs(WorldGenRegion region) {
    }

    @Override
    public int getGenDepth() {
        return HEIGHT;
    }

    @Override
    public int getSeaLevel() {
        return 0;
    }

    @Override
    public int getMinY() {
        return MIN_Y;
    }

    @Override
    public int getBaseHeight(int x, int z, Heightmap.Types type, LevelHeightAccessor level, RandomState random) {
        BakuraniTerrain t = BakuraniWorld.terrain();
        int sy = t == null ? 64 : (BakuraniTerrain.inside(x + 0.5, z + 0.5) ? t.surfaceY(x + 0.5, z + 0.5) : 24);
        return sy + 1;
    }

    @Override
    public NoiseColumn getBaseColumn(int x, int z, LevelHeightAccessor level, RandomState random) {
        int top = getBaseHeight(x, z, Heightmap.Types.WORLD_SURFACE_WG, level, random);
        BlockState[] column = new BlockState[level.getHeight()];
        for (int i = 0; i < column.length; i++) {
            column[i] = level.getMinBuildHeight() + i < top ? Blocks.STONE.defaultBlockState() : Blocks.AIR.defaultBlockState();
        }
        return new NoiseColumn(level.getMinBuildHeight(), column);
    }

    @Override
    public void addDebugScreenInfo(List<String> info, RandomState random, BlockPos pos) {
        info.add("Bakurani: surface y " + (getBaseHeight(pos.getX(), pos.getZ(), Heightmap.Types.WORLD_SURFACE_WG, null, random) - 1));
    }
}
