import { createServer } from 'node:http';
import { appendFileSync, lstatSync, readFileSync, realpathSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';

// This is a deterministic GET-only local teaching service, never a production provider adapter.
const file = resolve(process.argv[2] || '');
if (
    process.argv.length !== 3 ||
    !file.endsWith('/storage/app/private/external-requests.json') ||
    realpathSync(dirname(file)) !== dirname(file) ||
    lstatSync(file).isSymbolicLink() ||
    lstatSync(file).mode & 0o077
) {
    throw Error('Name the fresh host private external-requests.json fixture');
}
const config = JSON.parse(readFileSync(file, 'utf8'));
if (
    !/^http:\/\/127\.0\.0\.1:\d{2,5}$/.test(config.baseUrl) ||
    !/^[a-f0-9]{48}$/.test(config.token)
)
    throw Error('Invalid local fixture');
const url = new URL(config.baseUrl);
const modeFile = join(dirname(file), 'external-provider-mode.json');
const receipts = join(dirname(file), 'external-provider-receipts.jsonl');
for (const path of [modeFile, receipts]) {
    if (lstatSync(path).isSymbolicLink() || lstatSync(path).mode & 0o077)
        throw Error('Use private owned fixture controls');
}
const server = createServer(async (req, res) => {
    let status = 500;
    let source = '';
    try {
        const parsed = new URL(req.url, config.baseUrl);
        source = parsed.searchParams.get('source') || '';
        const reply = (code, body) => {
            status = code;
            res.writeHead(code, { 'content-type': 'application/json' });
            res.end(JSON.stringify(body));
        };
        if (req.method !== 'GET') {
            reply(405, { message: 'Read only' });
            return;
        }
        if (req.headers['x-fixture-token'] !== config.token) {
            reply(401, { message: 'Unauthorized' });
            return;
        }
        if (parsed.pathname !== '/requests' || !['a', 'b'].includes(source)) {
            reply(404, { message: 'Missing' });
            return;
        }
        const { mode } = JSON.parse(readFileSync(modeFile, 'utf8'));
        if (
            ![
                'ready',
                'empty',
                'unavailable',
                'malformed',
                'timeout',
                'oversized',
            ].includes(mode)
        ) {
            reply(500, { message: 'Invalid private fixture mode' });
            return;
        }
        if (mode === 'timeout')
            await new Promise((done) => setTimeout(done, 1800));
        if (mode === 'unavailable') {
            reply(503, { privateDiagnostic: 'not-for-browser' });
            return;
        }
        if (mode === 'malformed') {
            reply(200, { data: [{ externalId: 'R1' }] });
            return;
        }
        const ids =
            mode === 'empty'
                ? []
                : mode === 'oversized'
                  ? Array.from({ length: 101 }, (_, i) => i + 1)
                  : source === 'a'
                    ? [1, 2, 3, 4, 5]
                    : [1, 2];
        reply(200, {
            data: ids.map((i) => ({
                externalId: 'R' + i,
                title: `${source === 'a' ? 'A' : 'B'} external request ${i}`,
                status: i % 2 === 0 ? 'closed' : 'open',
                updatedAt: `2026-10-21T00:${String(Math.floor(i / 60)).padStart(2, '0')}:${String(i % 60).padStart(2, '0')}Z`,
                privateDiagnostic: 'not-for-browser',
            })),
        });
    } catch {
        if (!res.headersSent) {
            status = 500;
            res.writeHead(500);
            res.end();
        }
    } finally {
        appendFileSync(
            receipts,
            JSON.stringify({ method: req.method, source, status }) + '\n',
        );
    }
});
server.listen(
    { host: '127.0.0.1', port: Number(url.port), exclusive: true },
    () => console.log(`Local read-only fixture on ${url.origin}`),
);
server.on('error', (error) => {
    console.error(error.code);
    process.exitCode = 1;
});
