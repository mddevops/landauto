# Landflow — Decision Log

**Document:** `docs/automation/DECISIONS.md`  
**Status:** Living decision register  
**Purpose:** Record approved lightweight technical/product decisions, distinguish them from unresolved architecture choices, and prevent autonomous agents from silently redefining the system.

---

# 1. How This File Is Used

`DECISIONS.md` is the fast operational decision register for Landflow.

It answers:

- What has already been decided?
- What must agents not reopen casually?
- Which choices are still open?
- Which open choices require a dedicated ADR?
- Which decisions are implementation-level vs architecture-level?

This file does not replace:

- product specs;
- architecture docs;
- ADRs;
- BACKLOG.

---

# 2. Decision Statuses

Use:

- `APPROVED`
- `PROVISIONAL`
- `SUPERSEDED`
- `OPEN`
- `ADR_REQUIRED`

Meaning:

## APPROVED

Decision is active and should be followed.

## PROVISIONAL

Current direction is approved for now but may be revisited when scale/requirements change.

## SUPERSEDED

Historical decision no longer applies.

## OPEN

Decision not yet made, but does not necessarily block current work.

## ADR_REQUIRED

Decision must not be made implicitly inside implementation. A dedicated ADR is required before dependent work starts.

---

# 3. Decision Format

Each decision should contain:

- ID;
- status;
- title;
- decision;
- rationale;
- impact;
- references if useful.

IDs use:

`D-001`, `D-002`, etc.

---

# APPROVED DECISIONS

---

## D-001 — Workspace Is the Tenant

**Status:** APPROVED

### Decision

Workspace is the primary SaaS tenancy and ownership boundary.

User represents identity, not tenant ownership.

Site belongs to Workspace.

### Rationale

Supports:

- personal accounts;
- teams;
- agencies;
- multiple Sites;
- shared assets;
- shared vehicle library;
- shared Integration Profiles.

### Impact

Do not create direct User → Site ownership as the primary model.

### References

- `TENANCY.md`
- `ARCHITECTURE.md`

---

## D-002 — Shared Application / Shared Database Logical Tenancy

**Status:** APPROVED

### Decision

Landflow uses one application and one primary relational database with logical Workspace isolation.

### Rationale

Fits current product scale and modular-monolith architecture.

### Impact

Do not introduce separate databases per tenant unless a future ADR explicitly changes this.

---

## D-003 — Modular Monolith First

**Status:** APPROVED

### Decision

Landflow starts as a modular monolith.

### Rationale

The product has many domains but does not yet justify distributed operational complexity.

### Impact

Do not split into microservices prematurely.

---

## D-004 — Laravel + React + Inertia

**Status:** APPROVED

### Decision

Primary application stack:

- Laravel
- React
- Inertia
- TypeScript

### Impact

Do not introduce Vue or another SPA framework into Landflow without explicit architecture change.

---

## D-005 — shadcn/ui Is the UI Foundation

**Status:** APPROVED

### Decision

Use shadcn/ui primitives/components as the default UI foundation.

### Conventions

- use `@/lib/utils`;
- avoid duplicate `cn`;
- use granular Radix dependencies;
- do not introduce umbrella `radix-ui` package by preference.

---

## D-006 — Tailwind CSS 4

**Status:** APPROVED

### Decision

Use the existing Tailwind CSS 4.x foundation.

### Impact

Do not downgrade or add a competing styling framework.

---

## D-007 — npm Is the Only Package Manager

**Status:** APPROVED

### Decision

Use npm only.

### Impact

Do not add:

- yarn
- pnpm
- bun

without an explicit decision.

---

## D-008 — PHPUnit Is the PHP Test Framework

**Status:** APPROVED

### Decision

Use PHPUnit.

### Rationale

It already exists in the repository.

### Impact

Do not introduce Pest merely for developer preference.

---

## D-009 — Playwright for Browser Testing

**Status:** APPROVED

### Decision

Use Playwright for browser/E2E tests.

### Impact

Do not introduce Laravel Dusk unless a future decision explicitly changes this.

---

## D-010 — No Laravel Dusk

**Status:** APPROVED

