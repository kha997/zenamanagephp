# Retention & Advance Deductions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Chứng chỉ nghiệm thu trừ retention % + thu hồi tạm ứng (gợi ý tự động, sửa tay khi draft) → đợt thu tự sinh bằng `net_payable`; tạm ứng ban đầu tự sinh đợt thu riêng.

**Architecture:** 3 cột cấu hình trên `contracts`, 3 cột snapshot trên `payment_certificates`; toàn bộ logic khấu trừ là một private helper trong `ContractPageController` gọi từ `saveCertificateLines` và `approveCertificate` (cùng transaction hiện có). Spec: `docs/superpowers/specs/2026-07-14-retention-advance-deductions-design.md`.

**Tech Stack:** như slice IPC — mọi pattern đều đã tồn tại trong chính các file sẽ sửa.

## Global Constraints

- Toàn bộ ràng buộc handoff #1 + quy tắc mới: **count trong `phpstan-baseline.neon` không bao giờ được tăng** — code mới dùng `\Illuminate\Support\Facades\Auth` hoặc `$request->user()`, KHÔNG dùng helper `auth()`.
- PHPStan exit 0 sau mỗi task; tiền `decimal(15,2)`, % `decimal(5,2)`.
- Snapshot bất biến: chứng chỉ đã approve không bao giờ đổi số khi cấu hình HĐ đổi.
- Lũy kế (retention đang giữ, tạm ứng đã thu hồi) luôn DERIVE từ certs APPROVED — không lưu cột lũy kế.
- **Điều kiện tiên quyết:** handoff #5 Task P (gỡ nới baseline) phải xong trước — plan này thêm method mới vào đúng `ContractPageController`, nếu làm trước Task P sẽ dẫm chân nhau.

---

### Task 1: Cấu hình tài chính HĐ + auto advance payment

**Files:**
- Create: `database/migrations/2026_07_14_110000_add_finance_settings_to_contracts_table.php` (3 cột default 0, `down()` drop)
- Modify: `app/Models/Contract.php` (fillable + casts float cho 3 cột, `@property` docblock)
- Modify: `routes/web.php` — cạnh `contracts.expenses.store`:
  `Route::post('/contracts/{id}/finance-settings', [..., 'updateFinanceSettings'])->middleware('rbac:contract.update')->name('contracts.finance-settings.update');`
- Modify: `ContractPageController` — method mới `updateFinanceSettings`
- Test: `tests/Feature/Zena/ContractFinanceSettingsTest.php`

**Interfaces:** Produces `contracts.retention_percent|advance_amount|advance_recovery_percent` và hằng tên đợt thu `ContractPageController::ADVANCE_PAYMENT_NAME = 'Tạm ứng theo hợp đồng'`. Task 2-3 dùng đúng các tên này.

- [ ] **Step 1 — failing test** (setup copy `ContractFinanceViewTest`): (a) POST settings retention 5 / advance 200000000 / recovery 20 → DB có 3 giá trị + `contract_payments` có "Tạm ứng theo hợp đồng" amount 200000000 status planned; (b) POST lần 2 advance 250000000 khi payment còn planned → amount thành 250000000, KHÔNG tạo bản ghi thứ hai; (c) forceFill payment thành paid rồi POST advance 300000000 → payment giữ nguyên, settings vẫn lưu; (d) retention 150 → `assertSessionHasErrors('retention_percent')`; (e) user thiếu `contract.update` (dùng role `team_member`, KHÔNG dùng `admin` — admin bypass RBAC) → bị chặn.
- [ ] **Step 2 — implement:**

```php
    private const ADVANCE_PAYMENT_NAME = 'Tạm ứng theo hợp đồng';

    public function updateFinanceSettings(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'retention_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'advance_amount' => ['required', 'numeric', 'min:0'],
            'advance_recovery_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $tenantId = (string) \Illuminate\Support\Facades\Auth::user()?->tenant_id;
        $contract = Contract::query()->where('tenant_id', $tenantId)->findOrFail($id);

        \Illuminate\Support\Facades\DB::transaction(function () use ($contract, $tenantId, $validated): void {
            $contract->forceFill($validated)->save();

            $advance = (float) $validated['advance_amount'];
            if ($advance > 0) {
                $payment = \App\Models\ContractPayment::query()
                    ->where('tenant_id', $tenantId)
                    ->where('contract_id', (string) $contract->id)
                    ->where('name', self::ADVANCE_PAYMENT_NAME)
                    ->first();

                if ($payment === null) {
                    \App\Models\ContractPayment::query()->create([
                        'tenant_id' => $tenantId,
                        'contract_id' => (string) $contract->id,
                        'name' => self::ADVANCE_PAYMENT_NAME,
                        'amount' => $advance,
                        'status' => \App\Models\ContractPayment::STATUS_PLANNED,
                        'due_date' => now()->addDays(7),
                    ]);
                } elseif ($payment->status === \App\Models\ContractPayment::STATUS_PLANNED) {
                    $payment->forceFill(['amount' => $advance])->save();
                }
                // đã paid: không đụng — UI Task 3 hiển thị ghi chú lệch.
            }
        });

        return back()->with('success', 'Đã lưu thiết lập tài chính hợp đồng.');
    }
```

