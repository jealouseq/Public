package com.wardogs.item;

import com.wardogs.balance.Balance;
import com.wardogs.balance.ExplosiveDef;
import com.wardogs.explosive.GrenadeEntity;
import com.wardogs.explosive.PlacedCharge;
import com.wardogs.medic.Downed;
import net.minecraft.ChatFormatting;
import net.minecraft.network.chat.Component;
import net.minecraft.server.level.ServerLevel;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.sounds.SoundEvents;
import net.minecraft.sounds.SoundSource;
import net.minecraft.world.InteractionHand;
import net.minecraft.world.InteractionResult;
import net.minecraft.world.InteractionResultHolder;
import net.minecraft.world.entity.player.Player;
import net.minecraft.world.item.Item;
import net.minecraft.world.item.ItemStack;
import net.minecraft.world.item.TooltipFlag;
import net.minecraft.world.item.context.UseOnContext;
import net.minecraft.world.level.Level;
import net.minecraft.world.phys.Vec3;

import java.util.List;
import java.util.UUID;

/** Grenades (thrown), charges/mines (placed on a block) and the remote detonator. */
public class ExplosiveItem extends Item {
    private final String explosiveId;

    public ExplosiveItem(Properties props, String explosiveId) {
        super(props);
        this.explosiveId = explosiveId;
    }

    public ExplosiveDef def() {
        return Balance.get().explosive(explosiveId);
    }

    @Override
    public InteractionResultHolder<ItemStack> use(Level level, Player player, InteractionHand hand) {
        ItemStack stack = player.getItemInHand(hand);
        ExplosiveDef d = def();
        if (!(level instanceof ServerLevel sl) || !(player instanceof ServerPlayer sp) || Downed.is(sp)) {
            return InteractionResultHolder.pass(stack);
        }
        if (d.throwable()) {
            GrenadeEntity g = new GrenadeEntity(level, sp);
            g.setItem(stack.copyWithCount(1));
            g.shootFromRotation(sp, sp.getXRot(), sp.getYRot(), -2.0f, 1.1f, 0.5f);
            sl.addFreshEntity(g);
            sl.playSound(null, sp.blockPosition(), SoundEvents.SNOWBALL_THROW, SoundSource.PLAYERS, 0.7f, 0.8f);
            if (!sp.getAbilities().instabuild) {
                stack.shrink(1);
            }
            return InteractionResultHolder.consume(stack);
        }
        if (d.kind() == ExplosiveDef.Kind.DETONATOR) {
            int n = detonateAll(sl, sp.getUUID());
            sp.displayClientMessage(Component.literal(n > 0 ? "Detonated " + n : "No charges placed").withStyle(n > 0 ? ChatFormatting.RED : ChatFormatting.GRAY), true);
            return InteractionResultHolder.consume(stack);
        }
        return InteractionResultHolder.pass(stack);
    }

    @Override
    public InteractionResult useOn(UseOnContext ctx) {
        ExplosiveDef d = def();
        Player p = ctx.getPlayer();
        if (!d.placeable() || !(ctx.getLevel() instanceof ServerLevel sl) || !(p instanceof ServerPlayer sp) || Downed.is(sp)) {
            return InteractionResult.PASS;
        }
        Vec3 pos = ctx.getClickLocation();
        PlacedCharge.place(sl, d, pos, sp.getYRot(), sp.getUUID());
        sl.playSound(null, sp.blockPosition(), SoundEvents.TRIPWIRE_CLICK_ON, SoundSource.PLAYERS, 1.0f, 0.8f);
        if (!sp.getAbilities().instabuild) {
            ctx.getItemInHand().shrink(1);
        }
        return InteractionResult.CONSUME;
    }

    /** Sets off every C4 and IED the player has placed. */
    public static int detonateAll(ServerLevel level, UUID owner) {
        int n = 0;
        for (var e : level.getEntities(net.minecraft.world.level.entity.EntityTypeTest.forClass(PlacedCharge.class), c -> owner.equals(c.ownerId()))) {
            ExplosiveDef d = e.def();
            if (d != null && (d.kind() == ExplosiveDef.Kind.C4 || d.kind() == ExplosiveDef.Kind.IED)) {
                e.detonate();
                n++;
            }
        }
        return n;
    }

    @Override
    public void appendHoverText(ItemStack stack, TooltipContext ctx, List<Component> tip, TooltipFlag flag) {
        ExplosiveDef d = def();
        if (d != null) {
            tip.add(Component.literal(d.kind().name().toLowerCase().replace('_', ' ') + "  ·  $" + d.price()).withStyle(ChatFormatting.GRAY));
        }
    }
}
