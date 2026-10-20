---
work_id: GAP-085
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-20-gap-085-tailwind-4-evidence.md
  plan: null
  branch: docs/GAP-085-tailwind-4
  pr: https://github.com/kha997/zenamanagephp/pull/358
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
  created_at: "2026-10-20T17:00:27+07:00"
  updated_at: "2026-10-20T17:00:27+07:00"
generated_by: agent
---

# GAP-085 — tailwindcss 4: Gate 2 design

## OWNER GATE 2: AWAITING OWNER

## Owner Summary

Mình đã dựng thử phương án A trên một bản sao tạm (chưa commit). Kết quả:

- **`npm audit`: 0 lỗ hổng.**
- **Không sửa file view nào.** Chỉ thêm một file CSS tương thích
  (`resources/css/tailwind-v3-compat.css`, khoảng 320 dòng) để tailwind 4 vẽ
  giống tailwind 3:
  - bảng màu bản 3;
  - shadow, blur, độ cao dòng chữ, ring, outline như bản 3;
  - màu viền và placeholder mặc định như bản 3;
  - gradient tính màu như bản 3;
  - nền mờ `bg-opacity-*` cho đúng các cặp đang dùng;
  - chặn 3 tên lớp kiểu Bootstrap (`w-100`, `col-6`, `col-12`) mà bản 4 sẽ
    hiểu nhầm thành lớp Tailwind.
- **Kiểm chứng tự động bằng trình duyệt (Chromium):** dựng từng tổ hợp lớp
  lấy từ view thật, 2.988 tổ hợp cộng 23 thẻ HTML. Mỗi tổ hợp được dựng với
  CSS bản 3 và bản 4 rồi so mọi thuộc tính style tính được và kích thước, vị
  trí phần tử.
  - Trạng thái thường: **0 khác biệt nhìn thấy được**.
  - Trạng thái hover/focus: **0 khác biệt nhìn thấy được**.
  - Những chỗ khác còn lại chỉ là cách viết khác cho cùng kết quả, ví dụ
    `translate` thay `transform`, `oklab` thay `rgba` cùng màu, hay lớp shadow
    trong suốt thừa. Mỗi loại đều được liệt kê.
- **Trình duyệt tối thiểu** cho trang Operator/Portal tăng lên Safari 16.4+,
  Chrome 111+, Firefox 128+ (yêu cầu của tailwind 4).

**Phương án B:** nhận giao diện mới của tailwind 4 và sửa view theo công cụ
nâng cấp, chỉ cho trang Operator/Portal. Giao diện sẽ thay đổi và cần duyệt
thiết kế. Không khuyến nghị.

Đề xuất **Phương án A**.

## Design: Option A (exact allowlist)

1. **`package.json` / `package-lock.json`**:
   - `tailwindcss` `^3.4.19` → `^4.3.3`;
   - add `@tailwindcss/postcss` `^4.3.3`;
   - remove `autoprefixer`, because v4 adds vendor prefixes itself.
2. **`postcss.config.js`**: the plugin becomes `@tailwindcss/postcss`.
3. **`tailwind.config.js`**: `require('@tailwindcss/forms')` becomes an ESM
   `import`, which v4's loader needs. Nothing else changes.
4. **`resources/css/operator.css`**:
   - `@tailwind base/components/utilities` is replaced by
     `@import "tailwindcss"`, `@import "./tailwind-v3-compat.css"` and
     `@config "../../tailwind.config.js"`;
   - the rest of the file is unchanged.
5. **New `resources/css/tailwind-v3-compat.css`**, holding exactly the
   compatibility rules listed in the summary.
6. **New `scripts/dev/tailwind-style-diff.mjs`** (dev tool, not run in CI),
   which reruns the comparison above. Usage:
   `node scripts/dev/tailwind-style-diff.mjs <old.css> <new.css>`.
   It reports differences that are not proven equivalent.
7. **Docs**: plan, this packet, 03-release, and the evidence update.

**Not changed:**
- `Dockerfile.prod`, whose node stage already copies `postcss.config.js` and
  `tailwind.config.js`;
- the Blade views;
- `vite.config.js`;
- `resources/css/tailwind.css`;
- `public/css/tailwind.css`;
- every CDN page.

## Verification

- `npm audit`: 0 findings. `npm run build` emits the same 10 manifest entries.
- Style comparison, base state and forced hover/focus/active state:
  - Input: the old and new `operator.css` over every static class combination in
    `resources/views` and `app/`, plus 23 HTML elements.
  - Result: zero differences that are not proven equivalent.
- Known limits:
  - Dynamic class names built at runtime by Blade expressions are covered only as
    single utilities.
  - `space-y-*` and `divide-*` change sibling selectors in v4. The margin goes on
    the other side of each gap, which looks the same except with hidden or
    wrapped children.
- Exact-head CI, including browser-tests (Dusk on the operator pages) and staging-smoke.

## Rollback

Revert the squash commit.

## Out of scope

- The CDN pages and `public/css/tailwind.css`.
- Any visual redesign.
- Moving the views to v4 class names.
- Deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-085 Gate 2 Option A` (khuyến nghị) / Option B /
Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
