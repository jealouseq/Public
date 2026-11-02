package com.wardogs.world;

import com.wardogs.WardogsMod;
import net.minecraft.network.chat.Component;
import net.minecraft.server.level.ServerLevel;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.event.tick.ServerTickEvent;

import java.util.ArrayDeque;
import java.util.Collection;
import java.util.List;
import java.util.function.Consumer;

/**
 * Bakurani's one-time setup (towers, vendors, loot, boards, landmarks, outposts, parked cars) generates a lot of chunks, which can take
 * minutes. It is cut into small steps and run one per server tick, so the server never stalls for the 60 seconds the watchdog allows.
 */
@EventBusSubscriber(modid = WardogsMod.MOD_ID)
public final class SetupQueue {
    public record Step(String label, Consumer<ServerLevel> run) {}

    private static final ArrayDeque<Step> QUEUE = new ArrayDeque<>();
    private static ServerLevel level;
    private static int total, done;
    private static long started;

    private SetupQueue() {}

    /** Starts a run with these steps; steps still waiting from an earlier run are kept and run first. */
    public static synchronized void start(ServerLevel lvl, Collection<Step> steps) {
        if (QUEUE.isEmpty()) {
            total = 0;
            done = 0;
            started = System.currentTimeMillis();
        }
        level = lvl;
        QUEUE.addAll(steps);
        total += steps.size();
    }

    public static synchronized int pending() {
        return QUEUE.size();
    }

    public static synchronized int total() {
        return total;
    }

    /** Runs the next step now (used by tests). @return false if there was none. */
    public static boolean runNext() {
        Step s;
        ServerLevel l;
        synchronized (SetupQueue.class) {
            s = QUEUE.poll();
            l = level;
        }
        if (s == null || l == null) {
            return false;
        }
        try {
            s.run().accept(l);
        } catch (RuntimeException e) {
            WardogsMod.LOGGER.error("Bakurani setup step '{}' failed", s.label(), e);
        }
        synchronized (SetupQueue.class) {
            done++;
            if (QUEUE.isEmpty()) {
                long secs = (System.currentTimeMillis() - started) / 1000;
                WardogsMod.LOGGER.info("Bakurani setup finished: {} steps in {} s", total, secs);
                l.getServer().getPlayerList().broadcastSystemMessage(Component.literal("Bakurani setup finished (" + total + " steps, " + secs + " s)"), false);
            }
        }
        return true;
    }

    @SubscribeEvent
    public static void onTick(ServerTickEvent.Post e) {
        if (pending() > 0) {
            runNext();
        }
    }

    /** Forces (or releases) every chunk of a block rectangle, so chunks loaded by one step are still there for the next. */
    public static void force(ServerLevel l, int x0, int z0, int x1, int z1, boolean on) {
        for (int cx = x0 >> 4; cx <= x1 >> 4; cx++) {
            for (int cz = z0 >> 4; cz <= z1 >> 4; cz++) {
                l.setChunkForced(cx, cz, on);
            }
        }
    }

    /** Steps that force and generate the chunks of a rectangle in batches of eight chunks. */
    public static void loadSteps(List<Step> out, String label, int x0, int z0, int x1, int z1) {
        List<int[]> chunks = new java.util.ArrayList<>();
        for (int cx = x0 >> 4; cx <= x1 >> 4; cx++) {
            for (int cz = z0 >> 4; cz <= z1 >> 4; cz++) {
                chunks.add(new int[]{cx, cz});
            }
        }
        for (int i = 0; i < chunks.size(); i += 8) {
            List<int[]> batch = chunks.subList(i, Math.min(chunks.size(), i + 8));
            out.add(new Step(label + " chunks " + i, l -> {
                for (int[] c : batch) {
                    l.setChunkForced(c[0], c[1], true);
                    l.getChunkSource().getChunk(c[0], c[1], net.minecraft.world.level.chunk.status.ChunkStatus.FULL, true);
                }
            }));
        }
    }
}
