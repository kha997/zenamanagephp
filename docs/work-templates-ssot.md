# Work Templates MVP (SSOT Aligned)

## Implemented in this PR (MVP)
- Work Template (WT) backend with draft + immutable published versions.
- Work Instance (WI) backend created from `work_template_version_id` snapshot.
- Multi-tenant data model for WT/WI and related records (`tenant_id` on all WT/WI tables).
- ULID string IDs for new WT/WI/DT tables.
- RBAC-protected `api/zena` endpoints for template lifecycle and work execution.
- Audit log events for create/update/publish/apply/approve/import/export.
- Minimal Deliverable Template (DT) metadata/version pointer storage using `documents` + `document_versions` references.
- JSON template package import/export endpoints (ZIP deferred).

## Out of scope (Later phases)
- DOCX merge/render pipeline for deliverable export.
- Full ZIP package handling (`manifest.json + work_template.json + assets`) beyond JSON payload transport.
- Advanced rules engine and dynamic workflow branching beyond basic JSON storage.
- Rich attachment workflows for WI step execution (MVP includes attachment hook payload only).
- UI for WT/WI management and execution.
