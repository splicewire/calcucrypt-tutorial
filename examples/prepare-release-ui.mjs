import { existsSync, lstatSync, symlinkSync, unlinkSync } from 'node:fs';
import { join, resolve } from 'node:path';
import './prepare-read-schema-ui.mjs';

// Build-only peer emitted by generate-release-workflow-peer.php from this host's installed DTOs.
const peer = process.env.RELEASE_WORKFLOW_PEER;
if (!peer || !existsSync(join(peer, 'types/workflows.d.ts')))
    throw Error('Set RELEASE_WORKFLOW_PEER to the generated scratch bundle');
const beam = resolve(process.env.BEAM_UI_ROOT);
for (const [name, target] of [
    ['@splicewire/beam-ux', join(beam, 'beam-ux')],
    ['@splicewire/beam-workflows', join(beam, 'beam-workflows')],
    ['@splicewire/_resources', resolve(peer)],
]) {
    const dest = join(process.cwd(), 'node_modules', name);
    if (
        existsSync(dest) ||
        (() => {
            try {
                return lstatSync(dest).isSymbolicLink();
            } catch {
                return false;
            }
        })()
    ) {
        if (!lstatSync(dest).isSymbolicLink())
            throw Error('Expected disposable package link: ' + dest);
        unlinkSync(dest);
    }
    symlinkSync(target, dest);
}
console.log(
    'Prepared exact Theme/Release/action-form exports and generated workflow build peer',
);
