package com.wardogs.gametest;

import com.wardogs.WardogsMod;
import com.wardogs.balance.AmmoType;
import com.wardogs.balance.Balance;
import com.wardogs.balance.WeaponDef;
import com.wardogs.combat.Ballistics;
import com.wardogs.combat.PlayerHealth;
import com.wardogs.combat.ShooterState;
import com.wardogs.economy.Wallet;
import com.wardogs.match.Faction;
import com.wardogs.match.Factions;
import com.wardogs.match.MatchData;
import com.wardogs.match.MatchManager;
import com.wardogs.explosive.Blast;
import com.wardogs.explosive.PlacedCharge;
import com.wardogs.item.ExplosiveItem;
import com.wardogs.build.Building;
import com.wardogs.build.Buildable;
import com.wardogs.item.HammerItem;
import com.wardogs.build.Artillery;
import com.wardogs.vendor.VendorEntities;
import com.wardogs.vendor.VendorEntity;
import com.wardogs.world.BakuraniSetup;
import com.wardogs.world.BakuraniWorld;
import com.wardogs.build.Emplacements;
import com.wardogs.item.GunStats;
import com.wardogs.item.AttachmentItem;
import com.wardogs.item.AmmoBoxItem;
import com.wardogs.explosive.RocketEntity;
import com.wardogs.vehicle.Vehicle;
import com.wardogs.vehicle.VehicleGuns;
import com.wardogs.item.GunItem;
import com.wardogs.progress.Progression;
import com.wardogs.progress.Role;
import com.wardogs.shop.Shop;
import com.wardogs.item.MedicalItem;
import com.wardogs.medic.Downed;
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

    /** World height of the upper torso: the zone the weapon table's chest damage refers to. */
    private static double chestY(net.minecraft.world.entity.LivingEntity e) {
        return e.getY() + e.getBbHeight() * 0.75;
    }

    /** Points the shooter at a world height on the target (spread is far below the 0.6 m hitbox at this range). */
    private static void aimAt(ServerPlayer p, Zombie z, double y) {
        Vec3 eye = p.getEyePosition();
        double dx = z.getX() - eye.x, dz = z.getZ() - eye.z, dy = y - eye.y;
        float yaw = (float) (Math.toDegrees(Math.atan2(-dx, dz)));
        float pitch = (float) (-Math.toDegrees(Math.atan2(dy, Math.sqrt(dx * dx + dz * dz))));
        p.moveTo(p.getX(), p.getY(), p.getZ(), yaw, pitch);
    }

    /** Fresh server players have 3 s of spawn protection; tests that damage them must clear it. */
    private static void makeVulnerable(ServerPlayer p) {
        p.setGameMode(net.minecraft.world.level.GameType.SURVIVAL); // mock players start in creative
        net.neoforged.fml.util.ObfuscationReflectionHelper.setPrivateValue(ServerPlayer.class, p, 0, "spawnInvulnerableTime");
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
        aimAt(a.shooter, a.target, chestY(a.target));
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
        aimAt(a.shooter, a.target, chestY(a.target));
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
        check(paid == 450, "a close headshot kill with an assault rifle should pay $450 ($400 + $50) but paid $" + paid);
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

        later(h, def.reloadTicks() + 2L, () -> {
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
        aimAt(a.shooter, a.target, chestY(a.target));
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

    @GameTest(template = "empty", batch = "matchA")
    public static void killInsideControlZonePaysFiveTimes(GameTestHelper h) {
        Arena a = arena(h);
        MatchData d = MatchData.get(h.getLevel().getServer().overworld());
        boolean wasActive = d.active;
        String wasDim = d.dim;
        d.dim = "minecraft:overworld";
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
            check(paid == 450 * 5, "headshot kill with both soldiers inside the Control Zone should pay $2250 but paid $" + paid);
        } finally {
            d.active = wasActive;
            d.dim = wasDim;
            d.zoneX = zx; d.zoneZ = zz; d.zoneRadius = zr; d.hotX = hx; d.hotZ = hz; d.hotRadius = hr;
        }
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void buyCommandChargesMoneyAndGivesItem(GameTestHelper h) {
        Arena a = arena(h);
        long start = Wallet.get(a.shooter);
        int price = Balance.get().weapon("ggx17").price();
        var cmds = h.getLevel().getServer().getCommands();
        cmds.performPrefixedCommand(a.shooter.createCommandSourceStack(), "wd buy ggx17");
        check(Wallet.get(a.shooter) == start - price, "buying a GGX 17 should cost $" + price + " but wallet went " + start + " -> " + Wallet.get(a.shooter));
        boolean has = false;
        for (ItemStack s : a.shooter.getInventory().items) {
            has |= s.getItem() == ModItems.GUNS.get("ggx17").get();
        }
        check(has, "bought pistol should be in the inventory");
        Wallet.set(a.shooter, 10);
        cmds.performPrefixedCommand(a.shooter.createCommandSourceStack(), "wd buy ggx17");
        check(Wallet.get(a.shooter) == 10, "a purchase the player cannot afford must not charge anything");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void lockedItemsNeedLevelThenPaymentThenPurchase(GameTestHelper h) {
        Arena a = arena(h);
        ServerPlayer p = a.shooter;
        Wallet.set(p, 1_000_000);

        // M4 needs Assault 28 and $150 000 to unlock.
        check(!Shop.buy(p, "m4").ok(), "a fresh player cannot buy a locked M4");
        check(!Shop.unlock(p, "m4").ok(), "unlocking below the required level must fail");
        check(Wallet.get(p) == 1_000_000, "failed attempts must not charge");

        Progression.award(p, Role.ASSAULT, 700_000); // 0.4 XP per dollar = 280 000 XP, above the level-28 threshold
        check(Progression.level(p, Role.ASSAULT) >= 28, "assault level should be at least 28 but is " + Progression.level(p, Role.ASSAULT));
        check(!Shop.buy(p, "m4").ok(), "reaching the level is not enough: the unlock payment is also required");
        check(Shop.unlock(p, "m4").ok(), "unlocking at the right level with enough money should work");
        check(Wallet.get(p) == 1_000_000 - 150_000, "unlock costs $150 000, wallet is " + Wallet.get(p));
        check(!Shop.unlock(p, "m4").ok(), "a second unlock is refused");

        long before = Wallet.get(p);
        check(Shop.buy(p, "m4").ok(), "once unlocked the M4 can be bought");
        check(before - Wallet.get(p) == Balance.get().weapon("m4").price(), "the M4 costs its shop price");
        check(Shop.buy(p, "m4").ok(), "an unlock is permanent: buying again works");
        check(!Shop.buy(p, "no_such_item").ok(), "unknown items are refused");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void killsTrainTheWeaponsRoleAndTheGeneralBranch(GameTestHelper h) {
        Arena a = arena(h);
        WeaponDef m4 = Balance.get().weapon("m4");
        check(Progression.get(a.shooter).xpOf(Role.ASSAULT) == 0, "fresh player has no assault XP");
        for (int i = 0; i < 6 && a.target.isAlive(); i++) {
            aimAt(a.shooter, a.target, a.target.getEyeY() + 0.05);
            Ballistics.fire(a.level, a.shooter, m4, AmmoType.FMJ);
        }
        long expected = Balance.get().rewards().roleXp();
        check(Progression.get(a.shooter).xpOf(Role.ASSAULT) == expected, "a kill gives a flat " + expected + " assault XP, gave " + Progression.get(a.shooter).xpOf(Role.ASSAULT));
        check(Progression.get(a.shooter).xpOf(Role.WARDOG) == expected, "and the same amount of general WARDOG XP");
        check(Progression.get(a.shooter).xpOf(Role.MEDIC) == 0, "other branches are untouched");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void teamCommandSetsFaction(GameTestHelper h) {
        Arena a = arena(h);
        h.getLevel().getServer().getCommands().performPrefixedCommand(a.shooter.createCommandSourceStack(), "wd team valkyra");
        check(Factions.of(a.shooter) == Faction.VALKYRA, "faction should be Valkyra, is " + Factions.of(a.shooter));
        h.succeed();
    }

    @GameTest(template = "empty", batch = "matchB")
    public static void zoneLeaderScoresAndTieDoesNot(GameTestHelper h) {
        ServerLevel ow = h.getLevel().getServer().overworld();
        MatchData d = MatchData.get(ow);
        boolean wasActive = d.active;
        String wasDim = d.dim;
        d.dim = "minecraft:overworld";
        double zx = d.zoneX, zz = d.zoneZ, zr = d.zoneRadius, hx = d.hotX, hz = d.hotZ, hr = d.hotRadius;
        int[] saved = d.scores.clone();
        try {
            Vec3 c = h.absoluteVec(new Vec3(5.5, 1.0, 5.5));
            d.active = true;
            d.zoneX = c.x; d.zoneZ = c.z; d.zoneRadius = 4; // small: neighbouring test arenas can be only ~18 blocks away
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
            d.dim = wasDim;
            d.zoneX = zx; d.zoneZ = zz; d.zoneRadius = zr; d.hotX = hx; d.hotZ = hz; d.hotRadius = hr;
            System.arraycopy(saved, 0, d.scores, 0, saved.length);
        }
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void playersHave100HealthAndTakeRawBulletDamage(GameTestHelper h) {
        Arena a = arena(h);
        ServerPlayer victim = h.makeMockServerPlayerInLevel();
        Vec3 vp = h.absoluteVec(new Vec3(5.5, 1.0, 1.5 + RANGE));
        victim.moveTo(vp.x, vp.y, vp.z, 0f, 0f);
        h.getLevel().getServer().setPvpAllowed(true); // a match enables this itself
        makeVulnerable(victim);
        a.target.discard(); // keep the zombie out of the line of fire
        PlayerHealth.apply(victim);
        victim.setHealth(victim.getMaxHealth());
        check(victim.getMaxHealth() == 100f, "max health should be 100, is " + victim.getMaxHealth());

        WeaponDef m4 = Balance.get().weapon("m4");
        Vec3 eye = a.shooter.getEyePosition();
        double dx = victim.getX() - eye.x, dz = victim.getZ() - eye.z, dy = chestY(victim) - eye.y;
        a.shooter.moveTo(a.shooter.getX(), a.shooter.getY(), a.shooter.getZ(), (float) Math.toDegrees(Math.atan2(-dx, dz)),
                (float) -Math.toDegrees(Math.atan2(dy, Math.sqrt(dx * dx + dz * dz))));
        Ballistics.fire(a.level, a.shooter, m4, AmmoType.FMJ);
        float lost = 100f - victim.getHealth();
        check(Math.abs(lost - (float) m4.chest()) < 0.01f, "an M4 chest hit should cost a 100-HP player exactly " + m4.chest() + " but cost " + lost);
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void vanillaDamageIsScaledForPlayers(GameTestHelper h) {
        ServerPlayer p = h.makeMockServerPlayerInLevel();
        makeVulnerable(p);
        PlayerHealth.apply(p);
        p.setHealth(p.getMaxHealth());
        p.hurt(p.level().damageSources().generic(), 2.0f); // 2 vanilla HP = 10% of vanilla health
        float lost = 100f - p.getHealth();
        check(Math.abs(lost - 10f) < 0.01f, "2 vanilla damage should cost 10 of 100 HP, cost " + lost
);
        h.succeed();
    }

    // --- downed / revive ---------------------------------------------------------------------

    private static ServerPlayer soldier(GameTestHelper h, Faction f, double x, double z) {
        ServerPlayer p = h.makeMockServerPlayerInLevel();
        makeVulnerable(p);
        PlayerHealth.apply(p);
        p.setHealth(p.getMaxHealth());
        Vec3 pos = h.absoluteVec(new Vec3(x, 1.0, z));
        p.moveTo(pos.x, pos.y, pos.z, 0f, 0f);
        Factions.set(p, f);
        return p;
    }

    @GameTest(template = "empty")
    public static void fatalHitDownsACombatantAndPaysTheKillOnce(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        h.getLevel().getServer().setPvpAllowed(true);
        Factions.set(a.shooter, Faction.LONESTAR);
        ServerPlayer victim = soldier(h, Faction.VALKYRA, 5.5, 1.5 + RANGE);
        victim.setHealth(5f);
        WeaponDef m4 = Balance.get().weapon("m4");
        long before = Wallet.get(a.shooter);

        Vec3 eye = a.shooter.getEyePosition();
        double dx = victim.getX() - eye.x, dz = victim.getZ() - eye.z, dy = (victim.getY() + 1.0) - eye.y;
        a.shooter.moveTo(a.shooter.getX(), a.shooter.getY(), a.shooter.getZ(), (float) Math.toDegrees(Math.atan2(-dx, dz)),
                (float) -Math.toDegrees(Math.atan2(dy, Math.sqrt(dx * dx + dz * dz))));
        Ballistics.fire(a.level, a.shooter, m4, AmmoType.FMJ);

        check(victim.isAlive() && Downed.is(victim), "a fatal hit on a combatant must down them, not kill them");
        check(Math.abs(victim.getHealth() - (float) Balance.get().downedHp()) < 0.01f, "downed health should be " + Balance.get().downedHp() + " but is " + victim.getHealth());
        check(Wallet.get(a.shooter) - before == 400, "the kill should pay $400 when the victim goes down, paid " + (Wallet.get(a.shooter) - before));

        // Finishing the downed player is a real death and pays nothing more.
        long afterDown = Wallet.get(a.shooter);
        for (int i = 0; i < 4 && victim.isAlive(); i++) {
            Ballistics.fire(a.level, a.shooter, m4, AmmoType.FMJ);
        }
        check(!victim.isAlive(), "enough damage while downed must kill for real");
        check(Wallet.get(a.shooter) == afterDown, "finishing a downed player must not pay again");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void handReviveStandsUpAndPaysWithRepeatPenalty(GameTestHelper h) {
        ServerPlayer medic = soldier(h, Faction.LONESTAR, 5.5, 1.5);
        ServerPlayer mate = soldier(h, Faction.LONESTAR, 5.5, 3.0);
        var def = Balance.get().medical("field_resuscitator");
        ItemStack kit = ModItems.stack("field_resuscitator");
        kit.setCount(3);
        long start = Wallet.get(medic);

        Downed.down(mate);
        check(Downed.is(mate), "mate should be down");
        check(Downed.findTarget(medic) == mate, "the medic looking at the downed mate must find them");
        MedicalItem.completeRevive(medic, mate, kit, def);
        check(!Downed.is(mate), "revived mate must be upright");
        check(Math.abs(mate.getHealth() - (float) def.hp()) < 0.01f, "revived with " + def.hp() + " HP, has " + mate.getHealth());
        check(Wallet.get(medic) - start == 400, "first open-ground revive pays $400, paid " + (Wallet.get(medic) - start));
        check(kit.getCount() == 2, "a field resuscitator is single use");

        Downed.down(mate);
        long mid = Wallet.get(medic);
        MedicalItem.completeRevive(medic, mate, kit, def);
        check(Wallet.get(medic) - mid == 300, "reviving the same mate again pays a quarter less ($300), paid " + (Wallet.get(medic) - mid));
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void defibrillatorUsesChargesAndPaysMore(GameTestHelper h) {
        ServerPlayer medic = soldier(h, Faction.VALKYRA, 5.5, 1.5);
        ServerPlayer mate = soldier(h, Faction.VALKYRA, 5.5, 3.0);
        var def = Balance.get().medical("defibrillator");
        ItemStack defib = ModItems.stack("defibrillator");
        check(MedicalItem.charges(defib) == def.charges(), "a new defibrillator starts fully charged");
        long start = Wallet.get(medic);
        Downed.down(mate);
        MedicalItem.completeRevive(medic, mate, defib, def);
        check(MedicalItem.charges(defib) == def.charges() - 1, "a revive spends one charge");
        check(Wallet.get(medic) - start == 600, "defibrillator revive pays $600 on open ground, paid " + (Wallet.get(medic) - start));
        check(Math.abs(mate.getHealth() - (float) def.hp()) < 0.01f, "defibrillator revives with " + def.hp() + " HP");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void enemiesCannotBeRevivedAndBleedOutKills(GameTestHelper h) {
        ServerPlayer medic = soldier(h, Faction.LONESTAR, 5.5, 1.5);
        ServerPlayer enemy = soldier(h, Faction.MANTICORE, 5.5, 3.0);
        Downed.down(enemy);
        check(Downed.findTarget(medic) == null, "an enemy must not be revivable");

        for (int i = 0; i < Balance.get().bleedoutTicks() + 2 && enemy.isAlive(); i++) {
            Downed.tick(enemy);
        }
        check(!enemy.isAlive(), "a downed player nobody revives must bleed out");
        check(!Downed.is(enemy), "bleed-out clears the downed state");
        h.succeed();
    }

    // --- explosives --------------------------------------------------------------------------

    /** A husk: a zombie that does not burn in daylight, so delayed tests see only our damage. */
    private static Zombie zombieAt(GameTestHelper h, double x, double z) {
        return h.spawnWithNoFreeWill(EntityType.HUSK, new Vec3(x, 1.0, z));
    }

    /** Runs delayed test code; an assertion becomes a test failure instead of crashing the server. */
    private static void later(GameTestHelper h, long delay, Runnable r) {
        h.runAfterDelay(delay, () -> {
            try {
                r.run();
            } catch (AssertionError e) {
                h.fail(String.valueOf(e.getMessage()));
            }
        });
    }

    @GameTest(template = "empty")
    public static void blastDamageFallsOffWithDistance(GameTestHelper h) {
        arena(h).target.discard();
        Zombie near = zombieAt(h, 3.5, 5.5);
        Zombie far = zombieAt(h, 8.5, 5.5);
        var frag = Balance.get().explosive("m67_frag");
        Vec3 c = h.absoluteVec(new Vec3(5.5, 1.5, 5.5));
        Blast.explode(h.getLevel(), c, frag.radius(), frag.damage(), null, frag.id());
        float nearLost = 20f - near.getHealth();
        float farLost = 20f - far.getHealth();
        double nearDist = near.getBoundingBox().getCenter().distanceTo(c);
        float expected = Balance.get().damageTo(near, Blast.falloff(frag.damage(), frag.radius(), nearDist));
        check(Math.abs(nearLost - Math.min(20f, expected)) < 0.05f, "near zombie should lose " + expected + " but lost " + nearLost);
        check(farLost < nearLost, "damage must fall with distance: near " + nearLost + " far " + farLost);
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void wallsBlockExplosions(GameTestHelper h) {
        arena(h).target.discard();
        Zombie behindWall = zombieAt(h, 8.5, 5.5);
        for (int y = 1; y <= 3; y++) {
            for (int z = 3; z <= 8; z++) {
                h.setBlock(new BlockPos(7, y, z), Blocks.STONE);
            }
        }
        var frag = Balance.get().explosive("m67_frag");
        Blast.explode(h.getLevel(), h.absoluteVec(new Vec3(5.5, 1.5, 5.5)), frag.radius(), frag.damage(), null, frag.id());
        check(behindWall.getHealth() == 20f, "a zombie behind a wall must be shielded, health " + behindWall.getHealth());
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void thrownGrenadeExplodesAfterItsFuse(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        Zombie z = zombieAt(h, 5.5, 3.0);
        ItemStack stack = ModItems.stack("m67_frag");
        a.shooter.setItemInHand(InteractionHand.MAIN_HAND, stack);
        a.shooter.moveTo(a.shooter.getX(), a.shooter.getY(), a.shooter.getZ(), 0f, 80f); // drop it almost at the thrower's feet
        var item = (ExplosiveItem) stack.getItem();
        a.shooter.setGameMode(net.minecraft.world.level.GameType.SURVIVAL);
        item.use(a.level, a.shooter, InteractionHand.MAIN_HAND);
        check(stack.isEmpty(), "a thrown grenade is consumed");
        check(z.getHealth() == 20f, "nothing happens before the fuse runs out");
        long delay = Balance.get().explosive("m67_frag").fuse() + 30L;
        later(h, delay, () -> {
            check(z.getHealth() < 20f || !z.isAlive(), "the grenade should have hurt the zombie standing near the landing spot, health " + z.getHealth());
            h.succeed();
        });
    }

    @GameTest(template = "empty")
    public static void c4NeedsTheDetonator(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        Zombie z = zombieAt(h, 5.5, 4.0);
        var c4 = Balance.get().explosive("c4");
        Vec3 spot = h.absoluteVec(new Vec3(5.5, 1.0, 5.0));
        PlacedCharge.place(h.getLevel(), c4, spot, 0f, a.shooter.getUUID());
        later(h, PlacedCharge.ARM_TICKS + 10L, () -> {
            check(z.getHealth() == 20f, "C4 must not go off on its own");
            int n = ExplosiveItem.detonateAll(h.getLevel(), a.shooter.getUUID());
            check(n == 1, "the detonator should set off the one placed charge, set off " + n);
            check(z.getHealth() < 20f || !z.isAlive(), "the zombie one block away must be hurt, health " + z.getHealth());
            check(ExplosiveItem.detonateAll(h.getLevel(), a.shooter.getUUID()) == 0, "a used charge is gone");
            h.succeed();
        });
    }

    @GameTest(template = "empty")
    public static void claymoreHitsInFrontButNotBehind(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        Zombie front = zombieAt(h, 5.5, 8.0);
        Zombie back = zombieAt(h, 5.5, 2.0);
        var clay = Balance.get().explosive("claymore");
        PlacedCharge.place(h.getLevel(), clay, h.absoluteVec(new Vec3(5.5, 1.0, 5.0)), 0f, a.shooter.getUUID()); // faces +Z
        later(h, PlacedCharge.ARM_TICKS + 20L, () -> {
            check(front.getHealth() < 20f || !front.isAlive(), "the zombie in front should trigger and be hit, health " + front.getHealth());
            check(back.getHealth() == 20f, "the zombie behind the claymore must be safe, health " + back.getHealth());
            h.succeed();
        });
    }

    @GameTest(template = "empty")
    public static void antiTankMineIgnoresInfantryButHitsVehicles(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        Zombie walker = zombieAt(h, 5.5, 5.5);
        var mine = Balance.get().explosive("at_mine");
        PlacedCharge m = PlacedCharge.place(h.getLevel(), mine, h.absoluteVec(new Vec3(5.5, 1.0, 5.5)), 0f, a.shooter.getUUID());
        later(h, PlacedCharge.ARM_TICKS + 20L, () -> {
            check(!m.isRemoved(), "an anti-tank mine must not trigger on a soldier");
            check(walker.getHealth() == 20f, "infantry standing on the mine is unharmed");
            h.getLevel().addFreshEntity(new net.minecraft.world.entity.vehicle.Minecart(EntityType.MINECART, h.getLevel()) {{
                setPos(m.getX(), m.getY(), m.getZ());
            }});
            later(h, 10L, () -> {
                check(m.isRemoved(), "a vehicle rolling over the mine must set it off");
                h.succeed();
            });
        });
    }

    // --- building ----------------------------------------------------------------------------

    private static Vec3 ground(GameTestHelper h, double x, double z) {
        return h.absoluteVec(new Vec3(x, 1.0, z));
    }

    private static ServerPlayer builder(GameTestHelper h, Faction f) {
        Arena a = arena(h);
        a.target.discard();
        Factions.set(a.shooter, f);
        return a.shooter;
    }

    @GameTest(template = "empty")
    public static void hammerTiersUnlockProgressivelyLargerStructures(GameTestHelper h) {
        var small = (HammerItem) ModItems.BUILDING.get("hammer_small").get();
        var medium = (HammerItem) ModItems.BUILDING.get("hammer_medium").get();
        var large = (HammerItem) ModItems.BUILDING.get("hammer_large").get();
        check(small.allowed().stream().anyMatch(d -> d.id().equals("sandbag_wall")), "a small hammer builds sandbags");
        check(small.allowed().stream().noneMatch(d -> d.id().equals("hesco_wall")), "a small hammer cannot build HESCO walls");
        check(medium.allowed().stream().anyMatch(d -> d.id().equals("hesco_wall")), "a medium hammer builds HESCO walls");
        check(medium.allowed().stream().noneMatch(d -> d.id().equals("bunker")), "a medium hammer cannot build bunkers");
        check(large.allowed().stream().anyMatch(d -> d.id().equals("bunker")), "a large hammer builds bunkers");
        check(large.allowed().stream().noneMatch(d -> d.id().equals("fob")), "the FOB is deployed from a kit, not built");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void smallStructuresUseInventorySuppliesWithoutAFob(GameTestHelper h) {
        ServerPlayer p = builder(h, Faction.LONESTAR);
        var def = Balance.get().structure("sandbag_wall");
        check(!Building.place(p, h.getLevel(), def, ground(h, 5.5, 6.5), 0f).ok(), "building without supplies must fail");
        p.getInventory().add(ModItems.stack("build_supplies"));
        check(Building.inventorySupplies(p) == 50, "a stack is 50 supplies");
        var r = Building.place(p, h.getLevel(), def, ground(h, 5.5, 6.5), 0f);
        check(r.ok(), "a sandbag wall should be buildable: " + r.message().getString());
        check(Building.inventorySupplies(p) == 50 - def.cost(), "building spends " + def.cost() + " supplies, left " + Building.inventorySupplies(p));
        var walls = h.getLevel().getEntitiesOfClass(Buildable.class, new net.minecraft.world.phys.AABB(ground(h, 5.5, 6.5), ground(h, 5.5, 6.5)).inflate(2));
        check(walls.size() == 1 && walls.get(0).health() == (float) def.hp(), "one wall with full health " + def.hp());
        check(!Building.place(p, h.getLevel(), def, ground(h, 5.5, 6.5), 0f).ok(), "two structures cannot overlap");
        h.succeed();
    }

    @GameTest(template = "empty", batch = "fobA")
    public static void mediumStructuresNeedAFobAndDrawFromIt(GameTestHelper h) {
        ServerPlayer p = builder(h, Faction.VALKYRA);
        var hesco = Balance.get().structure("hesco_wall");
        p.getInventory().add(ModItems.stack("build_supplies"));
        check(!Building.place(p, h.getLevel(), hesco, ground(h, 5.5, 6.5), 0f).ok(), "a HESCO wall cannot be built without a FOB, even with supplies in the bag");

        var dep = Building.deployFob(p, h.getLevel(), ground(h, 2.5, 9.5));
        check(dep.ok(), "FOB should deploy: " + dep.message().getString());
        check(!Building.deployFob(p, h.getLevel(), ground(h, 9.5, 9.5)).ok(), "a second FOB inside the first one's radius is refused");
        check(!Building.place(p, h.getLevel(), hesco, ground(h, 5.5, 6.5), 0f).ok(), "an empty FOB cannot pay for a wall");

        var fob = h.getLevel().getEntitiesOfClass(Buildable.class, new net.minecraft.world.phys.AABB(ground(h, 2.5, 9.5), ground(h, 2.5, 9.5)).inflate(3)).get(0);
        check(fob.isFob() && fob.health() == 15000f, "the FOB has 15000 HP");
        fob.addSupply("build", 40);
        var r = Building.place(p, h.getLevel(), hesco, ground(h, 5.5, 6.5), 0f);
        check(r.ok(), "with supplies in the FOB the wall can be built: " + r.message().getString());
        check(fob.supply("build") == 40 - hesco.cost(), "the FOB paid " + hesco.cost() + ", has " + fob.supply("build"));
        check(Building.inventorySupplies(p) == 50, "the player's own supplies were not touched");
        h.succeed();
    }

    @GameTest(template = "empty", batch = "fobB")
    public static void supplyStacksAreStoredInTheFobAndRepairsUseThem(GameTestHelper h) {
        ServerPlayer p = builder(h, Faction.MANTICORE);
        check(Building.deployFob(p, h.getLevel(), ground(h, 3.5, 8.5)).ok(), "FOB deploys");
        var fob = h.getLevel().getEntitiesOfClass(Buildable.class, new net.minecraft.world.phys.AABB(ground(h, 3.5, 8.5), ground(h, 3.5, 8.5)).inflate(3)).get(0);

        p.setItemInHand(InteractionHand.MAIN_HAND, ModItems.stack("build_supplies"));
        fob.interact(p, InteractionHand.MAIN_HAND);
        check(fob.supply("build") == 50, "right-clicking the FOB with a stack stores all 50, has " + fob.supply("build"));
        check(p.getMainHandItem().isEmpty(), "the stack left the hand");
        p.setItemInHand(InteractionHand.MAIN_HAND, ModItems.stack("fuel_supplies"));
        fob.interact(p, InteractionHand.MAIN_HAND);
        check(fob.supply("fuel") == 50 && fob.supply("build") == 50, "fuel is stored separately from build supplies");

        var wall = Buildable.create(h.getLevel(), Balance.get().structure("hesco_wall"), ground(h, 7.5, 3.5), 0f, Faction.MANTICORE.ordinal(), p.getUUID());
        wall.damageStructure(1000, Faction.VALKYRA.ordinal());
        check(Math.abs(wall.health() - 3600f) < 0.01f, "wall at 3600 HP, is " + wall.health());
        p.setItemInHand(InteractionHand.MAIN_HAND, ModItems.stack("hammer_small"));
        wall.interact(p, InteractionHand.MAIN_HAND);
        check(wall.health() == 4600f, "ten supplies repair 1000 HP, wall is " + wall.health());
        check(fob.supply("build") == 40, "repair drew 10 supplies from the FOB, has " + fob.supply("build"));
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void explosiveHitCountsFollowTheTable(GameTestHelper h) {
        var lvl = h.getLevel();
        var hesco = Balance.get().structure("hesco_wall");
        var wall = Buildable.create(lvl, hesco, ground(h, 3.5, 3.5), 0f, Faction.LONESTAR.ordinal(), null);
        int enemy = Faction.VALKYRA.ordinal();
        for (int i = 1; i < hesco.hitsC4(); i++) {
            check(!wall.damageStructure(hesco.hitDamage("c4", Balance.get()), enemy), "C4 hit " + i + " must not destroy a HESCO wall yet");
        }
        check(wall.damageStructure(hesco.hitDamage("c4", Balance.get()), enemy), "the 8th C4 destroys a HESCO wall");
        check(wall.isRemoved(), "destroyed structures disappear");

        var sandbag = Buildable.create(lvl, Balance.get().structure("sandbag_wall"), ground(h, 8.5, 3.5), 0f, Faction.LONESTAR.ordinal(), null);
        check(sandbag.damageStructure(sandbag.def().hitDamage("c4", Balance.get()), enemy), "one C4 destroys a sandbag wall");

        var mine = Buildable.create(lvl, hesco, ground(h, 3.5, 8.5), 0f, Faction.LONESTAR.ordinal(), null);
        check(!mine.damageStructure(mine.def().hitDamage("c4", Balance.get()), Faction.LONESTAR.ordinal()), "friendly explosives do not damage your own structures");
        check(mine.health() == 4600f, "no damage taken from the same faction");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void aRealC4BlastDestroysAnAdjacentSandbagWall(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        Factions.set(a.shooter, Faction.LONESTAR);
        var sandbag = Buildable.create(h.getLevel(), Balance.get().structure("sandbag_wall"), ground(h, 5.5, 6.5), 0f, Faction.VALKYRA.ordinal(), null);
        var c4 = Balance.get().explosive("c4");
        Blast.explode(h.getLevel(), ground(h, 5.5, 5.0).add(0, 0.2, 0), c4.radius(), c4.damage(), a.shooter, "c4");
        check(sandbag.isRemoved(), "an enemy C4 next to a sandbag wall destroys it");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void wallsStopBullets(GameTestHelper h) {
        Arena a = arena(h);
        var wall = Buildable.create(h.getLevel(), Balance.get().structure("hesco_wall"), ground(h, 5.5, 1.5 + RANGE / 2), 0f, Faction.VALKYRA.ordinal(), null);
        WeaponDef m4 = Balance.get().weapon("m4");
        aimAt(a.shooter, a.target, chestY(a.target));
        float before = a.target.getHealth();
        Ballistics.fire(a.level, a.shooter, m4, AmmoType.FMJ);
        check(a.target.getHealth() == before, "a HESCO wall between shooter and target must absorb the bullet");
        check(wall.health() == 4600f, "bullets do not damage structures");
        h.succeed();
    }

    // --- vehicles ----------------------------------------------------------------------------

    private static Vehicle vehicleAt(GameTestHelper h, String id, Faction f, double x, double z) {
        return Vehicle.spawn(h.getLevel(), Balance.get().vehicle(id), ground(h, x, z), 0f, f == null ? -1 : f.ordinal());
    }

    @GameTest(template = "empty")
    public static void vehiclesSpawnWithTheirConfiguredStats(GameTestHelper h) {
        arena(h).target.discard();
        var def = Balance.get().vehicle("humvee");
        Vehicle v = vehicleAt(h, "humvee", Faction.LONESTAR, 6.5, 6.5);
        check(Math.abs(v.getMaxHealth() - (float) def.hp()) < 0.01f, "max health should be " + def.hp() + " but is " + v.getMaxHealth());
        check(Math.abs(v.getHealth() - (float) def.hp()) < 0.01f, "spawns undamaged");
        check(v.fuel() == (float) def.fuel(), "spawns with a full tank of " + def.fuel() + ", has " + v.fuel());
        check(v.faction() == Faction.LONESTAR.ordinal(), "belongs to the spawner's faction");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void smallArmsBarelyScratchATankButHurtACar(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        WeaponDef m4 = Balance.get().weapon("m4");
        Vehicle tank = vehicleAt(h, "l2a6", Faction.VALKYRA, 5.5, 1.5 + RANGE);
        Vehicle car = vehicleAt(h, "bobcat", Faction.VALKYRA, 9.5, 1.5 + RANGE);
        Vec3 eye = a.shooter.getEyePosition();
        for (Vehicle v : new Vehicle[]{tank, car}) {
            double dx = v.getX() - eye.x, dz = v.getZ() - eye.z, dy = (v.getY() + v.getBbHeight() / 2) - eye.y;
            a.shooter.moveTo(a.shooter.getX(), a.shooter.getY(), a.shooter.getZ(), (float) Math.toDegrees(Math.atan2(-dx, dz)),
                    (float) -Math.toDegrees(Math.atan2(dy, Math.sqrt(dx * dx + dz * dz))));
            Ballistics.fire(a.level, a.shooter, m4, AmmoType.FMJ);
        }
        float tankLost = tank.getMaxHealth() - tank.getHealth();
        float carLost = car.getMaxHealth() - car.getHealth();
        float tankExpected = (float) (m4.chest() * Balance.get().ammoMult(AmmoType.FMJ, true) * tank.def().smallArms());
        float carExpected = (float) (m4.chest() * Balance.get().ammoMult(AmmoType.FMJ, true) * car.def().smallArms());
        check(Math.abs(tankLost - tankExpected) < 0.05f, "tank should lose " + tankExpected + " but lost " + tankLost);
        check(Math.abs(carLost - carExpected) < 0.05f, "bobcat should lose " + carExpected + " but lost " + carLost);
        check(carLost > tankLost * 5, "a car takes far more from rifle fire than a tank");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void destroyingAnEnemyVehiclePaysItsRewardOnce(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        Factions.set(a.shooter, Faction.LONESTAR);
        Vehicle car = vehicleAt(h, "dune_buggy", Faction.VALKYRA, 6.5, 6.5);
        long before = Wallet.get(a.shooter);
        var mine = Balance.get().explosive("at_mine");
        Blast.explode(h.getLevel(), car.position().add(0, 0.5, 0), mine.radius(), mine.damage(), a.shooter, "at_mine");
        check(!car.isAlive(), "an anti-tank mine blast must destroy a dune buggy, health " + car.getHealth());
        check(Wallet.get(a.shooter) - before == 300, "destroying a dune buggy pays $300, paid " + (Wallet.get(a.shooter) - before));
        check(Progression.get(a.shooter).xpOf(Role.WARDOG) > 0, "and awards general XP");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void ownFactionVehicleDestructionPaysNothing(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        Factions.set(a.shooter, Faction.MANTICORE);
        Vehicle car = vehicleAt(h, "bobcat", Faction.MANTICORE, 6.5, 6.5);
        long before = Wallet.get(a.shooter);
        var mine = Balance.get().explosive("at_mine");
        Blast.explode(h.getLevel(), car.position().add(0, 0.5, 0), mine.radius(), mine.damage(), a.shooter, "at_mine");
        check(!car.isAlive(), "the blast still destroys it");
        check(Wallet.get(a.shooter) == before, "wrecking your own side's vehicle pays nothing");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void realMineTriggersOnAVehicleEntity(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        Factions.set(a.shooter, Faction.LONESTAR);
        Vehicle truck = vehicleAt(h, "kodiak", Faction.VALKYRA, 6.5, 6.5);
        var mine = Balance.get().explosive("at_mine");
        PlacedCharge m = PlacedCharge.place(h.getLevel(), mine, ground(h, 6.5, 6.5), 0f, a.shooter.getUUID());
        later(h, PlacedCharge.ARM_TICKS + 15L, () -> {
            check(m.isRemoved(), "the mine must detonate under a real vehicle");
            check(truck.getHealth() < truck.getMaxHealth() || !truck.isAlive(), "and damage it");
            h.succeed();
        });
    }

    @GameTest(template = "empty")
    public static void fuelSuppliesRefillAndWrenchesRepair(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        Factions.set(a.shooter, Faction.LONESTAR);
        var def = Balance.get().vehicle("humvee");
        Vehicle v = vehicleAt(h, "humvee", Faction.LONESTAR, 6.5, 6.5);
        v.setFuel(10f);
        a.shooter.setItemInHand(InteractionHand.MAIN_HAND, ModItems.stack("fuel_supplies"));
        v.interact(a.shooter, InteractionHand.MAIN_HAND);
        double per = Balance.get().fuelPerSupply();
        check(v.fuel() > 10f + per - 0.01f, "fuel supplies should add " + per + " per unit, fuel is " + v.fuel());
        check(v.fuel() <= (float) def.fuel(), "never above capacity");
        check(a.shooter.getMainHandItem().getCount() < 50, "supplies were spent");

        v.hurt(h.getLevel().damageSources().generic(), 100f);
        float damaged = v.getHealth();
        a.shooter.setItemInHand(InteractionHand.MAIN_HAND, ModItems.stack("wrench"));
        v.interact(a.shooter, InteractionHand.MAIN_HAND);
        float expected = (float) (damaged + def.hp() * Balance.get().repairFraction());
        check(Math.abs(v.getHealth() - expected) < 0.05f, "a wrench repairs " + Balance.get().repairFraction() * 100 + "% of max health: expected " + expected + " got " + v.getHealth());
        float afterOne = v.getHealth();
        v.interact(a.shooter, InteractionHand.MAIN_HAND);
        check(v.getHealth() == afterOne, "repairs have a short cooldown");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void enemiesCannotBoardButFriendsCan(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        Vehicle v = vehicleAt(h, "humvee", Faction.LONESTAR, 6.5, 3.5);
        ServerPlayer friend = soldier(h, Faction.LONESTAR, 6.5, 2.5);
        ServerPlayer foe = soldier(h, Faction.VALKYRA, 4.5, 2.5);
        foe.setItemInHand(InteractionHand.MAIN_HAND, ItemStack.EMPTY);
        v.interact(foe, InteractionHand.MAIN_HAND);
        check(foe.getVehicle() != v, "an enemy must not be able to board");
        v.interact(friend, InteractionHand.MAIN_HAND);
        check(friend.getVehicle() == v, "a friendly soldier boards");
        check(v.getControllingPassenger() == friend, "the first one in is the driver");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void seatCountIsEnforced(GameTestHelper h) {
        arena(h).target.discard();
        var def = Balance.get().vehicle("bobcat"); // 2 seats
        Vehicle v = vehicleAt(h, "bobcat", Faction.LONESTAR, 6.5, 3.5);
        ServerPlayer[] p = new ServerPlayer[def.seats() + 1];
        int seated = 0;
        for (int i = 0; i < p.length; i++) {
            p[i] = soldier(h, Faction.LONESTAR, 6.5, 2.5);
            if (p[i].startRiding(v)) {
                seated++;
            }
        }
        check(seated == def.seats(), "a bobcat seats " + def.seats() + " but seated " + seated);
        h.succeed();
    }

    @GameTest(template = "empty", batch = "matchC")
    public static void drivingBurnsFuelAndDeliveriesPayTheDriver(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        ServerLevel ow = h.getLevel().getServer().overworld();
        MatchData d = MatchData.get(ow);
        boolean wasActive = d.active;
        String wasDim = d.dim;
        d.dim = "minecraft:overworld";
        double zx = d.zoneX, zz = d.zoneZ, zr = d.zoneRadius, hx = d.hotX, hz = d.hotZ, hr = d.hotRadius;
        try {
            Vec3 spot = ground(h, 6.5, 6.5);
            Vehicle v = vehicleAt(h, "humvee", Faction.LONESTAR, 6.5, 6.5);
            ServerPlayer driver = soldier(h, Faction.LONESTAR, 6.5, 2.5);
            ServerPlayer rider = soldier(h, Faction.LONESTAR, 6.5, 2.5);
            check(driver.startRiding(v) && rider.startRiding(v), "both board");

            // Boarded in the open: no zone is active yet.
            check(MatchManager.zoneLevel(ow, spot) == 0, "no zone while idle");
            float before = v.fuel();
            v.moveTo(spot.x + 10, spot.y, spot.z, 0f, 0f); // simulate 10 blocks driven
            v.tick();
            float burned = before - v.fuel();
            double perBlock = Balance.get().vehicle("humvee").fuelPerBlock(Balance.get().fuelPerBlock());
            check(burned > 0.9f * (float) (10 * perBlock) && burned < 1.1f * (float) (10 * perBlock), "10 blocks should burn about " + 10 * perBlock + " L (tank / range), burned " + burned);

            // Now drive into a Control Zone and let the passenger out.
            d.active = true;
            d.zoneX = v.getX(); d.zoneZ = v.getZ(); d.zoneRadius = 4;
            d.hotX = v.getX() + 1000; d.hotZ = v.getZ(); d.hotRadius = 2;
            long start = Wallet.get(driver);
            rider.stopRiding();
            long pay = Wallet.get(driver) - start;
            check(pay == Balance.get().transportPay(false), "delivering a passenger into the zone pays the driver $" + Balance.get().transportPay(false) + ", paid " + pay);
            check(Progression.get(driver).xpOf(Role.DRIVER) > 0, "and trains the Driver role");

            // Someone who boarded inside the zone is not a delivery.
            ServerPlayer local = soldier(h, Faction.LONESTAR, 6.5, 2.5);
            check(local.startRiding(v), "boards inside the zone");
            long mid = Wallet.get(driver);
            local.stopRiding();
            check(Wallet.get(driver) == mid, "boarding inside the zone is not a delivery");
        } finally {
            d.active = wasActive;
            d.dim = wasDim;
            d.zoneX = zx; d.zoneZ = zz; d.zoneRadius = zr; d.hotX = hx; d.hotZ = hz; d.hotRadius = hr;
        }
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void mountedGunsShootAtTheDriversAim(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        Zombie z = zombieAt(h, 5.5, 1.5 + RANGE);
        Vehicle v = vehicleAt(h, "humvee_m249", Faction.LONESTAR, 5.5, 1.5);
        ServerPlayer driver = soldier(h, Faction.LONESTAR, 5.5, 1.5);
        check(driver.startRiding(v), "driver boards");
        Vec3 eye = driver.getEyePosition();
        double dx = z.getX() - eye.x, dz = z.getZ() - eye.z, dy = (z.getY() + 1.0) - eye.y;
        driver.moveTo(driver.getX(), driver.getY(), driver.getZ(), (float) Math.toDegrees(Math.atan2(-dx, dz)),
                (float) -Math.toDegrees(Math.atan2(dy, Math.sqrt(dx * dx + dz * dz))));
        check(VehicleGuns.fire(driver, v), "the first trigger pull fires");
        check(z.getHealth() < 20f, "the target is hit, health " + z.getHealth());
        check(!VehicleGuns.fire(driver, v), "the gun obeys its fire rate within the same tick");
        Vehicle plain = vehicleAt(h, "humvee", Faction.LONESTAR, 8.5, 1.5);
        ServerPlayer d2 = soldier(h, Faction.LONESTAR, 8.5, 1.5);
        d2.startRiding(plain);
        check(!VehicleGuns.fire(d2, plain), "an unarmed vehicle does not fire");
        h.succeed();
    }

    // --- launchers, mortar, stations ---------------------------------------------------------

    @GameTest(template = "empty")
    public static void aRocketFliesAndKillsInfantryItHits(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        Zombie z = zombieAt(h, 5.5, 1.5 + RANGE);
        ItemStack rpg = ModItems.stack("rpg7");
        a.shooter.setItemInHand(InteractionHand.MAIN_HAND, rpg);
        a.shooter.setGameMode(net.minecraft.world.level.GameType.SURVIVAL);
        aimAt(a.shooter, z, chestY(z));
        ((GunItem) rpg.getItem()).use(a.level, a.shooter, InteractionHand.MAIN_HAND);
        check(GunItem.ammo(rpg) == 0, "the RPG holds one rocket, " + GunItem.ammo(rpg) + " left");
        check(!a.level.getEntitiesOfClass(RocketEntity.class, a.shooter.getBoundingBox().inflate(20)).isEmpty(), "a rocket is in flight");
        later(h, 40, () -> {
            check(!z.isAlive(), "a direct RPG hit kills a husk, health " + z.getHealth());
            check(a.level.getEntitiesOfClass(RocketEntity.class, a.shooter.getBoundingBox().inflate(40)).isEmpty(), "the rocket is gone after impact");
            h.succeed();
        });
    }

    @GameTest(template = "empty")
    public static void antiTankRocketsHurtTanksFarMoreThanRifles(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        Vehicle tank = vehicleAt(h, "l2a6", Faction.VALKYRA, 6.5, 6.5);
        var w = Balance.get().weapon("rpg7");
        Blast.explode(h.getLevel(), tank.position().add(0, tank.getBbHeight() / 2, 0), Balance.get().launcher("rpg7").blast(), w.chest(), a.shooter, "rpg7");
        float lost = tank.getMaxHealth() - tank.getHealth();
        check(lost > 500f, "an RPG hit should take a serious bite out of a tank, took " + lost);
        float rifle = (float) (Balance.get().weapon("m4").chest() * tank.def().smallArms());
        check(lost > rifle * 100, "an RPG is far stronger than rifle fire against armor: " + lost + " vs " + rifle);
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void grenadeLaunchersHoldSixRounds(GameTestHelper h) {
        ItemStack mgl = ModItems.stack("mgl40");
        check(GunItem.ammo(mgl) == 6, "an MGL-40 starts with 6 grenades, has " + GunItem.ammo(mgl));
        check(Balance.get().launcher("mgl40").kind() == com.wardogs.balance.LauncherDef.Kind.GRENADE, "and lobs grenades");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void verbaLocksOnFlyingVehiclesNotGroundOnes(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        Vehicle heli = vehicleAt(h, "mh6", Faction.VALKYRA, 6.5, 10.5);
        Vehicle truck = vehicleAt(h, "ural", Faction.VALKYRA, 4.5, 10.5);
        Vec3 eye = a.shooter.getEyePosition();
        Vec3 dir = heli.getBoundingBox().getCenter().subtract(eye).normalize().add(0.03, 0.0, 0.0).normalize();
        var id = RocketEntity.nearestFlyingTarget(a.level, a.shooter, dir);
        check(heli.getUUID().equals(id), "Verba must lock the helicopter in front of it");
        Vec3 away = dir.scale(-1);
        check(RocketEntity.nearestFlyingTarget(a.level, a.shooter, away) == null, "nothing to lock when looking away");
        truck.discard();
        h.succeed();
    }

    @GameTest(template = "empty", batch = "fobC")
    public static void repairAndRefuelStationsServiceFriendlyVehiclesFromTheFob(GameTestHelper h) {
        ServerPlayer p = builder(h, Faction.LONESTAR);
        check(Building.deployFob(p, h.getLevel(), ground(h, 2.5, 9.5)).ok(), "FOB deploys");
        var fob = h.getLevel().getEntitiesOfClass(Buildable.class, new net.minecraft.world.phys.AABB(ground(h, 2.5, 9.5), ground(h, 2.5, 9.5)).inflate(3)).get(0);
        fob.addSupply("mechanical", 5);
        fob.addSupply("fuel", 5);
        Buildable.create(h.getLevel(), Balance.get().structure("repair_station"), ground(h, 8.5, 8.5), 0f, Faction.LONESTAR.ordinal(), p.getUUID());
        Buildable.create(h.getLevel(), Balance.get().structure("refuel_station"), ground(h, 8.5, 4.5), 0f, Faction.LONESTAR.ordinal(), p.getUUID());
        Vehicle v = vehicleAt(h, "humvee", Faction.LONESTAR, 8.5, 6.5);
        v.hurt(h.getLevel().damageSources().generic(), 300f);
        v.setFuel(5f);
        float hp0 = v.getHealth();
        later(h, 60, () -> {
            check(v.getHealth() > hp0, "the repair station should have healed the vehicle: " + hp0 + " -> " + v.getHealth());
            check(v.fuel() > 5f, "the refuel station should have refilled it: fuel " + v.fuel());
            check(fob.supply("mechanical") < 5 && fob.supply("fuel") < 5, "both services draw from the FOB's stores");
            h.succeed();
        });
    }

    @GameTest(template = "empty", batch = "fobD")
    public static void mortarsNeedAmmoRangeAndLandAfterADelay(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        Factions.set(a.shooter, Faction.MANTICORE);
        int mine = Faction.MANTICORE.ordinal();
        // The battery sits well behind the arena so the aim point is beyond the minimum range.
        Buildable fob = Buildable.create(h.getLevel(), Balance.get().structure("fob"), ground(h, 2.5, -30.5), 0f, mine, a.shooter.getUUID());
        Buildable mortar = Buildable.create(h.getLevel(), Balance.get().structure("l81_mortar"), ground(h, 5.5, -30.5), 0f, mine, a.shooter.getUUID());
        check(!fob.isRemoved() && !mortar.isRemoved(), "the battery was placed in a loaded chunk");
        Zombie enemy = zombieAt(h, 5.5, 5.5);

        // Aim at the floor about 4 blocks ahead of the shooter.
        a.shooter.moveTo(a.shooter.getX(), a.shooter.getY(), a.shooter.getZ(), 0f, 22f);

        String dry = Artillery.fire(a.shooter, mortar).getString();
        check(dry.contains("No ammo"), "an empty FOB gives no shell: " + dry);
        check(Artillery.inFlight() == 0, "nothing was fired");

        fob.addSupply("ammo", 2);
        String shot = Artillery.fire(a.shooter, mortar).getString();
        check(shot.contains("Shell away"), "with ammo the mortar fires: " + shot);
        check(fob.supply("ammo") == 2 - Artillery.ammoPerShot(), "one shot used " + Artillery.ammoPerShot() + " ammo, FOB has " + fob.supply("ammo"));
        check(Artillery.inFlight() == 1, "a shell is in flight");
        check(enemy.getHealth() == 20f, "shells do not land instantly");
        check(Artillery.fire(a.shooter, mortar).getString().contains("Reloading"), "the mortar has a cooldown between shots");

        later(h, Artillery.flightTicks(40) + 10L, () -> {
            check(Artillery.inFlight() == 0, "the shell has landed");
            check(enemy.getHealth() < 20f || !enemy.isAlive(), "the husk near the impact point was hurt, health " + enemy.getHealth());
            h.succeed();
        });
    }

    // --- attachments, special ammo, emplacements --------------------------------------------

    @GameTest(template = "empty")
    public static void attachmentsChangeTheGunAndReplaceTheirSlot(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        GunItem gun = ModItems.GUNS.get("m4").get();
        WeaponDef def = gun.def();
        ItemStack m4 = gun.newStack();
        check(GunStats.magazine(def, m4) == def.magazine(), "a bare gun has the base magazine");

        check(GunStats.attach(m4, def, Balance.get().attachment("mag_ext_ar")) == GunStats.AttachResult.OK, "extended magazine fits an assault rifle");
        check(GunStats.magazine(def, m4) > def.magazine(), "and enlarges the magazine");
        check(GunStats.attach(m4, def, Balance.get().attachment("holo_sight")) == GunStats.AttachResult.OK, "a sight fits");
        check(GunStats.attach(m4, def, Balance.get().attachment("prism_4x")) == GunStats.AttachResult.OK, "a second sight replaces the first");
        var ids = GunStats.attachments(m4);
        check(ids.contains("prism_4x") && !ids.contains("holo_sight") && ids.contains("mag_ext_ar"), "one item per slot: " + ids);
        check(GunStats.zoom(ids) == 4.0, "the prism magnifies 4x");

        ItemStack pistol = ModItems.GUNS.get("ggx17").get().newStack();
        check(GunStats.attach(pistol, ModItems.GUNS.get("ggx17").get().def(), Balance.get().attachment("scope_mrad")) == GunStats.AttachResult.WRONG_WEAPON,
                "a sniper scope does not fit a pistol");
        check(GunStats.attachments(pistol).isEmpty(), "and nothing is fitted");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void attachmentItemFitsToTheGunInTheOtherHand(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        a.shooter.setGameMode(net.minecraft.world.level.GameType.SURVIVAL);
        ItemStack gun = ModItems.stack("m4");
        ItemStack kit = ModItems.stack("hexagon_brake");
        a.shooter.setItemInHand(InteractionHand.OFF_HAND, gun);
        a.shooter.setItemInHand(InteractionHand.MAIN_HAND, kit);
        ((AttachmentItem) kit.getItem()).use(a.level, a.shooter, InteractionHand.MAIN_HAND);
        check(GunStats.attachments(gun).contains("hexagon_brake"), "the brake is now on the rifle");
        check(kit.isEmpty(), "the attachment was consumed");

        ItemStack scope = ModItems.stack("scope_mrad");
        a.shooter.setItemInHand(InteractionHand.OFF_HAND, ModItems.stack("ggx17"));
        a.shooter.setItemInHand(InteractionHand.MAIN_HAND, scope);
        ((AttachmentItem) scope.getItem()).use(a.level, a.shooter, InteractionHand.MAIN_HAND);
        check(scope.getCount() == 1, "an attachment that does not fit is not consumed");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void hollowPointBoxSwitchesTheLoadedAmmo(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        a.shooter.setGameMode(net.minecraft.world.level.GameType.SURVIVAL);
        Zombie z = zombieAt(h, 5.5, 1.5 + RANGE);
        ItemStack gun = ModItems.stack("ggx17");
        ItemStack box = ModItems.stack("ammo_hp");
        a.shooter.setItemInHand(InteractionHand.OFF_HAND, gun);
        a.shooter.setItemInHand(InteractionHand.MAIN_HAND, box);
        ((AmmoBoxItem) box.getItem()).use(a.level, a.shooter, InteractionHand.MAIN_HAND);
        check(GunItem.ammoType(gun) == AmmoType.HP, "the pistol now fires hollow points");

        a.shooter.setItemInHand(InteractionHand.MAIN_HAND, gun);
        a.shooter.setItemInHand(InteractionHand.OFF_HAND, ItemStack.EMPTY);
        aimAt(a.shooter, z, chestY(z));
        ((GunItem) gun.getItem()).use(a.level, a.shooter, InteractionHand.MAIN_HAND);
        float lost = 20f - z.getHealth();
        float expected = Balance.get().damageTo(z, Balance.get().weapon("ggx17").chest() * 2);
        check(Math.abs(lost - expected) < 0.05f, "a hollow-point 9mm hit should cost " + expected + " but cost " + lost);
        h.succeed();
    }

    @GameTest(template = "empty", batch = "fobE")
    public static void mgNestDrawsAmmoFromTheFobAndObeysItsRate(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        Factions.set(a.shooter, Faction.LONESTAR);
        int mine = Faction.LONESTAR.ordinal();
        Buildable fob = Buildable.create(h.getLevel(), Balance.get().structure("fob"), ground(h, 2.5, 9.5), 0f, mine, a.shooter.getUUID());
        Buildable nest = Buildable.create(h.getLevel(), Balance.get().structure("mg_nest"), ground(h, 5.5, 2.5), 0f, mine, a.shooter.getUUID());
        Zombie z = zombieAt(h, 5.5, 1.5 + RANGE);
        aimAt(a.shooter, z, chestY(z));

        check(Emplacements.nestFor(a.shooter) == nest, "the nest is within reach of the soldier");
        check(!Emplacements.fireMg(a.shooter, nest), "no ammo in the FOB, no fire");
        fob.addSupply("ammo", 1);
        check(Emplacements.fireMg(a.shooter, nest), "with ammo the nest fires");
        check(z.getHealth() < 20f, "and hits the target, health " + z.getHealth());
        check(fob.supply("ammo") == 0 && nest.shotsLeft == (int) Balance.get().emplacements()[4] - 1, "one supply loads " + (int) Balance.get().emplacements()[4] + " rounds, one is spent");
        check(!Emplacements.fireMg(a.shooter, nest), "the rate of fire limits shots within a tick");

        Factions.set(a.shooter, Faction.VALKYRA);
        check(Emplacements.nestFor(a.shooter) == null, "enemies cannot use your nest");
        h.succeed();
    }

    @GameTest(template = "empty", batch = "fobF", timeoutTicks = 300)
    public static void talonShootsDownAnEnemyHelicopterWithAPilot(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        int mine = Faction.LONESTAR.ordinal();
        Buildable fob = Buildable.create(h.getLevel(), Balance.get().structure("fob"), ground(h, 2.5, 9.5), 0f, mine, a.shooter.getUUID());
        fob.addSupply("ammo", 3);
        Buildable.create(h.getLevel(), Balance.get().structure("talon_sam"), ground(h, 5.5, 5.5), 0f, mine, a.shooter.getUUID());

        Vehicle empty = Vehicle.spawn(h.getLevel(), Balance.get().vehicle("mh6"), ground(h, 9.5, 5.5), 0f, Faction.VALKYRA.ordinal()); // on the ground: an unmanned helicopter has gravity
        later(h, 60, () -> {
            check(empty.getHealth() == empty.getMaxHealth(), "an unmanned helicopter is ignored");
            check(fob.supply("ammo") == 3, "and no missile was spent on it");
            ServerPlayer pilot = soldier(h, Faction.VALKYRA, 9.5, 5.5);
            check(pilot.startRiding(empty), "a pilot climbs in");
            later(h, 120, () -> {
                check(empty.getHealth() < empty.getMaxHealth() || !empty.isAlive(), "the SAM should have hit the manned helicopter, health " + empty.getHealth());
                check(fob.supply("ammo") < 3, "and drawn a missile from the FOB");
                h.succeed();
            });
        });
    }

    @GameTest(template = "empty", batch = "fobG", timeoutTicks = 200)
    public static void ciwsShredsEnemyAircraftButNotFriends(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        int mine = Faction.MANTICORE.ordinal();
        Buildable fob = Buildable.create(h.getLevel(), Balance.get().structure("fob"), ground(h, 2.5, 9.5), 0f, mine, a.shooter.getUUID());
        fob.addSupply("ammo", 2);
        Buildable.create(h.getLevel(), Balance.get().structure("vanguard_ciws"), ground(h, 5.5, 5.5), 0f, mine, a.shooter.getUUID());

        Vehicle friend = Vehicle.spawn(h.getLevel(), Balance.get().vehicle("mh6"), ground(h, 8.5, 5.5).add(0, 8, 0), 0f, mine);
        Vehicle foe = Vehicle.spawn(h.getLevel(), Balance.get().vehicle("mh6"), ground(h, 9.5, 8.5).add(0, 8, 0), 0f, Faction.VALKYRA.ordinal());
        ServerPlayer friendPilot = soldier(h, Faction.MANTICORE, 8.5, 5.5);
        ServerPlayer foePilot = soldier(h, Faction.VALKYRA, 9.5, 8.5);
        check(friendPilot.startRiding(friend) && foePilot.startRiding(foe), "both helicopters are manned");
        later(h, 80, () -> {
            check(foe.getHealth() < foe.getMaxHealth(), "the CIWS must have damaged the enemy helicopter, health " + foe.getHealth());
            check(friend.getHealth() == friend.getMaxHealth(), "and left its own side alone");
            h.succeed();
        });
    }

    // --- hit zones, falloff, armour wear, revive clawback --------------------------------------

    private static boolean near(float actual, float expected) {
        return Math.abs(actual - expected) <= Math.max(0.05f, expected * 0.002f);
    }

    /**
     * Fires one shot at full health and returns the damage in WARDOGS units (a fraction of the target's health x 100):
     * damage scales with the target's maximum health, so this is independent of what the target is.
     */
    private static float lostFrom(Arena a, net.minecraft.world.entity.LivingEntity z, double aimY, WeaponDef w, AmmoType ammo) {
        z.setHealth(z.getMaxHealth());
        a.shooter.moveTo(a.shooter.getX(), a.shooter.getY(), a.shooter.getZ(), 0f, 0f);
        Vec3 eye = a.shooter.getEyePosition();
        double dx = z.getX() - eye.x, dz = z.getZ() - eye.z, dy = aimY - eye.y;
        a.shooter.moveTo(a.shooter.getX(), a.shooter.getY(), a.shooter.getZ(), (float) Math.toDegrees(Math.atan2(-dx, dz)),
                (float) -Math.toDegrees(Math.atan2(dy, Math.sqrt(dx * dx + dz * dz))));
        float before = z.getHealth();
        Ballistics.fire(a.level, a.shooter, w, ammo);
        return (before - z.getHealth()) * 100f / z.getMaxHealth();
    }

    @GameTest(template = "empty")
    public static void limbsHurtLessThanTheTorsoAndTheHeadHurtsMost(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        Zombie z = zombieAt(h, 5.5, 1.5 + RANGE);
        WeaponDef m4 = Balance.get().weapon("m4");
        double height = z.getBbHeight();
        double base = m4.chest() / Balance.get().zoneMult("assault_rifle", "torso_upper");

        float thigh = lostFrom(a, z, z.getY() + height * 0.30, m4, AmmoType.FMJ);
        float upper = lostFrom(a, z, z.getY() + height * 0.75, m4, AmmoType.FMJ);
        float head = lostFrom(a, z, z.getY() + height * 0.93, m4, AmmoType.FMJ);
        float calf = lostFrom(a, z, z.getY() + height * 0.12, m4, AmmoType.FMJ);

        check(near(thigh, (float) (base * 0.54)), "a thigh hit is x0.54, lost " + thigh);
        check(near(upper, (float) (base * 1.10)), "an upper-torso hit is x1.10, lost " + upper);
        check(near(head, (float) (base * 2.35)), "a headshot is x2.35, lost " + head);
        check(near(calf, (float) (base * 0.45)), "a calf hit is x0.45, lost " + calf);
        check(head > upper && upper > thigh && thigh > calf, "damage must rank head > torso > thigh > calf");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void damageFallsOffWithDistanceAlongTheWeaponsCurve(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        WeaponDef m4 = Balance.get().weapon("m4");
        // Move the target far away: the M4's curve is flat to its effective range, then falls off.
        double far = Math.min(250, m4.range() - 50);
        check(m4.falloffMult(far) == 1.0, "inside the effective range the multiplier is 1");
        check(m4.falloffMult(m4.range() + 300) < 1.0, "beyond the effective range damage falls");
        check(m4.falloffMult(1200) <= 0.2 + 1e-9, "the curve ends at 20% at 1200 m");
        double mid = m4.falloffMult(m4.range() + (1200 - m4.range()) / 2);
        check(mid > 0.2 && mid < 1.0, "the curve is interpolated, mid = " + mid);
        check(Ballistics.damage(m4, com.wardogs.combat.HitZone.TORSO_UPPER, AmmoType.FMJ, null, 700) < Ballistics.damage(m4, com.wardogs.combat.HitZone.TORSO_UPPER, AmmoType.FMJ, null, 10),
                "the same shot is weaker from 700 m");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void armourAbsorbsDamageAndWearsOutThenStopsProtecting(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        Zombie z = zombieAt(h, 5.5, 1.5 + RANGE);
        ItemStack vest = ModItems.stack("vest_l1");
        z.setItemSlot(net.minecraft.world.entity.EquipmentSlot.CHEST, vest);
        var def = Balance.get().armor("vest_l1");
        WeaponDef m4 = Balance.get().weapon("m4");
        check(vest.getMaxDamage() == def.durability(), "a level 1 vest has durability " + def.durability() + ", has " + vest.getMaxDamage());

        double base = m4.chest() / Balance.get().zoneMult("assault_rifle", "torso_upper");
        float first = lostFrom(a, z, z.getY() + z.getBbHeight() * 0.75, m4, AmmoType.FMJ);
        float expected = (float) (m4.chest() * (1 - def.reduction()));
        check(near(first, expected), "the vest absorbs " + (int) (def.reduction() * 100) + "%: expected " + expected + " lost " + first);
        check(vest.getDamageValue() > 0, "the vest takes wear");

        for (int i = 0; i < 20 && vest.getDamageValue() < vest.getMaxDamage(); i++) {
            lostFrom(a, z, z.getY() + z.getBbHeight() * 0.75, m4, AmmoType.FMJ);
        }
        check(vest.getDamageValue() >= vest.getMaxDamage(), "enough hits wear the vest out");
        float bare = lostFrom(a, z, z.getY() + z.getBbHeight() * 0.75, m4, AmmoType.FMJ);
        float bareExpected = (float) m4.chest();
        check(near(bare, bareExpected), "a worn-out vest gives no protection: expected " + bareExpected + " lost " + bare);
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void armourPiercingBeatsArmourAndHollowPointsLoseToIt(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        Zombie z = zombieAt(h, 5.5, 1.5 + RANGE);
        z.setItemSlot(net.minecraft.world.entity.EquipmentSlot.CHEST, ModItems.stack("vest_l4"));
        WeaponDef m4 = Balance.get().weapon("m4");
        float fmj = lostFrom(a, z, z.getY() + z.getBbHeight() * 0.75, m4, AmmoType.FMJ);
        float ap = lostFrom(a, z, z.getY() + z.getBbHeight() * 0.75, m4, AmmoType.AP);
        float hp = lostFrom(a, z, z.getY() + z.getBbHeight() * 0.75, m4, AmmoType.HP);
        check(Math.abs(ap / fmj - 1.3) < 0.02, "AP does +30% against armour, ratio " + ap / fmj);
        check(Math.abs(hp / fmj - 0.7) < 0.02, "hollow points do -30% against armour, ratio " + hp / fmj);
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void revivingTheVictimTakesBackTheKillReward(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        h.getLevel().getServer().setPvpAllowed(true);
        Factions.set(a.shooter, Faction.LONESTAR);
        ServerPlayer victim = soldier(h, Faction.VALKYRA, 5.5, 1.5 + RANGE);
        ServerPlayer medic = soldier(h, Faction.VALKYRA, 5.5, 1.5 + RANGE + 1.5);
        victim.setHealth(5f);
        WeaponDef m4 = Balance.get().weapon("m4");
        long before = Wallet.get(a.shooter);
        aimAt(a.shooter, a.target, 0); // keep the helper's rotation maths out of the way
        Vec3 eye = a.shooter.getEyePosition();
        double dx = victim.getX() - eye.x, dz = victim.getZ() - eye.z, dy = chestY(victim) - eye.y;
        a.shooter.moveTo(a.shooter.getX(), a.shooter.getY(), a.shooter.getZ(), (float) Math.toDegrees(Math.atan2(-dx, dz)),
                (float) -Math.toDegrees(Math.atan2(dy, Math.sqrt(dx * dx + dz * dz))));
        Ballistics.fire(a.level, a.shooter, m4, AmmoType.FMJ);
        long paid = Wallet.get(a.shooter) - before;
        check(paid == 400 && Downed.is(victim), "the kill pays $400 as the victim goes down, paid " + paid);

        var kit = ModItems.stack("field_resuscitator");
        MedicalItem.completeRevive(medic, victim, kit, Balance.get().medical("field_resuscitator"));
        long kept = Wallet.get(a.shooter) - before;
        long expectedKept = Math.round(400 * Balance.get().rewards().revivedKeep());
        check(kept == expectedKept, "reviving the victim leaves the killer " + expectedKept + " of the $400, he has " + kept);
        h.succeed();
    }

    // --- vendors, towers, bases --------------------------------------------------------------

    private static VendorEntity vendorAt(GameTestHelper h, VendorEntity.Kind kind, double x, double z) {
        VendorEntity v = VendorEntities.VENDOR.get().create(h.getLevel());
        Vec3 pos = h.absoluteVec(new Vec3(x, 1.0, z));
        v.moveTo(pos.x, pos.y, pos.z, 0f, 0f);
        v.setKind(kind);
        h.getLevel().addFreshEntity(v);
        return v;
    }

    @GameTest(template = "empty")
    public static void shopsNeedTheRightVendorWithinRange(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        ServerPlayer p = a.shooter;
        var rule = com.wardogs.shop.Shop.VENDOR_REQUIRED;
        com.wardogs.shop.Shop.VENDOR_REQUIRED = pl -> true;
        try {
            Wallet.set(p, 100_000);
            check(!com.wardogs.shop.Shop.buy(p, "ggx17").ok(), "with no vendor nearby nothing can be bought");

            VendorEntity gear = vendorAt(h, VendorEntity.Kind.EQUIPMENT, 5.5, 3.5);
            check(com.wardogs.shop.Shop.buy(p, "ggx17").ok(), "next to an equipment vendor a pistol can be bought");
            check(!com.wardogs.shop.Shop.buy(p, "bobcat").ok(), "an equipment vendor does not sell vehicles");
            gear.discard();

            VendorEntity garage = vendorAt(h, VendorEntity.Kind.GARAGE, 5.5, 3.5);
            check(!com.wardogs.shop.Shop.buy(p, "ggx17").ok(), "a garage vendor does not sell weapons");
            long before = Wallet.get(p);
            var res = com.wardogs.shop.Shop.buy(p, "bobcat");
            check(res.ok(), "a garage vendor sells vehicles: " + res.message().getString());
            check(before - Wallet.get(p) == Balance.get().vehicle("bobcat").price(), "the vehicle's price was charged");
            var cars = h.getLevel().getEntitiesOfClass(Vehicle.class, garage.getBoundingBox().inflate(12));
            check(cars.size() == 1 && cars.get(0).faction() == Factions.index(p), "the bought vehicle waits beside the garage, " + cars.size() + " found");
            garage.discard();

            // out of range
            vendorAt(h, VendorEntity.Kind.EQUIPMENT, 5.5, 3.5).moveTo(p.getX() + 30, p.getY(), p.getZ(), 0f, 0f);
            check(!com.wardogs.shop.Shop.buy(p, "ggx17").ok(), "a vendor 30 blocks away is out of range");
        } finally {
            com.wardogs.shop.Shop.VENDOR_REQUIRED = rule;
        }
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void vendorsPlaceOnceAndTalkingToOneIsSafe(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        check(VendorEntity.Kind.fromSiteName("GarageVendor (Bravo)") == VendorEntity.Kind.GARAGE, "garage vendors");
        check(VendorEntity.Kind.fromSiteName("VehicleVendor (Alpha)") == VendorEntity.Kind.GARAGE, "vehicle vendors count as garages");
        check(VendorEntity.Kind.fromSiteName("EquipmentVendor (Charlie)") == VendorEntity.Kind.EQUIPMENT, "equipment vendors");

        Vec3 at = h.absoluteVec(new Vec3(8.5, 1.0, 8.5));
        var site = new BakuraniWorld.Site("EquipmentVendor (Alpha)", at.x, at.z);
        BakuraniSetup.Ground flat = (x, z) -> (int) Math.round(at.y);
        check(BakuraniSetup.placeVendor(h.getLevel(), site, flat), "the first placement creates a vendor");
        check(!BakuraniSetup.placeVendor(h.getLevel(), site, flat), "placing again must not duplicate it");
        var found = h.getLevel().getEntitiesOfClass(VendorEntity.class, new net.minecraft.world.phys.AABB(at, at).inflate(4, 6, 4));
        check(found.size() == 1, "exactly one vendor, found " + found.size());
        check(found.get(0).isInvulnerable() && found.get(0).isNoAi(), "an invulnerable, motionless vendor (invulnerable=" + found.get(0).isInvulnerable() + ", noAi=" + found.get(0).isNoAi() + ")");
        var res = found.get(0).interact(a.shooter, InteractionHand.MAIN_HAND);
        check(res.consumesAction(), "talking to the vendor is handled (the shop opens on a real client)");
        h.succeed();
    }

    @GameTest(template = "empty", timeoutTicks = 200)
    public static void towersAreBuiltOnceWithAClimbableChestPlatform(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        Vec3 at = h.absoluteVec(new Vec3(6.5, 1.0, 6.5));
        var site = new BakuraniWorld.Site("Tower Test", at.x, at.z);
        BakuraniSetup.Ground flat = (x, z) -> (int) Math.round(at.y);
        check(BakuraniSetup.buildTower(h.getLevel(), site, flat), "a tower is built");
        check(!BakuraniSetup.buildTower(h.getLevel(), site, flat), "a second request leaves the existing tower alone");
        BlockPos chest = BakuraniSetup.chestPos(site, flat);
        check(h.getLevel().getBlockState(chest).is(Blocks.CHEST), "there is a chest on the platform");
        int base = chest.getY() - BakuraniSetup.TOWER_HEIGHT - 1;
        BlockPos ladder = new BlockPos((int) Math.floor(at.x), base + 5, (int) Math.floor(at.z) + 1);
        check(h.getLevel().getBlockState(ladder).is(Blocks.LADDER), "a ladder leads up the side");
        check(!h.getLevel().getBlockState(new BlockPos((int) Math.floor(at.x), base + BakuraniSetup.TOWER_HEIGHT, (int) Math.floor(at.z) + 1)).is(Blocks.SPRUCE_PLANKS), "with an opening (not a plank) in the platform where the ladder arrives");

        // The chest refills: an empty one rolls again until it holds an item from the table.
        var be = (net.minecraft.world.level.block.entity.ChestBlockEntity) h.getLevel().getBlockEntity(chest);
        boolean got = false;
        for (int i = 0; i < 200 && !got; i++) {
            be.clearContent();
            BakuraniSetup.refill(h.getLevel(), site, flat);
            got = !be.isEmpty();
        }
        check(got, "an empty tower chest eventually rolls a loot item");
        String id = null;
        for (var e : com.wardogs.world.TowerLoot.TABLE) {
            if (ModItems.stack(e.itemId()) != null && be.getItem(0).getItem() == ModItems.stack(e.itemId()).getItem()) {
                id = e.itemId();
            }
        }
        check(id != null, "and the item comes from the tower loot table");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void playersAreNotSentToABaseWithoutABakuraniMatch(GameTestHelper h) {
        ServerPlayer p = soldier(h, Faction.LONESTAR, 5.5, 5.5);
        check(!com.wardogs.match.Respawns.sendToBase(p), "no Bakurani match is running, so nobody is moved");
        check(!com.wardogs.match.Respawns.sendToBase(h.makeMockServerPlayerInLevel()), "and a player without a faction is never moved");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void generatedHousesHaveDoorsWindowsFloorsAndRoofs(GameTestHelper h) {
        for (int kind = 0; kind <= 1; kind++) {
            var b = new com.wardogs.world.BakuraniTerrain.Building(0, 0, 11, 9, 3, kind);
            int w = b.w(), d = b.d();
            check(com.wardogs.world.BuildingGen.at(b, w / 2, d - 1, 1).is(Blocks.OAK_DOOR), "the south wall has a door (kind " + kind + ")");
            check(com.wardogs.world.BuildingGen.at(b, 3, 3, 0).is(Blocks.SPRUCE_PLANKS), "the ground floor is planks");
            check(com.wardogs.world.BuildingGen.at(b, 3, 3, 1).isAir(), "rooms are empty");
            check(com.wardogs.world.BuildingGen.at(b, 3, 3, 4).is(Blocks.SPRUCE_PLANKS), "storeys are separated by a floor slab");
            check(com.wardogs.world.BuildingGen.at(b, 1, 1, 6).is(Blocks.LADDER), "a ladder climbs through the storeys");
            check(com.wardogs.world.BuildingGen.at(b, 0, 4, 2) != null, "walls have blocks");
            boolean glass = false, roof = false;
            for (int i = 0; i < w; i++) {
                for (int k = 1; k < 12; k++) {
                    glass |= com.wardogs.world.BuildingGen.at(b, i, 0, k) != null && com.wardogs.world.BuildingGen.at(b, i, 0, k).is(Blocks.GLASS);
                }
            }
            for (int k = 12; k < com.wardogs.world.BuildingGen.height(b); k++) {
                roof |= com.wardogs.world.BuildingGen.at(b, w / 2, d / 2, k) != null;
            }
            check(glass, "the walls have windows");
            check(roof, "there is a roof above the top floor");
            check(com.wardogs.world.BuildingGen.at(b, 5, 4, -3) != null && com.wardogs.world.BuildingGen.at(b, 5, 4, -3).is(Blocks.DIRT), "the footprint is filled below the floor");
        }
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void lootCachesHoldTheItemTheirMarkerNames(GameTestHelper h) {
        var rnd = h.getLevel().random;
        check("bandage".equals(com.wardogs.world.LootCaches.itemFor("Bandages #3", rnd)), "bandage marker");
        check("defibrillator".equals(com.wardogs.world.LootCaches.itemFor("Defibrillator #4", rnd)), "defibrillator marker");
        check("sv98".equals(com.wardogs.world.LootCaches.itemFor("Sniper rifle loot #1", rnd)), "sniper marker");
        check(com.wardogs.world.LootCaches.itemFor("Weapon loot #2", rnd) != null, "weapon marker rolls a weapon");
        check("vest_l2".equals(com.wardogs.world.LootCaches.itemFor("Armor loot #1", rnd)), "armor marker");
        for (var c : com.wardogs.world.BakuraniWorld.sites().loot()) {
            String id = com.wardogs.world.LootCaches.itemFor(c.name(), rnd);
            check(id != null && ModItems.stack(id) != null, "loot marker " + c.name() + " maps to a real item");
        }
        var site = new com.wardogs.world.BakuraniWorld.Site("Weapon loot #9", h.absolutePos(new BlockPos(2, 2, 2)).getX() + 0.5,
                h.absolutePos(new BlockPos(2, 2, 2)).getZ() + 0.5);
        com.wardogs.world.BakuraniSetup.Ground flat = (x, z) -> h.absolutePos(new BlockPos(2, 1, 2)).getY();
        check(com.wardogs.world.BakuraniSetup.placeCache(h.getLevel(), site, flat), "a cache is placed");
        check(!com.wardogs.world.BakuraniSetup.placeCache(h.getLevel(), site, flat), "placing again changes nothing");
        var barrel = (net.minecraft.world.level.block.entity.BarrelBlockEntity) h.getLevel().getBlockEntity(com.wardogs.world.BakuraniSetup.cachePos(site, flat));
        check(barrel != null && !barrel.isEmpty(), "the barrel holds a weapon");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void deploymentBoardsListTheBaseAndAreBuiltOnce(GameTestHelper h) {
        var abs = h.absolutePos(new BlockPos(4, 2, 4));
        var site = new com.wardogs.world.BakuraniWorld.Site("Deployment board 9", abs.getX() + 0.5, abs.getZ() + 0.5);
        int groundY = h.absolutePos(new BlockPos(0, 1, 0)).getY();
        com.wardogs.world.BakuraniSetup.Ground flat = (x, z) -> groundY;
        check(com.wardogs.world.BakuraniSetup.placeBoard(h.getLevel(), site, flat), "a board is built");
        check(!com.wardogs.world.BakuraniSetup.placeBoard(h.getLevel(), site, flat), "a second request changes nothing");
        check(h.getLevel().getBlockState(new BlockPos(abs.getX(), groundY + 1, abs.getZ() + 1)).is(Blocks.LECTERN), "the lectern stands in front of the board");
        var targets = com.wardogs.match.Deployment.targets(h.getLevel(), Faction.LONESTAR);
        check(!targets.isEmpty() && targets.get(0).name().equals("Lonestar base"), "the base is the first deployment point");
        ServerPlayer p = soldier(h, Faction.LONESTAR, 5.5, 5.5);
        check(com.wardogs.match.Deployment.deploy(p, 0) != null, "deploying outside Bakurani is refused");
        h.succeed();
    }

    @GameTest(template = "empty", batch = "matchStats")
    public static void scoreboardCountsKillsDeathsAndSurvivesTheNetwork(GameTestHelper h) {
        com.wardogs.match.MatchStats.reset();
        ServerPlayer a = soldier(h, Faction.LONESTAR, 2.5, 2.5);
        ServerPlayer b = soldier(h, Faction.VALKYRA, 4.5, 2.5);
        com.wardogs.match.MatchStats.kill(a, 400);
        com.wardogs.match.MatchStats.kill(a, 600);
        com.wardogs.match.MatchStats.death(b);
        var rows = com.wardogs.match.MatchStats.rows();
        check(rows.size() == 2, "two players are listed");
        ServerPlayer c = soldier(h, Faction.MANTICORE, 8.5, 2.5);
        check(com.wardogs.match.MatchStats.rows(java.util.List.of(a, b, c)).size() == 3, "an online player without stats is listed too");
        rows = com.wardogs.match.MatchStats.rows();
        check(rows.get(0).faction() == Faction.LONESTAR.ordinal() && rows.get(0).kills() == 2 && rows.get(0).earned() == 1000, "the Lonestar player has 2 kills and $1000");
        check(rows.get(1).deaths() == 1, "the Valkyra player has one death");
        var payload = new com.wardogs.net.ScoreboardPayload(true, 0, new int[]{100, 40, 7}, rows);
        var buf = new net.minecraft.network.RegistryFriendlyByteBuf(io.netty.buffer.Unpooled.buffer(), h.getLevel().registryAccess(),
                net.minecraft.network.ConnectionProtocol.PLAY == null ? null : net.neoforged.neoforge.network.connection.ConnectionType.NEOFORGE);
        com.wardogs.net.ScoreboardPayload.CODEC.encode(buf, payload);
        var back = com.wardogs.net.ScoreboardPayload.CODEC.decode(buf);
        check(back.fin() && back.winner() == 0 && back.scores()[0] == 100 && back.rows().equals(rows), "the scoreboard survives encoding");
        com.wardogs.match.MatchStats.reset();
        check(com.wardogs.match.MatchStats.rows().isEmpty(), "reset clears the table");
        h.succeed();
    }

    @GameTest(template = "empty", batch = "requests")
    public static void radialRequestsReachOnlyNearbyTeammatesAndHaveACooldown(GameTestHelper h) {
        ServerPlayer a = soldier(h, Faction.LONESTAR, 2.5, 2.5);
        ServerPlayer mate = soldier(h, Faction.LONESTAR, 4.5, 2.5);
        ServerPlayer enemy = soldier(h, Faction.VALKYRA, 6.5, 2.5);
        var everyone = java.util.List.of(a, mate, enemy);
        check(com.wardogs.match.Requests.send(a, "heal", everyone) == 2, "a request reaches the sender and the teammate, not the enemy");
        check(com.wardogs.match.Requests.send(a, "ammo", everyone) == -1, "a second message within two seconds is refused");
        check(com.wardogs.match.Requests.send(mate, "nonsense", everyone) == -1, "an unknown id is refused");
        ServerPlayer loner = h.makeMockServerPlayerInLevel();
        check(com.wardogs.match.Requests.send(loner, "heal", everyone) == -1, "a player without a faction cannot send");
        check(com.wardogs.match.Requests.ENTRIES.keySet().containsAll(java.util.List.of("heal", "ammo", "supplies", "enemy", "salute")), "the menu entries exist");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void aPlayerWithNaNHealthIsRepaired(GameTestHelper h) {
        ServerPlayer p = soldier(h, Faction.LONESTAR, 2.5, 2.5);
        p.setHealth(Float.NaN);
        com.wardogs.combat.PlayerHealth.repair(p);
        check(!Float.isNaN(p.getHealth()) && p.getHealth() > 0, "the player has real health again");
        h.succeed();
    }

    @GameTest(template = "empty")
    public static void killFeedAndHitMarkerPacketsSurviveEncoding(GameTestHelper h) {
        var buf = new net.minecraft.network.RegistryFriendlyByteBuf(io.netty.buffer.Unpooled.buffer(), h.getLevel().registryAccess(),
                net.neoforged.neoforge.network.connection.ConnectionType.NEOFORGE);
        var feed = new com.wardogs.net.KillFeedPayload("Dev", 0, "Zombie", -1, "AK-74", true);
        com.wardogs.net.KillFeedPayload.CODEC.encode(buf, feed);
        check(com.wardogs.net.KillFeedPayload.CODEC.decode(buf).equals(feed), "a kill feed line survives encoding");
        com.wardogs.net.HitMarkerPayload.CODEC.encode(buf, new com.wardogs.net.HitMarkerPayload(3));
        check(com.wardogs.net.HitMarkerPayload.CODEC.decode(buf).kind() == 3, "a hit marker survives encoding");
        h.succeed();
    }

    @GameTest(template = "empty", batch = "slowBullet", timeoutTicks = 100)
    public static void bulletsWithMuzzleVelocityTakeTimeToArrive(GameTestHelper h) {
        Arena a = arena(h);
        WeaponDef m4 = Balance.get().weapon("m4");
        WeaponDef slow = new WeaponDef(m4.id(), m4.name(), m4.category(), m4.caliber(), m4.role(), m4.mode(), m4.chest(), m4.head(), m4.rpm(),
                m4.magazine(), m4.range(), m4.reloadTicks(), m4.spread(), m4.pellets(), m4.price(), m4.unlockLevel(), m4.unlockCost(),
                m4.estimated(), m4.falloff(), 40.0); // 2 blocks per tick: the target is 7 blocks away
        Ballistics.clearBullets();
        aimAt(a.shooter, a.target, chestY(a.target));
        float before = a.target.getHealth();
        Ballistics.fire(a.level, a.shooter, slow, AmmoType.FMJ);
        check(a.target.getHealth() == before, "the bullet has not arrived on the tick it was fired");
        check(Ballistics.bulletsInFlight() == 1, "it is still in flight");
        later(h, 12, () -> {
            check(a.target.getHealth() < before, "the bullet arrived and hurt the target");
            check(Ballistics.bulletsInFlight() == 0, "and is gone");
            h.succeed();
        });
    }

    @GameTest(template = "empty")
    public static void bulletDropGrowsWithTheSquareOfFlightTime(GameTestHelper h) {
        check(Math.abs(Ballistics.drop(20) - 4.9) < 0.4, "about 4.9 m after one second: " + Ballistics.drop(20));
        check(Ballistics.drop(40) > 3.5 * Ballistics.drop(20), "four times as far after twice the time");
        check(Balance.get().weapon("svd").velocity() == 800.0, "the SVD carries its researched muzzle velocity");
        check(Balance.get().weapon("m4").velocity() > 0, "rifles have a muzzle velocity");
        h.succeed();
    }
}
