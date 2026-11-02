package com.wardogs.client;

import java.util.ArrayList;
import java.util.List;

/** Recent reward lines for the HUD log (plain data, safe on any side). */
public final class ClientRewards {
    public record Line(String label, long amount, long time) {}

    private static final List<Line> LINES = new ArrayList<>();
    public static final long LIFETIME_MS = 7000;
    private static final int MAX = 6;

    private ClientRewards() {}

    public static synchronized void add(String label, long amount) {
        LINES.add(0, new Line(label, amount, System.currentTimeMillis()));
        while (LINES.size() > MAX) {
            LINES.remove(LINES.size() - 1);
        }
    }

    /** Lines still visible, newest first. */
    public static synchronized List<Line> visible() {
        long now = System.currentTimeMillis();
        LINES.removeIf(l -> now - l.time() > LIFETIME_MS);
        return new ArrayList<>(LINES);
    }
}
