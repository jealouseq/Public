package com.wardogs.combat;

import com.wardogs.WardogsMod;
import net.minecraft.server.level.ServerPlayer;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.event.tick.PlayerTickEvent;

import java.util.Map;
import java.util.UUID;
import java.util.concurrent.ConcurrentHashMap;

/**
 * Stamina 0-100 shown on the HUD. Sprinting drains it, standing or walking refills it. The value is mirrored into the vanilla food
 * bar (food = stamina * 17 / 100), which already refuses to let a player sprint at 6 food or less - that is, below about 35% stamina.
 * It stays below 18 food so vanilla natural regeneration never kicks in.
 */
@EventBusSubscriber(modid = WardogsMod.MOD_ID)
public final class Stamina {
    public static final float MAX = 100f;
    /** The food bar is kept at 17 or less: from 18 vanilla starts healing the player on its own, which this mod leaves to medics. */
    public static final int FOOD_MAX = 17;
    /** Lost per tick while sprinting: about 6.4 seconds from full down to the 30% cut-off. */
    public static final float DRAIN = 0.55f;
    /** Regained per tick while not sprinting. */
    public static final float REGEN = 0.30f;
    /** Never reaches zero, so the starvation damage of an empty food bar cannot happen. */
    public static final float MIN = 5f;

    private static final Map<UUID, Float> VALUE = new ConcurrentHashMap<>();

    private Stamina() {}

    public static float get(UUID id) {
        return VALUE.getOrDefault(id, MAX);
    }

    /** Advances one tick and returns the new value. */
    public static float tick(UUID id, boolean sprinting) {
        float v = get(id);
        v = sprinting ? Math.max(MIN, v - DRAIN) : Math.min(MAX, v + REGEN);
        VALUE.put(id, v);
        return v;
    }

    public static void reset() {
        VALUE.clear();
    }

    public static void forget(UUID id) {
        VALUE.remove(id);
    }

    @SubscribeEvent
    public static void onRespawn(net.neoforged.neoforge.event.entity.player.PlayerEvent.PlayerRespawnEvent e) {
        VALUE.remove(e.getEntity().getUUID());
    }

    @SubscribeEvent
    public static void onTick(PlayerTickEvent.Post e) {
        if (!(e.getEntity() instanceof ServerPlayer sp) || sp.isCreative() || sp.isSpectator()) {
            return;
        }
        float v = tick(sp.getUUID(), sp.isSprinting());
        var food = sp.getFoodData();
        food.setFoodLevel(Math.max(1, Math.min(FOOD_MAX, Math.round(v * FOOD_MAX / MAX))));
        food.setSaturation(0f);
        food.setExhaustion(0f);
    }
}
