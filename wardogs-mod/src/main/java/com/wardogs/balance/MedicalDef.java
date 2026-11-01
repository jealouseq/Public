package com.wardogs.balance;

import com.google.gson.JsonObject;

/** A medical item: heals the user, revives a downed teammate, or recharges a defibrillator. */
public record MedicalDef(String id, Kind kind, int price, int ticks, double hp, int charges,
                         String unlockRole, int unlockLevel, long unlockCost) {
    public enum Kind { HEAL, REVIVE, BATTERY }

    public static MedicalDef parse(String id, JsonObject o) {
        return new MedicalDef(id, Kind.valueOf(o.get("kind").getAsString().toUpperCase()),
                o.get("price").getAsInt(),
                o.has("ticks") ? o.get("ticks").getAsInt() : 0,
                o.has("hp") ? o.get("hp").getAsDouble() : 0,
                o.has("charges") ? o.get("charges").getAsInt() : 0,
                o.get("unlock_role").getAsString(), o.get("unlock_level").getAsInt(), o.get("unlock_cost").getAsLong());
    }
}
