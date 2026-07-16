# Retention & Advance Deductions — Design Spec

Date: 2026-07-14
Status: approved by user (option A + both recommendations, 2026-07-14)
Depends on: IPC slice (`2026-07-14-payment-certificates-design.md`) — shipped through commit `42b2e1c3`.

## Purpose

Chứng chỉ nghiệm thu hiện đề nghị thanh toán = 100% giá trị khối lượng. Thực tế hợp đồng xây dựng: mỗi kỳ **giữ lại retention %** và **thu hồi một phần tạm ứng**; số đề nghị thanh toán thực = giá trị KL − retention − thu hồi tạm ứng. Slice này chèn tầng khấu trừ đó vào giữa, và ghi nhận khoản tạm ứng ban đầu như một đợt thu.

## User-approved decisions

1. **Thu hồi tạm ứng — option A**: tự động gợi ý = `min(advance_recovery_percent × giá trị KL kỳ này, tạm ứng còn lại)`; kế toán **sửa tay được khi chứng chỉ còn draft** (0 ≤ x ≤ tạm ứng còn lại); lũy kế thu hồi derive từ chứng chỉ APPROVED — tổng thu hồi không bao giờ vượt tạm ứng.
2. **Hoàn trả retention: NGOÀI scope** — slice này chỉ trừ và hiển thị "lũy kế đang giữ lại"; hoàn trả gắn nghiệm thu hoàn thành/bảo hành, làm ở slice bảo hành sau.
3. **Tạm ứng tự sinh đợt thu**: khi thiết lập `advance_amount` từ 0 → dương, tự tạo `ContractPayment` planned "Tạm ứng theo hợp đồng" (due +7 ngày). Nếu sửa số tiền sau đó: payment còn `planned` thì cập nhật amount; đã `paid` thì không đụng, hiển thị ghi chú lệch.

## Data

`contracts` (+3, all snapshot-friendly defaults): `retention_percent` decimal(5,2) default 0, `advance_amount` decimal(15,2) default 0, `advance_recovery_percent` decimal(5,2) default 0.

`payment_certificates` (+3, SNAPSHOT per certificate — later config changes never alter history): `retention_amount` decimal(15,2) default 0, `advance_deduction` decimal(15,2) default 0, `net_payable` decimal(15,2) default 0.

## Behavior

- **Thiết lập tài chính HĐ**: form trên `contracts.show` (rbac `contract.update`) lưu 3 tham số. Validation: retention 0–100, recovery 0–100, advance ≥ 0. Đổi tham số chỉ ảnh hưởng chứng chỉ TƯƠNG LAI (mỗi chứng chỉ snapshot số tiền lúc tính). Auto advance-payment per decision 3 (logic trong cùng controller method, transaction).
- **Recompute khấu trừ** chạy trong `saveCertificateLines` (sau khi tính `total_this_period`) và chạy lại lúc `approve`: `retention_amount = round(retention_percent/100 × total, 2)`; `advance_deduction` = giá trị gợi ý trừ khi request gửi override (`advance_deduction` input, chỉ nhận khi draft, validate 0 ≤ x ≤ advance_remaining); `advance_remaining = advance_amount − Σ advance_deduction (certs APPROVED, mọi kỳ)`; `net_payable = total − retention_amount − advance_deduction`, validate ≥ 0 (retention + deduction ≤ total, 422 nếu vi phạm).
- **Approve**: `ContractPayment.amount = net_payable` (đổi từ total hiện tại); EventRecord payload thêm `retention_amount`, `advance_deduction`, `net_payable`.

## UI

- `contracts.show`: card "Thiết lập tài chính HĐ" (3 input + nút lưu, khóa hiển thị khi thiếu quyền); khối Tài chính thêm 3 dòng: **Đang giữ lại (retention lũy kế)** = Σ retention_amount của certs approved; **Tạm ứng đã thu hồi / còn lại**.
- `certificate-show`: dưới bảng dòng, khối tổng kết: Giá trị KL kỳ này → − Giữ lại ({retention_percent}%) → − Thu hồi tạm ứng (input khi draft + quyền create, kèm gợi ý tự động và "còn lại X") → **= Đề nghị thanh toán**.

## Error handling

- Override > advance_remaining hoặc âm: 422. retention + deduction > total: 422. Sửa deduction khi không phải draft: back error (pattern hiện có). Cross-tenant/permission: như IPC (scoped findOrFail + rbac routes).

## Testing (concrete numbers)

HĐ: total_value 1 tỷ, retention 5%, advance 200tr, recovery 20%. Kỳ 1 KL 300tr → retention 15tr, thu hồi gợi ý 60tr, net 225tr; approve → payment 225tr. Kỳ 2 KL 800tr → gợi ý min(160tr, 200−60=140tr)=140tr → net 800−40−140=620tr. Override kỳ 2 = 150tr → 422 (vượt còn lại). Đổi retention_percent sau kỳ 1 approve → kỳ 1 giữ nguyên 15tr (snapshot). Set advance 0→200tr tạo payment "Tạm ứng theo hợp đồng" 200tr planned; sửa thành 250tr khi còn planned → amount 250tr; test guard: baseline PHPStan count không tăng (dùng Auth facade/$request->user()).

## Out of scope

Hoàn trả retention, bảo hành, chứng từ tạm ứng nhiều đợt, lãi/phạt chậm thanh toán, PDF.
