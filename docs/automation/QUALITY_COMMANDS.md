# Landflow — Quality Commands

**Document:** `docs/automation/QUALITY_COMMANDS.md`  
**Status:** Canonical quality-check contract  
**Backlog task:** `P0-022 — Standardize Quality Commands`

---

# 1. Purpose

This document defines the canonical quality commands for Landflow.

Every Cursor agent, reviewer, local developer workflow, and future CI pipeline should use the same command vocabulary.

The goals are:

- one canonical command per quality gate;
- no competing scripts for the same check;
- non-interactive execution;
- deterministic exit codes;
- clear PASS / FAIL reporting;
- compatibility with autonomous agent fix loops;
- easy reuse in CI.

This document specifies the desired command contract.

Actual command availability must still be verified against the repository before reporting `PASS`.

---

# 2. Core Rule

Agents must never invent ad-hoc alternatives when a canonical command exists.

Example:

If the canonical PHP test command is:

```bash
composer test
```

do not randomly use:

```bash
vendor/bin/phpunit
php artisan test
composer run tests
```

in different agents unless a focused diagnostic requires it.

Focused diagnostic commands are allowed during debugging, but final task verification must use the canonical gate command.

---

# 3. Canonical Quality Gates

Landflow uses the following quality gates:

```text
PHPUnit
Larastan
Pint
Frontend Check
Production Build
Playwright
Full Quality Gate
```

Target canonical commands:

```bash
composer test
composer analyse
composer format:check
npm run check
npm run build
npm run test:e2e
composer quality
```

The final exact scripts must be verified/added in the repository during implementation of P0-022.

---

# 4. PHP Test Command

Canonical command:

```bash
composer test
```

Expected behavior:

- runs PHPUnit;
- uses test environment;
- exits non-zero on failure;
- requires no user interaction;
- does not call external production services.

Preferred underlying implementation:

```bash
php artisan test
```

or the currently established PHPUnit runner if repository conventions require it.

The Composer script is the stable agent-facing interface.

---

# 5. Focused PHP Tests

During implementation, agents may use focused commands for speed.

Examples:

```bash
php artisan test tests/Feature/Sites/SiteTest.php
```

or:

```bash
php artisan test --filter=user_cannot_access_site_from_another_workspace
```

These are debugging/iteration commands.

They do not replace final:

```bash
composer test
```

when full backend suite is required by Definition of Done.

---

# 6. Static Analysis

Canonical command:

```bash
composer analyse
```

Expected underlying tool:

**Larastan / PHPStan**

Preferred behavior:

```bash
vendor/bin/phpstan analyse --memory-limit=1G
```

Exact config should use the repository's approved `phpstan.neon` / `phpstan.neon.dist` if present.

Requirements:

- non-interactive;
- non-zero on new errors;
- no blanket baseline growth to silence task regressions.

---

# 7. Larastan Rule

Agents must not solve static-analysis failures by:

- adding broad ignores;
- reducing analysis level;
- adding `@phpstan-ignore-*` without a concrete reason;
- turning precise types into `mixed` everywhere.

A justified suppression must be narrow and documented.

---

# 8. PHP Formatting Check

Canonical verification command:

```bash
composer format:check
```

Expected underlying tool:

**Laravel Pint**

Preferred implementation:

```bash
vendor/bin/pint --test
```

This command must not modify files.

It is a verification gate.

---

# 9. PHP Auto-Format Command

Canonical developer/fix-loop command:

```bash
composer format
```

Preferred implementation:

```bash
vendor/bin/pint
```

This command may modify source files.

After it runs, agents must inspect the diff and then rerun:

```bash
composer format:check
```

Do not treat auto-format execution itself as proof that formatting passes.

---

# 10. Frontend Check

Canonical command:

```bash
npm run check
```

This should use the repository's existing vite-plus (`vp`) check workflow.

Expected responsibilities may include:

- TypeScript validation;
- frontend static checks configured by the existing toolchain.

Do not introduce standalone ESLint merely to create this gate.

The existing repository tooling is authoritative.

---

# 11. Frontend Check Rule

Do not bypass TypeScript failures using:

- `any`;
- `as any`;
- `@ts-ignore`;
- disabling strict mode;
- excluding files without reason.

Fix the real type boundary.

---

# 12. Production Build

Canonical command:

```bash
npm run build
```

Expected behavior:

- production Vite build;
- non-zero exit on compilation/build failure;
- uses normal project production build config;
- no production credentials required for compilation.

Development server success does not replace production build.

