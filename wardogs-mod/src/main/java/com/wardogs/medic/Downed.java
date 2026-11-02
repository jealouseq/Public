package com.wardogs.medic;

import com.wardogs.WardogsMod;
import com.wardogs.balance.Balance;
import com.wardogs.match.Factions;
import com.wardogs.net.HudPayload;
import net.minecraft.ChatFormatting;
import net.minecraft.network.chat.Component;
import net.minecraft.resources.ResourceLocation;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.tags.DamageTypeTags;
import net.minecraft.world.entity.Pose;
import net.minecraft.world.entity.ai.attributes.AttributeInstance;
import net.minecraft.world.entity.ai.attributes.AttributeModifier;
import net.minecraft.world.entity.ai.attributes.Attributes;
import net.minecraft.world.entity.player.Player;
import net.neoforged.bus.api.EventPriority;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.event.entity.living.LivingDeathEvent;
import net.neoforged.neoforge.event.entity.living.LivingEntityUseItemEvent;
import net.neoforged.neoforge.event.entity.player.AttackEntityEvent;
import net.neoforged.neoforge.event.entity.player.PlayerEvent;
import net.neoforged.neoforge.event.entity.player.PlayerInteractEvent;
import net.neoforged.neoforge.event.tick.PlayerTickEvent;

import java.util.Map;
import java.util.UUID;
import java.util.concurrent.ConcurrentHashMap;

/**
 * A fatal hit on a combatant (a player with a faction) puts them on the ground instead of killing them:
 * they crawl, cannot use items, and bleed out unless a teammate revives them. A second fatal hit while
 * downed, or running out of time, is a real death.
 */
