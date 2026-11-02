package com.wardogs.net;

import com.wardogs.WardogsMod;
import com.wardogs.client.ClientDamageLog;
import com.wardogs.combat.DamageLog;
import net.minecraft.network.RegistryFriendlyByteBuf;
import net.minecraft.network.codec.StreamCodec;
import net.minecraft.network.protocol.common.custom.CustomPacketPayload;
import net.minecraft.resources.ResourceLocation;
import net.minecraft.server.level.ServerPlayer;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.network.PacketDistributor;
import net.neoforged.neoforge.network.event.RegisterPayloadHandlersEvent;

import java.util.ArrayList;
import java.util.List;

/** Server -> the downed player: the hits that put them down, newest first. */
public record DamageLogPayload(List<Line> lines) implements CustomPacketPayload {
    public record Line(String attacker, String weapon, int damage, boolean head) {}

    public static final Type<DamageLogPayload> TYPE = new Type<>(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "damage_log"));
    public static final StreamCodec<RegistryFriendlyByteBuf, DamageLogPayload> CODEC = StreamCodec.of(
            (buf, p) -> {
                buf.writeVarInt(p.lines.size());
                for (Line l : p.lines) {
                    buf.writeUtf(l.attacker, 48);
                    buf.writeUtf(l.weapon, 48);
                    buf.writeVarInt(l.damage);
                    buf.writeBoolean(l.head);
                }
            },
            buf -> {
                int n = Math.min(16, buf.readVarInt());
                List<Line> lines = new ArrayList<>();
                for (int i = 0; i < n; i++) {
                    lines.add(new Line(buf.readUtf(48), buf.readUtf(48), buf.readVarInt(), buf.readBoolean()));
                }
                return new DamageLogPayload(lines);
            });

    public static void sendTo(ServerPlayer p) {
        if (p.connection == null || !p.connection.hasChannel(TYPE)) {
            return;
        }
        List<Line> lines = new ArrayList<>();
        for (DamageLog.Entry e : DamageLog.recent(p.getUUID(), p.level().getGameTime())) {
            lines.add(new Line(e.attacker(), e.weapon(), e.damage(), e.head()));
        }
        PacketDistributor.sendToPlayer(p, new DamageLogPayload(lines));
    }

    @Override
    public Type<? extends CustomPacketPayload> type() {
        return TYPE;
    }

    @EventBusSubscriber(modid = WardogsMod.MOD_ID)
    public static final class Registration {
        private Registration() {}

        @SubscribeEvent
        public static void register(RegisterPayloadHandlersEvent e) {
            e.registrar("1").playToClient(TYPE, CODEC, (p, ctx) -> ctx.enqueueWork(() -> ClientDamageLog.update(p)));
        }
    }
}
