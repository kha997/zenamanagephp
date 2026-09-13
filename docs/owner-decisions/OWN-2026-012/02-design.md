---
work_id: OWN-2026-012
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: null
  plan: null
  branch: docs/OWN-2026-012-backlog-governance-reconciliation
  pr: https://github.com/kha997/zenamanagephp/pull/315
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-13T21:25:25+07:00"
  owner_response_reference: "Owner decision in-session on 2026-09-13: 'APPROVE OWN-2026-012 Gate 2. APPROVED OPTION: Option B.' Reviewed design head: c8b13ec4b50c9b54c2dbb3ea0428f5cd50025f45. This approves Option B and the exact design contracts/allowlist at that reviewed head, authorizing only the bounded later reconciliation implementation; it does not approve Gate 3, merge, release, deployment, feature work, Issue closure, stale-PR merge, or historical Gate mutation."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-13T21:08:07+07:00"
  updated_at: "2026-09-13T21:25:25+07:00"
generated_by: agent
---

# OWN-2026-012 — Backlog governance reconciliation: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION B

Owner approved Option B in-session on 2026-09-13 against reviewed design head
`c8b13ec4b50c9b54c2dbb3ea0428f5cd50025f45`. This authorizes only the bounded
later reconciliation implementation defined by this packet. It does not
authorize Gate 3, merge, release, deployment, feature work, Issue closure,
stale-PR merge, or historical Gate mutation.

## Owner Summary

Thiết kế đề xuất giữ nguyên audit Gate 1, chỉ sửa đúng các dòng register đã
chứng minh stale, chỉ đóng bốn PR lịch sử theo comment provenance được chốt
sẵn, giữ Issues #244/#248 mở, và tạo một execution record riêng để Gate 3 của
OWN-2026-012 ràng buộc toàn bộ bằng chứng repository-side của reconciliation.

Gate 2 này đã được Owner duyệt theo Option B. Implementation sau quyết định này
vẫn phải tuân thủ exact allowlist, precondition và rollback contract; Gate 3,
merge và deployment tiếp tục chưa được phép.

## Sự thật và ràng buộc bất biến

- Canonical main được kiểm tra lại là
  `60a37b8a7b6f61fa607d36ad8ec9616f84638899`.
- OWN-2026-012 Gate 1 đã được Owner phê duyệt. Audit reconnaissance hiện tại
  là bằng chứng read-only tại
  `docs/audits/2026-09-13-own-2026-012-backlog-governance-reconciliation.md`.
- GAP-040, GAP-042 và GAP-044 có Gate 3 approved/ready và implementation/release
  PR đã merge, nhưng register vẫn mô tả trạng thái cũ.
- GAP-041 và GAP-045 chưa terminal; cả hai phải giữ OPEN.
- Issues #244 và #248 đang OPEN và acceptance scope sản phẩm chưa hoàn tất.
- PRs #264/#283/#285/#297 đang OPEN sau khi trạng thái pre-Gate được khôi phục.
  PRs #245/#257/#276/#277 cũng đang OPEN.
- Không historical Gate packet, approved subject, digest, decision, approval,
  hoặc Owner binding nào được sửa, recompute hay rebind bởi OWN-2026-012.

## Trước / Sau

**Trước:**

1. Register vẫn ghi GAP-040/042/044 không terminal dù release đã merge.
2. GitHub có bốn PR lịch sử/superseded chưa có disposition cuối cùng và bốn PR
   reference cần giữ để bảo toàn yêu cầu/provenance còn hữu ích.
3. Issues #244/#248 là active product work nhưng dễ bị hiểu sai là đã hoàn tất
   vì một số foundation đã merge.
4. Queue thực thi nằm rải rác giữa register, Issues và PR descriptions.

**Sau — chỉ khi Gate 2 được duyệt và implementation sau đó thực hiện đúng
allowlist:**

1. GAP-040/042/044 có terminal wording và exact merged evidence; GAP-041/045
   vẫn OPEN; GAP-015 chỉ được sửa wording để bỏ claim đã trở thành materially
   false, không đổi trạng thái hay ưu tiên.
2. #264/#283/#285/#297 đóng với exact provenance comments; #245/#257/#276/#277
   vẫn mở reference. Không PR nào được merge hay xóa branch.
