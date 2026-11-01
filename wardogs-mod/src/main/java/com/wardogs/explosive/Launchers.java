package com.wardogs.explosive;

import com.wardogs.balance.Balance;
import com.wardogs.balance.LauncherDef;
import com.wardogs.balance.WeaponDef;
import com.wardogs.combat.Ballistics;
import net.minecraft.server.level.ServerLevel;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.sounds.SoundEvents;
import net.minecraft.sounds.SoundSource;
import net.minecraft.world.phys.Vec3;

/** Fires launcher weapons (the weapon item handles ammo, reload and fire rate). */
public final class Launchers {
    private Launchers() {}

    public static boolean isLauncher(WeaponDef w) {
        return Balance.get().launcher(w.id()) != null;
    }

    public static void fire(ServerLevel level, ServerPlayer p, WeaponDef w) {
        LauncherDef def = Balance.get().launcher(w.id());
        Vec3 dir = Ballistics.withSpread(p.getLookAngle(), w.spread(), p.getRandom());
        RocketEntity.launch(level, p, def, dir);
        level.playSound(null, p.blockPosition(), SoundEvents.FIREWORK_ROCKET_LAUNCH, SoundSource.PLAYERS, 2.0f, 0.7f);
    }
}
