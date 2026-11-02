package com.wardogs.net;

import com.wardogs.WardogsMod;
import com.wardogs.client.ClientScoreboard;
import com.wardogs.match.MatchStats;
import net.minecraft.network.RegistryFriendlyByteBuf;
import net.minecraft.network.codec.StreamCodec;
import net.minecraft.network.protocol.common.custom.CustomPacketPayload;
import net.minecraft.resources.ResourceLocation;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.network.event.RegisterPayloadHandlersEvent;

import java.util.ArrayList;
import java.util.List;

/** Server -> client: the match scoreboard; {@code fin} marks the end of the round (winner = faction index, or -1). */
public record ScoreboardPayload(boolean fin, int winner, int[] scores, List<MatchStats.Row> rows) implements CustomPacketPayload {
    public static final Type<ScoreboardPayload> TYPE = new Type<>(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "scoreboard"));
    public static final StreamCodec<RegistryFriendlyByteBuf, ScoreboardPayload> CODEC = StreamCodec.of(
            (buf, p) -> {
                buf.writeBoolean(p.fin);
                buf.writeVarInt(p.winner + 1);
                for (int i = 0; i < 3; i++) {
                    buf.writeVarInt(p.scores[i]);
                }
                buf.writeVarInt(p.rows.size());
                for (MatchStats.Row r : p.rows) {
                    buf.writeUtf(r.name(), 32);
                    buf.writeVarInt(r.faction() + 1);
                    buf.writeVarInt(r.kills());
                    buf.writeVarInt(r.deaths());
                    buf.writeVarLong(r.earned());
                }
            },
            buf -> {
                boolean fin = buf.readBoolean();
                int winner = buf.readVarInt() - 1;
                int[] sc = {buf.readVarInt(), buf.readVarInt(), buf.readVarInt()};
                int n = buf.readVarInt();
                List<MatchStats.Row> rows = new ArrayList<>();
                for (int i = 0; i < n && i < 256; i++) {
                    rows.add(new MatchStats.Row(buf.readUtf(32), buf.readVarInt() - 1, buf.readVarInt(), buf.readVarInt(), buf.readVarLong()));
                }
                return new ScoreboardPayload(fin, winner, sc, rows);
            });

    @Override
    public Type<? extends CustomPacketPayload> type() {
        return TYPE;
    }

    @EventBusSubscriber(modid = WardogsMod.MOD_ID)
    public static final class Registration {
        private Registration() {}

        @SubscribeEvent
        public static void register(RegisterPayloadHandlersEvent e) {
            e.registrar("1").playToClient(TYPE, CODEC, (p, ctx) -> ctx.enqueueWork(() -> ClientScoreboard.update(p)));
        }
    }
}
