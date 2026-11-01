package com.wardogs.client;

import com.wardogs.WardogsMod;
import com.wardogs.match.Faction;
import com.wardogs.net.HudPayload;
import net.minecraft.client.DeltaTracker;
import net.minecraft.client.Minecraft;
import net.minecraft.client.gui.GuiGraphics;
import net.minecraft.resources.ResourceLocation;
import net.neoforged.api.distmarker.Dist;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.client.event.RegisterGuiLayersEvent;

/** Money, faction and match score overlay. */
@EventBusSubscriber(modid = WardogsMod.MOD_ID, value = Dist.CLIENT)
public final class HudLayer {
    private HudLayer() {}

    @SubscribeEvent
    public static void register(RegisterGuiLayersEvent e) {
        e.registerAboveAll(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "hud"), HudLayer::render);
    }

    private static void render(GuiGraphics g, DeltaTracker delta) {
        HudPayload h = ClientHud.get();
        Minecraft mc = Minecraft.getInstance();
        if (h == null || mc.options.hideGui || mc.player == null) {
            return;
        }
        int w = g.guiWidth();
        g.drawString(mc.font, "$" + h.money(), 6, 6, 0xFFD54A, true);
        Faction f = Faction.byIndex(h.faction());
        if (f != null) {
            g.drawString(mc.font, f.display, 6, 18, 0xFF000000 | (f.color.getColor() == null ? 0xFFFFFF : f.color.getColor()), true);
        }
        if (h.active()) {
            int[] s = {h.s0(), h.s1(), h.s2()};
            int total = 3 * 60;
            int x = (w - total) / 2;
            for (int i = 0; i < 3; i++) {
                Faction fi = Faction.values()[i];
                int col = 0xFF000000 | fi.color.getColor();
                int bx = x + i * 60;
                g.fill(bx + 2, 4, bx + 58, 16, 0x99000000);
                int filled = (int) (56.0 * Math.min(1.0, s[i] / (double) Math.max(1, h.winScore())));
                g.fill(bx + 2, 4, bx + 2 + filled, 16, (col & 0x00FFFFFF) | 0xAA000000);
                String t = s[i] + "/" + h.winScore();
                g.drawCenteredString(mc.font, t, bx + 30, 6, 0xFFFFFFFF);
            }
        }
    }
}
