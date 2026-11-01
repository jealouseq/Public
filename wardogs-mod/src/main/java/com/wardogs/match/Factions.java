package com.wardogs.match;

import com.mojang.serialization.Codec;
import com.wardogs.WardogsMod;
import net.minecraft.world.entity.player.Player;
import net.neoforged.neoforge.attachment.AttachmentType;
import net.neoforged.neoforge.registries.DeferredRegister;
import net.neoforged.neoforge.registries.NeoForgeRegistries;

import java.util.function.Supplier;

/** Which faction a player fights for (-1 = none). Kept through death. */
public final class Factions {
    public static final DeferredRegister<AttachmentType<?>> ATTACHMENTS =
            DeferredRegister.create(NeoForgeRegistries.ATTACHMENT_TYPES, WardogsMod.MOD_ID);

    public static final Supplier<AttachmentType<Integer>> FACTION = ATTACHMENTS.register("faction", () ->
            AttachmentType.builder(() -> -1).serialize(Codec.INT).copyOnDeath().build());

    private Factions() {}

    public static int index(Player p) {
        return p.getData(FACTION);
    }

    public static Faction of(Player p) {
        return Faction.byIndex(index(p));
    }

    public static void set(Player p, Faction f) {
        p.setData(FACTION, f == null ? -1 : f.ordinal());
    }

    /** Players of the same faction are teammates; players without a faction are nobody's. */
    public static boolean sameTeam(Player a, Player b) {
        int ia = index(a);
        return ia >= 0 && ia == index(b);
    }
}
