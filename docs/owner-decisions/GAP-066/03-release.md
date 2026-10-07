---
work_id: GAP-066
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
  spec: docs/audits/2026-10-07-gap-066-treasury-s3-expenses-readiness.md
  plan: docs/superpowers/plans/2026-10-07-gap-066-treasury-s3-expenses-implementation.md
  branch: docs/GAP-066-treasury-s3-expenses
  pr: https://github.com/kha997/zenamanagephp/pull/339
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
  created_at: "2026-10-07T23:16:02+07:00"
  updated_at: "2026-10-07T23:16:02+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "Treasury S3 at subject 3f780adc: expense draft/submit/approve(=post)/reject(terminal)/copy, mandatory cost allocation with v17 §6.3 cap, atomic ContractExpense creation, self-approval recorded and reported, expense reversal with §2.2b coupling; 88 Treasury feature tests green locally; real-MySQL race on one cost's cap from two wallets passes in CI (Treasury concurrency job: 2 tests / 6 assertions, run 37647761355) and a disposable cost-lock removal makes it fail (run 37617655931, job 112779812677); first CI run red on 3 PHPStan errors (fixed); exact-head PR checks green; UI verified in a local run; canonical digest computed at subject."
technical_evidence:
  base_sha: "7e783c675b49314c5201d1781c8fdec478b4accd"
  subject_sha: "3f780adcae723db0e29594ed3d5ef7f405d172cb"
  implementation_tree_digest: "7cb018c5bbb37b7656c44e7217f5c87a721305298cce4d210cdfec60046de658"
  verified_pr_head_sha: "3f780adcae723db0e29594ed3d5ef7f405d172cb"
  verified_at: "2026-10-07T23:16:02+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-066 — Gate 3 release decision (Treasury S3 — chi phí và duyệt chi)

## Gói quyết định phát hành

**1. Vấn đề là gì?** Tiền chi cho chi phí dự án chưa được ghi nhận (Gate 1).

**2. Sau thay đổi (Gate 2, Phương án A):**

- **Nháp → gửi duyệt** (PM/kỹ sư, X): chọn ví (Z chỉ ví mình giữ), người nhận,
  số tiền, ngày, tham chiếu; gắn vào chi phí hợp đồng / dòng phiếu nhập có sẵn
  hoặc tạo mới một chi phí hợp đồng; tổng phần gắn = số tiền chi. Phần gắn dự
  định lưu ở cột mới `expense_plan`.
- **Duyệt = ghi sổ** (X, kế toán): khoá ví → chi phí → đọc có khoá → chứng từ;
  tạo chi phí hợp đồng mới cùng giao dịch; chặn trả vượt chi phí (§6.3) và chặn
  thiếu tiền; trừ ví; ghi phần đã trả; nhật ký duyệt (`approval_mode`).
- **Tự duyệt** chỉ X; ghi `self_approval`, tổng "Chi tự duyệt" tách riêng, nhãn
  "Tự duyệt" trong sổ.
- **Từ chối** (bắt buộc lý do) là trạng thái cuối; **sao chép thành nháp mới**.
- **Đảo khoản chi** gỡ phần đã trả (§2.2b); chi phí hợp đồng vẫn giữ.
- **Hiển thị:** "Đã chi", "Trong đó tự duyệt", bảng "Chi phí phải trả" (phát
  sinh / đã trả / còn lại), hàng "Khoản chi chờ xử lý"; API tương ứng.

**3. Khác biệt so với Gate 2 (công khai)**

- Lượng "đã trả" lúc duyệt được đọc **có khoá chia sẻ** (cùng lý do ở S2: đọc
  không khoá có thể dùng ảnh chụp dữ liệu cũ) — thứ tự khoá vẫn 0 → 2 → 4 → 5.
- Sổ giao dịch nay chỉ hiện chứng từ đã ghi sổ / đã đảo (nháp, chờ duyệt, bị từ
  chối nằm ở mục "Khoản chi chờ xử lý").
- `TreasuryPostingService`: `lockWallet`, `requireBalance`, `postEntries`,
  `audit` chuyển thành public để dịch vụ chi phí dùng chung; dữ liệu nhật ký
  cho phép ghi đè `status_path`.
- Route hành động chi phí trên web gắn `rbac:treasury.view`; quyền cụ thể từng
  hành động (gửi / duyệt / từ chối / sao chép) do policy kiểm tra.
- Phép thử tranh chấp mới là phương thức thứ hai trong
  `tests/Feature/Concurrency/TreasuryTransferConcurrencyTest.php` + lệnh ẩn
  `treasury:concurrency-test-approve-expense` (job CI có sẵn).
- CI lần đầu đỏ 3 lỗi PHPStan (`1703a5dd`); nhãn loại chi phí sang tiếng Việt
  (`3f780adc`).

**4. Bằng chứng kỹ thuật**

- Base `7e783c67`; subject `3f780adcae723db0e29594ed3d5ef7f405d172cb`; digest `7cb018c5bbb37b7656c44e7217f5c87a721305298cce4d210cdfec60046de658`; 22 file.
- Test Treasury: 88 test / 381 assertion (dịch vụ chi phí 12 — kịch bản D/E,
  trả góp, một lần trả nhiều chi phí, chặn vượt, tạo chi phí hợp đồng cùng lúc,
  kế hoạch sai, ví của Z, thiếu tiền, từ chối + sao chép, đảo khoản chi, người
  tạo; API 4; web 4; S1+S2 68).
- **Tranh chấp thật trên MySQL** (run `37647761355`, job `112882877375`): 2
  test / 6 assertion xanh — hai khoản chi từ **hai ví khác nhau** cùng gắn 70 vào
  một chi phí 100: đúng một được duyệt, đã trả = 70.
- **Không xanh giả:** nhánh dùng một lần bỏ khoá chi phí và khoá đọc
  (`ba8ca2e6`, run `37617655931`, job `112779812677`): test chi phí **đỏ**
  "Exactly one approval may succeed" (test chuyển ví vẫn xanh). Nhánh đã xoá.
- Bộ liên quan xanh (Architecture, Deployment, Migrations/Models, OwnerGovernance,
  Zena, Services, ContractApiTest); SSOT lint, route mồ côi, domain-ownership,
  governance lint đạt.
- Xem trên bản chạy local: tạo khoản chi 12 triệu từ biểu mẫu, gửi duyệt, X tự
  duyệt → còn 188 triệu, "Đã chi" 12 triệu, "Tự duyệt" 12 triệu; chi phí phải
  trả 30 / 12 / 18 triệu; nhãn "Tự duyệt" trong sổ.
- CI exact head `3f780adc`: 34/34 pass.

**5. Ngoài phạm vi** — Tạm ứng (S5), trung gian + đối soát (S4), báo cáo (S6),
sửa chi phí hợp đồng ngoài việc tạo mới, deploy.

**6. Rủi ro còn lại** — Thấp. Migration chỉ thêm cột; bút toán và dòng gắn chi
phí bất biến.

**7. Hoàn tác** — Revert squash commit (migration có `down()`).

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: Approve / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy, S4–S6.
