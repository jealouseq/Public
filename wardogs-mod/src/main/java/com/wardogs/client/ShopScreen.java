package com.wardogs.client;

import com.wardogs.balance.Balance;
import com.wardogs.item.ModItems;
import com.wardogs.net.HudPayload;
import com.wardogs.net.ShopPayload;
import com.wardogs.progress.ProgressData;
import com.wardogs.progress.UnlockRule;
import com.wardogs.shop.Shop;
import net.minecraft.client.gui.GuiGraphics;
import net.minecraft.client.gui.components.Button;
import net.minecraft.client.gui.screens.Screen;
import net.minecraft.network.chat.Component;
import net.minecraft.world.item.ItemStack;
import net.neoforged.neoforge.network.PacketDistributor;

import java.util.ArrayList;
import java.util.Comparator;
import java.util.List;

/**
 * Vendor screen: category tabs, a sorted list with item icons on the left and a detail panel with the item's numbers and a
 * BUY / UNLOCK button on the right. The server re-validates every request; the colours here are only a hint.
 */
public class ShopScreen extends Screen {
    private static final int ROW_H = 22;
    private static final int LIST_W = 240;
    private static final int DETAIL_W = 200;
    private static final int GAP = 8;

    private Shop.Category category = Shop.Category.WEAPON;
    private List<Shop.Entry> rows = new ArrayList<>();
    private int scroll;
    private String selected;
    private Button buyButton;
    private final java.util.Map<Shop.Category, FlatButton> tabs = new java.util.EnumMap<>(Shop.Category.class);

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

    private int left() {
        return (width - (LIST_W + GAP + DETAIL_W)) / 2;
    }

    @Override
    protected void init() {
        int x = left();
        int bw = LIST_W / Math.max(1, allowed.size());
        int i = 0;
        for (Shop.Category c : Shop.Category.values()) {
            if (!allowed.contains(c)) {
                continue;
            }
            String label = switch (c) {
                case WEAPON -> "Guns";
                case ARMOR -> "Armor";
                case MEDICAL -> "Med";
                case EXPLOSIVE -> "Expl";
                case BUILDING -> "Build";
                case VEHICLE -> "Veh";
                case ATTACHMENT -> "Mods";
            };
            FlatButton tab = addRenderableWidget(new FlatButton(x + i * bw, 24, bw - 2, 18, label, false, b -> select(c)));
            tabs.put(c, tab);
            i++;
        }
        buyButton = addRenderableWidget(new FlatButton(x + LIST_W + GAP + 8, height - 40, DETAIL_W - 16, 20, "BUY", true, b -> buy()));
        select(category);
    }

    private static int rank(UnlockRule.State s) {
        return switch (s) {
            case OWNED -> 0;
            case PAYABLE -> 1;
            default -> 2;
        };
    }

    private void select(Shop.Category c) {
        category = c;
        tabs.forEach((k, b) -> b.selected = k == c);
        scroll = 0;
        ProgressData data = ClientProgress.get();
        rows = Shop.catalog().stream().filter(e -> e.category() == c)
                .sorted(Comparator.comparingInt((Shop.Entry e) -> rank(Shop.state(e, data)))
                        .thenComparingInt(e -> Shop.state(e, data) == UnlockRule.State.OWNED ? e.price() : e.level())
                        .thenComparing(Shop.Entry::name))
                .toList();
        selected = rows.isEmpty() ? null : rows.get(0).id();
    }

    private Shop.Entry current() {
        for (Shop.Entry e : rows) {
            if (e.id().equals(selected)) {
                return e;
            }
        }
        return null;
    }

    private int listTop() {
        return 48;
    }

    private int visibleRows() {
        return Math.max(1, (height - listTop() - 20) / ROW_H);
    }