Known external build dependency: the Instrument Sans font is loaded through `laravel-vite-plugin` `bunny(...)` (`vite.config.ts`) and downloaded from `https://fonts.bunny.net` at build time, then cached in `node_modules/.cache/laravel-vite-plugin/fonts`. A build with an empty cache (fresh clone, CI) needs that CDN to be reachable; if it is not, `npm run build` fails. The built site serves the fonts from `public/build` without runtime CDN requests.

---

# 13. Browser / E2E Tests

Canonical command after Playwright is configured:

```bash
npm run test:e2e
```

Expected underlying tool:

**Playwright**

Current status (P0-023):

```text
AVAILABLE
```

`npm run test:e2e` = `playwright test` (config: `playwright.config.ts`, tests: `tests/browser/`).

It is a separate gate: it is not part of `composer quality` and must run after it, never concurrently (its web server runs its own `npm run build`, unless `E2E_REUSE_BUILD=1` — see section 38).

Report `PASS` only when the command actually ran and exited with code 0.

---

# 14. Focused Playwright Tests

During UI implementation, focused commands may be used.

Examples:

```bash
npx playwright test tests/browser/sites/create-site.spec.ts
```

or:

```bash
npx playwright test -g "создание сайта"
```

Final task verification should use the canonical relevant browser command according to Definition of Done.

---

# 15. Playwright UI/Debug Modes

Developer-only diagnostic modes may include:

```bash
npx playwright test --headed
```

or:

```bash
npx playwright test --ui
```

These are not CI/final gate commands.

Autonomous agents should prefer deterministic headless execution for verification.

---

# 16. Full Quality Gate

Target canonical command:

```bash
composer quality
```

Purpose:

Run the core non-browser quality gates in a fixed order.

Recommended sequence:

```text
PHPUnit
→ Larastan
→ Pint check
→ npm run check
→ npm run build
```

Playwright may remain a separate gate because browser environment requirements are different.

---

# 17. Recommended `composer quality` Behavior

Target implementation concept:

```bash
composer test
composer analyse
composer format:check
npm run check
npm run build
```

If one command fails:

- stop;
- return non-zero;
- do not continue and hide the failure.

Exact implementation may use Composer script chaining.

Actual implementation (P0-025): `composer test` → `composer analyse` → `composer format:check` → `php artisan wayfinder:generate --with-form` → `npm run check` → `npm run build`. The Wayfinder step generates the git-ignored TypeScript route helpers (`resources/js/actions`, `resources/js/routes`, `resources/js/wayfinder`) with the same options as the Vite build, so `npm run check` type-checks correctly on a clean checkout (without it, a fresh clone fails with TS2307 `Cannot find module '@/routes'`).

---

# 18. Browser Quality Gate

After Playwright exists, browser validation remains:

```bash
npm run test:e2e
```

A future optional aggregate command may be introduced:

```bash
composer quality:full
```

or:

```bash
npm run quality
```

but only if it simplifies CI without duplicating behavior.

Do not create many overlapping quality aliases.

---

# 19. Canonical Command Table

| Gate | Canonical command | Current status (P0-025) |
|---|---|---|
| Backend tests | `composer test` | AVAILABLE |
| Static analysis | `composer analyse` | AVAILABLE |
| PHP formatting check | `composer format:check` | AVAILABLE |
| PHP auto-format | `composer format` | AVAILABLE |
| Frontend check | `npm run check` | AVAILABLE |
| Production build | `npm run build` | AVAILABLE |
| Browser E2E | `npm run test:e2e` | AVAILABLE |
| Core aggregate | `composer quality` | AVAILABLE |
| Full aggregate incl. browser | optional later | DEFERRED |

Implementation notes (verified in P0-022):

- `composer test` runs only PHPUnit and does not require `public/build`: `tests/TestCase.php` calls Laravel's `withoutVite()`.
- `npm run check` (`vp check`) covers formatting, type-aware lint and TypeScript type checking (`lint.options.typeCheck: true` in `vite.config.ts`); there is no separate `tsc` gate.
- `composer ci:check` (deprecated alias of `composer quality`) was removed in P0-025; CI calls `composer quality` directly.

Implementation notes (verified in P0-023):

