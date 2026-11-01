package com.wardogs.balance;

import com.google.gson.JsonObject;

/**
 * A buildable. {@code hp} and the hits-to-destroy are from game-file data; a C4/IED/mine hit deals hp/hits.
 * Size and supply cost are estimates.
 */
public record StructureDef(String id, Tier tier, double hp, double w, double h, double d, int cost,
                           int hitsC4, int hitsIed, int hitsMine, String block, boolean slows) {
    public enum Tier { SMALL, MEDIUM, LARGE, FOB }

    /** Damage one hit of the given explosive deals to this structure. */
    public double hitDamage(String explosiveId, Balance b) {
        return switch (explosiveId) {
            case "c4" -> hp / hitsC4;
            case "ied" -> hp / hitsIed;
            case "at_mine" -> hp / hitsMine;
            case "claymore" -> b.claymoreVsStructure();
            case "m67_frag" -> b.fragVsStructure();
            default -> 0.0;
        };
    }

    public static StructureDef parse(String id, JsonObject o) {
        JsonObject hits = o.getAsJsonObject("hits");
        return new StructureDef(id, Tier.valueOf(o.get("tier").getAsString().toUpperCase()),
                o.get("hp").getAsDouble(), o.get("w").getAsDouble(), o.get("h").getAsDouble(), o.get("d").getAsDouble(),
                o.get("cost").getAsInt(), hits.get("c4").getAsInt(), hits.get("ied").getAsInt(), hits.get("at_mine").getAsInt(),
                o.get("block").getAsString(), o.has("slows") && o.get("slows").getAsBoolean());
    }
}
