// Tests for scripts/js-overlay: node --test scripts/tests/
//
// Every case builds a SCRATCH git repo from this starter's committed JS manifests (read with `git show`, never the working
// tree) plus scratch copies of the family packages, and nothing is installed or linked against a live checkout.
// The fast cases use a stub pnpm; set JS_OVERLAY_REAL=1 to also run a real `pnpm install` round trip in scratch.

import { execFileSync, spawnSync } from 'node:child_process';
import { cpSync, mkdirSync, mkdtempSync, readFileSync, realpathSync, rmSync, writeFileSync, chmodSync } from 'node:fs';
import { homedir, tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { after, describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';

const STARTER = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const SCRIPTS = join(STARTER, 'scripts');
const scratchRoots = [];
after(() => scratchRoots.forEach((d) => rmSync(d, { recursive: true, force: true })));

const committed = (file) => execFileSync('git', ['-C', STARTER, 'show', `HEAD:${file}`], { encoding: 'utf8' });

// A stub pnpm: logs its arguments; a plain install changes the lock (as a real relink does); --frozen-lockfile insists the
// lock is exactly the committed one (as a real frozen install over the committed lock would).
const STUB = `#!/usr/bin/env bash
echo "$*" >> "$STUB_LOG"
if [[ "$*" == "install --frozen-lockfile" ]]; then
  diff -q pnpm-lock.yaml <(git show HEAD:pnpm-lock.yaml) >/dev/null || { echo "stub: lock differs" >&2; exit 1; }
elif [[ "$1" == "install" ]]; then
  echo "# stub relock" >> pnpm-lock.yaml
fi
`;

function scratch({ links } = {}) {
    const root = mkdtempSync(join(tmpdir(), 'js-overlay-'));
    scratchRoots.push(root);
    const repo = join(root, 'starter');
    mkdirSync(join(repo, 'scripts'), { recursive: true });
    for (const file of ['package.json', 'pnpm-lock.yaml', '.npmrc', 'pnpm-workspace.yaml']) writeFileSync(join(repo, file), committed(file));
    // The rollout (slice 02) gitignores js.local.json in each starter; the fixture does the same.
    writeFileSync(join(repo, '.gitignore'), `${committed('.gitignore')}js.local.json\n`);
    cpSync(join(SCRIPTS, 'js-overlay'), join(repo, 'scripts', 'js-overlay'));
    cpSync(join(SCRIPTS, 'js-overlay.mjs'), join(repo, 'scripts', 'js-overlay.mjs'));
    const g = (...args) => execFileSync('git', ['-C', repo, ...args], { encoding: 'utf8' });
    g('init', '-q');
    g('-c', 'user.email=t@t', '-c', 'user.name=t', 'add', '.');
    g('-c', 'user.email=t@t', '-c', 'user.name=t', 'commit', '-qm', 'fixture');

    for (const [name, dir] of [['@splicewire/beam-inertia', 'beam-inertia'], ['@schemastud/seam', 'seam']]) {
        mkdirSync(join(root, 'pkgs', dir), { recursive: true });
        writeFileSync(join(root, 'pkgs', dir, 'package.json'), JSON.stringify({ name, version: '9.9.9' }));
    }
    writeFileSync(
        join(repo, 'js.local.json'),
        JSON.stringify({ links: links ?? [`dep:@splicewire/beam-inertia=${root}/pkgs/beam-inertia`, `override:@schemastud/seam=${root}/pkgs/seam`] }),
    );
    const stub = join(root, 'pnpm-stub');
    writeFileSync(stub, STUB);
    chmodSync(stub, 0o755);
    const log = join(root, 'pnpm.log');
    writeFileSync(log, '');

    const run = (command, env = {}) =>
        spawnSync('bash', [join(repo, 'scripts', 'js-overlay'), command], {
            cwd: repo,
            encoding: 'utf8',
            env: { ...process.env, JS_OVERLAY_PNPM: stub, STUB_LOG: log, ...env },
        });
    const manifest = () => JSON.parse(readFileSync(join(repo, 'package.json'), 'utf8'));
    const hidden = () => g('ls-files', '-v', 'package.json', 'pnpm-lock.yaml').split('\n').filter(Boolean).map((l) => l[0]);
    const pnpmCalls = () => readFileSync(log, 'utf8').split('\n').filter(Boolean);
    return { root, repo, g, run, manifest, hidden, pnpmCalls };
}

describe('scripts/js-overlay', () => {
    it('round-trips: on links and hides, off restores the committed files and a frozen install', () => {
        const s = scratch();
        const on = s.run('on');
        assert.equal(on.status, 0, on.stderr);
        assert.equal(s.manifest().dependencies['@splicewire/beam-inertia'], `link:${s.root}/pkgs/beam-inertia`);
        assert.deepEqual(s.hidden(), ['S', 'S']);
        assert.equal(s.g('status', '--porcelain').trim(), '', 'git status must stay clean while the overlay is on');

        const off = s.run('off');
        assert.equal(off.status, 0, off.stderr);
        assert.equal(readFileSync(join(s.repo, 'package.json'), 'utf8'), committed('package.json'));
        assert.equal(readFileSync(join(s.repo, 'pnpm-lock.yaml'), 'utf8'), committed('pnpm-lock.yaml'));
        assert.deepEqual(s.hidden(), ['H', 'H']);
        assert.equal(s.g('status', '--porcelain').trim(), '');
        assert.deepEqual(s.pnpmCalls(), ['install', 'install --frozen-lockfile']);
    });

    it('links an override-pinned package through pnpm.overrides', () => {
        const s = scratch();
        assert.equal(JSON.parse(committed('package.json')).pnpm.overrides['@schemastud/seam'], '0.2.1', 'fixture premise: seam is pinned');
        assert.equal(s.run('on').status, 0);
        assert.equal(s.manifest().pnpm.overrides['@schemastud/seam'], `link:${s.root}/pkgs/seam`);
    });

    it('is idempotent: a second on re-applies to the same manifest', () => {
        const s = scratch();
        assert.equal(s.run('on').status, 0);
        const first = readFileSync(join(s.repo, 'package.json'), 'utf8');
        const again = s.run('on');
        assert.equal(again.status, 0, again.stderr);
        assert.equal(readFileSync(join(s.repo, 'package.json'), 'utf8'), first);
        assert.deepEqual(s.hidden(), ['S', 'S']);
        assert.equal(s.g('status', '--porcelain').trim(), '');
    });

    it('off refuses a real dependency change made while the overlay was on, and changes nothing', () => {
        const s = scratch();
        assert.equal(s.run('on').status, 0);
        const m = s.manifest();
        m.dependencies['left-pad'] = '^1.3.0';
        writeFileSync(join(s.repo, 'package.json'), `${JSON.stringify(m, null, 4)}\n`);

        const off = s.run('off');
        assert.notEqual(off.status, 0);
        assert.match(off.stderr, /dependencies\.left-pad/);
        assert.match(off.stderr, /again after the overlay is off/);
        assert.equal(s.manifest().dependencies['left-pad'], '^1.3.0', 'the change is still there');
        assert.deepEqual(s.hidden(), ['S', 'S'], 'still hidden: nothing was undone');
        assert.deepEqual(s.pnpmCalls(), ['install'], 'no frozen install ran');
    });

    it('off refuses a lock-only change made while the overlay was on (pnpm update within range)', () => {
        const s = scratch();
        assert.equal(s.run('on').status, 0);
        const lock = join(s.repo, 'pnpm-lock.yaml');
        writeFileSync(lock, `${readFileSync(lock, 'utf8')}# pnpm update left-pad\n`);

        const off = s.run('off');
        assert.notEqual(off.status, 0);
        assert.match(off.stderr, /pnpm-lock\.yaml/);
        assert.match(readFileSync(lock, 'utf8'), /# pnpm update left-pad/, 'the lock change is still there');
        assert.deepEqual(s.hidden(), ['S', 'S']);
    });

    it('off --discard-lock accepts lock-only drift explicitly, and the refusal names it', () => {
        const s = scratch();
        assert.equal(s.run('on').status, 0);
        const lock = join(s.repo, 'pnpm-lock.yaml');
        writeFileSync(lock, `${readFileSync(lock, 'utf8')}# a linked package's deps changed, then pnpm install\n`);

        const refused = s.run('off');
        assert.notEqual(refused.status, 0);
        assert.match(refused.stderr, /off --discard-lock/);

        const off = spawnSync('bash', [join(s.repo, 'scripts', 'js-overlay'), 'off', '--discard-lock'], {
            cwd: s.repo,
            encoding: 'utf8',
            env: { ...process.env, JS_OVERLAY_PNPM: join(s.root, 'pnpm-stub'), STUB_LOG: join(s.root, 'pnpm.log') },
        });
        assert.equal(off.status, 0, off.stderr);
        assert.equal(readFileSync(lock, 'utf8'), committed('pnpm-lock.yaml'));
        assert.equal(s.g('status', '--porcelain').trim(), '');
    });

    it('no path launders lock drift: a repeat on refuses it too, and on --accept-lock is the named way through', () => {
        // build.qa's reproduction against 5499f3b: on, a lock-only change, off refuses, on again, off, and the change was gone.
        const s = scratch();
        const run = (...args) =>
            spawnSync('bash', [join(s.repo, 'scripts', 'js-overlay'), ...args], {
                cwd: s.repo,
                encoding: 'utf8',
                env: { ...process.env, JS_OVERLAY_PNPM: join(s.root, 'pnpm-stub'), STUB_LOG: join(s.root, 'pnpm.log') },
            });
        assert.equal(run('on').status, 0);
        const lock = join(s.repo, 'pnpm-lock.yaml');
        writeFileSync(lock, `${readFileSync(lock, 'utf8')}# pnpm update left-pad\n`);

        assert.notEqual(run('off').status, 0, 'off refuses the drift');
        const again = run('on');
        assert.notEqual(again.status, 0, 'a repeat on refuses it too, instead of re-recording it silently');
        assert.match(again.stderr, /on --accept-lock/);
        assert.match(readFileSync(lock, 'utf8'), /# pnpm update left-pad/, 'still there');

        const accepted = run('on', '--accept-lock');
        assert.equal(accepted.status, 0, accepted.stderr);
        assert.equal(run('off').status, 0, 'the operator accepted the lock as the baseline, so off proceeds');
    });

    it('on --accept-lock never excuses a package.json change', () => {
        const s = scratch();
        assert.equal(s.run('on').status, 0);
        const m = s.manifest();
        m.dependencies['left-pad'] = '^1.3.0';
        writeFileSync(join(s.repo, 'package.json'), `${JSON.stringify(m, null, 4)}\n`);
        const on = spawnSync('bash', [join(s.repo, 'scripts', 'js-overlay'), 'on', '--accept-lock'], {
            cwd: s.repo,
            encoding: 'utf8',
            env: { ...process.env, JS_OVERLAY_PNPM: join(s.root, 'pnpm-stub'), STUB_LOG: join(s.root, 'pnpm.log') },
        });
        assert.notEqual(on.status, 0);
        assert.match(on.stderr, /dependencies\.left-pad/);
    });

    it('--discard-lock never excuses a package.json change', () => {
        const s = scratch();
        assert.equal(s.run('on').status, 0);
        const m = s.manifest();
        m.dependencies['left-pad'] = '^1.3.0';
        writeFileSync(join(s.repo, 'package.json'), `${JSON.stringify(m, null, 4)}\n`);
        const off = spawnSync('bash', [join(s.repo, 'scripts', 'js-overlay'), 'off', '--discard-lock'], {
            cwd: s.repo,
            encoding: 'utf8',
            env: { ...process.env, JS_OVERLAY_PNPM: join(s.root, 'pnpm-stub'), STUB_LOG: join(s.root, 'pnpm.log') },
        });
        assert.notEqual(off.status, 0);
        assert.match(off.stderr, /dependencies\.left-pad/);
    });

    it('off refuses nothing on a clean overlay, even after pnpm rewrote the lock', () => {
        const s = scratch();
        assert.equal(s.run('on').status, 0);
        assert.notEqual(readFileSync(join(s.repo, 'pnpm-lock.yaml'), 'utf8'), committed('pnpm-lock.yaml'), 'premise: the lock changed');
        assert.equal(s.run('off').status, 0);
    });

    it('on refuses to hide changes that were already there', () => {
        const s = scratch();
        const m = JSON.parse(committed('package.json'));
        m.dependencies['left-pad'] = '^1.3.0';
        writeFileSync(join(s.repo, 'package.json'), `${JSON.stringify(m, null, 4)}\n`);
        const on = s.run('on');
        assert.notEqual(on.status, 0);
        assert.match(on.stderr, /uncommitted changes/);
        assert.deepEqual(s.pnpmCalls(), []);
    });

    it('off works from what on recorded, even after js.local.json is gone', () => {
        const s = scratch();
        assert.equal(s.run('on').status, 0);
        rmSync(join(s.repo, 'js.local.json'));
        const off = s.run('off');
        assert.equal(off.status, 0, off.stderr);
        assert.equal(readFileSync(join(s.repo, 'package.json'), 'utf8'), committed('package.json'));
        assert.equal(s.g('status', '--porcelain').trim(), '');
    });

    it('off is a no-op when the overlay is not on', () => {
        const s = scratch();
        const off = s.run('off');
        assert.equal(off.status, 0);
        assert.match(off.stdout, /off already/);
        assert.deepEqual(s.pnpmCalls(), []);
    });
});

// Real pnpm, still scratch-only: the family packages are COPIED (no node_modules) into the scratch root, so `link:` never
// points at a live checkout, and pnpm never installs into one.
describe('scripts/js-overlay with a real pnpm', { skip: process.env.JS_OVERLAY_REAL !== '1' && 'set JS_OVERLAY_REAL=1' }, () => {
    it('links beam-inertia and override-pinned seam, and off restores the published install', () => {
        const s = scratch();
        const live = { 'beam-inertia': join(homedir(), 'Workspaces/js/packages/beam/beam-inertia'), seam: join(homedir(), 'Workspaces/js/packages/schemastud/seam') };
        for (const [dir, src] of Object.entries(live)) {
            rmSync(join(s.root, 'pkgs', dir), { recursive: true, force: true });
            cpSync(src, join(s.root, 'pkgs', dir), { recursive: true, filter: (p) => !p.includes('/node_modules') && !p.includes('/.git') });
        }
        const env = { JS_OVERLAY_PNPM: 'pnpm' };

        const on = s.run('on', env);
        assert.equal(on.status, 0, on.stderr);
        assert.equal(realpathSync(join(s.repo, 'node_modules/@splicewire/beam-inertia')), realpathSync(join(s.root, 'pkgs/beam-inertia')));
        assert.equal(realpathSync(join(s.repo, 'node_modules/@schemastud/seam')), realpathSync(join(s.root, 'pkgs/seam')));
        assert.equal(s.g('status', '--porcelain').trim(), '');

        const off = s.run('off', env);
        assert.equal(off.status, 0, off.stderr);
        assert.match(realpathSync(join(s.repo, 'node_modules/@schemastud/seam')), /node_modules\/\.pnpm\/@schemastud\+seam@0\.2\.1/);
        assert.equal(s.g('status', '--porcelain').trim(), '');
    });
});
