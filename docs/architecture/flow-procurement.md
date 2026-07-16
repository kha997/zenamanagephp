# Luồng Mua sắm (Procurement) — Material Request → Receipt → Cost

> Sơ đồ sống cho debug: mỗi node ghi rõ controller/model sở hữu.
> Ranh giới lớp được enforce bởi `composer deptrac`.

## Luồng nghiệp vụ

```mermaid
flowchart TD
    subgraph Request["Yêu cầu vật tư"]
        MR_CREATE["Tạo MR (draft)\nApi\\MaterialRequestController::store"]
        MR_SUBMIT["Gửi duyệt (submitted)\n::submit — rbac:material.request"]
        MR_APPROVE["Phê duyệt (approved)\n::approve — rbac:material.approve"]
        MR_FULFILL["Hoàn tất (fulfilled)\n::fulfill — rbac:material.receive"]
        MR_REJECT["Từ chối (rejected)\n::reject"]
    end

    subgraph Receiving["Tiếp nhận"]
        GRN["Phiếu nhập (MaterialReceipt)\nApi\\MaterialReceiptController"]
        LINE["Dòng vật tư (MaterialReceiptLine)\nliên kết Material catalog + unit_cost"]
        CHECKLIST["Checklist nghiệm thu\n(MaterialReceiptChecklist)"]
    end

    subgraph Commercial["Thương mại"]
        CONTRACT["Hợp đồng (Contract)\ncostSummary tổng hợp chi phí"]
        VENDOR["Nhà cung cấp (Vendor)"]
    end

    MR_CREATE --> MR_SUBMIT --> MR_APPROVE --> MR_FULFILL
    MR_SUBMIT --> MR_REJECT
    MR_APPROVE -. "material_request_id" .-> GRN
    VENDOR -. "vendor_id" .-> GRN
    CONTRACT -. "contract_id" .-> GRN
    GRN --> LINE --> CHECKLIST
    LINE -- "quantity × unit_cost" --> CONTRACT
```

## Bất biến cần nhớ khi debug

- **Trạng thái MR chỉ đi một chiều**: draft → submitted → (approved|rejected); approved → fulfilled. Controller chặn ở từng bước.
- **Tenant scope**: MR scope qua `whereHas('project', tenant_id)`; Receipt/Vendor/Contract có cột `tenant_id` trực tiếp.
- Web page controllers (`Web\MaterialRequestPageController`, `Web\ReceiptPageController`) chỉ **delegate** sang Api controllers — không chứa business logic.
- RBAC nằm ở **route level** (`rbac:material.*`), API controllers dùng thêm Policy (`authorize()`).
