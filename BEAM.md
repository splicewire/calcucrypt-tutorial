---
satellite: calcucrypt-tutorial
variant: inertia-react
adopts: [account]
deviations: []
deltas: []
---

# calcucrypt-tutorial — Beam Manifest

This manifest identifies `splicewire/calcucrypt-tutorial`, an application created from
`splicewire/laravel-beam-starter`. It keeps the starter's adopted surfaces unchanged.

The manifest follows the beam runbook's `references/conformance.md`: it records intentional
departures and detected base-flow gaps. The empty lists above do not attest to a completed
behavior sweep or a clean aggregate doctor report.

## Account schema ownership

The starter owns its committed auth migrations. The package's full auth migration estate is
deliberately excluded by `config/beam/accounts.php`; account functionality and the package's
teams estate remain adopted. That publishing choice does not declare an account flow omitted.
