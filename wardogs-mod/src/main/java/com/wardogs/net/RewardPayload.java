package com.wardogs.net;

import com.wardogs.WardogsMod;
import com.wardogs.client.ClientRewards;
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

/** Server -> owner: one line for the reward log under the money boxes ("KILL +$450"). Negative amounts are penalties. */
public record RewardPayload(String label, long amount) implements CustomPacketPayload {
    public static final Type<RewardPayload> TYPE = new Type<>(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "reward"));
    public static final StreamCodec<RegistryFriendlyByteBuf, RewardPayload> CODEC = StreamCodec.composite(
            ByteBufCodecs.STRING_UTF8, RewardPayload::label, ByteBufCodecs.VAR_LONG, RewardPayload::amount, RewardPayload::new);

    @Override
    public Type<? extends CustomPacketPayload> type() {
        return TYPE;
    }

    /** Sends a reward line; silently ignored for players without a client (tests, bots). */
    public static void send(ServerPlayer p, String label, long amount) {
        if (p.connection != null && p.connection.hasChannel(TYPE)) {
            PacketDistributor.sendToPlayer(p, new RewardPayload(label, amount));
        }
    }

    @EventBusSubscriber(modid = WardogsMod.MOD_ID)
    public static final class Registration {
        private Registration() {}

        @SubscribeEvent
        public static void register(RegisterPayloadHandlersEvent e) {
            e.registrar("1").playToClient(TYPE, CODEC, (p, ctx) -> ctx.enqueueWork(() -> ClientRewards.add(p.label(), p.amount())));
        }
    }
}
