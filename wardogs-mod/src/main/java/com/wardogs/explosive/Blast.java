package com.wardogs.explosive;

import com.wardogs.balance.AmmoType;
import com.wardogs.balance.Balance;
import com.wardogs.build.Buildable;
import com.wardogs.combat.Ballistics;
import com.wardogs.vehicle.Vehicle;
import com.wardogs.match.Factions;
import net.minecraft.core.particles.ParticleTypes;
import net.minecraft.core.registries.Registries;
import net.minecraft.server.level.ServerLevel;
import net.minecraft.sounds.SoundEvents;
import net.minecraft.sounds.SoundSource;
import net.minecraft.world.damagesource.DamageSource;
import net.minecraft.world.entity.Entity;
import net.minecraft.world.entity.LivingEntity;
import net.minecraft.world.level.ClipContext;
import net.minecraft.world.phys.AABB;
import net.minecraft.world.phys.HitResult;
import net.minecraft.world.phys.Vec3;

import javax.annotation.Nullable;

/** Area damage for grenades and charges: linear falloff, blocked by walls, never destroys blocks. */
public final class Blast {
    private Blast() {}

    /** Pure falloff: full damage at the centre, nothing at the edge. */
    public static double falloff(double maxDamage, double radius, double distance) {
        if (radius <= 0 || distance >= radius) {
            return 0.0;
        }
        return maxDamage * (1.0 - distance / radius);
    }

    public static void explode(ServerLevel level, Vec3 pos, double radius, double maxDamage, @Nullable Entity owner, String weaponId) {
        explode(level, pos, radius, maxDamage, owner, weaponId, null);
    }

    /**
     * As above, but {@code directHit} (an entity struck head-on, e.g. by a rocket) takes the full damage
     * instead of the distance-reduced splash.
     */
    public static void explode(ServerLevel level, Vec3 pos, double radius, double maxDamage, @Nullable Entity owner, String weaponId, @Nullable Entity directHit) {
        AABB box = new AABB(pos, pos).inflate(radius);
        for (LivingEntity e : level.getEntitiesOfClass(LivingEntity.class, box, LivingEntity::isAlive)) {
            if (e == directHit) {
                damage(level, e, owner, weaponId, maxDamage, owner == null ? 0 : owner.position().distanceTo(e.position()));
                continue;
            }
            Vec3 center = e.getBoundingBox().getCenter();
            double dist = center.distanceTo(pos);
            double wd = falloff(maxDamage, radius, dist);
            if (wd <= 0 || !exposed(level, pos, center, e)) {
                continue;
            }
            damage(level, e, owner, weaponId, wd, owner == null ? dist : owner.position().distanceTo(e.position()));
        }
        int attackerFaction = owner instanceof net.minecraft.world.entity.player.Player op ? Factions.index(op) : -1;
        for (Buildable b : level.getEntitiesOfClass(Buildable.class, box)) {
            var def = b.def();
            AABB bb = b.getBoundingBox();
            double dx = Math.max(Math.max(bb.minX - pos.x, 0), pos.x - bb.maxX);
            double dy = Math.max(Math.max(bb.minY - pos.y, 0), pos.y - bb.maxY);
            double dz = Math.max(Math.max(bb.minZ - pos.z, 0), pos.z - bb.maxZ);
            if (def != null && Math.sqrt(dx * dx + dy * dy + dz * dz) <= radius) {
                b.damageStructure(def.hitDamage(weaponId, Balance.get()), attackerFaction);
            }
        }
        level.sendParticles(ParticleTypes.EXPLOSION, pos.x, pos.y, pos.z, 1, 0, 0, 0, 0);
        level.playSound(null, net.minecraft.core.BlockPos.containing(pos), com.wardogs.sound.ModSounds.EXPLOSION.get(), SoundSource.PLAYERS, 3.0f, 1.0f);
    }

    /** Applies WARDOGS-unit damage so kills pay like bullet kills. */
    public static void damage(ServerLevel level, LivingEntity target, @Nullable Entity owner, String weaponId, double wardogsDamage, double distance) {
        if (target instanceof Vehicle v) {
            var launcher = Balance.get().launcher(weaponId);
            boolean antiTank = weaponId.equals("at_mine") || weaponId.equals("veh_shell")
                    || (launcher != null && launcher.kind() != com.wardogs.balance.LauncherDef.Kind.GRENADE);
            double dmg = launcher == null ? wardogsDamage : wardogsDamage * launcher.vehicleMult();
            v.applyWardogsDamage(dmg, antiTank ? Vehicle.DamageKind.ANTI_TANK : Vehicle.DamageKind.EXPLOSIVE, owner);
            return;
        }
        DamageSource src = new DamageSource(
                level.registryAccess().registryOrThrow(Registries.DAMAGE_TYPE).getHolderOrThrow(Ballistics.EXPLOSION), owner, owner);
        float mc = Balance.get().damageTo(target, wardogsDamage);
        Ballistics.runAs(new Ballistics.Hit(weaponId, false, distance, AmmoType.FMJ), () -> {
            target.invulnerableTime = 0;
            target.hurt(src, mc);
        });
    }

    private static boolean exposed(ServerLevel level, Vec3 from, Vec3 to, Entity e) {
        HitResult r = level.clip(new ClipContext(from, to, ClipContext.Block.COLLIDER, ClipContext.Fluid.NONE, e));
        return r.getType() == HitResult.Type.MISS;
    }
}
