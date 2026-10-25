---
work_id: GAP-017
gate: 3
gate_status: approved
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-25-gap-017-invitation-dead-ends-evidence.md
  plan: docs/superpowers/plans/2026-10-25-gap-017-invitation-decline.md
  branch: docs/GAP-017-invitation-decline
  pr: https://github.com/kha997/zenamanagephp/pull/367
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-25T15:23:31+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-10-25, verbatim: 'APPROVE GAP-017 Gate 3'. Given after the packet at PR head ad509046edfd827458be08f19a0f04a2f7fe1a73 (all checks green); bound to implementation subject 317e5332af513a79989fccb7cdbc5df95eb4abc7 and implementation-tree digest a59a940128c4536ee16ce8a9f049e28c1b3cfd8de7ec0c75686d871c3e4aff48 (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment or release authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-25T13:49:39+07:00"
  updated_at: "2026-10-25T15:23:31+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-017 at subject 317e5332af513a79989fccb7cdbc5df95eb4abc7 (Gate 2 Option A). routes/web.php: the invitations.decline route (GET /invitations/decline/{token} -> Web\\InvitationController::decline, a method that never existed) is removed, and so is the /invite/decline/{token} permanent redirect, which was declared twice with identical text; both copies are removed. legacy-map.json drops its /invite/decline/{token} entry. Web\\InvitationController, which had no other route, is deleted with the invitations index, create and manage views that only it rendered, and its 32 phpstan-baseline.neon entries. The accept route, the /invite/accept/{token} redirect, the accept view and the invitation API are unchanged. New test tests/Feature/Web/InvitationDeclineRouteRemovedTest.php: on the base all 3 tests fail (500, 301, named route present); after the change 3/3 pass (both URLs 404, invitations.decline gone, invitations.accept present). route:list differs from the base only by the two removed routes. Local: phpunit --filter='Route|Architecture|Legacy|Team' 295 tests OK (7 skipped); --filter=Invitation 8 tests OK; PHPStan no errors; php-cs-fixer 0 files to fix; governance and docs lints pass. Exact-head CI at 317e5332: 35/35 green."
technical_evidence:
  base_sha: "a2ee71a5f3ece423f41fd0fb0b7c79da565a853f"
  subject_sha: "317e5332af513a79989fccb7cdbc5df95eb4abc7"
  implementation_tree_digest: "a59a940128c4536ee16ce8a9f049e28c1b3cfd8de7ec0c75686d871c3e4aff48"
  verified_pr_head_sha: "317e5332af513a79989fccb7cdbc5df95eb4abc7"
  verified_at: "2026-10-25T13:49:39+07:00"
owner_decision_binding:
  implementation_tree_digest: "a59a940128c4536ee16ce8a9f049e28c1b3cfd8de7ec0c75686d871c3e4aff48"
  decision_recorded_at: "2026-10-25T15:23:31+07:00"
---

# GAP-017 — Gate 3 release decision (invitation decline link)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-10-25 ("APPROVE GAP-017 Gate 3"), bound to implementation subject
`317e5332af513a79989fccb7cdbc5df95eb4abc7` and implementation-tree digest `a59a940128c4536ee16ce8a9f049e28c1b3cfd8de7ec0c75686d871c3e4aff48`. No deployment or release is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Link từ chối lời mời `/invitations/decline/{token}` (và link cũ `/invite/decline/{token}`) luôn trả
lỗi 500 vì trỏ tới hàm không tồn tại; không cần đăng nhập (Gate 1).

**2. Sau thay đổi (Gate 2 Phương án A)** — commit `317e5332`:
- Xoá route từ chối và link chuyển hướng cũ. Hai link này giờ trả **404**.
- Xoá `Web\InvitationController` (không còn route nào khác), 3 view chỉ nó dùng, và 32 dòng phpstan-baseline của nó.
- Trang nhận lời mời và API lời mời giữ nguyên.

**3. Bằng chứng** — base `a2ee71a5`; subject `317e5332af513a79989fccb7cdbc5df95eb4abc7`;
digest `a59a940128c4536ee16ce8a9f049e28c1b3cfd8de7ec0c75686d871c3e4aff48`.
- Test mới: trên main cũ hỏng 3/3; sau khi sửa qua 3/3.
- Danh sách route chỉ khác đúng 2 route đã xoá.
- Trên máy: 295 test route/architecture/legacy/team qua; PHPStan, php-cs-fixer, lint governance và docs sạch.
- CI chạy đúng trên `317e5332`: **35/35 xanh**.

**4. Công khai**
- Link chuyển hướng `/invite/decline/{token}` được khai báo **hai lần giống hệt nhau** trong `routes/web.php`; thiết kế
  ghi "dòng" redirect, mình xoá cả hai bản.
- Không thêm chức năng từ chối lời mời; nếu sau này cần, đó là việc mới (Phương án B ở Gate 2).
- Register `OPERATIONAL_GAP_REGISTER.md` chưa cập nhật; sẽ làm trong đợt cập nhật register Owner đã xếp tiếp theo.

**5. Ảnh hưởng** — Không còn URL công khai trả 500; bớt khoảng 1.400 dòng controller và view không dùng.

**6. Rủi ro còn lại** — Thấp: nếu ai đó còn giữ link từ chối cũ, họ nhận 404 thay vì 500.

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-017 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy hay release; không làm chức năng từ chối lời mời.