### Decision

Laravel Dusk is not part of the planned test stack.

### Rationale

Playwright will handle browser-level testing.

---

## D-011 — No Pest

**Status:** APPROVED

### Decision

Do not introduce Pest.

### Rationale

PHPUnit is already sufficient and avoids unnecessary test-stack duplication.

---

## D-012 — TypeScript Strict Mode Remains Enabled

**Status:** APPROVED

### Decision

Frontend stays in TypeScript strict mode.

### Impact

Do not weaken type checking to ship features.

---

## D-013 — No Standalone ESLint Requirement

**Status:** APPROVED

### Decision

Use the existing vite-plus/check workflow rather than introducing standalone ESLint solely by convention.

### Impact

If future linting needs require a change, record a new decision.

---

## D-014 — No Premature REST API

**Status:** APPROVED

### Decision

Do not create a broad `api.php`/REST layer simply because many Laravel apps have one.

### Rationale

Current authenticated app works naturally through Inertia.

### API should be introduced only when required for:

- public Site runtime;
- public Forms;
- external integrations;
- future Developer API.

---

## D-017 — Global Automotive Catalog Is Platform-Owned

**Status:** APPROVED

### Decision

Global Automotive Catalog is canonical Landflow data.

Customers may read/import allowed data but may not mutate master records.

### Impact

All customer changes belong to Workspace or Site layers.

---

## D-018 — Automotive Hierarchy

**Status:** APPROVED

### Decision

Canonical hierarchy:

Make  
→ Model  
→ Series (optional)  
→ Generation  
→ Modification  
→ Trim / Configuration

### Impact

Do not collapse Modification and Trim casually.

---

## D-019 — Site Owns Commercial Price

**Status:** APPROVED

### Decision

Commercial pricing belongs to Site Offer.

### Examples

- RRP;
- current price;
- benefits;
- availability;
- badge.

### Impact

Do not store dealer/customer commercial prices in Global Catalog.

---

## D-020 — Workspace Vehicle Library Is a Reuse Layer

**Status:** APPROVED

### Decision

Workspace Vehicle Library is a reusable customer-owned preparation layer between Global Catalog and Sites.

### Impact

It may hold:

- preferred images;
- custom descriptions;
- reusable vehicle content.

Site commercial price remains Site-specific.

---

## D-021 — Site-to-Site Vehicle Transfer Uses Copy Semantics

**Status:** APPROVED

### Decision

Default Site-to-Site transfer is copy, not live synchronization.

### Impact

After copy, destination Site owns independent records.

---

## D-022 — No Silent Synchronization

**Status:** APPROVED

### Decision

Landflow must not silently synchronize mutable commercial/customer data between Sites.

### Impact

Future synchronization must be explicit and visible.

---

## D-023 — Automotive Colors May Be Multi-Tone

**Status:** APPROVED

### Decision

A vehicle color is not represented by one mandatory HEX value.

### Impact

Support multiple swatches/layers.

---

## D-024 — Automotive Images May Be Color-Specific

**Status:** APPROVED

### Decision

Vehicle images may belong to specific:

- Trim;
- Color;
- view/angle.

Transparent-background images are first-class.

---

## D-025 — Template Does Not Own Customer Vehicle Data

**Status:** APPROVED

### Decision

Template defines presentation/initial structure.

Template does not own customer vehicles, prices, or commercial offers.

### Impact

Changing Template must not inherently destroy customer automotive data.

---

## D-026 — Site Owns Instantiated Template State

**Status:** APPROVED

### Decision

When Template is used to create a Site, the Site owns its instantiated configuration.

### Impact

Later Template changes do not silently mutate existing Sites.

---

## D-027 — Block Definition / Version / Instance Separation

**Status:** APPROVED

### Decision

Core model:

Block Definition  
→ Block Version  
→ Block Instance

### Impact

Customer editing changes Block Instance, not reusable Definition.

---

## D-028 — Block Schema Is Runtime Source of Truth

**Status:** APPROVED

### Decision

Saved deterministic Block Schema defines editable fields at runtime.

### Impact

Do not make AI inspect arbitrary markup on every render/edit.

