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

Phase 1 — Core Platform: COMPLETED (gate `P1-017` DONE).

Phase 2 — Designer Foundation: COMPLETED (gate `P2-018` DONE).

Phase 3 — Automotive Foundation: COMPLETED (gate `P3-017` DONE; Catalog V2 schema adopted as `docs/architecture/AUTO_CATALOG_SCHEMA.md`).

Phase 4 — Forms & Interactive Components: COMPLETED (gate `P4-014` DONE).

Phase 5 — Publishing: COMPLETED (gate `P5-012` DONE, branch `autopilot/phase5-2026-10-05`).

Phase 6 — Integrations & Analytics: COMPLETED (gate `P6-015` DONE, branch `autopilot/phase6-2026-10-05`).

Phase 7 — Paid Site Features: NOT_STARTED. Cross-cutting `X-022 — Workspace / Site Dashboard Navigation` runs before P7-001.

Next ready task: `P7-001 — Custom Domain Schema`. Non-blocking follow-up: `X-017` (storage quota, before production).

Resolved stops: `X-014`, P1-005A, `X-011`, `X-012` and `X-015` (default Free plan, D-100) are DONE. Before the first production deployment: `X-013` and D-094.

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

**Status:** DONE
**Dependencies:** P1-003, P1-009

### Scope

- Sites;
- Site folders if included now;
- status;
- Workspace ownership.

### Notes from P0-027 validation

- No publication pointer columns (`current_draft_version_id`, `current_published_version_id`) before the snapshot ADR (X-002).
- A Site folder must belong to the same Workspace as the Site (TENANCY.md).

### Result

Added the minimal `sites` table with bigint internal keys, unique ULID `public_id`, required Workspace ownership, `name`, `active`/`archived` status, and timestamps. Workspace hard deletion is restricted while Sites exist. Site folders and feature-specific columns remain deferred.

---

## P1-011 — Site Domain Models and Policies

**Status:** DONE
**Dependencies:** P1-010, P1-008

### Acceptance Criteria

- foreign Workspace Site access denied;
- Site ownership resolved through Workspace.

### Result

Added the Site model with immutable public ULID binding, safe serialization, Workspace relationships and immutable tenant ownership. SitePolicy delegates canonical `view_site`, `create_sites`, `edit_site_settings` and `delete_site` checks to the current-Workspace authorization foundation; foreign and inactive memberships deny by default.

---

## P1-012 — Template Foundation

**Status:** DONE

**Dependencies:** P1-011

### Scope

- official Template model;
- Template version baseline;
- Blank Template;
- one starter Template if practical.

### Note from P0-027 validation

Pages and Block Instances arrive in P2-001 (Phase 2). Before implementation, the Architect defines what a Template instantiates in Phase 1 (P1-012/P1-013 "Site-owned initial structure") so that P1-013 does not create Page/Block schema ahead of P2-001.

### Result

Added the global official Template model and schema with immutable public ULID binding, safe serialization and a separate internal Template version baseline. An idempotent seeder provides the official Blank Template at version `1.0.0`; Page/Block manifests, Site linkage, lifecycle statuses, pricing and Marketplace fields remain deferred to their owning tasks.

---

## P1-013 — Create Site Flow Backend

**Status:** DONE

**Dependencies:** P1-011, P1-012

### Acceptance Criteria

- validates `max_sites`;
- chooses Template;
- creates Site-owned initial structure;
- tenant-safe.

### Result

Added a verified-auth backend endpoint for Site creation in the current Workspace. The flow authorizes canonical `create_sites`, enforces the effective `max_sites` entitlement against active Sites only (D-099), accepts only an available official Template by public ULID, serializes concurrent creation through a Workspace row lock and creates the active Site root transactionally without storing a live Template dependency or premature Page/Block state.

---

## P1-014 — Dashboard UI

**Status:** DONE

**Dependencies:** P1-007, P1-013

### Scope

- Workspace header;
- Sites grid;
- create Site;
- empty state;
- Site cards.

### Result

Replaced the starter Dashboard placeholders with a responsive current-Workspace header, tenant-scoped Site cards, localized active/archived states and a Russian empty state. The backend supplies only explicit safe Site props, applies the Site view policy, exposes safe create-permission/active-limit state and never sends numeric identifiers; the create CTA is shown only with `create_sites` and remains non-navigating until the P1-015 wizard provides an approved destination.

---

## P1-015 — Create Site Wizard UI

**Status:** DONE
**Dependencies:** P1-013, P1-014

### Flow

- Create Site;
- choose Template;
- name;
- create;
- open Site.

### Result

Added the verified, Workspace-context `sites.create` page authorized by the Site `create` policy. It lists only official Templates (public ULID and name), shows the backend-derived active-Site limit and posts to the P1-013 endpoint with Russian, input-linked validation errors. The Dashboard CTA now links to the wizard unless the limit is reached. Until a Site workspace exists (Designer, P2-006), "open Site" returns to the Dashboard, which confirms creation and highlights the new Site card only when it belongs to the current Workspace list. Checks: focused PHPUnit (Sites + Dashboard), Pint, PHPStan, `npm run check`, focused desktop Playwright dashboard spec.

---

## P1-016 — Core Platform E2E

**Status:** DONE
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

### Result

Added `tests/browser/core-platform.spec.ts`: a seeded Owner logs in, opens the wizard from the Dashboard, chooses the official Blank Template, names and creates a Site, and sees it confirmed in the Dashboard with a ULID-only Site reference. After switching to the user's second Workspace the Site is absent, and replaying its public ID in the Dashboard URL neither reveals nor confirms it. `E2eSeeder` adds an isolated creator with two Workspaces on a test-only plan and seeds the official Templates. The flow starts from Login, not Registration: newly registered Workspaces have no plan and therefore `max_sites = 0` until the owner defines a default plan (see open decision in PROJECT_STATE §42). Checks: focused desktop Playwright (core platform + dashboard), `E2eSeederTest`, Pint, PHPStan, `npm run check`.

---

## P1-017 — Phase 1 Review

**Status:** DONE
**Dependencies:** P1-001 through P1-016

### Acceptance Criteria

All Phase 1 DoD gates pass.

### Result

2026-10-03 (autonomous night batch). P1-001 … P1-016 and the Phase 1 X-tasks (X-007, X-011, X-012, X-014) are DONE. Full gates on the final Phase 1 state: `composer quality` PASS (PHPUnit 232/232, PHPStan, Pint, `npm run check`, build) and `npm run test:e2e` PASS (37/37). Review was a single primary-agent risk review per the owner's batch instruction (no reviewer subagents): tenancy (Workspace-derived Site ownership, policy-guarded create page and endpoint, cross-Workspace isolation covered in PHPUnit and E2E), identifiers (ULID-only Site/Template references in props and URLs), Russian UI and safe Inertia props verified. Known limitations, not blocking the gate: a newly created Workspace has no plan, so `max_sites = 0` until the owner defines the default plan (open owner decision, PROJECT_STATE §42); `X-013` and D-094 remain launch blockers. Phase 2 has no open ADR/decision that directly blocks P2-001 … P2-006 (D-087 media → before P2-013).

---

# PHASE 2 — DESIGNER FOUNDATION

---

## P2-001 — Page Schema and Models

**Status:** DONE
**Dependencies:** P1-017

### Scope

- Pages;
- home Page;
- slug;
- order;
- Site ownership.

### Result

Added the Site-owned `pages` table and `Page` model: bigint key, immutable ULID `public_id`, immutable Site ownership, `title`, Site-unique `slug`, `sort_order` and `is_home`. `is_home` is stored as TRUE/NULL so the `(site_id, is_home)` unique index guarantees at most one home Page per Site on MySQL and SQLite. Site creation now creates the home Page (`Главная`, slug `home`) in the same transaction, and the migration backfills a home Page for existing Sites (insert-only). Site deletion is restricted while Pages exist (deletion workflow not decided). `parent_id`, Page `status` and `deleted_at` from DATABASE.md §12 are deferred to the tasks that define their behavior. Checks: focused PHPUnit (Pages, Sites, Database), PHPStan, Pint. Development MySQL needs `php artisan migrate`.

---

## P2-002 — Block Definition / Version Schema

**Status:** DONE
**Dependencies:** P2-001

### References

- `BLOCK_SYSTEM.md`
- `DATABASE.md`

### Result

Added global `block_definitions` (bigint key, immutable ULID `public_id`, name, unique slug, explicit `is_official` scope) and `block_versions` (definition FK with restricted delete, per-definition unique version string, JSON `schema_json`, `created_at` only). `BlockVersion` is immutable at the model level; a change requires a new version. Developer ownership (D-093 open), Workspace-private scope, renderer reference, lifecycle statuses and `current_version_id` are deferred to the tasks that define them; schema validation is P2-003. Checks: `BlockFoundationTest`, PHPStan, Pint.

---

## P2-003 — Block Schema Validator

**Status:** DONE
**Dependencies:** P2-002

### Initial field types

- text
- textarea
- boolean
- select
- image
- group
- repeater

### Result

Added `App\Blocks\BlockSchemaValidator` and the `BlockFieldType` enum for the seven initial types. Schema syntax: `{"fields": [...]}`; each field has a snake_case `key` unique on its level, a supported `type`, a `label`, optional `help`, and only the options its type allows (unknown keys are rejected). Per type: text/textarea `max_length` (≤255 / ≤5000) and string `default`; boolean default; select non-empty unique `options` with a default from them; image declaration without default (asset reference format waits for X-010); group/repeater require nested fields, repeater requires `max_items` (1–50) with `min_items` ≤ max. Limits: container depth 3, repeater nesting 2; `id` is reserved inside Repeater items for stable item identity. Errors are Russian and keyed by schema path; Block Versions validate their schema on creation. Conditional fields stay out of the initial scope. Checks: validator unit tests, `BlockFoundationTest`, PHPStan, Pint.

---

## P2-004 — Block Instance Schema

**Status:** DONE
**Dependencies:** P2-002, P2-003

### Acceptance Criteria

- pinned Block Version;
- validated JSON state;
- Site/Page ownership.

### Result

Added `page_blocks` (`App\Models\BlockInstance`): bigint `id`, immutable public ULID, `page_id` and `block_version_id` (both restrict on delete, both immutable after creation), `sort_order` and draft `state_json`; internal IDs are hidden from serialization. Ownership is Site → Page → Block Instance; `Page::blocks()` returns instances in order. Only official Block Definitions can be placed. `App\Blocks\BlockStateValidator` validates state against the pinned Block Version schema on every save: unknown keys are rejected, values must match their field type (text/textarea `max_length`, select option, group object, repeater list ≤ `max_items` with unique ULID item `id`), missing keys and `null` are allowed for incomplete drafts; `required`/`min_items` are left for publish-time validation, and non-null image values are rejected until the asset reference format (X-010) exists. Errors are Russian and keyed by state path. Checks: validator unit tests, `BlockInstanceTest`, Pages/Blocks feature tests, PHPStan, Pint.

---

## P2-005 — Initial Official Blocks

**Status:** DONE
**Dependencies:** P2-004

### Blocks

- Header
- Hero
- Benefits
- CTA
- Contacts
- Footer

### Result

`App\Blocks\OfficialBlockCatalog` defines the six official Blocks (slugs `header`, `hero`, `benefits`, `cta`, `contacts`, `footer`; Russian names) with version `1.0.0` schemas built only from the P2-003 field types (texts with limits and Russian defaults, button groups, menu/benefit/link repeaters, select options for alignment, columns and style). The idempotent `OfficialBlockSeeder` (called by `DatabaseSeeder` and `E2eSeeder`) validates each schema, upserts the official Definition by slug and only creates missing versions — existing versions are never changed. Frontend renderers live in `resources/js/blocks` and are resolved by Definition slug for the Designer canvas; they render text only (button links wait for the Action System P2-014, images for Assets P2-013) and use neutral styling until Site Design Tokens (P2-012). Checks: `OfficialBlocksTest`, Blocks/Templates/E2E seeder tests, PHPStan, Pint, `npm run check`.

---

## P2-006 — Designer Shell

**Status:** DONE
**Dependencies:** P2-004

### UI

- top bar;
- left panel;
- canvas;
- right Properties Panel.

### Result

Added `GET /sites/{site}/designer` (`sites.designer`, public ULID only, current-Workspace context required). A Site outside the current Workspace returns 404 even for a member of its Workspace; access is authorized by the Site `view` policy. Props are explicit and safe: Site public ID/name, the home Page public ID/title and its ordered Block Instances (public ID, Definition slug/name, pinned version, draft state). The full-screen Russian shell has a top bar (back to Dashboard, Site name, page title, "Черновик"), a left panel with the page's block list, a canvas rendering official Blocks through the P2-005 renderers with click/keyboard selection, and a read-only right "Свойства" panel showing the selected Block and version. Dashboard Site cards link to the Designer. Pages Panel, Navigator actions, schema-driven property editing, adding Blocks, autosave and preview remain in P2-007+. Checks: `SiteDesignerTest`, Sites/Dashboard feature tests, PHPStan, Pint, `npm run check`, focused desktop Playwright (core platform flow now opens the Designer).

---

## P2-007 — Pages Panel

**Status:** DONE
**Dependencies:** P2-001, P2-006

### Result

Designer left panel "Страницы": list (home first, then sort order), open a page (`?page=<public_id>`), create, rename and delete Pages through `sites.pages.store/update/destroy`. Slugs are validated (`a-z0-9` with hyphens, unique per Site) or generated from the Russian title with a numeric suffix; the home Page keeps its slug and cannot be deleted; deleting a Page removes its Block Instances in one transaction. All Designer resources resolve through `DesignerScope` (current Workspace only, foreign IDs → 404 before validation) and page management requires `edit_design`. Checks: `SitePagesTest`, Sites feature tests, PHPStan, Pint, `npm run check`.

