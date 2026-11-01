package com.wardogs.item;

import com.wardogs.balance.Balance;
import com.wardogs.balance.VehicleDef;
import com.wardogs.match.Factions;
import com.wardogs.vehicle.Vehicle;
import net.minecraft.ChatFormatting;
import net.minecraft.core.Direction;
import net.minecraft.network.chat.Component;
import net.minecraft.server.level.ServerLevel;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.world.InteractionResult;
import net.minecraft.world.item.Item;
import net.minecraft.world.item.ItemStack;
import net.minecraft.world.item.TooltipFlag;
import net.minecraft.world.item.context.UseOnContext;

import java.util.List;

/**
 * A purchased vehicle, as a spawn ticket: right-click the ground to deploy it.
 * (The real game spawns bought vehicles at the base; that comes with the Bakurani map.)
 */
public class VehicleItem extends Item {
    private final String vehicleId;

    public VehicleItem(Properties props, String vehicleId) {
        super(props.stacksTo(1));
        this.vehicleId = vehicleId;
    }

    public VehicleDef def() {
        return Balance.get().vehicle(vehicleId);
    }

    @Override
    public InteractionResult useOn(UseOnContext ctx) {
        if (!(ctx.getLevel() instanceof ServerLevel sl) || !(ctx.getPlayer() instanceof ServerPlayer sp) || ctx.getClickedFace() != Direction.UP) {
            return InteractionResult.PASS;
        }
        Vehicle.spawn(sl, def(), ctx.getClickLocation().add(0, 0.05, 0), sp.getYRot(), Factions.index(sp));
        if (!sp.getAbilities().instabuild) {
            ctx.getItemInHand().shrink(1);
        }
        return InteractionResult.CONSUME;
    }

    @Override
    public void appendHoverText(ItemStack stack, TooltipContext ctx, List<Component> tip, TooltipFlag flag) {
        VehicleDef d = def();
        if (d != null) {
            tip.add(Component.literal(d.category() + "  ·  " + d.seats() + " seats  ·  " + (int) d.hp() + " HP").withStyle(ChatFormatting.GRAY));
            tip.add(Component.literal("Right-click the ground to deploy" + (d.gun() != null ? "  ·  armed" : "")).withStyle(ChatFormatting.DARK_GRAY));
        }
    }
}
