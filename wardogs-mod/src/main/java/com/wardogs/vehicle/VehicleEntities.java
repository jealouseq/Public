package com.wardogs.vehicle;

import com.wardogs.WardogsMod;
import net.minecraft.core.registries.Registries;
import net.minecraft.world.entity.EntityType;
import net.minecraft.world.entity.MobCategory;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.event.entity.EntityAttributeCreationEvent;
import net.neoforged.neoforge.registries.DeferredHolder;
import net.neoforged.neoforge.registries.DeferredRegister;

public final class VehicleEntities {
    public static final DeferredRegister<EntityType<?>> ENTITIES = DeferredRegister.create(Registries.ENTITY_TYPE, WardogsMod.MOD_ID);

    public static final DeferredHolder<EntityType<?>, EntityType<Vehicle>> VEHICLE = ENTITIES.register("vehicle", () ->
            EntityType.Builder.<Vehicle>of(Vehicle::new, MobCategory.MISC)
                    .sized(2f, 2f).clientTrackingRange(16).updateInterval(2).fireImmune().build("vehicle"));

    private VehicleEntities() {}

    @EventBusSubscriber(modid = WardogsMod.MOD_ID)
    public static final class Attributes {
        private Attributes() {}

        @SubscribeEvent
        public static void register(EntityAttributeCreationEvent e) {
            e.put(VEHICLE.get(), Vehicle.attributes().build());
        }
    }
}
