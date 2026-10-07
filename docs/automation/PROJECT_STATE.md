# Landflow — Project State

**Document:** `docs/automation/PROJECT_STATE.md`  
**Status:** Living project memory  
**Purpose:** Give Cursor and autonomous agents a concise, reliable snapshot of the current Landflow project state before they start or continue work.

---

# 1. How This File Is Used

`PROJECT_STATE.md` is a **living operational document**.

It is not a product specification.

It answers:

- What phase is the project currently in?
- What decisions are already approved?
- What exists in the repository now?
- What is still only planned?
- What checks/tools are currently available?
- What is the next approved task?
- What decisions remain blocked?
- What must an agent read before changing code?

Every meaningful implementation task should update this file when project state changes.

---

# 2. Project Identity

Project name:

**Landflow**

Product type:

**SaaS website builder for automotive websites**

Primary product reference:

Webflow-inspired professional builder model adapted specifically for automotive workflows.

Core customer flow:

Workspace  
→ Create Site  
→ Choose Template  
→ Customize in Designer  
→ Configure Vehicles & Prices  
→ Configure Forms / Integrations  
→ Preview  
→ Publish

Landflow is **not** the separate automotive CRM project.

---

# 3. Current Project Phase

Current phase:

**Phase 6 — Integrations & Analytics: COMPLETED** (gate `P6-015` DONE, branch `autopilot/phase6-2026-10-05`). **Phase 7 — Paid Site Features: COMPLETED** for planned scope (X-022, P7-001 … P7-008 and review `P7-010` DONE on `autopilot/phase7-2026-10-06`; P7-008 = YooKassa, ADR-007 / D-078; `P7-009` DEFERRED by plan). **Phase 8 — Team / Collaboration: COMPLETED** (P8-001 … P8-010 and review `P8-011` DONE on `autopilot/phase8-2026-10-06`; D-088 → D-114, D-083 → D-115, D-087 → D-116). **Phase 9 — Developer Platform: IN_PROGRESS** (branch `autopilot/phase9-2026-10-07`; P9-001 DONE; D-093 APPROVED, D-117). Next ready task per `BACKLOG.md`: `P9-002 — Developer Permissions`.

Phase 5 — Publishing is COMPLETED: gate `P5-012 — Phase 5 Review` DONE (branch `autopilot/phase5-2026-10-05`).

Phase 4 — Forms & Interactive Components is COMPLETED: gate `P4-014 — Phase 4 Review` DONE.

Phase 0 — Foundation / Automation is COMPLETED: gate `P0-027 — Phase 0 Validation` DONE (phase report in BACKLOG P0-027 `### Result`).

Phase 1 — Core Platform is COMPLETED: gate `P1-017 — Phase 1 Review` DONE (phase report in BACKLOG P1-017 `### Result`).

Phase 2 — Designer Foundation is COMPLETED: gate `P2-018 — Phase 2 Review` DONE.

Phase 3 — Automotive Foundation is COMPLETED: gate `P3-017 — Phase 3 Review` DONE.

Current focus:

