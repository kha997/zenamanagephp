---
work_id: GAP-092
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
  spec: docs/audits/2026-10-23-gap-092-smoke-server-evidence.md
  plan: docs/superpowers/plans/2026-10-23-gap-092-smoke-server.md
  branch: docs/GAP-092-smoke-server
  pr: https://github.com/kha997/zenamanagephp/pull/365
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-23T07:46:55+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-10-23, verbatim: 'APPROVE GAP-092 Gate 3'. Given after the re-presented packet (after Owner amendment 2) at PR head 93818c98535e51bf2b7c8dff36c448d92b0d5efe; bound to implementation subject f90fc994280dee91e8dd9e3a8b78327cd4ff6956 and implementation-tree digest fa08969a8f720ebe6cb604e028d7c1333273fa7ffc417bc5b1fb4f4ee2dd00f5 (recomputed at recording time, zero drift). Supersedes nothing beyond the earlier approval already recorded as superseded in this packet. Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment or release authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-23T01:28:57+07:00"
  updated_at: "2026-10-23T07:46:55+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "RE-PRESENTED after Owner amendment 2 (AskUserQuestion, 'Sửa trong PR này (Recommended)'): the Owner approval of 2026-10-23 (verbatim 'APPROVE GAP-092 Gate 3', bound to subject d1500ad2109b3f0d6d4a04f193d5cb3a90aff430 / digest 9393febc568665ef7206bb29d91033b8477f5ce11fb9cdbe5da7598dca8d2e25) is superseded because f90fc994 changed the tree to answer a Codex P2 finding. f90fc994 is documentation only: step 3 of the implementation plan now states the amended fault criterion, and 02-design.md records amendment 2; the code is unchanged from c3d209ab. GAP-092 at subject f90fc994280dee91e8dd9e3a8b78327cd4ff6956. d1500ad2 has a tree identical to implementation commit c3d209ab. c3d209ab (Gate 2 Option A): scripts/smoke_staging.sh gains SMOKE_SERVER (artisan default, fpm; any other value is a bootstrap failure). start_fpm_server writes /tmp/smoke-fpm.conf (pool on /tmp/smoke-fpm.sock, pm static 4, clear_env no, env[APP_ENV] = SMOKE_ENV_NAME, errors to the server log) and /tmp/smoke-nginx.conf (listen APP_HOST:APP_PORT, root public/, FastCGI to the socket, daemon off, all paths under /tmp). It starts php-fpm -F (the version matching the CLI, else php-fpm; -R only when run as root) as PHP_PID and nginx as NGINX_PID; cleanup stops both; the report names the server. staging-smoke.yml sets SMOKE_SERVER: fpm, installs nginx only if missing, runs the script once (the 3-attempt loop and its runner-image comment are removed) and uploads the nginx logs. Owner amendment (AskUserQuestion, 'Sửa tiêu chí chứng minh (Recommended)'): the local fault-tolerance criterion became 'after every php-fpm worker is killed the port never refuses a connection and every later request returns 200; the request in flight may fail', because a local run showed the in-flight request gets 502. Local: fpm run passes 4/4 checks; the run with all 4 workers killed by SIGSEGV just after readiness got one 502 on the in-flight health call, the master respawned the workers, and all later requests (login, me, projects, diagnostics) returned 200 with no refused connection; the default artisan run still passes 4/4; SMOKE_SERVER=bogus fails with a clear message. CI proof: temporary 69064d5d ran staging-smoke as a 16-copy matrix, one attempt each (run 39825250652): 16/16 green, with SMOKE_SERVER fpm and the nginx logs uploaded; every other check in that run was green; 69064d5d was reverted by d1500ad2. Baseline before: 8/30 runs needed a retry and 1 of 31 executions failed all 3 attempts. Static: bash -n passes; actionlint reports no findings. Exact-head CI at d1500ad2: every check green, including staging-smoke on php-fpm. Exact-head CI at f90fc994: 34/35 green, including staging-smoke on php-fpm and browser-tests; Owner Governance Lint red only because the packet was still bound to the superseded digest, which this re-presented packet resolves. Out of scope: nightly-matrix.yml still uses php artisan serve."
technical_evidence:
  base_sha: "84abd9a52cf7a22941a2435fcaf5dc5f856118c7"
  subject_sha: "f90fc994280dee91e8dd9e3a8b78327cd4ff6956"
  implementation_tree_digest: "fa08969a8f720ebe6cb604e028d7c1333273fa7ffc417bc5b1fb4f4ee2dd00f5"
  verified_pr_head_sha: "f90fc994280dee91e8dd9e3a8b78327cd4ff6956"
  verified_at: "2026-10-23T07:44:31+07:00"
