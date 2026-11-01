package com.wardogs.balance;

import com.google.gson.JsonObject;

/** Projectile behaviour of a launcher; its price, magazine and unlock live in the weapon entry with the same id. */
public record LauncherDef(String id, Kind kind, double speed, double gravity, double blast, double vehicleMult, boolean homing, double damage) {
    public enum Kind { AT, GRENADE, AA }

    public static LauncherDef parse(String id, JsonObject o) {
        return new LauncherDef(id, Kind.valueOf(o.get("kind").getAsString().toUpperCase()), o.get("speed").getAsDouble(),
                o.get("gravity").getAsDouble(), o.get("blast").getAsDouble(), o.get("vehicle_mult").getAsDouble(),
                o.has("homing") && o.get("homing").getAsBoolean(), o.has("damage") ? o.get("damage").getAsDouble() : -1.0);
    }
}
