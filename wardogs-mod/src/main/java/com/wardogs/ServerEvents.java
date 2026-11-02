package com.wardogs;

import com.wardogs.balance.Balance;
import com.wardogs.combat.ShooterState;
import com.wardogs.command.WdCommand;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.event.AddReloadListenerEvent;
import net.neoforged.neoforge.event.RegisterCommandsEvent;
import com.wardogs.net.HudPayload;
import com.wardogs.net.ProgressPayload;
import com.wardogs.progress.Progression;
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
            ProgressPayload.sendTo(sp);
        }
    }

    /** Keeps every client's HUD fresh once a second. */
    @SubscribeEvent
    public static void onServerTick(ServerTickEvent.Post e) {
        if (e.getServer().getTickCount() % 20 != 0) {
            return;
        }
        for (ServerPlayer p : e.getServer().getPlayerList().getPlayers()) {
            keepOnTheMap(p);
            HudPayload.sendTo(p);
            if (Progression.consumeDirty(p)) {
                ProgressPayload.sendTo(p);
            }
        }
    }

    /**
     * Bakurani is a 16.3 km square. Vanilla keeps every dimension's border in sync with the overworld's, so the edge is enforced
     * here instead: a player who walks off the map is put back on it.
     */
    private static void keepOnTheMap(ServerPlayer p) {
        if (!p.level().dimension().equals(com.wardogs.world.BakuraniWorld.DIMENSION)) {
            return;
        }
        double half = com.wardogs.world.BakuraniWorld.WORLD_SIZE / 2.0 - 8;
        double x = Math.max(-half, Math.min(half, p.getX()));
        double z = Math.max(-half, Math.min(half, p.getZ()));
        if (x != p.getX() || z != p.getZ()) {
            p.teleportTo(x, p.getY(), z);
            p.displayClientMessage(net.minecraft.network.chat.Component.literal("Edge of the map"), true);
        }
    }

    @SubscribeEvent
    public static void onLogout(PlayerEvent.PlayerLoggedOutEvent e) {
        ShooterState.remove(e.getEntity().getUUID());
    }
}
