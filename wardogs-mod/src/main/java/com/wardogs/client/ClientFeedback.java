package com.wardogs.client;

import com.wardogs.net.KillFeedPayload;
import net.minecraft.client.Minecraft;
import net.minecraft.sounds.SoundEvents;

import java.util.ArrayList;
import java.util.List;

/** Short-lived combat feedback: the hit marker at the crosshair and the kill feed lines. */
public final class ClientFeedback {
    public static final long HIT_MS = 280, FEED_MS = 7000;
    public record Line(KillFeedPayload kill, long time) {}

    private static volatile long hitTime;
    private static volatile int hitKind;
    private static final List<Line> FEED = new ArrayList<>();

    private ClientFeedback() {}

    public static void hit(int kind) {
        hitTime = System.currentTimeMillis();
        hitKind = kind;
        var mc = Minecraft.getInstance();
        if (mc.player != null) {
            mc.player.playSound(kind >= 2 ? SoundEvents.NOTE_BLOCK_BELL.value() : SoundEvents.NOTE_BLOCK_HAT.value(), 0.6f, kind == 1 || kind == 3 ? 1.6f : 1.2f);
        }
    }

    /** @return the kind of the marker to draw (0..3) or -1 if none is showing. */
    public static int markerKind() {
        return System.currentTimeMillis() - hitTime < HIT_MS ? hitKind : -1;
    }

    public static float markerAge() {
        return (System.currentTimeMillis() - hitTime) / (float) HIT_MS;
    }

    public static synchronized void feed(KillFeedPayload p) {
        FEED.add(new Line(p, System.currentTimeMillis()));
        while (FEED.size() > 6) {
            FEED.remove(0);
        }
    }

    public static synchronized List<Line> lines() {
        long now = System.currentTimeMillis();
        FEED.removeIf(l -> now - l.time() > FEED_MS);
        return new ArrayList<>(FEED);
    }
}
