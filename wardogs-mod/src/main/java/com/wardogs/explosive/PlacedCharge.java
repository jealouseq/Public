package com.wardogs.explosive;

import com.wardogs.balance.Balance;
import com.wardogs.balance.ExplosiveDef;
import com.wardogs.build.Buildable;
import com.wardogs.match.Factions;
import com.wardogs.item.ExplosiveItem;
import com.wardogs.item.ModItems;
import net.minecraft.core.BlockPos;
import net.minecraft.core.particles.ParticleTypes;
import net.minecraft.nbt.CompoundTag;
import net.minecraft.network.syncher.EntityDataAccessor;
import net.minecraft.network.syncher.EntityDataSerializers;
import net.minecraft.network.syncher.SynchedEntityData;
import net.minecraft.server.level.ServerLevel;
import net.minecraft.world.entity.Entity;
import net.minecraft.world.entity.EntityType;
import net.minecraft.world.entity.LivingEntity;
import net.minecraft.world.entity.projectile.ItemSupplier;
import net.minecraft.world.item.ItemStack;
import net.minecraft.world.level.Level;
import net.minecraft.world.phys.Vec3;

import javax.annotation.Nullable;
import java.util.UUID;

/** A placed C4, IED, anti-tank mine or claymore. */
public class PlacedCharge extends Entity implements ItemSupplier {
    private static final EntityDataAccessor<String> KIND = SynchedEntityData.defineId(PlacedCharge.class, EntityDataSerializers.STRING);
    /** Mines only arm after this many ticks so the placer can step away. */
    public static final int ARM_TICKS = 40;

    @Nullable
    private UUID owner;

    public PlacedCharge(EntityType<? extends PlacedCharge> type, Level level) {
        super(type, level);
        this.noPhysics = false;
    }

    public static PlacedCharge place(ServerLevel level, ExplosiveDef def, Vec3 pos, float yaw, @Nullable UUID owner) {
        PlacedCharge c = new PlacedCharge(ExplosiveEntities.CHARGE.get(), level);
        c.setPos(pos);
        c.setYRot(yaw);
        c.entityData.set(KIND, def.id());
        c.owner = owner;
        level.addFreshEntity(c);
        return c;
    }

    @Override
    protected void defineSynchedData(SynchedEntityData.Builder b) {
        b.define(KIND, "c4");
    }

    public ExplosiveDef def() {
        return Balance.get().explosive(entityData.get(KIND));
    }

    @Nullable
    public UUID ownerId() {
        return owner;
    }

    @Override
    public ItemStack getItem() {
        ExplosiveDef d = def();
        return d == null || !ModItems.EXPLOSIVES.containsKey(d.id()) ? ItemStack.EMPTY : new ItemStack(ModItems.EXPLOSIVES.get(d.id()).get());
    }

    @Override
    public boolean isPickable() {
        return false;
    }

    @Override
    public void tick() {
        super.tick();
        if (!(level() instanceof ServerLevel sl)) {
            return;
        }
        ExplosiveDef d = def();
        if (d == null) {
            discard();
            return;
        }
        // Fall if the supporting block disappears.
        if (!sl.getBlockState(BlockPos.containing(getX(), getY() - 0.05, getZ())).isSolidRender(sl, BlockPos.containing(getX(), getY() - 0.05, getZ()))
                && sl.getBlockState(BlockPos.containing(getX(), getY() - 0.05, getZ())).getCollisionShape(sl, BlockPos.containing(getX(), getY() - 0.05, getZ())).isEmpty()) {
            setPos(getX(), getY() - 0.2, getZ());
        }
        if (tickCount < ARM_TICKS) {
            return;
        }
        switch (d.kind()) {
            case AT_MINE -> {
                for (Entity e : sl.getEntities(this, getBoundingBox().inflate(1.2, 0.8, 1.2), x -> x.getType().is(ExplosiveEntities.VEHICLES))) {
                    detonate();
                    return;
                }
            }
            case CLAYMORE -> {
                if (tickCount % 2 == 0 && claymoreTriggered(sl, d)) {
                    detonate();
                }
            }
            default -> {
                if (tickCount % 20 == 0) {
                    sl.sendParticles(ParticleTypes.CRIT, getX(), getY() + 0.2, getZ(), 1, 0.05, 0.05, 0.05, 0);
                }
            }
        }
    }

