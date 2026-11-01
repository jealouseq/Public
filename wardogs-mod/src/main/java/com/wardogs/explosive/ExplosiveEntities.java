package com.wardogs.explosive;

import com.wardogs.WardogsMod;
import net.minecraft.core.registries.Registries;
import net.minecraft.resources.ResourceLocation;
import net.minecraft.tags.TagKey;
import net.minecraft.world.entity.EntityType;
import net.minecraft.world.entity.MobCategory;
import net.neoforged.neoforge.registries.DeferredHolder;
import net.neoforged.neoforge.registries.DeferredRegister;

public final class ExplosiveEntities {
    public static final DeferredRegister<EntityType<?>> ENTITIES = DeferredRegister.create(Registries.ENTITY_TYPE, WardogsMod.MOD_ID);

    /** Entities that count as vehicles for anti-tank mines (the real vehicles join this tag). */
    public static final TagKey<EntityType<?>> VEHICLES =
            TagKey.create(Registries.ENTITY_TYPE, ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "vehicles"));

    public static final DeferredHolder<EntityType<?>, EntityType<GrenadeEntity>> GRENADE = ENTITIES.register("grenade", () ->
            EntityType.Builder.<GrenadeEntity>of(GrenadeEntity::new, MobCategory.MISC)
                    .sized(0.25f, 0.25f).clientTrackingRange(8).updateInterval(2).build("grenade"));

    public static final DeferredHolder<EntityType<?>, EntityType<PlacedCharge>> CHARGE = ENTITIES.register("charge", () ->
            EntityType.Builder.<PlacedCharge>of(PlacedCharge::new, MobCategory.MISC)
                    .sized(0.4f, 0.2f).clientTrackingRange(8).updateInterval(20).fireImmune().build("charge"));

    public static final DeferredHolder<EntityType<?>, EntityType<RocketEntity>> ROCKET = ENTITIES.register("rocket", () ->
            EntityType.Builder.<RocketEntity>of(RocketEntity::new, MobCategory.MISC)
                    .sized(0.3f, 0.3f).clientTrackingRange(10).updateInterval(1).noSave().build("rocket"));

    private ExplosiveEntities() {}
}
