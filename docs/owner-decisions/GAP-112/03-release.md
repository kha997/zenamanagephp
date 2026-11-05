---
work_id: GAP-112
gate: 3
gate_status: approved
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-05-gap-112-time-logging-evidence.md
  plan: docs/superpowers/plans/2026-11-05-gap-112-time-logging.md
  branch: docs/GAP-112-time-logging
  pr: https://github.com/kha997/zenamanagephp/pull/402
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-05T15:45:20+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-11-05, verbatim: 'APPROVE GAP-112 Gate 3'. Given on the packet at PR head 43e630d1e651979fb07db36c8ee08c8bc59cee6e; bound to implementation subject d4e31530eff331700fbe4b75671a0c342ed91de6 and implementation-tree digest 567afb6add58f05ce263391b8d71e67961623646fa53c95414b7b635fb497028 (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment or release authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-05T15:15:45+07:00"
  updated_at: "2026-11-05T15:45:20+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-112 at subject d4e31530eff331700fbe4b75671a0c342ed91de6 (Gate 2 T-1, M-1, C-1, R-1, L-1, A-1, P-1; exact allowlist; one PR, one implementation commit). One migration creates time_entries (tenant, user, nullable project / task / category, work_date, started / ended, minutes, source, auto_stopped, note, status, review fields; FKs tenant, user, project; indexes user + date, project + date + status, user + status); no existing table changes; task hour fields untouched (A-1). TimeEntryService: start / stop under a lock on the user row, one running timer per person, starting another activity stops the running one, cap at the earlier of start + 10 h and 23:59 of the start day with auto_stopped flag (also applied lazily when the person next opens a page); manual entries need a reason, start / end (HH:MM, end after start) or minutes, not in the future, no overlap with timed entries of the day, day total within 24 h; fixed categories (C-1, 'other' needs a note); scope P-1 (task assignee, task_assignments user, project PM or project member via project_user_roles, same tenant); edit / delete own non-approved, non-running entries (a correction goes back to review); L-1 refuses any change dated in a closed payroll month; every change audited. TimeReviewService: queue per week within scope (project entries: project PM or project.update; non-project: payroll.manage; own entries only with payroll.close), approve / reject several entries in one locked transaction, rejection needs a reason, only submitted entries, L-1 lock, audit. TimeReportService: hours by project x person and person x project / category, approved vs pending, scope (payroll.manage whole company incl. non-project; project.update all projects; PM own projects), one grouped query each, CSV with formula guard. Pages: My work (start per task, running banner with Stop), timesheet (week, day totals, timer, manual form, edit / delete), review, report (+CSV); menu 'Bảng giờ'; review / report links shown only within scope. Routes under rbac:task.view; tenant-scoped lookups (404 across tenants); review / report 403 outside scope. No new permission, no new dependency, no money. Tests: TimeEntryTest 4, TimeReviewTest 3, TimeReportTest 2, red before (classes missing) and green after; the 9 also green with DB_CONNECTION=mysql on MySQL 8; migration up, down, up on MySQL 8; report query count constant with more entries. Navigation / architecture tests green. Unit + Feature + Integration 2961 tests with only the 2 known root-only failures; Feature/Api 824 green. PHPStan, php-cs-fixer, SSOT test lint, ssot:lint, route guard, deptrac pass. Exact-head CI pending at packet time."
technical_evidence:
  base_sha: "ef278c5c78961ff97f96f00406f76f3ae89bc76f"
  subject_sha: "d4e31530eff331700fbe4b75671a0c342ed91de6"
  implementation_tree_digest: "567afb6add58f05ce263391b8d71e67961623646fa53c95414b7b635fb497028"
  verified_pr_head_sha: "d4e31530eff331700fbe4b75671a0c342ed91de6"
  verified_at: "2026-11-05T15:15:45+07:00"
owner_decision_binding:
  implementation_tree_digest: "567afb6add58f05ce263391b8d71e67961623646fa53c95414b7b635fb497028"
  decision_recorded_at: "2026-11-05T15:45:20+07:00"
---

# GAP-112 — Gate 3 release decision (time logging per task)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-11-05 ("APPROVE GAP-112 Gate 3"), bound to implementation subject
`d4e31530eff331700fbe4b75671a0c342ed91de6` and implementation-tree digest `567afb6add58f05ce263391b8d71e67961623646fa53c95414b7b635fb497028`. No deployment or release is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Không biết mỗi người làm bao nhiêu giờ cho từng công trình, nên chưa tính được chi phí nhân công.

**2. Sau thay đổi (Gate 2 T-1, M-1, C-1, R-1, L-1, A-1, P-1)** — subject `d4e31530`:

**Ghi giờ**
- Trang **"Việc của tôi"**: mỗi công việc có nút **Bắt đầu**. Đồng hồ đang chạy hiện ở đầu trang kèm nút **Dừng**.
- Đồng hồ:
  - mỗi người một đồng hồ; bắt đầu việc khác thì đồng hồ cũ tự dừng;
  - quên tắt thì tự dừng ở mốc sớm hơn trong "10 giờ sau khi bắt đầu" và "23:59", đánh dấu **"Tự dừng"**.
- Trang **"Bảng giờ"** (menu mới):
  - xem theo tuần, tổng mỗi ngày;
  - bắt đầu cho việc hoặc cho **việc không thuộc công trình** (họp nội bộ, đào tạo, hành chính, kinh doanh / báo giá, chờ
    việc, khác);
  - **nhập tay**: bắt buộc lý do; không chồng giờ; không quá 24 giờ / ngày; không ghi ngày tương lai;
  - sửa / xoá dòng chưa duyệt (sửa xong thì chờ duyệt lại).
- Chỉ ghi giờ được cho việc mình được giao, hoặc việc của công trình mình là thành viên / PM.

**Duyệt**
- Trang **"Duyệt giờ"** theo tuần. Ai duyệt:
  - giờ của một công trình: PM của công trình đó hoặc người có `project.update`;
  - giờ không thuộc công trình: người có quyền lương.
- Duyệt / từ chối nhiều dòng một lần. Từ chối phải ghi lý do, dòng quay lại cho người ghi sửa.
- Không tự duyệt giờ của mình (trừ Owner).

**Khoá:** tháng đã chốt lương thì giờ trong tháng đó không thêm / sửa / xoá / duyệt được.

**Báo cáo giờ** (chỉ giờ, chưa có tiền)
- Theo công trình × người và theo người × công trình / loại việc; tách đã duyệt / chờ duyệt; xuất CSV.
- Ai xem được gì:
  - Owner / kế toán: toàn công ty;
  - người có `project.update`: mọi công trình;
  - PM: công trình của mình.

Không đổi ô giờ cũ của công việc, không thêm quyền / thư viện, không tính tiền. Có 1 migration tạo bảng `time_entries`.

**3. Đã kiểm thử** — base `ef278c5c`; subject `d4e31530eff331700fbe4b75671a0c342ed91de6`; digest `567afb6add58f05ce263391b8d71e67961623646fa53c95414b7b635fb497028`.
- **9 test mới**, hỏng khi chưa có mã, xanh khi có mã:
  - **ghi giờ (4):**
    - bắt đầu / dừng / đổi việc;
    - tự dừng ở 23:59 và ở 10 giờ, có cờ;
    - nhập tay: lý do, chồng giờ, giờ sai, 24 giờ / ngày, loại việc, ngày tương lai;
    - sửa / xoá trước khi duyệt;
    - phạm vi ghi giờ (được giao / thành viên / PM / công ty khác);
    - khoá tháng lương;
    - trang và nút;
  - **duyệt (3):**
    - phạm vi PM / `project.update` / quyền lương;
    - không tự duyệt (trừ Owner);
    - từ chối cần lý do, sửa xong chờ duyệt lại;
    - khoá tháng; trang;
  - **báo cáo (2):**
    - tổng giờ đúng, phạm vi theo quyền, CSV;
    - số truy vấn không tăng theo số dòng.
- **MySQL 8 thật:** migration lên / gỡ / lên được; 9 test mới xanh với MySQL.
- **Unit + Feature + Integration:** 2.961 test, chỉ 2 lỗi có sẵn do chạy bằng root. **Feature/Api:** 824 test xanh.
- PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac sạch.
- CI trên đúng head: đang chạy lúc lập hồ sơ.

**4. Lưu ý vận hành**
- Mỗi nhân viên cần **tài khoản đăng nhập**. Phần 3 cần thêm việc nối tài khoản với nhân sự ở trang Nhân sự để ghép giờ
  với lương.
- Tự dừng chạy khi người đó mở lại trang hoặc bấm giờ, không chạy nền.

**5. Ngoài phạm vi** — tính tiền (phần 3); chi phí chung / lời lỗ (phần 4); danh mục tuỳ chỉnh; ứng dụng điện thoại; theo
dõi màn hình; nhắc giờ; deploy.

**6. Rủi ro còn lại** — Thấp:
- chỉ thêm bảng và trang mới;
- không đụng số liệu cũ;
- quyền dùng lại quyền sẵn có.

**7. Hoàn tác** — revert PR. Migration `down()` xoá bảng `time_entries` (mất giờ đã ghi).

**8. Đề xuất** — Phát hành (merge).

## Decision Needed

Owner chọn: `APPROVE GAP-112 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy, phát hành production.
