<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;

/**
 * Covers OWN-2026-005's fail-closed changed-file acquisition:
 * scripts/ci/fetch-pr-changed-files.sh must use the paginated REST API
 * (never `gh pr view --json files`, which silently truncates at 100 items
 * via its underlying `files(first: 100)` GraphQL query) and must fail
 * closed — nonzero exit, no usable JSON on stdout — whenever it cannot
 * PROVE the fetched list is complete.
 *
 * These tests replace `gh` with a fake executable placed first on PATH, so
 * they run deterministically offline (no real GitHub API calls, no real
 * large PR needed to prove the >100-file / multi-page path works).
 */
class FetchPrChangedFilesTest extends TestCase
{
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

    private function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * Writes a fake `gh` executable that answers `gh pr view ... --json
     * changedFiles ...` with $changedFilesCount (or fails, if
     * $changedFilesExit !== 0) and `gh api --paginate ...` with
     * $apiJsonArray (or fails, if $apiExit !== 0).
     */
    private function makeFakeGh(
        string $changedFilesStdout,
        int $changedFilesExit,
        string $apiStdout,
        int $apiExit
    ): string {
        $dir = sys_get_temp_dir() . '/fake-gh-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        $this->tempDirs[] = $dir;

        $changedFilesStdoutLiteral = escapeshellarg($changedFilesStdout);
        $apiStdoutLiteral = escapeshellarg($apiStdout);

        $script = <<<SH
#!/usr/bin/env bash
if [ "\$1" = "pr" ] && [ "\$2" = "view" ]; then
    printf '%s' {$changedFilesStdoutLiteral}
    exit {$changedFilesExit}
fi
if [ "\$1" = "api" ]; then
    printf '%s' {$apiStdoutLiteral}
    exit {$apiExit}
fi
echo "fake gh: unrecognized invocation: \$*" >&2
exit 99
SH;
        file_put_contents($dir . '/gh', $script);
        chmod($dir . '/gh', 0755);

        return $dir;
    }

