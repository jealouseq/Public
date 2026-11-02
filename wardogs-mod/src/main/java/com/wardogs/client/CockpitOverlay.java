package com.wardogs.client;

import com.wardogs.vehicle.Vehicle;
import net.minecraft.client.Minecraft;
import net.minecraft.client.gui.GuiGraphics;

/**
 * The cockpit of a helicopter seen from the pilot's seat, drawn over the view like the official screenshots of WARDOGS: dark canopy
 * struts, a central instrument cluster (airspeed, artificial horizon, altimeter, radar, climb rate, heading) between two green screens.
 * Shown only in first person; the instruments read the vehicle's real speed, height, climb, hull and fuel.
 */
public final class CockpitOverlay {
    private static final int BLACK = 0xFF0C0C0E, BEZEL = 0xFF15171B, FACE = 0xFF1D2025, TICK = 0xFFD0D4DA, GREEN = 0xFF46E37E;

    private CockpitOverlay() {}

    public static boolean active(Minecraft mc) {
        return mc.player != null && mc.options.getCameraType().isFirstPerson() && mc.player.getVehicle() instanceof Vehicle v
                && v.def() != null && v.def().flying();
    }

    public static void render(GuiGraphics g, Minecraft mc) {
        if (!active(mc)) {
            return;
        }
        Vehicle v = (Vehicle) mc.player.getVehicle();
        int w = g.guiWidth(), h = g.guiHeight();
        frame(g, w, h);
        double kmh = v.getDeltaMovement().length() * 72.0;
        double climb = v.getDeltaMovement().y * 20.0;
        float pitch = mc.player.getXRot();
        double heading = ((mc.player.getYRot() + 180.0) % 360.0 + 360.0) % 360.0;
        long t = mc.level == null ? 0 : mc.level.getGameTime();
        // the central cluster
        int cw = Math.max(110, (int) (w * 0.155)), ch = (int) (h * 0.36);
        int cx0 = (int) (w * 0.455) - cw / 2, cy0 = h - ch - (int) (h * 0.05);
        g.fill(cx0, cy0, cx0 + cw, cy0 + ch, BLACK);
        g.renderOutline(cx0, cy0, cw, ch, 0xFF2D3036);
        for (int i = 0; i < 12; i++) { // a row of small warning lights along the top
            g.fill(cx0 + 4 + i * ((cw - 8) / 12), cy0 + 3, cx0 + 4 + i * ((cw - 8) / 12) + 3, cy0 + 5, i % 5 == 0 ? 0xFF6C3A18 : 0xFF2A2C31);
        }
        int r = Math.max(9, cw / 8);
        int gx = cx0 + cw / 4 - 1, gx2 = cx0 + cw / 2, gx3 = cx0 + cw * 3 / 4 + 1;
        int gy = cy0 + 8 + r, gy2 = gy + 2 * r + 5;
        dial(g, gx, gy, r, kmh / 260.0, 0.0);                  // airspeed
        horizon(g, gx2, gy, r + 2, pitch);                      // artificial horizon
        dial(g, gx3, gy, r, v.getY() / 320.0, v.getY() / 3200.0); // altitude
        radar(g, gx, gy2, r, t);
        dial(g, gx2, gy2, r, 0.5 + Math.max(-1, Math.min(1, climb / 12.0)) * 0.42, -1);   // climb rate
        compassDial(g, gx3, gy2, r, heading);
        // fuel and hull as two thin bars under the dials
        var d = v.def();
        double fuel = d.fuel() <= 0 ? 0 : Math.max(0, Math.min(1, v.fuel() / d.fuel()));
        double hull = Math.max(0, Math.min(1, v.getHealth() / Math.max(1.0, v.getMaxHealth())));
        int by = cy0 + ch - 9;
        bar(g, cx0 + 6, by, cw - 12, 2, fuel, fuel < 0.15 ? 0xFFFF5555 : 0xFFE7B93B);
        bar(g, cx0 + 6, by + 4, cw - 12, 2, hull, hull < 0.3 ? 0xFFFF5555 : 0xFF4ADE80);
        // the two green screens
        int mw = (int) (cw * 0.95), mh = (int) (ch * 0.78);
        screen(g, cx0 - mw - 4, cy0 + (ch - mh) / 2 + 6, mw, mh, mc, new String[]{String.format("SPD %3d", (int) kmh), String.format("ALT %3d", (int) v.getY()), String.format("HDG %03d", (int) heading)});
        screen(g, cx0 + cw + 4, cy0 + (ch - mh) / 2 + 12, mw, mh, mc, new String[]{d.gun() != null ? "GUN RDY" : "NO GUN", String.format("HP %3d%%", (int) Math.round(hull * 100)), String.format("FUEL %2d%%", (int) Math.round(fuel * 100))});
    }

    // --- frame ---------------------------------------------------------------------------------------

