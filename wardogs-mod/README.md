# WARDOGS: Bakurani (Minecraft mod)

Tactical 3-faction combat inspired by WARDOGS, for **Minecraft Java 1.21.1 / NeoForge 21.1.265** (Java 21).
Research and source numbers: [RESEARCH.md](RESEARCH.md).

## Status (milestone 2: match loop)
Done and covered by tests (unit + 13 server GameTests):
- Data-driven balance (`src/main/resources/data/wardogs/balance/*.json`, overridable by a datapack, reloads with `/reload`).
- 34 firearms: measured chest/head damage, RPM, price; ammo FMJ/HP/AP; hitscan, headshots, wall blocking, spread, magazines, reload (R).
- Armor: helmets and body armor L1-L4 (equip by right-click); headshots hit the helmet, body hits hit the vest.
- Three factions (Lonestar, Valkyra, Manticore), `/wd team <faction>`.
- Control Zone + moving Hot Zone: money while inside, entry bonus, score every 30 s (x2 if the leader also leads the Hot Zone),
  first to 100 wins and top-3 factions get placement money. Kill rewards are x5 in the zone and x10 in the Hot Zone.
- Teamkill costs 3x the kill reward. Wallet ($10 000 start). `/wd buy <item>` shop command.
- HUD (money, faction, three score bars), sent only to the owning player.

Admin commands (op): `/wd zone here <radius>`, `/wd match start|stop|status`, `/wd give <item>`, `/wd money set|add <n>`, `/wd weapons`.
Quick start: `/wd zone here 60`, `/wd team lonestar`, `/wd match start`, stand in the zone, `/wd buy m4`.

Not yet built: downed/revive, grenades and mines, vendor GUI, role progression/unlocks, FOB building, vehicles,
the Bakurani map, real models/sounds. The HUD and key binding compile but were never seen on a screen.

Fields flagged `estimated` in the balance JSON (reload time, spread, some magazines/ranges, armor absorption, HP/AP vs armor)
and the assumed 100 player health are placeholders: the sources do not publish them.

## Build & test
```
./gradlew build            # compiles, unit tests, jar in build/libs
./gradlew runGameTestServer # integration tests on a headless server
./gradlew runClient        # play it
```
