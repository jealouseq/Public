package com.wardogs.client;

import com.mojang.blaze3d.vertex.PoseStack;
import com.mojang.math.Axis;
import com.wardogs.vehicle.Vehicle;
import net.minecraft.client.Minecraft;
import net.minecraft.client.renderer.MultiBufferSource;
import net.minecraft.client.renderer.entity.EntityRenderer;
import net.minecraft.client.renderer.entity.EntityRendererProvider;
import net.minecraft.client.renderer.texture.OverlayTexture;
import net.minecraft.core.registries.BuiltInRegistries;
import net.minecraft.resources.ResourceLocation;
import net.minecraft.util.Mth;
import net.minecraft.world.inventory.InventoryMenu;
import net.minecraft.world.level.block.Blocks;
import net.minecraft.world.level.block.state.BlockState;

/** Draws a vehicle as one block stretched to its length, width and height (placeholder until real models exist). */
public class VehicleRenderer extends EntityRenderer<Vehicle> {
    public VehicleRenderer(EntityRendererProvider.Context ctx) {
        super(ctx);
    }

    @Override
    public void render(Vehicle v, float entityYaw, float partial, PoseStack pose, MultiBufferSource buffers, int light) {
        var def = v.def();
        if (def == null) {
            return;
        }
        BlockState state = BuiltInRegistries.BLOCK.get(ResourceLocation.parse(def.block())).defaultBlockState();
        if (state.isAir()) {
            state = Blocks.GRAY_CONCRETE.defaultBlockState();
        }
        pose.pushPose();
        pose.mulPose(Axis.YP.rotationDegrees(180.0f - Mth.rotLerp(partial, v.yRotO, v.getYRot())));
        pose.translate(-def.w() / 2.0, 0, -def.d() / 2.0);
        pose.scale((float) def.w(), (float) def.h(), (float) def.d());
        Minecraft.getInstance().getBlockRenderer().renderSingleBlock(state, pose, buffers, light, OverlayTexture.NO_OVERLAY);
        pose.popPose();
        super.render(v, entityYaw, partial, pose, buffers, light);
    }

    @Override
    public ResourceLocation getTextureLocation(Vehicle v) {
        return InventoryMenu.BLOCK_ATLAS;
    }
}
