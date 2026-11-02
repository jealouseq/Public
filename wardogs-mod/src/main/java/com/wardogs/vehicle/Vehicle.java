package com.wardogs.vehicle;

import com.wardogs.balance.Balance;
import com.wardogs.balance.VehicleDef;
import com.wardogs.combat.Ballistics;
import com.wardogs.economy.Wallet;
import com.wardogs.item.SupplyItem;
import com.wardogs.item.WrenchItem;
import com.wardogs.match.Factions;
import com.wardogs.match.MatchManager;
import com.wardogs.progress.Progression;
import com.wardogs.progress.Role;
import net.minecraft.ChatFormatting;
import net.minecraft.core.particles.ParticleTypes;
import net.minecraft.core.registries.Registries;
import net.minecraft.nbt.CompoundTag;
import net.minecraft.network.chat.Component;
import net.minecraft.network.syncher.EntityDataAccessor;
import net.minecraft.network.syncher.EntityDataSerializers;
import net.minecraft.network.syncher.SynchedEntityData;
import net.minecraft.server.level.ServerLevel;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.sounds.SoundEvents;
import net.minecraft.sounds.SoundSource;
import net.minecraft.util.Mth;
import net.minecraft.world.InteractionHand;
import net.minecraft.world.InteractionResult;
import net.minecraft.world.damagesource.DamageSource;
import net.minecraft.world.entity.Entity;
import net.minecraft.world.entity.EntityDimensions;
import net.minecraft.world.entity.EntityType;
import net.minecraft.world.entity.LivingEntity;
import net.minecraft.world.entity.MoverType;
import net.minecraft.world.entity.Mob;
import net.minecraft.world.entity.Pose;
import net.minecraft.world.entity.ai.attributes.AttributeSupplier;
import net.minecraft.world.entity.ai.attributes.Attributes;
import net.minecraft.world.entity.player.Player;
import net.minecraft.world.item.ItemStack;
import net.minecraft.world.level.Level;
import net.minecraft.world.phys.Vec3;

import javax.annotation.Nullable;
import java.util.HashSet;
import java.util.Set;
import java.util.UUID;

/**
 * A drivable vehicle (car, truck, tank, helicopter). Steering runs on the driver's client like a horse;
 * the server owns health, fuel, seats, damage and rewards. All numbers come from {@link VehicleDef}.
 */
public class Vehicle extends Mob {
    private static final EntityDataAccessor<String> KIND = SynchedEntityData.defineId(Vehicle.class, EntityDataSerializers.STRING);
    private static final EntityDataAccessor<Float> FUEL = SynchedEntityData.defineId(Vehicle.class, EntityDataSerializers.FLOAT);
    private static final EntityDataAccessor<Integer> FACTION = SynchedEntityData.defineId(Vehicle.class, EntityDataSerializers.INT);

    public enum DamageKind { BULLET, EXPLOSIVE, ANTI_TANK }

    @Nullable
    private UUID lastAttacker;
    private final Set<UUID> boardedOutside = new HashSet<>();
    private double lastX, lastZ;
    private long lastRepair;
    /** Game time (fractional ticks) when the mounted gun may fire again. */
    public double nextShot;
    /** Client-side forward speed while driven. */
    private double speedNow;

    public Vehicle(EntityType<? extends Vehicle> type, Level level) {
        super(type, level);
        setPersistenceRequired();
    }

    public static AttributeSupplier.Builder attributes() {
        return Mob.createMobAttributes()
                .add(Attributes.MAX_HEALTH, 100.0)
                .add(Attributes.MOVEMENT_SPEED, 0.2)
                .add(Attributes.STEP_HEIGHT, 1.1)
                .add(Attributes.KNOCKBACK_RESISTANCE, 1.0);
    }

    public static Vehicle spawn(ServerLevel level, VehicleDef def, Vec3 pos, float yaw, int faction) {
        Vehicle v = new Vehicle(VehicleEntities.VEHICLE.get(), level);
        v.entityData.set(KIND, def.id());
        v.entityData.set(FACTION, faction);
        v.entityData.set(FUEL, (float) def.fuel());
        v.getAttribute(Attributes.MAX_HEALTH).setBaseValue(def.hp());
        v.setHealth((float) def.hp());
        v.moveTo(pos.x, pos.y, pos.z, yaw, 0f);
        v.lastX = pos.x;
        v.lastZ = pos.z;
        level.addFreshEntity(v);
        return v;
    }

