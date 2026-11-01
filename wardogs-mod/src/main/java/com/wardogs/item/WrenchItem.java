package com.wardogs.item;

import net.minecraft.ChatFormatting;
import net.minecraft.network.chat.Component;
import net.minecraft.world.item.Item;
import net.minecraft.world.item.ItemStack;
import net.minecraft.world.item.TooltipFlag;

import java.util.List;

/** Right-click a friendly vehicle to repair a fraction of its health. */
public class WrenchItem extends Item {
    public WrenchItem(Properties props) {
        super(props.stacksTo(1));
    }

    @Override
    public void appendHoverText(ItemStack stack, TooltipContext ctx, List<Component> tip, TooltipFlag flag) {
        tip.add(Component.literal("Right-click a vehicle to repair it").withStyle(ChatFormatting.GRAY));
    }
}
