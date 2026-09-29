# Landflow — Autonomous Task Workflow

**Document:** `docs/automation/AUTONOMOUS_WORKFLOW.md`  
**Status:** Active protocol (P0-026)  
**Executed by:** [Orchestrator agent](../../.cursor/agents/orchestrator.md)  
**Purpose:** Define how Cursor selects, executes, verifies and closes Landflow backlog tasks with the existing specialist agents, and where it must stop.

This document is the normative protocol. `.cursor/agents/orchestrator.md` is the role prompt that executes it. If the two ever disagree, stop and report the conflict; do not pick one silently.

---

# 1. What This Is (and Is Not)

The autonomous workflow is a **repository-driven protocol**:

```text
Cursor agents
+ repository documents as source of truth
+ canonical quality commands
= autonomous task cycle
```

It is not a workflow engine. There is no orchestration database, daemon, queue, BACKLOG parser, external AI service or extra package. The Orchestrator reads Markdown and the repository, delegates to specialist agents, runs the canonical commands and edits the state documents.

Custom Cursor commands/workflow files (`.cursor/commands/`, `.cursor/workflows/`) are intentionally not used. The owner starts the workflow with the plain-text commands in §21.

---

# 2. Roles

| Role | File | Responsibility in the cycle |
|------|------|-----------------------------|
| Orchestrator | [orchestrator.md](../../.cursor/agents/orchestrator.md) | Selects the task, routes work, runs gates, requests reviews, updates state. Coordinator only. |
| Architect | [architect.md](../../.cursor/agents/architect.md) | Architecture, ADRs, ownership, domain boundaries, table scope, infrastructure. |
| Backend | [backend.md](../../.cursor/agents/backend.md) | Laravel, migrations, models, policies, Actions, jobs, backend tests. |
| Frontend | [frontend.md](../../.cursor/agents/frontend.md) | React, Inertia, shadcn, Designer, frontend state, browser flows. |
| UI Reviewer | [ui-reviewer.md](../../.cursor/agents/ui-reviewer.md) | Independent visual/product review in the real browser. |
| QA | [qa.md](../../.cursor/agents/qa.md) | Independent behavior/regression proof, Playwright, reproduction. |
| Security | [security.md](../../.cursor/agents/security.md) | Independent security review of sensitive changes. |
| Reviewer | [reviewer.md](../../.cursor/agents/reviewer.md) | Final independent Definition of Done gate before `DONE`. |

The Orchestrator does not implement Laravel, React, QA or security work itself in place of the specialists. Its own edits are limited to coordination content:

- task status and `### Result` in BACKLOG;
- new BACKLOG tasks required by the protocol (a missing ADR task, a follow-up discovered during work), following BACKLOG §4 (cross-cutting ADR tasks use the existing `X-` convention of the "Cross-Cutting Backlog Tasks" section) — always created as `NOT_STARTED`, then executed through the normal cycle;
- facts in PROJECT_STATE;
- DECISIONS entries in the cases of §17;
- automation/process documentation (`docs/automation/*` workflow content) when that is the task itself (§10);
- trivial mechanical fixes explicitly returned by a reviewer when no specialist judgment is involved.

---

# 3. Source of Truth

| Source | Answers |
|--------|---------|
| [BACKLOG.md](BACKLOG.md) | Tasks, order, statuses, dependencies, acceptance criteria. |
| [PROJECT_STATE.md](PROJECT_STATE.md) | Actual current project state and current phase. |
| [DECISIONS.md](DECISIONS.md) | `APPROVED`, `PROVISIONAL`, `SUPERSEDED`, `OPEN`, `ADR_REQUIRED` decisions. |
| [DEFINITION_OF_DONE.md](DEFINITION_OF_DONE.md) | When a task is complete. |
| [QUALITY_COMMANDS.md](QUALITY_COMMANDS.md) | Canonical check commands. |
| [MASTER_PLAN.md](MASTER_PLAN.md) | Phase order and phase gates. |
| `docs/product/*`, `docs/architecture/*` | Product and architecture invariants. |
| `.cursor/rules/*.mdc` | Operating rules (always applied). |
| Repository | What actually exists in code, tests, config and Git. |

