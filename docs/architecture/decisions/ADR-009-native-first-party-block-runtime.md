# ADR-009 — Native Runtime for Approved First-Party Blocks

**Status:** Accepted (owner decision 2026-10-08; recorded by X-023 on 2026-10-09). The owner approved the direction explicitly; this ADR documents it, it does not ask for it.
**Decisions:** D-123 (Native trust and approval), D-124 (Site Type customer editing matrix v2), D-125 (Marketplace scope during Native runtime work)
**Narrows:** ADR-008 — it stays authoritative for sandbox preview, untrusted authored code and legacy sandboxed Block Versions, and is superseded only for the target public runtime of approved first-party authored Blocks (§11)
**Builds on:** ADR-006 (publishing runtime), D-117 / D-118 (ownership, creator permissions), D-120 (no moderation queue), D-121 / D-122 (catalog access, grandfathering)
**Backlog:** X-023 (this ADR, documentation only); implementation X-024 … X-029

## Context

ADR-008 runs every authored Block (HTML / CSS / JS from Block Studio) inside an opaque-origin `srcdoc` iframe, including on published customer Sites. That was designed for an open third-party Developer platform whose code is untrusted. Two facts changed:

- There is no open third-party author platform. Blocks and Templates are produced by the Landflow team and internal authorized developers / designers / admins; AI is used as a code-generation tool. Developer Profiles stay, but access is granted manually (D-117) and serves controlled internal creator workflows.
- A published Site built from iframe-wrapped Hero / Header / CTA Blocks is a poor product: content inside iframes is not part of the host page for SEO, iframes need height bridging, styles and fonts cannot be shared, and actions need a message bridge.

The owner therefore decided that ordinary approved first-party Blocks render natively in the host document of the published Site.

## Current implementation (2026-10-09, before X-024)

- `App\Enums\BlockRuntime` has two cases: `official` (trusted application React registry) and `sandboxed` (immutable authored `html` / `css` / `js` on the Block Version).
- `BlockStudio` saves one `block_drafts` row per Block with an optimistic `revision`. `BlockPublisher::publish()` requires the revision the author saw, runs `BlockSourceChecker` (ADR-008 §7) and creates the next immutable `sandboxed` Block Version with `published_by_user_id`. Authorization is the edit right (`manage_platform_content` for platform Blocks, active Developer Profile + `create_blocks` for own Blocks). No source hash and no separate approval exist.
- `PublishedSnapshotBuilder` copies sandboxed sources into the public manifest (`blocks[].sandbox`); `PublishedArtifactBuilder` passes them to the publish-time SSR renderer, and `resources/js/public-runtime/published-site.tsx` renders them through `SandboxedBlock` — an iframe on the published page.
- Studio preview, the Designer canvas and Site Preview render sandboxed Blocks through the same sandbox frame (`resources/js/components/sandbox/sandbox-frame.tsx`, `resources/js/sandbox/*`).

Native runtime does not exist yet. Nothing in this ADR is implemented by X-023.

## Implementation status (X-024, 2026-10-09)

- **Implemented:** `BlockRuntime::Native` («Нативный»); §5 Native HTML and §6 Native CSS compilers (`app/Blocks/Native/*`, PHP, parser / AST based, no new package); §8 actions through the shared host action runtime (`resources/js/blocks/actions.ts` → `NativeBlock`); §9 publish-time compilation inside `PublishedArtifactBuilder` with atomic failure; one immutable deduplicated `native_css` runtime asset per Published Version (`published_runtime_assets`, `/_landflow/runtime/{version}/{hash}.css`).
- **Not implemented (X-025):** §2 / §3 approval (`approve_native_blocks`, approval metadata, «Одобрить и опубликовать»), §7 Native JavaScript. A Native Block Version with non-empty `js` is refused at Publish (`native_js_not_approved`); its JS is never executed, stripped or iframe-wrapped.
- **No production path creates Native versions yet.** Block Studio `BlockPublisher::publish()` still creates `sandboxed` versions; Native versions exist only through test factories / E2E fixtures (`BlockVersionFactory::native()`) until X-025.
- §4 application origin: Block Studio, the Designer canvas and authenticated Site Preview render Native versions through the same sandbox frame as sandboxed ones (`BlockVersion::previewSource()`); Native host-DOM output exists only on published Sites.

## Decision

### 1. Runtime modes (target)

| Mode | What it is | Where its code runs on a published Site |
|---|---|---|
| `official` | Trusted application-owned React / system components from the registry (current official Blocks and other registry-backed renderers). Unchanged. | Host DOM, application bundle |
| `native` | An approved first-party authored Block Version: Block Studio `html` / `css` / `js` + canonical schema, published only after the D-123 approval of the exact source state. | Host DOM: compiled HTML, scoped compiled CSS, approved compiled JS — no iframe wrapper |
| `sandboxed` | The untrusted execution boundary of ADR-008. | Opaque-origin iframe |

