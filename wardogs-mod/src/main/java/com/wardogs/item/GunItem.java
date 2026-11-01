package com.wardogs.item;

import com.wardogs.balance.AmmoType;
import com.wardogs.balance.Balance;
import com.wardogs.balance.WeaponDef;
import com.wardogs.combat.Ballistics;
import com.wardogs.combat.ShooterState;
import com.wardogs.medic.Downed;
import net.minecraft.network.chat.Component;
import net.minecraft.server.level.ServerLevel;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.sounds.SoundEvents;
import net.minecraft.sounds.SoundSource;
import net.minecraft.world.InteractionHand;
import net.minecraft.world.InteractionResultHolder;
import net.minecraft.world.entity.Entity;
import net.minecraft.world.entity.LivingEntity;
import net.minecraft.world.entity.player.Player;
import net.minecraft.world.item.Item;
import net.minecraft.world.item.ItemStack;
import net.minecraft.world.item.TooltipFlag;
import net.minecraft.world.item.UseAnim;
import net.minecraft.world.level.Level;

import java.util.List;

/** A firearm. All numbers come from {@link Balance}, keyed by {@code weaponId}. */
public class GunItem extends Item {
    private final String weaponId;

    public GunItem(Properties props, String weaponId) {
        super(props.stacksTo(1));
        this.weaponId = weaponId;
    }

    public String weaponId() {
        return weaponId;
    }

    public WeaponDef def() {
        return Balance.get().weapon(weaponId);
    }

    public static int ammo(ItemStack stack) {
        return stack.getOrDefault(ModComponents.AMMO.get(), 0);
    }

    public static AmmoType ammoType(ItemStack stack) {
        return AmmoType.byOrdinal(stack.getOrDefault(ModComponents.AMMO_TYPE.get(), 0));
    }

    public ItemStack newStack() {
        ItemStack s = new ItemStack(this);
        s.set(ModComponents.AMMO.get(), def().magazine());
        return s;
    }

    @Override
    public int getUseDuration(ItemStack stack, LivingEntity entity) {
        return 72000;
    }

    @Override
    public UseAnim getUseAnimation(ItemStack stack) {
        return UseAnim.NONE;
    }

    @Override
    public boolean shouldCauseReequipAnimation(ItemStack oldStack, ItemStack newStack, boolean slotChanged) {
        // Ammo count changes every shot; that must not replay the equip animation.
        return slotChanged || oldStack.getItem() != newStack.getItem();
    }

    @Override
    public InteractionResultHolder<ItemStack> use(Level level, Player player, InteractionHand hand) {
        ItemStack stack = player.getItemInHand(hand);
        WeaponDef def = def();
        if (!level.isClientSide && player instanceof ServerPlayer sp) {
            ShooterState st = ShooterState.of(sp.getUUID());
            st.burstLeft = def.mode() == WeaponDef.FireMode.BURST ? 3 : 0;
            if (!def.holdToFire()) {
                tryShoot(sp, (ServerLevel) level, stack, def, st);
            }
        }
        if (def.holdToFire()) {
            player.startUsingItem(hand);
        }
        return InteractionResultHolder.consume(stack);
    }

    @Override
    public void onUseTick(Level level, LivingEntity entity, ItemStack stack, int remaining) {
        if (level.isClientSide || !(entity instanceof ServerPlayer sp)) {
            return;
        }
        WeaponDef def = def();
        ShooterState st = ShooterState.of(sp.getUUID());
        if (def.mode() == WeaponDef.FireMode.BURST && st.burstLeft <= 0) {
            sp.stopUsingItem();
            return;
        }
        // Catch up on shots owed this tick (RPM > 1200 would need >1, but all measured weapons are below).
        int guard = 2;
        while (guard-- > 0 && level.getGameTime() >= st.nextShotTime) {
            if (!tryShoot(sp, (ServerLevel) level, stack, def, st)) {
                break;
            }
            if (def.mode() == WeaponDef.FireMode.BURST) {
                st.burstLeft--;
                if (st.burstLeft <= 0) {
                    break;
                }
            }
        }
    }

