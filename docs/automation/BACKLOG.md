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

Phase 0 — Foundation / Automation: COMPLETED (gate `P0-027` DONE).

Current phase:

`P1 — Core Platform`

Current next task:

`P1-006 — Workspace Context / Switcher Backend` (X-011 and P1-005A DONE).

Resolved stops: `X-014`, P1-005A and `X-011` are DONE. Upcoming stop: `X-012` before P1-014. Before the first production deployment: `X-013` and D-094.

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
- Passkey client errors mapped to Russian messages (`resources/js/lib/passkey-errors.ts`); raw English library messages are not shown. (Historical: passkeys and this file were removed in P1-002, D-095.)
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

**Status:** DONE  
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

### Result

- Composer: `test` (PHPUnit only), `analyse` (Larastan), `format` (Pint), `format:check` (Pint `--test`), `quality` (sequential: test → analyse → format:check → `npm run check` → `npm run build`, stops on first failure).
- Removed duplicates: Composer `lint`, `lint:check`, `types:check`; npm `types:check`. `ci:check` kept only as a deprecated alias of `quality` for the existing workflow until P0-025 (removed in P0-025).
- `npm run check` now includes TypeScript type checking (vite-plus `lint.options.typeCheck: true`).
- PHPUnit no longer depends on `public/build`: `tests/TestCase.php` uses Laravel's `withoutVite()`. Verified with `public/build` absent → `composer test` PASS.
- Composer package renamed to `mddevops/landauto`; `laravel/chisel` moved to `require-dev` (installer scaffolding tool, unused at runtime).
- Checks: `composer test` PASS, `composer analyse` PASS, `composer format:check` PASS, `npm run check` PASS, `npm run build` PASS, `composer quality` PASS; negative exit-code probes verified. Playwright NOT_AVAILABLE_YET.

---

## P0-023 — Install and Configure Playwright

**Status:** DONE  
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

### Result

- `@playwright/test` 1.63.0 added as npm devDependency; only Chromium (headless shell) installed — no Firefox/WebKit, Dusk, Cypress or Selenium.
- `playwright.config.ts`: `tests/browser/` (separate from PHPUnit), one project `chromium-desktop` at 1440×1000, headless, `baseURL` `http://127.0.0.1:8200`, `workers: 1`, `retries: 0`, test timeout 30 s / expect 5 s, trace `retain-on-failure`, screenshot `only-on-failure`, video off, locale `ru-RU`, timezone `Europe/Moscow`, reduced motion.
- Isolated E2E environment: `APP_ENV=e2e` passed to the web server; `tests/browser/support/prepare-e2e.mjs` regenerates git-ignored `.env.e2e` from committed `.env.e2e.example` (test-only values, fresh random `APP_KEY` per run) and recreates git-ignored file SQLite `database/e2e.sqlite`. The script fails if configuration is cached or `public/hot` exists (Vite dev server).
- `webServer`: prepare → `npm run build` → `php artisan migrate --force` → dedicated `php artisan serve` on 127.0.0.1:8200, readiness via `/up`; `reuseExistingServer: false` (a running server cannot be proven to be the E2E environment). No Docker, no developer `.env`/database.
- `tests/browser/support/fixtures.ts`: every test fails on browser console errors, uncaught page errors and 4xx/5xx responses (no global filtering). `tests/browser/support/viewports.ts`: desktop 1440×1000, tablet 1024×1366, mobile 390×844 (tablet/mobile projects are P0-024 scope).
- `tests/browser/smoke.spec.ts`: `/` and `/login` render Russian UI (`lang="ru"`, headings, labels, buttons).
- `npm run test:e2e` = `playwright test`; Playwright stays a separate gate outside `composer quality`. `.gitignore`: `/test-results`, `/playwright-report`, `/blob-report`, `/playwright/.cache`, `.env.e2e`.
- Checks: `composer quality` PASS, `npm run test:e2e` PASS (2 tests). Negative probe (temporary spec with `console.error` and a 404 page) failed as expected, with failure screenshot and trace; probe removed.

---

## P0-024 — Create Browser QA Baseline

**Status:** DONE  
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

### Result

- Playwright projects: `setup` (logs in once via the real login form, saves storage state to git-ignored `playwright/.auth/member.json`), `desktop` 1440×1000 (full suite), `tablet` 1024×1366 and `mobile` 390×844 (tests tagged `@responsive`; touch + mobile viewport emulation, device scale factor 1). All Chromium, light color scheme.
- Deterministic test data: `database/seeders/E2eSeeder.php` (runs only in `APP_ENV=e2e`, throws elsewhere; covered by `tests/Feature/E2eSeederTest.php`) creates two verified test-only users: `member@landflow.test` (long Russian name for layout QA, used by the storage state) and `login@landflow.test` (login/logout flows, separate login rate-limit key). Seeded by the web server command after migrations.
- Flows (`tests/browser/*.spec.ts`, 22 tests + 1 setup):
  - landing: Russian content for guests `@responsive`, link to login;
  - auth: login page `@responsive`, guest redirect from `/dashboard`, wrong password → Russian error, keyboard login → dashboard, logout from user menu;
  - dashboard: app shell `@responsive` (desktop/tablet sidebar; mobile sheet opens from header, closes with Escape), user menu → settings, collapsed sidebar persists after reload;
  - settings: profile `@responsive` (Russian labels, current account data), settings navigation → appearance, security requires password confirmation, profile validation error in Russian and unchanged data after reload.
- Every test fails on console errors, page errors and 4xx/5xx responses; `@responsive` tests also assert no document-level horizontal overflow.
- Screenshots: `test-results/screenshots/<area>/<name>--<project>.png` (15 per run; `test-results/` is cleared per run, so no stale evidence; attached to the HTML report; not pixel baselines). Reviewed: landing, login, invalid login, dashboard, mobile sidebar, profile, security at the relevant viewports — layouts stack/wrap as intended, long Russian name truncates in the sidebar, no overflow.
- Replaced the P0-023 `smoke.spec.ts` (its assertions moved into `landing.spec.ts` / `auth.spec.ts`).
- Final fix: the Laravel logo mark (login/register/auth layouts, sidebar, header, mobile navigation, favicons) replaced by the owner-provided Landflow logo (vector trace of the supplied image; favicons rasterized from it). No user-facing Laravel branding remains.
- Cleanup: removed the non-functional starter-kit search button from the app header (search is not implemented at this phase).
- Checks: `composer quality` PASS, `npm run test:e2e` PASS (23 passed) — re-run after the final fix.

---

## P0-025 — Create CI Pipeline

**Status:** DONE (CI configured and verified on GitHub Actions)  
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

### Result

