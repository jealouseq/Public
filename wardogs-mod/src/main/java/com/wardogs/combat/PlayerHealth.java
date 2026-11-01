package com.wardogs.combat;

import com.wardogs.WardogsMod;
import com.wardogs.balance.Balance;
import net.minecraft.resources.ResourceLocation;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.world.entity.ai.attributes.Attribute;
import net.minecraft.world.entity.ai.attributes.AttributeInstance;
import net.minecraft.world.entity.ai.attributes.AttributeModifier;
import net.minecraft.world.entity.ai.attributes.Attributes;
import net.minecraft.world.entity.player.Player;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.event.entity.living.LivingIncomingDamageEvent;
import net.neoforged.neoforge.event.entity.player.PlayerEvent;

/**
 * Players have {@link Balance#playerHealth()} (100) hit points instead of Minecraft's 20.
 * Bullets deal their WARDOGS damage 1:1; vanilla damage (falls, mobs, fire) is scaled up by the same ratio
 * so that it keeps its relative strength.
 */
@EventBusSubscriber(modid = WardogsMod.MOD_ID)
public final class PlayerHealth {
    private static final ResourceLocation MODIFIER_ID = ResourceLocation.fromNamespaceAndPath(WardogsMod.MOD_ID, "max_health");
    private static final double VANILLA_HEALTH = 20.0;

    private PlayerHealth() {}

    /** Gives the player the configured max health. Returns true if the attribute was changed. */
    public static boolean apply(Player p) {
        AttributeInstance attr = p.getAttribute(Attributes.MAX_HEALTH);
        if (attr == null) {
            return false;
        }
        double bonus = Balance.get().playerHealth() - VANILLA_HEALTH;
        AttributeModifier existing = attr.getModifier(MODIFIER_ID);
        if (existing != null && existing.amount() == bonus) {
            return false;
        }
        attr.removeModifier(MODIFIER_ID);
        attr.addTransientModifier(new AttributeModifier(MODIFIER_ID, bonus, AttributeModifier.Operation.ADD_VALUE));
        return true;
    }

    /** Multiplier from vanilla damage to this player's health scale (5.0 with 100 HP). */
    public static float vanillaScale(Player p) {
        return (float) (Balance.get().playerHealth() / VANILLA_HEALTH);
    }

    @SubscribeEvent
    public static void onLogin(PlayerEvent.PlayerLoggedInEvent e) {
        if (e.getEntity() instanceof ServerPlayer sp && apply(sp)) {
            // First time with the new maximum: start full, like a fresh soldier.
            sp.setHealth(sp.getMaxHealth());
        }
    }

    @SubscribeEvent
    public static void onRespawn(PlayerEvent.PlayerRespawnEvent e) {
        if (e.getEntity() instanceof ServerPlayer sp) {
            apply(sp);
            sp.setHealth(sp.getMaxHealth());
        }
    }

    @SubscribeEvent
    public static void onIncomingDamage(LivingIncomingDamageEvent e) {
        if (!(e.getEntity() instanceof Player p) || Ballistics.isWardogsDamage(e.getSource())) {
            return;
        }
        e.setAmount(e.getAmount() * vanillaScale(p));
    }
}
