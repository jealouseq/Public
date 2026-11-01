package com.wardogs.progress;

import java.util.Set;
import java.util.function.ToIntFunction;

/** Pure unlock rule: a role level requirement plus, for some items, a one-time payment. */
public final class UnlockRule {
    private UnlockRule() {}

    public enum State { OWNED, PAYABLE, LEVEL_TOO_LOW }

    /**
     * @param role      required branch, or {@code null} for items open to everyone
     * @param level     required level in that branch
     * @param cost      one-time unlock payment (0 = free once the level is reached)
     * @param unlocked  ids the player already paid for
     * @param levelOf   current level per branch
     */
    public static State check(String id, Role role, int level, long cost, Set<String> unlocked, ToIntFunction<Role> levelOf) {
        if (unlocked.contains(id)) {
            return State.OWNED;
        }
        if (role != null && levelOf.applyAsInt(role) < level) {
            return State.LEVEL_TOO_LOW;
        }
        return cost <= 0 ? State.OWNED : State.PAYABLE;
    }
}
