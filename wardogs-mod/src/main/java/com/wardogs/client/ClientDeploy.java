package com.wardogs.client;

import net.minecraft.client.Minecraft;

import java.util.List;

/** Client-only entry point for the deployment board screen. */
public final class ClientDeploy {
    private ClientDeploy() {}

    public static void open(List<String> names, List<String> keys) {
        Minecraft.getInstance().setScreen(new DeployScreen(names, keys));
    }
}
