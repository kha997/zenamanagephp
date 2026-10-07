---
work_id: GAP-065
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-07-gap-065-architecture-test-db-leak-evidence.md
  plan: null
  branch: docs/GAP-065-architecture-test-db-leak
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-07T08:56:01+07:00"
  updated_at: "2026-10-07T08:56:01+07:00"
generated_by: agent
---

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
