package com.wardogs.world;

import com.google.gson.Gson;
import com.google.gson.JsonObject;
import com.wardogs.WardogsMod;
import net.minecraft.core.registries.Registries;
import net.minecraft.resources.ResourceKey;
import net.minecraft.resources.ResourceLocation;
import net.minecraft.world.level.Level;
import net.neoforged.fml.loading.FMLPaths;

import java.io.IOException;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;

/** Loads the Bakurani terrain and sites once, from the folder named by -Dwardogs.mapdir or config/wardogs/bakurani. */
public final class BakuraniWorld {
    public static final ResourceKey<Level> DIMENSION =
            ResourceKey.create(Registries.DIMENSION, ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "bakurani"));

    /** Side of the playable square in metres. */
    public static final double WORLD_SIZE = 16320;

    public record Site(String name, double x, double z) {}

    public record Zone(String name, double x, double z, double radius, int weight) {}

    private static volatile BakuraniTerrain terrain;
    private static volatile boolean loaded;
    private static volatile Sites sites;

    public record Sites(Map<String, Site> bases, List<Site> spawns, List<Site> vendors, List<Site> boards, List<Site> towers,
                        List<Zone> zones, double verticalScale, int yOffset) {}

    private BakuraniWorld() {}

    public static synchronized Sites sites() {
        if (sites == null) {
            try (InputStream in = BakuraniWorld.class.getResourceAsStream("/data/wardogs/bakurani/sites.json")) {
                JsonObject o = new Gson().fromJson(new InputStreamReader(in, StandardCharsets.UTF_8), JsonObject.class);
                Map<String, Site> bases = new java.util.LinkedHashMap<>();
                o.getAsJsonObject("bases").entrySet().forEach(e -> bases.put(e.getKey(),
                        new Site(e.getKey(), e.getValue().getAsJsonObject().get("x").getAsDouble(), e.getValue().getAsJsonObject().get("z").getAsDouble())));
                List<Zone> zones = new ArrayList<>();
                o.getAsJsonArray("zones").forEach(e -> {
                    JsonObject z = e.getAsJsonObject();
                    zones.add(new Zone(z.get("name").getAsString(), z.get("x").getAsDouble(), z.get("z").getAsDouble(),
                            z.get("radius").getAsDouble(), z.get("weight").getAsInt()));
                });
                JsonObject w = o.getAsJsonObject("world");
                sites = new Sites(Map.copyOf(bases), list(o, "spawns"), list(o, "vendors"), list(o, "boards"), list(o, "towers"),
                        List.copyOf(zones), w.get("vertical_scale").getAsDouble(), w.get("y_offset").getAsInt());
            } catch (IOException e) {
                throw new IllegalStateException("Cannot read the Bakurani site list", e);
            }
        }
        return sites;
    }

    private static List<Site> list(JsonObject o, String key) {
        List<Site> out = new ArrayList<>();
        o.getAsJsonArray(key).forEach(e -> {
            JsonObject s = e.getAsJsonObject();
            out.add(new Site(s.get("name").getAsString(), s.get("x").getAsDouble(), s.get("z").getAsDouble()));
        });
        return List.copyOf(out);
    }

    public static Path dataDir() {
        String prop = System.getProperty("wardogs.mapdir");
        return prop != null ? Path.of(prop) : FMLPaths.CONFIGDIR.get().resolve("wardogs").resolve("bakurani");
    }

    /** The terrain, or {@code null} when the elevation data has not been installed (the generator then makes a flat world). */
    public static synchronized BakuraniTerrain terrain() {
        if (!loaded) {
            loaded = true;
            try {
                terrain = BakuraniTerrain.load(dataDir(), sites().verticalScale(), sites().yOffset());
                if (terrain == null) {
                    WardogsMod.LOGGER.warn("Bakurani elevation data not found in {} - generating a flat world. See research/tools/map_convert.py.", dataDir());
                } else {
                    WardogsMod.LOGGER.info("Bakurani terrain loaded from {}", dataDir());
                }
            } catch (IOException e) {
                WardogsMod.LOGGER.error("Cannot read Bakurani terrain", e);
            }
        }
        return terrain;
    }

    /** y of the walkable surface at a position (a flat plain at y=64 without data). */
    public static int surfaceY(double x, double z) {
        BakuraniTerrain t = terrain();
        return t == null ? 64 : t.surfaceY(x, z);
    }

    public static Site spawnOf(String faction) {
        String key = switch (faction) {
            case "lonestar" -> "Spawn - Alpha #1";
            case "valkyra" -> "Spawn - Bravo #3";
            default -> "Spawn - Charlie #2";
        };
        return sites().spawns().stream().filter(s -> s.name().equals(key)).findFirst().orElse(sites().bases().get(faction));
    }
}
