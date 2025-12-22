# Round 225 Implementation Report: Contract & Change Order Drilldown

## Overview

Round 225 adds drilldown UI & navigation from the Project Cost Dashboard into detailed Contract & Change Order views, allowing PM/QS to explore all financial layers.

## Implementation Summary

### 1. API & Hooks (Frontend)

#### API Methods Added (`frontend/src/features/projects/api.ts`)

- `getProjectContracts(projectId)` - List all contracts for a project
- `getContractDetail(projectId, contractId)` - Get contract detail with lines
- `getContractChangeOrders(projectId, contractId)` - List change orders for a contract
- `getChangeOrderDetail(projectId, contractId, coId)` - Get change order detail with lines
- `getContractPaymentCertificates(projectId, contractId)` - List payment certificates
- `getContractPayments(projectId, contractId)` - List actual payments

#### TypeScript Interfaces Added

- `ContractSummary` - Contract list item
- `ContractDetail` - Full contract with lines
- `ContractLine` - Contract line item
- `ChangeOrderSummary` - Change order list item
- `ChangeOrderDetail` - Full change order with lines
- `ChangeOrderLine` - Change order line item
- `PaymentCertificateSummary` - Payment certificate item
- `PaymentSummary` - Payment item

#### React Query Hooks Added (`frontend/src/features/projects/hooks.ts`)

- `useProjectContracts(projectId)`
- `useContractDetail(projectId, contractId)`
- `useContractChangeOrders(projectId, contractId)`
- `useChangeOrderDetail(projectId, contractId, coId)`
- `useContractPaymentCertificates(projectId, contractId)`
- `useContractPayments(projectId, contractId)`

### 2. UI Components

#### Contract List Page (`frontend/src/features/projects/pages/ProjectContractsPage.tsx`)

**Features:**
- Table view of all contracts for a project
- Columns: Code, Name, Contractor, Base Amount, Current Amount, Certified, Paid, Outstanding
- Click row → navigate to Contract Detail
- Loading, error, and empty states
- Back to Project button

**Route:** `/app/projects/:id/contracts`

#### Contract Detail Page (`frontend/src/features/projects/pages/ContractDetailPage.tsx`)

**Sections:**
1. **Contract Header**
   - Code, name, contractor
   - Base amount, current amount
   - Start/end dates, status

2. **Contract Lines Table**
   - Item code, description, quantity, unit price, amount
   - Budget line reference

3. **Change Orders Section**
   - Grouped by status: Approved / Pending / Rejected
   - Shows amount deltas
   - Click → Change Order Detail

4. **Payment Certificates**
   - List with amount payable, period, status
   - Table view

5. **Payments**
   - List with amount paid, paid date, reference no
   - Total footer

**Route:** `/app/projects/:id/contracts/:contractId`

#### Change Order Detail Page (`frontend/src/features/projects/pages/ChangeOrderDetailPage.tsx`)

**Sections:**
1. **Change Order Header**
   - Code, title, status
   - Amount delta
   - Reason, effective date

2. **Change Order Lines Table**
   - Contract line reference
   - Quantity delta, unit price delta, amount delta
   - Budget line reference

3. **Summary**
   - Total amount delta at bottom

**Route:** `/app/projects/:id/contracts/:contractId/change-orders/:coId`

### 3. Dashboard Navigation

#### ProjectCostDashboardSection Updates

Added click handlers for drilldown navigation:
- Contract Base Total → Contract List
- Contract Current Total → Contract List
- Approved/Pending/Rejected CO totals → Contract List
- Time-series charts → Contract List

All clickable elements have hover states and cursor-pointer styling.

### 4. Router Configuration

Added routes to `frontend/src/app/router.tsx`:
- `/app/projects/:id/contracts` → ProjectContractsPage
- `/app/projects/:id/contracts/:contractId` → ContractDetailPage
- `/app/projects/:id/contracts/:contractId/change-orders/:coId` → ChangeOrderDetailPage

### 5. Project Detail Page Integration

Added "View Contracts" button in Cost tab that navigates to Contract List.

### 6. Tests

#### Hook Tests (`frontend/src/features/projects/hooks/__tests__/contractHooks.test.ts`)

Tests for all 6 new hooks:
- `useProjectContracts`
- `useContractDetail`
- `useContractChangeOrders`
- `useChangeOrderDetail`
- `useContractPaymentCertificates`
- `useContractPayments`

#### Component Tests (`frontend/src/features/projects/pages/__tests__/ProjectContractsPage.test.tsx`)

Tests for Contract List Page:
- Loading state
- Renders contracts list
- Empty state
- Error state

## Files Changed

### Frontend

1. `frontend/src/features/projects/api.ts`
   - Added 6 API methods
   - Added 8 TypeScript interfaces

2. `frontend/src/features/projects/hooks.ts`
   - Added 6 React Query hooks

3. `frontend/src/features/projects/pages/ProjectContractsPage.tsx` (NEW)
   - Contract list page component

4. `frontend/src/features/projects/pages/ContractDetailPage.tsx` (NEW)
   - Contract detail page component

5. `frontend/src/features/projects/pages/ChangeOrderDetailPage.tsx` (NEW)
   - Change order detail page component

6. `frontend/src/features/projects/components/ProjectCostDashboardSection.tsx`
   - Added navigation handlers

7. `frontend/src/features/projects/pages/ProjectDetailPage.tsx`
   - Added "View Contracts" button in Cost tab

8. `frontend/src/app/router.tsx`
   - Added 3 new routes

9. `frontend/src/features/projects/hooks/__tests__/contractHooks.test.ts` (NEW)
   - Hook tests

10. `frontend/src/features/projects/pages/__tests__/ProjectContractsPage.test.tsx` (NEW)
    - Component tests

## Backend

No backend changes required. All endpoints already exist from Rounds 219-221.

## Acceptance Criteria Status

✅ **New Contracts entry accessible from Project Detail**
- Added "View Contracts" button in Cost tab

✅ **Contracts List:**
- Shows all contracts for project
- Values formatted via MoneyCell
- Click → Contract Detail

✅ **Contract Detail:**
- Shows header, lines, CO groups, certificates, payments
- All loading/error/empty states correct

✅ **Change Order Detail:**
- Shows header + lines
- Matches backend schema

✅ **Dashboard → Drilldown:**
- Clicking relevant totals on Dashboard opens Contract List

✅ **Tests:**
- Vitest tests for hooks
- Vitest tests for Contract List component
- All tests pass

## Design Notes

- Reused existing Card, Table, MoneyCell components
- Consistent spacing & typography with other project pages
- Loading/error/empty states match Round 224 patterns
- Mobile-responsive design
- Accessible keyboard navigation

## Next Steps (Future Rounds)

- Add filtering/search to Contract List
- Add export functionality
- Add editing capabilities (if needed)
- Add PDF export for contracts/change orders
- Add bulk actions

## Testing

All tests pass:
- ✅ Hook tests (6 hooks)
- ✅ Component tests (Contract List Page)
- ✅ No linting errors
- ✅ TypeScript compilation successful

## Notes

- All API endpoints use existing backend routes from Rounds 219-221
- No backend migrations or schema changes
- Pure frontend implementation
- Follows existing architectural patterns
- Maintains consistency with Round 224 dashboard
