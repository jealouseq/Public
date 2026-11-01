package com.wardogs.item;

import com.wardogs.balance.AttachmentDef;
import com.wardogs.balance.Balance;
import com.wardogs.balance.WeaponDef;
import net.minecraft.world.item.ItemStack;

import java.util.ArrayList;
import java.util.List;

/** Effective weapon stats after attachments. Pure helpers over the list of attachment ids on the gun. */
public final class GunStats {
    private GunStats() {}

    public static List<String> attachments(ItemStack gun) {
        return gun.getOrDefault(ModComponents.ATTACHMENTS.get(), List.of());
    }

    private static List<AttachmentDef> defs(List<String> ids) {
        List<AttachmentDef> out = new ArrayList<>();
        for (String id : ids) {
            AttachmentDef d = Balance.get().attachment(id);
            if (d != null) {
                out.add(d);
            }
        }
        return out;
    }

    public static int magazine(WeaponDef w, List<String> ids) {
        double m = 1.0;
        for (AttachmentDef d : defs(ids)) {
            m *= d.magMult();
        }
        return Math.max(1, (int) Math.round(w.magazine() * m));
    }

    public static int magazine(WeaponDef w, ItemStack gun) {
        return magazine(w, attachments(gun));
    }

    /** Multiplier on the weapon's spread; a bipod only helps while the shooter is crouched (sneaking). */
    public static double spreadMult(List<String> ids, boolean crouched) {
        double m = 1.0;
        for (AttachmentDef d : defs(ids)) {
            m *= d.spreadMult();
            if (crouched) {
                m *= d.bipodMult();
            }
        }
        return m;
    }

    /** Field-of-view divisor while aiming; 1.0 means no magnification. */
    public static double zoom(List<String> ids) {
        double z = 1.0;
        for (AttachmentDef d : defs(ids)) {
            z = Math.max(z, d.zoom());
        }
        return z;
    }

    public enum AttachResult { OK, WRONG_WEAPON }

    /** Fits {@code att} to the gun, replacing whatever already sits in that slot. */
    public static AttachResult attach(ItemStack gun, WeaponDef w, AttachmentDef att) {
        if (!att.fits(w)) {
            return AttachResult.WRONG_WEAPON;
        }
        List<String> next = new ArrayList<>();
        for (String id : attachments(gun)) {
            AttachmentDef d = Balance.get().attachment(id);
            if (d != null && !d.slot().equals(att.slot())) {
                next.add(id);
            }
        }
        next.add(att.id());
        gun.set(ModComponents.ATTACHMENTS.get(), List.copyOf(next));
        int cap = magazine(w, next);
        if (GunItem.ammo(gun) > cap) {
            gun.set(ModComponents.AMMO.get(), cap);
        }
        return AttachResult.OK;
    }
}