    @Override
    protected void defineSynchedData(SynchedEntityData.Builder b) {
        super.defineSynchedData(b);
        b.define(KIND, "bobcat");
        b.define(FUEL, 0f);
        b.define(FACTION, -1);
    }

    @Nullable
    public VehicleDef def() {
        return Balance.get().vehicle(entityData.get(KIND));
    }

    public float fuel() {
        return entityData.get(FUEL);
    }

    public int faction() {
        return entityData.get(FACTION);
    }

    public void setFuel(float f) {
        VehicleDef d = def();
        entityData.set(FUEL, Mth.clamp(f, 0f, d == null ? f : (float) d.fuel()));
    }

    @Override
    public EntityDimensions getDefaultDimensions(Pose pose) {
        VehicleDef d = def();
        if (d == null) {
            return super.getDefaultDimensions(pose);
        }
        // Collision boxes are square; use the geometric mean of width and length as a compromise.
        return EntityDimensions.scalable((float) Math.sqrt(d.w() * d.d()), (float) d.h());
    }

    @Override
    public void onSyncedDataUpdated(EntityDataAccessor<?> key) {
        super.onSyncedDataUpdated(key);
        if (KIND.equals(key)) {
            refreshDimensions();
        }
    }

    // --- seats -------------------------------------------------------------------------------

    @Override
    protected boolean canAddPassenger(Entity passenger) {
        VehicleDef d = def();
        return d != null && getPassengers().size() < d.seats();
    }

    @Nullable
    @Override
    public LivingEntity getControllingPassenger() {
        return getFirstPassenger() instanceof LivingEntity le ? le : null;
    }

    private Vec3 seatOffset(int idx) {
        VehicleDef d = def();
        double w = d == null ? 1 : d.w(), len = d == null ? 1 : d.d();
        double side = (idx % 2 == 0 ? -1 : 1) * w * 0.22;
        double[] rows = {0.2, -0.1, -0.3, -0.42};
        return new Vec3(side, 0, len * rows[Math.min(idx / 2, rows.length - 1)]);
    }

    @Override
    protected void positionRider(Entity passenger, MoveFunction cb) {
        if (!hasPassenger(passenger)) {
            return;
        }
        int idx = Math.max(0, getPassengers().indexOf(passenger));
        Vec3 off = seatOffset(idx);
        float yaw = getYRot() * Mth.DEG_TO_RAD;
        Vec3 fwd = new Vec3(-Mth.sin(yaw), 0, Mth.cos(yaw));
        Vec3 right = new Vec3(-Mth.cos(yaw), 0, -Mth.sin(yaw));
        VehicleDef d = def();
        double y = getY() + (d == null ? 0.5 : d.h() * 0.4);
        cb.accept(passenger, getX() + fwd.x * off.z + right.x * off.x, y, getZ() + fwd.z * off.z + right.z * off.x);
    }

    @Override
    protected void addPassenger(Entity passenger) {
        super.addPassenger(passenger);
        if (!level().isClientSide && MatchManager.zoneLevel(level(), position()) == 0) {
            boardedOutside.add(passenger.getUUID());
        }
    }

    @Override
    protected void removePassenger(Entity passenger) {
        boolean wasDriver = passenger == getControllingPassenger();
        super.removePassenger(passenger);
        if (level().isClientSide || wasDriver || !boardedOutside.remove(passenger.getUUID())) {
            return;
        }
        // A passenger delivered into the zone pays the driver.
        if (MatchManager.zoneLevel(level(), position()) >= 1 && getControllingPassenger() instanceof ServerPlayer driver && driver != passenger) {
            VehicleDef d = def();
            long pay = Balance.get().transportPay(d != null && d.flying());
            Wallet.add(driver, pay);
            Progression.award(driver, d != null && d.flying() ? Role.PILOT : Role.DRIVER, pay);
            com.wardogs.net.RewardPayload.send(driver, "PASSENGER DELIVERED", pay);
        }
    }

    // --- interaction -------------------------------------------------------------------------

