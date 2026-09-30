# Landflow — Master Engineering Plan

**Document:** `docs/automation/MASTER_PLAN.md`  
**Status:** Primary execution roadmap  
**Purpose:** Convert approved product and architecture decisions into a staged engineering plan for autonomous implementation by Cursor and specialist agents.

---

# 1. Master Plan Goal

This document defines the order in which Landflow should be built.

It is not a task backlog.

It defines:

- engineering phases;
- dependencies;
- architecture gates;
- automation milestones;
- testing requirements;
- when autonomous implementation is allowed;
- when major product capabilities are introduced;
- when a phase is considered complete.

`BACKLOG.md` will later break this plan into executable tasks.

---

# 2. Execution Philosophy

Landflow should be built incrementally.

Preferred pattern:

Foundation  
→ one small working vertical slice  
→ quality gates  
→ next capability  
→ quality gates  
→ repeat.

Do not build all major systems in parallel.

The application should remain coherent and testable after each phase.

---

# 3. Phase Transition Rule

A phase may move to `COMPLETED` only when:

- required scope is implemented;
- applicable Definition of Done checks pass;
- architecture docs remain consistent;
- relevant tests exist;
- PROJECT_STATE is updated;
- no critical `PARTIAL` task remains;
- unresolved major architecture issues are explicitly documented.

---

# 4. Task Execution Rule

Every implementation task should follow:

```text
Read current task
→ Read relevant architecture docs
→ Inspect current repository state
→ Implement
→ Run applicable checks
→ Fix failures
→ Review
→ Update docs/state
→ Mark task
```

Do not skip repository inspection because architecture describes a target state.

---

# 5. Autonomous Development Rule

Autonomous Cursor execution is allowed only within approved architecture and task boundaries.

Agents may make routine implementation decisions.

Agents may not redefine:

- Workspace tenancy;
- Global Catalog ownership;
- Site commercial data ownership;
- publishing semantics;
- secret boundaries;
- Marketplace execution model;
- package policy;
- major billing model.

Use `BLOCKED_DECISION` when necessary.

---

# 6. Master Phase Overview

```text
Phase 0  Foundation / Automation
Phase 1  Core Platform
Phase 2  Designer Foundation
Phase 3  Automotive Foundation
Phase 4  Forms & Interactive Components
Phase 5  Publishing
Phase 6  Integrations & Analytics
Phase 7  Paid Site Features
Phase 8  Team / Collaboration
Phase 9  Developer Platform
Phase 10 Marketplace
Phase 11 External Automotive Data Sources
```

These phases are intentionally ordered by dependency.

---

# 7. Phase 0 — Foundation / Automation

## Objective

Prepare the repository and Cursor environment so future work can be executed autonomously with reliable architectural and quality constraints.

## Current status

`IN_PROGRESS`

---

# 8. Phase 0 — Documentation Foundation

Required product docs:

- `PRODUCT.md`
- `WEBFLOW_TO_LANDFLOW.md`

Required architecture docs:

- `ARCHITECTURE.md`
- `DATABASE.md`
- `TENANCY.md`
- `PERMISSIONS.md`
- `AUTOMOTIVE_DATA.md`
- `BLOCK_SYSTEM.md`
- `FORMS_AND_INTEGRATIONS.md`
- `PUBLISHING.md`
- `SECURITY.md`

Required automation docs:

- `DEFINITION_OF_DONE.md`
- `PROJECT_STATE.md`
- `MASTER_PLAN.md`
- `BACKLOG.md`
- `DECISIONS.md`

---

# 9. Phase 0 — Cursor Rule System

Create:

```text
.cursor/rules/
├── 00-project-core.mdc
├── 10-architecture.mdc
├── 20-laravel.mdc
├── 30-react-inertia.mdc
├── 40-ui-shadcn.mdc
├── 50-database.mdc
├── 60-testing.mdc
├── 70-security.mdc
├── 80-browser-qa.mdc
└── 90-agent-workflow.mdc
```

Rules should be concise and operational.

Do not paste full architecture documents into every rule.

Rules should tell agents:

- what to read;
- what is mandatory;
- what is forbidden;
- how to verify work.

