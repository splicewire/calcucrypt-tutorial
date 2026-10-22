import { readFile } from 'node:fs/promises';
import { build } from 'esbuild';
const file = process.env.EXTERNAL_REQUEST_ACTORS;
const base = process.env.EXTERNAL_REQUEST_BASE_URL;
if (!file || !base)
    throw Error(
        'Set EXTERNAL_REQUEST_ACTORS and EXTERNAL_REQUEST_BASE_URL for the private fixture',
    );
const fixture = JSON.parse(await readFile(file, 'utf8'));
const result = await build({
    entryPoints: ['examples/external-request-client.ts'],
    bundle: true,
    platform: 'node',
    format: 'esm',
    write: false,
});
const module = await import(
    'data:text/javascript;base64,' +
        Buffer.from(result.outputFiles[0].text).toString('base64')
);
console.log(
    JSON.stringify(
        await module.runExternalRequestExample(base, fixture.actors),
        null,
        2,
    ),
);
