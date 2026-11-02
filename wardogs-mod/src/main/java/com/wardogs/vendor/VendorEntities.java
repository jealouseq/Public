package com.wardogs.vendor;

import com.wardogs.WardogsMod;
import net.minecraft.core.registries.Registries;
import net.minecraft.world.entity.EntityType;
import net.minecraft.world.entity.MobCategory;
import net.minecraft.world.entity.npc.Villager;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.event.entity.EntityAttributeCreationEvent;
import net.neoforged.neoforge.registries.DeferredHolder;
import net.neoforged.neoforge.registries.DeferredRegister;

public final class VendorEntities {
    public static final DeferredRegister<EntityType<?>> ENTITIES = DeferredRegister.create(Registries.ENTITY_TYPE, WardogsMod.MOD_ID);

    public static final DeferredHolder<EntityType<?>, EntityType<VendorEntity>> VENDOR = ENTITIES.register("vendor", () ->
            EntityType.Builder.<VendorEntity>of(VendorEntity::new, MobCategory.MISC)
                    .sized(0.6f, 1.95f).clientTrackingRange(10).build("vendor"));

    private VendorEntities() {}

    @EventBusSubscriber(modid = WardogsMod.MOD_ID)
    public static final class Attributes {
        private Attributes() {}

        @SubscribeEvent
        public static void register(EntityAttributeCreationEvent e) {
            e.put(VENDOR.get(), Villager.createAttributes().build());
        }
    }
}
