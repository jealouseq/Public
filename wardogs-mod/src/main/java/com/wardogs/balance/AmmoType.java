package com.wardogs.balance;

/** Ammo type. Flesh multipliers are measured; armor multipliers are estimates. */
public enum AmmoType {
    FMJ, HP, AP;

    public static AmmoType byOrdinal(int i) {
        AmmoType[] v = values();
        return v[Math.floorMod(i, v.length)];
    }
}
