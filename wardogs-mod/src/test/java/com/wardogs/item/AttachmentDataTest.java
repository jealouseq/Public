package com.wardogs.item;

import com.wardogs.balance.AttachmentDef;
import com.wardogs.balance.Balance;
import com.wardogs.balance.WeaponDef;
import org.junit.jupiter.api.Test;

import java.util.List;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertTrue;

class AttachmentDataTest {
    private final Balance b = Balance.get();

    @Test
    void unlockLevelsFollowTheSources() {
        assertEquals(2, b.attachment("hexagon_brake").unlockLevel());
        assertEquals(14, b.attachment("afg_grip").unlockLevel());
        assertEquals(17, b.attachment("cq_2x").unlockLevel());
        assertEquals(22, b.attachment("holo_sight").unlockLevel());
        assertEquals(43, b.attachment("prism_4x").unlockLevel());
        assertEquals(30, b.attachment("mag_ext_ar").unlockLevel());
        assertEquals(1, b.attachment("dual_port_brake").unlockLevel());
        assertEquals(14, b.attachment("svd_bipod").unlockLevel());
        assertEquals(40_000, b.attachment("scope_mrad").unlockCost());
        assertEquals(45_000, b.attachment("scope_moa").unlockCost());
        assertEquals("support", b.attachment("pkm_bipod").unlockRole());
    }

    @Test
    void magazineSpreadAndZoomCombine() {
        WeaponDef m4 = b.weapon("m4");
        assertEquals(m4.magazine(), GunStats.magazine(m4, List.of()));
        assertTrue(GunStats.magazine(m4, List.of("mag_ext_ar")) > m4.magazine());
        assertEquals(1.0, GunStats.spreadMult(List.of(), false), 1e-9);
        double both = GunStats.spreadMult(List.of("hexagon_brake", "afg_grip"), false);
        assertEquals(0.85 * 0.9, both, 1e-9);
        assertEquals(1.0, GunStats.zoom(List.of("hexagon_brake")), 1e-9);
        assertEquals(4.0, GunStats.zoom(List.of("prism_4x")), 1e-9);
        assertEquals(8.0, GunStats.zoom(List.of("afg_grip", "scope_mrad", "cq_2x")), 1e-9, "the strongest sight counts");
    }

    @Test
    void bipodsOnlyHelpWhenCrouched() {
        double standing = GunStats.spreadMult(List.of("m249_bipod"), false);
        double crouched = GunStats.spreadMult(List.of("m249_bipod"), true);
        assertEquals(1.0, standing, 1e-9);
        assertTrue(crouched < 0.7);
    }

    @Test
    void attachmentsOnlyFitTheirWeaponClasses() {
        AttachmentDef scope = b.attachment("scope_mrad");
        assertTrue(scope.fits(b.weapon("sv98")));
        assertFalse(scope.fits(b.weapon("ggx17")));
        assertFalse(scope.fits(b.weapon("rpg7")));
        assertTrue(b.attachment("mag_ext_smg").fits(b.weapon("mp5")));
        assertFalse(b.attachment("mag_ext_smg").fits(b.weapon("m4")));
    }

    @Test
    void specialAmmoUnlocksAtWardogLevels() {
        assertEquals(1, b.ammoItem("ammo_hp").ammoType());
        assertEquals(2, b.ammoItem("ammo_ap").ammoType());
        assertEquals(6, b.ammoItem("ammo_hp").unlockLevel());
        assertEquals("wardog", b.ammoItem("ammo_hp").unlockRole());
    }
}
