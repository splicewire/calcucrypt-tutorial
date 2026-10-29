"""Test support: run the example's prepare-composer.py on a fixture host, never on a real one.

usage: python3 -I prepare-local-marketplace-composer.py <fixture-root>

The fixture root holds a host's composer.json and composer.lock, a byte copy of examples/local-marketplace/, and one
source directory per selected checkout under sources/ (market, licenser, commerce, lunar), each with a composer.json naming
its package. The script's two input-identity guards (the accepted lock's hash, and each source checkout being clean at its
exact selected ref on a named branch) vouch for real inputs, which a fixture cannot have, so they are answered as
satisfied. Everything else is the script itself: what it writes, and what it leaves alone, is what the caller measures.
"""
import hashlib
import runpy
import subprocess
import sys
from pathlib import Path


class Satisfied(str):
    """Compares equal to the identity the script expects (the lock hash or a source ref)."""

    def __eq__(self, other):
        return True

    def __ne__(self, other):
        return False

    __hash__ = str.__hash__

    def strip(self, *args):
        return self


class Digest:
    def __init__(self, data=b''):
        self.data = data

    def hexdigest(self):
        return Satisfied('fixture-lock')


def check_output(command, cwd=None, text=False, **kwargs):
    if command[:3] == ['git', 'rev-parse', 'HEAD']:
        return Satisfied('fixture-ref')
    if command[:2] == ['git', 'status']:
        return ''
    if command[:3] == ['git', 'branch', '--show-current']:
        return 'fixture\n'
    raise AssertionError('unexpected command: %r' % (command,))


root = Path(sys.argv[1]).resolve(strict=True)
hashlib.sha256 = Digest
subprocess.check_output = check_output
script = root / 'examples' / 'local-marketplace' / 'prepare-composer.py'
sys.argv = [str(script)]
for key in ['market', 'licenser', 'commerce', 'lunar']:
    sys.argv += ['--' + key + '-source', str(root / 'sources' / key)]
runpy.run_path(str(script), run_name='__main__')
