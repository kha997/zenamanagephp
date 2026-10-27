---
work_id: GAP-085
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-20-gap-085-tailwind-4-evidence.md
  plan: null
  branch: docs/GAP-085-tailwind-4
  pr: https://github.com/kha997/zenamanagephp/pull/358
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-20T16:51:18+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-20, verbatim: 'APPROVE GAP-085 Gate 1'. Bound to reviewed Draft PR #358 head 087b83d35280a3d08b77651da4913de1bfbfbab1, canonical base 8bf36c4cfbc7defac9d9ed59965a83cbdecf3efe. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-20T16:49:11+07:00"
  updated_at: "2026-10-20T16:51:18+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-085 Gate 1 in-session on 2026-10-20 against Draft PR #358
head `087b83d35280a3d08b77651da4913de1bfbfbab1`. This authorizes preparation of Gate 2 only.

## Owner Summary

Nâng tailwindcss 3 → 4 để xoá **7 lỗ hổng npm còn lại** (5 high, không có
critical). Tất cả đều nằm trong tailwind 3. Chi tiết:
`docs/audits/2026-10-20-gap-085-tailwind-4-evidence.md`.

**Điểm khó:** dự án đang dùng Tailwind theo 3 cách khác nhau.

1. CSS build bằng Vite (`operator.css`): chỉ dùng cho layout Operator và
   Portal. Đây là phần duy nhất bị nâng.
2. 15 trang dùng Tailwind CDN bản 3.
3. 3 layout dùng file CSS Tailwind 3 dựng sẵn.

Công cụ nâng cấp chính thức tự sửa **107 file view**, đổi tên lớp như
`shadow-sm` thành `shadow-xs`, `rounded-sm` thành `rounded-xs`. Nhưng chỉ 15
file trong số đó thuộc trang Operator/Portal. 92 file còn lại là phần dùng
chung hoặc trang CDN/CSS 3. Áp nguyên kết quả của công cụ sẽ làm hỏng giao
diện các trang đó.

Vì vậy Gate 2 cần một cách nâng giữ nguyên giao diện:
- Chỉ đổi những tên lớp chạy giống nhau ở cả bản 3 và 4, ví dụ `shrink-0`,
  `bg-black/50`.
- Trong cấu hình tailwind 4, đặt lại thang shadow/bo góc/ring và màu viền mặc
  định giống bản 3.
- Kiểm tra bằng **chụp màn hình so sánh trước/sau** các trang Operator/Portal.

## Vấn đề vận hành

Còn 7 lỗ hổng trong công cụ build. GAP-083 chỉ chặn mức critical, nên chưa bị
chặn.

## Người dùng bị ảnh hưởng

Người dùng trang Operator và Portal, nếu giao diện thay đổi; mục tiêu là
không đổi. Trình duyệt tối thiểu sẽ là Safari 16.4+ và Chrome 111+.

## Phạm vi đề xuất

Gate 2 thiết kế:
- phương án nâng (giữ giao diện, so với chấp nhận giao diện mới);
- danh sách file được sửa;
- cách so sánh ảnh chụp màn hình.

## Loại trừ rõ ràng

Không đổi các trang dùng Tailwind CDN hay `public/css/tailwind.css`. Không
thiết kế lại giao diện. Không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, nâng gói, sửa view, merge, phát hành hay deploy.
