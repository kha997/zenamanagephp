# Receivables Aging Implementation Plan (Goal #5 Slice)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mở rộng `BusinessKpiService::outstandingDebt()` với breakdown công nợ theo tuổi nợ (30/60/90+ ngày), hiện trên trang báo cáo kinh doanh CRM.

**Architecture:** Sửa 1 method hiện có (KHÔNG viết service mới, KHÔNG đổi chữ ký/xóa key cũ), mở rộng view hiện có 1 khối field-value. Spec: `docs/superpowers/specs/2026-07-16-receivables-aging-design.md`.

## Global Constraints

- `Model::query()`; CẤM helper `auth()` nếu file đang sửa dùng `Auth` facade — đọc `CrmReportController` hiện tại để giữ đúng phong cách file đó.
- CẤM sửa `tests/TestCase.php`.
- File mới không có entry baseline (diff kiểm trước báo cáo — nhưng slice này gần như không tạo file mới, chỉ sửa).
- Checklist sau MỖI task: Architecture 29 / `--testsuite=Feature` toàn bộ xanh / phpstan exit 0. Push cuối: guardrails CI success.
- Claim "pre-existing failure" phải kèm bằng chứng chạy trên base commit hiện tại của `main`.
- PR: base `main`. Sau khi tạo dán `gh pr view <n> --json baseRefName,commits,mergeable` vào báo cáo. KHÔNG merge.
- **KHÔNG đụng** file `WorkTemplate*` untracked nếu thấy trong working tree (thuộc phiên khác, không liên quan).
- **Lưu ý CI**: 8 check (API Tests Fast/Slow, Code Quality Analysis, Security Tests, Security Vulnerability Scan, Zena RBAC/Tenant Invariants ×2, browser-tests) đang RED trên mọi PR gần đây kể cả PR đã merge thành công — đây là nợ hạ tầng có sẵn (đang được xử lý ở nhánh `fix/ci-quality-security-workflow`, không liên quan đến code của bạn). KHÔNG cố sửa các check đó, chỉ cần các check cốt lõi xanh: `test`, `code-quality`, `staging-smoke`, `test-routes-guardrails`, `Playwright Finance Smoke`, `Feature Tests`, `feature-tests`, `security-tests`.

---

### Task 1: Aging buckets trong BusinessKpiService

**Files:** Modify `app/Services/BusinessKpiService.php` (method `outstandingDebt()`, dòng ~60-77); Modify `tests/Unit/Services/BusinessKpiServiceTest.php`.

**Interface (Task 2 dùng đúng key):**

```php
/**
 * @return array{
 *   total: float, overdue_total: float, overdue_count: int,
 *   aging: array{not_due: float, due_1_30: float, due_31_60: float, due_61_90: float, due_over_90: float}
 * }
 */
public function outstandingDebt(string $tenantId): array
{
    return Cache::remember("business_kpi_outstanding_debt_{$tenantId}", 60, function () use ($tenantId): array {
        $unpaid = ContractPayment::query()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', ContractPayment::STATUS_PAID);

        $payments = (clone $unpaid)->get();

        $total = (float) $payments->sum('amount');
        $overdue = $payments->filter(fn ($p) => $p->due_date < now());

        $aging = [
            'not_due' => 0.0,
            'due_1_30' => 0.0,
            'due_31_60' => 0.0,
            'due_61_90' => 0.0,
            'due_over_90' => 0.0,
        ];

        foreach ($payments as $payment) {
            $daysOverdue = now()->diffInDays($payment->due_date, false) * -1;
            $amount = (float) $payment->amount;

            if ($daysOverdue <= 0) {
                $aging['not_due'] += $amount;
            } elseif ($daysOverdue <= 30) {
                $aging['due_1_30'] += $amount;
            } elseif ($daysOverdue <= 60) {
                $aging['due_31_60'] += $amount;
            } elseif ($daysOverdue <= 90) {
                $aging['due_61_90'] += $amount;
            } else {
                $aging['due_over_90'] += $amount;
            }
        }

        return [
            'total' => $total,
            'overdue_total' => (float) $overdue->sum('amount'),
            'overdue_count' => $overdue->count(),
            'aging' => $aging,
        ];
    });
}
```

