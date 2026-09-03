---
work_id: GAP-049
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-09-03-gap-049-production-readiness-evidence.md
  plan: null
  branch: worktree-agent-a8ae472e9023bf843
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-03T02:16:53Z"
  owner_response_reference: "GAP-049 Gate 1 Round 1 (relayed via coordinator session, reviewed exact PR head 456fc625ee90b425046ac01755efee20bffcc354 of PR #300, canonical main at review time 0872ac856932193a037ce30f00050179374811af): 'Owner Gate-1 Round 1 decision: CHANGES REQUESTED on PR #300 (pre-correction head 456fc625ee90b425046ac01755efee20bffcc354). The overall problem statement is ACCEPTED as directionally correct — do NOT redo the audit from scratch, do NOT start Gate 2, do NOT write an implementation plan, do NOT touch deployment workflows/app code/secrets/infra, do NOT deploy, do NOT self-approve. This round is narrowly about evidence truthfulness and deployment-surface reconciliation in the two existing docs.' Owner directed 8 corrections, all addressed in this re-presentation: (1) deploy.yml's secret contract was wrongly generalized as sharing production.yml's 4-secret gate — corrected to its actual 3-secret gate (no PRODUCTION_URL, no post-deploy health check), target /var/www/zenamanage, invocation via ./deploy.sh production, and a new per-workflow secret-contract table was added. (2) automated-deployment.yml was materially under-described — corrected with a full line-by-line re-read covering deploy-staging/deploy-production/rollback/blue-green-deployment/canary-deployment jobs, the PRODUCTION_USERNAME vs PRODUCTION_USER naming inconsistency (not silently normalized), Docker Buildx/GHCR build-push, /opt/zenamanage, the pre-deploy ./docker-manage.sh backup hook, migrate --force, cache rebuilds, and multi-endpoint health/smoke/performance checks, classified as IMPLEMENTED-IN-REPO vs EXECUTED/PROVEN-IN-REAL-ENVIRONMENT. (3) the deployment-surface inventory was reorganized into fixed categories A (executable workflows, 3), B (placeholder jobs, 1), C (underlying scripts/topologies invoked by A), D (infrastructure not independently invoked), with explicit path/secret-name/health-check contradictions surfaced. (4) the 'no workflow runs a backup' claim was corrected — automated-deployment.yml calls ./docker-manage.sh backup, whose create_backup()/restore_backup() implementation was read in full and reported with 6 separated epistemic claims (hook exists / ever executed / artifacts valid / retention proven / restore implemented / restore drill succeeded), none conflating code-existence with proven recoverability. (5) the global 'no rollback exists' claim was split per workflow: production.yml/deploy.yml still have none; automated-deployment.yml's rollback job code was read in full and classified as code-only (git reset --hard HEAD~1, no migration-down step) and flagged unsafe-by-default against forward schema migrations, never executed per Actions history. (6) the absolute 'never deployed' claim was replaced, after an exhaustive review of all available Actions run history for all three candidate production workflows (269 + 249 + 151 = 669 runs, every run's job-level step conclusions checked via the GitHub API), with the evidence-bounded statement that no successful real production deployment was proven by the repository and Actions evidence reviewed, explicitly noting retention-window and API-completeness caveats. (7) the migration-locking claim was corrected after checking composer.json (Laravel ^12.0, migrate --isolated available but never used anywhere in this repo's workflows/scripts) and confirming no concurrency: groups or flock mechanisms exist — corrected finding: no explicit cross-process migration/deployment isolation was proven. (8) the Gate-2 architecture recommendation was explicitly relabeled as a Gate-2 design candidate, not Owner-approved architecture, with an explicit statement that Gate 2 must compare the corrected deployment surfaces (including automated-deployment.yml's now-accurately-described Docker/GHCR path) before locking any target architecture. Correction scope strictly bounded to docs/audits/2026-09-03-gap-049-production-readiness-evidence.md and this file; no app/**, src/**, routes/**, .github/workflows/**, database/**, config/** change authorized or made. PR #300 remains Draft throughout; this packet remains gate_status: awaiting_owner / owner_decision.value: none pending a fresh Owner Gate-1 decision on the corrected re-presentation."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-03T01:18:13Z"
  updated_at: "2026-09-03T02:16:53Z"
generated_by: agent
---

## Owner Decision History — Round 1 — CHANGES REQUESTED (permanent record, never erased)

