package com.wardogs.client;

import com.mojang.blaze3d.vertex.PoseStack;
import com.wardogs.build.Buildable;
import net.minecraft.client.Minecraft;
import net.minecraft.client.renderer.MultiBufferSource;
import net.minecraft.client.renderer.entity.EntityRenderer;
import net.minecraft.client.renderer.entity.EntityRendererProvider;
import net.minecraft.client.renderer.texture.OverlayTexture;
import net.minecraft.core.registries.BuiltInRegistries;
import net.minecraft.resources.ResourceLocation;
import net.minecraft.world.inventory.InventoryMenu;
import net.minecraft.world.level.block.Blocks;
import net.minecraft.world.level.block.state.BlockState;
import net.minecraft.world.phys.AABB;

/** Draws a structure as a single block stretched to its bounding box (placeholder until real models exist). */
public class BuildableRenderer extends EntityRenderer<Buildable> {
    public BuildableRenderer(EntityRendererProvider.Context ctx) {
        super(ctx);
    }

    @Override
    public void render(Buildable e, float yaw, float partial, PoseStack pose, MultiBufferSource buffers, int light) {
        var def = e.def();
        if (def == null) {
            return;
        }
        BlockState state = BuiltInRegistries.BLOCK.get(ResourceLocation.parse(def.block())).defaultBlockState();
        if (state.isAir()) {
            state = Blocks.STONE.defaultBlockState();
        }
        AABB bb = e.getBoundingBox();
        pose.pushPose();
        pose.translate(-bb.getXsize() / 2.0, 0, -bb.getZsize() / 2.0);
        pose.scale((float) bb.getXsize(), (float) bb.getYsize(), (float) bb.getZsize());
        Minecraft.getInstance().getBlockRenderer().renderSingleBlock(state, pose, buffers, light, OverlayTexture.NO_OVERLAY);
        pose.popPose();
        super.render(e, yaw, partial, pose, buffers, light);
    }

    @Override
    public ResourceLocation getTextureLocation(Buildable e) {
        return InventoryMenu.BLOCK_ATLAS;
    }
}
