# Landflow — System Architecture

**Document:** `docs/architecture/ARCHITECTURE.md`  
**Status:** Core architecture source of truth  
**Purpose:** Define module boundaries, ownership rules, data flow, dependency direction, and non-negotiable architectural constraints for Landflow.

---

# 1. Architecture Goals

Landflow must be designed as a modular SaaS platform for building, configuring, and publishing automotive websites.

The architecture must support:

- multi-workspace ownership;
- multiple Sites per Workspace;
- reusable Templates and Blocks;
- structured automotive data;
- customer-owned vehicle copies/overrides;
- visual Designer editing;
- Draft/Preview/Published separation;
- reusable Popups and Forms;
- Workspace-level integrations with Site overrides;
- centralized anti-spam and analytics;
- future Team collaboration;
- future Developer Platform and Marketplace;
- safe autonomous development by Cursor agents.

The architecture must avoid premature complexity while preserving clean extension points.

---

# 2. Architectural Style

Landflow should be implemented initially as a **modular monolith**.

This means:

- one Laravel application;
- one primary relational database;
- one frontend application using React + Inertia;
- modules separated logically by domain;
- no premature microservices;
- no unnecessary service boundaries;
- queues may be used for asynchronous work;
- Redis may be introduced where operationally justified.

A modular monolith is preferred because:

- product requirements are still evolving;
- domain boundaries are important but infrastructure should remain simple;
- deployment and debugging are easier;
- autonomous agents can reason about the system more safely;
- modules can later be extracted if real scaling needs justify it.

---

# 3. Current Technology Baseline

The current project baseline is:

Backend:

- Laravel 13
- PHP 8.3+
- Inertia

Frontend:

- React 19
- TypeScript
- shadcn/ui
- Tailwind CSS 4
- Vite

Testing:

- PHPUnit
- Playwright browser/E2E testing (established in P0-023/P0-024, `npm run test:e2e`)

Important:

The architecture document does not authorize package installation by itself.

Package decisions belong in dedicated technical decisions/ADRs or implementation tasks.

---

# 4. Main Bounded Domains

Landflow should be divided conceptually into these major domains:

1. Identity
2. Workspace
3. Subscription / Entitlements
4. Site
5. Template
6. Designer / Page Composition
7. Global Automotive Catalog
8. Workspace Automotive Library
9. Site Vehicle Offers
10. Assets / Media
11. Popup / Form
12. Submission
13. Integration
14. Security / Anti-Spam
15. Analytics
16. Publishing
17. Domain Management
18. Developer Platform
19. Marketplace
20. Super Admin

These are logical domains, not necessarily separate Laravel packages.

---

# 5. Dependency Direction

High-level dependency direction should remain predictable.

Conceptually:

Identity
    ↓
Workspace
    ↓
Site
    ↓
Designer / Automotive / Forms / Publishing

Global Catalog
    ↓
Workspace Vehicle Library
    ↓
Site Vehicles / Offers

Template
    ↓
Site structure

Block Definition
    ↓
Site Block Instance

Workspace Integration Profile
    ↓
Site Integration Configuration
    ↓
Form Routing
    ↓
Submission Delivery

The system must avoid reverse ownership such as:

- Template owning Site Vehicles;
- Global Catalog depending on customer Site data;
- Form owning Integration credentials;
- Published Site being the editable source of truth.

---

# 6. Identity Domain

## Responsibility

Identity represents people using Landflow.

Primary entity:

User

User should contain person/account-level identity information only.

Examples:

- name;
- email;
- authentication state;
- security preferences.

User must not directly represent:

- a dealership;
- an agency;
- a tenant;
- a Site.

Business ownership belongs to Workspace.

---

# 7. Workspace Domain

Workspace is the primary tenant and ownership boundary.

A Workspace owns or controls access to:

- Sites;
- members;
- roles/permissions;
- Workspace Assets;
- Workspace Vehicle Library;
- Workspace Integration Profiles;
- template purchases/licenses;
- subscription context where applicable.

A User may belong to multiple Workspaces.

A Workspace may contain multiple Users.

This relationship is many-to-many through membership.

---

# 8. Workspace Membership

Workspace membership should conceptually contain:

- workspace_id;
- user_id;
- role or permission assignment;
- membership status;
- invited/accepted timestamps;
- relevant metadata.

Do not place Workspace-specific role information directly on User.

---

# 9. Tenant Isolation

Every Workspace-owned resource must be protected against cross-Workspace access.

Examples:

- Site;
- Workspace Asset;
- Workspace Vehicle;
- Integration Profile;
- Workspace-specific Forms where applicable.

Authorization must never rely solely on frontend filtering.

All backend queries/actions must validate ownership or permission.

Global platform resources are exceptions.

Examples:

- Global Automotive Catalog;
- public Templates;
- Marketplace listings.

These are not owned by a customer Workspace.

---

# 10. Site Domain

Site is an independently configurable website.

A Site belongs to one Workspace.

A Site owns:

- Site settings;
- Pages;
- Block instances;
- Site-specific assets;
- Site Vehicles;
- Site Offers;
- Popups;
- Site Forms or Form bindings;
- Site integration overrides;
- SEO settings;
- analytics settings;
- security settings;
- domain configuration;
- publication state.

Site does not own:

- Global Automotive Catalog records;
- Workspace Integration Profile credentials;
- Template definitions;
- global Block definitions.

---

# 11. Site Lifecycle

Conceptual Site states may include:

- Draft / Unpublished
- Published
- Archived

Additional operational states may exist later.

Site state must not be inferred only from presence of a domain.

---

# 12. Site Folder Domain

Site folders belong to Workspace.

They are organizational.

A Site may optionally belong to a folder.

Folders must not automatically redefine authorization or automotive ownership unless explicitly designed later.

---

# 13. Template Domain

Template is a reusable source definition used to initialize Site structure/design.

Template may define:

- Pages;
- Block placements;
- Block configuration defaults;
- global design tokens;
- default forms;
- default Popups;
- default content;
- supported data expectations.

Template must not contain customer-owned commercial vehicle data.

---

# 14. Template Instantiation

When a Site is created from a Template:

Template Definition
    ↓
instantiate
    ↓
Site-owned structure

After instantiation:

- Site content becomes independently editable;
- Site configuration belongs to the Site;
- customer data must not be mutated by later Template changes.

Future Template update systems must be explicit, versioned, and safe.

---

# 15. Template vs Site Rule

This rule is non-negotiable:

**Template describes presentation.  
Site owns customer configuration.**

Changing Template must not inherently delete:

- Site Vehicles;
- prices;
- integrations;
- Site identity;
- SEO;
- Forms.

---

# 16. Block Definition Domain

A Block Definition is a reusable component type.

Examples:

- Hero;
- Vehicle Grid;
- Vehicle Card;
- Benefits;
- Trade-in;
- Credit;
- Contacts.

A Block Definition may come from:

- Landflow official library;
- approved developer;
- Workspace private library;
- Marketplace later.

---

# 17. Block Schema

Each Block Definition must expose a deterministic schema describing editable fields.

Schema may contain:

- text;
- textarea;
- richtext;
- number;
- price;
- boolean;
- select;
- multiselect;
- color;
- image;
- gallery;
- icon;
- link;
- date;
- vehicle;
- vehicle_model;
- vehicle_trim;
- vehicle_color;
- group;
- repeater.

Schema is the source of truth for Designer property editing.

---

# 18. Block Instance

A Block Instance belongs to a Site Page.

It references a Block Definition and stores Site-specific configuration.

Conceptually:

Block Definition
+
Block Instance State
=
Rendered Block

Block Instance must not modify the global Block Definition.

---

# 19. Block Versioning

Block Definitions should be version-aware eventually.

A Site using a Block should not unexpectedly break when the developer publishes a newer Block version.

Safe strategies may later include:

- pinning Block version;
- controlled upgrade;
- migration rules.

MVP does not require advanced upgrade UX, but architecture must avoid assuming all Blocks are eternally mutable single records.

---

# 20. Developer Block Security

Developers must not receive unrestricted server-side execution by default.

Preferred model:

Developer defines:

- markup/component;
- schema;
- supported bindings;
- supported actions;
- safe configuration.

Landflow controls:

- data access;
- Form actions;
- analytics events;
- integration secrets;
- submission delivery;
- server-side privileged operations.

