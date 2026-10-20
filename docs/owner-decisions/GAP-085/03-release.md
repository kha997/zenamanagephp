---
work_id: GAP-085
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
  spec: docs/audits/2026-10-20-gap-085-tailwind-4-evidence.md
  plan: docs/superpowers/plans/2026-10-20-gap-085-tailwind-4.md
  branch: docs/GAP-085-tailwind-4
  pr: https://github.com/kha997/zenamanagephp/pull/358
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-20T17:52:55+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-10-20, verbatim: 'APPROVE GAP-085 Gate 3'. Given after the packet was presented at PR head caef6bea06281331db2e843a3ca650c5f8eb34a9 (implementation CI 34/34 green at 0c70af6b); bound to implementation subject 0c70af6bed3cd7f6cde30bf8b839834fce376b47 and implementation-tree digest abd1469d08d4918b7e101490bd7aeef8c99f32d5cec5d429795950be5b2d9775 (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-20T17:51:52+07:00"
  updated_at: "2026-10-20T17:52:55+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-085 at subject 0c70af6b. 2c237aba: tailwindcss 3.4.19 -> 4.3.3 with @tailwindcss/postcss 4.3.3, autoprefixer removed; postcss.config.js uses @tailwindcss/postcss; tailwind.config.js imports @tailwindcss/forms as ESM (otherwise unchanged); resources/css/operator.css swaps the three @tailwind directives for @import tailwindcss, @import ./tailwind-v3-compat.css and @config; new resources/css/tailwind-v3-compat.css (v3 hex palette, shadow-sm, blur-sm, ring and outline defaults, text line-heights, border/placeholder/cursor defaults, sRGB gradients for bg-gradient-to-r/br, the bg-opacity pairs and hover:bg-opacity-75 pairs used in markup, outline-none, one operator-button-primary hover ordering case, @source not inline for w-100/col-6/col-12). No Blade view changed. npm audit 7 -> 0 findings; build emits the same 10 manifest entries. 0c70af6b: scripts/dev/tailwind-style-diff.mjs (Chromium computed-style plus geometry comparison over every static class combination in resources/views and app/, plus 23 bare elements, at rest and with interaction states forced). Result base v3 build vs this branch: 2992 combinations, 0 unexplained differences in both modes; negative control v3 vs an uncompensated v4 build: 2833 unexplained (exit 1). Disclosed: Node prints MODULE_TYPELESS_PACKAGE_JSON for the now-ESM tailwind.config.js (warning only, build succeeds; adding \"type\": \"module\" was outside the allowlist). Exact-head CI 34/34 green at 0c70af6b, including browser-tests (Dusk) and staging-smoke; browser-tests took about 38 minutes (its workflow retries a failed Dusk attempt once; the final conclusion is success)."
technical_evidence:
  base_sha: "8bf36c4cfbc7defac9d9ed59965a83cbdecf3efe"
  subject_sha: "0c70af6bed3cd7f6cde30bf8b839834fce376b47"
  implementation_tree_digest: "abd1469d08d4918b7e101490bd7aeef8c99f32d5cec5d429795950be5b2d9775"
  verified_pr_head_sha: "0c70af6bed3cd7f6cde30bf8b839834fce376b47"
  verified_at: "2026-10-20T17:51:52+07:00"
owner_decision_binding:
  implementation_tree_digest: "abd1469d08d4918b7e101490bd7aeef8c99f32d5cec5d429795950be5b2d9775"
  decision_recorded_at: "2026-10-20T17:52:55+07:00"
---

# GAP-085 — Gate 3 release decision (tailwindcss 4)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-10-20 ("APPROVE GAP-085 Gate 3"), bound to implementation subject
`0c70af6bed3cd7f6cde30bf8b839834fce376b47` and implementation-tree digest `abd1469d08d4918b7e101490bd7aeef8c99f32d5cec5d429795950be5b2d9775`. No deployment is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Còn 7 lỗ hổng npm, tất cả nằm trong tailwind 3 (Gate 1).

**2. Sau thay đổi (Gate 2, Phương án A):**

| Commit | Thay đổi |
|---|---|
| `2c237aba` | tailwindcss 4.3.3 + `@tailwindcss/postcss`, bỏ `autoprefixer`; `operator.css` dùng `@import "tailwindcss"` cùng lớp tương thích `tailwind-v3-compat.css` |
| `0c70af6b` | công cụ so sánh `scripts/dev/tailwind-style-diff.mjs` |

**Không sửa file view nào.**

**3. Bằng chứng** — base `8bf36c4c`; subject `0c70af6bed3cd7f6cde30bf8b839834fce376b47`;
digest `abd1469d08d4918b7e101490bd7aeef8c99f32d5cec5d429795950be5b2d9775`.

- `npm audit`: **7 → 0** lỗ hổng. Build vẫn ra đủ 10 file.
- So sánh bằng trình duyệt Chromium, CSS bản 3 với bản nhánh này: 2.992 tổ
  hợp lớp lấy từ view thật, cộng 23 thẻ HTML.
  - Ở trạng thái thường và khi ép hover/focus: **0 khác biệt chưa giải thích**.
  - Thử ngược với bản tailwind 4 chưa có lớp tương thích, công cụ bắt được
    2.833 khác biệt, nên công cụ thật sự phát hiện được lỗi.
- CI exact head 34/34 xanh, gồm browser-tests (Dusk) và staging-smoke.

**4. Công khai**

- Khi build, Node in một cảnh báo `MODULE_TYPELESS_PACKAGE_JSON`. Lý do là
  `tailwind.config.js` giờ dùng `import`. Đây chỉ là cảnh báo và build vẫn
  chạy. Thêm `"type": "module"` để tắt nó nằm ngoài phạm vi được duyệt.
- browser-tests chạy khoảng 38 phút, lâu hơn thường lệ (khoảng 20 phút).
  Workflow này tự chạy lại một lần nếu Dusk lỗi. Kết quả cuối là xanh.
- Giới hạn của việc so sánh:
  - Lớp sinh động trong biểu thức Blade chỉ được so từng lớp riêng.
  - `space-y-*` và `divide-*` đặt lề ở phía bên kia khoảng cách. Nhìn giống
    nhau, trừ khi phần tử con bị ẩn hoặc xuống dòng.

**5. Ảnh hưởng**

- Trang Operator/Portal cần Safari 16.4+, Chrome 111+, Firefox 128+.
- Giao diện không đổi theo phép so sánh trên.
- Khi deploy: build lại image bằng `Dockerfile.prod`, tức là chạy lại
  `npm ci` và `npm run build`.

**6. Rủi ro còn lại** — Thấp:
- trình duyệt quá cũ sẽ không hiển thị đúng;
- những trường hợp nằm ngoài phạm vi so sánh nói ở mục 4.

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-085 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy; không đổi giao diện; không chuyển view sang tên lớp
tailwind 4.
