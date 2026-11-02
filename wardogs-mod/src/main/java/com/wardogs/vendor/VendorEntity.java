package com.wardogs.vendor;

import com.wardogs.net.OpenShopPayload;
import com.wardogs.shop.Shop;
import net.minecraft.nbt.CompoundTag;
import net.minecraft.network.chat.Component;
import net.minecraft.server.level.ServerPlayer;
import net.minecraft.world.InteractionHand;
import net.minecraft.world.InteractionResult;
import net.minecraft.world.entity.EntityType;
import net.minecraft.world.entity.npc.Villager;
import net.minecraft.world.entity.npc.VillagerProfession;
import net.minecraft.world.entity.player.Player;
import net.minecraft.world.level.Level;
import net.neoforged.neoforge.network.PacketDistributor;

import java.util.Set;

/**
 * A vendor NPC. Equipment vendors sell kit; garage and vehicle vendors sell vehicles. It is a villager that never moves,
 * cannot be hurt, and opens the WARDOGS shop instead of the trading screen.
 */
public class VendorEntity extends Villager {
    public enum Kind {
        EQUIPMENT("Equipment Vendor", VillagerProfession.WEAPONSMITH,
                Set.of(Shop.Category.WEAPON, Shop.Category.ARMOR, Shop.Category.MEDICAL, Shop.Category.EXPLOSIVE, Shop.Category.BUILDING, Shop.Category.ATTACHMENT)),
        GARAGE("Garage Vendor", VillagerProfession.TOOLSMITH, Set.of(Shop.Category.VEHICLE));

        public final String title;
        final VillagerProfession profession;
        public final Set<Shop.Category> categories;

        Kind(String title, VillagerProfession profession, Set<Shop.Category> categories) {
            this.title = title;
            this.profession = profession;
            this.categories = categories;
        }

        /** Marker names: "GarageVendor", "VehicleVendor" and "EquipmentVendor" (with a faction suffix). */
        public static Kind fromSiteName(String name) {
            String n = name.toLowerCase();
            return n.contains("garage") || n.contains("vehicle") ? GARAGE : EQUIPMENT;
        }
    }

    private Kind kind = Kind.EQUIPMENT;

    public VendorEntity(EntityType<? extends VendorEntity> type, Level level) {
        super(type, level);
        setNoAi(true);
        setInvulnerable(true);
        setPersistenceRequired();
    }

    public Kind kind() {
        return kind;
    }

    public void setKind(Kind k) {
        this.kind = k;
        setVillagerData(getVillagerData().setProfession(k.profession));
        setCustomName(Component.literal(k.title));
        setCustomNameVisible(true);
    }

    @Override
    public InteractionResult mobInteract(Player player, InteractionHand hand) {
        if (!level().isClientSide && player instanceof ServerPlayer sp && sp.connection != null && sp.connection.hasChannel(OpenShopPayload.TYPE)) {
            PacketDistributor.sendToPlayer(sp, new OpenShopPayload(kind.name()));
        }
        return InteractionResult.sidedSuccess(level().isClientSide);
    }

    @Override
    public boolean removeWhenFarAway(double dist) {
        return false;
    }

    @Override
    public boolean isPushable() {
        return false;
    }

    @Override
    public void addAdditionalSaveData(CompoundTag t) {
        super.addAdditionalSaveData(t);
        t.putString("vendor_kind", kind.name());
    }

    @Override
    public void readAdditionalSaveData(CompoundTag t) {
        super.readAdditionalSaveData(t);
        if (t.contains("vendor_kind")) {
            setKind(Kind.valueOf(t.getString("vendor_kind")));
        }
    }
}
