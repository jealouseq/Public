package com.wardogs.client;

import com.mojang.blaze3d.platform.NativeImage;
import com.wardogs.WardogsMod;
import net.minecraft.client.Minecraft;
import net.minecraft.client.renderer.texture.DynamicTexture;
import net.minecraft.resources.ResourceLocation;
import net.minecraft.world.level.Level;
import net.minecraft.world.level.block.state.BlockState;
import net.minecraft.world.level.chunk.LevelChunk;
import net.minecraft.world.level.levelgen.Heightmap;
import net.minecraft.core.BlockPos;

/** A top-down map of the blocks around the player (north up; the HUD turns it to the heading), redrawn twice a second from the loaded chunks. */
public final class ClientMinimap {
    /** 96 pixels of 3 blocks: the texture covers 288 blocks, enough to fill a 192-block window turned to any heading. */
    public static final int PIXELS = 96;
    /** Blocks per map pixel: 3 gives a 192 m wide view. */
    public static final int BLOCKS_PER_PIXEL = 3;
    public static final ResourceLocation TEXTURE = ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "minimap");

    private static DynamicTexture texture;
    private static long lastUpdate = -100;
    private static int centerX, centerZ;

    /** World block at the middle of the texture as last drawn. */
    public static int centerX() {
        return centerX;
    }

    public static int centerZ() {
        return centerZ;
    }

    private ClientMinimap() {}

    private static DynamicTexture texture() {
        if (texture == null) {
            texture = new DynamicTexture(PIXELS, PIXELS, true);
            Minecraft.getInstance().getTextureManager().register(TEXTURE, texture);
        }
        return texture;
    }

    /** Redraws the map if it is older than half a second. */
    public static void tick(Minecraft mc) {
        if (mc.level == null || mc.player == null) {
            return;
        }
        long now = mc.level.getGameTime();
        if (now - lastUpdate < 10) {
            return;
        }
        lastUpdate = now;
        DynamicTexture tex = texture();
        NativeImage img = tex.getPixels();
        Level level = mc.level;
        int cx = (int) Math.floor(mc.player.getX()), cz = (int) Math.floor(mc.player.getZ());
        centerX = cx;
        centerZ = cz;
        int[][] heights = new int[PIXELS + 1][PIXELS + 1];
        BlockPos.MutableBlockPos pos = new BlockPos.MutableBlockPos();
        for (int pz = 0; pz <= PIXELS; pz++) {
            for (int px = 0; px <= PIXELS; px++) {
                int wx = cx + (px - PIXELS / 2) * BLOCKS_PER_PIXEL, wz = cz + (pz - PIXELS / 2) * BLOCKS_PER_PIXEL;
                LevelChunk chunk = level.getChunkSource().getChunkNow(wx >> 4, wz >> 4);
                heights[pz][px] = chunk == null ? Integer.MIN_VALUE : chunk.getHeight(Heightmap.Types.WORLD_SURFACE, wx & 15, wz & 15);
            }
        }
        for (int pz = 0; pz < PIXELS; pz++) {
            for (int px = 0; px < PIXELS; px++) {
                int h = heights[pz][px];
                if (h == Integer.MIN_VALUE) {
                    img.setPixelRGBA(px, pz, 0xFF2A2A2A);
                    continue;
                }
                int wx = cx + (px - PIXELS / 2) * BLOCKS_PER_PIXEL, wz = cz + (pz - PIXELS / 2) * BLOCKS_PER_PIXEL;
                pos.set(wx, h - 1, wz);
                BlockState st = level.getBlockState(pos);
                int rgb = st.getMapColor(level, pos).col;
                int hn = heights[Math.max(0, pz - 1)][px] == Integer.MIN_VALUE ? h : heights[Math.max(0, pz - 1)][px];
                double shade = Math.max(0.6, Math.min(1.35, 1.0 + (h - hn) * 0.12));
                int r = clamp(((rgb >> 16) & 0xFF) * shade), g = clamp(((rgb >> 8) & 0xFF) * shade), b = clamp((rgb & 0xFF) * shade);
                img.setPixelRGBA(px, pz, 0xFF000000 | (b << 16) | (g << 8) | r); // NativeImage is ABGR
            }
        }
        tex.upload();
    }

    private static int clamp(double v) {
        return (int) Math.max(0, Math.min(255, v));
    }
}
