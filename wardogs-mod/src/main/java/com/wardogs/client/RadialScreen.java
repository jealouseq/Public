package com.wardogs.client;

import com.wardogs.match.Requests;
import com.wardogs.net.RequestPayload;
import net.minecraft.client.gui.GuiGraphics;
import net.minecraft.client.gui.screens.Screen;
import net.minecraft.network.chat.Component;
import net.neoforged.neoforge.network.PacketDistributor;

import java.util.ArrayList;
import java.util.List;

/** The radial menu on the middle mouse button: REQUESTS, LOCAL callouts and EMOTES, picked with the mouse. */
public final class RadialScreen extends Screen {
    private record Slot(String label, Requests.Group group, String id) {}

    private Requests.Group open;
    private final List<Slot> slots = new ArrayList<>();

    public RadialScreen() {
        super(Component.literal("Radial menu"));
    }

    @Override
    protected void init() {
        rebuild();
    }

    private void rebuild() {
        slots.clear();
        if (open == null) {
            for (Requests.Group g : Requests.Group.values()) {
                slots.add(new Slot(g.name(), g, null));
            }
        } else {
            for (Requests.Entry e : Requests.ENTRIES.values()) {
                if (e.group() == open) {
                    slots.add(new Slot(e.label().toUpperCase(), open, e.id()));
                }
            }
        }
    }

    private int hovered(double mx, double my) {
        double dx = mx - width / 2.0, dy = my - height / 2.0;
        if (slots.isEmpty() || Math.hypot(dx, dy) < 18) {
            return -1;
        }
        double ang = Math.toDegrees(Math.atan2(dx, -dy));   // 0 = up, clockwise
        if (ang < 0) {
            ang += 360;
        }
        double seg = 360.0 / slots.size();
        return (int) (((ang + seg / 2) % 360) / seg);
    }

    @Override
    public boolean mouseClicked(double mx, double my, int button) {
        int i = hovered(mx, my);
        if (i < 0) {
            if (open != null) {
                open = null;
                rebuild();
            }
            return true;
        }
        Slot s = slots.get(i);
        if (s.id() == null) {
            open = s.group();
            rebuild();
        } else {
            PacketDistributor.sendToServer(new RequestPayload(s.id()));
            onClose();
        }
        return true;
    }

    @Override
    public boolean isPauseScreen() {
        return false;
    }

    @Override
    public void render(GuiGraphics g, int mx, int my, float pt) {
        int cx = width / 2, cy = height / 2, radius = 62;
        int hover = hovered(mx, my);
        g.fill(cx - 3, cy - 3, cx + 4, cy + 4, 0xAAFFFFFF);
        g.drawCenteredString(font, open == null ? "MENU" : open.name(), cx, cy - radius - 26, 0xFFFFD54A);
        double seg = Math.PI * 2 / Math.max(1, slots.size());
        for (int i = 0; i < slots.size(); i++) {
            double a = i * seg;
            int sx = cx + (int) Math.round(Math.sin(a) * radius), sy = cy - (int) Math.round(Math.cos(a) * radius);
            int w = Math.max(54, font.width(slots.get(i).label()) + 12);
            boolean on = i == hover;
            g.fill(sx - w / 2, sy - 9, sx + w / 2, sy + 9, on ? 0xFFE7B93B : 0xCC10151B);
            g.renderOutline(sx - w / 2, sy - 9, w, 18, on ? 0xFFFFFFFF : 0x88FFFFFF);
            g.drawCenteredString(font, slots.get(i).label(), sx, sy - 4, on ? 0xFF1A1405 : 0xFFE8EAEE);
        }
        g.drawCenteredString(font, open == null ? "Click to choose  -  ESC to close" : "Click empty space to go back", cx, cy + radius + 26, 0xFF9AA0AA);
    }
}
