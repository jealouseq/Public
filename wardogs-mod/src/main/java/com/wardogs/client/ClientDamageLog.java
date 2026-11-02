package com.wardogs.client;

import com.wardogs.net.DamageLogPayload;

import java.util.List;

/** The hits that put this player down, as last reported by the server. */
public final class ClientDamageLog {
    private static volatile List<DamageLogPayload.Line> lines = List.of();
    private static volatile boolean open;

    private ClientDamageLog() {}

    public static void update(DamageLogPayload p) {
        lines = p.lines();
        open = false;
    }

    public static List<DamageLogPayload.Line> lines() {
        return lines;
    }

    public static boolean open() {
        return open;
    }

    public static void toggle() {
        open = !open;
    }

    public static void clear() {
        lines = List.of();
        open = false;
    }
}
