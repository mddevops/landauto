# ADR-008 — Sandboxed Runtime for Authored Block Code

**Status:** Accepted (owner instruction «Landflow Creator Studio, Marketplace и форматы сайтов», 2026-10-07)
**Resolves:** D-080 (Developer Block runtime / sandbox), D-081 (custom Developer code support)
**Builds on:** D-117 / D-118 (ownership and creator permissions), ADR-006 (publishing runtime), BLOCK_SYSTEM.md §8–§28 (canonical Block Schema)
**Leaves open:** third-party dependency bundles, a separate sandbox origin for production hardening (§9), AI-assisted schema drafts (P9-010)
**Backlog:** P9-004 … P9-009

## Context

Creator Studio lets a Super Admin (platform-owned Blocks) and a Developer with an active Developer Profile and `create_blocks` (Developer-owned Blocks) write Block source: HTML, CSS, JavaScript and the Block Schema. That JavaScript is untrusted. Landflow serves the dashboard, the Designer and published customer Sites; none of them may give authored code access to Landflow sessions, data, secrets or networks. There is no manual moderation queue (D-120), so isolation must be technical.

## Decision

### 1. Block Source

A Block Draft and every published sandboxed Block Version hold four sources:

- `html` — a Landflow template (§3), at most 64 KB;
- `css` — plain CSS, at most 64 KB;
- `js` — plain browser JavaScript, at most 64 KB;
- `schema` — the canonical Block Schema (`{"fields": [...]}`, validated by `BlockSchemaValidator`), at most 64 KB.

There is exactly one schema format: the existing canonical Block Schema. Creator Studio's Schema Builder and the schema source edit the same JSON; no JSON Schema or parallel format is introduced. New field types extend the canonical validator, state validator and Properties Editor together.

Editable fields are never inferred automatically from HTML/CSS/JS. The author declares them through the Schema Builder or the schema source.

### 2. Isolation boundary: opaque-origin sandboxed iframe

Authored code never runs in the Landflow application origin — not in the dashboard, Designer, Studio preview or published Site document.

Every rendering of a sandboxed Block (Studio preview, Designer canvas, published Site) is an `<iframe>` with:

- `sandbox="allow-scripts"` only — **never** `allow-same-origin`, `allow-top-navigation*`, `allow-popups*`, `allow-forms`, `allow-modals`, `allow-downloads`, `allow-pointer-lock` or `allow-storage-access-by-user-activation`;
- `srcdoc` built by Landflow (never a URL controlled by the author);
- `referrerpolicy="no-referrer"`, `loading="lazy"` on published Sites.

The document therefore has an opaque (`null`) origin: no access to Landflow cookies, session, `localStorage` / `sessionStorage` / IndexedDB, the parent DOM, `window.top` navigation, popups or form submission.

The `srcdoc` document starts with a Landflow-owned Content-Security-Policy `<meta>` element before any authored content:

`default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; img-src data: blob: <asset origin>; font-src data:; connect-src 'none'; media-src 'none'; frame-src 'none'; worker-src 'none'; object-src 'none'; form-action 'none'; base-uri 'none'`

`connect-src 'none'` blocks `fetch`, XHR, WebSocket, EventSource and beacons, so authored code cannot reach the Landflow backend, internal networks or arbitrary APIs; no external scripts, styles, fonts or CDNs load. Images load only from `data:` / `blob:` and the Landflow asset origin.

### 3. Template and props

- The HTML source is a logic-less Landflow template: `{{ path }}` (escaped text), `{{#if path}} … {{else}} … {{/if}}` and `{{#each path}} … {{/each}}`. Paths reference schema keys (dot paths into groups; inside `each`, keys of the repeater item first). Rendering happens inside the sandbox by the Landflow bootstrap script, never as raw HTML in the app.
- Props are the validated Block Instance state (or Studio preview data) serialized by Landflow as JSON (`<` escaped) inside the `srcdoc`. Images are resolved by Landflow to `{ url, alt }`; actions are exposed only as opaque keys. No secrets, numeric internal IDs, Workspace / user data or other Blocks' state are ever included.
- Authored JavaScript runs after rendering and sees only `window.landflow` (`props`, `root`, `action(key)`, `resize()`).
- Authored `</script` / `</style` sequences are neutralized; the template parser rejects unbalanced sections.

### 4. Message bridge

The only channel between the sandbox and Landflow is `postMessage` from the sandbox to its parent. The parent accepts a message only when `event.source` is that iframe's `contentWindow`, `event.origin === "null"` and `type` is one of:

- `landflow:resize` — `{ height: number }`, clamped;
- `landflow:action` — `{ key: string }`, which must name an `action` field of the Block's published schema; Landflow resolves and executes the stored, validated action itself (open popup, scroll, link) — the sandbox never supplies URLs or targets;
- `landflow:error` — `{ message: string }`, truncated, shown only in Studio / Designer.

Everything else is ignored. Landflow never sends credentials or privileged data into the sandbox.

### 5. Styles

CSS lives inside the iframe document, so it cannot affect the Designer, the dashboard or neighbouring Blocks, and their CSS cannot affect it.

### 6. Failure behaviour

A runtime error, missing prop or failed render never breaks the host page: the iframe shows a neutral fallback, Studio / Designer display the reported error, and published Sites keep the rest of the page working.

### 7. Automated checks instead of review (D-120)

Publishing runs deterministic server checks; there is no approval queue. Checks include: source size limits; canonical schema validity; template syntax and that every template path exists in the schema; forbidden HTML constructs (`<script>`, `<style>`, `<link>`, `<meta>`, `<base>`, `<iframe>`, `<frame>`, `<object>`, `<embed>`, `<form>`, `<portal>`); external URLs in HTML/CSS (`http(s)://`, protocol-relative `//`, `@import`); and that the sandbox wrapper builds. Failures block publication with Russian messages. Passing checks publish immediately.

### 8. Trusted official renderers

Existing official Blocks rendered by the application's trusted React registry stay as they are (`runtime = official`). Only Blocks with authored source use the sandbox (`runtime = sandboxed`). A sandboxed Block can never select or impersonate an official renderer.

### 9. Accepted residual risks and hardening path

- Authored code can navigate its own iframe (not the top window) and thereby send the props it received to an outside URL. Props are Block content the customer entered for that Block (published content on a public Site), never secrets or unrelated data. A dedicated sandbox origin with network-level egress controls is the production hardening path and requires infrastructure (DNS / TLS) — out of scope until then.
- CPU-heavy or looping code can slow its own iframe; it cannot reach other origins' data. Browsers isolate opaque-origin frames per their process model.
- iframe content is not indexed as part of the host page; authors are told to keep SEO-critical copy in official Blocks.

## Consequences

- P9-004 stores Drafts; P9-005 builds the sandbox wrapper and live preview; P9-008 implements the checks; P9-006 publishes immutable sandboxed versions; P9-009 renders them in the Designer and published Sites through the same wrapper.
- No manual moderation, no new npm / Composer dependency, no code editor library is required.