---

## D-029 — AI May Assist Block Authoring, Not Runtime

**Status:** APPROVED

### Decision

AI may propose Block Schema during Developer authoring.

Developer confirms it.

Runtime uses saved Schema.

---

## D-030 — Developer Blocks Do Not Query Raw Database

**Status:** APPROVED

### Decision

Developer/Marketplace Blocks consume approved data contexts/bindings.

### Impact

No arbitrary ORM/SQL access from third-party Blocks.

---

## D-031 — Group and Repeater Are First-Class Block Schema Types

**Status:** APPROVED

### Decision

Block Schema must support structured nested Groups and Repeaters.

---

## D-032 — Carousel Is a Platform Capability

**Status:** APPROVED

### Decision

Carousel is a semantic Landflow capability.

### Impact

Block state should not be permanently coupled to one library such as Swiper.

---

## D-033 — Lightbox Is Separate From Popup

**Status:** APPROVED

### Decision

- Gallery = media collection
- Carousel = presentation
- Lightbox = media viewer
- Popup = business/content modal

These remain separate abstractions.

---

## D-034 — Action System Is Centralized

**Status:** APPROVED

### Decision

Common actions are platform-defined.

Initial conceptual actions include:

- open URL;
- open Page;
- scroll;
- open Popup;
- submit Form;
- phone;
- email.

### Impact

Individual Blocks should not invent separate action systems.

---

## D-035 — Popup and Form Are Separate Entities

**Status:** APPROVED

### Decision

Popup handles presentation.

Form handles fields/submission/routing.

### Impact

One Form may be reused in different presentation contexts.

---

## D-036 — Submission Is Persisted Before External Delivery

**Status:** APPROVED

### Decision

Valid Form submission is saved before CRM/API/email delivery.

### Impact

CRM downtime must not lose a lead.

---

## D-037 — Delivery Is Asynchronous

**Status:** APPROVED

### Decision

External Form destinations should be delivered through queue/jobs.

### Impact

Visitor does not wait for CRM response.

---

## D-038 — One Submission Can Have Multiple Delivery Routes

**Status:** APPROVED

### Decision

A Submission may route independently to:

- email;
- CRM;
- webhook/API.

One route failure does not invalidate successful routes.

---

## D-039 — Workspace Owns Integration Profiles

**Status:** APPROVED

### Decision

Reusable shared credentials belong to Workspace Integration Profile.

### Impact

Multiple Sites may reference the same Profile.

---

## D-040 — Site Stores Integration Overrides

**Status:** APPROVED

### Decision

Site-specific values belong to Site Integration Binding.

Examples:

- site_id;
- dealer_id;
- source_id.

### Impact

Do not duplicate shared token for every Site.

---

## D-041 — Secrets Remain Server-Side

**Status:** APPROVED

### Decision

Secrets must not be sent to public/browser runtime unnecessarily.

### Includes

- CRM token;
- API secret;
- CAPTCHA secret;
- passwords;
- private keys.

---

## D-042 — Secrets Are Masked After Save

**Status:** APPROVED

### Decision

UI displays masked secret state and allows replacement.

Do not redisplay stored plaintext secret.

---

## D-043 — Yandex SmartCaptcha Is the Primary CAPTCHA Integration

**Status:** APPROVED

### Decision

Landflow should support Yandex SmartCaptcha for the primary Russian-market workflow.

### Impact

Implementation must verify current official documentation.

---

## D-044 — Yandex Metrica Is the Primary Analytics Integration

**Status:** APPROVED

### Decision

Landflow provides centralized semantic analytics events with Yandex Metrica support.

### Impact

Individual Blocks should not inject their own normal Metrica scripts.

---

## D-045 — Draft / Preview / Published Are Separate

**Status:** APPROVED

### Decision

Core publishing lifecycle:

Draft  
→ Preview  
→ Publish  
→ Published Version

---

## D-046 — Autosave Does Not Publish

**Status:** APPROVED

### Decision

Designer autosave changes Draft only.

### Impact

Production changes only after explicit Publish.

---

## D-047 — Failed Publish Keeps Existing Production

