import { readFile } from 'node:fs/promises';
import { build } from 'esbuild';
const file = process.env.APPROVAL_USAGE_ACTORS;
const base = process.env.APPROVAL_USAGE_BASE_URL;
const expected = process.env.APPROVAL_USAGE_EXPECTED;
if (!file || !base || expected === undefined)
    throw Error(
        'Set APPROVAL_USAGE_ACTORS, APPROVAL_USAGE_BASE_URL and APPROVAL_USAGE_EXPECTED for the private fixture',
    );
const fixture = JSON.parse(await readFile(file, 'utf8'));
const result = await build({
    entryPoints: ['examples/approval-usage-client.ts'],
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
        await module.runApprovalUsageExample(
            base,
            fixture.actors,
            Number(expected),
        ),
        null,
        2,
    ),
);
