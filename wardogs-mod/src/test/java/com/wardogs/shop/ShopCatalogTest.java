package com.wardogs.shop;

import com.wardogs.progress.ProgressData;
import com.wardogs.progress.Role;
import com.wardogs.progress.UnlockRule;
import org.junit.jupiter.api.Test;

import java.util.HashSet;
import java.util.Set;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertNotNull;
import static org.junit.jupiter.api.Assertions.assertTrue;

class ShopCatalogTest {
    @Test
    void catalogCoversEverythingWithUniqueIds() {
        var cat = Shop.catalog();
        Set<String> ids = new HashSet<>();
        for (Shop.Entry e : cat) {
            assertTrue(ids.add(e.id()), "duplicate id " + e.id());
            assertTrue(e.price() >= 0, e.id());
        }
        assertEquals(34, cat.stream().filter(e -> e.category() == Shop.Category.WEAPON).count());
        assertEquals(8, cat.stream().filter(e -> e.category() == Shop.Category.ARMOR).count());
        assertEquals(7, cat.stream().filter(e -> e.category() == Shop.Category.MEDICAL).count());
        assertEquals(15, cat.stream().filter(e -> e.category() == Shop.Category.EXPLOSIVE).count());
        assertEquals(9, cat.stream().filter(e -> e.category() == Shop.Category.BUILDING).count());
        assertEquals(25, cat.stream().filter(e -> e.category() == Shop.Category.VEHICLE).count());
    }

    @Test
    void researchUnlocksAreEncoded() {
        Shop.Entry m4 = Shop.find("m4");
        assertEquals(Role.ASSAULT, m4.role());
        assertEquals(28, m4.level());
        assertEquals(150_000, m4.cost());
        Shop.Entry mine = Shop.find("at_mine");
        assertEquals(Role.SUPPORT, mine.role());
        assertEquals(75_000, mine.cost());
        assertEquals(Role.WARDOG, Shop.find("helmet_l4").role());
        assertEquals(100, Shop.find("helmet_l4").level());
        assertNotNull(Shop.find("ggx17"));
    }

    @Test
    void freshPlayerStatesMakeSense() {
        ProgressData fresh = ProgressData.EMPTY;
        assertEquals(UnlockRule.State.OWNED, Shop.state(Shop.find("m67_frag"), fresh));
        assertEquals(UnlockRule.State.OWNED, Shop.state(Shop.find("ggx17"), fresh));
        assertEquals(UnlockRule.State.LEVEL_TOO_LOW, Shop.state(Shop.find("m4"), fresh));
        assertEquals(UnlockRule.State.LEVEL_TOO_LOW, Shop.state(Shop.find("defibrillator"), fresh));
    }
}
