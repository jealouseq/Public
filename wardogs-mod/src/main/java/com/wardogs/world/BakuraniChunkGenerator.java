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
        return CompletableFuture.completedFuture(chunk);
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