owner_decision_binding:
  implementation_tree_digest: "fa08969a8f720ebe6cb604e028d7c1333273fa7ffc417bc5b1fb4f4ee2dd00f5"
  decision_recorded_at: "2026-10-23T07:46:55+07:00"
---

# GAP-092 — Gate 3 release decision (staging-smoke on php-fpm + nginx)

## OWNER GATE 3: APPROVED

Owner approved the re-presented Gate 3 in-session on 2026-10-23 ("APPROVE GAP-092 Gate 3"), bound to implementation subject
`f90fc994280dee91e8dd9e3a8b78327cd4ff6956` and implementation-tree digest `fa08969a8f720ebe6cb604e028d7c1333273fa7ffc417bc5b1fb4f4ee2dd00f5`. No deployment or release is authorized.

## Gói quyết định phát hành (trình lại)

Owner đã duyệt Gate 3 tại digest `9393febc…`. Sau đó Codex báo lỗi P2: file kế hoạch còn ghi tiêu chí cũ. Owner chọn
"Sửa trong PR này (Recommended)" (Owner amendment 2). Chỉ sửa tài liệu, code giữ nguyên, nhưng cây file đổi nên lần duyệt
trên **không còn hiệu lực**.

**1. Vấn đề là gì?** Trong staging-smoke, server `artisan serve` chết giữa chừng. 8/30 lần chạy phải chạy lại,
và có một lần check đỏ (Gate 1).

**2. Sau thay đổi (Gate 2 Phương án A)** — commit `c3d209ab`:
- Script smoke có chế độ `SMOKE_SERVER=fpm`: app chạy bằng nginx + php-fpm. Chạy trên máy cá nhân vẫn mặc định dùng `artisan serve`.
- Workflow bật chế độ fpm, cài nginx nếu runner chưa có và tải log nginx lên.
- Smoke **chỉ chạy một lần**, bỏ chạy lại 3 lần.
- Các bước kiểm tra trong smoke giữ nguyên.

**3. Bằng chứng** — base `84abd9a5`; subject `f90fc994280dee91e8dd9e3a8b78327cd4ff6956`;
digest `fa08969a8f720ebe6cb604e028d7c1333273fa7ffc417bc5b1fb4f4ee2dd00f5`.
- **16/16 bản staging-smoke xanh ngay lần đầu**
  ([run 39825250652](https://github.com/kha997/zenamanagephp/actions/runs/39825250652)). Với tỉ lệ hỏng cũ, khả năng
  16/16 xanh do may mắn chỉ khoảng 0,6%. Commit tạm `69064d5d` đã được hoàn tác.
- **Thử trên máy:**
  - chế độ fpm qua 4/4 bước kiểm tra;
  - cố ý làm crash cả 4 tiến trình php-fpm giữa chừng: request đang chạy nhận lỗi 502, cổng không lần nào từ chối,
    mọi request sau đều 200;
  - chế độ mặc định `artisan serve` vẫn qua.
- CI chạy đúng trên `d1500ad2`: **xanh toàn bộ**. Trên `f90fc994`: 34/35 xanh; riêng Owner Governance Lint đỏ vì gói còn gắn
  digest cũ, gói trình lại này xử lý lỗi đó.

**4. Công khai**
- **Owner amendment 2:** sửa file kế hoạch cho khớp tiêu chí đã sửa (theo Codex P2).
- **Owner amendment:** Owner chọn "Sửa tiêu chí chứng minh". Tiêu chí thử crash trên máy đổi từ "smoke vẫn xanh"
  thành "cổng không từ chối và mọi request sau crash đều 200". Request đang chạy lúc crash được phép lỗi.
- Không còn chạy lại: nếu hiếm hoi một worker php-fpm crash đúng lúc có request, smoke sẽ đỏ. Trong 16 bản chạy thử
  không lần nào xảy ra.
- `nightly-matrix.yml` vẫn dùng `artisan serve`; không thuộc GAP này.

**5. Ảnh hưởng** — Không còn phải chạy lại smoke khoảng 1/4 số lần. Lỗi thật đỏ ngay.

**6. Rủi ro còn lại** — Thấp (xem mục 4).

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-092 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy hay release; không sửa `nightly-matrix.yml`.
