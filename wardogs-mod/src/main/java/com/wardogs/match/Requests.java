package com.wardogs.match;

import net.minecraft.ChatFormatting;
import net.minecraft.network.chat.Component;
import net.minecraft.server.level.ServerPlayer;

import java.util.LinkedHashMap;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.ConcurrentHashMap;

/** The radial menu messages: requests go to the whole team nearby, local callouts and emotes only to those close by. */
public final class Requests {
    public enum Group { SQUAD, REQUESTS, LOCAL, EMOTES }

    public record Entry(String id, Group group, String label, String text, double range) {}

    public static final Map<String, Entry> ENTRIES = new LinkedHashMap<>();

    static {
        add("squad_create", Group.SQUAD, "Create squad", "", 0);
        add("squad_invite", Group.SQUAD, "Invite nearest", "", 0);
        add("squad_leave", Group.SQUAD, "Leave squad", "", 0);
        add("heal", Group.REQUESTS, "Healing", "needs a medic!", 250);
        add("ammo", Group.REQUESTS, "Ammo", "needs ammo!", 250);
        add("supplies", Group.REQUESTS, "Supplies", "needs supplies!", 250);
        add("repair", Group.REQUESTS, "Repairs", "needs repairs!", 250);
        add("enemy", Group.LOCAL, "Enemy spotted", "spotted an enemy!", 120);
        add("transport", Group.LOCAL, "Need a ride", "needs a ride!", 120);
        add("follow", Group.LOCAL, "Follow me", "says: follow me!", 120);
        add("thanks", Group.LOCAL, "Thanks", "says: thanks!", 120);
        add("salute", Group.EMOTES, "Salute", "salutes.", 40);
        add("wave", Group.EMOTES, "Wave", "waves.", 40);
        add("cheer", Group.EMOTES, "Cheer", "cheers!", 40);
        add("point", Group.EMOTES, "Point", "points ahead.", 40);
    }

    private static final Map<UUID, Long> LAST = new ConcurrentHashMap<>();
    /** Minimum ticks between two messages from one player. */
    public static final int COOLDOWN = 40;

    private Requests() {}

    private static void add(String id, Group g, String label, String text, double range) {
        ENTRIES.put(id, new Entry(id, g, label, text, range));
    }

    /** Sends the message for {@code id} from {@code from} to the teammates in range. @return how many received it, or -1 if refused. */
    public static int send(ServerPlayer from, String id) {
        return send(from, id, from.server.getPlayerList().getPlayers());
    }

    /** Same as {@link #send(ServerPlayer, String)} with an explicit list of candidate receivers (the online players in production). */
    public static int send(ServerPlayer from, String id, java.util.Collection<ServerPlayer> everyone) {
        Entry e = ENTRIES.get(id);
        if (e != null && e.group() == Group.SQUAD) {
            String err = switch (id) {
                case "squad_create" -> Squads.create(from);
                case "squad_leave" -> Squads.leave(from);
                default -> {
                    ServerPlayer t = Squads.nearestInvitable(from, everyone);
                    yield t == null ? "No teammate nearby to invite" : Squads.invite(from, t);
                }
            };
            if (err != null) {
                from.sendSystemMessage(Component.literal("[SQUAD] " + err).withStyle(ChatFormatting.RED));
                return -1;
            }
            return 1;
        }
        Faction f = Factions.of(from);
        if (e == null || f == null) {
            return -1;
        }
        long now = from.level().getGameTime();
        Long last = LAST.get(from.getUUID());
        if (last != null && now - last < COOLDOWN) {
            return -1;
        }
        LAST.put(from.getUUID(), now);
        int n = 0;
        for (ServerPlayer p : everyone) {
            if (Factions.of(p) != f || p.level() != from.level()) {
                continue;
            }
            double d = p.distanceTo(from);
            if (d > e.range()) {
                continue;
            }
            Component msg = Component.literal("[" + e.group().name() + "] ").withStyle(ChatFormatting.GRAY)
                    .append(Component.literal(from.getGameProfile().getName()).withStyle(f.color))
                    .append(Component.literal(" " + e.text() + (p == from ? "" : " (" + (int) d + " m)")).withStyle(ChatFormatting.WHITE));
            p.sendSystemMessage(msg);
            n++;
        }
        return n;
    }
}
