package com.wardogs.item;

import com.wardogs.balance.Balance;
import com.wardogs.balance.StructureDef;
import com.wardogs.build.Building;
import net.minecraft.ChatFormatting;
import net.minecraft.core.Direction;
import net.minecraft.network.chat.Component;
import net.minecraft.server.level.ServerLevel;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.world.InteractionHand;
import net.minecraft.world.InteractionResult;
import net.minecraft.world.InteractionResultHolder;
import net.minecraft.world.entity.player.Player;
import net.minecraft.world.item.Item;
import net.minecraft.world.item.ItemStack;
import net.minecraft.world.item.TooltipFlag;
import net.minecraft.world.item.context.UseOnContext;
import net.minecraft.world.level.Level;

import java.util.Comparator;
import java.util.List;

/**
 * Small, medium and large hammers. Sneak + right-click cycles through the structures the tier can build;
 * right-click on the top of a block builds the selected one; right-click on a friendly structure repairs it.
 */
public class HammerItem extends Item {
    private final StructureDef.Tier tier;

    public HammerItem(Properties props, StructureDef.Tier tier) {
        super(props.stacksTo(1));
        this.tier = tier;
    }

    public StructureDef.Tier tier() {
        return tier;
    }

    /** Structures this tier can build, cheapest tier first. */
    public List<StructureDef> allowed() {
        return Balance.get().structures().stream()
                .filter(d -> d.tier() != StructureDef.Tier.FOB && d.tier().ordinal() <= tier.ordinal())
                .sorted(Comparator.comparingInt((StructureDef d) -> d.tier().ordinal()).thenComparing(StructureDef::id))
                .toList();
    }

    public StructureDef selected(ItemStack stack) {
        List<StructureDef> list = allowed();
        return list.get(Math.floorMod(stack.getOrDefault(ModComponents.SELECTED.get(), 0), list.size()));
    }

    @Override
    public InteractionResultHolder<ItemStack> use(Level level, Player player, InteractionHand hand) {
        ItemStack stack = player.getItemInHand(hand);
        if (player.isShiftKeyDown()) {
            if (!level.isClientSide) {
                int next = Math.floorMod(stack.getOrDefault(ModComponents.SELECTED.get(), 0) + 1, allowed().size());
                stack.set(ModComponents.SELECTED.get(), next);
                StructureDef d = selected(stack);
                player.displayClientMessage(Component.literal("Selected: " + d.id().replace('_', ' ') + "  (" + d.cost() + " supplies, "
                        + (int) d.hp() + " HP)").withStyle(ChatFormatting.YELLOW), true);
            }
            return InteractionResultHolder.sidedSuccess(stack, level.isClientSide);
        }
        return InteractionResultHolder.pass(stack);
    }

    @Override
    public InteractionResult useOn(UseOnContext ctx) {
        if (!(ctx.getLevel() instanceof ServerLevel sl) || !(ctx.getPlayer() instanceof ServerPlayer sp) || sp.isShiftKeyDown()) {
            return InteractionResult.PASS;
        }
        if (ctx.getClickedFace() != Direction.UP) {
            sp.displayClientMessage(Component.literal("Click the top of a block").withStyle(ChatFormatting.GRAY), true);
            return InteractionResult.FAIL;
        }
        Building.Result r = Building.place(sp, sl, selected(ctx.getItemInHand()), ctx.getClickLocation(), sp.getYRot());
        sp.displayClientMessage(r.message(), true);
        return r.ok() ? InteractionResult.CONSUME : InteractionResult.FAIL;
    }

    @Override
    public void appendHoverText(ItemStack stack, TooltipContext ctx, List<Component> tip, TooltipFlag flag) {
        tip.add(Component.literal(tier.name().toLowerCase() + " hammer  ·  sneak+click: choose  ·  click: build").withStyle(ChatFormatting.GRAY));
        tip.add(Component.literal("Selected: " + selected(stack).id().replace('_', ' ')).withStyle(ChatFormatting.DARK_GRAY));
    }
}
