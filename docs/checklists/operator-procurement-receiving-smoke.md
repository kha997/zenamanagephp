# Operator Procurement Receiving Smoke Checklist

- Log in as an internal operator user with `material.read`, `material.request`, `material.approve`, `material-receipt.view`, `material-receipt.create`, `material-receipt-line.create`, and `contract.view`.
- Open `/operator/dashboard` and confirm the operator sidebar shows `Dashboard`, `Material Requests`, and `Receipts`.
- Open `/operator/material-requests` and confirm the page loads without errors.
- Create one material request from `/operator/material-requests/create` and confirm the success flash appears.
- Submit the new request from the material requests index and confirm the status changes to `submitted`.
- Approve the same request and confirm the status changes to `approved`.
- Open `/operator/receipts/create`, create a receipt linked to the same project and approved request, and confirm you are redirected to the receipt detail page.
- Add one receipt line on the receipt detail page with quantity and unit cost, then confirm the line appears in the table.
- Confirm the contract cost summary block updates after the line is added and shows a changed total cost and mapped receipt count.
