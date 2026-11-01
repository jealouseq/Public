package com.wardogs.vehicle;

import com.wardogs.balance.AmmoType;
import com.wardogs.balance.VehicleDef;
import com.wardogs.balance.WeaponDef;
import com.wardogs.combat.Ballistics;
import com.wardogs.explosive.Blast;
import net.minecraft.server.level.ServerLevel;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.sounds.SoundEvents;
import net.minecraft.sounds.SoundSource;
import net.minecraft.world.entity.projectile.ProjectileUtil;
import net.minecraft.world.level.ClipContext;
import net.minecraft.world.phys.AABB;
import net.minecraft.world.phys.EntityHitResult;
import net.minecraft.world.phys.HitResult;
import net.minecraft.world.phys.Vec3;

import java.util.Set;

/** Fires the weapon mounted on a vehicle; only the driver can use it. */
public final class VehicleGuns {
    private VehicleGuns() {}

    /** @return true if a shot was fired. */
    public static boolean fire(ServerPlayer p, Vehicle v) {
        VehicleDef d = v.def();
        if (d == null || d.gun() == null || v.getControllingPassenger() != p || !(p.level() instanceof ServerLevel level)) {
            return false;
        }
        VehicleDef.Gun g = d.gun();
        long now = level.getGameTime();
        if (now < v.nextShot) {
            return false;
        }
        v.nextShot = Math.max(v.nextShot, now - 1) + g.intervalTicks();
        if (g.blast() > 0) {
            shell(level, p, v, g);
        } else {
            WeaponDef w = new WeaponDef("veh_" + d.id(), d.name() + " gun", "vehicle", "mounted", "any", WeaponDef.FireMode.AUTO,
                    g.damage(), g.damage(), g.rpm(), 9999, g.range(), 0, g.spread(), 1, 0, 0, 0L, Set.of());
            Ballistics.fire(level, p, w, AmmoType.FMJ);
            level.playSound(null, v.blockPosition(), SoundEvents.FIREWORK_ROCKET_BLAST, SoundSource.PLAYERS, 1.5f, 0.8f);
        }
        return true;
    }

    private static void shell(ServerLevel level, ServerPlayer p, Vehicle v, VehicleDef.Gun g) {
        Vec3 start = p.getEyePosition();
        Vec3 dir = Ballistics.withSpread(p.getLookAngle(), g.spread(), p.getRandom());
        Vec3 end = start.add(dir.scale(Math.min(g.range(), Ballistics.MAX_TRACE)));
        HitResult block = level.clip(new ClipContext(start, end, ClipContext.Block.COLLIDER, ClipContext.Fluid.NONE, p));
        Vec3 stop = block.getType() == HitResult.Type.MISS ? end : block.getLocation();
        EntityHitResult ehr = ProjectileUtil.getEntityHitResult(p, start, stop, new AABB(start, stop).inflate(1.0),
                e -> e.isPickable() && !e.isSpectator() && e != v && e != p && e.getVehicle() != v, Double.MAX_VALUE);
        Vec3 impact = ehr != null ? ehr.getLocation() : (block.getType() == HitResult.Type.MISS ? null : block.getLocation());
        level.playSound(null, v.blockPosition(), SoundEvents.GENERIC_EXPLODE.value(), SoundSource.PLAYERS, 2.0f, 1.4f);
        if (impact != null) {
            Blast.explode(level, impact, g.blast(), g.damage(), p, "veh_shell");
        }
    }
}
