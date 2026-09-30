# Landflow — Lean Autonomous Workflow v2

## Goal

Keep Agent work reliable without paying for a multi-agent review chain on every task.

Default flow:

```text
current task
→ one primary Agent
→ targeted implementation
→ targeted checks
→ optional risk-triggered review
→ concise state update
→ authorized commit/push
→ GitHub CI full gate
```

## Source of truth

Use:
- `BACKLOG.md` for task/dependencies/status;
- `PROJECT_STATE.md` for current facts;
- `DECISIONS.md` for approved/open decisions;
- repository reality for what actually exists.

Read only relevant sections. Do not preload the full documentation set.

## Modes

### SINGLE TASK — default

Complete one ready task and stop.

### CONTINUOUS — explicit only

Allowed only when the owner explicitly asks for it. Still stop on ADR/product decision, external/production action, Phase Gate or unknown repository state.

## Task start

1. Check `git status`.
2. Find the requested/current ready task.
3. Verify dependencies.
4. Check only decisions that can affect this task.
5. Inspect relevant code.
6. Select one primary implementation role.

Do not start multiple reviewers before implementation.

## Context budget

For ordinary work, usually enough:
- current backlog task;
- relevant PROJECT_STATE section;
- one or two relevant architecture docs/rules;
- files being changed.

Do not read all rules/agents/docs unless performing a Phase Gate.

## Review matrix

### No independent review by default

Routine implementation, CRUD, small UI, documentation and straightforward refactors.

### Security review

Required when the task materially changes:
- authentication/session recovery;
- tenancy/resource ownership;
- permissions;
- public endpoints;
- secrets;
- uploads;
- outbound HTTP/integrations;
- publishing/domains;
- personal-data export;
- developer/marketplace runtime.

Use one security review after implementation and targeted tests.

### UI review

Use for significant new screens, Designer/public UI or complex responsive changes. Not for every label, spacing change or backend task.

### QA review

Use for critical workflows, regression/security bugs, complex stateful behavior and Phase Gates.

### Architect

Use only for ADR/domain-boundary/cross-cutting architecture questions.

### Final Reviewer

Use only for Phase Gates, large cross-cutting tasks or explicit owner request.

### Review budget

- ordinary task: 0 subagents;
- high-risk task: normally 1, maximum 2 if two independent risks truly require it;
- Phase Gate: full review process allowed.

No parallel/background subagents for ordinary tasks.

## Verification policy

During implementation use targeted checks.

Examples:
- specific PHPUnit file/filter;
- `npm run check` for frontend changes;
- relevant Playwright spec for changed browser flow;
- Larastan when PHP/static typing changes justify it.

GitHub Actions is the full canonical post-push gate.
Do not run a full local suite and then make the Agent reread every green CI log unless needed.

Run full local `composer quality` + `npm run test:e2e` for Phase Gates, broad/cross-cutting work, high-risk work where targeted checks are insufficient, or when CI cannot provide the full gate.

## Fix loop

Targeted check fails -> diagnose -> fix -> rerun the same relevant check.

Do not launch a new reviewer for every small fix. If a security reviewer rejected a security issue, rerun that same security review after the fix; do not automatically add QA/UI/Final Reviewer.

If repeated attempts make no progress, stop and report `STALLED` without inventing a new persistent backlog status.

## Git and CI

Commit/push only with explicit owner authorization.
Never force-push.
Never overwrite unknown dirty-tree changes.
Never commit secrets/runtime artifacts.

After push:
- GitHub Actions success is sufficient evidence for the full gate;
- inspect detailed logs only if CI fails or a specific step requires investigation.

## State updates

Keep state concise.

BACKLOG: status + short result.
PROJECT_STATE: only durable current facts.
DECISIONS: only approved/open durable decisions.

Do not copy long Agent transcripts into project docs.

## Human approval boundaries

Stop for:
- unresolved ADR/product decision;
- production deploy/DNS/billing/credentials;
- destructive production/customer-data action;
- external provider approval/account action requiring owner involvement.

## Phase boundary

Do not cross a Phase Gate automatically. Phase Gate may use the full review set and full quality suite.

## Owner commands

Ordinary task:

```text
Выполни следующую ready task Landflow по Lean Autonomous Workflow в SINGLE TASK MODE. Не запускай subagents без явного risk trigger. Используй targeted checks. Commit/push только с моего разрешения.
```

Small fix:

```text
Исправь только указанную проблему. Не запускай subagents. Запусти только релевантные targeted tests. Не делай commit/push.
```

Status only:

```text
Покажи следующую ready task, blockers и нужные проверки. Ничего не изменяй и не запускай subagents.
```
