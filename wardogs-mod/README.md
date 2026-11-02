# WARDOGS: Bakurani (Minecraft mod)

Tactical 3-faction combat inspired by WARDOGS, for **Minecraft Java 1.21.1 / NeoForge 21.1.265** (Java 21).
Research and source numbers: [RESEARCH.md](RESEARCH.md).

## Status (milestone 3: playable Bakurani)
Done and covered by tests (unit + 82 server GameTests). Newer features (Bakurani world, HUD, map, squads, deployment, vehicle
sections, bullet travel, sounds, models) are listed in the sections further down; this list is the match-loop core:
- Data-driven balance (`src/main/resources/data/wardogs/balance/*.json`, overridable by a datapack, reloads with `/reload`).
- 34 firearms: measured chest/head damage, RPM, price; ammo FMJ/HP/AP; bullets that travel and drop (muzzle velocity from the data; close range is instant), headshots, wall blocking, spread, magazines, reload (R).
- Armor: helmets and body armor L1-L4 (equip by right-click); headshots hit the helmet, body hits hit the vest.
- Three factions (Lonestar, Valkyra, Manticore), `/wd team <faction>`.
- Control Zone + moving Hot Zone: money while inside, entry bonus, score every 30 s (x2 if the leader also leads the Hot Zone),
  first to 100 wins and top-3 factions get placement money. Kill rewards are x5 in the zone and x10 in the Hot Zone.
- Downed state: a fatal hit on a combatant puts them on the ground (crawling, no items, bleed-out timer) instead of killing them;
  the kill is paid when they go down. Teammates revive with a Field Resuscitator, Stim Pen or Defibrillator (battery charges),
  paid by zone and with a repeat-revive penalty. Bandage, IFAK and Medical Bag heal. All shop-buyable.
- Explosives: M67 frag, Molotov, C4 + remote detonator, IED, AT mine (vehicles only), Claymore (frontal cone), smoke and six
  signal smokes. Thrown grenades bounce and have a fuse; charges are placed on blocks; explosions fall off with distance, are
  blocked by walls and never break blocks. Kills pay like bullet kills.
- Role progression: XP per role (Assault, Medic, Recon, Support, Driver, Pilot) plus the general WARDOG branch, levels from the
  Season 2 XP curve. Items need a role level and sometimes a one-time unlock payment (`/wd unlock <item>`, `/wd progress`).
- Vendor screen (key B, next to a vendor in Bakurani): sorted list with icons, a stats panel and a BUY / UNLOCK button. The server re-checks every purchase.
- Building: small/medium/large hammers (sneak+click chooses a structure, click builds, click a friendly structure repairs),
  12 structures with the game's health and C4/IED/mine hit counts, FOB kit (stores build/ammo/fuel/mechanical supplies,
  build radius), supplies bought in stacks of 50. Small structures work from your own supplies; bigger ones need a friendly
  FOB with supplies stored. Structures stop bullets and players; only enemy explosives damage them.
- Vehicles: 23 for sale plus the M113 forward-spawn APC (cars, trucks, tanks, anti-air, artillery, 9 helicopters) with prices/unlocks from the sources. Seats, fuel
  (refill with Fuel Supplies), repair with a Wrench, per-vehicle armor against bullets/explosives/anti-tank, a destruction reward,
  a delivery bonus for passengers dropped in the zone, and mounted weapons fired by the driver (hold use). Helicopters climb and
  descend by looking up or down. Bought vehicles are spawn tickets (right-click the ground; the garage vendor spawns them beside itself in Bakurani).
- Launchers: RPG-7, MAAWS, MGL-40 (6 grenades), 9K333 Verba (homes on flying vehicles). They use the normal weapon system
  (magazine, reload, shop, unlocks). A direct hit deals full damage, splash falls off; anti-tank rockets hurt vehicles far more.
- Emplacements: L81 mortar (right-click with an empty hand to fire at your crosshair; needs ammo supplies in its FOB, minimum and
  maximum range, shell flight time, cooldown), and refuel/repair stations that service friendly vehicles from the FOB's stores.
- Attachments (sights with zoom while sneaking, extended magazines, brakes, grips, bipods) and special ammo boxes (HP, AP), fitted
  by right-clicking with the item in one hand and the weapon in the other. Emplacements: MG nest (hold use next to it),
  Talon SAM and Vanguard CIWS (automatic, shoot manned enemy helicopters, use ammo supplies from the FOB).
