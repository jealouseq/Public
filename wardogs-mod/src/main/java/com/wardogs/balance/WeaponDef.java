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
        Set<String> estimated,
        double[][] falloff,
        double velocity) {

    /** Constructor for synthetic weapons (vehicle guns, emplacements) with no falloff. */
    public WeaponDef(String id, String name, String category, String caliber, String role, FireMode mode, double chest, double head,
                     double rpm, int magazine, double range, int reloadTicks, double spread, int pellets, int price, int unlockLevel,
                     long unlockCost, Set<String> estimated) {
        this(id, name, category, caliber, role, mode, chest, head, rpm, magazine, range, reloadTicks, spread, pellets, price,
                unlockLevel, unlockCost, estimated, new double[][]{{0, 1}}, 0);
    }

    /** Damage multiplier at {@code distance} metres (linear between the curve's points, flat beyond the last). */
    public double falloffMult(double distance) {
        double[][] f = falloff;
        if (f == null || f.length == 0 || distance <= f[0][0]) {
            return f == null || f.length == 0 ? 1.0 : f[0][1];
        }
        for (int i = 1; i < f.length; i++) {
            if (distance <= f[i][0]) {
                double t = (distance - f[i - 1][0]) / (f[i][0] - f[i - 1][0]);
                return f[i - 1][1] + t * (f[i][1] - f[i - 1][1]);
            }
        }
        return f[f.length - 1][1];
    }


    public enum FireMode { AUTO, BURST, SEMI, BOLT, PUMP, BREAK, DRAW }

    /** Server ticks between two shots (can be fractional, shots are scheduled by game time). */
    public double shotIntervalTicks() {
        return 1200.0 / rpm;
    }

    public boolean holdToFire() {
        return mode == FireMode.AUTO || mode == FireMode.BURST;
    }

    private static double[][] parseFalloff(JsonObject o) {
        if (!o.has("falloff")) {
            return new double[][]{{0, 1}};
        }
        JsonArray a = o.getAsJsonArray("falloff");
        double[][] f = new double[a.size()][2];
        for (int i = 0; i < a.size(); i++) {
            f[i][0] = a.get(i).getAsJsonArray().get(0).getAsDouble();
            f[i][1] = a.get(i).getAsJsonArray().get(1).getAsDouble();
        }
        return f;
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
                Set.copyOf(est),
                parseFalloff(o),
                o.has("velocity") ? o.get("velocity").getAsDouble() : 0.0);
    }
}