**Status:** APPROVED

### Decision

New publication must be prepared/validated before activation.

If it fails, current Published Version remains active.

---

## D-048 — Block Version Is Pinned

**Status:** APPROVED

### Decision

A Site Block Instance references a specific Block Version.

### Impact

Developer releasing v2 does not silently update Sites on v1.

---

## D-049 — Restore Goes to Draft First

**Status:** APPROVED

### Decision

Normal version restore should restore historical state into Draft, then require explicit Publish.

Emergency direct rollback may be added later as a separate controlled capability.

---

## D-050 — Landflow Subdomain Is a Core Hosting Mode

**Status:** APPROVED

### Decision

Eligible Sites may use:

`*.landflow.me`

### Impact

Free plan may rely on this as primary public hosting.

---

## D-051 — Custom Domain Is a Site Capability

**Status:** APPROVED

### Decision

Custom domain belongs to Site and requires:

- permission;
- entitlement;
- verification.

---

## D-052 — Permission and Entitlement Are Separate

**Status:** APPROVED

### Decision

Permission answers:

"May this User perform the action?"

Entitlement answers:

"Does the Workspace/plan include the capability?"

### Impact

Both may be required.

---

## D-053 — Avoid Hardcoded Plan Checks

**Status:** APPROVED

### Decision

Do not scatter:

`if plan == team`

through product code.

Use entitlement/capability resolution.

---

## D-054 — Publishing Permission Is Separate

**Status:** APPROVED

### Decision

Editing Draft does not automatically grant `publish_site`.

---

## D-055 — Price Editing Permission Is Separate

**Status:** APPROVED

### Decision

`edit_design` and `edit_content` do not imply `edit_prices`.

---

## D-056 — Integration Management Is Separate

**Status:** APPROVED

### Decision

Form editing does not automatically grant Integration credential management.

---

## D-057 — Submission Access Is Separate

**Status:** APPROVED

### Decision

Viewing lead/submission data requires explicit permission.

---

## D-058 — Super Admin Uses Platform Authorization

**Status:** APPROVED

### Decision

Super Admin access must be implemented through explicit platform roles/permissions.

### Impact

Never use:

- user ID == 1;
- hardcoded email;
- disabled tenancy globally.

---

## D-059 — Customer Data Uses Backend Authorization

**Status:** APPROVED

### Decision

Frontend visibility is never the security boundary.

Backend must authorize every protected operation.

---

## D-060 — RichText Is Sanitized

**Status:** APPROVED

### Decision

RichText does not permit unrestricted arbitrary HTML.

---

## D-061 — Uploads Are Untrusted

**Status:** APPROVED

### Decision

Uploads require server-side validation.

### Impact

Do not trust filename extension.

---

## D-062 — Integration HTTP Must Be SSRF-Aware

**Status:** APPROVED

### Decision

Custom Webhook/API destinations require outbound request restrictions.

### Impact

Do not allow arbitrary access to internal/private infrastructure.

---

## D-063 — Published Output Contains No Secrets

**Status:** APPROVED

### Decision

Published snapshots/runtime must not contain:

- Integration secrets;
- Workspace private data;
- Submissions;
- permissions;
- admin metadata.

---

## D-064 — Definition of Done Is Mandatory

**Status:** APPROVED

### Decision

Task completion is governed by `DEFINITION_OF_DONE.md`.

Agent confidence is not sufficient.

---

## D-065 — No Fake Success

**Status:** APPROVED

### Decision

An agent must not claim:

- tests passed;
- browser verified;
- build passed;
- provider tested;

unless those checks actually ran or were validly mocked where appropriate.

---

## D-066 — Architecture Changes Require Explicit Decision

**Status:** APPROVED

### Decision

If implementation conflicts with approved architecture, agent must:

- stop;
- create decision/ADR;
or
- return `BLOCKED_DECISION`.

Do not silently redefine architecture inside code.

---

## D-067 — Phase 0 Before Core Product Implementation

**Status:** APPROVED

### Decision

Cursor rules, agents, quality gates, Playwright, CI, and task workflow should be established before Phase 1 implementation.

---

