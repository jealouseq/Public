package com.wardogs.sound;

import com.wardogs.WardogsMod;
import net.minecraft.core.registries.Registries;
import net.minecraft.resources.ResourceLocation;
import net.minecraft.sounds.SoundEvent;
import net.neoforged.neoforge.registries.DeferredHolder;
import net.neoforged.neoforge.registries.DeferredRegister;

import java.util.Map;

/** The mod's synthesised weapon sounds (see research/tools/gen_sounds.py). */
public final class ModSounds {
    public static final DeferredRegister<SoundEvent> SOUNDS = DeferredRegister.create(Registries.SOUND_EVENT, WardogsMod.MOD_ID);

    public static final DeferredHolder<SoundEvent, SoundEvent> SHOT_PISTOL = reg("shot_pistol");
    public static final DeferredHolder<SoundEvent, SoundEvent> SHOT_SMG = reg("shot_smg");
    public static final DeferredHolder<SoundEvent, SoundEvent> SHOT_RIFLE = reg("shot_rifle");
    public static final DeferredHolder<SoundEvent, SoundEvent> SHOT_LMG = reg("shot_lmg");
    public static final DeferredHolder<SoundEvent, SoundEvent> SHOT_SNIPER = reg("shot_sniper");
    public static final DeferredHolder<SoundEvent, SoundEvent> SHOT_SHOTGUN = reg("shot_shotgun");
    public static final DeferredHolder<SoundEvent, SoundEvent> SHOT_LAUNCHER = reg("shot_launcher");

    public static final DeferredHolder<SoundEvent, SoundEvent> DRY_FIRE = reg("dry_fire");
    public static final DeferredHolder<SoundEvent, SoundEvent> RELOAD_OUT = reg("reload_out");
    public static final DeferredHolder<SoundEvent, SoundEvent> RELOAD_IN = reg("reload_in");
    public static final DeferredHolder<SoundEvent, SoundEvent> EXPLOSION = reg("explosion");

    /** Weapon category (as in the balance data) -> shot sound. Unknown categories use the rifle. */
    public static final Map<String, DeferredHolder<SoundEvent, SoundEvent>> BY_CATEGORY = Map.of(
            "pistol", SHOT_PISTOL, "smg", SHOT_SMG, "assault_rifle", SHOT_RIFLE, "lmg", SHOT_LMG,
            "sniper_rifle", SHOT_SNIPER, "marksman_rifle", SHOT_SNIPER, "shotgun", SHOT_SHOTGUN, "launcher", SHOT_LAUNCHER);

    private ModSounds() {}

    private static DeferredHolder<SoundEvent, SoundEvent> reg(String name) {
        return SOUNDS.register(name, () -> SoundEvent.createVariableRangeEvent(ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, name)));
    }

    /** The event for a weapon category, or {@code null} for weapons that keep a vanilla sound (the bow). */
    public static SoundEvent forCategory(String category) {
        var h = BY_CATEGORY.get(category);
        return h == null ? null : h.get();
    }
}
