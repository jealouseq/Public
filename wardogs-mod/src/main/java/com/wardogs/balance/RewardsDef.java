package com.wardogs.balance;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;

/** Money-reward balance (kill rewards and starting cash). */
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
        int winScore) {

    public static RewardsDef parse(JsonObject o) {
        JsonObject k = o.getAsJsonObject("kill");
        JsonArray d = k.getAsJsonArray("distance_mult");
        double[][] tiers = new double[d.size()][2];
        for (int i = 0; i < d.size(); i++) {
            JsonArray t = d.get(i).getAsJsonArray();
            tiers[i][0] = t.get(0).getAsDouble();
            tiers[i][1] = t.get(1).getAsDouble();
        }
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
                o.get("win_score").getAsInt());
    }
}
