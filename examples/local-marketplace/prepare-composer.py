"""Prepare the selected isolated dependency context; never install or boot an app."""
from pathlib import Path
import argparse
import hashlib
import json
import subprocess

root = Path(__file__).resolve().parents[2]
parser = argparse.ArgumentParser(description=__doc__)
for name in ['market', 'licenser', 'commerce', 'lunar']:
    parser.add_argument('--' + name + '-source', type=Path, required=True)
args = parser.parse_args()
lock_bytes = (root / 'composer.lock').read_bytes()
if hashlib.sha256(lock_bytes).hexdigest() != '3a770fe49f04c5751eb91fa98b2af8591e6518f2128a6bcf8072306e5d1e9304':
    parser.error('This recipe binds the accepted 225-package starter lock; ground a new delta for another lock.')
output = root / 'composer.market.json'
output_lock = root / 'composer.market.lock'
if output.exists() or output_lock.exists():
    parser.error('Refusing to overwrite an existing alternate context.')
manifest = json.loads((root / 'composer.json').read_text())
lock = json.loads(lock_bytes)
for section, packages in [('require', lock['packages']), ('require-dev', lock['packages-dev'])]:
    for package in packages:
        version = package['version']
        if version.startswith('dev-'):
            version += '#' + package['source']['reference']
        manifest.setdefault(section, {})[package['name']] = version
manifest['require'].update(json.loads((Path(__file__).parent / 'selected-additions.json').read_text()))
manifest['require'].update({
    'spatie/laravel-activitylog': '4.12.3', 'laravel/scout': 'v10.25.0',
    'guzzlehttp/guzzle': '7.16.0', 'guzzlehttp/promises': '2.6.0', 'guzzlehttp/psr7': '2.13.2',
    'league/commonmark': '2.10.4', 'symfony/http-foundation': 'v8.1.9',
})
selected = [
    ('market', 'splicewire/laravel-beam-market', '09beb6843dedc3cef49b710308c4efa19afb7565'),
    ('licenser', 'splicewire/laravel-beam-licenser', '8336a345f20d7f8338fb1c042d08e71b3c67baa3'),
    ('commerce', 'rushing/laravel-commerce', 'aef4bfbdce492bbcc91c3027a46bdeb1042a4a77'),
    ('lunar', 'lunarphp/core', '1adeac5a3f404452cabf85095fc839afd713f6d8'),
]
repositories = []
for key, package, ref in selected:
    source = getattr(args, key + '_source').resolve(strict=True)
    actual = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=source, text=True).strip()
    dirty = subprocess.check_output(['git', 'status', '--porcelain=v1', '--untracked-files=no'], cwd=source, text=True)
    if actual != ref or dirty:
        parser.error(f'{package} must be clean at the exact selected ref {ref}.')
    metadata = json.loads((source / 'composer.json').read_text())
    if metadata['name'] != package:
        parser.error('Source package identity mismatch: ' + package)
    branch = subprocess.check_output(['git', 'branch', '--show-current'], cwd=source, text=True).strip()
    if not branch:
        parser.error('Use a named local branch for the explicit VCS source: ' + package)
    manifest['require'][package] = 'dev-' + branch + '#' + ref + (' as 2.0.0-alpha.6' if key == 'lunar' else '')
    repositories.append({'type': 'vcs', 'url': str(source), 'only': [package]})
manifest['repositories'] = repositories + manifest.get('repositories', [])
manifest.setdefault('extra', {}).setdefault('merge-plugin', {})['include'] = []
output.write_text(json.dumps(manifest, indent=4) + '\n')
output_lock.write_bytes(lock_bytes)
print('Prepared alternate manifest and copied original lock. No install, scripts, audit or app boot performed.')
