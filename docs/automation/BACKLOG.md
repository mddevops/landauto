# Landflow — Engineering Backlog

**Document:** `docs/automation/BACKLOG.md`  
**Status:** Executable project backlog  
**Purpose:** Convert the approved Master Plan into ordered autonomous tasks with dependencies, acceptance criteria, references, and Definition of Done gates.

---

# 1. Backlog Rules

Each task must have:

- ID;
- phase;
- status;
- dependencies;
- objective;
- scope;
- acceptance criteria;
- required checks;
- architecture references.

Task status values:

- `NOT_STARTED`
- `IN_PROGRESS`
- `DONE`
- `PARTIAL`
- `BLOCKED_DECISION`
- `BLOCKED_EXTERNAL`
- `DEFERRED`

A task may begin only when all required dependencies are `DONE`.

---

# 2. Task Execution Contract

For every task:

```text
Read task
→ Read relevant docs
→ Inspect repository
→ Implement
→ Run applicable checks
→ Fix failures
→ Review
→ Update PROJECT_STATE if state changed
→ Mark DONE
```

Do not mark task DONE because implementation "looks correct".

Use `DEFINITION_OF_DONE.md`.

---

# 3. Current Backlog Position

Current phase:

`P0 — Foundation / Automation`

Current next task:

`P0-022 — Standardize Quality Commands`

Phase 1 implementation must not start before required Phase 0 tasks are complete.

---

# PHASE 0 — FOUNDATION / AUTOMATION

---

## P0-001 — Product and Architecture Documentation

**Status:** DONE  
**Dependencies:** none

### Objective

Create the foundational product and architecture documentation.

### Completed artifacts

Product:

- `docs/product/PRODUCT.md`
- `docs/product/WEBFLOW_TO_LANDFLOW.md`

Architecture:

- `docs/architecture/ARCHITECTURE.md`
- `docs/architecture/DATABASE.md`
- `docs/architecture/TENANCY.md`
- `docs/architecture/PERMISSIONS.md`
- `docs/architecture/AUTOMOTIVE_DATA.md`
- `docs/architecture/BLOCK_SYSTEM.md`
- `docs/architecture/FORMS_AND_INTEGRATIONS.md`
- `docs/architecture/PUBLISHING.md`
- `docs/architecture/SECURITY.md`

### Acceptance Criteria

- Architecture boundaries are documented.
- Workspace tenancy is documented.
- Automotive ownership is documented.
- Block/Form/Publishing/Security models are documented.

### Checks

- Documentation review.

---

## P0-002 — Definition of Done

**Status:** DONE  
**Dependencies:** P0-001

### Objective

Define task completion gates.

### Artifact

`docs/automation/DEFINITION_OF_DONE.md`

### Acceptance Criteria

Document defines:

- DONE;
- PARTIAL;
- BLOCKED_DECISION;
- BLOCKED_EXTERNAL;
- test gates;
- security/tenancy gates;
- browser QA expectations;
- No Fake Success rule.

---

## P0-003 — Project State and Master Plan

**Status:** DONE  
**Dependencies:** P0-001, P0-002

### Objective

Create living project-state memory and staged engineering roadmap.

### Artifacts

- `docs/automation/PROJECT_STATE.md`
- `docs/automation/MASTER_PLAN.md`

### Acceptance Criteria

- Current repository baseline recorded.
- Planned vs implemented clearly separated.
- Phases 0–11 defined.
- Current next step defined.

---

## P0-004 — Create Decision Log

**Status:** DONE  
**Dependencies:** P0-003

### Objective

Create `docs/automation/DECISIONS.md`.

### Scope

Record approved lightweight decisions such as:

- Workspace is tenant;
- npm only;
- PHPUnit;
- Playwright;
- no Pest;
- no Dusk;
- shadcn conventions;
- initial DB session/cache/queue;
- Redis only when justified;
- no premature API;
- Global Catalog ownership;
- copy vs sync semantics.

Also list open major decisions requiring ADR.

### Acceptance Criteria

- Approved decisions have stable IDs.
- Open decisions clearly marked.
- Major ADR-required decisions separated from lightweight decisions.
- No implementation invented.

### Required Checks

- Documentation review.
- Architecture consistency review.

### References

- `ARCHITECTURE.md`
- `PROJECT_STATE.md`
- `MASTER_PLAN.md`

---

## P0-005 — Create Core Cursor Project Rule

**Status:** DONE  
**Dependencies:** P0-004

### Objective

Create:

`.cursor/rules/00-project-core.mdc`

### Scope

Define:

- project identity;
- required reading;
- package policy;
- scope exclusions;
- current phase;
- Definition of Done authority;
- task-status vocabulary;
- BLOCKED_DECISION behavior.

### Acceptance Criteria

- Rule is concise.
- Does not duplicate whole architecture docs.
- Prevents agents from starting unrelated work.
- Requires repository inspection before implementation.

### Checks

- Rule review.

---

## P0-006 — Create Architecture Cursor Rule

**Status:** DONE  
**Dependencies:** P0-005

### Artifact

`.cursor/rules/10-architecture.mdc`

### Scope

Encode non-negotiable architecture rules:

- Workspace tenancy;
- Global/Workspace/Site scopes;
- Site owns commercial data;
- Template ownership semantics;
- Block Schema runtime truth;
- Draft/Published separation;
- centralized Form pipeline.

### Acceptance Criteria

Agent can quickly identify architectural invariants without reading every document for every task.

---

## P0-007 — Create Laravel Cursor Rule

**Status:** DONE  
**Dependencies:** P0-005

### Artifact

`.cursor/rules/20-laravel.mdc`

### Scope

Define:

- Laravel conventions;
- controllers/actions/services;
- validation;
- policies;
- Eloquent;
- jobs;
- migrations;
- queue behavior;
- no unnecessary abstractions.

