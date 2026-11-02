package com.wardogs.client;

import com.mojang.blaze3d.vertex.PoseStack;
import com.mojang.math.Axis;
import com.wardogs.WardogsMod;
import com.wardogs.vehicle.Vehicle;
import net.minecraft.client.renderer.block.model.ItemTransforms;
import net.minecraft.client.resources.model.BakedModel;
import net.minecraft.client.resources.model.ModelResourceLocation;
import net.minecraft.world.item.ItemDisplayContext;
import net.minecraft.world.item.ItemStack;
import net.minecraft.world.item.Items;
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

/** Draws a vehicle from its box-built model (models/vehicle/&lt;id&gt;.json) stretched to its size; falls back to one stretched block. */
public class VehicleRenderer extends EntityRenderer<Vehicle> {
    private static final ItemStack ICON = new ItemStack(Items.STICK);

    private static final java.util.Map<String, Boolean> MESH = new java.util.concurrent.ConcurrentHashMap<>();

    /** True if the vehicle has a real mesh (models/vehicle/&lt;id&gt;.obj) instead of the old box model stretched to its size. */
    public static boolean isMesh(String id) {
        return MESH.computeIfAbsent(id, k -> Minecraft.getInstance().getResourceManager()
                .getResource(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "models/vehicle/" + k + ".obj")).isPresent());
    }

    public static void clearMeshCache() {
        MESH.clear();
        ROTORS = null;
    }

    private static java.util.Map<String, double[]> ROTORS;

    /** Hub position (x, y, z in blocks) of the separate main-rotor model of a helicopter, or null for other vehicles. */
    public static double[] rotorHub(String id) {
        if (ROTORS == null) {
            java.util.Map<String, double[]> m = new java.util.HashMap<>();
            var res = Minecraft.getInstance().getResourceManager().getResource(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "models/vehicle/rotors.json"));
            if (res.isPresent()) {
                try (var in = new java.io.InputStreamReader(res.get().open())) {
                    for (var e : com.google.gson.JsonParser.parseReader(in).getAsJsonObject().entrySet()) {
                        var a = e.getValue().getAsJsonArray();
                        m.put(e.getKey(), new double[]{a.get(0).getAsDouble(), a.get(1).getAsDouble(), a.get(2).getAsDouble()});
                    }
                } catch (java.io.IOException | RuntimeException ex) {
                    WardogsMod.LOGGER.warn("Cannot read the rotor list", ex);
                }
            }
            ROTORS = m;
        }
        return ROTORS.get(id);
    }

    public static ModelResourceLocation rotorLocation(String id) {
        return ModelResourceLocation.standalone(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "vehicle/" + id + "_rotor"));
    }

    public static ModelResourceLocation modelLocation(String id) {
        return ModelResourceLocation.standalone(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "vehicle/" + id));
    }

    public VehicleRenderer(EntityRendererProvider.Context ctx) {
        super(ctx);
    }

    @Override
    public void render(Vehicle v, float entityYaw, float partial, PoseStack pose, MultiBufferSource buffers, int light) {
        var def = v.def();
        if (def == null) {
            return;
        }
        var mm = Minecraft.getInstance().getModelManager();
        BakedModel model = mm.getModel(modelLocation(def.id()));
        if (model != mm.getMissingModel()) {
            pose.pushPose();
            pose.mulPose(Axis.YP.rotationDegrees(180.0f - Mth.rotLerp(partial, v.yRotO, v.getYRot())));
            if (isMesh(def.id())) {
                // a real mesh in blocks, origin on the ground at the vehicle's centre; ItemRenderer centres the model on (0.5, 0.5, 0.5)
                pose.translate(0.5, 0.5, 0.5);
            } else {
                pose.translate(0, def.h() / 2.0, 0);
                pose.scale((float) def.w(), (float) def.h(), (float) def.d());
            }
            Minecraft.getInstance().getItemRenderer().render(ICON, ItemDisplayContext.NONE, false, pose, buffers, light,
                    OverlayTexture.NO_OVERLAY, model);
            double[] hub = rotorHub(def.id());
            BakedModel rotor = hub == null ? null : mm.getModel(rotorLocation(def.id()));
            if (rotor != null && rotor != mm.getMissingModel()) {
                // the main rotor turns while anyone is aboard (engine running), and coasts still otherwise
                float angle = v.getPassengers().isEmpty() ? 0f : (v.tickCount + partial) * 70f;
                pose.pushPose();
                pose.translate(hub[0], hub[1], hub[2]);
                pose.mulPose(Axis.YP.rotationDegrees(angle));
                pose.translate(0.5, 0.5, 0.5);
                Minecraft.getInstance().getItemRenderer().render(ICON, ItemDisplayContext.NONE, false, pose, buffers, light,
                        OverlayTexture.NO_OVERLAY, rotor);
                pose.popPose();
            }
            pose.popPose();
            super.render(v, entityYaw, partial, pose, buffers, light);
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