`sandboxed` stays the runtime for Block Studio live preview, authored code before approval, legacy sandboxed Block Versions (§10) and any future untrusted external-author code. A Block Version's runtime is immutable.

### 2. Trust and approval (D-123)

Native execution in the host document is privileged first-party execution. A Native Block Version exists only after:

1. a canonical Draft revision;
2. deterministic source checks (ADR-008 §7 checks plus the Native compiler checks of §5–§7);
3. a source hash over the exact canonical sources;
4. a sandbox preview of that source;
5. an explicit approval / publication action by an authorized internal actor (§3);
6. creation of an immutable Native Block Version carrying the approval audit metadata.

Approval identity: `approved_revision`, `approved_source_hash`, `approved_by_user_id`, `approved_at`. Exact placement (columns on `block_versions`, a separate table, or both) is X-025 implementation work.

Approval applies to one exact source state. Any later Draft change invalidates it; every future version needs a new approval. AI is never an approving principal — only an authenticated User holding the approval capability approves.

Native approval is not Marketplace moderation (D-120 stays valid): there is no moderation queue, no `submitted` / `pending_review` / `rejected` status and no moderator role. It is the security trust step required before authored JS runs in a customer Site's origin.

### 3. Approval workflow and permission

Preferred product workflow is one explicit privileged action, «Одобрить и опубликовать», for the current Draft revision / hash:

```text
Draft → autosave → deterministic checks → sandbox preview
→ authorized actor clicks «Одобрить и опубликовать»
→ server re-checks revision + source hash + permission
→ immutable Native Block Version
```

- Autosave never publishes and never approves.
- The edit right (`create_blocks`, `manage_platform_content`) does not imply the approval right. Native approval needs a separate explicit capability (preferred key `approve_native_blocks`, or an equally explicit name); its code placement is X-025.
- Deny-by-default. If it becomes a `DeveloperPermission`, it is **not** backfilled or granted by default to existing or new Developer Profiles (unlike the D-118 MVP default for creator permissions); a Super Admin grants it deliberately to an internal authorized developer if that route is supported. Platform-owned content uses an explicit privileged platform authority.
- Future third-party authors never qualify for Native runtime automatically; their code stays `sandboxed` or needs a separate security model.

### 4. Security model

- Native JS is trusted first-party code. It is **not** sandboxed. It executes in the published Site origin and may access the host DOM and any same-origin browser capability the page CSP allows.
- The `mount(root, props, api) => cleanup` contract (§7) is an engineering / runtime contract, not a security boundary.
- Static checks alone do not make arbitrary code safe; AI-generated code is not trusted by default. Explicit human / internal approval of the exact source is required (§2).
- Native JS never runs in the Landflow application origin (dashboard, Designer, Block / Template Studio, authenticated Preview), where Landflow sessions live. In those surfaces authored JS stays in the sandbox (or Native Blocks render without JS); the exact preview / canvas treatment of Native Blocks is decided in X-024 / X-025 within this rule. Published Site hosts must stay cookie- and session-isolated from the application host (verified in X-025).
- Never placed into Native output: secrets, Integration credentials, numeric internal IDs, Draft state, other Workspaces' data.

### 5. Native HTML

At Publish time, inside the existing artifact pipeline (§9):

```text
immutable Block Version template + validated Block Instance state + published Site context
→ Native compiler → host-page HTML
```

Requirements:

- SEO-visible text is present in the initial HTML;
- context-aware escaping (text, attribute, URL contexts) and safe URL handling — no `javascript:` (or other unsafe-scheme) URLs;
- no inline event handlers; no `script` / `style` / `meta` / `base` / `embed` / `object` / `iframe` / `form` injection through authored HTML or state;
- `data-landflow-action` bindings are validated against the Block schema and resolved by the host runtime (§8);
- every Block Instance root carries its public identity for actions and runtime;
- no iframe wrapper around an ordinary Native Block;
- the HTML must survive client hydration of the published page unchanged.

### 6. Native CSS

Authored CSS is compiled and scoped, never concatenated raw. The compiler (AST / parser based; a regex-only rewriter is not acceptable) must:

- scope selectors to the Block instance / Block type root;
- handle `.class`, element selectors, `*`, `:root`, `html` / `body`, `@media`, `@supports`, `@keyframes` with `animation` / `animation-name` renaming, CSS custom properties, `url()` and `@import`;
- prevent a Block from styling another Block, a Popup view, the host shell or the global page.

Parser / dependency selection is X-024 work (no dependency is chosen in X-023). X-024 chose an in-repo PHP tokenizer / parser (CSS Syntax Level 3 subset) and libxml-based HTML parsing: no PHP CSS parser is installed, the Node CSS tools are build-time transitive dependencies (lightningcss is a native binary), and publish-time compilation stays in PHP without a Node call per Block.

