# Landflow — Definition of Done

**Document:** `docs/automation/DEFINITION_OF_DONE.md`  
**Status:** Mandatory engineering quality gate  
**Purpose:** Define when a task, feature, bug fix, migration, refactor, UI change, or architecture change is considered complete.

---

# 1. Core Principle

A Landflow task is **not complete because code exists**.

A task is complete only when:

- required behavior is implemented;
- architecture rules are respected;
- relevant tests pass;
- static checks pass;
- frontend/build checks pass;
- affected user flows are verified;
- security/tenancy risks are checked;
- no known regressions remain;
- documentation/project state is updated where required.

Agent statement such as:

> "Implemented successfully"

is never sufficient by itself.

---

# 2. Definition of Done Applies To

This document applies to:

- feature work;
- bug fixes;
- refactors;
- migrations;
- UI/UX changes;
- API changes;
- publishing changes;
- automotive catalog changes;
- permission changes;
- integration work;
- security changes;
- Developer Platform work;
- Marketplace work.

---

# 3. Task Completion States

Every task should end in one of these states:

## DONE

All applicable Definition of Done checks passed.

## BLOCKED_DECISION

Work cannot safely continue because an unresolved product/architecture decision is required.

## BLOCKED_EXTERNAL

Work requires an external dependency/action such as production credentials, DNS, payment-provider access, or an unavailable third-party system.

## PARTIAL

Some work completed, but required quality gates remain failing.

An agent must never mark PARTIAL as DONE.

---

# 4. Applicable Checks

Not every task requires every possible test.

The agent must determine which checks are applicable, but skipping a check must be intentional and explained.

Examples:

Backend-only domain change:
- PHPUnit
- static analysis
- formatting

Designer UI change:
- TypeScript/check
- build
- browser/E2E
- screenshot/visual review

Permission change:
- PHPUnit
- tenancy/security tests
- browser flow if UI changed

---

# 5. Baseline Engineering Checks

When applicable, Landflow should eventually require:

- PHPUnit
- Larastan/static analysis
- Pint/formatting
- TypeScript/check
- Vite production build
- Playwright E2E/browser tests
- browser console check

Exact commands belong to repository automation and may evolve.

---

# 6. Backend Tests

Any backend behavior change should have automated coverage where practical.

Examples:

- Workspace isolation;
- policies;
- automotive imports;
- Site Offer pricing;
- Form submission;
- delivery retry;
- publishing;
- Block Schema validation.

Bug fixes should preferably add a regression test reproducing the original failure.

---

# 7. Unit Tests

Unit tests are appropriate for isolated business rules.

Examples:

- entitlement resolution;
- money calculation;
- benefit aggregation rules;
- phone normalization;
- Block Schema validation;
- data-binding resolution.

Do not force all behavior into unit tests if Feature tests better represent the real system.

---

# 8. Feature Tests

Feature tests should cover request/domain behavior.

Examples:

- User creates Site;
- member cannot access foreign Workspace;
- customer imports Global Catalog vehicle;
- Form persists Submission;
- failed CRM delivery queues retry;
- Publish requires permission.

Feature tests are especially important for authorization and tenancy.

---

# 9. Regression Tests

When fixing a defect:

1. reproduce failure where practical;
2. add automated regression coverage;
3. implement fix;
4. confirm test now passes.

Do not fix recurring bugs only with manual verification.

---

# 10. Database Changes

Any database change must verify:

- migration runs from clean database;
- migration runs against existing supported schema;
- rollback behavior where safe/expected;
- indexes/foreign keys make sense;
- tenant ownership is explicit;
- delete behavior is deliberate.

Do not add tables without checking scope: Global, Workspace, or Site.

---

# 11. Migration Data Safety

Migration must not silently destroy customer data.

Destructive migrations require:

- explicit reasoning;
- backup/migration strategy;
- review;
- potentially separate rollout step.

Do not casually rename/drop production fields with no migration path.

---

# 12. Tenancy Gate

Any Workspace/Site-scoped feature must answer:

- Who owns this record?
- Can another Workspace access it?
- Is ownership verified backend-side?
- Are cross-Site references valid?
- Are queue jobs tenant-safe?

If tenant isolation is relevant and not tested, task is not DONE.

---

# 13. Permission Gate

Any protected action must verify relevant permission.