---

# 10. Phase 0 — Specialist Agents

Create:

```text
.cursor/agents/
├── architect.md
├── backend.md
├── frontend.md
├── ui-reviewer.md
├── qa.md
├── security.md
└── reviewer.md
```

Potential responsibilities:

## Architect

- architecture consistency;
- ADRs;
- dependency boundaries;
- schema review.

## Backend

- Laravel;
- domain services;
- migrations;
- policies;
- queues.

## Frontend

- React;
- Inertia;
- shadcn;
- Designer UI.

## UI Reviewer

- visual quality;
- layout;
- responsive;
- reference screenshots.

## QA

- PHPUnit;
- Playwright;
- regression testing;
- browser flows.

## Security

- tenancy;
- auth;
- public endpoints;
- secrets;
- XSS/SSRF/upload risk.

## Reviewer

- independent final review;
- Definition of Done verification.

---

# 11. Phase 0 — Standard Check Commands

Standardize commands for:

- PHP tests;
- static analysis;
- formatting;
- frontend check;
- production build;
- browser tests.

Avoid multiple competing commands for the same purpose.

Future autonomous agents must know one canonical command per quality gate.

---

# 12. Phase 0 — Playwright

Add Playwright after core rule/agent documents exist.

Purpose:

- user-flow verification;
- browser console checks;
- screenshots;
- responsive checks;
- published Site verification later.

Do not add Laravel Dusk.

---

# 13. Phase 0 — Browser QA Baseline

Create browser QA conventions:

- desktop viewport;
- tablet viewport;
- mobile viewport;
- screenshot location;
- console error policy;
- critical flow definitions.

Initial critical surfaces:

- login;
- dashboard;
- settings.

Later extend as features appear.

---

# 14. Phase 0 — CI

CI should eventually run:

- Composer install;
- npm install;
- PHPUnit;
- Larastan;
- Pint check;
- frontend check;
- production build;
- Playwright where practical.

CI must not require production credentials for core tests.

---

# 15. Phase 0 — Autonomous Task Loop

Establish task loop:

```text
Read next BACKLOG item
→ assign agent
→ implement
→ run checks
→ fix loop
→ security/reviewer pass
→ update PROJECT_STATE
→ mark task DONE
→ next item
```

Task advancement must depend on quality gates.

---

# 16. Phase 0 — Decision Management

Create:

`DECISIONS.md`

Use for lightweight approved choices.

Major choices use ADRs.

Initial known approved decisions include:

- npm only;
- PHPUnit;
- Playwright (established in P0-023);
- no Pest;
- no Dusk;
- Workspace is tenant;
- database session/cache/queue initially;
- Redis only when justified.

---

# 17. Phase 0 — Completion Gate

Phase 0 completes when:

- all required architecture/product docs exist;
- MASTER_PLAN exists;
- BACKLOG exists;
- DECISIONS exists;
- Cursor rules exist;
- core agents exist;
- canonical check commands exist;
- Playwright/browser QA exists;
- CI exists;
- autonomous task flow documented and usable.

Only then should Phase 1 become `IN_PROGRESS`.

---

# 18. Phase 1 — Core Platform

## Objective

Create the first working Landflow customer platform:

```text
Register/Login
→ Workspace
→ Dashboard
→ Create Site
→ Choose Template
→ Site exists
```

No advanced Designer yet.

---

# 19. Phase 1 — Identity Cleanup

Tasks:

- verify Fortify configuration;
- implement `MustVerifyEmail` if not already;
- confirm auth flows;
- remove starter-kit 2FA / TOTP / passkey features (D-095);
- test email verification behavior;
- add Yandex OAuth sign-in with a required email after the Workspace foundation (D-095; requires D-096, D-097).

Do not rebuild authentication: Fortify remains the email/password engine.

---

# 20. Phase 1 — Workspace Domain

Implement:

- Workspace model;
- Workspace membership;
- personal/default Workspace creation;
- Workspace switcher;
- active Workspace context;
- basic Owner/Admin/Designer/Content Editor role foundation.

Test cross-Workspace isolation immediately.

---

# 21. Phase 1 — Entitlement Foundation

Implement basic capability service.

