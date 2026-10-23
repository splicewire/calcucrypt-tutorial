"""Extend the accepted 254-package isolated example; do not install or boot it."""
from pathlib import Path
import argparse
import hashlib
import json
import subprocess

root = Path(__file__).resolve().parents[2]
p = argparse.ArgumentParser(description=__doc__)
p.add_argument('--base-manifest', type=Path, required=True)
p.add_argument('--base-lock', type=Path, required=True)
for name in ['market', 'beam-commerce', 'tenancy']:
    p.add_argument('--' + name + '-source', type=Path, required=True)
a = p.parse_args()
raw = a.base_lock.read_bytes()
lock = json.loads(raw)
packages = lock['packages'] + lock['packages-dev']
identities = sorted([(x['name'], x['version'], x.get('source', {}).get('reference')) for x in packages])
identity = hashlib.sha256(json.dumps(identities, separators=(',', ':')).encode()).hexdigest()
if identity != '69ae10c6f41d052e0be32b49eed4634dd6644988978103dab1941964a667a321':
    p.error('Expected exact accepted 254-package version/source identities, including the proof-only Lunar patch.')
out, out_lock = root / 'composer.paid.json', root / 'composer.paid.lock'
if out.exists() or out_lock.exists():
    p.error('Refusing to overwrite an alternate context.')
m = json.loads(a.base_manifest.read_text())
for section, entries in [('require', lock['packages']), ('require-dev', lock['packages-dev'])]:
    for x in entries:
        version = x['version']
        if version.startswith('dev-'):
            version += '#' + x['source']['reference']
        # Preserve the selected explicit Lunar alpha compatibility alias.
        if x['name'] == 'lunarphp/core':
            version += ' as 2.0.0-alpha.6'
        m.setdefault(section, {})[x['name']] = version
sources = [
    ('market', 'splicewire/laravel-beam-market', 'df2c1710e1fb77a6c83880c02db7af813ae28b85'),
    ('beam_commerce', 'splicewire/laravel-beam-commerce', '42c8d495630d4d3f3b174a57f5e9abb836350a09'),
    ('tenancy', 'splicewire/laravel-beam-tenancy', '4b527177171f2732699a9470abcdb376b3f93896'),
]
repos = []
for key, name, ref in sources:
    source = getattr(a, key + '_source').resolve(strict=True)
    def git(*args):
        return subprocess.check_output(['git', *args], cwd=source, text=True).strip()
    if git('rev-parse', 'HEAD') != ref or git('status', '--porcelain=v1', '--untracked-files=no'):
        p.error('Source must be clean at selected ref: ' + name)
    if json.loads((source / 'composer.json').read_text())['name'] != name:
        p.error('Package identity mismatch: ' + name)
    branch = git('branch', '--show-current')
    if not branch:
        p.error('Use an explicit named local VCS branch: ' + name)
    m['require'][name] = 'dev-' + branch + '#' + ref
    repos.append({'type': 'vcs', 'url': str(source), 'only': [name]})
m['require']['rushing/laravel-postgres-rls'] = 'dev-main#74567d0edab0f7bf7ace4bd598af3861d41cfa59'
repos.append({'type': 'git', 'url': 'https://github.com/stephenr85/laravel-postgres-rls.git', 'only': ['rushing/laravel-postgres-rls']})
m['repositories'] = repos + m.get('repositories', [])
m.setdefault('extra', {}).setdefault('merge-plugin', {})['include'] = []
out.write_text(json.dumps(m, indent=4) + '\n')
out_lock.write_bytes(raw)
print('Prepared paid alternate inputs only. Dry-run, installed audit and native preparation remain required.')
