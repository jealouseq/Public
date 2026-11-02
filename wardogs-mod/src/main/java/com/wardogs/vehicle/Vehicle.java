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
    /** Bit i set = the i-th section of the definition is destroyed. Synced so the driver's client (which steers) sees it too. */
    private static final EntityDataAccessor<Integer> BROKEN = SynchedEntityData.defineId(Vehicle.class, EntityDataSerializers.INT);
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
        b.define(BROKEN, 0);
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
            if (getHealth() >= getMaxHealth() && !sectionsDamaged()) {
                player.displayClientMessage(Component.literal("Undamaged"), true);
            } else if (now - lastRepair >= 20) {
                lastRepair = now;
                heal((float) (getMaxHealth() * Balance.get().repairFraction()));
                repairSections();
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

    /** Hit points left in each damageable section (Engine, Fuel Tank, wheels...); filled from the definition on first use. */
    private final java.util.Map<String, Float> sectionHp = new java.util.LinkedHashMap<>();

    private java.util.Map<String, Float> sections() {
        VehicleDef d = def();
        if (sectionHp.isEmpty() && d != null) {
            d.sections().forEach((k, v) -> sectionHp.put(k, v.floatValue()));
        }
        return sectionHp;
    }

    /** True while the named section still has hit points (or the vehicle has no such section). */
    public boolean sectionWorks(String name) {
        VehicleDef d = def();
        if (d == null) {
            return true;
        }
        int i = 0;
        for (String k : d.sections().keySet()) {
            if (k.equals(name)) {
                return (entityData.get(BROKEN) & (1 << i)) == 0;
            }
            i++;
        }
        return true;
    }

    /** Recomputes the synced bitmask from the server-side hit points. */
    private void syncBroken() {
        VehicleDef d = def();
        int mask = 0, i = 0;
        if (d != null) {
            for (String k : d.sections().keySet()) {
                Float hp = sections().get(k);
                if (hp != null && hp <= 0) {
                    mask |= 1 << i;
                }
                i++;
            }
        }
        entityData.set(BROKEN, mask);
    }

    public float sectionHealth(String name) {
        Float hp = sections().get(name);
        return hp == null ? -1f : hp;
    }

    /** Which section a hit at {@code hit} lands on: wheels/tracks low at the sides, engine up front (rear on tracked vehicles), fuel tank at the back. */
    public String sectionAt(Vec3 hit) {
        VehicleDef d = def();
        if (d == null) {
            return "Hull";
        }
        Vec3 f = Vec3.directionFromRotation(0, getYRot());         // forward
        Vec3 local = hit.subtract(position());
        double lf = (local.x * f.x + local.z * f.z) / Math.max(0.1, d.d());   // -0.5 (rear) .. +0.5 (front)
        double lat = (local.x * f.z - local.z * f.x) / Math.max(0.1, d.w());  // left positive
        double ry = (hit.y - getY()) / Math.max(0.1, d.h());
        var secs = d.sections();
        boolean tracked = secs.containsKey("Tracks Left");
        String name = "Hull";
        if (d.flying()) {
            if (ry > 0.7 && secs.containsKey("Rotor Main")) {
                name = "Rotor Main";
            } else if (lf < -0.3 && secs.containsKey("Rotor Tail")) {
                name = "Rotor Tail";
            } else if (lf > 0.1 && ry < 0.7) {
                name = "Engine";
            } else if (lf < -0.1) {
                name = "Fuel Tank";
            }
        } else if (ry < 0.38 && Math.abs(lat) > 0.22) {
            String side = lat > 0 ? "Left" : "Right";
            if (tracked) {
                name = "Tracks " + side;
            } else {
                String pos = lf > 0.2 ? "Front" : lf < -0.2 ? "Rear" : "Middle";
                name = "Wheel " + side + " " + pos;
                if (!secs.containsKey(name)) {
                    name = "Wheel " + side + (lf > 0 ? " Front" : " Rear");
                }
            }
        } else if (tracked ? lf < -0.2 : lf > 0.15 && ry < 0.8) {
            name = "Engine";
        } else if (tracked ? lf > 0.2 : lf < -0.2) {
            name = "Fuel Tank";
        }
        return secs.containsKey(name) ? name : "Hull";
    }

    private void damageSection(String name, double amount) {
        var secs = sections();
        Float hp = secs.get(name);
        if (hp == null || hp <= 0 || amount <= 0) {
            return;
        }
        float left = (float) Math.max(0, hp - amount);
        secs.put(name, left);
        if (left <= 0) {
            syncBroken();
            for (Entity e : getPassengers()) {
                if (e instanceof ServerPlayer sp) {
                    sp.displayClientMessage(Component.literal(name.toUpperCase() + " DESTROYED").withStyle(ChatFormatting.RED), true);
                }
            }
            if (level() instanceof ServerLevel sl) {
                sl.sendParticles(ParticleTypes.LARGE_SMOKE, getX(), getY() + getBbHeight() * 0.6, getZ(), 12, 0.3, 0.2, 0.3, 0.02);
                sl.playSound(null, blockPosition(), SoundEvents.ITEM_BREAK, SoundSource.NEUTRAL, 1.2f, 0.6f);
            }
        }
    }

    /** Fraction of full speed left after broken wheels or tracks (never below 30%). */
    public double mobility() {
        double m = 1.0;
        VehicleDef d = def();
        if (d != null) {
            for (String k : d.sections().keySet()) {
                if (!sectionWorks(k)) {
                    if (k.startsWith("Wheel")) {
                        m -= 0.2;
                    } else if (k.startsWith("Tracks")) {
                        m -= 0.45;
                    }
                }
            }
        }
        return Math.max(0.3, m);
    }

    /** True if any section has been shot to pieces. */
    public boolean sectionsDamaged() {
        return entityData.get(BROKEN) != 0;
    }

    /** Puts every section back to full hit points (a repair). */
    public void repairSections() {
        sectionHp.clear();
        sections();
        entityData.set(BROKEN, 0);
    }

    /** Applies damage given in WARDOGS units, scaled by the vehicle's armor against that kind of attack. */
    public void applyWardogsDamage(double wardogsDamage, DamageKind kind, @Nullable Entity attacker) {
        applyWardogsDamage(wardogsDamage, kind, attacker, null);
    }

    /** As above, and when {@code hit} is known the section under it loses hit points too (engine, fuel tank, wheels, rotors...). */
    public void applyWardogsDamage(double wardogsDamage, DamageKind kind, @Nullable Entity attacker, @Nullable Vec3 hit) {
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
        if (hit != null) {
            damageSection(sectionAt(hit), wardogsDamage * mult);
        }
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
            sl.playSound(null, blockPosition(), com.wardogs.sound.ModSounds.EXPLOSION.get(), SoundSource.NEUTRAL, 3.0f, 0.7f);
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
        boolean piloted = getControllingPassenger() instanceof Player && fuel() > 0 && sectionWorks("Engine")
                && !(d.flying() && !sectionWorks("Rotor Main"));
        setNoGravity(d.flying() && piloted);
        if (!level().isClientSide && !sectionWorks("Fuel Tank") && fuel() > 0) {
            setFuel(fuel() - (float) (d.fuel() / 1200.0)); // a punctured tank empties in about a minute
        }
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
        if (d != null && isAlive() && getControllingPassenger() instanceof Player p && fuel() > 0 && sectionWorks("Engine")
                && !(d.flying() && !sectionWorks("Rotor Main"))) {
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
        double max = d.speed() * mobility();
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
        double max = d.speed() * Balance.get().airSpeedScale() * (sectionWorks("Rotor Tail") ? 1.0 : 0.5);
        float throttle = p.zza;
        speedNow += throttle * 0.03;
        if (throttle == 0) {
            speedNow *= 0.96;
        }
        speedNow = Mth.clamp(speedNow, -max * 0.3, max);
        float target = p.getYRot();
        float diff = Mth.wrapDegrees(target - getYRot());
        setYRot(getYRot() + (sectionWorks("Rotor Tail") ? Mth.clamp(diff, -4f, 4f) : 6f)); // without a tail rotor it spins
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
        CompoundTag secs = new CompoundTag();
        sectionHp.forEach(secs::putFloat);
        t.put("sections", secs);
    }

    @Override
    public void readAdditionalSaveData(CompoundTag t) {
        super.readAdditionalSaveData(t);
        entityData.set(KIND, t.getString("kind"));
        entityData.set(FUEL, t.getFloat("fuel"));
        entityData.set(FACTION, t.getInt("faction"));
        sectionHp.clear();
        CompoundTag secs = t.getCompound("sections");
        for (String k : secs.getAllKeys()) {
            sectionHp.put(k, secs.getFloat(k));
        }
        syncBroken();
        VehicleDef d = def();
        if (d != null) {
            getAttribute(Attributes.MAX_HEALTH).setBaseValue(d.hp());
        }
    }
}