    /** A diagonal band: at every row from y0 to y1 a bar of the (interpolated) thickness, so a steep strut looks solid. */
    private static void band(GuiGraphics g, double x0, double y0, double x1, double y1, double t0, double t1, int color) {
        int a = (int) Math.floor(Math.min(y0, y1)), b = (int) Math.ceil(Math.max(y0, y1));
        for (int y = a; y <= b; y++) {
            double f = (y - y0) / (y1 - y0);
            double x = x0 + (x1 - x0) * f, t = t0 + (t1 - t0) * f;
            g.fill((int) Math.round(x - t / 2), y, (int) Math.round(x + t / 2), y + 1, color);
        }
    }

    private static void frame(GuiGraphics g, int w, int h) {
        band(g, w * 0.27, -2, w * 0.43, h * 0.58, 3, 7, 0xFF0A0A0C);                  // the thin windscreen strut
        band(g, w * 0.70, -2, w * 0.97, h + 2, 22, 52, 0xFF150F0A);                   // the thick right pillar
        band(g, w * 0.745, h * 0.18, w * 0.80, h * 0.42, 4, 4, 0xFF34261A);            // a grab handle on it
        for (int y = (int) (h * 0.46); y < h; y++) {                                  // the dark hull at the bottom left
            double f = (y - h * 0.46) / (h * 0.54);
            g.fill(0, y, (int) (w * (0.02 + 0.16 * f)), y + 1, 0xFF0B0B0D);
        }
        for (int y = (int) (h * 0.66); y < h; y++) {                                  // the dashboard shelf rising to the instruments
            double f = (y - h * 0.66) / (h * 0.34);
            g.fill((int) (w * (0.34 - 0.20 * f)), y, (int) (w * (0.60 + 0.14 * f)), y + 1, 0xFF111114);
        }
    }

    // --- instruments ---------------------------------------------------------------------------------

    private static void disc(GuiGraphics g, int cx, int cy, int r, int color) {
        for (int dy = -r; dy <= r; dy++) {
            int half = (int) Math.round(Math.sqrt(Math.max(0, r * r - dy * dy)));
            g.fill(cx - half, cy + dy, cx + half + 1, cy + dy + 1, color);
        }
    }

    private static void line(GuiGraphics g, double x0, double y0, double x1, double y1, int color) {
        int n = (int) Math.max(1, Math.ceil(Math.hypot(x1 - x0, y1 - y0)));
        for (int i = 0; i <= n; i++) {
            int x = (int) Math.round(x0 + (x1 - x0) * i / n), y = (int) Math.round(y0 + (y1 - y0) * i / n);
            g.fill(x, y, x + 1, y + 1, color);
        }
    }

    /** A round gauge: bezel, face, 12 ticks, a needle at fraction f of a 270 degree sweep and optionally a short second needle. */
    private static void dial(GuiGraphics g, int cx, int cy, int r, double f, double f2) {
        disc(g, cx, cy, r + 1, 0xFF050506);
        disc(g, cx, cy, r, FACE);
        for (int i = 0; i < 12; i++) {
            double a = Math.toRadians(-225 + i * 270.0 / 11.0);
            line(g, cx + Math.cos(a) * (r - 3), cy + Math.sin(a) * (r - 3), cx + Math.cos(a) * (r - 1), cy + Math.sin(a) * (r - 1), TICK);
        }
        double a = Math.toRadians(-225 + Math.max(0, Math.min(1, f)) * 270.0);
        line(g, cx, cy, cx + Math.cos(a) * (r - 3), cy + Math.sin(a) * (r - 3), 0xFFFFFFFF);
        if (f2 >= 0) {
            double b = Math.toRadians(-225 + Math.max(0, Math.min(1, f2)) * 270.0);
            line(g, cx, cy, cx + Math.cos(b) * (r - 6), cy + Math.sin(b) * (r - 6), 0xFFE7B93B);
        }
        g.fill(cx - 1, cy - 1, cx + 1, cy + 1, 0xFF8A8F98);
    }

    /** Artificial horizon: sky over ground, the split moving with the pitch (looking down moves it up), a yellow aircraft symbol. */
    private static void horizon(GuiGraphics g, int cx, int cy, int r, float pitch) {
        disc(g, cx, cy, r + 1, 0xFF050506);
        int split = (int) Math.round(Math.max(-r, Math.min(r, pitch / 90.0 * r * 1.5)));
        for (int dy = -r; dy <= r; dy++) {
            int half = (int) Math.round(Math.sqrt(Math.max(0, r * r - dy * dy)));
            g.fill(cx - half, cy + dy, cx + half + 1, cy + dy + 1, dy < -split ? 0xFF3A86C8 : 0xFF7A5430);
        }
        if (Math.abs(split) < r) {
            int half = (int) Math.round(Math.sqrt(Math.max(0, r * r - split * split)));
            g.fill(cx - half, cy - split, cx + half + 1, cy - split + 1, 0xFFFFFFFF);
        }
        g.fill(cx - r / 2, cy, cx - 2, cy + 1, 0xFFFFD54A);
        g.fill(cx + 2, cy, cx + r / 2 + 1, cy + 1, 0xFFFFD54A);
        g.fill(cx - 1, cy - 1, cx + 2, cy + 2, 0xFFFFD54A);
    }

