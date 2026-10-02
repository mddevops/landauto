# AGENTS.md — Landflow / Cursor / Codex

## Purpose

This is the lightweight operating guide for autonomous coding agents working in the Landflow repository.

Landflow is a Laravel + React + Inertia + shadcn/ui SaaS website builder focused on automotive websites.

The goal is to keep agents effective while minimizing token/context usage and avoiding unnecessary multi-agent review chains.

## Task order

Before starting or recommending the next task:

1. Re-read `docs/automation/BACKLOG.md`.
2. Select the first ready task according to order and dependencies.
3. Cross-check `docs/automation/PROJECT_STATE.md`.

Never use the `Next:` line from a previous task report as the source of truth.

`BACKLOG.md` overrides previously reported next-task values.

## 1. Source of truth

Read first:
- `docs/automation/BACKLOG.md` — task order, dependencies, status.
- `docs/automation/PROJECT_STATE.md` — actual current implementation state.
- `docs/automation/DECISIONS.md` — approved/open product and architecture decisions.
- `docs/automation/QUALITY_COMMANDS.md` — canonical checks, if present.
- `docs/automation/DEFINITION_OF_DONE.md` — completion criteria, if present.

Read only architecture/product documents directly relevant to the current task.
Do not read the entire `docs/` tree before every task.

If docs conflict with repository reality, report the mismatch and use repository evidence for what currently exists.

## 2. Context diet

For each task, load only:
- this `AGENTS.md`;
- the exact task in `BACKLOG.md`;
- the relevant section of `PROJECT_STATE.md`;
- directly related approved decisions/ADRs;
- only the code/files needed for the task.

Do not repeatedly summarize known architecture.
Do not read unrelated docs “just in case”.
Do not spawn subagents unless explicitly requested or a concrete high-risk boundary requires one.

## 3. Product language

All user-facing Landflow UI is Russian only.

Code identifiers remain English: classes, methods, variables, tables, columns, routes, permissions, TypeScript types and API fields.

External proper nouns such as Yandex may remain original.

## 4. Core architecture invariants

Preserve these unless an approved ADR changes them:

1. `User` is identity, not tenant.
2. `Workspace` is the tenant/ownership boundary.
3. A user may belong to multiple Workspaces.
4. A `Site` belongs to exactly one Workspace.
5. Global Automotive Catalog is platform-owned and customer-immutable.
6. Automotive layers: Global Catalog → Workspace Vehicle Library → Site Vehicle / Site Offer.
7. Site Offer owns commercial pricing, benefits, availability, badges and CTA state.
8. Template defines structure/design defaults and does not own customer vehicles, prices, integrations, domain or Site commercial state.
9. A Site created from a Template becomes independent customer-owned state.
10. Site-to-site reuse defaults to copy, not live sync.
11. Block Schema is deterministic runtime truth.
12. Block Definition / immutable Block Version / Site-owned Block Instance are separate.
13. Form and Popup are separate entities.
14. Valid Submission is persisted before external delivery.
15. Workspace owns reusable integration credentials; Site stores bindings/overrides.
16. Draft and Published state are separate.
17. Autosave never publishes.
18. Permissions and entitlements are separate.
19. Backend authorization is authoritative.
20. Secrets never go to React props, public output, logs, exports or committed files.
21. Marketplace is not MVP unless a backlog task explicitly says otherwise.
22. Do not grow Landflow into telephony/call-center/tasks/team chat/full CRM unless explicitly added.

## 5. Identifier strategy — ADR-001 / D-085

Approved:
- Internal PK/FK: `BIGINT UNSIGNED`.
- Externally addressable domain entities get immutable unique ULID `public_id` where defined.
- Numeric internal IDs must not be public URL/API contracts when `public_id` exists.
- Internal/pivot/log tables do not need `public_id` unless they become first-class externally addressable entities.
- `users.id` remains bigint.
- Invitations/preview/unsubscribe flows use separate random tokens.

`public_id` is an identifier, never authorization.

## 6. Authentication decisions

Supported authentication paths:
1. Email/password with mandatory email verification.
2. First-party Yandex OAuth through Laravel HTTP client.

Not supported:
- 2FA
- TOTP
- passkeys
- WebAuthn

Yandex rules:
- provider `user_id` is the stable external identity key;
- never auto-link an existing Landflow account solely because emails match;
- existing Yandex identity → normal login;
- new identity + free email → create user/identity;
- new identity + existing Landflow email → do not login or auto-link; require explicit future linking flow;
- Yandex-only user has `password = NULL`;
- do not persist OAuth access tokens unless future functionality explicitly requires it;
- credentials stay in env/config only.

## 7. Workspace / authorization / entitlement rules

- Current Workspace is resolved by backend context.
- Never trust `workspace_id` from the browser for ownership.
- Use central permission keys; do not perform raw role checks when permission foundation exists.
- `publish_site` is separate permission.
- Permissions and entitlements must remain separate.
- Never hardcode business logic like `if plan === free/pro/team`.
- Use typed entitlement keys.
- `max_sites` counts only active Sites (D-099).
- Archived Sites do not consume `max_sites`.
- Future archived → active restore must re-check the limit.

## 8. Lean single-task workflow

Default mode: one task, one primary agent.

For every task:
1. Read exact task from `BACKLOG.md`.
2. Cross-check relevant `PROJECT_STATE.md`.
3. Read only related approved decisions/docs.
4. Inspect actual repository code.
5. Implement the smallest correct change.
6. Run focused checks only.
7. Do one short risk-focused self-review.
8. Stop and report.

