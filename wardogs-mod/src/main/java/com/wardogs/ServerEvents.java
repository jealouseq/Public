package com.wardogs;

import com.wardogs.balance.Balance;
import com.wardogs.combat.ShooterState;
import com.wardogs.command.WdCommand;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.event.AddReloadListenerEvent;
import net.neoforged.neoforge.event.RegisterCommandsEvent;
import com.wardogs.net.HudPayload;
import net.minecraft.server.level.ServerPlayer;
import net.neoforged.neoforge.event.entity.player.PlayerEvent;
import net.neoforged.neoforge.event.tick.ServerTickEvent;

@EventBusSubscriber(modid = WardogsMod.MOD_ID)
public final class ServerEvents {
    private ServerEvents() {}

    @SubscribeEvent
    public static void onReloadListeners(AddReloadListenerEvent e) {
        e.addListener(new Balance.Reloader());
    }

    @SubscribeEvent
    public static void onCommands(RegisterCommandsEvent e) {
        WdCommand.register(e.getDispatcher());
    }

    @SubscribeEvent
    public static void onLogin(PlayerEvent.PlayerLoggedInEvent e) {
        if (e.getEntity() instanceof ServerPlayer sp) {
            HudPayload.sendTo(sp);
        }
    }

    /** Keeps every client's HUD fresh once a second. */
    @SubscribeEvent
    public static void onServerTick(ServerTickEvent.Post e) {
        if (e.getServer().getTickCount() % 20 != 0) {
            return;
        }
        for (ServerPlayer p : e.getServer().getPlayerList().getPlayers()) {
            HudPayload.sendTo(p);
        }
    }

    @SubscribeEvent
    public static void onLogout(PlayerEvent.PlayerLoggedOutEvent e) {
        ShooterState.remove(e.getEntity().getUUID());
    }
}
