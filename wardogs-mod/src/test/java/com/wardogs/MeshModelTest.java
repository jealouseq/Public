package com.wardogs;

import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import com.wardogs.balance.Balance;
import com.wardogs.balance.VehicleDef;
import org.junit.jupiter.api.Test;

import java.io.InputStreamReader;
import java.nio.charset.StandardCharsets;

import static org.junit.jupiter.api.Assertions.assertNotNull;
import static org.junit.jupiter.api.Assertions.assertTrue;

class MeshModelTest {
    private String read(String path) throws Exception {
        try (var in = getClass().getResourceAsStream(path)) {
            assertNotNull(in, path + " is missing");
            return new String(in.readAllBytes(), StandardCharsets.UTF_8);
        }
    }

    @Test
    void everyVehicleHasAConsistentObjMesh() throws Exception {
        for (VehicleDef v : Balance.get().vehicles()) {
            String json = read("/assets/wardogs/models/vehicle/" + v.id() + ".json");
            JsonObject o = JsonParser.parseString(json).getAsJsonObject();
            assertTrue(o.get("loader").getAsString().equals("neoforge:obj"), v.id() + " uses the OBJ loader");
            String obj = read("/assets/wardogs/models/vehicle/" + v.id() + ".obj");
            String mtl = read("/assets/wardogs/models/vehicle/" + v.id() + ".mtl");
            long verts = obj.lines().filter(l -> l.startsWith("v ")).count();
            long faces = obj.lines().filter(l -> l.startsWith("f ")).count();
            assertTrue(verts > 100 && faces > 200 && faces < 12000, v.id() + " has a sensible mesh: " + faces + " faces");
            assertTrue(obj.contains("mtllib " + v.id() + ".mtl"), v.id() + " names its material library");
            assertTrue(mtl.contains("map_Kd #base") && mtl.contains("newmtl m0"), v.id() + " materials use the shared texture");
            // faces must only reference existing vertices and normals
            long normals = obj.lines().filter(l -> l.startsWith("vn ")).count();
            for (String line : (Iterable<String>) obj.lines().filter(l -> l.startsWith("f "))::iterator) {
                for (String tok : line.substring(2).split(" ")) {
                    String[] p = tok.split("/");
                    assertTrue(Integer.parseInt(p[0]) <= verts && Integer.parseInt(p[2]) <= normals, v.id() + " face index out of range: " + line);
                }
            }
        }
    }

    @Test
    void theSharedWhiteTextureExists() throws Exception {
        try (var in = getClass().getResourceAsStream("/assets/wardogs/textures/item/mesh_white.png")) {
            assertNotNull(in);
        }
    }
}
