---
satellite: laravel-beam-starter
variant: inertia-react
adopts: [account]
deviations: []
deltas: []
---

# laravel-beam-starter — Beam Manifest

This manifest identifies `splicewire/laravel-beam-starter`, the beam-tier application template.
An application created from it must set `satellite` to its own identity and review the adopted
surfaces and deviations for its deployment.

The manifest follows the beam runbook's `references/conformance.md`: it records intentional
departures and detected base-flow gaps. The empty lists above do not attest to a completed
behavior sweep or a clean aggregate doctor report.

## Account schema ownership

The starter owns its committed auth migrations. The package's full auth migration estate is
deliberately excluded by `config/beam/accounts.php`; account functionality and the package's
teams estate remain adopted. That publishing choice does not declare an account flow omitted.
