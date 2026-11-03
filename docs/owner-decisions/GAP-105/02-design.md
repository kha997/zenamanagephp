---
work_id: GAP-105
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-03-gap-105-treasury-boq-fund-chains-receivable-evidence.md
  plan: docs/superpowers/plans/2026-11-03-gap-105-treasury-boq-fund-chains-receivable.md
  branch: docs/GAP-105-treasury-boq-fund-chains-receivable
  pr: https://github.com/kha997/zenamanagephp/pull/395
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-03T23:09:52+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-03, verbatim: 'APPROVE GAP-105 Gate 2 R-1 B-1 F-1 P-1'. Reviewed design head: b4e6a7b884e990675b2c9f292df210ac57699e1a. Approves R-1 (investor receivable per contract from ContractPayment paid; Treasury arrivals informational; unlinked funding shown separately, never subtracted), B-1 (nullable boq_line_item_id on treasury_financial_documents, one BOQ line per expense, set while draft, same-project check, restrict delete), F-1 (manual fund chains, members are posted documents or routes of the same project, metadata only with audit, permission treasury.manage_routes to edit and treasury.view to read), P-1 (one PR, one Gate 3), with the exact allowlist in this packet; one additive migration; no new permission; no change to ContractPayment or posting; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-03T22:55:00+07:00"
  updated_at: "2026-11-03T23:09:52+07:00"
generated_by: agent
---

# GAP-105 — Treasury: BOQ link, fund chains, investor receivable — Gate 2 design

## OWNER GATE 2: APPROVED — R-1, B-1, F-1, P-1

Owner approved R-1, B-1, F-1 and P-1 in-session on 2026-11-03 against reviewed design head
`b4e6a7b884e990675b2c9f292df210ac57699e1a`. This authorizes only the bounded implementation defined by this packet; it
does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Ba phần mở rộng Ngân quỹ. **Anh/chị cần chọn 4 điểm:**
1. **Phải thu nhà đầu tư:** định nghĩa nào. Đề xuất **R-1**: lấy theo hợp đồng và đợt thanh toán, không đếm trùng.
2. **Liên kết BOQ:** gắn thế nào. Đề xuất **B-1**: mỗi khoản chi gắn tối đa 1 dòng BOQ.
3. **Chuỗi quỹ:** ai tạo, quyền nào. Đề xuất **F-1**: kế toán tạo tay, dùng quyền `treasury.manage_routes` có sẵn.
4. **Phát hành:** một lần hay ba lần. Đề xuất **P-1**: một PR, một Gate 3.

## Điểm 1 — Phải thu nhà đầu tư

| Phương án | Cách tính | Ưu / nhược |
|---|---|---|
| **R-1 (đề xuất)** | Theo từng hợp đồng không huỷ của dự án: **giá trị**; **đã thu** (đợt `ContractPayment` đã `paid`); **còn phải thu** = giá trị − đã thu; thêm cột tham khảo "đã về ví Ngân quỹ" (tuyến tiền gắn đợt thanh toán đã hoàn tất). Tiền nhận khác trong Ngân quỹ (`funding` không gắn hợp đồng) hiện **riêng một dòng**, không trừ vào phải thu. | Không đếm trùng; đúng SSOT (công nợ hợp đồng thuộc ContractPayment). Không migration. Nhược: khoản nhà đầu tư trả thẳng vào Ngân quỹ mà chưa ghi thành đợt thanh toán hợp đồng sẽ không giảm phải thu — phải ghi đợt thanh toán như hiện nay. |
| R-2 | Thêm cột gắn chứng từ `funding` với hợp đồng; phải thu = giá trị − funding đã gắn. | Hai nguồn "đã thu" song song với ContractPayment, dễ lệch nhau; cần migration. |
| R-3 | Chỉ hiện hai nguồn cạnh nhau, không tính "còn phải thu". | An toàn nhưng không trả lời câu "còn nợ bao nhiêu". |

Không có hợp đồng → "Chưa có dữ liệu". Đã thu vượt giá trị → hiện số âm kèm nhãn "thu vượt". Không bao giờ cộng / trừ
ra lãi (SSOT §8).

## Điểm 2 — Liên kết BOQ

| Phương án | Nội dung |
|---|---|
| **B-1 (đề xuất)** | Thêm cột `boq_line_item_id` (cho phép trống) vào `treasury_financial_documents`. Mỗi khoản chi gắn tối đa 1 dòng BOQ, chọn lúc tạo / sửa **nháp**; đã gửi duyệt hoặc ghi sổ thì không đổi (giữ quy tắc chứng từ đã ghi sổ bất biến). Dòng BOQ phải thuộc BOQ của **cùng dự án và tenant** (kiểm tra trong dịch vụ). Khoá ngoại không cho xoá dòng BOQ đang được chứng từ dùng. |
| B-2 | Bảng mới chia một khoản chi cho nhiều dòng BOQ. Linh hoạt hơn nhưng nặng hơn nhiều (bảng, ràng buộc tổng, giao diện chia). |
| B-3 | Chỉ gắn ở mức BOQ, không tới dòng. Quá thô cho câu "chi cho hạng mục nào". |