3. Issues #244/#248 vẫn OPEN và được ghi rõ là `ACTIVE_PRODUCT_WORK`.
4. Một execution record riêng, immutable, ghi resulting GitHub state, evidence,
   queue và verification; Gate-1 audit giữ byte-identical.

## So sánh phương án

| Tiêu chí | Option A — register + existing history only | Option B — register + approved PR mutations + separate execution record | Option C — mutate Gate-1 audit into execution record |
|---|---|---|---|
| Auditability | Đủ để biết register đổi, nhưng resulting external GitHub state không có repository snapshot riêng. | Mạnh nhất: pre-action reconnaissance và post-action execution state là hai artifact tách biệt. | Trộn observation trước authorization với hành động sau authorization; khó biết câu nào được ghi ở thời điểm nào. |
| Owner binding | Register được digest bao phủ; external operations chỉ còn trong GitHub history. | Register và execution record đều được OWN digest bao phủ; exact closure state/comment URLs được repository-side record trích dẫn. | Final audit được digest bao phủ nhưng pre-Gate evidence bị viết lại sau quyết định. |
| Historical-evidence immutability | Không sửa Gate packets, nhưng provenance hậu hành động phân tán. | Không sửa Gate packets; Gate-1 audit và cross-work evidence giữ nguyên. | Không sửa Gate packets nhưng làm mất byte identity của chính Gate-1 evidence. |
| Digest coverage | Bao phủ register; không có dedicated blob cho external result. | Bao phủ register và dedicated execution record; active OWN Gate 3 bị loại đúng self-reference. | Bao phủ mutated audit, nhưng không thể chứng minh byte-identical pre-action evidence. |
| Rollback | Revert register; GitHub state phải suy ra từ history. | Revert register/execution record và reopen PR khi khả thi; execution record + GitHub audit history giải thích cả hai chiều. | Revert khó đọc hơn vì một file đổi vai trò qua nhiều lifecycle phase. |
| Future-agent clarity | Trung bình. | Cao: request/design/reconnaissance/execution/release có vai trò riêng. | Thấp hơn do audit vừa là evidence vừa là mutable execution log. |

**Khuyến nghị: Option B.** Repository có precedent trực tiếp tại OWN-2026-011:
active Gate-3 packet tự loại khỏi digest, cross-work Gate-3 packets cũng bị
loại, còn register và `docs/audits/*` được bao phủ. Tách execution record là
cách nhỏ nhất vừa giữ pre-Gate evidence bất biến vừa đưa external-result
provenance vào subject mà Gate 3 của OWN-2026-012 ràng buộc.

## Exact implementation allowlist sau khi Gate 2 được duyệt

Eventual implementation mutation được phép chạm đúng:

1. `OPERATIONAL_GAP_REGISTER.md` — chỉ các dòng GAP-040, GAP-042, GAP-044 và
   GAP-015 theo exact contracts dưới đây; không dòng nào khác.
2. `docs/audits/2026-09-13-own-2026-012-backlog-governance-reconciliation-execution.md`
   — file mới, dedicated immutable execution/reconciliation record.
3. `docs/owner-decisions/OWN-2026-012/02-design.md` — chỉ để ghi quyết định
   Gate-2 thật của Owner; không sửa design semantics ngoài quyết định đó.
4. `docs/owner-decisions/OWN-2026-012/03-release.md` — chỉ được tạo sau
   implementation/verification để trình Gate 3, theo schema canonical.

Các lifecycle record ở mục 3–4 là packet governance, không phải reconciliation
content. Không tạo implementation plan. File reconnaissance Gate 1
`docs/audits/2026-09-13-own-2026-012-backlog-governance-reconciliation.md` và
Gate-1 packet phải giữ byte-identical sau Gate-2 approval.

Mọi file ngoài allowlist là hard STOP, gồm `app/`, `src/`, `routes/`,
`database/`, `resources/`, `tests/`, `.github/workflows/`, CI scripts,
deployment config và production data.

## Exact register contracts

### GAP-040

- Giữ nguyên problem/root-cause history trong cột tiêu đề.
- Cột `Status` phải là chính xác:
  `**RESOLVED (verified 2026-09-13)**`.