- [ ] **Step 3 — PASS + `php artisan test --filter=Contract` + phpstan exit 0 (count không tăng) → commit** `feat(contracts): finance settings (retention/advance) with auto advance payment`.

---

### Task 2: Tầng khấu trừ trong chứng chỉ

**Files:**
- Create: `database/migrations/2026_07_14_110100_add_deductions_to_payment_certificates_table.php` (3 cột default 0)
- Modify: `app/Models/PaymentCertificate.php` (fillable, casts float, `@property`)
- Modify: `ContractPageController::saveCertificateLines` + `approveCertificate`
- Test: `tests/Feature/Zena/CertificateDeductionsTest.php`

**Interfaces:** private helper — Task 3 hiển thị đúng các field này:

```php
    /** Tính và gán retention_amount / advance_deduction / net_payable (chưa save).
     *  $override: giá trị nhập tay từ request (null = dùng gợi ý). Trả lỗi validate qua exception. */
    private function applyDeductions(Contract $contract, \App\Models\PaymentCertificate $cert, ?float $override): void
```

Logic (đúng spec): `retention = round(retention_percent/100 × total_this_period, 2)`; `remaining = advance_amount − Σ advance_deduction của certs APPROVED cùng HĐ (loại trừ chính $cert)`; `suggested = min(round(recovery/100 × total,2), remaining)`; deduction = override ?? suggested, validate `0 ≤ deduction ≤ remaining` và `retention + deduction ≤ total` (ném `ValidationException::withMessages(['advance_deduction' => ...])`); `net_payable = total − retention − deduction`.

- [ ] **Step 1 — failing test** với đúng bộ số trong spec: HĐ retention 5 / advance 200tr / recovery 20; BOQ 1 dòng qty 10000 × 100000. Kỳ 1 KL 3000 (300tr) → sau saveLines: retention 15tr, deduction 60tr, net 225tr; approve → payment "Nghiệm thu KL kỳ 1" amount **225000000**. Kỳ 2 KL 8000 (800tr) → deduction gợi ý 140tr, net 620tr. Override kỳ 2 = 150tr → errors `advance_deduction`. Override 100tr → net 660tr. Đổi retention_percent = 10 sau kỳ-1 approve → kỳ 1 vẫn 15tr (refresh + assert). Sửa deduction khi cert đã submit → back error.
- [ ] **Step 2 — implement:** gọi `applyDeductions($contract, $cert, $request->has('advance_deduction') ? (float) $request->input('advance_deduction') : null)` bên trong transaction của `saveCertificateLines` (sau khi recompute total; input chỉ chấp nhận khi draft — guard sẵn có); trong `approveCertificate` gọi lại với override = null NHƯNG giữ deduction đã lưu nếu có (`$cert->advance_deduction > 0 ? (float) $cert->advance_deduction : null`) rồi mới tạo payment với `amount => $cert->net_payable`; thêm 3 field vào payload EventRecord.
- [ ] **Step 3 — PASS + chạy lại `PaymentCertificateFlowTest` (payment amount đổi từ total → net: các assert cũ với HĐ không cấu hình khấu trừ vẫn đúng vì default 0 → net = total) + phpstan → commit** `feat(certificates): retention and advance-recovery deductions with net payable`.

---

### Task 3: UI

**Files:** `resources/views/contracts/show.blade.php` (card "Thiết lập tài chính HĐ" — 3 input + nút, sau card Tài chính; 3 dòng mới trong card Tài chính: Đang giữ lại / Tạm ứng đã thu hồi / còn lại — derive: `Σ retention_amount` và `Σ advance_deduction` của certs approved, query trong `show()`), `resources/views/contracts/certificate-show.blade.php` (khối tổng kết: Giá trị KL → − Giữ lại (x%) → − Thu hồi tạm ứng [input `advance_deduction` khi draft, hiển thị "gợi ý Y, còn lại Z"] → **= Đề nghị thanh toán**), controller `show()`/`showCertificate()` bổ sung biến. Ghi chú lệch tạm ứng khi payment đã paid mà `advance_amount` khác amount payment.

- [ ] Failing render assertions (thêm vào `CertificateDeductionsTest`): trang certificate hiện "Đề nghị thanh toán" + "225,000,000"; trang HĐ hiện "Đang giữ lại" + "15,000,000" sau kỳ-1 approve → implement → PASS → phpstan → commit `feat(certificates): deductions UI on contract and certificate pages`.

---

### Task 4: Final verification

- [ ] `php artisan test tests/Feature/Architecture/` (29) + `--testsuite=Feature` (ghi baseline mới) + `vendor/bin/phpstan analyse --memory-limit=1G` exit 0, **diff baseline count = 0 tăng**. Push, báo 3 con số. Không merge.

## Self-review notes

- Spec coverage: settings+auto-payment→T1; recompute/override/validate/approve-net/EventRecord→T2; toàn bộ UI + 2 dòng lũy kế→T3. Mọi rule error-handling của spec có test tương ứng trong T1(d,e)/T2(override, submit-guard, snapshot).
- Backward compatibility: HĐ chưa cấu hình → 3 default 0 → net = total → mọi test IPC cũ giữ nguyên ý nghĩa (T2 Step 3 chạy lại để chứng minh).
- Tên/field nhất quán: `ADVANCE_PAYMENT_NAME`, `retention_amount/advance_deduction/net_payable` khai báo một lần, dùng xuyên suốt.
