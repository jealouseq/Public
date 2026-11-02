package com.wardogs.match;

import net.minecraft.core.HolderLookup;
import net.minecraft.nbt.CompoundTag;
import net.minecraft.server.level.ServerLevel;
import net.minecraft.world.level.saveddata.SavedData;

/** Persistent state of the single match running in the overworld. */
public final class MatchData extends SavedData {
    private static final String NAME = "wardogs_match";

    /** Dimension the match runs in (the overworld unless started elsewhere, e.g. wardogs:bakurani). */
    public String dim = "minecraft:overworld";
    public boolean active;
    public double zoneX, zoneZ, zoneRadius = 50;
    public double hotX, hotZ, hotRadius = 15;
    public final int[] scores = new int[Faction.values().length];
    /** Ticks until the next score award / money pay-out / Hot Zone relocation. */
    public int scoreTimer, payTimer, hotTimer;

    public static MatchData get(ServerLevel overworld) {
        // One match per server, always stored with the overworld no matter which level asks.
        return overworld.getServer().overworld().getDataStorage().computeIfAbsent(new SavedData.Factory<>(MatchData::new, MatchData::load), NAME);
    }

    private static MatchData load(CompoundTag t, HolderLookup.Provider provider) {
        MatchData d = new MatchData();
        d.active = t.getBoolean("active");
        d.dim = t.contains("dim") ? t.getString("dim") : "minecraft:overworld";
        d.zoneX = t.getDouble("zx");
        d.zoneZ = t.getDouble("zz");
        d.zoneRadius = t.getDouble("zr");
        d.hotX = t.getDouble("hx");
        d.hotZ = t.getDouble("hz");
        d.hotRadius = t.getDouble("hr");
        int[] s = t.getIntArray("scores");
        System.arraycopy(s, 0, d.scores, 0, Math.min(s.length, d.scores.length));
        d.scoreTimer = t.getInt("st");
        d.payTimer = t.getInt("pt");
        d.hotTimer = t.getInt("ht");
        return d;
    }

    @Override
    public CompoundTag save(CompoundTag t, HolderLookup.Provider provider) {
        t.putBoolean("active", active);
        t.putString("dim", dim);
        t.putDouble("zx", zoneX);
        t.putDouble("zz", zoneZ);
        t.putDouble("zr", zoneRadius);
        t.putDouble("hx", hotX);
        t.putDouble("hz", hotZ);
        t.putDouble("hr", hotRadius);
        t.putIntArray("scores", scores);
        t.putInt("st", scoreTimer);
        t.putInt("pt", payTimer);
        t.putInt("ht", hotTimer);
        return t;
    }

    /** True if this level is the one the match runs in. */
    public boolean isHere(ServerLevel level) {
        return dim.equals(level.dimension().location().toString());
    }

    public boolean inControl(double x, double z) {
        return ZoneLogic.inCircle(x, z, zoneX, zoneZ, zoneRadius);
    }

    public boolean inHot(double x, double z) {
        return ZoneLogic.inCircle(x, z, hotX, hotZ, hotRadius);
    }
}
