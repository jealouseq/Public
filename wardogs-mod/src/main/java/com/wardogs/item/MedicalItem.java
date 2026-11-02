package com.wardogs.item;

import com.wardogs.balance.Balance;
import com.wardogs.balance.MedicalDef;
import com.wardogs.economy.Wallet;
import com.wardogs.match.MatchManager;
import com.wardogs.medic.Downed;
import com.wardogs.medic.ReviveReward;
import com.wardogs.net.HudPayload;
import com.wardogs.progress.Progression;
import com.wardogs.progress.Role;
import net.minecraft.ChatFormatting;
import net.minecraft.network.chat.Component;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.world.InteractionHand;
import net.minecraft.world.InteractionResultHolder;
import net.minecraft.world.entity.LivingEntity;
import net.minecraft.world.entity.player.Player;
import net.minecraft.world.item.Item;
import net.minecraft.world.item.ItemStack;
import net.minecraft.world.item.TooltipFlag;
import net.minecraft.world.item.UseAnim;
import net.minecraft.world.level.Level;

import java.util.List;

/** Bandages/kits (heal), resuscitators/defibrillator/stim pen (revive a downed teammate) and batteries. */
public class MedicalItem extends Item {
    private final String medId;

    public MedicalItem(Properties props, String medId) {
        super(props);
        this.medId = medId;
    }

    public MedicalDef def() {
        return Balance.get().medical(medId);
    }

    public static int charges(ItemStack s) {
        return s.getOrDefault(ModComponents.CHARGES.get(), 0);
    }

    private boolean needsCharge() {
        return def().kind() == MedicalDef.Kind.REVIVE && def().charges() > 0;
    }

    @Override
    public int getUseDuration(ItemStack stack, LivingEntity entity) {
        return Math.max(1, def().ticks());
    }

    @Override
    public UseAnim getUseAnimation(ItemStack stack) {
        return def().kind() == MedicalDef.Kind.HEAL ? UseAnim.EAT : UseAnim.BLOCK;
    }

    @Override
    public InteractionResultHolder<ItemStack> use(Level level, Player player, InteractionHand hand) {
        ItemStack stack = player.getItemInHand(hand);
        if (level.isClientSide || !(player instanceof ServerPlayer sp) || Downed.is(sp)) {
            return InteractionResultHolder.pass(stack);
        }
        MedicalDef d = def();
        switch (d.kind()) {
            case BATTERY -> {
                InteractionHand other = hand == InteractionHand.MAIN_HAND ? InteractionHand.OFF_HAND : InteractionHand.MAIN_HAND;
                ItemStack defib = sp.getItemInHand(other);
                if (defib.getItem() instanceof MedicalItem m && m.needsCharge() && charges(defib) < m.def().charges()) {
                    defib.set(ModComponents.CHARGES.get(), m.def().charges());
                    stack.shrink(1);
                    sp.displayClientMessage(Component.literal("Defibrillator recharged").withStyle(ChatFormatting.GREEN), true);
                    return InteractionResultHolder.consume(stack);
                }
                sp.displayClientMessage(Component.literal("Hold a depleted defibrillator in the other hand").withStyle(ChatFormatting.GRAY), true);
                return InteractionResultHolder.fail(stack);
            }
            case HEAL -> {
                if (sp.getHealth() >= sp.getMaxHealth()) {
                    return InteractionResultHolder.fail(stack);
                }
                sp.startUsingItem(hand);
                return InteractionResultHolder.consume(stack);
            }
            default -> {
                if (needsCharge() && charges(stack) <= 0) {
                    sp.displayClientMessage(Component.literal("No charge - use a battery").withStyle(ChatFormatting.RED), true);
                    return InteractionResultHolder.fail(stack);
                }
                if (Downed.findTarget(sp) == null) {
                    sp.displayClientMessage(Component.literal("Aim at a downed teammate").withStyle(ChatFormatting.GRAY), true);
                    return InteractionResultHolder.fail(stack);
                }
                sp.startUsingItem(hand);
                return InteractionResultHolder.consume(stack);
            }
        }
    }

    @Override
    public void onUseTick(Level level, LivingEntity entity, ItemStack stack, int remaining) {
        if (level.isClientSide || !(entity instanceof ServerPlayer sp)) {
            return;
        }
        // Reviving requires keeping the target in front of you for the whole duration.
        if (def().kind() == MedicalDef.Kind.REVIVE && Downed.findTarget(sp) == null) {
            sp.stopUsingItem();
        }
    }

    @Override
    public ItemStack finishUsingItem(ItemStack stack, Level level, LivingEntity entity) {
        if (level.isClientSide || !(entity instanceof ServerPlayer sp)) {
            return stack;
        }
        MedicalDef d = def();
        if (d.kind() == MedicalDef.Kind.HEAL) {
            sp.heal((float) d.hp());
            stack.shrink(1);
            sp.displayClientMessage(Component.literal("+" + (int) d.hp() + " HP").withStyle(ChatFormatting.GREEN), true);
            return stack;
        }
        if (d.kind() == MedicalDef.Kind.REVIVE) {
            ServerPlayer target = Downed.findTarget(sp);
            if (target != null) {
                completeRevive(sp, target, stack, d);
            }
        }
        return stack;
    }

    /** Stands the target up, pays the reviver and spends the item (or one defibrillator charge). */
    public static void completeRevive(ServerPlayer reviver, ServerPlayer target, ItemStack stack, MedicalDef d) {
        Downed.revive(target, d.hp());
        boolean defib = d.charges() > 0;
        int zone = MatchManager.zoneLevel(reviver.level(), target.position());
        long pay = ReviveReward.compute(Balance.get().rewards(), zone, defib, Downed.repeatCount(reviver.getUUID(), target.getUUID()));
        Downed.recordRevive(reviver.getUUID(), target.getUUID());
        Wallet.add(reviver, pay);
        Progression.award(reviver, Role.MEDIC, pay);
        if (defib) {
            stack.set(ModComponents.CHARGES.get(), Math.max(0, charges(stack) - 1));
        } else {
            stack.shrink(1);
        }
        reviver.displayClientMessage(Component.literal("+$" + pay + "  revive").withStyle(ChatFormatting.GOLD), true);
        target.sendSystemMessage(Component.literal("You were revived by " + reviver.getGameProfile().getName()).withStyle(ChatFormatting.GREEN));
        HudPayload.sendTo(reviver);
    }

    @Override
    public void appendHoverText(ItemStack stack, TooltipContext ctx, List<Component> tip, TooltipFlag flag) {
        MedicalDef d = def();
        if (d == null) {
            return;
        }
        String line = switch (d.kind()) {
            case HEAL -> "Heals " + (int) d.hp() + " HP over " + String.format("%.1f", d.ticks() / 20.0) + " s";
            case REVIVE -> "Revives a downed teammate (" + (int) d.hp() + " HP) in " + String.format("%.1f", d.ticks() / 20.0) + " s"
                    + (d.charges() > 0 ? "  ·  charges " + charges(stack) + "/" + d.charges() : "");
            case BATTERY -> "Recharges a defibrillator";
        };
        tip.add(Component.literal(line).withStyle(ChatFormatting.GRAY));
        tip.add(Component.literal("$" + d.price()).withStyle(ChatFormatting.DARK_GRAY));
    }
}