- last completed: `P1-002 — Remove 2FA / Passkeys and Enforce Email Verification` (auth baseline in §5);
- `X-007` DONE: D-085 APPROVED (ADR-001 Option B — bigint `id` + ULID `public_id`, §46);
- `P1-003` DONE: `workspaces` and `workspace_members` schema (DATABASE.md §5);
- `P1-004` DONE: `Workspace` / `WorkspaceMember` models (immutable ULID `public_id`, last-Owner guard);
- `P1-005` DONE: transactional personal Workspace creation and explicit account deletion lifecycle;
- `X-014` DONE: D-096 and D-097 APPROVED (ADR-002 — explicit Yandex linking and first-party HTTP client);
- `P1-005A` DONE: first-party Yandex OAuth, passwordless Users and external identities;
- `X-011` DONE: foundation security and test hygiene follow-ups;
- `P1-006` DONE: membership-scoped current Workspace resolution and secure switching;
- `P1-007` DONE: accessible sidebar Workspace switcher over the P1-006 backend;
- `P1-008` DONE: centralized Workspace permission catalog, role resolution, backend Gates and safe Inertia permission props;
- `P1-009` DONE: typed Plan entitlements and centralized Workspace capability/limit resolution, separate from permissions;
- `P1-010` DONE: minimal Workspace-owned Site schema with unique public ULID and restricted implicit tenant deletion;
- `P1-011` DONE: Site domain model, public-ID binding, Workspace relations and tenant-scoped permission policies;
- `P1-012` DONE: global official Template model, internal version baseline and idempotent Blank Template seed;
- `P1-013` DONE: permission- and entitlement-guarded Site creation in the current Workspace from an official Template public ID;
- `X-012` DONE: foundation UI/accessibility follow-ups for navigation, tab order, validation semantics and responsive browser coverage;
- `P1-014` DONE: responsive current-Workspace Dashboard with tenant-scoped safe Site cards, localized states and permission-aware create CTA;
- `P1-015` DONE: permission-guarded Create Site wizard (official Template choice, name, create) linked from the Dashboard; after creation the Dashboard confirms and highlights the new Site;
- `P1-016` DONE: browser E2E for Login → Workspace → Create Site → Template → Dashboard, plus cross-Workspace Site isolation;
- `P1-017` DONE: Phase 1 gate — full `composer quality` and full Playwright PASS;
- `P2-001` DONE: Site-owned Pages with Site-unique slug, ordering and a DB-enforced single home Page created with every new Site;
- `P2-002` DONE: global official Block Definitions with immutable versioned JSON Block Schema;
- `P2-003` DONE: deterministic Block Schema validator for the initial field types, enforced on Block Version creation;
- `P2-004` DONE: Page-owned Block Instances pinned to an official Block Version with draft state validated against its schema;
- `P2-005` DONE: six seeded official Blocks (Header, Hero, Benefits, CTA, Contacts, Footer) with version 1.0.0 schemas and frontend renderers;
- `P2-006` DONE: current-Workspace Designer shell (top bar, block list, canvas with official Block renderers and selection, read-only Properties panel) linked from Dashboard Site cards;
- `X-015` DONE: new personal Workspaces (registration and Yandex OAuth) get the idempotent system Free plan with `max_sites = 2` (D-100);
- `P2-007` DONE: Designer Pages panel (list, open, create, rename, delete non-home) scoped to the current Workspace;
- `P2-008` DONE: Designer Navigator (select, reorder, duplicate, hide/show, delete) and adding official Blocks with schema defaults;
- `P2-009` DONE: schema-driven Properties panel with live canvas draft and validated state saving;
- `P2-010` DONE: Repeater editing (add, delete, duplicate, reorder) with stable ULID item IDs;
- `P2-011` DONE: conditional schema fields (`visible_if` on earlier boolean/select sibling), multi-version official catalog, `header` 1.1.0;
- `P2-012` DONE: Site design tokens (fixed validated token set, «Стиль сайта» tab, CSS variables in renderers);
- `X-010` DONE: ADR-003 Site-owned immutable assets (D-087 Phase 2 scope, D-075 direction APPROVED);
- `P2-013` DONE: Site Asset upload (JPEG/PNG/WebP ≤10 MB, private storage, nosniff serving), same-Site image references, image picker;
- `P2-014` DONE: safe `action` field (open_url http/https, open_page same Site, scroll_to same Page, phone, email), action-enabled official block versions;
- `P2-015` DONE: debounced draft autosave of Block content with status indicator (never publishes);
- `P2-016` DONE: authenticated draft preview (`preview_site`), visible blocks with tokens/assets, anchors for scroll actions;
- `P2-017` DONE: Playwright designer flow (blocks, properties, repeater, action, upload, style, autosave, reorder, reload, preview);
- `P2-018` DONE: Phase 2 gate (composer quality, 38 E2E passed); Phase 2 COMPLETED;
- `X-008` DONE: D-084 APPROVED — ADR-004 integer minor-unit money (`BIGINT UNSIGNED *_minor`, `CHAR(3)` currency, basis points);
- Catalog V2 schema adopted as `docs/architecture/AUTO_CATALOG_SCHEMA.md` (owner input, version 2); the catalog BLOCKED_DECISION is RESOLVED;
- `X-016` DONE: automotive architecture reconciled with Catalog V2 (D-101 … D-106; Mark, Equipment under Modification, separate `catalog` database, Series Media Library, SiteVehicle → Series / SiteOffer → Equipment); follow-ups `X-017` (storage quota), `X-018` (action reference integrity), `X-019` (preview permission) recorded;
- `X-009` DONE: D-086 APPROVED — ADR-005 Equipment-level characteristic values (two-level dictionary, TEXT value, unit on definition, no inheritance, no empty rows);
- `P3-001` DONE: separate `catalog` connection + `catalog:migrate` guard, V2 core tables (marks … equipments) with `public_id`, `App\Models\Catalog` models, status-chain `available()` scopes, isolated test/E2E catalog databases;
- `P3-002` DONE: explicit platform roles (`super_admin`, `catalog_manager`) and permissions (`view_catalog`, `edit_catalog`, `manage_catalog_media`), gates + middleware, `php artisan platform:role grant|revoke <email> <role>`;
- `P3-003` DONE: two-level characteristic dictionary and Equipment values (ADR-005) with server validation and canonical values;
- `P3-004` DONE: two-level option dictionary and Equipment option values (`is_base` explicit, missing row = unknown);
- `P3-005` DONE: platform Series Media Sets in the main database (`catalog_series_public_id`, name, display swatch, status, order); no catalog color tables;
- `P3-006` DONE: immutable Series media images per angle (private storage, server keys, JPEG/PNG/WebP ≤10 MB, no SVG), platform-only upload/delete, nosniff serving; shared `ImageUpload` rules;
- `P3-007` DONE: platform catalog UI at `/platform/catalog` (cascading hierarchy, dictionaries, Equipment characteristics/options, Series media); no Filament;
- `P3-008` DONE: Site-owned `site_vehicles` (Series reference, one per Series per Site) and media-set selection by reference; vehicle policy abilities;
- `P3-009` DONE: `site_offers` (Equipment of the vehicle Series, ADR-004 `*_minor` money, availability, badge) and amount-based `site_offer_benefits`; shared `App\Support\Money`;
- `P3-010` DONE: customer «Автомобили» flow (cascading Series picker, media-set selection by reference, offers with server-parsed prices and benefits, tenant-scoped 404s);
- `P3-011` DONE: `VehicleMediaResolver` (Site selection → Global active Series media sets; Workspace level not in Phase 3), bulk resolution;
- `P3-012` DONE: `VehicleBindings` display-ready vehicle/offer view models (visible + available only, formatted money, no numeric IDs) in designer/preview, same-Site `vehicle` Block field;
- `P3-013` DONE: official `vehicle-card` Block (same-Site vehicle reference, color swatches, «от» price, benefit, action button);
- `P3-014` DONE: official `vehicle-grid` Block (all visible Site vehicles or a validated selection with per-item actions, columns, price/benefit/colors);
- `P3-015` DONE: vehicle detail Blocks — gallery (colors + angles), offers (expandable to Modification/characteristics/options), characteristics, equipment;
- `P3-016` DONE: automotive Playwright flow (platform media sets + characteristics → dealer vehicle/offer → Vehicle Grid/Offers → preview price, image, color switch, expanded offer); idempotent `CatalogDemoSeeder`;
- `X-019` DONE: `preview_site` for Admin and Designer (D-105); Designer still without `publish_site`; ContentEditor unchanged;
- `P3-017` DONE: Phase 3 gate (composer quality 440 tests, 39 E2E passed, diff check); Phase 3 COMPLETED;
- `X-020` DONE: Admin gets `view_site`, `view_vehicles` and `edit_benefits` (D-107). Offer saves now authorize by what changes: `edit_prices` for offer fields, `edit_benefits` for benefits, both when both change. Designer and ContentEditor are unchanged.
- Phase 4 COMPLETED:
- `P4-001` DONE: Site-owned reusable Popups (presentation only, D-035), «Попапы» section, accessible `PopupView` runtime in designer/preview props.
- `P4-002` DONE: `open_popup` action (same-Site active Popups only), preview runtime with per-trigger public-ID context (block/vehicle/offer/media set) and focus return.
- `P4-003` DONE: Site-owned Forms with stable-key fields (8 types, consent text is customer-owned, hidden values untrusted), «Формы» UI under `edit_forms`, same-Site Popup→Form attach; preview Popups render the form (display-only until P4-004).
- `P4-004` DONE: public `POST /forms/{form_public_id}/submissions` (guest, CSRF-exempt JSON, per-IP backstop), active Form + active Site only, strict payload keys and server-side field validation; published reachability deferred to Phase 5.
- `P4-005` DONE: `SubmissionPipeline` persists valid Submissions (immutable field snapshot, no raw request) before responding; read-only «Заявки» list under `view_submissions`.
- `P4-006` DONE: central `PhoneNormalizer` (digits only, 10–15 digits, no trunk-8 rewrite pending owner decision); original + normalized stored.
- `P4-007` DONE: `SubmissionContextResolver` turns public-ID hints into a trusted same-Site snapshot (vehicle/offer/equipment/server price/media set/page/block/popup); visitor URL/referrer/UTMs stored separately.
- `P4-008` DONE: centralized `SubmissionGuard` (honeypot, IP 5/10m and phone 2/30m per Site via RateLimiter, Form+phone duplicate 15m); per-Site overrides on «Защита форм» (`edit_site_settings`); spam never persisted.
- `P4-009` DONE: scoped `blacklist_entries` (global/workspace/site × ip/phone, expiry); Global only via audited `blacklist:global` command (super admin + reason); tenant lists on «Защита форм».
- `P4-010` DONE: `CaptchaVerifier` + `YandexSmartCaptchaVerifier` (ok passes, failed fails closed, outage fails open with safe log), fake verifier for testing/e2e; Site toggle `captcha_required`; client key only in browser.
- `P4-011` DONE: reusable vendor-neutral `Carousel` (scroll-snap, accessible, pausable autoplay, reduced motion); `vehicle-grid` 1.1.0 carousel mode.
- `P4-012` DONE: reusable `Lightbox` (D-033; keyboard, focus trap/return, alt text) adopted in the Vehicle Gallery.
- `P4-013` DONE: interactive Playwright flows (form/popup/submission, vehicle offer trusted context + spoofed price, duplicate, honeypot, fake CAPTCHA, carousel, lightbox, 375 px); fixture monitors all tabs.
- `P4-014` DONE: Phase 4 gate (composer quality 509 tests, 41 E2E passed, diff check); Phase 4 COMPLETED.
- Phase 5 (COMPLETED on `autopilot/phase5-2026-10-05`):
- `X-021` DONE (D-108): trunk-8 phone rewrite, typed Submission `mode` (`public` | `preview`) with an authenticated preview endpoint and separate per-mode duplicates/counters, real leads listed apart from preview test entries, Admin `edit_popups`, form security and Site blacklist under `edit_forms`.
- `X-018` DONE: reusable `BlockReferenceInspector` (stale Page / scroll target / Popup / asset / vehicle references, disabled Popup Form warning, publish mode for hidden Blocks); Designer warnings in the Navigator, properties summary and inline fields; state never rewritten.
- `P5-001` DONE: ADR-006 accepted — D-073 and D-074 APPROVED (publish-time React SSR, public manifest + private draft snapshot, DB HTML artifacts, versioned cache, published asset references, atomic activation, Published-Version-bound public forms, restore to Draft).
- `P5-002` DONE: Published Version schema — `published_versions`, `published_pages`, `published_asset_references`, `sites.active_published_version_id`; immutability guards and pointer validation in the models.
- `P5-003` DONE: Publication attempt records (actor, enforced status machine, safe failure code/summary/metadata) and `publish` / `restoreVersion` Site policy abilities.
- `P5-004` DONE: `PublishValidator` (errors block, warnings inform) reusing the X-018 inspector, pinned-schema validation and new publish-time completeness checks.
- `P5-005` DONE: `PublishedSnapshotBuilder` (public manifest + private draft snapshot, canonical hash) and `PublishedArtifactBuilder` (version-scoped per-Page payloads, publish-time React SSR via the compiled `bootstrap/ssr/render-server.js`, stored HTML artifacts and asset references, no activation).
- `P5-006` DONE: `PublishSite` atomic activation (Site-locked start with conflict/abandon handling, rendering outside transactions, final locked pointer switch after artifact verification, safe failure handling) and the «Публикация» page with the Publish action.
- `P5-007` DONE: anonymous public runtime on `{subdomain}.{LANDFLOW_PUBLIC_DOMAIN}` from stored artifacts only (no Draft reads, no Node per request), hydration from the stored payload, version-scoped immutable file delivery, version-bound public Form endpoint (`/_landflow/forms/{version}/{form}`, manifest-derived context and price, mode `public`). `sites.subdomain` column added (rules/UI in P5-008). The Draft public endpoint `POST /forms/{form}/submissions` was removed; preview keeps mode `preview`. Published Series media images cannot be deleted.
- `P5-008` DONE: Landflow subdomains — `SiteSubdomain` rules (DNS label, global uniqueness incl. archived, reserved list + `xn--`), transliterated suggestion at Site creation (stable on rename), backfill migration, explicit change on «Публикация» (`manage_domains`), pre-publish `subdomain_missing`.
- `P5-009` DONE: Page SEO fields (title, description, noindex) with `edit_seo` / `edit_seo_basic` split, published head (title, description, robots, canonical, real-value Open Graph, no invented image), `/sitemap.xml` (indexable Pages of the active version only), `/robots.txt` (no Draft URLs), preview `X-Robots-Tag: noindex, nofollow`.
- `P5-010` DONE: version history on «Публикация» (number, date, publisher, status, production badge) and Owner-only `restore_version` restore of the private Draft snapshot into the Draft in one transaction (Pages, Blocks, SEO, design, Forms/Fields, Popups, Vehicles, Offers, benefits; public IDs reused, cross-Site IDs rejected, missing Forms switched off not deleted); production unchanged until the next Publish creates a new version; operational data (Submissions, blacklists, form security, subdomain) untouched.
- `P5-011` DONE: Playwright `publishing-lifecycle.spec.ts` covers the full lifecycle (404 before publish, preview, publish v1–v4, no-JS HTML with price, Draft isolation incl. Form label and price, public vs preview leads, historical media URL, blocked Publish on a broken action, history, restore, permissions, protected platform image, 375 px).
- `P5-012` DONE: Phase 5 gate. Fixed static `public/robots.txt` shadowing per-Site robots on public hosts (route on the application host instead); added catalog/Block-catalog isolation test and extended lifecycle E2E (image A → B, interactivity after hydration, Admin/Designer/foreign permissions, robots/canonical). Phase 5 COMPLETED.
- Phase 6 (COMPLETED on `autopilot/phase6-2026-10-05`):
- `P6-001` DONE: Workspace `integration_profiles` (webhook / custom API, auth none / bearer / basic / API-key header, encrypted credentials, archive when referenced) with the «Интеграции» page; D-109 permission reconciliation (Admin gains `view_integrations`, `edit_form_routes`, `view_delivery_logs`, `retry_deliveries`).
- `P6-002` DONE: credentials in Laravel `encrypted:array`, fixed mask with a last-four hint only for long secrets, empty input keeps / new input replaces the secret, secret inputs never flashed; tests prove the token is absent from HTML, Inertia JSON, serialization, session and validation responses.
- `P6-003` DONE: `site_integration_bindings` (Site-specific non-secret overrides such as `site_id`, same-Workspace profile enforced in validation and the model, no token copy) with the «Интеграции сайта» page.
- `P6-004` DONE: `form_routes` (independent email / webhook / custom API routes per Form, same-Site binding with matching provider, safe method/path/headers, allowlisted subject placeholders) with the «Передача заявок» page under `edit_form_routes`; `config/integrations.php`.
- `P6-005` DONE: declarative field mapping (`FieldMapper`, allowlisted `MappingSources`: Form fields, trusted page/vehicle/offer/UTM context, Site, binding overrides, constants; `omit` | `null` | `error` for missing values; deterministic default payload) with a «Сопоставление полей» editor on HTTP routes.
- `P6-006` DONE: `submission_deliveries` / `submission_delivery_attempts`, `DeliveryDispatcher` (public Submissions only, after persistence), queued `ProcessSubmissionDelivery` (ID only), `DeliveryAdapter` contract with normalized `DeliveryResult`, atomic claim in `DeliveryProcessor`.
- `P6-007` DONE: retry ladder (immediate, 1/5/15/60 min, then failed) via `next_retry_at` and the scheduled `integrations:dispatch-due-deliveries` (stale `processing` / lost `pending` recovery), shared `DeliveryFailures` classifier, Delivery `public_id` as idempotency key, manual retry under `retry_deliveries`.
- `P6-008` DONE: `EmailDeliveryAdapter` + escaped `SubmissionLeadMail` (validated recipients, allowlisted subject placeholders, safe reply-to, no IP/IDs/blacklist/secrets; transport failures retried).
- `P6-009` DONE: `HttpDeliveryAdapter` (webhook / custom API) over the shared `OutboundHttpPolicy` + `OutboundHttpClient` (https only, all resolved IPs public, pinned connection, no redirects, no proxy, 3 s / 10 s timeouts, 1 MB response cap, auth from encrypted credentials, safe metadata only); tests forbid stray HTTP and real DNS.
- `P6-010` DONE: «Доставка заявок» log page (`view_delivery_logs`, status filter, attempt history, safe errors, retry button with `retry_deliveries`) and delivery badges on the Submissions page.
- `P6-011` DONE: «Проверить подключение» (`manage_integrations`, rate-limited) through the delivery adapter / `OutboundHttpClient` (same SSRF policy, timeouts, auth, classifier), no Submission, safe toast only.
- `P6-012` DONE: Yandex Metrica Site settings (`site_analytics_settings`, `manage_integrations`, Webvisor off by default); changes go live only through Publish (`analytics.yandex_metrica` in the manifest), official `tag.js` loader + `init` emitted on published pages only, never in Preview, no PII.
- `P6-013` DONE: payload-free semantic events (form start / submit / validation error / success, popup open / close, vehicle form submit) in the published runtime, routed once to `ym(counter, "reachGoal", goal)`; no-op without a counter or with a blocked loader.
- `P6-014` DONE: integrations browser lifecycle (`tests/browser/integrations.spec.ts`) over E2E-only fake DNS + CRM transport (`INTEGRATIONS_E2E_FAKE`, testing/e2e only; real SSRF policy) and a spec-driven `database` queue in E2E.
- `P6-015` DONE: Phase 6 gate. Audit found no regressions (ownership, encrypted/masked secrets, persist-before-deliver, preview never delivered, bounded retries, idempotency, SSRF incl. DNS rebinding and redirects, safe logs, semantic permissions, Metrica frozen per version and absent from Preview). Final gate: `composer quality` PASS (728 tests), `npm run test:e2e` 44 passed. Phase 6 COMPLETED; Phase 7 NOT_STARTED.
- Phase 7 (COMPLETED for planned scope, branch `autopilot/phase7-2026-10-06`):
- `X-022` DONE (D-110): shared `CreateWorkspace` action; new accounts get «Моё пространство»; always-interactive switcher with «Создать пространство» / «Управление пространством»; `/workspaces/create` (Free plan, Owner, becomes current, no cap) and `/workspace/settings` rename (`edit_workspace`); Workspace shell («Все сайты», «Интеграции», «Настройки пространства») and Site shell (`SiteLayout`, backend `siteContext` abilities, grouped Site sections, «← Все сайты»); `/sites/{site}` «Общее» with rename under `edit_site_settings`.
- `P7-001` DONE (D-111): `site_domains` (normalized globally unique hostname, TXT token, independent verification/routing/SSL states, no key material), `CustomHostname` validation, `CustomDomainAccess` (`manage_domains` + `custom_domain`), «Домены» page with DNS instructions from `config/domains.php`.
- `P7-002` DONE: `DnsResolver` (system / fake, fake only in testing/e2e), `DomainVerifier` (exact TXT ownership, sticky; routing via CNAME chain or A/AAAA vs configured ingress, re-checked every time; safe error codes), rate-limited «Проверить DNS», `domains:reconcile` every 5 minutes, `domains:fake-dns` E2E helper. No HTTP fetch.
- `P7-003` DONE: `SslProvisioner` (none / command / fake), `DomainSsl` lifecycle (eligibility, atomic claim, queued `ProvisionDomainSsl`, bounded backoff, manual retry 4/hour, stale recovery), metadata-only storage; provisioning-script contract recorded in D-111.
- `P7-004` DONE: custom-host route group (custom domains first, then Landflow subdomains, unknown → 404), effective primary (active + entitled), 301 to primary preserving path/query (no loops), «Сделать основным» / back to Landflow, removal fallback; canonical, sitemap, robots and app addresses use the primary; browser domain flow `tests/browser/domains.spec.ts`.
- `P7-005` DONE (D-112): «Создано на Landflow» footer decided per request from the live `remove_branding` entitlement, rendered by the page shell; no longer frozen in manifest / HTML / hydration.
- `P7-006` DONE: SEO audit (canonical / sitemap / robots / `og:url` on the primary address, OG only from real data, noindex excluded from sitemap); Site section «SEO» (`/sites/{site}/seo`) edits every page's SEO via the Page SEO endpoint with `edit_seo` / `edit_seo_basic`.
- `P7-007` DONE: entitlement review — `max_sites` (create only, locked, active Sites), `custom_domain` (manage + serve + reconcile), `remove_branding` (per request) enforced; `max_members` has no enforcement point until invitations (P8-001); D-100 preserved; inactive plan denies all. Account-level limit / anti-abuse policy for number of Free Workspaces is an open product/billing follow-up.
- `P7-008` DONE (D-078 APPROVED, ADR-007): YooKassa; Workspace = billing customer; Landflow-owned Subscription with entitlements still via the Workspace Plan; saved `payment_method_id` + Landflow-scheduled idempotent renewals; webhooks verified by authoritative API re-fetch; `past_due` keeps paid access until period end, then downgrade to Free; cancellation at period end; 54-FZ receipt adapter point. Docs only; `P7-009` DEFERRED.
- `P7-010` DONE: Phase 7 review, no regressions (domain ownership / DNS-only verification / SSL via server command without private keys in app / no app-host capture / loop-free 301s / live entitlements / consistent SEO). Phase 8 NOT_STARTED. Open follow-ups: unverified hostname claim expiry, certificate deprovisioning on domain removal, extra dotted app hostname hardening, `max_members` enforcement in P8-001, Free Workspace anti-abuse / account-level limit, pre-P7-005 Published Versions keep the frozen footer until republished, flaky tablet `auth.spec.ts` resend root cause (timeout increase `54e9895` is not a fix).
- `P8-001` DONE: Workspace invitations — separate `workspace_invitations` (hashed single-use token, TTL `WORKSPACE_INVITATION_TTL_HOURS`, rotation on resend), «Команда» `/workspace/team` (`manage_members`), `max_members` enforced as reserved seats (members + pending invitations) under a Workspace row lock, central `TeamAuthority` (Owner never invitable, Admin below Admin only), token-free session landing `/invitation` with `Referrer-Policy: no-referrer`, acceptance only by the verified User with the matching email.
- `P8-002` DONE: member lifecycle — suspend / reactivate / remove via central `TeamAuthority` (never self or Owner; Admin below Admin only); suspension keeps the seat, removal deletes only the membership and frees it; access re-resolved per request so sessions lose the Workspace immediately.
- `P8-003` DONE (D-088 resolved by D-114): Site access scope `all_sites` / `selected_sites` (`site_member_access`, invitation scope copied on acceptance); central `SiteAccessResolver` in every `SitePolicy` ability, `DesignerScope`, `EnsureSiteAccess` on all `sites/{site}` routes and the dashboard list; unassigned Site → 404 incl. nested URLs; Owner / Admin / Integrations Manager forced `all_sites`.
- `P8-004` DONE: system roles Pricing Manager / Lead Manager / Integrations Manager / Publisher with the exact approved permissions (no export, no lead contents for Integrations Manager, no restore for Publisher); Admin + `import_vehicles`; Owner-only role change via `manage_roles`, forcing `all_sites` for Admin / Integrations Manager.
- `P8-005` DONE (D-083 resolved by D-115): Workspace Vehicle Library — `workspace_vehicles` + media-set pivot (reusable name / description / platform media only, no commercial fields), «Библиотека автомобилей» (`manage_workspace_vehicle_library`), «Добавить на сайт» as an independent `SiteVehicle` copy with nullable provenance (library permission + Site access + `import_vehicles`), «Сохранить в библиотеку» with explicit overwrite confirmation; no live fallback or sync.
- `P8-006` DONE: Site-to-Site vehicle copy inside one Workspace («Импортировать с другого сайта») — access to both Sites, `import_vehicles` + `edit_vehicles` on the destination, `edit_prices` / `edit_benefits` for offers / benefits; new destination rows with exact money, one transaction per vehicle, per-vehicle results; existing Series reported as conflict and left untouched.
- `P8-007` DONE: copy conflict resolution — conflict = destination Site + Series, default skip, read-only preview (text / media / status / offer matches and price differences), explicit «Обновить выбранное» per field with `edit_prices` / `edit_benefits` for commercial fields; offers matched by Equipment, missing created, unrelated destination offers kept; summary Copied / Updated / Skipped / Conflicts unresolved.
- `P8-008` DONE (D-087 resolved by D-116): «Медиатека» — `workspace_assets` with SiteAsset file semantics and `manage_workspace_assets` (Owner); «Копировать на сайт» creates an independent `SiteAsset` file copy (Site access + `manage_assets`); deleting a Workspace Asset never touches Site copies; X-017 quota scope now includes Workspace Assets.
- `P8-009` DONE: publication note (≤ 500, plain text, on the immutable `Publication`), richer paginated history (note, actor, restore count / last restore), `site_version_restores` audit in the restore transaction, unpublished-changes indicator via deterministic manifest hash; Publisher cannot restore. Site Vehicle custom name / description are part of the draft snapshot (Phase 8 correctness pass).
- `P8-010` DONE: Team E2E fixtures (test-only 10-seat plan, Site A / B, role accounts, foreign Workspace with fixed IDs), env-gated `team:e2e-invitation-url` (testing / e2e only, hash-only storage) and `tests/browser/team.spec.ts` covering invitation, site access, suspend / restore / remove, role limits, library / copy / conflicts, shared assets, version notes / restore, foreign-resource 404s and 375px layouts.
- `P8-011` DONE: Phase 8 review, no regressions. Phase 8 COMPLETED, Phase 9 NOT_STARTED. Open follow-ups: platform Series media sets referenced by the Workspace library cannot be deleted (restrict); unpublished-changes indicator only on the Publishing page; restores are audited in `site_version_restores` but not written to the app log; no shipped Block renders the vehicle `description` binding yet; flaky tablet `auth.spec.ts` root cause still unknown. Invitation tokens: application logging never records them, the DB stores only the hash and normal Inertia / HTML never carries them; the local `MAIL_MAILER=log` transport writes the rendered email (including the invitation URL) to the local log by definition — production must use a real mail transport (X-013). Still open: D-076, D-077, D-089, D-090, D-091, D-093, D-094, X-013, X-017.
- Phase 8 correctness pass (after P8-011): restore-to-Draft brings back historical Site Vehicle `custom_name` / `custom_description` (snapshots from before these keys keep the current values); the public vehicle binding / Published manifest exposes optional `description` (= `custom_description`, present only when set, so vehicles without one keep their pre-Phase-8 manifest hash; a new description changes the hash and the unpublished-changes indicator); a Site-to-Site conflict update that would touch offers is refused as `ambiguous` (nothing on the vehicle changes) when the destination holds several offers for one of the source's Equipment.
- `P9-001` DONE (D-093 APPROVED, D-117): `developer_profiles` — User-owned creator identity (one per User, immutable owner, restricted User delete, no Workspace relation, `active` / `suspended`); `manage_developers` platform permission (Super Admin only, no Developer platform role); «Разработчики» `/platform/developers` (grant to an existing verified User by email, suspend / restore, no hard delete); «Панель разработчика» `/developer` for the current User's own active profile without Workspace context. Block / Template authoring does not exist yet (P9-002+).