Examples:

- `edit_prices`
- `publish_site`
- `manage_integrations`
- `view_submissions`
- `manage_domains`

UI hiding alone is insufficient.

---

# 14. Entitlement Gate

Paid/plan-controlled features must use entitlement/capability checks.

Do not use scattered checks such as:

`if plan == "team"`

Task is not DONE if plan behavior is hardcoded contrary to architecture.

---

# 15. Security Gate

Security-relevant work must check applicable risks:

- XSS;
- CSRF;
- authorization;
- tenancy;
- secret leakage;
- SSRF;
- upload validation;
- unsafe URLs;
- SQL injection;
- public endpoint abuse;
- rate limiting.

Security checks should be automated where practical.

---

# 16. Secret Safety

Before marking integration/security work DONE, verify:

- no secret in React/Inertia props;
- no secret in logs;
- no secret in published snapshot;
- no secret in committed fixture/example;
- masking works where UI displays credential status.

---

# 17. Public Endpoint Gate

Public endpoints must be tested for:

- validation;
- authorization/context rules;
- rate limits where relevant;
- malformed input;
- tenant isolation;
- safe errors.

Public Form endpoints additionally require anti-spam/captcha path tests.

---

# 18. Automotive Gate

Automotive changes must preserve:

- Global Catalog/customer ownership boundary;
- Site-specific pricing;
- no customer mutation of master catalog;
- color/image relationships;
- source references;
- copy semantics.

Do not mark automotive task DONE if it silently synchronizes independent Sites.

---

# 19. Block System Gate

Block work must verify:

- Schema validates;
- Instance does not mutate Definition;
- pinned Version remains stable;
- Repeater order persists;
- bindings use approved context;
- no raw DB access from Developer Block;
- actions use platform Action System.

---

# 20. Forms and Integrations Gate

Form/integration work must verify:

- Submission persists before external delivery;
- delivery is server-side;
- credentials remain Workspace-scoped;
- Site overrides work;
- one destination failure does not lose lead;
- retries are safe;
- logs redact secrets.

---

# 21. Publishing Gate

Publishing changes must verify:

- autosave changes Draft only;
- current production stays stable until Publish;
- failed Publish keeps previous version active;
- published output contains no secret/private Draft data;
- permission is enforced;
- cache invalidation is correct.

---

# 22. Frontend Type Safety

Frontend changes must pass TypeScript checks.

Avoid solving type errors with broad unsafe casts such as `any` or disabling strictness unless explicitly justified.

---

# 23. Frontend Build

Any meaningful frontend change must pass production build.

A page that works only in development is not DONE.

---

# 24. Browser Console

Critical browser flows should be checked for:

- JavaScript errors;
- React errors/warnings;
- failed network requests;
- hydration/runtime errors where relevant.

Unexpected console errors block DONE.

---

# 25. Browser / E2E Tests

Critical user flows should have Playwright coverage once browser automation is installed.

Examples:

- Register/Login;
- Workspace switch;
- Create Site;
- choose Template;
- Designer edit;
- vehicle import;
- price update;
- Preview;
- Publish;
- Form submission.

---

# 26. UI Functional Verification

A UI task must be exercised in the browser.

Do not mark UI DONE based only on TypeScript, unit tests, or code inspection.

---

# 27. Visual QA

Designer/dashboard UI changes must be visually reviewed.

Check:

- alignment;
- spacing;
- overflow;
- responsive behavior;
- loading states;
- empty states;
- errors;
- disabled states;
- long text;
- realistic data.

---

# 28. Reference Screenshots

For important UI surfaces Landflow should maintain visual references.

Potential areas:

- Dashboard;
- Site creation;
- Designer shell;
- Properties Panel;
- Vehicle Catalog;
- Site Vehicle editor;
- Publish dialog.

Browser automation may capture screenshots for comparison.

---

# 29. Responsive QA

Relevant UI must be checked at:

- Desktop;
- Tablet;
- Mobile.

Designer itself may primarily target desktop, but published Site Blocks must respect supported breakpoints.

---

# 30. Empty States

A feature is not DONE if it only works with perfect populated data.

Check applicable empty states:

- no Sites;
- no vehicles;
- no images;
- no Form submissions;
- no integrations;
- no search results.

---

# 31. Error States

Check expected failures.

Examples:

- failed API;
- invalid Form;
- unauthorized action;
- duplicate domain;
- failed Publish;
- missing media;
- CRM unavailable.

User-facing errors should be safe and useful.

---

# 32. Loading States

Async UI should provide appropriate loading/progress feedback.

Avoid frozen buttons, duplicate clicks, and unexplained delays.

---

# 33. Disabled/Permission States

When User lacks permission/entitlement:

- UI should hide or disable action appropriately;
- backend must still deny direct request.

Both sides matter.

---

# 34. Accessibility Baseline

Relevant UI should check:

- labels;
- keyboard interaction;
- buttons vs clickable divs;
- focus;
- modal focus behavior;
- alt text support;
- obvious contrast issues.

Accessibility regressions should not be knowingly introduced.

---

# 35. Performance Sanity

Check obvious performance problems.

Examples:

- N+1 queries;
- API request per vehicle card;
- loading entire Global Catalog unnecessarily;
- huge unpaginated lists;
- unnecessary repeated rendering;
- oversized images.

---

# 36. Query Review

Backend list tasks should check:

- eager loading;
- pagination;
- indexes;
- tenant filters.

Do not ship clear N+1 problems.

---

# 37. Media Review

Image/media work should verify:

- correct format;
- dimensions;
- transparency preservation;
- no broken URLs;
- optimization strategy;
- old Published assets remain stable where required.

---

# 38. External Provider Review

External-provider work must verify current official documentation at implementation time.

Record:

- API version;
- auth method;
- required fields;
- error behavior.

Do not mark DONE using stale provider assumptions.

---

# 39. Queue Review

Queued work should test:

- duplicate execution;
- retries;
- failure;
- idempotency where needed;
- tenant context.

---

# 40. Documentation Gate

Update documentation when behavior/architecture changed.

Potential documents:

- architecture;
- ADR;
- PROJECT_STATE;
- provider notes;
- README/setup.

Do not leave source-of-truth docs knowingly outdated.

---

# 41. Architecture Compliance

Before DONE, check relevant architecture docs:

- `ARCHITECTURE.md`
- `DATABASE.md`
- `TENANCY.md`
- `PERMISSIONS.md`
- `AUTOMOTIVE_DATA.md`
- `BLOCK_SYSTEM.md`
- `FORMS_AND_INTEGRATIONS.md`
- `PUBLISHING.md`
- `SECURITY.md`

If implementation contradicts them, do not silently proceed.

---

# 42. Architecture Change

If a task legitimately needs architecture change:

1. identify conflict;
2. create/update ADR or architecture decision;
3. update affected docs;
4. then implement.

Do not rewrite architecture accidentally inside feature code.

---

# 43. Code Quality

Code should be:

- readable;
- cohesive;
- appropriately named;
- not unnecessarily abstract;
- consistent with repository patterns.

Avoid giant controllers/components/services.

---

# 44. No Premature Abstraction

Do not build complex frameworks for hypothetical needs.

Use architecture extension points, but implement only current scope.

---

# 45. No Duplicate Logic

Important shared logic should be centralized.

Examples:

- entitlement checks;
- price formatting;
- phone normalization;
- automotive fallback resolution;
- Form delivery;
- Action resolution.

---

# 46. No Hidden Magic

Prefer explicit domain behavior.

Bad:
price copied somewhere automatically with no visible source.

Good:
Site Offer owns price.

Bad:
Site B silently syncs Site A.

Good:
explicit copy semantics.

---

# 47. Laravel Conventions

Backend implementation should follow agreed Laravel conventions:

- validation;
- policies;
- actions/services where complex;
- Eloquent relationships;
- jobs;
- events where justified.

Avoid unnecessary custom framework layers.

---

# 48. React / Inertia Conventions

Frontend should follow repository standards:

- TypeScript;
- Inertia;
- shadcn/ui;
- existing aliases;
- existing component patterns.

Do not introduce a competing frontend stack.

---

# 49. shadcn/ui

Use shadcn/ui primitives/components where suitable.

Do not rebuild common UI primitives unnecessarily.

---

# 50. Package Installation

A task is not authorized to install packages unless:

- task explicitly requires it;
- existing capabilities are insufficient;
- package choice is justified;
- architecture/package policy permits it.

Agents must not casually add dependencies.

---

