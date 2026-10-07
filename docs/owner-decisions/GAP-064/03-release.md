---
work_id: GAP-064
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
  spec: docs/audits/2026-10-07-gap-064-treasury-s2-ledger-readiness.md
  plan: docs/superpowers/plans/2026-10-07-gap-064-treasury-s2-ledger-implementation.md
  branch: docs/GAP-064-treasury-s2-ledger
  pr: https://github.com/kha997/zenamanagephp/pull/337
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-07T13:54:14+07:00"
  updated_at: "2026-10-07T13:54:14+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "Treasury S2 at subject 94e6407d: immediate direct posting of funding/owner contribution/transfer/adjustment, document reversal + replacement link, derived balances, register, audit rows, duplicate warning, negative-balance guard; 68 Treasury feature tests green locally; real-MySQL two-process concurrency test passes in the new CI job (1 test / 4 assertions, run 37580004944) and a disposable lock-removal mutation makes it fail (run 37580049863, job 112657401208: both transfers succeeded); related suites green; SSOT lint, orphan routes, governance lint pass; first CI run red (PHPStan row typing, orphan-route URL helper) then fixed; 34/34 exact-head PR checks green; UI verified in a local run; disclosed allowlist additions; canonical digest computed at subject."
technical_evidence:
  base_sha: "fbdc7b1c0fb1b6594e5216495121429659b60c35"
  subject_sha: "94e6407dff2545b0f6e013a650c62a2b9c3f04bc"
  implementation_tree_digest: "197b51084bbfeb7492b79171e5f0f4d5a2356df15037446eb66140fdf1481e57"
  verified_pr_head_sha: "94e6407dff2545b0f6e013a650c62a2b9c3f04bc"
  verified_at: "2026-10-07T13:54:14+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-064 — Gate 3 release decision (Treasury S2 — ghi sổ)

## Gói quyết định phát hành

**1. Vấn đề là gì?** Có ví nhưng chưa ghi nhận được đồng tiền nào, không có số
dư (Gate 1).

**2. Sau thay đổi (Gate 2, Phương án A):**

- **Khai báo tiền nhận / góp vốn chủ** (X, PM/kỹ sư): ghi sổ ngay
  `posted_unreconciled`, cộng ví nhận. Góp vốn chủ chỉ từ đối tác loại "Chủ
  doanh nghiệp", báo cáo tách riêng.
- **Chuyển ví**: trừ ví nguồn, cộng ví đích, tổng tiền dự án không đổi. X
  chuyển từ mọi ví; PM/kỹ sư chỉ từ ví mình giữ.
- **Điều chỉnh** tăng/giảm (X, kế toán), bắt buộc lý do.
- **Đảo bút toán** (X, kế toán): một lần, không đảo bút toán đảo, đảo chiều đầy
  đủ, chứng từ gốc thành "Đã đảo" cùng lúc; gắn **chứng từ thay thế** (chỉ là
  liên kết truy vết).
- **Chặn số dư âm** khi chuyển ví / điều chỉnh giảm; đảo bút toán không bị chặn
  (số âm hiện đỏ).
- **Cảnh báo khai trùng** (cùng dự án, loại, số tiền, ngày, nguồn, đích, tham
  chiếu) — phải xác nhận mới ghi.
- **Số dư** từng ví, tổng dự án, nhà đầu tư nộp, vốn chủ góp; **sổ giao dịch**;
  **nhật ký** `audit_logs` cho mọi thao tác.
- Migration thêm `transaction_date`, `reference` (chỉ thêm cột).

**3. Khác biệt so với Gate 2 (công khai)**

- **Khoá:** ngoài khoá ví (bậc 0 mới), số dư được đọc bằng **truy vấn có khoá
  chia sẻ** trên các dòng sổ cái của ví (bậc 4 của v17), trước khi tạo chứng từ
  (bậc 5) — thứ tự 0 → 4 → 5, không đảo thứ tự đã duyệt. Lý do: đọc số dư
  không khoá có thể dùng ảnh chụp dữ liệu cũ và để lọt chi quá số dư.
- **Ghi sổ ngay:** chỉ lưu trạng thái cuối `posted_unreconciled`; đường đi
  `draft → submitted → approved → posted_unreconciled` (đồ thị đóng §2.1a) ghi
  trong nhật ký.
