package com.wardogs.progress;

import com.mojang.serialization.Codec;
import com.mojang.serialization.codecs.RecordCodecBuilder;

import java.util.HashMap;
import java.util.HashSet;
import java.util.List;
import java.util.Map;
import java.util.Set;

/** A player's role XP and the paid unlocks they own. Immutable; updates return a copy. */
public record ProgressData(Map<String, Long> xp, Set<String> unlocked) {
    public static final ProgressData EMPTY = new ProgressData(Map.of(), Set.of());

    public static final Codec<ProgressData> CODEC = RecordCodecBuilder.create(i -> i.group(
            Codec.unboundedMap(Codec.STRING, Codec.LONG).fieldOf("xp").forGetter(ProgressData::xp),
            Codec.STRING.listOf().fieldOf("unlocked").forGetter(d -> List.copyOf(d.unlocked()))
    ).apply(i, (xp, list) -> new ProgressData(Map.copyOf(xp), Set.copyOf(list))));

    public long xpOf(Role r) {
        return xp.getOrDefault(r.key(), 0L);
    }

    public ProgressData withXp(Role r, long amount) {
        Map<String, Long> m = new HashMap<>(xp);
        m.merge(r.key(), amount, Long::sum);
        return new ProgressData(Map.copyOf(m), unlocked);
    }

    public ProgressData withUnlocked(String id) {
        Set<String> s = new HashSet<>(unlocked);
        s.add(id);
        return new ProgressData(xp, Set.copyOf(s));
    }
}
