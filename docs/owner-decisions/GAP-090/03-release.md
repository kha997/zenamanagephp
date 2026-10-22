---
work_id: GAP-090
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
  spec: docs/audits/2026-10-22-gap-090-dusk-flakes-evidence.md
  plan: docs/superpowers/plans/2026-10-22-gap-090-dusk-flakes.md
  branch: docs/GAP-090-dusk-flakes
  pr: https://github.com/kha997/zenamanagephp/pull/363
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
  created_at: "2026-10-22T10:34:53+07:00"
  updated_at: "2026-10-22T10:34:53+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-090 at subject b8cfebfaa2ecd08b639f6e50e22ac8d4662795d8 (tree identical to implementation commit 673f0953). 673f0953 (Gate 2 Option A): button-tests.yml job browser-tests serves the app with php-fpm (pool on /tmp/dusk-fpm.sock, pm static 4) behind nginx on 127.0.0.1:8000 instead of php -S and its supervisor loop; the healthcheck, diagnostics and artifacts follow the php-fpm and nginx pid and log files; Dusk runs once (2-attempt loop removed); ProjectCreateTest cancel flow runs window.scrollTo(0, 0) before clicking @project-cancel. Deviation disclosed: the ini overrides (pcov/xdebug off, opcache off, pcre.jit=0, 120 s limits) are passed with -d on the php-fpm command line, not as php_admin_value in the pool, because php-fpm can fail to start workers when a pool sets a value for an ini directive PHP does not know. Proof: temporary 6db41ad0 ran browser-tests as an 8-copy matrix (run 39709457805), each copy first killing all 4 php-fpm workers with SIGSEGV and probing port 8000 (HTTP 200 on the first probe, SIGSEGV logged); 8/8 copies passed Dusk (26 tests) in one attempt, against a measured 12/25 first-attempt failure rate before. One copy took 33 min, with every test about twice as slow, so the slowness is attributed to the runner; others 14-19 min. b8cfebfa reverted the proof. Local: nginx + php-fpm served static files with the right content type and PHP with APP_ENV=testing; SIGSEGV of every worker left the port answering 200. Static: actionlint no findings; lint-mysql-claim-truthfulness passes. Exact-head CI at b8cfebfa: browser-tests green on the first attempt (17 min); Security Vulnerability Scan failed once because registry.npmjs.org returned 503 to npm audit (not this PR; commented on the PR) and was re-run. Pre-existing, not touched: pint concat_space finding in ProjectCreateTest.php."
technical_evidence:
  base_sha: "90542b0d6ee1cc4aa7019746942425109d6e2563"
  subject_sha: "b8cfebfaa2ecd08b639f6e50e22ac8d4662795d8"
  implementation_tree_digest: "d3dbcfe59df41d5c08a937607f68ccca79d3a30e5208898305cdfdc3ef381f01"
  verified_pr_head_sha: "b8cfebfaa2ecd08b639f6e50e22ac8d4662795d8"
  verified_at: "2026-10-22T10:34:53+07:00"
owner_decision_binding:
  implementation_tree_digest: "d3dbcfe59df41d5c08a937607f68ccca79d3a30e5208898305cdfdc3ef381f01"
  decision_recorded_at: null
---

# GAP-090 — Gate 3 release decision (Dusk browser-tests flakes)

## OWNER GATE 3: AWAITING OWNER

## Gói quyết định phát hành

**1. Vấn đề là gì?** 12/25 lần chạy gần đây, job browser-tests (Dusk) hỏng ở lần đầu và phải chạy lại,
mất thêm khoảng 18 phút. Nguyên nhân: server `php -S` bị crash, và nút Hủy bị topbar che (Gate 1).

**2. Sau thay đổi (Gate 2 Phương án A):**

| Commit | Thay đổi |
|---|---|
| `673f0953` | Job browser-tests chạy app bằng **nginx + php-fpm** thay `php -S`. Dusk chạy **một lần**, bỏ lần chạy lại. Test Hủy cuộn trang về đầu trước khi click |
| `6db41ad0` / `b8cfebfa` | Commit tạm để thử, rồi hoàn tác |

**3. Bằng chứng** — base `90542b0d`; subject `b8cfebfaa2ecd08b639f6e50e22ac8d4662795d8`;
digest `d3dbcfe59df41d5c08a937607f68ccca79d3a30e5208898305cdfdc3ef381f01`.

- **8/8 bản chạy song song đều xanh ngay lần đầu**
  ([run 39709457805](https://github.com/kha997/zenamanagephp/actions/runs/39709457805)). Trước thay đổi, tỉ lệ hỏng
  là 48%. Nếu tỉ lệ đó vẫn còn, khả năng 8/8 xanh chỉ khoảng 0,5%.
- **Server chịu được crash:** trước khi chạy Dusk, mỗi bản cố ý làm crash cả 4 tiến trình php-fpm. Lần gọi ngay
  sau đó vẫn trả HTTP 200.
- CI chạy đúng trên commit `b8cfebfa`: browser-tests xanh ngay lần đầu, 17 phút.
- `actionlint` không có cảnh báo; lint MySQL của CI vẫn qua.

**4. Công khai**

- **Lệch nhỏ so với thiết kế:** các tùy chọn PHP (tắt pcov, tắt PCRE JIT, giới hạn 120 giây) được truyền bằng `-d`
  khi khởi động php-fpm, không đặt trong file cấu hình pool. Lý do: php-fpm có thể không khởi động được worker nếu
  pool đặt một tùy chọn mà PHP không nhận ra. Kết quả như nhau.
- Một bản trong 8 bản mất 33 phút: mọi test đều chậm khoảng gấp đôi, không có lần chạy lại. Có vẻ do runner chậm.
- Security Vulnerability Scan đỏ một lần trên `b8cfebfa` vì npm registry trả lỗi 503. Không phải do PR này; đã
  ghi chú lên PR và chạy lại.
- `pint` báo một lỗi khoảng trắng có sẵn trong `ProjectCreateTest.php`; không sửa ở đây.

**5. Ảnh hưởng**

- Job browser-tests thường mất khoảng 17 phút, không còn lần chạy 35 phút.
- Nếu Dusk hỏng, CI đỏ ngay, không còn bị lần chạy lại che đi.

**6. Rủi ro còn lại** — Thấp: nếu còn lỗi chập chờn hiếm gặp, nó sẽ làm đỏ CI thay vì bị che. Khi đó mở GAP mới.

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-090 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy hay release; không sửa các bộ test Dusk khác mà job này không chạy.
