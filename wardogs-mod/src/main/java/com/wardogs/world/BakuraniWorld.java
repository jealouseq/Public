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

    public record Landmark(String name, String type, double x, double z) {}

    public record Zone(String name, double x, double z, double radius, int weight) {}

    private static volatile BakuraniTerrain terrain;
    private static volatile boolean loaded;
    private static volatile Sites sites;

    public record Sites(Map<String, Site> bases, List<Site> spawns, List<Site> vendors, List<Site> boards, List<Site> towers,
                        List<Zone> zones, List<Site> loot, List<Landmark> landmarks, double verticalScale, int yOffset) {}

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
                        List.copyOf(zones), list(o, "loot"), landmarks(o), w.get("vertical_scale").getAsDouble(), w.get("y_offset").getAsInt());
            } catch (IOException e) {
                throw new IllegalStateException("Cannot read the Bakurani site list", e);
            }
        }
        return sites;
    }

    private static List<Landmark> landmarks(JsonObject o) {
        List<Landmark> out = new ArrayList<>();
        if (o.has("landmarks")) {
            o.getAsJsonArray("landmarks").forEach(e -> {
                JsonObject l = e.getAsJsonObject();
                out.add(new Landmark(l.get("name").getAsString(), l.get("type").getAsString(), l.get("x").getAsDouble(), l.get("z").getAsDouble()));
            });
        }
        return List.copyOf(out);
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
                if (terrain != null) {
                    keepSitesClear(terrain);
                }
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

    /** Removes procedural houses within 30 m of any base, spawn, vendor, board or tower so the hand-placed sites stay clear. */
    private static void keepSitesClear(BakuraniTerrain t) {
        List<Site> all = new ArrayList<>(sites().bases().values());
        all.addAll(sites().spawns());
        all.addAll(sites().vendors());
        all.addAll(sites().boards());
        all.addAll(sites().towers());
        List<BakuraniTerrain.Building> kept = new ArrayList<>();
        List<double[]> yards = new ArrayList<>(); // outpost areas (min x, min z, max x, max z) with a margin for the wall and a verge
        for (Outposts.Cluster c : Outposts.clusters()) {
            double x0 = Double.MAX_VALUE, z0 = Double.MAX_VALUE, x1 = -Double.MAX_VALUE, z1 = -Double.MAX_VALUE;
            for (Outposts.Point p : c.points()) {
                x0 = Math.min(x0, p.x()); x1 = Math.max(x1, p.x()); z0 = Math.min(z0, p.z()); z1 = Math.max(z1, p.z());
            }
            yards.add(new double[]{x0 - 36, z0 - 34, x1 + 36, z1 + 34});
        }
        for (BakuraniTerrain.Building b : t.buildingsIn(-9000, -9000, 9000, 9000)) {
            boolean clash = false;
            for (double[] y : yards) {
                if (b.x() > y[0] - b.radius() && b.x() < y[2] + b.radius() && b.z() > y[1] - b.radius() && b.z() < y[3] + b.radius()) {
                    clash = true;
                    break;
                }
            }
            for (Site s : all) {
                if (Math.abs(s.x() - b.x()) < 30 + b.w() / 2.0 && Math.abs(s.z() - b.z()) < 30 + b.d() / 2.0) {
                    clash = true;
                    break;
                }
            }
            if (!clash) {
                kept.add(b);
            }
        }
        int before = t.buildingCount();
        List<BakuraniTerrain.Building> withSheds = new ArrayList<>(kept);
        var sheds = Decorations.sheds(t, kept);
        var garages = Decorations.garages(t, kept);
        withSheds.addAll(sheds);
        withSheds.addAll(garages);
        t.rebuildBuildings(withSheds);
        WardogsMod.LOGGER.info("Bakurani: {} procedural buildings ({} removed near sites), {} sheds, {} garage rows", kept.size(), before - kept.size(), sheds.size(), garages.size());
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
