package com.wardogs.command;

import com.mojang.brigadier.CommandDispatcher;
import com.mojang.brigadier.arguments.DoubleArgumentType;
import com.mojang.brigadier.arguments.LongArgumentType;
import com.mojang.brigadier.arguments.StringArgumentType;
import com.mojang.brigadier.exceptions.CommandSyntaxException;
import com.mojang.brigadier.suggestion.SuggestionProvider;
import com.wardogs.balance.Balance;
import com.wardogs.economy.Wallet;
import com.wardogs.item.ModItems;
import com.wardogs.match.Faction;
import com.wardogs.match.Factions;
import com.wardogs.match.MatchManager;
import com.wardogs.net.HudPayload;
import com.wardogs.progress.Progression;
import com.wardogs.progress.Role;
import com.wardogs.shop.Shop;
import net.minecraft.ChatFormatting;
import net.minecraft.commands.CommandSourceStack;
import net.minecraft.commands.Commands;
import net.minecraft.commands.SharedSuggestionProvider;
import net.minecraft.network.chat.Component;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.world.item.ItemStack;

import java.util.Arrays;

/**
 * /wd buy <item>      buy a weapon or armor piece (players)
 * /wd team <faction>  join a faction (players)
 * /wd give|money|zone|match|weapons   admin tools (permission level 2)
 */
public final class WdCommand {
    private static final SuggestionProvider<CommandSourceStack> ITEMS = (ctx, b) ->
            SharedSuggestionProvider.suggest(ModItems.buyableIds(), b);
    private static final SuggestionProvider<CommandSourceStack> FACTIONS = (ctx, b) ->
            SharedSuggestionProvider.suggest(Arrays.stream(Faction.values()).map(f -> f.name().toLowerCase()), b);

    private WdCommand() {}

    public static void register(CommandDispatcher<CommandSourceStack> d) {
        d.register(Commands.literal("wd")
                .then(Commands.literal("buy")
                        .then(Commands.argument("item", StringArgumentType.word()).suggests(ITEMS)
                                .executes(c -> buy(c.getSource(), StringArgumentType.getString(c, "item")))))
                .then(Commands.literal("unlock")
                        .then(Commands.argument("item", StringArgumentType.word()).suggests(ITEMS)
                                .executes(c -> unlock(c.getSource(), StringArgumentType.getString(c, "item")))))
                .then(Commands.literal("progress").executes(c -> progress(c.getSource())))
                .then(Commands.literal("team")
                        .then(Commands.argument("faction", StringArgumentType.word()).suggests(FACTIONS)
                                .executes(c -> team(c.getSource(), StringArgumentType.getString(c, "faction")))))
                .then(Commands.literal("money").executes(c -> {
                    ServerPlayer p = c.getSource().getPlayerOrException();
                    c.getSource().sendSuccess(() -> Component.literal("$" + Wallet.get(p)), false);
                    return 1;
                }).then(Commands.literal("set").requires(s -> s.hasPermission(2))
                        .then(Commands.argument("amount", LongArgumentType.longArg(0))
                                .executes(c -> money(c.getSource(), LongArgumentType.getLong(c, "amount"), true))))
                        .then(Commands.literal("add").requires(s -> s.hasPermission(2))
                                .then(Commands.argument("amount", LongArgumentType.longArg())
                                        .executes(c -> money(c.getSource(), LongArgumentType.getLong(c, "amount"), false)))))
                .then(Commands.literal("give").requires(s -> s.hasPermission(2))
                        .then(Commands.argument("item", StringArgumentType.word()).suggests(ITEMS)
                                .executes(c -> give(c.getSource(), StringArgumentType.getString(c, "item")))))
                .then(Commands.literal("weapons").requires(s -> s.hasPermission(2)).executes(c -> {
                    c.getSource().sendSuccess(() -> Component.literal(String.join(", ", ModItems.buyableIds())), false);
                    return ModItems.buyableIds().size();
                }))
                .then(Commands.literal("zone").requires(s -> s.hasPermission(2))
                        .then(Commands.literal("here").then(Commands.argument("radius", DoubleArgumentType.doubleArg(5, 2000))
                                .executes(c -> {
                                    CommandSourceStack s = c.getSource();
                                    MatchManager.setZone(s.getServer().overworld(), s.getPosition().x, s.getPosition().z,
                                            DoubleArgumentType.getDouble(c, "radius"));
                                    s.sendSuccess(() -> Component.literal("Zone set. " + MatchManager.status(s.getServer().overworld())), true);
                                    return 1;
                                }))))
                .then(Commands.literal("match").requires(s -> s.hasPermission(2))
                        .then(Commands.literal("start").executes(c -> {
                            MatchManager.start(c.getSource().getServer().overworld());
                            return 1;
                        }))
                        .then(Commands.literal("stop").executes(c -> {
                            MatchManager.stop(c.getSource().getServer().overworld());
                            return 1;
                        }))
                        .then(Commands.literal("status").executes(c -> {
                            c.getSource().sendSuccess(() -> Component.literal(MatchManager.status(c.getSource().getServer().overworld())), false);
                            return 1;
                        }))));
    }