Marketplace security boundaries must be stricter than normal internal code.

---

# 21. AI-Assisted Block Schema

AI may later assist Developer workflow.

Conceptual process:

Developer creates/imports Block markup
    ↓
AI proposes editable fields
    ↓
Developer reviews
    ↓
Schema saved
    ↓
Runtime uses deterministic schema

AI must not be required to interpret Block editability on every user request/render.

---

# 22. Page Domain

A Page belongs to Site.

Page may contain:

- slug;
- title;
- SEO configuration;
- ordered Block Instances;
- page status;
- draft content state.

Pages should not duplicate Site-wide configuration such as Workspace integration secrets.

---

# 23. Designer Domain

Designer is an editor over Site Draft data.

Designer responsibilities:

- Pages;
- Block tree;
- Block selection;
- property editing;
- responsive settings;
- design tokens;
- assets;
- automotive data binding;
- action configuration;
- Popup/Form linking;
- preview.

Designer must not write directly to Published Production state.

---

# 24. Designer Draft Principle

All normal Designer changes write into Draft state.

Conceptually:

Published Snapshot
        |
        | independent
        v
Current Draft

Autosave updates Draft.

Publish creates or promotes a Published snapshot.

---

# 25. Design Tokens

Site should maintain global design values.

Examples:

- colors;
- typography;
- button styles;
- radius;
- spacing;
- container settings.

Blocks should reference tokens where possible.

Local Block overrides should remain possible where schema permits.

---

# 26. Action Domain

Interactive elements should reference standardized actions.

Potential action types:

- URL;
- internal page;
- scroll to section;
- open Popup;
- phone;
- email;
- submit Form.

Blocks should not implement inconsistent custom behavior for common interactions.

---

# 27. Global Automotive Catalog Domain

Global Automotive Catalog is a platform-owned domain.

Only Super Admin / authorized catalog managers can modify it.

Conceptual hierarchy:

Make
→ Model
→ Series
→ Generation
→ Modification
→ Trim / Configuration

Related entities may include:

- characteristics;
- options/equipment;
- colors;
- images;
- body attributes;
- technical data.

---

# 28. Global Catalog Ownership Rule

Global Catalog records are immutable from a customer perspective.

Customer code must never update Global Catalog records when editing their Site.

This must be enforced architecturally, not only by UI.

---

# 29. Catalog Import / Fork

Customers select records from the Global Catalog and import/copy them into customer-owned context.

Conceptually:

Global Trim
        ↓
import
        ↓
Workspace Vehicle / Site Vehicle
        ↓
customer overrides

Customer-owned records may preserve source references.

Examples:

- source_make_id;
- source_model_id;
- source_trim_id;
- source_catalog_version.

Exact schema belongs in `DATABASE.md`.

---

# 30. Source Reference Principle

Customer copies should retain enough source linkage to support future features such as:

- comparing against updated Global Catalog;
- refreshing technical characteristics;
- identifying origin;
- duplicate detection.

Source linkage must not imply automatic synchronization.

---

# 31. No Silent Catalog Synchronization

Global Catalog updates must not silently overwrite customer customizations.

Future update flow may be:

Catalog update available
→ compare
→ customer chooses fields
→ apply update

This is future functionality.

---

# 32. Workspace Vehicle Library

Workspace Vehicle Library is an optional customer-owned reusable layer.

Purpose:

- prepare vehicle content once;
- reuse across multiple Sites;
- share custom photos;
- share descriptions;
- reduce repeated setup.

It sits between:

Global Catalog
and
Site Vehicles.

---

# 33. Site Vehicle

Site Vehicle represents a vehicle/configuration used on a specific Site.

It belongs to Site.

Site Vehicle may reference:

- Workspace Vehicle;
- Global Catalog source;
- selected trim/configuration.

It may store Site-specific overrides.

---

# 34. Site Offer

Commercial data should be separated conceptually from canonical automotive data.

Site Offer may include:

- RRP;
- price;
- discounts;
- trade-in benefit;
- credit benefit;
- leasing benefit;
- custom benefits;
- badges;
- visibility;
- CTA.

Do not place customer price in Global Catalog.

---

# 35. Benefit Extensibility

