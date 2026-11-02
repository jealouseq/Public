package com.wardogs.shop;

import com.wardogs.balance.AmmoItemDef;
import com.wardogs.balance.ArmorDef;
import com.wardogs.balance.AttachmentDef;
import com.wardogs.balance.BuildingItemDef;
import com.wardogs.balance.Balance;
import com.wardogs.balance.ExplosiveDef;
import com.wardogs.balance.MedicalDef;
import com.wardogs.balance.VehicleDef;
import com.wardogs.balance.WeaponDef;
import com.wardogs.economy.Wallet;
import com.wardogs.item.ModItems;
import com.wardogs.net.HudPayload;
import com.wardogs.net.ProgressPayload;
import com.wardogs.progress.ProgressData;
import com.wardogs.progress.Progression;
import com.wardogs.progress.Role;
import com.wardogs.progress.UnlockRule;
import net.minecraft.ChatFormatting;
import net.minecraft.network.chat.Component;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.world.item.ItemStack;

import java.util.ArrayList;
import java.util.List;

/** The vendor: lists everything buyable, enforces role-level unlocks and charges money. */
public final class Shop {
    public enum Category { WEAPON, ARMOR, MEDICAL, EXPLOSIVE, BUILDING, VEHICLE, ATTACHMENT }

    /** One shop line. {@code role == null} means open to everyone. */
    public record Entry(String id, Category category, String name, int price, Role role, int level, long cost) {}

    public record Result(boolean ok, Component message) {}

    /**
     * Decides whether purchases need a vendor next to the player. On Bakurani they do (the real game buys at vendors in the
     * faction bases); elsewhere (development) the shop works anywhere. Tests replace this rule.
     */
    public static volatile java.util.function.Predicate<ServerPlayer> VENDOR_REQUIRED =
            p -> p.level().dimension().equals(com.wardogs.world.BakuraniWorld.DIMENSION);

    /** Interaction range to a vendor, in blocks. */
    public static final double VENDOR_RANGE = 8.0;

    /** The nearest vendor within range, or null. */
    public static com.wardogs.vendor.VendorEntity vendorNear(ServerPlayer p) {
        com.wardogs.vendor.VendorEntity best = null;
        double bestD = VENDOR_RANGE;
        for (var v : p.serverLevel().getEntitiesOfClass(com.wardogs.vendor.VendorEntity.class, p.getBoundingBox().inflate(VENDOR_RANGE))) {
            double d = v.distanceTo(p);
            if (d <= bestD) {
                best = v;
                bestD = d;
            }
        }
        return best;
    }

    /** Null if the player may buy this entry here, otherwise the reason. */
    private static Result vendorGate(ServerPlayer p, Entry e) {
        if (!VENDOR_REQUIRED.test(p)) {
            return null;
        }
        var v = vendorNear(p);
        if (v == null) {
            return new Result(false, Component.literal("Go to a vendor in your base to buy").withStyle(ChatFormatting.RED));
        }
        if (!v.kind().categories.contains(e.category())) {
            return new Result(false, Component.literal(v.kind().title + " does not sell this").withStyle(ChatFormatting.RED));
        }
        return null;
    }

    private Shop() {}

    public static List<Entry> catalog() {
        Balance b = Balance.get();
        List<Entry> out = new ArrayList<>();
        for (WeaponDef w : b.weapons()) {
            out.add(new Entry(w.id(), Category.WEAPON, w.name(), w.price(), Role.byKey(w.role()), w.unlockLevel(), w.unlockCost()));
        }
        for (ArmorDef a : b.armor()) {
            String name = (a.slot() == ArmorDef.Slot.HELMET ? "Helmet" : "Body Armor") + " L" + a.level();
            out.add(new Entry(a.id(), Category.ARMOR, name, a.price(), Role.WARDOG, a.unlockLevel(), 0));
        }
        for (MedicalDef m : b.medicalItems()) {
            out.add(new Entry(m.id(), Category.MEDICAL, pretty(m.id()), m.price(), Role.byKey(m.unlockRole()), m.unlockLevel(), m.unlockCost()));
        }
        for (ExplosiveDef x : b.explosiveItems()) {
            out.add(new Entry(x.id(), Category.EXPLOSIVE, pretty(x.id()), x.price(), Role.byKey(x.unlockRole()), x.unlockLevel(), x.unlockCost()));
        }
        for (BuildingItemDef x : b.buildingItems()) {
            String name = pretty(x.id()) + (x.stack() > 1 ? " x" + x.stack() : "");
            out.add(new Entry(x.id(), Category.BUILDING, name, x.price(), Role.byKey(x.unlockRole()), x.unlockLevel(), x.unlockCost()));
        }
        for (VehicleDef v : b.vehicles()) {
            out.add(new Entry(v.id(), Category.VEHICLE, v.name(), v.price(), Role.byKey(v.unlockRole()), v.unlockLevel(), v.unlockCost()));
        }
        for (AttachmentDef a : b.attachments()) {
            out.add(new Entry(a.id(), Category.ATTACHMENT, a.name(), a.price(), Role.byKey(a.unlockRole()), a.unlockLevel(), a.unlockCost()));
        }
        for (AmmoItemDef a : b.ammoItems()) {
            out.add(new Entry(a.id(), Category.ATTACHMENT, a.name(), a.price(), Role.byKey(a.unlockRole()), a.unlockLevel(), a.unlockCost()));
        }
        return out;
    }

