---
work_id: GAP-056
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-09-29-gap-056-script-mysql-password-evidence.md
  plan: null
  branch: docs/GAP-056-script-mysql-password-argv
  pr: https://github.com/kha997/zenamanagephp/pull/323
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-29T07:47:34+07:00"
  owner_response_reference: "Owner decision in-session on 2026-09-29: 'APPROVE GAP-056 Gate 1'. Bound to reviewed Draft PR #323 head 195c2dfa1e4a5ad31991eda2fc7c16f57e3ac2b9, canonical base 368536793117816417373a3bde2ac636d46b7d42. The Owner did not answer the open operational question (whether setup-production.sh / docker-manage.sh were ever run on a real server); it remains open. Authorizes preparing Gate 2 only; not implementation, credential rotation, host cleanup, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-29T07:44:26+07:00"
  updated_at: "2026-09-29T07:47:34+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-056 Gate 1 in-session on 2026-09-29 against Draft PR #323
head `195c2dfa1e4a5ad31991eda2fc7c16f57e3ac2b9`. The operational question below (were the scripts ever run on a real
server?) remains unanswered. This authorizes preparation of Gate 2 only.

## Owner Summary

11 script vận hành đưa mật khẩu cơ sở dữ liệu lên dòng lệnh (31 chỗ), và kiểm
tra kỹ cho thấy hai điều nặng hơn: một script cài đặt máy chủ **ghi thẳng mật
khẩu vào một file ai trên máy cũng đọc được** rồi cho chạy tự động mỗi đêm; và
script sao lưu duy nhất nằm trên đường tự động **chép cả file bí mật
`production.env` vào bản sao lưu**. Đề nghị cho phép thiết kế cách sửa.

## Vấn đề vận hành

1. **Mật khẩu trên dòng lệnh (31 chỗ / 11 file):** khi lệnh chạy, người dùng
   khác trên cùng máy có thể nhìn thấy mật khẩu. 4 chỗ dùng tài khoản quản trị
   cao nhất (`root`), và nếu biến môi trường thiếu thì tự dùng mật khẩu viết sẵn
   `root_password`.
2. **Script cài đặt máy chủ lưu mật khẩu dạng chữ thường** trong
   `/usr/local/bin/zenamanage-backup` (mọi tài khoản trên máy đọc được), chạy
   hằng đêm lúc 2 giờ, và nén cả thư mục dự án gồm file cấu hình bí mật.
3. **`docker-manage.sh backup`** (được workflow deploy tự động gọi) chép
   `production.env` vào mỗi bản sao lưu.
4. **Không có hàng rào kiểm thử** cho file script; kiểm thử của GAP-054 chỉ quét
   mã PHP.

## Người dùng bị ảnh hưởng

Mọi khách hàng, nếu ai đó trên máy chủ đọc được mật khẩu cơ sở dữ liệu hoặc bản
sao lưu. Chỉ xảy ra trên máy từng chạy các script này.

## Bằng chứng

Quét toàn bộ repo, đọc từng chỗ gọi, đối chiếu workflow tự động. Không chạy
script nào. Chi tiết: `docs/audits/2026-09-29-gap-056-script-mysql-password-evidence.md`.

## Câu hỏi vận hành cho Owner

**Các script này (đặc biệt `setup-production.sh` và `docker-manage.sh`) đã
từng được chạy trên máy chủ thật chưa?** Nếu rồi, mật khẩu và file bí mật đã
nằm sẵn trên máy chủ; sửa repo không tự xoá chúng — cần dọn máy và đổi mật
khẩu (việc vận hành riêng, ngoài repo).

## Tác động nếu không xử lý

Mật khẩu cơ sở dữ liệu và toàn bộ bí mật của hệ thống có thể lộ cho bất kỳ ai
có quyền vào máy chủ; ai chạy lại script cũ sẽ tái tạo lỗ hổng.

## Phạm vi đề xuất

Nếu duyệt, Gate 2 thiết kế: cách truyền mật khẩu an toàn (file tạm quyền 0600,
giống cách GAP-054 đã làm trong ứng dụng) hoặc gỡ các script không dùng; bỏ mật
khẩu viết sẵn; không chép `production.env` vào sao lưu; và mở rộng kiểm thử
kiến trúc để chặn mọi `-p$MẬT_KHẨU` mới trong file `.sh`.

## Loại trừ rõ ràng

- Không đổi mật khẩu, không vào máy chủ, không dọn file trên máy chủ.
- Không sửa việc truyền mật khẩu SMTP trên dòng lệnh (loại bí mật khác — ghi
  nhận riêng).
- Không deploy.

## Khả năng hoàn tác

Chỉ là tài liệu ở Gate này.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt cách sửa, Gate 2, merge, phát hành, hay đổi mật khẩu.
