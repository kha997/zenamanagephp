---
work_id: GAP-062
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
  spec: docs/audits/2026-10-06-gap-062-ssot-test-lint-false-green-evidence.md
  plan: null
  branch: docs/GAP-062-ssot-test-lint-false-green
  pr: https://github.com/kha997/zenamanagephp/pull/334
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-06T13:02:35+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-10-06: 'APPROVE GAP-062 Gate 3'. Given after the packet was presented at PR head b7c636b6b36f6e0316c9865006438a0b05905c2c with 33/33 exact-head checks green; bound to implementation subject db44ecfaf2e1e7481763132677c3b27e803a692c and implementation-tree digest 0610c418c0af00db147ec794b7fe04aed01fbcbf399d21cb30106adb86083f74 (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-06T12:21:39+07:00"
  updated_at: "2026-10-06T13:02:35+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "Option-1 implementation at subject db44ecfa: diff exactly the Gate-2 allowlist; local proofs (rg hidden -> exit 1 with guard message; new raw Role::create -> exit 1; blank lines above baselined lines -> pass; clean tree -> pass); baselines only grew in the three disclosed categories (raw_model_create 7->30 incl. one stale entry dropped, raw_model_create_feature 0->64, raw_model_create_zena 0->1), all others unchanged; exact-head CI run 37413461093 code-quality job prints ripgrep 14.1.0 and 'SSOT test lint passed' with zero 'rg: command not found'; disposable proof run 37413491370 fails code-quality on the planted Permission::create; 33/33 PR checks green; canonical digest computed at subject."
technical_evidence:
  base_sha: "1c2b01607c6ee014adad777a9f6656cd0e9298c4"
  subject_sha: "db44ecfaf2e1e7481763132677c3b27e803a692c"
  implementation_tree_digest: "0610c418c0af00db147ec794b7fe04aed01fbcbf399d21cb30106adb86083f74"
  verified_pr_head_sha: "db44ecfaf2e1e7481763132677c3b27e803a692c"
  verified_at: "2026-10-06T12:21:39+07:00"
owner_decision_binding:
  implementation_tree_digest: "0610c418c0af00db147ec794b7fe04aed01fbcbf399d21cb30106adb86083f74"
  decision_recorded_at: "2026-10-06T13:02:35+07:00"
---

# GAP-062 — Gate 3 release decision

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-10-06, bound to implementation subject
`db44ecfaf2e1e7481763132677c3b27e803a692c` and implementation-tree digest `0610c418c0af00db147ec794b7fe04aed01fbcbf399d21cb30106adb86083f74`. No deployment is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** `lint_tests.sh` (chạy trong CI chính qua `composer
ssot:lint`) luôn xanh vì máy CI thiếu `rg`; 99 vi phạm tích tụ; danh sách
endpoint "đã chết" sai (Gate 1).

**2. Sau thay đổi (đúng allowlist Gate 2, Phương án 1):**

- `scripts/ssot/lint_tests.sh`: `LC_ALL=C`; thiếu `rg` → exit 1 với thông
  báo rõ; so sánh baseline bỏ số dòng (in đầy đủ dòng vi phạm mới).
- `scripts/ssot/denylist_endpoints.txt`: bỏ `/api/dashboards`,
  `/api/widgets`, `/api/support/tickets` (route còn chạy); giữ 2 endpoint chết.
- Baselines: `raw_model_create` 7→30 (thêm 24, bỏ 1 dòng cũ
  `TaskDependenciesTest.php` không còn tồn tại), `raw_model_create_feature`
  0→64, `raw_model_create_zena` 0→1; `denylist_hits` vẫn 0 (10 hit hết nhờ
  sửa denylist); các baseline khác không đổi.
- `ci-cd.yml` (job code-quality) và `ci-cd-code-quality-debug.yml`: bước cài
  `ripgrep` trước `composer ssot:lint`.

**3. Khác biệt so với Gate 2** — Không có. Gate 2 dự kiến `raw_model_create`
"7→31"; thực tế 30 vì một mục baseline cũ đã không còn trong code (baseline
chỉ co lại ở mục đó, đúng quy tắc).

**4. Bằng chứng kỹ thuật**

- Base `1c2b0160`; subject `db44ecfaf2e1e7481763132677c3b27e803a692c`; digest `0610c418c0af00db147ec794b7fe04aed01fbcbf399d21cb30106adb86083f74`.
- Local (đã hoàn tác mọi thay đổi thử):
  - tree sạch → exit 0;
  - giấu `rg` khỏi PATH → `[ssot] ripgrep (rg) is required — refusing to
    report a false pass`, exit 1;
  - thêm `Role::create(` vào một Feature test → exit 1,
    `NEW raw_model_create_feature`;
  - chèn 3 dòng trống phía trên các dòng đã baseline trong
    `GAP042RbacProductionFidelityTest.php` → exit 0.
- `tests/Architecture` 26/26; YAML hai workflow parse OK; `bash -n` OK.
- **CI exact head** run `37413461093`, job code-quality `112106808562`:
  `ripgrep 14.1.0`, `SSOT test lint passed`, 0 dòng `rg: command not
  found` (trước đó: 10 dòng rồi "passed", run `37343750155`). 33/33 check xanh.
- **Proof CI thật** (nhánh dùng một lần `proof/GAP-062-new-violation`,
  commit `fbb442c4` = subject + một dòng `Permission::create(` trong
  `LegacyWidgetOwnershipTest.php`; `workflow_dispatch` run `37413491370`):
  job code-quality **failure** ở bước "Run SSOT Lint" với
  `NEW raw_model_create_feature violations` chỉ đúng dòng cài vào. Nhánh đã
  xoá, không phải tổ tiên của PR.

**5. Ngoài phạm vi** — Viết lại/gắn chú thích 89+1 dòng nợ trong baseline;
mã ứng dụng; deploy; cập nhật sổ (đối soát sau phát hành).

**6. Rủi ro còn lại** — Thấp. PR mới thêm vi phạm sẽ đỏ CI (đúng mục đích);
giới hạn đã nêu ở Gate 2: dòng trùng y hệt một dòng đã baseline trong cùng
file không bị báo.

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: Approve / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy hay việc dọn nợ trong baseline.
