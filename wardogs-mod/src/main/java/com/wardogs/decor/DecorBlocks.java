package com.wardogs.decor;

import com.google.gson.Gson;
import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import com.wardogs.WardogsMod;
import net.minecraft.core.registries.Registries;
import net.minecraft.world.item.BlockItem;
import net.minecraft.world.item.Item;
import net.minecraft.world.level.block.Block;
import net.minecraft.world.level.block.SoundType;
import net.minecraft.world.level.block.state.BlockBehaviour;
import net.neoforged.neoforge.registries.DeferredBlock;
import net.neoforged.neoforge.registries.DeferredItem;
import net.neoforged.neoforge.registries.DeferredRegister;

import java.io.InputStreamReader;
import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

/**
 * The decor blocks, defined by data/wardogs/decor/blocks.json (written by research/tools/gen_decor.py with the models): collision
 * boxes and light level per block. Each is a {@link DecorBlock} with a block item.
 */
public final class DecorBlocks {
    public static final DeferredRegister.Blocks BLOCKS = DeferredRegister.createBlocks(WardogsMod.MOD_ID);
    public static final DeferredRegister.Items ITEMS = DeferredRegister.createItems(WardogsMod.MOD_ID);

    public static final Map<String, DeferredBlock<DecorBlock>> ALL = new LinkedHashMap<>();
    public static final Map<String, DeferredItem<BlockItem>> ITEM_OF = new LinkedHashMap<>();

    static {
        try (var in = DecorBlocks.class.getResourceAsStream("/data/wardogs/decor/blocks.json")) {
            JsonObject root = new Gson().fromJson(new InputStreamReader(in), JsonObject.class);
            for (var e : root.entrySet()) {
                String id = e.getKey();
                JsonObject o = e.getValue().getAsJsonObject();
                int light = o.get("light").getAsInt();
                boolean solid = o.get("solid").getAsBoolean();
                List<double[]> boxes = new ArrayList<>();
                for (var b : o.getAsJsonArray("boxes")) {
                    JsonArray a = b.getAsJsonArray();
                    boxes.add(new double[]{a.get(0).getAsDouble(), a.get(1).getAsDouble(), a.get(2).getAsDouble(),
                            a.get(3).getAsDouble(), a.get(4).getAsDouble(), a.get(5).getAsDouble()});
                }
                var block = BLOCKS.register(id, () -> new DecorBlock(BlockBehaviour.Properties.of().strength(0.8f).sound(SoundType.WOOD)
                        .noOcclusion().lightLevel(s -> light).forceSolidOff(), boxes, solid));
                ALL.put(id, block);
                ITEM_OF.put(id, ITEMS.registerSimpleBlockItem(id, block));
            }
        } catch (java.io.IOException | NullPointerException ex) {
            throw new IllegalStateException("Cannot read the decor block list", ex);
        }
    }

    private DecorBlocks() {}

    /** The block for an id like {@code decor_chair}, or null. */
    public static Block get(String id) {
        var b = ALL.get(id);
        return b == null ? null : b.get();
    }

    /** A default-state block facing {@code dir}. */
    public static net.minecraft.world.level.block.state.BlockState state(String id, net.minecraft.core.Direction dir) {
        return get(id).defaultBlockState().setValue(DecorBlock.FACING, dir);
    }
}
