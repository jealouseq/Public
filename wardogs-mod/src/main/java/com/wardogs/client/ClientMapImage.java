package com.wardogs.client;

import com.mojang.blaze3d.platform.NativeImage;
import com.wardogs.WardogsMod;
import com.wardogs.world.BakuraniTerrain;
import com.wardogs.world.BakuraniWorld;
import net.minecraft.client.Minecraft;
import net.minecraft.client.renderer.texture.DynamicTexture;
import net.minecraft.resources.ResourceLocation;

import java.util.concurrent.CompletableFuture;

/**
 * The overview picture of Bakurani shown on the map screen, rendered once from the terrain data (material, land cover, roads, hill
 * shading). Needs the same map data folder the world generator uses; without it the screen falls back to a plain background.
 */
public final class ClientMapImage {
    public static final int SIZE = 512;
    public static final ResourceLocation TEXTURE = ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "map_overview");

    private static DynamicTexture texture;
    private static CompletableFuture<int[]> pending;
    private static boolean ready;

    private ClientMapImage() {}

    /** @return true once the picture is uploaded; starts rendering in the background on the first call. */
    public static boolean ensure() {
        if (ready) {
            return true;
        }
        if (pending == null) {
            pending = CompletableFuture.supplyAsync(ClientMapImage::render);
        }
        if (pending.isDone()) {
            int[] px = pending.join();
            if (px != null) {
                NativeImage img = new NativeImage(SIZE, SIZE, false);
                for (int y = 0; y < SIZE; y++) {
                    for (int x = 0; x < SIZE; x++) {
                        int rgb = px[y * SIZE + x];
                        img.setPixelRGBA(x, y, 0xFF000000 | ((rgb & 0xFF) << 16) | (rgb & 0xFF00) | ((rgb >> 16) & 0xFF));
                    }
                }
                texture = new DynamicTexture(img);
                texture.setFilter(true, false);
                Minecraft.getInstance().getTextureManager().register(TEXTURE, texture);
                ready = true;
            } else {
                pending = CompletableFuture.completedFuture(null);
            }
        }
        return ready;
    }

    public static boolean available() {
        return ready;
    }

    private static int mix(int rgb, double k) {
        int r = (int) Math.max(0, Math.min(255, ((rgb >> 16) & 0xFF) * k));
        int g = (int) Math.max(0, Math.min(255, ((rgb >> 8) & 0xFF) * k));
        int b = (int) Math.max(0, Math.min(255, (rgb & 0xFF) * k));
        return (r << 16) | (g << 8) | b;
    }

    private static int colour(BakuraniTerrain t, double x, double z) {
        int mat = t.material(x, z);
        int cov = t.cover(x, z);
        int c = switch (mat) {
            case BakuraniTerrain.GRASS -> 0x78963F;
            case BakuraniTerrain.ROCK -> 0x8A857C;
            case BakuraniTerrain.GRAVEL -> 0xA89C84;
            case BakuraniTerrain.SNOW -> 0xF2F6FA;
            case BakuraniTerrain.FOREST -> 0x3F6B38;
            default -> 0xA99670;
        };
        switch (cov) {
            case BakuraniTerrain.COVER_WATER -> c = 0x3C78B4;
            case BakuraniTerrain.COVER_FOREST -> c = mat == BakuraniTerrain.SNOW ? 0xCFE0D4 : mix(0x3F6B38, mat == BakuraniTerrain.ROCK ? 1.15 : 1.0);
            case BakuraniTerrain.COVER_URBAN -> c = 0x9A9A9E;
            case BakuraniTerrain.COVER_FIELD -> c = 0xC0AE55;
            default -> { }
        }
        return c;
    }

    private static int[] render() {
        BakuraniTerrain t = BakuraniWorld.terrain();
        if (t == null) {
            return null;
        }
        double world = BakuraniWorld.WORLD_SIZE, px = world / SIZE, half = world / 2;
        int[] out = new int[SIZE * SIZE];
        for (int j = 0; j < SIZE; j++) {
            for (int i = 0; i < SIZE; i++) {
                double x = -half + (i + 0.5) * px, z = -half + (j + 0.5) * px;
                int c = colour(t, x, z);
                boolean road = false;
                for (int a = -1; a <= 1 && !road; a++) {
                    for (int b = -1; b <= 1 && !road; b++) {
                        road = t.isRoad(x + a * px / 3, z + b * px / 3);
                    }
                }
                if (road) {
                    c = 0x5E554B;
                }
                double dx = t.elevation(x - px, z) - t.elevation(x + px, z);
                double dz = t.elevation(x, z - px) - t.elevation(x, z + px);
                double shade = 1.0 + Math.max(-0.45, Math.min(0.45, (dx * 0.5 + dz * 0.5) / 60.0));
                out[j * SIZE + i] = mix(c, shade);
            }
        }
        return out;
    }
}
