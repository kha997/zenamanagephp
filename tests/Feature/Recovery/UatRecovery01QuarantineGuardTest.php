<?php declare(strict_types=1);

namespace Tests\Feature\Recovery;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Keeps the UAT-RECOVERY-01 quarantine honest: it must contain EXACTLY the three documented tests, be excluded by group
 * (never by a broad selection), and stay documented. Runs in ordinary CI. See tests/Feature/Recovery/QUARANTINE.md.
 */
class UatRecovery01QuarantineGuardTest extends TestCase
{
    private const GROUP = 'uat-recovery-01-quarantine';

    private const EXPECTED = [
        'test_D2_user_with_only_seeded_project_manager_role_passes_api_gate',
        'test_D3_project_member_can_open_my_work',
        'test_D2_no_seeded_user_can_create_users_through_canonical_api',
    ];

    #[Group('uat-recovery-quarantine-guard')]
    public function test_the_quarantine_contains_exactly_the_three_documented_tests(): void
    {
        $found = [];
        foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
            $class = __NAMESPACE__ . '\\' . basename($file, '.php');
            if (!class_exists($class)) {
                continue;
            }
            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
                foreach ($m->getAttributes(Group::class) as $attr) {
                    if (($attr->getArguments()[0] ?? null) === self::GROUP) {
                        $found[] = $m->class . '::' . $m->getName();
                    }
                }
            }
        }
        sort($found);
        $expected = array_map(fn (string $n) => UatRecovery01IdentityRbacTest::class . '::' . $n, self::EXPECTED);
        sort($expected);

        $this->assertSame($expected, $found, 'The quarantine must be exactly the three documented tests — no more, no fewer.');
    }

    #[Group('uat-recovery-quarantine-guard')]
    public function test_the_quarantine_is_a_group_level_exclusion_and_is_documented(): void
    {
        $xml = (string) file_get_contents(dirname(__DIR__, 3) . '/phpunit.xml');
        $this->assertStringContainsString('<group>' . self::GROUP . '</group>', $xml);
        $this->assertSame(1, substr_count($xml, self::GROUP), 'excluded once, by group name only');

        $doc = (string) file_get_contents(__DIR__ . '/QUARANTINE.md');
        foreach (self::EXPECTED as $name) {
            $this->assertStringContainsString($name, $doc, "{$name} must have documented removal conditions");
        }
    }

    #[Group('uat-recovery-quarantine-guard')]
    public function test_quarantined_test_methods_still_exist_with_their_assertions(): void
    {
        foreach (self::EXPECTED as $name) {
            $this->assertTrue(method_exists(UatRecovery01IdentityRbacTest::class, $name), "{$name} must not be deleted to make the suite green");
        }
    }
}
