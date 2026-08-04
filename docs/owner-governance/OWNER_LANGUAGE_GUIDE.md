# Owner Language Guide

Required reading for any agent drafting an Owner Control Layer (OCL) packet field. **Not** a general software-engineering glossary, not a UI localization file, not an engineering style guide — it exists solely to keep OCL packet language consistent across work IDs and agents.

## Growth rule

**The glossary below grows only when a term has actually appeared in a real owner packet.** Do not pre-populate translations for terms that have not yet come up in a real packet — this stays a living record of actual drafting decisions under real pressure, not a speculative dictionary.

## Approved plain-Vietnamese replacements (seeded from the GAP-031 worked example, Task 4 — the first real packet this repo produces)

| Technical term | Plain Vietnamese |
|---|---|
| tenant isolation | "dữ liệu của một khách hàng không bị khách hàng khác nhìn thấy hoặc thao tác" |
| race condition / concurrency | "hai người cùng thao tác trên một hồ sơ cùng lúc" |
| RBAC / authorization | "ai được phép làm gì" |
| rollback | "có thể hoàn tác / quay lại phiên bản trước" |
| audit trail / decision metadata | "ai đã quyết định, quyết định gì, khi nào" |

## Prohibited or discouraged technical terms

Never appear untranslated in an OCL packet field: class/method names (e.g. `DocumentWorkflowService`), SQL, HTTP status codes, framework names (Laravel, PHPStan, Sanctum), CI job names, git/branch/PR jargon beyond "thay đổi này" ("this change").

## Acceptable residual-risk wording (examples)

Acceptable: "nếu phát hành lúc này, có khả năng hai người vô tình ghi đè quyết định của nhau."
Not acceptable: "TOCTOU race condition in the decide() method."

## What must remain in the Engineering Evidence Layer, never pasted into an OCL packet

Lock strategy, query plans, migration diffs, stack traces, CI job names/logs, PHPStan output, route tables.