**Owner Gate 1 Round 1 decision: CHANGES REQUESTED** (not a rejection — reviewed exact PR head `456fc625ee90b425046ac01755efee20bffcc354`, canonical main `0872ac856932193a037ce30f00050179374811af`). The overall problem statement was **accepted as directionally correct**; the correction was narrowly scoped to evidence truthfulness and deployment-surface reconciliation. Full verbatim directive preserved in this file's frontmatter `decision_provenance.owner_response_reference` above. Eight corrections were directed and are addressed in this re-presentation (see the audit document's now-corrected §1, §2, §6, §7, §10, §13, §14, §16): (1) `deploy.yml`'s secret contract was wrongly generalized as matching `production.yml`'s 4 secrets; (2) `automated-deployment.yml` was materially under-described (its full staging/production/rollback/blue-green/canary/backup surface); (3) the deployment-surface count was inconsistent across the document; (4) "no workflow runs a backup" was factually wrong; (5) "no rollback exists" was an over-broad global claim; (6) "never deployed" was asserted without exhaustive evidence; (7) a migration-locking guarantee was claimed without proof; (8) the Gate-2 architecture recommendation risked reading as pre-approved. This Round 1 record is preserved permanently and must not be removed by any future revision.

---

## Owner Summary

ZENA has no proven real production deployment: across an exhaustive review of all available Actions run history (669 runs total) for the three workflows that could reach a real host, zero runs show any real deployment step executing with a non-skipped conclusion — every one reports a misleadingly green (or occasionally a misleadingly job-level-failed-with-zero-steps) result while the actual deploy logic is gated behind secrets/conditions that were never satisfied. This request asks the Owner to confirm the problem is real and worth solving, and to authorize a Gate-2 design phase for a truthful, recoverable path to a first controlled deployment.

## Vấn đề vận hành

Không ai có thể phân biệt "chưa cấu hình deploy" với "đã thử và thất bại" chỉ bằng cách nhìn CI: cả 3 workflow có thể chạm tới host thật (`production.yml`, `deploy.yml`, `automated-deployment.yml`) đều báo xanh (hoặc đôi khi báo lỗi ở cấp job nhưng không có bước thật nào chạy) trong khi bước deploy thật luôn bị `skipped` — đã kiểm tra toàn bộ 669 lần chạy Actions còn lưu lại, không phải suy đoán từ vài lần gần nhất. Ba workflow này còn mâu thuẫn nhau về đường dẫn host (`/var/www/zena` vs `/var/www/zenamanage` vs `/opt/zenamanage`), tên secret (`PRODUCTION_USER` vs `PRODUCTION_USERNAME`), và hợp đồng health-check (đầy đủ / hoàn toàn không có / đa điểm). Endpoint `/api/health` mà `production.yml` dùng để xác nhận thành công là dữ liệu cứng (hardcoded), không thật sự kiểm tra database/cache/queue.

## Người dùng bị ảnh hưởng

- Chủ dự án / Owner: đang tin tưởng nhầm rằng vì CI xanh nên hệ thống "đã sẵn sàng deploy" hoặc "đã deploy", trong khi thực tế chưa từng deploy thật lần nào.
- Đội vận hành tương lai (operator đầu tiên dùng ZENA thật): sẽ là người đầu tiên gặp mọi lỗ hổng chưa được phát hiện trong quy trình deploy (không có rollback, không có atomic release, health check giả).
- Bất kỳ ai sau này đọc lịch sử CI và kết luận sai rằng production đã từng chạy thành công.

## Bằng chứng

Bằng chứng đầy đủ, có trích dẫn file/dòng cụ thể và kết quả `gh run view --json jobs` trực tiếp (không suy đoán), nằm trong `docs/audits/2026-09-03-gap-049-production-readiness-evidence.md`. Tóm tắt các điểm cốt lõi:

