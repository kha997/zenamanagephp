---
work_id: GAP-122
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
  spec: docs/audits/2026-11-11-gap-122-mobile-time-review-evidence.md
  plan: docs/superpowers/plans/2026-11-11-gap-122-mobile-time-review.md
  branch: docs/GAP-122-mobile-time-review
  pr: https://github.com/kha997/zenamanagephp/pull/412
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-11T00:25:32+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-11, verbatim: 'APPROVE GAP-122 Gate 3'. Given on the packet at PR head d6d80e397f1f8ed99408dbab4a99d9e8a050a229; bound to implementation subject 66eca693556038ca0c841ccbd90330e2645ec864 and implementation-tree digest d60a6ee105a54f429c281afaae8f277e1df5c1f43367ba5c510eaf135d955982 (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28, after exact-head CI is green; no deployment or release authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-11T00:22:14+07:00"
  updated_at: "2026-11-11T00:25:32+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-122 at subject 66eca693556038ca0c841ccbd90330e2645ec864 (Gate 2 L-1, A-1, N-1; one PR, implementation commit 66eca693; views, CSS and one test only, no controller, service, route or migration change). Below 768px 'Duyệt giờ' renders new partial time/_review-cards: cards grouped by person (name, entry count, total time), each card with date, task, project, duration in large type, note, 'Tự dừng' / 'Nhập tay' badges, an approve form and a reject form inside <details> with a required reason (max 500); a person with more than one entry gets an 'Duyệt cả N dòng của <tên>' form (a person with one entry already has the card's own button). All forms post to the existing app.time.review.act with exactly their ids[], action and week, sit outside the desktop table's form and carry no tick boxes. The desktop table and its bulk form are wrapped in operator-wide-only; the header week buttons are wide-only and a one-line phone week bar ('‹ Tuần trước', 'Tuần sau ›', 'Giờ của tôi') replaces them below 768px. New CSS classes operator-review-*. Tests: TimeReviewMobileLayoutTest (5) — the 3 layout tests red before, green after; the 2 behaviour guards (one entry approved per card form, reject without reason refused) green; TimeReviewTest green. Playwright on the demo app: iPhone 13 — no table rendered, no horizontal scroll, every action button 47 px tall (group button 68 px, two lines), approve and reject-with-reason clicked for real (one entry each, reason stored, reject without reason blocked by the browser); desktop 1440 px — screenshot pixel-identical to the pre-change one. php-cs-fixer, ssot:lint, docs-lint, governance lint pass. Local Unit + Feature + Integration 3035 tests: only the 2 known root-only failures; Feature/Api 825 green. Exact-head CI recorded in the body."
technical_evidence:
  base_sha: "41e87a441477db5c23938a86ad6a1cd8b8ecf5c0"
  subject_sha: "66eca693556038ca0c841ccbd90330e2645ec864"
  implementation_tree_digest: "d60a6ee105a54f429c281afaae8f277e1df5c1f43367ba5c510eaf135d955982"
  verified_pr_head_sha: "66eca693556038ca0c841ccbd90330e2645ec864"
  verified_at: "2026-11-11T00:22:14+07:00"
owner_decision_binding:
  implementation_tree_digest: "d60a6ee105a54f429c281afaae8f277e1df5c1f43367ba5c510eaf135d955982"
  decision_recorded_at: "2026-11-11T00:25:32+07:00"
---

# GAP-122 — Gate 3 release decision (phone layout for "Duyệt giờ")

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-11-11 ("APPROVE GAP-122 Gate 3"), bound to implementation subject
`66eca693556038ca0c841ccbd90330e2645ec864` and implementation-tree digest `d60a6ee105a54f429c281afaae8f277e1df5c1f43367ba5c510eaf135d955982`. No deployment or release is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Trên điện thoại, trang "Duyệt giờ" có 3 vấn đề:
- bảng bị cắt mất cột công trình, thời lượng và ghi chú;
- trang dài;
- mọi dòng được chọn sẵn, nên dễ duyệt hoặc từ chối nhầm.

**2. Sau thay đổi (Gate 2 L-1, A-1, N-1)** — subject `66eca693`. Chỉ áp dụng dưới 768px.

- **Thẻ gom theo người.** Đầu nhóm ghi tên, số dòng và **tổng giờ**. Mỗi thẻ có:
  - ngày, việc, công trình, ghi chú;
  - nhãn "Nhập tay" / "Tự dừng";
  - **thời lượng chữ lớn** bên phải.
- **Duyệt ngay trên thẻ.**
  - Nút "Duyệt" chỉ duyệt đúng dòng đó.
  - Bấm "Từ chối" thì mở ô **lý do (bắt buộc)** và nút "Xác nhận từ chối".
  - Người có từ 2 dòng trở lên có thêm nút **"Duyệt cả N dòng của <tên>"**.
  - Người chỉ có 1 dòng không có nút này, vì nút "Duyệt" của thẻ đã làm đúng việc đó.
- **Không còn ô chọn sẵn** trên điện thoại.
- **Thanh chọn tuần một hàng:** "‹ Tuần trước", "Tuần sau ›", "Giờ của tôi".

**Không đổi:**
- quy tắc duyệt, phân quyền, khoá tháng (các nút gửi vào đúng chức năng duyệt hiện có);
- bảng trên máy tính.

**3. Đã kiểm thử** — base `41e87a44`; subject `66eca693556038ca0c841ccbd90330e2645ec864`; digest
`d60a6ee105a54f429c281afaae8f277e1df5c1f43367ba5c510eaf135d955982`.

**Đo trên iPhone 13**

| | Trước | Sau |
|---|---|---|
| Bảng bị cắt | 492px trong khung 276px, mất 3 cột | Không còn bảng, mọi thông tin nằm trong thẻ |
| Ô chọn | 16px, chọn sẵn tất cả | Không có; nút trên từng thẻ cao 47px |
| Nút tuần | Mỗi nút xuống 2 dòng | Một hàng |

**Bấm thử thật trên điện thoại**
- "Duyệt" một thẻ: đúng 1 dòng được duyệt, hiện "Đã duyệt 1 dòng giờ".
- "Từ chối" khi chưa ghi lý do: bị chặn.
- "Từ chối" có lý do: đúng 1 dòng bị từ chối, lý do được lưu.

**Máy tính 1440px:** ảnh chụp **giống hệt từng điểm ảnh** so với trước khi sửa.

**Test tự động**
- Test mới (5): 3 test giao diện đỏ trước, xanh sau; 2 test về hành vi xanh.
- `TimeReviewTest` vẫn xanh.
- Toàn bộ 3.035 test: chỉ còn 2 lỗi có sẵn do chạy bằng root. API 825 test xanh.
- php-cs-fixer, SSOT lint, docs-lint, governance lint sạch.

**4. Lưu ý vận hành** — Không có.

**5. Ngoài phạm vi**
- Quy tắc duyệt.
- Bảng máy tính.
- Trang khác.
- Deploy.

**6. Rủi ro còn lại** — Thấp. Chỉ đổi giao diện.

**7. Hoàn tác** — Revert PR.

**8. Đề xuất** — Phát hành (merge).

**9. CI trên đúng head** — ghi nhận trước khi merge.

## Decision Needed

Owner chọn: `APPROVE GAP-122 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy, phát hành production.