No product feature implementation should begin merely because architecture documents now exist.

---

# 4. Current Repository Baseline

Correction (P0-022 inspection): earlier versions of this file assumed the Laravel + React + Inertia application was already installed in this repository. Repository inspection proved this false.

Verified actual state before P0-021A:

```text
Laravel application:        NOT_INSTALLED
React/Inertia application:  NOT_INSTALLED
composer.json:              MISSING
package.json:               MISSING
Playwright:                 NOT_AVAILABLE_YET
Documentation (docs/):      PRESENT
Cursor rules:               PRESENT
Cursor agents:              PRESENT
```

The application foundation was created by `P0-021A — Bootstrap Landflow Application` (status: DONE).

Verified actual state after P0-021A (historical snapshot; Playwright was added later by P0-023):

```text
Laravel application:        INSTALLED (official laravel/react-starter-kit)
React/Inertia application:  INSTALLED
composer.json:              PRESENT
package.json:               PRESENT
Git repository (.git):      INITIALIZED (P0-021B, branch main, origin mddevops/landauto)
Playwright:                 NOT_AVAILABLE_YET (at that time)
```

Current tooling state (Playwright, quality commands, CI): §40 and §70.

Installed stack (verified from `composer show` / `npm ls`):

Backend:

- Laravel 13.33.0
- PHP requirement ^8.3 (local runtime 8.3.6)
- Inertia backend (`inertiajs/inertia-laravel`) 3.4.0
- Laravel Fortify 1.40.0 (login, registration, password reset, email verification; 2FA / passkeys disabled and removed in P1-002, D-095)
- Laravel Wayfinder 0.1.21
- `laravel/chisel` 0.1.1 (starter-kit scaffolding tool, shipped by the official kit; not used by app code)

