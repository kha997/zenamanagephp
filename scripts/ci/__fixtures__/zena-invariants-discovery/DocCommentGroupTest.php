<?php declare(strict_types=1);

/**
 * GAP-050 Gate 3 correction (§1) fixture: a test class declaring its group
 * via the legacy PHPUnit doc-comment `@group` annotation, the form every
 * real `@group zena-invariants` file in this repo currently uses. Never
 * executed for real — only ever listed by
 * scripts/ci/zena-invariants-discover-files.php against the isolated
 * phpunit.xml in this directory, to prove doc-comment groups are still
 * discovered.
 *
 * @group zena-invariants-fixture
 */
final class DocCommentGroupTest extends \PHPUnit\Framework\TestCase
{
    public function test_placeholder(): void
    {
        $this->assertTrue(true);
    }
}
