package com.wardogs.world;

import net.minecraft.util.RandomSource;

/** What a loot marker of the Bakurani map holds, decided by its marker title. */
public final class LootCaches {
    private static final String[] WEAPONS = {"ak74", "mp5", "m1911", "m500"};

    private LootCaches() {}

    /** The item id a marker title stands for, or {@code null} for an unknown title. */
    public static String itemFor(String title, RandomSource rnd) {
        String t = title.toLowerCase();
        if (t.startsWith("bandage")) {
            return "bandage";
        }
        if (t.startsWith("defibrillator")) {
            return "defibrillator";
        }
        if (t.startsWith("sniper")) {
            return "sv98";
        }
        if (t.startsWith("weapon")) {
            return WEAPONS[rnd.nextInt(WEAPONS.length)];
        }
        if (t.startsWith("armor")) {
            return "vest_l2";
        }
        if (t.startsWith("helmet")) {
            return "helmet_l2";
        }
        return null;
    }

    /** Bandages come in stacks of three, everything else singly. */
    public static int countFor(String id) {
        return "bandage".equals(id) ? 3 : 1;
    }
}
