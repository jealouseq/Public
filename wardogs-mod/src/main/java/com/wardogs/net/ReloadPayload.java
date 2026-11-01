package com.wardogs.net;

import com.wardogs.WardogsMod;
import com.wardogs.combat.ShooterState;
import com.wardogs.item.GunItem;
import net.minecraft.network.RegistryFriendlyByteBuf;
import net.minecraft.network.codec.StreamCodec;
import net.minecraft.network.protocol.common.custom.CustomPacketPayload;
import net.minecraft.resources.ResourceLocation;
import net.minecraft.server.level.ServerLevel;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.world.item.ItemStack;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.network.event.RegisterPayloadHandlersEvent;
import net.neoforged.neoforge.network.handling.IPayloadContext;

/** Client -> server: "reload the weapon in my hand". */
public record ReloadPayload() implements CustomPacketPayload {
    public static final Type<ReloadPayload> TYPE =
            new Type<>(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "reload"));
    public static final StreamCodec<RegistryFriendlyByteBuf, ReloadPayload> CODEC = StreamCodec.unit(new ReloadPayload());

    @Override
    public Type<? extends CustomPacketPayload> type() {
        return TYPE;
    }

    public static void handle(ReloadPayload p, IPayloadContext ctx) {
        ctx.enqueueWork(() -> {
            if (!(ctx.player() instanceof ServerPlayer sp)) {
                return;
            }
            ItemStack stack = sp.getMainHandItem();
            if (stack.getItem() instanceof GunItem gun) {
                gun.startReload(sp, (ServerLevel) sp.level(), stack, gun.def(), ShooterState.of(sp.getUUID()));
            }
        });
    }

    @EventBusSubscriber(modid = WardogsMod.MOD_ID)
    public static final class Registration {
        private Registration() {}

        @SubscribeEvent
        public static void register(RegisterPayloadHandlersEvent e) {
            e.registrar("1").playToServer(TYPE, CODEC, ReloadPayload::handle);
        }
    }
}
