---
work_id: GAP-035
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: null
  plan: null
  branch: docs/GAP-035-route-name-collision-gate1-prep
  pr: https://github.com/kha997/zenamanagephp/pull/261
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-14T07:42:39+07:00"
  owner_response_reference: "Owner Gate 1 decision — APPROVE WITH BINDING SCOPE CLARIFICATIONS, recorded in-session on 2026-08-14 at actual wall-clock time 2026-08-14T07:42:39+07:00. Approved problem statement: php artisan route:cache is blocked on clean current main by duplicate Laravel route names; GAP-035 is authorized to design a routing/deployability remediation for the duplicate-name condition only, without changing business behavior. Confirmed 7 application-level duplicate-name groups: projects.store, projects.show, projects.update, projects.destroy, tasks.store, api.v1.dashboard., api.zena. Binding scope: GAP-035 may change route names and add missing explicit route names only as necessary to eliminate collisions and make route:cache succeed. For every affected route, GAP-035 must preserve HTTP method, URI, middleware, handler/controller/closure, tenant/RBAC/security behavior, and request/response business behavior. No merging, deleting, redirecting, or consolidating HTTP route surfaces is authorized — in particular, do not merge the bare /projects* closures with Api\\ProjectController, do not merge Web\\TaskController with Api\\TaskController, do not change Project or Task lifecycle/business semantics, do not alter Service Line semantics, do not use GAP-035 to clean up legacy Project architecture. The phrase in the original Gate 1 packet suggesting HTTP paths might be merged 'if needed' is explicitly non-authoritative and is superseded by this clarification. Named-route compatibility rule: Gate 2 must distinguish a real Laravel named-route consumer (route(), redirect()->route(), to_route(), URL::route(), equivalent framework named-route lookup, or a test/helper that ultimately performs one) from a mere coincidental string such as a permission-map key; PermissionService string keys are not, by themselves, route-name consumers and must not constrain the naming design unless an actual route-name lookup is independently proven; for each collision, Gate 2 must record which current route wins named-route resolution today and preserve the behavior of any verified real consumer unless an explicit migration is designed and tested. Complete inventory requirement: before drafting Gate 2, re-run the duplicate-name inventory without --except-vendor under both APP_ENV=testing and APP_ENV=production to prove the 7 application-level groups are the complete blocker set for the route collection route:cache actually serializes; report any additional package/vendor collisions found without modifying vendor/package code or silently expanding scope. Design Dependency Preflight: the preflight already performed against PR #257 is accepted provisionally; re-confirm live before Gate 2 that PR #257 remains pinned at ded7cf9f558bd7960b5eff5836140b1e15255b9a — if unchanged, no redundant full reread is required, but Gate 2 must explicitly carry forward: one canonical Project, no alternate Project semantics, no Service Line changes, no lifecycle changes, route-name remediation only; if the pinned head has moved, rerun the full preflight against the new head before Gate 2. Do not implement yet. Do not rename any route before Gate 2 approval. Do not touch GAP-011. Do not mark PR ready. Do not merge or deploy. Do not self-approve Gate 2."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-14T00:36:00+07:00"
  updated_at: "2026-08-14T07:42:39+07:00"
generated_by: agent
---

## Gate 1 approval addendum (added at approval time, does not alter the evidence below)