Initial entitlements may include:

- max_sites;
- max_members;
- custom_domain;
- remove_branding;
- version_history;
- workspace_vehicle_library;
- developer_access.

Do not build full billing provider yet.

Use local/configured Plan definitions for development.

---

# 22. Phase 1 — Site Domain

Implement:

- Site;
- Site folders;
- Site status;
- Site dashboard cards;
- create Site;
- archive/delete safety;
- Site access policies.

---

# 23. Phase 1 — Templates Foundation

Implement minimal official Template model.

Initial capability:

- Blank Site;
- one or more official starter Templates.

Template instantiation creates Site-owned structure.

Do not build Marketplace Template purchasing yet.

---

# 24. Phase 1 — Dashboard

Build Workspace dashboard inspired by professional builders.

Core UX:

- Workspace switcher;
- Sites grid;
- search;
- create Site;
- Site card;
- Site status;
- folders later within same phase if simple.

Use shadcn.

---

# 25. Phase 1 — Permissions

Implement enough permission infrastructure for:

- Workspace management;
- Site access;
- create Site;
- edit Site;
- publish permission placeholder.

Do not implement enterprise custom roles yet.

---

# 26. Phase 1 — Completion Gate

Required working browser flow:

```text
Register/Login
→ Workspace created
→ Create Site
→ select Template
→ Site appears in Dashboard
→ another Workspace cannot access it
```

Required:

- PHPUnit;
- static analysis;
- TypeScript;
- build;
- Playwright flow;
- tenancy tests.

---

# 27. Phase 2 — Designer Foundation

## Objective

Create the first useful visual Site editor.

Core flow:

```text
Open Site
→ Designer
→ select Page
→ add/reorder Block
→ edit properties
→ autosave Draft
→ Preview
```

---

# 28. Phase 2 — Page Model

Implement:

- Pages;
- Home Page;
- slugs;
- ordering;
- page status;
- basic SEO placeholder.

---

# 29. Phase 2 — Block Runtime

Implement:

- Block Definition;
- Block Version;
- Block Schema;
- Block Instance;
- pinned version;
- validation.

Start with official Blocks only.

---

# 30. Phase 2 — Initial Field Types

Implement first useful field types:

- text;
- textarea;
- boolean;
- select;
- image;
- group;
- repeater.

Do not implement every future field type immediately.

---

# 31. Phase 2 — Designer Shell

Build:

- top bar;
- left panel;
- central canvas;
- right Properties Panel.

Initial left panels:

- Pages;
- Blocks;
- Navigator;
- Assets.

---

# 32. Phase 2 — Navigator

Support:

- Block selection;
- reorder;
- hide/show;
- duplicate;
- delete.

Navigator should represent Block structure, not every DOM element.

---

# 33. Phase 2 — Properties Panel

Generate controls from Block Schema.

No manually coded settings screen per Block.

Support:

- content fields;
- design fields;
- group;
- repeater;
- conditional field basics.

---

# 34. Phase 2 — Design Tokens

Implement Site-wide basic tokens:

- colors;
- typography;
- border radius;
- container width;
- buttons.

Blocks use tokens by default.

---

# 35. Phase 2 — Assets

Implement basic:

- Site upload;
- Workspace assets if ready;
- image chooser;
- media references.

Do not overbuild media DAM.

---

# 36. Phase 2 — Action System Foundation

Implement safe Actions:

- open_url;
- open_page;
- scroll_to;
- phone;
- email;
- open_popup placeholder;
- submit_form placeholder.

Popup/Form implementation comes later.

---

# 37. Phase 2 — Autosave

Designer changes persist Draft safely.

Test:

- edit;
- reload;
- state remains.

Autosave must not affect production.

---

# 38. Phase 2 — Preview

Provide Draft Preview.

At this phase, Preview may use authenticated route/runtime.

Public production publishing comes in Phase 5.

---

# 39. Phase 2 — Initial Official Blocks

Recommended:

- Header;
- Hero;
- Benefits;
- Text/Content;
- CTA;
- Contacts;
- Footer.

Vehicle Blocks arrive after automotive foundation.

---

# 40. Phase 2 — Completion Gate

