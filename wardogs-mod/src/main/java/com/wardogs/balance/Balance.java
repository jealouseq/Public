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
    private final Map<String, MedicalDef> medical;
    private final int bleedoutTicks;
    private final double reviveRange;
    private final double downedHp;
    private final Map<String, ExplosiveDef> explosives;
    private final ProgressionDef progression;
    private final Map<String, StructureDef> structures;
    private final Map<String, BuildingItemDef> buildingItems;
    private final Map<String, VehicleDef> vehicles;
    private final double[] vehicleTuning; // fuelPerBlock, fuelPerSupply, repairFraction, groundPay, airPay
    private final double[] buildTuning; // fobRadius, claymoreVsStructure, fragVsStructure, barbedWireDps

    private Balance(Map<String, WeaponDef> weapons, RewardsDef rewards, double playerHealth,
                    List<ArmorDef> armor, double[][] ammoMult,
                    Map<String, MedicalDef> medical, int bleedoutTicks, double reviveRange, double downedHp,
                    Map<String, ExplosiveDef> explosives, ProgressionDef progression,
                    Map<String, StructureDef> structures, Map<String, BuildingItemDef> buildingItems, double[] buildTuning,
                    Map<String, VehicleDef> vehicles, double[] vehicleTuning) {
        this.weapons = weapons;
        this.rewards = rewards;
        this.playerHealth = playerHealth;
        this.armor = armor;
        this.ammoMult = ammoMult;
        this.medical = medical;
        this.bleedoutTicks = bleedoutTicks;
        this.reviveRange = reviveRange;
        this.downedHp = downedHp;
        this.explosives = explosives;
        this.progression = progression;
        this.structures = structures;
        this.buildingItems = buildingItems;
        this.buildTuning = buildTuning;
        this.vehicles = vehicles;
        this.vehicleTuning = vehicleTuning;
    }

    public MedicalDef medical(String id) {
        return medical.get(id);
    }

    public java.util.Collection<MedicalDef> medicalItems() {
        return medical.values();
    }

    /** Ticks a downed player lasts before bleeding out (estimate). */
    public int bleedoutTicks() {
        return bleedoutTicks;
    }

    public VehicleDef vehicle(String id) {
        return vehicles.get(id);
    }

    public java.util.Collection<VehicleDef> vehicles() {
        return vehicles.values();
    }

    public double fuelPerBlock() {
        return vehicleTuning[0];
    }

    public double fuelPerSupply() {
        return vehicleTuning[1];
    }

    public double repairFraction() {
        return vehicleTuning[2];
    }

    public long transportPay(boolean air) {
        return (long) vehicleTuning[air ? 4 : 3];
    }

    public StructureDef structure(String id) {
        return structures.get(id);
    }

    public java.util.Collection<StructureDef> structures() {
        return structures.values();
    }

    public BuildingItemDef buildingItem(String id) {
        return buildingItems.get(id);
    }

    public java.util.Collection<BuildingItemDef> buildingItems() {
        return buildingItems.values();
    }

    /** Build radius of a Forward Operating Base, in blocks (estimate). */
    public double fobRadius() {
        return buildTuning[0];
    }

    public double claymoreVsStructure() {
        return buildTuning[1];
    }

    public double fragVsStructure() {
        return buildTuning[2];
    }

    public double barbedWireDps() {
        return buildTuning[3];
    }

    public ProgressionDef progression() {
        return progression;
    }

    public ExplosiveDef explosive(String id) {
        return explosives.get(id);
    }

    public java.util.Collection<ExplosiveDef> explosiveItems() {
        return explosives.values();
    }

    /** Health a player has while downed (estimate). */
    public double downedHp() {
        return downedHp;
    }

    public double reviveRange() {
        return reviveRange;
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

    /**
     * Damage to apply to a living target. Health is expressed relative to a soldier's 100 HP:
     * a 100-HP player takes the WARDOGS figure 1:1, a 20-HP zombie takes a fifth of it.
     */
    public float damageTo(net.minecraft.world.entity.LivingEntity target, double wardogsDamage) {
        return (float) (wardogsDamage * target.getMaxHealth() / playerHealth);
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
        for (String name : new String[]{"weapons", "rewards", "armor", "ammo", "medical", "explosives", "progression", "structures", "vehicles"}) {
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
        JsonObject med = files.get("medical");
        Map<String, MedicalDef> medical = new LinkedHashMap<>();
        for (Map.Entry<String, JsonElement> e : med.getAsJsonObject("items").entrySet()) {
            medical.put(e.getKey(), MedicalDef.parse(e.getKey(), e.getValue().getAsJsonObject()));
        }
        Map<String, ExplosiveDef> explosives = new LinkedHashMap<>();
        for (Map.Entry<String, JsonElement> e : files.get("explosives").getAsJsonObject("explosives").entrySet()) {
            explosives.put(e.getKey(), ExplosiveDef.parse(e.getKey(), e.getValue().getAsJsonObject()));
        }
        return new Balance(Map.copyOf(weapons), rewards, hp, List.copyOf(armor), ammo, Map.copyOf(medical),
                med.get("bleedout_ticks").getAsInt(), med.get("revive_range").getAsDouble(), med.get("downed_hp").getAsDouble(), Map.copyOf(explosives),
                ProgressionDef.parse(files.get("progression")), structures(files.get("structures")), buildingItems(files.get("structures")),
                new double[]{files.get("structures").get("fob_radius").getAsDouble(), files.get("structures").get("claymore_vs_structure").getAsDouble(),
                        files.get("structures").get("frag_vs_structure").getAsDouble(), files.get("structures").get("barbed_wire_dps").getAsDouble()},
                vehicles(files.get("vehicles")),
                new double[]{files.get("vehicles").get("fuel_per_block").getAsDouble(), files.get("vehicles").get("fuel_per_supply").getAsDouble(),
                        files.get("vehicles").get("repair_fraction").getAsDouble(),
                        files.get("vehicles").getAsJsonObject("transport").get("ground_per_passenger").getAsDouble(),
                        files.get("vehicles").getAsJsonObject("transport").get("air_per_passenger").getAsDouble()});
    }

    private static Map<String, VehicleDef> vehicles(JsonObject o) {
        Map<String, VehicleDef> m = new LinkedHashMap<>();
        for (Map.Entry<String, JsonElement> e : o.getAsJsonObject("vehicles").entrySet()) {
            m.put(e.getKey(), VehicleDef.parse(e.getKey(), e.getValue().getAsJsonObject()));
        }
        return java.util.Collections.unmodifiableMap(m);
    }

    private static Map<String, StructureDef> structures(JsonObject o) {
        Map<String, StructureDef> m = new LinkedHashMap<>();
        for (Map.Entry<String, JsonElement> e : o.getAsJsonObject("structures").entrySet()) {
            m.put(e.getKey(), StructureDef.parse(e.getKey(), e.getValue().getAsJsonObject()));
        }
        return java.util.Collections.unmodifiableMap(m);
    }

    private static Map<String, BuildingItemDef> buildingItems(JsonObject o) {
        Map<String, BuildingItemDef> m = new LinkedHashMap<>();
        for (Map.Entry<String, JsonElement> e : o.getAsJsonObject("items").entrySet()) {
            m.put(e.getKey(), BuildingItemDef.parse(e.getKey(), e.getValue().getAsJsonObject()));
        }
        return java.util.Collections.unmodifiableMap(m);
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
            for (String name : new String[]{"weapons", "rewards", "armor", "ammo", "medical", "explosives", "progression", "structures", "vehicles"}) {
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
