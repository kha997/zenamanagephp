<?php

declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/scripts/ssot/owner_governance_lint.php';

/**
 * GAP-091: runtime coverage of scripts/ci/check-evidence-freshness.sh's live
 * check — the part that, while a Gate 3 packet is `awaiting_owner`, waits for
 * the PR's other checks. It must keep waiting while checks are only pending,
 * fail at once when one has failed or been cancelled, and fail when checks
 * are still pending after the last poll.
 *
 * Each test builds a throwaway git repository holding a copy of the script,
 * the lint library it requires, a symlink to this project's vendor/, and an
 * `awaiting_owner` Gate 3 packet whose recorded digest matches the tree, so
 * the structural checks pass and only the live check decides the outcome.
 * `gh` is a fake executable first on PATH that replays one canned
 * `gh pr checks` response per call.
 */
class EvidenceFreshnessLiveCheckTest extends TestCase
{
    private const WORK_ID = 'GAP-T91';

    /** @var string[] */
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            exec('rm -rf ' . escapeshellarg($dir));
        }
        $this->tempDirs = [];
        parent::tearDown();
    }

    public function test_waits_while_pending_then_passes_when_all_checks_pass(): void
    {
        $result = $this->runLiveCheck([
            $this->checks(['build' => 'pass', 'browser-tests' => 'pending']),
            $this->checks(['build' => 'pass', 'browser-tests' => 'pending']),
            $this->checks(['build' => 'pass', 'browser-tests' => 'pass', 'docs' => 'skipping']),
        ], 5);

        $this->assertSame(0, $result['exit'], $result['stdout'] . $result['stderr']);
        $this->assertSame(3, $result['calls'], 'It must keep polling while a check is pending, and stop once all pass.');
    }

    public function test_fails_on_the_first_poll_when_a_check_has_failed(): void
    {
        $result = $this->runLiveCheck([
            $this->checks(['build' => 'fail', 'browser-tests' => 'pending']),
            $this->checks(['build' => 'pass', 'browser-tests' => 'pass']),
        ], 5);

        $this->assertSame(1, $result['exit']);
        $this->assertSame(1, $result['calls'], 'A failed check must fail the step at once, without waiting for pending ones.');
        $this->assertStringContainsString('have failed or were cancelled', $result['stdout']);
        $this->assertStringContainsString('build (fail)', $result['stdout']);
    }

    public function test_a_cancelled_check_counts_as_failed(): void
    {
        $result = $this->runLiveCheck([
            $this->checks(['build' => 'cancel']),
        ], 5);

        $this->assertSame(1, $result['exit']);
        $this->assertSame(1, $result['calls']);
    }

    public function test_fails_after_the_last_poll_when_checks_are_still_pending(): void
    {
        $result = $this->runLiveCheck([
            $this->checks(['browser-tests' => 'pending']),
        ], 3);

        $this->assertSame(1, $result['exit']);
        $this->assertSame(3, $result['calls']);
        $this->assertStringContainsString('still pending after waiting', $result['stdout']);
        $this->assertStringContainsString('browser-tests (pending)', $result['stdout']);
    }

    public function test_its_own_check_is_ignored(): void
    {
        $result = $this->runLiveCheck([
            $this->checks(['build' => 'pass', 'Owner Governance Lint' => 'pending']),
        ], 2);

        $this->assertSame(0, $result['exit'], $result['stdout'] . $result['stderr']);
        $this->assertSame(1, $result['calls']);
    }

    /**
     * @param array<string, string> $buckets check name => bucket
     */
    private function checks(array $buckets): string
    {
        $checks = [];
        foreach ($buckets as $name => $bucket) {
            $checks[] = ['name' => $name, 'state' => strtoupper($bucket), 'bucket' => $bucket];
        }

        return json_encode($checks, JSON_THROW_ON_ERROR);
    }

    /**
     * @param string[] $responses one `gh pr checks` JSON response per call; the last repeats
     * @return array{stdout: string, stderr: string, exit: int, calls: int}
     */
    private function runLiveCheck(array $responses, int $pollAttempts): array
    {
        $repo = $this->makeRepo();
        $fakeGhDir = $this->makeFakeGh($responses);

        $env = [
            'PATH' => $fakeGhDir . ':' . getenv('PATH'),
            'HOME' => getenv('HOME') ?: sys_get_temp_dir(),
            'PR_NUMBER' => '1',
            'WORK_ID' => self::WORK_ID,
            'PR_HEAD_SHA' => trim((string) shell_exec('git -C ' . escapeshellarg($repo) . ' rev-parse HEAD')),
            'OWNER_GOV_LIVE_POLL_ATTEMPTS' => (string) $pollAttempts,
            'OWNER_GOV_LIVE_POLL_INTERVAL' => '0',
        ];

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(['bash', 'scripts/ci/check-evidence-freshness.sh'], $descriptors, $pipes, $repo, $env);
        $this->assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        $calls = (int) trim((string) @file_get_contents($fakeGhDir . '/calls'));

        return ['stdout' => (string) $stdout, 'stderr' => (string) $stderr, 'exit' => $exit, 'calls' => $calls];
    }

    private function makeRepo(): string
    {
        $root = dirname(__DIR__, 3);
        $repo = sys_get_temp_dir() . '/owner-gov-live-check-' . bin2hex(random_bytes(6));
        $this->tempDirs[] = $repo;

        mkdir($repo . '/scripts/ci', 0o777, true);
        mkdir($repo . '/scripts/ssot', 0o777, true);
        mkdir($repo . '/docs/owner-decisions/' . self::WORK_ID, 0o777, true);
        copy($root . '/scripts/ci/check-evidence-freshness.sh', $repo . '/scripts/ci/check-evidence-freshness.sh');
        copy($root . '/scripts/ssot/owner_governance_lint.php', $repo . '/scripts/ssot/owner_governance_lint.php');
        symlink($root . '/vendor', $repo . '/vendor');
        file_put_contents($repo . '/.gitignore', "/vendor\n");
        file_put_contents($repo . '/docs/owner-decisions/' . self::WORK_ID . '/01-request.md', "request\n");

        $git = 'git -C ' . escapeshellarg($repo);
        exec($git . ' init -q');
        exec($git . ' config user.email test@example.com');
        exec($git . ' config user.name Test');
        exec($git . ' add -A');
        exec($git . ' commit -q -m implementation');

        // The digest excludes the Gate 3 packet itself, so it can be computed
        // before the packet is written and still match afterwards.
        $digest = \owner_governance_compute_implementation_tree_digest('HEAD', self::WORK_ID, $repo);
        file_put_contents($repo . '/docs/owner-decisions/' . self::WORK_ID . '/03-release.md', <<<MD
            ---
            work_id: GAP-T91
            gate: 3
            gate_status: awaiting_owner
            owner_decision:
              value: none
            technical_evidence:
              implementation_tree_digest: "{$digest}"
            ---

            Gate 3 fixture.

            MD);
        exec($git . ' add -A');
        exec($git . ' commit -q -m "gate 3 packet"');

        return $repo;
    }

    /**
     * @param string[] $responses
     */
    private function makeFakeGh(array $responses): string
    {
        $dir = sys_get_temp_dir() . '/fake-gh-live-' . bin2hex(random_bytes(6));
        mkdir($dir, 0o777, true);
        $this->tempDirs[] = $dir;

        foreach (array_values($responses) as $i => $response) {
            file_put_contents($dir . '/response-' . ($i + 1), $response);
        }
        $last = count($responses);

        $script = <<<SH
            #!/usr/bin/env bash
            if [ "\$1" = "pr" ] && [ "\$2" = "checks" ]; then
                calls=\$(( \$(cat '{$dir}/calls' 2>/dev/null || echo 0) + 1 ))
                echo "\$calls" > '{$dir}/calls'
                n=\$calls
                if [ "\$n" -gt {$last} ]; then n={$last}; fi
                cat '{$dir}/response-'"\$n"
                exit 0
            fi
            echo "fake gh: unrecognized invocation: \$*" >&2
            exit 99
            SH;
        file_put_contents($dir . '/gh', $script);
        chmod($dir . '/gh', 0o755);

        return $dir;
    }
}
