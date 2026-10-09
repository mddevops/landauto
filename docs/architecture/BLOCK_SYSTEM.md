# Landflow — Block System Architecture

**Document:** `docs/architecture/BLOCK_SYSTEM.md`  
**Status:** Core Designer/Developer architecture source of truth  
**Purpose:** Define Block Definitions, Block Versions, Block Schema, Block Instances, editable fields, repeaters/groups, actions, data binding, carousel/lightbox capabilities, developer authoring, AI-assisted schema generation, rendering boundaries, and safe extensibility.

---

# 1. Block System Goals

The Block System is one of the central technologies of Landflow.

It must allow:

- Landflow to ship official reusable Blocks;
- Developers to create reusable Blocks;
- Templates to compose Pages from Blocks;
- Customers to customize Blocks without programming;
- Designer to automatically generate editing controls;
- Blocks to consume structured automotive data;
- Blocks to support repeaters and nested groups;
- Blocks to expose safe actions;
- compatible Blocks to render as Grid or Carousel;
- Gallery Blocks to open Lightbox;
- Blocks to open reusable Popups;
- Block behavior to remain version-safe;
- future Marketplace distribution;
- future AI assistance during developer authoring.

The Block System must remain deterministic at runtime.

---

# 2. Fundamental Model

The core model is:

Block Definition
→ Block Version
→ Block Schema + Renderer
→ Block Instance
→ Site-specific State
→ Rendered Output

**Block Definition describes what a Block is.  
Block Instance stores how one Site uses it.**

---

# 3. Block Definition

A Block Definition represents a reusable component type.

Examples:

- Header
- Hero
- Benefits
- Vehicle Grid
- Vehicle Card
- Credit
- Trade-in
- Contacts
- Footer

A Block Definition may be:

- Landflow official;
- Developer-created;
- Workspace-private;
- Marketplace-distributed.

---

# 4. Block Definition Ownership

Possible scopes:

## Global Official

Created and maintained by Landflow.

## Developer / Marketplace

Created by an approved developer and optionally distributed publicly.

## Workspace Private

Created for one Workspace and private by default.

Scope must always be explicit.

Implemented Block Definition ownership (D-093, D-117; P9-003). `block_definitions.owner_scope` (`App\Enums\BlockOwnerScope`) is the only ownership source; `is_official` was removed and every pre-existing definition was backfilled as `platform`:

- `platform`: official Landflow content, owned by the platform (`developer_profile_id` and `workspace_id` empty); the Super Admin creator / editor is audit identity only and needs no Developer Profile;
- `developer`: owned by exactly one Developer Profile (`developer_profile_id` set, `workspace_id` empty);
- `workspace_private`: owned by exactly one Workspace (`workspace_id` set, `developer_profile_id` empty). The scope exists in the ownership model, but there is no Workspace-private authoring UI yet.

The model rejects any other combination. Ownership, the slug and `created_by_user_id` never change after creation; `created_by_user_id` / `updated_by_user_id` are audit identity, never ownership. The slug stays globally unique across all scopes because the trusted official runtime uses it as a stable identifier.

Authoring authorization (D-118), centralized in `App\Blocks\BlockAuthoringAuthorization` and enforced by `App\Blocks\BlockAuthoring`:

- `platform` Blocks: «Блоки Landflow» (`/platform/blocks`), platform permission `manage_platform_content` (Super Admin); no Developer Profile needed.
- `developer` Blocks: «Мои блоки» (`/developer/blocks`), the User's own active Developer Profile plus `create_blocks`; ownership is derived on the server, and another profile's Block returns 404.
- `workspace_private` Blocks: denied in authoring (future separate Workspace-authorized path).

Authoring covers only name and slug; no delete, review status, schema editing, preview or version publishing exists yet (P9-004 … P9-008). A new definition has no Block Version.

`OfficialBlockCatalog` only bootstraps official content: `OfficialBlockSeeder` creates missing platform definitions and appends missing immutable official versions, but never overwrites the metadata of an existing definition (names edited in «Блоки Landflow» survive reseeding). A catalog slug held by a non-platform Block makes the seeder fail instead of taking it over.

