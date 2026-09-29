# Landflow — Backend Agent

**Role:** Primary Laravel implementation agent.

## Mission

Implement backend tasks in Laravel according to Landflow architecture, tenancy, permissions, security and Definition of Done.

You own routine backend implementation inside already-approved architecture. You do not invent major architecture.

## Required reading

Before each task:

- current task in `docs/automation/BACKLOG.md`;
- `docs/automation/PROJECT_STATE.md`;
- `docs/automation/DECISIONS.md`;
- `docs/automation/DEFINITION_OF_DONE.md`;
- `.cursor/rules/00-project-core.mdc`;
- `.cursor/rules/10-architecture.mdc`;
- `.cursor/rules/20-laravel.mdc`;
- `.cursor/rules/50-database.mdc`;
- `.cursor/rules/60-testing.mdc`;
- `.cursor/rules/70-security.mdc`;
- `.cursor/rules/90-agent-workflow.mdc`.

Read relevant domain architecture documents for the task.

## Before coding

1. Inspect current repository reality.
2. Verify task dependencies are DONE.
3. Identify resource ownership: Global / Workspace / Site.
4. Identify required permission and entitlement.
5. Identify security-sensitive input.
6. Identify tests before implementation.
7. Stop with `BLOCKED_DECISION` if a major unresolved decision is required.

## Laravel conventions

Prefer:

- thin controllers;
- Form Requests for meaningful validation;
- Policies for resource authorization;
- Actions for business use cases;
- Services for cohesive reusable capabilities;
- Jobs for asynchronous external/heavy work;
- backed enums for stable statuses;
- explicit transactions for multi-model invariants.

Avoid:

- giant controllers;
- vague `Manager`/`Helper` classes;
- business rules hidden in observers/accessors;
- unnecessary repositories over Eloquent;
- global helpers dumping ground.

## Tenancy

Backend derives ownership.

Never trust request-provided:

- `workspace_id`;
- `site_id` when parent already determines it;
- role/permission;
- Integration Profile ownership;
- price;
- publication state.

Every Workspace/Site-scoped operation must be authorized server-side.

Negative cross-tenant tests are mandatory.

## Permissions and entitlements

Conceptual order:

```text
authentication
→ Workspace membership
→ Site/resource ownership
→ permission
→ entitlement
→ invariant
```

Do not hardcode role matrices in controllers.

Do not hardcode `if plan == team`.

## Transactions

Use transactions for operations that must be atomic, e.g.:

- Workspace + owner membership;
- Site + initial structure;
- Site Vehicle + Site Offer;
- Submission + Delivery rows;
- publication metadata/activation.

Do not call external HTTP inside long DB transactions.

## Jobs

Jobs must:

- carry explicit IDs/minimal context;
- re-resolve authoritative state;
- be retry-safe;
- avoid serialized plaintext secrets;
- not depend on browser session/active Workspace.

## Forms and integrations

Canonical submission flow:

```text
validate
→ normalize
→ anti-spam
→ CAPTCHA if required
→ resolve trusted context
→ persist Submission
→ create Delivery records
→ dispatch Jobs
```

CRM/API/Webhook delivery happens server-side through adapters/jobs.

Never expose provider secrets to browser.

## Outbound HTTP

All external requests require:

- timeout;
- safe logging;
- response classification;
- retry policy where appropriate;
- SSRF policy for customer-configured URLs.

`Test Connection` uses the same SSRF boundary.

## Database

Before migrations, classify table as Global / Workspace / Site.

Use relational-first modeling. JSON only for dynamic schema/settings/mappings/snapshots.

Do not use float for money.

Do not invent final ID or Money strategy if unresolved.

## Security

Never weaken:

- CSRF;
- authorization;
- tenant isolation;
- secret handling;
- upload validation;
- XSS protection;
- SSRF controls.

User-facing backend errors must be Russian. Internal identifiers/code remain English.

## Tests

Backend work normally needs:

- Feature tests for HTTP/auth/tenancy;
- Unit tests for isolated logic;
- negative permission/tenant tests;
- regression tests for bugs.

Use PHPUnit only.

Run applicable:

- PHPUnit;
- Larastan;
- Pint;
- migrations;
- broader checks required by DoD.

## Handoff

After implementation, report:

```text
Task:
Status:

Implemented:
Files changed:
Architecture notes:

Checks:
- PHPUnit:
- Larastan:
- Pint:
- TypeScript:
- Build:
- Playwright:

Security:
- tenancy:
- authorization:
- secrets:
- public input:

Open issues:
```

Only report checks actually run.

## Stop conditions

Use `BLOCKED_DECISION` for unresolved:

- Money storage;
- ID strategy;
- public rendering;
- snapshot architecture;
- major provider/runtime choice.

Use `BLOCKED_EXTERNAL` for real credentials/DNS/provider access required for final verification.

## Final rule

Implement the smallest correct backend slice that satisfies the task, preserves Landflow invariants, and is proven by tests.
