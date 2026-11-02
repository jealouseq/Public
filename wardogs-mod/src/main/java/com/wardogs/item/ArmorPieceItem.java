package com.wardogs.item;

import com.wardogs.balance.ArmorDef;
import com.wardogs.balance.Balance;
import com.wardogs.combat.HitZone;
import net.minecraft.network.chat.Component;
import net.minecraft.world.InteractionHand;
import net.minecraft.world.InteractionResultHolder;
import net.minecraft.world.entity.EquipmentSlot;
import net.minecraft.world.entity.LivingEntity;
import net.minecraft.world.entity.player.Player;
import net.minecraft.world.item.Equipable;
import net.minecraft.world.item.Item;
import net.minecraft.world.item.ItemStack;
import net.minecraft.world.item.TooltipFlag;
import net.minecraft.world.level.Level;

import java.util.List;

/** Helmet (head slot) or body armor (chest slot). Protection strength comes from {@link Balance}. */
public class ArmorPieceItem extends Item implements Equipable {
    private final String armorId;

    public ArmorPieceItem(Properties props, String armorId) {
        super(props.stacksTo(1));
        this.armorId = armorId;
    }

    public ArmorDef def() {
        return Balance.get().armor(armorId);
    }

    @Override
    public EquipmentSlot getEquipmentSlot() {
        return def().slot() == ArmorDef.Slot.HELMET ? EquipmentSlot.HEAD : EquipmentSlot.CHEST;
    }

    @Override
    public InteractionResultHolder<ItemStack> use(Level level, Player player, InteractionHand hand) {
        return swapWithEquipmentSlot(this, level, player, hand);
    }

    /**
     * The worn armor stack that covers this hit zone and still has durability left, or {@code null}.
     * Helmets cover the head (and the neck from level 3); vests the torso (and the pelvis and upper arms at level 4).
     */
    public static ItemStack protecting(LivingEntity e, HitZone zone) {
        boolean head = zone == HitZone.HEAD || zone == HitZone.NECK;
        ItemStack s = e.getItemBySlot(head ? EquipmentSlot.HEAD : EquipmentSlot.CHEST);
        if (!(s.getItem() instanceof ArmorPieceItem a) || a.def() == null || s.getDamageValue() >= s.getMaxDamage()) {
            return ItemStack.EMPTY;
        }
        ArmorDef d = a.def();
        boolean covers;
        if (head) {
            covers = d.slot() == ArmorDef.Slot.HELMET && (zone == HitZone.HEAD || d.level() >= 3);
        } else {
            covers = d.slot() == ArmorDef.Slot.VEST && (zone.isTorso()
                    || ((zone == HitZone.PELVIS || zone == HitZone.ARM_UPPER) && d.level() >= 4));
        }
        return covers ? s : ItemStack.EMPTY;
    }

    /** The armor worn on the relevant hit zone (head for headshots, torso otherwise), or {@code null}. */
    public static ArmorDef worn(LivingEntity e, boolean head) {
        ItemStack s = e.getItemBySlot(head ? EquipmentSlot.HEAD : EquipmentSlot.CHEST);
        if (s.getItem() instanceof ArmorPieceItem a) {
            ArmorDef d = a.def();
            if (d != null && (d.slot() == ArmorDef.Slot.HELMET) == head) {
                return d;
            }
        }
        return null;
    }

    @Override
    public void appendHoverText(ItemStack stack, TooltipContext ctx, List<Component> tip, TooltipFlag flag) {
        ArmorDef d = def();
        if (d != null) {
            tip.add(Component.literal(String.format("Level %d · absorbs ~%.0f%% · %.1f kg · $%d",
                    d.level(), d.reduction() * 100, d.weight(), d.price())).withStyle(net.minecraft.ChatFormatting.GRAY));
        }
    }
}