Authored HTML / CSS / JS Blocks (platform and Developer) run only in the ADR-008 sandbox: opaque-origin `srcdoc` iframe with `sandbox="allow-scripts"` (no `allow-same-origin`), Landflow-built `srcdoc` with CSP `connect-src 'none'`, allowlisted `postMessage` bridge (D-080 / D-081, owner-confirmed 2026-10-08). Official renderers stay in the trusted application registry.

Runtime direction (ADR-009, D-123, owner decision 2026-10-08). **Current:** `BlockRuntime` = `official` | `sandboxed`; every authored version is `sandboxed` and renders in an iframe on published Sites. **Target (X-024 / X-025, not implemented):** a third runtime `native` — ordinary first-party Blocks whose exact Draft revision / source hash was explicitly approved by an authorized internal actor («Одобрить и опубликовать», separate deny-by-default capability, not moderation) render in the published host DOM as compiled HTML, scoped compiled CSS and approved JS (`mount(root, props, api) => cleanup`), without an iframe wrapper. The sandbox stays for Studio preview, pre-approval / untrusted code and legacy sandboxed versions (immutable; migrated only into new Native versions, X-028); iframes otherwise only for genuine Embed Blocks. Native JS never runs in the Landflow application origin.

Native runtime, X-024 (HTML / CSS / actions; implemented 2026-10-09):

- `BlockRuntime::Native` («Нативный»). Source invariant: `official` has no `html` / `css` / `js`; `sandboxed` and `native` carry all three (`BlockVersion::authoredSource()`; `sandboxSource()` stays sandboxed-only). No production path creates Native versions: Block Studio still publishes `sandboxed` versions until the X-025 approval flow; Native versions come only from `BlockVersionFactory::native()` (tests / E2E fixtures).
- Template language is the sandbox one (`{{ path }}`, `{{#if}}`, `{{#each}}`, `{{else}}`), compiled by `NativeTemplateCompiler` into a node tree from an HTML parse (libxml, template tags replaced by placeholders first), never by regex rewriting. Templates are allowed only in text and attribute values; sections must balance within one parent or one attribute.
- HTML policy (`NativeHtmlPolicy`): element allowlist (HTML phrasing / flow / table / media subset plus a static SVG subset); forbidden `script`, `style`, `link`, `meta`, `base`, `iframe`, `frame`, `object`, `embed`, `form`, `portal`, … ; no `on*` / `style` attributes; navigation URLs only fragment, relative, `http(s)`, `mailto`, `tel` (`javascript:`, `vbscript:`, `data:` and protocol-relative rejected after control-character stripping); `img src` must be exactly an image field `{{ x.url }}` and resolves only to a trusted Published Media URL; reserved `id` prefixes (`lf-`, `landflow`, `block-`) and `data-landflow-*` (except `data-landflow-action`, which must name a schema `action` field). Values coming from state are re-checked after substitution and escaped per context (text / double-quoted attribute).
- CSS (`NativeCssCompiler`): CSS Syntax L3 tokenizer + rule parser + selector scoper. Every selector is prefixed with the version scope `[data-landflow-native="{slug}--{version}--{hash10}"]`; `:root`, `html`, `body` map to the scope itself; `@media` / `@supports` are kept and their rules scoped; `@keyframes` names get the `lf-{hash10}-` prefix and `animation` / `animation-name` references follow. Rejected: `@import`, `@font-face`, other at-rules, nesting, `url()` / `image-set()` / `expression()` and similar, `behavior` / `-moz-binding`. Limits: 512 KB rendered HTML per instance, 256 KB CSS per version, 1 MB CSS per Published Version.
- Each instance renders as one root `<div data-landflow-native data-landflow-block data-landflow-instance>` inside the usual `#block-{public_id}` wrapper. Actions: delegated click on `[data-landflow-action]` within that root, resolved by the shared host runtime (`runBlockAction`, the same Popup runtime as sandboxed / official Blocks); `<form>` stays forbidden (Forms remain host-owned).
- Native JS is not supported yet: a Native version with non-empty `js` cannot be published (`native_js_not_approved`).
- Block Studio, the Designer canvas and authenticated Site Preview render Native versions through the sandbox frame (`BlockVersion::previewSource()`); Native host DOM exists only on published Sites.