Frontend:

- React 19.3.0
- Inertia frontend (`@inertiajs/react`) 3.7.1
- TypeScript 5.9.3, strict
- JSX `react-jsx`
- shadcn/ui (`components.json`, style `new-york`, utils `@/lib/utils`, granular `@radix-ui/*` packages)
- Tailwind CSS 4.3.3
- Vite 8.3.1 through vite-plus 0.3.0

Repository environment template (`.env.example`): SQLite database, database session/cache/queue (consistent with D-015). Safe placeholder values only.

Actual local development environment (verified 2026-09-29 with `php artisan db:show` / `migrate:status`):

```text
Development database:   MySQL 8.2.0 (OSPanel), connection mysql
Host / port:            MySQL-8.2 / 3306
Database:               landauto (all current migrations ran)
Configured in:          developer .env only (git-ignored); credentials are not stored in the repository
```

- The development MySQL server also hosts unrelated databases of the developer; `landauto` is the only Landflow database.
- This is the developer's local environment, not a production database-engine decision (still open; see `.cursor/rules/50-database.mdc` §85).

Automated test databases (unchanged, independent of the development database):

```text
PHPUnit:          SQLite :memory: (phpunit.xml)
Playwright E2E:   file SQLite database/e2e.sqlite, recreated and migrated before every run (.env.e2e, APP_ENV=e2e)
```

- Automated tests must never use the development MySQL database `landauto`. Guards: `tests/TestCase.php` refuses to boot unless the default connection is SQLite `:memory:` (e.g. cached config or shell `DB_*` variables), and `tests/browser/support/prepare-e2e.mjs` asks Laravel which connection the e2e environment resolves and stops unless it is `database/e2e.sqlite`. Both guards verified with a probe database path.
- Switching PHPUnit or E2E to MySQL requires a separate task/decision.

Aliases:

`@/*` → `resources/js/*`

Current frontend structure includes:

- `resources/js/pages`
- `resources/js/layouts`
- `resources/js/components`
- `resources/js/hooks`
- `resources/js/lib`
- `resources/js/types`

---

# 5. Current Authentication Baseline

Existing authentication uses Laravel Fortify.

Supported Landflow sign-in methods (D-095, Product Owner, 2026-09-29): email + password with mandatory email verification, and Yandex OAuth with a required email from the authenticated Yandex profile response (implemented in P1-005A). Landflow does not use 2FA, TOTP, passkeys or WebAuthn; the starter implementation was removed in P1-002.

Approved Yandex baseline (D-096, D-097): provider identity is keyed by `provider_user_id`; email collisions never auto-link; explicit linking starts from an authenticated existing account; the client is a first-party adapter on Laravel HTTP client. Yandex-only Users have no artificial password, so P1-005A must make `users.password` nullable and adapt password-dependent flows/UI.

Implemented Yandex baseline: internal `user_auth_identities` with both ADR-002 unique constraints; nullable password; state + PKCE authorization-code flow; server-only credentials; no stored OAuth tokens; verified new account + personal Workspace creation; session rotation on login; Russian collision/missing-data errors. Passwordless Users add a password through the email reset flow before changing email or deleting the account. Real Yandex OAuth smoke test PASS (2026-10-01): new User, repeat identity login, and email collision without auto-link; `BLOCKED_EXTERNAL` resolved.

Baseline after P1-002 (audit history: BACKLOG P1-001 `### Result`; changes: P1-002 `### Result`):

- Fortify features (`config/fortify.php`): registration, reset passwords, email verification. No 2FA / passkey features, routes, UI, schema (`passkeys` table and `users.two_factor_*` dropped by migration `2026_09_29_000001`) or direct npm dependencies. PHP packages `laravel/passkeys`, `pragmarx/google2fa`, `bacon/bacon-qr-code` remain installed as unused Fortify transitive dependencies; `laravel/passkeys` is excluded from package discovery. Fortify profile/password update features are not enabled; `app/Http/Controllers/Settings/*` replace them. `lowercase_usernames` is on.
- Rate limits: `login` 5/min (email + IP) plus 20/min per IP; registration, forgot-password, reset-password POST, `password.confirm.store` and `profile.destroy` each use a named 5/min limiter with a Russian 429 response; email verification resend, password update and profile update use `throttle:6,1`; reset-token creation is additionally throttled per email (60 s).
- Sessions: regenerated on login; logout invalidates the session; database driver, HttpOnly, SameSite lax; `SESSION_SECURE_COOKIE` is a production setting (`X-013`). A successful password reset deletes all of the user's `sessions` rows (database driver only; other drivers → `X-013`) and Fortify rotates the remember token. Password change / email change do not end other sessions (policy in `X-013`). Password confirmation window: 3 hours.
- Passwords: `Password::defaults()` — production min 12, mixed case, letters, numbers, symbols, uncompromised; min 8 outside production.
- Email verification: `User` implements `MustVerifyEmail`; registration creates the user unverified and sends the verification email. Unverified users may use only the allowlist in SECURITY.md §3 (verification notice / link / resend, logout, `settings` redirect, profile view / update, Fortify password confirmation); everything else, including account deletion, requires `verified`.
- Emails are stored trimmed and lowercased (registration, profile update); no backfill of existing rows (no production data). Email change requires the current password, clears verification, sends a new verification email and a Russian informational notice to the old address.
- Account deletion: verified users only; hard delete after current-password confirmation. P1-005 explicitly removes memberships and database sessions, deletes an empty single-member personal Workspace, and blocks deletion when the user is the sole active Owner of a Workspace with other members; retention: D-094.
- Russian localization covers auth UI, validation, the verify-email / reset-password / email-changed notifications and the 429 page (`lang/ru/*.php`, `lang/ru.json`).
- Tests: PHPUnit 102 tests (`tests/Feature/Auth/*`, `tests/Feature/Settings/*`, `LocalizationTest`), including removed-feature, unverified-access, session-invalidation, normalization and email-change tests; Playwright 27 tests (setup 1, desktop 18, tablet 4, mobile 4) including the unverified-user and email-change flows.

---

# 6. Current Routes / Application Baseline

Known existing routes include:

- `/`
- `/dashboard`
- settings routes
- Fortify routes
- health route `/up`

No standalone `api.php` is currently required for product architecture.

Approved direction:

Do not introduce a public/internal REST API merely because it is conventional.

APIs should be added only when required by:

- published runtime;
- public Form endpoints;
- external integrations;
- future developer/public API.

---

# 7. Current Model Baseline

Implemented core domains (Phases 0–8 COMPLETED):

- User / authentication (email + verification, Yandex OAuth);
- Workspace, membership, roles / permissions, entitlements, team invitations and Site access;
- Site;
- Templates, Pages, Block Definitions / Versions / Instances;
- automotive Site Vehicles / Site Offers and the Workspace Vehicle Library;
- Forms, Popups, Submissions;
- integrations, deliveries, analytics;
- publishing and Published Versions;
- custom domains;
- Site Assets and Workspace Assets.

The Global Automotive Catalog is implemented on the separate `catalog` connection.

Phase 9 — Developer Platform is IN_PROGRESS: Developer Profiles exist (P9-001); Block / Template authoring does not. Architecture documents also describe planned target models; agents must check the code to tell implemented from planned.

---

# 8. Current Testing Baseline

Current testing stack:

- PHPUnit 12.5.36
- SQLite in-memory tests
- Mockery available
- Playwright 1.63.0 (Chromium), `npm run test:e2e`, separate SQLite database `database/e2e.sqlite` (P0-023/P0-024)

Approved decisions:

- PHPUnit remains the main PHP testing framework.
- Do not introduce Pest merely for preference.
- Browser automation uses Playwright.
- Laravel Dusk is not currently planned.

---

# 9. Current Code Quality Baseline

Known tooling/packages include:

- Laravel Pint
- Larastan
- PHPUnit
- Composer scripts/tooling
- vite-plus check commands

Frontend:

No standalone ESLint is required currently.

Approved direction:

Use repository `npm run check` / `vp check` workflow rather than adding ESLint solely by convention.

---

# 10. Package Manager

Approved:

**npm only**

Do not introduce:

- yarn
- pnpm
- bun

unless a future explicit architecture decision changes this.

---

# 11. Current UI Foundation

Approved UI stack:

- React
- Inertia
- shadcn/ui
- Tailwind CSS

Approved shadcn conventions:

