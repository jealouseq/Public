package com.wardogs.net;

import com.wardogs.WardogsMod;
import com.wardogs.vehicle.Vehicle;
import com.wardogs.vehicle.VehicleGuns;
import net.minecraft.network.RegistryFriendlyByteBuf;
import net.minecraft.network.codec.StreamCodec;
import net.minecraft.network.protocol.common.custom.CustomPacketPayload;
import net.minecraft.resources.ResourceLocation;
import net.minecraft.server.level.ServerPlayer;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.network.event.RegisterPayloadHandlersEvent;
import net.neoforged.neoforge.network.handling.IPayloadContext;

/** Client -> server while the driver holds the fire button: shoot the vehicle's mounted weapon. */
public record VehicleFirePayload() implements CustomPacketPayload {
    public static final Type<VehicleFirePayload> TYPE = new Type<>(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "vehicle_fire"));
    public static final StreamCodec<RegistryFriendlyByteBuf, VehicleFirePayload> CODEC = StreamCodec.unit(new VehicleFirePayload());

    @Override
    public Type<? extends CustomPacketPayload> type() {
        return TYPE;
    }

    static void handle(VehicleFirePayload p, IPayloadContext ctx) {
        ctx.enqueueWork(() -> {
            if (ctx.player() instanceof ServerPlayer sp && sp.getVehicle() instanceof Vehicle v) {
                VehicleGuns.fire(sp, v);
            }
        });
    }

    @EventBusSubscriber(modid = WardogsMod.MOD_ID)
    public static final class Registration {
        private Registration() {}

        @SubscribeEvent
        public static void register(RegisterPayloadHandlersEvent e) {
            e.registrar("1").playToServer(TYPE, CODEC, VehicleFirePayload::handle);
        }
    }
}
