---
work_id: GAP-118
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
  spec: docs/audits/2026-11-10-gap-118-mobile-my-work-timesheet-evidence.md
  plan: docs/superpowers/plans/2026-11-10-gap-118-mobile-my-work-timesheet.md
  branch: docs/GAP-118-mobile-my-work-timesheet
  pr: https://github.com/kha997/zenamanagephp/pull/408
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
  created_at: "2026-11-10T09:10:11+07:00"
  updated_at: "2026-11-10T09:10:11+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-118 at subject 6518078a88cb8d2f1018972ccc9f19ba82c67d00 (Gate 2 N-1, H-1, C-1, T-1, R-2, V-1; exact allowlist, no amendment; one PR, one implementation commit; views, CSS and one test file only, no PHP runtime code, no migration). N-1/H-1: layouts/operator.blade.php adds a native <details class=operator-mobile-nav> '☰ Menu' in the top bar (same OperatorNavigationComposer navigation, search, full name, logout) and the user's initials; operator.css hides the sidebar and the long top-bar parts at ≤1024px and hides the mobile menu above; a small vanilla script closes the menu on link click; header.operator-topbar class kept. C-1/T-1: new partials app/_workload-items-cards (task / design-item cards, Start form on tasks) and time/_running-bar (fixed bottom bar with Stop, ≤1024px); my-work and timesheet show cards below 768px and the unchanged tables from 768px; the desktop running banner and 'Ghi giờ' card stay on wide screens. Tests: tests/Feature/Mobile/OperatorMobileLayoutTest (4) red before, green after; MyWorkPageTest, TimeTracking and the Alpine guard green. Playwright (iPhone 13, 390px): content starts at 96px (was 1,050px), top bar 64px, no horizontal scroll, no overflowing table, cards visible, Start / Stop buttons 47px. Desktop 1440px: screenshots of both pages pixel-identical before and after. npm run build, php-cs-fixer, ssot:lint, governance lint pass. Local Unit + Feature + Integration 3013 tests: only the 2 known root-only failures; Feature/Api 825 green (the PDF-export tests pass in this container now that node_modules is installed). Exact-head CI recorded in the body."
technical_evidence:
  base_sha: "153709a8ebf451e49c9b3283739ac91c31be222b"
  subject_sha: "6518078a88cb8d2f1018972ccc9f19ba82c67d00"
  implementation_tree_digest: "259a575fc42e9738cc3c68890309e1647f67e4ba71524a11dd367e3f2610a17e"
  verified_pr_head_sha: "6518078a88cb8d2f1018972ccc9f19ba82c67d00"
  verified_at: "2026-11-10T09:10:11+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-118 — Gate 3 release decision (phone layout for My Work and Timesheet)

## Gói quyết định phát hành

**1. Vấn đề là gì?**
- Trên điện thoại, toàn bộ menu nằm trên đầu trang, nên màn hình đầu tiên chỉ toàn menu.
- Bảng giờ bị cắt cột.
- Nút bấm giờ nằm tận cuối trang.

**2. Sau thay đổi (Gate 2 N-1, H-1, C-1, T-1, R-2, V-1)** — subject `6518078a`.

Trên màn hình hẹp:
- **Menu:** thu vào nút **"☰ Menu"**. Bấm mở ra đầy đủ menu, kèm ô tìm kiếm, tên người dùng và "Đăng xuất".
- **Thanh trên cùng:** một dòng gồm nút Menu, tên trang và chữ cái đầu tên người dùng.
- **"Việc của tôi":** mỗi việc là một thẻ, có nút **"▶ Bắt đầu ghi giờ"** to, dễ bấm.
- **"Bảng giờ":** mỗi dòng giờ là một thẻ đủ thông tin, có "Sửa / xoá" như trước.
- **Khi đồng hồ chạy:** thanh **"Đang ghi giờ … · ■ Dừng"** luôn ở đáy màn hình trên cả hai trang.

**Máy tính: không đổi gì.** Ảnh chụp trước và sau giống hệt từng điểm ảnh.

Trang "Duyệt giờ" của PM để sau (R-2), nhưng vẫn có menu và thanh trên cùng mới.

**3. Đã kiểm thử** — base `153709a8`; subject `6518078a88cb8d2f1018972ccc9f19ba82c67d00`; digest
`259a575fc42e9738cc3c68890309e1647f67e4ba71524a11dd367e3f2610a17e`.
- 4 test mới **đỏ** trước, **xanh** sau.
- Test có sẵn của hai trang và guard "không Alpine" vẫn xanh.
- **Đo trên trình duyệt giả lập iPhone 13 (390 px):**

| Chỉ số | Trước | Sau |
| --- | --- | --- |
| Nội dung bắt đầu từ | 1.050 px | **96 px** |
| Thanh trên cùng | ~120 px | **64 px** |
| Bảng bị tràn | 1 (bảng giờ) | **0** |
| Cuộn ngang cả trang | không | không |
| Nút Bắt đầu / Dừng | nút nhỏ cuối trang | **47 px**, trên từng thẻ / ở đáy |

- **Máy tính (1440 px):** ảnh hai trang trước và sau **giống hệt**.
- `npm run build`, php-cs-fixer, SSOT lint, governance lint sạch.

**4. Lưu ý vận hành**
- Phải build lại tài nguyên giao diện (`npm run build`) khi triển khai, như mọi lần đổi CSS.
- Không migration, không đổi dữ liệu.

**5. Ngoài phạm vi**
- Trang "Duyệt giờ".
- Thanh tab ở đáy.
- Các trang khác, ngoài phần khung dùng chung.
- Ứng dụng riêng.
- Chạy không cần mạng.
- Deploy.

**6. Rủi ro còn lại** — Thấp.
- Khung giao diện dùng chung cho khoảng 106 trang. Trên màn hình ≤1024 px (gồm cả máy tính bảng), các trang đó giờ dùng nút
  Menu thay cho khối menu dài.
- Trên máy tính lớn hơn 1024 px, không trang nào đổi.

**7. Hoàn tác** — Revert PR rồi build lại tài nguyên.

**8. Đề xuất** — Phát hành (merge).

**9. Kết quả chạy toàn bộ**
- **Unit + Feature + Integration:** 3.013 test, chỉ còn 2 lỗi có sẵn do chạy bằng root (`GrandfatherLoaderTest`,
  `BackupStorageLocationTest`).
- **Feature/Api:** 825 test, tất cả xanh.
- Lần này máy đã cài bộ dựng giao diện (node_modules) nên các test xuất PDF cũng xanh.
- **CI trên đúng head:** ghi nhận trước khi merge.

## Decision Needed

Owner chọn: `APPROVE GAP-118 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy, phát hành production.
