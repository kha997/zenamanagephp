---
work_id: GAP-125
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_correction_or_defer"
references:
  spec: docs/audits/2026-11-11-gap-125-mobile-field-pages-evidence.md
  plan: docs/superpowers/plans/2026-11-11-gap-125-mobile-field-pages.md
  branch: docs/GAP-125-mobile-field-pages
  pr: https://github.com/kha997/zenamanagephp/pull/416
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-11T14:10:11+07:00"
  updated_at: "2026-11-11T14:10:11+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-125 at subject 3515689c15ddc7291f44fe982fb6c185ae25fdda (Gate 2 C-1, D-1; one PR, implementation commit 3515689c; views, CSS and one test only). C-1: below 768px new partials site-diaries/_cards (date linking to the diary, status badge, project and code, work summary, workers, author, and the same submit / approve POST forms as the table by status), inspections/_cards (title linking to the inspection, status, QC plan, inspector, date) and inspections/_ncr-cards (whole card links to the NCR; number, title, severity, status, assignee); the tables are wrapped in operator-wide-only. D-1: on inspections/show the 'Thông tin' column gets operator-phone-first (order -1 below 768px); description, results, the conduct / complete form and the new-NCR form sit in <details open data-phone-collapse> with a phone-only summary; an inline script closes them below 768px (without JS they stay open); when the page returns with validation errors both forms render without data-phone-collapse and stay open. Tests: FieldPagesMobileLayoutTest (4) red before, green after. Playwright on the demo app: iPhone 13 — no table overflows on the three pages (diary table was 900 / 308 px, inspection table 515 / 308 px, NCR table 607 / 308 px), inspection detail 3,082 → 1,736 px with 'Thông tin' at the top, card buttons 47 px, section toggles 44 px; desktop 1440 px — the three pages are pixel-identical before and after. php-cs-fixer, ssot:lint, button inventory (no orphans), docs-lint, governance lint pass. Local Unit + Feature + Integration 3049 tests: only the 2 known root-only failures. Exact-head CI recorded in the body."
technical_evidence:
  base_sha: "c47261e36ad8e8f832a47521f2ac63618aa5877c"
  subject_sha: "3515689c15ddc7291f44fe982fb6c185ae25fdda"
  implementation_tree_digest: "a4948724b7561f2e5a0d66bfe77e16828799588bc54cb51ec3b356a0851f6b58"
  verified_pr_head_sha: "3515689c15ddc7291f44fe982fb6c185ae25fdda"
  verified_at: "2026-11-11T14:10:11+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-125 — Gate 3 release decision (phone layout for the field pages)

## Gói quyết định phát hành

**1. Vấn đề là gì?** Trên điện thoại:
- 3 bảng bị cắt: nhật ký công trường, kiểm định, NCR;
- trang chi tiết kiểm định dài gần 5 màn hình, trạng thái nằm tận dưới.

**2. Sau thay đổi (Gate 2 C-1, D-1)** — subject `3515689c`. Chỉ áp dụng dưới 768px.

**Thẻ thay bảng (C-1)**
- **Nhật ký công trường:**
  - thẻ có ngày (bấm để mở), trạng thái, dự án, tóm tắt công việc, số người, người lập;
  - nút **"Gửi duyệt"** (nhật ký nháp) hoặc **"Phê duyệt"** (đã gửi), cao 47px.
- **Kiểm định:** thẻ có tiêu đề (bấm để mở), trạng thái, kế hoạch QC, người kiểm định, ngày.
- **NCR** trong trang chi tiết: cả thẻ bấm được, gồm số, tiêu đề, mức độ, trạng thái, người được giao.

**Trang chi tiết kiểm định (D-1)**
- Khối **Thông tin** lên đầu.
- **Mô tả**, **Kết quả**, form **Hoàn tất** và form **Ghi nhận NCR mới** gập lại; bấm "Xem…" / "Mở form" để mở.
- Nếu form gửi lên bị lỗi, cả hai form tự mở sẵn để sửa.
- Trang ngắn từ **3.082px xuống 1.736px** (≈ 4,6 → 2,6 màn hình).

**Máy tính:** 3 trang **giống hệt từng điểm ảnh** trước và sau.

**3. Đã kiểm thử** — base `c47261e3`; subject `3515689c15ddc7291f44fe982fb6c185ae25fdda`; digest
`a4948724b7561f2e5a0d66bfe77e16828799588bc54cb51ec3b356a0851f6b58`.

**Đo trên iPhone 13**

| Trang | Trước | Sau |
|---|---|---|
| Nhật ký (danh sách) | Bảng 900px trong khung 308px | Thẻ, không tràn |
| Kiểm định (danh sách) | Bảng 515px / 308px | Thẻ, không tràn |
| Kiểm định (chi tiết) | 3.082px, bảng NCR 607px / 308px | 1.736px, Thông tin ở đầu, NCR dạng thẻ |

**Test và kiểm tra**
- Test mới (4) đỏ trước, xanh sau:
  - thẻ đủ thông tin và đúng nút theo trạng thái;
  - bảng máy tính vẫn còn;
  - các khối gập có đủ nội dung và form;
  - form mở sẵn khi có lỗi.
- Toàn bộ 3.049 test: chỉ còn 2 lỗi có sẵn do chạy bằng root.
- php-cs-fixer, SSOT lint, kiểm kê nút, lint đều sạch.

**4. Lưu ý vận hành** — Không có.

**5. Ngoài phạm vi**
- 4 trang không bị tràn.
- RFI, hồ sơ trình duyệt, mua sắm.
- Quy tắc, phân quyền.
- Deploy.

**6. Rủi ro còn lại** — Thấp. Chỉ giao diện; máy tính không đổi.

**7. Hoàn tác** — Revert PR.

**8. Đề xuất** — Phát hành (merge).

**9. CI trên đúng head** — ghi nhận trước khi merge.

## Decision Needed

Owner chọn: `APPROVE GAP-125 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy.
