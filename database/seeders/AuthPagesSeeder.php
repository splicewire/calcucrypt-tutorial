<?php

namespace Database\Seeders;

use App\Beam\RealmRegistry;
use Illuminate\Database\Seeder;
use Splicewire\Beam\Write\AsSystemWriter;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Splicewire\Beam\Ux\Publish\EntryPublication;
use Splicewire\Beam\Ux\Provenance\Reasserter;
use Splicewire\Beam\Ux\Type\UxType;

/**
 * Seeds the 7 auth-page `beam_ux_entries` rows (theme-entries-and-authoring STR-03) —
 * `FortifyServiceProvider` resolves a body for each of these via `Splicewire\Beam\Ux\Support\EntryBody`. Each entry's
 * body is an ordinary composable JsonDoc: a heading + description (today's static copy, as PLAIN
 * editable blocks — an author can retext/delete/reorder them) followed by the sealed island node
 * referencing the real Fortify-bound form (`resources/js/editor/registry.tsx`) — never decomposed.
 * The heading/description carry the SAME `className`s `layouts/auth/auth-simple-layout.tsx`'s own
 * `<h1>`/`<p>` used to render them with, so promoting them into the tree is visually a no-op; the
 * layout's own title/description slot is left blank now that the tree owns this text (`auth/entry.tsx`
 * no longer pushes a hardcoded chrome string via `setLayoutProps` for these 6 pages).
 *
 * `two-factor-challenge` is the one exception: its title/description are session-state-driven (the
 * authentication-code ⇄ recovery-code toggle), not editorial copy, so its tree is the sealed island
 * alone — its own component keeps calling `setLayoutProps` itself, unchanged.
 *
 * There is no package-side disk-seeding mechanism for this (mirrors `ThemeSeeder`'s own rationale):
 * `RegisterEntriesFromDisk` only stores raw `.tsx` source post-Puck-retirement, no structural-tree
 * seeding capability. This host-local seeder is the in-scope fix.
 *
 * Idempotent: re-running refreshes each SAME particle's payload (never mints a second orphaned one).
 */
class AuthPagesSeeder extends Seeder
{
    use AsSystemWriter;

    /** slug => [entry title (admin label), island component name, heading, description]. */
    private const PAGES = [
        'login' => [
            'Log in', 'AuthLogin',
            'Log in to your account', 'Enter your email and password below to log in',
        ],
        'register' => [
            'Register', 'AuthRegister',
            'Create an account', 'Enter your details below to create your account',
        ],
        'forgot-password' => [
            'Forgot password', 'AuthForgotPassword',
            'Forgot password', 'Enter your email to receive a password reset link',
        ],
        'reset-password' => [
            'Reset password', 'AuthResetPassword',
            'Reset password', 'Please enter your new password below',
        ],
        'confirm-password' => [
            'Confirm password', 'AuthConfirmPassword',
            'Confirm password', 'This is a secure area of the application. Please confirm your password before continuing.',
        ],
        'two-factor-challenge' => ['Two-factor challenge', 'AuthTwoFactorChallenge', null, null],
        'verify-email' => [
            'Verify email', 'AuthVerifyEmail',
            'Email verification', 'Please verify your email address by clicking on the link we just emailed to you.',
        ],
    ];

    public function run(): void
    {
        $this->asSystemWriter(fn () => $this->seedShipped());
    }

    private function seedShipped(): void
    {
        app(EntryPublication::class)->requireVersionStore();

        // The `auth` realm's canonical root — nothing else in this host provisions it (it has no
        // nav.yml entry, unlike site/account/operator): without this, `author-ux-auth` (and the
        // realm-grant cascade generally) can never resolve TRUE for anyone, no matter how broad their
        // grant, since `DefaultEntitlementResolver` only composes a realm key a grantee holds `manage`
        // on a REAL root row for.
        BeamUxEntry::rootFor(RealmRegistry::REALM_AUTH);

        foreach (self::PAGES as $slug => [$title, $island, $heading, $description]) {
            $this->seedOne($slug, $title, $island, $heading, $description);
        }
    }

    private function seedOne(string $slug, string $title, string $island, ?string $heading, ?string $description): void
    {
        $namespace = config('beam.ux.namespace', 'starter');
        $body = $this->body($island, $heading, $description);

        app(Reasserter::class)->seedBody(
            ['namespace' => $namespace, 'slug' => $slug],
            [
                'type' => UxType::Page,
                'format' => 'tsx',
                'title' => $title,
                'realm' => RealmRegistry::REALM_AUTH,
                'realms' => [RealmRegistry::REALM_AUTH],
            ],
            $body,
            'package:splicewire/laravel-beam-starter/auth-pages',
        );
    }

    /**
     * The composable JsonDoc: `[heading?, description?, island]` — the sealed form island always last,
     * always present; the heading/description are ordinary editable text blocks, omitted for
     * `two-factor-challenge` (its title/description are session-state-driven, not editorial copy).
     *
     * @return array<int, mixed>
     */
    private function body(string $island, ?string $heading, ?string $description): array
    {
        $nodes = [];

        if ($heading !== null) {
            $nodes[] = [
                'kind' => 'block', 'name' => 'h1', 'isComponent' => false, 'dynamic' => false,
                // `text-center` compensates for the lost `layouts/auth/auth-simple-layout.tsx` parent
                // wrapper (`<div className="space-y-2 text-center">`) that used to center this text —
                // the tree's h1 renders as a bare top-level node now, so it must carry the alignment
                // itself. `text-xl font-medium` matches the layout's own original `<h1>` classes.
                'props' => [['name' => 'className', 'kind' => 'string', 'value' => 'text-xl font-medium text-center']],
                'children' => [['kind' => 'text', 'value' => $heading]],
            ];
        }

        if ($description !== null) {
            $nodes[] = [
                'kind' => 'block', 'name' => 'p', 'isComponent' => false, 'dynamic' => false,
                'props' => [['name' => 'className', 'kind' => 'string', 'value' => 'text-center text-sm text-muted-foreground']],
                'children' => [['kind' => 'text', 'value' => $description]],
            ];
        }

        $nodes[] = ['kind' => 'block', 'name' => $island, 'isComponent' => true, 'props' => [], 'children' => [], 'dynamic' => false];

        return $nodes;
    }
}
