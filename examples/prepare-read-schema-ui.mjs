import { existsSync, lstatSync, mkdirSync, readdirSync, symlinkSync, unlinkSync } from 'node:fs';
import { join, resolve } from 'node:path';

// Disposable source clones only. Run from the fresh host after committed pnpm install.
const host = process.cwd();
const frame = resolve(process.env.FRAME_JS_ROOT || '');
const beam = resolve(process.env.BEAM_UI_ROOT || '');
const tools = resolve(process.env.UI_BUILD_TOOLS || '');
if (!process.env.FRAME_JS_ROOT || !process.env.BEAM_UI_ROOT || !process.env.UI_BUILD_TOOLS) {
    throw Error('Set FRAME_JS_ROOT, BEAM_UI_ROOT and UI_BUILD_TOOLS explicitly');
}
for (const root of [frame, beam]) {
    if (existsSync(join(root, 'node_modules'))) throw Error('Use a new source clone: ' + root);
}
for (const root of [frame, beam]) {
    const dest = join(root, 'node_modules');
    mkdirSync(dest);
    for (const name of readdirSync(join(host, 'node_modules'))) {
        if (name.startsWith('.') || ['tsup', 'typescript', 'zustand', '@schemastud'].includes(name)) continue;
        symlinkSync(join(host, 'node_modules', name), join(dest, name));
    }
    mkdirSync(join(dest, '@schemastud'));
    for (const name of readdirSync(join(host, 'node_modules/@schemastud'))) {
        symlinkSync(name === 'frame' ? join(frame, 'frame') : join(host, 'node_modules/@schemastud', name), join(dest, '@schemastud', name));
    }
    for (const name of ['tsup', 'typescript', 'zustand', '.bin']) symlinkSync(join(tools, 'node_modules', name), join(dest, name));
}
for (const [name, target] of [['@schemastud/frame', join(frame, 'frame')], ['@splicewire/beam-inertia', join(beam, 'beam-inertia')]]) {
    const installed = join(host, 'node_modules', name);
    if (!lstatSync(installed).isSymbolicLink()) throw Error('Expected disposable pnpm link: ' + name);
    unlinkSync(installed);
    symlinkSync(target, installed);
}
console.log('Prepared isolated Frame/Beam build and host package links');
