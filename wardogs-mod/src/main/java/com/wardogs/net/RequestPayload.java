package com.wardogs.net;

import com.wardogs.WardogsMod;
import com.wardogs.match.Requests;
import net.minecraft.network.RegistryFriendlyByteBuf;
import net.minecraft.network.codec.ByteBufCodecs;
import net.minecraft.network.codec.StreamCodec;
import net.minecraft.network.protocol.common.custom.CustomPacketPayload;
import net.minecraft.resources.ResourceLocation;
import net.minecraft.server.level.ServerPlayer;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.network.event.RegisterPayloadHandlersEvent;

/** Client -> server: the player picked an entry of the radial menu. */
public record RequestPayload(String id) implements CustomPacketPayload {
    public static final Type<RequestPayload> TYPE = new Type<>(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "request"));
    public static final StreamCodec<RegistryFriendlyByteBuf, RequestPayload> CODEC =
            StreamCodec.composite(ByteBufCodecs.STRING_UTF8, RequestPayload::id, RequestPayload::new);

    @Override
    public Type<? extends CustomPacketPayload> type() {
        return TYPE;
    }

    @EventBusSubscriber(modid = WardogsMod.MOD_ID)
    public static final class Registration {
        private Registration() {}

        @SubscribeEvent
        public static void register(RegisterPayloadHandlersEvent e) {
            e.registrar("1").playToServer(TYPE, CODEC, (p, ctx) -> ctx.enqueueWork(() -> {
                if (ctx.player() instanceof ServerPlayer sp) {
                    Requests.send(sp, p.id());
                }
            }));
        }
    }
}