Customer catalog access (D-121): each catalog Block / Template has one access mode — `free`, `entitlement` (typed entitlement), `paid` (Site and / or Workspace price, no checkout yet) or `admin_grant`. Restricted items need a `catalog_licenses` row for the Site or for its Workspace (covers current and future Sites of that Workspace; never account-wide). The Designer library card and the Add action follow current access for the current installable version.

Installed version grandfathering (D-122): adding a Block or installing a Template records a Site + Block Version grant after the current access check; a version restore re-creates grants for the versions it brings back. Duplicating a granted version inside the same Site and publishing pass on the grant; without a grant, current access applies. Publishing never creates grants, and revoking a license or restricting a Block never breaks an installed version — only new installs, new versions and other Sites follow current access.

---

# 5. Block Version

Every reusable Block must be version-aware.

Example:

Vehicle Card
- v1.0
- v1.1
- v2.0

A Block Version may contain:

- schema;
- renderer reference;
- capabilities;
- binding contract;
- compatibility metadata;
- migration metadata.

---

# 6. Version Pinning

A Site Block Instance references a specific Block Version.

Publishing a new Block Version must not unexpectedly mutate existing customer Sites.

Default rule:

Existing instance
→ stays on current version.

Upgrade
→ explicit or controlled.

Future safe patch upgrades may be allowed only when compatibility is guaranteed.

---

# 7. Semantic Versioning

Developer Blocks should eventually use semantic-style versions:

- major;
- minor;
- patch.

Typical meaning:

- patch = compatible fix;
- minor = compatible feature;
- major = potentially breaking change.

Exact enforcement may be added later.

---

# 8. Block Schema

Block Schema defines which properties a customer may edit.

Designer reads Schema and automatically builds the Properties Panel.

The developer must not create a separate admin interface for every Block.

Example Hero:

- title
- subtitle
- background
- vehicle
- button
- show_price

---

# 9. Runtime Determinism

Landflow must use saved Schema at runtime.

Do not make AI inspect arbitrary markup every time a Block renders.

Correct flow:

Developer markup
→ optional AI analysis
→ proposed Schema
→ developer review
→ confirmed Schema
→ deterministic runtime.

---

# 10. Schema Field Definition

A field may define:

- key;
- label;
- type;
- default;
- required;
- validation;
- help text;
- section;
- condition;
- binding support;
- responsive behavior where relevant.

Exact JSON syntax is an implementation decision.

---

# 11. Core Field Types

Initial field types should include:

- text
- textarea
- richtext
- number
- price
- boolean
- select
- multiselect
- color
- image
- gallery
- icon
- link
- date
- group
- repeater

Implemented canonical types (`App\Enums\BlockFieldType`, P9-004): text, textarea, number (`min` / `max` bound the value, `step` is the editor increment), boolean, select, image, action, vehicle, group, repeater. Block Studio's Schema Builder and `schema.json` edit this same JSON.

Automotive types:

- vehicle
- vehicle_model
- vehicle_trim
- vehicle_color
- automotive_characteristic
- automotive_option

Additional types may be added deliberately.

---

# 12. Text Field

Use for short values:

- heading
- CTA label
- badge
- caption

May define max length, placeholder, required state and default.

---

# 13. Textarea Field

Use for longer plain text:

- subtitle
- description
- disclaimer

Prefer plain text when rich formatting is not required.

---

# 14. Rich Text Field

Use only where formatting is intended.

May support:

- paragraphs
- bold
- lists
- links

Rich text must be sanitized.

Unsafe arbitrary HTML must not be enabled by default.

---

# 15. Number Field

Examples:

- visible item count
- gap
- animation delay

May define:

- min
- max
- step

---

# 16. Price Field

Price input must follow the platform Money strategy.

Automotive commercial prices should normally come from Site Offer bindings rather than being duplicated inside Block state.

---

# 17. Boolean Field

Examples:

- show_price
- show_arrows
- show_subtitle
- autoplay
- overlay_enabled

Designer renders a switch/checkbox.

---

# 18. Select and Multi-Select

Select chooses one controlled value.

Multi-select chooses several controlled values.

Examples:

- alignment
- layout
- button style
- visible characteristic categories
- selected vehicle models

