package com.wardogs;

import net.neoforged.bus.api.IEventBus;
import net.neoforged.fml.common.Mod;
import org.slf4j.Logger;
import com.mojang.logging.LogUtils;

@Mod(WardogsMod.MOD_ID)
public final class WardogsMod {
    public static final String MOD_ID = "wardogs";
    public static final Logger LOGGER = LogUtils.getLogger();

    public WardogsMod(IEventBus modBus) {
        LOGGER.info("WARDOGS: Bakurani loading");
    }
}
