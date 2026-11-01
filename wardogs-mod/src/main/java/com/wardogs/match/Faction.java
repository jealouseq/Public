package com.wardogs.match;

import net.minecraft.ChatFormatting;

/** The three WARDOGS factions. */
public enum Faction {
    LONESTAR("Lonestar", ChatFormatting.BLUE),
    VALKYRA("Valkyra", ChatFormatting.RED),
    MANTICORE("Manticore", ChatFormatting.GREEN);

    public final String display;
    public final ChatFormatting color;

    Faction(String display, ChatFormatting color) {
        this.display = display;
        this.color = color;
    }

    public static Faction byIndex(int i) {
        return i >= 0 && i < values().length ? values()[i] : null;
    }
}
