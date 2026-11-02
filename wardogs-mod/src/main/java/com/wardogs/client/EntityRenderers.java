package com.wardogs.client;

import com.wardogs.WardogsMod;
import com.wardogs.build.BuildEntities;
import com.wardogs.explosive.ExplosiveEntities;
import com.wardogs.vehicle.VehicleEntities;
import com.wardogs.vendor.VendorEntities;
import net.minecraft.client.renderer.entity.ThrownItemRenderer;
import net.neoforged.api.distmarker.Dist;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.client.event.EntityRenderersEvent;

@EventBusSubscriber(modid = WardogsMod.MOD_ID, value = Dist.CLIENT)
public final class EntityRenderers {
    private EntityRenderers() {}

    @SubscribeEvent
    public static void register(EntityRenderersEvent.RegisterRenderers e) {
        e.registerEntityRenderer(ExplosiveEntities.GRENADE.get(), ctx -> new ThrownItemRenderer<>(ctx, 0.8f, false));
        e.registerEntityRenderer(BuildEntities.BUILDABLE.get(), BuildableRenderer::new);
        e.registerEntityRenderer(VehicleEntities.VEHICLE.get(), VehicleRenderer::new);
        e.registerEntityRenderer(VendorEntities.VENDOR.get(), net.minecraft.client.renderer.entity.VillagerRenderer::new);
        e.registerEntityRenderer(ExplosiveEntities.ROCKET.get(), ctx -> new ThrownItemRenderer<>(ctx, 0.8f, true));
        e.registerEntityRenderer(ExplosiveEntities.CHARGE.get(), ctx -> new ThrownItemRenderer<>(ctx, 1.0f, true));
    }
}
