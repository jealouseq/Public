package com.wardogs.client;

import com.wardogs.vendor.VendorEntity;
import net.minecraft.client.Minecraft;

/** Client-only entry point for opening the vendor screen (kept out of common classes so a server never loads the screen). */
public final class ClientShop {
    private ClientShop() {}

    public static void open(String vendorKind) {
        Minecraft.getInstance().setScreen(new ShopScreen(VendorEntity.Kind.valueOf(vendorKind).categories));
    }
}
