---
work_id: GAP-067
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
  spec: docs/audits/2026-10-08-gap-067-treasury-s4a-reconciliation-readiness.md
  plan: docs/superpowers/plans/2026-10-08-gap-067-treasury-s4a-reconciliation.md
  branch: docs/GAP-067-treasury-s4a-reconciliation
  pr: https://github.com/kha997/zenamanagephp/pull/340
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-08T08:05:57+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-10-08, verbatim: 'APPROVE GAP-067 Gate 3'. Given after the packet (including the disclosed wider class-4 lock over sibling entries of the same documents, locking active-apply read, page path following the existing treasury URL pattern, read side in the reconciliation service, 422 for already-reconciled, and the UTC 'today' note left unfixed) was presented at PR head 98827fd3ff402d0da91809cf102827ff4eac617f; implementation subject 0239a5cce4f35e5e35d8ab57e12315bc76f97a53 had 34/34 exact-head checks green; bound to implementation-tree digest 05daaa7bf320046dd667fc456c47ae94b7b7aa837e079e7f1981ef0f1a684541 (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-08T06:01:24+07:00"
  updated_at: "2026-10-08T08:05:57+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "Treasury S4a at subject 0239a5cc: per-ledger-entry reconciliation (apply / undo line / undo whole) with v17 §11 class-4 then class-5 locks, §12.1 promotion of direct documents, §12.2 regression except reversed documents, route-leg entries rejected, type/reference/date rules, undo reason in audit_logs; per-wallet reconciled/unreconciled balances, unreconciled list, history, register status filter; API + operator web page; no migration. 108 Treasury feature tests green locally (20 new). Real-MySQL race on one ledger entry passes in CI (Treasury concurrency job 3 tests, run 37695741028, job 113046900732) and a disposable removal of the class-4 lock and locking read makes it fail (run 37695760592, job 113046964992: both processes OK). Full PHPStan clean; exact-head PR checks 34/34 green; UI verified in a local run; canonical digest computed at subject."
technical_evidence:
  base_sha: "a0381f5e3d5abaa37dc2f21f7099a09e6234ab2d"
  subject_sha: "0239a5cce4f35e5e35d8ab57e12315bc76f97a53"
  implementation_tree_digest: "05daaa7bf320046dd667fc456c47ae94b7b7aa837e079e7f1981ef0f1a684541"
  verified_pr_head_sha: "0239a5cce4f35e5e35d8ab57e12315bc76f97a53"
  verified_at: "2026-10-08T06:01:24+07:00"
owner_decision_binding:
  implementation_tree_digest: "05daaa7bf320046dd667fc456c47ae94b7b7aa837e079e7f1981ef0f1a684541"
  decision_recorded_at: "2026-10-08T08:05:57+07:00"
---

# GAP-067 — Gate 3 release decision (Treasury S4a — đối soát)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-10-08 ("APPROVE GAP-067 Gate 3"), bound to implementation subject
`0239a5cce4f35e5e35d8ab57e12315bc76f97a53` and implementation-tree digest `05daaa7bf320046dd667fc456c47ae94b7b7aa837e079e7f1981ef0f1a684541`. No deployment is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Mọi giao dịch từ S2/S3 ở "chưa đối soát" và không có cách
xác nhận chúng khớp với tiền thật trong tài khoản/quỹ (Gate 1).

**2. Sau thay đổi (Gate 2, Phương án A — không đổi cơ sở dữ liệu):**

- **Đối soát một ví** (X, kế toán): trang `Đối soát` của từng ví liệt kê các
  giao dịch chưa đối soát; tick các dòng khớp, chọn loại (sao kê ngân hàng /
  kiểm quỹ tiền mặt / chứng từ khác), số tham chiếu (bắt buộc trừ kiểm quỹ),
  ngày (không ở tương lai) → lưu. Một dòng sai làm cả yêu cầu bị từ chối.
- Chứng từ chuyển sang **"Đã đối soát"** khi mọi bút toán của nó đã đối soát;
  chuyển ví cần cả hai ví.
- **Gỡ đối soát** từng dòng hoặc cả lần, bắt buộc lý do (lưu ở nhật ký hệ
  thống, hiện trong lịch sử); chứng từ quay về "chưa đối soát", trừ chứng từ
  đã đảo (giữ "Đã đảo"). Gỡ xong có thể đối soát lại.
- **Không chặn đảo:** chứng từ đã đối soát vẫn đảo được; bút toán đảo là giao
  dịch mới, chưa đối soát. Bút toán của chứng từ đã đảo vẫn đối soát được.
- **Hiển thị:** mỗi ví có "Đã đối soát / Chưa đối soát" trên trang ngân quỹ dự
  án và trang đối soát; sổ giao dịch có bộ lọc trạng thái; lịch sử đối soát
  (loại, tham chiếu, ngày, người làm, các dòng, dòng đã gỡ + người gỡ + lý do).
  PM/kỹ sư/người xem: chỉ xem. Số dư không đổi.
- **API** `/api/zena/projects/{project}/treasury/…`: `GET wallets/{wallet}/reconciliation`,
  `POST wallets/{wallet}/reconciliations`, `GET reconciliations`,
  `POST reconciliations/{id}/undo`, `POST reconciliation-entries/{id}/undo`.

**3. Khác biệt so với Gate 2 (công khai)**

- **Khoá lớp 4 rộng hơn một chút:** ngoài các bút toán được chọn, khoá luôn mọi
  bút toán khác của cùng chứng từ (một câu lệnh, id tăng dần). Lý do: nếu hai
  người đối soát hai ví của **cùng một lần chuyển ví** cùng lúc, chỉ khoá dòng
  được chọn sẽ gây khoá chéo (deadlock) hoặc chứng từ không bao giờ thành "Đã
  đối soát". Thứ tự khoá vẫn 4 → 5, không lấy khoá ví (0) hay chi phí (2).
- **Kiểm tra "đã có đối soát còn hiệu lực"** dùng đọc có khoá (cùng lý do như
  S2/S3: đọc không khoá có thể dùng ảnh chụp dữ liệu cũ).
- **Đường dẫn trang:** `/operator/projects/{project}/treasury/wallets/{wallet}/reconcile`
  (theo mẫu URL ngân quỹ hiện có), thay cho `/operator/treasury/projects/…` ghi
  trong Gate 2.
- **Đọc** (số dư đã/chưa đối soát, danh sách, lịch sử) nằm trong
  `TreasuryReconciliationService`; `TreasuryBalanceService` không đổi.
- Lỗi "đã đối soát rồi" trả **422** (không dùng 409).
- **Lưu ý múi giờ:** ứng dụng chạy UTC, nên "ngày đối soát ≤ hôm nay" tính theo
  ngày UTC. Từ 0h đến 7h sáng giờ Việt Nam, chọn ngày hôm nay sẽ bị từ chối
  (chọn ngày hôm trước vẫn được). Không sửa trong Work ID này; Owner có thể yêu
  cầu sửa (tính "hôm nay" theo giờ Việt Nam).

**4. Bằng chứng kỹ thuật**

- Base `a0381f5e`; subject `0239a5cce4f35e5e35d8ab57e12315bc76f97a53`; digest
  `05daaa7bf320046dd667fc456c47ae94b7b7aa837e079e7f1981ef0f1a684541`; 18 file
  (không migration).
- Test Treasury: 108 test / 544 assertion (mới 20: dịch vụ 12 — một ví, chuyển
  ví cần hai ví, chọn một phần, không đối soát hai lần + một dòng sai từ chối
  cả yêu cầu, ví/dự án/tenant khác, bút toán tuyến tiền, loại/tham chiếu/ngày,
  gỡ dòng, gỡ cả lần + lịch sử lý do, gỡ trên chứng từ đã đảo, đối soát chứng
  từ đã đảo, gỡ từ dự án khác; API 4; web 4).
- **Tranh chấp thật trên MySQL** (run `37695741028`, job `113046900732`): 3 test
  xanh — hai tiến trình cùng đối soát một bút toán: đúng một thành công, đúng
  một dòng `apply`, chứng từ "Đã đối soát".
- **Không xanh giả:** nhánh dùng một lần bỏ khoá lớp 4 và đọc có khoá
  (`61f73a98`, run `37695760592`, job `113046964992`): test đối soát **đỏ**
  "Exactly one reconciliation may succeed" (cả A và B đều OK); hai test cũ vẫn
  xanh. Cũng đỏ khi chạy cục bộ trên MySQL 8.0.
- PHPStan toàn repo sạch; SSOT lint, route mồ côi, domain-ownership, governance
  lint, docs-lint, baseline guard đạt.
- Bộ liên quan: Architecture, Deployment, OwnerGovernance, Zena, Services, Models
  chạy cục bộ; các lỗi gặp phải đều không do thay đổi này
  (`ProductionBootstrapCommandTest` cũng lỗi trên `main` khi chạy chung bộ;
  `GrandfatherLoaderTest::test_unreadable_file_throws` do chạy bằng root;
  3 test PDF `DocumentTemplateRenderTest` do môi trường cục bộ). Trên CI các bộ
  này xanh.
- Xem trên bản chạy local (MySQL): ví "TK công ty" có 200 + 50 triệu nhận và
  chuyển 30 triệu sang "Quỹ công trường"; đối soát hai dòng (200 triệu và −30
  triệu) với sao kê `SK-T10/2026`, gỡ dòng 200 triệu với lý do "Sai số tham
  chiếu" → lịch sử hiện "Đã gỡ bởi Chủ DN — lý do: Sai số tham chiếu", số dư
  220 / đã đối soát −30 / chưa đối soát 250 triệu; chuyển ví vẫn "chưa đối
  soát" vì ví đến chưa đối soát.
- CI exact head `0239a5cc`: 34/34 pass.

**5. Ngoài phạm vi** — Tuyến tiền/trung gian và truy vết thanh toán hợp đồng
(S4b), tạm ứng (S5), báo cáo (S6), nhập file sao kê, khoá kỳ, deploy.

**6. Rủi ro còn lại** — Thấp. Không đổi lược đồ; dòng đối soát bất biến (gỡ =
thêm dòng `reverse`); số dư không đổi.

**7. Hoàn tác** — Revert squash commit (không có migration).

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-067 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy, S4b–S6.
