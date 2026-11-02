#!/usr/bin/env python3
"""Apply the decoded community datasets in research/data to the mod's balance JSON (idempotent).

Run from the wardogs-mod directory:  python3 research/tools/apply_research.py
"""
import json, re, os

BAL = "src/main/resources/data/wardogs/balance"
RD = "research/data"
load = lambda p: json.load(open(p))
def save(p, d): json.dump(d, open(p, "w"), indent=1, ensure_ascii=False)
norm = lambda s: re.sub(r"[^a-z0-9]", "", s.lower())

lab = load(f"{RD}/ballistics_lab.json")

# --- hit zones ---------------------------------------------------------------------------
CLASS = {"assault_rifle": "Assault Rifle", "smg": "Submachine Gun", "lmg": "Light Machine Gun", "shotgun": "Shotgun",
         "marksman_rifle": "Marksman Rifle", "sniper_rifle": "Sniper Rifle", "pistol": "Pistol", "bow": "Combat Bow"}
save(f"{BAL}/hitzones.json", {
    "_comment": "Hit-zone multipliers on a round's base damage (metaforge ballistics lab pack, see research/DATA_SOURCES.md). 'class_of' maps the mod's weapon categories to classes; launchers/emplacements/vehicle guns use flat damage.",
    "class_of": CLASS, "zones": lab["hitboxes"]})

# --- weapons: falloff curves and base damage ---------------------------------------------
W = load(f"{BAL}/weapons.json")
byname = {}
for p in lab["weapons"]:
    byname[norm(p["name"])] = p; byname[norm(p["slug"])] = p
for wid, w in W["weapons"].items():
    p = byname.get(norm(w["name"])) or byname.get(norm(wid))
    if p and p.get("falloff"):
        w["falloff"] = p["falloff"]
        w["velocity"] = p.get("muzzleVelocity")
    elif "falloff" not in w:
        w["falloff"] = [[0, 1], [w["range"], 1], [1200, 0.2]]  # same shape as the measured curves
    for k in ("falloff",):
        if "estimated" in w and p and k in w["estimated"]:
            w["estimated"].remove(k)
W["_comment"] = ("Damage/RPM/price: community measurements. 'falloff' is [[distance m, multiplier], ...] from the ballistics-lab pack "
                 "(default shape for the 4 weapons without data). Fields in 'estimated' are NOT from sources. chest/head are for a full shot at the "
                 "torso/head multiplier of the weapon's class; shotguns split them across pellets.")
save(f"{BAL}/weapons.json", W)

# --- armour and ammunition ---------------------------------------------------------------
A = load(f"{BAL}/armor.json")
red = {r["slot"]: {} for r in lab["armour"]}
for r in lab["armour"]:
    lvl = int(re.search(r"Level (\d)", r["name"]).group(1))
    red[r["slot"]][str(lvl)] = (r["reduction"] / 100.0, r["durability"])
for lvl, (rd, du) in red["helmet"].items():
    A["helmets"][lvl]["reduction"] = rd; A["helmets"][lvl]["durability"] = du
for lvl, (rd, du) in red["armor"].items():
    A["vests"][lvl]["reduction"] = rd; A["vests"][lvl]["durability"] = du
A["helmets"]["3"]["unlock_level"] = 70; A["vests"]["3"]["unlock_level"] = 70
A["_comment"] = ("price/weight/unlock from metabot.gg and the progression ladder (L3 unlocks at WARDOG 70). reduction and durability from the ballistics-lab pack "
                 "(30/40/55/65%). player_health 100 is the pack's assumed health.")
A["player_health_estimated"] = False
save(f"{BAL}/armor.json", A)

M = load(f"{BAL}/ammo.json")
for r in lab["rounds"]:
    key = {"FMJ": "fmj", "HollowPoint": "hp", "ArmorPiercing": "ap"}[r["key"]]
    M["ammo"][key] = {"flesh": 1.0 + r["vsUnarmoured"] / 100.0, "armor": 1.0 + r["vsArmour"] / 100.0}
M["armor_estimated"] = False
M["_comment"] = "Multipliers vs unarmoured flesh and vs armour/helmet (ballistics-lab pack: HP +100% / -30%, AP -20% / +30%)."
save(f"{BAL}/ammo.json", M)

# --- structures: FOB radius and emplacement hit points -----------------------------------
S = load(f"{BAL}/structures.json")
S["fob_radius"] = 60.0
for sid, hp in {"mg_nest": 1800, "talon_sam": 3000, "l81_mortar": 3000, "vanguard_ciws": 5000}.items():
    if sid in S["structures"]:
        S["structures"][sid]["hp"] = hp
S["_comment_fob"] = "FOB build range 60 m (Base Builder); emplacement hull HP from the wardogs.zone database."
save(f"{BAL}/structures.json", S)

# --- rewards ----------------------------------------------------------------------------
R = load(f"{BAL}/rewards.json")
R["kill"].update({
    "base_by_class": {"assault": 400, "small": 300, "sniper": 600, "explosive": 300, "artillery": 250, "aircraft": 300, "tank": 300},
    "shooter_weight": 0.5, "target_weight": 0.5, "shutdown_mult": 1.5, "revived_victim_keep": 0.01,
    "role_xp": 250, "streak_kills_cap": 5, "repeat_victim_penalty": 0.24})
