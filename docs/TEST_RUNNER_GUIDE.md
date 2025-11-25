# Test Runner Guide: I18n Translations API

## Cách chạy tests

### Backend Tests (Laravel)

```bash
# Chạy tất cả tests cho I18nTranslations
php artisan test --filter I18nTranslationsTest

# Chạy một test cụ thể
php artisan test --filter test_get_translations_with_defaults

# Chạy với coverage
php artisan test --filter I18nTranslationsTest --coverage

# Chạy với verbose output
php artisan test --filter I18nTranslationsTest -v
```

### Frontend Tests (nếu có)

```bash
cd frontend
npm test -- i18n
```

## Format báo cáo test results

Sau khi chạy tests, báo cáo theo format sau:

### 1. Test Summary

```
=== TEST SUMMARY ===
Total Tests: 15
Passed: 12
Failed: 3
Skipped: 0
Coverage: 85%
```

### 2. Failed Tests Detail

```
=== FAILED TESTS ===

1. test_invalid_locale_rejected
   Status: FAILED
   Error: Expected status 400 but got 200
   File: tests/Feature/Api/I18nTranslationsTest.php:145
   Message: Invalid locale should be rejected but endpoint accepted it
   
   Suggested Fix:
   - Check isValidLocale() method in I18nController
   - Ensure locale validation happens before processing

2. test_path_traversal_prevention
   Status: FAILED
   Error: Path traversal detected - file system accessed
   File: tests/Feature/Api/I18nTranslationsTest.php:280
   Message: Namespace validation not working correctly
   
   Suggested Fix:
   - Strengthen namespace validation regex
   - Add additional path traversal checks

3. test_etag_generation_and_304_response
   Status: FAILED
   Error: 304 response not returned when ETag matches
   File: tests/Feature/Api/I18nTranslationsTest.php:220
   Message: If-None-Match header not being checked
   
   Suggested Fix:
   - Verify If-None-Match header parsing
   - Check ETag comparison logic
```

### 3. Security Issues

```
=== SECURITY ISSUES ===

1. CRITICAL: Path Traversal Vulnerability
   Description: Namespace parameter allows path traversal
   Impact: Could expose sensitive files
   Location: I18nController@parseNamespaces()
   Recommendation: Strengthen validation, add whitelist

2. MEDIUM: Missing Rate Limiting
   Description: No rate limiting on public endpoint
   Impact: Could be abused for DoS
   Recommendation: Add rate limiting middleware

3. LOW: ETag Header Exposure
   Description: ETag reveals content hash
   Impact: Information disclosure
   Recommendation: Consider using weak ETags
```

### 4. Performance Issues

```
=== PERFORMANCE ISSUES ===

1. Cache Not Working
   Description: Server-side cache not being hit
   Impact: Slow response times
   Location: Cache::remember() in getTranslations()
   Recommendation: Check cache driver configuration

2. Large Response Size
   Description: Loading all namespaces by default
   Impact: High bandwidth usage
   Recommendation: Reduce default namespaces or implement pagination
```

### 5. Bugs Found

```
=== BUGS FOUND ===

1. Bug #1: Invalid Locale Not Rejected
   Severity: HIGH
   Description: Endpoint accepts invalid locale codes
   Steps to Reproduce:
   1. Call GET /api/i18n/translations?locale=xyz
   2. Observe 200 response instead of 400
   Expected: 400 Bad Request with error message
   Actual: 200 OK with default locale
   Fix: Add locale validation before processing

2. Bug #2: ETag Not Working
   Severity: MEDIUM
   Description: 304 Not Modified not returned when ETag matches
   Steps to Reproduce:
   1. Call GET /api/i18n/translations
   2. Get ETag from response
   3. Call again with If-None-Match header
   4. Observe 200 instead of 304
   Expected: 304 Not Modified
   Actual: 200 OK with full response
   Fix: Check If-None-Match header parsing logic
```

### 6. Recommendations

```
=== RECOMMENDATIONS ===

1. Add more test cases for edge cases
   - Empty namespaces array
   - Very long namespace names
   - Special characters in translations

2. Implement integration tests
   - Test with real translation files
   - Test with multiple locales simultaneously

3. Add performance benchmarks
   - Measure response time under load
   - Test cache hit rates

4. Improve error messages
   - More descriptive error messages
   - Include suggestions for fixes
```

## Template cho báo cáo

Copy template này và fill in:

```markdown
# Test Report: I18n Translations API

**Date:** [DATE]
**Tester:** [NAME]
**Environment:** [ENV]

## Summary
- Total Tests: [NUMBER]
- Passed: [NUMBER]
- Failed: [NUMBER]
- Coverage: [PERCENTAGE]%

## Failed Tests
[List failed tests with details]

## Security Issues
[List security issues found]

## Performance Issues
[List performance issues]

## Bugs Found
[List bugs with severity and steps to reproduce]

## Recommendations
[List recommendations for improvements]
```

## Checklist trước khi báo cáo

- [ ] Tất cả tests đã chạy
- [ ] Failed tests đã được investigate
- [ ] Error messages đã được document
- [ ] Security issues đã được identify
- [ ] Performance issues đã được measure
- [ ] Bugs đã được reproduce
- [ ] Recommendations đã được suggest
- [ ] Code changes cần thiết đã được identify

