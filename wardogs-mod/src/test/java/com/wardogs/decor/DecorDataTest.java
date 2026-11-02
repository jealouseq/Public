package com.wardogs.decor;

import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import org.junit.jupiter.api.Test;

import java.io.InputStreamReader;

import static org.junit.jupiter.api.Assertions.assertNotNull;
import static org.junit.jupiter.api.Assertions.assertTrue;

class DecorDataTest {
    private JsonObject read(String path) throws Exception {
        try (var in = getClass().getResourceAsStream(path)) {
            assertNotNull(in, path + " is missing");
            return JsonParser.parseReader(new InputStreamReader(in)).getAsJsonObject();
        }
    }

    @Test
    void everyDecorBlockHasModelBlockstateItemModelAndName() throws Exception {
        JsonObject blocks = read("/data/wardogs/decor/blocks.json");
        JsonObject lang = read("/assets/wardogs/lang/en_us.json");
        assertTrue(blocks.size() >= 40, "expected a big decor set, found " + blocks.size());
        for (var e : blocks.entrySet()) {
            String id = e.getKey();
            String model = id.substring("decor_".length());
            read("/assets/wardogs/models/block/decor/" + model + ".json");
            read("/assets/wardogs/blockstates/" + id + ".json");
            read("/assets/wardogs/models/item/" + id + ".json");
            assertTrue(lang.has("block.wardogs." + id), "no name for " + id);
            assertTrue(e.getValue().getAsJsonObject().getAsJsonArray("boxes").size() > 0, id + " has no shape");
        }
    }

    @Test
    void lightEmittingBlocksAreTheLamps() throws Exception {
        JsonObject blocks = read("/data/wardogs/decor/blocks.json");
        for (String lamp : new String[]{"decor_street_lamp", "decor_lamp_table", "decor_lamp_floor", "decor_floodlight"}) {
            assertTrue(blocks.getAsJsonObject(lamp).get("light").getAsInt() >= 13, lamp + " should glow");
        }
        assertTrue(blocks.getAsJsonObject("decor_chair").get("light").getAsInt() == 0, "a chair does not glow");
    }
}
