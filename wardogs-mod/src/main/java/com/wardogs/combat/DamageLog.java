package com.wardogs.combat;

import java.util.ArrayDeque;
import java.util.ArrayList;
import java.util.Deque;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.ConcurrentHashMap;

/** The last hits a player took, so the downed screen can show who hurt them, with what and for how much ("VIEW DAMAGE LOG"). */
public final class DamageLog {
    public record Entry(String attacker, String weapon, int damage, boolean head, long tick) {}

    /** How long an entry stays relevant, in ticks. */
    public static final long WINDOW = 20 * 30;
    private static final int MAX = 8;
    private static final Map<UUID, Deque<Entry>> LOG = new ConcurrentHashMap<>();

    private DamageLog() {}

    public static void record(UUID victim, String attacker, String weaponId, double damage, boolean head, long now) {
        Deque<Entry> q = LOG.computeIfAbsent(victim, k -> new ArrayDeque<>());
        synchronized (q) {
            q.addFirst(new Entry(attacker, weaponId, (int) Math.round(damage), head, now));
            while (q.size() > MAX) {
                q.removeLast();
            }
        }
    }

    /** Newest first, only entries from the last {@link #WINDOW} ticks. */
    public static List<Entry> recent(UUID victim, long now) {
        Deque<Entry> q = LOG.get(victim);
        List<Entry> out = new ArrayList<>();
        if (q == null) {
            return out;
        }
        synchronized (q) {
            for (Entry e : q) {
                if (now - e.tick() <= WINDOW) {
                    out.add(e);
                }
            }
        }
        return out;
    }

    public static void clear(UUID victim) {
        LOG.remove(victim);
    }
}
