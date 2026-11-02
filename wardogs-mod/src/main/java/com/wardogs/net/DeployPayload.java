package com.wardogs.net;

import com.wardogs.WardogsMod;
import com.wardogs.match.Deployment;
import net.minecraft.network.RegistryFriendlyByteBuf;
import net.minecraft.network.chat.Component;
import net.minecraft.network.codec.ByteBufCodecs;
import net.minecraft.network.codec.StreamCodec;
import net.minecraft.network.protocol.common.custom.CustomPacketPayload;
import net.minecraft.resources.ResourceLocation;
import net.minecraft.server.level.ServerPlayer;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.network.event.RegisterPayloadHandlersEvent;

/** Client -> server: deploy to the point with this index in the list the board showed. */
public record DeployPayload(int index, String key) implements CustomPacketPayload {
    public static final Type<DeployPayload> TYPE = new Type<>(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "deploy"));
    public static final StreamCodec<RegistryFriendlyByteBuf, DeployPayload> CODEC =
            StreamCodec.composite(ByteBufCodecs.VAR_INT, DeployPayload::index,
                    ByteBufCodecs.STRING_UTF8, DeployPayload::key, DeployPayload::new);

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
                    String err = Deployment.deploy(sp, p.index(), p.key());
                    if (err != null) {
                        sp.displayClientMessage(Component.literal(err), true);
                    }
                }
            }));
        }
    }
}