Options may be static or produced from an approved data source.

---

# 19. Design Color vs Automotive Color

A generic `color` field represents visual design color.

Automotive Vehicle Color is a structured catalog entity with:

- swatches;
- manufacturer name;
- images;
- availability.

These concepts must never be confused.

---

# 20. Image Field

Image may select from:

- Site assets;
- Workspace assets;
- upload;
- approved automotive media where context allows.

Block state stores a reference/configuration, not binary file data.

---

# 21. Gallery Field

Gallery represents ordered media.

Potential features:

- add/remove;
- reorder;
- alt text;
- caption;
- Lightbox support.

Automotive Gallery may bind directly to Site Vehicle media instead of manual items.

---

# 22. Icon Field

Icons must come from controlled icon/media systems.

Arbitrary executable SVG/HTML must be sanitized or disallowed.

---

# 23. Link and Action

A simple Link may describe:

- URL/page;
- target;
- label.

Standard interactive behavior should prefer the central Action System.

---

# 24. Group Field

Group organizes nested fields.

Example:

Button
- text
- icon
- style
- action

Group is not repeatable by itself.

---

# 25. Repeater Field

Repeater is a first-class customer-editable collection.

Example:

Benefits
- icon
- title
- description

Customer may:

- add;
- delete;
- reorder;
- duplicate;
- edit.

---

# 26. Nested Repeaters

Nested structures may be supported in a controlled way.

Example:

Slides
→ Buttons

Landflow may limit nesting depth to preserve Designer usability and rendering safety.

---

# 27. Repeater Limits

Schema may define:

- minimum items;
- maximum items;
- initial/default items.

This protects design integrity.

---

# 28. Stable Repeater Item Identity

Each Repeater item should have a stable internal identifier.

Do not rely solely on array index.

This improves:

- React rendering;
- reordering;
- autosave;
- diffing;
- future version history.

---

# 29. Conditional Fields

Schema should support safe conditional visibility.

Example:

`show_button = true`

Then show:

- button_label
- button_action
- button_style

Conditions must be deterministic.

---

# 30. Conditional Logic Scope

Initial condition operators should remain simple:

- equals
- not_equals
- contains
- boolean true/false

Do not allow arbitrary JavaScript expressions inside Schema.

---

# 31. Designer Sections

Schema fields should be organized into logical sections.

Example Vehicle Grid:

Content:
- title
- subtitle

Data:
- source
- filters
- ordering

Layout:
- grid/carousel
- columns
- gap

Design:
- colors
- typography

Actions:
- card click behavior

---

# 32. Content, Design, Data and Actions

Landflow should distinguish:

## Content
Text, images, copy.

## Design
Appearance and layout.

## Data
Structured data sources/bindings.

## Actions
Click/tap behavior.

This improves Designer UX and permission boundaries.

---

# 33. Responsive Fields

Only fields declared as responsive should vary by breakpoint.

Example:

columns:
- Desktop: 4
- Tablet: 2
- Mobile: 1

Do not make all Schema values responsive automatically.

---

# 34. Standard Breakpoints

Landflow should expose platform-standard breakpoints:

- Desktop
- Tablet
- Mobile

Exact pixel values belong in UI/design architecture.

Blocks must not invent incompatible breakpoint systems.

---

# 35. Block Capabilities

Block Version should explicitly declare capabilities.

Examples:

- responsive
- data_source
- carousel
- actions
- vehicle_context
- gallery
- lightbox

Do not infer capabilities from Block name.

---

# 36. Layout Modes

A compatible Block may support:

- static
- grid
- carousel

One Block can change layout without becoming another Block Definition.

Example:

Benefits
→ Grid / Carousel.

---

# 37. Carousel Capability

Carousel is a Landflow platform abstraction.

Potential settings:

- slides per view;
- gap;
- arrows;
- pagination;
- loop;
- autoplay;
- speed;
- responsive values.

---

# 38. Carousel Engine Independence

Block state must remain vendor-neutral.

Bad:

`swiper_loop = true`

Good:

`carousel.loop = true`

Implementation may later use Swiper, vanilla JS or another approved engine without changing the product schema.

---

# 39. Carousel Data Sources

Carousel may render:

- Repeater items;
- Site Vehicles;
- Gallery media;
- Promotions;
- Trims.

Carousel handles presentation, not ownership.

---

# 40. Gallery and Lightbox

Gallery = media collection.

Carousel = presentation mode.

Lightbox = media viewer.

Popup = business/content modal.

These are separate concepts.

---

# 41. Lightbox Capability

Lightbox may provide:

- enlarged media;
- next/previous;
- thumbnails;
- caption;
- item count.

Architecture remains independent from Fancybox or any specific library.

---

# 42. Popup Capability

A Block Action may open a reusable Site Popup.

Example:

Vehicle Card CTA
→ Open Popup
→ "Get Offer"

The Block does not own the Popup.

---

# 43. Action System

Supported conceptual Actions include:

- open_url
- open_page
- scroll_to
- open_popup
- phone
- email
- submit_form

Additional safe actions may be introduced later.

---

# 44. Common Action Editor

Schema may expose an Action field.

Designer should use one shared Action editor for all Blocks.

Example:

Action Type:
Open Popup

Popup:
Get Offer

This prevents inconsistent CTA configuration across Blocks.

---

# 45. Action Context

Actions may carry context.

Vehicle CTA context may include:

- site_vehicle
- trim
- selected color
- site_offer
- price
- page
- source block

Popup/Form can consume this context.

---

# 46. Action Security

Developer Blocks may only call approved actions.

Block configuration must never execute:

- arbitrary PHP;
- SQL;
- shell commands;
- unrestricted internal API calls.

---

# 47. Data Binding

Block fields may bind to structured Landflow data.

Example Vehicle Card:

- title → `vehicle.model.name`
- trim → `vehicle.trim.name`
- price → `offer.price`
- image → `vehicle.primary_image`
- colors → `vehicle.colors`

---

# 48. Manual Values vs Binding

A field may support:

- manual value;
- binding;
- fallback.

Schema declares allowed modes.

Example:

Marketing title:
manual.

Vehicle model title:
binding.

---

# 49. Approved Binding Sources

Potential sources:

- Site
- Page
- Site Vehicle
- Site Offer
- Automotive Trim
- Vehicle Color
- Characteristics
- Benefits
- Form/Action Context
- Workspace public data
- future normalized data source

Bindings use semantic paths, not raw database columns.

---

# 50. No Raw Database Access

Developer Block code must not directly query arbitrary Landflow tables.

Landflow supplies approved View Models/Data Contexts.

This protects:

- tenancy;
- security;
- schema evolution;
- Marketplace safety.

---

# 51. Data Contexts

Examples:

## PageContext
- Site public settings
- Page
- route metadata

## VehicleCardContext
- Site Vehicle
- Site Offer
- Images
- Colors
- Benefits
- public characteristics

## PopupContext
- source action
- selected vehicle
- selected offer

Renderer sees only safe data.

---

# 52. Binding Validation

Landflow validates compatibility.

Example:

Field type `price`
can bind to:
`offer.price`

It cannot bind to:
`vehicle.images`

Invalid binding must fail authoring/validation.

---

# 53. Binding Fallback

Platform resolvers handle fallback.

Example image resolution:

1. Site Vehicle override
2. Workspace Vehicle override
3. Global Catalog image
4. Block fallback

Developer should not reimplement this resolution logic.

---

# 54. Collection Blocks

Collection Blocks render multiple items.

Examples:

- Vehicle Grid
- Promotions
- Reviews
- Benefits

They may define:

- data source;
- filters;
- sorting;
- item renderer;
- layout.

---

# 55. Collection Sources

Initial sources should prioritize:

- manual Repeater;
- Site Vehicles.

Future:

- Site Offers;
- Trims;
- Workspace collections;
- normalized external sources.

---

# 56. Collection Filtering

Initial safe filters:

- active only;
- selected IDs;
- Make;
- Model;
- availability;
- manual inclusion.

Avoid arbitrary SQL/query language in customer configuration.

---

# 57. Collection Sorting

Potential modes:

- manual;
- name;
- price ascending;
- price descending.

Data source defines allowed sorts.

---

# 58. Item Renderer

Collection may use a compatible child Block Definition.

Example:

Vehicle Grid
→ Vehicle Card renderer.