- **File ngoài danh sách Gate 2** (cần cho cam kết của chính Gate 2):
  - `TreasuryRuleViolation`, `TreasuryDuplicateSuspected` (ngoại lệ nghiệp vụ);
  - `database/migrations/classifications.json` (lệnh deploy bắt buộc mỗi migration
    được phân loại — `expand`);
  - bằng chứng tranh chấp trên MySQL thật (Gate 2 §7): lệnh ẩn
    `treasury:concurrency-test-transfer`, `tests/Feature/Concurrency/TreasuryTransferConcurrencyTest.php`,
    `scripts/ci/treasury-transfer-concurrency-mysql`, job CI mới
    `treasury-transfer-concurrency-mysql` trong `automated-testing.yml`, một
    dòng trong `scripts/ssot/baselines/skipped_tests_baseline.txt` — theo đúng
    mẫu job concurrency của Document workflow.
- Đổi tên tham số route thành `{treasuryDocument}` để test kiến trúc của
  module Document không nhận nhầm.
- CI lần đầu đỏ: PHPStan (kiểu dòng truy vấn) và lint route mồ côi (URL ghép
  trong test) — đã sửa (`94e6407d`).

**4. Bằng chứng kỹ thuật**

- Base `fbdc7b1c`; subject `94e6407dff2545b0f6e013a650c62a2b9c3f04bc`; digest `197b51084bbfeb7492b79171e5f0f4d5a2356df15037446eb66140fdf1481e57`; 27 file.
- Test Treasury: 68 test / 275 assertion xanh (dịch vụ ghi sổ 14 — kịch bản A,
  C, F, đảo bút toán, chặn số dư, quyền chuyển ví của Z, trùng, khoá posting
  key, nhật ký; API 5; web 5; S1 44).
- **Tranh chấp thật trên MySQL:** job "Treasury Transfer Concurrency (real
  MySQL)" (run `37580004944`): preflight MySQL OK, 1 test / 4 assertion xanh —
  hai tiến trình cùng chuyển 70 từ ví 100: đúng một thành công, ví còn 30.
- **Chứng minh phép thử không xanh giả:** nhánh dùng một lần bỏ cả hai khoá
  (`b9302dcf`, run `37580049863`, job `112657401208`): cả hai lệnh chuyển
  đều thành công → test **đỏ** "Exactly one transfer may succeed". Nhánh đã
  xoá, không phải tổ tiên của PR.
- Bộ liên quan xanh trên máy (Architecture, Deployment, Migrations/Models
  Treasury, OwnerGovernance, Zena, Seeders, RBAC GAP-042, Services); SSOT lint,
  route mồ côi, domain-ownership, governance lint đạt.
- Xem trực tiếp trên bản chạy local: số dư sau chuỗi nhận 1 tỷ → chuyển 50
  triệu → đảo 1 tỷ → nhận lại 100 triệu đúng (tổng 100 triệu, mỗi ví 50 triệu);
  sổ giao dịch hiển thị trạng thái; gắn chứng từ thay thế từ giao diện thành công.
- CI exact head `94e6407d`: 34/34 pass.

**5. Lưu ý**

- Phát hiện ngoài phạm vi: `ProductionBootstrapCommandTest` đỏ khi bất kỳ test
  dùng `RefreshDatabase` chạy trước `tests/Architecture` trong cùng tiến trình
  (tái hiện với `VendorApiTest`, không liên quan Treasury) — đã tách thành việc
  riêng.
- Giao diện: ô chọn chứng từ thay thế trong sổ giao dịch hơi hẹp (chỉ thẩm mỹ).
- Chứng từ giữ trạng thái "chưa đối soát" cho tới S4.

**6. Ngoài phạm vi** — Chi phí/duyệt chi (S3), trung gian + đối soát (S4), tạm
ứng (S5), báo cáo (S6), ví chung công ty, deploy.

**7. Rủi ro còn lại** — Thấp. Migration chỉ thêm cột; mọi bút toán bất biến.

**8. Hoàn tác** — Revert squash commit (migration có `down()`).

**9. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: Approve / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy, S3–S6.
