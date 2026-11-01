package com.wardogs.explosive;

import com.wardogs.balance.ExplosiveDef;
import com.wardogs.item.ExplosiveItem;
import net.minecraft.core.particles.DustParticleOptions;
import net.minecraft.core.particles.ParticleTypes;
import net.minecraft.server.level.ServerLevel;
import net.minecraft.world.entity.EntityType;
import net.minecraft.world.entity.LivingEntity;
import net.minecraft.world.entity.projectile.ThrowableItemProjectile;
import net.minecraft.world.item.Item;
import net.minecraft.world.level.Level;
import net.minecraft.world.phys.AABB;
import net.minecraft.world.phys.BlockHitResult;
import net.minecraft.world.phys.Vec3;
import org.joml.Vector3f;

/** A thrown frag grenade, molotov or smoke/signal canister. */
public class GrenadeEntity extends ThrowableItemProjectile {
    private int landedAt = -1;

    public GrenadeEntity(EntityType<? extends GrenadeEntity> type, Level level) {
        super(type, level);
    }

    public GrenadeEntity(Level level, LivingEntity thrower) {
        super(ExplosiveEntities.GRENADE.get(), thrower, level);
    }

    @Override
    protected Item getDefaultItem() {
        return net.minecraft.world.item.Items.SNOWBALL;
    }

    private ExplosiveDef def() {
        return getItem().getItem() instanceof ExplosiveItem e ? e.def() : null;
    }

    @Override
    protected void onHitBlock(BlockHitResult hit) {
        ExplosiveDef d = def();
        if (d == null) {
            return;
        }
        if (d.kind() == ExplosiveDef.Kind.FIRE && landedAt < 0) {
            land();
            return;
        }
        // Bounce off the surface and lose most of the speed; settle when slow.
        Vec3 v = getDeltaMovement();
        Vec3 n = Vec3.atLowerCornerOf(hit.getDirection().getNormal());
        Vec3 reflected = v.subtract(n.scale(2 * v.dot(n))).scale(0.45);
        if (reflected.lengthSqr() < 0.004) {
            land();
        } else {
            setDeltaMovement(reflected);
        }
    }

    private void land() {
        landedAt = tickCount;
        setDeltaMovement(Vec3.ZERO);
        setNoGravity(true);
    }

    @Override
    public void tick() {
        super.tick();
        ExplosiveDef d = def();
        if (d == null || !(level() instanceof ServerLevel sl)) {
            return;
        }
        switch (d.kind()) {
            case FRAG -> {
                if (tickCount >= d.fuse()) {
                    Blast.explode(sl, position(), d.radius(), d.damage(), getOwner(), d.id());
                    discard();
                }
            }
            case FIRE -> {
                if (landedAt >= 0) {
                    int t = tickCount - landedAt;
                    sl.sendParticles(ParticleTypes.FLAME, getX(), getY() + 0.2, getZ(), 6, d.radius() * 0.4, 0.1, d.radius() * 0.4, 0.01);
                    if (t % 10 == 0) {
                        for (LivingEntity e : sl.getEntitiesOfClass(LivingEntity.class, new AABB(position(), position()).inflate(d.radius(), 1.5, d.radius()), LivingEntity::isAlive)) {
                            if (e.position().distanceTo(position()) <= d.radius()) {
                                Blast.damage(sl, e, getOwner(), d.id(), d.dps() / 2.0, e.distanceTo(getOwner() == null ? e : getOwner()));
                                e.igniteForSeconds(2);
                            }
                        }
                    }
                    if (t >= d.duration()) {
                        discard();
                    }
                }
            }
            case SMOKE -> {
                if (tickCount >= d.fuse()) {
                    if (landedAt < 0) {
                        land();
                    }
                    int t = tickCount - d.fuse();
                    Vector3f col = colour(d.color());
                    float size = (float) (2.0 + d.radius() * 0.5);
                    sl.sendParticles(new DustParticleOptions(col, size), getX(), getY() + 1.0, getZ(), 8, d.radius() * 0.4, 0.9, d.radius() * 0.4, 0.0);
                    if (t >= d.duration()) {
                        discard();
                    }
                }
            }
            default -> discard();
        }
    }

    public static Vector3f colour(String name) {
        return switch (name) {
            case "black" -> new Vector3f(0.08f, 0.08f, 0.08f);
            case "yellow" -> new Vector3f(1f, 0.9f, 0.1f);
            case "orange" -> new Vector3f(1f, 0.5f, 0.05f);
            case "blue" -> new Vector3f(0.15f, 0.35f, 1f);
            case "red" -> new Vector3f(1f, 0.1f, 0.1f);
            case "purple" -> new Vector3f(0.6f, 0.2f, 0.9f);
            case "green" -> new Vector3f(0.1f, 0.85f, 0.2f);
            default -> new Vector3f(0.92f, 0.92f, 0.92f);
        };
    }
}
