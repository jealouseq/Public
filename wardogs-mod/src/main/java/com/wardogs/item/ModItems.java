package com.wardogs.item;

import com.wardogs.WardogsMod;
import com.wardogs.balance.ArmorDef;
import com.wardogs.balance.Balance;
import com.wardogs.balance.WeaponDef;
import net.minecraft.core.registries.Registries;
import net.minecraft.network.chat.Component;
import net.minecraft.world.item.CreativeModeTab;
import net.minecraft.world.item.Item;
import net.neoforged.neoforge.registries.DeferredHolder;
import net.neoforged.neoforge.registries.DeferredItem;
import net.neoforged.neoforge.registries.DeferredRegister;

import java.util.LinkedHashMap;
import java.util.Map;

public final class ModItems {
    public static final DeferredRegister.Items ITEMS = DeferredRegister.createItems(WardogsMod.MOD_ID);
    public static final DeferredRegister<CreativeModeTab> TABS = DeferredRegister.create(Registries.CREATIVE_MODE_TAB, WardogsMod.MOD_ID);

    /** weapon id -> registered item. */
    public static final Map<String, DeferredItem<GunItem>> GUNS = new LinkedHashMap<>();

    static {
        for (WeaponDef def : Balance.get().weapons()) {
            String id = def.id();
            GUNS.put(id, ITEMS.register(id, () -> new GunItem(new Item.Properties()
                    .component(ModComponents.AMMO.get(), def.magazine()), id)));
        }
    }

    /** armor id (helmet_l1 ... vest_l4) -> registered item. */
    public static final Map<String, DeferredItem<ArmorPieceItem>> ARMOR = new LinkedHashMap<>();

    static {
        for (ArmorDef def : Balance.get().armor()) {
            String id = def.id();
            ARMOR.put(id, ITEMS.register(id, () -> new ArmorPieceItem(new Item.Properties(), id)));
        }
    }

    /** A fresh stack for any buyable id (weapon or armor), or {@code null}. */
    public static net.minecraft.world.item.ItemStack stack(String id) {
        if (GUNS.containsKey(id)) {
            return GUNS.get(id).get().newStack();
        }
        if (ARMOR.containsKey(id)) {
            return new net.minecraft.world.item.ItemStack(ARMOR.get(id).get());
        }
        return null;
    }

    /** Shop price of a buyable id, or -1 if unknown. */
    public static int price(String id) {
        if (GUNS.containsKey(id)) {
            return Balance.get().weapon(id).price();
        }
        if (ARMOR.containsKey(id)) {
            return Balance.get().armor(id).price();
        }
        return -1;
    }

    public static java.util.List<String> buyableIds() {
        java.util.List<String> ids = new java.util.ArrayList<>(GUNS.keySet());
        ids.addAll(ARMOR.keySet());
        return ids;
    }

    public static final DeferredHolder<CreativeModeTab, CreativeModeTab> TAB = TABS.register("main", () ->
            CreativeModeTab.builder()
                    .title(Component.translatable("itemGroup.wardogs"))
                    .icon(() -> GUNS.get("m4").get().newStack())
                    .displayItems((params, out) -> {
                        GUNS.values().forEach(g -> out.accept(g.get().newStack()));
                        ARMOR.values().forEach(a -> out.accept(a.get()));
                    })
                    .build());

    private ModItems() {}
}