- **Complete inventory re-verification (Owner-required before Gate 2):** re-ran the duplicate-name scan via `route:list --json` **without** `--except-vendor`, under both `APP_ENV=testing` and `APP_ENV=production`. Route counts were identical to the original `--except-vendor` scan (testing: 1186 routes / 791 named / 7 duplicate groups; production: 1164 / 776 / 7) — no vendor/package route contributed a name collision. The same 7 application-level groups are confirmed as the complete blocker set for the route collection `route:cache` actually serializes.
- **Design Dependency Preflight re-confirmed live:** PR #257 is still pinned at exactly `ded7cf9f558bd7960b5eff5836140b1e15255b9a` (checked live at approval time via `gh pr view 257`) — unchanged since the original preflight, so no redundant full reread was performed, per the Owner's instruction.
- **Evidence correction found while preparing Gate 2 (transparency note, not a rewrite of the table below):** the original evidence table's declaration-site column for the `projects.*`/`tasks.store` groups needs one precision correction, made explicitly in Gate 2 rather than edited here: the API-side route in each of these 5 pairs is generated by `Route::apiResource('projects', ...)` at `routes/api.php:268` and `Route::apiResource('tasks', ...)` at `routes/api.php:582` — **not** by `routes/api_zena.php`'s own `projects.*`/`tasks.store` declarations, which are actually named `api.zena.projects.*`/`api.zena.tasks.store` (inheriting the `api.zena.` group prefix) and do **not** collide with anything. Consequently, the `$this->zena('projects.store')`-style test calls originally cited as consumers of the bare colliding name actually resolve to the non-colliding `api.zena.projects.store` name and are not real consumers of the collision. Gate 2's route-by-route matrix carries the corrected declaration sites and consumer list.

## Owner Summary

Nhiều route trong hệ thống bị đặt trùng tên đăng ký nội bộ (route name) — không phải trùng URL, mà trùng "tên gọi" mà Laravel dùng để tối ưu hoá route khi triển khai production. Vì trùng tên, lệnh tối ưu hoá route (`php artisan route:cache`) — một bước bắt buộc trong quy trình triển khai production thật — không chạy được, ở bất kỳ môi trường nào. Phát hiện trong lúc xác minh GAP-011, hoàn toàn không liên quan tới GAP-011.

## Vấn đề vận hành

`php artisan route:cache` — lệnh Laravel dùng để biên dịch sẵn toàn bộ bảng route, giúp production khởi động nhanh hơn và là bước bắt buộc trong quy trình triển khai production hiện tại (`deploy-production.sh`, chuỗi `git pull → composer/npm → migrate → config:cache → route:cache → view:cache`) — thất bại ngay lập tức với lỗi:

```
LogicException: Unable to prepare route [projects] for serialization.
Another route has already been assigned name [projects.store].
```

Đây không phải lỗi ngẫu nhiên hay tạm thời — nó tái hiện giống hệt trên `main` sạch (chưa có bất kỳ sửa đổi nào), dưới cả `APP_ENV=testing` lẫn `APP_ENV=production`. Khi điều tra sâu hơn (không sửa code, chỉ đọc `route:list --json` và so khớp tên), phát hiện đây **không phải một lỗi đơn lẻ** — có **7 nhóm tên route bị trùng** trong toàn repo. `route:cache` chỉ báo lỗi ở nhóm đầu tiên nó gặp (`projects.store`) rồi dừng lại ngay; 6 nhóm còn lại là "quả bom nổ chậm" — nếu chỉ sửa nhóm đầu tiên rồi chạy lại, `route:cache` sẽ dừng ở nhóm thứ hai, rồi thứ ba, v.v. Gate 1 này xây dựng đầy đủ cả 7 nhóm cùng lúc để Gate 2 không phải sửa từng cái một qua nhiều vòng.

## Người dùng bị ảnh hưởng

- **Đội vận hành/triển khai:** không thể hoàn tất quy trình triển khai production đầy đủ như tài liệu mô tả — bước `route:cache` trong `deploy-production.sh` sẽ luôn thất bại ở bước này cho tới khi lỗi được xử lý.
- **Không có bằng chứng ảnh hưởng người dùng cuối trực tiếp:** ứng dụng vẫn chạy bình thường khi route CHƯA cache (hành vi mặc định hiện tại của mọi môi trường, kể cả production thật lúc này, vì bước cache đang thất bại) — route trùng tên vẫn resolve được (xem bằng chứng bên dưới), chỉ riêng bước "biên dịch sẵn" là không chạy được.
- **GAP-011 (Work ID khác, đã hoàn tất, đang chờ):** không thể xác minh "bảng route production sau khi cache không còn route `_debug/*`" vì đúng lệnh `route:cache` này đang hỏng. GAP-011 Gate 3 hiện ở trạng thái `blocked_technical`, chờ GAP-035.