    private boolean claymoreTriggered(ServerLevel sl, ExplosiveDef d) {
        Vec3 fwd = Vec3.directionFromRotation(0, getYRot());
        double half = Math.toRadians(d.cone() / 2.0);
        Vec3 origin = position().add(0, 0.2, 0);
        for (LivingEntity e : sl.getEntitiesOfClass(LivingEntity.class, getBoundingBox().inflate(d.range()), LivingEntity::isAlive)) {
            if (owner != null && e.getUUID().equals(owner)) {
                continue;
            }
            Vec3 to = e.getBoundingBox().getCenter().subtract(origin);
            double dist = to.length();
            if (dist < 0.5 || dist > d.range()) {
                continue;
            }
            if (Math.acos(Math.max(-1, Math.min(1, to.normalize().dot(fwd)))) <= half) {
                return true;
            }
        }
        return false;
    }

    /** Sets the charge off. */
    public void detonate() {
        if (!(level() instanceof ServerLevel sl) || isRemoved()) {
            return;
        }
        ExplosiveDef d = def();
        Entity ownerEntity = owner == null ? null : sl.getEntity(owner);
        if (ownerEntity == null && owner != null) {
            ownerEntity = sl.getServer().getPlayerList().getPlayer(owner);
        }
        if (d.kind() == ExplosiveDef.Kind.CLAYMORE) {
            Vec3 fwd = Vec3.directionFromRotation(0, getYRot());
            double half = Math.toRadians(d.cone() / 2.0);
            Vec3 origin = position().add(0, 0.2, 0);
            for (LivingEntity e : sl.getEntitiesOfClass(LivingEntity.class, getBoundingBox().inflate(d.range()), LivingEntity::isAlive)) {
                Vec3 to = e.getBoundingBox().getCenter().subtract(origin);
                double dist = to.length();
                if (dist > d.range() || dist < 0.01 || Math.acos(Math.max(-1, Math.min(1, to.normalize().dot(fwd)))) > half) {
                    continue;
                }
                Blast.damage(sl, e, ownerEntity, d.id(), Blast.falloff(d.damage(), d.range() * 1.5, dist),
                        ownerEntity == null ? dist : ownerEntity.position().distanceTo(e.position()));
            }
            int attackerFaction = ownerEntity instanceof net.minecraft.world.entity.player.Player op ? Factions.index(op) : -1;
            for (Buildable b : sl.getEntitiesOfClass(Buildable.class, getBoundingBox().inflate(d.range()))) {
                Vec3 to = b.getBoundingBox().getCenter().subtract(origin);
                if (to.length() <= d.range() && to.length() > 0.01
                        && Math.acos(Math.max(-1, Math.min(1, to.normalize().dot(fwd)))) <= half && b.def() != null) {
                    b.damageStructure(b.def().hitDamage(d.id(), Balance.get()), attackerFaction);
                }
            }
            sl.sendParticles(ParticleTypes.EXPLOSION, getX(), getY() + 0.3, getZ(), 1, 0, 0, 0, 0);
        } else {
            Blast.explode(sl, position().add(0, 0.2, 0), d.radius(), d.damage(), ownerEntity, d.id());
        }
        discard();
    }

    @Override
    protected void readAdditionalSaveData(CompoundTag t) {
        entityData.set(KIND, t.getString("kind"));
        owner = t.hasUUID("owner") ? t.getUUID("owner") : null;
    }

    @Override
    protected void addAdditionalSaveData(CompoundTag t) {
        t.putString("kind", entityData.get(KIND));
        if (owner != null) {
            t.putUUID("owner", owner);
        }
    }
}
