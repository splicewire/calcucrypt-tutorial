<?php

namespace App\Beam;

use Splicewire\Beam\Ux\Models\BeamUxEntry;

/**
 * The starter's realm registry (mirrors audiostud's beam Model-B ticket 14). A realm is a URL/nav/
 * permission grouping of beam-ux entries (`site`, `account`, `operator`, `auth`, …).
 *
 * `authorAbility($realm)` — the per-realm authoring ability name (`ux.{realm}.author`) gating who may
 * edit that realm's entries.
 *
 * **`composable` is retired (theme-entries-and-authoring ticket 04)** — fully subsumed by ADR-0016's
 * per-node opacity overlay. This registry no longer distinguishes CONTENT from BEHAVIOR realms;
 * `auth`/`operator` are ordinary named realms like any other.
 */
class RealmRegistry
{
    /** The public content realm the package roots URLs under by default. */
    public const REALM_SITE = BeamUxEntry::REALM_SITE;

    /** The authed content realm (Dashboard / Profile). */
    public const REALM_ACCOUNT = 'account';

    /** The auth realm (login / register). */
    public const REALM_AUTH = 'auth';

    /** The staff back-office realm. */
    public const REALM_OPERATOR = 'operator';

    /** The per-realm authoring ability name — `ux.{realm}.author`. */
    public static function authorAbility(string $realm): string
    {
        return "ux.{$realm}.author";
    }

    /**
     * Every realm this host knows about.
     *
     * @return list<string>
     */
    public static function realms(): array
    {
        return [
            self::REALM_SITE,
            self::REALM_ACCOUNT,
            self::REALM_AUTH,
            self::REALM_OPERATOR,
        ];
    }
}