---

## P2-008 — Navigator

**Status:** DONE
**Dependencies:** P2-004, P2-006

### Actions

- select;
- reorder;
- duplicate;
- hide/show;
- delete.

### Result

Designer "Блоки" tab: Navigator over the page's Block Instances (select, move up/down, duplicate after the source, hide/show via new `page_blocks.is_hidden`, delete with confirmation) plus an "Добавить блок" list of official Blocks. A new instance pins the latest version of an official Definition and starts from its schema defaults; order stays dense (0..n-1) through `ArrangePageBlocks`. Structure changes require `edit_design`; foreign Blocks/Pages return 404. Hidden Blocks stay visible but dimmed on the canvas. Checks: `PageBlocksTest`, Sites/Blocks feature tests, PHPStan, Pint, `npm run check`.

---

## P2-009 — Properties Panel from Schema

**Status:** DONE
**Dependencies:** P2-003, P2-006

### Acceptance Criteria

No one-off settings UI per official Block.

### Result

The right "Свойства" panel renders controls generically from the pinned Block Version schema (text, textarea, boolean, select, nested group) with labels, help, length limits and Russian errors linked to inputs. Edits update a local draft that the canvas renders immediately; "Сохранить" sends the draft to `sites.blocks.state` (`edit_content`), where `BlockStateValidator` errors are returned per state path. Repeater, image and conditional controls follow in P2-010/P2-013/P2-011; autosave replaces the button in P2-015. Checks: `PageBlocksTest`, Sites feature tests, PHPStan, Pint, `npm run check`.

---

## P2-010 — Repeater Editing

**Status:** DONE
**Dependencies:** P2-009

### Acceptance Criteria

- add;
- delete;
- duplicate;
- reorder;
- stable item IDs.

### Result

Repeater fields in the Properties panel support add (with item field defaults), delete, duplicate (new ID) and move up/down, limited by the schema `max_items`, with nested item fields and per-item errors. Each item gets a client-generated ULID `id` that survives edits and reordering; the backend validator still enforces ULID format, uniqueness and limits. Checks: `PageBlocksTest` (order and IDs persist, duplicate IDs rejected), `npm run check`.

---

## P2-011 — Conditional Schema Fields

**Status:** DONE
**Dependencies:** P2-009

### Result

Any schema field may declare `visible_if: {"field": key, "equals": value}`. The rule may reference only an earlier boolean/select sibling on the same level; `equals` must be a boolean or one of that select's option values. `BlockSchemaValidator` enforces this with Russian path errors. The Properties panel hides fields whose condition is not met; their stored values stay in state (draft data is never silently dropped). The official catalog now supports several versions per slug; `header` 1.1.0 shows «Телефон» only when «Показывать телефон» is on. Existing 1.0.0 instances stay pinned, and new placements use the latest version. Checks: schema validator unit tests, `OfficialBlocksTest`, designer/block feature tests, PHPStan, Pint, `npm run check`.

---

## P2-012 — Site Design Tokens

**Status:** DONE
**Dependencies:** P2-006

### Initial tokens

- primary/secondary colors;
- typography;
- radius;
- container;
- buttons.

### Result

`sites.design_tokens` (nullable JSON) stores a fixed token set: `primary_color` and `secondary_color` as lower-case `#rrggbb`, `font_family` sans/serif, `radius` none/small/medium/large, `container` narrow/default/wide, `button_style` solid/outline. There is no custom CSS. `App\Support\SiteDesignTokens` owns the defaults and rules, and its `resolve()` falls back to defaults for missing or invalid stored values. `PATCH sites/{site}/design` (`UpdateSiteDesignRequest`: Workspace scope 404 before validation, then `editDesign`) persists only the known keys. The Designer receives resolved `design` props and adds a «Стиль сайта» tab with live canvas preview and «Сохранить стиль». Official renderers consume the tokens as CSS variables (`--lf-primary`, `--lf-on-primary` from WCAG luminance, `--lf-secondary`, `--lf-radius`, `--lf-container`, font family), and the button style comes from React context. Checks: `SiteDesignTokensTest`, Sites feature tests, PHPStan, Pint, `npm run check`.

---

## P2-013 — Asset Upload / Image Picker

**Status:** DONE
**Dependencies:** P2-006

### Security

Upload validation required.

### Result

Implements ADR-003.

- **Storage.** `site_assets` holds Site-owned immutable images (ULID `public_id`, server-generated private-disk path `site-assets/{site}/{asset}.{ext}`, MIME type, size, dimensions). The model forbids changes to the file fields.
- **Upload.** `POST sites/{site}/assets` (`UploadSiteAssetRequest`) runs the Workspace scope check (404 before validation), requires `manage_assets` and is throttled to 60 per minute. It accepts only JPEG/PNG/WebP by detected MIME type up to 10 MB. Decoded `getimagesize` type must match, and each side is limited to 10 000 px. SVG and disguised files are rejected with Russian messages.
- **Serving.** `GET sites/{site}/assets/{asset}` checks scope and `view_site`, and serves with `nosniff` and `private, immutable` caching. Another Site's asset returns 404.
- **Block state.** An image value is a Site Asset ULID; `BlockStateValidator` checks the format, and the `BlockInstance` saving hook verifies the asset belongs to the Block's own Site.
- **Designer.** It receives `assets` (public ID, name, relative URL, size) and `can.manageAssets`. The Properties panel image picker offers the library dialog, upload and remove.
- **Renderers.** They resolve URLs through a render context: the `header` logo and the `hero` background with an overlay.

Checks: `SiteAssetsTest`, `BlockStateValidatorTest`, Sites feature tests, PHPStan, Pint, `npm run check`.

---

## P2-014 — Action System Foundation

**Status:** DONE
**Dependencies:** P2-004

### Actions

- open_url;
- open_page;
- scroll_to;
- phone;
- email.

Popup/Form actions may be placeholders until Phase 4.

### Result

New schema field type `action`. Its state is `{type, <target>}`, where the type is one of `BlockActionType`:
- `open_url` takes an `url` with an http/https scheme and a host. It must have no credentials, no whitespace or control characters, and be at most 2048 characters.
- `open_page` takes a `page` ULID of the same Site.
- `scroll_to` takes a `block` ULID on the same Page.
- `phone` takes a `phone` of digits, spaces, `()-` and an optional leading `+`.
- `email` takes an `email` of at most 254 characters.

Unknown types and foreign keys are rejected; a null target is allowed while drafting. Popup/Form actions are deliberately absent (no fake controls) until Phase 4.

Reference checks go through `BlockReferenceResolver` (`PageBlockReferences` in the `BlockInstance` saving hook), which also covers image assets.

New official versions add actions: `header` 1.2.0 (menu items and button), `hero` 1.1.0, `cta` 1.1.0 and `footer` 1.1.0 (links). Existing instances stay pinned.

The Properties panel action control offers a type select plus a URL, phone or email input or a page/block select. Renderers emit links through `actionHref()`, which repeats the http/https check; external URLs open in a new tab with `noopener noreferrer`.

Checks: `BlockStateValidatorTest` (safe and unsafe actions, `javascript:`/`data:`/protocol-relative URLs), `PageBlocksTest` (same-Site page and same-Page block only), full PHPUnit suite, PHPStan, Pint, `npm run check`.

---

## P2-015 — Draft Autosave

**Status:** DONE
**Dependencies:** P2-004, P2-009

### Acceptance Criteria

- edit;
- autosave;
- reload;
- state preserved;
- no production concept changed.

### Result

`useBlockAutosave` replaces the manual «Сохранить» button for Block content:
- Edits are debounced (700 ms per Block) and saved through the existing draft-state endpoint as async Inertia requests, one at a time.
- Edits made during a request are saved afterwards, and a cancelled request is retried.
- The top bar shows a live status: «Есть несохранённые изменения» / «Сохранение…» / «Сохранено» / «Не удалось сохранить».
- Validation errors stay on the fields and keep the draft.
- `beforeunload` warns while unsaved drafts exist.

Only draft `state_json` is written; nothing is published. Site style keeps its explicit «Сохранить стиль». Browser coverage: P2-017. Checks: `npm run check`; the backend endpoint is covered by `PageBlocksTest`.

---

## P2-016 — Draft Preview

**Status:** DONE
**Dependencies:** P2-015

### Result

`GET sites/{site}/preview?page=` (`SitePreviewController`) runs the Workspace scope check (404), requires `preview_site` (Owner in the current role matrix) and resolves the page (404 if foreign). It renders the current draft's visible Blocks only, with resolved design tokens and Site Asset URLs, inside a chrome-less page with «Вернуться в дизайнер» and a `noindex` meta tag.

- Block wrappers carry `block-{ULID}` anchors for `scroll_to`; `open_page` links stay inside the preview.
- No Published state is read or written.
- The Designer shows «Предпросмотр» (new tab) only when `can.preview`, and disables it while autosave is pending.

Checks: `SitePreviewTest`, Sites feature tests, PHPStan, Pint, `npm run check`.

---

## P2-017 — Designer Browser QA

**Status:** DONE
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

### Result

`tests/browser/designer.spec.ts` runs with a dedicated E2E Owner (`designer@landflow.test`, own Workspace with the E2E site limit). The flow:
1. Log in and create a Site from the Blank Template.
2. Add Hero, edit the title, set a phone action on the primary button and upload an in-memory PNG through the image library.
3. Switch the site style to outline buttons.
4. Add Benefits with two repeater items.
5. Wait for autosave «Сохранено» and move Benefits up.
6. Reload: order, title and action come back from the server.
7. Open Preview in a new tab: draft heading, items, a `tel:` link, the loaded asset image and no designer chrome.

The run found a real race: saving the site style could be cancelled by the next designer action, so the style save is now an async Inertia request. The fixture's console-error and failed-request guards stay strict. Checks: the Playwright spec (desktop), `E2eSeederTest`.

---

## P2-018 — Phase 2 Review

**Status:** DONE
**Dependencies:** P2-017

### Result

Phase 2 gate. Final gates passed on 2026-10-03: `composer quality` (357 PHPUnit tests, PHPStan, Pint, `npm run check`), `npm run test:e2e` (38 passed) and `git diff --check`.

The risk-focused review covered tenancy (every designer, asset, style and preview route checks the Workspace scope with 404 before validation), permissions (`edit_design` / `edit_content` / `manage_assets` / `preview_site`), uploads (detected MIME type plus decoded type, no SVG, private storage, nosniff), URL actions (http/https only on the server and in renderers) and the draft-only rule (autosave and preview never touch Published state). One fix: an Inertia confirm now protects unsaved drafts when leaving the Designer.

Non-blocking follow-ups:
- (a) No per-Site/Workspace storage quota for assets yet; this needs an entitlement decision.
- (b) A deleted page/block leaves stale action references, which surface as field errors on the next save.
- (c) The Designer role has no `preview_site` in the current role matrix, so only the Owner sees «Предпросмотр». Revisit with the role/permission owner if Designers should preview.
- (d) Site style uses an explicit save, not autosave.

---

# PHASE 3 — AUTOMOTIVE FOUNDATION

Reconciled with Catalog V2 by `X-016` (D-101 … D-104). Source of truth for the catalog schema: `docs/architecture/AUTO_CATALOG_SCHEMA.md`. Technical catalog = separate physical database (connection `catalog`). Terms: Mark (not Make), Equipment = «Комплектация», Option = factory option.

---

## P3-001 — Catalog Core Schema

**Status:** DONE
**Dependencies:** P2-018, X-016, X-009

### Scope

- Laravel connection `catalog` (separate database, local suggestion `landflow_catalog`), dedicated migration directory, explicit catalog migration command that refuses to target the main database.
- Core tables exactly per V2: `auto_marks`, `auto_models`, `auto_generations`, `auto_series`, `auto_modifications`, `auto_equipments`; additive immutable ULID `public_id`.
- Catalog models in the `App\Models\Catalog` namespace, explicitly on the `catalog` connection.
- Status-chain availability (a disabled parent hides the branch), RESTRICT deletes, application rules (model parent: same Mark, no self/cycles; `year_to >= year_from`; modification codes).
- Isolated test catalog connection; CI never depends on developer MySQL.

### Result

Connection and migrations:
- Connection `catalog` (`CATALOG_DB_*`). It defaults to `database/catalog.sqlite` and never falls back to `DB_*`.
- Migrations live in `database/migrations/catalog`. `php artisan catalog:migrate` runs `migrate --database=catalog` and refuses when the catalog connection targets the main database (`App\Catalog\CatalogDatabase`).

Models (`App\Models\Catalog\Auto*`, on the `catalog` connection):
- Six core V2 tables plus an additive ULID `public_id`; numeric IDs are hidden.
- RESTRICT foreign keys. The hierarchy parent is fixed after creation. `year_to >= year_from`.
- Model grouping parent: same Mark, no self-reference or cycles.
- `available()` scopes check status along the whole chain.

Modification codes are typed by `App\Enums\Catalog\{EngineType, TransmissionType, DriveType}`; decimals stay strings.

Environments: tests use in-memory SQLite (`RefreshCatalogDatabase`, TestCase guard); E2E uses `database/e2e-catalog.sqlite`, verified by `prepare-e2e.mjs`.

Checks: `CatalogCoreSchemaTest`.

---

## P3-002 — Platform Catalog Authorization

**Status:** DONE
**Dependencies:** P3-001

### Acceptance Criteria