## D-068 — Site Builder Does Not Include Full CRM

**Status:** APPROVED

### Decision

Landflow does not currently include:

- telephony;
- call center;
- task management;
- internal chat;
- full sales pipeline;
- warehouse CRM.

Lead capture and external CRM integration remain in scope.

---

## D-070 — Relational Database Is Primary Source of Truth

**Status:** APPROVED

### Decision

Use relational tables for stable domain entities.

Use JSON selectively for:

- Block Schema;
- Block state;
- integration mappings;
- dynamic settings;
- publication snapshots/manifests.

---

## D-092 — Product UI Language: Russian Only

**Status:** APPROVED

### Decision

All user-facing Landflow UI is Russian.

This covers every product surface: authentication, Dashboard, Sites, Designer, automotive management, Forms, Integrations, Publishing, Team, Super Admin, Developer Platform, Marketplace, validation/error/success messages, empty/loading states, dialogs, menus, tooltips, notifications and browser-visible titles.

Code identifiers remain English:

- classes, methods, variables;
- database tables/columns;
- enum values, permission keys, route names;
- TypeScript types, API fields.

External service names and other proper nouns (e.g. Yandex SmartCaptcha, Yandex Metrica, Webflow) may keep their original names.

### Rationale

Landflow targets the Russian-speaking automotive market; mixed-language UI is a product defect.

### Impact

- Mixed Russian/English UI is not allowed.
- Architecture must not make future i18n impossible (e.g. keep copy in components/translation files, avoid concatenating translated fragments with logic).
- A full multi-language i18n system is not implemented now.
- Long Russian strings must be considered in UI and browser QA (buttons, tabs, tables, dialogs, sidebars, mobile).

### References

- `.cursor/rules/00-project-core.mdc`
- `.cursor/rules/40-ui-shadcn.mdc`
- `.cursor/rules/80-browser-qa.mdc`

---

## D-095 — Landflow Authentication Methods

**Status:** APPROVED

Resolved product decision made by the Product Owner on 2026-09-29. The choice of methods is not reopened by agents.

### Decision

Supported sign-up / sign-in methods:

1. **Email + password (Fortify).** Registration creates the User with `email_verified_at = null` and sends a verification email; until the email is verified the User has no full access; after verification the account is active.
2. **Yandex OAuth.** On the first successful Yandex sign-in Landflow must obtain the user's email; the User is created or linked; the email is considered verified on the basis of the successful Yandex authorization, so no separate verification email is sent. If Yandex does not return an email, no account is created: the user sees a Russian explanation and is offered email registration or re-authorization with the required access.

Every User has an email (system and mandatory mail).

Not supported: two-factor authentication, TOTP, passkeys, WebAuthn. They are not Landflow features, current or future.

### Consequences

- The starter-kit 2FA / TOTP / passkey features (Fortify features, routes, UI, schema, frontend packages that are direct dependencies) are removed in `P1-002`. This replaces the P1-001 fix "2FA / passkey enrollment requires `verified`": the pre-account-takeover path is closed by removing the factors, not by adding verification checks.
- Fortify remains the email/password engine (registration, login, password reset, email verification, password confirmation).
- Yandex OAuth is a mandatory product method. Its implementation needs separate decisions: account linking (D-096) and OAuth client package (D-097). External identities are stored in a separate user-level entity, not in a provider column on `users` (`DATABASE.md` §4); its schema depends on D-085.
- Yandex OAuth is not implemented in `P1-002`.

### References

- `docs/architecture/SECURITY.md` §3
- `docs/architecture/DATABASE.md` §4
- `.cursor/rules/20-laravel.mdc` §56, `.cursor/rules/70-security.mdc` §119

---

# PROVISIONAL DECISIONS

---

## D-015 — Database Session / Cache / Queue Initially

**Status:** PROVISIONAL

### Decision

Initial infrastructure may use database-backed:

- session;
- cache;
- queue.

### Rationale

Keeps early infrastructure simple.

### Impact

Redis is not required at project start.

---

## D-016 — Redis Only When Justified

**Status:** PROVISIONAL

### Decision

Introduce Redis when real requirements justify it.

