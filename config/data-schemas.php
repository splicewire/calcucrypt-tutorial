<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Versioned Schema Base URI
    |--------------------------------------------------------------------------
    |
    | The authority of every versioned `$id` this host mints, and the origin that
    | SERVES those schemas — the same string on purpose, so an `$id` says who
    | answers for it.
    |
    | The package ships no default: a fleet-wide default can only be one vendor's
    | domain stamped onto every other vendor's schemas, which is how the dead
    | `https://schemas.splicewire.app` reached hosts across three vendors. Leaving
    | this unset THROWS (`MissingSchemaBaseUri`) the moment any class implementing
    | `Schemastud\DataSchemas\Contracts\SchemaIdentity` is generated — an `$id` is
    | write-once, so a guess is unrecoverable.
    |
    | THIS IS A STARTER, so the value here is inherited by every host cloned from
    | it — which rules out a real authority (one vendor's domain baked into
    | everyone's clone). So it ships with NO FALLBACK: set `SCHEMA_BASE_URI` in
    | this host's `.env` (there is a commented line in `.env.example`), and until
    | you do, the first versioned schema you generate fails loudly and tells you.
    |
    | It previously shipped `/schemas` — origin-less, on the theory that a
    | relative `$id` resolves against whatever origin serves the document.
    | Beam-facade ticket 112 measured that wrong and reverted it. Three reasons,
    | in order of weight:
    |
    |   1. An `$id` is not a `$ref`. It is the document's IDENTITY and, per
    |      ticket 64, the address it is served at. `/schemas/content/article/1`
    |      names no origin, so it identifies nothing globally — and it is
    |      write-once, so it is baked into every artifact a fresh site freezes
    |      before anyone notices.
    |   2. The public schema door refuses to mount for it, because the served
    |      registry is keyed by the absolute request URL — so `schemas/{path}`
    |      would be a door that could only ever 404.
    |   3. It is a FOURTH state the tri-state never declared (`null` / `false` /
    |      a URI). It cleared the `is_string` guard and minted silently; as of
    |      112 it throws `NonAbsoluteSchemaBaseUri` instead.
    |
    | Unset was rejected in favour of `/schemas` on the grounds that it left a
    | fresh clone "red out of the box". Measured 2026-08-26: this starter ships
    | ZERO `SchemaIdentity` classes and an EMPTY `resources/schemas/registry`, so
    | a clone boots, migrates and serves fine — it goes red at exactly the moment
    | someone declares versioned identity, which is exactly the moment the
    | authority has to be decided. That objection was true of nothing.
    |
    | `false` was rejected too: it opts the host out of versioned identity
    | entirely and SILENTLY, so a clone that later adds a `SchemaIdentity` class
    | freezes short-name `$id`s — also write-once — with no signal at all. It
    | remains the right value for a host that has genuinely decided it will never
    | serve schemas (`calcucrypt`, `numero`, `thingsontv` all use it).
    |
    | Only this key is set; every other `data-schemas` setting falls through to
    | the package default via `mergeConfigFrom`.
    |
    */

    'base_uri' => env('SCHEMA_BASE_URI'),

];
