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
     * Both CI Work-ID extraction locations must delegate to the shared
     * scripts/ci/extract-work-id.sh extractor rather than embed their own
     * copy of the extraction pattern — this makes divergence between the
     * two paths structurally impossible instead of merely tested-against.
     */
    private function assertDelegatesToSharedExtractor(string $relativePath): void
    {
        $content = file_get_contents($this->repoRoot() . '/' . $relativePath);
        $this->assertStringContainsString(
            'bash scripts/ci/extract-work-id.sh',
            $content,
            "{$relativePath} must delegate Work-ID extraction to the shared scripts/ci/extract-work-id.sh extractor."
        );
    }

    /**
     * Runs the REAL shared extractor script both CI paths delegate to,
     * against a "Work ID: <candidate>" body exactly as CI constructs it,
     * and returns the extracted token (or '' if nothing matched).
     */
    private function runRealExtraction(string $candidate): string
    {
        $extractorPath = $this->repoRoot() . '/scripts/ci/extract-work-id.sh';
        $this->assertFileExists($extractorPath);

        $body = "Work ID: {$candidate}\nGate 1: APPROVED";

        $output = shell_exec('printf ' . escapeshellarg('%s') . ' ' . escapeshellarg($body) . ' | bash ' . escapeshellarg($extractorPath));

        return trim((string) $output);
    }

    public static function validExtractionCandidates(): array
    {
        return [
            ['GAP-010', 'GAP-010'],
            ['GAP-010a', 'GAP-010a'],
            ['GAP-010b', 'GAP-010b'],
            ['GAP-014c', 'GAP-014c'],
            ['OWN-2026-004', 'OWN-2026-004'],
        ];
    }

    public static function invalidExtractionCandidates(): array
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
            ['XGAP-010b'],
            ['GAP-010bX'],
        ];
    }

    public function test_gate3_before_ready_delegates_to_shared_extractor(): void
    {
        $this->assertDelegatesToSharedExtractor('scripts/ci/check-gate3-before-ready.sh');
    }

    public function test_evidence_freshness_workflow_delegates_to_shared_extractor(): void
    {
        $this->assertDelegatesToSharedExtractor('.github/workflows/owner-governance-lint.yml');
    }

    /** @dataProvider validExtractionCandidates */
    public function test_shared_extractor_returns_exact_valid_tokens(string $candidate, string $expected): void
    {
        $this->assertSame(
            $expected,
            $this->runRealExtraction($candidate),
            "scripts/ci/extract-work-id.sh must return exactly '{$expected}' for '{$candidate}'."
        );
    }

    /**
     * The core regression this file exists to prevent: an unbounded
     * substring extraction pattern silently converts an INVALID candidate
     * into a DIFFERENT, valid-looking Work ID (e.g. 'GAP-010bb' -> 'GAP-010b',
     * 'GAP-0010' -> 'GAP-001', 'GAP-010-b' -> 'GAP-010') instead of matching
     * nothing. Every candidate here must extract to the empty string —
     * never accepted in full, never reduced to a valid parent ID, never
     * reduced to a valid sub-ID.
     *
     * @dataProvider invalidExtractionCandidates
     */
    public function test_shared_extractor_returns_nothing_for_invalid_tokens(string $candidate): void
    {
        $this->assertSame(
            '',
            $this->runRealExtraction($candidate),
            "scripts/ci/extract-work-id.sh must return EMPTY for invalid candidate '{$candidate}' — it must not silently convert it into a different valid Work ID."
        );
    }

    public function test_both_ci_paths_delegate_to_the_same_extractor_so_they_cannot_diverge(): void
    {
        $scriptContent = file_get_contents($this->repoRoot() . '/scripts/ci/check-gate3-before-ready.sh');
        $workflowContent = file_get_contents($this->repoRoot() . '/.github/workflows/owner-governance-lint.yml');

        preg_match('/bash scripts\/ci\/extract-work-id\.sh/', $scriptContent, $scriptMatch);
        preg_match('/bash scripts\/ci\/extract-work-id\.sh/', $workflowContent, $workflowMatch);

        $this->assertNotEmpty($scriptMatch, 'check-gate3-before-ready.sh must invoke the shared extractor.');
        $this->assertNotEmpty($workflowMatch, 'owner-governance-lint.yml must invoke the shared extractor.');
        $this->assertSame($scriptMatch[0], $workflowMatch[0], 'Both CI paths must invoke the extractor identically.');
    }
}
