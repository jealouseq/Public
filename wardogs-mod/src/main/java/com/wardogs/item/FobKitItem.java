package com.wardogs.item;

import com.wardogs.build.Building;
import net.minecraft.core.Direction;
import net.minecraft.server.level.ServerLevel;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.world.InteractionResult;
import net.minecraft.world.item.Item;
import net.minecraft.world.item.context.UseOnContext;

/** Deploys a Forward Operating Base on the clicked ground. */
public class FobKitItem extends Item {
    public FobKitItem(Properties props) {
        super(props.stacksTo(1));
    }

    @Override
    public InteractionResult useOn(UseOnContext ctx) {
        if (!(ctx.getLevel() instanceof ServerLevel sl) || !(ctx.getPlayer() instanceof ServerPlayer sp)) {
            return InteractionResult.PASS;
        }
        if (ctx.getClickedFace() != Direction.UP) {
            return InteractionResult.FAIL;
        }
        Building.Result r = Building.deployFob(sp, sl, ctx.getClickLocation());
        sp.displayClientMessage(r.message(), true);
        if (r.ok() && !sp.getAbilities().instabuild) {
            ctx.getItemInHand().shrink(1);
        }
        return r.ok() ? InteractionResult.CONSUME : InteractionResult.FAIL;
    }
}
