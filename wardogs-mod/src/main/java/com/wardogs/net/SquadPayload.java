package com.wardogs.net;

import com.wardogs.WardogsMod;
import com.wardogs.client.ClientSquad;
import com.wardogs.match.Squads;
import net.minecraft.network.RegistryFriendlyByteBuf;
import net.minecraft.network.codec.StreamCodec;
import net.minecraft.network.protocol.common.custom.CustomPacketPayload;
import net.minecraft.resources.ResourceLocation;
import net.minecraft.server.level.ServerPlayer;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.network.PacketDistributor;
import net.neoforged.neoforge.network.event.RegisterPayloadHandlersEvent;

import java.util.ArrayList;
import java.util.List;

/** Server -> squad members: who is in the squad, where they are and how healthy (an empty list clears the HUD). */
public record SquadPayload(List<Member> members) implements CustomPacketPayload {
    public record Member(String name, boolean leader, double x, double z, float health, float maxHealth, boolean downed) {}

    public static final Type<SquadPayload> TYPE = new Type<>(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "squad"));
    public static final StreamCodec<RegistryFriendlyByteBuf, SquadPayload> CODEC = StreamCodec.of(
            (buf, p) -> {
                buf.writeVarInt(p.members.size());
                for (Member m : p.members) {
                    buf.writeUtf(m.name, 32);
                    buf.writeBoolean(m.leader);
                    buf.writeDouble(m.x);
                    buf.writeDouble(m.z);
                    buf.writeFloat(m.health);
                    buf.writeFloat(m.maxHealth);
                    buf.writeBoolean(m.downed);
                }
            },
            buf -> {
                int n = Math.min(8, buf.readVarInt());
                List<Member> out = new ArrayList<>();
                for (int i = 0; i < n; i++) {
                    out.add(new Member(buf.readUtf(32), buf.readBoolean(), buf.readDouble(), buf.readDouble(), buf.readFloat(), buf.readFloat(), buf.readBoolean()));
                }
                return new SquadPayload(out);
            });

    /** Builds the payload for one player's squad (empty if none). */
    public static SquadPayload forPlayer(ServerPlayer p) {
        Squads.Squad s = Squads.of(p);
        List<Member> out = new ArrayList<>();
        if (s != null) {
            for (ServerPlayer m : p.server.getPlayerList().getPlayers()) {
                if (s.members.contains(m.getUUID())) {
                    out.add(new Member(m.getGameProfile().getName(), s.leader.equals(m.getUUID()), m.getX(), m.getZ(), m.getHealth(), m.getMaxHealth(), com.wardogs.medic.Downed.is(m)));
                }
            }
        }
        return new SquadPayload(out);
    }

    public static void sendTo(ServerPlayer p) {
        if (p.connection != null && p.connection.hasChannel(TYPE)) {
            PacketDistributor.sendToPlayer(p, forPlayer(p));
        }
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
            e.registrar("1").playToClient(TYPE, CODEC, (p, ctx) -> ctx.enqueueWork(() -> ClientSquad.update(p)));
        }
    }
}
