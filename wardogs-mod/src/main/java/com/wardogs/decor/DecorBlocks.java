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
        // blocks.json is part of the mod; blocks_imported.json (collision boxes of community-mod models, see research/IMPORTS.md) exists only in
        // developer builds that imported them
        load("/data/wardogs/decor/blocks.json", true);
        load("/data/wardogs/decor/blocks_imported.json", false);
    }

    private static void load(String path, boolean required) {
        try (var in = DecorBlocks.class.getResourceAsStream(path)) {
            if (in == null) {
                if (required) {
                    throw new IllegalStateException("Cannot read the decor block list " + path);
                }
                return;
            }
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
        } catch (java.io.IOException ex) {
            throw new IllegalStateException("Cannot read the decor block list " + path, ex);
        }
    }

    private DecorBlocks() {}

    /**
     * Outdoor props that have a better model among the imported blocks: where the import exists, the generators place it instead of the
     * built-in one (the same facing, a similar size).
     */
    private static final Map<String, String> UPGRADE = Map.ofEntries(
            Map.entry("decor_ammo_crate", "imp_sa_ammo_box"), Map.entry("decor_supply_crate", "imp_sa_military_crate"),
            Map.entry("decor_crate_stack", "imp_sa_wooden_supply_crate"), Map.entry("decor_hesco_barrier", "imp_sa_hesco_barrier"),
            Map.entry("decor_oil_drum", "imp_sa_oil_barrel"), Map.entry("decor_trash_bin", "imp_sa_trash_bin"),
            Map.entry("decor_road_barrier", "imp_sa_concrete_barrier"), Map.entry("decor_traffic_cone", "imp_sa_traffic_cone"),
            Map.entry("decor_bench_park", "imp_sa_park_bench"), Map.entry("decor_tire_stack", "imp_sa_tire_block"),
            Map.entry("decor_sandbags", "imp_sa_sand_bag"), Map.entry("decor_vending_stand", "imp_sa_vending_machine"));

    /** The block for an id like {@code decor_chair}, or null. */
    public static Block get(String id) {
        var up = ALL.get(UPGRADE.get(id));
        if (up != null) {
            return up.get();
        }
        var b = ALL.get(id);
        return b == null ? null : b.get();
    }

    /** True if an imported block of this id exists in this build. */
    public static boolean has(String id) {
        return ALL.containsKey(id);
    }

    /** A default-state block facing {@code dir}. */
    public static net.minecraft.world.level.block.state.BlockState state(String id, net.minecraft.core.Direction dir) {
        return get(id).defaultBlockState().setValue(DecorBlock.FACING, dir);
    }
}