- Cột evidence phải giữ citation code/migration hiện có và bổ sung:
  `docs/owner-decisions/GAP-040/03-release.md`, PR
  `https://github.com/kha997/zenamanagephp/pull/272`, merge SHA
  `aab48a23709534f5111db4580121aec28e66583d`, approved implementation subject
  `f8f4d1102d40188eb71024c8eab834a9efbae88f`, historical digest
  `c9425c973300ef31310221c89bb942f7b1f3f07d9e45aaa501a86818af1dde18`, và
  dedicated OWN execution record.
- Exact terminal note:

> **Released to main via PR #272 at squash/merge SHA
> `aab48a23709534f5111db4580121aec28e66583d`.** Historical Gate-3 approval in
> `docs/owner-decisions/GAP-040/03-release.md` remains bound to implementation
> subject `f8f4d1102d40188eb71024c8eab834a9efbae88f` and implementation-tree
> digest `c9425c973300ef31310221c89bb942f7b1f3f07d9e45aaa501a86818af1dde18`;
> OWN-2026-012 cites but does not recompute or rebind them. Administrative
> provenance is recorded in the dedicated OWN-2026-012 execution record.

### GAP-042

- Thay prefix mô tả `CHƯA XÁC MINH` bằng chính xác:
  `**HISTORICAL DEFECT — reproduced, corrected, Owner-approved and released.**`
  Giữ phần mô tả hữu ích về live-mounted RBAC/table-rename/test-shim root cause
  như historical context; bỏ các câu future-tense nói Gate 1 chưa bắt đầu.
- Cột `Status` phải là chính xác:
  `**RESOLVED (verified 2026-09-13)**`.
- Evidence bắt buộc:
  `docs/owner-decisions/GAP-042/03-release.md`, PR #299 URL, merge SHA
  `0872ac856932193a037ce30f00050179374811af`, approved implementation subject
  `13e9e64df3c9ceba29dd191494df8a4ee757b1f5`, historical digest
  `6192e9e48ffba5d04b875baf16b8848ee2d9e069645f3284367a2a7de2e22917`, và
  dedicated OWN execution record. PR #298 có thể được cited làm merged Gate-2
  design provenance nhưng không thay PR #299/Gate 3 làm terminal authority.
- Exact terminal note:

> **Released to main via PR #299 at squash/merge SHA
> `0872ac856932193a037ce30f00050179374811af`.** Historical Gate-3 approval in
> `docs/owner-decisions/GAP-042/03-release.md` remains bound to implementation
> subject `13e9e64df3c9ceba29dd191494df8a4ee757b1f5` and implementation-tree
> digest `6192e9e48ffba5d04b875baf16b8848ee2d9e069645f3284367a2a7de2e22917`;
> OWN-2026-012 cites but does not recompute or rebind them. Administrative
> provenance is recorded in the dedicated OWN-2026-012 execution record.

### GAP-044

- Thay câu root-cause-unverified trong cột mô tả bằng chính xác:
  `**HISTORICAL DEFECT — root cause confirmed, corrected, Owner-approved and released.**`
  Phần historical detail phải nói rõ hai surface đã release: shared test-helper
  DDL phá transaction isolation và permission fixture lookup bằng `name` thay
  vì canonical `code`. Không xóa lịch sử triệu chứng SAVEPOINT 1305.
- Cột `Status` phải là chính xác:
  `**RESOLVED (verified 2026-09-13)**`.
- Evidence bắt buộc:
  `docs/owner-decisions/GAP-044/03-release.md`, PR #286 URL, merge SHA
  `c3a1226059bcf5a573aad1eebf8f1333331d9ad2`, approved implementation subject
  `4361c5f59cbba548664a68d0b84fb440c9b54da3`, historical digest
  `716ea9cf50e4ab5ccbe478bd3a6ccf63aab2043e6dbd069db5a2b850eddf3d28`, và
  dedicated OWN execution record.
- Exact terminal note:

> **Released to main via PR #286 at squash/merge SHA
> `c3a1226059bcf5a573aad1eebf8f1333331d9ad2`.** Historical Gate-3 approval in
> `docs/owner-decisions/GAP-044/03-release.md` remains bound to implementation
> subject `4361c5f59cbba548664a68d0b84fb440c9b54da3` and implementation-tree
> digest `716ea9cf50e4ab5ccbe478bd3a6ccf63aab2043e6dbd069db5a2b850eddf3d28`;
> OWN-2026-012 cites but does not recompute or rebind them. GAP-045 remains
> separate and OPEN. Administrative provenance is recorded in the dedicated
> OWN-2026-012 execution record.

