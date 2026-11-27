---
work_id: OWN-2026-024
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/UAT-RECOVERY-01/100-o17h-super-admin-bypass-assessment.md
  plan: null
  branch: integration/uat-recovery-s1-s4d
  pr: https://github.com/kha997/zenamanagephp/pull/422
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-24T16:55:00+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-24, verbatim: 'WORK ID: OWN-2026-024 / OWNER DECISION: O17H_OPTION_B_APPROVED / IMPLEMENTATION AUTHORITY: H0 PREPARATION + H1 TESTS ONLY'. Given against Draft PR #422 head c94ff67c031c60a862def26e0c5c9f3bc33e2ffc after reviewing assessment 100. Approves the problem and the direction (Option B: explicit, revocable permissions instead of implicit super_admin authority). Authorizes H0 preparation (read-only procedure, not executed) and H1 tests only; no runtime authorization change, no grant or revocation, no commit, push, merge or deployment; not Gate 2 or Gate 3."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-24T16:55:00+07:00"
  updated_at: "2026-11-24T16:55:00+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner duyệt trong phiên ngày 2026-11-24: cấp mã việc OWN-2026-024, chọn hướng B, chỉ cho phép chuẩn bị H0 và viết kiểm thử H1.
Quyết định này không duyệt thiết kế (Gate 2) và không duyệt phát hành (Gate 3).

## Owner Summary

Hiện nay, ai mang vai trò "chủ công ty" (`super_admin`) thì **được làm mọi việc chỉ vì cái tên vai trò**, kể cả khi vai trò đó đã bị gỡ hết quyền.
Việc này cũng mở cho chủ công ty các thao tác ảnh hưởng **cả máy chủ**: bảo trì cơ sở dữ liệu, sao lưu toàn bộ dữ liệu, xoá bộ nhớ đệm.
Owner muốn chuyển sang mô hình **"ai được phép làm gì" phải được cấp rõ ràng, và thu hồi được**.

## Vấn đề vận hành

- Gỡ một quyền khỏi vai trò chủ công ty **không có tác dụng**: hệ thống vẫn cho làm.
- Có 80 đường dẫn quản trị máy chủ mà **chỉ cái tên vai trò** mới mở được. Chúng không gắn với quyền thật nào.
- Mỗi lần chạy lại bước nạp quyền chuẩn, những quyền đã cố ý gỡ **tự quay lại**.
- Một người được giao quản lý vai trò trong công ty có thể **tự nâng mình thành chủ công ty** hoặc tự cấp quyền cấp máy chủ.

## Người dùng bị ảnh hưởng

- Chủ công ty: quyền quá rộng, không thu hẹp được.
- Người quản lý vai trò được uỷ quyền: có thể vượt quyền.
- Mọi công ty dùng chung máy chủ: một chủ công ty có thể chạy thao tác ảnh hưởng tất cả.
- Người dùng thường không thấy thay đổi.

## Bằng chứng

- Tài liệu đánh giá `docs/audits/UAT-RECOVERY-01/100-o17h-super-admin-bypass-assessment.md`.
- Bộ kiểm thử H1. Kết quả và các phép thử đều chạy trên dữ liệu giả lập, không đụng máy thật, được ghi trong `docs/audits/UAT-RECOVERY-01/101-o17h-h0-h1-design.md`.

## Tác động nếu không xử lý

- Không thể giới hạn quyền của chủ công ty.
- Nếu có nhiều công ty trên cùng máy chủ, một công ty có thể làm gián đoạn hoặc sao chép dữ liệu của tất cả.
- Quyết định thu hồi quyền không đáng tin.

## Phạm vi đề xuất

- Thay quyền ngầm theo tên vai trò bằng quyền cấp rõ ràng.
- Tách quyền **quản trị máy chủ** ra khỏi quyền **chủ công ty**.
- Làm cho việc thu hồi quyền có hiệu lực lâu dài.
- Chặn việc tự nâng quyền qua màn hình quản lý vai trò.
- Làm từng bước (H0 đến H4); mỗi bước Owner duyệt riêng.

## Loại trừ rõ ràng

Không xử lý trong mã việc này. Mỗi mục được ghi nhận riêng:
- lỗi tạo người dùng hàng loạt sang công ty khác (BLK-1);
- thiếu nhật ký thao tác (AUD-1);
- cổng phân quyền không tham số (O8-1);
- không ai bổ nhiệm được quản lý dự án trên máy thật (O16-1);
- lỗi xem và sửa "thành phần" của công ty khác (CMP-1).

Ngoài ra không viết lại toàn bộ hệ phân quyền và không tự động đổi vai trò của ai.

## Đề xuất

Làm ngay theo hướng B. Đây là lỗ hổng phân quyền gốc, và các mã việc O-16, O-17 và O-18 đều đã chọn mô hình "quyền cấp rõ ràng".

## Decision Needed

Owner chooses one: Approve to proceed to design (Gate 2) / Request more information / Decline / Defer.
(Đã duyệt trong phiên, xem ở trên.)

## What the owner is NOT being asked to decide

Gate 1 không hỏi về:
- tên quyền mới;
- cách lưu "quyền đã thu hồi";
- thứ tự các bước kỹ thuật.

Những điều đó nằm ở Gate 2.
