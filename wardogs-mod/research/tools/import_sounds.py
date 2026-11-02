#!/usr/bin/env python3
"""Replaces the synthesised weapon sounds of a developer build by recordings from 'Superb Warfare' (GPL-3.0, see import_sbw.py and
research/IMPORTS.md): one recorded shot per weapon class, bullet impacts and explosions. Writes the files and a merged sounds.json into the
git-ignored local-data/generated tree.

usage: import_sounds.py <extracted Superb Warfare dir> [out dir = local-data/generated/assets/wardogs]
"""
import json, os, shutil, sys

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(HERE))
SRC = os.path.join(sys.argv[1], "assets/superbwarfare/sounds")
OUT = sys.argv[2] if len(sys.argv) > 2 else os.path.join(ROOT, "local-data/generated/assets/wardogs")

# our sound event -> recordings of the other mod (relative to its sounds folder, without .ogg); several = chosen at random
EVENTS = {
    "shot_pistol": ["gun/glock_17/glock_17_fire_3p", "gun/m_1911/m_1911_fire_3p"],
    "shot_smg": ["gun/mp_5/mp_5_fire_3p", "gun/vector/vector_fire_3p"],
    "shot_rifle": ["gun/ak_12/ak_12_fire_3p", "gun/m_4/m_4_fire_3p", "gun/ak_47/ak_47_fire_3p"],
    "shot_lmg": ["gun/m_60/m_60_fire_3p", "gun/rpk/rpk_fire_3p"],
    "shot_sniper": ["gun/awm/awm_fire_3p", "gun/svd/svd_fire_3p"],
    "shot_shotgun": ["gun/m_870/m_870_fire_3p"],
    "shot_launcher": ["gun/rpg/rpg_fire_3p"],
    "dry_fire": ["gun/ak_12/ak_12_reload_empty"],
    "impact_metal": ["bullet/metal_01", "bullet/metal_02", "bullet/metal_03", "bullet/metal_04", "bullet/metal_05"],
    "impact_block": ["bullet/bullet1", "bullet/bullet2", "bullet/bullet3", "bullet/bullet4", "bullet/bullet5", "bullet/bullet6"],
    "explosion": ["explosion/epic_explosion_close_01", "explosion/epic_explosion_close_02", "explosion/epic_explosion_close_03"],
}


def main():
    path = os.path.join(ROOT, "src/main/resources/assets/wardogs/sounds.json")
    sounds = json.load(open(path))
    for event, files in EVENTS.items():
        names = []
        for rel in files:
            src = os.path.join(SRC, rel + ".ogg")
            if not os.path.exists(src):
                print("missing", rel)
                continue
            dst_rel = "imp/" + rel.replace("/", "_")
            os.makedirs(os.path.join(OUT, "sounds/imp"), exist_ok=True)
            shutil.copy(src, os.path.join(OUT, "sounds", dst_rel + ".ogg"))
            names.append({"name": "wardogs:" + dst_rel, "stream": False})
        if names and event in sounds:
            sounds[event]["sounds"] = names
    os.makedirs(OUT, exist_ok=True)
    json.dump(sounds, open(os.path.join(OUT, "sounds.json"), "w"), indent=1)
    print("sound events replaced:", len(EVENTS))


if __name__ == "__main__":
    main()
