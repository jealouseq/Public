package com.wardogs.balance;

import com.google.gson.JsonObject;

/** A grenade, mine, charge or smoke. Stats other than price/unlock are estimates (not published). */
public record ExplosiveDef(String id, Kind kind, int price, int fuse, double radius, double damage,
                           int duration, double dps, double range, double cone, String color,
                           String unlockRole, int unlockLevel, long unlockCost) {
    public enum Kind { FRAG, FIRE, C4, IED, DETONATOR, AT_MINE, CLAYMORE, SMOKE }

    public boolean throwable() {
        return kind == Kind.FRAG || kind == Kind.FIRE || kind == Kind.SMOKE;
    }

    public boolean placeable() {
        return kind == Kind.C4 || kind == Kind.IED || kind == Kind.AT_MINE || kind == Kind.CLAYMORE;
    }

    public static ExplosiveDef parse(String id, JsonObject o) {
        return new ExplosiveDef(id, Kind.valueOf(o.get("kind").getAsString().toUpperCase()),
                o.get("price").getAsInt(),
                o.has("fuse") ? o.get("fuse").getAsInt() : 0,
                o.has("radius") ? o.get("radius").getAsDouble() : 0,
                o.has("damage") ? o.get("damage").getAsDouble() : 0,
                o.has("duration") ? o.get("duration").getAsInt() : 0,
                o.has("dps") ? o.get("dps").getAsDouble() : 0,
                o.has("range") ? o.get("range").getAsDouble() : 0,
                o.has("cone") ? o.get("cone").getAsDouble() : 0,
                o.has("color") ? o.get("color").getAsString() : "white",
                o.get("role").getAsString(), o.get("level").getAsInt(), o.get("cost").getAsLong());
    }
}
