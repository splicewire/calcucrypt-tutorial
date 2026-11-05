import { test, after } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, cpSync, existsSync, rmSync, chmodSync, realpathSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync, spawnSync } from 'node:child_process';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const scripts = fileURLToPath(new URL('../', import.meta.url));
const roots = [];
after(() => roots.forEach(p => rmSync(p, { recursive: true, force: true })));
const release = `github:splicewire/beam#${'a'.repeat(40)}&path:/beam-ux`;
function fixture({ spec = release, overrides = false } = {}) {
    const root = mkdtempSync(join(tmpdir(), 'pnpm-overlay-')); roots.push(root);
    const repo = join(root, 'consumer'); mkdirSync(repo); cpSync(scripts, join(repo, 'scripts'), { recursive: true });
    const local = join(root, 'beam-ux'); mkdirSync(local); writeFileSync(join(local, 'package.json'), JSON.stringify({ name: '@splicewire/beam-ux', version: '0.5.0' }));
    const manifest = JSON.stringify({ name: 'consumer', packageManager: 'pnpm@9.15.3', dependencies: { '@splicewire/beam-ux': spec }, ...(overrides ? { pnpm: { overrides: { '@splicewire/beam-ux': spec } } } : {}) });
    writeFileSync(join(repo, 'package.json'), manifest); writeFileSync(join(repo, 'pnpm-lock.yaml'), 'lockfileVersion: 9.0\n# canonical\n');
    writeFileSync(join(repo, '.gitignore'), '/pnpm.local.json\n/.pnpmfile.cjs\n/node_modules\n');
    const git = (...args) => execFileSync('git', ['-C', repo, ...args], { encoding: 'utf8' }).trim();
    git('init', '-q'); git('add', '.'); git('-c', 'user.name=Test', '-c', 'user.email=test@example.test', 'commit', '-qm', 'fixture');
    writeFileSync(join(repo, 'pnpm.local.json'), JSON.stringify({ packages: { '@splicewire/beam-ux': { release, path: local } } }));
    const stub = join(root, 'pnpm'); writeFileSync(stub, `#!/usr/bin/env node
const fs=require('node:fs');
if(process.argv[2]==='--version'){console.log('9.15.3');process.exit(0)}
fs.appendFileSync(process.env.PNPM_TEST_LOG,JSON.stringify(process.argv.slice(2))+'\\n');
if(process.argv.includes('--ignore-pnpmfile')) {if(fs.readFileSync('pnpm-lock.yaml','utf8')!=='lockfileVersion: 9.0\\n# canonical\\n')process.exit(2);process.exit(process.env.PNPM_FAIL_OFF==='1'?3:0);}
const hook=require(process.cwd()+'/.pnpmfile.cjs');const manifest=JSON.parse(fs.readFileSync('package.json'));const out=hook.hooks.readPackage(manifest);if(!out.dependencies['@splicewire/beam-ux'].startsWith('link:'))process.exit(4);
fs.writeFileSync('pnpm-lock.yaml','lockfileVersion: 9.0\\npnpmfileChecksum: local\\n# overlay\\n');process.exit(process.env.PNPM_FAIL_ON==='1'?5:0);
`); chmodSync(stub, 0o755);
    const run = (verb, env = {}) => spawnSync(process.execPath, [join(repo, 'scripts/pnpm-overlay.mjs'), verb], { cwd: repo, encoding: 'utf8', env: { ...process.env, PNPM_OVERLAY_PNPM: stub, PNPM_TEST_LOG: join(root, 'calls'), ...env } });
    const read = f => readFileSync(join(repo, f), 'utf8');
    return { root, repo, local, git, run, read, manifest };
}
function succeeds(p) { assert.equal(p.status, 0, p.stderr + p.stdout); }
function refuses(p, re) { assert.notEqual(p.status, 0); assert.match(p.stderr, re); }
test('enable/disable leaves manifest and index flags alone and restores exact canonical lock', () => {
    const f=fixture(); succeeds(f.run('on')); assert.equal(f.read('package.json'),f.manifest); assert.match(f.read('pnpm-lock.yaml'),/pnpmfileChecksum/);
    assert.deepEqual(f.git('ls-files','-v','package.json','pnpm-lock.yaml').split('\n').map(x=>x[0]),['H','H']);
    assert.match(f.git('status','--porcelain'),/M pnpm-lock.yaml/); succeeds(f.run('on')); succeeds(f.run('off'));
    assert.equal(f.read('package.json'),f.manifest); assert.equal(f.read('pnpm-lock.yaml'),'lockfileVersion: 9.0\n# canonical\n'); assert.ok(!existsSync(join(f.repo,'.pnpmfile.cjs'))); assert.equal(f.git('status','--porcelain'),'');
});
test('hook covers transitive and optional exact specs without rewriting peer ranges', () => {
    const f=fixture(); succeeds(f.run('on')); const h=require(join(f.repo,'.pnpmfile.cjs')).hooks;
    const p={ name:'another-package', dependencies:{'@splicewire/beam-ux':release}, optionalDependencies:{'@splicewire/beam-ux':release}, peerDependencies:{'@splicewire/beam-ux':'^0.5.0'} };
    const o=h.readPackage(p); assert.equal(o.dependencies['@splicewire/beam-ux'],`link:${realpathSync(f.local)}`); assert.equal(o.optionalDependencies['@splicewire/beam-ux'],`link:${realpathSync(f.local)}`); assert.equal(o.peerDependencies['@splicewire/beam-ux'],'^0.5.0'); assert.equal(p.dependencies['@splicewire/beam-ux'],release);
    assert.throws(()=>h.readPackage({dependencies:{'@splicewire/beam-ux':'^0.5.0'}}),/exact|release/);
});
for (const file of ['package.json','pnpm-lock.yaml','pnpm.local.json','.pnpmfile.cjs']) test(`disable refuses ${file} drift without changing it`, () => {
    const f=fixture(); succeeds(f.run('on')); const modified=f.read(file)+'\n ';writeFileSync(join(f.repo,file),modified);refuses(f.run('off'),/drift/);assert.equal(f.read(file),modified);assert.ok(existsSync(join(f.repo,'.pnpmfile.cjs')));
});
test('refuses registry inputs, first-party overrides, wrong package names and non-full refs', () => {
    refuses(fixture({spec:'^0.5.0'}).run('on'),/exact|release/);refuses(fixture({overrides:true}).run('on'),/overrides/);
    const f=fixture();writeFileSync(join(f.local,'package.json'),'{"name":"wrong"}');refuses(f.run('on'),/name/);
    const g=fixture();writeFileSync(join(g.repo,'pnpm.local.json'),JSON.stringify({packages:{'@splicewire/beam-ux':{release:'github:splicewire/beam#abc',path:g.local}}}));refuses(g.run('on'),/full|exact/);
});
test('refuses dirty, staged and hidden-index canonical inputs', () => {
    for(const mode of ['dirty','staged','skip','assume']) {const f=fixture();if(mode==='dirty'||mode==='staged'){writeFileSync(join(f.repo,'package.json'),f.manifest+'\n');if(mode==='staged')f.git('add','package.json');}else f.git('update-index',mode==='skip'?'--skip-worktree':'--assume-unchanged','package.json');refuses(f.run('on'),/clean|hidden|index/);assert.ok(!existsSync(join(f.repo,'.pnpmfile.cjs')));}
});
test('does not overwrite an existing hook or run over legacy overlay', () => {
    const f=fixture();writeFileSync(join(f.repo,'.pnpmfile.cjs'),'// owner hook');refuses(f.run('on'),/hook|exists/);assert.equal(f.read('.pnpmfile.cjs'),'// owner hook');
    const g=fixture();mkdirSync(join(g.repo,'.git/js-overlay'));writeFileSync(join(g.repo,'.git/js-overlay/package.json'),'{}');refuses(g.run('on'),/legacy/);
});
test('failed enable retains a guarded recoverable record; disable restores canonical bytes', () => {
    const f=fixture();refuses(f.run('on',{PNPM_FAIL_ON:'1'}),/failed/);succeeds(f.run('off'));assert.equal(f.git('status','--porcelain'),'');
});
test('failed canonical reinstall keeps a retryable disable state', () => {
    const f=fixture();succeeds(f.run('on'));refuses(f.run('off',{PNPM_FAIL_OFF:'1'}),/failed/);assert.ok(!existsSync(join(f.repo,'.pnpmfile.cjs')));assert.equal(f.read('pnpm-lock.yaml'),'lockfileVersion: 9.0\n# canonical\n');succeeds(f.run('off'));
});
test('changed committed baseline refuses disable rather than discarding a new lock', () => {
    const f=fixture();succeeds(f.run('on'));f.git('add','pnpm-lock.yaml');f.git('-c','user.name=Test','-c','user.email=test@example.test','commit','-qm','bad overlay commit');refuses(f.run('off'),/baseline|index|committed/);
});

test('legacy adapter refuses the new mode before rewriting manifests', () => {
    const f=fixture();succeeds(f.run('on'));const before=f.read('package.json');
    const p=spawnSync(process.execPath,[join(f.repo,'scripts/js-overlay.mjs'),'on'],{cwd:f.repo,encoding:'utf8'});refuses(p,/pnpm-overlay/);assert.equal(f.read('package.json'),before);
});
