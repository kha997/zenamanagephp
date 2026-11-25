<?php

declare(strict_types=1);

namespace Tests\Feature\Recovery;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Keeps the OWN-2026-024 (O-17h) expected-RED quarantine exact and visible. Since H4 (2026-11-25) every one of the H1 RED
 * tests passes with its assertions unchanged, so the quarantine must be EMPTY: no test may carry the group, phpunit.xml must not
 * exclude it, and every released test must still exist (none may be deleted to make the suite green). Runs in ordinary CI.
 */
class UatRecovery07O17hQuarantineGuardTest extends TestCase
{
    private const GROUP = 'uat-recovery-07-o17h-red';

    /** Released test method => the Owner-gated slice that made it pass (assertions unchanged). */
    private const RELEASED = [
        'test_every_rbac_argument_is_a_real_permission_code' => 'H2a',
        'test_tenant_role_admin_cannot_self_grant_host_or_owner_authority' => 'H2b',
        'test_reseeding_does_not_restore_a_deliberately_revoked_grant' => 'H2b',
        'test_zero_grant_owner_is_denied_by_every_permission_gate' => 'H3',
        'test_tenant_owner_cannot_reach_host_level_routes_by_role_name' => 'H3',
        'test_revoking_a_canonical_grant_from_the_owner_removes_access' => 'H3',
        'test_zero_grant_owner_cannot_manage_roles_or_permissions' => 'H3',
        'test_zero_grant_owner_cannot_approve_a_change_request' => 'H3',
        'test_document_policy_does_not_bypass_canonical_grants' => 'H4',
        'test_sidebar_config_policy_does_not_bypass_canonical_grants' => 'H4',
    ];

    #[Group('uat-recovery-quarantine-guard')]
    public function test_the_o17h_quarantine_is_empty_after_h4(): void
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

        $this->assertSame([], $found, 'Since H4 no O-17h security test may be quarantined; a new RED test needs a new Owner gate.');
    }

    #[Group('uat-recovery-quarantine-guard')]
    public function test_phpunit_no_longer_excludes_the_group_and_the_manifest_records_every_release(): void
    {
        $xml = (string) file_get_contents(dirname(__DIR__, 3) . '/phpunit.xml');
        $this->assertStringNotContainsString(self::GROUP, $xml, 'the O-17h exclusion must be gone once the quarantine is empty');

        $doc = (string) file_get_contents(__DIR__ . '/QUARANTINE-O17H.md');
        foreach (self::RELEASED as $name => $slice) {
            $this->assertMatchesRegularExpression('/`' . preg_quote($name, '/') . '`[^\n]*\|\s*' . preg_quote($slice, '/') . '\b/', $doc, "{$name} must be recorded as released by {$slice}");
        }
        $this->assertStringContainsString('quarantine is empty', $doc);
    }

    #[Group('uat-recovery-quarantine-guard')]
    public function test_released_o17h_methods_still_exist(): void
    {
        foreach (array_keys(self::RELEASED) as $name) {
            $this->assertTrue(method_exists(UatRecovery07O17hExplicitRbacTest::class, $name), "{$name} must not be deleted to make the suite green");
        }
    }
}
