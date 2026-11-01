package com.wardogs.explosive;

import com.wardogs.balance.AmmoType;
import com.wardogs.balance.Balance;
import com.wardogs.combat.Ballistics;
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
        AABB box = new AABB(pos, pos).inflate(radius);
        for (LivingEntity e : level.getEntitiesOfClass(LivingEntity.class, box, LivingEntity::isAlive)) {
            Vec3 center = e.getBoundingBox().getCenter();
            double dist = center.distanceTo(pos);
            double wd = falloff(maxDamage, radius, dist);
            if (wd <= 0 || !exposed(level, pos, center, e)) {
                continue;
            }
            damage(level, e, owner, weaponId, wd, owner == null ? dist : owner.position().distanceTo(e.position()));
        }
        level.sendParticles(ParticleTypes.EXPLOSION, pos.x, pos.y, pos.z, 1, 0, 0, 0, 0);
        level.playSound(null, net.minecraft.core.BlockPos.containing(pos), SoundEvents.GENERIC_EXPLODE.value(), SoundSource.PLAYERS, 3.0f, 1.0f);
    }

    /** Applies WARDOGS-unit damage so kills pay like bullet kills. */
    public static void damage(ServerLevel level, LivingEntity target, @Nullable Entity owner, String weaponId, double wardogsDamage, double distance) {
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
