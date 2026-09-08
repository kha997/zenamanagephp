<?php declare(strict_types=1);

use PHPUnit\Framework\Attributes\Group;

/**
 * GAP-050 Gate 3 correction (§1) fixture: a test class declaring its group
 * via the PHPUnit `#[Group]` attribute — the form the doc-comment
 * `@group` annotation is being gradually migrated to across PHPUnit
 * versions (PHPUnit 12 drops doc-comment metadata entirely). Never
 * executed for real — only ever listed by
 * scripts/ci/zena-invariants-discover-files.php against the isolated
 * phpunit.xml in this directory, to prove attribute-declared groups are
 * discovered exactly as reliably as doc-comment ones.
 */
#[Group('zena-invariants-fixture')]
final class AttributeGroupTest extends \PHPUnit\Framework\TestCase
{
    public function test_placeholder(): void
    {
        $this->assertTrue(true);
    }
}
