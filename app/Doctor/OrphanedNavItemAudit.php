<?php

namespace App\Doctor;

use App\Models\SitemapRecord;
use App\Providers\AppServiceProvider;
use Illuminate\Routing\Router;
use Rushing\Doctor\DoctorAudit;
use Rushing\Doctor\Finding;
use Throwable;

/**
 * Advisory doctor check (a moat-free {@see Finding} from rushing/laravel-doctor): validate
 * every record-leaf (kind A) nav link against the app's REAL registered routes and report
 * drift. A record link can rot in a way a content-glob (kind C) leaf cannot — an owner can
 * point a NavItem at a path that no route serves (a rename, a removed page), producing an
 * "orphaned" nav item. This surfaces that drift.
 *
 * ── Semantics (advisory, NEVER a hard fail — mirrors the base BeamDoctor's presence-conditional
 *    checks) ──
 *   - PASS  when every in-host record link resolves to a registered route path (external links
 *           are skipped — off-host targets are not the app's to validate). Also PASSes on a pure
 *           all-C nav (no record leaf) — nothing to orphan.
 *   - WARN  when one or more in-host record links resolve to no registered route (the drift signal).
 *
 * ── Plugging into the base doctor ──
 *   Registered — ADVISORY — into beam-core's `BeamDoctorManifest` from this app's own
 *   {@see AppServiceProvider}, so one `splicewire:beam:doctor` run reports it
 *   alongside the rest of the family. A HOST registers through exactly the same seam a package
 *   does: the manifest is a container singleton bound in beam's register phase and read fresh at
 *   command invocation, and an app's boot runs after every package's register — so there is no
 *   boot-order problem and no package-only constraint. (An earlier starter-local `sitemap:doctor`
 *   command claimed the base doctor "offers no host extension seam"; that was written ten days
 *   before the manifest existed and has been deleted.)
 */
class OrphanedNavItemAudit implements DoctorAudit
{
    public function __construct(private Router $router) {}

    /**
     * @return list<Finding>
     */
    public function run(): array
    {
        return [$this->finding()];
    }

    private function finding(): Finding
    {
        $check = 'orphaned nav item';

        try {
            $records = SitemapRecord::query()->get();
        } catch (Throwable $e) {
            // No table / record leaf not opted in — the all-C default has nothing to orphan.
            return Finding::pass($check, 'no record-leaf nav to validate (all-content-derived nav).');
        }

        if ($records->isEmpty()) {
            return Finding::pass($check, 'no record-leaf nav entries — nothing to orphan.');
        }

        $knownPaths = $this->registeredPaths();
        $orphans = [];

        foreach ($records as $record) {
            $item = $record->toNavItem();

            // Off-host links are not the app's to validate.
            if ($item->external) {
                continue;
            }

            if (! in_array($this->normalize($item->href), $knownPaths, true)) {
                $orphans[] = $item->label.' → '.$item->href;
            }
        }

        if ($orphans === []) {
            return Finding::pass($check, 'all record nav links resolve to registered routes.');
        }

        return Finding::warn(
            $check,
            count($orphans).' record nav link(s) resolve to no registered route: '.implode('; ', $orphans).'.',
        );
    }

    /**
     * @return list<string>
     */
    private function registeredPaths(): array
    {
        return array_values(array_map(
            fn ($route): string => $this->normalize($route->uri()),
            $this->router->getRoutes()->getRoutes(),
        ));
    }

    private function normalize(string $path): string
    {
        $parsed = parse_url($path, PHP_URL_PATH);

        return '/'.trim(is_string($parsed) ? $parsed : $path, '/');
    }
}
