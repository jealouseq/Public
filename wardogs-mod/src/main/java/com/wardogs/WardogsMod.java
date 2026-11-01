package com.wardogs;

import com.mojang.logging.LogUtils;
import com.wardogs.balance.Balance;
import com.wardogs.economy.KillRewardHandler;
import com.wardogs.explosive.ExplosiveEntities;
import com.wardogs.economy.Wallet;
import com.wardogs.match.Factions;
import com.wardogs.match.MatchManager;
import com.wardogs.item.ModComponents;
import com.wardogs.item.ModItems;
import net.neoforged.bus.api.IEventBus;
import net.neoforged.fml.common.Mod;
import org.slf4j.Logger;

@Mod(WardogsMod.MOD_ID)
public final class WardogsMod {
    public static final String MOD_ID = "wardogs";
    public static final Logger LOGGER = LogUtils.getLogger();

    public WardogsMod(IEventBus modBus) {
        // Touch Balance first: item registration needs the weapon list.
        LOGGER.info("WARDOGS: Bakurani loading with {} weapons", Balance.get().weapons().size());
        ModComponents.COMPONENTS.register(modBus);
        ModItems.ITEMS.register(modBus);
        ModItems.TABS.register(modBus);
        ExplosiveEntities.ENTITIES.register(modBus);
        Wallet.ATTACHMENTS.register(modBus);
        Factions.ATTACHMENTS.register(modBus);
        KillRewardHandler.ZONES = MatchManager::multiplierAt;
    }
}
