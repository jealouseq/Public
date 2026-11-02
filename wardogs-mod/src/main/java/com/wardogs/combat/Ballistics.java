package com.wardogs.combat;

import com.wardogs.WardogsMod;
import com.wardogs.balance.AmmoType;
import com.wardogs.balance.ArmorDef;
import com.wardogs.item.ArmorPieceItem;
import net.minecraft.world.item.ItemStack;
import com.wardogs.vehicle.Vehicle;
import com.wardogs.balance.Balance;
import com.wardogs.balance.WeaponDef;
import javax.annotation.Nullable;
import net.minecraft.core.registries.Registries;
import net.minecraft.core.particles.ParticleTypes;
import net.minecraft.resources.ResourceKey;
import net.minecraft.server.level.ServerLevel;
import net.minecraft.world.damagesource.DamageSource;
import net.minecraft.world.damagesource.DamageType;
import net.minecraft.world.entity.Entity;
import net.minecraft.world.entity.LivingEntity;
import net.minecraft.world.entity.player.Player;
import net.minecraft.world.entity.projectile.ProjectileUtil;
import net.minecraft.world.level.ClipContext;
import net.minecraft.world.phys.AABB;
import net.minecraft.world.phys.EntityHitResult;
import net.minecraft.world.phys.HitResult;
import net.minecraft.world.phys.Vec3;
import net.minecraft.resources.ResourceLocation;

