package com.wardogs.client;

import com.wardogs.progress.ProgressData;

/** Latest role XP and unlocks received from the server (plain data, safe on any side). */
public final class ClientProgress {
    private static volatile ProgressData latest = ProgressData.EMPTY;

    private ClientProgress() {}

    public static void update(ProgressData d) {
        latest = d;
    }

    public static ProgressData get() {
        return latest;
    }
}
