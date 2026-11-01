package com.wardogs.net;

import com.wardogs.WardogsMod;
import com.wardogs.client.ClientHud;
import com.wardogs.economy.Wallet;
import com.wardogs.match.Factions;
import com.wardogs.match.MatchData;
import net.minecraft.network.RegistryFriendlyByteBuf;
import net.minecraft.network.codec.StreamCodec;
import net.minecraft.network.protocol.common.custom.CustomPacketPayload;
import net.minecraft.resources.ResourceLocation;
import net.minecraft.server.level.ServerLevel;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.world.level.Level;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.network.PacketDistributor;
import net.neoforged.neoforge.network.event.RegisterPayloadHandlersEvent;
import net.neoforged.neoforge.network.handling.IPayloadContext;

/** Server -> owner only: money, faction and match scores for the HUD. */
public record HudPayload(long money, int faction, boolean active, int s0, int s1, int s2, int winScore) implements CustomPacketPayload {
    public static final Type<HudPayload> TYPE = new Type<>(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "hud"));
    public static final StreamCodec<RegistryFriendlyByteBuf, HudPayload> CODEC = StreamCodec.of(
            (buf, p) -> {
                buf.writeVarLong(p.money);
                buf.writeVarInt(p.faction + 1); // -1 (no faction) -> 0
                buf.writeBoolean(p.active);
                buf.writeVarInt(p.s0);
                buf.writeVarInt(p.s1);
                buf.writeVarInt(p.s2);
                buf.writeVarInt(p.winScore);
            },
            buf -> new HudPayload(buf.readVarLong(), buf.readVarInt() - 1, buf.readBoolean(),
                    buf.readVarInt(), buf.readVarInt(), buf.readVarInt(), buf.readVarInt()));

    @Override
    public Type<? extends CustomPacketPayload> type() {
        return TYPE;
    }

    public static void sendTo(ServerPlayer p) {
        // Mock/headless players have no client channel; skip them instead of throwing.
        if (p.connection == null || !p.connection.hasChannel(TYPE)) {
            return;
        }
        ServerLevel ow = p.server.overworld();
        MatchData d = MatchData.get(ow);
        PacketDistributor.sendToPlayer(p, new HudPayload(Wallet.get(p), Factions.index(p), d.active,
                d.scores[0], d.scores[1], d.scores[2], com.wardogs.balance.Balance.get().rewards().winScore()));
    }

    @EventBusSubscriber(modid = WardogsMod.MOD_ID)
    public static final class Registration {
        private Registration() {}

        @SubscribeEvent
        public static void register(RegisterPayloadHandlersEvent e) {
            e.registrar("1").playToClient(TYPE, CODEC, (pl, ctx) -> ctx.enqueueWork(() -> ClientHud.update(pl)));
        }
    }
}
