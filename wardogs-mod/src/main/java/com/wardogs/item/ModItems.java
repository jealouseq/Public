package com.wardogs.item;

import com.wardogs.WardogsMod;
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

    public static final DeferredHolder<CreativeModeTab, CreativeModeTab> TAB = TABS.register("main", () ->
            CreativeModeTab.builder()
                    .title(Component.translatable("itemGroup.wardogs"))
                    .icon(() -> GUNS.get("m4").get().newStack())
                    .displayItems((params, out) -> GUNS.values().forEach(g -> out.accept(g.get().newStack())))
                    .build());

    private ModItems() {}
}
