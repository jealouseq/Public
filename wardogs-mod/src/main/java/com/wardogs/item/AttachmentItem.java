package com.wardogs.item;

import com.wardogs.balance.AttachmentDef;
import com.wardogs.balance.Balance;
import net.minecraft.ChatFormatting;
import net.minecraft.network.chat.Component;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.world.InteractionHand;
import net.minecraft.world.InteractionResultHolder;
import net.minecraft.world.entity.player.Player;
import net.minecraft.world.item.Item;
import net.minecraft.world.item.ItemStack;
import net.minecraft.world.item.TooltipFlag;
import net.minecraft.world.level.Level;

import java.util.List;

/** Right-click with the attachment in one hand and the weapon in the other to fit it. */
public class AttachmentItem extends Item {
    private final String attachmentId;

    public AttachmentItem(Properties props, String attachmentId) {
        super(props);
        this.attachmentId = attachmentId;
    }

    public AttachmentDef def() {
        return Balance.get().attachment(attachmentId);
    }

    @Override
    public InteractionResultHolder<ItemStack> use(Level level, Player player, InteractionHand hand) {
        ItemStack stack = player.getItemInHand(hand);
        if (level.isClientSide || !(player instanceof ServerPlayer sp)) {
            return InteractionResultHolder.pass(stack);
        }
        ItemStack other = player.getItemInHand(hand == InteractionHand.MAIN_HAND ? InteractionHand.OFF_HAND : InteractionHand.MAIN_HAND);
        if (!(other.getItem() instanceof GunItem gun)) {
            sp.displayClientMessage(Component.literal("Hold the weapon in your other hand").withStyle(ChatFormatting.GRAY), true);
            return InteractionResultHolder.fail(stack);
        }
        if (GunStats.attach(other, gun.def(), def()) != GunStats.AttachResult.OK) {
            sp.displayClientMessage(Component.literal(def().name() + " does not fit the " + gun.def().name()).withStyle(ChatFormatting.RED), true);
            return InteractionResultHolder.fail(stack);
        }
        if (!sp.getAbilities().instabuild) {
            stack.shrink(1);
        }
        sp.displayClientMessage(Component.literal("Fitted " + def().name()).withStyle(ChatFormatting.GREEN), true);
        return InteractionResultHolder.consume(stack);
    }

    @Override
    public void appendHoverText(ItemStack stack, TooltipContext ctx, List<Component> tip, TooltipFlag flag) {
        AttachmentDef d = def();
        if (d != null) {
            tip.add(Component.literal(d.slot() + " attachment  ·  fits " + String.join(", ", d.categories()).replace('_', ' ')).withStyle(ChatFormatting.GRAY));
        }
    }
}
