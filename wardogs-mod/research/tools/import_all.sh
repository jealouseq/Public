#!/bin/bash
# Rebuilds every third-party model import from jars you extracted yourself into local-data/imports/<name>/ (see research/IMPORTS.md).
# The results go to local-data/generated, which is git-ignored and picked up by the Gradle build (unless -PnoImports).
set -e
cd "$(dirname "$0")"
I=../../local-data/imports
[ -d $I/gcaa-0.2.8-1.20.1 ] && python3 import_gcaa.py $I/gcaa-0.2.8-1.20.1
[ -d $I/LAV-1.16.5-V1 ] && python3 import_lav.py $I/LAV-1.16.5-V1
[ -d $I/Modern-Warfare-Content-Pack-1.12.2-5.8.2 ] && python3 import_flan.py $I/Modern-Warfare-Content-Pack-1.12.2-5.8.2
[ -d $I/TacCraft_0.9_1.12.2 ] && python3 armor_import.py $I/TacCraft_0.9_1.12.2
echo "done: local-data/generated"
