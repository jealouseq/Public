package com.wardogs.item;

import com.wardogs.WardogsMod;
import net.minecraft.core.component.DataComponentType;
import net.minecraft.core.registries.Registries;
import net.minecraft.network.codec.ByteBufCodecs;
import net.minecraft.util.ExtraCodecs;
import net.neoforged.neoforge.registries.DeferredHolder;
import net.neoforged.neoforge.registries.DeferredRegister;

public final class ModComponents {
    public static final DeferredRegister.DataComponents COMPONENTS =
            DeferredRegister.createDataComponents(Registries.DATA_COMPONENT_TYPE, WardogsMod.MOD_ID);

    /** Rounds currently in the magazine. */
    public static final DeferredHolder<DataComponentType<?>, DataComponentType<Integer>> AMMO =
            COMPONENTS.registerComponentType("ammo", b -> b
                    .persistent(ExtraCodecs.NON_NEGATIVE_INT)
                    .networkSynchronized(ByteBufCodecs.VAR_INT));

    /** Loaded ammo type (ordinal of {@link com.wardogs.balance.AmmoType}). */
    public static final DeferredHolder<DataComponentType<?>, DataComponentType<Integer>> AMMO_TYPE =
            COMPONENTS.registerComponentType("ammo_type", b -> b
                    .persistent(ExtraCodecs.NON_NEGATIVE_INT)
                    .networkSynchronized(ByteBufCodecs.VAR_INT));

    /** Remaining defibrillator charges. */
    public static final DeferredHolder<DataComponentType<?>, DataComponentType<Integer>> CHARGES =
            COMPONENTS.registerComponentType("charges", b -> b
                    .persistent(ExtraCodecs.NON_NEGATIVE_INT)
                    .networkSynchronized(ByteBufCodecs.VAR_INT));

    /** Index of the structure selected on a hammer. */
    public static final DeferredHolder<DataComponentType<?>, DataComponentType<Integer>> SELECTED =
            COMPONENTS.registerComponentType("selected", b -> b
                    .persistent(ExtraCodecs.NON_NEGATIVE_INT)
                    .networkSynchronized(ByteBufCodecs.VAR_INT));

    private ModComponents() {}
}
