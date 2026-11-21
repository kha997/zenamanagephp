# Retired assertions (integration onto main `cb958ad48`)

These assertions were part of the local S1 work (base `a0381f5e3`). While the work was in flight, upstream merged **GAP-098** (`V1PermissionGapSeeder`: defines *and grants* `role.*`, `permission.*`, `audit.view`, `baseline.*`, `compensation.*`, `role.assign`, `user.view`, … under its own Owner gate), **GAP-079** (NULL permission-name backfill) and **GAP-030** (rfi permissions). Those merged decisions falsified the premise "these codes are undefined / granted to no role", so the S1 catalog-only mechanism was **dropped from the PR** (zero permission delta versus `main`) and the tests below were retired. No security rule was weakened: nothing was granted or revoked by this PR except the Owner-approved `project.assign_manager` (System Admin only). Original text preserved here for review:

```php
    #[Group('uat-recovery-02-s1')]
    public function test_S1_catalog_is_complete_except_the_recorded_owner_decision_aliases(): void
    {
        $required = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $mw) {
                if (is_string($mw) && preg_match('/(?:RoleBasedAccessControlMiddleware|^rbac):([a-z0-9_.\-]+)$/i', $mw, $m)
                    && (str_contains($m[1], '.') || str_contains($m[1], '-'))) {
                    $required[$m[1]] = true;
                }
            }
        }
        $missing = array_values(array_diff(array_keys($required), DB::table('permissions')->pluck('name')->filter()->all()));
        sort($missing);
        $expected = array_keys(ZenaPermissionsSeeder::OWNER_DECISION_REQUIRED_ALIASES);
        sort($expected);

        $this->assertSame($expected, $missing, 'residual gap must be EXACTLY the OWNER_DECISION_REQUIRED aliases — nothing silently skipped, nothing invented');
    }


    #[Group('uat-recovery-02-s1')]
    public function test_S1_owner_decision_aliases_were_not_created_as_duplicates(): void
    {
        foreach (ZenaPermissionsSeeder::OWNER_DECISION_REQUIRED_ALIASES as $code => $equivalents) {
            $this->assertFalse(DB::table('permissions')->where('code', $code)->orWhere('name', $code)->exists(), "{$code} must not be invented");
            foreach ($equivalents as $eq) {
                $this->assertTrue(DB::table('permissions')->where('name', $eq)->exists(), "documented equivalent {$eq} must really exist");
            }
        }
    }


    #[Group('uat-recovery-02-s1')]
    public function test_S1_catalog_only_permissions_exist_but_are_granted_to_no_role(): void
    {
        foreach (ZenaPermissionsSeeder::catalogOnlyCodes() as $code) {
            $this->assertSame(1, DB::table('permissions')->where('code', $code)->where('name', $code)->count(), "{$code} defined once with name=code");
            $this->assertSame(0, DB::table('role_permissions')->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')->where('permissions.code', $code)->count(), "{$code} must not be granted to anyone");
        }
    }


    #[Group('uat-recovery-02-s1')]
    public function test_S1_project_manager_and_member_gained_no_new_grants(): void
    {
        $new = array_merge(ZenaPermissionsSeeder::catalogOnlyCodes(), ['role.assign']);
        foreach (['Project Manager', 'Project Member'] as $role) {
            $this->assertSame([], array_values(array_intersect($this->grantedCodes($role), $new)), "{$role} must not hold any S1 permission");
        }
        // Snapshot taken from the pre-patch seed (UAT-RECOVERY-01 evidence): grant counts must not move.
        $this->assertCount(42, $this->grantedCodes('Project Manager'));
        $this->assertCount(15, $this->grantedCodes('Project Member'));
    }


// UatRecovery01IdentityRbacTest::test_CHAR_project_manager_cannot_create_rfi_under_current_seed — retired SECOND assertion:
        $this->assertSame($before, DB::table('rfis')->count());
        $this->assertSame(0, DB::table('role_permissions')
            ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('roles.name', 'Project Manager')->where('permissions.name', 'like', 'rfi.%')->count());
```