Do not implement the next task early.
Do not perform unrelated refactors.
Do not invent future fields/routes/actions/data.

## 9. Autonomous batch mode

Use this mode only when the user explicitly requests autonomous/batch/night work.

Rules:
- Work only from ordered ready tasks in `BACKLOG.md`.
- Batch size must be explicitly provided by the user/prompt.
- Re-read `BACKLOG.md` before every new task.
- One primary agent only.
- Make one local commit per completed task if the batch prompt explicitly authorizes local commits.
- Do not merge into `main` during the batch.
- Do not force push.
- Push only the explicitly authorized autonomous branch, and only if the batch prompt allows it.
- Stop immediately on unresolved product/architecture decisions.
- Never invent a decision merely to keep the batch moving.
- Use targeted checks per task.
- Run full quality gates only at checkpoints and at batch end.
- Do not run multi-agent review chains unless explicitly requested.

Suggested checkpoint cadence: every 3 completed tasks.

At checkpoint:
```bash
composer quality
```

If significant browser-visible behavior changed:
```bash
npm run test:e2e
```

At batch end, run the same final gates and provide one compact report for the whole batch.

## 10. Hard stop conditions

Stop and report instead of guessing if any of these occur:
- `BLOCKED_DECISION`;
- docs contradict each other on a material rule;
- ownership/tenant boundary is unclear;
- a new ADR/product decision is required;
- destructive DB operation would be required;
- production secrets/DNS/provider approval are required;
- a new major dependency/package is required without task approval;
- an external API/provider contract must be invented;
- billing/pricing/lifecycle rule is undefined;
- unknown pre-existing working-tree changes are discovered;
- two reasonable fix attempts fail at a checkpoint.

Use `BLOCKED_EXTERNAL` for credentials, provider approval, DNS or similar unavailable external dependencies.

## 11. Testing strategy

Use targeted checks while implementing.

Backend-only, as relevant:
```bash
php artisan test tests/Feature/...
composer analyse
composer format:check
```

Frontend-only, as relevant:
```bash
npm run check
```

Run focused Playwright specs only when browser-visible behavior changed.

Canonical full gates:
```bash
composer quality
npm run test:e2e
```

Do not automatically run full suites after every small task.
After an authorized push, GitHub Actions is the primary clean-checkout verifier.

Never report PASS unless the command actually ran.

## 12. Git safety

Before editing:
```bash
git status
```

Do not overwrite unknown uncommitted work.
Never force push.
Default: do not commit/push without explicit authorization, except when an explicit autonomous batch prompt grants scoped local-commit/branch-push permission.

Never commit:
- `.env`
- `.env.e2e`
- `database/e2e.sqlite`
- `vendor/`
- `node_modules/`
- `public/build/`
- `test-results/`
- `playwright-report/`
- `blob-report/`
- `.phpstan-cache/`
- local logs
- `.idea/`
- intentionally untracked generated Wayfinder output

Before commit inspect diff/status for secrets, tokens, passwords and private keys.

Repository: `mddevops/landauto`.

## 13. Database safety

Local development uses MySQL.
Automated tests use isolated SQLite environments according to repository configuration.

Never casually run against development MySQL:
```bash
php artisan migrate:fresh
php artisan db:wipe
php artisan migrate:reset
```

Use safe incremental migrations.
Before migration, verify connection/database when there is any uncertainty.

## 14. Laravel conventions

Prefer:
- thin controllers;
- Form Requests;
- Policies;
- cohesive Actions/services;
- transactions for multi-record invariants;
- backed enums for stable statuses.

Avoid:
- giant controllers;
- vague `Manager`/`Helper` dumping grounds;
- frontend-only authorization;
- unnecessary repository abstractions.

Backend derives ownership. Never trust browser-supplied workspace/user/role/permission/plan/ownership state.

## 15. React / Inertia conventions

Use the existing React + Inertia + shadcn/ui stack.
Do not introduce another frontend framework.

Prefer explicit safe Inertia props over serializing whole Eloquent models.
Do not expose secrets or privileged numeric internal identifiers to React.
Do not create fake routes/actions for UI completeness.
All browser-visible copy is Russian.

## 16. Security review threshold

Extra security scrutiny is required only when materially touching:
- tenancy isolation;
- authorization/permissions;
- authentication;
- secrets;
- public endpoints;
- uploads;
- outbound HTTP/SSRF;
- forms/submissions;
- publishing;
- custom domains;
- personal-data export;
- marketplace/developer runtime;
- production-sensitive behavior.

Routine UI/schema tasks do not need a security-review agent chain.

## 17. Documentation updates

Keep docs changes small.

Update `BACKLOG.md` and `PROJECT_STATE.md` only when real task state changes.
Update `DECISIONS.md` only for durable decisions.
Do not paste large implementation reports into docs.

## 18. Reporting

Single-task report should be compact:

```text
Task:
Status:

Implemented:
- ...

Checks:
- ...

Changed files:
- ...

Open issues:
- ...
```

Autonomous batch mode should produce one final batch report, not a long report after every task.

## 19. Current repository handoff

As of commit `64aabcb5bbf0a713b4b8ceec4d7ed5e70c193eed`:
- P1-013 — Create Site Flow Backend: DONE.
- D-099 — active Sites count toward `max_sites`, archived do not: APPROVED.
- X-012 — UI / Accessibility Hygiene Follow-ups: DONE.
- P1-014 — Dashboard UI: DONE.
- Browser E2E stabilization: PASS in GitHub CI.

Do not trust this section indefinitely.
Before any next task, `BACKLOG.md` + `PROJECT_STATE.md` + repository state override this handoff.