- `npm run test:e2e` runs Playwright with Chromium only, headless, one worker, no retries.
- The Playwright `webServer` prepares an isolated environment on every run: `.env.e2e` regenerated from `.env.e2e.example` (`APP_ENV=e2e`, fresh `APP_KEY`), file SQLite `database/e2e.sqlite` recreated and migrated, production build, dedicated `php artisan serve` on `http://127.0.0.1:8200`. It never reuses a running server and never touches the developer `.env` or database.
- Prerequisites: Chromium installed (`npx playwright install chromium`), port 8200 free, no cached config, no running Vite dev server (`public/hot`).
- Artifacts (`test-results/`, `playwright-report/`) are git-ignored; failure screenshots and traces are kept there.

---

# 20. Repository Verification Step

When P0-022 is applied inside the real repository, the implementing agent must inspect:

```text
composer.json
package.json
vite config
PHPUnit config
PHPStan/Larastan config
Pint config
```

Then:

1. reuse existing scripts where they already match;
2. rename/add aliases only when needed;
3. avoid duplicate equivalent scripts;
4. run every canonical command;
5. record actual results in PROJECT_STATE.

---

# 21. Target Composer Scripts

Recommended target shape:

```json
{
  "scripts": {
    "test": "php artisan test",
    "analyse": "phpstan analyse --memory-limit=1G",
    "format": "pint",
    "format:check": "pint --test"
  }
}
```

Exact binary invocation may need:

```text
vendor/bin/phpstan
vendor/bin/pint
```

depending on Composer script execution environment and current repository conventions.

The implementing agent must verify rather than copy this blindly.

---

# 22. Target Aggregate Composer Script

Conceptual target:

```json
{
  "scripts": {
    "quality": [
      "@test",
      "@analyse",
      "@format:check",
      "npm run check",
      "npm run build"
    ]
  }
}
```

If Composer syntax/runtime differs in the current project, adapt while preserving one stable public command:

```bash
composer quality
```

---

# 23. Target npm Scripts

Recommended target shape:

```json
{
  "scripts": {
    "check": "...existing vp check command...",
    "build": "...existing production build command...",
    "test:e2e": "playwright test"
  }
}
```

Do not replace existing `check` or `build` behavior if it already works.

Only add `test:e2e` after P0-023.

---

# 24. Do Not Add ESLint by Default

The repository already uses vite-plus checking.

Do not introduce:

```text
eslint
eslint-config-*
prettier
```

solely to manufacture conventional scripts.

A future explicit need may change this.

---

# 25. Formatting Ownership

PHP formatting:

**Pint**

Frontend formatting/checking:

Use current vite-plus/project tooling.

Do not create competing automatic formatters unless explicitly approved.

---

# 26. Canonical Execution Order

For a backend-only task:

```text
focused PHPUnit
→ composer test
→ composer analyse
→ composer format:check
```

For a frontend-only task:

```text
npm run check
→ npm run build
→ npm run test:e2e (when available)
→ browser QA
```

For full-stack task:

```text
focused tests
→ composer test
→ composer analyse
→ composer format:check
→ npm run check
→ npm run build
→ npm run test:e2e
→ browser QA
```

Run only applicable gates, but do not omit relevant ones without explanation.

---

# 27. Fix Loop

Expected autonomous loop:

```text
run gate
→ fail
→ inspect exact failure
→ fix
→ rerun failed gate
→ continue
```

After all focused gates pass, run the broader canonical task gates.

Do not restart all slow checks after every tiny edit unless necessary.

---

# 28. Failure Reporting

If a command fails, report:

```text
Command:
Exit status:
Failure summary:
Likely task-related: YES/NO/UNKNOWN
Fix attempted:
Next action:
```

Do not paste enormous raw logs unless needed.

Preserve enough error context to reproduce.

---

# 29. Existing Unrelated Failure

If a canonical command fails on a pre-existing unrelated issue:

1. verify it is genuinely pre-existing;
2. do not claim the gate passed;
3. report exact known failure;
4. continue focused task checks if safe;
5. create backlog follow-up if needed.

Task status may be PARTIAL or otherwise governed by DoD.

---

# 30. Non-Interactive Requirement

Canonical quality commands must be suitable for agents and CI.

They must not:

- prompt for confirmation;
- open interactive UI;
- require manual keypress;
- require personal credentials.

Interactive debugging commands are separate.

---

# 31. Exit Codes

All canonical gates must:

- return `0` on success;
- return non-zero on failure.

Do not use scripts that print an error but exit successfully.

CI and orchestrator depend on exit codes.

---

# 32. No Production Dependencies

Core quality checks must not require:

- production DB;
- production CRM;
- production SMTP;
- production DNS;
- billing provider credentials;
- real SmartCaptcha credentials.

Use test doubles/fakes/mocks.

---

# 33. Test Database

PHPUnit uses the repository's approved test DB strategy.

