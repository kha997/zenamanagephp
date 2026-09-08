<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * GAP-050 Gate 3 correction (§1): proves
 * scripts/ci/zena-invariants-discover-files.php discovers test files
 * regardless of whether their group is declared via the legacy PHPUnit
 * doc-comment `@group` annotation or the `#[Group]` attribute — the exact
 * false-green risk this correction closes (a file migrated from
 * doc-comment to attribute form would silently vanish from a plain
 * `grep -rl '@group ...'` scan while the job kept reporting green).
 *
 * Deliberately a plain PHPUnit\Framework\TestCase (no Laravel app boot):
 * this only shells out to the discovery script and inspects its output.
 */
final class ZenaInvariantsDiscoveryTest extends TestCase
{
    private const FIXTURE_DIR_RAW = __DIR__ . '/../../scripts/ci/__fixtures__/zena-invariants-discovery';

    public function test_discovers_both_doc_comment_and_attribute_declared_groups(): void
    {
        $output = $this->runDiscovery('zena-invariants-fixture');
        $fixtureDir = realpath(self::FIXTURE_DIR_RAW);
        $this->assertIsString($fixtureDir, 'Fixture directory must exist.');

        $this->assertContains(
            $fixtureDir . '/DocCommentGroupTest.php',
            $output,
            'Expected the doc-comment @group fixture file to be discovered.'
        );
        $this->assertContains(
            $fixtureDir . '/AttributeGroupTest.php',
            $output,
            'Expected the #[Group] attribute fixture file to be discovered.'
        );
        $this->assertCount(
            2,
            $output,
            'Expected exactly the two fixture files, no more, no fewer.'
        );
    }

    public function test_returns_nothing_for_a_group_with_zero_matches(): void
    {
        $output = $this->runDiscovery('zena-invariants-fixture-group-that-does-not-exist');

        $this->assertSame(
            [],
            $output,
            'A non-matching group must produce an empty inventory, not an error — the caller decides whether zero-selection is acceptable.'
        );
    }

    /**
     * @return list<string>
     */
    private function runDiscovery(string $group): array
    {
        $repoRoot = dirname(__DIR__, 2);
        $script = $repoRoot . '/scripts/ci/zena-invariants-discover-files.php';
        $fixtureConfig = self::FIXTURE_DIR_RAW . '/phpunit.xml';

        $command = implode(' ', array_map('escapeshellarg', [
            PHP_BINARY,
            $script,
            '--group=' . $group,
            '--config=' . $fixtureConfig,
        ]));

        $descriptorSpec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptorSpec, $pipes, $repoRoot);
        $this->assertIsResource($process, 'Failed to start the discovery script subprocess.');

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode, "Discovery script exited {$exitCode}. stderr:\n{$stderr}");

        $lines = array_values(array_filter(explode("\n", $stdout), static fn ($line) => $line !== ''));
        sort($lines, SORT_STRING);

        return $lines;
    }
}
