package com.wardogs.combat;

import net.minecraft.world.entity.LivingEntity;
import net.minecraft.world.phys.Vec3;

/** The 12 body zones of the damage model. Keys match the hit-zone table in balance/hitzones.json. */
public enum HitZone {
    HEAD("head"), NECK("neck"), TORSO_UPPER("torso_upper"), TORSO_MIDDLE("torso_middle"), TORSO_LOWER("torso_lower"),
    PELVIS("pelvis"), ARM_UPPER("arm_upper"), ARM_LOWER("arm_lower"), HAND("arm_hand"),
    LEG_THIGH("leg_thigh"), LEG_CALF("leg_calf"), FOOT("leg_foot");

    public final String key;

    HitZone(String key) {
        this.key = key;
    }

    public boolean isTorso() {
        return this == TORSO_UPPER || this == TORSO_MIDDLE || this == TORSO_LOWER;
    }

    /**
     * Zone from the hit's height as a fraction of the body (0 = feet, 1 = top of head) and its sideways offset
     * from the body's centre line as a fraction of the body height. Arms hang at the sides, so a hit that far out
     * between pelvis and shoulders lands on the arm.
     */
    public static HitZone fromFractions(double height, double side) {
        if (side > 0.105 && height >= 0.42 && height < 0.82) {
            return height >= 0.62 ? ARM_UPPER : height >= 0.50 ? ARM_LOWER : HAND;
        }
        if (height >= 0.865) return HEAD;
        if (height >= 0.82) return NECK;
        if (height >= 0.68) return TORSO_UPPER;
        if (height >= 0.58) return TORSO_MIDDLE;
        if (height >= 0.50) return TORSO_LOWER;
        if (height >= 0.42) return PELVIS;
        if (height >= 0.23) return LEG_THIGH;
        if (height >= 0.06) return LEG_CALF;
        return FOOT;
    }

    public static HitZone at(LivingEntity target, Vec3 hit) {
        double h = Math.max(0.1, target.getBbHeight());
        double f = (hit.y - target.getY()) / h;
        double yaw = Math.toRadians(target.yBodyRot);
        Vec3 right = new Vec3(Math.cos(yaw), 0, Math.sin(yaw));
        Vec3 off = hit.subtract(target.position());
        double side = Math.abs(off.x * right.x + off.z * right.z) / h;
        return fromFractions(f, side);
    }
}
