package com.wardogs.net;

import com.wardogs.WardogsMod;
import com.wardogs.client.ClientDeploy;
import net.minecraft.network.RegistryFriendlyByteBuf;
import net.minecraft.network.codec.ByteBufCodecs;
import net.minecraft.network.codec.StreamCodec;
import net.minecraft.network.protocol.common.custom.CustomPacketPayload;
import net.minecraft.resources.ResourceLocation;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.network.event.RegisterPayloadHandlersEvent;

import java.util.List;

/** Server -> client: the player used a deployment board; show the list of places to deploy to. */
public record OpenDeployPayload(List<String> names, List<String> keys) implements CustomPacketPayload {
    public static final Type<OpenDeployPayload> TYPE = new Type<>(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "open_deploy"));
    public static final StreamCodec<RegistryFriendlyByteBuf, OpenDeployPayload> CODEC =
            StreamCodec.composite(ByteBufCodecs.STRING_UTF8.apply(ByteBufCodecs.list()), OpenDeployPayload::names,
                    ByteBufCodecs.STRING_UTF8.apply(ByteBufCodecs.list()), OpenDeployPayload::keys, OpenDeployPayload::new);

    @Override
    public Type<? extends CustomPacketPayload> type() {
        return TYPE;
    }

    @EventBusSubscriber(modid = WardogsMod.MOD_ID)
    public static final class Registration {
        private Registration() {}

        @SubscribeEvent
        public static void register(RegisterPayloadHandlersEvent e) {
            e.registrar("1").playToClient(TYPE, CODEC, (p, ctx) -> ctx.enqueueWork(() -> ClientDeploy.open(p.names(), p.keys())));
        }
    }
}
