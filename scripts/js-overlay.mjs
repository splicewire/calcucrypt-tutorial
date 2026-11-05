// js-overlay — a local, uncommitted JS co-dev overlay for this starter (the JS twin of composer.local.json).
//
//   scripts/js-overlay on       rewrite package.json to link local family packages, install, hide both files from git.
//                               A repeat on runs the same guard; --accept-lock keeps a changed lock as the new baseline
//   scripts/js-overlay off      refuse if package.json holds anything but the overlay's links, or the lock changed; else
//                               restore both and reinstall. --discard-lock accepts lock-only drift explicitly
//   scripts/js-overlay status   say whether the overlay is on, and what it links
//
// Links come from the gitignored js.local.json (copy js.local.json.dist), in fresh-install.sh's JS_LINKS grammar:
//   { "links": ["dep:<name>=<path>", "override:<name>=<path>"] }
//   dep       rewrites the package.json dependency (or devDependency) specifier to link:<path>
//   override  rewrites the pnpm.overrides entry to link:<path> (a transitive family package the starter pins)
// A path may start with ~/ or be relative to this starter.
//
// Why package.json itself (launch ticket 00, "owner decision, a JS co-dev overlay", option 4): pnpm 9 reads overrides only
// from package.json, and its versions overrider runs after any pnpmfile hook, so nothing else can link an override-pinned
// package. `on` marks package.json and pnpm-lock.yaml --skip-worktree, so git status stays clean and the committed lock never
// changes. git's FAQ warns skip-worktree is not meant for this; that caveat was accepted knowingly, with the guard below.
//
// The guard (build.qa): while the overlay is on, a real dependency change lands in the hidden files and never shows in git
// status. `off` would discard it silently, so `off` refuses when package.json differs from what `on` wrote anywhere except the
// overlay's own link: entries, or when pnpm-lock.yaml differs from the lock `on` left (a lock-only `pnpm update`), and names
// what to re-apply after `off`. It reads the links `on` recorded, so `off` works even if js.local.json has gone.
//
// Dependency-free on purpose: .npmrc sets ignore-scripts=true, and this runs before any install.
// JS_OVERLAY_PNPM overrides the pnpm executable (the tests use a stub).

