"""Сборка архивов для установки в WordPress: install/zhibek-theme.zip и install/zhibek-cattery.zip.
Запуск из корня проекта: python3 scripts/package.py"""
import os, zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
# файлы шрифтов прежней версии оформления — в архив не попадают
SKIP = {'Manrope.woff2', 'NotoSans-fallback.woff2', 'NotoSerifDisplay-roman.woff2', 'NotoSerifDisplay-italic.woff2',
        'OFL-manrope.txt', 'OFL-notosans.txt', 'OFL-notoserifdisplay.txt', '.DS_Store'}


def build(src, folder, out):
    os.makedirs(os.path.dirname(out), exist_ok=True)
    with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for base, _, files in sorted(os.walk(src)):
            for f in sorted(files):
                if f in SKIP:
                    continue
                p = os.path.join(base, f)
                z.write(p, os.path.join(folder, os.path.relpath(p, src)))
    print(out, os.path.getsize(out) // 1024, 'KB')


build(os.path.join(ROOT, 'theme'), 'zhibek', os.path.join(ROOT, 'install', 'zhibek-theme.zip'))
build(os.path.join(ROOT, 'plugin', 'zhibek-cattery'), 'zhibek-cattery', os.path.join(ROOT, 'install', 'zhibek-cattery.zip'))
