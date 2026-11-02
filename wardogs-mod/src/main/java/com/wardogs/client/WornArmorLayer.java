package com.wardogs.client;

import com.mojang.blaze3d.vertex.PoseStack;
import com.wardogs.WardogsMod;
import com.wardogs.item.ArmorPieceItem;
import net.minecraft.client.Minecraft;
import net.minecraft.client.model.PlayerModel;
import net.minecraft.client.player.AbstractClientPlayer;
import net.minecraft.client.renderer.MultiBufferSource;
import net.minecraft.client.renderer.entity.RenderLayerParent;
import net.minecraft.client.renderer.entity.layers.RenderLayer;
import net.minecraft.client.renderer.texture.OverlayTexture;
import net.minecraft.client.resources.model.BakedModel;
import net.minecraft.client.resources.model.ModelResourceLocation;
import net.minecraft.resources.ResourceLocation;
import net.minecraft.world.entity.EquipmentSlot;
import net.minecraft.world.item.ItemDisplayContext;
import net.minecraft.world.item.ItemStack;
import net.minecraft.world.item.Items;
import net.neoforged.api.distmarker.Dist;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.client.event.EntityRenderersEvent;
import net.neoforged.neoforge.client.event.ModelEvent;

/**
 * Draws the helmet and the vest a player wears on their head and body, so equipment is visible on the character like in the real game.
 * The meshes are optional resources (models/armor/&lt;armor id&gt;.json, in the frame of the player's head or body part: y down, 1/16 block
 * units): where a build has none, nothing is drawn.
 */
public final class WornArmorLayer extends RenderLayer<AbstractClientPlayer, PlayerModel<AbstractClientPlayer>> {
    private static final ItemStack CARRIER = new ItemStack(Items.STICK);
    private static final java.util.Map<String, Boolean> PRESENT = new java.util.concurrent.ConcurrentHashMap<>();

    public WornArmorLayer(RenderLayerParent<AbstractClientPlayer, PlayerModel<AbstractClientPlayer>> parent) {
        super(parent);
    }

    static ModelResourceLocation location(String armorId) {
        return ModelResourceLocation.standalone(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "armor/" + armorId));
    }

    static boolean present(String armorId) {
        return PRESENT.computeIfAbsent(armorId, id -> Minecraft.getInstance().getResourceManager()
                .getResource(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "models/armor/" + id + ".json")).isPresent());
    }

    public static void clearCache() {
        PRESENT.clear();
    }

    @Override
    public void render(PoseStack pose, MultiBufferSource buffers, int light, AbstractClientPlayer player, float limbSwing, float limbSwingAmount,
                       float partial, float age, float headYaw, float headPitch) {
        draw(pose, buffers, light, player.getItemBySlot(EquipmentSlot.HEAD), true);
        draw(pose, buffers, light, player.getItemBySlot(EquipmentSlot.CHEST), false);
    }

    private void draw(PoseStack pose, MultiBufferSource buffers, int light, ItemStack worn, boolean head) {
        if (!(worn.getItem() instanceof ArmorPieceItem piece) || piece.def() == null || !present(piece.def().id())) {
            return;
        }
        var mm = Minecraft.getInstance().getModelManager();
        BakedModel model = mm.getModel(location(piece.def().id()));
        if (model == mm.getMissingModel()) {
            return;
        }
        pose.pushPose();
        (head ? getParentModel().head : getParentModel().body).translateAndRotate(pose);
        pose.translate(0.5, 0.5, 0.5); // the item renderer centres a model on (0.5, 0.5, 0.5)
        Minecraft.getInstance().getItemRenderer().render(CARRIER, ItemDisplayContext.NONE, false, pose, buffers, light, OverlayTexture.NO_OVERLAY, model);
        pose.popPose();
    }

    @EventBusSubscriber(modid = WardogsMod.MOD_ID, value = Dist.CLIENT)
    public static final class Registration {
        private Registration() {}

        @SubscribeEvent
        public static void models(ModelEvent.RegisterAdditional e) {
            clearCache();
            for (var def : com.wardogs.balance.Balance.get().armor()) {
                if (present(def.id())) {
                    e.register(location(def.id()));
                }
            }
        }

        @SubscribeEvent
        public static void layers(EntityRenderersEvent.AddLayers e) {
            for (var skin : e.getSkins()) {
                if (e.getSkin(skin) instanceof net.minecraft.client.renderer.entity.player.PlayerRenderer renderer) {
                    renderer.addLayer(new WornArmorLayer(renderer));
                }
            }
        }
    }
}
