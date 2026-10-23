import importlib.util
import json
import os
from pathlib import Path
import tempfile
import subprocess
import sys
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('fixture', Path(__file__).with_name('fixture.py'))
f = importlib.util.module_from_spec(spec)
spec.loader.exec_module(f)


class FixtureSafeguards(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.root = Path(self.tmp.name).resolve()
        self.fixture = self.root / 'metadata'
        self.fixture.mkdir()
        self.env = patch.dict(os.environ, {'OPENRIG_SESSION_NAME': 'fixture-test@beam-dx'})
        self.env.start()
        self.addCleanup(self.env.stop)
        for p in [self.root, self.fixture]:
            (p / '.openrig-scratch-owner.json').write_text(json.dumps(dict(
                schema='openrig.scratch-owner/v2', path=str(p), dev=p.stat().st_dev,
                ino=p.stat().st_ino, owner='fixture-test@beam-dx', id=p.name)))

    def prepared(self):
        f.prepare(self.root, self.fixture)
        f.guard(self.root, self.fixture)

    def test_unmarked_owner_like_starter_refuses_before_native_apply(self):
        (self.fixture / 'composer.json').write_text('{"name":"owner/site"}')
        original = f.snapshot(self.fixture)
        with patch.object(f, 'native') as native:
            with self.assertRaises(ValueError):
                f.apply(self.root, self.fixture, self.root / 'host', 'php')
            native.assert_not_called()
        self.assertEqual(original, f.snapshot(self.fixture))
        with self.assertRaises(ValueError):
            f.prepare(self.root, self.fixture)

    def test_changed_lock_refuses_before_native_apply(self):
        self.prepared()
        (self.fixture / 'composer.lock').write_text('{"owner":"modified"}')
        with patch.object(f, 'native') as native:
            with self.assertRaisesRegex(ValueError, 'lock changed'):
                f.apply(self.root, self.fixture, self.root / 'host', 'php')
            native.assert_not_called()

    def test_overlay_drift_refuses(self):
        self.prepared()
        with (self.fixture / 'composer.local.json').open('a') as out:
            out.write(' ')
        with self.assertRaisesRegex(ValueError, 'bytes/mode'):
            f.guard(self.root, self.fixture)

    def test_reappeared_checkout_refuses(self):
        self.prepared()
        (self.fixture / f.URL).mkdir()
        with self.assertRaisesRegex(ValueError, 'reappeared'):
            f.guard(self.root, self.fixture)

    def test_symlink_lock_refuses_without_reading_target(self):
        self.prepared()
        outside = self.root / 'outside.txt'
        outside.write_text('not JSON')
        (self.fixture / 'composer.lock').unlink()
        (self.fixture / 'composer.lock').symlink_to(outside)
        with self.assertRaisesRegex(ValueError, 'Symlink'):
            f.guard(self.root, self.fixture)
        self.assertEqual('not JSON', outside.read_text())

    def test_symlinked_kept_directory_refuses_before_manifest_read(self):
        self.prepared()
        kept = self.fixture / 'packages/kept'
        original = kept / 'composer.json'
        original.unlink()
        kept.rmdir()
        outside = self.root / 'outside-package'
        outside.mkdir()
        (outside / 'composer.json').write_text('{"name":"fixture/kept"}')
        kept.symlink_to(outside, target_is_directory=True)
        with patch.object(f, 'read_json', wraps=f.read_json) as read:
            with self.assertRaisesRegex(ValueError, 'Symlink'):
                f.validate(self.fixture)
            read.assert_not_called()

    def test_native_actions_require_explicit_php_before_path_access(self):
        for action in ['diagnose', 'preview', 'apply']:
            result = subprocess.run([sys.executable, str(Path(__file__).with_name('fixture.py')),
                                     action, '--root', '/missing-root', '--fixture', '/missing-fixture'],
                                    text=True, capture_output=True)
            self.assertEqual(2, result.returncode)
            self.assertIn('--php is required', result.stderr)
            self.assertNotIn('Expected real directory', result.stderr)

    def test_outside_fixture_refuses(self):
        with self.assertRaisesRegex(ValueError, 'direct child'):
            f.locations(self.fixture, self.root)

    def test_invalid_json_refuses(self):
        self.prepared()
        (self.fixture / 'composer.local.json').write_text('{')
        with self.assertRaises(ValueError):
            f.guard(self.root, self.fixture)

    def test_marker_root_identity_refuses(self):
        self.prepared()
        p = self.fixture / f.MARKER
        data = json.loads(p.read_text())
        data['rootIdentity'][1] += 1
        p.write_text(json.dumps(data))
        with self.assertRaisesRegex(ValueError, 'identity mismatch'):
            f.guard(self.root, self.fixture)


if __name__ == '__main__':
    unittest.main()