1. Rà soát toàn bộ 269 lần chạy `production.yml` + 249 lần chạy `deploy.yml` + 151 lần chạy `automated-deployment.yml` (669 lần, toàn bộ lịch sử Actions còn lại qua GitHub API) cho thấy **0 lần** bất kỳ bước deploy thật nào (SSH git-pull, Docker build/push, `docker-compose exec`, `artisan migrate`, backup, health/smoke/performance check, rollback, blue-green, canary) có kết luận khác `skipped`.
2. Đọc trực tiếp nội dung `routes/api.php` cho thấy handler `/health` (đường dẫn chính xác mà `production.yml` gọi `curl -f $PRODUCTION_URL/api/health`) trả về chuỗi cứng `'database' => 'connected'`, không gọi bất kỳ kiểm tra thật nào.
3. Ba workflow (`production.yml`, `deploy.yml`, `automated-deployment.yml`) mâu thuẫn nhau về đường dẫn host (`/var/www/zena` vs `/var/www/zenamanage` vs `/opt/zenamanage`), tên secret (`PRODUCTION_USER` vs `PRODUCTION_USERNAME`), và hợp đồng health-check — không có quyết định nào ghi nhận cơ chế nào là chính thức. `docker-compose.prod.yml` (12 service) được `automated-deployment.yml` gọi tới thật (không phải hạ tầng chết như bản trước từng ghi nhầm), nhưng chưa từng thấy bằng chứng chạy thật.
4. `automated-deployment.yml` có gọi `./docker-manage.sh backup` (bản trước ghi nhầm là "không workflow nào chạy backup") — đọc toàn bộ `create_backup()`/`restore_backup()` cho thấy cả backup lẫn restore đều được cài đặt đầy đủ, nhưng bước gọi backup này luôn `skipped` trong mọi lần chạy đã kiểm tra.
5. `automated-deployment.yml` có job `rollback` với code thật (`git reset --hard HEAD~1`), nhưng đọc kỹ cho thấy đây là rollback chỉ ở mức code, không có bước hạ migration nào — nếu lần deploy trước có migration tiến, rollback này để lại schema và code lệch pha nhau, không an toàn theo mặc định; job này cũng chưa từng thực thi thật lần nào.

## Tác động nếu không xử lý

Nếu triển khai thật mà không sửa các lỗ hổng này trước: một lần deploy lỗi giữa chừng (composer/npm/migration fail) sẽ để lại code và schema ở trạng thái nửa vời, không có cách rollback đã định nghĩa, không có chế độ bảo trì (`artisan down`), và endpoint health-check sẽ vẫn báo "khỏe mạnh" ngay cả khi database/cache/queue thực sự đã hỏng — nghĩa là đội vận hành có thể tin production đang chạy tốt trong khi nó không hề.

## Phạm vi đề xuất

GAP-049 sẽ (qua các Gate tiếp theo, không phải Gate này) chọn MỘT cơ chế deploy làm chính thức, thiết kế lại nó để có atomic release + rollback thật, sửa health-check để kiểm tra thật, xác lập hợp đồng biến môi trường production đầy đủ, và thiết kế (không triển khai) một chuỗi smoke-test chấp nhận cho lần deploy thật đầu tiên.

## Loại trừ rõ ràng

- Không đụng đến ngữ nghĩa nghiệp vụ CRM/Lead/Opportunity/Quote/Contract/Project/Service-Line — nếu chuỗi smoke-test chấp nhận cần chạm vào các phần đó, việc đó phải tách thành Work ID riêng qua Design Dependency Preflight.
- Không tái mở phạm vi GAP-042 (RBAC production fidelity) — đã đóng và release.
- Gate 1 này không đề xuất giải pháp kỹ thuật, không chọn kiến trúc, không cấu hình secret thật, không deploy thật, không viết implementation plan.

## Đề xuất

Đội đề xuất: xử lý ngay ở Gate 2 (không defer), vì rủi ro "CI xanh giả" đang tồn tại ngay bây giờ trên `main` và có thể khiến bất kỳ ai — kể cả Owner — hiểu sai trạng thái deploy thật của hệ thống.

## Decision Needed

Owner chọn một: Approve để tiến sang Gate 2 (thiết kế kiến trúc deploy + hardening) / Yêu cầu thay đổi phạm vi bằng chứng / Từ chối.

## What the owner is NOT being asked to decide

Owner không được yêu cầu chọn kiến trúc deploy cụ thể (VPS đơn/Docker Compose/registry), không được yêu cầu cung cấp host/domain/credentials ngay bây giờ, không được yêu cầu phê duyệt bất kỳ thay đổi code/workflow/migration nào — Gate 1 này chỉ hỏi liệu vấn đề có thật và đáng giải quyết hay không, và liệu phạm vi bằng chứng trong tài liệu audit có đủ và đúng hay không.
