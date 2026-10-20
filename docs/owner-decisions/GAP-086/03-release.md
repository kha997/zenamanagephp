---
work_id: GAP-086
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_correction_or_defer
references:
  spec: docs/audits/2026-10-20-gap-086-npm-audit-high-gate-evidence.md
  plan: docs/superpowers/plans/2026-10-20-gap-086-npm-audit-high-gate.md
  branch: docs/GAP-086-npm-audit-high-gate
  pr: https://github.com/kha997/zenamanagephp/pull/359
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
  created_at: "2026-10-20T20:59:32+07:00"
  updated_at: "2026-10-20T23:16:48+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "RE-PRESENTED after the Owner amendment of 2026-10-20T21:12:09+07:00: the Owner approval of 2026-10-20 (verbatim 'APPROVE GAP-086 Gate 3', bound to subject 691e668cd8194cb6dbd77bcdd77ce23f418faa1c / digest 9c4fbf0131e4519e2b3c2eefb488796983663b309dd118cbe27c46011c2ceacb) is superseded because 461f50dc changed the tree in answer to a Codex P2 finding. GAP-086 at subject 1b4b2f33. fd1c3000: .github/workflows/code-quality-security.yml, Security Vulnerability Scan final gate step renamed 'Fail on Composer advisories or npm high/critical vulnerabilities'; it counts distinct high/critical GHSA ids from npm-audit.json .vulnerabilities[].via[] objects, minus exceptions in an optional .npm-audit-ignore, prints the counted advisories and fails when any remain; a missing or unreadable report still fails; the step summary prints .npm-audit-ignore when present. 461f50dc (Owner amendment 'Sửa trong PR này (Recommended)'): every non-blank, non-comment .npm-audit-ignore line must be a GHSA id followed by '#' and a non-empty reason; other lines fail the step and are printed with line numbers; only valid entries count as exceptions. Composer and Trivy gates unchanged; moderate/low npm findings stay report-only; .npm-audit-ignore not created. Proofs on the PR, each temporary commit reverted: 4016d2a7 (axios 1.18.1 pinned) red listing 7 high GHSA ids, 869751f8 (those ids ignored) green, reverted by 691e668c; 26366d18 (entry without a reason) red printing '1:GHSA-xxxx-xxxx-xxxx', 6ed98c32 (same entry with a reason) green, reverted by 1b4b2f33, whose tree is identical to 461f50dc. Local script test: clean 0; high 1; all ignored with reasons 0; one of two ignored 1; bare id 1; empty reason 1; non-GHSA line 1; comments only 0; missing report 1. Exact-head CI at 1b4b2f33: 33/34 green; Owner Governance Lint red only because the packet was still bound to the superseded digest, which this re-presented packet resolves."
technical_evidence:
  base_sha: "78fec1c22bbe6ba4fd78e1b6c9fdf59a266ca488"
  subject_sha: "1b4b2f339b55fc689f5172ffe915bff98df3f1ad"
  implementation_tree_digest: "d49ba389f7e4c2537c79c4aa7eab6d4d7154e4d03a25ad459d2d3d51d9bcd4df"
  verified_pr_head_sha: "1b4b2f339b55fc689f5172ffe915bff98df3f1ad"
  verified_at: "2026-10-20T23:16:48+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-086 — Gate 3 release decision (npm audit gate at high)

## OWNER GATE 3: AWAITING OWNER

## Gói quyết định phát hành (trình lại)

Owner đã duyệt Gate 3 lúc 21:08 tại subject `691e668c`. Sau đó Codex review báo
lỗi P2: một dòng trong `.npm-audit-ignore` thiếu lý do vẫn được bỏ qua. Owner chọn
"Sửa trong PR này (Recommended)" (Owner amendment). Vì sửa nên digest đổi, lần
duyệt trên **không còn hiệu lực**.

**1. Vấn đề là gì?** `npm audit` chỉ báo cáo, không chặn PR. Lỗ hổng npm
mức high hoặc critical có thể lọt vào mà CI vẫn xanh (Gate 1).

**2. Sau thay đổi (Gate 2, Phương án A):**

| Commit | Thay đổi |
|---|---|
| `fd1c3000` | Bước cuối của "Security Vulnerability Scan" đỏ khi có lỗ hổng npm **high/critical**, trừ ngoại lệ ghi trong `.npm-audit-ignore` (nếu có) |
| `461f50dc` | Mỗi dòng ngoại lệ phải là mã GHSA kèm `# lý do`. Dòng sai định dạng làm CI đỏ và được in ra kèm số dòng (Owner amendment, theo Codex) |
| `4016d2a7`, `869751f8`, `26366d18`, `6ed98c32` | Commit tạm để kiểm chứng đỏ/xanh |
| `691e668c`, `1b4b2f33` | Hoàn tác các commit tạm. Cây file `1b4b2f33` giống hệt `461f50dc` |

- Gate Composer và Trivy giữ nguyên.
- Lỗ hổng moderate/low vẫn chỉ báo cáo.
- Chưa tạo file `.npm-audit-ignore`.

**3. Bằng chứng** — base `78fec1c2`; subject `1b4b2f339b55fc689f5172ffe915bff98df3f1ad`;
digest `d49ba389f7e4c2537c79c4aa7eab6d4d7154e4d03a25ad459d2d3d51d9bcd4df`.

- Kiểm chứng trên PR:
  - ghim `axios` 1.18.1 (có lỗ hổng high): **đỏ**, liệt kê 7 mã GHSA;
  - ghi 7 mã đó vào `.npm-audit-ignore`: **xanh**;
  - một dòng ngoại lệ không có lý do: **đỏ**, in ra `1:GHSA-xxxx-xxxx-xxxx`;
  - cùng dòng đó có lý do: **xanh**.
- Thử script gate trên máy:

  | Trường hợp | Kết quả |
  |---|---|
  | báo cáo sạch | qua |
  | có lỗ hổng high | đỏ |
  | bỏ qua hết, mỗi dòng có lý do | qua |
  | bỏ qua một trong hai | đỏ |
  | có mã nhưng thiếu lý do | đỏ |
  | lý do rỗng | đỏ |
  | dòng không phải mã GHSA | đỏ |
  | file chỉ có dòng chú thích | qua |
  | thiếu báo cáo | đỏ |

- CI chạy đúng trên commit `1b4b2f33`: 33/34 xanh. Riêng Owner Governance Lint đỏ, vì gói Gate 3 lúc đó còn gắn digest cũ. Gói trình lại này xử lý lỗi đó.

**4. Ảnh hưởng**
- Từ khi merge, PR nào thêm hoặc vẫn còn lỗ hổng npm mức high/critical sẽ bị đỏ.
- Muốn tạm chấp nhận một lỗ hổng: thêm mã GHSA kèm lý do vào `.npm-audit-ignore`, rồi cho qua review PR.
- Không ảnh hưởng ứng dụng đang chạy.

**5. Rủi ro còn lại** — Thấp:
- một lỗ hổng mới công bố có thể làm đỏ PR không liên quan cho đến khi vá hoặc
  ghi ngoại lệ (đây là chủ đích của gate);
- ngoại lệ trong `.npm-audit-ignore` không tự hết hạn, cần review định kỳ.

**6. Hoàn tác** — Revert squash commit.

**7. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-086 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy; không đổi gate Composer/Trivy; không chặn mức moderate/low.
