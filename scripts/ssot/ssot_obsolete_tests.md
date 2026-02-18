# SSOT Obsolete Tests Report

## Summary Counts

| Metric | Count |
| --- | ---: |
| raw_user_create | 0 |
| raw_model_create_feature | 0 |
| raw_model_create_integration | 0 |
| hardcoded_api_paths | 0 |
| denylist_hits | 0 |
| skip_inventory | 11 |

## Fixture Pack Usage

- tests/Feature/Api/ProjectManagerApiIntegrationTest.php
- tests/Feature/BulkOperationsBasicTest.php
- tests/Feature/BusinessLogicTest.php
- tests/Feature/DashboardAnalyticsSimpleTest.php
- tests/Feature/SecurityFeaturesSimpleTest.php
- tests/Feature/UserManagementSimpleTest.php
- tests/Integration/SecurityIntegrationTest.php
- tests/Performance/DashboardPerformanceTest.php
- tests/Unit/Dashboard/DashboardRoleBasedServiceTest.php
- tests/Unit/Dashboard/DashboardServiceTest.php

## RAW CREATE TOP OFFENDERS

### raw_user_create (top 15 files)
No raw_user_create offenders detected.

### raw_model_create_feature (top 15 files)
No raw_model_create_feature offenders detected.

## SKIP INVENTORY

| Class::method | Group | Reason Token |
| --- | --- | --- |
| ApiTestConfiguration::test_redis_configuration | redis | REDIS_ |
| ApiTestConfiguration::test_required_services_available | redis | REDIS_ |
| BulkOperationsTest::skipUnlessSlowTestsEnabled | slow | RUN_SLOW_TESTS |
| FinalSystemTest::skipUnlessStressTestsEnabled | stress | RUN_STRESS_TESTS |
| FinalSystemTest::test_documentation_system | slow | dependency: |
| FinalSystemTest::test_support_ticket_system | slow | dependency: |
| FinalSystemTest::test_system_health_monitoring | slow | dependency: |
| LoadTest::setUp | load | RUN_LOAD_TESTS |
| LoadTest::skipIfAppIsUnreachable | slow | dependency: |
| PerformanceTest::skipUnlessSlowTestsEnabled | slow | RUN_SLOW_TESTS |
| ServiceUnitTest::skipUnlessRedisAvailable | redis | REDIS_ |

## New Violations vs Baseline

| Check | Current | New vs Baseline |
| --- | ---: | ---: |
| denylist_hits | 0 | 0 |
| hardcoded_api_paths | 0 | 0 |
| raw_model_create_feature | 0 | 0 |
| raw_model_create_integration | 0 | 0 |
| raw_user_create | 0 | 0 |
| skipped_tests_inventory | 11 | 0 |

## LEGACY

No legacy/orphan references detected.

## EXPERIMENTAL

No experimental/debug test routes detected.

## SLOW

```text
tests/Feature/Api/PerformanceTest.php:22: * @group slow
tests/Feature/Api/ProjectManagerApiIntegrationTest.php:16:/** @group slow */
tests/Feature/BulkOperationsTest.php:32:    private const SLOW_TESTS_ENV = 'RUN_SLOW_TESTS';
tests/Feature/BulkOperationsTest.php:33:    private const STRESS_TESTS_ENV = 'RUN_STRESS_TESTS';
tests/Feature/BulkOperationsTest.php:451:     * @group slow
tests/Feature/BulkOperationsTest.php:494:     * @group slow
tests/Feature/FinalSystemTest.php:224:     * @group slow
tests/Feature/FinalSystemTest.php:311:     * @group slow
tests/Feature/FinalSystemTest.php:413:     * @group slow
tests/Feature/LoadTest.php:140:        return filter_var(env('RUN_LOAD_TESTS', false), FILTER_VALIDATE_BOOLEAN);
tests/Feature/LoadTest.php:21: * @group slow
tests/Feature/LoadTest.php:22: * @group load
tests/Feature/LoadTest.php:41:            $this->markTestSkipped('Load tests require RUN_LOAD_TESTS=1 in your environment.');
tests/Feature/PerformanceTest.php:23:    private const SLOW_TESTS_ENV = 'RUN_SLOW_TESTS';
tests/Feature/PerformanceTest.php:24:    private const STRESS_TESTS_ENV = 'RUN_STRESS_TESTS';
tests/Feature/PerformanceTest.php:487:     * @group slow
tests/Feature/PerformanceTest.php:531:     * @group slow
tests/Integration/FinalSystemTest.php:224:     * @group stress
tests/Integration/FinalSystemTest.php:258:     * @group stress
tests/Integration/FinalSystemTest.php:29:    private const STRESS_TESTS_ENV = 'RUN_STRESS_TESTS';
tests/Integration/FinalSystemTest.php:675:     * @group stress
tests/Integration/FinalSystemTest.php:856:     * @group stress
```

## Suggested Actions

1. LEGACY: gate with `RUN_LEGACY_TESTS=1` and add `@group legacy`; delete only after coverage replacement.
2. EXPERIMENTAL: migrate to named routes or isolate behind explicit opt-in env flags.
3. SLOW: keep behind `RUN_SLOW_TESTS/RUN_STRESS_TESTS/RUN_LOAD_TESTS`; trim duplicated scenarios.
4. For active API tests, migrate hardcoded `/api/*` calls to named routes.