Commercial benefits must support extensibility.

Avoid architecture such as only:

- trade_in_discount column;
- credit_discount column.

Instead, design for multiple benefit types.

Exact database design will be defined later.

---

# 36. Vehicle Color Domain

Vehicle Color must support more than one simple HEX value.

Potential data:

- manufacturer name;
- display name;
- type;
- one or more swatches/layers;
- ordering;
- related images.

Multi-tone example:

White body + black roof.

The UI can render a split/multi-part swatch.

---

# 37. Vehicle Image Domain

Vehicle images may relate to:

- model;
- generation;
- trim;
- color;
- view/angle.

Global Catalog may store transparent-background automotive images.

Customer Workspace/Site may override images.

---

# 38. Image Override Resolution

Conceptual resolution order:

Site override
    ↓ fallback
Workspace override
    ↓ fallback
Global Catalog media

The closest explicit override wins.

---

# 39. Asset Domain

Generic media should be separated from structured automotive media.

Asset examples:

- logo;
- banner;
- background;
- icon;
- PDF;
- promotional image.

Vehicle Catalog Image is structured automotive media.

Workspace Asset is generic reusable customer media.

Do not collapse every file into one untyped business concept even if underlying storage is shared.

---

# 40. Popup Domain

Popup is a reusable Site-level content container.

Examples:

- Callback;
- Get Offer;
- Credit;
- Trade-in;
- Test Drive.

Multiple Block actions may reference one Popup.

Popup belongs to Site.

---

# 41. Popup vs Form

Popup and Form must remain separate.

Popup:

- presentation;
- content container;
- modal behavior.

Form:

- fields;
- validation;
- submission logic;
- routing context.

One Form may be used inside multiple visual containers.

---

# 42. Form Domain

Form defines data collection structure.

Potential fields:

- name;
- phone;
- email;
- custom fields;
- consent;
- hidden/context fields.

Forms must use the central submission pipeline.

Blocks should not send CRM requests directly.

---

# 43. Automotive Form Context

When Form is invoked from automotive content, context may include:

- Site Vehicle;
- catalog identifiers;
- trim;
- color;
- price;
- Page;
- Block;
- UTM parameters.

Context should be captured by the platform rather than manually reconstructed by Block developers.

---

# 44. Submission Domain

Submission is a persisted user form submission.

Important rule:

A valid Submission must be persisted before external delivery attempts.

Conceptual pipeline:

Request
→ validate
→ anti-spam
→ persist Submission
→ dispatch delivery jobs
→ track results

---

# 45. Submission State

Potential states:

- received;
- rejected_spam;
- delivery_pending;
- delivered;
- partially_delivered;
- failed.

Exact state model belongs in implementation design.

---

# 46. Integration Domain

Integration handles delivery to external systems.

Types may include:

- Email;
- Custom API;
- Webhook;
- CRM connector.

Integrations must not be hardcoded into individual Forms.

---

# 47. Workspace Integration Profile

Reusable credentials/configuration live at Workspace level.

Example:

Dealer CRM Profile

Contains:

- base URL;
- auth type;
- token/secret;
- timeout;
- reusable mapping defaults.

This avoids duplicating secrets across Sites.

---

# 48. Site Integration Binding / Override

A Site may use a Workspace Integration Profile with Site-specific values.

Example:

Shared:

- token;
- base URL.

Site A:

- site_id = 1001.

Site B:

- site_id = 1002.

The architecture should model inheritance/reference, not copy credentials unnecessarily.

---

# 49. Integration Secrets

Secrets must be:

- stored securely/encrypted where appropriate;
- redacted in UI;
- excluded from public frontend;
- excluded from logs;
- excluded from normal exports.

Frontend should not receive the full secret after it has been saved unless strictly required.

---

# 50. Form Routing

A Form or Site configuration may route a Submission to multiple destinations.

Example:

Submission
    ├── Email
    ├── Dealer CRM
    └── Webhook

Each delivery is independently tracked.

---

# 51. Integration Field Mapping

The Integration layer should map Landflow fields/context into destination payload fields.

Example:

name → client_name
phone → telephone
vehicle_id → car_id

Static Site parameters may be mapped as constants.

Example:

site_id = 456