- use `@/lib/utils`;
- use normal/granular Radix dependencies;
- do not create duplicate `cn` helpers;
- do not introduce umbrella `radix-ui` package without explicit need.

---

# 12. SSR Direction

Current approved direction:

Likely disable/not use Inertia SSR initially unless public rendering architecture later requires it.

Important:

The public Site rendering engine is still an architectural decision to be finalized in a dedicated ADR before publishing implementation.

Do not conflate authenticated dashboard rendering with public published Site rendering.

---

# 13. Session / Cache / Queue Direction

Approved initial direction:

- database session;
- database cache;
- database queue where appropriate.

Redis may be introduced later when actual operational requirements justify it.

Do not add Redis only because it is common.

---

# 14. Product Documents Completed

Current completed product documents:

`docs/product/PRODUCT.md`

Defines:

- product vision;
- customer workflows;
- Workspace/Site model;
- Templates;
- Designer;
- automotive catalog;
- Blocks;
- Forms;
- Integrations;
- plans;
- Team;
- Developer Platform;
- Marketplace;
- phases.

`docs/product/WEBFLOW_TO_LANDFLOW.md`

Defines:

- Webflow product/UX concepts used as reference;
- Landflow adaptations;
- MVP/later boundaries;
- what must not be copied blindly.

---

# 15. Architecture Documents Completed

Current completed architecture documents:

- `docs/architecture/ARCHITECTURE.md`
- `docs/architecture/DATABASE.md`
- `docs/architecture/TENANCY.md`
- `docs/architecture/PERMISSIONS.md`
- `docs/architecture/AUTOMOTIVE_DATA.md`
- `docs/architecture/BLOCK_SYSTEM.md`
- `docs/architecture/FORMS_AND_INTEGRATIONS.md`
- `docs/architecture/PUBLISHING.md`
- `docs/architecture/SECURITY.md`

These documents form the current architecture baseline.

---

# 16. Automation Documents Completed

Current completed automation documents:

- `docs/automation/DEFINITION_OF_DONE.md`

This defines:

- DONE
- PARTIAL
- BLOCKED_DECISION
- BLOCKED_EXTERNAL
- mandatory quality gates
- No Fake Success
- test/review expectations

Also present: `MASTER_PLAN.md`, `BACKLOG.md`, `DECISIONS.md`, `PROJECT_STATE.md`, `QUALITY_COMMANDS.md` (P0-022) and `AUTONOMOUS_WORKFLOW.md` (P0-026, the Orchestrator protocol).

---

# 17. Core Approved Ownership Model

Approved:

```text
User
  ↕ membership
Workspace
  └── Sites
```

Workspace is the tenant.

User is identity.

Site belongs to Workspace.

Do not create direct User → Site ownership as the primary business model.

---

# 18. Global vs Workspace vs Site Scope

Approved scopes:

## Global

- Global Automotive Catalog
- official Templates
- official Block Definitions
- Marketplace listings
- platform plans/features

## Workspace

- members
- Integration Profiles
- Workspace Assets
- Workspace Vehicle Library

## Site

- Pages
- Block Instances
- Site Vehicles
- Site Offers
- Popups
- Forms
- SEO
- domains
- publishing state

---

# 19. Automotive Domain Decisions

Approved hierarchy:

Make  
→ Model  
→ Series (optional)  
→ Generation  
→ Modification  
→ Trim / Configuration

Global Catalog:

- platform-owned;
- read/import only for customers.

Customer changes:

- never update Global Catalog.

Commercial prices:

- belong to Site Offer.

Automotive colors:

- may be multi-tone.

Images:

- may be color-specific;
- may have transparent background.

---

# 20. Automotive Data Layers

Approved:

```text
Global Automotive Catalog
        ↓
Workspace Vehicle Library
        ↓
Site Vehicle / Site Offer
```

Direct Global Catalog → Site import may also be supported.

Workspace Vehicle Library exists for reusable customer-prepared content.

Site Offer owns commercial values.

---

# 21. Site-to-Site Copy Semantics

Approved:

Default behavior is **copy**, not synchronization.

Example:

Site A price copied to Site B.

Later Site A price changes.

Site B remains unchanged.

Future synchronization, if ever added, must be explicit.

---

# 22. Block System Decisions

Approved:

```text
Block Definition
→ Block Version
→ Block Schema + Renderer
→ Block Instance
```

Block Schema is deterministic runtime source of truth.

Developer Blocks do not receive arbitrary database access.

AI may propose Schema during authoring, but AI is not required at runtime.

---

# 23. Block Schema Decisions

Approved first-class concepts include:

- text
- textarea
- richtext
- number
- price
- boolean
- select
- multiselect
- color
- image
- gallery
- icon
- link
- group
- repeater

Automotive bindings:

- vehicle
- model
- trim
- color
- characteristics
- options

---

# 24. Platform Capability Decisions

Approved platform abstractions:

- Action System
- Popup Engine
- Form System
- Carousel Engine
- Gallery
- Lightbox
- Analytics Layer

Architecture must not be tied permanently to:

- Swiper
- Fancybox

Specific libraries are implementation decisions later.

---

# 25. Form Architecture Decisions

Approved:

Popup ≠ Form.

Form submission flow:

```text
Validate
→ Anti-Spam
→ Persist Submission
→ Create Delivery
→ Queue
→ External destinations
```

A valid lead is stored before external CRM/API delivery.

---

# 26. Integration Decisions

Approved:

Workspace owns reusable Integration Profiles.

Site owns local overrides.

Example:

Workspace Profile:
- Base URL
- token

Site override:
- `site_id`
- `dealer_id`
- `source_id`

Do not duplicate shared credentials per Site unnecessarily.

---

# 27. Russian Service Decisions

Approved primary integrations:

- Yandex SmartCaptcha
- Yandex Metrica

At implementation time, current official Yandex documentation must be verified.

Do not hardcode provider behavior from stale assumptions.

---

# 28. Publishing Decisions

Approved:

```text
Draft
→ Preview
→ Publish
→ Published Version
```

Autosave:

- Draft only.

Production:

- changes only after explicit Publish.

Publish failure:

- current production remains active.

---

# 29. Domain Decisions

Approved concept:

Every eligible Site can have:

`*.landflow.me`

Paid capability may add:

- custom domain;
- SSL;
- primary domain;
- redirects.

Custom domain belongs to Site.

---

# 30. Versioning Decisions

Architecture must support:

- Published Versions;
- publication history;
- restore;
- future rollback.

Block Versions are pinned.

Template updates do not silently change instantiated Site.

---

# 31. Security Decisions

Approved:

- backend authoritative;
- Workspace isolation mandatory;
- secrets server-side;
- RichText sanitized;
- uploads validated;
- public input untrusted;
- custom integration URLs require SSRF-aware policy;
- Developer Blocks use allowlisted capabilities;
- Super Admin uses explicit platform permissions.

---

# 32. Permission Decisions

Important distinct permissions include:

- `edit_design`
- `edit_content`
- `edit_vehicles`
- `edit_prices`
- `edit_forms`
- `manage_integrations`
- `view_submissions`
- `export_submissions`
- `manage_domains`
- `publish_site`

Editing does not imply publishing.

Design editing does not imply price access.

---

# 33. Entitlement Decisions

Permissions and entitlements are separate.

Potential entitlement keys:

- max_sites
- max_members
- custom_domain
- remove_branding
- advanced_seo
- version_history
- workspace_vehicle_library
- site_vehicle_import
- developer_access

Do not scatter `if plan == ...` conditions.

---

# 34. Plan Direction

Conceptual plans:

## Free

- personal Workspace;
- limited Sites;
- Landflow subdomain;
- Landflow branding.

## Pro

- more Sites;
- custom domain;
- remove branding;
- advanced features.

## Team

- members;
- Site-specific access;
- shared resources;
- Workspace Vehicle Library;
- richer history.

Exact limits are not yet final and must remain configurable.

---

# 35. Developer Platform Direction

Future:

Developer Dashboard
→ My Templates
→ My Blocks
→ Assets
→ Testing
→ Submit for Review
→ Marketplace

Do not implement Developer Platform before core Block runtime is stable.

---

# 36. Marketplace Direction

Future initial product types:

- Templates
- Blocks

Marketplace requires:

- versioning;
- review;
- security;
- compatibility;
- licensing;
- later payments/earnings.

Not MVP.

---

# 37. Explicit Scope Exclusions

Landflow is not currently intended to include:

- telephony;
- call center;
- employee task management;
- internal team chat;
- full warehouse CRM;
- sales pipeline CRM.

Lead capture and integration delivery are valid.

Do not grow unrelated CRM features silently.

---

# 38. Current Implementation Status

Current project should be treated as:

**Architecture documented, implementation not started for core Landflow domains.**

Do not assume these exist until repository inspection proves otherwise:

- Workspace tables;
- Site tables;
- Designer;
- Catalog;
- Forms;
- Integrations;
- Publishing engine.

Architecture docs describe target state, not current migration state.

---

# 39. Current Automation Status

Completed:

- Definition of Done documented.
- `MASTER_PLAN.md`, `BACKLOG.md`, `DECISIONS.md` present.
- Cursor Rules (P0-005…P0-014) present.
- Cursor Agents (P0-015…P0-021) present.
- Application foundation (P0-021A).
- Git repository initialization (P0-021B).
- Russian foundation UI (P0-021C).
- Canonical quality commands (P0-022).
- Playwright E2E framework (P0-023).
- Browser QA baseline (P0-024).

- CI pipeline (P0-025): configured and verified on GitHub Actions.
- Autonomous task workflow (P0-026): `docs/automation/AUTONOMOUS_WORKFLOW.md` + `.cursor/agents/orchestrator.md`.
- Phase 0 validation (P0-027): Phase 0 gate passed.

Phase 0 validation (P0-027):

```text
Phase 0:                          COMPLETED
Foundation:                       VALIDATED
Architecture validation:          PASS
Security validation:              PASS
QA validation:                    PASS
Autonomous workflow validation:   PASS (scenarios A–E; first real run of the protocol)
Canonical quality:                PASS (composer quality)
Browser QA:                       PASS (npm run test:e2e)
GitHub CI:                        PASS (run 36583494996, commit 0b69c6d)
CI runner:                        ubuntu-24.04
```

Autonomous workflow (P0-026):

