# Canonical Pilot RBAC Alignment Lock

Date: 2026-04-15
Type: bounded implementation lock
Status: accepted

## Scope

Align the canonical RBAC seeding contract for the already-locked procurement receiving + contract cost read-side pilot vertical only.

In scope:

- materials
- vendors
- project-scoped contracts
- material requests
- material receipts
- material receipt checklists
- material receipt lines
- contract mapped receipts
- contract cost summary

## Root Cause

The pilot runtime was already internally split across two permission vocabularies:

- `material-requests` uses the proved contract `material.read`, `material.request`, `material.approve`, `material.receive`
- materials, vendors, project-scoped contracts, material receipts, receipt lines, and receipt checklists use the canonical runtime contract `*.view`, `*.create`, `*.update`, `*.delete`

`ZenaRbacSeeder` only seeded the first group, so a locally seeded `super_admin` could pass RBAC middleware but still fail policy-backed pilot controllers that call `User::hasPermission()` with literal runtime permission names like `material.view`, `vendor.view`, or `contract.view`.

## Decision

The bounded fix is to align seeding to the pilot runtime that already exists, not to rewrite routes or policies.

This round therefore:

1. Extends `ZenaRbacSeeder` with the exact missing pilot permission vocabulary already used by canonical pilot runtime:
   - `material.view|create|update|delete`
   - `vendor.view|create|update|delete`
   - `contract.view|create|update|delete`
   - `material-receipt.view|create`
   - `material-receipt-line.view|create`
   - `material-receipt-checklist.view|create`
2. Keeps the existing material-request permission contract unchanged.
3. Adds the aligned pilot permission set to the seeded pilot-relevant roles where that is already semantically consistent, while `super_admin` continues to receive the full seeded corpus.
4. Adds a focused route-access proof using the seeded `super_admin`.

## Why This Boundary

This is narrower and safer than normalizing all pilot families to a different read/write vocabulary because:

- the runtime route middleware and policies already use the `view/create/update/delete` names for most pilot owner families
- the pilot smoke test and existing feature suites already encode that same runtime truth
- changing the runtime vocabulary would force route, policy, and test rewrites across already-proved pilot families

## Verify Evidence

- `php artisan db:seed --class=ZenaRbacSeeder`
  - passes
- `XDG_CONFIG_HOME=/tmp php artisan tinker --execute="echo 'PILOT_RBAC_PERMISSION_COUNT=' . App\\Models\\Permission::count() . PHP_EOL;"`
  - `PILOT_RBAC_PERMISSION_COUNT=68`
- `php ./vendor/bin/phpunit tests/Feature/Api/ProcurementReceivingContractCostPilotRbacAlignmentTest.php`
  - passes and proves seeded `super_admin` no longer receives `403` on:
    - `/api/zena/materials`
    - `/api/zena/vendors`
    - `/api/zena/projects/{project}/contracts`
    - `/api/zena/material-requests`
- `php ./vendor/bin/phpunit tests/Feature/Api/ProcurementReceivingContractCostPilotSmokeTest.php`
  - passes
- `php ./vendor/bin/phpunit tests/Feature/Api/MaterialRequestApiTest.php`
  - passes
- `php ./vendor/bin/phpunit tests/Feature/Api/MaterialReceiptApiTest.php`
  - passes
- `php ./vendor/bin/phpunit tests/Feature/Api/MaterialReceiptLineApiTest.php`
  - passes
- `php ./vendor/bin/phpunit tests/Feature/Api/MaterialReceiptChecklistApiTest.php`
  - passes
- `php ./vendor/bin/phpunit tests/Feature/Api/ContractApiTest.php`
  - passes
- `php ./vendor/bin/phpunit tests/Feature/Api/ContractApiHardeningTest.php`
  - passes
- `php ./vendor/bin/phpunit tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`
  - passes

## Guardrails

- Do not use this round to rewrite Material Request semantics into `view/create/update/delete`.
- Do not widen this alignment into inventory, PO, invoice, payment, dashboard, or other non-pilot modules.
- Do not change route ownership, payload shape, request↔receipt linkage, or contract/payment semantics as part of RBAC alignment.
- Do not promote `/api/v1/*` compatibility aliases into pilot truth.