Compatibility must be validated.

Do not allow an unrelated Block to become a Vehicle Grid card accidentally.

---

# 59. Controlled Composition

Landflow may support nested Blocks and Slots, but the first Designer should remain controlled.

Do not immediately recreate unrestricted DOM authoring.

Prefer schema-driven composition first.

---

# 60. Slots

Future Block may expose named Slots.

Example Hero:

- media
- content
- actions

Slots accept approved child Block types.

This gives flexibility without uncontrolled HTML.

---

# 61. Block Instance State

Block Instance stores Site-specific state:

- text;
- media references;
- Repeater items;
- layout mode;
- action references;
- data source config;
- bindings;
- responsive config.

State must validate against Block Schema.

---

# 62. JSON State Rules

Block Instance state may use JSON because it is schema-driven and dynamic.

Requirements:

- validated;
- versioned;
- migration-capable;
- safe;
- no secrets;
- no huge duplicated business datasets.

---

# 63. Forbidden Block State

Do not store inside Block state:

- CRM/API secrets;
- full automotive database rows;
- duplicated Site Offer records;
- Form submissions;
- user permissions;
- arbitrary executable code.

Use references and bindings.

---

# 64. Defaults

Block Version defines default state.

When inserted:

Block defaults
→ create Block Instance.

Template may provide its own initial instance values.

Customer edits become Site-owned state.

---

# 65. Template Block Defaults

Example:

Official Hero Block default:
"Welcome"

Dealer Template instance default:
"Новый Changan UNI-K"

Customer then changes:
"Changan UNI-K в наличии"

These layers must remain conceptually distinct.

---

# 66. Reset to Source

Future Designer may support:

- reset field to Block default;
- reset to Template initial value;
- remove local override;
- reset binding.

UI should communicate what source is active.

---

# 67. Deleting a Block Instance

Deleting an instance removes it from the Page Draft.

It must not delete:

- Block Definition;
- Site Vehicle;
- Popup;
- Form;
- Integration.

---

# 68. Archiving a Block Definition

Used Block Definitions should generally be archived/deprecated rather than hard-deleted.

Existing published Sites must continue rendering.

---

# 69. Deprecated Versions

A deprecated version may:

- continue rendering;
- show upgrade warning;
- be disabled for new insertion;
- offer migration.

Do not break customer production.

---

# 70. Block Upgrade

Future upgrade process:

v1 instance
→ v2 available
→ compatibility check
→ migration preview
→ upgrade
→ test
→ keep rollback path.

Not required for MVP, but design must allow it.

---

# 71. State Migration

A new Block Version may provide deterministic state migration.

Example:

v1:
`button_text`

v2:
`button.label`

Migration transforms state.

AI must not be required for production migrations.

---

# 72. Official Blocks

Landflow should ship reference-quality official Blocks such as:

- Header
- Hero
- Benefits
- Vehicle Grid
- Vehicle Card
- Price Block
- Gallery
- Specifications
- Equipment
- Credit
- Trade-in
- Contacts
- Footer

Official Blocks define best practices for developers.

---

# 73. Developer Authoring Workflow

Developer should eventually be able to:

Create Block
→ write/import markup/component
→ define Schema
→ configure capabilities
→ configure bindings
→ preview
→ test
→ submit for review.

---

# 74. Schema Editor

Developer Platform may provide a visual Schema editor.

Example:

Add Field
Type: Text
Key: title
Label: Заголовок
Required: Yes

Repeater:

Add Field
Type: Repeater
Key: benefits

Children:
- icon
- title
- description

---

# 75. AI-Assisted Schema Generation

Future workflow:

Developer imports markup/component.

AI identifies:

- headings;
- text;
- images;
- CTA;
- repeated cards;
- possible fields.

AI proposes Schema.

Developer reviews and confirms.

Confirmed Schema becomes source of truth.

---

# 76. AI-Assisted Repeater Detection

Example:

AI finds 3 structurally similar benefit cards.

Proposes:

`benefits[]`

- icon
- title
- description

Developer may:

- accept;
- rename;
- split fields;
- reject.

---

# 77. AI Security Boundary

AI cannot automatically:

- publish Marketplace item;
- grant privileged action;
- expose private binding;
- add unrestricted scripts;
- modify production Block Schema silently.

