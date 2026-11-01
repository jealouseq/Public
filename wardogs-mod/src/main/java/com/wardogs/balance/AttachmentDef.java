package com.wardogs.balance;

import com.google.gson.JsonObject;

import java.util.ArrayList;
import java.util.List;

/** A weapon attachment. Levels are from sources; the effect multipliers are estimates. */
public record AttachmentDef(String id, String name, String slot, int price, String unlockRole, int unlockLevel, long unlockCost,
                            List<String> categories, double magMult, double spreadMult, double bipodMult, double zoom) {
    public static AttachmentDef parse(String id, JsonObject o) {
        List<String> cats = new ArrayList<>();
        o.getAsJsonArray("categories").forEach(e -> cats.add(e.getAsString()));
        return new AttachmentDef(id, o.get("name").getAsString(), o.get("slot").getAsString(), o.get("price").getAsInt(),
                o.get("role").getAsString(), o.get("level").getAsInt(), o.get("cost").getAsLong(), List.copyOf(cats),
                o.has("mag_mult") ? o.get("mag_mult").getAsDouble() : 1.0,
                o.has("spread_mult") ? o.get("spread_mult").getAsDouble() : 1.0,
                o.has("bipod_mult") ? o.get("bipod_mult").getAsDouble() : 1.0,
                o.has("zoom") ? o.get("zoom").getAsDouble() : 1.0);
    }

    public boolean fits(WeaponDef w) {
        return categories.contains(w.category());
    }
}
