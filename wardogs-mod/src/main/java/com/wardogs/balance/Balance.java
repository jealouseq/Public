package com.wardogs.balance;

import com.google.gson.Gson;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.wardogs.WardogsMod;
import net.minecraft.resources.ResourceLocation;
import net.minecraft.server.packs.resources.ResourceManager;
import net.minecraft.server.packs.resources.SimpleJsonResourceReloadListener;
import net.minecraft.util.profiling.ProfilerFiller;

import java.io.IOException;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.nio.charset.StandardCharsets;
import java.util.Collection;
import java.util.ArrayList;
import java.util.List;
import java.util.LinkedHashMap;
import java.util.Map;

/**
 * Immutable balance snapshot. Loaded from the mod jar at startup (so items can be registered),
 * then replaced on every datapack reload by the files in {@code data/wardogs/balance/}: balance can be
 * edited with a datapack without touching code.
 */
public final class Balance {
    private static final Gson GSON = new Gson();
    private static volatile Balance current;

    private final Map<String, WeaponDef> weapons;
    private final RewardsDef rewards;
    private final double playerHealth;
    private final List<ArmorDef> armor;
    private final double[][] ammoMult; // [ammo ordinal] -> {flesh, armored}

    private Balance(Map<String, WeaponDef> weapons, RewardsDef rewards, double playerHealth,
                    List<ArmorDef> armor, double[][] ammoMult) {
        this.weapons = weapons;
        this.rewards = rewards;
        this.playerHealth = playerHealth;
        this.armor = armor;
        this.ammoMult = ammoMult;
    }

    public List<ArmorDef> armor() {
        return armor;
    }

    public ArmorDef armor(String id) {
        return armor.stream().filter(a -> a.id().equals(id)).findFirst().orElse(null);
    }

    /** Damage multiplier of an ammo type against flesh or against armored hit zones. */
    public double ammoMult(AmmoType type, boolean armored) {
        return ammoMult[type.ordinal()][armored ? 1 : 0];
    }

    public static Balance get() {
        Balance b = current;
        if (b == null) {
            b = bootstrap();
        }
        return b;
    }

    public WeaponDef weapon(String id) {
        return weapons.get(id);
    }

    public Collection<WeaponDef> weapons() {
        return weapons.values();
    }

    public RewardsDef rewards() {
        return rewards;
    }

    /** Assumed total health of a soldier in WARDOGS damage units (not published by the developer). */
    public double playerHealth() {
        return playerHealth;
    }

    /** Converts WARDOGS damage units to Minecraft health points (player has 20). */
    public float toMinecraftDamage(double wardogsDamage) {
        return (float) (wardogsDamage * 20.0 / playerHealth);
    }

    private static synchronized Balance bootstrap() {
        if (current != null) {
            return current;
        }
        Map<String, JsonObject> files = new LinkedHashMap<>();
        for (String name : new String[]{"weapons", "rewards", "armor", "ammo"}) {
            files.put(name, readClasspath("/data/wardogs/balance/" + name + ".json"));
        }
        current = build(files);
        return current;
    }

    private static JsonObject readClasspath(String path) {
        try (InputStream in = Balance.class.getResourceAsStream(path)) {
            if (in == null) {
                throw new IllegalStateException("Missing balance file " + path);
            }
            return GSON.fromJson(new InputStreamReader(in, StandardCharsets.UTF_8), JsonObject.class);
        } catch (IOException e) {
            throw new IllegalStateException("Cannot read " + path, e);
        }
    }

    static Balance build(Map<String, JsonObject> files) {
        Map<String, WeaponDef> weapons = new LinkedHashMap<>();
        JsonObject w = files.get("weapons").getAsJsonObject("weapons");
        for (Map.Entry<String, JsonElement> e : w.entrySet()) {
            weapons.put(e.getKey(), WeaponDef.parse(e.getKey(), e.getValue().getAsJsonObject()));
        }
        RewardsDef rewards = RewardsDef.parse(files.get("rewards"));
        JsonObject armorJson = files.get("armor");
        double hp = armorJson.get("player_health").getAsDouble();
        List<ArmorDef> armor = new ArrayList<>();
        for (var e : armorJson.getAsJsonObject("helmets").entrySet()) {
            armor.add(ArmorDef.parse(ArmorDef.Slot.HELMET, e.getKey(), e.getValue().getAsJsonObject()));
        }
        for (var e : armorJson.getAsJsonObject("vests").entrySet()) {
            armor.add(ArmorDef.parse(ArmorDef.Slot.VEST, e.getKey(), e.getValue().getAsJsonObject()));
        }
        double[][] ammo = new double[AmmoType.values().length][2];
        JsonObject ammoJson = files.get("ammo").getAsJsonObject("ammo");
        for (AmmoType t : AmmoType.values()) {
            JsonObject a = ammoJson.getAsJsonObject(t.name().toLowerCase());
            ammo[t.ordinal()][0] = a.get("flesh").getAsDouble();
            ammo[t.ordinal()][1] = a.get("armor").getAsDouble();
        }
        return new Balance(Map.copyOf(weapons), rewards, hp, List.copyOf(armor), ammo);
    }

    /** Datapack reload listener: replaces the snapshot when the balance files in the datapack change. */
    public static final class Reloader extends SimpleJsonResourceReloadListener {
        public Reloader() {
            super(GSON, "balance");
        }

        @Override
        protected void apply(Map<ResourceLocation, JsonElement> in, ResourceManager rm, ProfilerFiller profiler) {
            Map<String, JsonObject> files = new LinkedHashMap<>();
            // Start from the jar defaults, then overlay anything a datapack provides.
            for (String name : new String[]{"weapons", "rewards", "armor", "ammo"}) {
                JsonElement over = in.get(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, name));
                files.put(name, over != null ? over.getAsJsonObject() : readClasspath("/data/wardogs/balance/" + name + ".json"));
            }
            try {
                current = build(files);
                WardogsMod.LOGGER.info("Balance reloaded: {} weapons", current.weapons.size());
            } catch (RuntimeException ex) {
                WardogsMod.LOGGER.error("Balance reload failed, keeping previous snapshot", ex);
            }
        }
    }
}