Potential triggers:

- queue throughput;
- rate-limit counters;
- cache pressure;
- pub/sub;
- realtime features.

### Impact

Do not install Redis into Landflow merely because it is common.

---

## D-069 — Inertia SSR Not Required Initially

**Status:** PROVISIONAL

### Decision

Authenticated application does not need Inertia SSR initially.

### Note

Public Site rendering architecture remains a separate open decision.

---

## D-071 — No Dedicated Search Engine Initially

**Status:** PROVISIONAL

### Decision

Use primary relational database search/filtering until scale proves the need for dedicated search infrastructure.

---

# ADR-REQUIRED OPEN DECISIONS

---

## D-072 — No Object Storage Provider Selected Yet

**Status:** OPEN

### Decision

Storage abstraction should remain provider-neutral until production infrastructure is selected.

### Tracked By

Provider selection is tracked by D-076 (ADR_REQUIRED), resolved by BACKLOG `X-003 — ADR: Object Storage Provider`.

---

## D-073 — Public Site Rendering Engine

**Status:** ADR_REQUIRED

### Decision Needed

Choose between:

- Laravel/server-rendered;
- React SSR;
- generated/static;
- hybrid.

### Must Consider

- SEO;
- publish atomicity;
- custom domains;
- performance;
- caching;
- developer Blocks;
- automotive dynamic pages.

### Blocking

Phase 5 publishing implementation.

---

## D-074 — Published Snapshot Representation

**Status:** ADR_REQUIRED

### Decision Needed

Choose the concrete version/snapshot strategy.

Potential approaches:

- normalized version references;
- serialized manifest;
- generated artifacts;
- hybrid.

### Blocking

Phase 5.

---

## D-075 — Public Asset Versioning Strategy

**Status:** ADR_REQUIRED

### Decision Needed

Define how old Published Versions keep stable media when customer replaces an asset.

Potential direction:

immutable objects/versioned references.

### Blocking

Production-grade publishing/media stability.

### Resolved By

BACKLOG `X-010 — ADR: Media Ownership and Asset Versioning` (together with D-087; trigger: before P2-013).

---

## D-076 — Object Storage Provider

**Status:** ADR_REQUIRED

### Decision Needed

Select production storage/CDN strategy.

Examples could include an S3-compatible provider or another object store.

### Not Blocking

Early local development.

---

## D-077 — Redis Adoption

**Status:** OPEN

### Decision Needed

Adopt only if measured/real need appears.

### Potential triggers

- queue scale;
- rate limiting;
- cache;
- pub/sub.

---

## D-078 — Billing Provider

**Status:** ADR_REQUIRED

### Decision Needed

Choose subscription/payment provider.

### Must Define

- customer;
- subscription;
- invoices;
- webhook verification;
- entitlement lifecycle.

### Blocking

Real paid subscription implementation.

---

## D-079 — Marketplace License Scope

**Status:** ADR_REQUIRED

### Decision Needed

Determine whether paid Template/Block license belongs to:

- Workspace;
- Site;
- account;
- another scope.

### Blocking

Paid Marketplace.

---

## D-080 — Marketplace Runtime / Sandbox

**Status:** ADR_REQUIRED

### Decision Needed

Define third-party Developer Block runtime and restrictions.

### Must Cover

- JavaScript;
- dependencies;
- network;
- data access;
- sandboxing;
- CSP;
- review.

### Blocking

Public third-party Marketplace runtime.

---

## D-081 — Custom Developer Script Support

**Status:** ADR_REQUIRED

### Decision Needed

Whether Landflow will support customer/developer custom code and under what restrictions.

### Note

Not required for core MVP.

---

## D-082 — Workspace-Level Reusable Forms

**Status:** OPEN

### Current Direction

Forms are Site-owned.

### Future Question

Whether reusable Workspace Form templates/entities are needed.

### Rule

Do not allow arbitrary cross-Site Form references as a shortcut.

---

## D-083 — Workspace Vehicle Live Fallback Behavior

**Status:** OPEN

### Question

When a Workspace Vehicle image/content changes, should Sites without explicit override automatically reflect the shared value before next Publish?

