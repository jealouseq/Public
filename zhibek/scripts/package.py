"""Сборка архивов для установки в WordPress: install/zhibek-theme.zip и install/zhibek-cattery.zip.
Запуск из корня проекта: python3 scripts/package.py

CSS и JS темы в архиве сжимаются (terser и csso через npx, нужен Node.js). Исходники остаются в theme/.
Если Node.js нет, архив собирается из несжатых файлов."""
import os, shutil, subprocess, tempfile, zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SKIP = {'.DS_Store'}


def minify(dst):
    """Сжимает css/js в копии темы; при ошибке оставляет исходный файл."""
    done = 0
    for base, _, files in os.walk(os.path.join(dst, 'assets')):
        for f in files:
            p = os.path.join(base, f)
            if f.endswith('.js'):
                cmd = ['npx', '--yes', 'terser', p, '--compress', '--mangle', '--format', 'comments=false', '-o', p + '.min']
            elif f.endswith('.css'):
                cmd = ['npx', '--yes', 'csso-cli', p, '-o', p + '.min']
            else:
                continue
            try:
                subprocess.run(cmd, check=True, capture_output=True, timeout=120)
                if os.path.getsize(p + '.min') > 0:
                    os.replace(p + '.min', p); done += 1
            except Exception as e:
                print('  не сжато:', os.path.relpath(p, dst), type(e).__name__)
                if os.path.exists(p + '.min'):
                    os.remove(p + '.min')
    return done


def build(src, folder, out, compress=False):
    os.makedirs(os.path.dirname(out), exist_ok=True)
    tmp = tempfile.mkdtemp()
    try:
        work = os.path.join(tmp, folder)
        shutil.copytree(src, work, ignore=shutil.ignore_patterns(*SKIP))
        if compress:
            print(folder, 'сжато файлов:', minify(work))
        with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
            for base, _, files in sorted(os.walk(work)):
                for f in sorted(files):
                    p = os.path.join(base, f)
                    z.write(p, os.path.relpath(p, tmp))
    finally:
        shutil.rmtree(tmp, ignore_errors=True)
    print(out, os.path.getsize(out) // 1024, 'KB')


build(os.path.join(ROOT, 'theme'), 'zhibek', os.path.join(ROOT, 'install', 'zhibek-theme.zip'), compress=True)
build(os.path.join(ROOT, 'plugin', 'zhibek-cattery'), 'zhibek-cattery', os.path.join(ROOT, 'install', 'zhibek-cattery.zip'))
