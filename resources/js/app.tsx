import { createInertiaApp } from '@inertiajs/react';
import { beamInertiaOptions } from '@splicewire/beam-inertia';
import type { PageModule } from '@splicewire/beam-inertia';
import { configureDocs } from '@splicewire/beam-docs';
import { Callout, Card, CardGrid, DoctorOutput, FileTree, Figure, SectionLanding, Step, Steps, Terminal } from '@splicewire/beam-mdx/kit';
import { beamDocsPages } from '@splicewire/beam-docs/pages';
import '@splicewire/beam-ux/tokens-docs.css';
import { authFeatures } from './beam';
import { ServiceDeskTheme } from './service-desk-theme';

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
    // Where MDX pages hold blocks (BEAM_UX_MDX_HOLDS_BLOCKS), the beam-mdx kit their profile offers; config/beam/mdx.php
    // leaves out the two this starter cannot render (artifacts, references).
    components: import.meta.env.VITE_BEAM_UX_MDX_HOLDS_BLOCKS === 'true'
        ? { Callout, Steps, Step, Figure, Terminal, SectionLanding, CardGrid, Card, DoctorOutput, FileTree }
        : {},
    pages: { ...beamDocsPages, ...pages },
});
configureDocs();

createInertiaApp({
    ...options,
    layout: (name) => {
        const layouts = options.layout(name);

        if (name !== 'frame/console') {
            return layouts;
        }

        const contentLayouts = Array.isArray(layouts)
            ? layouts
            : layouts
              ? [layouts]
              : [];

        return [...contentLayouts, ServiceDeskTheme];
    },
});
