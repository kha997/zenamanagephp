# Portal Payment Schedule Implementation Plan (Goal #6 Slice)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cổng khách hàng hiện danh sách chi tiết từng đợt thanh toán (thay vì chỉ 1 số tổng), giảm khách phải hỏi qua Zalo.

**Architecture:** Sửa 1 controller hiện có (`PortalDashboardController::index()`, thêm 1 query, giữ nguyên `outstandingBalance` key cũ) + mở rộng view hiện có 1 khối bảng. KHÔNG route mới, KHÔNG permission mới (chỉ đọc). Spec: `docs/superpowers/specs/2026-07-16-portal-payment-schedule-design.md`.

## Global Constraints

- `Model::query()`; file đang dùng `Auth::guard('client')->user()` — giữ nguyên pattern.
- CẤM sửa `tests/TestCase.php`.
- Checklist sau MỖI task: Architecture 29 / `--testsuite=Feature` toàn bộ xanh / phpstan exit 0. Push cuối: guardrails CI success.
- Claim "pre-existing failure" phải kèm bằng chứng chạy trên base commit hiện tại của `main`.
- PR: base `main`. Sau khi tạo dán `gh pr view <n> --json baseRefName,commits,mergeable` vào báo cáo. KHÔNG merge.
- **KHÔNG đụng** file `WorkTemplate*` untracked nếu thấy trong working tree (thuộc phiên khác).
- **CI hiện tại**: chỉ còn đúng 2 check đỏ đã biết rõ nguyên nhân, KHÔNG liên quan code của bạn — `API Tests (Fast)` (bug thật `submittals.submit` trả 404, đang chờ investigate riêng) và `browser-tests` (Chromium/Google API deprecated). Mọi check khác PHẢI xanh, kể cả `Code Quality Analysis`/`Security Tests` (phpstan job đã sửa xong, chạy đúng config thật — KHÔNG được coi lỗi phpstan là "nợ hạ tầng", phải sửa nếu có).
- **Bài học phpstan quan trọng**: khi thêm `@property` docblock hoặc dùng model có docblock, LUÔN đối chiếu với migration thật (nullable hay không) trước khi tin — 2 lần trong ngày lỗi docblock-sai-schema là nguyên nhân gốc của bug phpstan. Nếu bạn thêm code động tới field mới trên model đã có, kiểm tra docblock `ContractPayment` khớp đúng migration `contract_payments`.

---

### Task 1: Query danh sách đợt thanh toán trong controller

**Files:** Modify `app/Http/Controllers/Web/Portal/PortalDashboardController.php` (method `index()`, dòng ~59-63 — đọc nguyên method trước khi sửa); Test: mở rộng `tests/Feature/Portal/PortalDashboardTest.php`.

```php
$paymentSchedule = ContractPayment::query()
    ->where('tenant_id', $tenant->id)
    ->whereIn('contract_id', $contracts->pluck('id'))
    ->where('status', '!=', ContractPayment::STATUS_PAID)
    ->orderBy('due_date')
    ->get(['id', 'contract_id', 'name', 'amount', 'due_date', 'status']);
```

Thêm `'paymentSchedule' => $paymentSchedule,` vào mảng trả về của `view('portal.dashboard', [...])` — KHÔNG xóa `outstandingBalance` đang có.

Test: seed tenant/account/opportunity/project/contract theo đúng pattern `test_dashboard_shows_projects_design_items_documents_and_balance_for_the_account` (đọc method này trước — đã có sẵn 1 `ContractPayment` mẫu, tái dùng cấu trúc); thêm 3 `ContractPayment`: 1 `planned` với `due_date` tương lai (`now()->addDays(10)`), 1 `planned` với `due_date` quá khứ (`now()->subDays(5)` — quá hạn), 1 `paid` (`due_date` bất kỳ); gọi route dashboard → response chứa đúng 2 khoản chưa-paid (không chứa khoản `paid`), sắp xếp theo `due_date` tăng dần; `outstandingBalance` (key cũ) vẫn tính đúng như trước — regression ngay trong test này.

- [ ] Steps: failing test → sửa controller → PASS → checklist → commit `feat(portal): itemized payment schedule query on dashboard`.

---

### Task 2: Hiện bảng lịch thanh toán trên UI

**Files:** Modify `resources/views/portal/dashboard.blade.php` (ngay dưới field "Số dư còn lại", dòng ~115 — đọc cấu trúc block xung quanh trước khi chèn).

```blade
@if ($paymentSchedule->isNotEmpty())
    <div class="mt-4">
        <h3 class="text-sm font-semibold text-slate-700 mb-2">Các đợt thanh toán còn lại</h3>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500">
                    <th class="pb-2">Đợt</th>
                    <th class="pb-2 text-right">Số tiền</th>
                    <th class="pb-2 text-right">Hạn thanh toán</th>
                    <th class="pb-2 text-right">Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($paymentSchedule as $payment)
                    <tr class="border-t border-slate-100">
                        <td class="py-2">{{ $payment->name }}</td>
                        <td class="py-2 text-right">{{ number_format((float) $payment->amount, 0, ',', '.') }}₫</td>
                        <td class="py-2 text-right">{{ $payment->due_date->format('d/m/Y') }}</td>
                        <td class="py-2 text-right">
                            @if ($payment->due_date->lt(now()))
                                <span class="rounded bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">Quá hạn</span>
                            @else
                                <span class="rounded bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800">Chưa đến hạn</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
```

Lưu ý: dùng `$payment->due_date->lt(now())` để tô "Quá hạn" theo THỜI ĐIỂM XEM THẬT (không dựa cột `status` lưu sẵn có thể lỗi thời) — khớp đúng cách `BusinessKpiService::outstandingDebt()` tính overdue.

Test: render assertion — response thấy "Các đợt thanh toán còn lại", tên đợt, số tiền định dạng đúng, badge "Quá hạn" cho khoản quá hạn và "Chưa đến hạn" cho khoản còn hạn; khách không có khoản nào chưa-paid (tất cả đã `paid` hoặc không có contract) → KHÔNG thấy tiêu đề "Các đợt thanh toán còn lại" (khối ẩn hoàn toàn).

- [ ] Steps: failing render test → view → PASS → checklist → commit `feat(portal): render payment schedule table on client dashboard`.

---

### Task 3: Final verification + PR

- [ ] 3 con số (Architecture 29 / Feature suite full xanh / phpstan 0) + baseline diff 0 path mới + `PortalDashboardTest` nguyên bộ xanh + các check CI cốt lõi xanh (xem Global Constraints — chỉ bỏ qua đúng 2 check đã biết).
- [ ] `gh pr create` head nhánh này **base `main`**; dán `gh pr view <n> --json baseRefName,commits,mergeable` vào báo cáo. KHÔNG merge.

## Self-review notes

- Spec coverage: Query→T1, UI→T2.
- `outstandingBalance` key cũ giữ nguyên xuyên suốt — không phá consumer nào khác.
- "Quá hạn" tính theo `due_date < now()` tại thời điểm render, nhất quán với `BusinessKpiService::outstandingDebt()` (không dựa cột `status` tĩnh) — tránh lệch logic giữa 2 nơi cùng hiển thị khái niệm "quá hạn".