Theo B-1:
- **Báo cáo "Chi theo BOQ"** (CSV như các báo cáo S6): mỗi dòng BOQ có
  - **đã chi** = tổng khoản chi đã ghi sổ, không tính khoản đã đảo;
  - **ngân sách** = số lượng × đơn giá, chỉ khi có đơn giá; thiếu đơn giá thì "chưa có dữ liệu";
  - **chênh lệch**, chỉ khi có ngân sách.

  Có thêm một dòng "Chưa gắn BOQ".
- **Sổ giao dịch:** thêm bộ lọc theo dòng BOQ.
- **Cảnh báo "Vượt ngân sách BOQ"** trên trang Ngân quỹ: số dòng có đã chi > ngân sách (chỉ dòng có đơn giá).

## Điểm 3 — Chuỗi quỹ

| Phương án | Nội dung |
|---|---|
| **F-1 (đề xuất)** | Kế toán tạo chuỗi bằng tay (mã, mô tả), rồi thêm / bỏ thành viên: chứng từ đã ghi sổ (`funding`, `internal_transfer`, `expense`, `owner_contribution`, `advance`, `advance_return`) hoặc tuyến tiền của **cùng dự án**; mỗi chứng từ / tuyến chỉ thuộc một chuỗi (ràng buộc đã có). Việc thêm / bỏ chỉ là gắn nhãn, **không đổi sổ cái**, có ghi audit. Quyền: `treasury.manage_routes` (có sẵn; chuỗi là nhóm các dòng tiền như tuyến) + quyền xem dự án Ngân quỹ. |
| F-2 | Tạo quyền mới `treasury.manage_fund_chains` (phải sửa seeder quyền). |
| F-3 | Tự tạo chuỗi từ tuyến tiền. Tuyến đã gom các chặng; tự động hoá thêm ít giá trị, khó đúng khi tiền chia nhánh. |

Theo F-1:
- Trang **"Chuỗi quỹ"** của dự án: danh sách chuỗi, tổng tiền vào / ra theo loại.
- Trang chi tiết chuỗi: các thành viên theo ngày, kèm số tiền và trạng thái.
- **Báo cáo "Truy vết chuỗi quỹ"** dạng CSV.
- Sổ giao dịch có thêm bộ lọc theo chuỗi.

## Điểm 4 — Phát hành

| Phương án | Nội dung |
|---|---|
| **P-1 (đề xuất)** | Một PR, ba commit theo thứ tự R → F → B, một Gate 3. |
| P-2 | Ba PR, mỗi phần một Gate 3 riêng (03-release, 03-release-v2, v3). Chậm hơn, nhiều vòng duyệt. |

## Thiết kế chi tiết (theo R-1, B-1, F-1, P-1)

### Dữ liệu
Một migration thêm `treasury_financial_documents.boq_line_item_id`:
- cho phép trống, có chỉ mục;
- khoá ngoại tới `boq_line_items.id`, chặn xoá (restrict);
- có `down()`.

Không đổi bảng nào khác. Bảng chuỗi quỹ đã có sẵn.

### Dịch vụ
- **`TreasuryReceivableService::forProject()` (mới):**
  - đọc hợp đồng, `ContractPayment` và tuyến tiền đã về ví;
  - tính theo hợp đồng và tổng;
  - số tiền giữ ở dạng chuỗi 2 số lẻ như S6.
- **`TreasuryExpenseService` (createDraft / updateDraft):** nhận `boq_line_item_id`, kiểm tra dòng thuộc dự án.
- **`TreasuryFundChainService` (mới):** `create`, `addMember`, `removeMember`.
  - Mọi thao tác đều kiểm tra cùng tenant và dự án, thành viên đã ghi sổ, và chưa thuộc chuỗi khác.
  - Mọi thao tác ghi audit qua `TreasuryPostingService::audit`.
  - Đồng thời: dựa vào chỉ mục unique sẵn có, bắt lỗi trùng và trả thông báo.
- **`TreasuryReportService`:**
  - thêm 3 báo cáo: `investor_receivable`, `expense_by_boq`, `fund_chain_trace`;
  - sổ giao dịch có bộ lọc `boq_line_item_id` và `fund_chain_id`.
