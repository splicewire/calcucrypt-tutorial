/**
 * Development-only Inertia host page for the package's prototype gallery and routes.
 * The PHP twin registers the local-only server route; app.tsx excludes this page from production.
 */
import { createPrototypeRoutes } from '@splicewire/beam-ux-prototype';
import { useSyncExternalStore } from 'react';
import { createBrowserRouter, RouterProvider } from 'react-router';

// The package already emits absolute /_prototype paths; no additional basename is needed.
const router =
    import.meta.env.DEV && typeof document !== 'undefined'
        ? createBrowserRouter(
              createPrototypeRoutes(
                  import.meta.glob<Record<string, unknown>>(
                      '../_prototype/**/*.tsx',
                  ),
              ),
          )
        : null;

const subscribe = () => () => undefined;

export default function PrototypeHost() {
    // Match the empty server render during hydration, then mount the browser-only gallery.
    const isClient = useSyncExternalStore(
        subscribe,
        () => true,
        () => false,
    );

    if (!isClient || !router) {
        return null;
    }

    return <RouterProvider router={router} />;
}

// The gallery is self-chromed. An empty layout takes precedence over the host's default layout
// callback, so the host's layout code never has to name this dev-only page (a name that would ship in
// every production bundle and fail beam-verify-prototype-boundary).
PrototypeHost.layout = [] as const;
