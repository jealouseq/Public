package com.wardogs.client;

import com.mojang.blaze3d.platform.InputConstants;
import com.wardogs.WardogsMod;
import com.wardogs.item.GunItem;
import com.wardogs.net.ReloadPayload;
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

    private ClientEvents() {}

    @EventBusSubscriber(modid = WardogsMod.MOD_ID, value = Dist.CLIENT)
    public static final class Mod {
        private Mod() {}

        @SubscribeEvent
        public static void keys(RegisterKeyMappingsEvent e) {
            e.register(RELOAD);
        }
    }

    @EventBusSubscriber(modid = WardogsMod.MOD_ID, value = Dist.CLIENT)
    public static final class Game {
        private Game() {}

        @SubscribeEvent
        public static void tick(ClientTickEvent.Post e) {
            Minecraft mc = Minecraft.getInstance();
            if (mc.player == null) {
                return;
            }
            while (RELOAD.consumeClick()) {
                if (mc.player.getMainHandItem().getItem() instanceof GunItem) {
                    PacketDistributor.sendToServer(new ReloadPayload());
                }
            }
        }
    }
}
