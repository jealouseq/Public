package com.wardogs.client;

import com.wardogs.net.ScoreboardPayload;
import net.minecraft.client.Minecraft;
import net.minecraft.client.gui.GuiGraphics;
import net.minecraft.client.gui.screens.Screen;
import net.minecraft.network.chat.Component;

/** End-of-round screen: who won and the final scoreboard. Closes with ESC or after 25 seconds. */
public final class ResultScreen extends Screen {
    private static final String[] NAME = {"LONESTAR", "VALKYRA", "MANTICORE"};
    private static final int[] COLOR = {0xFF3B82F6, 0xFFEF4444, 0xFF22C55E};
    private final ScoreboardPayload sb;
    private long opened;

    public ResultScreen(ScoreboardPayload sb) {
        super(Component.literal("Round result"));
        this.sb = sb;
    }

    @Override
    protected void init() {
        opened = System.currentTimeMillis();
    }

    @Override
    public void tick() {
        if (System.currentTimeMillis() - opened > 25_000) {
            onClose();
        }
    }

    @Override
    public void render(GuiGraphics g, int mx, int my, float pt) {
        renderBackground(g, mx, my, pt);
        Minecraft mc = Minecraft.getInstance();
        String title = sb.winner() < 0 ? "ROUND OVER" : NAME[sb.winner()] + " WINS";
        int color = sb.winner() < 0 ? 0xFFFFFFFF : COLOR[sb.winner()];
        g.pose().pushPose();
        g.pose().translate(width / 2f, 24, 0);
        g.pose().scale(2.2f, 2.2f, 1f);
        g.drawCenteredString(font, title, 0, 0, color);
        g.pose().popPose();
        ScoreTable.draw(g, mc, sb, 60, width);
        g.drawCenteredString(font, "Press ESC to continue", width / 2, height - 16, 0xFF9AA0AA);
    }
}