Browser flow:

```text
Open Site
→ add Hero
→ edit title
→ add/reorder Benefits
→ reload
→ changes persist
→ Preview
```

Checks:

- Schema validation;
- Block version pinning;
- no console errors;
- responsive basic QA;
- screenshots.

---

# 41. Phase 3 — Automotive Foundation

## Objective

Make automotive data a first-class Landflow capability.

Core flow:

```text
Super Admin Catalog
→ Customer selects vehicle
→ Import to Site
→ Set price
→ Vehicle Block renders structured data
```

---

# 42. Phase 3 — Global Catalog Core

Implement:

- Make;
- Model;
- Series optional;
- Generation;
- Modification;
- Trim.

Start with manual/admin-managed data.

External source import comes much later.

---

# 43. Phase 3 — Characteristics

Implement:

- characteristic dictionary;
- categories;
- values;
- inheritance/resolution.

Start with realistic automotive examples.

---

# 44. Phase 3 — Equipment

Implement:

- option dictionary;
- categories;
- Trim options.

Support structured rendering.

---

# 45. Phase 3 — Colors

Implement:

- automotive colors;
- multiple swatches;
- two-tone examples;
- Trim availability.

---

# 46. Phase 3 — Images

Implement:

- automotive images;
- color relation;
- angle/view metadata;
- transparency flag;
- ordering.

Media fallback:

Site
→ Workspace
→ Global.

---

# 47. Phase 3 — Catalog Admin

Create Super Admin catalog management.

Required:

- hierarchy editing;
- status;
- images;
- colors;
- options;
- characteristics.

Customers remain read-only.

---

# 48. Phase 3 — Customer Import

Customer flow:

Global Catalog
→ select Trim
→ import to Site or Workspace vehicle context.

Preserve source references.

---

# 49. Phase 3 — Site Vehicle

Implement:

- Site Vehicle;
- active/inactive;
- custom content;
- selected colors;
- order.

---

# 50. Phase 3 — Site Offer

Implement:

- RRP;
- price;
- availability;
- badge;
- benefits.

Use correct Money strategy.

---

# 51. Phase 3 — Benefits

Implement extensible benefits rather than only fixed columns.

Start with:

- direct discount;
- trade-in;
- credit;
- custom.

---

# 52. Phase 3 — Automotive Bindings

Expose safe semantic bindings to Blocks.

Examples:

- vehicle.make.name;
- vehicle.model.name;
- vehicle.trim.name;
- offer.price;
- primary_image;
- colors;
- benefits.

---

# 53. Phase 3 — Vehicle Blocks

Initial official automotive Blocks:

- Vehicle Card;
- Vehicle Grid;
- Price/Offer;
- Characteristics;
- Equipment;
- Vehicle Gallery.

---

# 54. Phase 3 — Completion Gate

Browser flow:

```text
Admin creates catalog vehicle
→ customer imports vehicle
→ sets Site price
→ adds Vehicle Grid
→ Vehicle Card shows model/price/image
→ changes color
→ correct image appears
```

Tests:

- customer cannot edit Global Catalog;
- price remains Site-specific;
- two-tone colors;
- image fallback;
- tenant isolation.

---

# 55. Phase 4 — Forms & Interactive Components

## Objective

Enable real lead-generation Sites.

Core flow:

```text
Vehicle CTA
→ Popup
→ Form
→ validated Submission
```

External CRM delivery may remain minimal until Phase 6.

---

# 56. Phase 4 — Popup Engine

Implement Site-level reusable Popups.

Support:

- open/close;
- overlay;
- size;
- responsive;
- reusable content;
- Action System integration.

---

# 57. Phase 4 — Form Engine

Implement:

- Form;
- fields;
- validation;
- consent;
- Site ownership;
- public-safe identifier.

---

# 58. Phase 4 — Context Passing

Action → Popup → Form should preserve:

- Site Vehicle;
- Site Offer;
- Trim;
- selected Color;
- source Page;
- source Block;
- UTMs.

---

# 59. Phase 4 — Submission Persistence

Implement:

- Submission;
- normalized phone;
- trusted context;
- status.

Persist before external delivery.

---