# 51. Dependency Approval

Before adding dependency, evaluate:

- maintenance;
- security;
- license;
- bundle size;
- necessity;
- existing alternative.

Record major choices as ADRs where appropriate.

---

# 52. Formatting

Code must pass repository formatting tools.

Formatting failures block DONE.

---

# 53. Static Analysis

Relevant backend changes should pass configured static analysis.

Do not suppress errors broadly without explanation.

---

# 54. TypeScript Errors

No new TypeScript errors.

Do not disable strict checking to ship a task.

---

# 55. Test Fixtures

Tests should use deterministic fixtures/factories.

Do not rely on production data or internet connectivity for core tests.

---

# 56. External Provider Tests

Use mocks/fakes where appropriate.

Unit/Feature tests must never accidentally send real CRM leads.

---

# 57. Test Isolation

Tests must not depend on execution order.

Each test sets up its own required state.

---

# 58. CI Readiness

All automated checks required for DONE should be runnable non-interactively.

Eventually CI must execute them.

---

# 59. Local vs CI

A task cannot be considered DONE if it works only because of undocumented local machine state.

Required environment assumptions must be documented.

---

# 60. Environment Variables

New environment variables must be:

- documented;
- added to example config if appropriate;
- named clearly;
- never committed with real secret values.

---

# 61. Backward Compatibility

Changes affecting customer configuration should consider:

- existing Blocks;
- existing Sites;
- published versions;
- Form mappings;
- automotive imports.

Do not break existing data silently.

---

# 62. Schema Versioning

Dynamic structures such as:

- Block Schema;
- Block state;
- Integration mapping;
- Published manifest;

must preserve version/migration compatibility.

---

# 63. Feature Flags

Experimental rollout may use feature flags.

Feature flags do not replace permissions or entitlements.

---

# 64. Audit Requirements

Sensitive changes should confirm audit coverage where required.

Examples:

- price changes;
- Publish;
- permission changes;
- integration secret changes;
- domain changes.

---

# 65. Destructive Actions

Destructive UI/actions need safeguards.

Examples:

- delete Site;
- remove domain;
- delete Integration Profile;
- archive catalog item.

Check dependent references first.

---

# 66. Public SEO

Published frontend changes should check:

- title/meta;
- canonical;
- crawlable content;
- no accidental Draft/noindex changes;
- sitemap when relevant.

---

# 67. Analytics

Interactive changes should emit standardized semantic events where product requires them.

Do not wire independent analytics implementations inside individual Blocks.

---

# 68. Yandex Services

When implementing SmartCaptcha or Metrica, use current official Yandex documentation.

Architecture adapters must remain intact.

---

# 69. Browser Network Review

For critical flows inspect network behavior:

- expected request;
- no duplicate unexpected request;
- no secret payload;
- correct failure handling.

---

# 70. No Sensitive Frontend Payload

Inspect relevant Inertia/API payloads.

Ensure they do not include:

- Integration token;
- private secret;
- unnecessary Submission personal data;
- unrelated Workspace data.

---

# 71. Public Runtime Payload

Published Site should receive only data needed to render.

Do not dump full internal models into public JSON.

---

# 72. Review Step

Important tasks should receive a final review pass separate from implementation thinking.

Review:

- architecture;
- correctness;
- security;
- tests;
- UX;
- regressions.

Future Reviewer agent should perform this.

---

# 73. Self-Review Checklist

Before claiming DONE, implementing agent should ask:

- What could break?
- What permission did I assume?
- What tenant boundary did I touch?
- What happens with empty data?
- What happens if provider fails?
- What happens on mobile?
- Did I expose secrets?
- Did I add hidden coupling?

---

# 74. Browser Screenshot Review

For significant visual work:

- capture final screenshots;
- compare against reference/design;
- inspect actual rendered UI.

Code inspection is not visual QA.

---

# 75. Designer QA

Designer changes should test:

- selection;
- reorder;
- editing;
- autosave;
- reload persistence;
- responsive preview;
- Preview output;
- console errors.

Undo/Redo joins the checklist when implemented.

---

# 76. Form QA

Forms should test:

- required fields;
- phone normalization;
- validation;
- CAPTCHA;
- duplicate submission;
- success UX;
- Submission persistence;
- Delivery creation.

---

# 77. Automotive QA

Vehicle UX should test:

- cascading catalog selection;
- Trim selection;
- color swatches;
- two-tone colors;
- image fallback;
- price;
- inactive vehicle;
- Site copy independence.

---

# 78. Publishing QA

Publishing flow should test:

- Draft edit;
- Preview;
- Publish;
- production verification;
- second Draft change without Publish;
- failed Publish;
- restore/version where implemented.

---

# 79. Integration QA

Integration work should test:

- Profile creation;
- Site override;
- test connection;
- Form mapping;
- successful delivery;
- failed delivery;
- retry;
- secret masking.

---

# 80. Super Admin QA

Platform admin changes should verify:

- platform role;
- audit;
- no customer-role escalation;
- tenancy bypass only where explicit;
- least privilege.

---

# 81. Git Hygiene

Task changes should be scoped.

Avoid unrelated repository-wide refactors or formatting.

---

# 82. Generated Files

Do not commit transient generated files unless intentionally part of repository.

Examples:

- local screenshots;
- caches;
- runtime output;
- secrets.

---

# 83. TODOs

A task is not DONE if critical behavior remains as an undocumented TODO.

Non-blocking future enhancements belong in BACKLOG.

---

# 84. Known Limitations

Accepted limitations should be documented in task result, PROJECT_STATE, or BACKLOG.

Do not hide them.

---

# 85. BLOCKED_DECISION

Use `BLOCKED_DECISION` when implementation requires a major unresolved choice such as:

- publishing runtime architecture;
- billing semantics;
- incompatible automotive source model;
- Marketplace execution model.

Agent should state:

- decision required;
- options;
- impact.

It must not invent a major irreversible answer.

---

# 86. BLOCKED_EXTERNAL

Use when full verification requires:

- missing credential;
- DNS;
- third-party account;
- production-only setup.

Automated/local parts should still be completed as far as possible.

---

# 87. No Fake Success

Agents must never:

- mark skipped test as passed;
- claim browser verified without browser execution;
- claim provider tested without actual/mocked test;
- claim migration safe without running it;
- claim build passes without build.

Report facts.

---

# 88. Final Task Report

Every autonomous task should eventually produce a concise report containing:

- task ID/title;
- status;
- files changed;
- behavior implemented;
- checks run;
- result;
- remaining issues;
- architecture decisions created;
- next-task readiness.

---

# 89. Check Result Format

Recommended:

```text
PHPUnit: PASS
Larastan: PASS
Pint: PASS
TypeScript: PASS
Build: PASS
Playwright: PASS
Browser Console: PASS
Security/Tenancy: PASS
```

If unavailable:

```text
Playwright: NOT_AVAILABLE_YET
```

If irrelevant:

```text
Playwright: NOT_APPLICABLE — backend-only migration
```

---

# 90. Autonomous Fix Loop

When a required check fails:

Implement
→ Run checks
→ Analyze failure
→ Fix
→ Run checks again

Do not proceed to next task while a required check remains failing.

Repeated architectural failure may become `BLOCKED_DECISION`.

---

# 91. Regression Responsibility

If new work breaks an existing test:

- fix regression;
or
- prove test is outdated because of an approved change and update it deliberately.

Never delete failing tests merely to make pipeline green.

---

# 92. Definition of Done Is Mandatory

Cursor agents cannot override this document for convenience.

Task prompts may add stricter checks.

They may not silently remove critical security/tenancy requirements.

---

# 93. Initial Tooling Reality

Some checks may not yet exist in the repository.

Before a tool is introduced, mark it:

`NOT_AVAILABLE_YET`

Never mark it PASS.

Phase 0 will establish the required automation.

---

# 94. Source of Truth

This document works together with:

- Product docs;
- Architecture docs;
- future `MASTER_PLAN.md`;
- future `BACKLOG.md`;
- future Cursor rules;
- future CI configuration.

If a task conflicts with architecture, the conflict must be resolved before DONE.

---

# 95. Final Definition

A Landflow task is DONE only when:

**The requested behavior works.  
The correct owner/scope is enforced.  
Permissions and entitlements are correct.  
Security boundaries are preserved.  
Relevant automated checks pass.  
The actual UI/flow is verified where applicable.  
No required test remains failing.  
Architecture/documentation remain consistent.  
The implementation is ready for the next task without hidden debt.**
