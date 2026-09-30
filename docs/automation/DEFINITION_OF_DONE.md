# Landflow — Definition of Done (Lean)

A task is DONE when its acceptance criteria are implemented, applicable targeted checks pass, required risk-triggered review passes, and durable project state is updated.

## Required for every implementation task

- scope matches the backlog task;
- no unresolved blocker was silently decided;
- relevant tests/checks actually ran and passed;
- no known blocking defect remains;
- user-facing UI is Russian where applicable;
- no secrets/runtime artifacts are added to Git;
- BACKLOG/PROJECT_STATE updated only when real state changed.

## Testing level

Use targeted local verification first.

Backend change:
- relevant PHPUnit tests;
- Larastan/Pint when applicable to the changed PHP surface.

Frontend change:
- `npm run check`;
- build when the change affects bundling/runtime integration;
- relevant Playwright flow when browser behavior changed.

Docs/rules-only:
- consistency/link/content checks appropriate to the edit;
- do not run expensive browser suites without a reason.

## Full gate

Full `composer quality` and full `npm run test:e2e` are mandatory for:
- Phase Gate;
- broad/cross-cutting release-quality changes;
- high-risk changes where targeted tests are insufficient;
- release/deploy preparation;
- cases explicitly required by the task.

Otherwise GitHub Actions is the canonical full post-push verification.

## Reviews

Independent review is risk-triggered, not universal.

- Security: security-sensitive trigger.
- UI: significant visual/user-facing surface.
- QA: critical workflow/regression/security bug/Phase Gate.
- Architect: ADR/cross-cutting architecture.
- Final Reviewer: Phase Gate, large cross-cutting task or explicit owner request.

An ordinary task does not require all reviewers.

## Evidence

Never claim a test/review/browser check passed if it did not run.
Use `NOT_APPLICABLE` when genuinely irrelevant.
Use `BLOCKED_DECISION` or `BLOCKED_EXTERNAL` when appropriate.
