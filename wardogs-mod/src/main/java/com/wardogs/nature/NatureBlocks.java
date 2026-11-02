package com.wardogs.nature;

import com.wardogs.WardogsMod;
import net.minecraft.world.item.BlockItem;
import net.minecraft.world.level.block.Blocks;
import net.minecraft.world.level.block.LeavesBlock;
import net.minecraft.world.level.block.RotatedPillarBlock;
import net.minecraft.world.level.block.state.BlockBehaviour;
import net.minecraft.world.level.block.state.BlockState;
import net.neoforged.neoforge.registries.DeferredBlock;
import net.neoforged.neoforge.registries.DeferredItem;
import net.neoforged.neoforge.registries.DeferredRegister;

import java.util.EnumMap;
import java.util.Map;

/**
 * The tree materials of Bakurani: for every species a painted foliage block (a persistent leaf block with its own cut-out texture, so
 * no tint and no decay) and a bark pillar. The world generator builds its trees from these instead of the vanilla spruce and birch.
 */
public final class NatureBlocks {
    public static final DeferredRegister.Blocks BLOCKS = DeferredRegister.createBlocks(WardogsMod.MOD_ID);
    public static final DeferredRegister.Items ITEMS = DeferredRegister.createItems(WardogsMod.MOD_ID);

    public enum Species {
        FIR, PINE, BIRCH, ASPEN, OAK, BEECH;

        public String id() {
            return name().toLowerCase(java.util.Locale.ROOT);
        }
    }

    public static final Map<Species, DeferredBlock<LeavesBlock>> FOLIAGE = new EnumMap<>(Species.class);
    public static final Map<Species, DeferredBlock<RotatedPillarBlock>> BARK = new EnumMap<>(Species.class);
    public static final Map<String, DeferredItem<BlockItem>> ITEM_OF = new java.util.LinkedHashMap<>();

    static {
        for (Species s : Species.values()) {
            var foliage = BLOCKS.register(s.id() + "_foliage", () -> new LeavesBlock(BlockBehaviour.Properties.ofFullCopy(Blocks.OAK_LEAVES)));
            var bark = BLOCKS.register(s.id() + "_bark", () -> new RotatedPillarBlock(BlockBehaviour.Properties.ofFullCopy(Blocks.OAK_LOG)));
            FOLIAGE.put(s, foliage);
            BARK.put(s, bark);
            ITEM_OF.put(s.id() + "_foliage", ITEMS.registerSimpleBlockItem(s.id() + "_foliage", foliage));
            ITEM_OF.put(s.id() + "_bark", ITEMS.registerSimpleBlockItem(s.id() + "_bark", bark));
        }
    }

    private NatureBlocks() {}

    /** Foliage that never decays: world-generated and player-placed leaves keep the PERSISTENT flag. */
    public static BlockState foliage(Species s) {
        return FOLIAGE.get(s).get().defaultBlockState().setValue(LeavesBlock.PERSISTENT, true);
    }

    public static BlockState bark(Species s, net.minecraft.core.Direction.Axis axis) {
        return BARK.get(s).get().defaultBlockState().setValue(RotatedPillarBlock.AXIS, axis);
    }
}
