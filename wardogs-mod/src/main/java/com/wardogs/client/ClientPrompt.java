package com.wardogs.client;

import com.wardogs.build.Buildable;
import com.wardogs.item.HammerItem;
import com.wardogs.item.SupplyItem;
import com.wardogs.vehicle.Vehicle;
import com.wardogs.vendor.VendorEntity;
import net.minecraft.client.Minecraft;
import net.minecraft.world.entity.Entity;
import net.minecraft.world.phys.EntityHitResult;
import net.minecraft.world.phys.HitResult;

/** The context action shown under the crosshair, e.g. "[RMB] UNLOAD SUPPLIES > Into F.O.B". */
public final class ClientPrompt {
    public record Prompt(String key, String action, String target) {}

    private ClientPrompt() {}

    public static Prompt current(Minecraft mc) {
        if (mc.player == null || mc.player.getVehicle() != null || !(mc.hitResult instanceof EntityHitResult hit) || hit.getType() != HitResult.Type.ENTITY) {
            return null;
        }
        Entity t = hit.getEntity();
        if (mc.player.distanceTo(t) > 5) {
            return null;
        }
        var held = mc.player.getMainHandItem();
        if (t instanceof VendorEntity v) {
            return new Prompt("RMB", "OPEN SHOP", v.kind().title);
        }
        if (t instanceof Buildable b) {
            if (b.isFob() && held.getItem() instanceof SupplyItem) {
                return new Prompt("RMB", "UNLOAD SUPPLIES", "Into F.O.B");
            }
            if (held.getItem() instanceof HammerItem && b.def() != null && b.health() < b.def().hp()) {
                return new Prompt("RMB", "REPAIR", b.def().id().replace('_', ' ').toUpperCase());
            }
            return null;
        }
        if (t instanceof Vehicle v && v.def() != null) {
            return new Prompt("RMB", "ENTER VEHICLE", v.def().name().toUpperCase());
        }
        return null;
    }
}
