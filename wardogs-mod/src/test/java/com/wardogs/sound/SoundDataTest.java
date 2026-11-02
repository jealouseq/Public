package com.wardogs.sound;

import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import com.wardogs.balance.Balance;
import com.wardogs.balance.WeaponDef;
import org.junit.jupiter.api.Test;

import java.io.InputStreamReader;

import static org.junit.jupiter.api.Assertions.assertNotNull;
import static org.junit.jupiter.api.Assertions.assertTrue;

class SoundDataTest {
    private JsonObject sounds() throws Exception {
        try (var in = getClass().getResourceAsStream("/assets/wardogs/sounds.json")) {
            assertNotNull(in, "sounds.json is missing");
            return JsonParser.parseReader(new InputStreamReader(in)).getAsJsonObject();
        }
    }

    @Test
    void everyDeclaredSoundHasAnOggFile() throws Exception {
        for (var e : sounds().entrySet()) {
            String file = e.getValue().getAsJsonObject().getAsJsonArray("sounds").get(0).getAsJsonObject().get("name").getAsString().replace("wardogs:", "");
            try (var in = getClass().getResourceAsStream("/assets/wardogs/sounds/" + file + ".ogg")) {
                assertNotNull(in, e.getKey() + " has no audio file " + file);
                byte[] head = in.readNBytes(4);
                assertTrue(head.length == 4 && head[0] == 'O' && head[1] == 'g' && head[2] == 'g' && head[3] == 'S', file + " is not an Ogg file");
            }
        }
    }

    @Test
    void everyWeaponClassHasAShotSound() throws Exception {
        var declared = sounds().keySet();
        for (WeaponDef w : Balance.get().weapons()) {
            if (w.category().equals("bow")) {
                continue;
            }
            assertTrue(ModSounds.BY_CATEGORY.containsKey(w.category()), "no shot sound for category " + w.category());
        }
        for (String extra : new String[]{"dry_fire", "reload_out", "reload_in", "explosion", "impact_block", "impact_flesh", "impact_metal"}) {
            assertTrue(declared.contains(extra), extra + " is not declared in sounds.json");
        }
        for (var h : ModSounds.BY_CATEGORY.values()) {
            assertTrue(declared.contains(h.getId().getPath()), h.getId() + " is not declared in sounds.json");
        }
    }
}
