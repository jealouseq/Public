package com.wardogs.client;

import com.wardogs.WardogsMod;
import com.wardogs.item.GunItem;
import com.wardogs.item.GunStats;
import net.minecraft.client.Minecraft;
import net.neoforged.api.distmarker.Dist;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.client.event.ComputeFovModifierEvent;

/** Sneaking with a scoped weapon in hand magnifies the view by the sight's zoom factor. */
@EventBusSubscriber(modid = WardogsMod.MOD_ID, value = Dist.CLIENT)
public final class ZoomHandler {
    private ZoomHandler() {}

    @SubscribeEvent
    public static void fov(ComputeFovModifierEvent e) {
        Minecraft mc = Minecraft.getInstance();
        if (e.getPlayer() != mc.player || !e.getPlayer().isShiftKeyDown() || !(e.getPlayer().getMainHandItem().getItem() instanceof GunItem)) {
            return;
        }
        double zoom = GunStats.zoom(GunStats.attachments(e.getPlayer().getMainHandItem()));
        if (zoom > 1.0) {
            e.setNewFovModifier((float) (e.getNewFovModifier() / zoom));
        }
    }
}