    @Override
    protected InteractionResult mobInteract(Player player, InteractionHand hand) {
        if (level().isClientSide) {
            return InteractionResult.SUCCESS;
        }
        ItemStack held = player.getItemInHand(hand);
        VehicleDef d = def();
        int pf = Factions.index(player);
        boolean friendly = faction() < 0 || pf < 0 || pf == faction();
        if (d == null || !friendly) {
            return InteractionResult.PASS;
        }
        if (held.getItem() instanceof SupplyItem sup && sup.kind().equals("fuel")) {
            double per = Balance.get().fuelPerSupply();
            int need = (int) Math.ceil((d.fuel() - fuel()) / per);
            int use = Math.min(need, held.getCount());
            if (use <= 0) {
                player.displayClientMessage(Component.literal("Tank is full"), true);
                return InteractionResult.CONSUME;
            }
            held.shrink(use);
            setFuel((float) (fuel() + use * per));
            player.displayClientMessage(Component.literal("Fuel " + (int) fuel() + "/" + (int) d.fuel()), true);
            return InteractionResult.CONSUME;
        }
        if (held.getItem() instanceof WrenchItem) {
            long now = level().getGameTime();
            if (getHealth() >= getMaxHealth()) {
                player.displayClientMessage(Component.literal("Undamaged"), true);
            } else if (now - lastRepair >= 20) {
                lastRepair = now;
                heal((float) (getMaxHealth() * Balance.get().repairFraction()));
                level().playSound(null, blockPosition(), SoundEvents.ANVIL_USE, SoundSource.NEUTRAL, 0.5f, 1.4f);
                player.displayClientMessage(Component.literal("HP " + (int) getHealth() + "/" + (int) getMaxHealth()), true);
            }
            return InteractionResult.CONSUME;
        }
        if (player.isSecondaryUseActive()) {
            return InteractionResult.PASS;
        }
        if (getPassengers().size() < d.seats() && player.startRiding(this)) {
            return InteractionResult.CONSUME;
        }
        return InteractionResult.PASS;
    }

    // --- damage ------------------------------------------------------------------------------

    /** Applies damage given in WARDOGS units, scaled by the vehicle's armor against that kind of attack. */
    public void applyWardogsDamage(double wardogsDamage, DamageKind kind, @Nullable Entity attacker) {
        VehicleDef d = def();
        if (d == null || !(level() instanceof ServerLevel sl)) {
            return;
        }
        double mult = switch (kind) {
            case BULLET -> d.smallArms();
            case EXPLOSIVE -> d.explosive();
            case ANTI_TANK -> d.antiTank();
        };
        if (attacker != null) {
            lastAttacker = attacker.getUUID();
        }
        var type = kind == DamageKind.BULLET ? Ballistics.BULLET : Ballistics.EXPLOSION;
        DamageSource src = new DamageSource(sl.registryAccess().registryOrThrow(Registries.DAMAGE_TYPE).getHolderOrThrow(type), attacker, attacker);
        invulnerableTime = 0;
        hurt(src, (float) (wardogsDamage * mult));
    }

    @Override
    public void die(DamageSource source) {
        if (!level().isClientSide && level() instanceof ServerLevel sl) {
            VehicleDef d = def();
            Entity killer = source.getEntity();
            if (killer == null && lastAttacker != null) {
                killer = sl.getServer().getPlayerList().getPlayer(lastAttacker);
            }
            if (d != null && killer instanceof ServerPlayer sp && (faction() < 0 || Factions.index(sp) != faction())) {
                Wallet.add(sp, d.killReward());
                Progression.award(sp, Role.WARDOG, d.killReward());
                com.wardogs.net.RewardPayload.send(sp, "VEHICLE DESTROYED", d.killReward());
            }
            ejectPassengers();
            sl.sendParticles(ParticleTypes.EXPLOSION_EMITTER, getX(), getY() + getBbHeight() / 2, getZ(), 1, 0, 0, 0, 0);
            sl.playSound(null, blockPosition(), SoundEvents.GENERIC_EXPLODE.value(), SoundSource.NEUTRAL, 3.0f, 0.7f);
        }
        super.die(source);
    }

    // --- movement and fuel -------------------------------------------------------------------

