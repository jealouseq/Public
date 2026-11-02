# Bakurani in Moxel Worlds

`district_data.py x0 x1 z0 z1` turns the local Bakurani rasters (`local-data/bakurani`, git-ignored) into
Moxel `run_script` payloads for one window of the map:

* `/tmp/moxel/helper.js` - decoder, saved once on the Moxel map with `save_as: "dd"`
* `/tmp/moxel/strip_N.js` - 64-row strips (`return strip(x0, z, [...])`, run-length coded heights and surface types)
* `/tmp/moxel/buildings.json`, `/tmp/moxel/trees.json` - building records and tree sites inside the window

Free Moxel plan limits that shaped this: 15 s per script, 256x256 per `generate_terrain` call, 1 000 000 blocks placed per day.
The first district (x -420..-101, z 900..1155, around the Control Zone flag at -219, 1025) costs about 380 000 blocks:
ground crust from the real height/material/road/cover rasters, 28 buildings (footprints, floors and kinds from `buildings.bin`, snapped to
the axes), 234 trees on forest cover, the Control Zone ring/flag and markers.

## State (first build session)

Two contiguous windows are built in the Moxel project `wardogs-bakurani`: x -420..-101 with z 900..1155 (around the Control Zone flag)
and z 1156..1411 (hills, forest, farm tower 6). About 760 000 blocks, 51 buildings, ~520 trees, 1 500 fence blocks, one steel water tower.

Look-and-feel decisions come from the Steam community screenshots (`local-data/reference/community/`, git-ignored): grey/rusty low-pitch metal roofs on
brick and plaster houses, tall dark plank fences, muddy broken asphalt, pine/spruce mixed with birch, steel water tower with a concrete tank.
The first district still has the earlier red roofs and light fences; re-roofing it is a to-do.

Known rough edges: the strips are pasted by hand into `run_script`, a few rows were mistyped (the decoder now tolerates that, but those
columns can be a block off), roads come from a 4 m raster so they are blocky, and buildings are snapped to the axes.
