#!/usr/bin/env python3
"""Disposable example only. Native Surgeon is trusted CLI, not an ownership gate."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import stat
import subprocess
import sys

URL = 'packages/stale-checkout'
FILES = ['composer.json', 'composer.local.json', 'composer.lock',
         'packages/kept/composer.json', 'canary.txt']
MARKER = '.overlay-example.json'


def digest(data):
    return hashlib.sha256(data).hexdigest()


def read_json(path):
    # Never follow symlinks even for an input manifest.
    if path.is_symlink() or not path.is_file():
        raise ValueError('Expected regular JSON file: ' + str(path))
    data = json.loads(path.read_text())
    if not isinstance(data, dict):
        raise ValueError('Expected JSON object')
    return data


def identity(path):
    s = path.stat()
    return [s.st_dev, s.st_ino]


def real_directory(path):
    path = Path(os.path.abspath(path))
    if not path.is_dir() or path.resolve() != path:
        raise ValueError('Expected real directory without symlink components')
    return path


def owned(path):
    data = read_json(path / '.openrig-scratch-owner.json')
    if (data.get('schema') != 'openrig.scratch-owner/v2'
            or data.get('path') != str(path)
            or [data.get('dev'), data.get('ino')] != identity(path)
            or data.get('owner') != os.environ.get('OPENRIG_SESSION_NAME')
            or not data.get('owner') or not data.get('id')):
        raise ValueError('Exclusive scratch ownership receipt required')
    return data['id']


def locations(root, fixture):
    root, fixture = real_directory(root), real_directory(fixture)
    if fixture.parent != root or fixture == root:
        raise ValueError('Fixture must be a direct child of the owned run root')
    return root, fixture


def git(fixture, *args):
    return subprocess.check_output(['git', '-C', str(fixture), *args])


def snapshot(fixture):
    result = {}
    for p in fixture.rglob('*'):
        rel = p.relative_to(fixture)
        if rel.parts[0] == '.git':
            continue
        if p.is_symlink():
            raise ValueError('Symlink in fixture')
        if p.is_file():
            result[str(rel)] = [digest(p.read_bytes()), stat.S_IMODE(p.stat().st_mode)]
        elif not p.is_dir():
            raise ValueError('Nonregular fixture payload')
    return result


def validate(fixture, *, repaired=False):
    snapshot(fixture)  # reject parent-directory symlinks before reading manifests
    base = read_json(fixture / 'composer.json')
    overlay = read_json(fixture / 'composer.local.json')
    read_json(fixture / 'composer.lock')
    kept = read_json(fixture / 'packages/kept/composer.json')
    if base.get('require') != {'fixture/kept': 'dev-main'} or overlay.get('require') != base['require'] or kept.get('name') != 'fixture/kept':
        raise ValueError('Kept package/requiredness changed')
    expected = ['packages/kept'] if repaired else ['packages/kept', URL]
    repos = overlay.get('repositories')
    if not isinstance(repos, list) or [r.get('url') for r in repos] != expected or any(r.get('type') != 'path' for r in repos):
        raise ValueError('Unexpected or duplicate path repository')
    for url in expected:
        target = fixture / url
        if not target.resolve().is_relative_to(fixture) or target.is_symlink():
            raise ValueError('Path escapes fixture')
    if os.path.lexists(fixture / URL):
        raise ValueError('Stale checkout reappeared; diagnose and preview again')
    snapshot(fixture)


def prepare(root, fixture):
    root, fixture = locations(root, fixture)
    rid, fid = owned(root), owned(fixture)
    # Cannot bless an existing starter/site or arbitrary owner files as disposable.
    if set(p.name for p in fixture.iterdir()) != {'.openrig-scratch-owner.json'}:
        raise ValueError('Prepare requires an empty registered fixture')
    proof = root / (fixture.name + '-backup')
    if proof.exists() or proof.is_symlink():
        raise ValueError('Backup already exists')
    proof.mkdir(mode=0o700)
    (fixture / 'packages/kept').mkdir(parents=True)
    data = {
        'composer.json': {'name': 'fixture/overlay-example', 'require': {'fixture/kept': 'dev-main'}},
        'composer.local.json': {'require': {'fixture/kept': 'dev-main'}, 'repositories': [
            {'type': 'path', 'url': 'packages/kept'}, {'type': 'path', 'url': URL}],
            'extra': {'example-canary': ['preserve', 25]}},
        'composer.lock': {'_readme': ['Illustrative fixture metadata; never installed'], 'packages': [], 'packages-dev': []},
        'packages/kept/composer.json': {'name': 'fixture/kept', 'version': 'dev-main'},
    }
    for name, value in data.items():
        (fixture / name).write_text(json.dumps(value, indent=2) + '\n')
    (fixture / 'canary.txt').write_text('Preserve this unrelated file.\n')
    marker = dict(schema='beam.overlay-example/v1', root=str(root), fixture=str(fixture),
                  rootIdentity=identity(root), fixtureIdentity=identity(fixture), rootOwner=rid, fixtureOwner=fid,
                  lockSha256=digest((fixture / 'composer.lock').read_bytes()))
    (fixture / MARKER).write_text(json.dumps(marker, indent=2) + '\n')
    subprocess.run(['git', 'init', '-q', str(fixture)], check=True)
    subprocess.run(['git', '-C', str(fixture), 'add', '--', *FILES, MARKER, '.openrig-scratch-owner.json'], check=True)
    subprocess.run(['git', '-C', str(fixture), '-c', 'user.name=Overlay Example', '-c', 'user.email=fixture@invalid',
                    'commit', '-qm', 'Disposable overlay example baseline'], check=True)
    validate(fixture)
    before = (fixture / 'composer.local.json').read_bytes()
    (proof / 'before.json').write_bytes(before)
    (proof / 'before.json').chmod(0o600)
    state = dict(marker=marker, snapshot=snapshot(fixture), overlayIdentity=identity(fixture / 'composer.local.json'),
                 head=git(fixture, 'rev-parse', 'HEAD').decode().strip(), index=digest(git(fixture, 'ls-files', '--stage')))
    (proof / 'state.json').write_text(json.dumps(state, indent=2) + '\n')
    (proof / 'state.json').chmod(0o600)
    return state


def guard(root, fixture, *, repaired=False):
    root, fixture = locations(root, fixture)
    # Marker checked BEFORE reading a potential owner-like fixture's lock/overlay.
    marker = read_json(fixture / MARKER)
    if (marker.get('schema') != 'beam.overlay-example/v1' or marker.get('root') != str(root)
            or marker.get('fixture') != str(fixture) or marker.get('rootIdentity') != identity(root)
            or marker.get('fixtureIdentity') != identity(fixture)
            or marker.get('rootOwner') != owned(root) or marker.get('fixtureOwner') != owned(fixture)):
        raise ValueError('Disposable marker identity mismatch')
    snapshot(fixture)  # refuse symlinks before reading lock bytes
    if (fixture / '.git').is_symlink() or not (fixture / '.git').is_dir():
        raise ValueError('Expected independent real fixture Git directory')
    if digest((fixture / 'composer.lock').read_bytes()) != marker.get('lockSha256'):
        raise ValueError('Fixture lock changed; refusing write')
    proof = real_directory(root / (fixture.name + '-backup'))
    state = read_json(proof / 'state.json')
    if state['marker'] != marker or digest((proof / 'before.json').read_bytes()) != state['snapshot']['composer.local.json'][0]:
        raise ValueError('Backup/marker changed')
    validate(fixture, repaired=repaired)
    expected = read_json(proof / 'applied.json') if repaired else state
    if snapshot(fixture) != expected['snapshot'] or identity(fixture / 'composer.local.json') != state['overlayIdentity']:
        raise ValueError('Fixture bytes/mode/file identity changed')
    if git(fixture, 'rev-parse', 'HEAD').decode().strip() != state['head'] or digest(git(fixture, 'ls-files', '--stage')) != expected['index']:
        raise ValueError('Fixture HEAD/index changed')
    return root, fixture, proof, state


def native(host, php, fixture, action):
    host = real_directory(host)
    if host.parent != fixture.parent or host == fixture or (host / 'artisan').is_symlink():
        raise ValueError('Command host must be a separate sibling inside run root')
    argv = [php, str(host / 'artisan'), 'surgeon:overlay', *action, '--base=' + str(fixture)]
    # Fixed option list; never --composer. Inherit the isolated host environment.
    return subprocess.run(argv, cwd=host, env={**os.environ, 'XDEBUG_MODE': 'off'}, check=True)


def apply(root, fixture, host, php):
    root, fixture, proof, state = guard(root, fixture)
    native(host, php, fixture, ['remove', URL, '--apply'])
    validate(fixture, repaired=True)
    actual = snapshot(fixture)
    sidecar = '.surgeon/overlay-manifest.json'
    if set(actual) != set(state['snapshot']) | {sidecar}:
        raise ValueError('Unexpected native touch set; preserve fixture for inspection')
    for name in state['snapshot']:
        if name != 'composer.local.json' and actual[name] != state['snapshot'][name]:
            raise ValueError('Unrelated file changed')
    old = json.loads((proof / 'before.json').read_bytes())
    old['repositories'] = old['repositories'][:1]
    if read_json(fixture / 'composer.local.json') != old:
        raise ValueError('Unexpected overlay semantics')
    if git(fixture, 'rev-parse', 'HEAD').decode().strip() != state['head']:
        raise ValueError('Native command committed unexpectedly')
    (proof / 'applied.json').write_text(json.dumps(dict(snapshot=actual, index=digest(git(fixture, 'ls-files', '--stage'))), indent=2) + '\n')


def restore(root, fixture):
    root, fixture, proof, state = guard(root, fixture, repaired=True)
    overlay = fixture / 'composer.local.json'
    overlay.write_bytes((proof / 'before.json').read_bytes())
    overlay.chmod(state['snapshot']['composer.local.json'][1])
    # Only this example's two native touched paths; no reset/clean or native rollback claim.
    subprocess.run(['git', '-C', str(fixture), 'restore', '--staged', '--', 'composer.local.json', '.surgeon/overlay-manifest.json'], check=True)
    (fixture / '.surgeon/overlay-manifest.json').unlink()
    (fixture / '.surgeon').rmdir()
    guard(root, fixture)


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('action', choices=['prepare', 'diagnose', 'preview', 'apply', 'restore'])
    p.add_argument('--root', required=True, type=Path)
    p.add_argument('--fixture', required=True, type=Path)
    p.add_argument('--host', type=Path)
    p.add_argument('--php', help='Explicit Herd PHP executable for native command actions')
    a = p.parse_args()
    if a.action in ['diagnose', 'preview', 'apply'] and not a.php:
        p.error('--php is required for native command actions')
    try:
        if a.action == 'prepare':
            prepare(a.root, a.fixture)
        elif a.action == 'restore':
            restore(a.root, a.fixture)
        elif a.action == 'apply':
            apply(a.root, a.fixture, a.host, a.php)
        else:
            root, fixture = locations(a.root, a.fixture)
            validate(fixture, repaired=len(read_json(fixture / 'composer.local.json').get('repositories', [])) == 1)
            before = snapshot(fixture)
            index = git(fixture, 'ls-files', '--stage')
            native(a.host, a.php, fixture, ['list', '--json'] if a.action == 'diagnose' else ['remove', URL, '--json'])
            if snapshot(fixture) != before or git(fixture, 'ls-files', '--stage') != index:
                raise ValueError('Read/preview changed fixture')
        return 0
    except (ValueError, OSError, KeyError, TypeError, subprocess.CalledProcessError) as e:
        print('REFUSED [overlay example] ' + str(e), file=sys.stderr)
        return 2


if __name__ == '__main__':
    sys.exit(main())
