package com.wardogs.gametest;

import com.wardogs.WardogsMod;
import com.wardogs.balance.AmmoType;
import com.wardogs.balance.Balance;
import com.wardogs.balance.WeaponDef;
import com.wardogs.combat.Ballistics;
import com.wardogs.combat.ShooterState;
import com.wardogs.economy.Wallet;
import com.wardogs.match.Faction;
import com.wardogs.match.Factions;
import com.wardogs.match.MatchData;
import com.wardogs.match.MatchManager;
import com.wardogs.item.GunItem;
import com.wardogs.item.ModComponents;
import com.wardogs.item.ModItems;
import net.minecraft.core.BlockPos;
import net.minecraft.gametest.framework.GameTest;
import net.minecraft.gametest.framework.GameTestHelper;
import net.minecraft.server.level.ServerLevel;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.world.InteractionHand;
import net.minecraft.world.entity.EntityType;
import net.minecraft.world.entity.monster.Zombie;
import net.minecraft.world.item.ItemStack;
import net.minecraft.world.level.block.Blocks;
import net.minecraft.world.phys.Vec3;
import net.neoforged.bus.api.SubscribeEvent;
import net.neoforged.fml.common.EventBusSubscriber;
import net.neoforged.neoforge.event.RegisterGameTestsEvent;
import net.neoforged.neoforge.gametest.GameTestHolder;
import net.neoforged.neoforge.gametest.PrefixGameTestTemplate;

/**
 * Integration tests that run on a real (headless) server: shooting, damage, headshots, rewards, reload.
 * Run with {@code ./gradlew runGameTestServer}.
 */
@GameTestHolder(WardogsMod.MOD_ID)
@PrefixGameTestTemplate(false)
public final class WardogsGameTests {
    private static final double RANGE = 7.0;

    private WardogsGameTests() {}

    @EventBusSubscriber(modid = WardogsMod.MOD_ID)
    public static final class Registration {
        private Registration() {}

        @SubscribeEvent
        public static void register(RegisterGameTestsEvent e) {
            e.register(WardogsGameTests.class);
        }
    }

    // --- helpers -----------------------------------------------------------------------------

    private record Arena(ServerPlayer shooter, Zombie target, ServerLevel level) {}

    private static Arena arena(GameTestHelper h) {
        for (int x = 0; x < 12; x++) {
            for (int z = 0; z < 12; z++) {
                h.setBlock(new BlockPos(x, 0, z), Blocks.STONE);
            }
        }
        ServerPlayer shooter = h.makeMockServerPlayerInLevel();
        Vec3 sp = h.absoluteVec(new Vec3(5.5, 1.0, 1.5));
        shooter.moveTo(sp.x, sp.y, sp.z, 0f, 0f);
        Zombie z = h.spawnWithNoFreeWill(EntityType.ZOMBIE, new Vec3(5.5, 1.0, 1.5 + RANGE));
        return new Arena(shooter, z, h.getLevel());
    }

    /** Points the shooter at a world height on the target (spread is far below the 0.6 m hitbox at this range). */
    private static void aimAt(ServerPlayer p, Zombie z, double y) {
        Vec3 eye = p.getEyePosition();
        double dx = z.getX() - eye.x, dz = z.getZ() - eye.z, dy = y - eye.y;
        float yaw = (float) (Math.toDegrees(Math.atan2(-dx, dz)));
        float pitch = (float) (-Math.toDegrees(Math.atan2(dy, Math.sqrt(dx * dx + dz * dz))));
        p.moveTo(p.getX(), p.getY(), p.getZ(), yaw, pitch);
    }

    private static void check(boolean cond, String msg) {
        if (!cond) {
            throw new AssertionError(msg);
        }
    }

    // --- tests -------------------------------------------------------------------------------

