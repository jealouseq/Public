package com.wardogs.client;

import com.wardogs.WardogsMod;
import com.wardogs.balance.Balance;
import com.wardogs.item.ArmorPieceItem;
import com.wardogs.item.GunItem;
import com.wardogs.item.GunStats;
import com.wardogs.match.Faction;
import com.wardogs.net.HudPayload;
import com.wardogs.progress.Progression;
import com.wardogs.progress.Role;
import com.wardogs.vehicle.Vehicle;
import net.minecraft.client.DeltaTracker;
import net.minecraft.client.Minecraft;
import net.minecraft.client.gui.GuiGraphics;
import net.minecraft.resources.ResourceLocation;
import net.minecraft.world.entity.EquipmentSlot;
import net.minecraft.world.item.ItemStack;
import net.neoforged.api.distmarker.Dist;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.client.event.RegisterGuiLayersEvent;
import net.neoforged.neoforge.client.event.RenderGuiLayerEvent;
import net.neoforged.neoforge.client.gui.VanillaGuiLayers;

import java.util.List;

/**
 * The in-match HUD, laid out like the real game (checked against gameplay footage): compass strip at the top centre, two money
 * boxes with a reward log at the top right, a square minimap with the three faction scores and a "CONTROL" label at the bottom left,
 * armour/health at the bottom right, a context prompt under the crosshair and a vehicle read-out.
 */
@EventBusSubscriber(modid = WardogsMod.MOD_ID, value = Dist.CLIENT)
public final class HudLayer {
    private static final int[] FACTION_COLOR = {0x3B82F6, 0xEF4444, 0x22C55E}; // Lonestar blue, Valkyra red, Manticore green
    private static final int BOX = 0xB0101418;
    private static final int TEXT = 0xFFE8EAEE;
    private static final int DIM = 0xFF9AA0AA;
    private static final int GOLD = 0xFFFFD54A;

    private static final ResourceLocation HEART_EMPTY = ResourceLocation.withDefaultNamespace("hud/heart/container");
    private static final ResourceLocation HEART_FULL = ResourceLocation.withDefaultNamespace("hud/heart/full");

    private HudLayer() {}