- Platform roles `super_admin`, `catalog_manager` are persistent explicit assignments, separate from Workspace roles.
- Platform permissions at minimum `view_catalog`, `edit_catalog`, `manage_catalog_media`.
- Never identified by email or user ID; no automatic super admin.
- Safe local Artisan command grants an existing User a platform role.
- Workspace Owner/Admin cannot mutate the catalog.

### Result

- Table `platform_role_assignments` (`user_id`, `role`, unique pair) and enums `PlatformRole` (`super_admin`, `catalog_manager`) and `PlatformPermission` (`view_catalog`, `edit_catalog`, `manage_catalog_media`).
- `App\Support\PlatformAuthorization` resolves permissions from persisted roles only. Gates are defined per platform permission, and the `EnsurePlatformPermission` middleware enforces them.
- Shared Inertia prop `platform.permissions`.
- Operator command: `php artisan platform:role grant|revoke <email> <role>`. It works only for existing users, rejects unknown actions and roles, and asks for confirmation in production unless `--force` is passed.

Checks: `PlatformAuthorizationTest`:
- Workspace Owner/Admin and user #1 get no platform permissions;
- role grants;
- middleware 403/200;
- shared prop;
- command grant/idempotency/revoke/failures.

---

## P3-003 — Characteristics

**Status:** DONE
**Dependencies:** P3-001, X-009

V2 `auto_characteristics` (two-level group → parameter, unit on the definition) and `auto_characteristic_values` (TEXT value per Equipment + parameter). Server validation; no fake empty rows; no editable duplicates of Modification filter fields (ADR-005).

### Result

Catalog migration for both tables, with an additive `public_id` on the dictionary.

`TwoLevelDictionary` rules:
- the parent must be a root group;
- the code format is enforced;
- the parent is fixed after creation, so no cycles.

`AutoCharacteristic`:
- reserved Modification/Mark/Model codes are rejected;
- groups have no unit.

`AutoCharacteristicValue` accepts parameter-only values and no empty values. `App\Catalog\EquipmentCharacteristics::sync()`:
- blank input deletes the row;
- numbers are stored canonically;
- unknown keys and group keys are rejected.

Checks: `CatalogCharacteristicsTest`.

---

## P3-004 — Options

**Status:** DONE
**Dependencies:** P3-001

V2 `auto_options` (two-level group → option) and `auto_option_values` (per Equipment, `is_base` explicit, no default). Missing row = unknown.

### Result

- Catalog migration: `is_base` is NOT NULL with no default.
- `AutoOption` is a two-level dictionary. `AutoOptionValue` requires an explicit `is_base` and refers to options only, never groups.
- `OptionAvailability` (unknown / standard / optional) is a view: unknown is never stored.
- `App\Catalog\EquipmentOptions::sync()`: unknown deletes the row.

Checks: `CatalogOptionsTest`.

---

## P3-005 — Series Media Sets

**Status:** DONE
**Dependencies:** P3-002

Supersedes "Automotive Colors / Swatches" (D-103). Platform-owned media sets in the main database referencing a catalog Series by `catalog_series_public_id`: `public_id`, name, nullable `swatch_hex` (display metadata), status, sort order. No `auto_colors` / `auto_paints`. Customer read-only.

### Result

Main-database table `series_media_sets`:
- unique name per Series;
- index on (series, status, order).

`SeriesMediaSet` model:
- the Series must exist in the catalog; this is checked through `App\Catalog\CatalogReferences` because there is no cross-database foreign key;
- the Series cannot change after creation;
- `swatch_hex` must be lowercase `#rrggbb`;
- `active()` and `ordered()` scopes.

Mutation is limited to the platform `manage_catalog_media` permission (P3-007 routes). Customers have no write path.

Checks: `SeriesMediaSetTest`.

---

## P3-006 — Series Media Images

**Status:** DONE
**Dependencies:** P3-005

Prepared images per media set and angle (front, front_3_4, side, rear_3_4, rear, interior). JPEG/PNG/WebP (transparency allowed), no SVG, 10 MB max, immutable objects with server-generated storage keys, no URL fetch. Controlled by `manage_catalog_media`; never stored as Site Assets.

### Result

Table and model:
- Table `series_media_images`, one image per (set, angle).
- `SeriesMediaImage` is immutable, stored on the private disk at `series-media/{set}/{image}.{ext}`.
- `MediaAngle` enum with Russian labels.

Upload validation, shared with Site Assets through `App\Support\ImageUpload`:
- JPEG/PNG/WebP only, ≤10 MB, side ≤10000 px;
- the decoded type must match the MIME type;
- SVG and polyglot files are rejected.

Routes:
- `POST platform/catalog/media-sets/{set}/images` and `DELETE platform/catalog/media-images/{image}` require `view_catalog` plus `manage_catalog_media`; deleting also removes the file.
- `GET media/series/{image}` serves with nosniff; any signed-in user can read images of active sets, and inactive sets are visible to catalog staff only.

Checks: `SeriesMediaImagesTest`, `SiteAssetsTest`.

---

## P3-007 — Super Admin Catalog UI

**Status:** DONE
**Dependencies:** P3-002 through P3-006

Separate platform surface on the existing Laravel/React/Inertia/shadcn stack (no Filament): «Каталог автомобилей» with marks, models, generations, series, modifications, equipments (cascading; changing an upper selection clears lower levels; backend verifies hierarchy), characteristic and option dictionaries, Equipment characteristics/options page and Series media page. Create, edit, activate/deactivate (status instead of hard delete), sort.

### Result

The surface lives at `/platform/catalog`. All of it requires `view_catalog`. Changes to the hierarchy and the dictionaries also require `edit_catalog`, and media changes require `manage_catalog_media`. The sidebar link «Каталог автомобилей» appears only to users with `view_catalog`.

Catalog browser (`CatalogBrowserController`, `App\Catalog\CatalogLevel`):
- Six cascading columns addressed by ULID query parameters.
- A lower selection is accepted only when it belongs to the selected parent; otherwise it is dropped together with everything below it.
- Create, edit, a quick activate/deactivate toggle and sort order. There is no hard delete.
- `entries/{level}` checks the parent `public_id` against the expected level, URL segments are unique within their parent, decimal commas are accepted, and a model group must be a model of the same Mark.

Other pages:
- Equipment page: characteristic values and option availability by dictionary group.
- Dictionaries: create a group or an element inside a root group; the code cannot be changed after creation.
- Series media page: create and edit media sets (name, swatch, status, order), and upload or delete an image for each of the six angles.

Checks: `PlatformCatalogUiTest`, existing catalog tests, PHPStan, `npm run check`.

---

## P3-008 — Site Vehicle Schema

**Status:** DONE
**Dependencies:** P3-001

SiteVehicle in the main database: belongs to a Site, references `catalog_series_public_id` (vehicle family/body page), selected Series media sets, status and order. Tenant isolation mandatory.

### Result

Tables in the main database:
- `site_vehicles`: public_id, site_id (restrict on delete), catalog_series_public_id, status, sort_order. Each Series appears at most once per Site.
- `site_vehicle_media_sets`: references platform media sets with an order. No files are copied.

`SiteVehicle` rules:
- The Series must exist in the catalog when the vehicle is created.
- The Site and the Series cannot be changed afterwards.
- `selectMediaSets()` accepts only active sets of the vehicle's own Series.
- Numeric IDs are hidden.

`SitePolicy` gains:
- `viewVehicles`: any of `view_vehicles`, `edit_vehicles` or `edit_prices`;
- `editVehicles`;
- `editPrices`.

These are checked against the Site's Workspace in the current backend context.

Checks: `SiteVehicleSchemaTest`. The policy is covered over HTTP in P3-010.

---

## P3-009 — Site Offer / Benefits Schema

**Status:** DONE
**Dependencies:** P3-008, X-008

SiteOffer belongs to a SiteVehicle and references `catalog_equipment_public_id`; the Equipment chain must reach the SiteVehicle's Series. Money per ADR-004 (integer minor units, `CHAR(3)` currency), availability, badge, benefits (amount-based). Factory data stays catalog-side.

### Result

Tables in the main database:
- `site_offers`: public_id, site_vehicle_id, catalog_equipment_public_id, `price_minor`, nullable `rrp_minor`, `currency CHAR(3)` (default RUB), availability, badge (up to 40 characters), status, sort_order.
- `site_offer_benefits`: type, `amount_minor`, optional label, order.

Availability values are «В наличии», «В пути» and «Под заказ». Benefit types are «Скидка», «Выгода по трейд-ин», «Выгода в кредит» and «Выгода в лизинг».

`SiteOffer` rules:
- The Equipment must exist, and its chain must reach the vehicle's Series. This is checked again whenever the Equipment changes.
- The vehicle cannot be changed after creation.
- Only supported currencies are allowed, and amounts must be in range.
- `replaceBenefits()` replaces all benefits in one transaction; each benefit amount must be at least 1.

`App\Support\Money` is the shared ADR-004 helper:
- parses decimal strings into integer minor units, with no floats and at most two decimal places for RUB;
- `toInput()` returns a decimal string for form fields;
- `format()` returns the Russian display form, for example `1 850 000 ₽`.

Checks: `SiteOfferSchemaTest`, `MoneyTest`.

---

## P3-010 — Customer Vehicle Flow

**Status:** DONE
**Dependencies:** P3-007, P3-008, P3-009

Supersedes "Customer Vehicle Import". Site → «Автомобили» → «Добавить автомобиль»: Mark → Model → Generation → Series creates a SiteVehicle; then one or more Offers (Modification → Equipment → price). Dealer selects which active Series media sets are shown (stored as references, never copied).

### Result

Pages:
- **Dashboard:** each site card has an «Автомобили» button, shown to users with any vehicle permission.
- **List** (`sites/{site}/vehicles`): vehicles with their offer and media-set counts, and a warning when the Series has been switched off in the catalog.
- **«Добавить автомобиль»:** four cascading columns (Mark, Model, Generation, Series) showing only available catalog rows, with the selection carried in ULID query parameters. A Series that is already on the site is marked «Уже на сайте». The server accepts only an available Series and rejects duplicates.
- **Vehicle page:**
  - show or hide on the site, and delete the vehicle together with its offers;
  - «Цвета и ракурсы»: tick active media sets of this Series; they are stored as references;
  - «Предложения»: the offer dialog picks a Modification, then an Equipment (available ones only), and takes price, price without discount, availability, badge, display, order and up to 10 benefits.

Offer saving (`SaveSiteOfferRequest`):
- Prices and benefit amounts arrive as decimal strings and are parsed on the server with `Money`; a numeric JSON value is rejected.
- A newly chosen Equipment must be available and belong to the vehicle's Series. An offer can keep its current Equipment after the catalog switches it off.

Authorization:
- The Site must be in the current Workspace, and the vehicle or offer must belong to that Site; otherwise the response is 404.
- Viewing requires `viewVehicles`, vehicle changes require `editVehicles`, and offer changes require `editPrices`.
- Designer and ContentEditor get 403.

`App\Automotive\VehicleCatalog` provides read-only bulk catalog lookups and the Russian modification summary.

Checks: `SiteVehicleFlowTest`, PHPStan, `npm run check`.

---

## P3-011 — Automotive Fallback Resolver

**Status:** DONE
**Dependencies:** P3-006, P3-008

### Priority

Site selection
→ Workspace when available (not implemented in Phase 3)
→ Global (active Series media sets).

### Result

`App\Automotive\VehicleMediaResolver` decides which prepared media a vehicle shows:
- If the vehicle's own selection contains active sets with images, those are used in the selected order; the source is `site`.
- Otherwise all active sets with images for the vehicle's Series are used in platform order; the source is `global`.
- If neither exists, the result is empty.
- The Workspace level is skipped because there is no Workspace media library in Phase 3.

Each image is returned with its angle (in angle order), its URL and its size. `resolveMany()` resolves any number of vehicles with a fixed number of queries. Files are referenced, never copied.

The image URLs point to the signed-in route `media/series/{image}`. Serving these images in published output belongs to Publishing.

Checks: `VehicleMediaResolverTest`.

---

## P3-012 — Automotive Binding Registry

**Status:** DONE
**Dependencies:** P3-008, P3-009, P2-003

Approved automotive view model / binding: SiteVehicle identity, Mark, Model, Generation, Series, selected media variants and angles, Offers (Modification, Equipment, price, benefits, characteristics, factory options). Blocks never query the raw catalog database.

### Result

`App\Automotive\VehicleBindings::forSite()` builds display-ready view models for the designer and the preview (prop `vehicles`):
- **Included:** only visible vehicles whose Series is available, and only visible offers whose Equipment is available.
- **Vehicle fields:** identity, Mark/Model/Generation/Series, resolved media sets with angle labels, a «from» price and the largest benefit total.
- **Offer fields:** Modification summary and specs, Equipment name, price, RRP, availability, badge, benefits, grouped characteristics and known factory options (standard or optional).
- **Money:** passed only as formatted labels.
- **Never included:** numeric IDs and raw `*_minor` values.

The new Block field type `vehicle` references a vehicle of the same Site by `public_id`. References are validated on the server, and the designer offers a picker for it. Renderers read bindings through `BlockRenderContext.vehicle()` / `vehicles`.

Checks: `VehicleBindingsTest`.

---

## P3-013 — Vehicle Card Block

**Status:** DONE
**Dependencies:** P3-012

### Result

New official Block `vehicle-card` («Карточка автомобиля»).

Fields:
- **Vehicle:** a required `vehicle` reference.
- **Display flags:** show price, show benefit and show colors.
- **Button:** a safe action button.

The renderer shows:
- the image for the chosen color, preferring the front three-quarter angle;
- clickable color swatches;
- «от» price, benefit and offer count;
- the button.

