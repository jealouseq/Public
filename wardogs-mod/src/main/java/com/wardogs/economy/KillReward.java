package com.wardogs.economy;

import com.wardogs.balance.Balance;
import com.wardogs.balance.RewardsDef;
import com.wardogs.balance.WeaponDef;

/** Season 2 kill payout (wardogs.zone Cash & XP calculator). Pure apart from the balance lookup in {@link #classify}. */
public final class KillReward {
    private KillReward() {}

    /**
     * @param base        class base ($300 small arms, $400 assault weapons, $600 sniper, ...)
     * @param targetOnly  sniper, explosive and artillery kills weigh only the victim's position
     * @param distanceTiers whether the long-range multiplier applies (sniper and marksman kills)
     * @param shooterZone zone multiplier where the shooter stands (1, 5 or 10)
     * @param targetZone  zone multiplier where the victim fell
     * @param streak      kills since the shooter last died, counting this one
     * @param repeatCount how many times the shooter already killed this victim
     * @param shutdown    this kill ended the victim's own streak
     */
    public record Context(double base, boolean headshot, boolean targetOnly, boolean distanceTiers, double distance,
                          double shooterZone, double targetZone, int streak, int repeatCount, boolean revenge, boolean shutdown) {}

    public static double distanceMult(RewardsDef r, double distance) {
        double m = 1.0;
        for (double[] tier : r.distanceMult()) {
            if (distance >= tier[0]) {
                m = tier[1];
            }
        }
        return m;
    }

    /**
     * cash = (1 - 0.24 x repeats) x (base [+50 headshot] + 50 x streak bonus kills)
     *        x (0.5 x shooterZone + 0.5 x targetZone) x distance x revenge x shutdown.
     * Example: a rifle kill with both soldiers in the Hot Zone pays 400 x 10 = $4 000.
     */
    public static long compute(RewardsDef r, Context c) {
        double unit = c.base() + (c.headshot() ? 50.0 : 0.0) + r.streakBonus() * Math.min(Math.max(c.streak() - 1, 0), r.streakKillsCap());
        double zone = c.targetOnly() ? c.targetZone() : r.shooterWeight() * c.shooterZone() + r.targetWeight() * c.targetZone();
        double v = unit * zone;
        if (c.distanceTiers()) {
            v *= distanceMult(r, c.distance());
        }
        if (c.revenge()) {
            v *= r.revengeMult();
        }
        if (c.shutdown()) {
            v *= r.shutdownMult();
        }
        int steps = Math.min(Math.max(c.repeatCount(), 0), r.repeatSteps());
        v *= Math.max(0.0, 1.0 - r.repeatPenalty() * steps);
        return Math.round(v);
    }

    /** Which payout class a weapon, explosive or emplacement id belongs to. */
    public static String classify(String weaponId) {
        Balance b = Balance.get();
        WeaponDef w = b.weapon(weaponId);
        if (w != null) {
            return switch (w.category()) {
                case "assault_rifle", "pistol" -> "assault";
                case "marksman_rifle", "sniper_rifle", "bow" -> "sniper";
                case "launcher" -> "explosive";
                default -> "small";
            };
        }
        if (b.explosive(weaponId) != null) {
            return "explosive";
        }
        return switch (weaponId) {
            case "mortar_shell" -> "artillery";
            case "veh_shell" -> "tank";
            case "ciws" -> "aircraft";
            default -> "small";
        };
    }

    public static boolean targetOnly(String killClass) {
        return killClass.equals("sniper") || killClass.equals("explosive") || killClass.equals("artillery");
    }
}
