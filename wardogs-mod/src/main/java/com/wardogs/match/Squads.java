package com.wardogs.match;

import net.minecraft.ChatFormatting;
import net.minecraft.network.chat.Component;
import net.minecraft.server.level.ServerPlayer;

import java.util.ArrayList;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.ConcurrentHashMap;

/** Squads of up to four soldiers of one faction: created by a leader, joined by invitation. In memory only. */
public final class Squads {
    public static final int MAX_SIZE = 4;

    public static final class Squad {
        public final UUID id = UUID.randomUUID();
        public UUID leader;
        public final List<UUID> members = new ArrayList<>();
        public final int faction;

        Squad(UUID leader, int faction) {
            this.leader = leader;
            this.faction = faction;
            members.add(leader);
        }
    }

    private static final Map<UUID, Squad> OF_PLAYER = new ConcurrentHashMap<>();
    /** invited player -> the squad that invited them. */
    private static final Map<UUID, Squad> INVITES = new ConcurrentHashMap<>();

    private Squads() {}

    public static Squad of(ServerPlayer p) {
        return OF_PLAYER.get(p.getUUID());
    }

    private static void say(ServerPlayer p, String text) {
        p.sendSystemMessage(Component.literal("[SQUAD] ").withStyle(ChatFormatting.GRAY).append(Component.literal(text).withStyle(ChatFormatting.WHITE)));
    }

    /** @return an error message, or null on success */
    public static String create(ServerPlayer p) {
        if (Factions.index(p) < 0) {
            return "Pick a faction first";
        }
        if (of(p) != null) {
            return "You are already in a squad";
        }
        Squad s = new Squad(p.getUUID(), Factions.index(p));
        OF_PLAYER.put(p.getUUID(), s);
        say(p, "Squad created. Invite teammates with the radial menu or /wd squad invite <name>.");
        return null;
    }

    /** @return an error message, or null on success */
    public static String invite(ServerPlayer leader, ServerPlayer target) {
        Squad s = of(leader);
        if (s == null) {
            return "You are not in a squad";
        }
        if (!s.leader.equals(leader.getUUID())) {
            return "Only the squad leader can invite";
        }
        if (target == leader) {
            return "You cannot invite yourself";
        }
        if (Factions.index(target) != s.faction) {
            return target.getGameProfile().getName() + " is not on your team";
        }
        if (of(target) != null) {
            return target.getGameProfile().getName() + " is already in a squad";
        }
        if (s.members.size() >= MAX_SIZE) {
            return "The squad is full";
        }
        INVITES.put(target.getUUID(), s);
        say(target, leader.getGameProfile().getName() + " invites you to a squad. Use /wd squad accept.");
        say(leader, "Invited " + target.getGameProfile().getName());
        return null;
    }

    /** @return an error message, or null on success */
    public static String accept(ServerPlayer p) {
        Squad s = INVITES.remove(p.getUUID());
        if (s == null || !s.members.contains(s.leader)) {
            return "You have no squad invitation";
        }
        if (of(p) != null) {
            return "You are already in a squad";
        }
        if (s.members.size() >= MAX_SIZE) {
            return "The squad is full";
        }
        s.members.add(p.getUUID());
        OF_PLAYER.put(p.getUUID(), s);
        for (ServerPlayer m : p.server.getPlayerList().getPlayers()) {
            if (s.members.contains(m.getUUID())) {
                say(m, p.getGameProfile().getName() + " joined the squad");
            }
        }
        return null;
    }

    /** @return an error message, or null on success */
    public static String leave(ServerPlayer p) {
        Squad s = OF_PLAYER.remove(p.getUUID());
        if (s == null) {
            return "You are not in a squad";
        }
        s.members.remove(p.getUUID());
        if (s.members.isEmpty()) {
            INVITES.values().removeIf(x -> x == s);
        } else if (s.leader.equals(p.getUUID())) {
            s.leader = s.members.get(0);
        }
        say(p, "You left the squad");
        return null;
    }

    public static void forget(ServerPlayer p) {
        INVITES.remove(p.getUUID());
        if (of(p) != null) {
            leave(p);
        }
    }

    public static void reset() {
        OF_PLAYER.clear();
        INVITES.clear();
    }

    /** The online teammate closest to {@code p} that could be invited, or null. */
    public static ServerPlayer nearestInvitable(ServerPlayer p, java.util.Collection<ServerPlayer> everyone) {
        ServerPlayer best = null;
        double bestD = 60;
        for (ServerPlayer o : everyone) {
            if (o != p && Factions.index(o) == Factions.index(p) && of(o) == null && o.level() == p.level() && o.distanceTo(p) < bestD) {
                best = o;
                bestD = o.distanceTo(p);
            }
        }
        return best;
    }
}
