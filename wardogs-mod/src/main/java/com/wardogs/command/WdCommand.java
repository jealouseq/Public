package com.wardogs.command;

import com.mojang.brigadier.CommandDispatcher;
import com.mojang.brigadier.arguments.LongArgumentType;
import com.mojang.brigadier.arguments.StringArgumentType;
import com.mojang.brigadier.suggestion.SuggestionProvider;
import com.wardogs.balance.Balance;
import com.wardogs.economy.Wallet;
import com.wardogs.item.GunItem;
import com.wardogs.item.ModItems;
import net.minecraft.commands.CommandSourceStack;
import net.minecraft.commands.Commands;
import net.minecraft.commands.SharedSuggestionProvider;
import net.minecraft.network.chat.Component;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.world.item.ItemStack;

/** Developer/admin commands: /wd give, /wd money, /wd weapons. The vendor GUI replaces buying later. */
public final class WdCommand {
    private static final SuggestionProvider<CommandSourceStack> WEAPONS = (ctx, b) ->
            SharedSuggestionProvider.suggest(ModItems.GUNS.keySet(), b);

    private WdCommand() {}

    public static void register(CommandDispatcher<CommandSourceStack> d) {
        d.register(Commands.literal("wd").requires(s -> s.hasPermission(2))
                .then(Commands.literal("give")
                        .then(Commands.argument("weapon", StringArgumentType.word()).suggests(WEAPONS)
                                .executes(c -> give(c.getSource(), StringArgumentType.getString(c, "weapon")))))
                .then(Commands.literal("money")
                        .then(Commands.literal("set").then(Commands.argument("amount", LongArgumentType.longArg(0))
                                .executes(c -> money(c.getSource(), LongArgumentType.getLong(c, "amount"), true))))
                        .then(Commands.literal("add").then(Commands.argument("amount", LongArgumentType.longArg())
                                .executes(c -> money(c.getSource(), LongArgumentType.getLong(c, "amount"), false))))
                        .executes(c -> {
                            ServerPlayer p = c.getSource().getPlayerOrException();
                            c.getSource().sendSuccess(() -> Component.literal("$" + Wallet.get(p)), false);
                            return 1;
                        }))
                .then(Commands.literal("weapons").executes(c -> {
                    c.getSource().sendSuccess(() -> Component.literal(String.join(", ", ModItems.GUNS.keySet())), false);
                    return ModItems.GUNS.size();
                })));
    }

    private static int give(CommandSourceStack src, String id) throws com.mojang.brigadier.exceptions.CommandSyntaxException {
        var holder = ModItems.GUNS.get(id);
        if (holder == null) {
            src.sendFailure(Component.literal("Unknown weapon: " + id));
            return 0;
        }
        ServerPlayer p = src.getPlayerOrException();
        ItemStack stack = holder.get().newStack();
        if (!p.getInventory().add(stack)) {
            p.drop(stack, false);
        }
        src.sendSuccess(() -> Component.literal("Gave " + Balance.get().weapon(id).name()), true);
        return 1;
    }

    private static int money(CommandSourceStack src, long amount, boolean set) throws com.mojang.brigadier.exceptions.CommandSyntaxException {
        ServerPlayer p = src.getPlayerOrException();
        if (set) {
            Wallet.set(p, amount);
        } else {
            Wallet.add(p, amount);
        }
        src.sendSuccess(() -> Component.literal("$" + Wallet.get(p)), true);
        return 1;
    }
}
