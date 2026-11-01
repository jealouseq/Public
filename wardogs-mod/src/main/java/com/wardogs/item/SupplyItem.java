package com.wardogs.item;

import net.minecraft.ChatFormatting;
import net.minecraft.network.chat.Component;
import net.minecraft.world.item.Item;
import net.minecraft.world.item.ItemStack;
import net.minecraft.world.item.TooltipFlag;

import java.util.List;

/** Build, ammo, fuel or mechanical supplies. Right-click a friendly FOB to store a stack. */
public class SupplyItem extends Item {
    private final String kind;

    public SupplyItem(Properties props, String kind) {
        super(props);
        this.kind = kind;
    }

    public String kind() {
        return kind;
    }

    @Override
    public void appendHoverText(ItemStack stack, TooltipContext ctx, List<Component> tip, TooltipFlag flag) {
        tip.add(Component.literal(kind + " supplies  ·  right-click a FOB to store").withStyle(ChatFormatting.GRAY));
    }
}