- **`TreasuryDashboardService`:**
  - thêm thẻ "Giá trị hợp đồng" và "Còn phải thu";
  - thêm cảnh báo "Vượt ngân sách BOQ".

### Giao diện và route
- Form khoản chi: thêm ô chọn dòng BOQ, chỉ hiện các dòng BOQ của dự án.
- Route mới, đều có `rbac` cộng chính sách Ngân quỹ:
  - `GET /projects/{project}/treasury/fund-chains` — danh sách chuỗi;
  - `GET .../fund-chains/{chain}` — chi tiết chuỗi;
  - `POST .../fund-chains` — tạo chuỗi;
  - `POST .../fund-chains/{chain}/members` — thêm thành viên;
  - `POST .../fund-chains/{chain}/members/{member}/delete` — bỏ thành viên.

  Xem dùng `treasury.view`; sửa dùng `treasury.manage_routes`.
- Trang báo cáo: thêm 3 báo cáo mới, xuất CSV qua đúng đường S6 (quyền `treasury.export`).

### Test (đỏ trước / xanh sau)
- **Phải thu:**
  - không hợp đồng → no_data;
  - hợp đồng huỷ bị bỏ;
  - chỉ đợt `paid` được tính;
  - tiền về ví chỉ để tham khảo;
  - `funding` không gắn hợp đồng không trừ vào phải thu;
  - thu vượt;
  - cách ly tenant / dự án.
- **BOQ:**
  - gắn dòng BOQ dự án khác → lỗi;
  - không sửa được sau khi gửi duyệt;
  - báo cáo đã chi theo dòng (bỏ khoản đã đảo);
  - không có đơn giá → no_data;
  - cảnh báo vượt;
  - bộ lọc sổ;
  - xoá dòng BOQ đang dùng → bị chặn.
- **Chuỗi:**
  - tạo / thêm / bỏ;
  - thành viên dự án khác, chưa ghi sổ, đã thuộc chuỗi khác → từ chối;
  - không đổi số dư ví;
  - audit;
  - quyền (`manage_routes` để sửa, `view` để xem);
  - báo cáo truy vết;
  - bộ lọc sổ.
- **Migration:** chạy trên SQLite và MySQL (job MySQL hiện có); `down()` chạy được.
- **Số truy vấn:** các báo cáo mới không tăng theo số chứng từ.

### Files (allowlist)
- Mới: `database/migrations/2026_11_03_120000_add_boq_line_item_id_to_treasury_financial_documents.php`.
- `app/Models/Treasury/TreasuryFinancialDocument.php`, `app/Models/Treasury/TreasuryFundChain.php`,
  `app/Models/Treasury/TreasuryFundChainMember.php` (quan hệ / fillable nếu cần).
- Mới: `app/Services/Treasury/TreasuryReceivableService.php`, `app/Services/Treasury/TreasuryFundChainService.php`.
- `app/Services/Treasury/TreasuryExpenseService.php`, `TreasuryReportService.php`, `TreasuryDashboardService.php`.
- `app/Http/Controllers/Web/Treasury/TreasuryPageController.php` (form chi);
  `TreasuryReportPageController.php` (báo cáo mới); mới `TreasuryFundChainPageController.php`.
- `routes/web.php` (5 route chuỗi quỹ).
- Views: `resources/views/treasury/project.blade.php`, `_dashboard.blade.php`, `reports.blade.php`; mới
  `fund-chains.blade.php`, `fund-chain.blade.php`.
- Tests mới trong `tests/Feature/Treasury/` (receivable, BOQ link, fund chains); sửa test S6 nếu số báo cáo / thẻ thay đổi.
- Plan `docs/superpowers/plans/2026-11-03-gap-105-treasury-boq-fund-chains-receivable.md`; packet và evidence GAP-105.

Không quyền mới, không đổi ContractPayment hay công nợ hiện có, không đổi cách ghi sổ.

## Ngoài phạm vi

Chia một khoản chi cho nhiều dòng BOQ; gắn BOQ cho tạm ứng / phân bổ chi phí; tự động tạo chuỗi; chứng từ đính kèm;
khoá kỳ / import sao kê; ngưỡng cấu hình; sửa màn hình BOQ; lợi nhuận; deploy.

## Verification required at Gate 3

Đỏ trước / xanh sau; Unit + Feature + Integration và `tests/Feature/Api` không lỗi mới; migration chạy trên MySQL thật;
PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac sạch; CI trên đúng head xanh.

## Rollback

Revert squash commit; migration `down()` xoá cột (chỉ mất nhãn BOQ đã gắn, không ảnh hưởng sổ cái).

## Decision Needed

Owner chọn: `APPROVE GAP-105 Gate 2 R-1 B-1 F-1 P-1` (hoặc phương án khác cho từng điểm) / Changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, phát hành.