Missing or hidden vehicles render as a Russian placeholder. Checks: `VehicleBlocksTest`, `OfficialBlocksTest`.

---

## P3-014 — Vehicle Grid Block

**Status:** DONE
**Dependencies:** P3-013

### Result

New official Block `vehicle-grid` («Каталог автомобилей»):
- **Header:** title and subtitle.
- **Source** («Все автомобили сайта» or «Выбранные»):
  - «Все автомобили сайта» (the default) shows every visible vehicle in Site order.
  - «Выбранные» shows a repeater (up to 24) of same-Site vehicle references, each with an optional safe action.
- **Layout and flags:** columns, plus flags for price, benefit and colors.
- **Card button label:** used with each item's action.

Each card reuses the Vehicle Card renderer, so it shows the color-specific image, swatches, «от» price and benefit. The empty state is shown in Russian. Checks: `VehicleBlocksTest`.

---

## P3-015 — Vehicle Detail Blocks

**Status:** DONE
**Dependencies:** P3-012

### Blocks

- Gallery (media variant/color selector + angles)
- Price/Offer (dealer Offers, expandable to modification data)
- Characteristics
- Equipment (factory options)

### Result

Four official Blocks. Each references one same-Site vehicle:
- `vehicle-gallery` («Галерея автомобиля») shows:
  - a large image with angle thumbnails;
  - a color selector with the color name;
  - title, «от» price and benefit, each of which can be turned off.
- `vehicle-offers` («Цены и предложения») lists the dealer offers. Each offer shows:
  - Equipment, Modification summary, availability and badge;
  - price, with the RRP struck through, and benefits;
  - a safe action button;
  - an accessible «Подробнее» expansion with Modification specs, grouped characteristics and options.
- `vehicle-characteristics` («Характеристики автомобиля») has an offer picker, Modification specs and grouped Equipment characteristics.
- `vehicle-equipment` («Оснащение автомобиля») has an offer picker and grouped factory options (standard or optional, with an optional filter).

Checks: `VehicleBlocksTest` (same-Site reference for every vehicle Block), `OfficialBlocksTest`.

---

## P3-016 — Automotive E2E

**Status:** DONE
**Dependencies:** P3-010 through P3-015

### Flow

```text
Platform admin manages catalog
→ customer adds vehicle (Series)
→ adds offer with price
→ adds Vehicle Grid
→ sees price/image/colors
```

### Result

`tests/browser/automotive.spec.ts` runs the whole flow in one test:
1. The platform super admin:
   - opens `/platform/catalog`;
   - creates two Series media sets with swatches and uploads a front three-quarter image to each;
   - saves an Equipment characteristic through the bracket-named form, and the value survives a reload.
2. The dealer:
   - creates a Site and adds Kia Rio IV Рестайлинг Седан by Series;
   - adds an offer with a price and a benefit;
   - adds the Vehicle Grid, which shows the price and benefit;
   - adds an Offers block bound to the vehicle.
3. The preview shows:
   - the price and a loaded image;
   - color switching by swatch;
   - the expanded offer with the platform characteristic and option data.

Supporting data:
- `Database\Seeders\CatalogDemoSeeder` is an idempotent demo catalog branch that refuses to run in production (`CatalogDemoSeederTest`).
- `E2eSeeder` adds the test-only users `catalog@landflow.test` (`super_admin`) and `dealer@landflow.test`.

---

## P3-017 — Phase 3 Review

**Status:** DONE
**Dependencies:** P3-016

### Result

Phase 3 COMPLETED. Gate checks:
- `composer quality` PASS: 440 PHPUnit tests, PHPStan, Pint, `vp check` and the production build.
- `npm run test:e2e` PASS: 39 tests, including the automotive flow.
- `git diff --check main..HEAD` PASS.

Risk-focused review:
- **Tenancy:** every vehicle, offer, designer and preview route checks the Workspace scope with a 404. Vehicle Block references resolve only to vehicles of the same Site.
- **Catalog immutability:** only explicit platform roles mutate the catalog. Customer Workspace permissions never reach the catalog routes.
- **Identifiers:** catalog and Site entities are addressed by `public_id`. Bindings carry no numeric IDs and no raw `*_minor` values.
- **Database boundary:** no cross-database foreign keys. Catalog references are validated in the application, and only available rows are bound.
- **Uploads:** Series media rejects SVG, is limited to 10 MB, uses server-generated keys and private storage, and is served with nosniff.
- **Draft only:** autosave and preview never publish.

Post-gate fix from the first real MySQL migration:
- Two generated index names exceeded MySQL's 64-character limit, which SQLite does not enforce. They now have explicit short names.
- `MigrationIdentifierLengthTest` guards every table and index name in both databases.
- Re-run gates: 441 tests, 39 E2E.

Known limits, carried forward:
- Series media URLs require sign-in. Public delivery belongs to Publishing.
- Designers and ContentEditors see offer prices in the designer and preview canvas. Prices are site-facing content.
- The Admin role matrix lacks `view_site`, `view_vehicles` and `edit_benefits`. This needs an owner decision.
- `edit_benefits` is not enforced separately; benefits are saved under `edit_prices`.

---

# PHASE 4 — FORMS & INTERACTIVE COMPONENTS

---

## P4-001 — Popup Schema / Runtime

**Status:** DONE
**Dependencies:** P3-017

Result:
- Site-owned `popups` table: ULID `public_id`, name, status, title, text, size, animation, overlay/Escape/close-button behaviour and mobile fullscreen. A Popup holds no fields, routing or credentials (D-035).
- «Попапы» section (`sites/{site}/popups`) requires `view_site` to open and `edit_popups` to change anything. It is reachable from the designer's «Разделы» menu and has a live «Просмотр».
- `PopupRuntime` gives the designer and the preview a payload of active popups only, with no internal IDs.
- `PopupView` is a Radix dialog: `aria-modal`, a title (an `sr-only` name when no title is set), focus trap and focus return.
- Tests cover CRUD, the closability rule, 404 for foreign, other-site and numeric references, the `edit_popups` matrix, and the runtime props.

---

## P4-002 — Open Popup Action

**Status:** DONE
**Dependencies:** P4-001, P2-014

Result:
- `open_popup` action stores a Popup `public_id`. On save, only active Popups of the same Site are accepted. Inactive, other-Site, foreign and numeric references, and extra keys, are rejected.
- In the preview, the action renders as a `<button aria-haspopup="dialog">` that opens the reusable `PopupView`. There is no eval, injected selector or URL.
- Each block passes a trigger context made of public IDs (block, plus vehicle, offer or media set from vehicle cards and offer rows), so one Popup opens with different context per trigger.
- Focus returns to the trigger, and Escape and overlay closing follow the Popup settings.
- The designer action control lists the active Popups.

---

## P4-003 — Form Schema / Fields

**Status:** DONE
**Dependencies:** P3-017

Result:
- Site-owned `forms` (ULID `public_id`, name, status, submit label, success message) and `form_fields` keyed by a stable lowercase key, unique per Form. The key and type are immutable after creation.
- Field types: text, phone, email, textarea, select, checkbox, consent and hidden. Labels are plain text, never HTML.
- Consent text is the customer's own; required consent must be ticked. Hidden values are marked untrusted.
- «Формы» management UI: create and edit a Form, activate or deactivate it, and add, edit, delete or reorder fields. All of this is gated by `edit_forms`.
- A Popup may reference one Form of the same Site (`popups.form_id`, enforced by validation and the model). The runtime exposes only active Forms. The Popup owns no routing or delivery.

---

## P4-004 — Public Form Identifier / Endpoint

**Status:** DONE
**Dependencies:** P4-003

Result:
- `POST /forms/{form_public_id}/submissions` is unauthenticated, JSON-only and CSRF-exempt, behind a coarse per-IP backstop of 30 per minute. Numeric IDs return 404.
- Reachability: an active Form of an active Site. Inactive or missing Forms and archived Sites get a generic Russian 404. Which published hosts may call the endpoint is decided in Phase 5.
- Only `fields` is accepted at the top level for now. Keys such as `workspace_id`, `site_id`, `destination` and `price`, and any other unknown key, are rejected. Unknown field keys are rejected as well.
- Values are validated on the server against the current Form definition (required, consent accepted, length, email, select options). Errors come back as Russian messages keyed by field.
- The preview popup form submits to this endpoint.

---

## P4-005 — Submission Persistence

**Status:** DONE
**Dependencies:** P4-004

Result:
- `submissions` stores public_id, site_id, form_id, status (`received`), a `payload` snapshot (key, type, label and value per field), `context`, phone original/normalized, lowercased email, IP, user agent truncated to 255 characters, and `submitted_at`.
- No headers, cookies or the raw request are stored. The snapshot is immutable, and the Form must belong to the same Site.
- A valid request is persisted before the success response. Invalid, inactive and spoofed requests are not persisted. History survives Form edits and field deletion.
- A read-only «Заявки» list is gated by `view_submissions` (Owner and Admin). It never shows the IP or user agent.
- All personal data is row-local, so future D-094 deletion or anonymization stays possible.

---

## P4-006 — Phone Normalization

**Status:** DONE
**Dependencies:** P4-005

Result:
- The central `App\Support\PhoneNormalizer` turns `+7 (999) 111-22-33` into `79991112233`. Allowed characters are digits, spaces, parentheses, hyphens, dots and a leading `+`. A number must have 10 to 15 digits.
- Phone fields are validated with Russian messages. Submissions store both the original and the normalized value.
- A leading `8` is kept as dialled. No trunk rewriting is applied without an owner decision (open question: should `8XXXXXXXXXX` be treated as `7XXXXXXXXXX`?).

---

## P4-007 — Context Passing

**Status:** DONE
**Dependencies:** P4-002, P4-003, P3-012

Result:
- Visitors send only public-ID hints: page, block, popup, vehicle, offer and media set. `SubmissionContextResolver` resolves each one inside the Form's Site.
  - The page must belong to the Site, and the block to that page.
  - The popup must be active and attached to this Form.
  - The vehicle and offer must be active, and the offer must belong to the vehicle; an offer on its own resolves its vehicle.
  - The media set must be selected and active on that vehicle.
  - Catalog titles, equipment, modification and the offer price are read on the server.
- A malformed, foreign or mismatched hint rejects the submission with a Russian message. Visitor price keys are ignored: the price always comes from the Site Offer.
- `context.trusted` holds the snapshot. `context.visitor` separately holds the page URL, referrer and UTMs, kept only when well-formed.
- The same Popup opened from Vehicle A or B records different context.
- The «Заявки» list shows the context.

### Context

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

**Status:** DONE
**Dependencies:** P4-005

Result:
- `SubmissionGuard` runs inside the single public pipeline, so every Form uses it.
  - A filled honeypot (`lf_hp`) is rejected.
  - IP limit: 5 per 10 minutes per Site. Phone limit: 2 per 30 minutes per Site, counted across Forms.
  - Same Form + normalized phone is a duplicate within 15 minutes.
  - Phone checks are skipped for Forms without a phone field.
- Counters use the cache through RateLimiter, with hashed keys, and only persisted Submissions consume them. Duplicate detection reads existing Submissions through an index.
- Spam is never persisted. Visitors get one generic Russian message (422 for the honeypot, 429 for limits).
- Defaults live in `config/forms.php`. Each Site can override them in `sites.form_security` from the «Защита форм» page, gated by `edit_site_settings`. Missing or invalid stored values fall back to the defaults.

### Features

- honeypot;
- rate limit;
- duplicate detection.

---

## P4-009 — Blacklist

**Status:** DONE
**Dependencies:** P4-008

Result:
- `blacklist_entries` has explicit scope columns: `global` has no owner, `workspace` sets `workspace_id`, `site` sets `site_id`. It stores type (`ip`/`phone`), the normalized value (canonical IP or normalized phone), reason, `expires_at` and `created_by_user_id`. The model enforces scope consistency and immutability.
- Resolution: an active Global, Workspace or Site entry rejects the submission with the generic message before any rate limiting. Expired entries are ignored, and there is no leak across Workspaces or Sites.
- Global entries are platform-only, through the `blacklist:global add|remove|list` operator command. Each change requires a platform super admin and a reason, and is logged for audit. Removal expires the entry instead of deleting it. Global entries are never exposed to tenants.
- «Защита форм» manages Site entries under `edit_site_settings` and Workspace entries under `edit_workspace`. Each list is sent only to members who may edit it.

### Scopes

- Global;
- Workspace;
- Site.

---

## P4-010 — Yandex SmartCaptcha Adapter

**Status:** DONE
**Dependencies:** P4-008

### Requirement

Verify current official documentation during implementation.

Result:
- The contract was checked against the official «Валидация пользователя» docs: form-urlencoded POST of `secret`, `token` and `ip` to `/validate`. Only `status` is evaluated: `ok` passes, `failed` fails closed. Network errors, non-200 responses and malformed bodies fail open with a safe warning log (D-043).
- `CaptchaVerifier` interface with a `YandexSmartCaptchaVerifier` implementation using the Laravel HTTP client with a 3 s timeout. Credentials come from `YANDEX_SMARTCAPTCHA_CLIENT_KEY` / `YANDEX_SMARTCAPTCHA_SERVER_KEY`. A `FakeCaptchaVerifier` is honoured only in `testing`/`e2e` (`FORMS_CAPTCHA_DRIVER=fake`).
- The pipeline runs CAPTCHA after rate limits and before context. It applies only when the Site policy `captcha_required` is on and the platform is configured. A missing or failed token is rejected; the token is never persisted or logged.
- «Защита форм» has a toggle that cannot be enabled without platform credentials. The preview receives the provider and client key only, and the widget renders in Russian with reserved height and a fresh token after each failed attempt.

---

## P4-011 — Carousel Capability

