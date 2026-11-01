# WARDOGS: Bakurani (Minecraft mod)

Tactical 3-faction combat inspired by WARDOGS, for **Minecraft Java 1.21.1 / NeoForge 21.1.265** (Java 21).
Research and source numbers: [RESEARCH.md](RESEARCH.md).

## Status (milestone 2: match loop)
Done and covered by tests (unit + 34 server GameTests):
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
- Teamkill costs 3x the kill reward. Wallet ($10 000 start). `/wd buy <item>` shop command.
- Players have 100 HP (not 20 hearts). Bullets deal WARDOGS damage 1:1, vanilla damage is scaled x5. Vanilla hearts are hidden;
  the HUD shows the number and a heart that fills in proportion to health.
- HUD (money, faction, three score bars), sent only to the owning player. A match forces PvP on.

Admin commands (op): `/wd zone here <radius>`, `/wd match start|stop|status`, `/wd give <item>`, `/wd money set|add <n>`, `/wd weapons`.
Quick start: `/wd zone here 60`, `/wd team lonestar`, `/wd match start`, stand in the zone, `/wd buy m4`.

Not yet built: vehicles,
the Bakurani map, real models/sounds. The HUD and key binding compile but were never seen on a screen.

Fields flagged `estimated` in the balance JSON (reload time, spread, some magazines/ranges, armor absorption, HP/AP vs armor)
and the assumed 100 player health are placeholders: the sources do not publish them.

## Build & test
```
./gradlew build            # compiles, unit tests, jar in build/libs
./gradlew runGameTestServer # integration tests on a headless server
./gradlew runClient        # play it
```
