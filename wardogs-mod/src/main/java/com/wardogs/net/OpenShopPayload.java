package com.wardogs.net;

import com.wardogs.WardogsMod;
import com.wardogs.client.ClientShop;
import net.minecraft.network.RegistryFriendlyByteBuf;
import net.minecraft.network.codec.ByteBufCodecs;
import net.minecraft.network.codec.StreamCodec;
import net.minecraft.network.protocol.common.custom.CustomPacketPayload;
import net.minecraft.resources.ResourceLocation;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.network.event.RegisterPayloadHandlersEvent;

/** Server -> client: the player talked to a vendor; open the shop limited to what that vendor sells. */
public record OpenShopPayload(String kind) implements CustomPacketPayload {
    public static final Type<OpenShopPayload> TYPE = new Type<>(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "open_shop"));
    public static final StreamCodec<RegistryFriendlyByteBuf, OpenShopPayload> CODEC =
            StreamCodec.composite(ByteBufCodecs.STRING_UTF8, OpenShopPayload::kind, OpenShopPayload::new);

    @Override
    public Type<? extends CustomPacketPayload> type() {
        return TYPE;
    }

    @EventBusSubscriber(modid = WardogsMod.MOD_ID)
    public static final class Registration {
        private Registration() {}

        @SubscribeEvent
        public static void register(RegisterPayloadHandlersEvent e) {
            e.registrar("1").playToClient(TYPE, CODEC, (p, ctx) -> ctx.enqueueWork(() -> ClientShop.open(p.kind())));
        }
    }
}
