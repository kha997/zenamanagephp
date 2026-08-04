# Owner Operating Model

This document is the single source of truth for how the three owner gates work in this repository. It is itself an Engineering Evidence Layer (EEL) document — written for agents and reviewers, not for the owner to read directly (the owner reads gate packets, §"Owner Decision Packets" below, and the plain-Vietnamese templates in `templates/`).

## What the owner decides

Product goals, business scope, business rules, user-visible workflow, acceptable business risk, and whether a verified change may be released — exactly the three gate decisions below. Nothing else.

## What engineering decides

Everything else: class/method names, database schema (within the approved business design), test structure, framework choices, query implementation, internal technical organization, CI configuration, refactoring.

## The three gates

1. **Gate 1 — Business Request Approval.** Is the operational problem real, important, correctly scoped? No implementation plan or code exists yet when this gate opens.
2. **Gate 2 — Business Design Approval.** Does the proposed before/after workflow, role/rule set, and acceptance-scenario list match what the business wants? No implementation plan or code exists yet when this gate opens.
3. **Gate 3 — Release Approval.** Is a *verified, already-built* change ready to go live? Implementation, testing, review, and CI happen freely between Gate 2 approval and Gate 3 — Gate 3 authorizes only the release act (merge/deploy/production-data-change/user-release), never the engineering work that already happened.

## Packet lifecycle (`gate_status`)

`gate_status` moves through exactly these values (full transition tables per gate in `docs/superpowers/specs/2026-08-04-non-technical-owner-control-layer-design.md` §3.6):

```
not_started → preparing → [blocked_technical (Gate 3 only)] → awaiting_owner → approved | changes_requested | deferred
changes_requested → preparing
deferred → preparing | superseded
approved → superseded
```

**Only `gate_status: awaiting_owner` exposes an owner decision action.** Every other value is either "nothing to decide yet" or "already decided, terminal for this packet revision."

## `technical_readiness` vs `owner_decision`

Two independent fields on every Gate 3 packet:
- `technical_readiness.value` (`not_checked`/`blocked`/`ready`) — set exclusively from engineering evidence (CI, review, tenant-isolation/RBAC checks). Never an agent opinion, never owner-set.
- `owner_decision.value` — set exclusively by a recorded owner action. Never inferred from `technical_readiness`.

Release requires `technical_readiness: ready` AND `owner_decision: approved` AND repository requirements (branch protection, required reviews, green required CI) — all three, evaluated at release time, never cached from an earlier moment.

## What the owner may never override

Red status on: data integrity, tenant isolation, security, authorization, required CI, failed migrations, destructive-data risk, missing mandatory evidence. If any of these is red, `technical_readiness` cannot be `ready`, `gate_status` cannot reach `awaiting_owner`, and no approval action is offered — not because the packet is hidden, but because the decision surface does not exist while readiness is not `ready`.

## Owner Decision Packets

One packet file per gate per work ID, at `docs/owner-decisions/<WORK-ID>/0X-<name>.md`, conforming to `docs/owner-governance/packet-schema.yml`. Packet body content follows the matching template in `docs/owner-governance/templates/`.

## Immutability and supersession

A packet is never edited in place once `owner_decision.value` is not `none`. A new file (`03-release-v2.md`, etc.) is created instead, with `supersedes`/`superseded_by` frontmatter linking the two. See `docs/owner-decisions/GAP-031/` for a real example of this (Task 4).