    @SubscribeEvent
    public static void register(RegisterGuiLayersEvent e) {
        e.registerAboveAll(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "hud"), HudLayer::render);
    }

    /** The vanilla health, armour and food bars are replaced by this HUD. */
    @SubscribeEvent
    public static void hideVanilla(RenderGuiLayerEvent.Pre e) {
        if (e.getName().equals(VanillaGuiLayers.TAB_LIST) && ClientHud.get() != null && ClientHud.get().active()) {
            e.setCanceled(true); // the match scoreboard replaces the vanilla player list
            return;
        }
        if (e.getName().equals(VanillaGuiLayers.PLAYER_HEALTH) || e.getName().equals(VanillaGuiLayers.ARMOR_LEVEL)
                || e.getName().equals(VanillaGuiLayers.FOOD_LEVEL)) {
            e.setCanceled(true);
        }
    }

    // --- helpers -----------------------------------------------------------------------------

    private static void box(GuiGraphics g, int x, int y, int w, int h) {
        g.fill(x, y, x + w, y + h, BOX);
        g.fill(x, y, x + w, y + 1, 0x30FFFFFF);
    }

    private static void small(GuiGraphics g, Minecraft mc, String s, float x, float y, int color, float scale) {
        g.pose().pushPose();
        g.pose().translate(x, y, 0);
        g.pose().scale(scale, scale, 1f);
        g.drawString(mc.font, s, 0, 0, color, false);
        g.pose().popPose();
    }

    private static float smallWidth(Minecraft mc, String s, float scale) {
        return mc.font.width(s) * scale;
    }

    private static String money(long v) {
        return String.format("$%,d", v);
    }

    private static int faction(int i) {
        return 0xFF000000 | FACTION_COLOR[Math.max(0, Math.min(2, i))];
    }

    // --- layer -------------------------------------------------------------------------------

    private static void render(GuiGraphics g, DeltaTracker delta) {
        Minecraft mc = Minecraft.getInstance();
        if (mc.options.hideGui || mc.player == null || mc.level == null) {
            return;
        }
        HudPayload h = ClientHud.get();
        ClientMinimap.tick(mc);
        if (h != null && h.active() && mc.options.keyPlayerList.isDown() && ClientScoreboard.get() != null) {
            ScoreTable.draw(g, mc, ClientScoreboard.get(), 36, g.guiWidth());
        }
        renderCompass(g, mc, h);
        WorldMarkers.render(g, mc, h);
        renderKillFeed(g, mc);
        renderSquad(g, mc);
        renderHitMarker(g);
        if (h != null) {
            renderMoney(g, mc, h);
        }
        renderRewardLog(g, mc);
        if (h == null || !h.hardcore()) {
            renderMinimap(g, mc, h);
        }
        renderSelf(g, mc);
        renderPrompt(g, mc);
        renderVehicle(g, mc);
        if (h != null && h.downed() > 0) {
            renderDowned(g, mc, h);
        }
    }

    /** A ring with a medic star, drawn as segments around (cx, cy); {@code fraction} of it is lit. */
    private static void ring(GuiGraphics g, int cx, int cy, int radius, double fraction, int lit, int dark) {
        int steps = 72;
        for (int i = 0; i < steps; i++) {
            double a = -Math.PI / 2 + i * Math.PI * 2 / steps;
            int x = (int) Math.round(cx + Math.cos(a) * radius), y = (int) Math.round(cy + Math.sin(a) * radius);
            int c = i < fraction * steps ? lit : dark;
            g.fill(x - 1, y - 1, x + 1, y + 1, c);
        }
    }

    /** Downed screen like the real game: dark red vignette, a bleed-out ring with a medic star, GIVE UP and CALL FOR HELP keys. */
    private static void renderDowned(GuiGraphics g, Minecraft mc, HudPayload h) {
        int w = g.guiWidth(), ht = g.guiHeight();
        g.fill(0, 0, w, ht, 0x30000000);
        int band = ht / 3;
        g.fillGradient(0, 0, w, band, 0x80300000, 0x00300000);
        g.fillGradient(0, ht - band, w, ht, 0x00300000, 0x90400000);
        int side = w / 10;
        for (int i = 0; i < side; i++) {
            int a = (int) (0x50 * (1.0 - i / (double) side));
            g.fill(i, 0, i + 1, ht, (a << 24) | 0x300000);
            g.fill(w - 1 - i, 0, w - i, ht, (a << 24) | 0x300000);
        }
        int cx = w / 2, cy = (int) (ht * 0.9);
        double total = Math.max(1, Balance.get().bleedoutTicks());
        ring(g, cx, cy, 17, Math.min(1.0, h.downed() / total), 0xFFFFFFFF, 0xFFD13B3B);
        g.fill(cx - 12, cy - 12, cx + 12, cy + 12, 0x55000000);
        for (int k = 0; k < 3; k++) { // a six-pointed medic star
            double a = Math.PI / 3 * k;
            for (int t = -9; t <= 9; t++) {
                int x = (int) Math.round(cx + Math.cos(a) * t), y = (int) Math.round(cy + Math.sin(a) * t);
                g.fill(x - 1, y - 1, x + 1, y + 1, 0xFFFFFFFF);
            }
        }
        String secs = (int) Math.ceil(h.downed() / 20.0) + "s";
        small(g, mc, secs, cx - smallWidth(mc, secs, 0.8f) / 2, cy + 22, 0xFFFFC9C9, 0.8f);
        String giveKey = ClientEvents.GIVE_UP.getTranslatedKeyMessage().getString().toUpperCase();
        String helpKey = ClientEvents.CALL_HELP.getTranslatedKeyMessage().getString().toUpperCase();
        int kw1 = mc.font.width(giveKey) + 6, kw2 = mc.font.width(helpKey) + 6;
        String l1 = "GIVE UP", l2 = "CALL FOR HELP";
        float lw1 = smallWidth(mc, l1, 0.8f);
        int x1 = cx - 34 - kw1 - 4 - (int) lw1;
        small(g, mc, l1, x1, cy - 3, 0xFFE8EAEE, 0.8f);
        int kx1 = x1 + (int) lw1 + 4;
        g.fill(kx1, cy - 6, kx1 + kw1, cy + 6, 0xCC0B0E12);
        g.renderOutline(kx1, cy - 6, kw1, 12, 0xAAFFFFFF);
        g.drawString(mc.font, giveKey, kx1 + 3, cy - 4, TEXT, false);
        int kx2 = cx + 34;
        g.fill(kx2, cy - 6, kx2 + kw2, cy + 6, 0xCC0B0E12);
        g.renderOutline(kx2, cy - 6, kw2, 12, 0xAAFFFFFF);
        g.drawString(mc.font, helpKey, kx2 + 3, cy - 4, TEXT, false);
        small(g, mc, l2, kx2 + kw2 + 4, cy - 3, 0xFFE8EAEE, 0.8f);
    }

    /** Heading strip: a tick label every 15 degrees, the current heading in a box, the Hot Zone as a marker with its distance. */
    private static void renderCompass(GuiGraphics g, Minecraft mc, HudPayload h) {
        int w = g.guiWidth();
        int cx = w / 2, y = 4, half = 110;
        double heading = (mc.player.getYRot() + 180.0) % 360.0;
        if (heading < 0) {
            heading += 360;
        }
        g.fill(cx - half, y + 1, cx + half, y + 11, 0x66101418);
        double pxPerDeg = half / 55.0;
        for (int a = -60; a <= 60; a += 15) {
            double deg = Math.floor(heading / 15.0) * 15.0 + a;
            double off = deg - heading;
            double x = cx + off * pxPerDeg;
            if (Math.abs(x - cx) > half - 4 || Math.abs(x - cx) < 14) {
                continue;
            }
            int d = (int) ((deg % 360 + 360) % 360);
            String label = d % 90 == 0 ? new String[]{"N", "E", "S", "W"}[d / 90] : String.valueOf(d);
            small(g, mc, label, (float) (x - smallWidth(mc, label, 0.75f) / 2), y + 3, d % 90 == 0 ? TEXT : DIM, 0.75f);
        }
        String[] dirs = {"N", "NE", "E", "SE", "S", "SW", "W", "NW"};
        String centre = (int) heading + " " + dirs[(int) Math.round(heading / 45.0) % 8];
        g.fill(cx - 20, y, cx + 20, y + 12, 0xCC0B0E12);
        g.renderOutline(cx - 20, y, 40, 12, 0xAAFFFFFF);
        small(g, mc, centre, cx - smallWidth(mc, centre, 0.8f) / 2, y + 3, TEXT, 0.8f);
        if (h != null && h.active() && h.hr() > 0 && !h.hardcore()) {
            double bearing = Math.toDegrees(Math.atan2(h.hx() - mc.player.getX(), -(h.hz() - mc.player.getZ())));
            double off = ((bearing - heading + 540) % 360) - 180;
            if (Math.abs(off) < 55) {
                int x = (int) (cx + off * pxPerDeg);
                g.fill(x - 5, y + 14, x + 5, y + 24, 0xCC000000);
                g.renderOutline(x - 5, y + 14, 10, 10, GOLD);
                small(g, mc, "HZ", x - 4, y + 16, GOLD, 0.6f);
                String dist = (int) Math.hypot(h.hx() - mc.player.getX(), h.hz() - mc.player.getZ()) + " m";
                small(g, mc, dist, x - smallWidth(mc, dist, 0.6f) / 2, y + 25, TEXT, 0.6f);
            }
        }
    }

    /** Kill feed, top left: "KILLER [weapon] VICTIM" in faction colours, newest at the bottom. */
    private static void renderKillFeed(GuiGraphics g, Minecraft mc) {
        int y = 8;
        long now = System.currentTimeMillis();
        for (ClientFeedback.Line l : ClientFeedback.lines()) {
            var k = l.kill();
            int alpha = (int) (255 * Math.min(1.0, (ClientFeedback.FEED_MS - (now - l.time())) / 1000.0));
            if (alpha < 12) {
                continue;
            }
            String weapon = (k.headshot() ? "\u2316 " : "") + k.weapon();
            int x = 8;
            int w = mc.font.width(k.killer()) + mc.font.width(weapon) + mc.font.width(k.victim()) + 20;
            g.fill(x - 3, y - 2, x + w, y + 10, (Math.min(alpha, 150) << 24) | 0x101418);
            g.drawString(mc.font, k.killer(), x, y, (alpha << 24) | (k.killerFaction() >= 0 ? FACTION_COLOR[Math.min(2, k.killerFaction())] : 0xE8EAEE), false);
            x += mc.font.width(k.killer()) + 6;
            g.drawString(mc.font, weapon, x, y, (alpha << 24) | (k.headshot() ? 0xFFD54A : 0x9AA0AA), false);
            x += mc.font.width(weapon) + 6;
            g.drawString(mc.font, k.victim(), x, y, (alpha << 24) | (k.victimFaction() >= 0 ? FACTION_COLOR[Math.min(2, k.victimFaction())] : 0xE8EAEE), false);
            y += 13;
        }
    }

    /** Squad list on the left edge: name, a health bar, a star for the leader. */
    private static void renderSquad(GuiGraphics g, Minecraft mc) {
        var members = ClientSquad.members();
        if (members.isEmpty()) {
            return;
        }
        int x = 8, y = g.guiHeight() / 2 - members.size() * 11;
        g.drawString(mc.font, "SQUAD", x, y - 11, 0xFFFFD54A, true);
        for (var m : members) {
            g.fill(x - 3, y - 2, x + 78, y + 17, 0x99101418);
            g.drawString(mc.font, (m.leader() ? "\u2605 " : "") + m.name(), x, y, m.downed() ? 0xFFEF4444 : 0xFFE8EAEE, false);
            if (m.downed()) {
                g.drawString(mc.font, "DOWN", x + 72 - mc.font.width("DOWN"), y, 0xFFEF4444, false);
            }
            float f = m.maxHealth() <= 0 ? 0 : Math.max(0f, Math.min(1f, m.health() / m.maxHealth()));
            g.fill(x, y + 11, x + 72, y + 14, 0xFF2A2F36);
            g.fill(x, y + 11, x + (int) (72 * f), y + 14, f > 0.5f ? 0xFF4ADE80 : f > 0.25f ? 0xFFFACC15 : 0xFFEF4444);
            y += 21;
        }
    }

    /** An X around the crosshair when a shot lands: white for a hit, gold for a headshot, red for a kill. */
    private static void renderHitMarker(GuiGraphics g) {
        int kind = ClientFeedback.markerKind();
        if (kind < 0) {
            return;
        }
        int cx = g.guiWidth() / 2, cy = g.guiHeight() / 2;
        float age = ClientFeedback.markerAge();
        int alpha = (int) (255 * (1 - age));
        int rgb = kind >= 2 ? 0xFF3030 : (kind == 1 ? 0xFFD54A : 0xFFFFFF);
        int color = (alpha << 24) | rgb;
        int gap = 3 + (int) (age * 3), len = kind >= 2 ? 6 : 4;
        for (int i = 0; i < len; i++) {
            int o = gap + i;
            g.fill(cx - o - 1, cy - o - 1, cx - o, cy - o, color);
            g.fill(cx + o, cy - o - 1, cx + o + 1, cy - o, color);
            g.fill(cx - o - 1, cy + o, cx - o, cy + o + 1, color);
            g.fill(cx + o, cy + o, cx + o + 1, cy + o + 1, color);
        }
    }

    private static void renderMoney(GuiGraphics g, Minecraft mc, HudPayload h) {
        int w = g.guiWidth();
        String cash = money(h.money());
        long xp = Progression.get(mc.player).xpOf(Role.WARDOG);
        String level = "LV " + Balance.get().progression().levelFor(xp);
        int bw = 62, bh = 12, gap = 3, x2 = w - 6 - bw, x1 = x2 - gap - bw;
        box(g, x1, 5, bw, bh);
        box(g, x2, 5, bw, bh);
        g.drawString(mc.font, cash, x1 + bw - 4 - mc.font.width(cash), 7, h.money() > 0 ? TEXT : 0xFFFF6666, false);
        g.drawString(mc.font, level, x2 + bw - 4 - mc.font.width(level), 7, GOLD, false);
    }

    private static void renderRewardLog(GuiGraphics g, Minecraft mc) {
        int w = g.guiWidth();
        int y = 20;
        long now = System.currentTimeMillis();
        for (ClientRewards.Line l : ClientRewards.visible()) {
            long age = now - l.time();
            int alpha = (int) (255 * Math.min(1.0, (ClientRewards.LIFETIME_MS - age) / 1500.0));
            if (alpha < 12) {
                continue;
            }
            String amount = (l.amount() >= 0 ? "+" : "-") + money(Math.abs(l.amount()));
            int color = (alpha << 24) | (l.amount() >= 0 ? 0xFFFFFF : 0xFF6666);
            float aw = smallWidth(mc, amount, 0.85f);
            g.fill((int) (w - 8 - aw - 3), y - 1, w - 5, y + 8, ((alpha * 2 / 3) << 24) | 0x101418);
            small(g, mc, amount, w - 8 - aw, y, color, 0.85f);
            small(g, mc, l.label().toUpperCase(), w - 8 - aw - 8 - smallWidth(mc, l.label().toUpperCase(), 0.65f), y + 1, (alpha / 2 << 24) | 0xB8BEC8, 0.65f);
            y += 9;
        }
    }

    /** Square minimap turned to the heading (forward is up, like the real game), faction score strip and the "CONTROL" label. */
    private static void renderMinimap(GuiGraphics g, Minecraft mc, HudPayload h) {
        int S = 84, x = 8, labelH = 9, stripH = 11;
        int y = g.guiHeight() - S - stripH - labelH - 8;
        g.fill(x - 1, y - 1, x + S + 1, y + S + 1, 0xFF0B0E12);
        double scale = S / 192.0; // gui px per block: the window shows 192 blocks
        double px = mc.player.getX(), pz = mc.player.getZ();
        double alpha = Math.toRadians(-(mc.player.getYRot() + 180.0));
        double ca = Math.cos(alpha), sa = Math.sin(alpha);
        g.enableScissor(x, y, x + S, y + S);
        g.fill(x, y, x + S, y + S, 0xFF1D2229);
        g.pose().pushPose();
        g.pose().translate(x + S / 2f, y + S / 2f, 0);
        g.pose().mulPose(com.mojang.math.Axis.ZP.rotation((float) alpha));
        g.pose().translate((float) -((px - ClientMinimap.centerX()) * scale), (float) -((pz - ClientMinimap.centerZ()) * scale), 0);
        int W = Math.round((float) (ClientMinimap.PIXELS * ClientMinimap.BLOCKS_PER_PIXEL * scale));
        g.blit(ClientMinimap.TEXTURE, -W / 2, -W / 2, W, W, 0, 0, ClientMinimap.PIXELS, ClientMinimap.PIXELS, ClientMinimap.PIXELS, ClientMinimap.PIXELS);
        g.pose().popPose();
        java.util.function.BiFunction<Double, Double, int[]> at = (wx, wz) -> {
            double dx = (wx - px) * scale, dz = (wz - pz) * scale;
            return new int[]{(int) Math.round(x + S / 2.0 + dx * ca - dz * sa), (int) Math.round(y + S / 2.0 + dx * sa + dz * ca)};
        };
        if (h != null && h.active()) {
            circle(g, at, h.zx(), h.zz(), h.zr(), scale, x, y, S, 0xCCFFFFFF);
            circle(g, at, h.hx(), h.hz(), h.hr(), scale, x, y, S, 0xFFFFD54A);
        }
        for (var m : ClientSquad.members()) {
            int[] p = at.apply(m.x(), m.z());
            g.fill(p[0] - 2, p[1] - 2, p[0] + 3, p[1] + 3, 0xFF000000);
            g.fill(p[0] - 1, p[1] - 1, p[0] + 2, p[1] + 2, m.leader() ? 0xFFFFD54A : 0xFF4ADE80);
        }
        // the viewer always points up
        int vx = x + S / 2, vy = y + S / 2;
        g.fill(vx - 1, vy - 5, vx + 1, vy - 3, 0xFFFFFFFF);
        g.fill(vx - 2, vy - 3, vx + 2, vy - 1, 0xFFFFFFFF);
        g.fill(vx - 3, vy - 1, vx + 3, vy + 1, 0xFF3B82F6);
        g.fill(vx - 1, vy + 1, vx + 1, vy + 3, 0xFF3B82F6);
        g.disableScissor();
        // north rides the rim as the map turns
        double nx = sa * 1.0, ny = -ca; // the north direction (0, -1) turned by alpha
        double edge = S / 2.0 - 6, k = edge / Math.max(Math.abs(nx), Math.abs(ny));
        g.drawString(mc.font, "N", (int) Math.round(x + S / 2.0 + nx * k) - 2, (int) Math.round(y + S / 2.0 + ny * k) - 4, 0xFFFFFFFF, true);

        int sy = y + S + 2;
        box(g, x, sy, S, stripH);
        int seg = S / 3;
        for (int i = 0; i < 3; i++) {
            int sx = x + i * seg;
            int score = h == null ? 0 : new int[]{h.s0(), h.s1(), h.s2()}[i];
            int count = h == null ? 0 : new int[]{h.c0(), h.c1(), h.c2()}[i];
            int win = h == null ? 100 : Math.max(1, h.winScore());
            g.fill(sx + 1, sy + 1, sx + seg - 1, sy + 3, 0x55000000);
            g.fill(sx + 1, sy + 1, sx + 1 + (int) ((seg - 2) * Math.min(1.0, score / (double) win)), sy + 3, faction(i));
            String sc = String.format("%03d", score);
            small(g, mc, sc, sx + 2, sy + 4, faction(i), 0.7f);
            small(g, mc, String.valueOf(count), sx + 2 + smallWidth(mc, sc, 0.7f) + 2, sy + 4.5f, DIM, 0.6f);
        }
        int ly = sy + stripH;
        int controller = h == null ? -1 : h.controller();
        g.fill(x, ly, x + S, ly + labelH, controller >= 0 ? (0xCC000000 | FACTION_COLOR[controller]) : 0xCC3A3F48);
        String label = "CONTROL: " + (controller >= 0 ? Faction.byIndex(controller).display.toUpperCase() : "NONE");
        small(g, mc, label, x + 3, ly + 2, 0xFFFFFFFF, 0.7f);
    }

    private static void circle(GuiGraphics g, java.util.function.BiFunction<Double, Double, int[]> at, double cx, double cz, double r, double scale,
                               int mx, int my, int S, int color) {
        if (r <= 0) {
            return;
        }
        int steps = (int) Math.max(48, Math.min(720, r * scale * 8));
        for (int i = 0; i < steps; i++) {
            double a = i * Math.PI * 2 / steps;
            int[] p = at.apply(cx + Math.cos(a) * r, cz + Math.sin(a) * r);
            if (p[0] >= mx && p[0] < mx + S && p[1] >= my && p[1] < my + S) {
                g.fill(p[0], p[1], p[0] + 1, p[1] + 1, color);
            }
        }
    }

    /** Bottom right: ammo line, then stamina / armour / health like the real HUD. */
    private static void renderSelf(GuiGraphics g, Minecraft mc) {
        if (mc.player.isSpectator()) {
            return;
        }
        int w = g.guiWidth(), h = g.guiHeight();
        int right = w - 8;
        ItemStack held = mc.player.getMainHandItem();
        if (held.getItem() instanceof GunItem gun && gun.def() != null) {
            String ammo = GunItem.ammo(held) + " / " + GunStats.magazine(gun.def(), held);
            String sub = gun.def().name().toUpperCase() + "  " + GunItem.ammoType(held).name();
            g.drawString(mc.font, ammo, right - mc.font.width(ammo), h - 44, TEXT, true);
            small(g, mc, sub, right - smallWidth(mc, sub, 0.7f), h - 33, DIM, 0.7f);
        }
        // thin segmented line
        int lineW = 104, lx = right - lineW, ly = h - 24;
        for (int i = 0; i < 3; i++) {
            g.fill(lx + i * 36, ly, lx + i * 36 + 32, ly + 1, 0xCCFFFFFF);
        }
        float hp = mc.player.getHealth(), max = Math.max(1f, mc.player.getMaxHealth());
        int armour = armourPercent(mc);
        int stamina = Math.min(100, mc.player.getFoodData().getFoodLevel() * 100 / com.wardogs.combat.Stamina.FOOD_MAX);
        String s1 = stamina + "%", s2 = armour + "%", s3 = String.valueOf((int) Math.ceil(hp));
        small(g, mc, s1, lx + 2, h - 20, TEXT, 0.9f);
        // armour shield glyph then percentage
        g.fill(lx + 38, h - 20, lx + 44, h - 12, 0xFF8892A0);
        g.fill(lx + 39, h - 19, lx + 43, h - 13, armour > 0 ? 0xFF4A90E2 : 0xFF3A3F48);
        small(g, mc, s2, lx + 47, h - 20, TEXT, 0.9f);
        // heart that fills with health, then the number
        int hx = lx + 76, hy = h - 22, hs = 12;
        g.blitSprite(HEART_EMPTY, hx, hy, hs, hs);
        int filled = Math.round(hs * Math.max(0f, Math.min(1f, hp / max)));
        if (filled > 0) {
            g.enableScissor(hx, hy + hs - filled, hx + hs, hy + hs);
            g.blitSprite(HEART_FULL, hx, hy, hs, hs);
            g.disableScissor();
        }
        small(g, mc, s3, hx + hs + 2, h - 20, TEXT, 0.9f);
    }

    private static int armourPercent(Minecraft mc) {
        ItemStack vest = mc.player.getItemBySlot(EquipmentSlot.CHEST);
        if (vest.getItem() instanceof ArmorPieceItem && vest.getMaxDamage() > 0) {
            return Math.max(0, 100 - vest.getDamageValue() * 100 / vest.getMaxDamage());
        }
        return 0;
    }

    /** "[RMB] UNLOAD SUPPLIES > Into F.O.B" under the crosshair. */
    private static void renderPrompt(GuiGraphics g, Minecraft mc) {
        ClientPrompt.Prompt p = ClientPrompt.current(mc);
        if (p == null) {
            return;
        }
        int cx = g.guiWidth() / 2, y = g.guiHeight() / 2 + 18;
        String key = p.key();
        int kw = mc.font.width(key) + 6, aw = mc.font.width(p.action()) + 8;
        int total = kw + 2 + aw + (p.target().isEmpty() ? 0 : 10 + mc.font.width(p.target()));
        int x = cx - total / 2;
        g.fill(x, y, x + kw, y + 12, 0xCC0B0E12);
        g.renderOutline(x, y, kw, 12, 0xAAFFFFFF);
        g.drawString(mc.font, key, x + 3, y + 2, TEXT, false);
        g.fill(x + kw + 2, y, x + kw + 2 + aw, y + 12, 0xFFE7B93B);
        g.drawString(mc.font, p.action(), x + kw + 6, y + 2, 0xFF1A1405, false);
        if (!p.target().isEmpty()) {
            g.drawString(mc.font, "▸ " + p.target(), x + kw + aw + 6, y + 2, TEXT, true);
        }
    }

    /** Vehicle read-out: key list on the right, speed and fuel at the bottom centre, name above the stats. */
    private static void renderVehicle(GuiGraphics g, Minecraft mc) {
        if (!(mc.player.getVehicle() instanceof Vehicle v) || v.def() == null) {
            return;
        }
        var d = v.def();
        int w = g.guiWidth(), h = g.guiHeight();
        boolean driver = v.getControllingPassenger() == mc.player;
        List<String[]> keys = new java.util.ArrayList<>();
        keys.add(new String[]{"EXIT VEHICLE", "SNEAK"});
        if (driver) {
            keys.add(new String[]{"DRIVE", "W A S D"});
            if (d.gun() != null) {
                keys.add(new String[]{"FIRE", "HOLD USE"});
            }
            if (d.flying()) {
                keys.add(new String[]{"CLIMB / DESCEND", "LOOK UP / DOWN"});
            }
        }
        int y = h / 2 - 12;
        for (String[] k : keys) {
            small(g, mc, k[0], w - 8 - smallWidth(mc, k[0], 0.7f) - 6 - mc.font.width(k[1]) * 0.7f, y, DIM, 0.7f);
            small(g, mc, k[1], w - 8 - smallWidth(mc, k[1], 0.7f), y, TEXT, 0.7f);
            y += 9;
        }
        g.drawString(mc.font, d.name().toUpperCase(), w - 8 - mc.font.width(d.name().toUpperCase()), h - 60, TEXT, true);
        double speed = v.getDeltaMovement().horizontalDistance() * 20.0 * 3.6;
        String spd = "SPD " + (int) Math.round(speed) + " KM/H";
        int cx = w / 2;
        box(g, cx - 38, h - 28, 76, 11);
        g.drawCenteredString(mc.font, spd, cx, h - 26, TEXT);
        double fuel = d.fuel() <= 0 ? 0 : Math.max(0, Math.min(1, v.fuel() / d.fuel()));
        g.fill(cx - 30, h - 15, cx + 30, h - 12, 0x88000000);
        g.fill(cx - 30, h - 15, cx - 30 + (int) (60 * fuel), h - 12, fuel < 0.15 ? 0xFFFF5555 : 0xFFE7B93B);
        small(g, mc, String.format("HP %d/%d", (int) Math.ceil(v.getHealth()), (int) v.getMaxHealth()), cx - 30, h - 11, DIM, 0.6f);
    }
}
