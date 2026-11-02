package com.wardogs.client;

import com.wardogs.net.SquadPayload;

import java.util.List;

/** The player's squad as last reported by the server. */
public final class ClientSquad {
    private static volatile List<SquadPayload.Member> members = List.of();

    private ClientSquad() {}

    public static void update(SquadPayload p) {
        members = p.members();
    }

    public static List<SquadPayload.Member> members() {
        return members;
    }
}
