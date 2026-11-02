package com.wardogs.client;

import net.minecraft.client.Minecraft;
import net.minecraft.client.gui.GuiGraphics;
import net.minecraft.client.gui.components.Button;
import net.minecraft.network.chat.Component;

/** A flat dark button with the interface font: lit yellow when selected or when it is the main action. */
public final class FlatButton extends Button {
    public boolean selected;
    private final boolean accent;

    public FlatButton(int x, int y, int w, int h, String label, boolean accent, OnPress press) {
        super(x, y, w, h, Component.literal(label), press, DEFAULT_NARRATION);
        this.accent = accent;
    }

    @Override
    protected void renderWidget(GuiGraphics g, int mouseX, int mouseY, float partial) {
        boolean hover = isHoveredOrFocused() && active;
        boolean lit = selected || (accent && active);
        int bg = !active ? 0xAA262626 : lit ? (hover ? 0xFFFFCB5A : Ui.YELLOW) : hover ? 0xCC404040 : 0xCC1E1E1E;
        g.fill(getX(), getY(), getX() + width, getY() + height, bg);
        g.fill(getX(), getY(), getX() + width, getY() + 1, lit ? 0x40FFFFFF : 0x30FFFFFF);
        int color = !active ? 0xFF6A6A6A : lit ? 0xFF1A1405 : Ui.TEXT;
        Ui.center(g, Minecraft.getInstance().font, getMessage().getString(), getX() + width / 2, getY() + (height - 8) / 2 + 1, color);
    }
}