## Bằng chứng

Baseline: `origin/main` tại `1024b68640c2aeddc924620ef7be2885339fecec` (SHA sạch, chưa có sửa đổi nào của GAP-011 hay GAP-035). Phương pháp: `php artisan route:list --json --except-vendor` dưới từng `APP_ENV`, so khớp trường `name` giữa các route để tìm trùng lặp — không sửa bất kỳ file nào, không chạy `route:cache` thật ngoài đúng 2 lần để lấy nguyên văn exception rồi `route:clear` ngay.

### Tái hiện lỗi gốc

- `APP_ENV=testing php artisan route:cache` → `LogicException: Unable to prepare route [projects] for serialization. Another route has already been assigned name [projects.store].`
- `APP_ENV=production php artisan route:cache` → cùng hệt exception, cùng dòng file/line (`AbstractRouteCollection.php:257`).
- Cả hai lần đều tái hiện trên **đúng SHA `1024b68640c2aeddc924620ef7be2885339fecec`**, không có thay đổi code nào.

### Danh sách đầy đủ 7 nhóm tên route trùng (deterministic, từ `route:list --json`, giống hệt nhau ở cả `testing` và `production`)

| # | Tên trùng | Số route | Vị trí khai báo | Method + URI | Middleware chính | Handler |
|---|---|---|---|---|---|---|
| 1 | `projects.store` | 2 | `routes/api_zena.php:217` (trong `routes/api.php:1008` `require`) | `POST api/projects` | `auth:sanctum, tenant.isolation, input.sanitization, error.envelope, rbac:project.create` | `Api\ProjectController@store` |
| | | | `routes/web.php:513` (top-level, không nằm trong group `app.`) | `POST projects` | `auth, tenant.isolation, rbac:project.create` | Closure (tạo `Project` trực tiếp, trả JSON) |
| 2 | `projects.show` | 2 | `routes/api_zena.php:219` | `GET\|HEAD api/projects/{project}` | như trên + `rbac:project.view` | `Api\ProjectController@show` |
| | | | `routes/web.php:519` (khu vực gần dòng 526, closure) | `GET\|HEAD projects/{project}` | `auth, tenant.isolation, rbac:project.view` | Closure (trả JSON id/name/description/status) |
| 3 | `projects.update` | 2 | `routes/api_zena.php:220` | `PUT\|PATCH api/projects/{project}` | như trên + `rbac:project.update` | `Api\ProjectController@update` |
| | | | `routes/web.php:527` (khu vực gần dòng 536, closure) | `PUT projects/{project}` | `auth, tenant.isolation, rbac:project.update` | Closure (update field, trả JSON) |
| 4 | `projects.destroy` | 2 | `routes/api_zena.php:221` | `DELETE api/projects/{project}` | như trên + `rbac:project.delete` | `Api\ProjectController@destroy` |
| | | | `routes/web.php:544` | `DELETE projects/{project}` | `auth, tenant.isolation, rbac:project.delete` | Closure (xoá, trả JSON) |
| 5 | `tasks.store` | 2 | `routes/api_zena.php:266` | `POST api/tasks` | `auth:sanctum, tenant.isolation, input.sanitization, error.envelope, rbac:task.create` | `Api\TaskController@store` |
| | | | `routes/web.php:560` (top-level, không nằm trong group `app.`) | `POST tasks` | `auth, tenant.isolation, rbac:task.create` | `Web\TaskController@store` |
| 6 | `api.v1.dashboard.` | 12 | `routes/api.php:840`+`927`+`940-953` (group `Route::prefix('v1')->as('api.v1.')` mở ở dòng 785, lồng `Route::prefix('dashboard')->as('dashboard.')` ở dòng 840, lồng tiếp `users-v2`/`tasks`/`assignments`/`users` — **không route con nào trong nhóm này tự gọi `->name()`**, nên tất cả 12 route cùng "thừa kế" đúng tên nhóm cha `api.v1.dashboard.` (có dấu chấm cuối, rỗng phần đuôi) | `GET/POST/PUT/DELETE api/v1/dashboard/users-v2*`, `api/v1/dashboard/tasks/{taskId}/assignments`, `api/v1/dashboard/assignments/{assignmentId}`, `api/v1/dashboard/users/{userId}/assignments*` | `auth:sanctum, tenant.isolation, rbac` (bị lặp 2 lần do lồng group) + `input.sanitization, error.envelope` (+ `production.security` cho nhóm `users-v2`) | `UserControllerV2@*`, `Api\TaskAssignmentController@*` |
| 7 | `api.zena.` | 5 | `routes/api_zena.php:78,89,110,131,173` (trong group `Route::group(['prefix' => 'zena', 'as' => 'api.zena.'], ...)` mở ở dòng 12, lồng `Route::middleware(['auth:sanctum', ...])->group()` ở dòng 66 — **5 closure debug/test không tự gọi `->name()`**, cùng thừa kế tên nhóm cha `api.zena.` | `GET api/zena/simple-test`, `/minimal-auth-test`, `/sanctum-auth-test`, `/me-test`, `/auth-test` | `auth:sanctum, tenant.isolation, input.sanitization, error.envelope` (+ `rbac:auth.test.*` cho vài route) | Closures debug/test, trả JSON tĩnh |

**Ghi chú quan trọng — nhóm #1-4 không phải chỉ là trùng tên đơn thuần, mà là hai bộ handler độc lập cùng làm một việc:** `routes/api_zena.php`'s `Api\ProjectController` (được xác nhận qua `route('projects.store')` thực sự resolve về `/api/projects`, tức route API thật) VÀ `routes/web.php`'s closure độc lập (tạo/sửa/xoá `Project` trực tiếp bằng code riêng, KHÔNG gọi qua `Api\ProjectController`). Cả hai bộ đều có bằng chứng đang được test trực tiếp (xem "Người tiêu dùng" bên dưới) — không phải một bên là code chết.

### Người tiêu dùng hiện tại của từng tên trùng

- **Qua route-name resolution (`route('...')` / helper test `$this->zena('...')`):** `tests/Feature/Api/SecurityTest.php`, `tests/Feature/Api/IntegrationTest.php`, `tests/Feature/Api/TaskApiTest.php` gọi `$this->zena('projects.store')`, `$this->zena('projects.show', [...])`, `$this->zena('tasks.store')` — xác minh bằng `php artisan tinker` rằng `route('projects.store')` hiện resolve đúng về `Api\ProjectController` (`/api/projects`), không phải closure trong `routes/web.php` — hành vi hiện tại đúng như mong đợi, nhưng phụ thuộc vào thứ tự đăng ký route nội bộ, không có gì đảm bảo giữ nguyên nếu thứ tự load file thay đổi.
- **Qua URI trực tiếp (bare `POST /projects`, không qua tên):** `tests/Feature/Buttons/ButtonCRUDTest.php` (2 chỗ), `tests/Feature/CsrfProtectionTest.php` (2 chỗ), `tests/Feature/SecurityFeaturesTest.php`, `tests/Feature/Buttons/ButtonAuthorizationTest.php` (2 chỗ) — gọi thẳng `$this->post('/projects', [...])`, tức đang thực sự test closure trong `routes/web.php`, không phải `Api\ProjectController`.
- **Qua URI trực tiếp (bare `POST /tasks`):** `tests/Feature/CsrfProtectionTest.php`, `tests/Feature/Legacy/LegacyTaskCreationPersistsTest.php`.
- **`app/Services/PermissionService.php`:** dùng chuỗi `'projects.show'`, `'projects.update'`, `'projects.destroy'` làm KEY trong một mảng ánh xạ quyền — đây là chuỗi độc lập, không phải gọi `route()`, không bị ảnh hưởng bởi việc đổi tên route, nhưng cần lưu ý để không nhầm lẫn khi đối chiếu.
- **Nhóm #6 (`api.v1.dashboard.`) và #7 (`api.zena.`):** không tìm thấy bất kỳ chỗ nào trong `app/`, `resources/`, `tests/` gọi `route('api.v1.dashboard.')` hay `route('api.zena.')` — hợp lý vì tên có dấu chấm cuối/rỗng gần như không thể dùng làm tham chiếu tên có ý nghĩa. Không có bằng chứng consumer nào phụ thuộc vào chính CHUỖI TÊN của 2 nhóm này; các route vẫn hoạt động bình thường qua URI trực tiếp.
- **Blade/JS:** không tìm thấy `<a href>`, `fetch()`, hay `action=` nào trỏ tới bare `/projects` hoặc `/tasks` trong `resources/views/` — các consumer thật sự đều nằm trong test suite, không phải UI đang render.
- **Tài liệu:** không tìm thấy tài liệu nào (`docs/`) mô tả các route trùng tên này như một API hợp đồng chính thức.

### Chỉ là trùng tên, hay còn trùng hành vi nghiệp vụ?

- Nhóm #1-4 (`projects.*`): **trùng cả tên lẫn có chồng lấn hành vi nghiệp vụ thật** — hai đường tạo/sửa/xoá `Project` độc lập, không dùng chung service layer. Đây là phát hiện quan trọng cho Gate 2 (không tự ý xử lý ở Gate 1 này).
- Nhóm #5 (`tasks.store`): tương tự — hai controller khác nhau (`Api\TaskController` và `Web\TaskController`) cùng tạo `Task`.
- Nhóm #6, #7: **chỉ trùng tên do thiếu `->name()` ở route con trong group đặt tên**, không có chồng lấn hành vi — các route bên trong đã khác URI/handler nhau rõ ràng, chỉ là cái "tên" (định danh nội bộ) bị rỗng/trùng.

### Tác động triển khai (deployment impact)

`deploy-production.sh` (dòng 143-160, workflow triển khai production thật của repo này) chạy chuỗi bắt buộc `migrate → config:cache → route:cache → view:cache` bên trong container đang chạy. Bước `route:cache` hiện đang thất bại — nghĩa là quy trình triển khai production như tài liệu hóa hiện tại không thể hoàn tất đúng như thiết kế, độc lập với bất kỳ Work ID nào khác. Rủi ro nghiệp vụ trực tiếp: **thấp trong ngắn hạn** (ứng dụng vẫn chạy tốt không cache route — hành vi hiện tại của production thật), nhưng **là một khoảng trống hạ tầng triển khai có thật, cần đóng lại**.

## Tác động nếu không xử lý

- Quy trình triển khai production không bao giờ hoàn tất đúng như tài liệu (`deploy-production.sh`) mô tả — bước cache route luôn thất bại.
- GAP-011 (đã hoàn tất, đang chờ) không thể đóng Gate 3 vì không xác minh được hành vi production đã cache.
- Bất kỳ Work ID nào khác trong tương lai cũng sẽ gặp đúng vấn đề này nếu cần xác minh route table đã cache.
- Rủi ro âm thầm về đúng route nào đang thực sự phục vụ request (`projects.*`, `tasks.store`) vẫn tồn tại — hiện tại route API "thắng" theo `route()` resolution, nhưng đây là hành vi phụ thuộc thứ tự đăng ký, không được đảm bảo bởi hợp đồng rõ ràng nào.

## Phạm vi đề xuất

Gate 2 (sau khi Gate 1 được duyệt) sẽ thiết kế cách đặt lại tên cho cả 7 nhóm route trùng ở trên — sao cho `php artisan route:cache` chạy thành công, không route nào bị mất tên hiện có mà consumer thật đang phụ thuộc (test suite, `PermissionService` key mapping), và bảo toàn đúng handler nào đang thực sự phục vụ từng URI như hiện tại (không đổi hành vi runtime). Đây là một bản sửa **routing/deployability**, KHÔNG phải cơ hội để hợp nhất hai bộ handler `projects.*` chồng lấn thành một — việc đó (nếu cần) sẽ là quyết định nghiệp vụ riêng, ngoài phạm vi GAP-035.

## Loại trừ rõ ràng

- **Không đổi URI, middleware, hay handler của bất kỳ route nào** ở Gate 1 này — Gate 1 chỉ là bằng chứng/phạm vi, không sửa code.
- **Không hợp nhất hai bộ `Api\ProjectController` và closure trong `routes/web.php`** thành một — đó là quyết định nghiệp vụ riêng (có chồng lấn hành vi thật, không tự ý xử lý dưới GAP-035).
- **Không đổi bất kỳ hành vi nghiệp vụ, vòng đời (lifecycle), hay ngữ nghĩa nào của Project** — đã xác minh qua Design Dependency Preflight (xem bên dưới) rằng 3 tài liệu thiết kế đang pin ở PR #257 hoàn toàn không đề cập route hay `ProjectController`/`TaskController`; GAP-035 chỉ là sửa tầng định danh route nội bộ.
- **Không đổi Service Line semantics** — không liên quan.
- **Không đụng tới GAP-011** — GAP-011 đã hoàn tất, đang chờ (branch/PR riêng, `docs/GAP-011-debug-route-cleanup-gate1-prep` / PR #260), không sửa gì trong nhánh đó.
- **Không xử lý phát hiện Redis không liên quan** (`Illuminate\Cache\RedisStore::publish()` là `\Error` thoát khỏi `catch (\Exception)` trong `DashboardApiTest`/`DashboardRealTimeService`) — ghi nhận riêng, không gộp vào GAP-035, không cấp Work ID mới cho phát hiện này ở thời điểm này.

## Design Dependency Preflight (đã chạy, vì GAP-035 đụng tới route `Project`)

Vì 4 trong 7 nhóm trùng tên liên quan tới `Project`/`Task`, đã đọc 3 tài liệu thiết kế đang pin tại PR #257 (Draft, head `ded7cf9f558bd7960b5eff5836140b1e15255b9a`, chỉ đọc, không sửa):

1. `docs/superpowers/specs/2026-08-12-zena-one-page-management-control-tower-design.md`
2. `docs/superpowers/specs/2026-08-12-zena-contract-finance-one-page-control-design.md`
3. `docs/superpowers/specs/2026-08-12-zena-service-line-taxonomy-design.md`

**Kết quả:** cả 3 tài liệu không hề nhắc tới route, `ProjectController`, hay `TaskController` (xác minh bằng grep toàn văn, 0 kết quả). Nguyên tắc xuyên suốt cả 3 tài liệu là **"one canonical Project, one shared Project platform"** (dòng 23, 715, 777 của tài liệu #1) — GAP-035 không vi phạm nguyên tắc này vì không đổi model, không đổi semantics, không đổi lifecycle — chỉ đổi tên định danh route nội bộ và (nếu cần) hợp nhất đường dẫn HTTP trùng lặp mà KHÔNG đổi controller nào đang thực sự phục vụ request thật hiện tại.

## Đề xuất

Đội kỹ thuật đề xuất: xử lý GAP-035 như một work item riêng, ưu tiên trung bình — không khẩn cấp cho người dùng cuối (production vẫn chạy được), nhưng là một khoảng trống hạ tầng triển khai có thật cần đóng lại trước khi bất kỳ Work ID nào khác (bao gồm GAP-011) cần xác minh route table đã cache.

## Decision Needed

Owner chọn một trong: Approve to proceed to design (Gate 2) / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Không được yêu cầu quyết định giải pháp kỹ thuật cụ thể (đổi tên thế nào, có hợp nhất 2 bộ handler `projects.*` hay không) — đó là quyết định Gate 2 sau khi có thiết kế chi tiết. Cũng không được yêu cầu quyết định gì về GAP-011 (đã hoàn tất, đang chờ riêng) hay về phát hiện Redis (ghi nhận riêng, chưa cấp Work ID) — cả hai đều ngoài phạm vi quyết định này.
