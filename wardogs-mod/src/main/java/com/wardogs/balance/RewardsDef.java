package com.wardogs.balance;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;

/** Money-reward and match-flow balance. */
public record RewardsDef(
        long startingCash,
        double killBase,
        double killHeadshot,
        double controlZoneMult,
        double hotZoneMult,
        double[][] distanceMult,
        double streakBonus,
        double streakCap,
        double revengeMult,
        double repeatPenalty,
        int repeatSteps,
        double teamkillCostMult,
        int winScore,
        int scoreSeconds,
        long zoneEnter,
        int zoneTickSeconds,
        long zoneControlTick,
        long zoneHotTick,
        long hotBonusStep,
        long hotBonusCap,
        long[] placeBonus,
        long[] reviveBy,
        long[] defibBy,
        double reviveRepeatPenalty,
        java.util.Map<String, Double> killBaseByClass,
        double shooterWeight,
        double targetWeight,
        double shutdownMult,
        int streakKillsCap,
        double revivedKeep,
        long roleXp) {

    private static java.util.Map<String, Double> bases(JsonObject o) {
        java.util.Map<String, Double> m = new java.util.HashMap<>();
        o.entrySet().forEach(e -> m.put(e.getKey(), e.getValue().getAsDouble()));
        return java.util.Map.copyOf(m);
    }

    public static RewardsDef parse(JsonObject o) {
        JsonObject k = o.getAsJsonObject("kill");
        JsonObject z = o.getAsJsonObject("zone");
        JsonArray d = k.getAsJsonArray("distance_mult");
        double[][] tiers = new double[d.size()][2];
        for (int i = 0; i < d.size(); i++) {
            JsonArray t = d.get(i).getAsJsonArray();
            tiers[i][0] = t.get(0).getAsDouble();
            tiers[i][1] = t.get(1).getAsDouble();
        }
        JsonArray p = o.getAsJsonObject("round_end").getAsJsonArray("place");
        long[] place = new long[p.size()];
        for (int i = 0; i < p.size(); i++) {
            place[i] = p.get(i).getAsJsonArray().get(0).getAsLong();
        }
        JsonObject rv = o.getAsJsonObject("revive");
        long[] revive = {rv.get("open").getAsLong(), rv.get("control").getAsLong(), rv.get("hot").getAsLong()};
        long[] defib = {rv.get("defib_open").getAsLong(), rv.get("defib_control").getAsLong(), rv.get("defib_hot").getAsLong()};
        return new RewardsDef(
                o.get("starting_cash").getAsLong(),
                k.get("base").getAsDouble(),
                k.get("headshot").getAsDouble(),
                k.get("control_zone_mult").getAsDouble(),
                k.get("hot_zone_mult").getAsDouble(),
                tiers,
                k.get("streak_bonus").getAsDouble(),
                k.get("streak_cap").getAsDouble(),
                k.get("revenge_mult").getAsDouble(),
                k.get("repeat_victim_penalty").getAsDouble(),
                k.get("repeat_victim_steps").getAsInt(),
                k.get("teamkill_cost_mult").getAsDouble(),
                o.get("win_score").getAsInt(),
                o.get("zone_tick_score_seconds").getAsInt(),
                z.get("enter").getAsLong(),
                z.get("tick_seconds").getAsInt(),
                z.get("control_tick").getAsLong(),
                z.get("hot_tick").getAsLong(),
                z.get("hot_consecutive_bonus").getAsLong(),
                z.get("hot_consecutive_cap").getAsLong(),
                place, revive, defib, rv.get("repeat_penalty").getAsDouble(),
                bases(k.getAsJsonObject("base_by_class")),
                k.get("shooter_weight").getAsDouble(), k.get("target_weight").getAsDouble(), k.get("shutdown_mult").getAsDouble(),
                k.get("streak_kills_cap").getAsInt(), k.get("revived_victim_keep").getAsDouble(), k.get("role_xp").getAsLong());
    }
}
