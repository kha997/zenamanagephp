---
work_id: GAP-091
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-22-gap-091-governance-lint-wait-evidence.md
  plan: null
  branch: docs/GAP-091-governance-lint-wait
  pr: null
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
  created_at: "2026-10-22T19:29:37+07:00"
  updated_at: "2026-10-22T19:29:37+07:00"
generated_by: agent
---

## OWNER GATE 1: AWAITING OWNER

## Owner Summary

Owner chọn GAP-091 là "Sửa lint Owner đỏ giả".

Mình xem 60 lần chạy gần nhất của **Owner Governance Lint**:

- **8/8 lần trình Gate 3** (GAP-085 đến GAP-090) đều đỏ ở lần chạy đầu. Mỗi lần đều phải chạy lại
  bằng tay, và lần chạy lại đều xanh.
- Nguyên nhân: khi gói Gate 3 ở trạng thái "đang chờ Owner", lint kiểm tra mọi check khác đã xanh.
  Nhưng lint chỉ chờ **5 phút**, trong khi browser-tests mất khoảng 20 phút. Lint không phân biệt
  check "đang chạy" với check "đã đỏ".
- 4 lần đỏ khác là lỗi thật: digest cũ sau khi đổi code. Kiểm tra đó vẫn đúng và phải giữ nguyên.

## Vấn đề vận hành

Mỗi lần trình Gate 3, PR hiện một check đỏ giả, trông giống hệt lỗi thật. Phải mở log để phân biệt
và chạy lại bằng tay.

## Người dùng bị ảnh hưởng

Owner và người vận hành PR: mọi Work ID có Gate 3.

## Phạm vi đề xuất

Gate 2 thiết kế cách để lint:
- chờ đủ lâu cho các check khác chạy xong, hoặc chỉ chạy sau khi chúng xong;
- vẫn **đỏ ngay** nếu có check khác thật sự đỏ, tức là không nới lỏng kiểm tra;
- cách chứng minh: một lần trình Gate 3 thử phải xanh mà không cần chạy lại, và một check đỏ thật vẫn phải làm lint đỏ.

## Loại trừ rõ ràng

Không đổi luật governance: các trạng thái gate, kiểm tra digest, kiểm tra thứ tự gate. Không đổi
các workflow khác. Không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, sửa script hay workflow, merge, phát hành hay deploy.