Mapping is configuration, not hardcoded into Form components.

---

# 52. Delivery Domain

External delivery should normally be asynchronous.

Recommended conceptual flow:

Submission persisted
→ queue delivery job
→ call external destination
→ store Delivery Attempt
→ retry if allowed

This prevents CRM latency/outages from breaking frontend form UX.

---

# 53. Delivery Log

Each destination delivery should retain operational information such as:

- destination;
- status;
- attempt count;
- last attempt;
- response status;
- safe error summary.

Sensitive response/request values must be redacted.

---

# 54. Security / Anti-Spam Domain

Anti-spam is a platform service.

Possible checks:

- honeypot;
- request rate limit;
- IP rate limit;
- phone rate limit;
- duplicate submission detection;
- blacklist;
- Yandex SmartCaptcha.

No standard Landflow Form should bypass central protection.

---

# 55. Yandex SmartCaptcha Adapter

SmartCaptcha should be integrated through a dedicated platform service/adapter.

Blocks must not directly depend on SmartCaptcha implementation.

Conceptually:

Form
→ Security Policy
→ Captcha Provider Adapter
→ Yandex SmartCaptcha

This allows implementation details to change without redesigning Blocks.

---

# 56. Blacklist Domain

Blacklist scopes may include:

- global;
- Workspace;
- Site.

Entry types may include:

- IP;
- phone.

Entries should support metadata:

- reason;
- created by;
- source;
- created time;
- optional expiration.

---

# 57. Rate Limit Policy

Site/Workspace security configuration may define limits.

Example dimensions:

- submissions per IP per interval;
- submissions per normalized phone per interval;
- duplicate Form + phone interval.

Phone values must be normalized before comparison.

Rate-limit counters should not be implemented as random Block-specific logic.

---

# 58. Analytics Domain

Landflow should define internal semantic events.

Examples:

- page.view;
- vehicle.view;
- vehicle.click;
- popup.open;
- popup.close;
- form.start;
- form.submit;
- form.success;
- form.error;
- phone.click;
- cta.click.

Blocks emit semantic Landflow events.

Analytics adapters forward them.

---

# 59. Yandex Metrica Adapter

Yandex Metrica is the primary required analytics integration for the Russian-oriented product configuration.

Architecture:

Landflow Event
→ Analytics Service
→ Yandex Metrica Adapter

Template developers should not manually implement standard Metrica event logic.

---

# 60. Publishing Domain

Publishing converts validated Site Draft state into a public representation.

Core states:

Draft
→ Preview
→ Publish
→ Published Snapshot

Published output must not depend directly on partially edited Draft state.

---

# 61. Published Snapshot

A publication should conceptually preserve enough data to reproduce the public Site version.

Potential strategies will be defined later.

Important principle:

Once published, Site output should remain stable until another publication occurs.

Autosave must not change production.

---

# 62. Versioning

Publishing architecture should be version-ready.

Potential entities/concepts:

- Publication;
- Site Version;
- snapshot;
- restore point.

Advanced visual version history is later.

The first implementation may use a simpler model if it preserves upgradeability.

---

# 63. Preview

Preview renders current Draft safely.

Preview should use the same rendering rules as Published output whenever practical.

Avoid separate preview-only component implementations that drift from production.

---

# 64. Domain Management

Domain management belongs to Site.

Potential concepts:

- Landflow subdomain;
- custom domain;
- verification;
- DNS status;
- SSL status;
- primary domain;
- redirects.

Domain infrastructure must remain separated from Page content/Designer state.

---

# 65. Landflow Subdomain

A Site may have a stable platform hostname such as:

`project.landflow.me`

This may serve:

- Free public Sites;
- staging;
- previews where appropriate.

Custom domains do not replace ownership of the Site itself.

---

# 66. Subscription / Entitlement Domain

Feature access must be determined through capabilities/entitlements.

Avoid scattered checks:

`if plan == 'team'`

Prefer concepts such as:

- max_sites;
- max_members;
- custom_domain;
- remove_branding;
- advanced_seo;
- version_history;
- site_vehicle_import;
- workspace_vehicle_library;
- developer_access.

Plans map to entitlements.

Product code consumes entitlements.

---

# 67. Entitlement Resolution

Conceptually:

Subscription / Plan
→ Entitlements
→ Capability Service
→ authorization/business rule

The exact billing provider must not leak into unrelated domain logic.

---

# 68. Developer Platform Domain

Developer Platform is separate from customer Site editing.

Responsibilities:

- developer profile;
- Block authoring;
- Template authoring;
- test/preview;
- submission;
- review status;
- Marketplace publishing;
- future earnings.

A normal customer account may also become a developer, but developer capabilities are separate permissions/state.

---

# 69. Marketplace Domain

Marketplace distributes approved reusable products.

Initial product types:

- Template;
- Block.

Marketplace handles:

- listing;
- author;
- version;
- price/free;
- license;
- compatibility;
- moderation.

Purchases and payouts are later.

---

# 70. Marketplace Moderation

Marketplace items must pass Landflow review before public publication.

Potential states:

- Draft;
- Submitted;
- In Review;
- Changes Requested;
- Approved;
- Published;
- Suspended.

Super Admin controls moderation.

---

# 71. Super Admin Domain

Super Admin is a platform control plane.

Responsibilities include:

- Users;
- Workspaces;
- Sites;
- Plans;
- subscriptions;
- feature flags;
- Global Catalog;
- Templates;
- Blocks;
- Marketplace;
- developers;
- domains;
- integrations diagnostics;
- platform configuration.

Super Admin actions should be auditable where risk warrants it.

---

# 72. Global vs Workspace vs Site Scope

Every entity must have an explicit scope.

## Global

Examples:

- Global Catalog;
- public Template definitions;
- public Block definitions;
- Marketplace listings;
- platform configuration.

## Workspace

Examples:

- members;
- Integration Profiles;
- Workspace Assets;
- Workspace Vehicle Library.

## Site

Examples:

- Pages;
- Block Instances;
- Site Vehicles;
- Site Offers;
- Popups;
- Site settings;
- domains;
- Site overrides.

Do not create ambiguous ownership.

---

# 73. Scope Rule for Cursor Agents

Whenever an agent creates a new business entity, it must explicitly answer:

- Is this Global, Workspace, or Site scoped?
- Who owns it?
- Who may modify it?
- Can it be shared?
- Can it be copied?
- Can it be overridden?
- Does deletion cascade?

If these answers are unclear, the agent must not improvise a major architecture decision.

---

# 74. Cross-Site Copy

Cross-Site reuse must distinguish:

- reference;
- copy;
- override;
- synchronization.

Default Site-to-Site vehicle/config transfer is COPY.

Shared Workspace Integration Profile is REFERENCE + Site override.

Global Catalog import is SOURCE REFERENCE + customer-owned data/override.

Never hide these semantics.

---

# 75. Deletion Rules

Deletion must be designed conservatively.

Examples:

Deleting Global Catalog item must not blindly destroy customer Site data.

Deleting Workspace Integration Profile must check Site bindings.

Deleting Template must not delete Sites created from it.

Deleting Block Definition must not silently destroy Published Sites.

Exact FK/cascade behavior belongs in `DATABASE.md`.

---

# 76. Auditability

Sensitive actions should eventually support audit history.

Examples:

- publishing;
- domain changes;
- price changes;
- Integration Profile changes;
- permission changes;
- blacklist changes;
- Super Admin actions.

MVP may introduce audit incrementally.

---

# 77. Queue Usage

Queues are appropriate for operations such as:

- form delivery;
- external API/CRM calls;
- image processing;
- publication tasks if expensive;
- notifications;
- future feed imports;
- scheduled synchronization.

Do not use synchronous browser requests for slow/unreliable external operations where persistence/queueing is safer.

---

# 78. Scheduled Jobs

Scheduled jobs may later handle:

- delivery retries;
- expired temporary blacklist entries;
- domain verification;
- cleanup;
- external imports;
- publication housekeeping.

Cron/scheduler infrastructure should remain standard Laravel where possible.

---

# 79. File and Media Storage

Media storage should use an abstraction compatible with object storage.

Do not permanently couple business logic to local filesystem paths.

Media records should retain business metadata separately from physical storage.

Potential storage targets:

- local during development;
- S3-compatible storage in production;
- other provider if required.

Provider choice belongs in deployment architecture.

