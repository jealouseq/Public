package com.wardogs.client;

import com.wardogs.match.MatchStats;
import com.wardogs.net.ScoreboardPayload;
import net.minecraft.client.Minecraft;
import net.minecraft.client.gui.GuiGraphics;

/** Draws the three faction columns of the scoreboard; shared by the TAB overlay and the round result screen. */
public final class ScoreTable {
    private static final int[] COLOR = {0xFF3B82F6, 0xFFEF4444, 0xFF22C55E};
    private static final String[] NAME = {"LONESTAR", "VALKYRA", "MANTICORE"};

    private ScoreTable() {}

    public static void draw(GuiGraphics g, Minecraft mc, ScoreboardPayload sb, int top, int width) {
        int colW = Math.min(150, (width - 20) / 3);
        int total = colW * 3 + 8;
        int x0 = (width - total) / 2;
        for (int f = 0; f < 3; f++) {
            int x = x0 + f * (colW + 4);
            g.fill(x, top, x + colW, top + 14, 0xCC0B0E12);
            g.fill(x, top, x + colW, top + 2, COLOR[f]);
            g.drawString(mc.font, NAME[f], x + 4, top + 4, COLOR[f], false);
            String sc = String.valueOf(sb.scores()[f]);
            g.drawString(mc.font, sc, x + colW - 4 - mc.font.width(sc), top + 4, 0xFFFFFFFF, false);
            int y = top + 16;
            g.fill(x, y, x + colW, y + 10, 0x99101418);
            g.drawString(mc.font, "PLAYER", x + 4, y + 1, 0xFF9AA0AA, false);
            g.drawString(mc.font, "K", x + colW - 62, y + 1, 0xFF9AA0AA, false);
            g.drawString(mc.font, "D", x + colW - 46, y + 1, 0xFF9AA0AA, false);
            g.drawString(mc.font, "$", x + colW - 12, y + 1, 0xFF9AA0AA, false);
            y += 11;
            for (MatchStats.Row r : sb.rows()) {
                if (r.faction() != f) {
                    continue;
                }
                g.fill(x, y, x + colW, y + 10, 0x66101418);
                String name = mc.font.plainSubstrByWidth(r.name(), colW - 80);
                boolean me = mc.player != null && r.name().equals(mc.player.getGameProfile().getName());
                g.drawString(mc.font, name, x + 4, y + 1, me ? 0xFFFFD54A : 0xFFE8EAEE, false);
                g.drawString(mc.font, String.valueOf(r.kills()), x + colW - 62, y + 1, 0xFFE8EAEE, false);
                g.drawString(mc.font, String.valueOf(r.deaths()), x + colW - 46, y + 1, 0xFFE8EAEE, false);
                String money = r.earned() >= 1000 ? (r.earned() / 1000) + "k" : String.valueOf(r.earned());
                g.drawString(mc.font, money, x + colW - 4 - mc.font.width(money), y + 1, 0xFFFFD54A, false);
                y += 11;
            }
        }
    }
}
