# Post-S6.5 Evidence Intake Map And Trigger Protocol

Date: 2026-04-04
Status: docs-only process-control memo

## Why this artifact exists

The post-`S6.5` blocker is already locked, and the unlock requirements memo already defines the three mandatory unlock conditions.

This artifact does not unlock roadmap planning. It exists only to tell future threads where valid unlock evidence may come from, what order to review it in, and what must be treated as noise.

This artifact does not create `S6.6`.

This artifact does not open `EPIC-7`.

This artifact does not patch runtime.

## A. Current Locked State

From `docs/change-proposals/2026-04-04-post-s6-5-roadmap-blocker-lock.md`, `docs/change-proposals/2026-04-04-post-s6-5-roadmap-unlock-requirements-memo.md`, `docs/progress.md`, and `docs/roadmap/backlog.yaml`:

- roadmap currently stops at proved `S6.5`
- no `S6.6` exists
- no `EPIC-7` exists
- unlock remains blocked until one candidate simultaneously proves:
  - exact next story key/title
  - exact canonical owner anchor
  - exact narrow first proof surface

Until then, next-story status remains `UNKNOWN`.

## B. Authoritative Evidence Sources

Only the sources below may create a real post-`S6.5` unlock signal.

### 1. Explicit new change proposal

Why it matters:

- a change proposal is the cleanest planning artifact for naming the next story without inventing runtime scope
- it can explicitly bind story identity, owner anchor, and first proof surface in one place

Minimum acceptable effect:

- names one exact post-`S6.5` story key/title
- names one exact canonical owner anchor
- narrows one exact first proof surface

### 2. Explicit new readiness lock

Why it matters:

- a readiness lock is the narrowest docs-first way to convert owner-backed evidence into a controlled next step
- it can promote one known candidate from blocked residue into an owner-anchored next slice

Minimum acceptable effect:

- stays narrower than speculative roadmap planning
- is grounded in current SSOT plus runtime truth
- defines the exact first proof surface rather than a broad family

### 3. Explicit backlog/progress update with owner-backed basis

Why it matters:

- `docs/roadmap/backlog.yaml` is planning/story-status SSOT
- `docs/progress.md` is execution-progress SSOT
- if either file explicitly promotes a candidate with owner-backed basis, that is a planning signal, not just inventory

Minimum acceptable effect:

- the wording is explicit, not interpretive
- the promotion is supported by canonical owner truth, not by compatibility residue
- the update names the next story unambiguously

### 4. New canonical owner family on `/api/zena/*`

Why it matters:

- some blocked candidate families cannot unlock until a canonical owner exists at all
- a real new `/api/zena/*` owner family can remove owner-blocked status for areas like notification rules, event records, QC dashboard, or Finance dashboard

Minimum acceptable effect:

- the owner family is real and canonical on `/api/zena/*`
- SSOT planning evidence explicitly explains why that owner family now becomes the next story anchor

### 5. Module ownership SSOT update with explicit planning effect

Why it matters:

- `docs/architecture/module-ownership-ssot.md` can clarify whether a route family is canonical, compatibility-only, or frozen
- by itself it is usually ownership evidence only, not roadmap evidence

Minimum acceptable effect:

- the update does more than restate ownership
- it explicitly changes owner ambiguity in a way that planning artifacts can consume immediately

If the module ownership update does not produce explicit planning impact, it remains verification support only.

## C. Non-Authoritative / Non-Trigger Sources

The sources below do not unlock roadmap planning by themselves.

### Mounted route alone

Why not enough:

- route presence proves runtime inventory only
- it does not prove exact next story key/title
- it does not prove that the route is the next honest owner in roadmap order

### Residue dashboard surfaces

Why not enough:

- adjacent overview routes can exist without being next-story owners
- blocker lock already rejected PM/designer/site adjunct residue as standalone triggers

### `/api/v1/*` compatibility surfaces

Why not enough:

- compatibility runtime is not forward canonical proof
- module ownership SSOT explicitly freezes several `/api/v1/*` families instead of promoting them

### Generic family discovery

Why not enough:

- discovery can reveal possibilities but not planning authorization
- "smallest remaining slice" language alone cannot mint roadmap state

### Read-model inference

Why not enough:

- canonical notifications prove read-model ownership, not automatic rule/event/dashboard ownership
- adjacent read helpers cannot be promoted into the next story without explicit planning evidence

### Existing known routes with no new planning artifact

Why not enough:

- post-`S6.5` known routes are already in the blocker inventory
- re-reading them without a new planning artifact only repeats the locked state

## D. Intake Order When New Evidence Arrives

Future threads must review new evidence in this exact order.

1. Read `docs/change-proposals/2026-04-04-post-s6-5-roadmap-blocker-lock.md`.
2. Read `docs/change-proposals/2026-04-04-post-s6-5-roadmap-unlock-requirements-memo.md`.
3. Read this intake protocol artifact.
4. Read `docs/progress.md`.
5. Read `docs/roadmap/backlog.yaml`.
6. Read the newly-arrived artifact that appears to contain the new signal.
7. Use `docs/architecture/module-ownership-ssot.md` only to verify canonical ownership status.
8. Use `routes/api_zena.php` and `php artisan route:list` only to verify runtime truth.

Route truth and module ownership come after the planning artifacts on purpose. They are verification inputs, not permission to invent roadmap state.

## E. Validation Checklist

Use this checklist against the new artifact before any unlock claim.

- Does the artifact explicitly name one exact next story key/title?
- Does it explicitly name one exact canonical owner anchor on `/api/zena/*`, or explicitly create one with owner-backed planning basis?
- Does it explicitly narrow one exact first proof surface that is direct and verifiable?
- Is the wording explicit rather than interpretive or suggestive?
- Does the candidate avoid `/api/v1/*`, dashboard residue, and read-model inference as forward proof?
- Does the candidate avoid bundling multiple broad surfaces into one first slice?
- Does the candidate stay narrower than platform, convergence, or family-sweep semantics?

Hard fails:

- owner exists but no exact story key/title
- route exists but no exact first proof surface
- wording says "future", "possible", "adjacent", or "smallest remaining" without explicit lock
- the only support comes from compatibility surfaces
- the artifact merely restates known inventory

## F. Escalation / Outcome Rules

### If all three unlock conditions are satisfied

Valid next step:

- prepare one docs-only owner-backed readiness lock for the exact next story
- update `docs/progress.md` only if needed to record that lock
- keep runtime deferred until the docs lock is accepted

### If one or more unlock conditions are missing

Required outcome:

- record the candidate as `insufficient`, `owner-blocked`, `absent`, or `UNKNOWN`
- do not create `S6.6`
- do not open `EPIC-7`
- do not patch runtime

### If the new input is only noise / non-trigger

Required outcome:

- stop immediately
- keep SSOT lock intact
- do not repeat route-mining beyond verification needed to confirm the noise classification

## Operational Reminder

Future threads must not treat verification surfaces as planning authority.

Planning unlock comes from explicit owner-backed artifact changes first. Runtime truth is then used only to confirm that the claimed owner anchor is real and canonical.

## Verdict

Post-`S6.5` roadmap planning remains locked. Future threads may unlock only by following this intake order, rejecting non-triggers, and proving all three mandatory unlock conditions from an explicit authoritative artifact.
