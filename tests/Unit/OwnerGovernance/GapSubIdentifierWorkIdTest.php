<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Covers OWN-2026-004: canonical GAP sub-identifiers (e.g. GAP-010b,
 * GAP-014c) must be accepted by the packet schema and recognized in full
 * — never reduced to their parent GAP-NNN prefix — by both CI Work-ID
 * extraction locations.
 */
class GapSubIdentifierWorkIdTest extends TestCase
{
    private function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private function schemaWorkIdPattern(): string
    {
        $schema = Yaml::parseFile($this->repoRoot() . '/docs/owner-governance/packet-schema.yml');
        $this->assertArrayHasKey('work_id_pattern', $schema);

        return $schema['work_id_pattern'];
    }

    public static function acceptedWorkIds(): array
    {
        return [
            ['GAP-010'],
            ['GAP-010a'],
            ['GAP-010b'],
            ['GAP-014c'],
            ['GAP-999z'],
        ];
    }

    public static function rejectedWorkIds(): array
    {
        return [
            ['GAP-10'],
            ['GAP-010B'],
            ['GAP-010bb'],
            ['GAP-010-b'],
            ['GAP-0010'],
            ['GAP-0010b'],
            ['GAP-010_'],
            ['GAP-010/'],
        ];
    }

    /** @dataProvider acceptedWorkIds */
    public function test_canonical_schema_accepts_valid_gap_identity(string $workId): void
    {
        $pattern = $this->schemaWorkIdPattern();
        $this->assertSame(
            1,
            preg_match('/' . $pattern . '/', $workId),
            "Schema work_id_pattern must accept '{$workId}'."
        );
    }

    /** @dataProvider rejectedWorkIds */
    public function test_canonical_schema_rejects_invalid_gap_identity(string $workId): void
    {
        $pattern = $this->schemaWorkIdPattern();
        $this->assertSame(
            0,
            preg_match('/' . $pattern . '/', $workId),
            "Schema work_id_pattern must reject '{$workId}'."
        );
    }

    public function test_schema_pattern_does_not_alter_zmc_wp_or_own_forms(): void
    {
        $pattern = $this->schemaWorkIdPattern();

        foreach (['ZMC-001', 'ZMC-1234', 'WP-042', 'WP-9999', 'OWN-2026-004'] as $stillValid) {
            $this->assertSame(1, preg_match('/' . $pattern . '/', $stillValid), "'{$stillValid}' must remain valid.");
        }

        foreach (['ZMC-01', 'WP-42', 'OWN-26-004', 'OWN-2026-04'] as $stillInvalid) {
            $this->assertSame(0, preg_match('/' . $pattern . '/', $stillInvalid), "'{$stillInvalid}' must remain invalid.");
        }
    }

    /**
     * Extracts the GAP alternative out of the extraction regex embedded in
     * a CI script/workflow's `grep -oE '(...)'` line, so this test breaks
     * if a future edit reverts the extraction pattern instead of relying
     * on a hardcoded copy that could drift from the real file.
     */
    private function extractGapAlternativeFromExtractionLine(string $content): string
    {
        $this->assertMatchesRegularExpression(
            '/grep -oE \'\((GAP-[^|]+)\|OWN-\[0-9\]\{4\}-\[0-9\]\{3\}\)\'/',
            $content
        );
        preg_match('/grep -oE \'\((GAP-[^|]+)\|OWN-\[0-9\]\{4\}-\[0-9\]\{3\}\)\'/', $content, $m);

        return $m[1];
    }

    public function test_gate3_before_ready_extraction_recognizes_full_subidentifier(): void
    {
        $content = file_get_contents($this->repoRoot() . '/scripts/ci/check-gate3-before-ready.sh');
        $gapAlternative = $this->extractGapAlternativeFromExtractionLine($content);

        preg_match('/' . $gapAlternative . '/', 'GAP-010b', $matches);
        $this->assertSame('GAP-010b', $matches[0] ?? null, 'check-gate3-before-ready.sh must extract the full sub-identifier, not the parent prefix.');
    }

    public function test_evidence_freshness_workflow_extraction_recognizes_full_subidentifier(): void
    {
        $content = file_get_contents($this->repoRoot() . '/.github/workflows/owner-governance-lint.yml');
        $gapAlternative = $this->extractGapAlternativeFromExtractionLine($content);

        preg_match('/' . $gapAlternative . '/', 'GAP-010b', $matches);
        $this->assertSame('GAP-010b', $matches[0] ?? null, 'owner-governance-lint.yml must extract the full sub-identifier, not the parent prefix.');
    }

    public function test_extraction_lines_actually_run_via_grep_and_return_the_full_subidentifier(): void
    {
        foreach (
            [
                'scripts/ci/check-gate3-before-ready.sh' => 'work_id="',
                '.github/workflows/owner-governance-lint.yml' => 'work_id="',
            ] as $relativePath => $needle
        ) {
            $content = file_get_contents($this->repoRoot() . '/' . $relativePath);
            $gapAlternative = $this->extractGapAlternativeFromExtractionLine($content);

            $extractionPattern = "({$gapAlternative}|OWN-[0-9]{4}-[0-9]{3})";
            $body = "Work ID: GAP-010b\nGate 1: APPROVED";

            $escapedPattern = escapeshellarg($extractionPattern);
            $output = shell_exec("printf '%s' " . escapeshellarg($body) . " | grep -oE {$escapedPattern} | head -n1");

            $this->assertSame('GAP-010b', trim((string) $output), "{$relativePath}'s real extraction pattern, run through grep -oE, must return the full sub-identifier.");
        }
    }
}
