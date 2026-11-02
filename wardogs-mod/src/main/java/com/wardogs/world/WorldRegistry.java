package com.wardogs.world;

import com.mojang.serialization.MapCodec;
import com.wardogs.WardogsMod;
import net.minecraft.core.registries.Registries;
import net.minecraft.world.level.chunk.ChunkGenerator;
import net.neoforged.neoforge.registries.DeferredRegister;

public final class WorldRegistry {
    public static final DeferredRegister<MapCodec<? extends ChunkGenerator>> GENERATORS =
            DeferredRegister.create(Registries.CHUNK_GENERATOR, WardogsMod.MOD_ID);

    static {
        GENERATORS.register("bakurani", () -> BakuraniChunkGenerator.CODEC);
    }

    private WorldRegistry() {}
}