    private void buy() {
        Shop.Entry e = current();
        if (e == null) {
            return;
        }
        UnlockRule.State st = Shop.state(e, ClientProgress.get());
        if (st != UnlockRule.State.LEVEL_TOO_LOW) {
            PacketDistributor.sendToServer(new ShopPayload(e.id(), st == UnlockRule.State.PAYABLE));
        }
    }

    private static ItemStack icon(Shop.Entry e) {
        ItemStack s = ModItems.stack(e.id());
        return s == null ? ItemStack.EMPTY : s;
    }

    private static String pct(double v) {
        return String.format("%.0f%%", v * 100);
    }

    /** The numbers shown under the item name, as label/value pairs. */
    private static List<String[]> stats(Shop.Entry e) {
        List<String[]> out = new ArrayList<>();
        Balance b = Balance.get();
        switch (e.category()) {
            case WEAPON -> {
                var w = b.weapon(e.id());
                if (w != null) {
                    out.add(new String[]{"Class", w.category().replace('_', ' ')});
                    out.add(new String[]{"Damage", String.format("%.0f / %.0f (head)", w.chest(), w.head())});
                    out.add(new String[]{"Rate of fire", (int) w.rpm() + " RPM"});
                    out.add(new String[]{"Magazine", String.valueOf(w.magazine())});
                    out.add(new String[]{"Range", (int) w.range() + " m"});
                    if (w.velocity() > 0) {
                        out.add(new String[]{"Muzzle velocity", (int) w.velocity() + " m/s"});
                    }
                    out.add(new String[]{"Spread", String.valueOf(w.spread())});
                }
            }
            case ARMOR -> {
                var a = b.armor(e.id());
                if (a != null) {
                    out.add(new String[]{"Slot", a.slot().name().toLowerCase()});
                    out.add(new String[]{"Level", String.valueOf(a.level())});
                    out.add(new String[]{"Protection", pct(a.reduction())});
                    out.add(new String[]{"Durability", String.valueOf(a.durability())});
                }
            }
            case EXPLOSIVE -> {
                var x = b.explosive(e.id());
                if (x != null) {
                    out.add(new String[]{"Type", x.kind().name().toLowerCase().replace('_', ' ')});
                    if (x.damage() > 0) {
                        out.add(new String[]{"Damage", String.valueOf((int) x.damage())});
                    }
                    if (x.radius() > 0) {
                        out.add(new String[]{"Radius", x.radius() + " m"});
                    }
                    if (x.fuse() > 0) {
                        out.add(new String[]{"Fuse", String.format("%.1f s", x.fuse() / 20.0)});
                    }
                }
            }
            case MEDICAL -> {
                var m = b.medical(e.id());
                if (m != null) {
                    out.add(new String[]{"Type", m.kind().name().toLowerCase()});
                    out.add(new String[]{"Heals", (int) m.hp() + " HP"});
                    out.add(new String[]{"Use time", String.format("%.1f s", m.ticks() / 20.0)});
                    if (m.charges() > 1) {
                        out.add(new String[]{"Charges", String.valueOf(m.charges())});
                    }
                }
            }
            case VEHICLE -> {
                var v = b.vehicle(e.id());
                if (v != null) {
                    out.add(new String[]{"Class", v.category()});
                    out.add(new String[]{"Hull", (int) v.hp() + " HP"});
                    out.add(new String[]{"Top speed", (int) Math.round(v.speed() * 72) + " km/h"});
                    out.add(new String[]{"Seats", String.valueOf(v.seats())});
                    out.add(new String[]{"Fuel", (int) v.fuel() + " L"});
                    if (v.gun() != null) {
                        out.add(new String[]{"Gun", (int) v.gun().damage() + " dmg, " + (int) v.gun().rpm() + " RPM"});
                    }
                }
            }
            case ATTACHMENT -> {
                var a = b.attachment(e.id());
                if (a != null) {
                    out.add(new String[]{"Slot", a.slot()});
                    if (a.magMult() != 1.0) {
                        out.add(new String[]{"Magazine", "x" + a.magMult()});
                    }
                    if (a.spreadMult() != 1.0) {
                        out.add(new String[]{"Spread", "x" + a.spreadMult()});
                    }
                    if (a.zoom() > 1.0) {
                        out.add(new String[]{"Zoom", "x" + a.zoom()});
                    }
                }
            }
            case BUILDING -> {
                var d = b.buildingItem(e.id());
                if (d != null && d.stack() > 1) {
                    out.add(new String[]{"Stack", String.valueOf(d.stack())});
                }
            }
        }
        return out;
    }