### GAP-041 and GAP-045 — mandatory non-change

- GAP-041 remains `**OPEN (verified 2026-08-20)**`; no selector, workflow,
  description, status or evidence mutation is permitted here.
- GAP-045 remains `**UNVERIFIED (LIVE assertion observed 2026-08-21)**`; its
  450ms threshold is not changed and no resolution is inferred from any later
  isolated passing observation.

### GAP-015 — bounded truthfulness correction

Current main proves a mounted Project-level template list/preview/apply UI at
`routes/web.php:371-373`, implemented by
`app/Http/Controllers/Web/WorkTemplateApplyController.php` and exercised by
`tests/Browser/Projects/WorkTemplateApplyBrowserTest.php`. Therefore the note
claiming users have no screen to use is materially false. Status and priority
remain unchanged.

- Exact title replacement:
  `Chưa có UI/screen owner được xác nhận cho toàn bộ vòng đời authoring/quản lý WorkTemplate → WorkInstance; đã có UI giới hạn để chọn, preview và apply template ở cấp Project`.
- Status remains exactly: `OPEN (tự khai là ngoài phạm vi MVP)`.
- Evidence must add the three current-main sources above while retaining the
  existing audit/roadmap citations.
- Exact replacement note:

> Current main provides a bounded Project-level UI to list, preview and apply
> a WorkTemplate. It does not establish complete Owner-confirmed operational
> screen ownership for authoring and managing the full WorkTemplate →
> WorkInstance lifecycle. This remains a strategic business decision, not
> authorization to expand UI.

No other register row may change, including mechanical cleanup.

## Exact PR disposition policy

Closing means GitHub `state=CLOSED`, `mergedAt=null`, branch/content unchanged,
and the audit history remains reachable. A closure is forbidden if the live
state/head differs from this design or any unique required work would become
unavailable.

| PR | Evidence and authority analysis | Designed disposition | Exact provenance comment |
|---|---|---|---|
| #264 | Its two Gate-1/2 files are not blobs on current main, so byte identity is **not** claimed. They remain available in GitHub history. Canonical release PR #265 is merged at `cb5cb893d92b8ef0534672d7f5c7bfe35062eb64`; current-main GAP-038 Gate 3 records the approved Option B and is the release authority. No unmerged runtime work exists in #264. | Close only after rechecking head `d394ff5d797cbf566d2bd6cfdb3c03bad83887e8`, OPEN/unmerged, and #265/Gate 3 unchanged. | `Closing as historical/superseded under Owner-approved OWN-2026-012. Canonical GAP-038 implementation/release merged via PR #265 at cb5cb893d92b8ef0534672d7f5c7bfe35062eb64. This PR's Gate-1/Gate-2 source records remain available in GitHub history; canonical release authority is preserved on main. No branch or content is deleted, and no unique unmerged implementation is discarded.` |
| #283 | Both payload blobs are byte-identical to current main. PR #286 merged at `c3a1226059bcf5a573aad1eebf8f1333331d9ad2`; Gate 3 is approved. | Close after exact-head/state recheck. | `Closing as historical/superseded under Owner-approved OWN-2026-012. Both Gate-1 payload files are byte-identical to current main and GAP-044 implementation/release merged via PR #286 at c3a1226059bcf5a573aad1eebf8f1333331d9ad2. GitHub history remains available; no branch or content is deleted.` |
| #285 | All four payload blobs are byte-identical to current main. PR #286/Gate 3 is canonical release authority. | Close after exact-head/state recheck. | `Closing as historical/superseded under Owner-approved OWN-2026-012. All four design/evidence payload files are byte-identical to current main and GAP-044 implementation/release merged via PR #286 at c3a1226059bcf5a573aad1eebf8f1333331d9ad2. GitHub history remains available; no branch or content is deleted.` |
| #297 | Its Gate-1 request/audit are not blobs on current main, so byte identity is **not** claimed; they remain in GitHub history. Gate 2 merged via #298 at `673855f69a3633b64c378e965ae409ed3a098c50`; implementation/release #299 merged at `0872ac856932193a037ce30f00050179374811af`, and current-main Gate 3 is canonical authority. No unmerged implementation exists in #297. | Close only after rechecking head `3667aa44a7d67481805dc50dc8bcf68a1c440a5f`, OPEN/unmerged, and downstream evidence unchanged. | `Closing as historical/superseded under Owner-approved OWN-2026-012. GAP-042 Gate 2 merged via PR #298 at 673855f69a3633b64c378e965ae409ed3a098c50 and implementation/release merged via PR #299 at 0872ac856932193a037ce30f00050179374811af. This PR's Gate-1 source records remain available in GitHub history; canonical release authority is preserved on main. No branch or content is deleted, and no unique unmerged implementation is discarded.` |
| #245 | Contains Treasury runtime/UI/workflow requirements not fully absorbed by current runtime. | Preserve OPEN/reference. Closure requires a future #244 lifecycle to canonicalize all still-required requirements, or an explicit Owner decision that every remainder is superseded. | None. |
| #257 | Retains useful Project OPPM/control-tower design; shared semantics are only partly superseded. | Preserve OPEN/reference. Closure requires governed canonicalization of its useful OPPM material and proof that no unique requirement remains. | None. |
| #276 | Preserves unique GAP-041 Gate-1 request/evidence provenance while GAP-041 remains live. | Preserve OPEN/reference. Closure requires a fresh GAP-041 lifecycle to carry forward that provenance and an authorized disposition. | None. |
| #277 | Contains Owner-approved Option D, but is stale and unimplemented. | Preserve OPEN/reference. Closure requires Option D to be revalidated and either released or explicitly superseded by a later Owner-approved Gate-2 design, with provenance retained. | None. |

