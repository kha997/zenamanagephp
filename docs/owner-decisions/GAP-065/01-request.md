---
work_id: GAP-065
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-07-gap-065-architecture-test-db-leak-evidence.md
  plan: null
  branch: docs/GAP-065-architecture-test-db-leak
  pr: https://github.com/kha997/zenamanagephp/pull/338
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-27T23:21:37+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-27, verbatim: 'APPROVE GAP-065 Gate 1'. Bound to reviewed Draft PR #338 head c30166b18be262da199e72bbcee7d36a6bc764a1, canonical base 2b336bced728c5bc0c77ec185ac946e8a10964bf (leak re-checked on that base). Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-07T08:56:01+07:00"
  updated_at: "2026-10-27T23:21:37+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-065 Gate 1 in-session on 2026-10-27 against Draft PR #338
head `c30166b18be262da199e72bbcee7d36a6bc764a1`. This authorizes preparation of Gate 2 only.

## Owner Summary

Một test kiến trúc (`DebugRouteBoundaryInvariantTest`, phần kiểm tra hồi quy
đăng nhập nhanh của GAP-011) **tạo 1 tenant + 1 user thật vào DB test mà
không dọn**, vì lớp test này không dùng `RefreshDatabase`. Khi chạy sau một
test có `RefreshDatabase`, dữ liệu này rò sang các test sau: lệnh
`VendorApiTest + tests/Architecture + ProductionBootstrapCommandTest` đỏ 3
test (bootstrap tưởng DB đã có dữ liệu). CI hiện vẫn xanh **chỉ nhờ thứ tự
chạy**, nhưng rác vẫn rò sang toàn bộ bộ Integration. Đã chứng minh bằng thử
nghiệm (đã hoàn tác): thêm `RefreshDatabase` vào lớp đó → 35/35 xanh. Đề
xuất: phê duyệt Gate 1 để thiết kế bản sửa cách ly test.

## Vấn đề vận hành

Test không cách ly làm kết quả phụ thuộc thứ tự chạy, nên có thể đỏ giả hoặc
xanh giả khi chạy chọn lọc, đổi thứ tự hoặc thêm test đếm số bản ghi.

## Người dùng bị ảnh hưởng

Đội kỹ thuật (độ tin cậy test/CI). Không ảnh hưởng người dùng cuối hay
production. Lệnh `production:bootstrap` đúng, nó chỉ là nạn nhân.

## Bằng chứng

Kiểm tra lại ngày 2026-10-27 trên main `2b336bce`: vẫn đỏ đúng 3 test như cũ (xem mục "Re-check on current main"
trong file bằng chứng).


`docs/audits/2026-10-07-gap-065-architecture-test-db-leak-evidence.md` gồm:
lệnh tái hiện và kết quả, bảng chia đôi theo file và theo method, cơ chế
`ensureTestingSchema()` / `RefreshDatabaseState::$migrated` giải thích vì sao
phụ thuộc thứ tự, mức phơi nhiễm CI và thử nghiệm xác nhận.

## Phạm vi đề xuất

Gate 2 chọn giữa phương án 1 (thêm `RefreshDatabase` vào lớp) và phương án 2
(tách 3 test HTTP sang `tests/Feature` để `tests/Architecture` không đụng DB).

## Loại trừ rõ ràng

Không sửa mã ứng dụng. Không sửa `ProductionBootstrapCommand`. Không xử lý 10
file "ứng viên" cùng mẫu ở Finding 5, vì chưa xác minh và cần Work ID riêng.
Không thêm cơ chế guard toàn suite (phương án 3). Không deploy.

## Khả năng hoàn tác

Revert commit (chỉ thay đổi file test).

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, thay đổi code, merge hay phát hành.
