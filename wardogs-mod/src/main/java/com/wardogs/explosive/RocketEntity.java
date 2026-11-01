package com.wardogs.explosive;

import com.wardogs.balance.Balance;
import com.wardogs.balance.LauncherDef;
import com.wardogs.item.ModItems;
import com.wardogs.vehicle.Vehicle;
import net.minecraft.core.particles.ParticleTypes;
import net.minecraft.nbt.CompoundTag;
import net.minecraft.network.syncher.EntityDataAccessor;
import net.minecraft.network.syncher.EntityDataSerializers;
import net.minecraft.network.syncher.SynchedEntityData;
import net.minecraft.server.level.ServerLevel;
import net.minecraft.world.entity.Entity;
import net.minecraft.world.entity.EntityType;
import net.minecraft.world.entity.projectile.ItemSupplier;
import net.minecraft.world.entity.projectile.Projectile;
import net.minecraft.world.entity.projectile.ProjectileUtil;
import net.minecraft.world.item.ItemStack;
import net.minecraft.world.level.Level;
import net.minecraft.world.phys.HitResult;
import net.minecraft.world.phys.Vec3;

import javax.annotation.Nullable;
import java.util.UUID;

/** A rocket, missile or 40 mm grenade in flight. Explodes on the first thing it touches. */
public class RocketEntity extends Projectile implements ItemSupplier {
    private static final EntityDataAccessor<String> KIND = SynchedEntityData.defineId(RocketEntity.class, EntityDataSerializers.STRING);
    private static final int MAX_AGE = 20 * 12;

    @Nullable
    private UUID target;

    public RocketEntity(EntityType<? extends RocketEntity> type, Level level) {
        super(type, level);
    }

    /** Launches from an arbitrary point (turrets). */
    public static RocketEntity launchFrom(ServerLevel level, @Nullable Entity owner, LauncherDef def, Vec3 from, Vec3 dir, @Nullable UUID target) {
        RocketEntity r = new RocketEntity(ExplosiveEntities.ROCKET.get(), level);
        r.entityData.set(KIND, def.id());
        if (owner != null) {
            r.setOwner(owner);
        }
        r.setPos(from);
        r.setDeltaMovement(dir.scale(def.speed()));
        r.target = target;
        level.addFreshEntity(r);
        return r;
    }

    public static RocketEntity launch(ServerLevel level, net.minecraft.world.entity.player.Player shooter, LauncherDef def, Vec3 dir) {
        RocketEntity r = new RocketEntity(ExplosiveEntities.ROCKET.get(), level);
        r.entityData.set(KIND, def.id());
        r.setOwner(shooter);
        r.setPos(shooter.getEyePosition().add(dir.scale(0.8)));
        r.setDeltaMovement(dir.scale(def.speed()));
        if (def.homing()) {
            r.target = nearestFlyingTarget(level, shooter, dir);
        }
        level.addFreshEntity(r);
        return r;
    }

    /** The flying vehicle closest to the line of sight (within 20 degrees and 120 blocks), or null. */
    @Nullable
    public static UUID nearestFlyingTarget(ServerLevel level, Entity shooter, Vec3 dir) {
        Vec3 eye = shooter.getEyePosition();
        Vehicle best = null;
        double bestDot = Math.cos(Math.toRadians(20));
        for (Vehicle v : level.getEntitiesOfClass(Vehicle.class, shooter.getBoundingBox().inflate(120))) {
            if (v.def() == null || !v.def().flying() || !v.isAlive() || v.hasPassenger(shooter)) {
                continue;
            }
            Vec3 to = v.getBoundingBox().getCenter().subtract(eye);
            double dot = to.normalize().dot(dir);
            if (dot > bestDot) {
                bestDot = dot;
                best = v;
            }
        }
        return best == null ? null : best.getUUID();
    }

    public LauncherDef def() {
        return Balance.get().launcher(entityData.get(KIND));
    }

    @Override
    protected void defineSynchedData(SynchedEntityData.Builder b) {
        b.define(KIND, "rpg7");
    }

    @Override
    public ItemStack getItem() {
        var holder = ModItems.GUNS.get(entityData.get(KIND));
        return holder == null ? new ItemStack(net.minecraft.world.item.Items.FIREWORK_ROCKET) : new ItemStack(holder.get());
    }

    @Override
    public void tick() {
        super.tick();
        LauncherDef d = def();
        if (d == null || !(level() instanceof ServerLevel sl)) {
            if (d == null) {
                discard();
            }
            return;
        }
        if (tickCount > MAX_AGE) {
            discard();
            return;
        }
        Vec3 v = getDeltaMovement();
        if (target != null && sl.getEntity(target) instanceof Vehicle tv && tv.isAlive()) {
            Vec3 want = tv.getBoundingBox().getCenter().subtract(position()).normalize();
            v = v.normalize().scale(0.82).add(want.scale(0.18)).normalize().scale(d.speed());
        }
        v = v.add(0, -d.gravity(), 0);
        setDeltaMovement(v);
        HitResult hit = ProjectileUtil.getHitResultOnMoveVector(this, this::canHitEntity);
        setPos(position().add(v));
        if (hit.getType() != HitResult.Type.MISS) {
            explode(sl, hit.getLocation(), hit instanceof net.minecraft.world.phys.EntityHitResult ehr ? ehr.getEntity() : null);
            return;
        }
        sl.sendParticles(ParticleTypes.SMOKE, getX(), getY(), getZ(), 1, 0, 0, 0, 0);
    }

    @Override
    protected boolean canHitEntity(Entity e) {
        return super.canHitEntity(e) && e.isPickable() && !e.isSpectator();
    }

    private void explode(ServerLevel sl, Vec3 at, @Nullable Entity directHit) {
        LauncherDef d = def();
        double damage = d.damage() > 0 ? d.damage() : Balance.get().weapon(d.id()).chest();
        Blast.explode(sl, at, d.blast(), damage, getOwner(), d.id(), directHit);
        discard();
    }

    @Override
    protected void readAdditionalSaveData(CompoundTag t) {
        entityData.set(KIND, t.getString("kind"));
        target = t.hasUUID("target") ? t.getUUID("target") : null;
    }

    @Override
    protected void addAdditionalSaveData(CompoundTag t) {
        t.putString("kind", entityData.get(KIND));
        if (target != null) {
            t.putUUID("target", target);
        }
    }
}