    @Override
    public void tick() {
        super.tick();
        VehicleDef d = def();
        if (d == null) {
            return;
        }
        boolean piloted = getControllingPassenger() instanceof Player && fuel() > 0;
        setNoGravity(d.flying() && piloted);
        if (!level().isClientSide) {
            double moved = Math.hypot(getX() - lastX, getZ() - lastZ);
            lastX = getX();
            lastZ = getZ();
            if (piloted) {
                double burn = moved * d.fuelPerBlock(Balance.get().fuelPerBlock()) + (d.flying() ? 0.01 : 0.0);
                if (burn > 0) {
                    setFuel((float) (fuel() - burn));
                }
            }
        }
    }

    @Override
    public void travel(Vec3 input) {
        VehicleDef d = def();
        if (d != null && isAlive() && getControllingPassenger() instanceof Player p && fuel() > 0) {
            if (d.flying()) {
                flyWith(p, d);
            } else {
                driveWith(p, d);
            }
            return;
        }
        speedNow = 0;
        super.travel(Vec3.ZERO);
    }

    private void driveWith(Player p, VehicleDef d) {
        double max = d.speed();
        float throttle = p.zza;
        speedNow += throttle * 0.035;
        if (throttle == 0) {
            speedNow *= 0.93;
        }
        speedNow = Mth.clamp(speedNow, -max * 0.4, max);
        double grip = Mth.clamp(Math.abs(speedNow) / max, 0.25, 1.0);
        float turn = (float) (p.xxa * 3.2 * grip * (speedNow >= 0 ? 1 : -1));
        setYRot(getYRot() - turn);
        yRotO = getYRot();
        setXRot(0);
        Vec3 dir = Vec3.directionFromRotation(0, getYRot());
        double vy = onGround() ? 0 : (getDeltaMovement().y - 0.08) * 0.98;
        setDeltaMovement(dir.x * speedNow, vy, dir.z * speedNow);
        move(MoverType.SELF, getDeltaMovement());
        if (horizontalCollision) {
            speedNow *= 0.4;
        }
    }

    private void flyWith(Player p, VehicleDef d) {
        double max = d.speed() * Balance.get().airSpeedScale();
        float throttle = p.zza;
        speedNow += throttle * 0.03;
        if (throttle == 0) {
            speedNow *= 0.96;
        }
        speedNow = Mth.clamp(speedNow, -max * 0.3, max);
        float target = p.getYRot();
        float diff = Mth.wrapDegrees(target - getYRot());
        setYRot(getYRot() + Mth.clamp(diff, -4f, 4f));
        yRotO = getYRot();
        setXRot(0);
        // Look up/down beyond a dead zone to climb/descend; hover otherwise.
        float pitch = p.getXRot();
        double climb = Math.abs(pitch) > 15 ? -Math.signum(pitch) * Mth.clamp((Math.abs(pitch) - 15) / 60.0, 0, 1) : 0;
        Vec3 dir = Vec3.directionFromRotation(0, getYRot());
        Vec3 side = new Vec3(-dir.z, 0, dir.x);
        double strafe = p.xxa * max * 0.4;
        setDeltaMovement(dir.x * speedNow + side.x * strafe, climb * max * 0.7, dir.z * speedNow + side.z * strafe);
        move(MoverType.SELF, getDeltaMovement());
    }

    @Override
    public boolean isPushable() {
        return false;
    }

    @Override
    public boolean removeWhenFarAway(double dist) {
        return false;
    }

    @Override
    public boolean canBeLeashed() {
        return false;
    }

    // --- persistence -------------------------------------------------------------------------

    @Override
    public void addAdditionalSaveData(CompoundTag t) {
        super.addAdditionalSaveData(t);
        t.putString("kind", entityData.get(KIND));
        t.putFloat("fuel", fuel());
        t.putInt("faction", faction());
    }

    @Override
    public void readAdditionalSaveData(CompoundTag t) {
        super.readAdditionalSaveData(t);
        entityData.set(KIND, t.getString("kind"));
        entityData.set(FUEL, t.getFloat("fuel"));
        entityData.set(FACTION, t.getInt("faction"));
        VehicleDef d = def();
        if (d != null) {
            getAttribute(Attributes.MAX_HEALTH).setBaseValue(d.hp());
        }
    }
}
