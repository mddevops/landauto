# ADR-006 — Publishing Runtime: Published Manifest, Publish-Time React SSR, Atomic Activation

**Status:** Accepted (owner approval in the Phase 5 autopilot instruction, 2026-10-05: "THIS PROMPT IS EXPLICIT OWNER APPROVAL for the following Publishing decisions")
**Resolves:** D-073 (public rendering engine), D-074 (published snapshot representation)
**Builds on:** ADR-001 (public IDs), ADR-003 (immutable Site Assets, D-075 direction), D-103 (platform Series Media)
**Leaves open:** D-076 (object storage / CDN provider), D-077 (Redis), D-094 (personal data retention)
**Backlog:** P5-001 (gate for P5-002 … P5-012)

## Context

Phase 5 turns the Draft into public sites. PUBLISHING.md §50–§55 required an ADR to choose how public pages are rendered (D-073) and how a published version is represented (D-074). Requirements: crawlable HTML with real content and metadata, strict Draft/Published separation, atomic activation, stable automotive prices and media after publish, no secrets in public output, and reuse of the official React Block renderers instead of a second rendering stack.

## Decision

### 1. Two snapshots per Published Version

Every successful Publish creates an immutable **Published Version** with two JSON representations:

- **Public manifest** — the sanitized rendering source. It contains only what the public runtime needs: Site public identity and display settings, design tokens, Pages (public ID, slug, home flag, title, order, SEO, indexability), visible Blocks (public ID, Block Definition slug, pinned Block Version, validated state, order), the public URLs of referenced Site Assets and Series Media images, the display-ready automotive presentation (Mark/Model/Generation/Series titles, selected media sets and exact images, Offers with the exact published price, RRP, availability, badge, benefits, Modification data, characteristics and options), Popup presentation, public Form definitions (public ID, stable field keys, labels, validation rules, consent/submit/success texts, CAPTCHA requirement) and validated public-safe action references. It is stored in `published_versions.public_manifest_json` with a `manifest_hash`.
- **Private draft snapshot** — enough editable Site state to restore the Draft later (P5-010): Pages, Block instances with pinned Block Versions and state, design tokens, Site settings, Site Vehicles, Site Offers and benefits, selected media references, Popups, Forms and Form fields, SEO. Relationships use public IDs. It is never sent to the browser or any public endpoint.

Neither snapshot contains Submissions, blacklist entries, CAPTCHA server keys, Integration credentials, audit logs or queue state.

### 2. Publish-time React SSR with stored HTML artifacts

- Public HTML is produced **at Publish time** by a dedicated, pure React renderer (`resources/js/public-runtime/render-server.tsx`) using `react-dom/server` `renderToString` and the same official Block registry as the Designer and Preview. The render context is built only from the public manifest: no database access, no HTTP, no secrets; output is deterministic for the same manifest.
- The renderer is built once per application deployment with the existing Vite tooling (`laravel-vite-plugin` `ssr` entry, `vp build --ssr`, self-contained bundle in `bootstrap/ssr/`). A customer Publish never runs a Vite build; it runs the compiled renderer once for all Pages of the Site.
- Laravel invokes it through `PublishedArtifactBuilder` with the Process API: an argument array (no shell string), the manifest on STDIN, a timeout, and safe error capture. The manifest is never written to logs.
- Each rendered Page is stored as a `published_pages` row: page public ID, slug, home flag, title, `rendered_html` (LONGTEXT), `hydration_json` (the exact public payload used to render it), `seo_json`, `content_hash`.
- A public visitor request serves the stored HTML inside a small Laravel shell (head metadata from `seo_json`, the hydration payload, the compiled public client bundle). **No Node process runs on a visitor request** and no permanent SSR daemon exists.
- The client entry (`hydrate-client.tsx`) calls `hydrateRoot` with the same payload. After hydration, Popups, Forms, Carousel, Lightbox, color/angle selection and offer expansion work inside the same Published Version context; nothing fetches Draft state.
- No Next.js, Remix, Astro, Nuxt, second templating framework or static-site generator is introduced.

The database is the initial artifact store (MVP). Moving artifacts to object storage/CDN later does not change manifest semantics and is decided with D-076. HTML rendered by an older renderer build remains valid; re-rendering on deployment is future work.

### 3. Versioned cache

Laravel Cache may cache published artifacts. Every key includes the version identity, e.g. `published:{site_public_id}:{version_public_id}:{page_public_id}`. Activating v2 makes requests resolve v2 keys; correctness never depends on deleting v1 entries, and a Site Publish never calls `Cache::flush()`. No Redis requirement (D-077 open).

