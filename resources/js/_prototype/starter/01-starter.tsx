/**
 * PROTOTYPE — throwaway. Do not ship.
 *
 * Starter prototype stamped by `splicewire:beam:ux:prototype:install` (laravel-beam-ux-prototype). It proves
 * the mechanism end-to-end: dropping this file auto-mounts it at `/_prototype/starter` via the host's
 * one-line `createPrototypeRoutes(import.meta.glob(...))` router glob — no router edit.
 *
 * Design question this answers: "does the prototyping mechanism mount in THIS host?" Replace it with a
 * real design prototype: import shipped `@/` components read-only over a colocated `_fixtures/` file,
 * on-brand by construction. Delete this starter once you have your own.
 */
import { Link } from 'react-router';
import { Button } from '@splicewire/beam-inertia';
import { starter } from './_fixtures/starter';

export function StarterPrototype() {
    return (
        <div className="min-h-screen dotted-bg p-10">
            <div className="mx-auto max-w-xl rounded-xl border bg-background p-8 shadow-sm">
                <h1 className="text-2xl font-semibold text-sidebar-active-foreground">
                    {starter.title}
                </h1>
                <p className="mt-3 text-muted-foreground">
                    This starter is auto-mounted at{' '}
                    <code>/_prototype/starter</code> by
                    <code> @splicewire/beam-ux-prototype</code>. Replace it with
                    a real-React prototype that imports shipped components over
                    colocated fixtures.
                </p>
                <Button asChild className="mt-6">
                    <Link to="/_prototype">{starter.backLabel}</Link>
                </Button>
            </div>
        </div>
    );
}
