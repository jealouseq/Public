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

/** Hitscan bullet simulation: raycast, headshot detection, damage in WARDOGS units. */
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

    private static void shootRay(ServerLevel level, Player shooter, WeaponDef w, AmmoType ammo, int pellets, double spreadMult) {
        Vec3 start = shooter.getEyePosition();
        Vec3 dir = withSpread(shooter.getLookAngle(), w.spread() * spreadMult, shooter.getRandom());
        double range = Math.min(w.range(), MAX_TRACE);
        Vec3 end = start.add(dir.scale(range));

        HitResult block = level.clip(new ClipContext(start, end, ClipContext.Block.COLLIDER, ClipContext.Fluid.NONE, shooter));
        Vec3 blockEnd = block.getType() == HitResult.Type.MISS ? end : block.getLocation();

        AABB box = shooter.getBoundingBox().expandTowards(dir.scale(range)).inflate(1.0);
        EntityHitResult ehr = ProjectileUtil.getEntityHitResult(shooter, start, blockEnd, box,
                e -> !e.isSpectator() && e.isPickable() && e != shooter
                        && e != shooter.getVehicle() && !(shooter.getVehicle() != null && e.getVehicle() == shooter.getVehicle()), range * range);
        if (ehr == null || !(ehr.getEntity() instanceof LivingEntity target)) {
            if (block.getType() != HitResult.Type.MISS) {
                level.sendParticles(ParticleTypes.SMOKE, block.getLocation().x, block.getLocation().y, block.getLocation().z, 2, 0.02, 0.02, 0.02, 0.0);
            }
            return;
        }

        if (target instanceof Vehicle veh) {
            // Vehicles have no head; armor-piercing rounds do extra damage to them.
            double dmg = w.chest() * w.falloffMult(start.distanceTo(ehr.getLocation()))
                    * (ammo == AmmoType.AP ? Balance.get().apVsVehicle() : Balance.get().ammoMult(ammo, true)) / pellets;
            Ballistics.runAs(new Hit(w.id(), false, start.distanceTo(ehr.getLocation()), ammo),
                    () -> veh.applyWardogsDamage(dmg, Vehicle.DamageKind.BULLET, shooter));
            if (shooter instanceof net.minecraft.server.level.ServerPlayer sp) {
                com.wardogs.net.HitMarkerPayload.send(sp, false, !veh.isAlive());
            }
            return;
        }
        Vec3 hitPos = ehr.getLocation();
        HitZone zone = HitZone.at(target, hitPos);
        boolean head = zone == HitZone.HEAD;
        double distance = start.distanceTo(hitPos);
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
        if (shooter instanceof net.minecraft.server.level.ServerPlayer sp) {
            boolean down = !target.isAlive() || (target instanceof Player tp && com.wardogs.medic.Downed.is(tp));
            com.wardogs.net.HitMarkerPayload.send(sp, head, down);
        }
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