### 7. Native JavaScript

Contract:

```text
mount(root, props, api) => cleanup
```

- `root` — the exact Block Instance DOM root;
- `props` — validated published state only;
- `api` — controlled Landflow host APIs (actions, popups, analytics hooks as defined later);
- `cleanup` — removes listeners, timers, observers and other instance resources.

No runtime architecture based on `eval`, `new Function` or unvalidated dynamic imports. Approved JS becomes an immutable, versioned publication artifact. Multiple instances of the same Block Version on one page stay independent; a failing instance must not break the page or other instances. CSP implications are part of X-025.

### 8. Actions, Popups and Forms

Native Blocks reuse the central host runtime and its validated actions: `open_url`, `open_page`, `scroll_to`, `phone`, `email`, `open_popup`. Authored JS never replaces trusted routing or invents server behaviour.

Forms stay Landflow-owned: validation, anti-spam, CAPTCHA, Submission persistence before delivery, Delivery / CRM routing — all through the existing backend (ADR-006 §7). Native code never creates an alternate form-delivery path or security boundary.

### 9. Publishing integration (ADR-006 stays)

ADR-006 remains the foundation and is not redesigned: Draft, Preview, `PublishSite`, `PublishValidator`, `PublishedSnapshotBuilder`, `PublishedArtifactBuilder`, `NodePageRenderer`, `PublishedVersion`, `PublishedPage` and the atomic `sites.active_published_version_id` switch.

- Native compilation runs inside the publish-time artifact pipeline. Public requests keep serving already-built immutable artifacts; Node never becomes a per-request renderer.
- Atomicity: a Native compilation failure never activates the new Published Version, never changes the active production version, never corrupts the Draft and produces a safe Russian publishing error. Every Native asset referenced by a Published Version is complete before the pointer switch.

### 10. Legacy sandboxed Block Versions

Existing `sandboxed` Block Versions are immutable; `runtime = sandboxed` is never silently changed to `native`. Migration path:

```text
legacy sandboxed version → copy its source into a new Draft → deterministic checks
→ sandbox preview → explicit Native approval → new immutable Native Block Version
→ deliberate Site / Template Draft upgrade → Preview → Publish Site
```

Historical active Published Versions keep serving unchanged and may contain iframe artifacts until the customer / team deliberately republishes with a migrated version. A **new** Site publication must not silently treat an ordinary legacy sandboxed Block as the final preferred runtime: X-028 provides the diagnostic, Russian blocking / warning UX and the migration path. D-122 grants apply to the new Native version like to any other version introduced into a Site.

### 11. Relationship to ADR-008

ADR-008 is not deleted and not wholly superseded. It remains authoritative for: the sandbox preview, untrusted / pre-approval authored code, legacy sandboxed Block Versions and any future untrusted external-author code. It is superseded only where it says authored code never runs in the published Site document: approved first-party Native Block Versions do (§1–§9).

### 12. Embed Blocks

A Native ordinary Block is not an Embed Block. An Embed / Widget may contain an iframe when the external provider genuinely requires it (maps, external chats, video / provider widgets). A future Embed implementation controls the provider / source allowlist, iframe `sandbox` permissions, `referrerpolicy`, lazy loading, responsive sizing and CSP `frame-src`. Ordinary Blocks (Hero, Header, CTA, …) are never turned into Embeds to avoid Native implementation.

### 13. Catalog access is unchanged

Runtime mode does not change catalog access: `CatalogAccessMode`, `CatalogLicense`, `site_block_version_grants`, D-121 and D-122 apply to Native versions exactly as to other versions. The Plan ↔ Catalog access matrix is X-026.

## Consequences

X-025 is implemented: separate deny-by-default approval capabilities, exact revision/source-hash audit metadata, token-aware policy and Node syntax validation, sandboxed Native preview, and one immutable same-origin `native_js` module per Published Version. Native JS requires no `unsafe-eval`; complete integration-aware public CSP hardening remains X-029.

- X-024: Native runtime model, HTML compiler, escaping / sanitization, scoped CSS compiler, host action integration, publish-time artifacts; no authored Native JS yet; sandbox preview stays.
- X-025: approval capability and metadata, «Одобрить и опубликовать», immutable JS artifacts, `mount` contract, instance isolation, CSP.
- X-026: Plan ↔ Catalog access matrix. X-027: D-124 server enforcement. X-028: legacy sandboxed migration. X-029: hardening and E2E review.
- Published Sites built from Native Blocks become crawlable and stylistically coherent; the cost is that approved first-party code is trusted in the customer Site origin, so the approval step and its permission are security-critical.
- No package, migration or code change is part of X-023.
