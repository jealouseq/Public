package com.wardogs.balance;

import com.google.gson.JsonObject;

/** A helmet or body armor tier. {@code reduction} (fraction of damage absorbed) is an estimate: sources publish none. */
public record ArmorDef(String id, Slot slot, int level, int price, double weight, int unlockLevel, double reduction) {
    public enum Slot { HELMET, VEST }

    public static ArmorDef parse(Slot slot, String levelKey, JsonObject o) {
        String prefix = slot == Slot.HELMET ? "helmet" : "vest";
        int level = Integer.parseInt(levelKey);
        return new ArmorDef(prefix + "_l" + level, slot, level,
                o.get("price").getAsInt(), o.get("weight").getAsDouble(),
                o.get("unlock_level").getAsInt(), o.get("reduction").getAsDouble());
    }
}