AI is an assistant, not a trust boundary.

---

# 78. Developer Preview Data

Developers need sample data.

Examples:

- sample vehicle;
- sample Site Offer;
- sample benefits;
- sample colors.

Use fixtures/synthetic preview data.

Never expose unrelated real customer Workspace data.

---

# 79. Block Validation

Before publication validate:

- valid Schema;
- unique field keys;
- supported types;
- valid conditions;
- valid bindings;
- valid capabilities;
- renderer compatibility;
- no forbidden privileged behavior.

---

# 80. Marketplace Review

There is no manual moderation queue: Blocks and Templates publish immediately after the automated ADR-008 §7 checks (D-120, owner-confirmed 2026-10-08). The list below describes what the automated checks and future quality signals should cover; `submit_marketplace_item` authorizes Marketplace listings of own products (P10-001), not approval.

Native trust approval (D-123) is not this review: it is a security step that approves one exact Draft revision / source hash before authored JS may run in a customer Site's host origin, performed by an actor with a separate approval capability. It adds no queue or moderation status.

Review should evaluate:

- visual quality;
- responsive quality;
- Schema quality;
- usability;
- security;
- performance;
- accessibility baseline;
- bindings;
- dependencies;
- compatibility.

---

# 81. Dependency Policy

Developers should not freely install arbitrary backend packages.

Prefer Landflow platform capabilities:

- Carousel
- Lightbox
- Popup
- Forms
- Icons
- Analytics
- Actions

This reduces bundle duplication and security risk.

---

# 82. JavaScript Policy

Marketplace Blocks should not rely on unrestricted arbitrary JavaScript execution.

When custom scripting becomes necessary, use approved/sandboxed runtime APIs.

Target (ADR-009): approved first-party Native JS follows `mount(root, props, api) => cleanup` — trusted code, not a sandbox; no `eval` / `new Function` / unvalidated dynamic imports; actions, popups and forms go through the Landflow host runtime. Untrusted or third-party code stays `sandboxed`.

A Block must never access:

- integration secrets;
- dashboard authentication state;
- another Workspace's data.

---

# 83. Styling Isolation

Block styles must not leak globally.

Preferred:

- scoped/block-local styling;
- platform design tokens;
- controlled style configuration.

One Block must not accidentally override another Block or Landflow dashboard UI.

---

# 84. Design Token Binding

Blocks should use Site Design Tokens by default.

Example:

Button:
`site.colors.primary`

Heading:
`site.typography.heading`

Changing Site branding updates compatible Blocks consistently.

---

# 85. Local Style Overrides

Where Schema allows, customer may override token-derived values.

UI should show:

Inherited
vs
Overridden.

Removing override restores token value.

---

# 86. Accessibility

Official and Marketplace Blocks should support baseline accessibility:

- semantic controls;
- image alt;
- keyboard navigation;
- Popup focus handling;
- Lightbox focus handling;
- Form labels.

---

# 87. Performance

Blocks should:

- avoid duplicate JS libraries;
- lazy-load appropriate images;
- use optimized media;
- avoid one API call per card;
- use platform Carousel/Lightbox;
- minimize client-side runtime.

---

# 88. SEO

SEO-relevant public Block content should be present in published output when possible.

Do not make all primary content dependent on client-only fetching.

Exact rendering strategy belongs in `PUBLISHING.md`.

---

# 89. Forms in Blocks

Block may reference a Landflow Form.

Example:

Hero
→ Form reference.

Block must never send directly to CRM.

All submissions use central Form System.

---

# 90. Popups in Blocks

Block references Popup through Action.

Popup is Site-owned and reusable.

The Block must not duplicate full Popup configuration for each button.

---

# 91. Analytics in Blocks

Blocks emit semantic Landflow events.

Examples:

- cta.click
- vehicle.card_click
- popup.open

Platform Analytics Layer forwards to Yandex Metrica.

Developers should not paste separate Metrica code for normal events.

---

# 92. Editing Permissions

Schema fields may later carry permission categories.

Examples:

title:
`edit_content`

layout:
`edit_design`

Sensitive Site Offer price should normally remain outside Block state and be controlled by `edit_prices`.

