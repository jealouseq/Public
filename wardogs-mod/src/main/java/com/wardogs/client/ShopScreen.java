package com.wardogs.client;

import com.wardogs.balance.Balance;
import com.wardogs.net.HudPayload;
import com.wardogs.net.ShopPayload;
import com.wardogs.progress.ProgressData;
import com.wardogs.progress.UnlockRule;
import com.wardogs.shop.Shop;
import net.minecraft.client.gui.GuiGraphics;
import net.minecraft.client.gui.components.Button;
import net.minecraft.client.gui.screens.Screen;
import net.minecraft.network.chat.Component;
import net.neoforged.neoforge.network.PacketDistributor;

import java.util.ArrayList;
import java.util.List;

/**
 * Vendor screen: category tabs and a scrollable list. Click a row to buy it, or to pay its one-time unlock cost.
 * The server re-validates every request; the colours here are only a hint.
 */
public class ShopScreen extends Screen {
    private static final int ROW_H = 14;
    private static final int LIST_W = 300;

    private Shop.Category category = Shop.Category.WEAPON;
    private List<Shop.Entry> rows = new ArrayList<>();
    private int scroll;

    private final java.util.Set<Shop.Category> allowed;

    /** All categories (development use outside Bakurani). */
    public ShopScreen() {
        this(java.util.EnumSet.allOf(Shop.Category.class));
    }

    public ShopScreen(java.util.Set<Shop.Category> allowed) {
        super(Component.literal("Vendor"));
        this.allowed = allowed;
        this.category = Shop.Category.values()[0];
        for (Shop.Category c : Shop.Category.values()) {
            if (allowed.contains(c)) {
                this.category = c;
                break;
            }
        }
    }

    @Override
    protected void init() {
        int x = width / 2 - LIST_W / 2;
        int bw = LIST_W / Math.max(1, allowed.size());
        int i = 0;
        for (Shop.Category c : Shop.Category.values()) {
            if (!allowed.contains(c)) {
                continue;
            }
            String label = c.name().charAt(0) + c.name().substring(1).toLowerCase();
            addRenderableWidget(Button.builder(Component.literal(label), b -> select(c))
                    .bounds(x + i * bw, 24, bw - 2, 18).build());
            i++;
        }
        select(category);
    }

    private void select(Shop.Category c) {
        category = c;
        scroll = 0;
        rows = Shop.catalog().stream().filter(e -> e.category() == c).toList();
    }

    private int listTop() {
        return 48;
    }

    private int visibleRows() {
        return Math.max(1, (height - listTop() - 20) / ROW_H);
    }

    @Override
    public void render(GuiGraphics g, int mouseX, int mouseY, float partial) {
        renderBackground(g, mouseX, mouseY, partial);
        super.render(g, mouseX, mouseY, partial);
        HudPayload hud = ClientHud.get();
        g.drawString(font, "Vendor", width / 2 - LIST_W / 2, 8, 0xFFFFFFFF, true);
        String money = "$" + (hud == null ? 0 : hud.money());
        g.drawString(font, money, width / 2 + LIST_W / 2 - font.width(money), 8, 0xFFFFD54A, true);

        ProgressData data = ClientProgress.get();
        int x = width / 2 - LIST_W / 2;
        for (int i = 0; i < visibleRows() && scroll + i < rows.size(); i++) {
            Shop.Entry e = rows.get(scroll + i);
            int y = listTop() + i * ROW_H;
            boolean hover = mouseX >= x && mouseX < x + LIST_W && mouseY >= y && mouseY < y + ROW_H;
            g.fill(x, y, x + LIST_W, y + ROW_H - 1, hover ? 0x88FFFFFF : 0x66000000);
            UnlockRule.State st = Shop.state(e, data);
            int colour;
            String right;
            switch (st) {
                case OWNED -> {
                    colour = 0xFFFFFFFF;
                    right = "$" + e.price();
                }
                case PAYABLE -> {
                    colour = 0xFFFFD54A;
                    right = "UNLOCK $" + e.cost();
                }
                default -> {
                    colour = 0xFF8A8A8A;
                    right = e.role().key() + " L" + e.level();
                }
            }
            g.drawString(font, e.name(), x + 4, y + 3, colour, false);
            g.drawString(font, right, x + LIST_W - 4 - font.width(right), y + 3, colour, false);
        }
        if (rows.size() > visibleRows()) {
            g.drawString(font, "scroll for more (" + rows.size() + ")", x, height - 14, 0xFF888888, false);
        }
    }

    @Override
    public boolean mouseScrolled(double mx, double my, double dx, double dy) {
        int max = Math.max(0, rows.size() - visibleRows());
        scroll = Math.max(0, Math.min(max, scroll - (int) Math.signum(dy)));
        return true;
    }

    @Override
    public boolean mouseClicked(double mx, double my, int button) {
        if (super.mouseClicked(mx, my, button)) {
            return true;
        }
        int x = width / 2 - LIST_W / 2;
        if (mx >= x && mx < x + LIST_W && my >= listTop()) {
            int i = (int) ((my - listTop()) / ROW_H);
            if (i >= 0 && i < visibleRows() && scroll + i < rows.size()) {
                Shop.Entry e = rows.get(scroll + i);
                UnlockRule.State st = Shop.state(e, ClientProgress.get());
                if (st != UnlockRule.State.LEVEL_TOO_LOW) {
                    PacketDistributor.sendToServer(new ShopPayload(e.id(), st == UnlockRule.State.PAYABLE));
                }
                return true;
            }
        }
        return false;
    }

    @Override
    public boolean isPauseScreen() {
        return false;
    }
}