Lưu ý: `now()->diffInDays($payment->due_date, false)` trả âm nếu `due_date` ở TƯƠNG LAI (chưa tới hạn) khi gọi trên đối tượng `now()` với tham số so sánh là ngày trong tương lai — kiểm tra kỹ dấu bằng test thực tế (viết failing test trước, đừng tin công thức trên mù quáng, verify bằng `Carbon` thật). Nếu dấu ngược, đổi thành `$payment->due_date->diffInDays(now(), false)` — chọn công thức nào cho kết quả đúng theo test.

Test (mở rộng `BusinessKpiServiceTest`): tạo 5 `ContractPayment` (`STATUS_PLANNED`, khác `due_date`: `addDays(5)`, `subDays(10)`, `subDays(45)`, `subDays(75)`, `subDays(120)`) → assert `$result['aging']['not_due']` = amount của cái đầu, `due_1_30` = cái thứ 2, v.v.; đồng thời `total`/`overdue_total`/`overdue_count` (key cũ) vẫn đúng như trước — regression trong cùng 1 test file; `test_results_are_cached_for_60_seconds` vẫn PASS nguyên trạng.

- [ ] Steps: failing test (5 bucket, verify dấu diffInDays bằng assertion thực tế trước khi tin công thức) → sửa method → PASS toàn bộ `BusinessKpiServiceTest` → checklist → commit `feat(kpi): aging buckets for outstanding debt`.

---

### Task 2: Hiện aging buckets trên báo cáo kinh doanh

**Files:** Modify `resources/views/crm/report.blade.php` (dòng ~34-36, thêm khối ngay sau 3 field hiện có); Test: thêm render assertion vào test hiện có của `CrmReportController` (tìm bằng grep `CrmReportController` hoặc `crm.reports` trong `tests/`).

```blade
<x-ui.field-value label="Chưa đến hạn" :value="number_format($outstandingDebt['aging']['not_due'], 0, ',', '.') . '₫'" />
<x-ui.field-value label="Quá hạn 1-30 ngày" :value="number_format($outstandingDebt['aging']['due_1_30'], 0, ',', '.') . '₫'" />
<x-ui.field-value label="Quá hạn 31-60 ngày" :value="number_format($outstandingDebt['aging']['due_31_60'], 0, ',', '.') . '₫'" />
<x-ui.field-value label="Quá hạn 61-90 ngày" :value="number_format($outstandingDebt['aging']['due_61_90'], 0, ',', '.') . '₫'" />
<x-ui.field-value label="Quá hạn trên 90 ngày" :value="number_format($outstandingDebt['aging']['due_over_90'], 0, ',', '.') . '₫'" />
```

Test: trang báo cáo (`route('operator.crm.reports')`) render thấy 5 label mới + số tiền đúng (seed dữ liệu tương tự Task 1, assert `assertSee` với số đã format).

- [ ] Steps: failing render test → view → PASS → checklist → commit `feat(kpi): show aging buckets on business report page`.

---

### Task 3: Final verification + PR

- [ ] 3 con số (Architecture 29 / Feature suite full xanh / phpstan 0) + baseline diff 0 path mới + `BusinessKpiServiceTest` nguyên bộ xanh + các check cốt lõi CI xanh (xem Global Constraints — bỏ qua 8 check nợ hạ tầng).
- [ ] `gh pr create` head nhánh này **base `main`**; dán `gh pr view <n> --json baseRefName,commits,mergeable` vào báo cáo. KHÔNG merge.

## Self-review notes

- Spec coverage: Data+công thức→T1, UI→T2.
- Key `aging` khai báo 1 lần ở T1 (docblock chuẩn), T2 chỉ đọc.
- Không đổi key cũ (`total`/`overdue_total`/`overdue_count`) — tránh phá bất kỳ consumer nào khác của `outstandingDebt()` chưa được rà hết trong spec này.
