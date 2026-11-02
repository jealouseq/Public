package com.wardogs.net;

import com.wardogs.WardogsMod;
import com.wardogs.client.ClientFeedback;
import net.minecraft.network.RegistryFriendlyByteBuf;
import net.minecraft.network.codec.ByteBufCodecs;
import net.minecraft.network.codec.StreamCodec;
import net.minecraft.network.protocol.common.custom.CustomPacketPayload;
import net.minecraft.resources.ResourceLocation;
import net.minecraft.server.level.ServerPlayer;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.network.PacketDistributor;
import net.neoforged.neoforge.network.event.RegisterPayloadHandlersEvent;

/** Server -> shooter: a bullet landed. kind: 0 hit, 1 headshot, 2 kill, 3 headshot kill. */
public record HitMarkerPayload(int kind) implements CustomPacketPayload {
    public static final Type<HitMarkerPayload> TYPE = new Type<>(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "hit_marker"));
    public static final StreamCodec<RegistryFriendlyByteBuf, HitMarkerPayload> CODEC =
            StreamCodec.composite(ByteBufCodecs.VAR_INT, HitMarkerPayload::kind, HitMarkerPayload::new);

    public static void send(ServerPlayer p, boolean head, boolean kill) {
        if (p.connection != null && p.connection.hasChannel(TYPE)) {
            PacketDistributor.sendToPlayer(p, new HitMarkerPayload((head ? 1 : 0) + (kill ? 2 : 0)));
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
            e.registrar("1").playToClient(TYPE, CODEC, (p, ctx) -> ctx.enqueueWork(() -> ClientFeedback.hit(p.kind())));
        }
    }
}
