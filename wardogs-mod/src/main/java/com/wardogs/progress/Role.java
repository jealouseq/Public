package com.wardogs.progress;

/** Progression branches. WARDOG is the general branch every XP award also feeds. */
public enum Role {
    WARDOG, ASSAULT, MEDIC, RECON, SUPPORT, DRIVER, PILOT;

    public String key() {
        return name().toLowerCase();
    }

    /** @return the role for an item's unlock_role string, or {@code null} for "any"/unknown (no role needed). */
    public static Role byKey(String key) {
        for (Role r : values()) {
            if (r.key().equals(key)) {
                return r;
            }
        }
        return null;
    }
}
