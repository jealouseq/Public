package com.wardogs.client;

import com.wardogs.balance.Balance;
import com.wardogs.build.Buildable;
import com.wardogs.net.HudPayload;
import com.wardogs.world.BakuraniWorld;
import net.minecraft.client.Minecraft;
import net.minecraft.client.gui.GuiGraphics;
import net.minecraft.client.gui.screens.Screen;
import net.minecraft.network.chat.Component;

/** Full-screen tactical map: terrain overview, kilometre grid, zones, bases, spawns, vendors, FOBs and the player. */
public final class MapScreen extends Screen {
    private static final int[] FACTION_COLOR = {0x3B82F6, 0xEF4444, 0x22C55E};
    private double cx, cz;          // world position at the centre of the screen
    private double scale;           // screen pixels per metre
    private double minScale, maxScale = 1.5;

    public MapScreen() {
        super(Component.literal("Map"));
    }

    @Override
    protected void init() {
        minScale = Math.min(width, height - 24) / BakuraniWorld.WORLD_SIZE;
        if (scale == 0) {
            scale = minScale * 2.2;
            Minecraft mc = Minecraft.getInstance();
            cx = mc.player == null ? 0 : mc.player.getX();
            cz = mc.player == null ? 0 : mc.player.getZ();
        }
        clamp();
    }

    private void clamp() {
        scale = Math.max(minScale, Math.min(maxScale, scale));
        double half = BakuraniWorld.WORLD_SIZE / 2;
        cx = Math.max(-half, Math.min(half, cx));
        cz = Math.max(-half, Math.min(half, cz));
    }

    private double sx(double x) { return width / 2.0 + (x - cx) * scale; }
    private double sy(double z) { return height / 2.0 + (z - cz) * scale; }

    @Override
    public boolean mouseScrolled(double mx, double my, double dx, double dy) {
        double wx = cx + (mx - width / 2.0) / scale, wz = cz + (my - height / 2.0) / scale;
        scale *= dy > 0 ? 1.25 : 0.8;
        clamp();
        cx = wx - (mx - width / 2.0) / scale;
        cz = wz - (my - height / 2.0) / scale;
        clamp();
        return true;
    }

    @Override
    public boolean mouseDragged(double mx, double my, int button, double dx, double dy) {
        cx -= dx / scale;
        cz -= dy / scale;
        clamp();
        return true;
    }

    @Override
    public boolean keyPressed(int key, int scan, int mods) {
        if (ClientEvents.MAP.matches(key, scan)) {
            onClose();
            return true;
        }
        return super.keyPressed(key, scan, mods);
    }

    @Override
    public boolean isPauseScreen() {
        return false;
    }

    @Override
    public void renderBackground(GuiGraphics g, int mx, int my, float pt) {
        g.fill(0, 0, width, height, 0xFF0B0E12);
    }

    @Override
    public void render(GuiGraphics g, int mx, int my, float pt) {
        renderBackground(g, mx, my, pt);
        Minecraft mc = Minecraft.getInstance();
        double world = BakuraniWorld.WORLD_SIZE, half = world / 2;
        boolean image = ClientMapImage.ensure();
        int x0 = (int) Math.round(sx(-half)), y0 = (int) Math.round(sy(-half));
        int w = (int) Math.round(world * scale), h = w;
        g.enableScissor(0, 0, width, height - 14);
        if (image) {
            g.blit(ClientMapImage.TEXTURE, x0, y0, w, h, 0, 0, ClientMapImage.SIZE, ClientMapImage.SIZE, ClientMapImage.SIZE, ClientMapImage.SIZE);
        } else {
            g.fill(x0, y0, x0 + w, y0 + h, 0xFF26303A);
            g.drawCenteredString(font, "Map data not installed on this client - showing markers only", width / 2, height / 2, 0xFF9AA0AA);
        }
        grid(g, x0, y0, half);
        HudPayload hud = ClientHud.get();
        if (hud != null && hud.active()) {
            circle(g, hud.zx(), hud.zz(), hud.zr(), 0xFFFFFFFF);
            circle(g, hud.hx(), hud.hz(), hud.hr(), 0xFFFFD54A);
            label(g, hud.hx(), hud.hz() - hud.hr() - 12, "HOT ZONE", 0xFFFFD54A);
        }
        var sites = BakuraniWorld.sites();
        int i = 0;
        for (var e : sites.bases().entrySet()) {
            int f = switch (e.getKey()) { case "lonestar" -> 0; case "valkyra" -> 1; default -> 2; };
            marker(g, e.getValue().x(), e.getValue().z(), 5, 0xFF000000 | FACTION_COLOR[f]);
            label(g, e.getValue().x(), e.getValue().z() + 9, e.getKey().toUpperCase(), 0xFF000000 | FACTION_COLOR[f]);
            i++;
        }
        for (var s : sites.towers()) {
            marker(g, s.x(), s.z(), 2, 0xFFE7B93B);
        }
        for (var s : sites.vendors()) {
            marker(g, s.x(), s.z(), 2, 0xFFFFFFFF);
        }
        if (mc.level != null) {
            double r = Balance.get().fobRadius();
            for (Buildable b : mc.level.getEntitiesOfClass(Buildable.class, mc.player.getBoundingBox().inflate(256))) {
                if (b.isFob()) {
                    int col = 0xFF000000 | FACTION_COLOR[Math.max(0, Math.min(2, b.faction()))];
                    int fx = (int) sx(b.getX()), fy = (int) sy(b.getZ()), rr = (int) Math.max(3, r * scale);
                    g.renderOutline(fx - rr, fy - rr, rr * 2, rr * 2, col);
                    g.fill(fx - 2, fy - 2, fx + 3, fy + 3, col);
                }
            }
        }
        if (mc.player != null) {
            player(g, mc.player.getX(), mc.player.getZ(), mc.player.getYRot());
        }
        g.disableScissor();
        // legend
        g.fill(0, height - 14, width, height, 0xFF10151B);
        g.drawString(font, "[M] close   Scroll: zoom   Drag: pan   Grid: 1 km", 6, height - 11, 0xFF9AA0AA, false);
        if (mc.player != null) {
            String pos = "X " + (int) mc.player.getX() + "  Z " + (int) mc.player.getZ();
            g.drawString(font, pos, width - 6 - font.width(pos), height - 11, 0xFFE8EAEE, false);
        }
        // cursor coordinates
        double wx = cx + (mx - width / 2.0) / scale, wz = cz + (my - height / 2.0) / scale;
        String cur = gridName(wx, wz);
        g.drawString(font, cur, width / 2 - font.width(cur) / 2, height - 11, 0xFFFFD54A, false);
    }