/** Bullet simulation: hitscan for weapons without a muzzle velocity, otherwise a bullet that travels and drops; headshots; damage in WARDOGS units. */
@net.neoforged.fml.common.EventBusSubscriber(modid = WardogsMod.MOD_ID)
public final class Ballistics {
    public static final ResourceKey<DamageType> BULLET =
            ResourceKey.create(Registries.DAMAGE_TYPE, ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "bullet"));

    public static final ResourceKey<DamageType> EXPLOSION =
            ResourceKey.create(Registries.DAMAGE_TYPE, ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "explosion"));

    /** True for damage dealt by this mod's weapons (already in WARDOGS units). */
    public static boolean isWardogsDamage(DamageSource s) {
        return s.is(BULLET) || s.is(EXPLOSION);
    }

    /** Runs {@code action} with {@code hit} visible to the kill-reward handler (death events fire inside hurt()). */
    public static void runAs(Hit hit, Runnable action) {
        CURRENT.set(hit);
        try {
            action.run();
        } finally {
            CURRENT.remove();
        }
    }

    /** Hard cap on trace length; chunk loading/render distance make longer traces meaningless. */
    public static final double MAX_TRACE = 256.0;

    /** Details of the bullet currently being resolved. Read by the death handler (fires synchronously inside hurt()). */
    public record Hit(String weaponId, boolean headshot, double distance, AmmoType ammo) {}

    private static final ThreadLocal<Hit> CURRENT = new ThreadLocal<>();

    private Ballistics() {}

    public static Hit current() {
        return CURRENT.get();
    }

    /** Damage of a round at a body zone before armour and falloff, in WARDOGS units. Weapons without a hit-zone class deal flat damage. */
    public static double rawDamage(WeaponDef w, HitZone zone) {
        Balance b = Balance.get();
        if (!b.hasHitZones(w.category())) {
            return w.chest();
        }
        double base = w.chest() / b.zoneMult(w.category(), HitZone.TORSO_UPPER.key);
        return base * b.zoneMult(w.category(), zone.key);
    }

    /**
     * Damage in WARDOGS units: zone multiplier x distance falloff x ammo multiplier, then armour absorption.
     * Unarmoured flesh uses the ammo's flesh multiplier; armoured zones use its armour multiplier and lose the armour's reduction.
     */
    public static double damage(WeaponDef w, HitZone zone, AmmoType ammo, @Nullable ArmorDef armor, double distance) {
        double d = rawDamage(w, zone) * w.falloffMult(distance);
        Balance b = Balance.get();
        if (armor == null) {
            return d * b.ammoMult(ammo, false);
        }
        return d * b.ammoMult(ammo, true) * (1.0 - armor.reduction());
    }

    /** Fires one trigger pull (all pellets) from the shooter's eyes. */
    public static void fire(ServerLevel level, Player shooter, WeaponDef w, AmmoType ammo) {
        fire(level, shooter, w, ammo, 1.0);
    }

    /** As {@link #fire(ServerLevel, Player, WeaponDef, AmmoType)} with the weapon's spread scaled (attachments). */
    public static void fire(ServerLevel level, Player shooter, WeaponDef w, AmmoType ammo, double spreadMult) {
        int pellets = Math.max(1, w.pellets());
        for (int i = 0; i < pellets; i++) {
            shootRay(level, shooter, w, ammo, pellets, spreadMult);
        }
    }

    /** Acceleration of gravity on a bullet, in m/s^2. */
    public static final double GRAVITY = 9.81;
    /** A bullet in flight is dropped after this many ticks (10 s). */
    private static final int MAX_AGE = 200;

    /** A round travelling at its muzzle velocity: advanced one tick at a time so it takes time to arrive and drops. */
    private static final class Bullet {
        final ServerLevel level;
        final Player shooter;
        final WeaponDef w;
        final AmmoType ammo;
        final int pellets;
        final Vec3 origin;
        Vec3 pos, vel; // vel in blocks per tick
        int age;

        Bullet(ServerLevel level, Player shooter, WeaponDef w, AmmoType ammo, int pellets, Vec3 origin, Vec3 vel) {
            this.level = level;
            this.shooter = shooter;
            this.w = w;
            this.ammo = ammo;
            this.pellets = pellets;
            this.origin = origin;
            this.pos = origin;
            this.vel = vel;
        }
    }

    private static final java.util.List<Bullet> FLYING = new java.util.concurrent.CopyOnWriteArrayList<>();

    /** How far a bullet has fallen (in blocks) after {@code ticks} ticks of flight with the per-tick gravity step used by {@link #advance}. */
    public static double drop(int ticks) {
        return GRAVITY / 400.0 * ticks * (ticks - 1) / 2.0;
    }

    /** Bullets currently in flight (for tests and tooling). */
    public static int bulletsInFlight() {
        return FLYING.size();
    }

    public static void clearBullets() {
        FLYING.clear();
    }

    /** Moves every flying bullet one tick; called from the server tick. */
    public static void tickBullets() {
        for (Bullet b : FLYING) {
            if (!advance(b)) {
                FLYING.remove(b);
            }
        }
    }

    /** One tick of flight. @return true while the bullet is still flying. */
    private static boolean advance(Bullet b) {
        Vec3 next = b.pos.add(b.vel);
        boolean consumed = resolve(b.level, b.shooter, b.w, b.ammo, b.pellets, b.origin, b.pos, next);
        b.pos = next;
        b.vel = b.vel.add(0, -GRAVITY / 400.0, 0); // gravity acts after the step, so the first segment follows the crosshair line
        b.age++;
        if (!consumed && b.age % 2 == 0 && b.w.velocity() >= 150) {
            b.level.sendParticles(ParticleTypes.END_ROD, next.x, next.y, next.z, 1, 0, 0, 0, 0); // faint tracer
        }
        double range = Math.min(b.w.range(), MAX_TRACE);
        return !consumed && b.age < MAX_AGE && b.origin.distanceTo(b.pos) <= range
                && b.pos.y > b.level.getMinBuildHeight() - 8 && b.pos.y < b.level.getMaxBuildHeight() + 64;
    }

    @net.neoforged.bus.api.SubscribeEvent
    public static void onServerTick(net.neoforged.neoforge.event.tick.ServerTickEvent.Post e) {
        tickBullets();
    }

    private static void shootRay(ServerLevel level, Player shooter, WeaponDef w, AmmoType ammo, int pellets, double spreadMult) {
        Vec3 start = shooter.getEyePosition();
        Vec3 dir = withSpread(shooter.getLookAngle(), w.spread() * spreadMult, shooter.getRandom());
        double range = Math.min(w.range(), MAX_TRACE);
        if (w.velocity() > 0) {
            Bullet b = new Bullet(level, shooter, w, ammo, pellets, start, dir.scale(w.velocity() / 20.0));
            if (advance(b)) { // the first tick resolves immediately, so close targets are hit at once
                FLYING.add(b);
            }
            return;
        }
        resolve(level, shooter, w, ammo, pellets, start, start, start.add(dir.scale(range)));
    }

    /**
     * Checks the segment {@code from}-{@code to} for a block or an entity and applies damage if it hits something.
     * Distance (for falloff) is measured from {@code origin}, where the shot left the barrel.
     * @return true if the round was used up (hit a block or an entity)
     */
    private static boolean resolve(ServerLevel level, Player shooter, WeaponDef w, AmmoType ammo, int pellets,
                                   Vec3 origin, Vec3 from, Vec3 to) {
        Vec3 start = from, end = to;
        Vec3 dir = end.subtract(start);
        double range = dir.length();
        if (range < 1.0E-6) {
            return false;
        }

        HitResult block = level.clip(new ClipContext(start, end, ClipContext.Block.COLLIDER, ClipContext.Fluid.NONE, shooter));
        Vec3 blockEnd = block.getType() == HitResult.Type.MISS ? end : block.getLocation();

        AABB box = new AABB(start, end).inflate(1.0); // candidates near this segment, not near the shooter
        EntityHitResult ehr = ProjectileUtil.getEntityHitResult(shooter, start, blockEnd, box,
                e -> !e.isSpectator() && e.isPickable() && e != shooter
                        && e != shooter.getVehicle() && !(shooter.getVehicle() != null && e.getVehicle() == shooter.getVehicle()), range * range + 4);
        if (ehr == null || !(ehr.getEntity() instanceof LivingEntity target)) {
            if (block.getType() != HitResult.Type.MISS) {
                level.sendParticles(ParticleTypes.SMOKE, block.getLocation().x, block.getLocation().y, block.getLocation().z, 2, 0.02, 0.02, 0.02, 0.0);
                impactSound(level, block.getLocation(), com.wardogs.sound.ModSounds.IMPACT_BLOCK.get());
                return true;
            }
            return false;
        }

        if (target instanceof Vehicle veh) {
            // Vehicles have no head; armor-piercing rounds do extra damage to them.
            double dmg = w.chest() * w.falloffMult(origin.distanceTo(ehr.getLocation()))
                    * (ammo == AmmoType.AP ? Balance.get().apVsVehicle() : Balance.get().ammoMult(ammo, true)) / pellets;
            Ballistics.runAs(new Hit(w.id(), false, origin.distanceTo(ehr.getLocation()), ammo),
                    () -> veh.applyWardogsDamage(dmg, Vehicle.DamageKind.BULLET, shooter, ehr.getLocation()));
            if (shooter instanceof net.minecraft.server.level.ServerPlayer sp) {
                com.wardogs.net.HitMarkerPayload.send(sp, false, !veh.isAlive());
            }
            impactSound(level, ehr.getLocation(), com.wardogs.sound.ModSounds.IMPACT_METAL.get());
            return true;
        }
        Vec3 hitPos = ehr.getLocation();
        HitZone zone = HitZone.at(target, hitPos);
        boolean head = zone == HitZone.HEAD;
        double distance = origin.distanceTo(hitPos);
        ItemStack armorStack = ArmorPieceItem.protecting(target, zone);
        ArmorDef armor = armorStack.isEmpty() ? null : ((ArmorPieceItem) armorStack.getItem()).def();
        double wd = damage(w, zone, ammo, armor, distance) / pellets;
        if (armor != null) {
            double absorbed = rawDamage(w, zone) * w.falloffMult(distance) * Balance.get().ammoMult(ammo, true) * armor.reduction() / pellets;
            armorStack.setDamageValue(Math.min(armorStack.getMaxDamage(), armorStack.getDamageValue() + (int) Math.ceil(absorbed)));
        }
        float mc = Balance.get().damageTo(target, wd);

        DamageSource src = new DamageSource(
                level.registryAccess().registryOrThrow(Registries.DAMAGE_TYPE).getHolderOrThrow(BULLET), shooter, shooter);
        CURRENT.set(new Hit(w.id(), head, distance, ammo));
        try {
            target.invulnerableTime = 0; // bullets must not be swallowed by vanilla hit-immunity frames
            target.hurt(src, mc);
        } finally {
            CURRENT.remove();
        }
        level.sendParticles(head ? ParticleTypes.CRIT : ParticleTypes.DAMAGE_INDICATOR,
                hitPos.x, hitPos.y, hitPos.z, head ? 6 : 2, 0.05, 0.05, 0.05, 0.1);
        impactSound(level, hitPos, com.wardogs.sound.ModSounds.IMPACT_FLESH.get());
        if (shooter instanceof net.minecraft.server.level.ServerPlayer sp) {
            boolean down = !target.isAlive() || (target instanceof Player tp && com.wardogs.medic.Downed.is(tp));
            com.wardogs.net.HitMarkerPayload.send(sp, head, down);
        }
        return true;
    }

    private static void impactSound(ServerLevel level, Vec3 at, net.minecraft.sounds.SoundEvent event) {
        level.playSound(null, at.x, at.y, at.z, event, net.minecraft.sounds.SoundSource.PLAYERS, 0.9f, 0.9f + level.random.nextFloat() * 0.2f);
    }

    /** Head = upper part of the hitbox around the eye line. */
    public static boolean isHeadshot(LivingEntity target, Vec3 hit) {
        return hit.y >= target.getEyeY() - 0.25;
    }

    /** Random direction inside a cone; {@code spreadValue} is the sources' "accuracy" figure (lower is better). */
    public static Vec3 withSpread(Vec3 dir, double spreadValue, net.minecraft.util.RandomSource rnd) {
        double maxAngle = Math.toRadians(spreadValue * 0.3);
        if (maxAngle <= 0) {
            return dir;
        }
        double angle = Math.sqrt(rnd.nextDouble()) * maxAngle;
        double rot = rnd.nextDouble() * Math.PI * 2.0;
        Vec3 up = Math.abs(dir.y) > 0.99 ? new Vec3(1, 0, 0) : new Vec3(0, 1, 0);
        Vec3 right = dir.cross(up).normalize();
        Vec3 realUp = right.cross(dir).normalize();
        Vec3 offset = right.scale(Math.cos(rot) * Math.tan(angle)).add(realUp.scale(Math.sin(rot) * Math.tan(angle)));
        return dir.add(offset).normalize();
    }
}
