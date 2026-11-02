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
public record HudPayload(long money, int faction, boolean active, int s0, int s1, int s2, int winScore, int downed,
                         int c0, int c1, int c2, int controller, int zx, int zz, int zr, int hx, int hz, int hr) implements CustomPacketPayload {
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
                buf.writeVarInt(p.downed);
                buf.writeVarInt(p.c0); buf.writeVarInt(p.c1); buf.writeVarInt(p.c2);
                buf.writeVarInt(p.controller + 1);
                buf.writeInt(p.zx); buf.writeInt(p.zz); buf.writeVarInt(p.zr);
                buf.writeInt(p.hx); buf.writeInt(p.hz); buf.writeVarInt(p.hr);
            },
            buf -> new HudPayload(buf.readVarLong(), buf.readVarInt() - 1, buf.readBoolean(),
                    buf.readVarInt(), buf.readVarInt(), buf.readVarInt(), buf.readVarInt(), buf.readVarInt(),
                    buf.readVarInt(), buf.readVarInt(), buf.readVarInt(), buf.readVarInt() - 1,
                    buf.readInt(), buf.readInt(), buf.readVarInt(), buf.readInt(), buf.readInt(), buf.readVarInt()));

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
        int[] counts = new int[3];
        for (ServerPlayer o : p.server.getPlayerList().getPlayers()) {
            int f = Factions.index(o);
            if (f >= 0 && f < 3) {
                counts[f]++;
            }
        }
        PacketDistributor.sendToPlayer(p, new HudPayload(Wallet.get(p), Factions.index(p), d.active,
                d.scores[0], d.scores[1], d.scores[2], com.wardogs.balance.Balance.get().rewards().winScore(),
                com.wardogs.medic.Downed.ticksLeft(p), counts[0], counts[1], counts[2],
                d.active ? com.wardogs.match.MatchManager.controller(ow, d) : -1,
                (int) d.zoneX, (int) d.zoneZ, (int) d.zoneRadius, (int) d.hotX, (int) d.hotZ, (int) d.hotRadius));
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