No PR may be closed or merged during Gate 2. No branch may be deleted or
rewritten. Exact comments must not be shortened in a way that removes the
qualification for #264/#297.

## Issue lifecycle contracts

| Issue | Current classification/state | Closure evidence required later |
|---|---|---|
| #244 | `ACTIVE_PRODUCT_WORK`, OPEN. Treasury schema/model/CHECK foundation exists; posting, approval, ledger/register, reconciliation, dashboard/report and usable UI workflow remain incomplete. | A separately governed implementation must satisfy the Issue's mandatory business decisions and accepted scope, prove tenant/project isolation and financial invariants, receive its own Gate-3 approval, merge, and obtain explicit acceptance that no requested capability remains. Foundation merges alone are insufficient. |
| #248 | `ACTIVE_PRODUCT_WORK`, OPEN. No canonical OPPM runtime exists; PR #257 remains source material pending canonicalization. | A separately governed Project-OPPM program must canonicalize metrics/data sources, deliver and verify the one-project read model/page, reliability semantics, permissions/isolation, performance and print acceptance criteria, receive Gate-3 approval, merge, and obtain explicit acceptance that the Issue criteria are complete. Umbrella/foundation documents alone are insufficient. |

OWN-2026-012 must not comment on, edit, label, or close either Issue during
implementation unless a later Owner instruction explicitly expands scope.

## Canonical evidence-backed execution queue

Revalidation against current main supports the proposed order without change.
The order reflects dependency and user impact, not numeric Gap ID. Every item
requires a new session from then-current canonical main.

