package com.wardogs.client;

import com.wardogs.net.HudPayload;

/** Latest HUD data received from the server. Plain data: safe to load on any side. */
public final class ClientHud {
    private static volatile HudPayload latest;

    private ClientHud() {}

    public static void update(HudPayload p) {
        latest = p;
    }

    public static HudPayload get() {
        return latest;
    }
}
