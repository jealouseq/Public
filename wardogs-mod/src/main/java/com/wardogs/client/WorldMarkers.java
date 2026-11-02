package com.wardogs.client;

import com.wardogs.WardogsMod;
import com.wardogs.net.HudPayload;
import net.minecraft.client.Minecraft;
import net.minecraft.client.gui.GuiGraphics;
import net.minecraft.world.level.levelgen.Heightmap;
import net.minecraft.world.phys.Vec3;
import net.neoforged.api.distmarker.Dist;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.client.event.RenderLevelStageEvent;
import org.joml.Matrix4f;
import org.joml.Vector4f;

/**
 * Markers drawn over the world like the real game: the Hot Zone as a yellow ring with its distance, squad mates as a green diamond
 * with name and distance. The view and projection matrices of the last rendered frame project a world position onto the screen.
 */
@EventBusSubscriber(modid = WardogsMod.MOD_ID, value = Dist.CLIENT)
public final class WorldMarkers {
    private static final Matrix4f VIEW = new Matrix4f(), PROJECTION = new Matrix4f();
    private static boolean captured;

    private WorldMarkers() {}

    @SubscribeEvent
    public static void capture(RenderLevelStageEvent e) {
        if (e.getStage() == RenderLevelStageEvent.Stage.AFTER_PARTICLES) {
            VIEW.set(e.getModelViewMatrix());
            PROJECTION.set(e.getProjectionMatrix());
            captured = true;
        }
    }

    /** Screen position (gui pixels) of a world point, or null when it is behind the camera or off screen. */
    static int[] project(Minecraft mc, double x, double y, double z, int guiW, int guiH) {
        if (!captured) {
            return null;
        }
        Vec3 cam = mc.gameRenderer.getMainCamera().getPosition();
        Vector4f v = new Vector4f((float) (x - cam.x), (float) (y - cam.y), (float) (z - cam.z), 1f);
        v.mul(VIEW);
        v.mul(PROJECTION);
        if (v.w <= 0.05f) {
            return null;
        }
        float nx = v.x / v.w, ny = v.y / v.w;
        if (Math.abs(nx) > 0.96f || Math.abs(ny) > 0.92f) {
            return null;
        }
        return new int[]{Math.round((nx * 0.5f + 0.5f) * guiW), Math.round((1f - (ny * 0.5f + 0.5f)) * guiH)};
    }

    private static double groundY(Minecraft mc, double x, double z) {
        int bx = (int) Math.floor(x), bz = (int) Math.floor(z);
        if (mc.level.getChunkSource().getChunkNow(bx >> 4, bz >> 4) != null) {
            return mc.level.getHeight(Heightmap.Types.WORLD_SURFACE, bx, bz) + 3;
        }
        return mc.player.getY() + 3;
    }

    private static void centred(GuiGraphics g, Minecraft mc, String s, int x, int y, int color, float scale) {
        g.pose().pushPose();
        g.pose().translate(x, y, 0);
        g.pose().scale(scale, scale, 1f);
        Ui.text(g, mc.font, s, -Ui.width(mc.font, s) / 2, 0, color, true);
        g.pose().popPose();
    }

    static void render(GuiGraphics g, Minecraft mc, HudPayload h) {
        if (h == null || !h.active() || h.hardcore() || mc.player == null) {
            return;
        }
        int gw = g.guiWidth(), gh = g.guiHeight();
        if (h.hr() > 0) {
            double dist = Math.hypot(h.hx() - mc.player.getX(), h.hz() - mc.player.getZ());
            int[] p = project(mc, h.hx(), groundY(mc, h.hx(), h.hz()), h.hz(), gw, gh);
            if (p != null && dist > h.hr() * 0.6) {
                int cx = p[0], cy = p[1];
                g.fill(cx - 7, cy - 7, cx + 8, cy + 8, 0xAA000000);
                g.renderOutline(cx - 7, cy - 7, 15, 15, 0xFFFFD54A);
                centred(g, mc, "HZ", cx, cy - 3, 0xFFFFD54A, 0.7f);
                centred(g, mc, (int) dist + " m", cx, cy - 16, 0xFFE8EAEE, 0.65f);
            }
        }
        for (var m : ClientSquad.members()) {
            double dist = Math.hypot(m.x() - mc.player.getX(), m.z() - mc.player.getZ());
            if (dist < 4 || dist > 400) {
                continue;
            }
            int[] p = project(mc, m.x(), groundY(mc, m.x(), m.z()), m.z(), gw, gh);
            if (p == null) {
                continue;
            }
            int cx = p[0], cy = p[1];
            int col = m.downed() ? 0xFFEF4444 : m.leader() ? 0xFFFFD54A : 0xFF4ADE80;
            for (int i = 0; i < 4; i++) { // a diamond
                g.fill(cx - 3 + i, cy - i, cx + 4 - i, cy - i + 1, col);
                g.fill(cx - 3 + i, cy + i, cx + 4 - i, cy + i + 1, col);
            }
            centred(g, mc, m.name(), cx, cy - 14, 0xFFE8EAEE, 0.65f);
            centred(g, mc, (int) dist + "m", cx, cy + 6, 0xFFB8BEC8, 0.55f);
        }
    }
}