### Acceptance Criteria

- Consistent with current Laravel version.
- Does not introduce new packages.

---

## P0-008 — Create React/Inertia Cursor Rule

**Status:** DONE  
**Dependencies:** P0-005

### Artifact

`.cursor/rules/30-react-inertia.mdc`

### Scope

Define:

- React + TypeScript strict;
- Inertia conventions;
- page/layout/component structure;
- server-authoritative permissions;
- no duplicate frontend state architecture.

---

## P0-009 — Create shadcn/UI Rule

**Status:** DONE  
**Dependencies:** P0-008

### Artifact

`.cursor/rules/40-ui-shadcn.mdc`

### Scope

Define:

- shadcn/ui default;
- existing `@/lib/utils`;
- granular Radix packages;
- no duplicate `cn`;
- responsive and accessibility expectations;
- visual QA requirement.

---

## P0-010 — Create Database Rule

**Status:** DONE  
**Dependencies:** P0-006, P0-007

### Artifact

`.cursor/rules/50-database.mdc`

### Scope

Define:

- Global/Workspace/Site ownership;
- relational-first;
- JSON boundaries;
- foreign keys;
- indexes;
- delete/archive behavior;
- migration safety;
- no customer prices in Global Catalog.

---

## P0-011 — Create Testing Rule

**Status:** DONE  
**Dependencies:** P0-005

### Artifact

`.cursor/rules/60-testing.mdc`

### Scope

Define:

- PHPUnit;
- Feature vs Unit tests;
- regression tests;
- Playwright policy;
- no fake success;
- required checks;
- test isolation.

---

## P0-012 — Create Security Rule

**Status:** DONE  
**Dependencies:** P0-006, P0-011

### Artifact

`.cursor/rules/70-security.mdc`

### Scope

Define:

- tenancy;
- authorization;
- secrets;
- XSS;
- SSRF;
- upload validation;
- public Form safety;
- Marketplace boundary.

---

## P0-013 — Create Browser QA Rule

**Status:** DONE  
**Dependencies:** P0-011

### Artifact

`.cursor/rules/80-browser-qa.mdc`

### Scope

Define:

- desktop/tablet/mobile viewports;
- console-error policy;
- screenshot policy;
- critical flow verification;
- Playwright conventions.

---

## P0-014 — Create Agent Workflow Rule

**Status:** DONE  
**Dependencies:** P0-005 through P0-013

### Artifact

`.cursor/rules/90-agent-workflow.mdc`

### Scope

Define:

- backlog task selection;
- dependency checking;
- agent handoff;
- fix loop;
- review;
- PROJECT_STATE update;
- stop conditions.

---

## P0-015 — Create Architect Agent

**Status:** DONE  
**Dependencies:** P0-014

### Artifact

`.cursor/agents/architect.md`

### Responsibilities

- architecture consistency;
- ADR proposals;
- schema boundaries;
- dependency review;
- block unsafe architectural changes.

---

## P0-016 — Create Backend Agent

**Status:** DONE  
**Dependencies:** P0-014

### Artifact

`.cursor/agents/backend.md`

### Responsibilities

- Laravel domain implementation;
- migrations;
- policies;
- services/actions;
- queues;
- tests.

---

## P0-017 — Create Frontend Agent

**Status:** DONE  
**Dependencies:** P0-014

### Artifact

`.cursor/agents/frontend.md`

### Responsibilities

- React;
- Inertia;
- shadcn;
- Designer UI;
- TypeScript;
- browser behavior.

---

## P0-018 — Create UI Reviewer Agent

**Status:** DONE  
**Dependencies:** P0-013, P0-014

### Artifact

`.cursor/agents/ui-reviewer.md`

### Responsibilities

- visual comparison;
- spacing;
- responsive;
- empty/error states;
- screenshot review.

---

## P0-019 — Create QA Agent

**Status:** DONE  
**Dependencies:** P0-011, P0-013, P0-014

### Artifact

`.cursor/agents/qa.md`

### Responsibilities

- PHPUnit;
- Playwright;
- regression coverage;
- browser console/network;
- Definition of Done verification.

---

## P0-020 — Create Security Agent

**Status:** DONE  
**Dependencies:** P0-012, P0-014

### Artifact

`.cursor/agents/security.md`

### Responsibilities

- tenancy;
- authorization;
- secrets;
- public endpoints;
- SSRF/XSS/upload review.

---

## P0-021 — Create Reviewer Agent

**Status:** DONE  
**Dependencies:** P0-015 through P0-020

### Artifact

`.cursor/agents/reviewer.md`

### Responsibilities

Independent final review of:

- diff;
- architecture;
- tests;
- regressions;
- Definition of Done.

---

## P0-021A — Bootstrap Landflow Application

**Status:** DONE  
**Dependencies:** P0-001 through P0-021

### Context

Repository inspection showed that the repository contained only `.cursor/`, `docs/` and `.idea/`.

No Laravel/React/Inertia application existed, so quality commands (P0-022) had nothing to standardize.

### Objective

Create the technical Landflow application foundation in the current repository root.

### Scope

- create the Laravel application in a temporary directory, then move it into the repository root;
- preserve existing `.cursor/`, `docs/`, `.idea/` and `.git/` (if present);
- stack according to approved decisions:
  - Laravel, PHP ^8.3;
  - React + Inertia + TypeScript strict;
  - Vite (vite-plus), Tailwind CSS 4, shadcn/ui;
  - Fortify authentication foundation;
  - PHPUnit, Laravel Pint, Larastan/PHPStan;
  - npm only.

### Out of Scope

- Pest, Laravel Dusk, Vue, Redux, Zustand;
- standalone ESLint/Prettier;
- Redis, Sanctum/API infrastructure;
- billing/Marketplace packages;
- Playwright (P0-023);
- quality command standardization (P0-022);
- any product domain: Workspaces, Sites, Designer, Automotive, Forms, Publishing, Team, Marketplace.

