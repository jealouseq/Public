package com.wardogs.match;

import com.wardogs.WardogsMod;
import com.wardogs.build.Buildable;
import com.wardogs.net.DeployPayload;
import com.wardogs.net.OpenDeployPayload;
import com.wardogs.world.BakuraniWorld;
import net.minecraft.core.BlockPos;
import net.minecraft.network.chat.Component;
import net.minecraft.server.level.ServerLevel;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.world.InteractionResult;
import net.minecraft.world.level.block.Blocks;
import net.minecraft.world.level.levelgen.Heightmap;
import net.minecraft.world.entity.EntityType;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.event.entity.player.PlayerInteractEvent;
import net.neoforged.neoforge.network.PacketDistributor;

import java.util.ArrayList;
import java.util.List;

/** Deployment boards: a lectern in front of a notice board lets a soldier pick where to deploy - their base or a friendly FOB. */
@EventBusSubscriber(modid = WardogsMod.MOD_ID)
public final class Deployment {
    /** How close a soldier must stand to a board to deploy. */
    public static final double BOARD_RANGE = 8;

    private Deployment() {}

    public record Target(String name, double x, double z) {}

    /** Where a player of this faction may deploy: their base first, then every friendly FOB. */
    public static List<Target> targets(ServerLevel level, Faction f) {
        List<Target> out = new ArrayList<>();
        var base = BakuraniWorld.spawnOf(f.name().toLowerCase());
        out.add(new Target(f.display + " base", base.x(), base.z()));
        int n = 1;
        for (Buildable b : level.getEntities(EntityType_BUILD(), e -> e.isFob() && e.faction() == f.ordinal())) {
            out.add(new Target("FOB " + n++, b.getX(), b.getZ()));
        }
        return out;
    }

    private static net.minecraft.world.level.entity.EntityTypeTest<net.minecraft.world.entity.Entity, Buildable> EntityType_BUILD() {
        return net.minecraft.world.level.entity.EntityTypeTest.forClass(Buildable.class);
    }

    public static boolean nearBoard(ServerPlayer p) {
        for (var s : BakuraniWorld.sites().boards()) {
            if (Math.hypot(p.getX() - s.x(), p.getZ() - s.z()) <= BOARD_RANGE) {
                return true;
            }
        }
        return false;
    }

    /** Sends the player to target {@code index}. @return the message to show, or null if it worked. */
    public static String deploy(ServerPlayer p, int index) {
        Faction f = Factions.of(p);
        if (f == null) {
            return "Pick a faction first";
        }
        ServerLevel bk = p.server.getLevel(BakuraniWorld.DIMENSION);
        MatchData d = MatchData.get(p.server.overworld());
        if (bk == null || !d.active || !d.dim.equals(BakuraniWorld.DIMENSION.location().toString())) {
            return "No Bakurani match is running";
        }
        if (!p.level().dimension().equals(BakuraniWorld.DIMENSION) || !nearBoard(p)) {
            return "Stand next to a deployment board";
        }
        List<Target> t = targets(bk, f);
        if (index < 0 || index >= t.size()) {
            return "That deployment point is gone";
        }
        Target to = t.get(index);
        double ang = p.getRandom().nextDouble() * Math.PI * 2, r = 3 + p.getRandom().nextDouble() * 3;
        double x = to.x() + Math.cos(ang) * r, z = to.z() + Math.sin(ang) * r;
        bk.getChunk((int) Math.floor(x) >> 4, (int) Math.floor(z) >> 4);
        double y = bk.getHeight(Heightmap.Types.MOTION_BLOCKING, (int) Math.floor(x), (int) Math.floor(z)) + 0.5;
        p.teleportTo(bk, x, y, z, p.getYRot(), 0f);
        return null;
    }

    @SubscribeEvent
    public static void onLectern(PlayerInteractEvent.RightClickBlock e) {
        if (e.getLevel().isClientSide || !(e.getEntity() instanceof ServerPlayer sp) || e.getHand() != net.minecraft.world.InteractionHand.MAIN_HAND) {
            return;
        }
        BlockPos pos = e.getPos();
        if (!e.getLevel().getBlockState(pos).is(Blocks.LECTERN) || !e.getLevel().dimension().equals(BakuraniWorld.DIMENSION) || !nearBoard(sp)) {
            return;
        }
        e.setCanceled(true);
        e.setCancellationResult(InteractionResult.SUCCESS);
        Faction f = Factions.of(sp);
        if (f == null) {
            sp.displayClientMessage(Component.literal("Pick a faction first (/wd team)"), true);
            return;
        }
        List<String> names = new ArrayList<>();
        for (Target t : targets((ServerLevel) e.getLevel(), f)) {
            names.add(t.name());
        }
        if (sp.connection != null && sp.connection.hasChannel(OpenDeployPayload.TYPE)) {
            PacketDistributor.sendToPlayer(sp, new OpenDeployPayload(names));
        }
    }
}
