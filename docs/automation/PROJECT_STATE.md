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

**Phase 0 — Foundation / Architecture / Automation Design**

Current focus:

- finalize product architecture;
- finalize engineering rules;
- build Cursor operating system;
- establish Master Plan and Backlog;
- create Cursor rules/agents;
- only then begin implementation.

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

Verified actual state after P0-021A:

```text
Laravel application:        INSTALLED (official laravel/react-starter-kit)
React/Inertia application:  INSTALLED
composer.json:              PRESENT
package.json:               PRESENT
Git repository (.git):      INITIALIZED (P0-021B, branch main, origin mddevops/landauto)
Playwright:                 NOT_AVAILABLE_YET
```

Installed stack (verified from `composer show` / `npm ls`):

Backend:

- Laravel 13.33.0
- PHP requirement ^8.3 (local runtime 8.3.6)
- Inertia backend (`inertiajs/inertia-laravel`) 3.4.0
- Laravel Fortify 1.40.0 (login, registration, password reset, email verification, 2FA, passkeys)
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

Local environment defaults (`.env`): SQLite database, database session/cache/queue (consistent with D-015).

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

Known capabilities/configuration include:

- login;
- registration;
- password reset;
- email verification feature;
- 2FA;
- passkeys;
- rate limiting.

Approved direction:

`User` should implement `MustVerifyEmail` because email verification feature is enabled.

This is an approved future implementation decision, not yet a statement that implementation has been completed.

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

Known current business model state:

- `User` exists.

Core Landflow business entities described in architecture documents are not assumed to exist yet.

Examples still to be implemented later:

- Workspace
- Site
- Template
- Block Definition
- Global Automotive Catalog
- Site Vehicle
- Form
- Integration Profile
- Publication

Agents must not confuse architecture specification with already-shipped code.

---

# 8. Current Testing Baseline

Current testing stack:

- PHPUnit 12.5.36
- SQLite in-memory tests
- Mockery available

Approved decisions:

- PHPUnit remains the main PHP testing framework.
- Do not introduce Pest merely for preference.
- Browser automation should later use Playwright.
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

Still needed:

- Russian foundation UI (P0-021C)
- standardized quality commands (P0-022)
- Playwright (P0-023)
- browser QA baseline (P0-024)
- CI (P0-025)
- autonomous workflow (P0-026)

---

# 40. Current Check Availability

Verified after P0-021A (tools installed and executed successfully on the starter-kit baseline):

- PHPUnit 12.5.36: AVAILABLE — `php artisan test` PASS (40 tests, 138 assertions)
- Laravel Pint 1.32.1: AVAILABLE — `pint --test` PASS
- Larastan 3.12.2 / PHPStan 2.2.16 (level 7): AVAILABLE — `phpstan analyse` PASS (0 errors)
- vite-plus check: AVAILABLE — `npm run check` PASS (format + type-aware lint)
- TypeScript: AVAILABLE — `npm run types:check` (`tsc --noEmit`) PASS
- Vite production build: AVAILABLE — `npm run build` PASS

Canonical quality command aliases are NOT yet standardized; this is `P0-022`.
The starter-kit scripts currently overlap (e.g. `composer test` also runs Pint and PHPStan; `lint`, `lint:check`, `types:check`, `ci:check`).

Not yet confirmed/installed as project quality gate:

- Playwright: NOT_AVAILABLE_YET
- browser screenshot regression automation: NOT_AVAILABLE_YET
- full CI pipeline: NOT_AVAILABLE_YET

Agents must not claim unavailable checks as PASS.

---

# 41. Next Planned Automation Documents

Recommended next order:

1. `MASTER_PLAN.md`
2. `BACKLOG.md`
3. `DECISIONS.md`
4. Cursor rules
5. Cursor agents
6. testing/browser QA setup
7. CI
8. autonomous orchestrator workflow

---

# 42. Current Next Approved Task

Last completed task: `P0-021B — Repository Initialization & Project State Reconciliation` (DONE).

**Next task: `P0-021C — Russian Foundation UI`.**

No implementation task should be inferred from this alone.

Known gaps:

- Starter-kit UI (welcome, auth, dashboard, settings) is English; `APP_LOCALE=en`. Violates D-092 → tracked as `P0-021C`.
- Starter kit ships `.github/workflows/tests.yml` (`composer setup` + `composer ci:check`) and `.github/dependabot.yml`; CI is formally `P0-025` and must switch to canonical commands after `P0-022`.
- `composer.json` package name is still `laravel/react-starter-kit`.
- `laravel/chisel` is a production `require` of the starter kit; keep/remove needs an explicit decision.
- No initial commit exists yet; `origin` (`mddevops/landauto`) is empty. First commit/push requires explicit authorization.
- PHPUnit Feature tests that render Inertia pages depend on built Vite assets (`public/build/manifest.json`, fonts CSS). Running `npm run build` concurrently with PHPUnit causes a false 500 (`ViteException: Unable to locate font CSS file`); a clean checkout without a build would fail the same way. `P0-022` must account for this when ordering `composer quality` / CI (build before tests, or make tests independent of built assets), and gates must not run build and tests in parallel.

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

These are not blockers for current documentation/automation work.

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
Phase 0 — Foundation / Architecture / Automation: IN_PROGRESS
Phase 1 — Core Platform: NOT_STARTED
Phase 2 — Designer Foundation: NOT_STARTED
Phase 3 — Automotive Foundation: NOT_STARTED
Phase 4 — Forms & Interactive Components: NOT_STARTED
Phase 5 — Publishing: NOT_STARTED
Phase 6 — Integrations & Analytics: NOT_STARTED
Phase 7 — Paid Features: NOT_STARTED
Phase 8 — Team: NOT_STARTED
Phase 9 — Developer Platform: NOT_STARTED
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
    ├── DEFINITION_OF_DONE.md
    └── PROJECT_STATE.md
```

---

# 59. Future Cursor Structure

Planned:

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
    └── reviewer.md
```

Do not create all of these until Master Plan/Backlog ordering is finalized.

---

# 60. Autonomous Workflow Goal

Target workflow:

```text
Product Docs
→ Master Plan
→ Backlog
→ Orchestrator
→ Specialist Agent
→ Tests/Checks
→ Fix Loop
→ Reviewer
→ Commit
→ PROJECT_STATE update
→ Next Task
```

Task status is determined by quality gates, not agent confidence.

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

**Create `docs/automation/MASTER_PLAN.md`.**

After Master Plan:

1. BACKLOG
2. DECISIONS
3. Cursor rules
4. agents
5. automation/testing infrastructure
6. CI
7. begin Phase 1 implementation

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
Russian foundation UI:        NOT_STARTED (P0-021C)
Quality command aliases:      NOT_STARTED (P0-022)
Playwright/browser QA:        NOT_AVAILABLE_YET
CI:                           NOT_STARTED (starter-kit workflow present, not standardized)

Core Landflow implementation: NOT_STARTED
```

**Current phase: Phase 0 — IN_PROGRESS.  
Next approved task: P0-021C — Russian Foundation UI.**