Repository reality decides what exists; documents decide what is intended and allowed.

If documents and repository reality diverge (a task is `DONE` but its artifact is missing, PROJECT_STATE contradicts BACKLOG, a referenced file does not exist):

- do not guess which side is right;
- do not hide the divergence;
- report it with file/line evidence;
- continue only if the divergence does not affect the selected task; otherwise stop (§14).

---

# 4. Task Statuses

The Orchestrator sets only:

```text
NOT_STARTED
IN_PROGRESS
PARTIAL
BLOCKED_DECISION
BLOCKED_EXTERNAL
DONE
```

`DEFERRED` exists in the BACKLOG vocabulary for intentionally postponed work. It is an owner decision: the Orchestrator never selects a `DEFERRED` task and never sets `DEFERRED` itself.

`STALLED` is **not** a status. It is a runtime condition of the fix loop (§13). The persistent status stays what it really is (`IN_PROGRESS`, `PARTIAL` or `BLOCKED_*`).

The Orchestrator changes only the status of the task it is executing. It never changes the status of future tasks.

---

# 5. Reading the Backlog

- Phases are the `# PHASE N — ...` sections of BACKLOG. Tasks are `## <ID> — <Title>` headings with `**Status:**` and `**Dependencies:**` lines.
- BACKLOG order is the document order inside a phase section.
- Dependency notation:
  - `none` — no dependencies;
  - `P1-010, P1-008` — every listed task;
  - `P0-014 through P0-025` — every task from the first to the last ID **in BACKLOG document order**, inclusive, including letter-suffixed tasks (`P0-021A`, `P0-021B`, `P0-021C` lie between `P0-021` and `P0-022`).
- A dependency counts as satisfied only when its status is exactly `DONE` (the heading line may add a qualifier, e.g. `DONE (CI configured and verified on GitHub Actions)`).
- The **Phase Review task** is the phase's final validation task: `P0-027 — Phase 0 Validation`, `P1-017 — Phase 1 Review`, `P2-018 — Phase 2 Review`, and so on.
- **Cross-cutting tasks** (`X-001` ...) sit outside the phase sections and have a `**Trigger:**` instead of dependencies:
  - `before <task ID>` or a bare task ID (`P7-008`) makes the X-task an implicit dependency of that task: the target task is not ready until the X-task is `DONE`;
  - when the target task would otherwise be the next selection, the X-task is selected instead and is treated as a task of the current phase (§7);
  - a non-ID trigger (a measured need, "before production-scale media deployment") is checked whenever a candidate task would act on that condition; if it applies, the X-task is selected first.

---

# 6. Current Phase

Determine the current phase from both:

1. PROJECT_STATE §54 "Phase Status" — the phase marked `IN_PROGRESS` (authoritative phase status, MASTER_PLAN §138);
2. BACKLOG — the lowest phase that still contains a task that is not `DONE` (ignoring `DEFERRED`), cross-checked with BACKLOG §3 "Current Backlog Position".

They must agree. If they do not, report the divergence and stop before selecting a task. Other prose mentions of the phase in PROJECT_STATE are descriptive; if one contradicts §54, report it as a divergence, but §54 decides.

The previous phase gate is satisfied only when that phase's Phase Review task is `DONE`. For Phase 0 this is `P0-027`.

**Phase transition.** When a Phase Review task closes as `DONE` with a final Reviewer `PASS`, the Orchestrator, in the same run, sets in PROJECT_STATE §54 the finished phase to `COMPLETED` (MASTER_PLAN §3) and the next phase to `IN_PROGRESS` — status only, no task of the new phase is started — and then stops (§18). This keeps the agreement check above valid for the next run: §54 and BACKLOG both point to the new phase.

---

# 7. Ready Task and Selection

A task is **ready** when all hold:

- status = `NOT_STARTED`;
- every dependency is `DONE` (§5), including implicit cross-cutting ADR dependencies;
- no blocking unresolved decision (see below);
- the task belongs to the current phase (a cross-cutting task counts as current-phase when its trigger is reached, §5);
- the previous phase gate is satisfied.

**Blocking decisions.** DECISIONS "Blocking" sections are free text ("Phase 5", "Core domain migration implementation ..."), so this check is a judgment, made conservatively:

- an `ADR_REQUIRED` entry, or an `OPEN` entry with a "Blocking" section, whose text plausibly covers the task blocks it;
- an entry without a "Blocking" section (or marked "Not Blocking") does not block, unless doing the task would implicitly make that decision;
- when unsure, ask the Architect for an ownership/decision assessment before starting;
- the selected task then becomes `BLOCKED_DECISION` with the §14 record; if no BACKLOG task resolves the decision, first add one (cross-cutting `X-` ADR task, `NOT_STARTED`, trigger `before <task ID>`) and reference it in the record. Then stop. The Architect may draft the ADR; accepting it is an owner decision.

Selection order:

1. **Resume first.** If a task of the current phase is `IN_PROGRESS` or `PARTIAL`, it is the current task. Resume it (after the Git check in §9) instead of starting a new one. Run only one Orchestrator session per working copy at a time: an `IN_PROGRESS` status does not show whether another session is still working on it. If there is any sign of a concurrent session (the owner mentions one, files keep changing), ask the owner before resuming.
2. Otherwise select the **first ready task of the current phase in BACKLOG order**. Never skip ahead to an easier or more interesting task.
3. A task already `BLOCKED_DECISION` / `BLOCKED_EXTERNAL` is reported at the start of the run. If its blocker is resolved (the ADR is accepted and DECISIONS updated, or the external action is done), it is the current task again: resume it and set it back to `IN_PROGRESS`. Otherwise the first ready task after it may be selected only if it does not depend on the blocked task directly or through its dependencies.
4. If no task is ready and the phase is not complete, report why (missing dependency, blocker, divergence) and stop.

**Owner-named task.** The owner may name a specific task (rule `90-agent-workflow.mdc` §47). It is executed only if it is ready by the definition above; naming it may override BACKLOG order inside the current phase, but never dependencies, blocking decisions, the phase boundary or the phase gate. If it is not ready, report why and stop.

A task from a later phase is never selected, even if its own dependencies look satisfied.

---

# 8. Modes

## SINGLE TASK MODE (default)

Execute exactly one task (the resumed or next ready one) through the full cycle, then stop and report, ending with `Next ready task: <ID — title>`.

This is the default and the safest mode. Use it whenever the owner did not explicitly ask for CONTINUOUS mode.

An owner command in SINGLE TASK MODE (command 1 in §21, or naming the task) is the explicit request that allows a Phase Review task to run when it is the next ready task.

## CONTINUOUS MODE

Only when the owner explicitly asks. Execute ready tasks one after another, each through the full cycle, until a stop condition occurs:

- the next ready task is a **Phase Review task** (the phase gate is run in SINGLE TASK MODE on an explicit owner request);
- a task becomes `BLOCKED_DECISION` or `BLOCKED_EXTERNAL`;
- human approval is required (§15);
- a production or external sensitive action would be next;
- the fix loop is STALLED (§13);
- no ready task remains in the current phase;
- the working tree contains changes of unknown origin (§16).

CONTINUOUS MODE never enters the next phase. It always stops before a Phase Review task and after any phase gate.

## Read-only status mode

Owner command 3 (§21). Read the state and report the current phase, current/next ready task, its dependencies with statuses, primary specialist, required reviewers and required gates. No file changes, no status changes, no gates.

## Review-only mode

Owner command 4 (§21). Run the Definition of Done review of the current task through the Reviewer (plus the specialist reviews it requires). Implement nothing. The only allowed change is recording the review outcome if the owner asks for it.

---

# 9. Task Start Sequence

Before any code change:

1. `git status` — clean tree, or every change attributed (§16);
2. read the task: objective, scope, acceptance criteria, required checks, references;
3. verify every dependency is `DONE`;
4. check DECISIONS (and cross-cutting ADR triggers) for blocking decisions;
5. inspect repository reality for the task area (existing files, migrations, routes, tests, packages);
6. read the domain documents from PROJECT_STATE §50 "Required Reading by Domain";
7. determine the primary specialist (§10);
8. determine the required reviewers (§11);
9. determine the quality gates (§12);
10. only then set the task status to `IN_PROGRESS` in BACKLOG.

If any step fails (dependency not `DONE`, blocking decision, divergence), do not start: report and stop, or return `BLOCKED_DECISION` (§14).

---

# 10. Specialist Routing

| Task nature | Primary | Additional |
|-------------|---------|------------|
| Architecture, ADR, ownership, domain boundaries, new table scope, infrastructure | architect | — |
| Laravel, migrations, models, policies, Actions, jobs, backend tests | backend | architect first when ownership/schema is new |
| React, Inertia, Designer, frontend state | frontend | — |
| Visually significant UI | frontend | ui-reviewer |
| Bug / regression | implementing specialist | qa |
| Security-sensitive change | implementing specialist | security |
| Full-stack | backend, then frontend (sequential) | per triggers |
| Architecture/product documentation, Cursor rules | architect | reviewer |
| Automation/process documentation (`docs/automation/*` workflow content, agent prompts) | orchestrator (coordination content, §2) | reviewer; architect if architecture or decisions are touched |
| Phase Review | qa (validation executor) | architect, security, reviewer; ui-reviewer if any task of the phase changed user-visible UI |
| Final acceptance of every task | — | reviewer |

Rules:

- One primary owner per task. For full-stack work the backend delivers first, then an explicit handoff (the backend handoff report from `backend.md`) goes to the frontend.
- Two agents never edit the same high-conflict files concurrently: `routes/*`, `bootstrap/app.php`, `composer.json`/`composer.lock`, `package.json`/`package-lock.json`, `config/*`, shared layouts and `resources/js/types`, migration ordering, `BACKLOG.md`, `PROJECT_STATE.md`, `DECISIONS.md`. Implementation is sequential. Only read-only reviews may run in parallel.
- Architect review is required when a task adds a domain boundary, changes ownership or table scope, introduces infrastructure, needs an ADR, or changes the publishing runtime or Marketplace execution model (rule `90-agent-workflow.mdc` §28).

## Delegation mechanics