    public static Entry find(String id) {
        for (Entry e : catalog()) {
            if (e.id().equals(id)) {
                return e;
            }
        }
        return null;
    }

    public static UnlockRule.State state(Entry e, ProgressData data) {
        return UnlockRule.check(e.id(), e.role(), e.level(), e.cost(), data.unlocked(),
                r -> Balance.get().progression().levelFor(data.xpOf(r)));
    }

    /** Pays the one-time unlock cost if the role level is high enough. */
    public static Result unlock(ServerPlayer p, String id) {
        Entry e = find(id);
        if (e == null) {
            return new Result(false, Component.literal("Unknown item: " + id));
        }
        Result gate = vendorGate(p, e);
        if (gate != null) {
            return gate;
        }
        return switch (state(e, Progression.get(p))) {
            case OWNED -> new Result(false, Component.literal(e.name() + " is already unlocked"));
            case LEVEL_TOO_LOW -> new Result(false, Component.literal("Requires " + e.role().key() + " level " + e.level()
                    + " (you are " + Progression.level(p, e.role()) + ")").withStyle(ChatFormatting.RED));
            case PAYABLE -> {
                if (!Wallet.tryPay(p, e.cost())) {
                    yield new Result(false, Component.literal("Unlock costs $" + e.cost() + ", you have $" + Wallet.get(p)).withStyle(ChatFormatting.RED));
                }
                Progression.unlock(p, id);
                HudPayload.sendTo(p);
                ProgressPayload.sendTo(p);
                yield new Result(true, Component.literal("Unlocked " + e.name() + " for $" + e.cost()).withStyle(ChatFormatting.GOLD));
            }
        };
    }

    public static Result buy(ServerPlayer p, String id) {
        Entry e = find(id);
        if (e == null) {
            return new Result(false, Component.literal("Unknown item: " + id));
        }
        Result gate = vendorGate(p, e);
        if (gate != null) {
            return gate;
        }
        switch (state(e, Progression.get(p))) {
            case LEVEL_TOO_LOW -> {
                return new Result(false, Component.literal("Locked: requires " + e.role().key() + " level " + e.level()
                        + " (you are " + Progression.level(p, e.role()) + ")").withStyle(ChatFormatting.RED));
            }
            case PAYABLE -> {
                return new Result(false, Component.literal("Locked: unlock it first for $" + e.cost() + " (/wd unlock " + id + ")").withStyle(ChatFormatting.RED));
            }
            default -> { }
        }
        if (!Wallet.tryPay(p, e.price())) {
            return new Result(false, Component.literal("Not enough money: need $" + e.price() + ", you have $" + Wallet.get(p)).withStyle(ChatFormatting.RED));
        }
        if (e.category() == Category.VEHICLE && VENDOR_REQUIRED.test(p) && vendorNear(p) != null) {
            // A vehicle bought at a garage appears next to it, ready to drive.
            var v = vendorNear(p);
            net.minecraft.world.phys.Vec3 dir = net.minecraft.world.phys.Vec3.directionFromRotation(0, p.getYRot());
            double x = v.getX() + dir.x * 7, z = v.getZ() + dir.z * 7;
            double y = p.serverLevel().getHeight(net.minecraft.world.level.levelgen.Heightmap.Types.MOTION_BLOCKING, (int) Math.floor(x), (int) Math.floor(z)) + 0.5;
            com.wardogs.vehicle.Vehicle.spawn(p.serverLevel(), Balance.get().vehicle(id), new net.minecraft.world.phys.Vec3(x, y, z),
                    p.getYRot(), com.wardogs.match.Factions.index(p));
            HudPayload.sendTo(p);
            return new Result(true, Component.literal("Your " + e.name() + " is waiting outside  (left $" + Wallet.get(p) + ")").withStyle(ChatFormatting.GOLD));
        }
        ItemStack stack = ModItems.stack(id);
        if (!p.getInventory().add(stack)) {
            p.drop(stack, false);
        }
        HudPayload.sendTo(p);
        return new Result(true, Component.literal("Bought " + e.name() + " for $" + e.price() + "  (left $" + Wallet.get(p) + ")").withStyle(ChatFormatting.GOLD));
    }

    private static String pretty(String id) {
        StringBuilder sb = new StringBuilder();
        for (String part : id.split("_")) {
            if (!part.isEmpty()) {
                sb.append(Character.toUpperCase(part.charAt(0))).append(part.substring(1)).append(' ');
            }
        }
        return sb.toString().trim();
    }
}
