package com.wardogs.economy;

import com.mojang.serialization.Codec;
import com.wardogs.WardogsMod;
import com.wardogs.balance.Balance;
import net.minecraft.core.registries.Registries;
import net.minecraft.world.entity.player.Player;
import net.neoforged.neoforge.attachment.AttachmentType;
import net.neoforged.neoforge.registries.DeferredHolder;
import net.neoforged.neoforge.registries.DeferredRegister;
import net.neoforged.neoforge.registries.NeoForgeRegistries;

import java.util.function.Supplier;

/**
 * A player's money, stored as a data attachment that survives death.
 * Deliberately not auto-synced: that would also leak every player's balance to nearby players.
 * The HUD gets an owner-only packet instead (later milestone).
 */
public final class Wallet {
    public static final DeferredRegister<AttachmentType<?>> ATTACHMENTS =
            DeferredRegister.create(NeoForgeRegistries.ATTACHMENT_TYPES, WardogsMod.MOD_ID);

    public static final Supplier<AttachmentType<Long>> MONEY = ATTACHMENTS.register("money", () ->
            AttachmentType.builder(() -> Balance.get().rewards().startingCash())
                    .serialize(Codec.LONG)
                    .copyOnDeath()
                    .build());

    private Wallet() {}

    public static long get(Player p) {
        return p.getData(MONEY);
    }

    public static void set(Player p, long amount) {
        p.setData(MONEY, Math.max(0, amount));
    }

    public static void add(Player p, long delta) {
        set(p, get(p) + delta);
    }

    /** @return true if the player could pay and the amount was deducted. */
    public static boolean tryPay(Player p, long cost) {
        if (cost < 0 || get(p) < cost) {
            return false;
        }
        add(p, -cost);
        return true;
    }
}