import { execFileSync, spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { dirname, isAbsolute, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const MANIFEST = 'package.json';
const LOCK = 'pnpm-lock.yaml';
const HIDDEN = [MANIFEST, LOCK];

const git = (...args) => execFileSync('git', args, { cwd: ROOT, encoding: 'utf8' });
const fail = (message) => {
    process.stderr.write(`js-overlay: ${message}\n`);
    process.exit(1);
};
const say = (message) => process.stdout.write(`js-overlay: ${message}\n`);

// The overlay's record lives inside the git directory, so it can never show up as an untracked file.
const stateDir = () => resolve(ROOT, git('rev-parse', '--git-path', 'js-overlay').trim());
const recordedPath = () => join(stateDir(), 'package.json');
const recordedLockPath = () => join(stateDir(), LOCK);
const recordedLinksPath = () => join(stateDir(), 'links.json');
const isOn = () => existsSync(recordedPath());

function readLinks() {
    const file = join(ROOT, 'js.local.json');
    if (!existsSync(file)) {
        fail('no js.local.json. Copy js.local.json.dist to js.local.json and edit the paths for this machine.');
    }
    const links = JSON.parse(readFileSync(file, 'utf8')).links ?? [];
    if (!Array.isArray(links) || links.length === 0) fail('js.local.json lists no links.');

    return links.map((entry) => {
        const match = /^(dep|override):(.+?)=(.+)$/.exec(entry);
        if (!match) fail(`unreadable link "${entry}" (expected dep:<name>=<path> or override:<name>=<path>)`);
        const [, kind, name, raw] = match;
        const path = raw.startsWith('~/') ? join(homedir(), raw.slice(2)) : isAbsolute(raw) ? raw : resolve(ROOT, raw);
        if (!existsSync(join(path, 'package.json'))) fail(`${name}: no package at ${path}`);
        return { kind, name, path };
    });
}

// Where a link lives in the manifest: [section, name] pairs a guard may treat as the overlay's own.
function slots(manifest, link) {
    if (link.kind === 'override') return [['pnpm.overrides', link.name]];
    return ['dependencies', 'devDependencies'].filter((s) => manifest[s]?.[link.name] !== undefined).map((s) => [s, link.name]);
}

function applyLinks(manifest, links) {
    const out = structuredClone(manifest);
    for (const link of links) {
        const spec = `link:${link.path}`;
        if (link.kind === 'override') {
            out.pnpm ??= {};
            out.pnpm.overrides ??= {};
            out.pnpm.overrides[link.name] = spec;
            continue;
        }
        const section = ['dependencies', 'devDependencies'].find((s) => out[s]?.[link.name] !== undefined);
        if (!section) fail(`${link.name}: not a dependency of this starter (use override: for a transitive package)`);
        out[section][link.name] = spec;
    }
    return out;
}

const get = (obj, dotted) => dotted.split('.').reduce((o, k) => (o == null ? undefined : o[k]), obj);

// Every JSON path where a and b differ, ignoring the overlay's own link: entries.
function driftPaths(recorded, current, links) {
    const ignored = new Set();
    for (const link of links) {
        for (const [section, name] of slots(recorded, link)) {
            const now = get(current, section)?.[name];
            if (typeof now === 'string' && now.startsWith('link:')) ignored.add(`${section}.${name}`);
        }
    }
    const paths = [];
    const walk = (a, b, at) => {
        if (ignored.has(at)) return;
        const objects = a && b && typeof a === 'object' && typeof b === 'object' && !Array.isArray(a) && !Array.isArray(b);
        if (!objects) {
            if (JSON.stringify(a) !== JSON.stringify(b)) paths.push(at || '(root)');
            return;
        }
        for (const key of new Set([...Object.keys(a), ...Object.keys(b)])) {
            // pnpm.overrides and the dependency sections hold names with dots, so join with a separator the ignore set uses.
            walk(a[key], b[key], at ? `${at}.${key}` : key);
        }
    };
    walk(recorded, current, '');
    return paths;
}

// The links `on` applied, as it recorded them: `off` must not depend on js.local.json still being there or unchanged.
const recordedLinks = () => (existsSync(recordedLinksPath()) ? JSON.parse(readFileSync(recordedLinksPath(), 'utf8')) : readLinks());

function guard({ checkLock = true } = {}) {
    const recorded = JSON.parse(readFileSync(recordedPath(), 'utf8'));
    const current = JSON.parse(readFileSync(join(ROOT, MANIFEST), 'utf8'));
    const drift = driftPaths(recorded, current, recordedLinks());
    // A lock-only change (pnpm update within range) leaves package.json alone but is just as hidden (build.qa). Compared only
    // when `on` got as far as recording the lock; an `on` whose install failed has nothing to compare against.
    const lockDrift =
        checkLock && existsSync(recordedLockPath()) && readFileSync(join(ROOT, LOCK), 'utf8') !== readFileSync(recordedLockPath(), 'utf8');
    if (lockDrift) drift.push(LOCK);
    if (drift.length > 0) {
        fail(
            `package.json or ${LOCK} has changes beyond the overlay's link: entries, and off would discard them:\n` +
                drift.map((p) => `  - ${p}`).join('\n') +
                `\nNote them, run the change again after the overlay is off (for example pnpm add/remove, then commit), ` +
                `or revert them by hand. Nothing was changed.` +
                (lockDrift && drift.length === 1
                    ? `\nIf only the lock differs because a linked package's own dependencies changed, choose explicitly:\n` +
                      `  scripts/js-overlay on --accept-lock    keep this lock as the overlay's baseline and re-apply\n` +
                      `  scripts/js-overlay off --discard-lock  drop it (off restores the committed lock anyway)`
                    : ''),
        );
    }
}

function pnpm(args, hint = '') {
    const bin = process.env.JS_OVERLAY_PNPM || 'pnpm';
    const run = spawnSync(bin, args, { cwd: ROOT, stdio: 'inherit' });
    if (run.status !== 0) fail(`${bin} ${args.join(' ')} failed (exit ${run.status}).${hint ? ` ${hint}` : ''}`);
}

const setHidden = (on) => git('update-index', on ? '--skip-worktree' : '--no-skip-worktree', ...HIDDEN);

function refuseGitReleaseOverlay() {
    const state = resolve(ROOT, git('rev-parse', '--git-path', 'pnpm-local-overlay').trim());
    if (existsSync(state) || existsSync(join(ROOT, '.pnpmfile.cjs'))) {
        fail('pnpm-overlay or another pnpmfile hook is present; disable it before using legacy js-overlay. Nothing was changed.');
    }
}

function on() {
    refuseGitReleaseOverlay();
    const links = readLinks();
    if (isOn()) {
        // A second `on` re-applies cleanly, but never over a change. It checks the lock too: re-recording drift silently would
        // make it the baseline the next `off` discards (build.qa, against 5499f3b). --accept-lock is the operator saying so.
        guard({ checkLock: !process.argv.slice(3).includes('--accept-lock') });
    } else {
        const changed = git('status', '--porcelain', '--', ...HIDDEN).trim();
        if (changed) fail(`uncommitted changes in ${HIDDEN.join(' / ')}; commit or revert them first, or the overlay would hide them:\n${changed}`);
    }

    const base = JSON.parse(git('show', `:${MANIFEST}`));
    const text = `${JSON.stringify(applyLinks(base, links), null, 4)}\n`;
    writeFileSync(join(ROOT, MANIFEST), text);
    mkdirSync(stateDir(), { recursive: true });
    writeFileSync(recordedPath(), text);
    writeFileSync(recordedLinksPath(), JSON.stringify(links));
    rmSync(recordedLockPath(), { force: true });

    // If this fails, package.json stays visibly modified in git status (not yet hidden): `off` restores it.
    pnpm(['install'], 'package.json is left modified and visible; run scripts/js-overlay off to restore it.');
    writeFileSync(recordedLockPath(), readFileSync(join(ROOT, LOCK), 'utf8'));
    setHidden(true);
    say(`on: ${links.map((l) => `${l.kind}:${l.name}`).join(', ')} linked; package.json and ${LOCK} hidden from git.`);
}

function off() {
    refuseGitReleaseOverlay();
    if (!isOn()) {
        say('off already.');
        return;
    }
    // --discard-lock: an explicit choice to drop lock-only drift (never a package.json change), which off would restore anyway.
    guard({ checkLock: !process.argv.slice(3).includes('--discard-lock') });

    setHidden(false);
    git('checkout', '--', ...HIDDEN);
    rmSync(stateDir(), { recursive: true, force: true });
    pnpm(['install', '--frozen-lockfile']);

    const left = git('status', '--porcelain', '--', ...HIDDEN).trim();
    if (left) fail(`off finished but git still shows changes:\n${left}`);
    say('off: package.json and the lock are as committed, and the published install is restored.');
}

function status() {
    if (!isOn()) {
        say('off.');
        return;
    }
    const links = recordedLinks();
    say(`on: ${links.map((l) => `${l.kind}:${l.name} -> ${l.path}`).join(', ')}`);
}

const commands = { on, off, status };
const command = commands[process.argv[2]];
if (!command) fail('usage: scripts/js-overlay on|off|status');
command();
