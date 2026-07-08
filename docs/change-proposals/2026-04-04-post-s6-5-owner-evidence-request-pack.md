# Post-S6.5 Owner Evidence Request Pack

Date: 2026-04-04
Status: docs-only unblock-enablement memo

## Why this memo exists

The repo already has a blocker lock, an unlock requirements memo, and an evidence intake protocol for post-`S6.5` roadmap planning.

This memo exists for a narrower purpose: if evidence must come from an owner or planning authority outside the current repo state, this pack defines exactly what they must answer and what answer shapes are strong enough to create a usable unlock signal.

This memo does not create `S6.6`.

This memo does not open `EPIC-7`.

This memo does not patch runtime.

## A. Current Blocked State

Current SSOT truth:

- roadmap currently stops at proved `S6.5`
- no `S6.6` exists
- no `EPIC-7` exists
- internal repo evidence is still insufficient to unlock the next story honestly

Until new external owner-backed evidence arrives, blocker status remains locked.

## B. What Must Be Provided To Unlock

Any owner or planning authority response must provide all three conditions below.

### 1. Exact next story key/title

Practical meaning:

- say exactly what the next story is
- not a family, not a module, not a broad intention

Examples of acceptable specificity:

- one exact story title
- one exact next-story identity that can be locked into SSOT without interpretation

### 2. Exact canonical owner anchor

Practical meaning:

- say exactly which canonical `/api/zena/*` owner path or owner family the story belongs to
- if the owner does not exist yet, say that explicitly and provide the owner-backed planning basis for establishing it

This must not rely on `/api/v1/*`, residue, or generic dashboard language.

### 3. Exact narrow first proof surface

Practical meaning:

- say exactly what the first honest slice proves and nothing more
- the proof surface must be narrow enough to verify directly and narrow enough to avoid platform creep

This must include the first scope guard, not just the desired area.

## C. Owner Questionnaire

Use the questions below exactly or with only minimal wording changes.

### Story identity

1. What is the exact next story immediately after proved `S6.5`?
2. Write the answer as one exact story title, not as a family, module, or broad initiative.
3. Why is this the next story now, rather than another known deferred area?

### Owner anchor

4. What exact canonical `/api/zena/*` owner anchor or owner family owns that story?
5. If the owner anchor does not yet exist canonically, what explicit owner-backed planning basis establishes it as the next owner?
6. Which existing surfaces are explicitly not the owner for this story?

### First proof surface

7. What is the exact first proof surface for the story?
8. Write it as one narrow slice that can be verified directly, not as a family sweep or continuation theme.
9. What exact parts are explicitly out of scope for the first slice?

### Scope guard

10. What broader adjacent surfaces must remain out of scope so the first slice does not expand into dashboard platform, alert platform, rule convergence, event platform, or family-wide cleanup?
11. If there are adjacent mounted routes or known residues, why are they not part of the first proof claim?

### Decision basis

12. What artifact should future threads point to as the authoritative planning basis for this answer?
13. Is this answer explicit enough to lock into SSOT without route-mining or interpretation by the next thread?

## D. Acceptable Answer Shapes

The examples below are templates only. They do not mint a new story.

### Template 1: existing canonical owner

`The exact next story after S6.5 is [exact story title]. The canonical owner anchor is [exact /api/zena/* route or owner family]. The first proof surface is limited to [one exact narrow slice]. Out of scope for the first slice are [explicit non-goals]. This is the next story because [one explicit ordering reason grounded in planning evidence].`

### Template 2: new owner-backed planning establishment

`The exact next story after S6.5 is [exact story title]. A new canonical owner family must be established at [exact /api/zena/* owner family], and that owner is the required next anchor because [explicit owner-backed planning basis]. The first proof surface is limited to [one exact narrow slice]. Out of scope for the first slice are [explicit non-goals].`

Usable evidence must always include:

- exact story title
- exact owner anchor
- exact first proof surface
- explicit non-goals
- explicit reason it is next after `S6.5`

## E. Insufficient Answer Patterns

The patterns below do not produce usable unlock evidence.

### Only naming a family or module

Examples:

- "continue dashboard"
- "do alerts next"
- "move to QC"

Why insufficient:

- no exact story identity
- no exact first proof surface

### Only naming a route

Examples:

- "use `/api/zena/pm/progress`"
- "use `/api/zena/notifications`"

Why insufficient:

- route presence alone is not planning authority
- no exact story title
- no owner-backed reason it is next

### Only stating a general preference

Examples:

- "QC should probably be next"
- "finance can come later"
- "maybe finish notifications first"

Why insufficient:

- wording is preference, not explicit planning evidence
- no exact proof surface

### Only describing a broad desired area

Examples:

- "finish dashboard"
- "do rule/event convergence"
- "cover notification flow"

Why insufficient:

- scope is too broad
- first slice is not bounded

### Only relying on known internal repo state

Examples:

- "that route already exists"
- "the family is already mounted"
- "the model already exists"

Why insufficient:

- this only repeats known inventory
- blocker lock already says mounted/runtime truth alone cannot unlock roadmap

## F. Handoff Rule After Owner Response

### If the owner response satisfies all three conditions

Valid future step:

- prepare one docs-only owner-backed readiness lock for the exact next story
- update `docs/progress.md` only if needed to record that lock
- keep runtime deferred until the docs lock is accepted

### If the owner response misses any one condition

Required outcome:

- classify the answer as `insufficient`, `owner-blocked`, `absent`, or `UNKNOWN`
- keep blocker status locked
- do not create `S6.6`
- do not open `EPIC-7`

### If the owner response is only a general preference or directional guidance

Required outcome:

- treat it as non-authoritative for roadmap unlock
- do not convert it into story identity by interpretation
- stop after recording that the answer is still insufficient

## G. External Request Message

Use the message below as-is or with only minimal wording changes.

```text
We are blocked after proved S6.5 and cannot open the roadmap further without explicit owner/planning evidence.

Please reply with one concrete next-story decision in the format below:

1. Exact next story title immediately after S6.5
2. Exact canonical owner anchor on /api/zena/* for that story
3. Exact narrow first proof surface for the first honest slice
4. Explicit non-goals / scope guard for what is out of scope in that first slice
5. Why this is the next story now, instead of another known deferred area

Please do not answer with broad directions such as:
- "continue dashboard"
- "do alerts/notifications/QC next"
- "use the route that already exists"

If the owner anchor does not yet exist canonically, say that explicitly and state the owner-backed planning basis for establishing it.

We cannot use /api/v1/*, mounted routes alone, or general preference language as unlock evidence.
```

## Operational Reminder

Future threads must not upgrade ambiguous owner responses into roadmap state.

If the owner answer still requires the thread to guess the story title, guess the owner anchor, or invent the first proof surface, the answer has failed the unlock bar.

## Verdict

Post-`S6.5` roadmap planning remains locked pending external owner-backed evidence. This request pack defines the minimum answer quality required before any later thread may prepare a docs-only readiness lock.