**Status:** DONE
**Dependencies:** P2-003

Result:
- Vendor-neutral `carousel` schema group (D-032) with bounded choices: enabled, cards per view (1–4), gap, arrows, dots, loop, autoplay and delay (3/5/8 s). Each field is stored as a plain Block state value; there is no library-specific config.
- The reusable `Carousel` component uses dependency-free CSS scroll-snap. It shows one card on phones and at most two on tablets; the arrow and dot buttons are labelled in Russian, and Left/Right keys work on the region. Slides stay in the normal tab order, so there is no keyboard trap. Autoplay pauses on hover or focus, has a pause/play button, and is disabled for `prefers-reduced-motion`.
- Adopted in the new `vehicle-grid` 1.1.0 as a carousel mode. Existing 1.0.0 instances are unchanged.

---

## P4-012 — Gallery / Lightbox

**Status:** DONE
**Dependencies:** P4-011

Result:
- The reusable `Lightbox` is a media viewer separate from the business Popup (D-033). It shows only already authorized image URLs with their alt text, and is responsive up to full screen.
- Navigation: previous/next buttons with Russian labels, Left/Right keys and a live «N из M» counter. Escape and the close button close it; focus is trapped while open and returns to the opening element.
- Adopted in the Vehicle Gallery: the main image is a labelled button that opens the current color's angles, and navigating in the viewer keeps the selected angle in sync. There is no schema change, and the designer canvas stays inert.

---

## P4-013 — Interactive E2E

**Status:** DONE
**Dependencies:** P4-001 through P4-012

Result:
- `tests/browser/interactive.spec.ts`, end-to-end flow: owner → Site → Form (name, phone, consent) → Popup → CTA `open_popup` → Preview → keyboard-usable popup → submit → success → lead with context in «Заявки».
- Seeded «Витрина Запад» (E2eSeeder, dedicated Lada Vesta catalog branch, priced Offer, two-angle media). The vehicle Offer button opens a popup and the lead stores the server-side vehicle, offer and price while a spoofed `price` in the request is ignored. Also covered: duplicate rejected (429), honeypot rejected (422), fake CAPTCHA (required, then passes), carousel buttons, dots and arrow keys, and Lightbox open, next, arrow key, Escape and focus return. At 375 px there is no horizontal overflow; this check found and fixed preview toolbar overflow on phones.
- The blacklist is covered by backend tests (`BlacklistTest`).
- The browser fixture now watches every page of the context (preview tabs included), and intentionally provoked 4xx responses must be declared with `expectFailedResponse`.

---

## P4-014 — Phase 4 Review

**Status:** DONE
**Dependencies:** P4-013

### Result

Phase 4 COMPLETED. Gate checks:
- `composer quality` PASS: 509 PHPUnit tests, PHPStan, Pint, `vp check` and the production build.
- `npm run test:e2e` PASS: 41 tests, including both interactive flows.
- `git diff --check` PASS.

Risk-focused review:
- **Ownership:** Popup, Form, Submission and Blacklist entries are Site- or Workspace-scoped. The public endpoint derives Site and Workspace from the Form `public_id`, and never trusts browser workspace, site, form, popup or price data. Form and Popup stay separate, and the Popup owns no delivery.
- **Pipeline:** the order is Form → validation → honeypot → blacklist → rate limits and duplicates → CAPTCHA → trusted context → persist. Nothing is delivered; there are no integrations or CRM.
- **Secrets and PII:** the SmartCaptcha server key exists only in env/config, and the browser gets the client key only. Logs contain no token, key, IP, phone or blacklisted value, and rate-limit keys are hashed.
- **Permissions:** popups need `edit_popups`, forms `edit_forms`, leads `view_submissions`, the security policy and Site blacklist `edit_site_settings`, and the Workspace blacklist `edit_workspace`. Global blacklist changes go only through the audited platform command.
- **Accessibility:** popup, carousel and lightbox are keyboard-usable with focus return. Reduced motion is respected, and there is no horizontal overflow at 375 px.

Known limits, carried forward:
- The public endpoint accepts any active Form of an active Site, because there is no Published state yet. Phase 5 must bind submissions to published reachability.
- Preview submissions are real persisted Submissions.
- Phone normalization does not rewrite a Russian trunk-8 prefix; this needs an owner decision (P4-006).
- Admin lacks `edit_popups` and `edit_site_settings`, and Designer lacks `edit_forms`, per the approved role matrix.
- Retention and deletion of Submissions (D-094) remain a production blocker; the schema allows row deletion.
- Blacklist UI shows normalized values only to editors. A management screen for global entries is not built; the command is used instead.

---

# PHASE 5 — PUBLISHING

---

## P5-001 — Publishing Runtime ADR

**Status:** DONE
**Dependencies:** P4-014