@EventBusSubscriber(modid = WardogsMod.MOD_ID)
public final class Downed {
    private static final ResourceLocation SLOW_ID = ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "downed_slow");
    private static final ResourceLocation JUMP_ID = ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "downed_jump");

    private static final Map<UUID, Integer> TICKS = new ConcurrentHashMap<>();
    private static final Map<String, Integer> REPEAT = new ConcurrentHashMap<>();

    private Downed() {}

    public static boolean is(Player p) {
        return TICKS.containsKey(p.getUUID());
    }

    /** Ticks until the downed player bleeds out, or 0 if not downed. */
    public static int ticksLeft(Player p) {
        return TICKS.getOrDefault(p.getUUID(), 0);
    }

    public static int repeatCount(UUID reviver, UUID target) {
        return REPEAT.getOrDefault(reviver + ">" + target, 0);
    }

    public static void recordRevive(UUID reviver, UUID target) {
        REPEAT.merge(reviver + ">" + target, 1, Integer::sum);
    }

    public static void reset() {
        REPEAT.clear();
    }

    // --- transitions -------------------------------------------------------------------------

    public static void down(ServerPlayer p) {
        TICKS.put(p.getUUID(), Balance.get().bleedoutTicks());
        p.setHealth((float) Balance.get().downedHp());
        p.stopUsingItem();
        p.setForcedPose(Pose.SWIMMING);
        modify(p, true);
        p.sendSystemMessage(Component.literal("You are down! Wait for a teammate to revive you.").withStyle(ChatFormatting.RED));
        for (ServerPlayer mate : p.server.getPlayerList().getPlayers()) {
            if (mate != p && Factions.sameTeam(mate, p)) {
                mate.displayClientMessage(Component.literal(p.getGameProfile().getName() + " is down!").withStyle(ChatFormatting.RED), true);
            }
        }
        HudPayload.sendTo(p);
    }

    /** A downed player gives up: a real death at once instead of bleeding out. */
    public static boolean giveUp(ServerPlayer p) {
        if (!is(p)) {
            return false;
        }
        p.kill();
        return true;
    }

    /** Stands the player up with the given health. */
    public static void revive(ServerPlayer p, double hp) {
        cleanup(p);
        com.wardogs.economy.KillRewardHandler.onVictimRevived(p.server, p.getUUID());
        p.setHealth((float) Math.min(hp, p.getMaxHealth()));
        p.invulnerableTime = 20;
        HudPayload.sendTo(p);
    }

    private static void cleanup(Player p) {
        TICKS.remove(p.getUUID());
        p.setForcedPose(null);
        modify(p, false);
    }

    private static void modify(Player p, boolean on) {
        AttributeInstance speed = p.getAttribute(Attributes.MOVEMENT_SPEED);
        AttributeInstance jump = p.getAttribute(Attributes.JUMP_STRENGTH);
        if (speed != null) {
            speed.removeModifier(SLOW_ID);
            if (on) {
                speed.addTransientModifier(new AttributeModifier(SLOW_ID, -0.85, AttributeModifier.Operation.ADD_MULTIPLIED_TOTAL));
            }
        }
        if (jump != null) {
            jump.removeModifier(JUMP_ID);
            if (on) {
                jump.addTransientModifier(new AttributeModifier(JUMP_ID, -1.0, AttributeModifier.Operation.ADD_MULTIPLIED_TOTAL));
            }
        }
    }

    // --- events ------------------------------------------------------------------------------

    @SubscribeEvent(priority = EventPriority.LOW)
    public static void onDeath(LivingDeathEvent e) {
        if (!(e.getEntity() instanceof ServerPlayer p)) {
            return;
        }
        if (is(p)) {
            cleanup(p); // finished off: this is a real death
            return;
        }
        if (Factions.index(p) < 0 || e.getSource().is(DamageTypeTags.BYPASSES_INVULNERABILITY)) {
            return; // not a combatant, or /kill and the void
        }
        e.setCanceled(true);
        down(p);
    }

    @SubscribeEvent
    public static void onTick(PlayerTickEvent.Post e) {
        if (e.getEntity() instanceof ServerPlayer p) {
            tick(p);
        }
    }

    /** One server tick of the downed state: keep crawling, count down, bleed out at zero. */
    public static void tick(ServerPlayer p) {
        if (!is(p)) {
            return;
        }
        int left = TICKS.merge(p.getUUID(), -1, Integer::sum);
        p.setForcedPose(Pose.SWIMMING);
        if (left <= 0) {
            cleanup(p);
            p.kill(); // bled out
        }
    }

    @SubscribeEvent
    public static void onRespawn(PlayerEvent.PlayerRespawnEvent e) {
        cleanup(e.getEntity());
    }

    @SubscribeEvent
    public static void onLogout(PlayerEvent.PlayerLoggedOutEvent e) {
        cleanup(e.getEntity());
    }

    // Downed players cannot act.

    @SubscribeEvent
    public static void noUse(PlayerInteractEvent.RightClickItem e) {
        cancelIfDowned(e.getEntity(), e);
    }

    @SubscribeEvent
    public static void noUseBlock(PlayerInteractEvent.RightClickBlock e) {
        cancelIfDowned(e.getEntity(), e);
    }

    @SubscribeEvent
    public static void noBreak(PlayerInteractEvent.LeftClickBlock e) {
        cancelIfDowned(e.getEntity(), e);
    }

    @SubscribeEvent
    public static void noInteractEntity(PlayerInteractEvent.EntityInteract e) {
        cancelIfDowned(e.getEntity(), e);
    }

    @SubscribeEvent
    public static void noAttack(AttackEntityEvent e) {
        if (is(e.getEntity())) {
            e.setCanceled(true);
        }
    }

    @SubscribeEvent
    public static void noUseStart(LivingEntityUseItemEvent.Start e) {
        if (e.getEntity() instanceof Player p && is(p)) {
            e.setCanceled(true);
        }
    }

    private static void cancelIfDowned(Player p, net.neoforged.bus.api.ICancellableEvent e) {
        if (is(p)) {
            e.setCanceled(true);
        }
    }

    /** The downed teammate the reviver is closest to aiming at within revive range, or {@code null}. */
    public static ServerPlayer findTarget(ServerPlayer reviver) {
        double range = Balance.get().reviveRange();
        net.minecraft.world.phys.Vec3 eye = reviver.getEyePosition();
        net.minecraft.world.phys.Vec3 look = reviver.getLookAngle();
        ServerPlayer best = null;
        double bestDot = 0.5; // must be roughly in front
        for (ServerPlayer other : reviver.serverLevel().players()) {
            if (other == reviver || !is(other) || !Factions.sameTeam(reviver, other)) {
                continue;
            }
            net.minecraft.world.phys.Vec3 to = other.getBoundingBox().getCenter().subtract(eye);
            if (to.length() > range) {
                continue;
            }
            double dot = to.normalize().dot(look);
            if (dot > bestDot) {
                bestDot = dot;
                best = other;
            }
        }
        return best;
    }
}
