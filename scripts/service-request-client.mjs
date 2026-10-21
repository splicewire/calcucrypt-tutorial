import process from 'node:process';
import { Buffer } from 'node:buffer';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { build } from 'esbuild';

const path = process.env.SERVICE_REQUEST_FIXTURE;

if (!path) {
    throw new Error(
        'Set SERVICE_REQUEST_FIXTURE to the private JSON written by scripts/service-request-fixture.php',
    );
}

const fixture = JSON.parse(await readFile(path, 'utf8'));
process.env.SERVICE_REQUEST_TOKEN = fixture.owner.token;
process.env.SERVICE_REQUEST_OTHER_TOKEN = fixture.other.token;
process.env.SERVICE_REQUEST_OWNER_ID = fixture.owner.id;

if (
    !fixture.outsider?.token ||
    !fixture.teamless?.token ||
    !fixture.outsider?.teamId
) {
    throw new Error(
        'Latest shared-team client requires a fresh --shared fixture; use checkpoint 35cbc40 for the private-row client',
    );
}

process.env.SERVICE_REQUEST_OUTSIDER_TOKEN = fixture.outsider.token;
process.env.SERVICE_REQUEST_TEAMLESS_TOKEN = fixture.teamless.token;
process.env.SERVICE_REQUEST_OUTSIDER_ID = fixture.outsider.id;
process.env.SERVICE_REQUEST_OUTSIDER_TEAM_ID = String(fixture.outsider.teamId);
const result = await build({
    entryPoints: [
        fileURLToPath(
            new URL('../examples/service-request-client.ts', import.meta.url),
        ),
    ],
    bundle: true,
    write: false,
    platform: 'node',
    format: 'esm',
    target: 'node22',
});
await import(
    `data:text/javascript;base64,${Buffer.from(result.outputFiles[0].text).toString('base64')}`
);
