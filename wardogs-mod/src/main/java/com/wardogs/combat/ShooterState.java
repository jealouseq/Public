package com.wardogs.combat;

import java.util.Map;
import java.util.UUID;
import java.util.concurrent.ConcurrentHashMap;

/** Server-side, non-persistent per-player firing state (fire-rate scheduling, reload, burst). */
public final class ShooterState {
    private static final Map<UUID, ShooterState> STATES = new ConcurrentHashMap<>();

    /** Game time (in ticks, fractional) at which the next shot is allowed. */
    public double nextShotTime;
    /** Game time when the current reload completes; negative if not reloading. */
    public long reloadEndsAt = -1;
    public int reloadSlot = -1;
    public int burstLeft;

    public static ShooterState of(UUID id) {
        return STATES.computeIfAbsent(id, k -> new ShooterState());
    }

    public static void remove(UUID id) {
        STATES.remove(id);
    }

    public boolean reloading() {
        return reloadEndsAt >= 0;
    }

    public void cancelReload() {
        reloadEndsAt = -1;
        reloadSlot = -1;
    }
}