    private static void radar(GuiGraphics g, int cx, int cy, int r, long t) {
        disc(g, cx, cy, r + 1, 0xFF050506);
        disc(g, cx, cy, r, 0xFF0E3A1C);
        disc(g, cx, cy, r * 2 / 3, 0xFF0A2E16);
        disc(g, cx, cy, r / 3, 0xFF0E3A1C);
        g.fill(cx - r, cy, cx + r + 1, cy + 1, 0xFF1E6B34);
        g.fill(cx, cy - r, cx + 1, cy + r + 1, 0xFF1E6B34);
        double a = Math.toRadians((t * 7) % 360);
        line(g, cx, cy, cx + Math.cos(a) * (r - 1), cy + Math.sin(a) * (r - 1), GREEN);
    }

    /** Heading dial: a fixed lubber mark at the top and the compass card turning under it, N marked. */
    private static void compassDial(GuiGraphics g, int cx, int cy, int r, double heading) {
        disc(g, cx, cy, r + 1, 0xFF050506);
        disc(g, cx, cy, r, FACE);
        for (int i = 0; i < 12; i++) {
            double a = Math.toRadians(i * 30.0 - heading - 90);
            line(g, cx + Math.cos(a) * (r - 3), cy + Math.sin(a) * (r - 3), cx + Math.cos(a) * (r - 1), cy + Math.sin(a) * (r - 1), i % 3 == 0 ? 0xFFFFFFFF : TICK);
        }
        double n = Math.toRadians(-heading - 90);
        g.fill((int) Math.round(cx + Math.cos(n) * (r - 5)) - 1, (int) Math.round(cy + Math.sin(n) * (r - 5)) - 1,
                (int) Math.round(cx + Math.cos(n) * (r - 5)) + 2, (int) Math.round(cy + Math.sin(n) * (r - 5)) + 2, 0xFFFF6A6A);
        g.fill(cx - 1, cy - r + 1, cx + 2, cy - r + 4, 0xFFFFD54A);
        g.fill(cx - 1, cy - 1, cx + 2, cy + 2, 0xFF8A8F98);
    }

    private static void bar(GuiGraphics g, int x, int y, int w, int h, double f, int color) {
        g.fill(x, y, x + w, y + h, 0xFF26282D);
        g.fill(x, y, x + (int) Math.round(w * f), y + h, color);
    }

    /** A multi-function display: a dark bezel with screws, a green screen with corner brackets, a crosshair and three lines of text. */
    private static void screen(GuiGraphics g, int x, int y, int w, int h, Minecraft mc, String[] lines) {
        g.fill(x, y, x + w, y + h, BLACK);
        g.renderOutline(x, y, w, h, 0xFF2D3036);
        for (int[] s : new int[][]{{2, 2}, {w - 4, 2}, {2, h - 4}, {w - 4, h - 4}}) {
            g.fill(x + s[0], y + s[1], x + s[0] + 2, y + s[1] + 2, 0xFF3A3D44);
        }
        int sx = x + 5, sy = y + 5, sw = w - 10, sh = h - 10;
        g.fill(sx, sy, sx + sw, sy + sh, 0xFF08130D);
        int br = 6;
        for (int[] c : new int[][]{{sx + 6, sy + 6, 1, 1}, {sx + sw - 7, sy + 6, -1, 1}, {sx + 6, sy + sh - 7, 1, -1}, {sx + sw - 7, sy + sh - 7, -1, -1}}) {
            g.fill(Math.min(c[0], c[0] + c[2] * br), c[1], Math.max(c[0], c[0] + c[2] * br) + 1, c[1] + 1, 0xAA46E37E);
            g.fill(c[0], Math.min(c[1], c[1] + c[3] * br), c[0] + 1, Math.max(c[1], c[1] + c[3] * br) + 1, 0xAA46E37E);
        }
        int mx = sx + sw / 2, my = sy + sh / 2;
        g.fill(mx - 7, my, mx + 8, my + 1, GREEN);
        g.fill(mx, my - 7, mx + 1, my + 8, GREEN);
        g.renderOutline(mx - 3, my - 3, 7, 7, 0x8846E37E);
        for (int i = 0; i < lines.length; i++) {
            g.pose().pushPose();
            g.pose().translate(sx + 3, sy + 3 + i * 7, 0);
            g.pose().scale(0.55f, 0.55f, 1f);
            Ui.text(g, mc.font, lines[i], 0, 0, 0xFF46E37E, false);
            g.pose().popPose();
        }
    }
}
