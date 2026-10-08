---
work_id: GAP-073
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_correction_or_defer
references:
  spec: docs/audits/2026-10-08-gap-073-phpstan-2-3-evidence.md
  plan: docs/superpowers/plans/2026-10-08-gap-073-phpstan-2-3.md
  branch: docs/GAP-073-phpstan-2-3
  pr: https://github.com/kha997/zenamanagephp/pull/346
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-08T22:47:06+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-08T22:47:06+07:00"
  updated_at: "2026-10-08T22:47:06+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-073 at subject 5d9f0b80: phpstan/phpstan 2.2.5 -> 2.3.0 (lockfile moves only that package); the 10 PHPStan 2.3 findings fixed in place with no new suppressions (two fixed baseline entries removed). Red first: 10 errors on unchanged code. Disclosed deviation: the Gate-2 step 'drop unused $validator captures' was a PHPStan false positive and broke the settings PATCH endpoints at 9e385790 (CI API Tests (Fast): Undefined variable $validator); fixed at 5d9f0b80 by keeping the captures and typing the locked-user query (same query and lock), plus getAttribute('preferences'). PHPStan clean; settings API tests 14 passed; Unit 894 and Feature+Integration 1774 passed locally with one root-only failure each (also on main as root); lints pass; exact-head PR checks 34/34 green."
technical_evidence:
  base_sha: "af7d5968078d2e50caeba38a5cb8d62261e7e6cd"
  subject_sha: "5d9f0b80799bbc56d2d2784e6e38186c99e5c2ca"
  implementation_tree_digest: "807770bf9df7009062548161eccfe7b77613c54509774ac16e8bf9376c479293"
  verified_pr_head_sha: "5d9f0b80799bbc56d2d2784e6e38186c99e5c2ca"
  verified_at: "2026-10-08T22:47:06+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-073 — Gate 3 release decision (PHPStan 2.3 và 10 lỗi)

## Gói quyết định phát hành

**1. Vấn đề là gì?** PHPStan 2.3 báo 10 lỗi ở code có sẵn nên chưa nâng được;
có bug thật đang bị ẩn trong baseline (Gate 1).

**2. Sau thay đổi (Gate 2, Phương án A):**

- `phpstan/phpstan` 2.2.5 → 2.3.0 (chỉ gói này đổi trong `composer.lock`).
- `BasicSidebarController`: thêm truy vấn `$dbConfig` bị thiếu (theo mẫu
  `SimpleSidebarBuilderController`).
- `UpdateInteractionLogRequest`: closure dùng `use ($interactionLog)`; giá trị
  route chỉ dùng khi đúng là `InteractionLog`.
- `HealthCheckService`: `setex(key, 60, value)` thay cho `set(key, value, 'EX', 60)`.
- `SettingsController`: xem mục 3.
- Baseline: bỏ 2 dòng đã sửa; **không** thêm dòng hay `@phpstan-ignore` nào.

**3. Khác biệt so với Gate 2 (công khai)**

- **Sự cố đã sửa:** Gate 2 ghi "bỏ `$validator` thừa trong 3 closure". Lỗi đó
  là **báo sai** của PHPStan: không có Larastan, PHPStan coi
  `User::query()->…->lockForUpdate()->first()` trả về `stdClass|null`, nên cho
  rằng phần sau `instanceof User` không bao giờ chạy. Bỏ `$validator` (commit
  `9e38579`) đã làm hỏng 3 API cập nhật cài đặt; CI "API Tests (Fast)" bắt
  được (`Undefined variable $validator`). Đã sửa ở `5d9f0b8`: giữ `$validator`,
  tách query ra biến để PHPStan hiểu đúng kiểu `User|null` (cùng truy vấn, cùng
  khoá bản ghi), đọc `preferences` bằng `getAttribute`.
- Sửa thêm trong các file đã duyệt để PHPStan không báo lỗi kiểu mới:
  `SidebarConfig::query()->where`, `getAttribute('config')`, kiểm tra kiểu
  `InteractionLog`.

**4. Bằng chứng kỹ thuật**

- Base `af7d5968`; subject `5d9f0b80799bbc56d2d2784e6e38186c99e5c2ca`; digest
  `807770bf9df7009062548161eccfe7b77613c54509774ac16e8bf9376c479293`.
- **Đỏ trước:** PHPStan 2.3 trên code cũ: 10 lỗi. Sự cố `$validator`: CI đỏ ở
  `9e38579`, xanh ở `5d9f0b8`.
- PHPStan 2.3 sạch; 14 test API cài đặt đạt; Unit 894 đạt, Feature + Integration
  1774 đạt (mỗi bộ 1 lỗi chỉ do chạy bằng root, cũng lỗi trên main).
- Lint SSOT, governance, docs, baseline guard; `composer validate` đạt.
- CI exact head `5d9f0b8`: 34/34 pass. Gói cũ theo bot: 15 → 14.

**5. Ngoài phạm vi** — Xoá các class không được route nào dùng; các lỗi khác
trong baseline; bản nâng phiên bản chính; deploy.

**6. Rủi ro còn lại** — Thấp. Code đang chạy thật chỉ đổi ở `SettingsController`
(cùng truy vấn/khoá, test bao phủ) và `HealthCheckService` (lệnh tương đương).

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-073 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy.
