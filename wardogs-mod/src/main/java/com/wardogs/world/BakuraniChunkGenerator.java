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
                boolean road = t != null && t.isRoad(wx, wz) && mat != BakuraniTerrain.SNOW;
                BlockState top = road ? Blocks.DIRT_PATH.defaultBlockState() : top(mat);
                BlockState fill = filler(mat);
                for (int y = MIN_Y; y <= sy; y++) {
                    BlockState s = y < BEDROCK_BELOW ? bedrock : y == sy ? top : y >= sy - 3 ? fill : stone;
                    pos.set(baseX + dx, y, baseZ + dz);
                    chunk.setBlockState(pos, s, false);
                    oceanFloor.update(dx, y, dz, s);
                    surface.update(dx, y, dz, s);
                }
            }
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
                double density = mat == BakuraniTerrain.FOREST ? 1.0 / 24 : mat == BakuraniTerrain.GRASS ? 1.0 / 900 : 0;
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
