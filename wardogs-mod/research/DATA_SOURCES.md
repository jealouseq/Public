# Precise data found online (2026-11-01)

Everything below comes from community sites that extracted data from the game (wardogs.zone, clutchbase.app, metaforge.app,
metabot.gg, dexerto, kennel.gg). The developer publishes none of it, so treat it as the best available evidence, not as official.
Raw/decoded files are in `research/data/`; the map rasters are in `local-data/` (git-ignored, see "About the map images").

## 1. Bakurani map

| Fact | Value |
|---|---|
| World capture | "Kavkazi", 16 320 x 16 320 m (Unreal bounds x -1 632 000..0, y -2 040 000..-408 000 cm) |
| Rasters | base 5120², relief 4096², roads 4096² (RGBA), surface 2048², height 2048² (7.97 m/texel); community 16 384 px viewers ≈ 1 m/px |
| Marker coordinates | metres, origin = map centre, **x east, y north**. Exception: category `settlement` has **y south-positive** |
| Marker -> image px (16 384 space) | `px = (x + 8187) / 0.99902`, `py = (8191 - y) / 0.99983` (verified: the Control Zone marker lands on the central town and matches the artillery calculator's (7959, 7153) centre to ~10 m) |
| Height raster | `raw16 = R*256 + G`; elevation = `0.25993 * raw16 - 1008.34` (fitted on 557 markers, residual std 9 m, r = 0.978). Range -1007..+224, i.e. about 1230 m of relief |
| Marker elevation `z` | same units as the fitted elevation (metres-like) |

### Bases (settlements), spawns, boards, vendors (marker coordinates, x east / y north in m)

| Faction | Base (settlement; y flipped) | Spawn | Deployment board | Vendors near it |
|---|---|---|---|---|
| Lonestar (Alpha) | (554, -4942) | Alpha #1 (544, -4938), #7 (407, -4809) | #1 (516, -4920) | Garage (499, -4927), Equipment (530, -4924), Vehicle (494, -4928), Equipment (383, -4764) |
| Valkyra (Bravo) | (3683, -1099) | Bravo #3 (3680, -1101) | #3 (3649, -1135) | Equipment (3640, -1119), Garage (3648, -1154), Vehicle (3624, -1150) |
| Manticore (Charlie) | (-4183, -440) | Charlie #2 (-4242, -424), #4 (-2881, -1665), #6 (-4267, -2514) | #2 (-4222, -444), #4 (-2848, -1652) | Equipment (-4214, -427), Vehicle (-4180, -461), Garage (-4181, -462), Equipment (-2860, -1648), Garage (-2763, -1649), Vehicle (-2841, -1667) |

NOTE: the Season 2 patch moved all three faction spawns; these coordinates are from the pre-patch dataset and may be outdated.
Neutral spawn #5 (-733, -889) with an equipment vendor (-728, -886).

### Objectives and towers
* Control Zone markers: (-219, -1025) [the default zone] and (1439, -1073) [Farmland/Lumberyard variants sit nearby]; radius 500 m.
* Other objective markers: StrikeDisarm laptop (-1878, -1820), a shooting-range tutorial area around (1650, 2766) with ~14 vendors.
* Towers (5, tier "Tower"): (177,-907), (-478,-848), (-472,-1188), (32,-1345), (-145,-1207).
* Abandoned towers (7): (1857,-385), (-2501,-1733), (-2384,-3939), (-2849,-1000), (-1851,-2877), (630,-4099), (-665,-4367).
* Every tower: "spawn 50%, respawns"; loot table with weights: FOB kit 0.5, SV98 0.8, M249 1.0, standard medkit 0.8, M500 1.0, heavy repair drill 0.5.
* Counts in the whole dataset: 248 doors, 101 ladders, 150 sniper-loot, 15 medical, 4 weapon, 2 armor loot points, 26 vendors, 7 spawns, 4 deployment boards. (Full list: `research/data/bakurani_markers.json`, doors/ladders/sniper loot omitted.)

### About the map images
The rasters are screenshots/exports of the game's own map assets. They are useful for generating the terrain, but they are Bulkhead's
material extracted by third parties, so they are **not committed**. They sit in `local-data/bakurani/` (git-ignored) and `research/tools/map_convert.py`
turns the height image into a compact `.bin`. Decide before shipping a mod that embeds terrain derived from them.

## 2. Damage model (metaforge "ballistics lab" pack, assumed health 100)

Hit-zone multipliers on a round's base damage (`research/data/ballistics_lab.json -> hitboxes`):

| Class | head | neck | upper torso | mid torso | lower torso | pelvis | upper arm | lower arm | hand | thigh | calf | foot |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Assault rifle | 2.35 | 1.76 | 1.10 | 1.00 | 0.95 | 0.90 | 0.54 | 0.45 | 0.27 | 0.54 | 0.45 | 0.27 |
| SMG | 2.10 | 1.575 | 1.155 | 1.05 | 0.9975 | 0.945 | 0.78 | 0.65 | 0.39 | 0.78 | 0.65 | 0.39 |
| LMG | 2.30 | 1.725 | 1.155 | 1.05 | 0.9975 | 0.90 | 0.54 | 0.45 | 0.27 | 0.54 | 0.45 | 0.27 |

(Other classes - shotgun, marksman, sniper, pistol, bow - are in the JSON.) Example: AK74 base damage 26 (item cards): chest 26 x 1.10 = 28.6, head 26 x 2.35 = 61.1,
matching the community's measured 28.61 / 61.12.

Round types (`rounds`): FMJ neutral; **Hollow point** +100% vs unarmoured, -30% vs armour and helmet, penetration 1.5; **Armor piercing** -20% vs unarmoured, +30% vs armour/helmet, penetration 0.3.

Armour (`armour`): damage reduction and durability:

| Item | reduction | durability | covers |
|---|---|---|---|
| Level 1 / 2 / 3 / 4 armour | 30% / 40% / 55% / 65% | 200 / 220 / 250 / 300 | torso (L4 also pelvis, upper arms) |
| Level 1 / 2 / 3 / 4 helmet | 30% / 40% / 55% / 65% | 80 / 100 / 180 / 200 | head (L3, L4 also neck) |

Weapons carry falloff curves, e.g. A-91: `[[0,1],[300,1],[1200,0.2]]` (full damage to 300 m, linear to 20% at 1200 m), muzzle velocity 715 m/s, MOA 1.5, 700 RPM.
Drag models (G1, G7, GL, GC) with curves are also in the pack: bullets have drop and travel time.
Calibre base damages (e.g. 120 mm 150, 155 mm 450) and per-weapon data for 35 weapons are in `ballistics_lab.json`; 592 item cards (price, weight, base damage, capacity) in `item_cards.json`.

## 3. Money and XP (wardogs.zone "Cash & XP", Season 2)

* Kill: base **$300 / 250 role XP**; headshot +$50; revenge +$300; "shutdown" (ending a streak) +$150; team kill **-$1 200**; victim later revived -$297 (a revived victim keeps 1% of the value); crewed vehicle -$60 per extra crew.
* Zone: cash = `(1 - repeats x 0.24) x [ (base + streak x 50) x yourZone x 0.5 + (base + streak x 50) x targetZone x 0.5 ] x distance x revenge x ...`.
  Control Zone factor 5, Hot Zone 10 (so a kill with both sides in the Hot Zone = $3 000; shooter outside + victim in the Hot Zone = about $1 650).
  The shooter's and the victim's positions each count for half. Sniper, artillery and explosive kills weigh the target's position alone.
* Kill type bases: small arms $300, assault weapon $400, sniper rifle $600, explosive $300, artillery $250, aircraft $300, tank $300.
* Repeats: -24% per repeat, 4 deep. Streak: +$50 per kill after the first, cap 5 kills. Tank/helicopter kill streaks pay more. Distance multiplier up to 4x for a sniper.
* Heals and revives weigh the **patient's** position only. Revive $400 / $800 / $1 600 (open / Control / Hot), -25% per repeat.
* Server size: under 10 players nothing pays, ~45 pays 80%, 60+ pays 100%.
* End of match: $1 000 / $500 / $250 flat plus 30% / 25% / 20% of the match's earnings (counted up to $100 000); XP up to 2 500 gets x2 / x1.75 / x1.5.
* A running drill rig pays the Hot Zone rate for everything inside it; firing the Hot Zone Magnet drill pays $2 500.
* Role XP: rifle/pistol kills -> Assault, SMG -> Medic, LMG/shotgun/launcher -> Support, marksman/sniper/bow -> Recon; medical items -> Medic; tools, explosives, supplies -> Support;
  spotting -> Recon; delivery -> Driver (truck) or Pilot (helicopter); capturing a tower or hacking a FOB -> Assault.

## 4. Vehicles (wardogs.zone database, Season 2 prices)

Top speed km/h, fuel L. "small arms" is the damage change against bullets; hull sections have separate hit points; helicopters are downed by losing a main or tail rotor.

| Vehicle | Cost $ | Unlock $ | Requirement | km/h | Seats | Fuel L | Small arms | Sections (HP) |
|---|---|---|---|---|---|---|---|---|
| Havoc | 28000 | 650000 | Pilot 35 | 400 | 2 | 130 | -95% damage | Hull 800, Fuel Tank 200, Engine 200, Rotor Tail 300, Rotor Main 400, Wheel Left Front 100, Wheel Right Front 100 |
| MH-6 | 6250 | - | - | 350 | 6 | 105 | -80% damage | Hull 400, Fuel Tank 200, Engine 200, Rotor Tail 300, Rotor Main 400 |
| AH-6M [Miniguns] | 12000 | 125000 | Pilot 8 | 350 | 2 | 105 | -80% damage | Hull 400, Fuel Tank 200, Engine 200, Rotor Tail 300, Rotor Main 400 |
| AH-6R [Rockets] | 18800 | 250000 | Pilot 20 | 350 | 2 | 105 | -80% damage | Hull 400, Fuel Tank 200, Engine 200, Rotor Tail 300, Rotor Main 400 |
| Z20 Lakota | 7400 | 35000 | Pilot 10 | 445 | 13 | 120 | -95% damage | Hull 600, Fuel Tank 200, Rotor Tail 300, Engine 200, Rotor Main 400 |
| Z20 Lakota [Miniguns] | 15000 | 85000 | Pilot 25 | 445 | 13 | 120 | -95% damage | Hull 600, Fuel Tank 200, Rotor Tail 300, Engine 200, Rotor Main 400 |
| M113 APC SV | - | - | - | 71 | 10 | 100 | Immune | Hull 1000, Fuel Tank 100, Tracks Left 125, Tracks Right 125, Radar 100 |
| M113 APC SV | - | - | - | 71 | 10 | 100 | Immune | Hull 1000, Fuel Tank 100, Tracks Left 125, Tracks Right 125, Radar 100 |
| M113 APC SV | - | - | - | 71 | 10 | 100 | Immune | Hull 1000, Fuel Tank 100, Tracks Left 125, Tracks Right 125, Radar 100 |
| Flakpanzer Gepard | 14000 | 300000 | Driver 25 | 65 | 2 | 110 | Immune | Hull 800, Fuel Tank 150, Engine 150, Tracks Left 125, Tracks Right 125 |
| SPH-2 | 8000 | 350000 | Driver 31 | 65 | 3 | 105 | Immune | Hull 650, Fuel Tank 100, Engine 150, Tracks Left 125, Tracks Right 125 |
| L2A6 | 22000 | 500000 | Driver 35 | 65 | 3 | 110 | Immune | Hull 800, Fuel Tank 150, Engine 150, Tracks Left 125, Tracks Right 125, Body Strong Area 350 |
| T-72B3 | 16000 | 250000 | Driver 20 | 60 | 3 | 110 | Immune | Hull 750, Fuel Tank 150, Engine 150, Tracks Left 125, Tracks Right 125 |
| Bobcat | 500 | - | - | 75 | 2 | 18 | -92.5% damage | Hull 200, Engine 200, Fuel Tank 100, Wheel Left Front 75, Wheel Left Rear 75, Wheel Right Front 75, Wheel Right Rear 75 |
| Dune Buggy | 1500 | 25000 | Driver 9 | 130 | 3 | 68 | -90% damage | Hull 400, Engine 400, Fuel Tank 300, Wheel Left Front 75, Wheel Left Rear 75, Wheel Right Front 75, Wheel Right Rear 75 |
| Humvee | 3000 | 75000 | Driver 14 | 113 | 4 | 65 | -95% damage | Hull 500, Engine 400, Fuel Tank 400, Wheel Left Front 75, Wheel Left Rear 75, Wheel Right Front 75, Wheel Right Rear 75 |
| Humvee [M249] | 8500 | 125000 | Driver 24 | 113 | 5 | 65 | -96% damage | Hull 500, Engine 400, Fuel Tank 400, Wheel Left Front 75, Wheel Left Rear 75, Wheel Right Front 75, Wheel Right Rear 75 |
| Humvee [Minigun] | 10000 | 150000 | Driver 30 | 113 | 5 | 65 | -97.5% damage | Hull 500, Engine 400, Fuel Tank 400, Wheel Left Front 75, Wheel Left Rear 75, Wheel Right Front 75, Wheel Right Rear 75 |
| Kodiak | 2500 | - | - | 154 | 4 | 50 | -95% damage | Hull 400, Engine 400, Fuel Tank 400, Wheel Left Front 75, Wheel Left Rear 75, Wheel Right Front 75, Wheel Right Rear 75 |
| Kodiak [M249] | 6750 | 50000 | Driver 6 | 154 | 5 | 50 | -95% damage | Hull 400, Engine 400, Fuel Tank 400, Wheel Left Front 75, Wheel Left Rear 75, Wheel Right Front 75, Wheel Right Rear 75 |
| Kodiak [Pickup] | 3000 | 35000 | Driver 10 | 154 | 6 | 50 | -95% damage | Hull 400, Engine 400, Fuel Tank 400, Wheel Left Front 75, Wheel Left Rear 75, Wheel Right Front 75, Wheel Right Rear 75 |
| Ural Defender [M249] | 9500 | 100000 | Driver 22 | 79 | 5 | 85 | -95% damage | Hull 700, Engine 400, Fuel Tank 400, Body Moderate Area 1, Wheel Left Front 75, Wheel Left Middle 75, Wheel Left Rear 75, Wheel Right Front 75, Wheel Right Middle 75, Wheel Right Rear 75 |
| Ural Defender | 7200 | 75000 | Driver 15 | 79 | 3 | 85 | -95% damage | Hull 700, Engine 400, Fuel Tank 400, Body Moderate Area 1, Wheel Left Front 75, Wheel Left Middle 75, Wheel Left Rear 75, Wheel Right Front 75, Wheel Right Middle 75, Wheel Right Rear 75 |
| Ural | 5000 | 35000 | Driver 3 | 79 | 3 | 85 | -95% damage | Hull 700, Engine 400, Fuel Tank 400, Body Moderate Area 1, Wheel Left Front 75, Wheel Left Middle 75, Wheel Left Rear 75, Wheel Right Front 75, Wheel Right Middle 75, Wheel Right Rear 75 |
| Wolf-110 | 2200 | 50000 | Driver 8 | 129 | 4 | 60 | -95% damage | Hull 500, Engine 400, Fuel Tank 400, Wheel Left Front 75, Wheel Left Rear 75, Wheel Right Front 75, Wheel Right Rear 75 |
| Wolf-110 [M2 Browning] | 7800 | 125000 | Driver 16 | 129 | 5 | 60 | -95% damage | Hull 500, Engine 400, Fuel Tank 400, Wheel Left Front 75, Wheel Left Rear 75, Wheel Right Front 75, Wheel Right Rear 75 |

Emplacement hull HP: MG Nest 1800, Talon 9K-SAM 3000, L81 Mortar 3000, Vanguard CIWS 5000, Stingray 3000, Loudspeaker 1000.
RPG-7 on vehicles: 110 damage, 12 m blast, 2.5 m full-effect radius (vehicle damage calculator). AP rounds add roughly +17.5% to vehicles.

## 5. Building

* FOB build range is **60 m** (Base Builder), not the 30 m the mod currently assumes. 59 buildable pieces in total. Season 2 added Large Shelter, Wooden Step and MG Nest; sandbags and barbed wire can sit on top of Bremer walls.
* Structure supply costs are shipped with the Base Builder tool but not exposed in the text we fetched.

## 6. Progression

`research/data/progression_ladder.txt` lists every unlock by level and price (Wardog career: 57 levels, $2 645 000 of fees; six role tracks to 100);
`research/data/unlock_grid.json` holds the beta ladder per track with internal item ids. Season 2 made levels come "a lot faster".

## 7. HUD and controls (kennel.gg guide + settings guides)

* Always-on elements: **cash, compass, health, killfeed, equipped item**, plus central reward popups and a minimap.
* Map shows the Control Zone outline (2x2 km), the roaming Hot Zone, faction positions, FOB markers, vehicle spawn/vendor icons.
* Squad markers show alive/downed/dead; proximity-voice range indicator; separate squad channel indicator.
* Combat HUD: ballistics/drop reticle hints, vehicle status (fuel, ammo, health/damage state), construction-radius indicator near a FOB.
* Radial menu on middle mouse: SQUAD, REQUESTS (healing, ammo, supplies), EMOTES, LOCAL. Buy ammo/supplies on B. Hardcore removes the killcam, trims the HUD and limits the kill feed to your squad.
* No pixel-accurate screenshots of the HUD or vendor screen were found; the only Steam screenshot shows gameplay without HUD.

## 8. Where this contradicted the mod (status)

DONE (wave 1): armour, hit zones, HP/AP vs armour, falloff, kill reward, XP, vehicle stats, FOB range. Since then also done: Control Zone variants, bases/vendors/towers/boards/loot at real coordinates, per-section vehicle damage, bullet drop/travel time. NOTE: the Kennel guide says the Control Zone outline is "2x2 km" while the marker data gives radius 500 m; the mod uses 500 m (change `radius` in sites.json if the real zone proves larger).

| Area | Mod now | Evidence |
|---|---|---|
| Armour absorption | 10/20/35/50% | 30/40/55/65% with durability (armour 200-300, helmets 80-200) |
| Hit zones | head / body only | 12 zones with per-class multipliers |
| HP / AP vs armour | invented 0.5 / 1.5 | HP -30%, AP +30% (and -20% unarmoured) |
| Damage falloff | none | per-weapon curves |
| Kill reward position | victim only, x5 / x10 | half shooter, half victim; kill-type bases; flat bonuses |
| XP per kill | 0.4 x dollars | flat 250 role XP |
| Vehicle stats | invented | real prices, speed, seats, fuel, per-section HP |
| FOB radius | 30 m | 60 m |
| Control Zone | random circle by command | fixed variants around (-219,-1025), radius 500 m |
| Bases / vendors | none | exact coordinates above |