- Balance upgraded to the researched data (see research/DATA_SOURCES.md and research/tools/apply_research.py): 12 hit zones per
  weapon class, per-weapon damage falloff curves, armour 30/40/55/65% with durability that wears out, hollow points -30% / armour
  piercing +30% against armour, the Season 2 kill payout (half from the shooter's zone, half from the victim's; class bases
  $300/$400/$600; flat 250 XP; the reward is clawed back when the victim is revived), 23 vehicles with real prices, unlocks, seats,
  speeds, bullet resistance, fuel and range, FOB range 60 m.
- Teamkill costs 3x the kill reward. Wallet ($10 000 start). `/wd buy <item>` shop command.
- Players have 100 HP (not 20 hearts). Bullets deal WARDOGS damage 1:1, vanilla damage is scaled x5. Vanilla hearts are hidden;
  the HUD shows the number and a heart that fills in proportion to health.
- HUD laid out like the real game (see Controls and interface), sent only to the owning player. A match forces PvP on.

Admin commands (op): `/wd zone here <radius>`, `/wd match start|stop|status`, `/wd give <item>`, `/wd money set|add <n>`, `/wd weapons`.
Quick start: `/wd zone here 60`, `/wd team lonestar`, `/wd match start`, stand in the zone, `/wd buy m4`.

The Bakurani dimension is generated from the researched elevation, material, road, land-cover and building data (kept in the
git-ignored `local-data/bakurani`, built by `research/tools/map_convert.py` and `bake_cover.py`; they are not part of the repository).
`/wd bakurani` teleports you there, `/wd bakurani setup` places vendors, towers, boards, loot, the shooting range and flags,
and `/wd match bakurani` starts a match.

Fields flagged `estimated` in the balance JSON (reload time, spread, some magazines/ranges, armor absorption, HP/AP vs armor)
and the assumed 100 player health are placeholders: the sources do not publish them.

## Controls and interface

| Key | What it does |
|---|---|
| R | Reload the held gun |
| B | Open the shop (only next to a vendor during a Bakurani match) |
| M | Full-screen map (scroll to zoom, drag to pan; needs the map data folder on the client) |
| Middle mouse | Radial menu: squad (create, invite the nearest teammate, leave), requests (healing, ammo, supplies, repairs), local callouts, emotes |
| `/wd mode standard / hardcore` (op) | Hardcore trims the HUD (no minimap, no Hot Zone marker) and limits the kill feed to your squad |
| `/wd squad create / invite <name> / accept / leave` | Squads of up to four; members appear on the minimap and in a list on the left |
| X / G / V (while downed) | Give up (die at once) / call for a medic / open the damage log |
| Hold TAB | Match scoreboard: three faction columns with kills, deaths, money earned |
| Right-click a lectern at a deployment board | Choose where to deploy: your base or a friendly FOB |

The HUD follows the real game: compass strip with the Hot Zone marker (top centre), money and level boxes with a reward log (top
right), kill feed (top left), minimap that turns with your heading with faction scores and the "CONTROL" label (bottom left), ammo and
stamina / armour / heart (bottom right), hit markers at the crosshair, context prompts, a vehicle read-out, world markers (Hot Zone ring
and squad mates with their distance) a NEARBY teammate list, a downed screen with the bleed-out ring, GIVE UP, CALL FOR HELP and the damage log (who hit you, with what, for how much). Driving uses a chase camera; crews of closed vehicles sit inside them. The vendor shows guns and vehicles as turning 3D models.

## Assets

Weapons and vehicles are real triangle meshes (`neoforge:obj` models), not boxes: the 33 gun models are the CC0 Quaternius "Ultimate Gun
Pack" re-posed for the hand and tinted per weapon; the Wolf-110 is the CC0 "Soviet military off-road vehicle" from OpenGameArt; the other
22 vehicles (tanks, APC, SPH, AA gun, trucks, SUVs, buggies, ATV, helicopters) are generated procedurally with smooth shading and
box-projected UVs over one shared grime texture (`research/tools/vehicles_proc.py`, `build_models.py`, `meshkit.py`, `meshlib.py`). Trees are painted here too: six species with their own
generated foliage and bark textures (`research/tools/gen_nature.py`) and procedural shapes (`nature/Trees.java`). Weapon sounds are
synthesised (`research/tools/gen_sounds.py`, no recordings); grenade, mine, charge, flare, armour and launcher models come from
`research/tools/gen_item_models.py`, and the 59 decor blocks (furniture, street furniture, camp gear) from `research/tools/gen_decor.py`.
No asset of the real WARDOGS or of STALCRAFT is used (they are proprietary); see `research/MODS_AND_ASSETS.md` for what was considered and
the licences. A developer can additionally import community mods' models (guns, tanks, helicopters, worn armour, props, road blocks, recorded
weapon sounds) into the git-ignored
`local-data/generated` folder (`research/IMPORTS.md`); they are not redistributable, so nothing of them is committed and builds meant for
others use `-PnoImports`.

