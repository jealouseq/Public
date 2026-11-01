package com.wardogs.balance;

import com.google.gson.JsonObject;

/** A drivable vehicle. Prices/unlocks are from sources; hp, speed, seats, fuel and multipliers are estimates. */
public record VehicleDef(String id, String name, String category, boolean flying, double hp, double speed, int seats,
                         double fuel, double w, double h, double d, int price, String unlockRole, int unlockLevel,
                         long unlockCost, double smallArms, double explosive, double antiTank, long killReward,
                         String block, Gun gun) {

    /** A vehicle-mounted weapon; {@code blast > 0} makes it an explosive shell. */
    public record Gun(double damage, double rpm, double range, double spread, double blast) {
        public double intervalTicks() {
            return 1200.0 / rpm;
        }
    }

    public static VehicleDef parse(String id, JsonObject o) {
        Gun gun = null;
        if (o.has("gun")) {
            JsonObject g = o.getAsJsonObject("gun");
            gun = new Gun(g.get("damage").getAsDouble(), g.get("rpm").getAsDouble(), g.get("range").getAsDouble(),
                    g.get("spread").getAsDouble(), g.get("blast").getAsDouble());
        }
        return new VehicleDef(id, o.get("name").getAsString(), o.get("category").getAsString(), o.get("flying").getAsBoolean(),
                o.get("hp").getAsDouble(), o.get("speed").getAsDouble(), o.get("seats").getAsInt(), o.get("fuel").getAsDouble(),
                o.get("w").getAsDouble(), o.get("h").getAsDouble(), o.get("d").getAsDouble(), o.get("price").getAsInt(),
                o.get("role").getAsString(), o.get("level").getAsInt(), o.get("cost").getAsLong(),
                o.get("small_arms").getAsDouble(), o.get("explosive").getAsDouble(), o.get("anti_tank").getAsDouble(),
                o.get("kill_reward").getAsLong(), o.get("block").getAsString(), gun);
    }
}
