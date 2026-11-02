package com.wardogs.client;

import com.mojang.blaze3d.vertex.PoseStack;
import com.mojang.blaze3d.vertex.VertexConsumer;
import com.wardogs.WardogsMod;
import com.wardogs.balance.Balance;
import com.wardogs.balance.StructureDef;
import com.wardogs.build.Building;
import com.wardogs.build.Buildable;
import com.wardogs.item.HammerItem;
import net.minecraft.client.Minecraft;
import net.minecraft.client.renderer.LevelRenderer;
import net.minecraft.client.renderer.RenderType;
import net.minecraft.core.Direction;
import net.minecraft.world.phys.AABB;
import net.minecraft.world.phys.BlockHitResult;
import net.minecraft.world.phys.HitResult;
import net.minecraft.world.phys.Vec3;
import net.neoforged.api.distmarker.Dist;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.client.event.RenderLevelStageEvent;

/** The build ghost: while holding a hammer, an outline of the selected structure where it would stand (green = can be built, red = not). */
@EventBusSubscriber(modid = WardogsMod.MOD_ID, value = Dist.CLIENT)
public final class BuildPreview {
    private BuildPreview() {}

    /** The footprint box of a structure placed at {@code pos} facing {@code yaw}, as {@link Building#place} computes it. */
    public static AABB boxFor(StructureDef def, Vec3 pos, float yaw) {
        float snapped = Building.snapYaw(yaw);
        boolean turned = Math.round(snapped / 90.0f) % 2 != 0;
        double hx = (turned ? def.d() : def.w()) / 2.0, hz = (turned ? def.w() : def.d()) / 2.0;
        return new AABB(pos.x - hx, pos.y + 0.02, pos.z - hz, pos.x + hx, pos.y + def.h(), pos.z + hz);
    }

    @SubscribeEvent
    public static void render(RenderLevelStageEvent e) {
        if (e.getStage() != RenderLevelStageEvent.Stage.AFTER_PARTICLES) {
            return;
        }
        Minecraft mc = Minecraft.getInstance();
        if (mc.player == null || mc.level == null || !(mc.player.getMainHandItem().getItem() instanceof HammerItem hammer)
                || !(mc.hitResult instanceof BlockHitResult hit) || hit.getType() != HitResult.Type.BLOCK || hit.getDirection() != Direction.UP) {
            return;
        }
        StructureDef def = hammer.selected(mc.player.getMainHandItem());
        Vec3 pos = hit.getLocation();
        AABB box = boxFor(def, pos, mc.player.getYRot());
        boolean ok = mc.level.noBlockCollision(null, box) && mc.level.getEntitiesOfClass(Buildable.class, box.deflate(0.01)).isEmpty();
        if (ok && def.tier() != StructureDef.Tier.SMALL) {
            var hud = ClientHud.get();
            int faction = hud == null ? -1 : hud.faction();
            double r = Balance.get().fobRadius();
            ok = mc.level.getEntitiesOfClass(Buildable.class, mc.player.getBoundingBox().inflate(r + 8)).stream()
                    .anyMatch(b -> b.isFob() && b.faction() == faction && b.position().multiply(1, 0, 1).distanceTo(pos.multiply(1, 0, 1)) <= r);
        }
        Vec3 cam = e.getCamera().getPosition();
        PoseStack pose = e.getPoseStack();
        var buffers = mc.renderBuffers().bufferSource();
        VertexConsumer vc = buffers.getBuffer(RenderType.lines());
        LevelRenderer.renderLineBox(pose, vc, box.move(-cam.x, -cam.y, -cam.z), ok ? 0.2f : 1.0f, ok ? 1.0f : 0.2f, 0.2f, 1.0f);
        buffers.endBatch(RenderType.lines());
    }
}
