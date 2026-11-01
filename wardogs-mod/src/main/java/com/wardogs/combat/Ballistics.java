package com.wardogs.combat;

import com.wardogs.WardogsMod;
import com.wardogs.balance.AmmoType;
import com.wardogs.balance.Balance;
import com.wardogs.balance.WeaponDef;
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

    /** Hard cap on trace length; chunk loading/render distance make longer traces meaningless. */
    public static final double MAX_TRACE = 256.0;

    /** Details of the bullet currently being resolved. Read by the death handler (fires synchronously inside hurt()). */
    public record Hit(String weaponId, boolean headshot, double distance, AmmoType ammo) {}

    private static final ThreadLocal<Hit> CURRENT = new ThreadLocal<>();

    private Ballistics() {}

    public static Hit current() {
        return CURRENT.get();
    }

    /** Pure damage calculation in WARDOGS units (flesh only; armor is a later milestone). */
    public static double damage(WeaponDef w, boolean headshot, AmmoType ammo) {
        double base = headshot ? w.head() : w.chest();
        double flesh = switch (ammo) {
            case FMJ -> 1.0;
            case HP -> 2.0;
            case AP -> 0.8;
        };
        return base * flesh;
    }

    /** Fires one trigger pull (all pellets) from the shooter's eyes. */
    public static void fire(ServerLevel level, Player shooter, WeaponDef w, AmmoType ammo) {
        int pellets = Math.max(1, w.pellets());
        for (int i = 0; i < pellets; i++) {
            shootRay(level, shooter, w, ammo, pellets);
        }
    }

    private static void shootRay(ServerLevel level, Player shooter, WeaponDef w, AmmoType ammo, int pellets) {
        Vec3 start = shooter.getEyePosition();
        Vec3 dir = withSpread(shooter.getLookAngle(), w.spread(), shooter.getRandom());
        double range = Math.min(w.range(), MAX_TRACE);
        Vec3 end = start.add(dir.scale(range));

        HitResult block = level.clip(new ClipContext(start, end, ClipContext.Block.COLLIDER, ClipContext.Fluid.NONE, shooter));
        Vec3 blockEnd = block.getType() == HitResult.Type.MISS ? end : block.getLocation();

        AABB box = shooter.getBoundingBox().expandTowards(dir.scale(range)).inflate(1.0);
        EntityHitResult ehr = ProjectileUtil.getEntityHitResult(shooter, start, blockEnd, box,
                e -> !e.isSpectator() && e.isPickable() && e != shooter, range * range);
        if (ehr == null || !(ehr.getEntity() instanceof LivingEntity target)) {
            if (block.getType() != HitResult.Type.MISS) {
                level.sendParticles(ParticleTypes.SMOKE, block.getLocation().x, block.getLocation().y, block.getLocation().z, 2, 0.02, 0.02, 0.02, 0.0);
            }
            return;
        }

        Vec3 hitPos = ehr.getLocation();
        boolean head = isHeadshot(target, hitPos);
        double distance = start.distanceTo(hitPos);
        double wd = damage(w, head, ammo) / pellets;
        float mc = Balance.get().toMinecraftDamage(wd);

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
    }

    /** Head = upper part of the hitbox around the eye line. */
    public static boolean isHeadshot(LivingEntity target, Vec3 hit) {
        return hit.y >= target.getEyeY() - 0.25;
    }

    /** Random direction inside a cone; {@code spreadValue} is the sources' "accuracy" figure (lower is better). */
    static Vec3 withSpread(Vec3 dir, double spreadValue, net.minecraft.util.RandomSource rnd) {
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
