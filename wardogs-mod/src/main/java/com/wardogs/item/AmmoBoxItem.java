package com.wardogs.item;

import com.wardogs.balance.AmmoItemDef;
import com.wardogs.balance.AmmoType;
import com.wardogs.balance.Balance;
import net.minecraft.ChatFormatting;
import net.minecraft.network.chat.Component;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.world.InteractionHand;
import net.minecraft.world.InteractionResultHolder;
import net.minecraft.world.entity.player.Player;
import net.minecraft.world.item.Item;
import net.minecraft.world.item.ItemStack;
import net.minecraft.world.level.Level;

/** Right-click with the box in one hand and a weapon in the other to switch its ammunition type. */
public class AmmoBoxItem extends Item {
    private final String ammoId;

    public AmmoBoxItem(Properties props, String ammoId) {
        super(props);
        this.ammoId = ammoId;
    }

    public AmmoItemDef def() {
        return Balance.get().ammoItem(ammoId);
    }

    @Override
    public InteractionResultHolder<ItemStack> use(Level level, Player player, InteractionHand hand) {
        ItemStack stack = player.getItemInHand(hand);
        if (level.isClientSide || !(player instanceof ServerPlayer sp)) {
            return InteractionResultHolder.pass(stack);
        }
        ItemStack other = player.getItemInHand(hand == InteractionHand.MAIN_HAND ? InteractionHand.OFF_HAND : InteractionHand.MAIN_HAND);
        if (!(other.getItem() instanceof GunItem gun) || gun.def().category().equals("launcher") || gun.def().category().equals("bow")) {
            sp.displayClientMessage(Component.literal("Hold a firearm in your other hand").withStyle(ChatFormatting.GRAY), true);
            return InteractionResultHolder.fail(stack);
        }
        other.set(ModComponents.AMMO_TYPE.get(), def().ammoType());
        if (!sp.getAbilities().instabuild) {
            stack.shrink(1);
        }
        sp.displayClientMessage(Component.literal(gun.def().name() + " now fires " + AmmoType.byOrdinal(def().ammoType())).withStyle(ChatFormatting.GREEN), true);
        return InteractionResultHolder.consume(stack);
    }
}
