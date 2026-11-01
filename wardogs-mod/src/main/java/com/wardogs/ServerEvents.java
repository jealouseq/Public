package com.wardogs;

import com.wardogs.balance.Balance;
import com.wardogs.combat.ShooterState;
import com.wardogs.command.WdCommand;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.event.AddReloadListenerEvent;
import net.neoforged.neoforge.event.RegisterCommandsEvent;
import net.neoforged.neoforge.event.entity.player.PlayerEvent;

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
    public static void onLogout(PlayerEvent.PlayerLoggedOutEvent e) {
        ShooterState.remove(e.getEntity().getUUID());
    }
}
