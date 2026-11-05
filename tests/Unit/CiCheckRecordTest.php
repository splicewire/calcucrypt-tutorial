<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * `bin/ci-check` writes the record `beam.install.desired-state` R3 reads, and R3 is the only consumer
 * of it — so the two halves of this contract live in different repositories and nothing but this file
 * holds them together.
 *
 * ## Why the runner is invoked for real, from a scratch tree
 *
 * The record write is three lines of logic wrapped around a `proc_open` loop that runs the entire
 * suite; there is no seam to unit-test and no test harness for this file at all. Requiring it would
 * execute it, and executing it here would recurse into this very suite.
 *
 * So each case COPIES `bin/ci-check` into a throwaway directory and runs it there. That tree has no
 * `artisan`, so the PRELUDE fails on its first spawn — before any gate — and the runner reaches its
 * `exit 2` path in about a second, having exercised the real file: the real `ci_head_commit`, the
 * real directory creation, the real JSON, and the real three-state aggregate. Nothing is mocked and
 * nothing is re-implemented here, which is the point: a second implementation of the sha read is
 * exactly the defect the runner's own docblock warns about.
 *
 * ⚠️ The scratch directory is keyed by pid AND by `uniqid()`. A fixed scratch path shared between
 * concurrent sessions is a measured failure mode in this estate, and this file writes into one.
 */
