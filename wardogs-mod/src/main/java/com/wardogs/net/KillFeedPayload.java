package com.wardogs.net;

import com.wardogs.WardogsMod;
import com.wardogs.client.ClientFeedback;
import net.minecraft.network.RegistryFriendlyByteBuf;
import net.minecraft.network.codec.StreamCodec;
import net.minecraft.network.protocol.common.custom.CustomPacketPayload;
import net.minecraft.resources.ResourceLocation;
import net.minecraft.server.level.ServerPlayer;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.network.PacketDistributor;
import net.neoforged.neoforge.network.event.RegisterPayloadHandlersEvent;

/** Server -> everyone in the match: one line of the kill feed (faction -1 for non-player victims). */
public record KillFeedPayload(String killer, int killerFaction, String victim, int victimFaction, String weapon, boolean headshot)
        implements CustomPacketPayload {
    public static final Type<KillFeedPayload> TYPE = new Type<>(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "kill_feed"));
    public static final StreamCodec<RegistryFriendlyByteBuf, KillFeedPayload> CODEC = StreamCodec.of(
            (buf, p) -> {
                buf.writeUtf(p.killer, 32);
                buf.writeVarInt(p.killerFaction + 1);
                buf.writeUtf(p.victim, 48);
                buf.writeVarInt(p.victimFaction + 1);
                buf.writeUtf(p.weapon, 48);
                buf.writeBoolean(p.headshot);
            },
            buf -> new KillFeedPayload(buf.readUtf(32), buf.readVarInt() - 1, buf.readUtf(48), buf.readVarInt() - 1, buf.readUtf(48), buf.readBoolean()));

    public static void broadcast(net.minecraft.server.MinecraftServer server, KillFeedPayload p) {
        broadcast(server, p, null, null);
    }

    /** In hardcore mode only squad members of the killer or the victim see the line. */
    public static void broadcast(net.minecraft.server.MinecraftServer server, KillFeedPayload p, ServerPlayer killer, net.minecraft.world.entity.Entity victim) {
        boolean hardcore = com.wardogs.match.MatchData.get(server.overworld()).hardcore;
        for (ServerPlayer sp : server.getPlayerList().getPlayers()) {
            if (hardcore && !sees(sp, killer, victim)) {
                continue;
            }
            if (sp.connection != null && sp.connection.hasChannel(TYPE)) {
                PacketDistributor.sendToPlayer(sp, p);
            }
        }
    }

    /** True if {@code viewer} is the killer or victim, or shares a squad with one of them. */
    public static boolean sees(ServerPlayer viewer, ServerPlayer killer, net.minecraft.world.entity.Entity victim) {
        var squad = com.wardogs.match.Squads.of(viewer);
        for (net.minecraft.world.entity.Entity e : new net.minecraft.world.entity.Entity[]{killer, victim}) {
            if (e == null) {
                continue;
            }
            if (e == viewer || (squad != null && squad.members.contains(e.getUUID()))) {
                return true;
            }
        }
        return false;
    }

    @Override
    public Type<? extends CustomPacketPayload> type() {
        return TYPE;
    }

    @EventBusSubscriber(modid = WardogsMod.MOD_ID)
    public static final class Registration {
        private Registration() {}

        @SubscribeEvent
        public static void register(RegisterPayloadHandlersEvent e) {
            e.registrar("1").playToClient(TYPE, CODEC, (p, ctx) -> ctx.enqueueWork(() -> ClientFeedback.feed(p)));
        }
    }
}
