package com.wardogs.match;

import com.wardogs.WardogsMod;
import com.wardogs.world.BakuraniWorld;
import net.minecraft.server.level.ServerLevel;
import net.minecraft.server.level.ServerPlayer;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.event.entity.player.PlayerEvent;

/** During a Bakurani match, soldiers start and respawn at their faction's base. */
@EventBusSubscriber(modid = WardogsMod.MOD_ID)
public final class Respawns {
    private Respawns() {}

    /** @return true if the player was sent to their base. */
    public static boolean sendToBase(ServerPlayer p) {
        Faction f = Factions.of(p);
        if (f == null) {
            return false;
        }
        ServerLevel bk = p.server.getLevel(BakuraniWorld.DIMENSION);
        MatchData d = MatchData.get(p.server.overworld());
        if (bk == null || !d.active || !d.dim.equals(BakuraniWorld.DIMENSION.location().toString())) {
            return false;
        }
        BakuraniWorld.Site site = BakuraniWorld.spawnOf(f.name().toLowerCase());
        // Spread soldiers around the spawn point so they do not stack.
        double ang = p.getRandom().nextDouble() * Math.PI * 2, r = 2 + p.getRandom().nextDouble() * 4;
        double x = site.x() + Math.cos(ang) * r, z = site.z() + Math.sin(ang) * r;
        bk.getChunk((int) Math.floor(x) >> 4, (int) Math.floor(z) >> 4);
        double y = bk.getHeight(net.minecraft.world.level.levelgen.Heightmap.Types.MOTION_BLOCKING, (int) Math.floor(x), (int) Math.floor(z)) + 0.5;
        p.teleportTo(bk, x, y, z, 0f, 0f);
        return true;
    }

    @SubscribeEvent
    public static void onRespawn(PlayerEvent.PlayerRespawnEvent e) {
        if (!e.isEndConquered() && e.getEntity() instanceof ServerPlayer sp) {
            sendToBase(sp);
        }
    }
}