class CiCheckRecordTest extends TestCase
{
    private string $tree;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tree = sys_get_temp_dir().'/ci-check-record-'.getmypid().'-'.uniqid();
        mkdir($this->tree.'/bin', 0o775, true);
        copy($this->runnerPath(), $this->tree.'/bin/ci-check');
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->tree);

        parent::tearDown();
    }

    private function runnerPath(): string
    {
        return dirname(__DIR__, 2).'/bin/ci-check';
    }

    /**
     * Run the copied runner in the scratch tree.
     *
     * @param  array<string, string>  $env
     * @return array{code: int, output: string}
     */
    private function runRunner(array $env = []): array
    {
        $spec = [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, 'bin/ci-check'],
            $spec,
            $pipes,
            $this->tree,
            array_merge(getenv(), ['XDEBUG_MODE' => 'off'], $env),
        );

        $this->assertIsResource($process, 'The runner could not be spawned, so nothing below was measured.');

        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['code' => proc_close($process), 'output' => $output];
    }

    /** @return array<string, mixed> */
    private function readRecord(): array
    {
        $path = $this->tree.'/storage/app/beam/ci-check.json';

        $this->assertFileExists($path, 'No record was written, so R3 would keep reading "no record" forever.');

        $decoded = json_decode((string) file_get_contents($path), true);

        $this->assertIsArray($decoded, 'A malformed record is not a pass — R3 warns on it, and it must not be what a clean run writes.');

        return $decoded;
    }

    private function rmrf(string $path): void
    {
        if (! is_dir($path)) {
            @unlink($path);

            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->rmrf($path.'/'.$entry);
            }
        }

        @rmdir($path);
    }

    /* ------------------------------------------------------- the write itself */

    public function test_a_run_writes_the_record_r3_reads(): void
    {
        $run = $this->runRunner();

        $this->assertSame(2, $run['code'], 'The scratch tree has no artisan, so the prelude cannot succeed.');

        $record = $this->readRecord();

        $this->assertSame(['exit', 'at', 'commit'], array_keys($record), 'R3 reads these three keys by name.');
        $this->assertStringContainsString('Recorded exit 2', $run['output']);
    }

    /**
     * The three-state contract, at the one place it could be lost. `2` is UNMEASURED and dominates 1;
     * R3 words it *"a gate could not be measured, which is not a verdict"*, and it can only say that
     * about a record that carries the 2. A runner collapsing 2 to 1 would hand the audit a verdict
     * nobody reached.
     */
    public function test_exit_two_is_recorded_as_two_rather_than_collapsed_to_a_verdict(): void
    {
        $this->runRunner();

        $this->assertSame(2, $this->readRecord()['exit']);
        $this->assertIsInt($this->readRecord()['exit'], 'R3 requires a NUMERIC exit; a string would still pass is_numeric but the writer should not rely on that.');
    }

    /**
     * Every aggregate exit is recorded as itself. The other two states need a passing and a failing
     * suite, which this scratch tree cannot produce in a second — so they are covered structurally:
     * all three literals reach `ci_finish`, and no bare `exit(<state>)` survives anywhere that would
     * return without writing.
     */
    public function test_all_three_exit_states_are_routed_through_the_writer(): void
    {
        $source = (string) file_get_contents($this->runnerPath());

        foreach ([0, 1, 2] as $state) {
            $this->assertStringContainsString("ci_finish({$state}, \$root)", $source);
            $this->assertStringNotContainsString("exit({$state});", $source, "A bare exit({$state}) would return without recording.");
        }
    }

    public function test_the_timestamp_is_a_real_iso8601_instant(): void
    {
        $this->runRunner();

        $at = $this->readRecord()['at'];

        $this->assertIsString($at);
        $this->assertInstanceOf(\DateTimeImmutable::class, \DateTimeImmutable::createFromFormat(DATE_ATOM, $at));
    }

    /* -------------------------------------------------------------- the commit */

    public function test_a_tree_that_is_not_a_checkout_records_null_rather_than_guessing(): void
    {
        $this->runRunner();

        $this->assertNull($this->readRecord()['commit'], 'A guessed sha would make a foreign-commit record read as a fresh one.');
    }

    /**
     * The sha must be read the way the AUDIT reads it, or R3's staleness branch fires on an instrument
     * mismatch rather than on drift. Both walk `.git/HEAD` → the named ref.
     */
    public function test_the_recorded_commit_is_head_read_the_way_the_audit_reads_it(): void
    {
        $sha = str_repeat('c', 40);
        mkdir($this->tree.'/.git/refs/heads', 0o775, true);
        file_put_contents($this->tree.'/.git/HEAD', "ref: refs/heads/main\n");
        file_put_contents($this->tree.'/.git/refs/heads/main', $sha."\n");

        $this->runRunner();

        $this->assertSame($sha, $this->readRecord()['commit']);
    }

    public function test_a_detached_head_records_the_sha_it_holds(): void
    {
        $sha = str_repeat('d', 40);
        mkdir($this->tree.'/.git', 0o775, true);
        file_put_contents($this->tree.'/.git/HEAD', $sha."\n");

        $this->runRunner();

        $this->assertSame($sha, $this->readRecord()['commit']);
    }

    public function test_an_unresolvable_head_records_null_rather_than_the_ref_name(): void
    {
        mkdir($this->tree.'/.git', 0o775, true);
        file_put_contents($this->tree.'/.git/HEAD', "ref: refs/heads/never-written\n");

        $this->runRunner();

        $this->assertNull($this->readRecord()['commit']);
    }

    /* ------------------------------------------- the write may not fail the run */

    /**
     * A gate result is a fact about the repo; being able to create a directory is a fact about the
     * filesystem. If the second could overwrite the first, this runner would be reporting its own
     * storage permissions as a CI verdict.
     */
    public function test_an_unwritable_record_path_says_so_and_leaves_the_exit_code_alone(): void
    {
        // A file, not a directory — so `mkdir -p` of its "parent" cannot succeed.
        file_put_contents($this->tree.'/not-a-dir', 'x');

        $run = $this->runRunner(['CI_CHECK_RECORD_PATH' => $this->tree.'/not-a-dir/beam/ci-check.json']);

        $this->assertSame(2, $run['code'], 'The aggregate exit must survive a failed record write.');
        $this->assertStringContainsString('the ci:check record was NOT written', $run['output']);
        $this->assertFileDoesNotExist($this->tree.'/storage/app/beam/ci-check.json');
    }

    public function test_the_record_path_is_overridable_for_a_host_that_repoints_the_config_key(): void
    {
        $path = $this->tree.'/elsewhere/record.json';

        $run = $this->runRunner(['CI_CHECK_RECORD_PATH' => $path]);

        $this->assertSame(2, $run['code']);
        $this->assertFileExists($path);
        $this->assertSame(2, json_decode((string) file_get_contents($path), true)['exit']);
    }
}