    private static String gridName(double x, double z) {
        int gx = (int) Math.floor((x + BakuraniWorld.WORLD_SIZE / 2) / 1000), gz = (int) Math.floor((z + BakuraniWorld.WORLD_SIZE / 2) / 1000);
        return (gx < 0 || gz < 0 || gx > 16 || gz > 16) ? "-" : (char) ('A' + gx) + "" + (gz + 1);
    }

    private void grid(GuiGraphics g, int x0, int y0, double half) {
        for (int k = 0; k <= 16; k++) {
            double w = -half + k * 1000.0;
            int gx = (int) Math.round(sx(w)), gy = (int) Math.round(sy(w));
            g.fill(gx, 0, gx + 1, height - 14, 0x30FFFFFF);
            g.fill(0, gy, width, gy + 1, 0x30FFFFFF);
            if (k < 16) {
                int lx = (int) Math.round(sx(w + 500)), ly = (int) Math.round(sy(w + 500));
                if (lx > 0 && lx < width) {
                    g.drawString(font, String.valueOf((char) ('A' + k)), lx - 2, 3, 0xAAFFFFFF, false);
                }
                if (ly > 0 && ly < height - 14) {
                    g.drawString(font, String.valueOf(k + 1), 3, ly - 3, 0xAAFFFFFF, false);
                }
            }
        }
    }

    private void marker(GuiGraphics g, double x, double z, int r, int color) {
        int px = (int) sx(x), py = (int) sy(z);
        g.fill(px - r - 1, py - r - 1, px + r + 2, py + r + 2, 0xFF000000);
        g.fill(px - r, py - r, px + r + 1, py + r + 1, color);
    }

    private void label(GuiGraphics g, double x, double z, String s, int color) {
        int px = (int) sx(x), py = (int) sy(z);
        g.drawString(font, s, px - font.width(s) / 2, py, color, true);
    }

    private void circle(GuiGraphics g, double x, double z, double r, int color) {
        if (r <= 0) {
            return;
        }
        int steps = (int) Math.max(64, Math.min(1440, r * scale * 6));
        for (int i = 0; i < steps; i++) {
            double a = i * Math.PI * 2 / steps;
            int px = (int) Math.round(sx(x + Math.cos(a) * r)), py = (int) Math.round(sy(z + Math.sin(a) * r));
            g.fill(px, py, px + 2, py + 2, color);
        }
    }

    private void player(GuiGraphics g, double x, double z, float yaw) {
        g.pose().pushPose();
        g.pose().translate(sx(x), sy(z), 0);
        g.pose().mulPose(com.mojang.math.Axis.ZP.rotationDegrees(yaw + 180f));
        g.fill(-3, -7, 4, 6, 0xFF000000);
        g.fill(-1, -6, 2, 4, 0xFFFFFFFF);
        g.fill(-2, -3, 3, -1, 0xFFFFFFFF);
        g.pose().popPose();
    }
}
