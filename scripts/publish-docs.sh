#!/usr/bin/env bash
# Run from release automation on the prepared host, sharing its publication ledger and config.
set -euo pipefail
if [[ $# -ne 1 || -z "$1" ]]; then
    echo "Usage: scripts/publish-docs.sh RELEASE_VERSION" >&2
    exit 2
fi
cd "$(dirname "$0")/.."
"${BEAM_DOCS_PHP:-php}" artisan splicewire:beam:docs:generate --no-interaction
"${BEAM_DOCS_PHP:-php}" artisan splicewire:beam:docs:publish "$1" --json --no-interaction
