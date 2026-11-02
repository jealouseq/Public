package com.wardogs.client;

import com.mojang.blaze3d.platform.InputConstants;
import com.wardogs.WardogsMod;
import com.wardogs.item.GunItem;
import com.wardogs.net.ReloadPayload;
import com.wardogs.net.VehicleFirePayload;
import com.wardogs.vehicle.Vehicle;
import net.minecraft.client.KeyMapping;
import net.minecraft.client.Minecraft;
import net.neoforged.api.distmarker.Dist;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.client.event.ClientTickEvent;
import net.neoforged.neoforge.client.event.RegisterKeyMappingsEvent;
import net.neoforged.neoforge.network.PacketDistributor;
import org.lwjgl.glfw.GLFW;

public final class ClientEvents {
    public static final KeyMapping RELOAD = new KeyMapping("key.wardogs.reload", InputConstants.Type.KEYSYM,
            GLFW.GLFW_KEY_R, "key.categories.wardogs");

    public static final KeyMapping SHOP = new KeyMapping("key.wardogs.shop", InputConstants.Type.KEYSYM,
            GLFW.GLFW_KEY_B, "key.categories.wardogs");

    public static final KeyMapping MAP = new KeyMapping("key.wardogs.map", InputConstants.Type.KEYSYM,
            GLFW.GLFW_KEY_M, "key.categories.wardogs");

    /** While downed: give up (die at once) and call for a medic. */
    public static final KeyMapping GIVE_UP = new KeyMapping("key.wardogs.give_up", InputConstants.Type.KEYSYM,
            GLFW.GLFW_KEY_X, "key.categories.wardogs");

    public static final KeyMapping CALL_HELP = new KeyMapping("key.wardogs.call_help", InputConstants.Type.KEYSYM,
            GLFW.GLFW_KEY_G, "key.categories.wardogs");

    public static final KeyMapping DAMAGE_LOG = new KeyMapping("key.wardogs.damage_log", InputConstants.Type.KEYSYM,
            GLFW.GLFW_KEY_V, "key.categories.wardogs");

    private ClientEvents() {}

    @EventBusSubscriber(modid = WardogsMod.MOD_ID, value = Dist.CLIENT)
    public static final class Mod {
        private Mod() {}

        @SubscribeEvent
        public static void keys(RegisterKeyMappingsEvent e) {
            e.register(RELOAD);
            e.register(SHOP);
            e.register(MAP);
            e.register(GIVE_UP);
            e.register(CALL_HELP);
            e.register(DAMAGE_LOG);
        }
    }

    @EventBusSubscriber(modid = WardogsMod.MOD_ID, value = Dist.CLIENT)
    public static final class Game {
        private static boolean wasRiding;
        private static net.minecraft.client.CameraType cameraBefore;

        private Game() {}

        @SubscribeEvent
        public static void mouse(net.neoforged.neoforge.client.event.InputEvent.MouseButton.Pre e) {
            Minecraft mc = Minecraft.getInstance();
            if (e.getButton() == GLFW.GLFW_MOUSE_BUTTON_MIDDLE && e.getAction() == GLFW.GLFW_PRESS && mc.screen == null && mc.player != null
                    && ClientHud.get() != null && ClientHud.get().active() && ClientHud.get().faction() >= 0) {
                mc.setScreen(new RadialScreen());
                e.setCanceled(true);
            }
        }

        /** The crew of a closed vehicle is inside it: their models are not drawn over the body. */
        @SubscribeEvent
        public static void hideCrew(net.neoforged.neoforge.client.event.RenderPlayerEvent.Pre e) {
            if (e.getEntity().getVehicle() instanceof Vehicle v && Vehicle.enclosed(v.def())) {
                e.setCanceled(true);
            }
        }

        @SubscribeEvent
        public static void tick(ClientTickEvent.Post e) {
            Minecraft mc = Minecraft.getInstance();
            if (mc.player == null) {
                return;
            }
            // Holding the use button in an armed vehicle fires its mounted weapon.
            if (mc.screen == null && mc.options.keyUse.isDown() && mc.player.getVehicle() instanceof Vehicle v
                    && v.def() != null && v.def().gun() != null && v.getControllingPassenger() == mc.player) {
                PacketDistributor.sendToServer(new VehicleFirePayload());
            }
            // Holding use with empty hands next to a friendly MG nest fires it.
            if (mc.screen == null && mc.options.keyUse.isDown() && mc.player.getVehicle() == null && mc.player.getMainHandItem().isEmpty()) {
                double r = com.wardogs.build.Emplacements.mgUseRadius() + 3;
                for (var b : mc.level.getEntitiesOfClass(com.wardogs.build.Buildable.class, mc.player.getBoundingBox().inflate(r))) {
                    if (b.def() != null && b.def().id().equals("mg_nest")) {
                        PacketDistributor.sendToServer(new VehicleFirePayload());
                        break;
                    }
                }
            }
            // Driving is seen from behind the vehicle, like the real game; the camera returns to what it was on exit.
            boolean riding = mc.player.getVehicle() instanceof Vehicle;
            if (riding && !wasRiding) {
                cameraBefore = mc.options.getCameraType();
                mc.options.setCameraType(net.minecraft.client.CameraType.THIRD_PERSON_BACK);
            } else if (!riding && wasRiding && cameraBefore != null) {
                mc.options.setCameraType(cameraBefore);
                cameraBefore = null;
            }
            wasRiding = riding;
            boolean downed = ClientHud.get() != null && ClientHud.get().downed() > 0;
            while (GIVE_UP.consumeClick()) {
                if (downed && mc.screen == null) {
                    PacketDistributor.sendToServer(new com.wardogs.net.RequestPayload("giveup"));
                }
            }
            while (DAMAGE_LOG.consumeClick()) {
                if (downed) {
                    ClientDamageLog.toggle();
                }
            }
            if (!downed && !ClientDamageLog.lines().isEmpty()) {
                ClientDamageLog.clear();
            }
            while (CALL_HELP.consumeClick()) {
                if (downed && mc.screen == null) {
                    PacketDistributor.sendToServer(new com.wardogs.net.RequestPayload("heal"));
                }
            }
            while (MAP.consumeClick()) {
                if (mc.screen == null) {
                    mc.setScreen(new MapScreen());
                }
            }
            while (SHOP.consumeClick()) {
                if (mc.level.dimension().equals(com.wardogs.world.BakuraniWorld.DIMENSION)) {
                    var near = mc.level.getEntitiesOfClass(com.wardogs.vendor.VendorEntity.class, mc.player.getBoundingBox().inflate(8));
                    if (near.isEmpty()) {
                        mc.player.displayClientMessage(net.minecraft.network.chat.Component.literal("Go to a vendor in your base"), true);
                    } else {
                        mc.setScreen(new ShopScreen(near.get(0).kind().categories));
                    }
                } else {
                    mc.setScreen(new ShopScreen());
                }
            }
            while (RELOAD.consumeClick()) {
                if (mc.player.getMainHandItem().getItem() instanceof GunItem) {
                    PacketDistributor.sendToServer(new ReloadPayload());
                }
            }
        }
    }
}
