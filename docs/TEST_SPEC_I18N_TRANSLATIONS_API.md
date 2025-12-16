# Test Specification: I18n Translations API Endpoint

## Mục đích
File này mô tả chi tiết các test cases cần viết cho API endpoint `/api/i18n/translations` để đảm bảo tính an toàn, hiệu năng và tính đúng đắn của implementation.

## Phạm vi Test

### Backend Tests (Laravel)
- Unit tests cho `I18nController@getTranslations`
- Feature tests cho API endpoint
- Security tests
- Performance/Caching tests

### Frontend Tests (React)
- Unit tests cho `getTranslations()` API client
- Component tests cho `I18nProvider`
- Integration tests cho translation loading

---

## Backend Test Cases

### 1. Happy Path Tests

#### Test 1.1: Get translations với default locale và namespaces
**File:** `tests/Feature/Api/I18nTranslationsTest.php`
**Test name:** `test_get_translations_with_defaults`
**Description:** Test endpoint trả về translations với locale mặc định và default namespaces
**Steps:**
1. Gọi `GET /api/i18n/translations`
2. Assert status 200
3. Assert response structure: `{success: true, locale: string, data: {...}}`
4. Assert có data cho default namespaces (app, settings, tasks, projects, dashboard, auth)
5. Assert nested structure (không phải flat)
6. Assert có ETag header
7. Assert có Cache-Control header với `public, max-age=3600`

**Expected:**
- Status: 200
- Response có đầy đủ translations cho default namespaces
- Headers đúng format

---

#### Test 1.2: Get translations với locale cụ thể
**Test name:** `test_get_translations_with_specific_locale`
**Steps:**
1. Gọi `GET /api/i18n/translations?locale=vi`
2. Assert status 200
3. Assert `response.locale === 'vi'`
4. Assert translations là tiếng Việt (kiểm tra một vài keys)

**Expected:**
- Locale được set đúng
- Translations đúng ngôn ngữ

---

#### Test 1.3: Get translations với namespaces cụ thể
**Test name:** `test_get_translations_with_specific_namespaces`
**Steps:**
1. Gọi `GET /api/i18n/translations?namespaces=app,settings`
2. Assert status 200
3. Assert chỉ có data cho `app` và `settings`
4. Assert không có data cho namespaces khác

**Expected:**
- Chỉ load namespaces được yêu cầu
- Không load namespaces không cần thiết

---

#### Test 1.4: Get translations với flat structure
**Test name:** `test_get_translations_with_flat_structure`
**Steps:**
1. Gọi `GET /api/i18n/translations?flat=true`
2. Assert status 200
3. Assert data structure là flat: `{"app.title": "...", "settings.save": "..."}`
4. Assert không có nested structure

**Expected:**
- Flat structure đúng format
- Keys có format `namespace.key`

---

### 2. Locale Resolution Tests

#### Test 2.1: Locale từ query parameter
**Test name:** `test_locale_resolution_from_query_param`
**Steps:**
1. Set app locale = 'en'
2. Gọi `GET /api/i18n/translations?locale=vi`
3. Assert `response.locale === 'vi'`

**Expected:**
- Query param có priority cao nhất

---

#### Test 2.2: Locale từ Accept-Language header
**Test name:** `test_locale_resolution_from_accept_language_header`
**Steps:**
1. Set app locale = 'en'
2. Gọi `GET /api/i18n/translations` với header `Accept-Language: vi,en;q=0.9`
3. Assert `response.locale === 'vi'`

**Expected:**
- Accept-Language header được parse đúng
- Locale được resolve từ header nếu không có query param

---

#### Test 2.3: Locale fallback to app locale
**Test name:** `test_locale_fallback_to_app_locale`
**Steps:**
1. Set app locale = 'vi'
2. Gọi `GET /api/i18n/translations` (không có query param và header)
3. Assert `response.locale === 'vi'`

**Expected:**
- Fallback về app locale nếu không có query/header

---

#### Test 2.4: Invalid locale được reject
**Test name:** `test_invalid_locale_rejected`
**Steps:**
1. Gọi `GET /api/i18n/translations?locale=invalid`
2. Assert status 400
3. Assert error message chứa "Unsupported locale"

**Expected:**
- Invalid locale bị reject
- Error message rõ ràng

---

### 3. Namespace Validation Tests

#### Test 3.1: Valid namespaces được accept
**Test name:** `test_valid_namespaces_accepted`
**Steps:**
1. Gọi `GET /api/i18n/translations?namespaces=app,settings,tasks`
2. Assert status 200
3. Assert có data cho tất cả namespaces hợp lệ

**Expected:**
- Valid namespaces được load thành công

---

#### Test 3.2: Invalid namespaces được filter
**Test name:** `test_invalid_namespaces_filtered`
**Steps:**
1. Gọi `GET /api/i18n/translations?namespaces=app,../../../etc/passwd,settings`
2. Assert status 200
3. Assert chỉ có data cho `app` và `settings`
4. Assert không có path traversal

