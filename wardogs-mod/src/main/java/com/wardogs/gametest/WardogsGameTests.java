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
        long expected = Math.round(350 * Balance.get().progression().xpPerDollar());
        check(Progression.get(a.shooter).xpOf(Role.ASSAULT) == expected, "an M4 kill worth $350 should give " + expected + " assault XP, gave " + Progression.get(a.shooter).xpOf(Role.ASSAULT));
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
        double dx = victim.getX() - eye.x, dz = victim.getZ() - eye.z, dy = (victim.getY() + 1.0) - eye.y;
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
        check(Wallet.get(a.shooter) - before == 300, "the kill should pay $300 when the victim goes down, paid " + (Wallet.get(a.shooter) - before));

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
        aimAt(a.shooter, a.target, a.target.getY() + 1.0);
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

    @GameTest(template = "empty")
    public static void drivingBurnsFuelAndDeliveriesPayTheDriver(GameTestHelper h) {
        Arena a = arena(h);
        a.target.discard();
        ServerLevel ow = h.getLevel().getServer().overworld();
        MatchData d = MatchData.get(ow);
        boolean wasActive = d.active;
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
            check(burned > 0.9f * (float) (10 * Balance.get().fuelPerBlock()), "10 blocks should burn about " + 10 * Balance.get().fuelPerBlock() + " fuel, burned " + burned);

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
}
