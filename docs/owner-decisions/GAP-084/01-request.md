---
work_id: GAP-084
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-19-gap-084-npm-vulnerabilities-evidence.md
  plan: null
  branch: docs/GAP-084-npm-vulnerabilities
  pr: https://github.com/kha997/zenamanagephp/pull/357
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-20T06:43:11+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-20, verbatim: 'APPROVE GAP-084 Gate 1'. Bound to reviewed Draft PR #357 head 307c0caa36ea0dc80aca828f10203621ae56536b, canonical base 2a01adeaeb854bafb081cc04b5541a45c78c2cde. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-20T06:41:42+07:00"
  updated_at: "2026-10-20T06:43:11+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-084 Gate 1 in-session on 2026-10-20 against Draft PR #357
head `307c0caa36ea0dc80aca828f10203621ae56536b`. This authorizes preparation of Gate 2 only.

## Owner Summary

`npm audit` báo 17 lỗ hổng: 12 high, 4 moderate, 1 low, không có critical.
Chi tiết ở `docs/audits/2026-10-19-gap-084-npm-vulnerabilities-evidence.md`.

- **Sửa được ngay, không đổi bản lớn:** chỉ cập nhật `package-lock.json`. Số lỗ
  hổng giảm từ 17 xuống 10 (high từ 12 xuống 6).
  - Có `axios`, là thư viện chạy trong trình duyệt của người dùng, nên quan
    trọng nhất.
- **10 lỗ hổng còn lại** đều nằm trong công cụ build/dev, không có trong code
  chạy ở trình duyệt. Muốn sửa phải nâng bản lớn:
  - **vite 4 → 8:** kéo theo `laravel-vite-plugin` 3. `Dockerfile.prod` phải đổi
    Node 18 → 22.
  - **tailwindcss 3 → 4:** cấu hình đổi kiểu mới, một số mặc định giao diện
    thay đổi. Cần kiểm tra bằng mắt vùng Operator.
  - Riêng `braces`, `micromatch`, `chokidar` (đi kèm tailwind 3) chưa có bản
    vá nào. Cách duy nhất để hết lỗ hổng là bỏ tailwind 3.

## Vấn đề vận hành

Thư viện có lỗ hổng đã biết; GAP-083 sẽ chặn nếu xuất hiện lỗ hổng critical.

## Người dùng bị ảnh hưởng

- Người dùng cuối: `axios` chạy trong trình duyệt.
- Người phát triển: các công cụ build.

## Phạm vi đề xuất

Gate 2 thiết kế các phương án:
- **A:** chỉ cập nhật trong phạm vi bản hiện tại.
- **B:** A cộng nâng vite 8 (và Node cho `Dockerfile.prod`).
- **C:** B cộng nâng tailwindcss 4.

Gate 2 cũng nêu cách kiểm chứng (build, so sánh CSS, kiểm tra giao diện).

## Loại trừ rõ ràng

Không đổi gói PHP; không đổi giao diện ngoài phần bắt buộc do nâng cấp; không
deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, nâng gói, merge, phát hành hay deploy.