| # | Item / current status | Dependency or blocker | Next bounded action | Recommended agent/reasoning | New session | Design Dependency Preflight |
|---:|---|---|---|---|---|---|
| 1 | GAP-041 — `ACTIONABLE_TECHNICAL_GAP`, OPEN | Preserve #276/#277 provenance; current selectors remain untruthful. | Revalidate approved Option D against current workflow, then follow its own lifecycle for selector repair. | Codex / GPT-5.6 Sol / High | Yes | No; required if scope expands into business/domain semantics. |
| 2 | GAP-045 — `ACTIONABLE_TECHNICAL_GAP`, OPEN/unverified | GAP-041 must make measurement truthful first; do not change 450ms. | Gate-1 controlled repeated live reproduction only. | Codex / GPT-5.6 Sol / High | Yes | Conditional for Project/CRM/Service-Line/RBAC/tenant/Finance/OPPM changes. |
| 3 | GAP-017 — `ACTIONABLE_TECHNICAL_GAP`, OPEN | Invitation contract and normal Owner gates. | Verify mounted expired path; design smallest truthful expired-state page/response. | Codex / GPT-5.6 Sol / Medium | Yes | No. |
| 4 | Issue #244 — `ACTIVE_PRODUCT_WORK`, OPEN | OWN-2026-009, GAP-037 v17/GAP-038; PR #245 is reference. | Fresh discovery for next smallest usable Treasury runtime vertical slice. | Codex / GPT-5.6 Sol / High | Yes | Yes: Finance/Treasury, Project, tenant, RBAC. |
| 5 | Issue #248 — `ACTIVE_PRODUCT_WORK`, OPEN | Current Project/Task/Service-Line/Treasury truth; PR #257 canonicalization. | Fresh OPPM discovery and governed canonicalization before implementation. | Codex / GPT-5.6 Sol / High | Yes | Yes: Project/OPPM/Finance/Service-Line. |
| 6 | GAP-012 — `ACTIONABLE_TECHNICAL_GAP`, OPEN/deferred | Recipient/event semantics and deduplication. | Gate-1 evidence plus business recipient matrix. | Codex / GPT-5.6 Sol / Medium | Yes | Yes: Project workflow. |
| 7 | GAP-013 — `ACTIONABLE_TECHNICAL_GAP`, OPEN | Full fan-out distinct from existing resubmit path. | Gate-1 audit and one canonical notification contract. | Codex / GPT-5.6 Sol / Medium | Yes | Yes: Submittal/Project workflow. |
| 8 | GAP-014b — `ACTIONABLE_TECHNICAL_GAP`, OPEN | Canonical mutation owner and delivery/idempotency semantics. | Gate-1 investigation before event wiring. | Codex / GPT-5.6 Sol / Medium | Yes | Yes: NCR/CAPA workflow. |
| 9 | GAP-014c — `ACTIONABLE_TECHNICAL_GAP`, OPEN | Schema/lifecycle/tenant-parent integrity/history policy. | Gate-1 discovery and business design before migration. | Codex / GPT-5.6 Sol / High | Yes | Yes: schema, Project, tenant. |
| 10 | GAP-030 — `DEFERRED_BY_OWNER` | Owner must choose resolver roles/capability. | Obtain business capability decision, then resume gates. | Codex / GPT-5.6 Sol / High | Yes | Yes: RBAC/tenant/workflow. |
| 11 | GAP-015 — `DEFERRED_BY_OWNER`, OPEN | Bounded apply UI exists; full lifecycle screen ownership is undecided. | Business discovery; do not expand UI because backend exists. | Codex / GPT-5.6 Sol / High | Yes | Yes: Project/template/OPPM boundaries. |
| 12 | GAP-016 — `ACTIONABLE_TECHNICAL_GAP`, OPEN | Must identify consumers and preserve Project semantics. | Verify consumers; design bounded delete/redirect. | Codex / GPT-5.6 Sol / Medium | Yes | Yes for Project route. |
| 13 | GAP-011 — `ACTIONABLE_TECHNICAL_GAP`, OPEN | Low priority; preserve production fail-closed gate. | Audit whether remaining non-production debug routes are still useful. | Codex / GPT-5.6 Sol / Medium | Yes | No. |
| 14 | GAP-020 — `ACTIONABLE_TECHNICAL_GAP`, OPEN | Confirm no runtime/include consumer. | Narrow archive/delete lifecycle. | Codex / GPT-5.6 Sol / Low–Medium | Yes | No. |
| 15 | GAP-021 — `ACTIONABLE_TECHNICAL_GAP`, UNVERIFIED | Current route/middleware/consumer inventory required. | Dedicated Gate-1 architecture/contract audit. | Codex / GPT-5.6 Sol / High | Yes | Yes: Task/tenant/RBAC ownership. |
| 16 | GAP-018 — `ACTIONABLE_TECHNICAL_GAP`, OPEN | Confirm no dynamic consumer. | Archive/delete only; do not revive guessed APIs. | Codex / GPT-5.6 Sol / Low–Medium | Yes | No. |
| 17 | GAP-019 — `DEFERRED_BY_OWNER`, OPEN | Keep-as-demo decision; no product owner for revival. | Leave dormant or separately approve archival cleanup. | Codex / GPT-5.6 Sol / Medium | Yes | Conditional if reactivation proposed. |
| 18 | GAP-026 — `BLOCKED_EXTERNAL` | External Slack administrator must identify webhook destination. | Obtain routing fact before any secret/workflow design. | Human Slack admin, then Codex / GPT-5.6 Sol / Medium | Yes | No. |

## Dedicated execution record contract

The new file must use this frontmatter:

```yaml
---
work_id: OWN-2026-012
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/OWN-2026-012/02-design.md
record_type: immutable_reconciliation_execution
---
```

