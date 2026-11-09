import { createInertiaApp } from '@inertiajs/react';
import { beamInertiaOptions } from '@splicewire/beam-inertia';
import type { PageModule } from '@splicewire/beam-inertia';
import { ApiReference, configureDocs } from '@splicewire/beam-docs';
import { Callout, Card, CardGrid, DoctorOutput, FileTree, Figure, SectionLanding, Step, Steps, Terminal } from '@splicewire/beam-mdx/kit';
import { beamDocsPages } from '@splicewire/beam-docs/pages';
import '@splicewire/beam-ux/tokens-docs.css';
// The 14-segment LCD face behind Calcucrypt's readout and wordmark.
import 'dseg/css/dseg.css';
import { authFeatures } from './beam';
import SiteLayout from './layouts/site-layout';

const pages = import.meta.glob<PageModule>([
    './pages/**/*.tsx',
    '!./pages/_prototype.tsx',
]);

if (import.meta.env.DEV) {
    pages['_prototype'] = () =>
        import('./pages/_prototype') as Promise<PageModule>;
}

const options = beamInertiaOptions({
    development: import.meta.env.DEV,
    features: authFeatures,
    fileEditorHref: () => '/account/theme',
    // The packaged page form, where the server mounts its public intake door (BEAM_INTAKE_ENABLED).
    intakeForms: import.meta.env.VITE_BEAM_INTAKE_ENABLED === 'true',
    // Where MDX pages hold blocks (BEAM_UX_MDX_HOLDS_BLOCKS), what their profile offers beyond the packaged islands:
    // beam-inertia registers SiteNav and ManifestTable, and this starter the beam-mdx kit and the docs pages'
    // ApiReference. config/beam/mdx.php leaves out the two this starter cannot render (artifacts, references).
    mdxHoldsBlocks: import.meta.env.VITE_BEAM_UX_MDX_HOLDS_BLOCKS === 'true',
    components: import.meta.env.VITE_BEAM_UX_MDX_HOLDS_BLOCKS === 'true'
        ? { Callout, Steps, Step, Figure, Terminal, SectionLanding, CardGrid, Card, DoctorOutput, FileTree, ApiReference }
        : {},
    pages: { ...beamDocsPages, ...pages },
});
configureDocs();

createInertiaApp({
    ...options,
    layout: (name) => {
        // Calcucrypt's public pages wear its own site chrome (layouts/site-layout.tsx).
        if (name.startsWith('calculators/') || name.startsWith('guides/')) {
            return SiteLayout;
        }

        return options.layout(name);
    },
});
