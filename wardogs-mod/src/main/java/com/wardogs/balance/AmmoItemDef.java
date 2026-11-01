package com.wardogs.balance;

import com.google.gson.JsonObject;

/** A box of special ammunition that sets the type a weapon fires. */
public record AmmoItemDef(String id, String name, int ammoType, int price, String unlockRole, int unlockLevel, long unlockCost) {
    public static AmmoItemDef parse(String id, JsonObject o) {
        return new AmmoItemDef(id, o.get("name").getAsString(), o.get("ammo_type").getAsInt(), o.get("price").getAsInt(),
                o.get("role").getAsString(), o.get("level").getAsInt(), o.get("cost").getAsLong());
    }
}