### Current Safe Direction

Preserve clear fallback semantics and Draft/Published boundaries.

### Note

Must be clarified before advanced Workspace Vehicle synchronization behavior.

---

## D-084 — Money Storage Representation

**Status:** ADR_REQUIRED

### Decision Needed

Choose exact database representation.

Potential options:

- integer minor units;
- fixed decimal.

### Requirement

Never use floating point.

### Blocking

Final Site Offer implementation.

### Resolved By

BACKLOG `X-008 — ADR: Money Storage Representation` (trigger: before P3-009).

---

## D-085 — Primary Identifier Strategy

**Status:** ADR_REQUIRED

### Decision Needed

Choose:

- bigint;
- UUID;
- ULID;
- mixed internal/public IDs.

### Must Consider

- database performance;
- public identifiers;
- distributed generation needs;
- developer ergonomics.

### Blocking

Core domain migration implementation if not already standardized by project.

### Resolved By

BACKLOG `X-007 — ADR: Primary Identifier Strategy` (trigger: before P1-003).

---

## D-086 — Characteristic Value Schema Strategy

**Status:** ADR_REQUIRED

### Decision Needed

Determine exact storage pattern for automotive characteristic values across:

- Generation;
- Modification;
- Trim.

### Current Product Rule

More specific canonical value may override inherited value.

### Blocking

Automotive schema implementation.

### Resolved By

BACKLOG `X-009 — ADR: Characteristic Value Schema` (trigger: before P3-001).

---

## D-087 — Site Asset / Workspace Asset Relationship

**Status:** OPEN

### Question

Whether Site assets use:

- direct Media ownership;
- Workspace Media with Site references;
- both.

### Must Preserve

- tenant isolation;
- reusable Workspace assets;
- Published version stability.

### Resolved By

BACKLOG `X-010 — ADR: Media Ownership and Asset Versioning` (together with D-075; trigger: before P2-013).

---

## D-088 — Site-Specific Permission Override Model

**Status:** OPEN

### Current Direction

Workspace role provides baseline.

Site access may use a Site-specific role.

Avoid complex allow/deny matrices initially.

### Blocking

Advanced Team phase, not basic MVP.

---

## D-089 — Real-Time Collaboration

**Status:** OPEN

### Decision

Not currently in MVP.

If introduced later, requires dedicated architecture for:

- presence;
- conflict resolution;
- concurrent editing;
- realtime transport.

---

## D-090 — Undo / Redo Architecture

**Status:** OPEN

### Decision Needed Later

Define Designer command/state history architecture.

### Note

Designer MVP may initially ship without full collaborative undo/redo.

---

## D-091 — External Automotive Source Provider

**Status:** OPEN

### Decision

No external provider is selected as core source yet.

### Rule

Internal Landflow schema stays provider-neutral.

---

## D-093 — Developer Profile Ownership

**Status:** OPEN

### Question

Whether a Developer Profile is:

- owned by a User;
- owned by a Workspace;
- another explicit creator-ownership model.

`DATABASE.md` §69 currently leaves `developer_profiles` as "user_id or workspace relation".

### Must Preserve

- `TENANCY.md` §59: a Developer profile is not automatically a customer Workspace; creator ownership must not be confused with customer tenancy;
- Workspace-private Blocks remain Workspace-owned;
- Marketplace license scope (D-079) stays consistent with the chosen owner.

### Blocking

`P9-001 — Developer Profile`.

---

## D-094 — Personal Data Retention and Deletion Policy

**Status:** ADR_REQUIRED

### Decision Needed

Define for personal data (Submissions, contact fields, IP addresses, delivery payloads):

- retention periods;
- deletion and anonymization rules and triggers;
- applicable Russian personal-data requirements.

### Current Constraint

`SECURITY.md` §21: retention policy must be defined before production launch. Schema must not make deletion/anonymization impossible (`.cursor/rules/50-database.mdc` §99, `.cursor/rules/70-security.mdc` §99).

### Blocking

- production launch;
- Submission export (no BACKLOG task exists yet; the task must reference D-094 when created).

---

