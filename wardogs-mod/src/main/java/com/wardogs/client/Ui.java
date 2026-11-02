package com.wardogs.client;

import com.wardogs.WardogsMod;
import net.minecraft.client.gui.Font;
import net.minecraft.client.gui.GuiGraphics;
import net.minecraft.network.chat.Component;
import net.minecraft.network.chat.Style;
import net.minecraft.resources.ResourceLocation;

/**
 * The look of the interface: Rajdhani (SIL OFL, assets/wardogs/font) instead of the vanilla pixel font - a squared, medium-weight sans like the
 * numerals and the tracked capitals of the real game's HUD - and the colours sampled from the official screenshots.
 */
public final class Ui {
    public static final ResourceLocation FONT = ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "hud");

    // team colours (Lonestar, Valkyra, Manticore) and surfaces, measured on the official 1080p screenshots
    public static final int BLUE = 0x4C97D3, RED = 0xFC3C2C, GREEN = 0x4CC35A;
    public static final int[] TEAM = {BLUE, RED, GREEN};
    public static final int PANEL = 0xC0181818, PANEL_LIGHT = 0xCC343434, TEXT = 0xFFF0F0F0, DIM = 0xFF8A8A8A, DIM_DIGIT = 0xFF585858;
    public static final int YELLOW = 0xFFF2B63C;

    private Ui() {}

    public static Component c(String s) {
        return Component.literal(s).withStyle(Style.EMPTY.withFont(FONT));
    }

    public static int text(GuiGraphics g, Font f, String s, int x, int y, int color, boolean shadow) {
        return g.drawString(f, c(s), x, y, color, shadow);
    }

    public static int text(GuiGraphics g, Font f, String s, int x, int y, int color) {
        return text(g, f, s, x, y, color, true);
    }

    public static void center(GuiGraphics g, Font f, String s, int cx, int y, int color) {
        text(g, f, s, cx - width(f, s) / 2, y, color, true);
    }

    public static int width(Font f, String s) {
        return f.width(c(s));
    }

    /** The longest prefix of {@code s} that fits in {@code max} pixels. */
    public static String clip(Font f, String s, int max) {
        int n = s.length();
        while (n > 0 && width(f, s.substring(0, n)) > max) {
            n--;
        }
        return s.substring(0, n);
    }
}
