package com.wardogs.client;

import com.wardogs.WardogsMod;
import com.wardogs.match.Faction;
import com.wardogs.net.HudPayload;
import com.wardogs.vehicle.Vehicle;
import net.minecraft.client.DeltaTracker;
import net.minecraft.client.Minecraft;
import net.minecraft.client.gui.GuiGraphics;
import net.minecraft.resources.ResourceLocation;
import net.neoforged.api.distmarker.Dist;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.client.event.RegisterGuiLayersEvent;
import net.neoforged.neoforge.client.event.RenderGuiLayerEvent;
import net.neoforged.neoforge.client.gui.VanillaGuiLayers;

/** Money, faction and match score overlay. */
@EventBusSubscriber(modid = WardogsMod.MOD_ID, value = Dist.CLIENT)
public final class HudLayer {
    private HudLayer() {}

    @SubscribeEvent
    public static void register(RegisterGuiLayersEvent e) {
        e.registerAboveAll(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "hud"), HudLayer::render);
    }

    /** Vanilla hearts are replaced by the numeric HP bar below. */
    @SubscribeEvent
    public static void hideHearts(RenderGuiLayerEvent.Pre e) {
        if (e.getName().equals(VanillaGuiLayers.PLAYER_HEALTH)) {
            e.setCanceled(true);
        }
    }

    private static final ResourceLocation HEART_EMPTY = ResourceLocation.withDefaultNamespace("hud/heart/container");
    private static final ResourceLocation HEART_FULL = ResourceLocation.withDefaultNamespace("hud/heart/full");
    private static final int HEART_SIZE = 18; // vanilla sprite is 9x9, drawn at 2x

    /** "100 [heart]": the heart fills from the bottom in proportion to current health (half full at 50 HP). */
    private static void renderHealth(GuiGraphics g, Minecraft mc) {
        if (mc.player == null || mc.player.isCreative() || mc.player.isSpectator()) {
            return;
        }
        float hp = mc.player.getHealth();
        float frac = Math.max(0f, Math.min(1f, hp / Math.max(1f, mc.player.getMaxHealth())));
        int x = g.guiWidth() / 2 - 91;
        int y = g.guiHeight() - 44;

        String text = String.valueOf((int) Math.ceil(hp));
        g.pose().pushPose();
        g.pose().translate(x, y, 0);
        g.pose().scale(1.5f, 1.5f, 1f);
        g.drawString(mc.font, text, 0, 4, 0xFFFFFFFF, true);
        g.pose().popPose();
        int textW = (int) (mc.font.width(text) * 1.5f);

        int hx = x + textW + 6;
        int hy = y - 2;
        g.blitSprite(HEART_EMPTY, hx, hy, HEART_SIZE, HEART_SIZE);
        int filled = Math.round(HEART_SIZE * frac);
        if (filled > 0) {
            g.enableScissor(hx, hy + HEART_SIZE - filled, hx + HEART_SIZE, hy + HEART_SIZE);
            g.blitSprite(HEART_FULL, hx, hy, HEART_SIZE, HEART_SIZE);
            g.disableScissor();
        }
    }

    private static void render(GuiGraphics g, DeltaTracker delta) {
        HudPayload h = ClientHud.get();
        Minecraft mc = Minecraft.getInstance();
        if (mc.options.hideGui || mc.player == null) {
            return;
        }
        renderHealth(g, mc);
        if (mc.player.getVehicle() instanceof Vehicle veh && veh.def() != null) {
            var d = veh.def();
            int y = g.guiHeight() - 70;
            int x = g.guiWidth() - 6;
            String l1 = d.name() + (d.gun() != null && veh.getControllingPassenger() == mc.player ? "  [hold fire]" : "");
            String l2 = "HP " + (int) Math.ceil(veh.getHealth()) + "/" + (int) veh.getMaxHealth() + "   Fuel " + (int) veh.fuel() + "/" + (int) d.fuel();
            g.drawString(mc.font, l1, x - mc.font.width(l1), y, 0xFFFFFFFF, true);
            g.drawString(mc.font, l2, x - mc.font.width(l2), y + 11, veh.fuel() < d.fuel() * 0.15 ? 0xFFFF5555 : 0xFFDDDDDD, true);
        }
        if (h == null) {
            return;
        }
        if (h.downed() > 0) {
            String t = "YOU ARE DOWN  " + (int) Math.ceil(h.downed() / 20.0) + "s";
            g.drawCenteredString(mc.font, t, g.guiWidth() / 2, g.guiHeight() / 2 + 20, 0xFFFF4040);
            g.drawCenteredString(mc.font, "Wait for a teammate to revive you", g.guiWidth() / 2, g.guiHeight() / 2 + 32, 0xFFCCCCCC);
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
