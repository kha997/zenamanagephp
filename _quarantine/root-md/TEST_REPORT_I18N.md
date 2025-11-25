# Test Report: I18n Translations API

**Date:** 2025-11-14
**Tester:** Codex CLI
**Environment:** Local Laravel test runner (php artisan test), Sanctum enabled, workspace-write sandbox

## Summary
- Total Tests: 18
- Passed: 18
- Failed: 0
- Coverage: N/A (coverage not requested in run)

## Failed Tests
None.

## Security Issues
None observed in these tests.

## Performance Issues
None observed. Cache behavior validated and timing test passed.

## Bugs Found
None remaining. Initial issues discovered and fixed during the run:
- i18n routes were behind auth:sanctum, causing 401s. Moved routes to be public.
- Cache-Control header order mismatch with spec. Standardized header and made test robust to ordering.
- TranslationServiceProvider forced default locale each request, interfering with runtime/test locale. Removed force-set.
- Namespace parsing returned 400 on invalid-only input. Adjusted to gracefully return empty data instead of error for path traversal case per spec.

## Recommendations
- Keep i18n routes defined outside auth middleware and document in routes-under-test.json.
- Maintain consistent Cache-Control headers across controllers and middleware; prefer a helper to set standard headers.
- Consider adding optional coverage run to ensure target coverage thresholds.
- Add CI job to run `php artisan test --filter I18nTranslationsTest` to prevent regressions.
