<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/scripts/ssot/owner_governance_lint.php';

/**
 * Covers GAP-047 Defect B, Binding Clarification B: fail-closed loading
 * semantics for docs/owner-governance/grandfathered-nonfrontmatter-documents.txt.
 * Every failure mode here must throw, never silently return [] (which would
 * be indistinguishable from "legitimately zero grandfather entries" and
 * defeat the fail-closed contract).
 */
class GrandfatherLoaderTest extends TestCase
{
    /** @var string[] */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $f) {
            @chmod($f, 0644);
            @unlink($f);
        }
        $this->tempFiles = [];
        parent::tearDown();
    }

    private function writeTemp(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'gap047-grandfather-fixture-');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    public function test_missing_file_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        \owner_governance_load_grandfathered_paths('/nonexistent/path/does-not-exist.txt');
    }

    public function test_unreadable_file_throws(): void
    {
        $path = $this->writeTemp("docs/superpowers/specs/x.md\n");
        chmod($path, 0000);
        try {
            $this->expectException(\RuntimeException::class);
            \owner_governance_load_grandfathered_paths($path);
        } finally {
            chmod($path, 0644); // restore so tearDown can unlink
        }
    }

    public function test_absolute_path_entry_throws(): void
    {
        $path = $this->writeTemp("/etc/passwd\n");
        $this->expectException(\RuntimeException::class);
        \owner_governance_load_grandfathered_paths($path);
    }

    public function test_path_traversal_entry_throws(): void
    {
        $path = $this->writeTemp("docs/superpowers/specs/../../../etc/passwd\n");
        $this->expectException(\RuntimeException::class);
        \owner_governance_load_grandfathered_paths($path);
    }

    public function test_entry_outside_approved_roots_throws(): void
    {
        $path = $this->writeTemp("docs/owner-governance/legacy-work-ids.txt\n");
        $this->expectException(\RuntimeException::class);
        \owner_governance_load_grandfathered_paths($path);
    }

    public function test_non_md_entry_throws(): void
    {
        $path = $this->writeTemp("docs/superpowers/specs/not-markdown.txt\n");
        $this->expectException(\RuntimeException::class);
        \owner_governance_load_grandfathered_paths($path);
    }

    public function test_duplicate_entry_throws(): void
    {
        $path = $this->writeTemp("docs/superpowers/specs/dup.md\ndocs/superpowers/specs/dup.md\n");
        $this->expectException(\RuntimeException::class);
        \owner_governance_load_grandfathered_paths($path);
    }

    public function test_malformed_entry_throws(): void
    {
        // A line that is neither blank, a comment, nor a plausible relative path
        // (contains a NUL-unsafe or otherwise unparsable shape) — using an entry
        // with an embedded tab/control character as the malformed case.
        $path = $this->writeTemp("docs/superpowers/specs/bad\tentry.md\n");
        $this->expectException(\RuntimeException::class);
        \owner_governance_load_grandfathered_paths($path);
    }

    public function test_blank_lines_and_comments_are_ignored(): void
    {
        $path = $this->writeTemp("# a comment\n\ndocs/superpowers/specs/a.md\n\n# another comment\ndocs/superpowers/plans/b.md\n\n");
        $result = \owner_governance_load_grandfathered_paths($path);
        sort($result);
        $this->assertSame(['docs/superpowers/plans/b.md', 'docs/superpowers/specs/a.md'], $result);
    }

    public function test_valid_exact_paths_in_both_approved_roots_accepted(): void
    {
        $path = $this->writeTemp("docs/superpowers/specs/a.md\ndocs/superpowers/plans/b.md\n");
        $result = \owner_governance_load_grandfathered_paths($path);
        sort($result);
        $this->assertSame(['docs/superpowers/plans/b.md', 'docs/superpowers/specs/a.md'], $result);
    }

    public function test_empty_file_after_comments_and_blanks_returns_empty_array_not_error(): void
    {
        // An empty RESULT SET is legitimate (zero grandfathered entries is a
        // valid state); an empty/missing/unreadable FILE is not. This proves
        // the two are distinguished correctly.
        $path = $this->writeTemp("# just a header\n\n# nothing else\n");
        $result = \owner_governance_load_grandfathered_paths($path);
        $this->assertSame([], $result);
    }
}