    /** The dark backdrop is drawn once at the start of render(); the vanilla blur pass would smear everything drawn before the widgets. */
    @Override
    public void renderBackground(GuiGraphics g, int mouseX, int mouseY, float partial) {
    }

    @Override
    public void render(GuiGraphics g, int mouseX, int mouseY, float partial) {
        g.fill(0, 0, width, height, 0xC00B0E12);
        int x = left();
        HudPayload hud = ClientHud.get();
        Ui.text(g, font, "VENDOR", x, 8, 0xFFFFD54A, true);
        String money = "$" + (hud == null ? 0 : hud.money());
        Ui.text(g, font, money, x + LIST_W + GAP + DETAIL_W - Ui.width(font, money), 8, 0xFFFFFFFF, true);

        ProgressData data = ClientProgress.get();
        // list
        for (int i = 0; i < visibleRows() && scroll + i < rows.size(); i++) {
            Shop.Entry e = rows.get(scroll + i);
            int y = listTop() + i * ROW_H;
            boolean hover = mouseX >= x && mouseX < x + LIST_W && mouseY >= y && mouseY < y + ROW_H - 1;
            boolean sel = e.id().equals(selected);
            g.fill(x, y, x + LIST_W, y + ROW_H - 1, sel ? 0xCC3A4A66 : hover ? 0x88FFFFFF : 0x99101418);
            if (sel) {
                g.fill(x, y, x + 2, y + ROW_H - 1, 0xFFFFD54A);
            }
            UnlockRule.State st = Shop.state(e, data);
            int colour = st == UnlockRule.State.OWNED ? 0xFFFFFFFF : st == UnlockRule.State.PAYABLE ? 0xFFFFD54A : 0xFF7A7F88;
            String right = switch (st) {
                case OWNED -> "$" + e.price();
                case PAYABLE -> "UNLOCK $" + e.cost();
                default -> (e.role() == null ? "" : e.role().key()) + " L" + e.level();
            };
            ItemStack ic = icon(e);
            if (!ic.isEmpty()) {
                g.renderItem(ic, x + 4, y + 2);
            }
            Ui.text(g, font, Ui.clip(font, e.name(), LIST_W - 28 - Ui.width(font, right) - 8), x + 26, y + 7, colour, false);
            Ui.text(g, font, right, x + LIST_W - 4 - Ui.width(font, right), y + 7, colour, false);
        }
        if (rows.size() > visibleRows()) {
            Ui.text(g, font, "scroll for more (" + rows.size() + ")", x, height - 14, 0xFF888888, false);
        }
        // detail panel
        int dx = x + LIST_W + GAP;
        g.fill(dx, 24, dx + DETAIL_W, height - 12, 0xCC10151B);
        Shop.Entry e = current();
        if (e == null) {
            buyButton.visible = false;
            super.render(g, mouseX, mouseY, partial);
            return;
        }
        buyButton.visible = true;
        UnlockRule.State st = Shop.state(e, data);
        ItemStack ic = icon(e);
        if (e.category() == Shop.Category.VEHICLE || e.category() == Shop.Category.WEAPON) { // a lit stage for the model
            g.fillGradient(dx + 8, 30, dx + DETAIL_W - 8, 148, 0x884A5E74, 0x88222C3A);
            g.renderOutline(dx + 8, 30, DETAIL_W - 16, 118, 0x33FFFFFF);
        }
        boolean big = (e.category() == Shop.Category.VEHICLE && vehiclePreview(g, e.id(), dx + DETAIL_W / 2f, 86))
                || (e.category() == Shop.Category.WEAPON && !ic.isEmpty() && gunPreview(g, ic, dx + DETAIL_W / 2f, 84));
        if (big) {
            // the rotating mesh above is the picture
        } else if (!ic.isEmpty()) {
            g.pose().pushPose();
            g.pose().translate(dx + DETAIL_W / 2f - 24, 34, 0);
            g.pose().scale(3f, 3f, 1f);
            g.renderItem(ic, 0, 0);
            g.pose().popPose();
        }
        int off = big ? 62 : 0; // the vehicle mesh is a taller picture than an icon
        Ui.center(g, font, e.name().toUpperCase(), dx + DETAIL_W / 2, 90 + off, 0xFFFFFFFF);
        int y = 106 + off;
        for (String[] s : stats(e)) {
            Ui.text(g, font, s[0], dx + 10, y, 0xFF9AA0AA, false);
            Ui.text(g, font, s[1], dx + DETAIL_W - 10 - Ui.width(font, s[1]), y, 0xFFE8EAEE, false);
            y += 12;
        }
        String status = switch (st) {
            case OWNED -> "Price $" + e.price();
            case PAYABLE -> "Unlock once for $" + e.cost();
            default -> "Requires " + (e.role() == null ? "" : e.role().key()) + " level " + e.level();
        };
        Ui.center(g, font, status, dx + DETAIL_W / 2, height - 58, st == UnlockRule.State.LEVEL_TOO_LOW ? 0xFFEF4444 : 0xFFFFD54A);
        buyButton.setMessage(Component.literal(st == UnlockRule.State.OWNED ? "BUY  $" + e.price() : st == UnlockRule.State.PAYABLE ? "UNLOCK  $" + e.cost() : "LOCKED"));
        buyButton.active = st != UnlockRule.State.LEVEL_TOO_LOW;
        super.render(g, mouseX, mouseY, partial);
    }

