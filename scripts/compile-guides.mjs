// Precompiles authored MDX Guides into a { slug, frontmatter, html } artifact that the PHP
// `content:sync` command projects into publishing Posts. MDX compilation stays entirely
// frontend-side (the pure-PHP publishing package must never compile MDX); this is the node
// half of the "MDX source → precompiled → projected into Posts" pipeline.
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import * as runtime from 'react/jsx-runtime';
import { evaluate } from '@mdx-js/mdx';
import remarkFrontmatter from 'remark-frontmatter';
import remarkMdxFrontmatter from 'remark-mdx-frontmatter';
import { readdirSync, readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { basename, join } from 'node:path';

const SRC_DIR = 'resources/content/guides';
const OUT_DIR = 'resources/content/compiled';
const OUT_FILE = join(OUT_DIR, 'guides.json');

const files = readdirSync(SRC_DIR)
    .filter((name) => name.endsWith('.mdx'))
    .sort();

const guides = [];
for (const file of files) {
    const source = readFileSync(join(SRC_DIR, file), 'utf8');
    const { default: Content, frontmatter = {} } = await evaluate(source, {
        ...runtime,
        remarkPlugins: [remarkFrontmatter, remarkMdxFrontmatter],
    });

    const html = renderToStaticMarkup(createElement(Content));
    const slug = typeof frontmatter.slug === 'string' ? frontmatter.slug : basename(file, '.mdx');

    guides.push({ slug, frontmatter, html });
}

mkdirSync(OUT_DIR, { recursive: true });
writeFileSync(OUT_FILE, `${JSON.stringify(guides, null, 2)}\n`);
console.log(`compile-guides — ${guides.length} guide(s) → ${OUT_FILE}`);
