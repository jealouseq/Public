# Third-party model imports (local only)

Some models in a developer build come from community mods the project owner supplied. They are **not ours**: the licences are
"All Rights Reserved" or not stated, so nothing from them is committed, and a build that contains them must not be handed to anyone.
Superb Warfare is GPL-3.0: its assets could go into the repository only if this whole project is licensed GPL-3.0-or-later (today it says
"All Rights Reserved") and the authors are credited - the owner's decision, not made here.

| Pack (what was supplied) | Used for | Tool |
|---|---|---|
| *Guns Craft - Adventurer's Arsenal* gcaa 0.2.8 (1.20.1, All Rights Reserved) | 32 gun item models (AK-12, AKM, M4A1, AUG, M249, M60E4, MP5, Vector, ...) with their textures | `import_gcaa.py`, `geo_import.py` (GeckoLib / Bedrock geometry to OBJ) |
| *Immersive Vehicle - LAV* 1.16.5 (All rights reserved) | T-72B3 tank, MT-LB (as the M113) with textures; tracks added here | `import_lav.py`, `objlib.py` |
| Flan's Mod *Modern Warfare Content Pack* 5.8.2 (1.12.2) | Humvee (3 variants, roof guns added here), Leopard 2A6 (as the L2A6), Little Bird (MH-6 / AH-6), Black Hawk (Z-20), Hind (Havoc), RPG-7, AT4 (MAAWS), Stinger (Verba), GL6 (MGL-40); rotors are built here | `import_flan.py`, `flan_import.py` (replays the compiled Java model classes) |
| *Superb Warfare* (1.20.1, **GPL-3.0**; Atsuishio, Roki27, Light_Quanta and contributors) | 16 guns (AK-12, AK-47, QBZ-191, SKS, SVD, MK14, Mosin, AWM, M98B, M60, M870, Glock 17/18, MP5, RPG) with textures, and the vehicles of the AA gun (LAV-AD), SPH (PLZ-05), pickups, the APC (Bradley), AH-6 and Mi-28 helicopters with separate spinning rotors | `import_sbw.py`, `geo_import.py` |
| *TacCraft* 0.9 (1.12.2, MCreator) | 8 worn helmets and vests drawn on the player | `armor_import.py` |
| *TF51 Basic Content Pack* (1.12.2) | looked at, nothing used yet (its guns are low-detail Flan boxes) | |

## How to use

1. Extract each jar (they are zip files) into `local-data/imports/<jar name without .jar>/`.
2. `research/tools/import_all.sh` - wipes and rebuilds (later importers override earlier ones per weapon / vehicle), writing models and textures into `local-data/generated/assets/wardogs` (git-ignored).
3. `./gradlew runClient` / `runServer` now show them: the build copies `local-data/generated` over the bundled models.
4. `./gradlew build -PnoImports` builds without them - use this for anything you share.

Without the imports the mod still has complete models: the CC0 Quaternius guns, the CC0 off-road truck and the procedural vehicles.
The worn-armour layer (`WornArmorLayer`) draws nothing when no armour mesh exists.

## Why a replay of bytecode

Flan's Mod and MCreator models are Java classes, not data files. `flan_import.py` and `armor_import.py` disassemble them with `javap` and replay
the constructor on a small stack machine (no foreign code runs). Two details were found by testing against the pack: the 24 offsets of
`addShapeBox` come in the order *y-min face loop, then y-max face loop* (only that order gives faces without self-intersections on 4 000
boxes), and a positive offset pulls a corner inward.