    /** @return true if a shot was fired. */
    private boolean tryShoot(ServerPlayer sp, ServerLevel level, ItemStack stack, WeaponDef def, ShooterState st) {
        long now = level.getGameTime();
        if (Downed.is(sp) || st.reloading() || now < st.nextShotTime) {
            return false;
        }
        int ammo = ammo(stack);
        if (ammo <= 0) {
            level.playSound(null, sp.blockPosition(), SoundEvents.DISPENSER_FAIL, SoundSource.PLAYERS, 0.6f, 1.6f);
            st.nextShotTime = now + 6;
            startReload(sp, level, stack, def, st);
            return false;
        }
        stack.set(ModComponents.AMMO.get(), ammo - 1);
        // Schedule from the previous due time to keep the average rate exact (e.g. 786 rpm = 1.53 ticks/shot).
        st.nextShotTime = Math.max(st.nextShotTime, now - 1) + def.shotIntervalTicks();
        Ballistics.fire(level, sp, def, ammoType(stack));
        level.playSound(null, sp.blockPosition(), SoundEvents.FIREWORK_ROCKET_BLAST, SoundSource.PLAYERS, 1.2f, 1.1f);
        sp.displayClientMessage(ammoLine(stack, def), true);
        return true;
    }

    /** Starts a reload if the magazine is not full and the gun is not already reloading. */
    public void startReload(ServerPlayer sp, ServerLevel level, ItemStack stack, WeaponDef def, ShooterState st) {
        if (st.reloading() || ammo(stack) >= def.magazine()) {
            return;
        }
        st.reloadEndsAt = level.getGameTime() + def.reloadTicks();
        st.reloadSlot = sp.getInventory().selected;
        sp.stopUsingItem();
        level.playSound(null, sp.blockPosition(), SoundEvents.CROSSBOW_LOADING_START.value(), SoundSource.PLAYERS, 0.8f, 1.0f);
    }

    @Override
    public void inventoryTick(ItemStack stack, Level level, Entity entity, int slot, boolean selected) {
        if (level.isClientSide || !(entity instanceof ServerPlayer sp)) {
            return;
        }
        ShooterState st = ShooterState.of(sp.getUUID());
        if (!st.reloading() || st.reloadSlot != slot) {
            return;
        }
        if (!selected) {
            st.cancelReload(); // switching weapons cancels the reload
            return;
        }
        if (level.getGameTime() >= st.reloadEndsAt) {
            stack.set(ModComponents.AMMO.get(), def().magazine());
            st.cancelReload();
            level.playSound(null, sp.blockPosition(), SoundEvents.CROSSBOW_LOADING_END.value(), SoundSource.PLAYERS, 0.8f, 1.0f);
            sp.displayClientMessage(ammoLine(stack, def()), true);
        }
    }

    private static Component ammoLine(ItemStack stack, WeaponDef def) {
        return Component.literal(def.name() + "  " + ammo(stack) + " / " + def.magazine() + "  [" + ammoType(stack).name() + "]");
    }

    @Override
    public void appendHoverText(ItemStack stack, TooltipContext ctx, List<Component> tip, TooltipFlag flag) {
        WeaponDef d = def();
        if (d == null) {
            return;
        }
        tip.add(Component.literal(d.category().replace('_', ' ') + " · " + d.caliber()).withStyle(net.minecraft.ChatFormatting.GRAY));
        tip.add(Component.literal(String.format("Damage %.1f / head %.1f · %.0f RPM", d.chest(), d.head(), d.rpm())).withStyle(net.minecraft.ChatFormatting.GRAY));
        tip.add(Component.literal("Ammo " + ammo(stack) + "/" + d.magazine() + "  ·  $" + d.price()).withStyle(net.minecraft.ChatFormatting.DARK_GRAY));
    }
}
