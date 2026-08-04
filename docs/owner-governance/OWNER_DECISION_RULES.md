# Owner Decision Rules

Precise escalation matrix: which findings require the owner, which do not.

## Requires owner involvement

A finding requires routing to the owner (a new or amended gate packet) when it changes any of: product goal, scope, business rules, user roles or authority, data visibility, approval responsibility, workflow states, financial or legal behavior, user-facing acceptance criteria, risk accepted by the business, release timing.

## Does NOT require owner involvement

No owner decision is needed for implementation choices that remain inside the approved Gate 2 design: class or method names, refactoring structure, test mechanics, framework patterns, query implementation, internal technical organization.

## The four-question anti-escalation test

Before drafting an owner-facing escalation, an agent must answer:

1. Does this change what a user can do?
2. Does this change what data they can see?
3. Does this change who is responsible for a decision?
4. Does this change what risk the business carries?

**If the answer to all four is no, this is an implementation detail — resolve it technically, do not escalate to the owner.** Manufacturing an owner-escalation for a finding that fails this test is itself a governance failure: it trains the owner to stop reading packets carefully.

## Routing rule by lifecycle position

- Finding surfaces *before* Gate 2 approval → fold into the Gate 2 packet draft, no separate escalation.
- Finding surfaces *after* Gate 2 approval, *before* Gate 3 → requires a Gate 2 packet **revision** (new file, `supersedes` the prior one) before implementation continues on the affected part.
- Finding surfaces *after* Gate 3 approval (post-release) → requires a new Gate 1 (it is operationally a new problem), unless it is a rollback/incident, which follows incident process and is retrospectively logged in `OPERATIONAL_GAP_REGISTER.md`.

## Fixed decision enums (must match `docs/owner-governance/packet-schema.yml` exactly)

```
gate_1 owner_decision.value: none | approved | more_info_requested | declined | deferred
gate_2 owner_decision.value: none | approved | changes_requested | declined
gate_3 owner_decision.value: none | approved | correction_requested | deferred
```

No other values are valid at any gate. `owner-governance-lint` (Task 5) rejects anything else.
