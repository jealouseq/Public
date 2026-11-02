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
import com.wardogs.match.Squads;
import com.wardogs.world.BakuraniWorld;
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
                .then(Commands.literal("bakurani").executes(c -> travel(c.getSource()))
                        .then(Commands.literal("setup").requires(s -> s.hasPermission(2)).executes(c -> setupBakurani(c.getSource()))))
                .then(Commands.literal("vehicle").requires(src -> src.hasPermission(2))
                        .then(Commands.literal("break").then(Commands.argument("section", StringArgumentType.greedyString())
                                .executes(c -> vehicleSection(c.getSource(), StringArgumentType.getString(c, "section"), true))))
                        .then(Commands.literal("repair").executes(c -> vehicleSection(c.getSource(), null, false))))
                .then(Commands.literal("mode").requires(src -> src.hasPermission(2))
                        .then(Commands.literal("standard").executes(c -> setMode(c.getSource(), false)))
                        .then(Commands.literal("hardcore").executes(c -> setMode(c.getSource(), true))))
                .then(Commands.literal("squad")
                        .then(Commands.literal("create").executes(c -> squadResult(c.getSource(), Squads.create(c.getSource().getPlayerOrException()))))
                        .then(Commands.literal("accept").executes(c -> squadResult(c.getSource(), Squads.accept(c.getSource().getPlayerOrException()))))
                        .then(Commands.literal("leave").executes(c -> squadResult(c.getSource(), Squads.leave(c.getSource().getPlayerOrException()))))
                        .then(Commands.literal("invite").then(Commands.argument("player", net.minecraft.commands.arguments.EntityArgument.player())
                                .executes(c -> squadResult(c.getSource(), Squads.invite(c.getSource().getPlayerOrException(),
                                        net.minecraft.commands.arguments.EntityArgument.getPlayer(c, "player")))))))
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
                                    MatchManager.setZone(s.getLevel(), s.getPosition().x, s.getPosition().z,
                                            DoubleArgumentType.getDouble(c, "radius"));
                                    s.sendSuccess(() -> Component.literal("Zone set. " + MatchManager.status(s.getLevel())), true);
                                    return 1;
                                }))))
                .then(Commands.literal("match").requires(s -> s.hasPermission(2))
                        .then(Commands.literal("start").executes(c -> {
                            MatchManager.start(c.getSource().getLevel());
                            return 1;
                        }))
                        .then(Commands.literal("bakurani").executes(c -> startBakurani(c.getSource())))
                        .then(Commands.literal("stop").executes(c -> {
                            MatchManager.stop(c.getSource().getServer().overworld());
                            return 1;
                        }))
                        .then(Commands.literal("status").executes(c -> {
                            c.getSource().sendSuccess(() -> Component.literal(MatchManager.status(c.getSource().getServer().overworld())), false);
                            return 1;
                        }))));
    }

    /** Teleports the player to their faction's spawn on Bakurani. */
    private static int travel(CommandSourceStack src) throws CommandSyntaxException {
        ServerPlayer p = src.getPlayerOrException();
        net.minecraft.server.level.ServerLevel level = src.getServer().getLevel(BakuraniWorld.DIMENSION);
        if (level == null) {
            src.sendFailure(Component.literal("The wardogs:bakurani dimension is not loaded"));
            return 0;
        }
        Faction f = Factions.of(p);
        BakuraniWorld.Site site = BakuraniWorld.spawnOf(f == null ? "lonestar" : f.name().toLowerCase());
        int y = BakuraniWorld.surfaceY(site.x(), site.z()) + 2;
        p.teleportTo(level, site.x(), y, site.z(), 0f, 0f);
        src.sendSuccess(() -> Component.literal("Welcome to Bakurani" + (f == null ? " (pick a faction with /wd team)" : "")), false);
        return 1;
    }

    /** Breaks a named section of (or fully repairs) the vehicle the player rides, or else the nearest one within 8 blocks. */
    private static int vehicleSection(CommandSourceStack src, String section, boolean breakIt) throws CommandSyntaxException {
        ServerPlayer p = src.getPlayerOrException();
        com.wardogs.vehicle.Vehicle v = p.getVehicle() instanceof com.wardogs.vehicle.Vehicle rv ? rv : p.level()
                .getEntitiesOfClass(com.wardogs.vehicle.Vehicle.class, p.getBoundingBox().inflate(8)).stream()
                .min(java.util.Comparator.comparingDouble(e -> e.distanceToSqr(p))).orElse(null);
        if (v == null) {
            src.sendFailure(Component.literal("No vehicle within 8 blocks"));
            return 0;
        }
        if (breakIt) {
            if (!v.destroySection(section)) {
                src.sendFailure(Component.literal("This vehicle has no section '" + section + "': " + v.def().sections().keySet()));
                return 0;
            }
            src.sendSuccess(() -> Component.literal(section + " destroyed"), true);
        } else {
            v.repairSections();
            src.sendSuccess(() -> Component.literal("Sections repaired"), true);
        }
        return 1;
    }

    private static int setMode(CommandSourceStack src, boolean hardcore) {
        var d = com.wardogs.match.MatchData.get(src.getServer().overworld());
        d.hardcore = hardcore;
        d.setDirty();
        src.getServer().getPlayerList().getPlayers().forEach(HudPayload::sendTo);
        src.sendSuccess(() -> Component.literal(hardcore ? "Hardcore mode: no minimap or Hot Zone marker, kill feed limited to your squad"
                : "Standard mode"), true);
        return 1;
    }

    private static int squadResult(CommandSourceStack src, String error) {
        if (error != null) {
            src.sendFailure(Component.literal(error));
            return 0;
        }
        return 1;
    }

    private static int setupBakurani(CommandSourceStack src) {
        net.minecraft.server.level.ServerLevel level = src.getServer().getLevel(BakuraniWorld.DIMENSION);
        if (level == null) {
            src.sendFailure(Component.literal("The wardogs:bakurani dimension is not loaded"));
            return 0;
        }
        var r = com.wardogs.world.BakuraniSetup.buildAll(level);
        src.sendSuccess(() -> Component.literal("Bakurani setup started: " + r.towers() + " towers, " + r.vendors() + " vendors, outposts and more, in "
                + com.wardogs.world.SetupQueue.total() + " steps over the next seconds or minutes (watch the chat for \"setup finished\")"), true);
        return 1;
    }

    /** Starts a match on Bakurani with one of the real Control Zone variants (picked by weight). */
    private static int startBakurani(CommandSourceStack src) {
        net.minecraft.server.level.ServerLevel level = src.getServer().getLevel(BakuraniWorld.DIMENSION);
        if (level == null) {
            src.sendFailure(Component.literal("The wardogs:bakurani dimension is not loaded"));
            return 0;
        }
        var zones = BakuraniWorld.sites().zones();
        int total = zones.stream().mapToInt(BakuraniWorld.Zone::weight).sum();
        int roll = level.random.nextInt(Math.max(1, total));
        BakuraniWorld.Zone pick = zones.get(0);
        for (BakuraniWorld.Zone z : zones) {
            roll -= z.weight();
            if (roll < 0) {
                pick = z;
                break;
            }
        }
        var built = com.wardogs.world.BakuraniSetup.buildAll(level);
        MatchManager.setZone(level, pick.x(), pick.z(), pick.radius());
        MatchManager.start(level);
        for (ServerPlayer sp : src.getServer().getPlayerList().getPlayers()) {
            com.wardogs.match.Respawns.sendToBase(sp);
        }
        BakuraniWorld.Zone chosen = pick;
        src.sendSuccess(() -> Component.literal("Bakurani match started: " + chosen.name() + " zone at (" + (int) chosen.x() + ", " + (int) chosen.z() + ")"), true);
        return 1;
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