### 4. Assets

- Site Assets stay immutable (ADR-003) and Series Media images stay platform media (D-103). Files are not copied per publication.
- Each Published Version records explicit references in `published_asset_references` (`published_version_id`, `kind` ∈ {`site_asset`, `series_media_image`}, `reference_public_id`). No polymorphic class names.
- Public delivery is version-scoped: `/_landflow/assets/{version}/{asset}` and `/_landflow/media/{version}/{image}`. The backend serves a file only if the reference exists for that version, with `X-Content-Type-Options: nosniff` and a long immutable cache lifetime. Private storage never becomes globally public; a Draft-only Site Asset remains private.
- A file referenced by any Published Version is never physically deleted. Phase 5 rejects deletion of a referenced Series Media image; Site Assets have no deletion path. A future cleanup may remove only files with zero Draft and zero Published references.

### 5. Atomic activation and concurrency

Flow: authorize `publish_site` → create a Publication attempt → validate the Draft (`PublishValidator`) → build both snapshots → create the Published Version (`building`) → render all Page artifacts → verify them → final transaction → switch `sites.active_published_version_id`.

- One build per Site at a time: lock the Site row, reject a second request while a Publication is building (safe conflict), create the building Publication, commit. Rendering runs **outside** any DB transaction.
- Activation: lock the Site, verify the Publication and version state, mark the version `ready`, set the Site pointer, mark the Publication `succeeded`.
- On any failure the Publication becomes `failed` with a safe code/summary, the version never becomes `ready`, and the previous production pointer is unchanged.
- Version numbers are per-Site monotonic integers allocated under the Site lock; gaps after failures are acceptable.
- Phase 5 publishes the whole Site; there is no Page-only publish. Old versions are kept.

### 6. Editorial snapshot vs live operational systems

The public display (content, prices, automotive data, media, Popup/Form presentation) comes only from the active Published Version; the public runtime never reads Draft Pages, Blocks, Offers, Forms, Popups or the catalog database. Live operational systems stay live: anti-spam limits, blacklists, CAPTCHA server configuration and Submission persistence. A Draft price change, a Global Catalog edit or a Series Media change never alters a published Site until the next successful Publish.

### 7. Public forms

- Preview submits against the current Draft through the authenticated preview endpoint and stores `mode = preview` (D-108).
- A published page submits to `/_landflow/forms/{version}/{form}` on the Site host. The backend resolves the Form definition and the trusted automotive context (vehicle, offer, exact published price) from **that** Published Version's manifest, never from the Draft. Browser-sent prices are ignored.
- A legitimately published version (belongs to the Site, reached `ready`) may accept submissions from its own already-loaded pages after a newer version is activated, provided the Form exists in its manifest and live anti-spam/security allows the request. This avoids activation races. A Draft-only Form or an unpublished/inactive Site cannot accept public leads.
- The Submission row keeps its FK to the Site's Form (Forms are never hard-deleted) and stores `mode = public`.

### 8. Restore

Restore copies a historical version's private draft snapshot into the **current Draft** in one transaction (requires `restore_version`). Production is unchanged; the owner reviews Preview and an explicit Publish creates a **new** version. The active pointer is never switched directly. Restore never touches Submissions, blacklists, security logs, Integration data, the catalog or platform media; entities with operational history are reconciled without destructive deletion.

### 9. Public hosts

Published Sites are served on `{subdomain}.{LANDFLOW_PUBLIC_DOMAIN}` (scheme `LANDFLOW_PUBLIC_SCHEME`), e.g. `dealer.localhost` locally and `dealer.landflow.me` in production. Reserved paths on public hosts: `/_landflow/*`, `/sitemap.xml`, `/robots.txt`. No Draft or authenticated routes are reachable through a public host's content resolution. Without an active version, the host returns a safe 404. Custom domains remain future work.

## Consequences

- Public pages are crawlable without JavaScript; hydration adds interactivity.
- `npm run build` must also build the SSR renderer; a Publish requires `node` on the application server, a visitor request does not.
- Artifacts live in the main database for now; their size grows with versions. Retention of old versions and artifact offloading are future decisions (D-076).
- PHPUnit cannot rely on the compiled renderer (it runs before the build), so feature tests bind a deterministic test renderer; the real renderer is covered by a bundle-dependent test and the browser E2E suite.