```text
Autonomous workflow:              CONFIGURED (docs/automation/AUTONOMOUS_WORKFLOW.md, normative protocol)
Orchestrator agent:               AVAILABLE (.cursor/agents/orchestrator.md, coordinator only)
Single-task mode:                 AVAILABLE (default)
Continuous mode:                  AVAILABLE (explicit owner request; stops before Phase Review tasks)
Specialist routing:               CONFIGURED (architect, backend, frontend, ui-reviewer, qa, security, reviewer)
Review routing:                   CONFIGURED (security / UI / QA triggers, final Reviewer for every task)
Quality routing:                  CONFIGURED (canonical commands per task type, gates sequential)
Human approval boundaries:        DOCUMENTED (commit/push only with explicit per-task authorization; force push forbidden)
Phase boundary enforcement:       CONFIGURED (phase ends only through its Phase Review task)
Workflow engine/packages:         none (repository-driven protocol, no parser/daemon/queue/database)
Custom Cursor commands/workflows: not used (owner starts the workflow with the plain-text commands in AUTONOMOUS_WORKFLOW.md §21)
```

- Dry run on the actual repository state (independent read-only subagent): current phase Phase 0; next ready task after P0-026 is `P0-027 — Phase 0 Validation` (primary qa; reviews architect, security, qa, final reviewer, plus ui-reviewer because Phase 0 changed user-visible UI in P0-021C/P0-024; gates `composer quality` → `npm run test:e2e` + repository/documentation consistency check); Phase 1 task selected: NO.
- Look-ahead recorded by the dry run: D-085 "Primary Identifier Strategy" (`ADR_REQUIRED`) will be flagged as a potential blocker for `P1-003 — Create Workspace Schema`. P0-027 added the resolving task `X-007` (trigger before P1-003); see §46.

Still needed:

- none for Phase 0; follow-ups `X-011` and `X-012` are DONE.

---

# 40. Current Check Availability

Verified after P0-021A (tools installed and executed successfully on the starter-kit baseline):

- PHPUnit 12.5.36: AVAILABLE — `php artisan test` PASS (40 tests, 138 assertions)
- Laravel Pint 1.32.1: AVAILABLE — `pint --test` PASS
- Larastan 3.12.2 / PHPStan 2.2.16 (level 7): AVAILABLE — `phpstan analyse` PASS (0 errors)
- vite-plus check: AVAILABLE — `npm run check` PASS (format + type-aware lint)
- TypeScript: AVAILABLE — `npm run types:check` (`tsc --noEmit`) PASS (script removed in P0-022; type checking now runs inside `npm run check`)
- Vite production build: AVAILABLE — `npm run build` PASS

Re-verified after P0-021C: PHPUnit PASS (45 tests, incl. `LocalizationTest`), Pint PASS, Larastan PASS, `npm run check` PASS, `npm run build` PASS.

Canonical quality commands (P0-022) — contract: `docs/automation/QUALITY_COMMANDS.md`:

```text
Canonical quality commands:  CONFIGURED

composer test          AVAILABLE  PHPUnit only (config:clear + php artisan test)
composer analyse       AVAILABLE  Larastan/PHPStan only (phpstan analyse --memory-limit=1G)
composer format        AVAILABLE  Pint, modifies files (pint --parallel)
composer format:check  AVAILABLE  Pint --test, no file changes
npm run check          AVAILABLE  vite-plus: format + type-aware lint + TypeScript type check
npm run build          AVAILABLE  production Vite build (vp build)
composer quality       AVAILABLE  sequential: test → analyse → format:check → wayfinder:generate → npm run check → npm run build; stops on first failure
npm run test:e2e       AVAILABLE  Playwright browser E2E (separate gate, not part of composer quality)
```

Browser E2E (P0-023):

```text
Playwright:                 AVAILABLE (@playwright/test 1.63.0)
Browser E2E framework:      Playwright
Browser installed:          Chromium (headless shell only; no Firefox/WebKit)
Canonical E2E command:      npm run test:e2e
Test directory:             tests/browser/ (separate from PHPUnit tests/)
E2E environment:            APP_ENV=e2e, .env.e2e generated per run from .env.e2e.example (git-ignored, fresh APP_KEY)
E2E database:               file SQLite database/e2e.sqlite, recreated + migrated per run (git-ignored)
E2E server:                 dedicated php artisan serve on http://127.0.0.1:8200 (readiness /up), production build
E2E test data:              database/seeders/E2eSeeder.php (e2e environment only): member@landflow.test, login@landflow.test
Browser QA baseline:        DONE (P0-024)
Browser automated QA:       CONFIGURED
```

Browser QA baseline (P0-024):

```text
Projects:        setup (UI login → playwright/.auth/member.json, git-ignored)
                 desktop 1440×1000 — full suite
                 tablet 1024×1366, mobile 390×844 — tests tagged @responsive
Flows:           landing, auth (login page, guest redirect, wrong password, keyboard login, logout),
                 dashboard (app shell, mobile sidebar sheet, user menu, collapsed sidebar persistence),
                 settings (profile, navigation, security password confirmation, Russian validation)
Checks per test: console errors, page errors, 4xx/5xx responses; @responsive → no horizontal overflow
Screenshots:     test-results/screenshots/<area>/<name>--<project>.png (cleared per run, attached to HTML report)
Visual baselines: none (screenshots are review evidence, not pixel baselines)
```

CI (P0-025):

```text
CI pipeline:                    CONFIGURED (.github/workflows/ci.yml, «Landflow CI»)
CI GitHub verification:         PASS (run 36577884025, commit fe864ee)
GitHub Actions platform:        Ubuntu
CI runner:                      ubuntu-24.04 (pinned; verified in run 36579169216, commit e6dc967)
Canonical quality gate:         composer quality
Browser E2E gate:               npm run test:e2e
Chromium CI:                    VERIFIED (npx playwright install --with-deps chromium)
CI database:                    SQLite test environments (PHPUnit :memory:, E2E database/e2e.sqlite); no DB service
CI secrets:                     none required
CI triggers:                    push to main, pull_request into main
CI failure artifacts:           test-results/, playwright-report/, storage/logs/ — only on E2E failure, 7 days
Production deployment:          NOT_CONFIGURED
```

