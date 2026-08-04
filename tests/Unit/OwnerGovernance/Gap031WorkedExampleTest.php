<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class Gap031WorkedExampleTest extends TestCase
{
    private function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_all_four_gap031_packet_files_exist(): void
    {
        $root = $this->repoRoot();
        $this->assertFileExists($root . '/docs/owner-decisions/GAP-031/01-request.md');
        $this->assertFileExists($root . '/docs/owner-decisions/GAP-031/02-design.md');
        $this->assertFileExists($root . '/docs/owner-decisions/GAP-031/03-release.md');
        $this->assertFileExists($root . '/docs/owner-decisions/GAP-031/03-release-v2.md');
        $this->assertFileExists($root . '/docs/owner-governance/examples/GAP-031-owner-release-packet.md');
    }

    public function test_release_v2_supersedes_release_v1_without_contradiction(): void
    {
        $root = $this->repoRoot();

        $v1 = Yaml::parse($this->frontmatterOf($root . '/docs/owner-decisions/GAP-031/03-release.md'));
        $v2 = Yaml::parse($this->frontmatterOf($root . '/docs/owner-decisions/GAP-031/03-release-v2.md'));

        $this->assertSame('blocked_technical', $v1['gate_status']);
        $this->assertSame('docs/owner-decisions/GAP-031/03-release-v2.md', $v1['superseded_by']);

        $this->assertSame('awaiting_owner', $v2['gate_status']);
        $this->assertSame('docs/owner-decisions/GAP-031/03-release.md', $v2['supersedes']);

        // No two non-superseded Gate 3 packets for the same work_id may both be "current".
        $this->assertNotNull($v1['superseded_by'], 'v1 must point forward — it is not the current record.');
        $this->assertNull($v2['superseded_by'], 'v2 is the current record — nothing supersedes it yet.');
    }

    public function test_awaiting_owner_packet_does_not_ask_owner_to_read_pr_or_ci(): void
    {
        $root = $this->repoRoot();
        $content = file_get_contents($root . '/docs/owner-decisions/GAP-031/03-release-v2.md');

        foreach (['gh pr', 'CI log', 'source code', 'PR #238', 'stack trace'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase(
                $forbidden,
                $this->stripFrontmatterAndReferences($content),
                "Owner-facing body must not ask the owner to inspect '{$forbidden}'."
            );
        }
    }

    private function frontmatterOf(string $path): string
    {
        $content = file_get_contents($path);
        preg_match('/^---\n(.*?)\n---\n/s', $content, $matches);

        return $matches[1] ?? '';
    }

    private function stripFrontmatterAndReferences(string $content): string
    {
        // Remove the YAML frontmatter block (which legitimately contains a PR URL
        // under `references.pr` — that is an EEL link, not owner-facing prose).
        return (string) preg_replace('/^---\n.*?\n---\n/s', '', $content);
    }
}
