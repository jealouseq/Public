package com.wardogs.net;

import com.wardogs.WardogsMod;
import com.wardogs.client.ClientProgress;
import com.wardogs.progress.ProgressData;
import com.wardogs.progress.Progression;
import com.wardogs.progress.Role;
import net.minecraft.network.RegistryFriendlyByteBuf;
import net.minecraft.network.codec.StreamCodec;
import net.minecraft.network.protocol.common.custom.CustomPacketPayload;
import net.minecraft.resources.ResourceLocation;
import net.minecraft.server.level.ServerPlayer;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.network.PacketDistributor;
import net.neoforged.neoforge.network.event.RegisterPayloadHandlersEvent;

import java.util.HashMap;
import java.util.HashSet;
import java.util.Map;
import java.util.Set;

/** Server -> owner only: role XP and paid unlocks, for the shop screen. */
public record ProgressPayload(ProgressData data) implements CustomPacketPayload {
    public static final Type<ProgressPayload> TYPE = new Type<>(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "progress"));
    public static final StreamCodec<RegistryFriendlyByteBuf, ProgressPayload> CODEC = StreamCodec.of(
            (buf, p) -> {
                for (Role r : Role.values()) {
                    buf.writeVarLong(p.data.xpOf(r));
                }
                buf.writeVarInt(p.data.unlocked().size());
                p.data.unlocked().forEach(buf::writeUtf);
            },
            buf -> {
                Map<String, Long> xp = new HashMap<>();
                for (Role r : Role.values()) {
                    xp.put(r.key(), buf.readVarLong());
                }
                int n = buf.readVarInt();
                Set<String> unlocked = new HashSet<>();
                for (int i = 0; i < n; i++) {
                    unlocked.add(buf.readUtf());
                }
                return new ProgressPayload(new ProgressData(Map.copyOf(xp), Set.copyOf(unlocked)));
            });

    @Override
    public Type<? extends CustomPacketPayload> type() {
        return TYPE;
    }

    public static void sendTo(ServerPlayer p) {
        if (p.connection == null || !p.connection.hasChannel(TYPE)) {
            return; // headless/mock players have no client
        }
        PacketDistributor.sendToPlayer(p, new ProgressPayload(Progression.get(p)));
    }

    @EventBusSubscriber(modid = WardogsMod.MOD_ID)
    public static final class Registration {
        private Registration() {}

        @SubscribeEvent
        public static void register(RegisterPayloadHandlersEvent e) {
            e.registrar("1").playToClient(TYPE, CODEC, (pl, ctx) -> ctx.enqueueWork(() -> ClientProgress.update(pl.data())));
        }
    }
}
