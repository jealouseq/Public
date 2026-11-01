package com.wardogs.progress;

import com.wardogs.WardogsMod;
import com.wardogs.balance.Balance;
import net.minecraft.world.entity.player.Player;
import net.neoforged.neoforge.attachment.AttachmentType;
import net.neoforged.neoforge.registries.DeferredRegister;
import net.neoforged.neoforge.registries.NeoForgeRegistries;

import java.util.Set;
import java.util.UUID;
import java.util.concurrent.ConcurrentHashMap;
import java.util.function.Supplier;

/** Role XP, levels and unlocks, stored on the player and kept through death. */
public final class Progression {
    public static final DeferredRegister<AttachmentType<?>> ATTACHMENTS =
            DeferredRegister.create(NeoForgeRegistries.ATTACHMENT_TYPES, WardogsMod.MOD_ID);

    public static final Supplier<AttachmentType<ProgressData>> PROGRESS = ATTACHMENTS.register("progress", () ->
            AttachmentType.builder(() -> ProgressData.EMPTY).serialize(ProgressData.CODEC).copyOnDeath().build());

    /** Players whose progress changed since the last packet. */
    private static final Set<UUID> DIRTY = ConcurrentHashMap.newKeySet();

    private Progression() {}

    public static ProgressData get(Player p) {
        return p.getData(PROGRESS);
    }

    public static int level(ProgressData d, Role r) {
        return Balance.get().progression().levelFor(d.xpOf(r));
    }

    public static int level(Player p, Role r) {
        return level(get(p), r);
    }

    /**
     * Awards XP for earning {@code dollars}: the role branch and the general WARDOG branch both grow.
     * The real XP formula is unpublished; XP is a fixed fraction of the money (see progression.json).
     */
    public static void award(Player p, Role role, long dollars) {
        if (dollars <= 0) {
            return;
        }
        long xp = Math.max(1, Math.round(dollars * Balance.get().progression().xpPerDollar()));
        ProgressData d = get(p);
        if (role != null && role != Role.WARDOG) {
            d = d.withXp(role, xp);
        }
        d = d.withXp(Role.WARDOG, xp);
        p.setData(PROGRESS, d);
        DIRTY.add(p.getUUID());
    }

    public static void unlock(Player p, String id) {
        p.setData(PROGRESS, get(p).withUnlocked(id));
        DIRTY.add(p.getUUID());
    }

    public static void markDirty(Player p) {
        DIRTY.add(p.getUUID());
    }

    /** Removes and returns whether this player's progress needs sending. */
    public static boolean consumeDirty(Player p) {
        return DIRTY.remove(p.getUUID());
    }
}
