package com.wardogs.net;

import com.wardogs.WardogsMod;
import com.wardogs.shop.Shop;
import net.minecraft.network.RegistryFriendlyByteBuf;
import net.minecraft.network.codec.ByteBufCodecs;
import net.minecraft.network.codec.StreamCodec;
import net.minecraft.network.protocol.common.custom.CustomPacketPayload;
import net.minecraft.resources.ResourceLocation;
import net.minecraft.server.level.ServerPlayer;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.network.event.RegisterPayloadHandlersEvent;
import net.neoforged.neoforge.network.handling.IPayloadContext;

/** Client -> server: buy an item, or pay to unlock it. The server re-checks everything. */
public record ShopPayload(String id, boolean unlock) implements CustomPacketPayload {
    public static final Type<ShopPayload> TYPE = new Type<>(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "shop"));
    public static final StreamCodec<RegistryFriendlyByteBuf, ShopPayload> CODEC = StreamCodec.composite(
            ByteBufCodecs.STRING_UTF8, ShopPayload::id,
            ByteBufCodecs.BOOL, ShopPayload::unlock,
            ShopPayload::new);

    @Override
    public Type<? extends CustomPacketPayload> type() {
        return TYPE;
    }

    static void handle(ShopPayload p, IPayloadContext ctx) {
        ctx.enqueueWork(() -> {
            if (ctx.player() instanceof ServerPlayer sp) {
                Shop.Result r = p.unlock() ? Shop.unlock(sp, p.id()) : Shop.buy(sp, p.id());
                sp.displayClientMessage(r.message(), true);
                HudPayload.sendTo(sp);
                ProgressPayload.sendTo(sp);
            }
        });
    }

    @EventBusSubscriber(modid = WardogsMod.MOD_ID)
    public static final class Registration {
        private Registration() {}

        @SubscribeEvent
        public static void register(RegisterPayloadHandlersEvent e) {
            e.registrar("1").playToServer(TYPE, CODEC, ShopPayload::handle);
        }
    }
}