Current baseline:

**SQLite in-memory**

If a future feature requires production-engine-specific behavior, add an explicit supplemental test strategy rather than silently abandoning all fast tests.

---

# 34. External Provider Tests

Normal quality command must not contact:

- CRM;
- Yandex;
- arbitrary webhook receivers;
- billing provider;
- DNS provider.

Provider adapters should be mocked/faked.

Real integration verification is a separate controlled check.

---

# 35. Security Checks

Security is not represented by one single shell command.

Security verification is composed of:

- PHPUnit negative tests;
- static analysis;
- browser tests where applicable;
- Security Agent review.

Do not create a fake `security:pass` script that provides false confidence.

---

# 36. UI Visual Checks

Visual review is also not reducible to build success.

For UI tasks, browser QA must still verify:

- Russian text;
- responsive behavior;
- console;
- visual hierarchy;
- empty/loading/error states.

`npm run build` alone is not UI verification.

---

# 37. Current Playwright Status

Current status (after P0-024, verified by a successful `npm run test:e2e` run):

```text
Playwright: AVAILABLE
Browser installed: Chromium
Canonical E2E command: npm run test:e2e
Browser QA baseline: DONE (P0-024)
Browser automated QA: CONFIGURED
```

Browser QA conventions (P0-024):

- Projects: `desktop` runs the full suite; `tablet` and `mobile` run only tests tagged `@responsive` (`test('…', { tag: '@responsive' }, …)`). Tag a test when its screen must behave intentionally on narrow viewports.
- Authentication: most tests start from the `member` storage state created by the `setup` project (real login form). Guest tests use `test.use({ storageState: guestStorageState })`. Tests that log in via the UI use the `login` user (login rate limit is 5/min per email + IP).
- Test data comes only from `database/seeders/E2eSeeder.php` (e2e environment only); credentials live in `tests/browser/support/users.ts`.
- Import `test`/`expect` from `tests/browser/support/fixtures.ts` so console errors, page errors and 4xx/5xx responses fail the test.
- Review screenshots: `captureScreenshot()` → `test-results/screenshots/<area>/<name>--<project>.png` (cleared every run, attached to the HTML report). Use `{ fullPage: false }` for open overlays. They are review evidence, not pixel baselines.
- Responsive tests assert `expectNoHorizontalOverflow(page)`.

Focused run examples: `npx playwright test tests/browser/settings.spec.ts`, `npx playwright test --project=mobile`.

---

# 38. CI Reuse

Future CI must call the same canonical commands used locally.

Avoid CI-only custom commands that agents never run.

Target:

```text
composer quality
npm run test:e2e
```

with any necessary environment setup around them.

Actual implementation (P0-025) — `.github/workflows/ci.yml` («Landflow CI»), one job on `ubuntu-24.04` (pinned, not `ubuntu-latest`, so the verified environment does not change implicitly):

```text
checkout → PHP 8.3 + Composer v2 → Node 22 (npm cache) → Composer cache
→ composer install → npm ci
→ cp .env.example .env + php artisan key:generate   (placeholder values, throwaway key)
→ composer quality
→ npx playwright install --with-deps chromium
→ npm run test:e2e   (E2E_REUSE_BUILD=1)
→ on E2E failure only: upload test-results/, playwright-report/, storage/logs/ (7 days)
```

- Triggers: push to `main`, pull requests into `main`. Concurrency: one run per workflow + ref, newer runs cancel older ones. Timeout: 20 minutes. Permissions: `contents: read`. Actions pinned to commit SHAs (Dependabot updates them weekly).
- No database service: PHPUnit uses in-memory SQLite, E2E uses `database/e2e.sqlite`. No GitHub Secrets are required.
- `E2E_REUSE_BUILD=1` (CI only) makes the Playwright web server skip its own `npm run build` and reuse the production build `composer quality` just produced from the same checkout (Vite reads `.env` in both cases, so the output is identical). `prepare-e2e.mjs` fails if the flag is set but `public/build/manifest.json` is missing. Locally leave it unset: the E2E server then always rebuilds, so a stale build is never tested.
- `composer setup` (local project bootstrap: install, `.env`, key, migrate, build) is not used by CI.

---

# 39. CI and Local Parity

The closer local and CI quality commands are, the better.

Differences should be limited to environment setup, such as:

- installing dependencies;
- starting test server;
- setting test env vars;
- installing browser binary.

The actual validation command remains the same.

---

# 40. Orchestrator Integration

The autonomous task runner should map task type to gates.

Example:

```text
backend task:
  composer test
  composer analyse
  composer format:check

frontend task:
  npm run check
  npm run build
  npm run test:e2e

full-stack:
  all applicable gates
```

Task metadata/agent judgment may narrow focused tests, but final DoD controls completion.

Implemented (P0-026): the canonical task-type → gate mapping used by the Orchestrator is `docs/automation/AUTONOMOUS_WORKFLOW.md` §12 "Quality Routing". It refines the example above: a frontend change with no browser-facing effect runs `npm run check` + `npm run build`; a user-facing UI change also runs `npm run test:e2e`.

---

# 41. Check Result Vocabulary

Use exactly:

- `PASS`
- `FAIL`
- `NOT_AVAILABLE_YET`
- `NOT_APPLICABLE`
- `BLOCKED_EXTERNAL`

Do not use:

- probably okay;
- seems green;
- mostly passed.

---

# 42. Example Backend Report

```text
PHPUnit (`composer test`): PASS
Larastan (`composer analyse`): PASS
Pint (`composer format:check`): PASS
Frontend check: NOT_APPLICABLE
Build: NOT_APPLICABLE
Playwright: NOT_APPLICABLE
```

---

# 43. Example Frontend Report Before Playwright Setup

```text
PHPUnit: NOT_APPLICABLE
Larastan: NOT_APPLICABLE
Pint: NOT_APPLICABLE
Frontend check (`npm run check`): PASS
Build (`npm run build`): PASS
Playwright: NOT_AVAILABLE_YET
Browser QA: NOT_AVAILABLE_YET
```

Do not convert unavailable checks into PASS.

---

# 44. Example Full-Stack Report

```text
PHPUnit (`composer test`): PASS
Larastan (`composer analyse`): PASS
Pint (`composer format:check`): PASS
Frontend check (`npm run check`): PASS
Build (`npm run build`): PASS
Playwright (`npm run test:e2e`): PASS
Console: PASS
Browser desktop: PASS
Browser mobile: PASS
```

---

# 45. Performance of the Quality Loop

Quality commands should remain fast enough for repeated autonomous use.

Avoid making `composer test` depend on:

- huge demo automotive datasets;
- real network;
- full browser install;
- production asset generation.

Separate expensive specialized gates when needed.

---

# 46. Phase-Level Validation

Phase review may run a broader set than an individual task.

Example Phase 1:

```text
composer quality
npm run test:e2e
```

plus:

- tenancy review;
- Security Agent;
- UI Reviewer;
- Reviewer.

---

# 47. Do Not Hide Warnings

If a tool exits successfully but emits meaningful new warnings:

- inspect them;
- do not automatically ignore them.

Especially:

- React warnings;
- static-analysis warnings;
- build deprecations that indicate imminent breakage.

Not every informational line is a failure, but repeated meaningful warning should be tracked.

---

# 48. Dependency Audit

Package vulnerability auditing may later be added as a release/CI gate.

Do not add it as a blocking P0-022 command without first defining policy for:

- advisory severity;
- transitive-only issues;
- false positives;
- major upgrade requirements.

Track as future quality/security enhancement.

---

# 49. Future Optional Commands

Potential future canonical commands may include:

```bash
composer test:unit
composer test:feature
npm run test:e2e:smoke
npm run test:e2e:visual
```

Add them only when the suite is large enough to justify separate stable groups.

Do not create aliases preemptively.

---

# 50. P0-022 Completion Criteria

P0-022 is fully `DONE` in the repository only when:

1. current `composer.json` is inspected;
2. current `package.json` is inspected;
3. canonical aliases are reused or added;
4. no duplicate competing commands remain;
5. `composer test` runs;
6. `composer analyse` runs;
7. `composer format:check` runs;
8. `npm run check` runs;
9. `npm run build` runs;
10. outputs are recorded honestly;
11. `PROJECT_STATE.md` is updated.

Playwright became `AVAILABLE` in P0-023 (`npm run test:e2e`).

---

# 51. Documentation-Only State

If this document exists but the repository scripts have not yet been changed/verified:

P0-022 must not be reported as fully verified implementation.

Use a state such as:

```text
QUALITY COMMAND CONTRACT: DOCUMENTED
REPOSITORY SCRIPT VERIFICATION: PENDING
```

This prevents documentation from being confused with executed repository work.

---

# 52. Final Rule

Landflow has one shared language for quality checks.

Agents, developers, Reviewer and CI must agree on the same commands and the same meaning of PASS.

**A quality gate only passes when its canonical command actually runs successfully.**
