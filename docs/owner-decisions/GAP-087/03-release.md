---
work_id: GAP-087
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
  spec: docs/audits/2026-10-21-gap-087-actions-node24-evidence.md
  plan: docs/superpowers/plans/2026-10-21-gap-087-actions-node24.md
  branch: docs/GAP-087-actions-node24
  pr: https://github.com/kha997/zenamanagephp/pull/360
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-21T14:16:00+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-10-21, verbatim: 'APPROVE GAP-087 Gate 3'. Given after the packet was presented at PR head 5150c0eaba135f5dc09c3deb4e95aa1a96ab7bbd (implementation CI 34/34 green at f72d44b5); bound to implementation subject f72d44b546555f6686fa454077bcd51919c0e936 and implementation-tree digest c2b30d4303f3567000a99b91de5914dd79a1a68b6290bed25cfbefb7e6f0c5a2 (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment or release authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-21T13:13:41+07:00"
  updated_at: "2026-10-21T14:16:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-087 at subject f72d44b5. eb5c2391 (Gate 2 Option A, uses: lines only): actions/upload-artifact v4->v7 (18), actions/download-artifact v4->v8 (6), slackapi/slack-github-action v2.1.1->v4.0.0 (9), docker/build-push-action v5->v7 (8), docker/setup-buildx-action v3->v4 (7), docker/metadata-action v5->v6 (5), docker/login-action v3->v4 (5), github/codeql-action/upload-sarif v3->v4 (2). a31697c0 (Owner amendment 1, 'Sửa trong PR này (Recommended)'): package-lock.json follow-redirects 1.16.0->1.16.1 only, for GHSA-8r9p-f939-6h3c and GHSA-q3jh-wrc6-jgq8 (high, published after GAP-086 merged; the GAP-086 gate turned every branch red); npm audit 0. f72d44b5 (Owner amendment 2, 'Sửa cả hai trong PR này (Recommended)'): codecov/codecov-action v5->v6 (4, including ci-cd.yml) and aquasecurity/trivy-action 0.35.0->v0.36.0 (5), because both composites called a Node 20 action internally (github-script v7.0.1, actions/cache v4.2.4); trivy-action v0.36.0 defaults to Trivy v0.70.0. Static checks: actionlint 1.7.12 shows the same 22 findings as main and none new; every with: key on the 69 changed steps exists in the target action.yml, and every upgraded action declares node24; the 9 Slack payloads parse as objects with js-yaml 5.5.0, the parser Slack v4 uses. Proof: temporary workflow f93ae788 ran docker/metadata-action@v6 (tags pr-360, sha-...) and docker/login-action@v4 (ghcr.io, Login Succeeded), reverted by ef8121a4. Docker Security Scan failed once at ef8121a4 on 'pecl install redis' (No releases available for package pecl.php.net/redis, after a 22 s stall), a network failure upstream of the changed actions; it passed on its one re-run. Exact-head CI 34/34 green at f72d44b5, including Docker Security Scan (setup-buildx v4, build-push v7, upload-sarif v4, Trivy v0.70.0), Test Coverage Report (download-artifact v8) and staging-smoke; the Unit Tests and Docker Security Scan logs no longer end with the Node 20 deprecation warning. Not exercised by PR CI: Slack steps and the release, deployment and production workflows (first real run at the next release; every Slack step has continue-on-error: true)."
technical_evidence:
  base_sha: "1928f2ae1c85388ce88661f53855a5aba03a6720"
  subject_sha: "f72d44b546555f6686fa454077bcd51919c0e936"
  implementation_tree_digest: "c2b30d4303f3567000a99b91de5914dd79a1a68b6290bed25cfbefb7e6f0c5a2"
  verified_pr_head_sha: "f72d44b546555f6686fa454077bcd51919c0e936"
  verified_at: "2026-10-21T13:13:41+07:00"
owner_decision_binding:
  implementation_tree_digest: "c2b30d4303f3567000a99b91de5914dd79a1a68b6290bed25cfbefb7e6f0c5a2"
  decision_recorded_at: "2026-10-21T14:16:00+07:00"
---

# GAP-087 — Gate 3 release decision (GitHub Actions off Node 20)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-10-21 ("APPROVE GAP-087 Gate 3"), bound to implementation subject
`f72d44b546555f6686fa454077bcd51919c0e936` and implementation-tree digest `c2b30d4303f3567000a99b91de5914dd79a1a68b6290bed25cfbefb7e6f0c5a2`. No deployment or release is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** CI cảnh báo "Node.js 20 is deprecated", vì nhiều action vẫn
được viết cho Node 20 (Gate 1).

**2. Sau thay đổi (Gate 2 Phương án A và 2 Owner amendment):**

| Commit | Thay đổi |
|---|---|
| `eb5c2391` | 60 dòng `uses:`: `upload-artifact` v7, `download-artifact` v8, Slack v4.0.0, `build-push` v7, `setup-buildx` v4, `metadata` v6, `login` v4, `upload-sarif` v4 |
| `a31697c0` | Owner amendment 1: `follow-redirects` 1.16.0 → 1.16.1 trong `package-lock.json`, vá 2 lỗ hổng high mới công bố |
| `f72d44b5` | Owner amendment 2: `codecov` v5 → v6 (4 chỗ, gồm `ci-cd.yml`), `trivy-action` 0.35.0 → v0.36.0 (5 chỗ). Hai action này gọi bên trong một action Node 20 |
| `f93ae788` / `ef8121a4` | Workflow tạm chạy thử `metadata` và `login`, đã hoàn tác |

Chỉ đổi dòng `uses:` (và 1 mục lockfile). Không đổi input, trigger hay logic job
nào.

**3. Bằng chứng** — base `1928f2ae`; subject `f72d44b546555f6686fa454077bcd51919c0e936`;
digest `c2b30d4303f3567000a99b91de5914dd79a1a68b6290bed25cfbefb7e6f0c5a2`.

- `actionlint`: vẫn đúng 22 cảnh báo cũ như main, không có cảnh báo mới.
- 69 bước đã sửa: mọi input đều có trong bản mới, mọi action đã nâng đều chạy Node 24.
- 9 payload Slack đọc được bằng js-yaml 5.5.0, đúng bộ đọc Slack v4 dùng.
- Workflow tạm: `metadata@v6` tạo đúng tag, `login@v4` báo "Login Succeeded".
- CI chạy đúng trên commit `f72d44b5`: 34/34 xanh. Trong đó có:
  - Docker Security Scan: `setup-buildx` v4, `build-push` v7, `upload-sarif` v4 và
    Trivy 0.70, không có lỗ hổng mới;
  - Test Coverage Report: `download-artifact` v8;
  - staging-smoke.
- Log Unit Tests và Docker Security Scan không còn cảnh báo Node 20.
- `npm audit`: 0 lỗ hổng.

**4. Công khai**

- Docker Security Scan từng đỏ 1 lần ở `ef8121a4`, tại bước `pecl install redis`
  ("No releases available", sau khoảng 22 giây treo). Đây là lỗi mạng tới
  pecl.php.net, không liên quan action nào. Chạy lại một lần thì xanh.
- `build-push` v7 thêm phần tóm tắt build vào trang kết quả CI, và tải lên một
  artifact "build record" (khoảng 100 KB) cho mỗi lần build.
- `trivy-action` v0.36.0 mặc định dùng trình quét Trivy 0.70.0. Từ nay cổng Trivy
  có thể bắt thêm lỗ hổng mà bản cũ chưa biết.
- Hồ sơ Gate 1 bỏ sót 2 action gọi Node 20 từ bên trong (codecov, trivy-action).
  Amendment 2 đã xử lý cả hai.

**5. Ảnh hưởng**

- CI không còn cảnh báo Node 20 ở các job đã kiểm tra.
- Workflow release, deploy và production chạy các bản action mới ở lần phát hành tới.
- Ứng dụng không đổi, trừ `follow-redirects` 1.16.1, một gói con của axios.

**6. Rủi ro còn lại** — Thấp:
- Bước Slack và các workflow release/deploy/production chưa chạy thật. Đã kiểm tra
  tĩnh, và bước Slack nào cũng có `continue-on-error: true`.
- Các action docker `metadata` và `login` chỉ được chạy thử bằng workflow tạm, chưa
  chạy trong workflow thật.

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-087 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy hay release; không chạy workflow release/production.
