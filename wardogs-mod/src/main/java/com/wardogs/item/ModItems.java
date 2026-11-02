package com.wardogs.item;

import com.wardogs.WardogsMod;
import com.wardogs.balance.AmmoItemDef;
import com.wardogs.balance.ArmorDef;
import com.wardogs.balance.AttachmentDef;
import com.wardogs.balance.Balance;
import com.wardogs.balance.BuildingItemDef;
import com.wardogs.balance.ExplosiveDef;
import com.wardogs.balance.MedicalDef;
import com.wardogs.balance.VehicleDef;
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
            ARMOR.put(id, ITEMS.register(id, () -> new ArmorPieceItem(new Item.Properties().durability(def.durability()), id)));
        }
    }

    /** medical item id -> registered item. */
    public static final Map<String, DeferredItem<MedicalItem>> MEDICAL = new LinkedHashMap<>();

    static {
        for (MedicalDef def : Balance.get().medicalItems()) {
            String id = def.id();
            MEDICAL.put(id, ITEMS.register(id, () -> {
                Item.Properties p = new Item.Properties();
                if (def.charges() > 0 && def.kind() == MedicalDef.Kind.REVIVE) {
                    p.stacksTo(1).component(ModComponents.CHARGES.get(), def.charges());
                } else {
                    p.stacksTo(def.kind() == MedicalDef.Kind.BATTERY ? 16 : 8);
                }
                return new MedicalItem(p, id);
            }));
        }
    }

    /** explosive id -> registered item. */
    public static final Map<String, DeferredItem<ExplosiveItem>> EXPLOSIVES = new LinkedHashMap<>();

    static {
        for (ExplosiveDef def : Balance.get().explosiveItems()) {
            String id = def.id();
            EXPLOSIVES.put(id, ITEMS.register(id, () -> new ExplosiveItem(new Item.Properties()
                    .stacksTo(def.kind() == ExplosiveDef.Kind.DETONATOR ? 1 : 8), id)));
        }
    }

    /** hammers, supplies and the FOB kit. */
    public static final Map<String, DeferredItem<Item>> BUILDING = new LinkedHashMap<>();

    static {
        for (BuildingItemDef def : Balance.get().buildingItems()) {
            String id = def.id();
            BUILDING.put(id, ITEMS.register(id, () -> {
                Item.Properties p = new Item.Properties();
                if (def.hammerTier() != null) {
                    return new HammerItem(p.component(ModComponents.SELECTED.get(), 0), def.hammerTier());
                }
                if (def.supply() != null) {
                    return new SupplyItem(p.stacksTo(64), def.supply());
                }
                if (def.wrench()) {
                    return new WrenchItem(p);
                }
                return new FobKitItem(p);
            }));
        }
    }

    /** vehicle id -> spawn ticket item. */
    public static final Map<String, DeferredItem<VehicleItem>> VEHICLES = new LinkedHashMap<>();

    static {
        for (VehicleDef def : Balance.get().vehicles()) {
            String id = def.id();
            VEHICLES.put(id, ITEMS.register("vehicle_" + id, () -> new VehicleItem(new Item.Properties(), id)));
        }
    }

    public static final Map<String, DeferredItem<AttachmentItem>> ATTACHMENTS = new LinkedHashMap<>();
    public static final Map<String, DeferredItem<AmmoBoxItem>> AMMO_BOXES = new LinkedHashMap<>();

    static {
        for (AttachmentDef def : Balance.get().attachments()) {
            String id = def.id();
            ATTACHMENTS.put(id, ITEMS.register(id, () -> new AttachmentItem(new Item.Properties().stacksTo(8), id)));
        }
        for (AmmoItemDef def : Balance.get().ammoItems()) {
            String id = def.id();
            AMMO_BOXES.put(id, ITEMS.register(id, () -> new AmmoBoxItem(new Item.Properties().stacksTo(16), id)));
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
        if (MEDICAL.containsKey(id)) {
            return new net.minecraft.world.item.ItemStack(MEDICAL.get(id).get());
        }
        if (EXPLOSIVES.containsKey(id)) {
            return new net.minecraft.world.item.ItemStack(EXPLOSIVES.get(id).get());
        }
        if (BUILDING.containsKey(id)) {
            return new net.minecraft.world.item.ItemStack(BUILDING.get(id).get(), Balance.get().buildingItem(id).stack());
        }
        if (VEHICLES.containsKey(id)) {
            return new net.minecraft.world.item.ItemStack(VEHICLES.get(id).get());
        }
        if (ATTACHMENTS.containsKey(id)) {
            return new net.minecraft.world.item.ItemStack(ATTACHMENTS.get(id).get());
        }
        if (AMMO_BOXES.containsKey(id)) {
            return new net.minecraft.world.item.ItemStack(AMMO_BOXES.get(id).get());
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
        if (MEDICAL.containsKey(id)) {
            return Balance.get().medical(id).price();
        }
        if (EXPLOSIVES.containsKey(id)) {
            return Balance.get().explosive(id).price();
        }
        if (BUILDING.containsKey(id)) {
            return Balance.get().buildingItem(id).price();
        }
        if (VEHICLES.containsKey(id)) {
            return Balance.get().vehicle(id).price();
        }
        if (ATTACHMENTS.containsKey(id)) {
            return Balance.get().attachment(id).price();
        }
        if (AMMO_BOXES.containsKey(id)) {
            return Balance.get().ammoItem(id).price();
        }
        return -1;
    }

    public static java.util.List<String> buyableIds() {
        java.util.List<String> ids = new java.util.ArrayList<>(GUNS.keySet());
        ids.addAll(ARMOR.keySet());
        ids.addAll(MEDICAL.keySet());
        ids.addAll(EXPLOSIVES.keySet());
        ids.addAll(BUILDING.keySet());
        ids.addAll(VEHICLES.keySet());
        ids.addAll(ATTACHMENTS.keySet());
        ids.addAll(AMMO_BOXES.keySet());
        return ids;
    }

    public static final DeferredHolder<CreativeModeTab, CreativeModeTab> TAB = TABS.register("main", () ->
            CreativeModeTab.builder()
                    .title(Component.translatable("itemGroup.wardogs"))
                    .icon(() -> GUNS.get("m4").get().newStack())
                    .displayItems((params, out) -> {
                        GUNS.values().forEach(g -> out.accept(g.get().newStack()));
                        ARMOR.values().forEach(a -> out.accept(a.get()));
                        MEDICAL.values().forEach(m -> out.accept(new net.minecraft.world.item.ItemStack(m.get())));
                        EXPLOSIVES.values().forEach(x -> out.accept(new net.minecraft.world.item.ItemStack(x.get())));
                        BUILDING.values().forEach(x -> out.accept(new net.minecraft.world.item.ItemStack(x.get())));
                        VEHICLES.values().forEach(x -> out.accept(new net.minecraft.world.item.ItemStack(x.get())));
                        ATTACHMENTS.values().forEach(x -> out.accept(new net.minecraft.world.item.ItemStack(x.get())));
                        AMMO_BOXES.values().forEach(x -> out.accept(new net.minecraft.world.item.ItemStack(x.get())));
                        com.wardogs.decor.DecorBlocks.ITEM_OF.values().forEach(x -> out.accept(x.get()));
                    })
                    .build());

    private ModItems() {}
}