It must record factual post-action evidence only:

- canonical base, implementation subject, timestamps and exact changed files;
- before/after register status for GAP-040/042/044 and wording-only GAP-015;
- unchanged status for GAP-041/GAP-045 and every non-allowlisted row;
- for each closure: PR number, expected and observed head, state before/after,
  `mergedAt:null`, exact closure-comment URL and downstream merge evidence;
- observed unchanged OPEN state/head for #245/#257/#276/#277;
- observed unchanged OPEN state for Issues #244/#248;
- historical Gate-3 packet hashes and confirmation of zero diff;
- exact queue and verification results;
- explicit no-feature/no-merge/no-deployment statement;
- any attempted action that failed, its resulting state, and rollback outcome.

The execution record cannot declare Owner approval, cannot replace a Gate
packet, and becomes immutable after the implementation subject is frozen.

## Digest coverage — inspected canonical semantics

`owner_governance_compute_implementation_tree_digest()` in
`scripts/ssot/owner_governance_lint.php` was inspected directly. It obtains
every blob via `git ls-tree -r <subject>`, then excludes only:

1. the active `docs/owner-decisions/OWN-2026-012/03-release*.md` packet, if it
   exists, to avoid self-reference; and
2. every recognized `docs/owner-decisions/<other-work-id>/03-release*.md`
   cross-work Gate-3 packet.

It does **not** exclude `OPERATIONAL_GAP_REGISTER.md`, `docs/audits/*`, Gate 1,
or Gate 2. Therefore the eventual register blob and dedicated execution-record
blob are included in the OWN-2026-012 implementation-tree manifest and any
change to either changes the digest. Gate 1 and approved Gate 2 are also
included. The active OWN-2026-012 Gate-3 packet is normally excluded but stores
the exact subject/digest and Owner binding. Historical GAP-040/042/044 Gate-3
packets are cross-work exclusions: they must remain byte-identical and are
cited, never edited or recomputed, so the exclusion cannot conceal an
OWN-2026-012 mutation.

Gate-3 preparation must inspect the canonical manifest and prove both exact
paths are present before recommending readiness; textual reasoning alone is
insufficient.

## Rollback and failure semantics

- Before any mutation, fetch GitHub state and canonical main again. Any head,
  state, merge evidence, file-content, Issue state, or historical-packet hash
  difference from this design is a hard STOP; return Gate 2 for correction
  rather than adapt silently.
- If a supposedly superseded PR contains unique required unmerged work, do not
  close it. Record the conflict and STOP.
- If register evidence conflicts with historical approved Gate records, the
  historical approved evidence wins; do not edit either source and return this
  Gate-2 design for correction.
- Execute repository content first as a normal commit, then the four approved
  closure/comment pairs one at a time, verifying each resulting state. Never
  batch past a failed or unexpected mutation.
- If a closure succeeds but later implementation verification fails, preserve
  its GitHub audit trail and reopen that PR where feasible before Gate 3, using
  a factual restoration comment. Record both events in the execution record.
- If the exact provenance comment fails after closure, reopen the PR before
  proceeding unless GitHub proves the same required provenance already exists.
- Repository rollback uses normal revert commits. GitHub rollback uses reopen,
  never deletion. No force-push, history rewrite, branch deletion, stale-PR
  merge, or production operation is allowed.

## Vai trò bị ảnh hưởng

- Owner receives one truthful backlog and retains every decision boundary.
- Engineering agents gain a single prioritized queue and unambiguous reference
  vs execution PR semantics.
- Reviewers/auditors can compare immutable pre-action reconnaissance with the
  digest-bound post-action execution record.
- Product users see no runtime or UI behavior change from this work.

## Được phép / Không được phép

At `awaiting_owner`, only this design exists; no reconciliation action is
allowed. If Owner approves Gate 2, an agent may execute only the exact allowlist,
register contracts and four PR closure/comment operations above, then verify and
prepare Gate 3. Gate-2 approval does not authorize Gate 3, merge, release or
deployment. Issues #244/#248 and open-reference PRs remain untouched.

## Trạng thái và bước tiếp theo

- Gate 2 `awaiting_owner`: PR #315 stays Draft/Open; wait.
- Gate 2 `approved`: record the real decision, then perform bounded
  implementation in the same governed work item; freeze subject and prepare
  Gate 3 only after verification.
