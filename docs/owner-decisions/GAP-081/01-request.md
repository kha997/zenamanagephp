---
work_id: GAP-081
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-15-gap-081-git-blame-ignore-revs-evidence.md
  plan: null
  branch: docs/GAP-081-git-blame-ignore-revs
  pr: https://github.com/kha997/zenamanagephp/pull/354
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-15T18:34:08+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-15, verbatim: 'APPROVE GAP-081 Gate 1'. Bound to reviewed Draft PR #354 head b9e484b77c7bfd4ac085226a44c5c415abac5b46, canonical base 40d19ab10f51c77587127f2ab5c477724f4ab7cf. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-15T15:24:56+07:00"
  updated_at: "2026-10-15T18:34:08+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-081 Gate 1 in-session on 2026-10-15 against Draft PR #354
head `b9e484b77c7bfd4ac085226a44c5c415abac5b46`. This authorizes preparation of Gate 2 only.

## Owner Summary

Sau GAP-080, `git blame` trên hầu hết dòng PHP chỉ hiện commit định dạng lại
`40d19ab1` thay vì commit thật sự sửa code. Repo chưa có
`.git-blame-ignore-revs` — file GitHub tự đọc để bỏ qua các commit chỉ định
dạng.

Lưu ý: commit `40d19ab1` có lẫn vài thay đổi không phải định dạng (cấu hình
fixer, bước CI, script, tài liệu); khi bỏ qua commit, blame của những dòng đó
cũng trỏ về bản trước (`git log` vẫn thấy).

Đề xuất: phê duyệt Gate 1 để thiết kế.

## Vấn đề vận hành

Khó tra lịch sử một dòng code (ai/sao sửa) trên GitHub và trong `git blame`.

## Người dùng bị ảnh hưởng

Người đọc lịch sử code. Không ảnh hưởng ứng dụng.

## Phạm vi đề xuất

Gate 2: thêm `.git-blame-ignore-revs` ở thư mục gốc với commit `40d19ab1` và
ghi chú cách bật khi dùng git trên máy.

## Loại trừ rõ ràng

Không đổi code, CI hay lịch sử git; không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, thay đổi file, merge, phát hành hay deploy.
