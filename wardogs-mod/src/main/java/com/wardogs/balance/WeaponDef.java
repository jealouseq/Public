package com.wardogs.balance;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;

import java.util.HashSet;
import java.util.Set;

/** One weapon's balance entry. Values marked in {@code estimated} are placeholders, not measured. */
public record WeaponDef(
        String id,
        String name,
        String category,
        String caliber,
        String role,
        FireMode mode,
        double chest,
        double head,
        double rpm,
        int magazine,
        double range,
        int reloadTicks,
        double spread,
        int pellets,
        int price,
        int unlockLevel,
        long unlockCost,
        Set<String> estimated) {

    public enum FireMode { AUTO, BURST, SEMI, BOLT, PUMP, BREAK, DRAW }

    /** Server ticks between two shots (can be fractional, shots are scheduled by game time). */
    public double shotIntervalTicks() {
        return 1200.0 / rpm;
    }

    public boolean holdToFire() {
        return mode == FireMode.AUTO || mode == FireMode.BURST;
    }

    public static WeaponDef parse(String id, JsonObject o) {
        Set<String> est = new HashSet<>();
        if (o.has("estimated")) {
            JsonArray a = o.getAsJsonArray("estimated");
            a.forEach(e -> est.add(e.getAsString()));
        }
        return new WeaponDef(
                id,
                o.get("name").getAsString(),
                o.get("category").getAsString(),
                o.get("caliber").getAsString(),
                o.get("role").getAsString(),
                FireMode.valueOf(o.get("mode").getAsString().toUpperCase()),
                o.get("chest").getAsDouble(),
                o.get("head").getAsDouble(),
                o.get("rpm").getAsDouble(),
                o.get("magazine").getAsInt(),
                o.get("range").getAsDouble(),
                o.get("reload_ticks").getAsInt(),
                o.get("spread").getAsDouble(),
                o.has("pellets") ? o.get("pellets").getAsInt() : 1,
                o.get("price").getAsInt(),
                o.get("unlock_level").getAsInt(),
                o.get("unlock_cost").getAsLong(),
                Set.copyOf(est));
    }
}
