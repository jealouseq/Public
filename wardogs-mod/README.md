# WARDOGS: Bakurani (Minecraft mod)

Tactical 3-faction combat inspired by WARDOGS, for **Minecraft Java 1.21.1 / NeoForge 21.1.265** (Java 21).
Research and source numbers: [RESEARCH.md](RESEARCH.md).

## Status (milestone 2: match loop)
Done and covered by tests (unit + 65 server GameTests):
- Data-driven balance (`src/main/resources/data/wardogs/balance/*.json`, overridable by a datapack, reloads with `/reload`).
- 34 firearms: measured chest/head damage, RPM, price; ammo FMJ/HP/AP; hitscan, headshots, wall blocking, spread, magazines, reload (R).
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
- Vendor screen (key B): category tabs, click a row to buy or unlock. The server re-checks every purchase.
- Building: small/medium/large hammers (sneak+click chooses a structure, click builds, click a friendly structure repairs),
  12 structures with the game's health and C4/IED/mine hit counts, FOB kit (stores build/ammo/fuel/mechanical supplies,
  build radius), supplies bought in stacks of 50. Small structures work from your own supplies; bigger ones need a friendly
  FOB with supplies stored. Structures stop bullets and players; only enemy explosives damage them.
- Vehicles: 25 (cars, trucks, tanks, anti-air, artillery, 9 helicopters) with prices/unlocks from the sources. Seats, fuel
  (refill with Fuel Supplies), repair with a Wrench, per-vehicle armor against bullets/explosives/anti-tank, a destruction reward,
  a delivery bonus for passengers dropped in the zone, and mounted weapons fired by the driver (hold use). Helicopters climb and
  descend by looking up or down. Bought vehicles are spawn tickets for now (right-click the ground).
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
- HUD (money, faction, three score bars), sent only to the owning player. A match forces PvP on.

Admin commands (op): `/wd zone here <radius>`, `/wd match start|stop|status`, `/wd give <item>`, `/wd money set|add <n>`, `/wd weapons`.
Quick start: `/wd zone here 60`, `/wd team lonestar`, `/wd match start`, stand in the zone, `/wd buy m4`.

Not yet built: M113 forward spawn, the Bakurani terrain (data found, see research/DATA_SOURCES.md),
the Bakurani map, real models/sounds. The HUD and key binding compile but were never seen on a screen.

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
| Hold TAB | Match scoreboard: three faction columns with kills, deaths, money earned |
| Right-click a lectern at a deployment board | Choose where to deploy: your base or a friendly FOB |

The HUD follows the real game: compass strip with the Hot Zone marker (top centre), money and level boxes with a reward log (top
right), kill feed (top left), minimap with faction scores and the "CONTROL" label (bottom left), ammo and stamina / armour / heart
(bottom right), hit markers at the crosshair, context prompts and a vehicle read-out.

## Assets

Gun item models are voxelised from the CC0 Quaternius "Ultimate Gun Pack" (`research/tools/obj_to_item_model.py`); vehicle models are
generated box shapes (`research/tools/gen_vehicle_models.py`), as are the grenade, mine, charge, flare and armour models
(`research/tools/gen_item_models.py`). See `research/MODS_AND_ASSETS.md` for the ready-made mods and assets
that were considered and their licences.

## Not done yet

Launcher 3D models, real recorded sounds (shots currently use vanilla sounds by weapon class), houses that follow the street direction
(they are axis-aligned), hand-built landmarks, the Stingray station (the sources give no data for it).

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