---

# 93. Automotive Price Rule

A Vehicle Card Block must display bound Site Offer price.

It must not make each card store its own independent text price.

Otherwise prices would diverge across the Site.

One Site Offer should drive all compatible Blocks.

---

# 94. Block Validation Tests

Required test categories:

1. Schema rejects unsupported field types.
2. Duplicate field keys rejected.
3. Repeater order persists.
4. Conditions evaluate deterministically.
5. Block Instance cannot mutate Definition.
6. New Version does not silently replace pinned Version.
7. Automotive bindings resolve safe Site data.
8. Cross-Workspace binding rejected.
9. Action cannot reference another Site's private Popup/Form.
10. Carousel config remains vendor-neutral.
11. Form uses central submission system.
12. Unsafe privileged code/config is rejected.
13. Archived Block remains renderable for existing Site.
14. Design token inheritance/override works.

---

# 95. Developer Testing

Developer preview should test:

- Desktop;
- Tablet;
- Mobile;
- empty content;
- long content;
- minimum/maximum Repeater items;
- missing optional image;
- automotive context;
- hover/focus;
- Actions;
- Carousel;
- Lightbox if supported.

---

# 96. Published Runtime Contract

Published runtime must receive:

- resolved Block Version;
- validated Block Instance state;
- safe public data context;
- resolved design tokens;
- permitted actions.

It must not receive:

- secrets;
- editor-only metadata;
- unauthorized Workspace data.

---

# 97. Cursor Rules

Cursor agents must never:

- make every Block a one-off admin implementation;
- infer runtime editability from HTML instead of Schema;
- store secrets in Block state;
- duplicate whole automotive records into Block JSON;
- let Blocks query raw tables;
- couple Schema to Swiper/Fancybox;
- make Block own Popup/Form business data;
- silently upgrade versions;
- allow arbitrary PHP/SQL/server execution;
- require AI for production runtime;
- duplicate automotive price as free-form text in every card.

---

# 98. Implementation Order

Recommended implementation sequence:

1. Block Definition
2. Block Version
3. basic Schema
4. Block Instance
5. Page ordering
6. Designer Properties Panel
7. common field types
8. Group
9. Repeater
10. conditions
11. responsive values
12. Action System
13. basic data binding
14. automotive bindings
15. collection sources
16. Grid/Carousel
17. Gallery/Lightbox
18. Popup/Form references
19. version migrations
20. Developer authoring
21. AI Schema assistance
22. Marketplace distribution

---

# 99. Source of Truth

This document refines:

- `PRODUCT.md`
- `ARCHITECTURE.md`
- `DATABASE.md`
- `AUTOMOTIVE_DATA.md`
- `PERMISSIONS.md`

If implementation requires changing these Block semantics, create an explicit architecture decision first.

---

# 100. Final Block Model

Landflow Block System should always be understood as:

Block Definition
→ Block Version
   ├── Schema
   │   ├── fields
   │   ├── groups
   │   ├── repeaters
   │   ├── conditions
   │   └── defaults
   ├── Capabilities
   │   ├── responsive
   │   ├── carousel
   │   ├── gallery/lightbox
   │   ├── actions
   │   └── data sources
   ├── Binding Contract
   │   └── approved semantic data
   └── Renderer
       ↓
Block Instance
   ├── state
   ├── data source
   ├── bindings
   ├── responsive config
   └── action references
       ↓
Rendered Site

Developer flow:

Developer
→ Create Block
→ Define or AI-propose Schema
→ Configure Capabilities/Bindings
→ Preview/Test
→ Submit
→ Review
→ Publish

Customer flow:

Designer
→ Add Block
→ Landflow reads Schema
→ Properties Panel generated
→ Customer edits safe fields
→ Bindings resolve Site data
→ Preview
→ Publish

Critical rules:

**Block Schema is deterministic runtime truth.  
Block Instances never modify Block Definitions.  
Blocks consume approved data contexts, not raw tables.  
Repeater and Group are first-class.  
Carousel, Lightbox, Popup, Form, Actions and Analytics are platform capabilities.  
Automotive pricing remains structured outside Block state.  
Versions never silently break existing Sites.  
AI assists authoring but does not control production runtime.**
