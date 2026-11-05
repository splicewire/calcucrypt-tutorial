// Tracked implementation; scripts/pnpm-overlay generates the ignored root .pnpmfile.cjs.
// pnpm.local.json plays composer.local.json's role: machine-local source selection, never release selection.
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const family = name => /^@(splicewire|schemastud)\//.test(name);
const exactRelease = spec => typeof spec === 'string' && /^(?:github:[\w.-]+\/[\w.-]+|git\+https:\/\/github\.com\/[\w.-]+\/[\w.-]+\.git)#[a-f0-9]{40}(?:&path:\/(?:[\w.-]+\/)*[\w.-]+)?$/.test(spec) && !spec.includes('/../');
function readMap(root) {
    const map = JSON.parse(fs.readFileSync(path.join(root, 'pnpm.local.json'), 'utf8'));
    if (!map.packages || Array.isArray(map.packages) || typeof map.packages !== 'object' || !Object.keys(map.packages).length) throw Error('pnpm.local.json must declare packages');
    return Object.fromEntries(Object.entries(map.packages).map(([name, entry]) => {
        if (!family(name) || !entry || !exactRelease(entry.release)) throw Error(`${name}: expected an exact full-SHA Git release spec`);
        if (typeof entry.path !== 'string' || !entry.path) throw Error(`${name}: local path required`);
        const local = path.resolve(root, entry.path.startsWith('~/') ? path.join(os.homedir(), entry.path.slice(2)) : entry.path);
        const pkg = JSON.parse(fs.readFileSync(path.join(local, 'package.json'), 'utf8'));
        if (pkg.name !== name) throw Error(`${name}: local package name is ${pkg.name}`);
        return [name, { release: entry.release, path: fs.realpathSync(local) }];
    }));
}
function createHook(root) {
    return { hooks: { readPackage(pkg) {
        const entries = readMap(root);
        const result = { ...pkg };
        // Peer ranges express compatibility, not resolution; leave peerDependencies untouched.
        for (const section of ['dependencies', 'devDependencies', 'optionalDependencies']) {
            if (!pkg[section]) continue;
            result[section] = { ...pkg[section] };
            for (const [name, spec] of Object.entries(pkg[section])) {
                if (!entries[name]) continue;
                if (spec !== entries[name].release) throw Error(`${name}: dependency does not match configured exact release`);
                result[section][name] = `link:${entries[name].path}`;
            }
        }
        return result;
    } } };
}
module.exports = createHook;
module.exports.readMap = readMap;
module.exports.family = family;