    /**
     * @return array{stdout: string, stderr: string, exit: int}
     */
    private function runScript(string $fakeGhDir): array
    {
        $script = $this->repoRoot() . '/scripts/ci/fetch-pr-changed-files.sh';
        $this->assertFileExists($script);

        $env = [
            'PATH' => $fakeGhDir . ':' . getenv('PATH'),
            'PR_NUMBER' => '1',
            'GH_REPO' => 'owner/repo',
        ];

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(['bash', $script], $descriptors, $pipes, null, $env);
        $this->assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit];
    }

    // --- Scenario: multi-page pagination fully collected (150 files, one combined array) — PASS ---

    public function test_more_than_one_page_of_results_is_fully_collected(): void
    {
        $filenames = array_map(fn ($i) => "docs/superpowers/specs/fixture-{$i}.md", range(1, 150));
        $apiOutput = json_encode($filenames, JSON_THROW_ON_ERROR);

        $fakeGh = $this->makeFakeGh('150', 0, $apiOutput, 0);
        $result = $this->runScript($fakeGh);

        $this->assertSame(0, $result['exit'], "Expected success, got stderr: {$result['stderr']}");
        $decoded = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(150, $decoded, 'All 150 files across multiple pages must be present in the final output — this is exactly the case gh pr view --json files (100-item cap) would have silently truncated.');
        $this->assertSame($filenames, $decoded);
    }

    // --- Scenario: 101-file PR, 100 design-only + file #101 an app file — the OUTPUT must include #101 ---

    public function test_101st_file_beyond_the_old_100_item_cap_is_present_in_output(): void
    {
        $filenames = array_map(fn ($i) => "docs/superpowers/specs/fixture-{$i}.md", range(1, 100));
        $filenames[] = 'app/Http/Controllers/Api/ExportController.php'; // file #101
        $apiOutput = json_encode($filenames, JSON_THROW_ON_ERROR);

        $fakeGh = $this->makeFakeGh('101', 0, $apiOutput, 0);
        $result = $this->runScript($fakeGh);

        $this->assertSame(0, $result['exit']);
        $decoded = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(101, $decoded);
        $this->assertContains('app/Http/Controllers/Api/ExportController.php', $decoded, "File #101 (beyond the old GraphQL files(first: 100) cap) must be present — the whole point of this correction is that this file is never silently dropped.");
    }

    // --- Scenario: fetched count != authoritative changedFiles total — FAIL CLOSED ---

    public function test_count_mismatch_fails_closed(): void
    {
        $filenames = array_map(fn ($i) => "docs/superpowers/specs/fixture-{$i}.md", range(1, 99)); // only 99 fetched
        $apiOutput = json_encode($filenames, JSON_THROW_ON_ERROR);

        $fakeGh = $this->makeFakeGh('100', 0, $apiOutput, 0); // PR says 100 total
        $result = $this->runScript($fakeGh);

        $this->assertNotSame(0, $result['exit'], 'A count mismatch (99 fetched vs 100 expected) must fail closed.');
        $this->assertStringContainsString('mismatch', $result['stderr']);
        $this->assertSame('', trim($result['stdout']), 'No usable output may be produced on a fail-closed path.');
    }

    // --- Scenario: gh api itself errors — FAIL CLOSED ---

    public function test_api_error_fails_closed(): void
    {
        $fakeGh = $this->makeFakeGh('5', 0, 'HTTP 502 Bad Gateway', 1);
        $result = $this->runScript($fakeGh);

        $this->assertNotSame(0, $result['exit'], 'A gh api failure must fail closed.');
        $this->assertSame('', trim($result['stdout']));
    }

    // --- Scenario: gh pr view (authoritative count) itself errors — FAIL CLOSED ---

    public function test_authoritative_count_lookup_error_fails_closed(): void
    {
        $fakeGh = $this->makeFakeGh('HTTP 502 Bad Gateway', 1, '[]', 0);
        $result = $this->runScript($fakeGh);

        $this->assertNotSame(0, $result['exit'], 'Failure to determine the authoritative total must fail closed.');
        $this->assertSame('', trim($result['stdout']));
    }

    // --- Scenario: authoritative count is not a valid integer — FAIL CLOSED ---

    public function test_non_integer_authoritative_count_fails_closed(): void
    {
        $fakeGh = $this->makeFakeGh('not-a-number', 0, '["a.md"]', 0);
        $result = $this->runScript($fakeGh);

        $this->assertNotSame(0, $result['exit']);
        $this->assertSame('', trim($result['stdout']));
    }

    // --- Scenario: empty fetched list — FAIL CLOSED (never treated as "no changes") ---

    public function test_empty_fetched_list_fails_closed(): void
    {
        $fakeGh = $this->makeFakeGh('3', 0, '[]', 0);
        $result = $this->runScript($fakeGh);

        $this->assertNotSame(0, $result['exit'], 'An empty fetched list when the PR reports 3 changed files must fail closed, never be silently accepted as "no changes".');
        $this->assertSame('', trim($result['stdout']));
    }

    // --- Scenario: authoritative changedFiles itself reports 0 — FAIL CLOSED ---

    public function test_zero_authoritative_changed_files_fails_closed(): void
    {
        $fakeGh = $this->makeFakeGh('0', 0, '[]', 0);
        $result = $this->runScript($fakeGh);

        $this->assertNotSame(0, $result['exit']);
        $this->assertSame('', trim($result['stdout']));
    }

    // --- Scenario: gh api returns malformed JSON — FAIL CLOSED ---

    public function test_malformed_api_json_fails_closed(): void
    {
        $fakeGh = $this->makeFakeGh('2', 0, 'not valid json at all', 0);
        $result = $this->runScript($fakeGh);

        $this->assertNotSame(0, $result['exit']);
        $this->assertSame('', trim($result['stdout']));
    }

    // --- Scenario: a filename containing a comma is preserved losslessly (never comma-split) ---

    public function test_filename_containing_a_comma_is_preserved_exactly(): void
    {
        $filenames = [
            'docs/superpowers/specs/normal.md',
            'docs/superpowers/specs/weird, filename, with commas.md',
        ];
        $apiOutput = json_encode($filenames, JSON_THROW_ON_ERROR);

        $fakeGh = $this->makeFakeGh('2', 0, $apiOutput, 0);
        $result = $this->runScript($fakeGh);

        $this->assertSame(0, $result['exit']);
        $decoded = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(2, $decoded, 'A comma-containing filename must count as exactly ONE entry, never be split into extra entries.');
        $this->assertContains('docs/superpowers/specs/weird, filename, with commas.md', $decoded);
    }

    // --- Static assertions: the workflow and script use the paginated source, not the capped one ---

    public function test_workflow_does_not_use_the_capped_gh_pr_view_files_field(): void
    {
        $content = file_get_contents($this->repoRoot() . '/.github/workflows/owner-governance-lint.yml');
        $this->assertStringNotContainsString('--json files', $content, 'The workflow must not use the 100-item-capped `gh pr view --json files` field for changed-file evidence.');
        $this->assertStringContainsString('fetch-pr-changed-files.sh', $content);
    }

    public function test_fetch_script_uses_paginated_rest_api_and_cross_checks_authoritative_count(): void
    {
        $content = file_get_contents($this->repoRoot() . '/scripts/ci/fetch-pr-changed-files.sh');
        $this->assertStringContainsString('gh api --paginate', $content);
        $this->assertStringContainsString('changedFiles', $content, 'Must cross-check against the authoritative (non-paginated, non-capped) changedFiles scalar.');
        $this->assertStringContainsString('Failing closed', $content);
    }

    public function test_lint_script_requires_json_array_not_comma_separated_string(): void
    {
        $content = file_get_contents($this->repoRoot() . '/scripts/ssot/owner_governance_lint.php');
        $this->assertStringContainsString('--changed-files-json=', $content);
        $this->assertStringContainsString('JSON_THROW_ON_ERROR', $content);
        $this->assertStringNotContainsString("explode(','", $content, 'Must not comma-split a changed-files list — filenames may legally contain commas.');
    }
}
