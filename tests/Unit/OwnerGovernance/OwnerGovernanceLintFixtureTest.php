<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

require_once dirname(__DIR__, 3) . '/scripts/ssot/owner_governance_lint.php';

class OwnerGovernanceLintFixtureTest extends TestCase
{
    private array $schema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schema = Yaml::parseFile(dirname(__DIR__, 3) . '/docs/owner-governance/packet-schema.yml');
    }

    private function contentOf(string $fixture): string
    {
        return file_get_contents(__DIR__ . '/fixtures/' . $fixture);
    }

    /** @dataProvider validFixtures */
    public function test_valid_fixtures_produce_zero_violations(string $fixture): void
    {
        $violations = \owner_governance_validate_packet($fixture, $this->contentOf($fixture), $this->schema);
        $this->assertCount(0, $violations, "{$fixture} should be valid but got: " . json_encode(array_map(fn ($v) => $v->message, $violations)));
    }

    public static function validFixtures(): array
    {
        return [
            ['valid-gate-1.md'],
            ['valid-gate-2.md'],
            ['valid-gate-3-blocked.md'],
            ['valid-gate-3-awaiting.md'],
        ];
    }

    /** @dataProvider invalidFixtures */
    public function test_invalid_fixtures_produce_at_least_one_violation_of_the_expected_rule(string $fixture, string $expectedRule): void
    {
        $violations = \owner_governance_validate_packet($fixture, $this->contentOf($fixture), $this->schema);
        $rules = array_map(fn ($v) => $v->rule, $violations);

        $this->assertNotEmpty($violations, "{$fixture} should produce at least one violation.");
        $this->assertContains($expectedRule, $rules, "{$fixture} should trigger rule '{$expectedRule}', got: " . implode(', ', $rules));
    }

    public static function invalidFixtures(): array
    {
        return [
            ['invalid-missing-frontmatter.md', 'frontmatter'],
            ['invalid-bad-enum.md', 'gate-status-enum'],
            ['invalid-status-decision-contradiction.md', 'status-decision-contradiction'],
            ['invalid-blocked-requests-decision.md', 'decision-requested-leaked'],
            ['invalid-todo-placeholder.md', 'placeholder-token'],
            ['invalid-missing-provenance.md', 'dishonest-provenance'],
            ['invalid-not-ready-but-awaiting.md', 'not-ready-but-decision-eligible'],
            ['invalid-stale-decision-digest-mismatch.md', 'stale-decision-tree-digest-mismatch'],
        ];
    }

    // NOTE: the CI-check-based owner_governance_compute_evidence_digest()
    // function these two tests originally covered was removed and replaced
    // by owner_governance_compute_implementation_tree_digest() (a git-tree
    // digest) as part of Correction 2's revision (2026-08-05) — the old
    // model was self-referential: hashing head_sha meant presenting a Gate 3
    // packet (itself a commit, which changes head_sha) invalidated its own
    // evidence before it could ever be valid. Full coverage for the new
    // function's determinism, exclusion behavior, and the real regression
    // proof lives in tests/Unit/OwnerGovernance/EvidenceBindingTest.php.
    // owner_governance_count_blocking_checks() (the CI-greenness helper
    // extracted from check-evidence-freshness.sh) is also covered there.

    public function test_implementation_tree_digest_function_exists_and_is_used_by_the_live_script(): void
    {
        $lintScript = file_get_contents(dirname(__DIR__, 3) . '/scripts/ssot/owner_governance_lint.php');
        $this->assertStringContainsString('function owner_governance_compute_implementation_tree_digest', $lintScript);
        $this->assertStringNotContainsString('function owner_governance_compute_evidence_digest', $lintScript, 'The old, self-referential CI-check digest function must not exist anymore.');
    }
}