# 60. Phase 4 — Anti-Spam

Implement:

- honeypot;
- IP rate limit;
- phone rate limit;
- duplicate detection;
- blacklist.

---

# 61. Phase 4 — SmartCaptcha

Implement Yandex SmartCaptcha adapter using current official documentation.

Keep provider details outside Blocks.

---

# 62. Phase 4 — Carousel Engine

Implement platform Carousel abstraction.

Use one approved frontend implementation underneath.

Schema remains vendor-neutral.

---

# 63. Phase 4 — Gallery / Lightbox

Implement:

- media gallery;
- carousel mode;
- Lightbox.

Do not confuse with business Popup.

---

# 64. Phase 4 — Completion Gate

Browser flow:

```text
Vehicle Card
→ click Get Offer
→ Popup opens
→ Form includes vehicle context
→ submit
→ Submission persisted
→ anti-spam enforced
```

Also verify:

- reusable Popup;
- same Popup from multiple vehicles;
- correct context each time;
- Carousel responsive;
- Lightbox keyboard behavior.

---

# 65. Phase 5 — Publishing

## Objective

Make Sites publicly deployable and stable.

Core flow:

```text
Draft
→ Preview
→ Publish
→ *.landflow.me
```

---

# 66. Phase 5 — Publishing ADR

Before runtime implementation, create ADR defining:

- public rendering engine;
- snapshot strategy;
- asset versioning;
- cache strategy;
- activation model.

This is a required gate.

---

# 67. Phase 5 — Site Version

Implement:

- Published Version;
- Publication record;
- current published pointer.

---

# 68. Phase 5 — Validation

Implement Publish validation for:

- Pages;
- Blocks;
- Forms;
- Popups;
- Site Vehicles;
- assets;
- SEO basics.

---

# 69. Phase 5 — Atomic Publish

Build/prepare version first.

Then activate.

On failure:

previous production remains live.

---

# 70. Phase 5 — Landflow Subdomain

Implement:

`site-name.landflow.me`

Requirements:

- uniqueness;
- reserved names;
- hostname routing;
- HTTPS infrastructure plan;
- noindex staging rules where appropriate.

---

# 71. Phase 5 — SEO Basics

Implement:

- title;
- meta description;
- canonical;
- robots;
- OG;
- sitemap.

---

# 72. Phase 5 — Version History

Implement basic publication history.

Initial restore:

historical version
→ restore to Draft
→ review
→ Publish.

---

# 73. Phase 5 — Completion Gate

Browser flow:

```text
Edit Draft
→ Preview new value
→ production still old
→ Publish
→ production updates
→ edit Draft again
→ production remains unchanged
```

Failure test:

Publish fails
→ old production remains live.

---

# 74. Phase 6 — Integrations & Analytics

## Objective

Deliver captured leads reliably to external systems and add analytics.

---

# 75. Phase 6 — Workspace Integration Profiles

Implement:

- reusable profile;
- encrypted secrets;
- masking;
- provider type;
- Base URL/auth.

---

# 76. Phase 6 — Site Integration Bindings

Implement:

- Site → Profile;
- Site overrides;
- `site_id`;
- `dealer_id`;
- `source_id`.

---

# 77. Phase 6 — Form Routes

Implement one-to-many routing.

Examples:

- Email;
- CRM;
- Webhook.

---

# 78. Phase 6 — Field Mapping

Implement mappings from:

- Form Fields;
- automotive context;
- Site settings;
- Site override;
- constants.

---

# 79. Phase 6 — Delivery Queue

Implement:

- Delivery records;
- queued jobs;
- retries;
- safe logs;
- partial delivery state.

---

# 80. Phase 6 — Initial Adapters

Implement:

- Email;
- Webhook;
- Custom JSON API.

Dedicated CRM adapters follow real customer requirements.

---

# 81. Phase 6 — Integration Diagnostics

UI:

- delivery status;
- safe error;
- retry;
- Test Connection.

---

# 82. Phase 6 — Yandex Metrica

Implement:

- Site Counter ID;
- Webvisor setting;
- semantic Landflow event forwarding.

Standard events:

- page.view;
- vehicle.view;
- popup.open;
- form.submit;
- form.success;
- phone.click;
- cta.click.

---

# 83. Phase 6 — Completion Gate

Flow:

```text
Form submitted
→ Submission saved
→ Email delivered
→ Webhook delivered
→ CRM/API failure
→ retry
→ delivery succeeds
```

Secrets must remain server-side.

---

# 84. Phase 7 — Paid Site Features

## Objective

Enable commercially useful paid Site capabilities.

---

# 85. Phase 7 — Custom Domains

Implement:

- add domain;
- DNS verification;
- status;
- SSL;
- primary domain;
- redirects.

Requires entitlement + permission.

---

# 86. Phase 7 — Branding

Implement:

- Landflow branding on Free;
- `remove_branding` entitlement.

Do not hardcode branding per Template.

---

# 87. Phase 7 — Advanced SEO

Potential:

- structured automotive SEO templates;
- advanced robots;
- richer OG;
- dynamic vehicle SEO.

---

# 88. Phase 7 — Plan Enforcement

Enforce:

- Site count;
- domains;
- branding;
- advanced features.

Billing provider may still be mocked/internal until selected.

---

# 89. Phase 7 — Billing ADR / Provider

Before real payments/subscriptions:

- choose billing provider;
- create ADR;
- define webhook security;
- entitlement lifecycle.

---

# 90. Phase 7 — Completion Gate

Verify:

- Free limitations;
- Pro capabilities;
- entitlement changes;
- custom domain access;
- branding behavior.

---

# 91. Phase 8 — Team / Collaboration

## Objective

Enable agencies and dealer groups.

---

# 92. Phase 8 — Invites

Implement:

- invite;
- accept;
- pending membership;
- remove;
- suspend.

---

# 93. Phase 8 — Site-Level Access

Implement:

- all Sites;
- selected Sites;
- Site-specific roles.

---

# 94. Phase 8 — Granular Roles

Enable:

- Designer;
- Content Editor;
- Pricing Manager;
- Lead Manager;
- Integrations Manager;
- Publisher;
- custom roles later.

---

# 95. Phase 8 — Workspace Vehicle Library

Implement reusable customer vehicle content/media.

---

# 96. Phase 8 — Site-to-Site Vehicle Copy

Support:

- vehicle;
- colors;
- images;
- description;
- Site Offer;
- benefits.

Conflict modes:

- Skip;
- Update;
- Replace selected fields.

Default remains copy.

---

# 97. Phase 8 — Shared Assets

Enhance Workspace Assets/library reuse.

---

# 98. Phase 8 — Richer Versions

Team may receive:

- more version history;
- actor;
- notes;
- restore tools.

---

# 99. Phase 8 — Completion Gate

Flow:

```text
Owner invites Designer
→ grants Site A only
→ Designer edits design
→ cannot edit price
→ Pricing Manager edits price
→ Publisher publishes
```

Verify permissions end-to-end.

---

# 100. Phase 9 — Developer Platform

## Objective

Let approved developers create reusable Landflow Blocks/Templates.

---

# 101. Phase 9 — Developer Profile

Implement:

- Developer account/profile;
- status;
- permissions.

---

# 102. Phase 9 — Block Authoring

Build:

- Block editor;
- Schema editor;
- capabilities;
- bindings;
- preview;
- versioning.

---

# 103. Phase 9 — AI Schema Assistant

Optional after deterministic authoring works.

Flow:

Markup
→ AI proposes Schema
→ developer reviews
→ save deterministic Schema.

Do not make AI mandatory.

---

# 104. Phase 9 — Template Authoring

Developers compose approved Blocks into Templates.

---

# 105. Phase 9 — Review Workflow

States:

- Draft;
- Testing;
- Submitted;
- In Review;
- Changes Requested;
- Approved.

---

# 106. Phase 9 — Security Review

Before third-party content is enabled publicly:

- sandbox/runtime review;
- dependency policy;
- script policy;
- data access review.

---

# 107. Phase 9 — Completion Gate

Developer flow:

```text
Create Block
→ define Schema
→ preview with sample Vehicle
→ submit
→ moderator reviews
→ approve
→ Block becomes available
```

