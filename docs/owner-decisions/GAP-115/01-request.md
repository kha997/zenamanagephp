---
work_id: GAP-115
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-06-gap-115-deploy-readiness-evidence.md
  plan: null
  branch: docs/GAP-115-deploy-readiness
  pr: https://github.com/kha997/zenamanagephp/pull/405
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-06T01:21:50+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-06, verbatim: 'APPROVE GAP-115 Gate 1.'. Bound to reviewed Draft PR #405 head fd75865d783fe86cd068c683b76b1574ee409160, canonical base a9ff611acb4b033ac96a5cd14426b2dccd4d4c38. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-06T01:30:00+07:00"
  updated_at: "2026-11-06T01:21:50+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-115 Gate 1 in-session on 2026-11-06 against Draft PR #405 head
`fd75865d783fe86cd068c683b76b1574ee409160`. This authorizes preparation of Gate 2 only.

## Owner Summary

Chuẩn bị **đưa phần mềm lên máy chủ thật** (mục 1) và **cấp quyền trên môi trường thật** (mục 2).

**Đã có sẵn từ trước (GAP-049):**
- Quy trình deploy bằng tay theo đúng một commit.
- Kiểm tra an toàn migration.
- Script sao lưu / khôi phục / quay lại bản cũ.
- Lệnh tạo công ty và tài khoản quản trị đầu tiên.
- Hướng dẫn chuẩn bị máy chủ.

**Khảo sát tìm ra 5 chỗ chưa ổn:**
1. **9 migration mới (GAP-106 → GAP-114) chưa được phân loại** → lần deploy đầu sẽ dừng ngay ở bước migration.
2. **Chưa có cách nạp quyền an toàn cho môi trường thật.**
   - Bộ seeder hiện tại trộn quyền với dữ liệu mẫu, trong đó có tài khoản `admin@zena.local` / mật khẩu `password`.
   - Tài khoản quản trị đầu tiên có vai trò nhưng chưa có quyền, nên chưa vào được trang lương, Ngân quỹ, bảng giờ.
3. **Chưa có cách tạo tài khoản kế toán / PM / nhân viên** ngoài sửa thẳng database.
4. **Còn một đường deploy cũ:** đẩy tag `v*` là tự `git pull` lên máy chủ, bỏ qua mọi kiểm tra. Hiện chưa chạy chỉ vì
   thiếu cấu hình bí mật.
5. **Tài liệu deploy rải rác, nhiều bản cũ.** Chưa có một danh sách "lên chạy lần đầu" thống nhất.

**Gate 2 sẽ đưa phương án:**
- phân loại 9 migration và thêm test chặn thiếu phân loại;
- lệnh nạp quyền an toàn (không tạo người dùng, không dữ liệu mẫu, có chạy thử);
- cách tạo tài khoản và gán vai trò;
- xử lý đường deploy cũ;
- một runbook "lên chạy lần đầu" và đánh dấu các tài liệu cũ.

## Vấn đề vận hành

Các tính năng đã làm (Ngân quỹ, lương, giờ, lời lỗ) chưa dùng thật được vì chưa deploy được an toàn và chưa có quyền trên
môi trường thật.

## Người dùng bị ảnh hưởng

Owner (deploy, tạo tài khoản), toàn bộ người dùng sau khi lên chạy thật.

## Bằng chứng

`docs/audits/2026-11-06-gap-115-deploy-readiness-evidence.md`.

## Phạm vi đề xuất

Gate 2: phân loại migration; lệnh nạp quyền; cách tạo tài khoản; xử lý workflow deploy cũ; runbook go-live; test.

## Loại trừ rõ ràng

Những việc dưới đây vẫn do anh/chị tự làm theo runbook:
- Thuê / cài máy chủ thật.
- Đặt secrets trên GitHub.
- Bấm deploy.
- Chạy lệnh trên database thật.

## Khả năng hoàn tác

Revert. Không có migration mới.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-115 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn phương án, thiết kế, Gate 2, merge, và không phải quyết định deploy thật.
