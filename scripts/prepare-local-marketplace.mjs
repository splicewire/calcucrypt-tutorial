import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
const source = new URL(
    '../examples/local-marketplace/page.tsx',
    import.meta.url,
);
const target = new URL(
    '../resources/js/pages/examples/local-marketplace.tsx',
    import.meta.url,
);
await mkdir(new URL('.', target), { recursive: true });
await writeFile(target, await readFile(source));
console.log(`Prepared optional marketplace page: ${fileURLToPath(target)}`);