**Expected:**
- Invalid namespaces bị filter
- Path traversal được prevent

---

#### Test 3.3: Empty namespaces được reject
**Test name:** `test_empty_namespaces_rejected`
**Steps:**
1. Gọi `GET /api/i18n/translations?namespaces=`
2. Assert status 400 hoặc fallback về default namespaces
3. Assert error message hoặc default behavior

**Expected:**
- Empty namespaces được handle đúng

---

#### Test 3.4: Namespace với special characters được reject
**Test name:** `test_namespace_with_special_characters_rejected`
**Steps:**
1. Gọi `GET /api/i18n/translations?namespaces=app<script>,settings`
2. Assert status 200 (chỉ load `settings`)
3. Assert không có XSS vulnerability

**Expected:**
- Special characters được sanitize
- Không có XSS risk

---

### 4. Caching Tests

#### Test 4.1: Server-side cache hoạt động
**Test name:** `test_server_side_cache_works`
**Steps:**
1. Gọi `GET /api/i18n/translations` lần 1
2. Assert cache key được tạo
3. Gọi lại cùng request lần 2
4. Assert response giống nhau
5. Assert cache được hit (có thể check cache stats)

**Expected:**
- Cache được tạo và reuse
- Performance tốt hơn ở lần 2

---

#### Test 4.2: ETag được generate đúng
**Test name:** `test_etag_generation`
**Steps:**
1. Gọi `GET /api/i18n/translations`
2. Assert có ETag header
3. Assert ETag là MD5 hash của response data
4. Gọi lại với `If-None-Match: {etag}`
5. Assert status 304

**Expected:**
- ETag được generate đúng
- 304 response khi ETag match

---

#### Test 4.3: Cache invalidation khi locale thay đổi
**Test name:** `test_cache_invalidation_on_locale_change`
**Steps:**
1. Gọi `GET /api/i18n/translations?locale=en`
2. Gọi `GET /api/i18n/translations?locale=vi`
3. Assert cache keys khác nhau
4. Assert translations khác nhau

**Expected:**
- Cache keys khác nhau cho mỗi locale
- Không có cache pollution

---

#### Test 4.4: Cache invalidation khi namespaces thay đổi
**Test name:** `test_cache_invalidation_on_namespaces_change`
**Steps:**
1. Gọi `GET /api/i18n/translations?namespaces=app`
2. Gọi `GET /api/i18n/translations?namespaces=app,settings`
3. Assert cache keys khác nhau

**Expected:**
- Cache keys khác nhau cho mỗi namespace combination

---

### 5. Security Tests

#### Test 5.1: Path traversal prevention
**Test name:** `test_path_traversal_prevention`
**Steps:**
1. Gọi `GET /api/i18n/translations?namespaces=../../../etc/passwd`
2. Assert không có file system access
3. Assert error hoặc empty result

**Expected:**
- Path traversal được prevent
- Không có file system access

---

#### Test 5.2: Public endpoint không cần auth
**Test name:** `test_public_endpoint_no_auth_required`
**Steps:**
1. Gọi `GET /api/i18n/translations` không có auth token
2. Assert status 200
3. Assert không có auth error

**Expected:**
- Endpoint public, không cần auth
- Response thành công

---

#### Test 5.3: Rate limiting (nếu có)
**Test name:** `test_rate_limiting_if_configured`
**Steps:**
1. Gọi endpoint nhiều lần liên tiếp (>100 requests)
2. Assert không có rate limit error (vì có cache)
3. Hoặc assert rate limit nếu được config

**Expected:**
- Rate limiting hoạt động nếu được config
- Cache giúp giảm load

---

### 6. Error Handling Tests

#### Test 6.1: Missing translation files được handle gracefully
**Test name:** `test_missing_translation_files_handled`
**Steps:**
1. Tạo namespace không tồn tại trong lang/
2. Gọi `GET /api/i18n/translations?namespaces=nonexistent`
3. Assert status 200
4. Assert data không có namespace đó hoặc empty object

**Expected:**
- Missing files không crash
- Graceful degradation

---

#### Test 6.2: Invalid translation file format được handle
**Test name:** `test_invalid_translation_file_format_handled`
**Steps:**
1. Tạo file translation với syntax error
2. Gọi endpoint với namespace đó
3. Assert status 200
4. Assert error được log nhưng không crash
5. Assert các namespaces khác vẫn load được

**Expected:**
- Invalid files không crash toàn bộ
- Error được log
- Các namespaces khác vẫn hoạt động

---

### 7. Performance Tests

#### Test 7.1: Response time với cache
**Test name:** `test_response_time_with_cache`
**Steps:**
1. Measure response time lần 1 (cold cache)
2. Measure response time lần 2 (warm cache)
3. Assert lần 2 nhanh hơn đáng kể (< 50ms)

**Expected:**
- Cache giúp giảm response time
- Performance tốt

---