Existing Sites remain version-pinned.

---

# 108. Phase 10 — Marketplace

## Objective

Distribute approved Blocks/Templates.

---

# 109. Phase 10 — Listings

Implement:

- title;
- description;
- category;
- screenshots;
- preview;
- version;
- compatibility;
- author.

---

# 110. Phase 10 — Free Items

Start with Free Marketplace items if that reduces billing complexity.

---

# 111. Phase 10 — Paid Items

Later add:

- orders;
- licenses;
- entitlement;
- payments;
- earnings;
- payouts.

Requires billing/legal decisions.

---

# 112. Phase 10 — Licensing

License scope must be explicit:

- Workspace;
- Site;
- account;

depending on product decision.

Do not infer licensing silently.

---

# 113. Phase 10 — Ratings / Reviews

Customer reviews may be added after basic Marketplace stability.

Internal moderation review remains separate.

---

# 114. Phase 10 — Completion Gate

Verify:

- listing discoverability;
- install/use;
- version pinning;
- license enforcement;
- security;
- Site stability after Marketplace item update.

---

# 115. Phase 11 — External Automotive Data Sources

## Objective

Import external vehicle/catalog/inventory data safely.

---

# 116. Phase 11 — Source Adapter

Architecture:

```text
External Source
→ Adapter
→ Normalize
→ Validate
→ Landflow automotive model
```

External provider schema must never become core internal schema.

---

# 117. Phase 11 — Catalog Import

Potential sources:

- API;
- XML;
- CSV;
- dealer feed;
- external catalog provider.

---

# 118. Phase 11 — Inventory / VIN Layer

If required, introduce stock-unit model:

- VIN;
- year;
- mileage;
- exact images;
- stock price;
- availability.

Do not pollute Global canonical Trim.

---

# 119. Phase 11 — Mapping UI

Future customer/admin mapping may normalize:

- Make;
- Model;
- Trim;
- characteristics;
- options;
- colors.

---

# 120. Phase 11 — Completion Gate

Verify:

- source isolation;
- normalization;
- duplicate handling;
- no master catalog corruption;
- import logs;
- retry/failure behavior.

---

# 121. Cross-Phase Architecture Gates

Before any phase implementation, verify:

- ownership/scope;
- permissions;
- entitlement;
- security boundary;
- test plan;
- migration order.

---

# 122. Database Gate

Before creating new tables:

- check `DATABASE.md`;
- classify Global/Workspace/Site;
- define delete behavior;
- define indexes;
- define source/copy/reference semantics.

---

# 123. Security Gate

Before exposing new public endpoint or extension:

- validate input;
- authorize where applicable;
- rate limit;
- sanitize;
- protect secrets;
- test tenant isolation.

---

# 124. UI Gate

Before calling UI feature complete:

- browser tested;
- empty state;
- error state;
- responsive;
- console clean;
- screenshot reviewed.

---

# 125. Integration Gate

Before enabling provider:

- current official docs verified;
- credentials server-side;
- timeout/retry defined;
- safe logging;
- mocked tests;
- real test where possible.

---

# 126. Publishing Gate

Before enabling public production:

- ADR approved;
- atomic activation;
- Draft/Published separation;
- secret exclusion;
- cache strategy;
- rollback path.

---

# 127. Marketplace Gate

Before public third-party distribution:

- developer permissions;
- moderation;
- version pinning;
- security review;
- runtime restrictions;
- dependency policy.

---

# 128. Backlog Structure

`BACKLOG.md` should divide work into task IDs.

Suggested pattern:

```text
P0-001
P0-002
P1-001
P1-002
...
```

Each task should contain:

- title;
- status;
- phase;
- dependencies;
- objective;
- scope;
- files/domains likely affected;
- acceptance criteria;
- checks;
- references.

---

# 129. Task Size

Tasks should be small enough for one autonomous agent cycle.

Prefer:

"Create Workspace membership migration/model/policy/tests"

over:

"Build Team system"

Large epics should be split.

---

# 130. Dependency Rules

A task cannot start until required dependencies are DONE.

Example:

Vehicle Card binding cannot start before:

- Site Vehicle;
- Site Offer;
- Block binding infrastructure.