    @GameTest(template = "empty")
    public static void chestShotDamageMatchesBalance(GameTestHelper h) {
        Arena a = arena(h);
        WeaponDef m4 = Balance.get().weapon("m4");
        aimAt(a.shooter, a.target, a.target.getY() + 1.0);
        float before = a.target.getHealth();
        Ballistics.fire(a.level, a.shooter, m4, AmmoType.FMJ);
        float lost = before - a.target.getHealth();
        float expected = Balance.get().toMinecraftDamage(m4.chest());
        check(Math.abs(lost - expected) < 0.01f, "chest hit should cost " + expected + " hp but cost " + lost);
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void headshotUsesHeadDamage(GameTestHelper h) {
        Arena a = arena(h);
        WeaponDef m4 = Balance.get().weapon("m4");
        aimAt(a.shooter, a.target, a.target.getEyeY() + 0.05);
        float before = a.target.getHealth();
        Ballistics.fire(a.level, a.shooter, m4, AmmoType.FMJ);
        float lost = before - a.target.getHealth();
        float expected = Balance.get().toMinecraftDamage(m4.head());
        check(Math.abs(lost - expected) < 0.01f, "headshot should cost " + expected + " hp but cost " + lost);
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void hollowPointDoublesFleshDamage(GameTestHelper h) {
        Arena a = arena(h);
        WeaponDef m4 = Balance.get().weapon("m4");
        aimAt(a.shooter, a.target, a.target.getY() + 1.0);
        float before = a.target.getHealth();
        Ballistics.fire(a.level, a.shooter, m4, AmmoType.HP);
        float lost = before - a.target.getHealth();
        float expected = Balance.get().toMinecraftDamage(m4.chest() * 2);
        check(Math.abs(lost - expected) < 0.01f, "HP chest hit should cost " + expected + " hp but cost " + lost);
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void killPaysMoneyAndHeadshotPaysMore(GameTestHelper h) {
        Arena a = arena(h);
        WeaponDef m4 = Balance.get().weapon("m4");
        long start = Wallet.get(a.shooter);
        check(start == Balance.get().rewards().startingCash(), "new player should start with " + Balance.get().rewards().startingCash() + " but has " + start);
        for (int i = 0; i < 6 && a.target.isAlive(); i++) {
            aimAt(a.shooter, a.target, a.target.getEyeY() + 0.05);
            Ballistics.fire(a.level, a.shooter, m4, AmmoType.FMJ);
        }
        check(!a.target.isAlive(), "target should be dead after headshots");
        long paid = Wallet.get(a.shooter) - start;
        check(paid == 350, "a close headshot kill should pay $350 but paid $" + paid);
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void shotsConsumeAmmoAndEmptyGunStartsReload(GameTestHelper h) {
        Arena a = arena(h);
        GunItem gun = ModItems.GUNS.get("m1911").get();
        WeaponDef def = gun.def();
        ItemStack stack = gun.newStack();
        a.shooter.setItemInHand(InteractionHand.MAIN_HAND, stack);
        ShooterState st = ShooterState.of(a.shooter.getUUID());
        stack.set(ModComponents.AMMO.get(), 1);

        gun.use(a.level, a.shooter, InteractionHand.MAIN_HAND);
        check(GunItem.ammo(stack) == 0, "one shot should leave 0 rounds, has " + GunItem.ammo(stack));

        // Next click is a dry fire that starts the reload.
        st.nextShotTime = 0;
        gun.use(a.level, a.shooter, InteractionHand.MAIN_HAND);
        check(st.reloading(), "an empty gun must start reloading");

        h.runAfterDelay(def.reloadTicks() + 2L, () -> {
            gun.inventoryTick(stack, a.level, a.shooter, a.shooter.getInventory().selected, true);
            check(GunItem.ammo(stack) == def.magazine(), "reload should refill to " + def.magazine() + " but has " + GunItem.ammo(stack));
            check(!st.reloading(), "reload state must clear");
            ShooterState.remove(a.shooter.getUUID());
            h.succeed();
        });
    }

    @GameTest(template = "empty")
    public static void switchingWeaponCancelsReload(GameTestHelper h) {
        Arena a = arena(h);
        GunItem gun = ModItems.GUNS.get("m4").get();
        ItemStack stack = gun.newStack();
        stack.set(ModComponents.AMMO.get(), 3);
        a.shooter.setItemInHand(InteractionHand.MAIN_HAND, stack);
        ShooterState st = ShooterState.of(a.shooter.getUUID());
        gun.startReload(a.shooter, a.level, stack, gun.def(), st);
        check(st.reloading(), "reload should have started");
        gun.inventoryTick(stack, a.level, a.shooter, a.shooter.getInventory().selected, false);
        check(!st.reloading(), "deselecting the weapon must cancel the reload");
        check(GunItem.ammo(stack) == 3, "cancelled reload must not refill the magazine");
        ShooterState.remove(a.shooter.getUUID());
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void bulletsStopAtWalls(GameTestHelper h) {
        Arena a = arena(h);
        for (int y = 1; y <= 3; y++) {
            for (int x = 3; x <= 8; x++) {
                h.setBlock(new BlockPos(x, y, 4), Blocks.STONE);
            }
        }
        WeaponDef m4 = Balance.get().weapon("m4");
        aimAt(a.shooter, a.target, a.target.getY() + 1.0);
        float before = a.target.getHealth();
        Ballistics.fire(a.level, a.shooter, m4, AmmoType.FMJ);
        check(a.target.getHealth() == before, "a wall between shooter and target must block the bullet");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void helmetAbsorbsPartOfHeadshotDamage(GameTestHelper h) {
        Arena a = arena(h);
        WeaponDef m4 = Balance.get().weapon("m4");
        a.target.setItemSlot(net.minecraft.world.entity.EquipmentSlot.HEAD, ModItems.stack("helmet_l2"));
        double reduction = Balance.get().armor("helmet_l2").reduction();
        aimAt(a.shooter, a.target, a.target.getEyeY() + 0.05);
        float before = a.target.getHealth();
        Ballistics.fire(a.level, a.shooter, m4, AmmoType.FMJ);
        float lost = before - a.target.getHealth();
        float expected = Balance.get().toMinecraftDamage(m4.head() * (1.0 - reduction));
        check(Math.abs(lost - expected) < 0.01f, "helmet L2 headshot should cost " + expected + " hp but cost " + lost);
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void vestDoesNotProtectTheHead(GameTestHelper h) {
        Arena a = arena(h);
        WeaponDef m4 = Balance.get().weapon("m4");
        a.target.setItemSlot(net.minecraft.world.entity.EquipmentSlot.CHEST, ModItems.stack("vest_l4"));
        aimAt(a.shooter, a.target, a.target.getEyeY() + 0.05);
        float before = a.target.getHealth();
        Ballistics.fire(a.level, a.shooter, m4, AmmoType.FMJ);
        float lost = before - a.target.getHealth();
        float expected = Balance.get().toMinecraftDamage(m4.head());
        check(Math.abs(lost - expected) < 0.01f, "a vest must not reduce headshot damage: expected " + expected + " got " + lost);
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void killInsideControlZonePaysFiveTimes(GameTestHelper h) {
        Arena a = arena(h);
        MatchData d = MatchData.get(h.getLevel().getServer().overworld());
        boolean wasActive = d.active;
        double zx = d.zoneX, zz = d.zoneZ, zr = d.zoneRadius, hx = d.hotX, hz = d.hotZ, hr = d.hotRadius;
        try {
            d.active = true;
            d.zoneX = a.target.getX();
            d.zoneZ = a.target.getZ();
            d.zoneRadius = 30;
            d.hotX = a.target.getX() + 1000; // Hot Zone far away
            d.hotZ = a.target.getZ() + 1000;
            d.hotRadius = 5;
            WeaponDef m4 = Balance.get().weapon("m4");
            long start = Wallet.get(a.shooter);
            for (int i = 0; i < 6 && a.target.isAlive(); i++) {
                aimAt(a.shooter, a.target, a.target.getEyeY() + 0.05);
                Ballistics.fire(a.level, a.shooter, m4, AmmoType.FMJ);
            }
            long paid = Wallet.get(a.shooter) - start;
            check(paid == 350 * 5, "headshot kill in the Control Zone should pay $1750 but paid $" + paid);
        } finally {
            d.active = wasActive;
            d.zoneX = zx; d.zoneZ = zz; d.zoneRadius = zr; d.hotX = hx; d.hotZ = hz; d.hotRadius = hr;
        }
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void buyCommandChargesMoneyAndGivesItem(GameTestHelper h) {
        Arena a = arena(h);
        long start = Wallet.get(a.shooter);
        int price = Balance.get().weapon("m4").price();
        var cmds = h.getLevel().getServer().getCommands();
        cmds.performPrefixedCommand(a.shooter.createCommandSourceStack(), "wd buy m4");
        check(Wallet.get(a.shooter) == start - price, "buying an M4 should cost $" + price + " but wallet went " + start + " -> " + Wallet.get(a.shooter));
        boolean has = false;
        for (ItemStack s : a.shooter.getInventory().items) {
            has |= s.getItem() == ModItems.GUNS.get("m4").get();
        }
        check(has, "bought M4 should be in the inventory");
        Wallet.set(a.shooter, 10);
        cmds.performPrefixedCommand(a.shooter.createCommandSourceStack(), "wd buy m4");
        check(Wallet.get(a.shooter) == 10, "a purchase the player cannot afford must not charge anything");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void teamCommandSetsFaction(GameTestHelper h) {
        Arena a = arena(h);
        h.getLevel().getServer().getCommands().performPrefixedCommand(a.shooter.createCommandSourceStack(), "wd team valkyra");
        check(Factions.of(a.shooter) == Faction.VALKYRA, "faction should be Valkyra, is " + Factions.of(a.shooter));
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void zoneLeaderScoresAndTieDoesNot(GameTestHelper h) {
        ServerLevel ow = h.getLevel().getServer().overworld();
        MatchData d = MatchData.get(ow);
        boolean wasActive = d.active;
        double zx = d.zoneX, zz = d.zoneZ, zr = d.zoneRadius, hx = d.hotX, hz = d.hotZ, hr = d.hotRadius;
        int[] saved = d.scores.clone();
        try {
            Vec3 c = h.absoluteVec(new Vec3(5.5, 1.0, 5.5));
            d.active = true;
            d.zoneX = c.x; d.zoneZ = c.z; d.zoneRadius = 20;
            d.hotX = c.x + 1000; d.hotZ = c.z; d.hotRadius = 5;
            java.util.Arrays.fill(d.scores, 0);

            ServerPlayer a1 = h.makeMockServerPlayerInLevel();
            ServerPlayer a2 = h.makeMockServerPlayerInLevel();
            ServerPlayer b1 = h.makeMockServerPlayerInLevel();
            for (ServerPlayer p : new ServerPlayer[]{a1, a2, b1}) {
                p.moveTo(c.x, c.y, c.z, 0f, 0f);
            }
            Factions.set(a1, Faction.LONESTAR);
            Factions.set(a2, Faction.LONESTAR);
            Factions.set(b1, Faction.VALKYRA);

            check(ow.players().contains(a1) && ow.players().contains(b1), "mock players must be tracked by the level for this test to mean anything");
            {
                MatchManager.scoreTick(ow, d);
                check(d.scores[Faction.LONESTAR.ordinal()] == 1, "2 vs 1 in the zone: Lonestar should score 1, scores=" + java.util.Arrays.toString(d.scores));
                check(d.scores[Faction.VALKYRA.ordinal()] == 0, "the minority must not score");
                Factions.set(a2, Faction.VALKYRA); // now 1 v 1: tie
                MatchManager.scoreTick(ow, d);
                check(d.scores[Faction.LONESTAR.ordinal()] == 1, "a tie must not score");
            }
        } finally {
            d.active = wasActive;
            d.zoneX = zx; d.zoneZ = zz; d.zoneRadius = zr; d.hotX = hx; d.hotZ = hz; d.hotRadius = hr;
            System.arraycopy(saved, 0, d.scores, 0, saved.length);
        }
        h.succeed();
    }
}