    private static final ItemStack MODEL_CARRIER = new ItemStack(net.minecraft.world.item.Items.STICK);

    /**
     * Draws the vehicle's own mesh turning slowly, lit like an item, centred on (cx, cy) and fitted to the panel. Returns false when the
     * vehicle has no model (the caller then shows its icon).
     */
    private boolean vehiclePreview(GuiGraphics g, String id, float cx, float cy) {
        var def = Balance.get().vehicle(id);
        var mm = net.minecraft.client.Minecraft.getInstance().getModelManager();
        var model = mm.getModel(VehicleRenderer.modelLocation(id));
        if (def == null || model == mm.getMissingModel() || !VehicleRenderer.isMesh(id)) {
            return false;
        }
        double length = Math.max(def.d(), def.w());
        float scale = (float) Math.min((DETAIL_W - 24) / Math.max(1.0, length), 104.0 / Math.max(1.0, def.h() + length * 0.35));
        float spin = (System.currentTimeMillis() % 14000) / 14000f * 360f;
        g.pose().pushPose();
        g.pose().translate(cx, cy, 150);
        g.pose().scale(scale, -scale, scale);
        g.pose().mulPose(com.mojang.math.Axis.XP.rotationDegrees(-18));
        g.pose().mulPose(com.mojang.math.Axis.YP.rotationDegrees(spin));
        g.pose().translate(0, -def.h() / 2.0, 0);
        g.pose().translate(0.5, 0.5, 0.5); // the item renderer centres a model on (0.5, 0.5, 0.5); the mesh origin is the vehicle's ground centre
        com.mojang.blaze3d.platform.Lighting.setupFor3DItems();
        net.minecraft.client.Minecraft.getInstance().getItemRenderer().render(MODEL_CARRIER, net.minecraft.world.item.ItemDisplayContext.NONE, false,
                g.pose(), g.bufferSource(), 0xF000F0, net.minecraft.client.renderer.texture.OverlayTexture.NO_OVERLAY, model);
        g.flush();
        com.mojang.blaze3d.platform.Lighting.setupForFlatItems();
        g.pose().popPose();
        return true;
    }

