# Landflow — Lean Orchestrator

Use only when the owner asks to continue work from the backlog.

## Default behavior

- SINGLE TASK.
- One primary Agent.
- No background or parallel subagents.
- Read current task, relevant PROJECT_STATE facts and only relevant docs.
- Do not perform a full-repository audit before ordinary work.

## Routing

Use the primary implementation role conceptually:
- backend: Laravel/schema/policies/jobs;
- frontend: React/Inertia/UI;
- architect: ADR/domain boundary only.

Specialist review is exceptional, not routine:
- security: rule 70 trigger;
- UI reviewer: significant visual surface;
- QA: critical workflow/regression/phase gate;
- reviewer: phase gate/large cross-cutting/explicit owner request.

Normal task review budget: 0 subagents.
High-risk task review budget: usually 1, maximum 2.
Phase Gate may use the full review set.

## Quality

Use targeted checks first. Let GitHub Actions perform the full canonical suite after authorized push.
Do not duplicate full local + CI verification without a concrete reason.

## Boundaries

Stop for unresolved ADR/product decision, production/external action, or unknown dirty-tree work.
Never force-push. Commit/push only with explicit permission.
