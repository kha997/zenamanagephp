---
work_id: GAP-084
gate: 3
gate_status: approved
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-19-gap-084-npm-vulnerabilities-evidence.md
  plan: docs/superpowers/plans/2026-10-20-gap-084-npm-vulnerabilities.md
  branch: docs/GAP-084-npm-vulnerabilities
  pr: https://github.com/kha997/zenamanagephp/pull/357
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-20T13:50:09+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-10-20, verbatim: 'APPROVE GAP-084 Gate 3'. Given after the packet was presented at PR head d7a15ff5ae6d14b82723457a6844b0a8caead6c5 (implementation CI 34/34 green at 1b22ddbd); bound to implementation subject 1b22ddbd41114e83f5754b27d906beced8c7d357 and implementation-tree digest 9cb285bea59687ef55107205355a566c177e55aef24e323cd17d6d533742caaf (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-20T07:16:56+07:00"
  updated_at: "2026-10-20T13:50:09+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-084 at subject 1b22ddbd. 30c0eba1: npm audit fix without --force (package-lock.json only): axios 1.18.1 -> 1.20.0, postcss 8.5.16 -> 8.5.31, browserslist 4.29.4, nanoid 3.3.20, socket.io-parser 4.2.7, source-map-js 1.2.2, postcss-selector-parser 6.1.5, baseline-browser-mapping 2.11.30, caniuse-lite, electron-to-chromium, update-browserslist-db; findings 17 -> 10; built CSS byte-identical, app.js differs only by the bundled axios. 09edf3be: vite 4.5.14 -> 8.3.4, laravel-vite-plugin 0.8.1 -> 3.2.0, Dockerfile.prod node:18-alpine -> node:22-alpine; findings 10 -> 7 (0 critical; all in the tailwindcss 3 build chain: tailwindcss, braces, micromatch, chokidar, fast-glob, postcss-nested, postcss-selector-parser); build emits the same 10 manifest entries; app.css rules identical and operator.css rules equivalent after normalising the minifier (vendor prefixes, skew()->skewX(), legacy ::-moz-placeholder copies dropped with ::placeholder kept, -webkit-backdrop-filter added to .transition). 1b22ddbd (Owner amendment 'Cho sửa 1 dòng (Recommended)'): button-tests.yml generated Vite config manifest: true -> 'manifest.json' so the Vite manifest stays at public/build/manifest.json. Dockerfile.prod could not be built locally (no Docker daemon); exact-head CI 34/34 green at 1b22ddbd including browser-tests (Vite 8 build) and staging-smoke (npm ci on Node 20)."
technical_evidence:
  base_sha: "2a01adeaeb854bafb081cc04b5541a45c78c2cde"
  subject_sha: "1b22ddbd41114e83f5754b27d906beced8c7d357"
  implementation_tree_digest: "9cb285bea59687ef55107205355a566c177e55aef24e323cd17d6d533742caaf"
  verified_pr_head_sha: "1b22ddbd41114e83f5754b27d906beced8c7d357"
  verified_at: "2026-10-20T07:16:56+07:00"
owner_decision_binding:
  implementation_tree_digest: "9cb285bea59687ef55107205355a566c177e55aef24e323cd17d6d533742caaf"
  decision_recorded_at: "2026-10-20T13:50:09+07:00"
---

# GAP-084 — Gate 3 release decision (sửa lỗ hổng npm)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-10-20 ("APPROVE GAP-084 Gate 3"), bound to implementation subject
`1b22ddbd41114e83f5754b27d906beced8c7d357` and implementation-tree digest `9cb285bea59687ef55107205355a566c177e55aef24e323cd17d6d533742caaf`. No deployment is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** `npm audit` báo 17 lỗ hổng (Gate 1).

**2. Sau thay đổi (Gate 2, Phương án B):**

| Bước | Thay đổi | Lỗ hổng |
|---|---|---|
| `30c0eba1` | cập nhật trong phạm vi bản hiện tại (axios 1.20.0, postcss 8.5.31, các gói phụ) | 17 → 10 |
| `09edf3be` | vite 8.3.4, laravel-vite-plugin 3.2.0; `Dockerfile.prod` Node 22 | 10 → **7** (0 critical) |
| `1b22ddbd` | sửa 1 dòng trong `button-tests.yml` (Owner amendment) | — |

7 lỗ hổng còn lại đều nằm trong chuỗi gói build của tailwind 3 (theo Gate 2).

**3. Bằng chứng** — base `2a01adea`; subject `1b22ddbd41114e83f5754b27d906beced8c7d357`;
digest `9cb285bea59687ef55107205355a566c177e55aef24e323cd17d6d533742caaf`.

- Bước 1:
  - CSS build ra **giống hệt từng byte**;
  - `app.js` chỉ khác ở bản axios mới.
- Bước 2:
  - build vẫn ra đủ 10 đầu vào;
  - CSS **tương đương**: chỉ khác do bộ nén mới (bỏ tiền tố trình duyệt cũ,
    `skew` thành `skewX`, bỏ bản sao `::-moz-placeholder` vì `::placeholder`
    chuẩn vẫn còn).
- CI exact head 34/34 xanh, gồm:
  - browser-tests, có build bằng Vite 8;
  - staging-smoke (`npm ci` trên Node 20).
- Lint governance, docs đạt.

**4. Công khai**

- **Owner amendment** ("Cho sửa 1 dòng (Recommended)"): `button-tests.yml` nằm
  ngoài allowlist. Vite 8 đổi chỗ ghi manifest, nên mình sửa để giữ
  `public/build/manifest.json`.
- Không build thử được `Dockerfile.prod` trên máy (không có Docker daemon).
  Bước build Node trong image chỉ được kiểm gián tiếp qua CI.
- Vite 8 cảnh báo về cách nạp `vite.config.js` trong tương lai. Đây chỉ là cảnh
  báo, không đổi hành vi.

**5. Ảnh hưởng** — Khi deploy: build image bằng `Dockerfile.prod` mới (Node
22). Người dùng nhận axios mới trong `app.js`. Giao diện không đổi.

**6. Rủi ro còn lại** — Thấp:
- 7 lỗ hổng còn lại chỉ nằm trong công cụ build;
- `Dockerfile.prod` chưa được build thử trực tiếp.

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên. Tailwind 4 (hết 7
lỗ hổng còn lại) để Work ID sau.

## Decision Needed

Owner chọn: `APPROVE GAP-084 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy; không duyệt nâng tailwind 4.