## D-096 — OAuth Account Linking Policy

**Status:** OPEN

### Question

An existing User `user@example.com` (email/password) signs in with Yandex, and Yandex returns `user@example.com`. When may the Yandex identity be attached to the existing User?

Linking by plain email match is not allowed without an explicit safe policy (account-takeover risk: whoever controls the external account would gain the existing Landflow account; an unverified local account registered with someone else's email would capture the real owner's Yandex sign-in).

### Options

- never auto-link: the Yandex sign-in is refused for an email that already belongs to a User, with a Russian message to sign in with the password and link Yandex from account settings;
- link only after the user proves control of the existing account: password login in the same flow, or a confirmation link sent to the existing email;
- auto-link only when the existing User's email is already verified and the provider confirms the email is verified (weakest option; requires evidence of the provider's guarantee);
- linking initiated only from an authenticated session (settings → "Привязать Яндекс"), combinable with any option above.

Must also define: behavior for an existing *unverified* User with the same email, unlinking (a User must keep at least one sign-in method), what happens when the provider email later changes, re-authentication for OAuth-only Users without a password (`password.confirm`, email change, account deletion; nullable `users.password` or an unusable hash; whether password reset may add a password), and Russian user messages.

### Blocking

Yandex OAuth implementation. Does not block `P1-002`.

### Resolved By

A cross-cutting X- task before the Yandex OAuth task (security boundary: an ADR is recommended; owner accepts).

---

## D-097 — Yandex OAuth Client Implementation

**Status:** OPEN

### Question

How Landflow performs the Yandex OAuth authorization-code flow:

- `laravel/socialite` with a community Yandex provider (e.g. `socialiteproviders/yandex`);
- `laravel/socialite` with a small in-repo Yandex provider;
- Laravel HTTP client without a new package.

Adding a package requires evaluation under `.cursor/rules/00-project-core.mdc` §8 (maintenance, security, license). Provider endpoints, scopes and the email fields must be checked against current official Yandex ID documentation during implementation (`.cursor/rules/90-agent-workflow.mdc` §43).

### Blocking

Yandex OAuth implementation. Resolved together with D-096.

---

# SUPERSEDED DECISIONS

None currently.

---

# DECISION GOVERNANCE

---

# 4. When to Add a Decision

Add an entry when:

- a recurring implementation question has been settled;
- multiple agents need the same answer;
- a choice affects future tasks;
- an ADR is not necessary but ambiguity would create inconsistent code.

---

# 5. When to Use an ADR Instead

Use a dedicated ADR when the choice:

- is hard to reverse;
- affects architecture broadly;
- introduces infrastructure;
- changes security boundary;
- changes runtime model;
- changes data ownership;
- selects a major provider/platform.

Examples:

- rendering engine;
- storage;
- Redis;
- billing;
- Marketplace sandbox.

---

# 6. Agent Rule

Agents must treat `APPROVED` decisions as constraints.

They may not reopen them casually.

If a task requires contradicting one:

1. stop;
2. explain conflict;
3. propose decision change;
4. use `BLOCKED_DECISION`.

---

# 7. Decision Change

If an approved decision changes:

- do not delete historical entry;
- mark it `SUPERSEDED`;
- create new decision;
- reference replacement;
- update affected architecture docs.

---

# 8. Open Decisions

Open decisions do not block unrelated work.

Example:

billing provider is open.

This does not block:

- Workspace;
- Designer;
- Automotive;
- Forms.

---

# 9. ADR-Required Decisions

Tasks depending directly on an `ADR_REQUIRED` item must not start until the ADR is approved.

Examples:

Phase 5 cannot implement public publishing engine before D-073/D-074 are resolved.

---

# 10. Current Immediate Rule

Task order is defined by `docs/automation/BACKLOG.md`.

Current position and the immediate sequence live in `BACKLOG.md` §3 (Current Backlog Position) and §12 (Current Immediate Sequence); they are not duplicated here.

---

# 11. Final Decision Principle

**Approved decisions prevent repeated debate.  
Open decisions remain visible.  
Major irreversible choices require ADRs.  
Agents must not silently invent architecture in implementation code.**