### Acceptance Criteria

- `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `storage/`, `tests/`, `artisan`, `composer.json`, `package.json`, `phpunit.xml`, `vite.config.*` exist in repository root.
- Existing `.cursor/`, `docs/`, `.idea/` are unchanged.
- Laravel boots.
- Baseline PHPUnit suite passes.
- Frontend check passes (if provided by installed stack).
- Production build passes.

### Checks

- Laravel boot (`php artisan about` / HTTP smoke);
- PHPUnit;
- frontend check;
- production build;
- Playwright: NOT_AVAILABLE_YET.

### Result

- Created from official `laravel/react-starter-kit` (Laravel installer: `--react --phpunit --npm --database=sqlite --no-boost`) in a temporary directory, then moved into repository root.
- `.cursor/`, `docs/`, `.idea/` preserved (no `.git/` existed).
- Not moved: `pnpm-workspace.yaml` (npm only, D-007).
- `.gitignore`: removed starter-kit `/.cursor` entry so Cursor rules/agents stay versioned.
- `vite.config.ts`: `fmt.ignorePatterns` += `docs/**`, `.cursor/**` (frontend formatter must not govern project documentation).
- `APP_NAME=Landflow` in `.env` / `.env.example`.
- Checks: Laravel boot PASS, PHPUnit PASS (40 tests), `npm run check` PASS, `npm run build` PASS, Playwright NOT_AVAILABLE_YET.

---

## P0-021B — Repository Initialization & Project State Reconciliation

**Status:** DONE  
**Dependencies:** P0-021A

### Objective

Make the current `landauto` working copy the single versioned source of the project and reconcile project-state documents with repository reality.

### Scope

- initialize Git in repository root (branch `main`);
- add remote `origin` = `https://github.com/mddevops/landauto.git`;
- no force push;
- verify `.gitignore`: `.cursor/`, `docs/`, `.env.example` tracked; `.env`, `vendor/`, `node_modules/` ignored; `.idea/` stays ignored;
- the old repository `mddevops/landflow` is not used, compared or merged;
- reconcile `BACKLOG.md` statuses of P0-004…P0-021 against actually existing artifacts;
- sync Phase 0 sequence in `.cursor/rules/90-agent-workflow.mdc`;
- record D-092 (Russian-only product UI) in `DECISIONS.md`;
- update `PROJECT_STATE.md`.

### Acceptance Criteria

- `.git` exists, current branch `main`, `origin` points to `mddevops/landauto`.
- Ignore rules verified with `git check-ignore`.
- P0-004…P0-021 marked DONE only where the artifact file exists.
- Existing checks (PHPUnit, Pint, Larastan, `npm run check`, `npm run build`) still pass.

### Result

- Git initialized, branch `main`, `origin` = `https://github.com/mddevops/landauto.git` (remote was empty; nothing pushed, no commit created by agent).
- All 18 artifacts of P0-004…P0-021 verified present → DONE.

---

## P0-021C — Russian Foundation UI

**Status:** DONE  
**Dependencies:** P0-021B

### Objective

Translate the existing starter-kit user-facing UI to Russian so the application foundation complies with D-092 before quality gates and browser QA are established.

### Scope

- existing surfaces only: welcome page, authentication (login, registration, password reset, email verification, 2FA, passkeys), dashboard, settings, navigation/app shell;
- page titles, headings, buttons, labels, placeholders, menus, breadcrumbs, empty/loading/error states, toasts;
- browser-visible backend validation and auth/Fortify messages in Russian (`APP_LOCALE=ru` with Russian translation files for used messages);
- no full multi-language i18n system (D-092).

### Out of Scope

- new product features or screens;
- Playwright (P0-023);
- quality command standardization (P0-022).

### Acceptance Criteria

- No English user-facing strings remain on existing surfaces (external proper nouns allowed).
- Validation/auth error messages shown to users are Russian.
- Long Russian labels fit layout (desktop and mobile smoke).
- Existing PHPUnit tests pass (tests adjusted only where they assert user-facing copy).
- `npm run check` and `npm run build` pass.

### Checks

- PHPUnit;
- Pint;
- Larastan;
- frontend check;
- production build;
- manual browser review of changed surfaces (Playwright: NOT_AVAILABLE_YET).

### Result

- All starter-kit surfaces translated to Russian: welcome, auth (login, registration, password reset, email verification, password confirmation, 2FA challenge, passkeys), dashboard, settings (profile, security, appearance, account deletion), app shell (sidebar, header, user menu, mobile sheet), shadcn primitives' screen-reader/aria texts.
- Passkey client errors mapped to Russian messages (`resources/js/lib/passkey-errors.ts`); raw English library messages are not shown.
- `APP_LOCALE=ru` (`config/app.php` default `ru`, `.env.example`); fallback locale `en`.
- Standard Laravel localization, no translation package / React i18n library: `lang/ru/{auth,passwords,pagination,validation}.php`, `lang/ru.json` (flash toasts, Fortify, passkeys, validation summary, notifications/mail, error pages).
- `tests/Feature/LocalizationTest.php` added (locale, `<html lang="ru">`, Russian auth/validation/flash messages); no existing tests weakened or removed.
- Layout: long Russian strings reviewed at 1440×1000 and 390×844 (manual headless Chrome review; no overflow, no console errors, no failed requests); no layout fixes required beyond shorter appearance labels.
- Checks: PHPUnit PASS (45 tests), Pint PASS, Larastan PASS, `npm run check` PASS, `npm run build` PASS, Playwright NOT_AVAILABLE_YET.
- Allowed Latin text: proper nouns (Landflow, Laravel, Laracasts on placeholder landing), technical abbreviations (QR, TOTP), user data.

### References

- `docs/automation/DECISIONS.md` (D-092)
- `.cursor/rules/00-project-core.mdc`
- `.cursor/rules/40-ui-shadcn.mdc`

---

## P0-022 — Standardize Quality Commands

**Status:** NOT_STARTED  
**Dependencies:** P0-011, P0-021C

### Objective

Document and/or add canonical commands for:

- PHPUnit;
- Larastan;
- Pint;
- frontend check;
- production build.

### Acceptance Criteria

One canonical command per gate.

Commands execute non-interactively.

### Checks

Run every standardized command.

---

## P0-023 — Install and Configure Playwright

**Status:** NOT_STARTED  
**Dependencies:** P0-013, P0-022

### Objective

Add Playwright browser QA.

### Scope

- npm only;
- Chromium;
- config;
- base URL;
- screenshots;
- console capture;
- initial smoke test.

### Acceptance Criteria

- Playwright runs locally.
- Browser test can open application.
- JavaScript console errors can fail test when appropriate.

### Required Checks

- frontend check;
- build;
- Playwright.

---

## P0-024 — Create Browser QA Baseline

**Status:** NOT_STARTED  
**Dependencies:** P0-023

### Objective

Create initial tests for current application.

### Flows

- landing/root;
- login;
- dashboard;
- settings.

### Acceptance Criteria

- Desktop baseline verified.
- Tablet/mobile smoke checks where relevant.
- Screenshots produced intentionally.

---

## P0-025 — Create CI Pipeline

**Status:** NOT_STARTED  
**Dependencies:** P0-022, P0-023

### Objective

Create CI for required quality gates.

### Pipeline

- Composer install;
- npm install;
- PHPUnit;
- Larastan;
- Pint check;
- frontend check;
- build;
- Playwright if environment permits.

### Acceptance Criteria

CI runs without production secrets.

---

## P0-026 — Create Autonomous Task Runner Workflow

**Status:** NOT_STARTED  
**Dependencies:** P0-014 through P0-025

### Objective

Document/orchestrate the autonomous task cycle.

### Acceptance Criteria

Workflow can:

- select next ready task;
- assign correct agent;
- run gates;
- stop on failures;
- request Reviewer;
- update task status;
- update PROJECT_STATE.

---

## P0-027 — Phase 0 Validation

**Status:** NOT_STARTED  
**Dependencies:** P0-004 through P0-026

### Objective

Verify Foundation is ready for product implementation.

### Acceptance Criteria

- all Phase 0 docs exist;
- Cursor rules exist;
- agents exist;
- canonical checks run;
- Playwright available;
- CI available;
- autonomous flow documented;
- PROJECT_STATE updated.

### Required Checks

All Phase 0 quality commands.

---

# PHASE 1 — CORE PLATFORM

---

## P1-001 — Audit Authentication Baseline

**Status:** NOT_STARTED  
**Dependencies:** P0-027

### Objective

Verify current Fortify/auth implementation before changing it.

### Scope

- registration;
- login;
- reset;
- verification;
- 2FA;
- passkeys.

### Acceptance Criteria

Document current behavior and identify only required changes.

---

## P1-002 — Enforce Email Verification

**Status:** NOT_STARTED  
**Dependencies:** P1-001

### Objective

Implement `MustVerifyEmail` behavior consistently.

### Acceptance Criteria

- verification flow works;
- protected pages behave correctly;
- regression tests exist.

---

## P1-003 — Create Workspace Schema

**Status:** NOT_STARTED  
**Dependencies:** P1-002

### Scope

- `workspaces`;
- `workspace_members`;
- ownership fields;
- statuses.

### Acceptance Criteria

- migration clean;
- relationships defined;
- indexes/constraints correct.

### References

- `TENANCY.md`
- `DATABASE.md`

---

## P1-004 — Workspace Domain Models

**Status:** NOT_STARTED  
**Dependencies:** P1-003

### Scope

- Workspace model;
- membership model;
- User relationships;
- owner semantics.

---

## P1-005 — Create Default Personal Workspace

**Status:** NOT_STARTED  
**Dependencies:** P1-004

### Objective

New account receives valid initial Workspace according to product flow.

### Acceptance Criteria

- no duplicate accidental Workspace;
- owner membership created transactionally.

---

## P1-006 — Workspace Context / Switcher Backend

**Status:** NOT_STARTED  
**Dependencies:** P1-004

### Scope

- resolve active Workspace;
- validate membership;
- remember last Workspace safely.

---

## P1-007 — Workspace Switcher UI

**Status:** NOT_STARTED  
**Dependencies:** P1-006

### Acceptance Criteria

- only accessible Workspaces appear;
- switching changes Dashboard context;
- removed membership invalidates old context.

---

## P1-008 — Permission Foundation

**Status:** NOT_STARTED  
**Dependencies:** P1-004

### Objective

Implement initial system roles/permission catalog.

### Initial roles

- Owner
- Admin
- Designer
- Content Editor

---

## P1-009 — Entitlement Foundation

**Status:** NOT_STARTED  
**Dependencies:** P1-004

### Scope

- Plan model/configuration;
- entitlement resolver;
- `max_sites`;
- future feature keys.

No real billing provider.

---

## P1-010 — Site Schema

**Status:** NOT_STARTED  
**Dependencies:** P1-003, P1-009

### Scope

- Sites;
- Site folders if included now;
- status;
- Workspace ownership.

---

## P1-011 — Site Domain Models and Policies

**Status:** NOT_STARTED  
**Dependencies:** P1-010, P1-008

### Acceptance Criteria

- foreign Workspace Site access denied;
- Site ownership resolved through Workspace.

---

## P1-012 — Template Foundation

**Status:** NOT_STARTED  
**Dependencies:** P1-011

### Scope

- official Template model;
- Template version baseline;
- Blank Template;
- one starter Template if practical.

---

## P1-013 — Create Site Flow Backend

**Status:** NOT_STARTED  
**Dependencies:** P1-011, P1-012

### Acceptance Criteria

- validates `max_sites`;
- chooses Template;
- creates Site-owned initial structure;
- tenant-safe.

---

## P1-014 — Dashboard UI

**Status:** NOT_STARTED  
**Dependencies:** P1-007, P1-013

### Scope

- Workspace header;
- Sites grid;
- create Site;
- empty state;
- Site cards.

---

## P1-015 — Create Site Wizard UI

**Status:** NOT_STARTED  
**Dependencies:** P1-013, P1-014

### Flow

- Create Site;
- choose Template;
- name;
- create;
- open Site.

---

## P1-016 — Core Platform E2E

**Status:** NOT_STARTED  
**Dependencies:** P1-015

### Required flow

```text
Register/Login
→ Workspace
→ Create Site
→ Choose Template
→ Site in Dashboard
```

### Security test

Another Workspace cannot access created Site.

---

## P1-017 — Phase 1 Review

**Status:** NOT_STARTED  
**Dependencies:** P1-001 through P1-016

### Acceptance Criteria

All Phase 1 DoD gates pass.

---

# PHASE 2 — DESIGNER FOUNDATION

---

## P2-001 — Page Schema and Models

**Status:** NOT_STARTED  
**Dependencies:** P1-017

### Scope

- Pages;
- home Page;
- slug;
- order;
- Site ownership.

---

## P2-002 — Block Definition / Version Schema

**Status:** NOT_STARTED  
**Dependencies:** P2-001

### References

- `BLOCK_SYSTEM.md`
- `DATABASE.md`

---

## P2-003 — Block Schema Validator

**Status:** NOT_STARTED  
**Dependencies:** P2-002

### Initial field types

- text
- textarea
- boolean
- select
- image
- group
- repeater

---

## P2-004 — Block Instance Schema

**Status:** NOT_STARTED  
**Dependencies:** P2-002, P2-003

### Acceptance Criteria

- pinned Block Version;
- validated JSON state;
- Site/Page ownership.

---

## P2-005 — Initial Official Blocks

**Status:** NOT_STARTED  
**Dependencies:** P2-004

### Blocks

- Header
- Hero
- Benefits
- CTA
- Contacts
- Footer

---

## P2-006 — Designer Shell

**Status:** NOT_STARTED  
**Dependencies:** P2-004

### UI

- top bar;
- left panel;
- canvas;
- right Properties Panel.

---

## P2-007 — Pages Panel

**Status:** NOT_STARTED  
**Dependencies:** P2-001, P2-006

---

## P2-008 — Navigator

**Status:** NOT_STARTED  
**Dependencies:** P2-004, P2-006

### Actions

- select;
- reorder;
- duplicate;
- hide/show;
- delete.

---

## P2-009 — Properties Panel from Schema

**Status:** NOT_STARTED  
**Dependencies:** P2-003, P2-006

### Acceptance Criteria

No one-off settings UI per official Block.

---

## P2-010 — Repeater Editing

**Status:** NOT_STARTED  
**Dependencies:** P2-009

### Acceptance Criteria

- add;
- delete;
- duplicate;
- reorder;
- stable item IDs.

---

## P2-011 — Conditional Schema Fields

**Status:** NOT_STARTED  
**Dependencies:** P2-009

---

## P2-012 — Site Design Tokens

**Status:** NOT_STARTED  
**Dependencies:** P2-006

### Initial tokens

- primary/secondary colors;
- typography;
- radius;
- container;
- buttons.

---

## P2-013 — Asset Upload / Image Picker

**Status:** NOT_STARTED  
**Dependencies:** P2-006

### Security

Upload validation required.

---

## P2-014 — Action System Foundation

**Status:** NOT_STARTED  
**Dependencies:** P2-004

### Actions

- open_url;
- open_page;
- scroll_to;
- phone;
- email.

Popup/Form actions may be placeholders until Phase 4.

---

## P2-015 — Draft Autosave

**Status:** NOT_STARTED  
**Dependencies:** P2-004, P2-009

### Acceptance Criteria

- edit;
- autosave;
- reload;
- state preserved;
- no production concept changed.

---

## P2-016 — Draft Preview

**Status:** NOT_STARTED  
**Dependencies:** P2-015

---

## P2-017 — Designer Browser QA

**Status:** NOT_STARTED  
**Dependencies:** P2-005 through P2-016

### Flow

```text
Open Site
→ add Hero
→ edit title
→ add Benefits items
→ reorder
→ reload
→ Preview
```

---

## P2-018 — Phase 2 Review

**Status:** NOT_STARTED  
**Dependencies:** P2-017

---

# PHASE 3 — AUTOMOTIVE FOUNDATION

---

## P3-001 — Global Catalog Core Schema

**Status:** NOT_STARTED  
**Dependencies:** P2-018

### Entities

- Make
- Model
- Series
- Generation
- Modification
- Trim

---

## P3-002 — Catalog Policies / Platform Permissions

**Status:** NOT_STARTED  
**Dependencies:** P3-001

### Acceptance Criteria

Customers cannot mutate catalog.

---

## P3-003 — Characteristics

**Status:** NOT_STARTED  
**Dependencies:** P3-001

---

## P3-004 — Equipment / Options

**Status:** NOT_STARTED  
**Dependencies:** P3-001

---

## P3-005 — Automotive Colors / Swatches

**Status:** NOT_STARTED  
**Dependencies:** P3-001

### Acceptance Criteria

Two-tone color supported.

---

## P3-006 — Automotive Images

**Status:** NOT_STARTED  
**Dependencies:** P3-005

### Metadata

- Trim;
- Color;
- angle;
- transparency;
- order.

---

## P3-007 — Super Admin Catalog UI

**Status:** NOT_STARTED  
**Dependencies:** P3-002 through P3-006

---

## P3-008 — Site Vehicle Schema

**Status:** NOT_STARTED  
**Dependencies:** P3-001

---

## P3-009 — Site Offer / Benefits Schema

**Status:** NOT_STARTED  
**Dependencies:** P3-008

---

## P3-010 — Customer Vehicle Import

**Status:** NOT_STARTED  
**Dependencies:** P3-007, P3-008

---

## P3-011 — Automotive Fallback Resolver

**Status:** NOT_STARTED  
**Dependencies:** P3-006, P3-008

### Priority

Site
→ Workspace when available
→ Global.

---

## P3-012 — Automotive Binding Registry

**Status:** NOT_STARTED  
**Dependencies:** P3-008, P3-009, P2-003

---

## P3-013 — Vehicle Card Block

**Status:** NOT_STARTED  
**Dependencies:** P3-012

---

## P3-014 — Vehicle Grid Block

**Status:** NOT_STARTED  
**Dependencies:** P3-013

---

## P3-015 — Vehicle Detail Blocks

**Status:** NOT_STARTED  
**Dependencies:** P3-012

### Blocks

- Price/Offer
- Characteristics
- Equipment
- Gallery

---

## P3-016 — Automotive E2E

**Status:** NOT_STARTED  
**Dependencies:** P3-010 through P3-015

### Flow

```text
Admin creates vehicle
→ customer imports
→ sets price
→ adds Vehicle Grid
→ sees price/image/colors
```

---

## P3-017 — Phase 3 Review

**Status:** NOT_STARTED  
**Dependencies:** P3-016

---

# PHASE 4 — FORMS & INTERACTIVE COMPONENTS

---

## P4-001 — Popup Schema / Runtime

**Status:** NOT_STARTED  
**Dependencies:** P3-017

---

## P4-002 — Open Popup Action

**Status:** NOT_STARTED  
**Dependencies:** P4-001, P2-014

---

## P4-003 — Form Schema / Fields

**Status:** NOT_STARTED  
**Dependencies:** P3-017

---

## P4-004 — Public Form Identifier / Endpoint

**Status:** NOT_STARTED  
**Dependencies:** P4-003

---

## P4-005 — Submission Persistence

**Status:** NOT_STARTED  
**Dependencies:** P4-004

---

## P4-006 — Phone Normalization

**Status:** NOT_STARTED  
**Dependencies:** P4-005

---

## P4-007 — Context Passing

**Status:** NOT_STARTED  
**Dependencies:** P4-002, P4-003, P3-012

### Context

- vehicle;
- offer;
- trim;
- color;
- Page;
- Block;
- UTMs.

---

## P4-008 — Anti-Spam Base

**Status:** NOT_STARTED  
**Dependencies:** P4-005

### Features

- honeypot;
- rate limit;
- duplicate detection.

---

## P4-009 — Blacklist

**Status:** NOT_STARTED  
**Dependencies:** P4-008

### Scopes

- Global;
- Workspace;
- Site.

---

## P4-010 — Yandex SmartCaptcha Adapter

**Status:** NOT_STARTED  
**Dependencies:** P4-008

### Requirement

Verify current official documentation during implementation.

---

## P4-011 — Carousel Capability

**Status:** NOT_STARTED  
**Dependencies:** P2-003

---

## P4-012 — Gallery / Lightbox

**Status:** NOT_STARTED  
**Dependencies:** P4-011

---

## P4-013 — Interactive E2E

**Status:** NOT_STARTED  
**Dependencies:** P4-001 through P4-012

---

## P4-014 — Phase 4 Review

**Status:** NOT_STARTED  
**Dependencies:** P4-013

---

# PHASE 5 — PUBLISHING

---

## P5-001 — Publishing Runtime ADR

**Status:** NOT_STARTED  
**Dependencies:** P4-014

### Required decision

- rendering engine;
- snapshot format;
- cache;
- asset versioning;
- activation strategy.

### Completion

ADR approved before P5-002.

---

## P5-002 — Published Version Schema

**Status:** NOT_STARTED  
**Dependencies:** P5-001

---

## P5-003 — Publication Records

**Status:** NOT_STARTED  
**Dependencies:** P5-002

---

## P5-004 — Publish Validator

**Status:** NOT_STARTED  
**Dependencies:** P5-002

---

## P5-005 — Published Snapshot Builder

**Status:** NOT_STARTED  
**Dependencies:** P5-001, P5-004

---

## P5-006 — Atomic Activation

**Status:** NOT_STARTED  
**Dependencies:** P5-005

---

## P5-007 — Public Runtime

**Status:** NOT_STARTED  
**Dependencies:** P5-006

---

## P5-008 — Landflow Subdomains

**Status:** NOT_STARTED  
**Dependencies:** P5-007

---

## P5-009 — SEO / Sitemap / Robots

**Status:** NOT_STARTED  
**Dependencies:** P5-007

---

## P5-010 — Version History / Restore

**Status:** NOT_STARTED  
**Dependencies:** P5-006

---

## P5-011 — Publishing E2E

**Status:** NOT_STARTED  
**Dependencies:** P5-008, P5-009, P5-010

### Flow

Draft
→ Preview
→ production unchanged
→ Publish
→ production changes.

Also test failed Publish.

---

## P5-012 — Phase 5 Review

**Status:** NOT_STARTED  
**Dependencies:** P5-011

---

# PHASE 6 — INTEGRATIONS & ANALYTICS

---

## P6-001 — Workspace Integration Profiles

**Status:** NOT_STARTED  
**Dependencies:** P5-012

---

## P6-002 — Secret Encryption / Masking

**Status:** NOT_STARTED  
**Dependencies:** P6-001

---

## P6-003 — Site Integration Bindings

**Status:** NOT_STARTED  
**Dependencies:** P6-001

---

## P6-004 — Form Routes

**Status:** NOT_STARTED  
**Dependencies:** P4-003, P6-003

---

## P6-005 — Field Mapping

**Status:** NOT_STARTED  
**Dependencies:** P6-004

---

## P6-006 — Delivery Records / Jobs

**Status:** NOT_STARTED  
**Dependencies:** P6-004

---

## P6-007 — Retry / Idempotency

**Status:** NOT_STARTED  
**Dependencies:** P6-006

---

## P6-008 — Email Adapter

**Status:** NOT_STARTED  
**Dependencies:** P6-006

---

## P6-009 — Webhook / Custom API Adapter

**Status:** NOT_STARTED  
**Dependencies:** P6-006

### Security

SSRF protection mandatory.

---

## P6-010 — Delivery Logs UI

**Status:** NOT_STARTED  
**Dependencies:** P6-007, P6-008, P6-009

---

## P6-011 — Test Connection

**Status:** NOT_STARTED  
**Dependencies:** P6-001, P6-009

---

## P6-012 — Yandex Metrica Adapter

**Status:** NOT_STARTED  
**Dependencies:** P5-007

---

## P6-013 — Semantic Analytics Events

**Status:** NOT_STARTED  
**Dependencies:** P6-012

---

## P6-014 — Integrations E2E

**Status:** NOT_STARTED  
**Dependencies:** P6-010 through P6-013

---

## P6-015 — Phase 6 Review

**Status:** NOT_STARTED  
**Dependencies:** P6-014

---

# PHASE 7 — PAID SITE FEATURES

---

## P7-001 — Custom Domain Schema

**Status:** NOT_STARTED  
**Dependencies:** P6-015

---

## P7-002 — Domain Validation / Verification

**Status:** NOT_STARTED  
**Dependencies:** P7-001

---

## P7-003 — SSL Provisioning Integration

**Status:** NOT_STARTED  
**Dependencies:** P7-002

---

## P7-004 — Primary Domain / Redirects

**Status:** NOT_STARTED  
**Dependencies:** P7-003

---

## P7-005 — Landflow Branding Entitlement

**Status:** NOT_STARTED  
**Dependencies:** P1-009, P5-007

---

## P7-006 — Advanced SEO

**Status:** NOT_STARTED  
**Dependencies:** P5-009

---

## P7-007 — Plan Limit Enforcement Review

**Status:** NOT_STARTED  
**Dependencies:** P7-004, P7-005

---

## P7-008 — Billing Provider ADR

**Status:** NOT_STARTED  
**Dependencies:** P7-007

---

## P7-009 — Real Subscription Integration

**Status:** DEFERRED  
**Dependencies:** P7-008

---

## P7-010 — Phase 7 Review

**Status:** NOT_STARTED  
**Dependencies:** P7-007

---

# PHASE 8 — TEAM / COLLABORATION

---

## P8-001 — Workspace Invitations

**Status:** NOT_STARTED  
**Dependencies:** P7-010

---

## P8-002 — Member Suspension / Removal

**Status:** NOT_STARTED  
**Dependencies:** P8-001

---

## P8-003 — Site-Level Access

**Status:** NOT_STARTED  
**Dependencies:** P8-001

---

## P8-004 — Expanded System Roles

**Status:** NOT_STARTED  
**Dependencies:** P8-003

### Roles

- Pricing Manager
- Lead Manager
- Integrations Manager
- Publisher

---

## P8-005 — Workspace Vehicle Library

**Status:** NOT_STARTED  
**Dependencies:** P3-010, P8-003

---

## P8-006 — Site-to-Site Vehicle Copy

**Status:** NOT_STARTED  
**Dependencies:** P8-005

---

## P8-007 — Copy Conflict Resolution

**Status:** NOT_STARTED  
**Dependencies:** P8-006

---

## P8-008 — Shared Workspace Assets

**Status:** NOT_STARTED  
**Dependencies:** P2-013, P8-003

---

## P8-009 — Richer Version History

**Status:** NOT_STARTED  
**Dependencies:** P5-010

---

## P8-010 — Team E2E

**Status:** NOT_STARTED  
**Dependencies:** P8-001 through P8-009

---

## P8-011 — Phase 8 Review

**Status:** NOT_STARTED  
**Dependencies:** P8-010

---

# PHASE 9 — DEVELOPER PLATFORM

---

## P9-001 — Developer Profile

**Status:** NOT_STARTED  
**Dependencies:** P8-011

---

## P9-002 — Developer Permissions

**Status:** NOT_STARTED  
**Dependencies:** P9-001

---

## P9-003 — Block Authoring UI

**Status:** NOT_STARTED  
**Dependencies:** P2-003, P9-002

---

## P9-004 — Schema Editor

**Status:** NOT_STARTED  
**Dependencies:** P9-003

---

## P9-005 — Developer Preview Data

**Status:** NOT_STARTED  
**Dependencies:** P9-003, P3-012

---

## P9-006 — Block Version Publishing

**Status:** NOT_STARTED  
**Dependencies:** P9-004

---

## P9-007 — Template Authoring

**Status:** NOT_STARTED  
**Dependencies:** P9-006

---

## P9-008 — Review Workflow

**Status:** NOT_STARTED  
**Dependencies:** P9-006

---

## P9-009 — Marketplace Runtime Security ADR

**Status:** NOT_STARTED  
**Dependencies:** P9-008

---

## P9-010 — AI Schema Assistant

**Status:** DEFERRED  
**Dependencies:** P9-004

### Rule

Only after deterministic Schema authoring works.

---

## P9-011 — Developer Platform E2E

**Status:** NOT_STARTED  
**Dependencies:** P9-001 through P9-009

---

## P9-012 — Phase 9 Review

**Status:** NOT_STARTED  
**Dependencies:** P9-011

---

# PHASE 10 — MARKETPLACE

---

## P10-001 — Marketplace Listings

**Status:** NOT_STARTED  
**Dependencies:** P9-012

---

## P10-002 — Categories / Search / Detail

**Status:** NOT_STARTED  
**Dependencies:** P10-001

---

## P10-003 — Free Install Flow

**Status:** NOT_STARTED  
**Dependencies:** P10-002

---

## P10-004 — Licensing Model Decision

**Status:** NOT_STARTED  
**Dependencies:** P10-003

---

## P10-005 — Paid Marketplace Billing

**Status:** DEFERRED  
**Dependencies:** P10-004, P7-008

---

## P10-006 — Sales / Earnings

**Status:** DEFERRED  
**Dependencies:** P10-005

---

## P10-007 — Ratings / Reviews

**Status:** DEFERRED  
**Dependencies:** P10-003

---

## P10-008 — Marketplace E2E

**Status:** NOT_STARTED  
**Dependencies:** P10-001 through P10-004

---

## P10-009 — Phase 10 Review

**Status:** NOT_STARTED  
**Dependencies:** P10-008

---

# PHASE 11 — EXTERNAL AUTOMOTIVE DATA SOURCES

---

## P11-001 — External Source Contract

**Status:** NOT_STARTED  
**Dependencies:** P10-009

### Objective

Define provider-neutral adapter contract.

---

## P11-002 — Source Registry

**Status:** NOT_STARTED  
**Dependencies:** P11-001

---

## P11-003 — Normalization Pipeline

**Status:** NOT_STARTED  
**Dependencies:** P11-001

---

## P11-004 — External ID Mapping

**Status:** NOT_STARTED  
**Dependencies:** P11-003

---

## P11-005 — Import Logs / Failure Handling

**Status:** NOT_STARTED  
**Dependencies:** P11-003

---

## P11-006 — First External Catalog Adapter

**Status:** NOT_STARTED  
**Dependencies:** P11-002 through P11-005

### Requirement

Adapter selected only after explicit source decision.

---

## P11-007 — VIN / Stock Item ADR

**Status:** NOT_STARTED  
**Dependencies:** P11-006

---

## P11-008 — VIN / Inventory Layer

**Status:** DEFERRED  
**Dependencies:** P11-007

---

## P11-009 — External Source E2E

**Status:** NOT_STARTED  
**Dependencies:** P11-006

---

## P11-010 — Phase 11 Review

**Status:** NOT_STARTED  
**Dependencies:** P11-009

---

# CROSS-CUTTING BACKLOG TASKS

---

## X-001 — ADR: Public Site Rendering

**Status:** NOT_STARTED  
**Trigger:** before P5-002

Decision must cover:

- Laravel/server render;
- React SSR;
- generated/static;
- hybrid;
- SEO;
- cache;
- deployment.

---

## X-002 — ADR: Published Snapshot Format

**Status:** NOT_STARTED  
**Trigger:** before P5-002

---

## X-003 — ADR: Object Storage Provider

**Status:** NOT_STARTED  
**Trigger:** before production-scale media deployment

---

## X-004 — ADR: Redis Adoption

**Status:** DEFERRED  
**Trigger:** measured need for cache/queue/rate limiting scale

---

## X-005 — ADR: Billing Provider

**Status:** NOT_STARTED  
**Trigger:** P7-008

---

## X-006 — ADR: Marketplace Sandbox

**Status:** NOT_STARTED  
**Trigger:** P9-009

---

# BACKLOG MAINTENANCE RULES

---

# 4. Adding Tasks

New task must:

- use phase ID;
- define dependency;
- avoid overlapping another task;
- reference architecture;
- have acceptance criteria.

Do not add vague tasks like:

`Improve backend`.

---

# 5. Splitting Tasks

Split a task if:

- one agent cannot complete it cleanly in one cycle;
- it affects several unrelated domains;
- it has independent review gates;
- it repeatedly fails autonomous execution.

---

# 6. Reordering

Task order may change only when dependencies remain valid.

Architecture dependencies take precedence over convenience.

---

# 7. Status Update

When marking `DONE`:

- all required checks must be reported;
- PROJECT_STATE updated if implementation state changed;
- known limitations recorded.

---

# 8. Deferred Tasks

`DEFERRED` means intentionally postponed.

It does not block current phase unless phase completion explicitly requires it.

Examples:

- paid Marketplace billing;
- AI Schema Assistant;
- VIN inventory.

---

# 9. BLOCKED_DECISION

Use when a decision is required.

The task should reference:

- decision needed;
- ADR or DECISIONS entry;
- blocked dependent tasks.

---

# 10. BLOCKED_EXTERNAL

Use when waiting for:

- DNS;
- production credentials;
- provider account;
- external approval.

Complete all local work first.

---

# 11. Definition of Done

All tasks are governed by:

`docs/automation/DEFINITION_OF_DONE.md`

This backlog cannot weaken those rules.

---

# 12. Current Immediate Sequence

Completed: P0-001 … P0-021 (documentation, rules, agents).

The remaining required sequence is:

```text
P0-021A Bootstrap Landflow Application                           DONE
P0-021B Repository Initialization & Project State Reconciliation DONE
P0-021C Russian Foundation UI                                    DONE
P0-022  Quality Commands
P0-023  Playwright
P0-024  Browser QA Baseline
P0-025  CI
P0-026  Autonomous Workflow
P0-027  Phase 0 Validation
```

Only after `P0-027 = DONE`:

```text
P1-001 — Core Platform implementation begins.
```

---

# 13. Final Backlog Principle

**The backlog is executable, not aspirational.  
Each task must be small, dependency-aware, testable, reviewable, and grounded in approved architecture.  
Cursor should never choose a random future feature while a required prerequisite remains unfinished.**