- Gate 2 `changes_requested`: update this packet only; no implementation.
- Gate 2 `declined`: no reconciliation mutation; retain evidence/history.
- Gate 3 `awaiting_owner`: PR remains Draft/Open/unmerged.
- Gate 3 `approved`: only the explicitly recorded Owner release/merge direction
  may be followed; approval is not inferred from green CI.

## Ngoại lệ

- #264/#297 are not byte-identical to main; their unique historical source
  documents remain in GitHub. This does not make them implementation authority,
  but it requires the exact qualified closure comments above.
- A PR already closed by someone else, an Issue unexpectedly closed, or a
  changed head is drift, not permission to skip an action.
- GitHub API partial success must be treated as a failed transaction requiring
  state restoration where feasible and a stop report.
- Markdown wrapping may change mechanically, but exact status values, IDs,
  URLs, SHAs, digests and semantic statements may not be weakened.

## Hành vi người dùng nhìn thấy

No product user behavior changes. Owner/reviewers will see terminal register
truth for three released gaps, truthful GAP-015 wording, four provenance-backed
historical PR closures, four preserved reference PRs, two preserved active
Issues, and one canonical queue—only after later authorized implementation.

## Kịch bản chấp nhận

1. Given GAP-040/042/044 approved Gate-3 and merged evidence, when the register
   is reconciled, then each has the exact terminal status/citations above and
   its historical packet remains byte-identical.
2. Given GAP-041/045 are non-terminal, when the diff is inspected, then neither
   row nor any workflow/threshold is changed.
3. Given the bounded template-apply UI exists, when GAP-015 wording is updated,
   then status stays OPEN and the note does not imply full lifecycle UI exists.
4. Given #264/#283/#285/#297 exactly match the expected preconditions, when
   closed, then each is unmerged, retains its branch/content/history and has the
   exact provenance comment.
5. Given #245/#257/#276/#277, when implementation ends, then each remains OPEN
   at its expected head and no comment/content mutation occurred.
6. Given Issues #244/#248, when implementation ends, then both remain OPEN and
   are recorded as `ACTIVE_PRODUCT_WORK`.
7. Given external GitHub mutations occurred, when the subject is frozen, then
   the dedicated execution record contains exact resulting states/comment URLs.
8. Given the implementation-tree manifest, when computed canonically, then the
   register and execution record are present and bound; active OWN Gate 3 is
   excluded only for self-reference and historical cross-work Gate 3 files are
   unchanged.
9. Given any precondition drift or verification failure, then the agent stops,
   restores affected PR state where feasible, and does not present Gate 3 as
   ready.
10. Given final verification, then only allowlisted docs/governance files differ,
    lint/gate ordering/diff/Routes Guardrails pass, no production run exists,
    and PR #315 remains Draft/Open/unmerged.

## Verification contract for eventual implementation

- exact canonical-main and merge-base check;
- allowlist and per-register-row diff check;
- byte comparison/hashes for Gate-1 audit and every historical Gate-3 packet;
- live GitHub state/head/comment verification for all eight PRs and two Issues;
- targeted and full Owner Governance Lint;
- gate ordering and `git diff --check`;
- Routes Guardrails;
- canonical digest computation plus manifest proof for register/execution record;
- exact-head GitHub Actions reported separately from local checks;
- production workflow query proving no run/deployment was invoked by this work.

## Loại trừ phạm vi

- No feature work or fix/reproduction for GAP-041, GAP-045, or any queue item.
- No Issue #244/#248 closure or product implementation.
- No merge of any stale/reference PR and no closure beyond the four exact
  candidates after authorization.
- No modification of historical Gate packets, decisions, subjects, digests,
  bindings or approval evidence.
- No implementation plan, Gate 3, final execution record, register mutation or
  PR closure in this Gate-2 turn.
- No application/source/test/workflow/CI/migration/schema/route/runtime/
  deployment-config/production-data change.

## Decision Needed

Owner chose: **Approve Option B and the exact contracts above to proceed to
bounded implementation.**

## What the owner is NOT being asked to decide

Owner is not being asked to approve completed reconciliation, Gate 3, merge,
release, deployment, any feature fix, any Issue closure, or any historical Gate
mutation. This decision is only whether Option B, its allowlist, exact register
contracts, exact PR dispositions, queue, digest treatment and rollback rules
are the correct business/governance design for later implementation.