Backlog should encode these relationships explicitly.

---

# 131. Review Roles

Recommended autonomous flow for implementation tasks:

Backend task:

Backend Agent  
→ QA  
→ Security if relevant  
→ Reviewer.

Frontend task:

Frontend Agent  
→ UI Reviewer  
→ QA  
→ Reviewer.

Architecture-sensitive:

Architect  
→ specialist implementation  
→ Security/QA  
→ Reviewer.

---

# 132. Reviewer Independence

Reviewer should not simply repeat implementer claims.

Reviewer should inspect:

- diff;
- architecture compliance;
- tests;
- known risks;
- Definition of Done.

---

# 133. Stop Conditions

Automation must stop and return `BLOCKED_DECISION` if:

- two architecture docs materially conflict;
- product semantics are undefined;
- major irreversible package/infrastructure decision is required;
- ownership cannot be determined;
- public rendering strategy is required before implementation;
- security model cannot be safely inferred.

---

# 134. External Stop Conditions

Use `BLOCKED_EXTERNAL` for:

- production DNS;
- real CRM credential;
- payment provider account;
- app-store/provider approval;
- production SSL/control-plane action unavailable locally.

Complete all non-blocked work first.

---

# 135. No Premature Feature Work

Do not implement:

- Marketplace;
- complex billing;
- external vehicle feeds;
- enterprise collaboration;
- custom code execution;

before their prerequisite phases.

---

# 136. No Architecture by Accident

A migration, model, or component created during a feature task must not silently become a new architectural standard.

Major patterns require:

- existing architecture basis;
or
- ADR.

---

# 137. ADR Triggers

Create ADR for decisions such as:

- public Site rendering engine;
- published snapshot representation;
- storage provider;
- Redis adoption;
- Marketplace sandbox;
- billing provider;
- custom script runtime;
- search engine.

---

# 138. Phase Status Tracking

`PROJECT_STATE.md` is responsible for current phase status.

`MASTER_PLAN.md` defines desired sequence.

Do not duplicate implementation progress throughout this file.

---

# 139. Completion Reporting

At the end of each phase, produce a phase report:

- implemented capabilities;
- tests;
- remaining known limitations;
- ADRs;
- metrics if available;
- next phase readiness.

---

# 140. MVP Interpretation

The first commercially useful MVP should roughly include:

- Workspace;
- Sites;
- Templates;
- Designer;
- Global Catalog;
- Site Vehicles;
- Site Offers;
- Forms;
- Popups;
- anti-spam;
- Preview;
- Publish to Landflow subdomain;
- basic Integration delivery;
- Yandex Metrica.

Team, Marketplace, advanced billing, and external feeds can follow.

---

# 141. Product Quality Priority

If schedule pressure exists, reduce feature scope before reducing:

- tenant isolation;
- security;
- Draft/Publish separation;
- Submission persistence;
- testability;
- version safety.

Core correctness is more important than feature count.

---

# 142. Automation Quality Priority

If an autonomous agent repeatedly fails a task:

- inspect architecture/task size;
- split task;
- improve rule/agent prompt;
- add regression test;
- update PROJECT_STATE.

Do not simply increase agent freedom.

---

# 143. Human Review Priority

Human review is most valuable for:

- product UX;
- major architecture;
- visual design quality;
- legal/privacy;
- billing;
- production credentials;
- final commercial decisions.

Routine implementation should increasingly be automated.

---

# 144. End State

Target mature workflow:

```text
User/product decision
→ Product/Architecture docs
→ Master Plan
→ Backlog
→ Cursor Orchestrator
→ Specialist Agent
→ Automated Tests
→ Browser QA
→ Security Check
→ Independent Review
→ Commit
→ Project State Update
→ Next Task
```

---

# 145. Final Master Plan Rule

The engineering sequence is:

**First build the rules that allow reliable automation.  
Then build the core platform.  
Then build the Designer.  
Then add structured automotive data.  
Then add interactive lead-generation features.  
Then publish safely.  
Then connect external systems.  
Then add paid/team capabilities.  
Only after the core runtime is stable should Developer Platform and Marketplace be opened.**