    private static int team(CommandSourceStack src, String name) throws CommandSyntaxException {
        ServerPlayer p = src.getPlayerOrException();
        Faction f = Arrays.stream(Faction.values()).filter(x -> x.name().equalsIgnoreCase(name)).findFirst().orElse(null);
        if (f == null) {
            src.sendFailure(Component.literal("Unknown faction. Choose: lonestar, valkyra, manticore"));
            return 0;
        }
        Factions.set(p, f);
        HudPayload.sendTo(p);
        src.sendSuccess(() -> Component.literal("You joined " + f.display).withStyle(f.color), false);
        return 1;
    }

    private static int buy(CommandSourceStack src, String id) throws CommandSyntaxException {
        Shop.Result r = Shop.buy(src.getPlayerOrException(), id);
        if (r.ok()) {
            src.sendSuccess(r::message, false);
        } else {
            src.sendFailure(r.message());
        }
        return r.ok() ? 1 : 0;
    }

    private static int unlock(CommandSourceStack src, String id) throws CommandSyntaxException {
        Shop.Result r = Shop.unlock(src.getPlayerOrException(), id);
        if (r.ok()) {
            src.sendSuccess(r::message, false);
        } else {
            src.sendFailure(r.message());
        }
        return r.ok() ? 1 : 0;
    }

    private static int progress(CommandSourceStack src) throws CommandSyntaxException {
        ServerPlayer p = src.getPlayerOrException();
        var data = Progression.get(p);
        StringBuilder sb = new StringBuilder();
        for (Role r : Role.values()) {
            sb.append(r.key()).append(" L").append(Progression.level(data, r)).append(" (").append(data.xpOf(r)).append(" xp)  ");
        }
        src.sendSuccess(() -> Component.literal(sb.toString().trim()), false);
        return 1;
    }

    private static int give(CommandSourceStack src, String id) throws CommandSyntaxException {
        ItemStack stack = ModItems.stack(id);
        if (stack == null) {
            src.sendFailure(Component.literal("Unknown item: " + id));
            return 0;
        }
        ServerPlayer p = src.getPlayerOrException();
        if (!p.getInventory().add(stack)) {
            p.drop(stack, false);
        }
        src.sendSuccess(() -> Component.literal("Gave " + id), true);
        return 1;
    }

    private static int money(CommandSourceStack src, long amount, boolean set) throws CommandSyntaxException {
        ServerPlayer p = src.getPlayerOrException();
        if (set) {
            Wallet.set(p, amount);
        } else {
            Wallet.add(p, amount);
        }
        HudPayload.sendTo(p);
        src.sendSuccess(() -> Component.literal("$" + Wallet.get(p)), true);
        return 1;
    }
}
