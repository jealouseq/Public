package com.wardogs.client;

import com.wardogs.net.ScoreboardPayload;
import net.minecraft.client.Minecraft;

/** Latest scoreboard from the server; a final one opens the round result screen. */
public final class ClientScoreboard {
    private static volatile ScoreboardPayload latest;

    private ClientScoreboard() {}

    public static ScoreboardPayload get() {
        return latest;
    }

    public static void update(ScoreboardPayload p) {
        latest = p;
        if (p.fin()) {
            Minecraft.getInstance().setScreen(new ResultScreen(p));
        }
    }
}
