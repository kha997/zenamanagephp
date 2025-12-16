# Test Documentation: I18n Translations API

## Tổng quan

Đây là tài liệu test cho API endpoint `/api/i18n/translations`. File này hướng dẫn Codex/Cursor cách viết và chạy tests, cũng như cách báo cáo kết quả.

## Files liên quan

1. **`docs/TEST_SPEC_I18N_TRANSLATIONS_API.md`**
   - Test specification chi tiết
   - Tất cả test cases cần implement
   - Expected behaviors

2. **`tests/Feature/Api/I18nTranslationsTest.php`**
   - Implementation mẫu của một số test cases
   - Có thể dùng làm reference

3. **`docs/TEST_RUNNER_GUIDE.md`**
   - Hướng dẫn chạy tests
   - Format báo cáo kết quả
   - Template cho test report

## Yêu cầu cho Codex/Cursor

### 1. Implement Tests

**Task:** Hoàn thiện file `tests/Feature/Api/I18nTranslationsTest.php` với tất cả test cases trong `TEST_SPEC_I18N_TRANSLATIONS_API.md`

**Requirements:**
- Implement tất cả test cases được liệt kê
- Đảm bảo tests pass
- Code coverage > 80%
- Tests phải độc lập (không phụ thuộc vào nhau)
- Clean up sau mỗi test (cache, files, etc.)

**Priority Test Cases:**
1. Happy path tests (1.1 - 1.4) - CRITICAL
2. Security tests (5.1 - 5.3) - CRITICAL
3. Locale resolution tests (2.1 - 2.4) - HIGH
4. Caching tests (4.1 - 4.4) - HIGH
5. Error handling tests (6.1 - 6.2) - MEDIUM
6. Performance tests (7.1 - 7.2) - LOW

### 2. Chạy Tests và Báo cáo

**Task:** Chạy tests và báo cáo kết quả theo format trong `TEST_RUNNER_GUIDE.md`

**Steps:**
1. Chạy tests: `php artisan test --filter I18nTranslationsTest`
2. Ghi lại kết quả (passed/failed)
3. Investigate failed tests
4. Identify security issues
5. Measure performance
6. Document bugs found
7. Suggest improvements

**Output:** Tạo file `TEST_REPORT_I18N.md` với format trong `TEST_RUNNER_GUIDE.md`

### 3. Fix Issues

**Task:** Fix các issues được tìm thấy trong tests

**Process:**
1. Đọc test report
2. Prioritize issues (Critical > High > Medium > Low)
3. Fix issues theo priority
4. Re-run tests để verify
5. Update test report

**Communication:**
- Nếu không thể fix, báo cáo với:
  - Issue description
  - Why it can't be fixed
  - Suggested workaround
  - Impact assessment

## Test Coverage Goals

- **Unit Tests:** > 90% coverage cho I18nController methods
- **Feature Tests:** Tất cả API endpoints được test
- **Security Tests:** Tất cả security scenarios được cover
- **Edge Cases:** Tất cả edge cases được handle

## Security Checklist

Khi implement tests, đảm bảo cover:

- [ ] Path traversal prevention
- [ ] XSS prevention
- [ ] Input validation
- [ ] Authentication/Authorization (nếu có)
- [ ] Rate limiting (nếu có)
- [ ] Information disclosure
- [ ] Cache poisoning

## Performance Checklist

- [ ] Response time < 500ms (cold cache)
- [ ] Response time < 50ms (warm cache)
- [ ] Cache hit rate > 80%
- [ ] Memory usage reasonable
- [ ] No memory leaks

## Reporting Format

Khi báo cáo, sử dụng format sau:

```markdown
# Test Report: [Component Name]

## Summary
- Total: X tests
- Passed: Y
- Failed: Z
- Coverage: XX%

## Critical Issues
[List critical issues]

## Recommendations
[List recommendations]
```

## Notes

1. **Test Isolation:** Mỗi test phải độc lập, không phụ thuộc vào test khác
2. **Clean Up:** Luôn clean up sau mỗi test (cache, database, files)
3. **Mocking:** Mock external dependencies khi cần
4. **Assertions:** Sử dụng descriptive assertions
5. **Error Messages:** Error messages phải rõ ràng và actionable

## Questions?

Nếu có questions về test implementation:
1. Check `TEST_SPEC_I18N_TRANSLATIONS_API.md` trước
2. Check `TEST_RUNNER_GUIDE.md` cho format
3. Check existing tests trong `I18nTranslationsTest.php` làm reference
4. Nếu vẫn không rõ, document question và suggest approach