---

# 80. Image Processing

Automotive images may require:

- resizing;
- WebP/AVIF generation;
- thumbnails;
- transparent images;
- optimization.

Image processing should occur as media processing logic, not inside arbitrary Blocks.

Exact implementation will be decided later.

---

# 81. Frontend Architecture

React frontend should be organized by product/domain responsibility rather than one giant components folder.

Potential conceptual areas:

- workspace;
- sites;
- designer;
- automotive;
- forms;
- integrations;
- settings;
- admin;
- developer.

Shared generic UI belongs in reusable primitives/components.

shadcn/ui remains the preferred UI foundation.

---

# 82. Server-Driven Data

Inertia remains the primary application transport for authenticated product UI.

Do not introduce a standalone REST API simply because it is common.

Create APIs only when required for:

- published frontend runtime;
- external integrations;
- async endpoints;
- developer/public API later.

---

# 83. Published Site Runtime

Published Sites may eventually require a rendering/runtime strategy different from the authenticated Landflow dashboard.

The exact strategy is not defined in this document.

Architecture must support:

- fast public rendering;
- SEO;
- custom domains;
- Forms;
- analytics;
- dynamic Site Vehicle data where intended;
- stable Published snapshots.

This will receive a dedicated `PUBLISHING.md`.

---

# 84. Public Form Endpoint

Published Sites will require secure public Form submission endpoints.

These endpoints must:

- identify Site/Form;
- validate payload;
- enforce Site Security Policy;
- verify captcha where configured;
- rate limit;
- normalize data;
- persist valid Submission;
- queue delivery.

They must never expose Workspace integration secrets.

---

# 85. API Security Boundary

Any public endpoint must assume untrusted input.

Never trust:

- Site IDs from browser without validation;
- vehicle prices sent by browser;
- Integration IDs;
- hidden Form fields;
- role/permission claims;
- arbitrary Block configuration.

Server resolves authoritative Site configuration.

---

# 86. Pricing Integrity

When automotive price/context is submitted through a Form, backend should resolve trusted Site Vehicle/Site Offer data where needed rather than treating a browser-supplied price as authoritative.

Client context may be recorded for diagnostics but not trusted for privileged behavior.

---

# 87. Caching

Caching may be used for:

- Global Catalog reads;
- Published Site representation;
- entitlement resolution;
- domain routing;
- Template/Block metadata.

Cache must never become the source of truth.

Invalidation strategy must be explicit for mutable data.

---

# 88. Search

Search may eventually be needed for:

- Global Automotive Catalog;
- Workspace Sites;
- Assets;
- Marketplace;
- Blocks/Templates.

Do not add Elasticsearch/OpenSearch prematurely.

Start with database capabilities unless actual scale/search requirements justify a dedicated engine.

---

# 89. Database Principle

Use a relational database as the primary source of truth.

Structured business concepts should generally be normalized.

JSON may be appropriate for:

- Block instance state;
- schema definitions;
- configurable mappings;
- flexible provider-specific settings;
- snapshots.

Do not place the entire business model into opaque JSON.

Detailed table design belongs in `DATABASE.md`.

---

# 90. Schema Evolution

Flexible systems such as Block Schema and Integration configuration require version awareness.

When storing flexible JSON configuration, include enough metadata to migrate safely later.

Avoid unmanaged anonymous structures.

---

# 91. Errors and Observability

The application should distinguish:

- validation errors;
- authorization errors;
- domain errors;
- external integration failures;
- infrastructure errors.

External provider failures must be logged safely without leaking secrets.

Future operational monitoring may be added.

---

# 92. Feature Flags

Feature flags may be used for:

- beta Designer features;
- Marketplace rollout;
- new integrations;
- publishing engine changes.

Feature flags must not replace entitlement logic.

Entitlement answers:
"Is this included for this customer?"

Feature flag answers:
"Is this feature enabled/rolled out?"

---

# 93. Testing Architecture

Testing should eventually include:

## Unit / Domain tests

For isolated business rules.

## Feature tests

For:

- authorization;
- Workspace isolation;
- Site actions;
- catalog import;
- Form submission;
- integration routing;
- publishing.

## Browser/E2E tests

For critical flows:

Register
→ Workspace
→ Site
→ Template
→ Designer
→ vehicle configuration
→ Form/Popup
→ Preview
→ Publish

Browser testing uses Playwright (established in P0-023/P0-024, `npm run test:e2e`); these critical flows are added as the features exist.

---

# 94. Definition of Done Principle

A feature is not complete solely because code exists.

Completion should eventually require applicable checks such as:

- PHPUnit passing;
- static analysis passing;
- formatting/lint passing;
- TypeScript checks passing;
- production build passing;
- E2E passing for affected critical paths;
- no unexpected browser console errors;
- authorization tested;
- Workspace isolation tested.

A dedicated `DEFINITION_OF_DONE.md` will define exact rules.

---

# 95. Cursor Agent Architecture Rules

Cursor agents must not:

- introduce microservices without explicit architecture decision;
- install libraries without task authorization;
- change tenancy model;
- let customers edit Global Catalog;
- store Site prices in Global Catalog;
- make Template own Site vehicle data;
- bypass centralized Forms;
- put CRM tokens in frontend;
- send Forms directly from Block code to external CRM;
- couple product logic directly to Swiper/Fancybox when platform abstraction exists;
- auto-sync Sites silently;
- rewrite architecture to match personal preference;
- implement Future features during unrelated MVP tasks.

---

# 96. Architecture Decision Records

Major technical decisions should eventually be recorded as ADRs.

Examples:

- publishing rendering strategy;
- media storage provider;
- Block runtime format;
- Block schema versioning;
- queue backend;
- tenancy enforcement strategy;
- marketplace sandbox;
- billing provider.

Location:

`docs/architecture/decisions/`

Agents should consult existing ADRs before changing these decisions.

---

# 97. Architecture Documents to Follow

This file defines the high-level system.

It should be followed by focused documents:

- `DATABASE.md`
- `TENANCY.md`
- `PERMISSIONS.md`
- `AUTOMOTIVE_DATA.md`
- `BLOCK_SYSTEM.md`
- `FORMS_AND_INTEGRATIONS.md`
- `PUBLISHING.md`
- `SECURITY.md`

Not all documents must be created at once.

They should be produced before implementation reaches the relevant complex domain.

---

# 98. Initial Implementation Philosophy

The implementation should progress vertically and incrementally.

Example:

Do not build:

- complete Marketplace;
- complete Team;
- complete Designer;
- complete Global Catalog;

all simultaneously.

Instead:

Foundation
→ small working Workspace/Site flow
→ small Template flow
→ small Designer
→ small Automotive vertical slice
→ Publish
→ expand.

Every phase should leave the application in a coherent testable state.

---

# 99. Architecture Source of Truth

When documents conflict, use this priority:

1. Explicit current product decision from the user
2. `PRODUCT.md`
3. approved Architecture/ADR documents
4. current roadmap/task specification
5. existing implementation

Existing code is **not automatically correct architecture** merely because it exists.

If implementation contradicts approved architecture, the contradiction must be resolved intentionally.

---

# 100. Final Architecture Model

Landflow should be understood as the following system:

User
    ↓
Workspace
    ├── Members / Permissions
    ├── Integration Profiles
    ├── Assets
    ├── Vehicle Library
    │
    └── Sites
         ├── Pages
         │    └── Block Instances
         │          ↓
         │      Block Definitions
         │
         ├── Site Vehicles
         │      ↑
         │  Global Automotive Catalog
         │
         ├── Site Offers
         ├── Popups
         ├── Forms
         │      ↓
         │  Submissions
         │      ↓
         │  Integration Routing
         │
         ├── Analytics / Security
         ├── SEO / Domain
         ├── Draft
         └── Published Version

Separate platform areas:

Developer Platform
→ Templates / Blocks
→ Review
→ Marketplace

Super Admin
→ Platform Management
→ Global Automotive Catalog
→ Moderation
→ Plans / Features

The most important ownership rules are:

**Global Catalog is platform-owned.  
Workspace owns reusable customer resources.  
Site owns website-specific configuration and commercial offers.  
Template/Block definitions describe reusable presentation.  
Draft is editable.  
Published state is stable.  
Forms use centralized security and delivery.  
Secrets remain server-side.**