- One workflow: the starter-kit `.github/workflows/tests.yml` («tests», `composer setup` + `composer ci:check`) was replaced in place by `.github/workflows/ci.yml` («Landflow CI»). No second workflow.
- Steps: PHP 8.3 (`composer.json` `^8.3`) + Composer v2 with Composer cache → Node 22 (as in the starter workflow and local tooling; no `.nvmrc`/`engines`) with npm cache → `composer install` → `npm ci` → `.env` from `.env.example` + `php artisan key:generate` → `composer quality` → `npx playwright install --with-deps chromium` → `npm run test:e2e`. Triggers: push to `main`, pull requests into `main`; concurrency cancels outdated runs; 20-minute timeout; `contents: read`; actions pinned to commit SHAs.
- No database service; PHPUnit in-memory SQLite, E2E `database/e2e.sqlite`; the development MySQL database is not reachable from CI. No GitHub Secrets, no production credentials.
- Playwright failure artifacts (traces, failure screenshots, review screenshots, HTML report, Laravel log) are uploaded only when the E2E step fails, retained 7 days.
- Duplicate build removed: the old workflow built in `composer setup` and again in `ci:check`. Now `composer quality` builds once and the E2E server reuses that build in CI (`E2E_REUSE_BUILD=1`; locally it still rebuilds).
- Clean-checkout fix: `composer quality` now runs `php artisan wayfinder:generate --with-form` before `npm run check`. The generated route helpers are git-ignored and only the build created them, so on a fresh clone `npm run check` failed (TS2307). Previously hidden because `composer setup` built first.
- `composer ci:check` removed (no remaining references). `composer setup` kept: it is the local bootstrap script, not a CI step.
- No deployment of any kind.
- Local validation: workflow YAML parsed and structurally checked (symfony/yaml from vendor: step shape, SHA pins, step-id references, no secrets). The CI steps were replayed on a clean copy of the tracked files (no vendor, node_modules, .env, build, generated helpers): `composer install`, `npm ci`, `.env` + key, `composer quality` PASS, `npm run test:e2e` with `E2E_REUSE_BUILD=1` PASS (23 passed, build reused). In the workspace: `composer quality` PASS, `npm run test:e2e` PASS (23 passed).
- GitHub verification: commit `fe864ee`, run [36577884025](https://github.com/mddevops/landauto/actions/runs/36577884025) — success on the first real run, Ubuntu 24.04 (`ubuntu-latest`), job 1m29s. Log confirmed: PHP 8.3.35 with `pdo_sqlite`/`sqlite3`, 139 Composer packages, `npm ci` (291 packages), PHPUnit 47 passed, Larastan `[OK] No errors`, Pint PASS (61 files), Wayfinder generated on the clean checkout, `vp check` (format, lint, TypeScript) PASS, one production build, Chromium (Chrome for Testing 153 + headless shell) installed with system dependencies, E2E 23 passed (1 setup, 14 desktop, 4 tablet, 4 mobile) against `php artisan serve` + `database/e2e.sqlite`, reusing the build. Failure-artifact upload step correctly skipped.
- Hardening: runner pinned from `ubuntu-latest` to `ubuntu-24.04` (commit `e6dc967`, run [36579169216](https://github.com/mddevops/landauto/actions/runs/36579169216) PASS) ahead of the announced `ubuntu-latest` → Ubuntu 26 migration.

---

## P0-026 — Create Autonomous Task Runner Workflow

**Status:** DONE  
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

### Result

- `docs/automation/AUTONOMOUS_WORKFLOW.md` — normative protocol: Orchestrator role, source of truth and divergence handling, task statuses (`DEFERRED` owner-only, `STALLED` runtime-only), BACKLOG reading rules (dependency ranges incl. letter-suffixed tasks, Phase Review tasks, cross-cutting `X-` triggers), current-phase determination and phase transition, ready-task selection (resume first, first ready task in BACKLOG order, blocking-decision check, owner-named task), SINGLE TASK / CONTINUOUS / read-only / review-only modes, task start sequence, specialist routing and delegation mechanics, review routing with per-role verdict mapping, quality routing, fix loop and STALLED, BLOCKED_DECISION / BLOCKED_EXTERNAL records, human approval boundaries, Git safety, state updates, phase boundary, report formats, Orchestrator self-limits, four owner commands.
- `.cursor/agents/orchestrator.md` — Orchestrator role prompt (official Cursor subagent frontmatter `name`/`description`): coordinator only; routes to architect, backend, frontend, ui-reviewer, qa, security, reviewer; never implements specialist work, never writes a reviewer's verdict.
- No `.cursor/commands/` or `.cursor/workflows/`: `.cursor/agents/*.md` subagents are verified in the official Cursor documentation; commands are supported but not needed, workflows are not documented. No workflow engine, database, daemon, queue or package.
- `QUALITY_COMMANDS.md` §40 points to the protocol's quality routing. PROJECT_STATE updated: autonomous workflow fields (§39), next task P0-027 (§42, §68, §70), automation documents (§16), status-vocabulary scope (§53), and stale §3/§41/§58/§59/§60 statements corrected.
- Dry run (independent read-only subagent, actual repository state): actual state → resume P0-026 (`IN_PROGRESS`); with P0-026 `DONE` → current phase Phase 0, next ready task P0-027 (primary qa; architect, security, qa, final reviewer, ui-reviewer because Phase 0 changed user-visible UI; `composer quality` → `npm run test:e2e` + repository/documentation consistency check); Phase 1 selected: NO. Its protocol findings (X-tasks unselectable, missing ADR tasks, docs owner, MASTER_PLAN §131 review defaults, session concurrency, subagent nesting, explicit Phase Review request) were fixed.
- Final Reviewer (independent subagent, `reviewer.md`): first REJECTED with 3 findings (security triggers narrower than rule 90 §25 / rule 70 §127, inaccurate verdict vocabulary, undefined phase transition) → fixed (per-role verdict table, trigger union, phase transition: finished phase `COMPLETED` + next phase `IN_PROGRESS` in PROJECT_STATE §54, then stop) → second review PASS.
- Checks: link/reference check PASS (all relative links and file references resolve, all 8 agent files exist); `composer quality` PASS and `npm run test:e2e` PASS (23 passed) — run before the review fixes and re-run on the final state. No contradiction with `90-agent-workflow.mdc` found by the dry run or the Reviewer.
- Follow-up for P0-027: re-exercise the protocol end to end on the final text (phase transition, verdict table, owner-named task were added after the dry run).
- Committed and pushed to `origin/main` with owner authorization (`chore: establish Landflow autonomous workflow`).

---

## P0-027 — Phase 0 Validation

**Status:** DONE  
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

### Result

Run through `AUTONOMOUS_WORKFLOW.md` in SINGLE TASK MODE (first real run of the protocol). Validation only: no feature code, no migrations, no package changes; documentation consistency fixes only.

Phase 0 report (MASTER_PLAN §139):

- Implemented capabilities: product / architecture / automation documentation; Cursor rules 00–90 and 8 agent files; Landflow application on Laravel 13 + Fortify (2FA, passkeys) + Inertia 3 + React 19 + TypeScript strict + Tailwind 4 + shadcn; Russian foundation UI (`lang="ru"`, Russian validation); canonical quality commands (`composer quality`); Playwright E2E with isolated SQLite and viewports desktop 1440×1000 / tablet 1024×1366 / mobile 390×844; GitHub Actions CI on ubuntu-24.04; autonomous workflow protocol and Orchestrator.
- Tests / metrics (final state of this run, not requirements): PHPUnit 47 tests / 152 assertions; PHPStan level 7 0 errors; Pint pass; vite-plus format 82 files and check 76 files (lint + TypeScript) clean; production build OK; `npm run test:e2e` 23 passed (setup, 14 desktop, 4 tablet, 4 mobile). CI run 36583494996 (commit 0b69c6d) PASS on ubuntu-24.04.
- Known limitations: foundation hygiene follow-ups `X-011` (shared Inertia User allowlist, `.gitignore` `.env.*`, seeder environment guard, Playwright `requestfailed` collector, SSR leftovers, Pest allow-plugin) and UI follow-ups `X-012` (dashboard starter placeholders, login tab order, password toggle keyboard access, minor a11y/copy); production build downloads fonts from fonts.bunny.net (`QUALITY_COMMANDS.md` §12).
- ADRs: none accepted yet. Pending ADR tasks: `X-007` (D-085 identifiers, before P1-003), `X-008` (D-084 money, before P3-009), `X-009` (D-086 characteristics, before P3-001), `X-010` (D-087 / D-075 media, before P2-013). ADR location `docs/architecture/decisions/` (ARCHITECTURE.md §96); lifecycle `AUTONOMOUS_WORKFLOW.md` §14 "ADR tasks".
- Next phase readiness: Phase 1 may start with P1-001. Deterministic order: P1-001 → P1-002 → X-007 (stops for owner acceptance of the identifier ADR) → P1-003 → P1-004 → P1-005 → X-011 → P1-006 … P1-013 → X-012 → P1-014 → P1-015 → P1-016 → P1-017.

Decisions audit (DECISIONS.md):

- Resolved / approved or provisional-in-force: D-001–D-014, D-017–D-068, D-070, D-092; provisional D-015, D-016, D-069, D-071.
- Open, not blocking Phase 1: D-072–D-084 (D-084 only while P1-009 adds no money columns), D-086–D-091 (D-088 only while P1-008 stays with Workspace system roles), new D-093 (Developer Profile ownership, blocks P9-001), new D-094 (personal data retention, ADR_REQUIRED, blocks production launch and Submission export).
- Potentially blocking Phase 1: only D-085 (ADR_REQUIRED), first affected task P1-003; not decided in this task; resolved through `X-007`.

Documentation fixes: stale MustVerifyEmail / Playwright / "future automation" statements (PROJECT_STATE §4, §5, §8; DoD §93–§94; MASTER_PLAN; ARCHITECTURE.md §3, §93; rule 60); PERMISSIONS.md Russian error examples; WEBFLOW_TO_LANDFLOW reusable forms → D-082; SECURITY.md §16 mandatory SSRF policy and §12 Fortify QR SVG safe source; FORMS_AND_INTEGRATIONS SSRF reference; DECISIONS filing (D-015/D-016 provisional, D-070 approved, D-072 open), "Resolved By" lines, stale §10 sequence; QUALITY_COMMANDS §12 font CDN note. Workflow protocol: ADR task lifecycle, `**Resolves:**` field, X- follow-up convention, sequential command-running reviews, Phase Review QA independence, phase transition records, owner-only ADR acceptance. Backlog: P1-003 decision gate and notes; P1-008 / P1-009 / P1-010 / P1-012 notes; X-007–X-012.

Reviews (each by a separate subagent after reading its role file):

- Architect: PASS (ADR required for Phase 0: NO).
- Security: PASS (non-blocking findings → X-011, D-094, SECURITY.md fixes).
- QA (primary validation report): PASS (non-blocking findings → X-011, doc fixes).
- UI reviewer: PASS_WITH_MINOR_NOTES (15 baseline screenshots; notes → X-012).
- Orchestrator workflow validation: REJECTED → REJECTED → PASS (scenarios A–E; X-009 deadlock fixed).
- Final Reviewer: PASS.

Checks on the final state (sequential): `composer quality` PASS; `npm run test:e2e` PASS; link/reference check PASS (remaining unresolved paths are intentionally absent or historical); git safety PASS (only tracked docs / rules / agents modified; `.env`, `.env.e2e`, `database/e2e.sqlite`, build and report artifacts, Wayfinder output ignored and untracked; no secrets in diff). Commit/push: not performed (requires owner authorization).

---

# PHASE 1 — CORE PLATFORM

---

## P1-001 — Audit Authentication Baseline

**Status:** DONE  
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

### Result

Audit only; no application code, config or tests changed. Current behavior is recorded in PROJECT_STATE §5.

Classification of findings:

- Required in P1-002 (section "Required changes from P1-001 audit"):
  - explicit unverified-user route policy; account deletion UI vs `profile.destroy` consistency;
  - 2FA / passkey enrollment requires `verified` (pre-account-takeover);
  - password reset ends other sessions;
  - email change requires re-authentication, sends a new verification notification, lowercases the email;
  - regression tests for all of the above, `verification.send` throttling and Russian verification email.
- Required in existing tasks:
  - `X-011`: shared-props allowlist (keep `email_verified_at`); rate limits for `register.store`, `password.email`, `password.update`, `password.confirm.store`, `profile.destroy`; consider a per-IP login limit;
  - `P1-003` / `P1-005`: sole-Owner guard on account deletion (TENANCY.md §41) and deletion of the user's `sessions` rows;
  - D-094: retention policy for deleted-user personal data (e.g. password reset tokens keyed by email) stays open.
- New follow-up: `X-013 — Production Security Hardening Baseline` (secure cookie / HTTPS / HSTS, security headers, password-change session policy, debug off, trusted proxies, Inertia DevTools / `APP_ENV`, `APP_URL`, `local` disk `serve`).
- Not required (accepted baseline):
  - reset-link "user not found" message: Fortify default, registration's unique check reveals existence anyway (SECURITY.md §26 "where practical", rule 70 §117);
  - passkey login skips the TOTP challenge: a passkey with user verification is a strong factor;
  - non-production password minimum 8: production policy is min 12 with complexity and uncompromised check;
  - no app-level tests for vendor-tested flows (2FA management, challenge codes, passkeys, remember me, password-confirm POST): rule 60 §12 requires tests when these change;
  - password confirmation window 3 hours; login limiter keyed by email + IP with no trusted proxies configured (not spoofable via `X-Forwarded-For`).
- Checked and accepted by security review: CSRF (no exceptions), intended-URL redirect (same host), session fixation (regeneration on every login path), signed + throttled verification link, 2FA limiter per login id, passkey deletion ownership check.

Reviews (separate subagents after reading their role files):

- Primary: backend (audit with file:line evidence, `php artisan route:list --json`).
- Security: REJECTED (pre-account-takeover via unverified 2FA / passkey enrollment and sessions surviving a password reset; unthrottled password-checking routes; email change without re-authentication; missing Result) → fixed in docs → PASS.
- QA: omitted (default, non-trigger review): audit only, no behavior change; the existing suite was run as evidence.
- Final Reviewer: PASS.

Checks: `composer test` PASS (47 tests / 152 assertions, audit evidence); link/reference check PASS; diff secret scan clean; Larastan / Pint / TypeScript / build / Playwright NOT_APPLICABLE (no code, config or tooling change).

Superseded by the Product Owner decision D-095 (2026-09-29, after this audit): Landflow does not support 2FA, TOTP, passkeys or WebAuthn. The 2FA / passkey facts above describe the starter-kit baseline as audited. The pre-account-takeover finding (unverified user enrolls a passkey / TOTP and keeps access after the victim recovers the account) was real for that baseline; its Landflow fix is removing 2FA and passkeys entirely in P1-002, not adding verification checks to enrollment. The "passkey login skips TOTP" note and the 2FA / passkey items in `X-011`, `X-012` and `X-013` are obsolete. Supported sign-in methods: verified email/password and Yandex OAuth with a required email (P1-005A).

---

## P1-002 — Remove 2FA / Passkeys and Enforce Email Verification

**Status:** DONE  
**Dependencies:** P1-001

### Objective

Align the starter authentication with D-095: remove 2FA / TOTP / passkeys / WebAuthn completely and make verified email/password the consistent email/password flow.

### Scope

1. Remove Two-Factor Authentication from the product: Fortify `Features::twoFactorAuthentication` disabled, `TwoFactorAuthenticatable` removed from `User`, 2FA challenge page, settings UI, hooks, requests, controller props, translations and tests that exist only for 2FA.
2. Remove passkeys / WebAuthn: Fortify `Features::passkeys` disabled, passkey config and `PASSKEYS_USER_HANDLE_SECRET`, passkey login button, settings UI, components, helpers, `.well-known/passkey-endpoints` route, translations and tests that exist only for passkeys.
3. Backend routes / features no longer registered; the settings security page keeps only what remains needed (password change) — or is merged, with a documented choice.
4. Remove frontend components, imports and tests that exist only for these features; update Playwright baseline (security page, login page) accordingly.
5. Dependencies: remove npm `@laravel/passkeys` and `input-otp` only after confirming no remaining references (npm uninstall, lockfile updated, diff reviewed). PHP packages `laravel/passkeys`, `pragmarx/google2fa`, `bacon/bacon-qr-code` are transitive dependencies of `laravel/fortify` and stay installed but unused.
6. Schema: a new forward migration drops the `passkeys` table and the `two_factor_*` columns of `users` (with a `down()` that restores the schema); the original migrations are not deleted (architect assessment for D-095, rule `50-database.mdc` §73–§77). Verify no package auto-loads a migration that recreates `passkeys`. No dependency on D-085 (no new identifiers).
7. Email/password registration: verification mandatory; an unverified user has only minimal access. Define and document the allowed unverified routes (at least: verification notice, verification link, resend, logout, profile edit/update needed to fix a mistyped email, account deletion decision — consider D-094). Resend verification works; all messages Russian.
8. Password reset: after a successful reset, end the user's other active sessions (database session driver) if the Laravel architecture allows it safely; otherwise record the reason. Whether a password *change* logs out other sessions stays a decision in `X-013`.
9. Email normalization: store emails trimmed and lowercased on every write path (registration, profile update; Fortify `lowercase_usernames` already covers login / reset lookup) (rule `50-database.mdc` §25).
10. Email change must not allow account takeover: for a password account require the current password (or `password.confirm`) according to the chosen implementation; the new email resets `email_verified_at` and a new verification notification is sent. Notifying the old address: decide and record. Moving email change to a separate task is allowed only with an explicit reason and dependency.
11. Remove the SECURITY.md §12 temporary 2FA QR-code "safe source" entry once the component is gone.

Out of scope: Yandex OAuth (P1-005A); shared-props allowlist and auth rate limits (`X-011`).

### Acceptance Criteria

- no 2FA / TOTP / passkey / WebAuthn route, feature, UI, component or direct npm dependency remains; schema dropped by a forward migration;
- unverified users cannot enter the protected Landflow area; verified users can; allowed unverified routes are documented and tested;
- resend verification works; verification completes the account;
- password reset ends other sessions (or the limitation is recorded with reason);
- emails are stored normalized; email change requires re-authentication and re-verification;
- all auth / verification UI and messages are Russian;
- existing login / registration / reset continue working;
- regression tests (PHPUnit): unverified user denied protected area; verified user allowed; resend verification; verification completes account; passkey routes / UI unavailable; 2FA routes / UI unavailable; password reset invalidates other sessions; email normalization; email change re-auth + re-verification; Russian auth / verification UI; login / register still work;
- Playwright baseline updated (login and security page no longer expect passkey / 2FA UI);
- `composer quality` PASS and `npm run test:e2e` PASS.

### Reviews

Primary: backend, then frontend (full-stack, sequential). Required: security (authentication / access change), qa (auth flow and browser E2E), ui-reviewer (login and settings pages change), final reviewer. Architect pre-assessment for the schema removal is recorded under D-095 (no ADR).

### Result

Completed 2026-09-29 (uncommitted; commit not authorized).

Removed (D-095):
- Fortify `twoFactorAuthentication` and `passkeys` features, the `two-factor` / `passkeys` limiters, the 2FA challenge view, the `fortify.passkeys` config, the `.well-known/passkey-endpoints` route, `TwoFactorAuthenticatable` / `PasskeyAuthenticatable` / `PasskeyUser` on `User`, the 2FA factory state, `TwoFactorAuthenticationRequest`, 2FA / passkey props of `SecurityController`, 2FA / passkey lang strings. Route count 46 → 29; none match two-factor / passkey / webauthn.
- Frontend: 11 files deleted (2FA / passkey components, hook, helper, challenge page, `ui/input-otp`); login, confirm-password and security pages cleaned; npm `@laravel/passkeys` (with `@simplewebauthn/browser`) and `input-otp` uninstalled (lockfile diff: deletions only). PHP packages `laravel/passkeys`, `pragmarx/google2fa`, `bacon/bacon-qr-code` stay as unused Fortify transitive dependencies (discovery exclusion → `X-011`).
- Schema: forward migration `2026_09_29_000001_remove_two_factor_and_passkeys` drops `passkeys` and `users.two_factor_*`; `down()` restores the original definitions; original migrations kept. The packages only publish migrations (no auto-load). Migrate / rollback / migrate / reset verified on a throwaway SQLite file (not covered by PHPUnit; optional test → `X-011`).

Decisions:
- Settings security page keeps only the password change (still behind `RequirePassword`).
- Unverified access allowlist (documented in SECURITY.md §3): verification notice, signed link, resend (`throttle:6,1`), logout, `settings` redirect, `profile.edit` / `profile.update`, Fortify `password.confirm` / `password.confirm.store` / `password.confirmation`. Everything else needs `verified`, including account deletion (D-094; the email-correction path stays open). The profile page shows a neutral explanation instead of the delete block for unverified users.
- Password reset: `ResetUserPassword` deletes all of the user's `sessions` rows when the session driver is `database` (the default); Fortify rotates the remember token. Other drivers: policy in `X-013`.
- Emails stored `lower(trim())` on registration and profile update; uniqueness checked on the normalized value; login / reset lookups covered by Fortify `lowercase_usernames` + `TrimStrings`. No backfill of existing rows (no production data); a legacy mixed-case email would count as changed on the first profile save.
- Email change requires `current_password` (only when the normalized email differs), clears verification, sends `VerifyEmail` to the new address and `EmailChangedNotification` (Russian, informational, no link, no new address) to the old address. `profile.update` is `throttle:6,1` (security review B1).
- Login page: positive `tabIndex` values removed; `Забыли пароль?` moved to the `Запомнить меня` row (row wraps on narrow screens).
- Yandex OAuth not implemented (new schema; P1-005A after `X-007` / D-085 and `X-014` / D-096, D-097).

Checks (orchestrator, final run after all fixes, sequential):
- `composer quality`: PASS — PHPUnit 102/102 (347 assertions; baseline 47), Larastan 0 errors, Pint, Wayfinder, `npm run check`, build.
- `npm run test:e2e`: PASS — 27/27 (setup 1, desktop 18, tablet 4, mobile 4); console / network collector active.

Reviews:
- ui-reviewer: PASS_WITH_MINOR_NOTES — unverified delete subtitle and login row wrap fixed; remaining notes → `X-012`.
- security: REJECTED (B1 `profile.update` throttle, B2 SECURITY.md §12 stale entry) → fixed → PASS; non-blocking → `X-011`, `X-013`.
- qa: PASS — findings 1–3 (weak login negative regex, verification recipient assertion, unverified email-change test) fixed; remaining non-blocking → `X-011`, `X-012`.
- final reviewer: PASS.

Recorded minor notes: `X-011` (passkeys package discovery, array email 500 in Fortify controllers, unverified-route inventory test, old-address notice for unverified old emails, E2E fixed-address fragility and resend check, optional rollback test); `X-012` (verify-email link to profile, redirecting nav for unverified users, input-error ARIA links, extra screenshots, support contact wording); `X-013` (session policy for password / email change and non-database drivers). A now-unused `PASSKEYS_USER_HANDLE_SECRET` may remain in local untracked `.env` files.

---

## P1-003 — Create Workspace Schema

**Status:** DONE  
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

### Decision gate

Satisfied: D-085 APPROVED through `X-007` (ADR-001) — `BIGINT UNSIGNED` `id` + `foreignId`; Workspace and Workspace Membership (first-class entity, not a pivot) get an immutable ULID `public_id`.

### Notes from P0-027 validation

- Owner source of truth: DATABASE.md has `owner_user_id` next to membership roles, PERMISSIONS.md allows "at least one Owner". Define the single authoritative source and the consistency rule.
- Account deletion: `Settings/ProfileController::destroy` calls `$user->delete()`. Define how User deletion affects Workspaces/memberships (TENANCY.md §41 "User Account Deletion"), guard the sole-Owner case, test it (here or in P1-005). Also delete the user's `sessions` rows on account deletion (they have no foreign key and otherwise persist until garbage collection) (P1-001 security review).

### References

- `TENANCY.md`
- `DATABASE.md`

### Result

Completed 2026-09-30 (uncommitted).

- Migrations `2026_09_30_000001_create_workspaces_table` (`id`, ULID `public_id` unique, `name`, `status` default `active` indexed, timestamps) and `2026_09_30_000002_create_workspace_members_table` (`id`, ULID `public_id` unique, `workspace_id` FK cascade, `user_id` FK restrict, `role`, `status` default `active`, `joined_at`, timestamps; unique `workspace_id + user_id`, indexes `workspace_id + role`, `user_id`).
- Enums `App\Enums\WorkspaceStatus` (`active`, `suspended`) and `WorkspaceMemberStatus` (`active`, `invited`, `suspended`); removing a member deletes the row.
- Owner source of truth: membership `role = owner` only (no `workspaces.owner_user_id`); "at least one Owner" enforced in application code (P1-004).
- Account deletion: `user_id` restrict FK makes implicit deletion of a user with memberships fail; the explicit flow (sole-Owner guard, membership removal, `sessions` rows cleanup) moves to P1-005, where memberships are first created. No code path creates memberships yet, so current account deletion is unaffected.
- DATABASE.md §5 updated to the implemented schema.
- Checks: `WorkspaceSchemaTest` 10/10 (columns, defaults, unique public IDs, unique membership, FK existence, cascade on Workspace delete, restrict on User delete); `ProfileUpdateTest` 18/18 and `E2eSeederTest` 2/2 unchanged; Pint and Larastan on changed files PASS; migrate / rollback / migrate on a throwaway SQLite file PASS. Full `composer quality` / Playwright left to CI.
- No security review: schema only, no routes or access paths (ownership enforcement arrives with P1-004 / P1-006 policies).

---

## P1-004 — Workspace Domain Models

**Status:** DONE  
**Dependencies:** P1-003

### Scope

- Workspace model;
- membership model;
- User relationships;
- owner semantics.

### Result

Completed 2026-09-30 (uncommitted).

- `App\Models\Workspace` and `App\Models\WorkspaceMember` with trait `App\Models\Concerns\HasImmutablePublicId` (`HasUlids` + `uniqueIds(): ['public_id']`, integer key kept, route key `public_id`, `public_id` change throws). Numeric `id` (and membership `workspace_id` / `user_id`) hidden from serialization; only `name` (Workspace) and `role` / `status` / `joined_at` (membership) are fillable.
- Enum `App\Enums\WorkspaceRole` (`owner`, `admin`, `designer`, `content_editor`; permission catalog stays in P1-008); role / status cast to enums.
- Relationships: `Workspace::members()`, `owners()` (active Owner memberships), `users()`; `WorkspaceMember::workspace()`, `user()`; `User::memberships()`, `workspaces()`, `activeWorkspaces()`, `activeMembershipIn()`.
- `Workspace::addMember()` is the single creation path (ownership fields from server context; `joined_at` set for active members). Membership `workspace_id` / `user_id` cannot be reassigned.
- Owner semantics: `Workspace::isOwnedBy()`; `WorkspaceMember` model events block demoting, suspending or deleting the last active Owner (`App\Exceptions\LastWorkspaceOwnerException`, Russian message; row locked while checking). Bulk query-builder updates bypass the guard and must not be used for memberships.
- Factories: `WorkspaceFactory` (`suspended`), `WorkspaceMemberFactory` (`owner`, `invited`, `suspended`).
- Checks: `WorkspaceModelTest` 17 + `WorkspaceSchemaTest` 10; with `ProfileUpdateTest`, `RegistrationTest`, `E2eSeederTest`, `LocalizationTest` — 63/63; Pint and Larastan on changed paths PASS. Full suite / Playwright left to CI.
- No security review: no routes or authorization paths yet (policies / Workspace context in P1-006 / P1-008).

---

## P1-005 — Create Default Personal Workspace

**Status:** DONE
**Dependencies:** P1-004

### Objective

New account receives valid initial Workspace according to product flow.

### Acceptance Criteria

- no duplicate accidental Workspace;
- owner membership created transactionally;
- applies to every User creation path through one shared "new account" path (Fortify email/password registration now; Yandex OAuth in P1-005A);
- account deletion handles memberships explicitly (the `workspace_members.user_id` FK restricts implicit deletion, P1-003): sole-Owner guard (TENANCY.md §41), removal of the user's memberships / personal Workspace per the defined rule, deletion of the user's `sessions` rows; tested (moved from P1-003 notes).

### Result

Completed 2026-09-30 (uncommitted).

- `CreateNewAccount` is the shared transactional account path: it creates the User, one personal Workspace named after the user, and an active Owner membership. Fortify registration uses it; P1-005A can reuse it.
- `DeleteUserAccount` resolves memberships transactionally. It deletes an empty single-member personal Workspace, removes memberships when another active Owner remains, and blocks deletion when the user is the sole active Owner of a Workspace with other members. Database sessions are removed explicitly.
- The profile deletion UI shows the Russian ownership-transfer error without logging out a blocked user.
- Targeted checks: PHPUnit 46 tests / 224 assertions PASS; focused shared-Workspace deletion regression 1 test / 4 assertions PASS; Pint targeted PASS; `npm run check` PASS; PHP syntax PASS; PHPStan level 7 PASS (0 errors).

---

## P1-005A — Yandex OAuth Authentication

**Status:** DONE
**Dependencies:** P1-005

### Objective

Implement Yandex OAuth as the second supported sign-in method (D-095).

### Scope

- Separate external identity entity (`user_auth_identities`: `user_id`, `provider`, `provider_user_id`, `provider_email`, timestamps; unique `provider + provider_user_id` and `user_id + provider`), no `yandex_id` on `users` (DATABASE.md "External Auth Identities"); keys per D-085 (internal-only: bigint `id`, no `public_id`).
- Redirect / callback with `state` check, server-side code exchange, client secret in server config (`config/services.php`, env; placeholders only in `.env.example`) (SECURITY.md §3).
- Email required: if Yandex returns no email, create nothing, show a Russian explanation and offer email registration or re-authorization with the required access.
- First sign-in creates the user with `email_verified_at` set server-side (no verification email) and the identity row in one transaction, through the shared new-account path of P1-005 (default Workspace).
- Existing identity → sign in. Email already used by another account → handled only per the accepted D-096 policy; never linked by plain email match.
- Rate limits for redirect / callback (SECURITY.md §24); Russian UI.

### Decision gate

Satisfied: D-096 and D-097 APPROVED through `X-014` / ADR-002; D-085 is APPROVED (`X-007` DONE). Confirm in the current official Yandex ID documentation that the returned email is a confirmed address.

Yandex-only Users must not receive an artificial password. This task owns the nullable `users.password` migration and safe adaptation of password-dependent flows/UI.

### Acceptance Criteria

- mocked-provider Feature tests: new user, returning user, missing email, email collision per D-096, invalid `state`;
- no secret in props, logs or repository;
- security review PASS; real Yandex OAuth smoke test PASS.

### Result

Completed 2026-10-01 (uncommitted).

- Added internal `user_auth_identities` (`BIGINT` keys, no `public_id`, required provider email, cascade to User, both ADR-002 unique constraints) and nullable `users.password`; no OAuth token columns.
- First-party Laravel HTTP client implements Yandex authorization-code exchange and authenticated profile fetch. Redirect/callback use one-time 10-minute session state, PKCE S256, rate limiting, server-only env/config credentials and provider `client_id` verification.
- Known provider identity signs in without changing `users.email`; a new identity with a free normalized email transactionally creates a verified passwordless User, identity, personal Workspace and Owner membership; existing Landflow email is rejected without login/link.
- Russian Yandex buttons were added to login/registration without auth-screen redesign. Passwordless settings expose no current-password form: password addition uses the email-confirmed reset flow; email change/account deletion require adding a password first. Existing password login/settings remain unchanged.
- Official Yandex ID documentation checked 2026-10-01: authorization endpoint supports `state` and PKCE; token exchange uses authorization code; authenticated `/info` returns stable `id`, application `client_id` and `default_email` when email access is granted.
- Targeted checks: PHPUnit 54 tests / 320 assertions PASS; Pint targeted PASS; PHPStan changed PHP surface PASS (0 errors); `npm run check` PASS.
- Security-focused self-review: PASS after removing direct first-password creation from an old OAuth session.
- Real Yandex OAuth smoke test: PASS — new Yandex User, repeat login through the existing identity, and email collision without automatic login/link or extra account/Workspace records. `BLOCKED_EXTERNAL` resolved 2026-10-01.

---

## P1-006 — Workspace Context / Switcher Backend

**Status:** DONE
**Dependencies:** P1-004

### Scope

- resolve active Workspace;
- validate membership;
- remember last Workspace safely.

### Result

- Request-scoped Workspace context resolves only active Workspaces reached through the authenticated User's active membership and stores only the selected Workspace `public_id` in session.
- Switching is a CSRF-protected verified-user POST using a ULID route parameter; foreign, suspended and nonexistent Workspaces receive the same 404 response.
- Invalid remembered context falls back deterministically to the first accessible Workspace; Dashboard redirects home when none exists.
- Shared Inertia context contains only `public_id` / name summaries for accessible Workspaces; numeric IDs, foreign Workspaces and role/permission behavior are not exposed or introduced.
- Focused tenant-isolation tests, affected Dashboard/auth tests, Pint and PHPStan pass.

---

## P1-007 — Workspace Switcher UI

**Status:** DONE
**Dependencies:** P1-006

### Acceptance Criteria

- only accessible Workspaces appear;
- switching changes Dashboard context;
- removed membership invalidates old context.

### Result

- Added a Russian, keyboard-accessible shadcn dropdown in the existing sidebar using only safe shared Workspace summaries and `public_id`.
- The selected Workspace is posted to the P1-006 endpoint through the generated Wayfinder action; pending state disables repeat switching and the refreshed shared context updates the label.
- A single Workspace is rendered as non-interactive current context; multiple Workspaces expose only the backend-provided accessible list.
- Focused browser coverage verifies current/list/single/switch/persistence/public-ID behavior; local execution was blocked before browser startup by the sandbox system-temp restriction, while PHPUnit, Pint, PHPStan and `npm run check` pass.

---

## P1-008 — Permission Foundation

**Status:** DONE
**Dependencies:** P1-004

### Objective

Implement initial system roles/permission catalog.

### Initial roles

- Owner
- Admin
- Designer
- Content Editor

### Notes from P0-027 validation

- Permission key names conflict across docs (`edit_integrations` vs `manage_integrations`, `delete_sites` vs `delete_site`): record the canonical names in DECISIONS and align the docs as an acceptance criterion.
- Workspace system roles only; Site-specific overrides wait for D-088.

### Result

Added a centralized Workspace permission enum, role resolver, current-Workspace authorization service, backend Gates, and safe Inertia permission keys. Active membership in the server-resolved current Workspace is required; permissions default to deny. Canonical keys are recorded in D-098. Site-specific overrides and entitlements remain separate and out of scope.

---

## P1-009 — Entitlement Foundation

**Status:** DONE
**Dependencies:** P1-004

### Scope

- Plan model/configuration;
- entitlement resolver;
- `max_sites`;
- future feature keys.

No real billing provider. No plan price or other money columns (D-084 / X-008 unresolved).

### Result

Added internal Plan and typed Plan Entitlement models, nullable Workspace plan assignment, and a centralized resolver for boolean capabilities and numeric limits. The initial catalog is limited to `max_sites`, `max_members`, `custom_domain`, and `remove_branding`; missing or inactive values deny safely. Billing providers, prices, subscriptions, and plan-name business checks remain out of scope.

---

## P1-010 — Site Schema

**Status:** NOT_STARTED  
**Dependencies:** P1-003, P1-009

### Scope

- Sites;
- Site folders if included now;
- status;
- Workspace ownership.

### Notes from P0-027 validation

- No publication pointer columns (`current_draft_version_id`, `current_published_version_id`) before the snapshot ADR (X-002).
- A Site folder must belong to the same Workspace as the Site (TENANCY.md).

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

### Note from P0-027 validation

Pages and Block Instances arrive in P2-001 (Phase 2). Before implementation, the Architect defines what a Template instantiates in Phase 1 (P1-012/P1-013 "Site-owned initial structure") so that P1-013 does not create Page/Block schema ahead of P2-001.

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

## X-007 — ADR: Primary Identifier Strategy

**Status:** DONE
**Trigger:** before P1-003
**Resolves:** D-085

The repository has only framework-default keys (`id()` on users, jobs; the starter `passkeys` table is removed in P1-002, D-095); no project identifier standard exists (DATABASE.md "to be finalized"). P1-003 is the first core domain migration.

Decision must cover:

- primary key type for domain tables (bigint / UUID / ULID / mixed internal + public IDs);
- whether framework tables (users, sessions, jobs) keep their keys, and the `users.id` foreign-key convention (also used by the planned `user_auth_identities`, P1-005A);
- public identifiers: which entities need one (Site, Form, Site Vehicle / Offer references, Publication) and their format;
- route-binding keys for the authenticated app vs public runtime;
- SQLite test and production engine compatibility;
- Laravel conventions to use (`foreignId` / `foreignUlid`, `HasUuids` / `HasUlids`).

Lifecycle: agents draft the ADR in `docs/architecture/decisions/`; only the owner accepts it (rule `10-architecture.mdc`).

### Result

2026-09-30: `docs/architecture/decisions/ADR-001-primary-identifier-strategy.md` Accepted by the owner — Option B (the draft recommended ULID primary keys; the owner chose mixed IDs). D-085 APPROVED. Convention: bigint `id()` + `foreignId` everywhere; externally addressed entities add a unique ULID `public_id` (`HasUlids` + `uniqueIds(): ['public_id']`), bind routes by it and never expose internal `id`; internal-only tables have no `public_id`; users have none for now; secrets use separate tokens. Recorded in rule `50-database.mdc`, DATABASE.md §2 / §95 / External Auth Identities. Docs only; no checks required. P1-003 unblocked.

---

## X-008 — ADR: Money Storage Representation

**Status:** NOT_STARTED  
**Trigger:** before P3-009  
**Resolves:** D-084

Decision must cover: integer minor units vs fixed decimal, currency representation, rounding, Block price field presentation. Until accepted, no money columns anywhere (including P1-009 Plans).

---

## X-009 — ADR: Characteristic Value Schema

**Status:** NOT_STARTED  
**Trigger:** before P3-001  
**Resolves:** D-086

Decision must cover: storage of characteristic values across Generation / Modification / Trim and override/inheritance resolution.

---

## X-010 — ADR: Media Ownership and Asset Versioning

**Status:** NOT_STARTED  
**Trigger:** before P2-013  
**Resolves:** D-087, D-075

Decision must cover: Site asset vs Workspace asset ownership/reference model, immutable/versioned public asset strategy compatible with Published Version stability.

---

## X-011 — Foundation Hygiene Follow-ups (from P0-027)

**Status:** DONE
**Trigger:** before P1-006

Non-blocking findings of the Phase 0 validation reviews. P1-006 is the first task that adds Workspace context to shared Inertia props, so the shared-props allowlist must exist before it. Primary: backend (frontend for the Playwright fixture item); reviews: security, qa, reviewer.

Scope:

- `HandleInertiaRequests` shares the full `User` model: replace with an explicit safe field allowlist matching `resources/js/types/auth.ts` (keep `email_verified_at` or an equivalent verification flag used by `settings/profile.tsx`); Feature test that password / remember-token columns never appear in shared props (2FA columns are removed in P1-002) (security review);
- Fortify registers `register.store`, `password.email` and `password.update` (reset-password POST) without a route rate limit (only the per-email reset-token throttle exists); SECURITY.md §24 requires limits for registration and password reset. `password.confirm.store` and `profile.destroy` check the current password without any throttle (password guessing from a hijacked session). Add named limiters to all five routes with a Russian 429 message and tests; consider an additional per-IP login limit against password spraying (the `login` limiter is keyed by email + IP) (P1-001 audit and security review);
- `.gitignore`: ignore `.env` and `.env.*` with exceptions `!.env.example`, `!.env.e2e.example` (`.env.local`, `.env.testing`, `.env.staging` are currently not ignored) (security review);
- `DatabaseSeeder` creates `test@example.com` / `password` without an environment guard: refuse outside `local`/`testing`, with a test (security review);
- Playwright fixtures detect 4xx/5xx responses but not network-level failures: add a `requestfailed` collector with a narrow documented exception for aborted superseded navigations (rule `80-browser-qa.mdc` §21) (QA review);
- starter-kit SSR leftovers: `build:ssr` script without `resources/js/ssr.tsx`, `config/inertia.php` SSR `enabled => true` — remove / disable per D-069 (QA review);
- `composer.json` `allow-plugins` still lists `pestphp/pest-plugin` although Pest is forbidden (D-011) — remove (QA review).
- From P1-002 reviews:
  - `laravel/passkeys` (transitive via Fortify) is still auto-discovered (global `passkey` route binding, merged config; no routes / migrations): exclude it from package discovery (`composer.json` `extra.laravel.dont-discover`) and confirm nothing breaks (security review);
  - Fortify vendor controllers (register, login, reset link, reset POST) call `Str::lower()` on the raw email before validation, so an array `email` returns 500: handle safely (e.g. request middleware / validation before Fortify) with tests (security review);
  - route inventory test: every `auth` route without `verified` must be in the SECURITY.md §3 unverified allowlist (incl. `password.confirmation`); add an unverified `password.confirm.store` submit test (QA / reviewer);
  - `EmailChangedNotification` is also sent to an old address that was never verified (possibly a stranger's mistyped address): consider skipping it for unverified old addresses (reviewer);
  - E2E unverified-user flow registers a fixed address and breaks on `--repeat-each` / command-line retries against the same server: seed an unverified user in `E2eSeeder` or derive a unique suffix; add an E2E check of the resend-verification success message (QA review);
  - migration `2026_09_29_000001_remove_two_factor_and_passkeys` `down()` is verified only manually on a throwaway SQLite file: optionally add a rollback test (QA review).

Acceptance Criteria:

- each item fixed or explicitly re-classified with reason;
- tests added where listed;
- `composer quality` and `npm run test:e2e` PASS;
- security review (shared props, seeder, gitignore, auth rate limits).

### Result

- Shared Inertia User data now uses the explicit `name`, `email`, `email_verified_at` allowlist; a Feature test excludes internal IDs, password, remember token and timestamps.
- Named five-per-minute Russian-response limiters protect registration, reset-link request, reset submit, password confirmation and account deletion; login also has a 20-per-minute IP-wide spraying limit.
- `.env.*` is ignored except the two committed examples; `DatabaseSeeder` refuses environments other than `local` / `testing`.
- Playwright records network failures and ignores only superseded navigation `net::ERR_ABORTED`; the unverified flow uses seeded data and checks resend success.
- SSR is disabled and the stale `build:ssr` command removed; the forbidden Pest plugin allow-entry was removed.
- `laravel/passkeys` remains a Fortify transitive dependency but is excluded from Laravel package discovery.
- Structured email inputs on all four affected Fortify endpoints produce validation errors instead of 500 responses.
- The unverified-route inventory and password-confirm submit are covered; notices to an old email are sent only when that old address was verified.
- The optional migration rollback automation was re-classified as unnecessary: the reversible migration was already manually round-tripped on throwaway SQLite, and no migration behavior changed in X-011.
- Lean verification used focused PHPUnit, Pint, PHPStan and `npm run check`; full `composer quality` / Playwright remain CI gates and were intentionally not run locally per the task instruction.

---

## X-012 — Foundation UI Follow-ups (from P0-027)

**Status:** NOT_STARTED  
**Trigger:** before P1-014

Non-blocking findings of the Phase 0 UI review (screenshots of the P0-024 baseline). P1-014 is the first task that reworks the Dashboard, so the starter scaffolding and the foundation accessibility gaps are fixed before it. Primary: frontend; reviews: ui-reviewer, qa (keyboard / tab order), reviewer.

Scope:

- `resources/js/pages/dashboard.tsx` still renders starter `PlaceholderPattern` boxes without a heading: replace with a `Heading` `Панель управления` and one short neutral Russian empty-state line (no feature promises);
- ~~`resources/js/pages/auth/login.tsx` positive `tabIndex` values~~ — resolved in P1-002 (removed; `Забыли пароль?` moved to the `Запомнить меня` row); `register.tsx` still has positive `tabIndex` values in DOM order: remove for consistency;
- `resources/js/components/password-input.tsx` show/hide button has `tabIndex={-1}`: make it keyboard reachable;
- `resources/js/layouts/auth/auth-simple-layout.tsx` home logo link announces `Вход в аккаунт`: give it an accurate accessible name;
- login status message renders below the form with hardcoded `text-green-600`: move above the form, use theme token / `Alert`;
- `resources/js/pages/settings/profile.tsx` breadcrumb `Настройки профиля` is inconsistent with sibling settings pages: use `Профиль`;
- `resources/js/layouts/settings/layout.tsx` active settings link indicated by color only: add `aria-current="page"`.
- From P1-002 reviews:
  - verify-email page has no link to profile settings (the mistyped-email fix path is reachable only by URL): add a Russian link;
  - unverified users see sidebar `Панель управления` and settings `Безопасность` / `Внешний вид` links that silently redirect to the verification notice: hide, disable or explain;
  - validation errors are not linked to their inputs (`aria-describedby` / `aria-invalid`), app-wide;
  - add screenshots of the confirm-password page and of the mobile email-change / unverified profile states; verify the login `Запомнить меня` row wrap at ~320 px;
  - `EmailChangedNotification` says `обратитесь в службу поддержки` while no support contact is defined: revisit before launch together with `X-013`.

Acceptance Criteria:

- each item fixed or explicitly re-classified with reason;
- all UI remains Russian;
- `composer quality` and `npm run test:e2e` PASS (update baseline selectors/screenshots if affected);
- ui-reviewer review of updated screenshots.

---

## X-013 — Production Security Hardening Baseline (from P1-001)

**Status:** NOT_STARTED  
**Trigger:** before the first production deployment

No BACKLOG task covers production security configuration yet; P1-001 found items that are acceptable locally but must be settled before production. Primary: backend; reviews: security, reviewer; architect if infrastructure is introduced.

Scope:

- secure session cookie (`SESSION_SECURE_COOKIE`), HTTPS enforcement and HSTS where the domain setup permits (rule `70-security.mdc` §12, §69);
- centralized security headers for the authenticated app (rule `70-security.mdc` §68–§69);
- decide whether a password change or an email change logs out other sessions (SECURITY.md §4); P1-002 ends all database sessions on password reset only for the `database` session driver — decide the policy for other drivers (e.g. Laravel `auth.session` middleware) if the production driver changes;
- support contact referenced by the email-changed notice (`X-012`);
- confirm `APP_DEBUG=false` and safe error pages in production (rule `70-security.mdc` §101);
- trusted proxies for the production load balancer (otherwise all users share one IP and per-IP rate limits break);
- `APP_ENV=production` and `INERTIA_DEVTOOLS_ENABLED` unset on every internet-facing environment including staging (the Inertia DevTools routes `_inertia/devtools/entries*` are open without login when `APP_ENV=local`);
- `APP_URL` is the HTTPS production origin;
- production Yandex OAuth redirect URI and client secret configured server-side only (P1-005A);
- the `local` disk has `serve => true` (`config/filesystems.php`), which registers `storage/{path}` and a signed upload route `PUT storage/{path}`: disable or confirm safe before production, and review in the first media/upload task;
- reference D-094 (personal data retention) as the related launch blocker.

Acceptance Criteria:

- each item implemented or explicitly decided with reason;
- tests where behavior changes;
- security review PASS.

---

## X-014 — Decision: OAuth Account Linking and Yandex Client

**Status:** DONE  
**Trigger:** before P1-005A  
**Resolves:** D-096, D-097

Yandex OAuth (D-095) must not link an external identity to an existing Landflow account by plain email match. Architect and Security draft `docs/architecture/decisions/ADR-NNN-<slug>.md` covering: the account-linking flow for an email collision (never auto-link / link after proving control of the existing account / link only from a signed-in session / other), OAuth-only users without a password (password reset, email change, account deletion), and the Yandex client implementation (Socialite + provider vs in-repo provider vs Laravel HTTP client; package evaluation per rule `00-project-core.mdc` §8). Lifecycle: agents draft, only the owner accepts (rule `10-architecture.mdc`). Does not block P1-002.

### Result

Completed 2026-09-30 (docs only; owner decisions).

- D-096 APPROVED: no email-based auto-link; known provider identity signs in; new identity creates a User only when email is free; collisions require authenticated explicit linking; provider ID is authoritative; provider email changes do not mutate `users.email`; identity cardinality and last-sign-in-method safeguards defined.
- D-097 APPROVED: first-party adapter on Laravel HTTP client; server-only credentials, no persisted one-use access token; internal identity table with the approved unique constraints.
- ADR-002 accepted. P1-005A must support Yandex-only Users through nullable passwords and adapted password flows/UI; it must not generate artificial passwords.
- Tests: NOT_APPLICABLE (docs-only decision task).

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

Exception: cross-cutting tasks (ADR tasks and follow-ups that must precede a specific task) use the `X-` prefix and a `**Trigger:** before <ID>` line instead of a phase ID and dependencies; ADR tasks also name the decisions they resolve in `**Resolves:**`; agents draft ADRs, only the owner accepts them.

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
P0-022  Quality Commands                                         DONE
P0-023  Playwright                                               DONE
P0-024  Browser QA Baseline                                      DONE
P0-025  CI                                                       DONE
P0-026  Autonomous Workflow                                      DONE
P0-027  Phase 0 Validation                                       DONE
```

Phase 0 is COMPLETED. Phase 1 — Core Platform:

```text
P1-001  Audit Authentication Baseline                            DONE
P1-002  Remove 2FA / Passkeys and Enforce Email Verification     DONE
X-007   ADR: Primary Identifier Strategy (before P1-003)          DONE (ADR-001 accepted, Option B)
P1-003  Create Workspace Schema                                  DONE
P1-004  Workspace Domain Models                                  DONE
P1-005  Create Default Personal Workspace                        DONE
X-014   Decision: OAuth Account Linking and Yandex Client         DONE (ADR-002 accepted)
P1-005A Yandex OAuth Authentication                               DONE
X-011   Foundation Hygiene Follow-ups                             next
```

---

# 13. Final Backlog Principle

**The backlog is executable, not aspirational.  
Each task must be small, dependency-aware, testable, reviewable, and grounded in approved architecture.  
Cursor should never choose a random future feature while a required prerequisite remains unfinished.**