Result: ADR-006 accepted (owner approval 2026-10-05), resolving D-073 and D-074: public manifest + private draft snapshot, publish-time React SSR with the existing Vite/`react-dom/server` stack into DB-stored HTML artifacts, client hydration, versioned cache keys, `published_asset_references` with version-scoped delivery, atomic pointer activation with per-Site serialization, Published-Version-bound public forms, restore-to-Draft. Feasibility verified: `vp build --ssr` builds the renderer entry and plain Node renders an official Block with `renderToString` (Inertia's own SSR handling disabled). D-076, D-077, D-094 stay open.

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

**Status:** DONE
**Dependencies:** P5-001

Result: `published_versions` (ULID `public_id`, per-Site unique `version_number`, `status` building/ready/failed, `public_manifest_json`, private `draft_snapshot_json`, `manifest_hash`, `created_by`, `ready_at`), `published_pages` (per-version unique page/slug, `rendered_html` LONGTEXT, `hydration_json`, `seo_json`, `content_hash`), `published_asset_references` (kinds `site_asset` / `series_media_image` only), nullable `sites.active_published_version_id` FK (separate migration). Model guards: snapshot columns immutable, only building versions change state, ready versions/artifacts/references frozen, versions never deleted, the Site pointer accepts only its own ready version; snapshots hidden from serialization.

---

## P5-003 — Publication Records

**Status:** DONE
**Dependencies:** P5-002

Result: `publications` table and `Publication` model — one row per Publish attempt with audit actor, status (validating → building → activating → succeeded, or failed from any in-progress state; transitions enforced, finished attempts frozen, never deleted), `safe_error_code`/`safe_error_summary` from the `PublishFailure` enum and safe `metadata_json`. `SitePolicy::publish` (`publish_site`) and `SitePolicy::restoreVersion` (`restore_version`): Owner/Admin publish, only Owner restores, Designer/Content Editor denied, foreign Workspace denied.

---

## P5-004 — Publish Validator

**Status:** DONE
**Dependencies:** P5-002

Result: `App\Publishing\PublishValidator` returns `errors[]` / `warnings[]` of `PublishIssue` (stable code, Russian message, Page/Block/target public IDs, state path). Checks: `publish_site` for the actor, active Site, exactly one home Page, valid/unique slugs, pinned-schema state of visible Blocks, publish-time completeness (`BlockStateValidator::missing`: required values, Repeater `min_items`, `visible_if` honoured), X-018 inspector in publish mode (stale page/scroll/popup actions, hidden scroll targets, missing assets/vehicles; disabled Popup Form = warning), asset and Series Media files present, referenced vehicles visible and catalog-available (no offers / no media / unavailable offers = warnings), active Popup Forms belong to the Site and have fields. All lookups are Site-scoped. Subdomain and SEO checks are added with P5-008 / P5-009.

---

## P5-005 — Published Snapshot Builder

**Status:** DONE
**Dependencies:** P5-001, P5-004

Result: `PublishedSnapshotBuilder` reads the Draft in one transaction and builds the public manifest (Site identity, branding flag from `remove_branding`, resolved design tokens, CAPTCHA requirement, Pages with SEO and visible Blocks pinned to definition slug + version, referenced Site Asset IDs, display-ready vehicles with media image public IDs and server-only exact offer money, Popups, published Form definitions) and the private draft snapshot (all Pages and Blocks incl. hidden, vehicles/offers/benefits/media-set selection, all Forms with fields, all Popups). Canonical-JSON SHA-256 `manifest_hash`; same Draft → same manifest. No secrets, Submissions, blacklists, form security or numeric IDs. `PublishedArtifactBuilder` derives one version-scoped payload per Page (`/_landflow/assets|media|forms/{version}/…`, no Draft URLs), renders all Pages with the compiled React renderer outside any transaction, rejects missing/empty artifacts and stores `published_pages` + `published_asset_references`; it never activates. Renderer: `resources/js/public-runtime/{published-site,render-server,hydrate-client}.tsx`, built by `npm run build` (`vp build --ssr` → `bootstrap/ssr/render-server.js`), invoked by `NodePageRenderer` via the Process API (argument array, STDIN, timeout, safe errors). PHPUnit binds a deterministic fake renderer; `NodePageRendererTest` runs the real bundle when built.

---

## P5-006 — Atomic Activation

**Status:** DONE
**Dependencies:** P5-005

Result: `App\Publishing\PublishSite` orchestrates a whole-Site Publish. A short Site-locked transaction creates the Publication; a second attempt while one is running is recorded as a failed `conflict`, and attempts stuck past `publishing.stale_after_minutes` are abandoned (`internal_error`, `abandoned`). Validation, snapshots and SSR run outside transactions; the version number is allocated under the Site lock (gaps allowed). The final transaction re-checks the attempt and the building version, verifies every artifact (one per manifest Page, non-empty, hash match), marks the version `ready`, switches `sites.active_published_version_id` and marks the Publication `succeeded`. Any failure fails the attempt with a safe code, fails a building version, keeps the previous production and Draft, and logs only the publication public ID, failure code and exception class. «Публикация» page (`sites/{site}/publishing`, `view_site`): production version, last attempt, live pre-publish check, Publish button (`publish_site`, throttled); Designer header link. Tests: first/second publish, production unchanged during render, validation and render failures, concurrent and abandoned attempts, Owner/Admin allowed, Designer/Content Editor forbidden, foreign Workspace 404.

---

## P5-007 — Public Runtime

**Status:** DONE
**Dependencies:** P5-006

Result: Public hosts `{subdomain}.{LANDFLOW_PUBLIC_DOMAIN}` (`config/publishing.php`, `routes/public.php`, registered before `web` and without session/CSRF/Inertia) resolve host → active Site → active ready Published Version → `published_pages` artifact; nothing reads the Draft. `sites.subdomain` (nullable, unique) was added for host resolution; label rules, generation and UI belong to P5-008. Reserved labels never resolve. Pages are cached as `published:{site}:{version}:{page}`, served as the stored HTML plus the stored hydration payload (`resources/views/published/page.blade.php`, `hydrate-client.tsx`); unknown hosts, unpublished Sites, unknown paths and every application route on a public host return the safe Russian 404. Version-scoped files `/_landflow/assets|media/{version}/{id}` are served only when a ready version of that Site references them (`nosniff`, `public, max-age=31536000, immutable`); historical versions keep working, Draft-only and foreign files are 404. Public leads post only to `/_landflow/forms/{version}/{form}`: the Form definition comes from that version's manifest, the version must be ready with the Site active and published, and page/block/popup/vehicle/offer context and the price come from the manifest (`PublishedSubmissionContext`), never from the Draft or the browser; mode `public`. The old Draft endpoint `POST /forms/{form}/submissions` was removed; the preview endpoint keeps the Draft path with mode `preview`. Platform Series media images referenced by a Published Version cannot be deleted. `NodePageRenderer` passes `PATH`/`SystemRoot`/`TEMP`/`TMP` explicitly (web SAPIs filter child environments). Tests: `PublishedRuntimeTest`, Forms suite on the published path, Playwright `publishing.spec.ts` (publish, raw HTML before JS, hydration, public lead, Draft isolation).

---

## P5-008 — Landflow Subdomains

**Status:** DONE
**Dependencies:** P5-007

Result: `App\Support\SiteSubdomain` owns the label rules: one lowercase DNS label (`a-z`, `0-9`, inner hyphens, ≤ 63), globally unique across all Sites including archived, reserved `www/admin/api/app/support/static/assets/platform/auth/login/register` plus IDN `xn--` labels. `CreateSite` assigns a transliterated suggestion from the Site name (`changan-moskva`, then `-2`, `-3`…; reserved → `-site`; nothing usable → `site`); renaming never changes it. Migration `2026_10_08_000005` backfills existing Sites. Explicit change on «Публикация» → «Адрес сайта» (`PUT sites/{site}/subdomain`, `manage_domains`: Owner/Admin; Russian errors; unique-index race → validation error); the public host switches immediately and the old address returns 404. Pre-publish check `subdomain_missing`. Local root `LANDFLOW_PUBLIC_DOMAIN=localhost` (`dealer.localhost`), no custom domains, no plan gating, no billing. Tests: `SiteSubdomainTest`, Playwright address change.

---

## P5-009 — SEO / Sitemap / Robots

**Status:** DONE
**Dependencies:** P5-007

Result: Pages gained Draft SEO (`seo_title` ≤ 120, `seo_description` ≤ 300, `seo_noindex`), edited in the designer («Страницы» → «SEO страницы», `PATCH sites/{site}/pages/{page}/seo`): title/description need `edit_seo` or `edit_seo_basic` (Owner, Admin, Content Editor), indexing needs `edit_seo` (Owner, Admin); Designer forbidden. The manifest carries `seo.title` (falls back to the Page title), `seo.description`, `seo.indexable`; the private snapshot keeps the raw SEO fields for restore. Published head: `<title>`, meta description only when set, `robots` `index, follow` / `noindex, follow`, canonical `{scheme}://{subdomain}.{domain}{path}`, Open Graph type/locale/title/url/site name and description only from real values — no `og:image` is ever invented. Public `/sitemap.xml` lists only indexable Pages of the active version (`lastmod` = version ready time); `/robots.txt` allows the Site, disallows `/_landflow/forms/` and points to the sitemap; both 404 without a published version and never contain Draft or application URLs. Preview responses send `X-Robots-Tag: noindex, nofollow` (plus the existing meta). Draft SEO edits reach visitors only after Publish. Tests: `PublishedSeoTest`, Playwright SEO dialog → raw head + sitemap.

---

## P5-010 — Version History / Restore

**Status:** DONE
**Dependencies:** P5-006

Result: «Публикация» → «История версий» lists the last 30 versions (number, date, publisher, status label, «На сайте» badge on the production version); the private snapshot never reaches React. `POST sites/{site}/versions/{version}/restore` (`restore_version`: Owner only; foreign Site/version 404; throttled) runs `App\Publishing\RestoreVersion` in one transaction with the Site locked: only ready versions with the current snapshot schema; the private Draft snapshot rebuilds Site name/design tokens, Pages (incl. SEO), Block instances (pinned versions, state, hidden, order), Forms + Fields, Popups, Site Vehicles (status, order, active media sets), Offers (price, availability, badge, CTA, benefits). Public IDs are reused; a public ID owned by another Site aborts (`RestoreFailed`, Russian toast). Forms absent from the version are switched off, never deleted (leads keep their Form); vehicles/offers whose catalog series/equipment disappeared are skipped and reported. Submissions, blacklists, form security, subdomain, Integration data, Catalog and Platform Series Media are untouched. Production pointer, versions and publications do not change; the next Publish creates a new version (same manifest hash as the restored one). Tests: `RestoreVersionTest`.

---

## P5-011 — Publishing E2E

**Status:** DONE
**Dependencies:** P5-008, P5-009, P5-010

Result: `tests/browser/publishing-lifecycle.spec.ts` on the seeded `lifecycle-e2e` Site (E2eSeeder: Owner + Designer member, ready Draft v1 with hero and priced vehicle card, dedicated Moskvich catalog branch with a real media file) covers: public 404 before the first Publish → Draft v1 in preview → Publish v1 → no-JS HTML with heading and price → Draft v2 (heading, Offer price, Form field label) while production stays v1 → public Popup uses the published v1 Form and posts a real lead to `/_landflow/forms/…` → preview shows v2 and posts a preview test lead → leads listed apart → Publish v2 with the v1 media URL still served → scroll action to a hidden block blocks Publish (error listed, button disabled) → fix → Publish v3 → version history (production badge, publisher) → restore v1 → preview restored, production still v3 → Publish creates v4 with v1 content → hydrated Popup at 375 px without horizontal overflow → Designer sees no Publish/restore → referenced platform image cannot be deleted. Console errors and failed requests fail the test (fixture). Existing `publishing.spec.ts` keeps SEO head/sitemap and subdomain change coverage.

### Flow

Draft
→ Preview
→ production unchanged
→ Publish
→ production changes.

Also test failed Publish.

---

## P5-012 — Phase 5 Review

**Status:** DONE
**Dependencies:** P5-011

Result: Phase 5 audit against ADR-006 and the publishing acceptance list. Fixed: the default static `public/robots.txt` shadowed the per-Site `/robots.txt` on public hosts (web servers serve existing files before routing); it was removed and the application host now serves the same body from a route (`PublishedSeoTest` guards against static `robots.txt` / `sitemap.xml`). Added coverage: catalog and Block catalog changes leave production HTML byte-identical (`PublishedRuntimeTest`); the lifecycle E2E now also covers hero image A → B stability, public v2 kept during a blocked Publish, robots/canonical/sitemap, carousel, lightbox, color switch, offer details and offer Popup after hydration, Designer preview, Admin publish without restore, foreign Workspace 404. Template independence is structural: a Site keeps no Template reference. Follow-ups (not Phase 5 criteria): «unpublished changes» indicator (PUBLISHING.md §23), publication notes, rollback, audit log of restores.

Final Phase 5 gate (commit `bd45982`): `composer quality` PASS (593 tests), PHPStan PASS, Pint PASS, `npm run check` and build PASS, Playwright `npm run test:e2e` 43 passed, `git diff --check` PASS. Local MySQL (`landauto`) incremental migrations `2026_10_08_000004`–`000006` applied (no catalog migrations). Phase 5 COMPLETED; Phase 6 not started.

---

# PHASE 6 — INTEGRATIONS & ANALYTICS

---

## P6-001 — Workspace Integration Profiles

**Status:** DONE  
**Dependencies:** P5-012

### Result

- Table `integration_profiles` (ULID `public_id`, Workspace-owned, provider `webhook` | `custom_api`, auth `none` | `bearer` | `basic` | `api_key_header`, `encrypted_credentials`, `settings_json`, status `active` | `disabled` | `archived`). Owner and provider type are immutable.
- Russian «Интеграции» page at `/integrations`, shown in the sidebar to members with `view_integrations`. Members with `manage_integrations` can create, edit non-secret fields, disable and delete profiles. A profile that is still referenced is archived instead of deleted.
- Profiles are always resolved inside the current Workspace; a foreign public ID returns 404. No named CRM adapters.
- Permission reconciliation D-109: Admin gains `view_integrations`, `edit_form_routes`, `view_delivery_logs` and `retry_deliveries`; Designer and ContentEditor get no integration permissions.

---

## P6-002 — Secret Encryption / Masking

**Status:** DONE  
**Dependencies:** P6-001

### Result

- Credentials are stored with Laravel's `encrypted:array` cast (application key, no custom cryptography). The model hides `encrypted_credentials` and the hint; browser props are built explicitly.
- The UI shows a fixed mask `••••••••` plus the last four characters only for secrets of at least 12 characters (`••••••••7K2F`). Secret inputs are never prefilled; an empty input keeps the stored value, a new value replaces it, a changed auth type requires a full new secret, and `none` drops stored credentials.
- Secret inputs (`credential_*`) are excluded from session old-input flashing; tests assert the fake token is absent from the HTML page, Inertia JSON, model serialization, the raw DB row, the session and validation responses.

---

## P6-003 — Site Integration Bindings

**Status:** DONE  
**Dependencies:** P6-001

### Result

- Table `site_integration_bindings` (ULID `public_id`, Site, profile, optional name, `overrides_json` key/value map, `mapping_defaults_json`, status). Site and profile are immutable; the model refuses a profile from another Workspace.
- «Интеграции сайта» page (`/sites/{site}/integrations`, linked from the Designer «Разделы» menu): view with `view_integrations`; bind, edit overrides (up to 20 `snake_case` keys, single-line values), disable and unbind with `manage_integrations`. Only active profiles of the Site's own Workspace can be bound; a foreign profile is a validation error.
- Tokens are never copied to the binding. A bound profile is archived instead of deleted.

---

## P6-004 — Form Routes

**Status:** DONE  
**Dependencies:** P4-003, P6-003

### Result

- Table `form_routes` (ULID `public_id`, Form, name, destination `email` | `webhook` | `custom_api`, profile and Site binding, `email_destination`, `mapping_json`, `settings_json`, status, sort order). Form, destination and binding are immutable. The model refuses a binding of another Site or one whose profile provider type does not match.
- «Передача заявок» page (`/sites/{site}/forms/{form}/routes`, linked from the Form page), gated by `edit_form_routes`. Email routes take up to 5 RFC-valid recipients, a subject restricted to the placeholders `{form.name}`, `{site.name}`, `{vehicle.title}`, `{offer.price}`, and an optional reply-to email field. HTTP routes take an active binding of this Site, a method (`POST` | `PUT` | `PATCH`), an optional path and up to 10 safe headers (transport, proxy, cookie, Authorization, Content-Type and Idempotency-Key are forbidden).
- Routes are independent; at most 10 per Form. A route with delivery history is archived instead of deleted; a binding used by a route is archived instead of unbound. Central settings live in `config/integrations.php`.

---

## P6-005 — Field Mapping

**Status:** DONE  
**Dependencies:** P6-004

### Result

- Declarative mapping rules on HTTP routes (`mapping_json`: `target`, `source`, `missing` = `omit` | `null` | `error`, optional constant `value`), up to 50 rules, targets up to 4 dotted `snake_case` segments, no expressions or code.
- Allowlisted sources (`MappingSources`): Form fields `field.<key>`, Submission public ID and time, Form, page URL/title, referrer, UTM, Popup, Block, trusted vehicle and offer snapshot (public IDs, titles, price, currency), Site name/URL and binding overrides `override.<key>`. Unknown sources and targets, conflicting targets, unknown field or override keys are rejected on save; numeric internal IDs and blacklist data are not sources.
- `FieldMapper` builds the payload from `MappingContext` (Submission + optional binding, refuses a binding of another Site). Empty rules produce a deterministic default payload. A missing required value fails with `missing_required_value`.
- Route editor gains a «Сопоставление полей» section with grouped source selects.

---

## P6-006 — Delivery Records / Jobs

**Status:** DONE  
**Dependencies:** P6-004

### Result

- Tables `submission_deliveries` (ULID `public_id`, Submission, Form Route, destination, status `pending` | `processing` | `delivered` | `retry_scheduled` | `failed` | `cancelled`, attempt count, next retry, delivered/last attempt time, last HTTP status, safe error code and Russian message; unique Submission + route) and `submission_delivery_attempts` (attempt number, trigger `automatic` | `manual`, start/finish, outcome, HTTP status, provider code, latency, allowlisted scalar summary, safe error).
- `SubmissionPipeline` persists the Submission first, then `DeliveryDispatcher` creates one Delivery per active route of public Submissions only (preview never delivers) and queues `ProcessSubmissionDelivery` after commit with only the Delivery ID. A dispatch failure never undoes the lead; the visitor response does not wait for providers (database queue in production).
- Adapter contract `DeliveryAdapter` (`supports`, `deliver`, `testConnection`) returning `DeliveryResult` (`success` | `transient_failure` | `permanent_failure`, HTTP status, provider code, safe message, safe metadata). `DeliveryProcessor` claims atomically, records the Attempt, maps disabled route → `cancelled`, disabled profile/binding or missing adapter → `failed`, unexpected adapter exceptions → transient `internal_error` (only the class name is logged). Failure logs carry only delivery/profile public IDs, provider type, HTTP status, error code and attempt number.

---

## P6-007 — Retry / Idempotency

**Status:** DONE  
**Dependencies:** P6-006

### Result

- Idempotency: unique Submission + route Delivery; the Delivery `public_id` is the stable key sent as `Idempotency-Key` on every attempt; a single conditional UPDATE claims a due Delivery, so duplicate jobs, duplicate dispatches and concurrent workers never repeat a provider call.
- Retry ladder: immediate, +1, +5, +15, +60 minutes (`INTEGRATIONS_RETRY_DELAYS`), then `failed`. Retries are stored on the row (`next_retry_at`) and queued by `integrations:dispatch-due-deliveries`, scheduled every minute without overlap (production needs the Laravel scheduler and a queue worker). The command also recovers deliveries stuck in `processing` (`worker_lost`) and pending deliveries whose job was lost.
- `DeliveryFailures` is the shared classifier: timeout, DNS, network, 408, 425, 429, 500, 502, 503, 504 are transient; 3xx (`redirect_not_followed`), 401, 403, other 4xx and other 5xx are permanent, with short Russian messages.
- Manual retry `POST /sites/{site}/deliveries/{delivery}/retry` requires `retry_deliveries`, works only for `failed` deliveries of this Site (foreign → 404), creates a new `manual` Attempt and never rewrites the Submission.

---

## P6-008 — Email Adapter

**Status:** DONE  
**Dependencies:** P6-006

### Result

- `EmailDeliveryAdapter` sends `SubmissionLeadMail` through Laravel Mail inside the delivery job. Recipients are re-validated at send time (none valid → permanent `invalid_recipients`); the subject uses only the allowlisted placeholders and is a sanitized single line; reply-to is set only from a valid value of the configured email field.
- Escaped Blade template `mail/submission-lead`: Form, Site and Site URL, submitted fields (Да/Нет for checkboxes), vehicle/color/offer/price, page, Popup, referrer and UTM, Submission public ID and time. No IP, user agent, numeric IDs, blacklist data or credentials.
- SMTP/transport failures are transient (`mail_transport_error`, exception text never stored). Tests use `Mail::fake()`.

---

## P6-009 — Webhook / Custom API Adapter

**Status:** DONE  
**Dependencies:** P6-006

### Security

SSRF protection mandatory.

### Result

- `HttpDeliveryAdapter` (webhook and custom API): profile base URL + route path, route method (`POST` | `PUT` | `PATCH`), re-validated safe headers, mapped JSON payload (`FieldMapper::fromStored`, malformed rules fail permanently), `Idempotency-Key` = Delivery public ID.
- One `OutboundHttpPolicy` (shared with Test Connection): `https` only unless `INTEGRATIONS_HTTP_ALLOW_PLAIN` is set; no userinfo, fragment, control characters or numeric/hex/short IP hostnames; every resolved A/AAAA address must be public (loopback, RFC 1918, CGNAT, link-local/metadata, multicast, reserved, documentation, unique-local, NAT64, 6to4, IPv4-mapped/compatible IPv6 refused). DNS goes through `HostResolver` (system in production, fake in tests); an empty answer is a transient `dns_error`.
- `OutboundHttpClient`: connection pinned to the vetted address (cURL `RESOLVE`, so DNS rebinding cannot redirect it), redirects never followed (3xx = permanent `redirect_not_followed`), environment proxies disabled, TLS verification on, 3 s connect / 10 s total timeouts, response read aborted past 1 MB. Auth `none` | `bearer` | `basic` | `api_key_header` is built only from encrypted credentials and always overrides route headers. Only status, latency, response size, content type and a well-formed request ID are kept; bodies, tokens and Authorization are never stored or logged.
- Tests never touch the network: the base `TestCase` enables `Http::preventStrayRequests()` and an empty fake resolver.

---

## P6-010 — Delivery Logs UI

**Status:** DONE  
**Dependencies:** P6-007, P6-008, P6-009

### Result

- «Доставка заявок» page (`/sites/{site}/deliveries`, `view_delivery_logs`; Owner and Admin): per Submission + route status badge, destination, Form and time, attempt count, delivered / next retry time, safe Russian error with HTTP status, collapsible attempt history (manual flag, outcome, HTTP status, latency) and a status filter. No lead field values, provider bodies, credentials or numeric IDs.
- «Повторить» button on failed deliveries only with `retry_deliveries`. Linked from the Designer «Разделы» menu and the Submissions page; the Submissions page shows per-lead delivery badges only to members with `view_delivery_logs`. No CRM features.

---

## P6-011 — Test Connection

**Status:** DONE  
**Dependencies:** P6-001, P6-009

### Result

- «Проверить подключение» on the «Интеграции» page (`POST /integrations/{profile}/test`, `manage_integrations`, current Workspace only, foreign → 404). It calls the provider adapter's `testConnection`, i.e. the same `OutboundHttpClient`: same SSRF policy, pinned connection, timeouts, auth builder and classifier as delivery. It sends `POST {"event":"landflow.test_connection"}` with a `test-…` Idempotency-Key to the profile base URL and creates no Submission or Delivery.
- The result is a Russian toast with success, HTTP status and latency, or the safe failure message; tokens and provider bodies are never returned. Rate limit per user: `INTEGRATIONS_TEST_CONNECTION_PER_MINUTE` (default 5).

---

## P6-012 — Yandex Metrica Adapter

**Status:** DONE  
**Dependencies:** P5-007

### Result

- Official API checked 2026-10-05 ([counter initialization](https://yandex.ru/support/metrica/code/counter-initialize.html), [reachGoal](https://yandex.ru/support/metrica/objects/reachgoal.html)): async loader for `https://mc.yandex.ru/metrika/tag.js` defining `window.ym`, `ym(ID, "init", {clickmap, trackLinks, accurateTrackBounce, webvisor})`, goals via `ym(ID, "reachGoal", target)`; goal IDs must not contain `/ \ & # ? = "`. Matches the architecture; no contradiction.
- `site_analytics_settings` (DATABASE.md §63): enabled, counter ID (digits, 4–15, public by design), clickmap / trackLinks / accurateTrackBounce (default on), Webvisor (default off). Edited in «Интеграции сайта» → «Яндекс Метрика» with `manage_integrations` (Owner, Admin); Designer / ContentEditor 403.
- Saving changes only Draft settings. Publish copies the safe subset into the Published manifest (`analytics.yandex_metrica`, only when enabled, so other Sites keep their hash); the published page emits the official loader, `init` and the noscript pixel only when the active version has it. No user IDs, `userParams` or form data. Authenticated Preview never loads Metrica.

---

## P6-013 — Semantic Analytics Events

**Status:** DONE  
**Dependencies:** P6-012

### Result

- Published runtime event bus (`public-runtime/analytics.ts`): `form.start` (first focus), `form.submit`, `form.validation_error` (client or server field errors), `form.success`, `popup.open`, `popup.close`, `vehicle.form_submit` (Form opened with a vehicle context). Events carry no payload: no field values, phone, email, IP, IDs or Submission data.
- Metrica adapter connects once at hydration when the active version has a counter and calls `ym(counter, "reachGoal", goal)` with goals `form_start`, `form_submit`, `form_validation_error`, `form_success`, `popup_open`, `popup_close`, `vehicle_form_submit` (no reserved characters). Without a counter, or if the loader is blocked, it is a silent no-op; listener errors are swallowed so analytics never breaks the site. Editor previews and authenticated Preview emit nothing.

---

## P6-014 — Integrations E2E

**Status:** DONE  
**Dependencies:** P6-010 through P6-013

### Result

- `tests/browser/integrations.spec.ts` (seeded Owner / Admin / Designer on `integrations-e2e`): profile with masked token (absent from HTML/props after save and reload), Test Connection, SSRF refusal of a host resolving to a private network, Site binding with override, Metrica settings, email + webhook routes with field / trusted price / override mapping, Publish, public lead (201 while both Deliveries are still queued), worker → delivered, 503 → retry scheduled → delivered on attempt 2, 401 → failed with one attempt, Admin manual retry → attempt «№2 (вручную)», preview lead → no Delivery and no Metrica, Designer 403 and foreign Workspace 404, published loader + `ym` goals (`popup_open`, `form_start`, `form_validation_error`, `form_submit`, `vehicle_form_submit`, `form_success`, `popup_close`) with no phone, email or name.
- E2E-only fakes (`app/Integrations/Testing/E2eIntegrationFakes`, `INTEGRATIONS_E2E_FAKE`, honoured only in `testing` / `e2e`): fixed DNS answers and a fake CRM transport; the real SSRF policy, auth builder and classifier still run and no request leaves the machine. E2E uses the `database` queue driven by the spec (`queue:work --stop-when-empty`, `integrations:dispatch-due-deliveries`) with zero retry delays; production queue and SSRF settings are unchanged.

---

## P6-015 — Phase 6 Review

**Status:** DONE  
**Dependencies:** P6-014

### Result

- Audit (no Phase 6 regressions found, no code changes): Workspace owns profiles and Sites store only bindings/overrides (no credential copies); credentials are `encrypted:array`, hidden from serialization, shown only as a mask and never re-displayed; Submission is persisted before any Delivery is created and the job is dispatched after commit; preview Submissions never create Deliveries; one Delivery and attempt log per route (route failures independent), bounded retry ladder, Delivery `public_id` as idempotency key, manual retry under `retry_deliveries`; email and HTTP adapters run in the queued job; one SSRF policy for delivery and Test Connection (https, public IPs only, pinned connection against DNS rebinding, no redirects, no proxy, timeouts, response cap); delivery logs keep only safe Russian messages and metadata (no bodies, tokens or lead values); logs carry public IDs, provider type, HTTP status, error code and exception class only; permissions are semantic keys (no role or plan checks); Metrica config is frozen into the Published manifest, loaded only on published pages, never in Preview, goals carry no payload. No Phase 7 work started.
- Notes: Metrica settings are live Draft settings like form security, so restoring an old version to Draft does not change them; the next Publish uses the current settings.

Final Phase 6 gate: `composer quality` PASS (728 tests, PHPStan, Pint, `npm run check`, build), Playwright `npm run test:e2e` 44 passed, `git diff --check` PASS. Phase 6 COMPLETED; Phase 7 not started.

---

# PHASE 7 — PAID SITE FEATURES

---

## P7-001 — Custom Domain Schema

**Status:** DONE
**Dependencies:** P6-015, X-022
**Decision:** D-111

### Result

- `site_domains` (ULID `public_id`, Site FK, globally unique normalized `hostname`, per-hostname `verification_token`, independent `verification_status` / `routing_status` / `ssl_status`, `is_primary`, lifecycle timestamps, safe last error). No certificate or key columns.
- `CustomHostname` normalization (trim, lowercase, one trailing dot) and validation (scheme, path, query, fragment, port, userinfo, wildcard, empty/oversized labels, control chars, IP literals, IDN/punycode with a clear Russian message, Landflow-owned hosts).
- `CustomDomainAccess`: `manage_domains` AND `custom_domain` entitlement, separately checked; Free denied by default.
- «Домены» (`/sites/{site}/domains`) in the Site shell: Landflow address, add domain, per-domain state (ожидает проверки, DNS настроен не полностью, DNS настроен, выпуск сертификата, активен, ошибка), exact TXT / CNAME / A / AAAA instructions from `config/domains.php`, delete with confirmation. Safe log events `custom_domain.added` / `custom_domain.removed`.
- Tests: create, Free denied, entitlement without permission, Admin allowed, foreign Site/domain, case-insensitive duplicate, trailing dot, invalid hostname matrix, IDN message, removal.

---

## P7-002 — Domain Validation / Verification

**Status:** DONE
**Dependencies:** P7-001
**Decision:** D-111

### Result

- `DnsResolver` abstraction: `SystemDnsResolver` (`dns_get_record`, bounded answers) and `FakeDnsResolver` (cache-backed; bound only when `CUSTOM_DOMAIN_DNS_DRIVER=fake` in the testing/e2e environments). No HTTP fetch of the customer host.
- `DomainVerifier`: ownership by exact TXT match on `_landflow-verification.<host>` (sticky once verified); routing re-evaluated every check — CNAME chain (≤5 hops) reaching `CUSTOM_DOMAIN_CNAME_TARGET`, or all A answers equal to `CUSTOM_DOMAIN_IPV4` and AAAA empty or equal to `CUSTOM_DOMAIN_IPV6`. Independent states, safe error codes and Russian messages only; log events `custom_domain.ownership_verified` / `custom_domain.routing_verified`.
- Manual «Проверить DNS» (`POST /sites/{site}/domains/{domain}/check`), server-side, rate-limited per Site (`domain-checks`, `CUSTOM_DOMAIN_CHECK_PER_MINUTE`).
- `domains:reconcile` scheduled every 5 minutes (`withoutOverlapping`): pending domains after `reconcile_after_minutes`, ready domains daily; skips Workspaces without `custom_domain`. `domains:fake-dns` E2E helper refuses without the fake driver.
- Tests: TXT correct/wrong/missing, A ingress, CNAME chain, wrong routing, AAAA mismatch/normalization, missing ingress config, sticky ownership + routing regression, no HTTP, logs, rate limit, permission, reconcile scope, fake command guard.

---

## P7-003 — SSL Provisioning Integration

**Status:** DONE
**Dependencies:** P7-002
**Decision:** D-111

### Result

- Verified against Let's Encrypt documentation (challenge types, rate limits): HTTP-01 on port 80 after routing is verified; works for hosts CNAMEd to the ingress; no wildcard.
- `SslProvisioner` contract with drivers `none` (default; safe «не настроен» message), `command` (configured executable, hostname as a discrete argument via Laravel/Symfony Process, hostname re-validated, timeout, exit-code contract, bounded output parsing, nothing logged) and `fake` (testing/e2e only; success, temporary and permanent failure).
- `DomainSsl`: eligibility (active Site, verified ownership + routing, `custom_domain` entitlement, hostname not active for another Site; initiator `manage_domains` + entitlement checked by the controller), atomic claim to `provisioning`, queued `ProvisionDomainSsl` re-checks eligibility, statuses pending / provisioning / active / failed, bounded backoff (`ssl_attempts`, `ssl_retry_at`), stale-provisioning recovery, safe log events. DB stores metadata only (issued / expiry dates).
- DNS check that completes DNS requests the certificate; `domains:reconcile` requests due certificates; manual «Выпустить сертификат» (`POST /sites/{site}/domains/{domain}/ssl`, 4/hour per domain).
- Tests: before DNS ready, fake success / temporary backoff → failed / permanent → manual retry, job re-checks eligibility, lost entitlement, none driver, stale recovery, command argv + timeout + expiry parsing, exit-code mapping, invalid hostname / missing script refusal, no key columns, endpoint permission + rate limit, DNS check → SSL, reconcile.

---

## P7-004 — Primary Domain / Redirects

**Status:** DONE
**Dependencies:** P7-003
**Decision:** D-111

### Result

- Public runtime has a second host group for custom hostnames (`PublicSiteResolver::customHostPattern()`: dotted names except the app host, `localhost`, the public domain and its subdomains, IPv4 literals). Custom domains resolve first, then Landflow subdomains; unknown hosts and application paths on customer hosts are safe 404s.
- A custom domain is served only while fully active (ownership + routing + SSL), the Site is active and the Workspace keeps `custom_domain`. Effective primary = `is_primary` + active + entitled; otherwise the Landflow subdomain.
- 301 to the primary host with path and query preserved: Landflow subdomain → custom primary; alternate custom hosts → primary (or → Landflow subdomain when no custom primary). The primary host never redirects, so no loops.
- «Сделать основным» (`POST /sites/{site}/domains/{domain}/primary`, active only, one primary per Site under a Site row lock), «Сделать основным адрес Landflow» (`DELETE /sites/{site}/domains/primary`); removing the primary falls back to the subdomain.
- Canonical / `og:url`, sitemap, robots, dashboard and Site overview addresses and integration payload Site URL use the primary address; the Publishing page keeps managing the Landflow subdomain.
- Tests: primary serving + canonical / sitemap / robots, subdomain → primary 301 with query, alternate 301, no-primary alternate → subdomain, no-SSL not served / not primary, lost entitlement fallback, removal fallback, reset, single primary + permission + foreign, unknown host / app paths 404, app hosts never custom, archived Site. Browser: `tests/browser/domains.spec.ts` (fake DNS + fake SSL → active → primary → publish → 301 + canonical + sitemap + robots on `dealer.e2e.test`, Designer has no access, 375px). Chromium maps `*.e2e.test` to the local server and ignores system proxies.
- Follow-ups (not blocking): an unverified hostname row blocks the real owner until removed (no expiry of unverified claims yet); certificates of removed domains are not deprovisioned by the app (infrastructure cleanup); an app reachable under extra dotted hostnames besides `APP_URL` would see them as customer hosts.

---

## P7-005 — Landflow Branding Entitlement

**Status:** DONE
**Dependencies:** P1-009, P5-007
**Decision:** D-112

### Result

- `branding` removed from the Published Version manifest, hydration payload and React runtime; the public page shell renders «Создано на Landflow» (`data-testid="landflow-branding"`) outside `#lf-root` unless the Workspace currently has `remove_branding`.
- Tests: footer for Workspaces without the entitlement, grant → absent / revoke or plan change → visible again on the same version, nothing stored in rendered HTML / hydration / manifest. Browser: footer visible on the custom primary domain, entitlement granted → absent after reload.

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

**Status:** DONE
**Trigger:** before P3-009  
**Resolves:** D-084

Decision must cover: integer minor units vs fixed decimal, currency representation, rounding, Block price field presentation. Until accepted, no money columns anywhere (including P1-009 Plans).

### Result

ADR-004 (`docs/architecture/decisions/ADR-004-money-storage-representation.md`) records the owner-approved D-084:
- Money is stored in integer minor units in `BIGINT UNSIGNED` `price_minor` / `rrp_minor` / `amount_minor` columns, with currency in `CHAR(3)` (uppercase ISO 4217).
- Arithmetic is integer-only; formatting happens at the UI boundary.
- Decimal input is converted by the currency's minor-unit rules on the backend before persistence.
- Any percentage is stored in basis points.

This is a docs-only decision; no code or columns were added.

---

## X-009 — ADR: Characteristic Value Schema

**Status:** DONE
**Trigger:** before P3-001  
**Resolves:** D-086

Decision must cover: storage of characteristic values across Generation / Modification / Trim and override/inheritance resolution.

### Result

ADR-005 (`docs/architecture/decisions/ADR-005-equipment-characteristic-model.md`), D-086 APPROVED:
- No inheritance engine; values belong to Equipment.
- `auto_characteristics` is a two-level tree (group → parameter); the unit lives on the definition.
- The value is TEXT with no unit. Missing data means no row.
- Codes for the Modification filter fields (`engine_volume`, `engine_power`, `consumption_100_km`, `acceleration_0_100`) are reserved and never duplicated as characteristics.

---

## X-010 — ADR: Media Ownership and Asset Versioning

**Status:** DONE
**Trigger:** before P2-013  
**Resolves:** D-087, D-075

Decision must cover: Site asset vs Workspace asset ownership/reference model, immutable/versioned public asset strategy compatible with Published Version stability.

### Result

ADR-003 (`docs/architecture/decisions/ADR-003-site-asset-ownership-and-versioning.md`) is based on the owner instruction "P2 Site assets are customer Site assets":
- Assets are Site-owned and referenced from Block state by ULID, with same-Site validation.
- Files are immutable; replacement means a new asset; deletion must respect references.
- Storage is private, served through an authenticated policy-checked controller.
- Accepted formats are JPEG, PNG and WebP up to 10 MB; SVG is rejected.
- Uploading requires `manage_assets`.
- The Workspace Media Library is deferred, and the Series Media Library stays separate.

D-087 is APPROVED for the Phase 2 scope; D-075 is APPROVED (direction).

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

**Status:** DONE

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

### Result

- The starter Dashboard finding was superseded and resolved by the semantic, responsive current-Workspace Dashboard delivered in P1-014.
- Registration uses natural DOM tab order; password visibility controls are keyboard reachable; the auth logo has the accurate `На главную` accessible name.
- Login success feedback now uses the shared theme-aware Alert above the form; the profile breadcrumb is `Профиль`; active settings links expose `aria-current="page"`.
- Verify-email links directly to profile correction. Unverified users no longer receive Dashboard, Workspace switcher, Security or Appearance navigation that would redirect silently.
- All current form validation messages are live alerts linked to their inputs with `aria-describedby` and `aria-invalid`.
- Focused browser coverage now includes the 320px login row, confirm-password screenshot, responsive email-change state and responsive unverified-profile/navigation state. Local Playwright remains environment-blocked at config webServer startup; CI is the browser gate.
- The undefined support-contact wording remains intentionally assigned to X-013 before launch; no unsupported contact was invented in X-012.

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

## X-015 — Default Free Plan for New Workspaces

**Status:** DONE
**Resolves:** D-100

Newly registered Workspaces had no plan, so their effective `max_sites` was 0 and a new customer could not create a Site.

Acceptance Criteria:

- new personal Workspaces (email/password and Yandex OAuth) get the active system Free plan through `CreateNewAccount`;
- Free resolves `max_sites = 2`; no other Free entitlements are invented;
- the third active Site is denied by existing enforcement; archived Sites do not count (D-099);
- no plan-name business checks; no billing/pricing/subscriptions.

### Result

Added the stable system key `Plan::FREE_KEY` and `App\Support\DefaultWorkspacePlan`, which idempotently creates the active Free plan (`createOrFirst` on the unique key, `max_sites = 2` only on first creation) and never overwrites existing values. `CreateNewAccount` assigns it to the personal Workspace inside the account transaction, so registration and Yandex OAuth share the same default. Existing Workspaces are not backfilled. Checks: `DefaultFreePlanTest` (Free plan and limit on registration, shared idempotent plan, two active Sites allowed / third denied / archived Site frees a slot, no raw plan-name checks in `app/` and `resources/js`), extended Yandex new-user test, Registration/Workspaces/Sites/E2E seeder tests, PHPStan, Pint.

---

## X-016 — Automotive Catalog Architecture V2

**Status:** DONE
**Trigger:** before P3-001

Reconcile the automotive architecture with the owner-approved Catalog V2 (`docs/architecture/AUTO_CATALOG_SCHEMA.md`).

### Result

- `AUTOMOTIVE_DATA.md` §0 is the authoritative V2 summary; incompatible sections are marked SUPERSEDED, not deleted.
- `DATABASE.md` §19, `TENANCY.md` and `PERMISSIONS.md` record:
  - the separate physical catalog database (connection `catalog`, no cross-database foreign keys, `public_id` references);
  - the platform permissions `view_catalog` / `edit_catalog` / `manage_catalog_media`.
- New decisions:
  - D-101 — V2 hierarchy and ten tables; supersedes D-018;
  - D-102 — separate catalog database;
  - D-103 — platform Series Media Library; supersedes D-023/D-024;
  - D-104 — SiteVehicle → Series, SiteOffer → Equipment;
  - D-105 — preview permission;
  - D-106 — storage quota direction.
- Phase 3 tasks were rewritten to the V2 scope:
  - P3-005 → Series Media Sets;
  - P3-006 → Series Media Images;
  - P3-010 → Customer Vehicle Flow.

---

## X-017 — Workspace Asset Storage Quota

**Status:** NOT_STARTED
**Trigger:** before the first public production deployment
**Decision:** D-106

Typed Workspace entitlement `max_storage_mb` (or an equivalent typed storage limit) aggregating Site Assets owned by the Workspace's Sites, enforced on upload. No plan-name checks; numeric plan values require owner approval. Not a Phase 3 blocker.

---

## X-018 — Designer Action Reference Integrity

**Status:** DONE
**Trigger:** before Phase 5 Publishing

`open_page` / `scroll_to` action targets can become stale after the referenced Page or Block is deleted (saves are validated, existing states are not rewritten). Before publishing, detect or clear stale references (for example, validation at publish time and a visible warning in the Designer). Non-blocking for Phase 3.

Result:
- `BlockStateValidator::references()` exposes the referenced public IDs per kind and state path; the reusable read-only `App\Blocks\BlockReferenceInspector` checks them against the Site/Page (Pages, scroll targets on the same Page, active Popups, assets, vehicles) and reports Popups whose Form is disabled as a warning.
- Publish mode (`visibleOnly`) skips hidden source Blocks and treats a hidden scroll target as broken; P5-004 reuses it.
- The Designer receives `referenceIssues` per Block: a warning icon in the Navigator, a Russian summary above the properties, and the message inline at the affected field. Block state is never rewritten.
- Tests: deleted Page / deleted Block / disabled Popup, state not rewritten, disabled Popup Form warning, hidden target only in publish mode, Designer props.

---

## X-019 — Preview Permission for Admin and Designer

**Status:** DONE
**Trigger:** before the Phase 3 gate (P3-017)
**Decision:** D-105

Add `preview_site` to the Admin and Designer roles; Designer must not receive `publish_site`; ContentEditor unchanged. Focused permission tests.

Result: `WorkspacePermissionResolver` grants `preview_site` to Admin and Designer. The tests cover:
- designer preview is allowed and `can.preview` is true;
- admin preview is allowed;
- designer `publish_site` is denied;
- ContentEditor is still forbidden.

Found while testing: the Admin role has no `view_site`, so Admins cannot open the dashboard Site list or the designer. This is the same stale Admin matrix as the missing `view_vehicles` / `edit_benefits`, and it is left for the owner.

---

## X-020 — Admin Permission Matrix Reconciliation

**Status:** DONE
**Trigger:** before P4-001
**Decision:** D-107

Grant Admin `view_site`, `view_vehicles` and `edit_benefits`; keep Designer without `publish_site`, `edit_prices` and `edit_benefits`; keep ContentEditor unchanged; enforce `edit_benefits` separately from `edit_prices`.

Result:
- `WorkspacePermissionResolver` grants Admin `view_site`, `view_vehicles` and `edit_benefits`.
- `SitePolicy::editBenefits` is added, and `viewVehicles` also accepts `edit_benefits`.
- `SaveSiteOfferRequest` authorizes by what changes. Offer fields (price, RRP, availability, badge, Equipment, status, order) need `edit_prices`. A changed benefit list needs `edit_benefits`. A request that changes both needs both.
- The vehicle page exposes `can.editBenefits`, and the offer dialog disables the sections the member cannot change.
- Tests cover:
  - Admin access to the dashboard Site list, the designer and vehicles;
  - Admin editing benefits;
  - a role with `edit_prices` but without `edit_benefits`, which can change the price but gets 403 for benefit changes and for combined changes;
  - Designer preview allowed and publish denied;
  - ContentEditor unchanged.

---

## X-021 — Phase 4 Operational Reconciliation

**Status:** DONE
**Trigger:** before P5-001
**Decision:** D-108

Apply the owner-approved Phase 4 follow-ups: Russian trunk-8 phone normalization, typed preview/public Submission mode, Admin `edit_popups`, and `edit_forms` for Site form security and the Site blacklist.

Result:
- `PhoneNormalizer` rewrites exactly 11 digits starting with 8 (written without «+») to 7XXXXXXXXXX; «+»-prefixed and other numbers are untouched.
- `submissions.mode` (`SubmissionMode`: `public` | `preview`, immutable). The draft preview posts to the authenticated `POST /sites/{site}/preview/forms/{form}/submissions` (`preview_site`, Form of that Site, CSRF via the session token) and stores `preview`. Preview submissions pass the same protection, but duplicates and rate-limit counters are separate per mode.
- «Заявки» lists real leads by default; «Тестовые из предпросмотра» lists preview entries with a visible badge.
- Admin has `edit_popups`; «Защита форм» and the Site blacklist require `edit_forms`; the Workspace blacklist keeps `edit_workspace`.
- Tests: phone cases (trunk 8, «+8», foreign 13 digits, 10 digits), preview permission/foreign Form/mode marking/list separation, preview not counted as a public duplicate, updated permission matrices; interactive E2E reads preview leads from the test tab.
- D-094 stays open; no Submission export.

---

## X-022 — Workspace / Site Dashboard Navigation

**Status:** DONE
**Trigger:** before P7-001
**Decision:** D-110

Separate the Workspace context from the Site context and prepare the navigation structure for Phase 7 domain, SEO and publishing settings: default «Моё пространство» name for new accounts, an always-interactive Workspace switcher, Workspace creation and management, a Workspace shell («Все сайты», «Интеграции», «Настройки пространства») and a Site shell with grouped Site sections.

### Result

- `CreateWorkspace` action (one transaction: Workspace on the `DefaultWorkspacePlan` Free plan + active Owner membership) is shared by `CreateNewAccount` (email/password and Yandex) and the new `GET /workspaces/create` + `POST /workspaces` (verified users, throttled, name required/trimmed/≤120/plain text). New accounts get «Моё пространство»; existing Workspaces are untouched. The created Workspace becomes current. No workspace cap.
- `GET|PATCH /workspace/settings` renames the current Workspace (`edit_workspace`; others get 403; no IDs exposed).
- Workspace switcher is always a menu: Workspaces with a selected mark, «Создать пространство», «Управление пространством» (with `edit_workspace`). Switching lands on the dashboard.
- Workspace shell: «Все сайты», «Интеграции» (`view_integrations`), «Настройки пространства» (`edit_workspace`), platform catalog only with a platform permission. Dashboard heading «Все сайты»; Site cards have «Открыть» (Site context), status and the public address once published.
- Site shell (`SiteLayout` for `sites/*` pages): «← Все сайты», Site name, groups Общее (Общее, Дизайнер, Предпросмотр), Контент (Автомобили, Формы, Попапы), Заявки (Заявки, Доставка заявок), Настройки (Интеграции, Защита форм, Публикация). Visibility comes from a backend-computed `siteContext` shared prop (Site policy abilities, current Workspace only); pages still authorize themselves.
- `GET /sites/{site}` «Общее»: name, status, subdomain, publish state, active address; `PATCH /sites/{site}` renames with `edit_site_settings`.
- Tests: default names (registration, Yandex, no backfill), switcher props, Workspace creation (Free plan, Owner, current, validation, unverified), foreign Workspace, rename permission, Site shell abilities for Owner/Admin/Designer, foreign Site 404, Site rename permission; E2E switcher (one and many), create + rename Workspace, Site context and back, Designer/Admin navigation, tablet/375px.

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