- Latest verified run before the Phase 0 gate: [36583494996](https://github.com/mddevops/landauto/actions/runs/36583494996) on `0b69c6d` (P0-026) — success on `ubuntu-24.04`: `composer quality` then `npm run test:e2e` (47 PHPUnit tests, 23 E2E tests); confirmed from the run log in P0-027.
- External build dependency: `vite.config.ts` loads the Instrument Sans font through `laravel-vite-plugin` `bunny(...)`, which downloads it from `https://fonts.bunny.net` at build time (cached locally in `node_modules/.cache/laravel-vite-plugin/fonts`). CI starts with an empty cache, so `npm run build` / `composer quality` in CI needs that CDN reachable. At runtime the fonts are served from `public/build`.
- GitHub verification (P0-025): first real run [36577884025](https://github.com/mddevops/landauto/actions/runs/36577884025) succeeded — PHPUnit 47 passed, Larastan no errors, Pint PASS, Wayfinder generated on the clean checkout, `vp check` (incl. TypeScript) PASS, one production build, Chromium installed with system dependencies, E2E 23 passed on `php artisan serve` + `database/e2e.sqlite`.

Notes:

- All gates verified after P0-022: each command PASS with exit code 0; negative probes confirmed non-zero exit for `composer test`, `composer format:check` and `composer quality` (the aggregate stops at the first failing gate).
- TypeScript type checking is part of `npm run check` via vite-plus `lint.options.typeCheck: true` (verified: a deliberate TS2322 error fails `npm run check`). The separate npm `types:check` (`tsc --noEmit`) script was removed as a duplicate.
- Removed duplicate Composer scripts: `lint`, `lint:check`, `types:check`. `ci:check` was kept as a deprecated alias of `composer quality` for the starter-kit workflow and removed in P0-025.
- P0-025: `composer quality` generates the git-ignored Wayfinder route helpers (`php artisan wayfinder:generate --with-form`) before `npm run check`, so it passes on a clean checkout (before, only a previous build created them and a fresh clone failed with TS2307). `E2E_REUSE_BUILD=1` (set only in CI) makes the E2E web server reuse the build `composer quality` just made; locally it always rebuilds.
- Verified after P0-025: CI steps replayed on a clean copy of the tracked files — `composer install`, `npm ci`, `.env` + key, `composer quality` PASS, `npm run test:e2e` (`E2E_REUSE_BUILD=1`) PASS, 23 passed, build reused. Workspace: `composer quality` PASS, `npm run test:e2e` PASS (23 passed).
- Developer helpers that are not gates: `npm run check:fix` (vite-plus auto-fix), `composer format`.
- Gates must run sequentially, never in parallel (`composer quality` enforces this).
- `npm run test:e2e` runs after `composer quality`, never concurrently: its web server runs its own `npm run build` into `public/build`.
- E2E verified after P0-023: `composer quality` PASS, then `npm run test:e2e` PASS (2 smoke tests: `/`, `/login`). A temporary negative probe (`console.error` + 404 page) failed as expected with failure screenshot and trace, then was removed.
- Every browser test fails on console errors, uncaught page errors and 4xx/5xx responses (`tests/browser/support/fixtures.ts`); no global filtering.
- `reuseExistingServer` is disabled: a running server cannot be proven to be the E2E environment. Port 8200 must be free. `prepare-e2e.mjs` refuses to run when configuration is cached (`bootstrap/cache/config.php`) or `public/hot` exists (Vite dev server running).
- Verified after P0-024: `composer quality` PASS (47 PHPUnit tests), then `npm run test:e2e` PASS (23 passed: 1 setup, 14 desktop, 4 tablet, 4 mobile). The development MySQL database `landauto` was unchanged by both runs (table count, row counts and table creation times compared before/after).
- Screenshots reviewed after P0-024: landing, login, invalid login, dashboard (desktop/tablet/mobile, mobile sidebar sheet), profile (desktop/tablet/mobile), security. No overflow; layouts stack on mobile as intended; the long Russian user name truncates with an ellipsis in the sidebar.
- Login rate limit (5/min per email + IP) is why login flows use their own test user; new tests that log in via the UI should not reuse `member`.

Not yet confirmed/installed as project quality gate:

- browser screenshot regression automation: NOT_AVAILABLE_YET
- full CI pipeline: AVAILABLE (P0-025, verified on GitHub Actions). Report CI results only from real runs.

Agents must not claim unavailable checks as PASS.

---

# 41. Next Planned Automation Documents

All planned Phase 0 automation documents exist: `MASTER_PLAN.md`, `BACKLOG.md`, `DECISIONS.md`, Cursor rules, Cursor agents, testing/browser QA setup (`QUALITY_COMMANDS.md`, Playwright), CI and the autonomous orchestrator workflow (`AUTONOMOUS_WORKFLOW.md`). Their completeness is verified by `P0-027 — Phase 0 Validation`.

---

# 42. Current Next Approved Task

Last completed task: `X-020 — Admin Permission Matrix Reconciliation` (DONE). Phase 3 gate `P3-017` is DONE. Phase 2 gate `P2-018` is DONE. Phase 1 gate `P1-017` is DONE. Phase 0 gate `P0-027` is DONE.

Resolved owner decision: D-100 (X-015 DONE) — every new personal Workspace gets the active system Free plan (`max_sites = 2`); Workspaces created before X-015 are not backfilled.

Also done: `X-007 — ADR: Primary Identifier Strategy` (D-085 APPROVED, ADR-001 Option B).

Also done: `P1-003 — Create Workspace Schema`; `P1-004 — Workspace Domain Models`; `P1-005 — Create Default Personal Workspace`; `X-014 — Decision: OAuth Account Linking and Yandex Client` (ADR-002); `P1-005A — Yandex OAuth Authentication`; `X-011 — Foundation Hygiene Follow-ups`; `P1-006 — Workspace Context / Switcher Backend`; `P1-007 — Workspace Switcher UI`; `P1-008 — Permission Foundation`; `P1-009 — Entitlement Foundation`; `P1-010 — Site Schema`; `P1-011 — Site Domain Models and Policies`; `P1-012 — Template Foundation`; `P1-013 — Create Site Flow Backend`; `X-012 — Foundation UI Follow-ups`; `P1-014 — Dashboard UI`; `P1-015 — Create Site Wizard UI`; `P1-016 — Core Platform E2E`; `P1-017 — Phase 1 Review`; `P2-001 — Page Schema and Models`; `P2-002 — Block Definition / Version Schema`; `P2-003 — Block Schema Validator`; `P2-004 — Block Instance Schema`; `P2-005 — Initial Official Blocks`; `P2-006 — Designer Shell`; `X-015 — Default Free Plan for New Workspaces`.

**Next: `P9-002 — Developer Permissions`** (Phase 9 — Developer Platform IN_PROGRESS: P9-001 DONE; Phases 0–8 COMPLETED, P7-009 DEFERRED) per `BACKLOG.md`.

No implementation task should be inferred from this alone.

UI language state (P0-021C):

```text
Product UI language:       Russian
Foundation UI localized:   YES
APP_LOCALE:                ru (fallback_locale: en)
Translations:              lang/ru/*.php + lang/ru.json (standard Laravel localization, no i18n package)
Playwright:                AVAILABLE (P0-023)
```

Foundation cleanup (before P0-023):

- Removed starter-kit external links from the UI: sidebar footer and header "Репозиторий" (`github.com/laravel/react-starter-kit`) and "Документация" (Laravel docs), the unused `nav-footer.tsx` component, and the Laravel/Laracasts promo content of the welcome page. No replacement links were added.
- The welcome page is now a minimal neutral placeholder: app name (Landflow), one-line Russian description, «Войти» / «Регистрация» (or «Панель управления» for signed-in users). A real Landflow landing belongs to a future product task.
- Fallback app name in `resources/js/app.tsx` and `resources/views/app.blade.php` changed from `Laravel` to `Landflow`.
- Laravel logo replaced by the owner-provided Landflow logo (P0-024 final fix): `resources/js/components/app-logo-icon.tsx` (auth layouts, sidebar, header, mobile navigation) and `public/favicon.svg` use a vector trace of the supplied logo image (black isometric parallelograms forming an «L»; the file was supplied as PNG 194×150, the trace matches it on 99.5% of pixels, differences are edge anti-aliasing only). `public/favicon.ico` (16/32 px) and `public/apple-touch-icon.png` (180 px) are rasterized from the same vector. No user-facing Laravel branding remains; «Laravel» appears only in technical code (package imports, generated action paths, `public/index.php`).
- Removed the non-functional starter-kit search button (and its unused `Search` icon import) from `resources/js/components/app-header.tsx` (header-style app layout). Search is not a Landflow feature at this phase; no replacement was added.

Known gaps:

- RESOLVED (2026-09-29) — Local development database: the developer `.env` now points to the working MySQL database `landauto` (previously a non-existent `autoland`). See "Actual local development environment" above.
- RESOLVED (P0-025) — starter-kit workflow replaced by `.github/workflows/ci.yml` (canonical commands, single build, E2E included, `ci:check` removed); `.github/dependabot.yml` kept for weekly action updates. Original note: Starter kit ships `.github/workflows/tests.yml` (`composer setup` + `composer ci:check`) and `.github/dependabot.yml`. After P0-022 `composer ci:check` is a deprecated alias of `composer quality`, so the workflow still resolves; it now also runs Larastan, Pint and a second production build (`composer setup` already builds). P0-025 should call canonical commands directly (`composer quality`), drop the `ci:check` alias and the duplicate build, and add `npm run test:e2e` (CI must install Chromium via `npx playwright install --with-deps chromium`; it does not depend on a developer server).
- Commits: baseline `fdcf597`, P0-021C `a96f6ee`, `3070ef7` (P0-022, foundation cleanup, P0-023, development-environment record, P0-024) `fe864ee` (P0-025 CI), `7d12cfd` (P0-025 docs) and `e6dc967` (runner pinned to Ubuntu 24.04) are pushed to `origin/main`, followed by the runner-pin documentation commit `412e85c` and the P0-026 commit `chore: establish Landflow autonomous workflow`. Commits/pushes require explicit authorization.
- RESOLVED (e6dc967) — `ubuntu-latest` migration risk: GitHub announced the move of `ubuntu-latest` to Ubuntu 26 from 2026-10-19. The CI runner is now pinned to `ubuntu-24.04`, the environment CI was verified on (run [36579169216](https://github.com/mddevops/landauto/actions/runs/36579169216) PASS). Moving to a newer runner is a deliberate future change, not an automatic one.

Resolved:

- RESOLVED (P0-022) — PHPUnit dependency on `public/build`: `tests/TestCase.php` calls Laravel's built-in `$this->withoutVite()` in `setUp()`, so `@vite` renders nothing in tests and no Vite manifest is needed; `@fonts` renders nothing when no build exists. Verified: `public/build` absent → `composer test` PASS (45 tests); then `npm run build` PASS separately. Tests that need real Vite output can opt in with `$this->withVite()`. Residual: `withoutVite()` does not stub `@fonts`, so running `npm run build` concurrently with PHPUnit (half-written `public/build`) is still unsupported — gates run sequentially.
- RESOLVED (P0-022) — Composer package name changed from `laravel/react-starter-kit` to `mddevops/landauto` (description: Landflow). Product name remains Landflow.
- RESOLVED (P0-022) — `laravel/chisel` moved from `require` to `require-dev`: it is the Laravel installer's scaffolding toolkit (removes unwanted starter-kit code at project creation), has no service provider and no references in application code. Version unchanged (v0.1.1); lock diff limited to its section move and `content-hash` (which was already stale from the installer).

---

# 43. Master Plan Purpose

`MASTER_PLAN.md` should define:

- full delivery phases;
- dependency order;
- architecture gates;
- automation setup;
- product implementation stages;
- when tests/browser automation are introduced;
- when agents are allowed to work autonomously.

It should convert product phases into engineering execution order.

---

# 44. Backlog Purpose

`BACKLOG.md` should later convert Master Plan into actionable tasks.

Each task should contain:

- ID;
- title;
- phase;
- dependencies;
- scope;
- acceptance criteria;
- relevant architecture docs;
- Definition of Done checks;
- status.

---

# 45. Decision Log Purpose

`DECISIONS.md` should track lightweight pending/approved project decisions.

Major technical decisions still receive dedicated ADR files.

Examples:

- package manager = npm;
- tests = PHPUnit;
- browser tests = Playwright;
- no Pest;
- no Dusk;
- Workspace is tenant.

---

# 46. Known Major Decisions Still Open

The following important decisions are not finalized for implementation yet:

## Public rendering engine

Options may include:

- Laravel/server rendering;
- React SSR;
- generated/static;
- hybrid.

Requires ADR before publishing engine implementation.

## Published snapshot format

Needs ADR.

## Queue backend at scale

Database initially approved; Redis later if justified.

## Marketplace execution/sandbox model

Future ADR.

## Billing provider

Not chosen.

## Object storage provider

Not chosen.

## Primary identifier strategy (D-085)

APPROVED 2026-09-30 (`X-007` DONE, `docs/architecture/decisions/ADR-001-primary-identifier-strategy.md`, Option B): bigint `id()` + `foreignId` on all tables; externally addressed entities add a unique ULID `public_id` (`HasUlids` + `uniqueIds(): ['public_id']`), routes bind by it and internal IDs are never exposed; internal-only tables have no `public_id`; users have none for now. Rule: `50-database.mdc`.

## Money storage (D-084), characteristic values (D-086), media ownership / asset versioning (D-087, D-075)

`ADR_REQUIRED` / `OPEN`. Resolved through `X-008` (before P3-009), `X-009` (before P3-001), `X-010` (before P2-013). Not affecting Phase 1 as long as P1-009 stores no money columns.

None of these block the current Phase 1 position (D-085 is approved). Full register: `DECISIONS.md` (audit in P0-027 `### Result`).

Known pre-production blockers (no deployment task exists yet): D-094 (personal data retention, `ADR_REQUIRED`) and `X-013 — Production Security Hardening Baseline` (from P1-001).

---

# 47. Package Policy

Cursor agents may not install packages unless:

- task explicitly authorizes;
- package is necessary;
- architecture permits;
- alternatives are evaluated.

No random package installation during Phase 0.

---

# 48. Architecture Change Policy

If implementation later conflicts with architecture:

Agent must not silently rewrite system.

Use:

`BLOCKED_DECISION`

or create approved ADR/update docs before implementation.

---

# 49. Required Reading for Agents

Before broad implementation work, agents should read:

1. `docs/product/PRODUCT.md`
2. `docs/product/WEBFLOW_TO_LANDFLOW.md`
3. `docs/architecture/ARCHITECTURE.md`
4. relevant domain architecture files
5. `docs/automation/DEFINITION_OF_DONE.md`
6. `docs/automation/PROJECT_STATE.md`
7. current task specification

Do not load every document mechanically for every tiny task if a focused subset is sufficient.

---

# 50. Required Reading by Domain

## Workspace / Site

Read:

- ARCHITECTURE
- DATABASE
- TENANCY
- PERMISSIONS

## Automotive

Read:

- AUTOMOTIVE_DATA
- DATABASE
- TENANCY
- PERMISSIONS

## Designer / Blocks

Read:

- BLOCK_SYSTEM
- PUBLISHING
- SECURITY

## Forms / Integrations

Read:

- FORMS_AND_INTEGRATIONS
- SECURITY
- TENANCY
- PERMISSIONS

## Publishing

Read:

- PUBLISHING
- SECURITY
- BLOCK_SYSTEM
- AUTOMOTIVE_DATA

---

# 51. Agent State Update Rule

After a task changes real project state, update this file.

Examples:

Workspace migrations added:
→ update implementation status.

Playwright installed:
→ change availability.

Phase 1 completed:
→ update current phase.

Do not rewrite historical architecture decisions here.

---

# 52. What PROJECT_STATE Must Not Become

Do not turn this file into:

- giant changelog;
- duplicated PRODUCT.md;
- full task backlog;
- implementation tutorial.

It should remain concise enough for an agent to understand project state quickly.

---

# 53. Status Vocabulary

This vocabulary describes capabilities and facts in this file. Backlog task statuses are defined in `BACKLOG.md` §1 and `AUTONOMOUS_WORKFLOW.md` §4; phase statuses in §54 also use `COMPLETED` (MASTER_PLAN §3).

Use:

- NOT_STARTED
- IN_PROGRESS
- IMPLEMENTED
- VERIFIED
- BLOCKED_DECISION
- BLOCKED_EXTERNAL
- DEFERRED

Do not use ambiguous status such as:

- probably done;
- mostly okay.

---

# 54. Phase Status

Current:

```text
Phase 0 — Foundation / Architecture / Automation: COMPLETED
Phase 1 — Core Platform: COMPLETED (gate P1-017)
Phase 2 — Designer Foundation: COMPLETED (gate P2-018)
Phase 3 — Automotive Foundation: COMPLETED (gate P3-017)
Phase 4 — Forms & Interactive Components: COMPLETED
Phase 5 — Publishing: COMPLETED (gate P5-012)
Phase 6 — Integrations & Analytics: COMPLETED (gate P6-015)
Phase 7 — Paid Features: COMPLETED for planned scope (P7-008 ADR-007; P7-009 DEFERRED)
Phase 8 — Team: COMPLETED
Phase 9 — Developer Platform: IN_PROGRESS
Phase 10 — Marketplace: NOT_STARTED
Phase 11 — External Data Sources: NOT_STARTED
```

---

# 55. Phase 0 Completion Requirements

Phase 0 is complete only after at least:

- product docs finalized;
- architecture docs finalized;
- Master Plan created;
- Backlog created;
- Decision log created;
- Cursor rules created;
- core agents created;
- Definition of Done active;
- test commands standardized;
- Playwright/browser QA established;
- CI established;
- autonomous task workflow established.

---

# 56. Do Not Start Phase 1 Prematurely

Core feature implementation should begin only after Phase 0 is sufficiently operational.

Reason:

The goal is for Cursor to work autonomously with strong constraints.

Starting feature code before rules/backlog/testing are ready creates rework.

---

# 57. Git Baseline

Repository (P0-021B):

```text
GitHub:                          mddevops/landauto
origin:                          https://github.com/mddevops/landauto.git
branch:                          main
repository initialized:          YES
old mddevops/landflow repository: NOT_USED
```

The current `landauto` working copy is the only source of truth for the project.

The old repository `mddevops/landflow` does not belong to this project: do not use it, compare code with it, or import its history.

Verified ignore rules (`git check-ignore`):

- tracked: `.cursor/`, `docs/`, `.env.example`, application source and configuration;
- ignored: `.env`, `vendor/`, `node_modules/`, `.idea/`, `public/build/`, `database/*.sqlite*`, `storage/logs/*`.

No force push. Commits/pushes are performed only when workflow explicitly authorizes them (agent-workflow rule §41).

Agents should verify current repository state before making implementation changes.

---

# 58. Current Architecture Directory

Expected:

```text
docs/
├── product/
│   ├── PRODUCT.md
│   └── WEBFLOW_TO_LANDFLOW.md
├── architecture/
│   ├── ARCHITECTURE.md
│   ├── DATABASE.md
│   ├── TENANCY.md
│   ├── PERMISSIONS.md
│   ├── AUTOMOTIVE_DATA.md
│   ├── BLOCK_SYSTEM.md
│   ├── FORMS_AND_INTEGRATIONS.md
│   ├── PUBLISHING.md
│   └── SECURITY.md
└── automation/
    ├── AUTONOMOUS_WORKFLOW.md
    ├── BACKLOG.md
    ├── DECISIONS.md
    ├── DEFINITION_OF_DONE.md
    ├── MASTER_PLAN.md
    ├── PROJECT_STATE.md
    └── QUALITY_COMMANDS.md
```

---

# 59. Cursor Structure

Present:

```text
.cursor/
├── rules/
│   ├── 00-project-core.mdc
│   ├── 10-architecture.mdc
│   ├── 20-laravel.mdc
│   ├── 30-react-inertia.mdc
│   ├── 40-ui-shadcn.mdc
│   ├── 50-database.mdc
│   ├── 60-testing.mdc
│   ├── 70-security.mdc
│   ├── 80-browser-qa.mdc
│   └── 90-agent-workflow.mdc
└── agents/
    ├── architect.md
    ├── backend.md
    ├── frontend.md
    ├── ui-reviewer.md
    ├── qa.md
    ├── security.md
    ├── reviewer.md
    └── orchestrator.md   (P0-026, coordinator)
```

No `.cursor/commands/` or `.cursor/workflows/`: the workflow is started with the owner commands in `AUTONOMOUS_WORKFLOW.md` §21.

---

# 60. Autonomous Workflow Goal

Workflow (implemented by P0-026, protocol `AUTONOMOUS_WORKFLOW.md`):

```text
Product Docs
→ Master Plan
→ Backlog
→ Orchestrator
→ Specialist Agent
→ Tests/Checks
→ Fix Loop
→ Reviews + final Reviewer
→ BACKLOG / PROJECT_STATE update
→ Commit (only with explicit owner authorization for the task)
→ Next Task (SINGLE TASK MODE stops here)
```

Task status is determined by quality gates and independent reviews, not agent confidence.

---

# 61. Autonomous Agent Constraint

An autonomous agent may make routine implementation decisions within approved architecture.

It may not independently redefine:

- tenant model;
- ownership model;
- publishing semantics;
- Global Catalog ownership;
- Marketplace security model;
- package policy;
- major data architecture.

Use `BLOCKED_DECISION` when needed.

---

# 62. Human Decision Boundary

Human input remains required for important product/business choices such as:

- pricing/plan limits;
- billing provider;
- legal/privacy text;
- production credentials;
- DNS/account ownership;
- final visual approval where subjective;
- major architecture alternatives when no approved direction exists.

Automation should minimize interruptions but not fabricate these decisions.

---

# 63. Current Product Definition

Landflow should be understood as:

> A Webflow-inspired SaaS for creating, configuring and publishing automotive websites using reusable templates, schema-driven Blocks, structured automotive data, Site-specific commercial offers, centralized Forms/Integrations, and a future developer Marketplace.

---

# 64. Current Customer Workflow

```text
Workspace
→ Create Site
→ Choose Template
→ Designer
→ Import/Configure Vehicles
→ Set Prices/Benefits
→ Configure Forms/Integrations
→ Preview
→ Publish
```

---

# 65. Current Developer Workflow

Future:

```text
Developer
→ Create Block/Template
→ Define or Confirm Schema
→ Test
→ Submit
→ Landflow Review
→ Publish
→ Marketplace
```

---

# 66. Current Admin Workflow

Future:

```text
Super Admin
→ Manage Platform
→ Maintain Global Automotive Catalog
→ Moderate Developer Content
→ Manage Plans/Features
```

---

# 67. Non-Negotiable Architecture Summary

Agents must preserve:

1. Workspace is tenant.
2. Global Catalog is platform-owned.
3. Site owns commercial price.
4. Templates do not own customer vehicles.
5. Block Schema is runtime source of truth.
6. Developer Blocks do not query raw DB.
7. Form and Popup are separate.
8. Submission persists before delivery.
9. Credentials live Workspace-side and server-side.
10. Draft is separate from Published.
11. Autosave never publishes.
12. Site-to-Site copy is not automatic sync.
13. Permission and entitlement are separate.
14. Backend authorization is mandatory.
15. Secrets never reach public runtime.

---

# 68. Current Next Step

**`P9-002 — Developer Permissions`**. Phases 0–8 are COMPLETED (Phase 7 for planned scope: P7-001 … P7-008 and P7-010 DONE, P7-009 DEFERRED; Phase 8: P8-001 … P8-011 DONE); Phase 9 is IN_PROGRESS (P9-001 DONE).

---

# 69. Update Protocol

When editing this file later:

- update only facts that changed;
- keep architecture summaries consistent with approved docs;
- distinguish planned vs implemented;
- change check availability only after verification;
- keep next task current;
- do not claim implementation without repository evidence.

---

# 70. Final State Snapshot

As of this document creation:

```text
Product specification:        DOCUMENTED
Webflow mapping:              DOCUMENTED
System architecture:          DOCUMENTED
Database architecture:        DOCUMENTED
Tenancy:                      DOCUMENTED
Permissions:                  DOCUMENTED
Automotive architecture:      DOCUMENTED
Block system:                 DOCUMENTED
Forms/integrations:           DOCUMENTED
Publishing:                   DOCUMENTED
Security:                     DOCUMENTED

Definition of Done:           DOCUMENTED
Project State:                DOCUMENTED

Master Plan:                  PRESENT
Backlog:                      PRESENT
Decision log:                 PRESENT
Cursor rules:                 PRESENT
Cursor agents:                PRESENT
Application foundation:       INSTALLED (P0-021A)
Git repository:               INITIALIZED (P0-021B, mddevops/landauto, main)
Russian foundation UI:        DONE (P0-021C, APP_LOCALE=ru)
Quality command aliases:      CONFIGURED (P0-022, composer quality)
Playwright:                   AVAILABLE (P0-023, Chromium, npm run test:e2e)
Browser QA baseline:          DONE (P0-024, desktop + tablet/mobile smoke)
CI:                           CONFIGURED, GitHub verification PASS (P0-025, Landflow CI, Ubuntu)
Autonomous workflow:          CONFIGURED (P0-026, AUTONOMOUS_WORKFLOW.md + orchestrator agent), validated (P0-027)
Phase 0 validation:           PASS (P0-027: architecture, security, QA, workflow, gates, CI ubuntu-24.04)
Production deployment:        NOT_CONFIGURED

Core Landflow implementation: IN_PROGRESS (Phases 0–8 COMPLETED, Phase 9 IN_PROGRESS; P7-009 DEFERRED; D-093 APPROVED; D-094, X-013, X-017 OPEN)
```

**Current phase: Phase 9 — Developer Platform (IN_PROGRESS; P9-001 DONE; Phases 0–8 COMPLETED, P7-009 DEFERRED).
Next: `P9-002 — Developer Permissions` per `BACKLOG.md`.**