Specialists are Cursor subagents defined in `.cursor/agents/` ([Cursor subagents documentation](https://cursor.com/docs/subagents)). An agent can be requested by name (`/backend ...`) or through the Task tool. A subagent does not see the parent conversation, so the delegation prompt must contain the task ID, the relevant BACKLOG text, the files involved, the constraints and the expected report format.

If a role is not registered as a subagent type in the current session, launch a general-purpose subagent instructed to read `.cursor/agents/<role>.md` in full first and act strictly in that role.

Nesting is limited to one level: the main chat agent and its direct subagents can launch subagents, but a subagent launched by another subagent cannot. Therefore:

- the Orchestrator runs either in the main chat (the agent follows `orchestrator.md`) or as a direct subagent (`/orchestrator`); both can delegate;
- specialists cannot launch further subagents, so the Orchestrator launches every implementation and every review itself;
- delegation requires the Task tool in the current mode (Agent mode).

---

# 11. Review Routing

**Security review is mandatory for** (union of rule `90-agent-workflow.mdc` §25, rule `70-security.mdc` §127 and `security.md` triggers): new or changed public endpoints, tenancy/access, permissions and the permission system, secrets, integrations, outbound HTTP, uploads, Forms/Submissions, publishing, domains, Marketplace/developer runtime (Developer Platform), custom HTML/script/RichText, exports/personal data.

**UI Reviewer is mandatory for:** Dashboard, a significant new screen, Designer, automotive UI, public Blocks, publishing UI, Team UI, Developer Platform, Marketplace.

**QA is mandatory for:** bug fixes, critical workflows, cross-domain workflows, tenancy, permissions, Forms, integrations, publishing, phase validation.

**Final Reviewer is mandatory for every task** before `DONE`.

The triggers above are the minimum that can never be skipped. On top of them, the default flow of MASTER_PLAN §131 applies to implementation tasks:

- backend task: backend → qa → security if relevant → reviewer;
- frontend task: frontend → ui-reviewer → qa → reviewer;
- architecture-sensitive task: architect → specialist → security/qa → reviewer.

The Orchestrator may omit a default (non-trigger) QA or UI review only when the change has no behavior or visible UI impact (for example tooling or documentation), and must state the reason in the task report.

Order:

```text
primary implementation
→ quality gates PASS
→ specialist reviews (architect / ui-reviewer / qa / security as triggered)
→ final Reviewer
```

Independence:

- every review runs in its own subagent context, separate from the implementer;
- a reviewer's `PASS` exists only if that reviewer actually produced it;
- the Orchestrator never writes a review verdict on a reviewer's behalf;
- if a required review cannot be run, the task is not `DONE`: report it and leave the task `IN_PROGRESS`/`PARTIAL`.

Verdicts are the ones each agent file defines:

| Role | Verdicts | Counts as passed |
|------|----------|------------------|
| reviewer | `PASS` / `REJECTED` / `BLOCKED_DECISION` / `BLOCKED_EXTERNAL` | `PASS` |
| qa | `PASS` / `REJECTED` / `BLOCKED_EXTERNAL` | `PASS` |
| security | `PASS` / `REJECTED` / `BLOCKED_DECISION` | `PASS` |
| ui-reviewer | `PASS` / `PASS_WITH_MINOR_NOTES` / `REJECTED` | `PASS`, or `PASS_WITH_MINOR_NOTES` with the notes recorded in the Result |
| architect | `architect.md` §70 structured output, §71 `REJECTED`, §72 `Status: BLOCKED_DECISION` | the §70 output with no blocking open decision and `ADR required: NO`, or the required ADR already exists and is accepted |

Mapping to the task:

- `REJECTED` → back to the primary specialist, fix loop (§13);
- `BLOCKED_DECISION` → task `BLOCKED_DECISION` with the §14 record;
- `BLOCKED_EXTERNAL` → finish all safe local work, then task `BLOCKED_EXTERNAL` with the §14 record.

---

# 12. Quality Routing

Canonical commands come from [QUALITY_COMMANDS.md](QUALITY_COMMANDS.md). Gates run **sequentially, never in parallel**. `npm run test:e2e` always runs after `composer quality`, never concurrently.

| Task type | Required gates |
|-----------|----------------|
| Backend | `composer test` → `composer analyse` → `composer format:check` (a route/controller change regenerates Wayfinder TypeScript: treat it as full-stack) |
| Frontend without browser-facing change | `npm run check` → `npm run build` |
| Frontend with user-facing UI | `npm run check` → `npm run build` → `npm run test:e2e` (+ browser QA per rule `80-browser-qa.mdc`) |
| Full-stack | `composer quality` → `npm run test:e2e` if a browser/user flow is affected |
| Docs / rules / automation / ADR | only the relevant checks (links, referenced files exist, no contradiction with rules/docs); heavy gates only if code, config or tooling changed or the task demands them |
| Phase Review | `composer quality` → `npm run test:e2e` + architecture review + security review + QA review + final Reviewer + repository/documentation consistency check |

Focused checks (a single PHPUnit test/filter, one Playwright spec) are used during the fix loop. The task's final gates are always the canonical commands above.

This table refines the example mapping in QUALITY_COMMANDS §40: a frontend change that affects nothing browser-facing (types, build config, non-rendered code) does not need `npm run test:e2e`; any user-facing UI change does.

Check results use only `PASS`, `FAIL`, `NOT_AVAILABLE_YET`, `NOT_APPLICABLE`, `BLOCKED_EXTERNAL`. A check that did not run did not pass.

---

# 13. Fix Loop and STALLED

```text
implementation
→ focused checks
→ failure? → diagnose → fix → rerun
→ canonical quality gates
→ failure? → fix loop
→ required specialist reviews
→ REJECTED? → back to the primary specialist → fix loop
→ final Reviewer
→ PASS
→ DONE
```

An ordinary fixable failure (test, type error, formatting, lint, broken selector) is fixed, not reported as a stop.

Failures are fixed at the root. Never delete or weaken a test, skip a gate, disable a check or mock away the behavior under test to get green.

A failure caused by a pre-existing unrelated issue follows QUALITY_COMMANDS §29: verify it is pre-existing, never report the gate as `PASS`, record it.

**STALLED:** when the same failure repeats for **three consecutive fix attempts without new information or progress**, stop the loop and report:

```text
STALLED
Task:
Root problem:
Already tried:
Last error:
Suspected missing context / decision:
Safe next step:
Persistent status: IN_PROGRESS | PARTIAL | BLOCKED_DECISION | BLOCKED_EXTERNAL
```

---

# 14. Stop Conditions and Blockers

## BLOCKED_DECISION

Use when the task needs a decision agents may not make: an `ADR_REQUIRED` entry, material conflict between architecture docs, undefined product semantics, unclear data ownership, a major package/infrastructure choice, or an unresolved security model (MASTER_PLAN §133).

Set the task to `BLOCKED_DECISION` and record in its BACKLOG entry:

```text
Decision required:
Why:
Known options:
Tradeoffs:
Related DECISIONS/ADR:
Blocked task:
Safe work completed:
```

Never pick an irreversible option on the owner's behalf. The Architect may draft an ADR with options; accepting it is an owner decision. After the decision is resolved, the task is resumed (§7).

## BLOCKED_EXTERNAL

Use for real DNS, production secrets, provider accounts, external approvals, manual external configuration. Complete all safe local work first (mocked tests, local implementation), then block with the exact external action needed.

## Other stop conditions

- human approval required (§15);
- STALLED (§13);
- documents vs repository divergence that affects the task (§3);
- working tree changes of unknown origin (§16);
- a required review cannot be executed (§11).

---

# 15. Human Approval Boundaries

The Orchestrator stops and asks the owner before:

- force push — **forbidden always**, approval does not unlock it;
- production deployment;
- production DNS changes;
- using production credentials;
- live billing/payment configuration;
- irreversible production migrations;
- deleting production or customer data;
- a major architecture decision without an ADR;
- an unresolved product decision;
- external actions with real accounts.

**Commit and push are not allowed by default.** They are allowed only when the owner explicitly authorizes them for the current task. Without that authorization, the Orchestrator prepares the changes, reports what would be committed, and stops.

---

# 16. Git Safety

- Run `git status` before work.
- If the tree is dirty, attribute every change: to the current task, to a previous task that is `DONE` but not yet committed (identified from BACKLOG/PROJECT_STATE), or unknown. Unknown changes → stop and ask. Changes of a previous uncommitted task are reported, left untouched and kept out of the new task's commit.
- Never run a broad `git reset`, `git checkout -- .`, `git clean` or revert; never delete work you did not create.
- One coherent task per commit; English technical commit message (rule `90-agent-workflow.mdc` §40).
- Before any authorized commit, review the staged diff and make sure none of these are staged: `.env`, `.env.e2e`, `database/e2e.sqlite`, `test-results/`, `playwright-report/`, `blob-report/`, `storage/logs/*`, `vendor/`, `node_modules/`, `public/build/`, `.idea/`, `playwright/.auth/`.
- Scan the staged diff for tokens, passwords, private keys and real credentials.
- Force push is forbidden.

**Generated files.** Wayfinder output (`resources/js/actions/`, `resources/js/routes/`, `resources/js/wayfinder/`) is intentionally not in Git. `composer quality` regenerates it before type checking. Never commit it; never hand-edit generated files.

---

# 17. State Updates

Only after the final Reviewer `PASS` and all required gates `PASS`:

**BACKLOG.md**

- task status → `DONE`;
- add a `### Result` section following the existing convention (what was delivered, notable decisions, checks with real results);
- update §3 "Current Backlog Position" and §12 "Current Immediate Sequence" where they list the task.

**PROJECT_STATE.md**

- only facts that really changed (new capability, tool availability, phase status, next task, resolved/new known gaps);
- not a changelog.

**DECISIONS.md**

- only for a new stable decision, a superseded decision, or a newly discovered unresolved decision.

For `PARTIAL` / `BLOCKED_*`, record the reason and the remaining work in the task's BACKLOG entry and keep PROJECT_STATE truthful.

---

# 18. Phase Boundary

- A phase ends only through its Phase Review task. Phase 0 ends only through `P0-027 — Phase 0 Validation`.
- No Phase 1 task may start until `P0-027` is `DONE` with a `PASS` review. The same holds for every later phase and its Phase Review task.
- The Phase Review task is executed only in SINGLE TASK MODE on an explicit owner request. CONTINUOUS MODE stops before it.
- After a Phase Review task is `DONE`, the Orchestrator performs the phase transition of §6 (finished phase `COMPLETED`, next phase `IN_PROGRESS` in PROJECT_STATE §54, gate result recorded) and stops. It does not start the next phase's first task in the same run.
- A phase is marked `COMPLETED` only when the conditions of MASTER_PLAN §3 hold and the Phase Review reviews passed. If the Phase Review ends `PARTIAL` or `BLOCKED_*`, the phase stays `IN_PROGRESS`.

---

# 19. Reports

Task report (end of every task):

```text
Task: <ID — title>
Mode: SINGLE TASK | CONTINUOUS
Status: DONE | PARTIAL | BLOCKED_DECISION | BLOCKED_EXTERNAL | IN_PROGRESS (STALLED)
Primary: <role>
Reviews: <role>: <verdict> ... (omitted default reviews: <role — reason>)
Behavior implemented:
Changed files:
Architecture decisions created: none | <D-xxx / ADR>
Checks:
- composer test / analyse / format:check / quality:
- npm run check / build:
- npm run test:e2e:
Open issues / limitations:
Commit/push: not authorized | done (<hash>)
Next ready task: <ID — title> | none (<reason>)
```

Read-only status report (command 3):

```text
Current phase:
Phase gate status:
Working tree:
Current task (IN_PROGRESS/PARTIAL): <ID> | none
Next ready task: <ID — title>
Dependencies: <ID>: <status> ...
Blocking decisions: none | <D-xxx ...>
Primary specialist:
Required reviewers:
Required gates:
Divergences found: none | <list>
Later-phase task selected: NO
```

---

# 20. Orchestrator Self-Limits

The Orchestrator must not:

- declare a check `PASS` without running it;
- declare a Reviewer (or any reviewer) `PASS` itself;
- bypass a required Security review;
- make a major architecture decision;
- start a future phase;
- hide a failure or a divergence;
- delete or weaken a test to get green;
- disable or skip a quality gate;
- perform a production action without approval;
- commit or push without explicit authorization for the current task.

---

# 21. Owner Commands

Copy one of these into Cursor chat (Agent mode). To authorize a commit/push for the task being executed, say so explicitly in the same message, for example «Разрешаю commit и push для этой задачи»; without it nothing is committed.

Command 1 — one task:

```text
Продолжай Landflow: выполни следующую ready task по Autonomous Workflow в SINGLE TASK MODE.
```

Command 2 — continuous:

```text
Продолжай Landflow по Autonomous Workflow в CONTINUOUS MODE до следующего blocker, human approval или Phase Gate.
```

Command 3 — status only:

```text
Покажи текущее состояние Landflow, следующую ready task, зависимости и required reviewers. Ничего не изменяй.
```

Command 4 — review only:

```text
Проведи только review текущей задачи Landflow по Definition of Done, ничего не реализуй.
```
