# WARDOGS: Bakurani (Minecraft mod)

Tactical 3-faction combat inspired by WARDOGS, for **Minecraft Java 1.21.1 / NeoForge 21.1.265** (Java 21).
Research and source numbers: [RESEARCH.md](RESEARCH.md).

## Status (milestone 1: combat core)
Done and covered by tests:
- Data-driven balance (`src/main/resources/data/wardogs/balance/*.json`, overridable by a datapack, reloads with `/reload`).
- 34 firearms with measured chest/head damage, RPM, price; ammo types FMJ/HP/AP.
- Hitscan with headshots, wall blocking, spread, magazines, reload (key R), fire-rate scheduling.
- Money wallet ($10 000 start) and kill rewards: headshot, distance tiers, zone multipliers, streak, repeat victim, revenge.
- Dev commands: `/wd give <weapon>`, `/wd money [set|add] <n>`, `/wd weapons`.

Not yet built: armor, grenades/mines, medic/revive, vendor GUI, Control Zone + scoring, FOB building, vehicles, Bakurani map, HUD, real models/sounds.

Fields flagged `estimated` in `weapons.json` (reload time, spread, some magazines/ranges) and the assumed 100 player health
are placeholders: the sources do not publish them.

## Build & test
```
./gradlew build            # compiles, unit tests, jar in build/libs
./gradlew runGameTestServer # integration tests on a headless server
./gradlew runClient        # play it
```
