package com.wardogs.balance;

import com.google.gson.JsonObject;

/** A building tool or supply: hammers, supply stacks and the FOB kit. */
public record BuildingItemDef(String id, int price, int stack, String unlockRole, int unlockLevel, long unlockCost,
                              StructureDef.Tier hammerTier, String supply, boolean fobKit, boolean wrench) {
    public static BuildingItemDef parse(String id, JsonObject o) {
        return new BuildingItemDef(id, o.get("price").getAsInt(), o.has("stack") ? o.get("stack").getAsInt() : 1,
                o.get("role").getAsString(), o.get("level").getAsInt(), o.get("cost").getAsLong(),
                o.has("tier") ? StructureDef.Tier.valueOf(o.get("tier").getAsString().toUpperCase()) : null,
                o.has("supply") ? o.get("supply").getAsString() : null,
                o.has("kit") && o.get("kit").getAsBoolean(),
                o.has("wrench") && o.get("wrench").getAsBoolean());
    }
}