## The Bakurani map

Terrain, rivers, forest, fields, roads and about 4 000 houses come from the researched map data (kept in the git-ignored
`local-data/bakurani`, built by `research/tools/map_convert.py` and `bake_cover.py`). Generated at chunk time: houses along the roads
facing them (rotated in 45-degree steps) with overhanging eaves, hip or gable roofs in brick, mud-brick, slate, copper or stone tiles, a
stone plinth, quoins, a belt course at every storey, tall windows, attic windows, a porch with a first-floor balcony over it, lantern, partition walls, furniture and ceiling
lamps; sheds; rows of lock-up garages behind the big blocks; flat-roofed blocks with parapets and rooftop vents; yards with benches,
mailboxes, bins, firewood, gardens and fences; a lawn clearing around every house; surfaced roads with gravel shoulders; street lamps,
bus shelters, kiosks, power poles along tracks; bridges over rivers. Nature: stands of fir and pine, birch and aspen groves, oaks and
copper-leaved beeches with forked limbs and lobed crowns, bushes, stumps and fallen logs, a mossy forest floor with ferns, mushrooms and
berries, alpine turf on gentle rock-class ground, banded cliffs (stone, andesite, tuff, cobble), boulders with moss, and meadows with
drifts of wild flowers and tall grass.
Placed by `/wd bakurani setup` (a queue of small steps that runs for about a minute; watch the chat): faction outposts with a hangar for
the garage vendor, a market stall for the equipment vendor, tent camps at the spawns, a depot, a concrete wall with barbed wire and
floodlights, corner watchtowers and a faction flag; loot towers, loot caches, deployment boards, the shooting range, zone flags and
parked cars. The first run builds everything, later runs only fill gaps.

## Not done yet

Recorded sounds (shots, reloads, dry fire, explosions and bullet impacts are synthesised; footsteps are vanilla), smoother roofs and walls on rotated houses (they follow the street direction; diagonal walls are stair-stepped, as diagonals are in blocks), more hand-built landmarks (only the shooting range and the Control Zone flags so far), the Stingray station (the sources give no data for it).

## Build & test
```
./gradlew build            # compiles, unit tests, jar in build/libs
./gradlew runGameTestServer # integration tests on a headless server
./gradlew runClient        # play it
```

## Research
`research/DATA_SOURCES.md` summarises the precise data found online (map coordinates and elevation, hit zones, armour, rewards formula, vehicle specs, HUD). Raw/decoded datasets are in `research/data/`.

## Bakurani world
The mod adds the `wardogs:bakurani` dimension: a 1:1 (1 block = 1 m) copy of the Bakurani valley, vertical relief halved (y 16..632,
dimension height 704), built from elevation, surface-material and road data. Surface blocks: grass, rock, gravel, snow, forest floor, roads as dirt path.

Data is not shipped (it is extracted game material, see research/DATA_SOURCES.md). To install it:
1. put the downloaded rasters in `local-data/bakurani/` (names as in `research/tools/map_convert.py`) and run `python3 research/tools/map_convert.py`;
2. copy `height_2048_u16.bin`, `material_2048_u8.bin`, `roads_4096_u8.bin` to `config/wardogs/bakurani/` of the game or server
   (`./gradlew runClient/runServer` already read them from `local-data/bakurani` via `-Dwardogs.mapdir`).
Without the data the dimension is a flat plain.

Commands: `/wd bakurani` teleports you to your faction's spawn; `/wd match bakurani` (op) starts a match with one of the real
Control Zone variants (default / farmland / lumberyard, radius 500 m). Players who walk off the map are put back.
`local-data/bakurani/minecraft_preview.png` shows the result (bases, zone variants, towers).

Match flow on Bakurani: `/wd match bakurani` builds the vendors and the 12 loot towers (idempotent, also `/wd bakurani setup`),
starts a match on a real Control Zone variant and sends every soldier to their faction's base; dead soldiers respawn at the base.
Buying on Bakurani needs a vendor within 8 blocks: equipment vendors sell kit, garage vendors sell vehicles (a bought vehicle
appears beside the garage). Towers hold the loot from the marker data (50% chance, weights FOB kit 0.5, SV98 0.8, M249 1, medkit 0.8,
M500 1) and refill every five minutes. Forests get spruces. Not built yet: buildings, the river, deployment boards.