#### Test 7.2: Large namespace được handle
**Test name:** `test_large_namespace_handled`
**Steps:**
1. Load namespace có nhiều translations (>1000 keys)
2. Assert response time hợp lý (< 500ms)
3. Assert memory usage hợp lý

**Expected:**
- Large namespaces được handle tốt
- Không có memory leak

---

## Frontend Test Cases

### 1. API Client Tests

#### Test 1.1: getTranslations() với default options
**File:** `frontend/src/shared/api/__tests__/i18n.test.ts`
**Test name:** `test_get_translations_with_defaults`
**Steps:**
1. Mock API response
2. Call `getTranslations()`
3. Assert request URL đúng
4. Assert response được return đúng

**Expected:**
- API call đúng format
- Response được parse đúng

---

#### Test 1.2: getTranslations() với ETag
**Test name:** `test_get_translations_with_etag`
**Steps:**
1. Mock API response với ETag
2. Call `getTranslations()` với cachedEtag
3. Assert `If-None-Match` header được set
4. Mock 304 response
5. Assert NOT_MODIFIED error được throw đúng

**Expected:**
- ETag được gửi đúng
- 304 được handle đúng

---

### 2. I18nProvider Tests

#### Test 2.1: Load translations khi locale thay đổi
**File:** `frontend/src/app/providers/__tests__/I18nProvider.test.tsx`
**Test name:** `test_load_translations_on_locale_change`
**Steps:**
1. Render `I18nProvider` với locale='en'
2. Assert translations được load
3. Change locale to 'vi'
4. Assert translations được reload

**Expected:**
- Translations load đúng khi locale thay đổi
- Loading state được set đúng

---

#### Test 2.2: Function t() hoạt động đúng
**Test name:** `test_translation_function_works`
**Steps:**
1. Mock translations: `{app: {title: 'App Title'}}`
2. Render component sử dụng `useI18n()`
3. Call `t('app.title')`
4. Assert return 'App Title'

**Expected:**
- Translation function hoạt động đúng
- Nested keys được resolve đúng

---

#### Test 2.3: Parameter replacement hoạt động
**Test name:** `test_parameter_replacement_works`
**Steps:**
1. Mock translations: `{app: {welcome: 'Hello {name}'}}`
2. Call `t('app.welcome', {name: 'John'})`
3. Assert return 'Hello John'

**Expected:**
- Parameters được replace đúng
- Multiple parameters hoạt động

---

#### Test 2.4: Fallback to key khi không tìm thấy
**Test name:** `test_fallback_to_key_when_not_found`
**Steps:**
1. Call `t('nonexistent.key')`
2. Assert return 'nonexistent.key'

**Expected:**
- Fallback về key khi không tìm thấy translation

---

## Test Implementation Checklist

### Backend Tests
- [ ] Tạo file `tests/Feature/Api/I18nTranslationsTest.php`
- [ ] Implement tất cả test cases trên
- [ ] Đảm bảo test coverage > 80%
- [ ] Test với cả locale 'en' và 'vi'
- [ ] Test với các namespaces khác nhau
- [ ] Test caching behavior
- [ ] Test security scenarios

### Frontend Tests
- [ ] Tạo file `frontend/src/shared/api/__tests__/i18n.test.ts`
- [ ] Tạo file `frontend/src/app/providers/__tests__/I18nProvider.test.tsx`
- [ ] Mock API calls đúng cách
- [ ] Test error handling
- [ ] Test loading states

## Báo cáo Test Results

Sau khi chạy tests, cần báo cáo:

1. **Test Results Summary:**
   - Tổng số tests: X
   - Passed: Y
   - Failed: Z
   - Coverage: XX%

2. **Failed Tests:**
   - Liệt kê các tests failed
   - Error messages
   - Root cause analysis

3. **Security Issues Found:**
   - Path traversal vulnerabilities
   - XSS risks
   - Authentication bypass
   - Rate limiting issues

4. **Performance Issues:**
   - Slow response times
   - Memory leaks
   - Cache không hoạt động

5. **Bugs Found:**
   - Description
   - Steps to reproduce
   - Expected vs Actual behavior
   - Severity (Critical/High/Medium/Low)

6. **Recommendations:**
   - Improvements needed
   - Additional test cases
   - Code changes required

## Notes cho Codex/Cursor

1. **Khi implement tests:**
   - Sử dụng Laravel testing helpers: `$this->get()`, `$this->assertJson()`, etc.
   - Mock external dependencies nếu cần
   - Clean up sau mỗi test (database, cache, files)

2. **Khi tests fail:**
   - Báo cáo chi tiết error message
   - Include stack trace nếu cần
   - Suggest fixes nếu có thể

3. **Khi tìm thấy bugs:**
   - Tạo issue description rõ ràng
   - Include code snippets
   - Suggest fix nếu có thể

4. **Khi tests pass:**
   - Confirm implementation đúng
   - Note any edge cases cần lưu ý
   - Suggest improvements nếu có