    private static final java.util.Map<net.minecraft.client.resources.model.BakedModel, float[]> BOUNDS = new java.util.WeakHashMap<>();

    /** Bounding box (min xyz, max xyz) of a baked model's quads, in blocks. */
    private static float[] bounds(net.minecraft.client.resources.model.BakedModel model) {
        return BOUNDS.computeIfAbsent(model, m -> {
            float[] b = {1e9f, 1e9f, 1e9f, -1e9f, -1e9f, -1e9f};
            var rnd = net.minecraft.util.RandomSource.create(1);
            java.util.List<net.minecraft.core.Direction> sides = new java.util.ArrayList<>(java.util.Arrays.asList(net.minecraft.core.Direction.values()));
            sides.add(null);
            for (var side : sides) {
                for (var q : m.getQuads(null, side, rnd)) {
                    int[] v = q.getVertices();
                    for (int i = 0; i < 4; i++) {
                        for (int a = 0; a < 3; a++) {
                            float c = Float.intBitsToFloat(v[i * 8 + a]);
                            b[a] = Math.min(b[a], c);
                            b[a + 3] = Math.max(b[a + 3], c);
                        }
                    }
                }
            }
            if (b[0] > b[3]) {
                return new float[]{0, 0, 0, 1, 1, 1};
            }
            return b;
        });
    }

    /** A gun swinging slowly left and right on its own mesh, lit like an item, centred on (cx, cy). */
    private boolean gunPreview(GuiGraphics g, ItemStack stack, float cx, float cy) {
        var mc = net.minecraft.client.Minecraft.getInstance();
        var model = mc.getItemRenderer().getModel(stack, mc.level, mc.player, 0);
        if (model == mc.getModelManager().getMissingModel()) {
            return false;
        }
        double t = System.currentTimeMillis() / 1800.0;
        float[] box = bounds(model);
        float length = Math.max(0.3f, box[3] - box[0]);
        float scale = Math.min(150f / (length * 0.58f), 90f / (Math.max(0.1f, box[4] - box[1]) * 0.58f * 1.5f)); // fits the stage, whatever the gun's size
        g.pose().pushPose();
        g.pose().translate(cx, cy, 150);
        g.pose().scale(scale, -scale, scale);
        g.pose().translate(-(box[0] + box[3]) / 2 + 0.5, -(box[1] + box[4]) / 2 + 0.5, -(box[2] + box[5]) / 2 + 0.5); // the item renderer centres on (0.5, 0.5, 0.5)
        g.pose().mulPose(com.mojang.math.Axis.XP.rotationDegrees(-12));
        g.pose().mulPose(com.mojang.math.Axis.YP.rotationDegrees((float) (Math.sin(t) * 40.0)));
        com.mojang.blaze3d.platform.Lighting.setupFor3DItems();
        mc.getItemRenderer().render(stack, net.minecraft.world.item.ItemDisplayContext.GUI, false, g.pose(), g.bufferSource(), 0xF000F0,
                net.minecraft.client.renderer.texture.OverlayTexture.NO_OVERLAY, model);
        g.flush();
        com.mojang.blaze3d.platform.Lighting.setupForFlatItems();
        g.pose().popPose();
        return true;
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
        int x = left();
        if (mx >= x && mx < x + LIST_W && my >= listTop()) {
            int i = (int) ((my - listTop()) / ROW_H);
            if (i >= 0 && i < visibleRows() && scroll + i < rows.size()) {
                Shop.Entry e = rows.get(scroll + i);
                if (e.id().equals(selected)) {
                    buy(); // clicking the selected row again buys it
                } else {
                    selected = e.id();
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