R["_comment"] = "Season 2 Cash & XP calculator (wardogs.zone): half the zone bonus from the shooter's position and half from the victim's; flat 250 role XP per kill."
save(f"{BAL}/rewards.json", R)

# --- vehicles: real prices, speeds, seats, fuel, hull and bullet resistance --------------
V = load(f"{BAL}/vehicles.json")
Z = load(f"{RD}/vehicles_wardogs_zone.json")
alias = {  # mod id -> wardogs.zone page key
    "bobcat": "land-wheeled-bobcat-default", "dune_buggy": "land-wheeled-dunebuggy-default", "kodiak": "land-wheeled-kodiak-default",
    "kodiak_m249": "land-wheeled-kodiak-machinegun", "kodiak_pickup": "land-wheeled-kodiak-pickup", "wolf_110": "land-wheeled-whl_08-default",
    "wolf_110_m2": "land-wheeled-whl_08-machinegun", "humvee": "land-wheeled-humvee-default", "humvee_m249": "land-wheeled-humvee-machinegun",
    "humvee_minigun": "land-wheeled-humvee-minigun", "ural": "land-wheeled-ural-default", "ural_defender": "land-wheeled-ural-battle",
    "ural_defender_m249": "land-wheeled-ural-attack", "l2a6": None, "t72b3": "land-tracked-tnk_04-default", "flakpanzer_gepard": None,
    "sph2": None, "mh6": "air-rotary-littlebird-default", "ah6m": "air-rotary-littlebird-mountedmachineguns", "ah6r": "air-rotary-littlebird-rocketpods",
    "uh1y": None, "uh1y_miniguns": None, "z20_lakota": None, "z20_lakota_miniguns": None, "havoc": "air-rotary-havoc-default"}
by_title = {}
for key, v in Z.items():
    by_title.setdefault(norm(v["name"]), v)
title_of = {"t72b3": "T-72B3", "flakpanzer_gepard": "Flakpanzer Gepard", "sph2": "SPH-2", "l2a6": "L2A6", "z20_lakota": "Z20 Lakota",
            "z20_lakota_miniguns": "Z20 Lakota [Miniguns]", "uh1y": "Z20 Lakota"}
for dup in ("uh1y", "uh1y_miniguns"):  # the UH-1Y was renamed Z20 Lakota in Season 2: same vehicle, same price
    V["vehicles"].pop(dup, None)
for vid, vd in V["vehicles"].items():
    z = Z.get(alias.get(vid) or "") or by_title.get(norm(title_of.get(vid, vd["name"])))
    if not z or z.get("cost") is None:
        continue
    vd["price"] = int(z["cost"])
    if z.get("unlock"):
        vd["cost"] = int(z["unlock"])
    if z.get("req"):
        vd["role"] = z["req"][0].lower(); vd["level"] = int(z["req"][1])
    if z.get("seats"):
        vd["seats"] = int(z["seats"])
    if z.get("speed_kmh"):
        vd["speed_kmh"] = z["speed_kmh"]
        vd["speed"] = round(z["speed_kmh"] / 72.0, 3)  # blocks per tick (km/h / 3.6 / 20)
    if z.get("fuel_l"):
        vd["fuel"] = float(z["fuel_l"])
        m = re.search(r"~?\s*([\d.]+)\s*km", z.get("range") or "")
        if m:
            vd["range_km"] = float(m.group(1))
    if z["hull"]:
        vd["hp"] = float(z["hull"].get("Hull", vd["hp"]))
        vd["sections"] = z["hull"]
    sa = (z.get("small_arms") or "")
    if sa.startswith("Immune"):
        vd["small_arms"] = 0.0
    else:
        m = re.search(r"-([\d.]+)%", sa)
        if m:
            vd["small_arms"] = round(1.0 - float(m.group(1)) / 100.0, 4)
    vd["source"] = "wardogs.zone"
# the M113 APC "spawn vehicle": one per faction, not for sale; a forward spawn point that can drive (wardogs.zone: land-tracked-spawnvehicle-*)
zm = Z.get("land-tracked-spawnvehicle-lonestar")
if zm:
    V["vehicles"]["m113"] = {"name": "M113 APC SV", "category": "apc", "flying": False, "hp": zm["hull"]["Hull"], "speed": round(zm["speed_kmh"] / 72.0, 3),
        "seats": int(zm["seats"]), "fuel": zm["fuel_l"], "w": 2.7, "h": 2.4, "d": 5.0, "price": 0, "role": "any", "level": 0, "cost": 0,
        "small_arms": 0.0, "explosive": 0.5, "anti_tank": 1.0, "kill_reward": 2000, "block": "minecraft:green_concrete_powder",
        "speed_kmh": zm["speed_kmh"], "range_km": 18.0, "sections": zm["hull"], "source": "wardogs.zone"}
V["_comment"] = ("price, unlock, level, speed, seats, fuel, hull hp and bullet resistance from wardogs.zone (Season 2). speed is blocks/tick; "
                 "explosive/anti_tank multipliers, kill rewards (except sourced ones), sizes and gun stats are still ESTIMATES. ap_vs_vehicle from the same source.")
V["ap_vs_vehicle"] = 1.175
V["air_speed_scale"] = 0.5
save(f"{BAL}/vehicles.json", V)
print("applied; vehicles updated from wardogs.zone:", sum(1 for v in V["vehicles"].values() if v.get("source")))
