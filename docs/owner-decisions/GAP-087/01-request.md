---
work_id: GAP-087
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-21-gap-087-actions-node24-evidence.md
  plan: null
  branch: docs/GAP-087-actions-node24
  pr: https://github.com/kha997/zenamanagephp/pull/360
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-21T07:47:30+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-21, verbatim: 'APPROVE GAP-087 Gate 1'. Bound to reviewed Draft PR #360 head b2416bb061cb0b9b632000139882a2f29ecc3fe1, canonical base 1928f2ae1c85388ce88661f53855a5aba03a6720. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-21T07:44:54+07:00"
  updated_at: "2026-10-21T07:47:30+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-087 Gate 1 in-session on 2026-10-21 against Draft PR #360
head `b2416bb061cb0b9b632000139882a2f29ecc3fe1`. This authorizes preparation of Gate 2 only.

## Owner Summary

CI đang cảnh báo "Node.js 20 is deprecated". Có **8 action** (60 chỗ dùng
trong 8 file workflow) vẫn được viết cho Node 20. GitHub hiện ép chúng chạy trên
Node 24, nên vẫn chạy được, nhưng tác giả không phát hành chúng cho runtime
này:

| Action | Số chỗ | Bản đầu tiên chạy Node 24 |
|---|---|---|
| `upload-artifact@v4` | 18 | v6 |
| `download-artifact@v4` | 6 | v7 |
| Slack `@v2` | 9 | v3 |
| 4 action Docker (`build-push`, `setup-buildx`, `metadata`, `login`) | 25 | v7 / v4 / v6 / v4 |
| CodeQL `upload-sarif@v3` | 2 | v4 |

**Rủi ro cần biết:** đây đều là bản nâng major. Ngoài ra, 37/60 chỗ nằm trong
workflow release, deploy, production hoặc nightly. CI của PR không chạy các
workflow này, nên không kiểm chứng được tự động. Gate 2 sẽ đề xuất cách kiểm
tra.

## Vấn đề vận hành

Các action viết cho Node 20 đang bị ép chạy trên Node 24. Mỗi lần chạy, log
CI hiện một cảnh báo. Nếu một action không tương thích Node 24, nó có thể hỏng
mà không báo trước.

## Người dùng bị ảnh hưởng

- Người phát triển: CI của PR.
- Người phát hành: workflow release, deploy và production.

## Phạm vi đề xuất

Gate 2 thiết kế:
- nâng lên bản nào cho từng action (bản đầu tiên chạy Node 24, hay bản mới nhất);
- các thay đổi phá vỡ phải xử lý;
- cách kiểm chứng các workflow mà CI của PR không chạy.

## Loại trừ rõ ràng

- Không nâng các action đã chạy Node 24, hoặc không chạy bằng Node.
- Không đổi logic workflow.
- Không chạy release hay deploy thật.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, sửa workflow, merge, phát hành hay deploy.
