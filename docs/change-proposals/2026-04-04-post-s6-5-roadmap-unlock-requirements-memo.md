# Post-S6.5 Roadmap Unlock Requirements Memo

Date: 2026-04-04
Status: docs-only unblock guidance memo

## Why this memo exists

The post-`S6.5` blocker is already locked in SSOT.

This memo does not unlock roadmap planning. It exists only to make the unlock bar explicit so later threads do not restart route mining, invent `S6.6`, or open `EPIC-7` from residue.

This memo does not create `S6.6`.

This memo does not open `EPIC-7`.

This memo does not patch runtime.

## Current locked state

From `docs/roadmap/backlog.yaml`, `docs/progress.md`, and `docs/change-proposals/2026-04-04-post-s6-5-roadmap-blocker-lock.md`:

- `EPIC-6` currently ends at proved `S6.5`
- no `S6.6` exists
- no `EPIC-7` exists
- no exact post-`S6.5` story is currently locked in SSOT
- the last unlock assessment concluded `No valid unlock evidence; blocker remains locked`

This is a factual SSOT stop point, not a temporary reading gap.

## Required unlock conditions

Roadmap planning may unlock only when one candidate simultaneously satisfies all three conditions below.

### 1. Exact next story key/title

SSOT must explicitly name one exact next story key/title.

Valid forms:

- a new change proposal that explicitly locks the exact next story
- an explicit backlog/progress entry that names the next story without ambiguity

Invalid forms:

- "probably next"
- "smallest remaining slice"
- a mounted route with no planning lock
- a generic family name with no exact story identity

### 2. Exact owner anchor

The candidate must have one exact canonical owner anchor on `/api/zena/*` or a new owner-backed planning artifact that explicitly establishes that anchor.

Valid forms:

- one exact canonical route already mounted on `/api/zena/*`
- one newly-established canonical `/api/zena/*` owner family explicitly named in SSOT

Invalid forms:

- `/api/v1/*`
- compatibility-only ownership
- generic dashboard residue
- inference from read-model adjacency alone

### 3. Exact first proof surface

The candidate must have one narrow, direct, verifiable first proof surface that avoids platform creep.

Valid forms:

- one read-only projection with exact boundary
- one exact owner-scoped write/read pair
- one exact internal proof surface explicitly locked by readiness evidence

Invalid forms:

- broad family exploration
- multi-surface bundles
- "continue dashboard work"
- "cover alerts/rules/events together"

If any one of the three conditions is missing, the candidate remains blocked and next-story status stays `UNKNOWN`.

## Candidate inventory

The list below is limited to currently known post-`S6.5` candidates already surfaced by SSOT, runtime truth, or the blocker lock.

### PM adjunct surfaces

Known surfaces:

- `GET /api/zena/pm/progress`
- `GET /api/zena/pm/risks`
- `GET /api/zena/pm/weekly-report`

Current known owner status:

- mounted canonical adjunct routes under the PM family
- not locked as the next roadmap owner

Current evidence status:

- blocker lock explicitly rejects them as next-story anchors
- no backlog/progress/change-proposal artifact promotes any one of them from residue into the next story

Missing unlock conditions:

- exact next story key/title: missing
- exact owner anchor: present at route level, but not promoted as next owner in planning
- exact first proof surface: missing

Conclusion:

- insufficient

### Designer adjunct surfaces

Known surfaces:

- `GET /api/zena/designer/drawings`
- `GET /api/zena/designer/workload`

Current known owner status:

- mounted canonical adjunct routes under the designer family
- `GET /api/zena/designer/dashboard` was already consumed by `S6.1`

Current evidence status:

- blocker lock explicitly rejects these adjunct routes as next-story anchors
- no post-`S6.5` readiness lock promotes either surface into roadmap ownership

Missing unlock conditions:

- exact next story key/title: missing
- exact owner anchor: present at route level, but not as next planning owner
- exact first proof surface: missing

Conclusion:

- insufficient

### Site-engineer adjunct surfaces

Known surfaces:

- `GET /api/zena/site-engineer/material-requests`
- `GET /api/zena/site-engineer/safety`

Current known owner status:

- mounted canonical adjunct routes under the site-engineer family
- `GET /api/zena/site-engineer/dashboard` was already consumed by the proved `S5.4` slice

Current evidence status:

- blocker lock explicitly rejects these adjunct routes as next-story anchors
- mounted runtime alone is the only current signal

Missing unlock conditions:

- exact next story key/title: missing
- exact owner anchor: present at route level, but not promoted by planning evidence
- exact first proof surface: missing

Conclusion:

- insufficient

### Canonical notifications

Known surfaces:

- `/api/zena/notifications*`
- `/api/zena/auth/notifications*`

Current known owner status:

- canonical inbox/read-model ownership exists on `/api/zena/notifications`
- auth notification endpoints are projection helpers, not a separate business owner family

Current evidence status:

- module ownership marks notifications as canonical read-model ownership
- `S6.2` keeps notifications as read-model only and does not promote them into rule-definition ownership
- no post-`S6.5` artifact names notifications as the next story owner

Missing unlock conditions:

- exact next story key/title: missing
- exact owner anchor: present for notifications, but not for a post-`S6.5` next-story claim
- exact first proof surface: missing

Conclusion:

- insufficient

### Notification rules

Known surfaces:

- `/api/v1/notification-rules*`

Current known owner status:

- active compatibility owner exists only on `/api/v1/notification-rules`
- no canonical `/api/zena/notification-rules*` family exists today

Current evidence status:

- module ownership explicitly marks notification rules as compatibility-owned and frozen
- blocker lock rejects notification-rule convergence as a next-story candidate under current SSOT

Missing unlock conditions:

- exact next story key/title: missing
- exact owner anchor: missing on canonical `/api/zena/*`
- exact first proof surface: missing

Conclusion:

- owner-blocked

### Event records

Known surfaces:

- internal `event_records` persistence exists in repo state
- no canonical `/api/zena/event-records*` family is mounted

Current known owner status:

- no canonical public event-record owner exists on `/api/zena/*`
- prior `S6.3` proof stayed internal-only and did not create a public read/replay owner

Current evidence status:

- blocker lock rejects public event-record read/replay candidates
- no post-`S6.5` readiness artifact promotes event-record ownership into the next story

Missing unlock conditions:

- exact next story key/title: missing
- exact owner anchor: missing on canonical `/api/zena/*`
- exact first proof surface: missing for a post-`S6.5` next-story lock

Conclusion:

- absent

### QC dashboard family

Known surfaces:

- no canonical QC dashboard owner route is currently established on `/api/zena/*`

Current known owner status:

- `S6.1` explicitly deferred QC dashboard ownership until a canonical `/api/zena/*` anchor exists

Current evidence status:

- blocker lock rejects QC continuation because no canonical QC owner anchor exists

Missing unlock conditions:

- exact next story key/title: missing
- exact owner anchor: missing
- exact first proof surface: missing

Conclusion:

- owner-blocked

### Finance dashboard family

Known surfaces:

- no canonical Finance dashboard owner route is currently established on `/api/zena/*`

Current known owner status:

- `S6.1` explicitly deferred Finance dashboard ownership until a canonical `/api/zena/*` anchor exists

Current evidence status:

- blocker lock rejects Finance continuation because no canonical Finance owner anchor exists

Missing unlock conditions:

- exact next story key/title: missing
- exact owner anchor: missing
- exact first proof surface: missing

Conclusion:

- owner-blocked

## Acceptable future triggers

Roadmap planning may unlock later only if one of the artifact types below appears and explicitly clears all three unlock conditions.

### New explicit change proposal

Accept if it:

- names one exact post-`S6.5` story key/title
- names one exact canonical owner anchor
- narrows one exact first proof surface

### Explicit backlog/progress update

Accept if it:

- is explicit rather than interpretive
- promotes one exact candidate into next-story status
- aligns with canonical owner truth rather than compatibility residue

### New canonical owner family on `/api/zena/*`

Accept if it:

- creates one real canonical owner family for a blocked area such as notification rules, event records, QC dashboard, or Finance dashboard
- is accompanied by SSOT evidence naming how that new owner becomes the next story

### New owner-backed readiness lock

Accept if it:

- is narrower than speculative roadmap planning
- is evidence-backed by current SSOT and runtime truth
- defines the exact first proof surface without bundling unrelated platform scope

## Non-triggers

The items below are not valid unlock evidence by themselves.

- a mounted route by itself
- dashboard residue or adjacent overview surfaces by themselves
- any `/api/v1/*` compatibility surface
- generic family discovery
- route mining without a new planning artifact
- inference from read-model surfaces such as canonical notifications
- broad canonical families with no new narrowing lock
- "smallest remaining slice" language without an exact story key/title

## How future threads should use this memo

If a future thread cannot point to one artifact that clears all three unlock conditions, it must stop and keep the roadmap locked.

If a future thread finds only partial evidence, it must record `UNKNOWN` or `insufficient` instead of promoting a candidate into `S6.6` or `EPIC-7`.

If a future thread does find valid unlock evidence, the next step is still docs-first: prepare one owner-backed readiness lock before any runtime patching.

## Verdict

Post-`S6.5` roadmap planning remains locked. The unblock bar is now explicit: no future thread may create `S6.6` or open `EPIC-7` unless one candidate simultaneously proves an exact next story key/title, an exact canonical owner anchor, and one exact narrow first proof surface.
