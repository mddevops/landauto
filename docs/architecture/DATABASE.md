# Landflow — Database Architecture

**Document:** `docs/architecture/DATABASE.md`  
**Status:** Database design source of truth  
**Purpose:** Define primary entities, relationships, ownership, normalization rules, JSON boundaries, deletion behavior, and future-proofing requirements before migrations are written.

---

# 1. Database Design Goals

The Landflow database must support:

- Users in multiple Workspaces;
- strict Workspace isolation;
- multiple Sites per Workspace;
- reusable Templates and Blocks;
- Site-specific Draft and Published states;
- a platform-owned automotive catalog;
- customer-owned automotive copies/overrides;
- Workspace Vehicle Library;
- Site Vehicles and Site Offers;
- structured colors, images, specifications, and equipment;
- reusable Popups and Forms;
- persisted Submissions;
- Workspace Integration Profiles with Site overrides;
- delivery routing and retries;
- anti-spam, blacklist, and rate-limit configuration;
- analytics configuration;
- domains and publishing;
- future Team, Developer Platform, and Marketplace features.

The database must remain relational as the primary source of truth.

Flexible JSON is allowed where a schema is inherently dynamic, but core business ownership must remain explicit and queryable.

---

# 2. General Naming Principles

Use:

- snake_case table names;
- plural table names;
- primary keys: bigint `id()` with `foreignId`; externally addressed entities also have a unique ULID `public_id` used in URLs, props and API (D-085, `decisions/ADR-001-primary-identifier-strategy.md`);
- explicit foreign keys;
- timestamps;
- soft deletes only where business recovery/history justifies them.

Avoid:

- generic `data` tables;
- polymorphism everywhere;
- storing full business domains as opaque JSON;
- duplicated credentials;
- duplicated Global Catalog structures inside every Site without source linkage.

---

# 3. Scope Classification

Every table/entity must be one of:

## Global Scope

Platform-owned and reusable across all Workspaces.

Examples:

- automotive catalog;
- public Templates;
- public Block Definitions;
- Marketplace catalog.

## Workspace Scope

Owned by one Workspace.

Examples:

- Workspace Integration Profiles;
- Workspace Assets;
- Workspace Vehicle Library;
- Workspace Members.

## Site Scope

Owned by one Site.

Examples:

- Pages;
- Block Instances;
- Site Vehicles;
- Site Offers;
- Popups;
- Forms;
- Site domains;
- Site settings.

---

# 4. Identity Tables

## users

Represents a human account.

Suggested fields:

- id
- name
- email
- email_verified_at
- password (nullable for OAuth-only Users; no artificial password)
- remember_token
- timestamps

Only email/password authentication fields used by Fortify belong here. No two-factor columns (D-095; the starter-kit `two_factor_*` columns and `passkeys` table are removed in P1-002). No provider-specific columns such as `yandex_id`.

`email` is required for every User, including Users created through Yandex OAuth.

Do not store Workspace role directly here.

## External Auth Identities

Scope: platform user-level (Global identity data, not Workspace or Site).

Implemented in P1-005A as `user_auth_identities`.

Suggested fields:

- id
- user_id
- provider (backed enum, e.g. `yandex`)
- provider_user_id
- provider_email (required; normalized email returned by the authenticated provider profile)
- timestamps

Constraints:

- unique `provider + provider_user_id`;
- unique `user_id + provider` (one identity per provider per User);
- FK `user_id` → `users`, cascade on User deletion (the identity is a disposable sign-in link, not history).

No OAuth access/refresh tokens are stored unless a later feature requires them (then encrypted).

Keys per D-085: bigint `id`, `foreignId('user_id')`, no `public_id` (internal-only). Linking to existing Users follows D-096.

---

# 5. Workspace Tables

## workspaces

Implemented in P1-003:

- id (`BIGINT UNSIGNED`)
- public_id (ULID, unique, immutable — D-085)
- name
- status (`App\Enums\WorkspaceStatus`: `active`, `suspended`; default `active`; indexed)
- timestamps

Notes:

- No `owner_user_id`: ownership is authoritative in `workspace_members` (`role = owner`). One source of truth; the "at least one Owner" rule (PERMISSIONS.md §22) is enforced by `WorkspaceMember` model events (P1-004; Eloquent operations only, no bulk updates of memberships).
- No soft deletes (not approved); Workspace deletion stays a guarded application flow (TENANCY.md §38).
- P1-005 account deletion removes an empty single-member personal Workspace; a Workspace with other members must retain another active Owner before the User can be deleted (TENANCY.md §41).
- Slug, default locale and timezone are added by the task that needs them.

## workspace_members

First-class domain entity (managed through UI / routes), not a technical pivot.

Implemented in P1-003:

- id (`BIGINT UNSIGNED`)
- public_id (ULID, unique, immutable)
- workspace_id (FK, cascade on Workspace delete)
- user_id (FK, restrict on User delete: account deletion must resolve memberships explicitly — sole-Owner guard, TENANCY.md §41)
- role (system role slug: `owner`, `admin`, `designer`, `content_editor`; catalog / permissions in P1-008)
- status (`App\Enums\WorkspaceMemberStatus`: `active`, `invited`, `suspended`; default `active`; only `active` grants access; removing a member deletes the row)
- joined_at nullable
- timestamps

Constraints / indexes:

- unique workspace_id + user_id
- index workspace_id + role (owner lookup)
- index user_id

Invitation fields (inviter, invited_at, token) are defined by the invite task (TENANCY.md §86–§87).

---

# 6. Roles and Permissions

Exact package/implementation is not fixed here.

Conceptually needed:

## roles

Potential scope:

- global system role definition;
- Workspace custom role later.

Fields may include:

- id
- workspace_id nullable
- name
- slug
- is_system
- timestamps

## permissions

Examples:

- edit_design
- edit_content
- edit_vehicles
- edit_prices
- manage_integrations
- publish_site

## role_permissions

Join table.

## site_member_access

Optional future table if Site access differs from Workspace membership.

Possible fields:

- site_id
- user_id or workspace_member_id
- role_id nullable
- explicit permissions/denials if needed

Do not overbuild custom ACL before the actual authorization design is approved in `PERMISSIONS.md`.

---

# 7. Subscription / Entitlement Tables

Exact billing provider is intentionally not part of this design.

## plans

Fields may include:

- id
- name
- slug
- status
- billing metadata
- timestamps

P1-009 foundation assigns an optional active Plan directly to a Workspace through internal `workspaces.plan_id`. This is a pre-billing entitlement source; business code consumes only the entitlement resolver, so a future Subscription model can replace the source without plan-name checks.

## plan_entitlements

Suggested fields:

- id
- plan_id
- key
- value_type
- value
- timestamps

Examples:

- max_sites = 2
- max_members = 1
- custom_domain = false
- version_history = false

## subscriptions

Suggested fields:

- id
- workspace_id
- plan_id
- provider
- provider_subscription_id
- status
- starts_at
- renews_at
- ends_at
- timestamps

Business code should read capabilities through an Entitlement/Capability service rather than query plan names everywhere.

---

# 8. Site Tables

## sites

Suggested fields:

- id
- workspace_id
- folder_id nullable
- name
- slug
- status
- template_id nullable
- template_version_id nullable
- locale
- timezone
- landflow_subdomain
- current_draft_version_id nullable
- current_published_version_id nullable
- timestamps
- deleted_at if approved

P1-010 implements only the foundation subset: bigint `id` / required `workspace_id`, unique ULID `public_id` (ADR-001), `name`, `active` / `archived` status and timestamps. Workspace hard deletion is restricted while Sites exist. All other suggested fields remain deferred to their owning tasks.

P9-013 (D-119) adds `site_type` (`multi_page` / `landing` / `quiz` / `chat_selection`, `App\Enums\SiteType`, default `multi_page` for pre-existing rows), fixed at creation (model-enforced).

Important:

Site owns commercial configuration.

Site must not own Global Catalog master records.

---

# 9. Site Folders

## site_folders

Fields:

- id
- workspace_id
- parent_id nullable if nested folders are allowed
- name
- sort_order
- timestamps

Folders are organizational only.

---

# 10. Site Settings

Avoid one giant unstructured JSON blob for everything.

Use dedicated tables where data is operational or frequently queried.

A limited `site_settings` table may exist for flexible, low-risk settings.

## site_settings

Possible fields:

- id
- site_id
- key
- value_json
- timestamps

Use only for truly flexible settings.

Do not store:

- prices;
- integration secrets;
- domains;
- permissions;
- form submissions

in this table.

---

# 11. Site Design Tokens

## site_design_tokens

Potential design:

- id
- site_id
- key
- type
- value_json
- timestamps

Examples:

- primary_color
- secondary_color
- heading_font
- button_radius
- container_width

Alternative implementations may normalize common tokens and use JSON for advanced ones.

---

# 12. Pages

## pages

Fields:

- id
- site_id
- parent_id nullable
- title
- slug
- status
- sort_order
- is_home
- timestamps
- deleted_at optional

Unique constraint:

- site_id + slug

Page SEO can be stored in a related table rather than mixed into Page structure.

---

# 13. Page SEO

## page_seo

Suggested fields:

- id
- page_id
- meta_title
- meta_description
- h1_override nullable
- canonical_url nullable
- robots_index
- robots_follow
- og_title nullable
- og_description nullable
- og_image_asset_id nullable
- timestamps

Dynamic SEO templates can be added later.

---

# 14. Block Definitions

## block_definitions

Represents reusable Block types.

Implemented fields (P2-002, ownership P9-003):

- id
- public_id ULID, unique, immutable
- name
- slug unique across all scopes, immutable (lowercase ASCII, digits, single inner hyphens, 3–60 for new authoring)
- owner_scope (`platform` / `developer` / `workspace_private`), immutable
- developer_profile_id nullable FK (restrict), immutable
- workspace_id nullable FK (restrict), immutable
- created_by_user_id nullable FK (null on User delete), immutable audit identity
- updated_by_user_id nullable FK (null on User delete), audit identity
- category string(32), `App\Enums\BlockCategory`, default `other`, editable (P9-004; official Blocks backfilled)
- access_mode string(16), `App\Enums\CatalogAccessMode` (`free` default / `entitlement` / `paid` / `admin_grant`), D-121 (P9-014)
- access_entitlement string(64) nullable — boolean `App\Enums\Entitlement` key, set only for `entitlement`
- site_price_minor unsigned bigint nullable + workspace_price_minor unsigned bigint nullable + price_currency char(3) nullable — ADR-004; `paid` needs at least one price (each > 0) and a supported currency, other modes keep all three null
- timestamps

## catalog_licenses

Catalog license (D-121, P9-014 / P9-015): one catalog item for one Site or one Workspace. Effective access for a Site = its Site licenses OR its Workspace's licenses; rows are never copied per Site.

- id
- public_id ULID, unique
- scope string(16), `App\Enums\CatalogLicenseScope` (`site` / `workspace`)
- site_id nullable FK (cascade) — set only for scope `site`
- workspace_id nullable FK (cascade) — set only for scope `workspace`
- block_definition_id nullable FK (cascade)
- template_id nullable FK (cascade) — exactly one of `block_definition_id` / `template_id`; exactly one target matching the scope (model guard)
- source string(16), `App\Enums\CatalogLicenseSource` (`purchase` / `admin_grant`), independent of scope; purchases are created only by the future billing flow (P10-005)
- granted_by_user_id nullable FK (null on User delete), audit identity
- timestamps
- unique (site_id, block_definition_id), (site_id, template_id), (workspace_id, block_definition_id), (workspace_id, template_id); rows are never updated — revoke = delete

## site_block_version_grants

Installed Block Version grandfathering (D-122): internal provenance, no `public_id`, no browser endpoint.

- id
- site_id FK (cascade)
- block_version_id FK (restrict)
- created_at nullable
- unique (site_id, block_version_id)
- created after a successful current access check on add / Template install (and on version restore without a re-check); publishing reads but never writes it; license revocation never deletes it; existing `page_blocks` were backfilled by the migration

## block_drafts

Block Studio Draft (P9-004, ADR-008), one per Block Definition, created on the first save:

- id
- block_definition_id unique FK (cascade)
- html, css, js, schema_source mediumText — raw sources, ≤ 64 KB (bytes) each; `schema_source` may be invalid JSON while drafting
- preview_data json nullable (P9-005)
- revision unsigned int — optimistic concurrency, +1 per save
- updated_by_user_id nullable FK (null on User delete), audit identity
- timestamps

Saving a Draft never creates or changes a Block Version.

Scope rules (D-117), enforced by the model:

- `platform`: developer_profile_id = null, workspace_id = null
- `developer`: developer_profile_id set, workspace_id = null
- `workspace_private`: workspace_id set, developer_profile_id = null

`is_official` was removed; existing definitions were backfilled as `platform`. Later candidates (not implemented): status / review state, Marketplace listing relationship.

---

# 15. Block Versions

## block_versions

Fields may include:

- id
- block_definition_id
- version
- schema_json
- renderer_reference
- metadata_json nullable
- status
- created_by_user_id nullable
- created_at

Implemented (P9-006, ADR-008): `runtime` string(16) (`App\Enums\BlockRuntime`: `official` default / backfill, `sandboxed`); `html`, `css`, `js` mediumText nullable — the immutable source snapshot, set for every `sandboxed` version and null for `official`; `published_by_user_id` nullable FK (null on User delete), audit identity. Sandboxed versions are created only by Studio publishing (`BlockPublisher`, semantic version auto-incremented); since P9-009 platform-owned (and since P9-014 Developer-owned, subject to catalog access) sandboxed versions are placeable and their sources are copied into the Published Version manifest (`blocks[].sandbox`).

Important:

`schema_json` is a valid JSON use-case because Block Schema is intentionally dynamic and typed.

Do not store customer instance content here.

---

# 16. Page Block Instances

## page_blocks

Fields may include:

- id
- page_id
- block_definition_id
- block_version_id
- parent_id nullable
- sort_order
- state_json
- responsive_json nullable
- visibility_json nullable
- timestamps

`state_json` is appropriate because Block instance values vary according to Block Schema.

Requirements:

- validate state against Block Schema;
- preserve schema/version metadata;
- never trust arbitrary state keys at runtime;
- support future migrations.

---

# 17. Templates

## templates

Fields:

- id
- developer_id nullable
- name
- slug
- description
- category_id nullable
- status
- is_official
- pricing_type
- price nullable
- current_version_id nullable
- timestamps

Implemented so far: `public_id`, `name`, `slug`, `is_official`, timestamps and (P9-013, D-119) `site_types` — JSON list of compatible Site types; empty / null means the Template is not offered at Site creation (the legacy official `blank` Template).

P9-007: `owner_scope` (`platform` | `developer`, default `platform`) + `developer_profile_id` (FK restrict, required iff developer), `created_by_user_id` / `updated_by_user_id` (FK null on delete). Ownership and slug are immutable. Draft content:

- `template_pages`: `public_id`, `template_id` (cascade), `title`, `slug` (unique per Template), `sort_order`, `is_home` (TRUE / NULL, unique per Template).
- `template_blocks`: `public_id`, `template_page_id` (cascade), `block_version_id` (restrict, never workspace-private), `sort_order`, `is_hidden`, `state_json` (validated by the Block Schema; references only to Pages / Blocks of the same Template).

P9-015 (D-121): `access_mode` string(16) default `free`, `access_entitlement` string(64) nullable, `site_price_minor` / `workspace_price_minor` unsigned bigint nullable, `price_currency` char(3) nullable — same consistency rules as `block_definitions` (`CatalogAccessMode::fieldsMatch`). A Template with at least one `template_versions` row is offered at Site creation; installing copies the latest version into new Pages / Block Instances and records `site_block_version_grants` for every copied Block Version (D-122).

## template_versions

Potential fields:

- id
- template_id
- version
- manifest_json
- preview metadata
- status
- created_at

`manifest_json` may describe Site bootstrap composition:

- pages;
- Block versions;
- default content;
- design tokens;
- Forms;
- Popups.

When instantiated, data must become Site-owned.

Implemented (P9-007): `template_id`, `version` (`1.0.0`, then minor bumps), `content_json` (snapshot `{pages: [{key, title, slug, is_home, blocks: [{key, block_version_id, is_hidden, state}]}]}`), `published_by_user_id`, timestamps. Rows are immutable (update / delete throw).

---

# 18. Template Categories

## template_categories

Fields:

- id
- name
- slug
- parent_id nullable
- sort_order
- status
- timestamps

---

# 19. Global Automotive Catalog Overview

> **Catalog V2 (X-016) — authoritative.** The exact catalog schema is `docs/architecture/AUTO_CATALOG_SCHEMA.md` (ten `auto_*` tables: marks, models, generations, series, modifications, equipments, characteristics, characteristic_values, options, option_values). The catalog is a **separate physical database** on the Laravel connection `catalog` (local suggestion `landflow_catalog`), with its own migration directory. There are no SQL foreign keys between the main database and the catalog database: main rows store immutable catalog `public_id` values (`site_vehicles.catalog_series_public_id`, `site_offers.catalog_equipment_public_id`, `series_media_sets.catalog_series_public_id`) and the application validates them. The platform Series Media Library (media sets and images by angle) lives in the main database and is not part of the catalog. The `automotive_*` naming, Trim/Configuration, catalog colors/swatches/color availability and site vehicle colors below (§19–§33, §41) are **SUPERSEDED** and kept only for history. See `AUTOMOTIVE_DATA.md` §0.

The Global Catalog should be normalized.

Core hierarchy:

automotive_makes
→ automotive_models
→ automotive_series
→ automotive_generations
→ automotive_modifications
→ automotive_trims

Naming can be shortened later, but explicit domain naming is recommended until collisions are evaluated.

---

# 20. Automotive Makes — SUPERSEDED (see §19 / AUTOMOTIVE_DATA §0)

## automotive_makes

Fields may include:

- id
- name
- slug
- country_code nullable
- logo_asset/media reference nullable
- status
- sort_order
- timestamps

---

# 21. Automotive Models

## automotive_models

Fields:

- id
- make_id
- name
- slug
- status
- sort_order
- timestamps

Unique constraints should prevent accidental duplicates within a Make.

---

# 22. Automotive Series — SUPERSEDED (see §19 / AUTOMOTIVE_DATA §0)

## automotive_series

Fields:

- id
- model_id
- name
- slug nullable
- status
- sort_order
- timestamps

Series is optional for brands/models that use it.

Do not force meaningless placeholder rows if the domain does not require Series.

---

# 23. Automotive Generations

## automotive_generations

Fields:

- id
- model_id
- series_id nullable
- name
- code nullable
- production_start_year nullable
- production_end_year nullable
- status
- sort_order
- timestamps

---

# 24. Automotive Modifications

## automotive_modifications

Represents technical/powertrain/body variants.

Fields may include:

- id
- generation_id
- name
- engine_name nullable
- engine_volume nullable
- fuel_type nullable
- transmission nullable
- drivetrain nullable
- power_hp nullable
- body_type nullable
- doors nullable
- status
- timestamps

Some technical data may instead use the characteristic system below.

Avoid duplicating the same technical fact both as a dedicated column and a characteristic without a clear reason.

---

# 25. Automotive Trims / Configurations — SUPERSEDED (see §19 / AUTOMOTIVE_DATA §0)

## automotive_trims

Fields:

- id
- modification_id
- name
- code nullable
- status
- sort_order
- timestamps

A Trim/Configuration is the selectable customer/catalog commercial configuration.

Global Catalog does not store customer Site price here.

---

# 26. Automotive Characteristics Dictionary

## automotive_characteristics

Defines characteristics.

Fields:

- id
- category_id nullable
- name
- slug
- data_type
- unit nullable
- sort_order
- status
- timestamps

Examples:

- length
- width
- height
- acceleration_0_100
- fuel_consumption_combined
- trunk_volume

---

# 27. Characteristic Categories — SUPERSEDED (see §19 / AUTOMOTIVE_DATA §0)

## automotive_characteristic_categories

Examples:

- Dimensions
- Engine
- Performance
- Economy
- Safety

Fields:

- id
- name
- slug
- sort_order
- timestamps

---

# 28. Characteristic Values — SUPERSEDED (see §19 / AUTOMOTIVE_DATA §0)

Characteristic values may be attached at the most appropriate level.

Recommended flexible relational structure:

## automotive_characteristic_values

Fields:

- id
- characteristic_id
- entity_type
- entity_id
- value_string nullable
- value_number nullable
- value_boolean nullable
- value_json nullable
- timestamps

However, polymorphic design must be used carefully.

Alternative explicit tables per level may be preferable if query/performance complexity warrants it.

This decision should be finalized in implementation design.

---

# 29. Automotive Options / Equipment

## automotive_options

Dictionary of known equipment items.

Fields:

- id
- category_id nullable
- name
- slug
- description nullable
- status
- timestamps

Examples:

- heated steering wheel
- adaptive cruise control
- panoramic roof

## automotive_option_categories

Fields:

- id
- name
- slug
- sort_order

## automotive_trim_options

Join table:

- trim_id
- option_id
- value/status if needed
- metadata_json nullable

---

# 30. Automotive Colors — SUPERSEDED (see §19 / AUTOMOTIVE_DATA §0)

## automotive_colors

Represents manufacturer/catalog colors.

Fields may include:

- id
- make_id nullable
- name
- manufacturer_code nullable
- type
- status
- timestamps

Do not assume one HEX value per color.

---

# 31. Automotive Color Swatches — SUPERSEDED (see §19 / AUTOMOTIVE_DATA §0)

## automotive_color_swatches

Allows one or multiple visible color layers.

Fields:

- id
- color_id
- position
- hex nullable
- image/texture reference nullable
- proportion nullable
- sort_order
- timestamps

Example:

White + Black Roof:

- swatch 1: white
- swatch 2: black

This allows two-tone and future multi-tone colors.

---

# 32. Trim/Vehicle Color Availability — SUPERSEDED (see §19 / AUTOMOTIVE_DATA §0)

## automotive_trim_colors

Join table:

- trim_id
- color_id
- status
- sort_order
- metadata_json nullable

If availability is actually defined higher/lower in the hierarchy for some brands, the model may evolve.

---

# 33. Automotive Images — SUPERSEDED (see §19 / AUTOMOTIVE_DATA §0)

## automotive_images

Fields may include:

- id
- trim_id nullable
- generation_id nullable
- modification_id nullable
- color_id nullable
- media_id
- view_type nullable
- angle nullable
- has_transparent_background
- sort_order
- status
- timestamps

Examples of `view_type`:

- front
- front_3_4
- side
- rear
- interior
- detail

Avoid using filename to infer business meaning.

---

# 34. Media Storage

## media

Generic media metadata.

Fields may include:

- id
- owner_scope_type
- owner_scope_id nullable
- disk
- path
- filename
- mime_type
- size
- width nullable
- height nullable
- checksum nullable
- metadata_json nullable
- timestamps

Important:

Business entities such as `automotive_images` reference `media`.

This keeps storage metadata separate from automotive semantics.

Exact ownership design may be refined.

---

# 35. Workspace Assets

## workspace_assets

Fields:

- id
- workspace_id
- media_id
- name
- asset_type
- folder/group nullable
- metadata_json nullable
- timestamps

Used for:

- logos
- banners
- backgrounds
- icons
- promotional files

---

# 36. Site Assets

## site_assets

May be needed for explicitly Site-owned uploads.

Fields:

- id
- site_id
- media_id
- name
- asset_type
- metadata_json nullable
- timestamps

Potential simplification:

Workspace assets may support Site tagging rather than a separate table.

Final choice should depend on UX requirements.

---

# 37. Workspace Vehicle Library

## workspace_vehicles

Represents a customer-owned reusable vehicle derived from Global Catalog.

Suggested fields:

- id
- workspace_id
- source_make_id nullable
- source_model_id nullable
- source_generation_id nullable
- source_modification_id nullable
- source_trim_id nullable
- custom_name nullable
- custom_description nullable
- status
- source_snapshot_version nullable
- timestamps
- deleted_at optional

This record does not mutate Global Catalog.

---

# 38. Workspace Vehicle Media Overrides

## workspace_vehicle_images

Fields:

- id
- workspace_vehicle_id
- source_automotive_image_id nullable
- color_id nullable
- media_id
- view_type nullable
- sort_order
- timestamps

This allows customer-specific photography.

---

# 39. Workspace Vehicle Overrides

Avoid duplicating every Global Catalog field immediately.

Possible approaches:

1. explicit override columns for commonly edited fields;
2. override JSON for infrequent flexible fields;
3. copied snapshot tables.

Recommended:

Keep source references + explicit customer fields + a controlled override structure.

Exact implementation requires `AUTOMOTIVE_DATA.md`.

---

# 40. Site Vehicles — SUPERSEDED (see §19 / AUTOMOTIVE_DATA §0)

## site_vehicles

Represents a vehicle/configuration used on one Site.

Suggested fields:

- id
- site_id
- workspace_vehicle_id nullable
- source_trim_id nullable
- custom_name nullable
- custom_description nullable
- status
- sort_order
- selected_color_id nullable
- timestamps
- deleted_at optional

A Site Vehicle may originate from:

- Workspace Vehicle;
- direct Global Catalog import.

---

# 41. Site Vehicle Colors — SUPERSEDED (see §19 / AUTOMOTIVE_DATA §0)

A Site may limit available colors or override them.

Potential table:

## site_vehicle_colors

Fields:

- id
- site_vehicle_id
- source_color_id nullable
- custom_name nullable
- status
- sort_order
- metadata_json nullable
- timestamps

Do not alter Global Catalog color rows.

---

# 42. Site Vehicle Images — SUPERSEDED (see §19 / AUTOMOTIVE_DATA §0)

## site_vehicle_images

Fields:

- id
- site_vehicle_id
- site_vehicle_color_id nullable
- source_automotive_image_id nullable
- source_workspace_vehicle_image_id nullable
- media_id nullable
- view_type nullable
- sort_order
- is_override
- timestamps

The resolver decides fallback order:

Site
→ Workspace
→ Global Catalog

---

# 43. Site Offers

## site_offers

Suggested fields:

- id
- site_vehicle_id
- currency
- rrp nullable
- price nullable
- monthly_payment nullable
- status
- availability_status nullable
- badge_text nullable
- cta_label nullable
- metadata_json nullable
- timestamps

There may be one active Offer per Site Vehicle initially.

Future support for multiple campaigns/offers should remain possible.

---

# 44. Benefit Types

## benefit_types

Potential global or Workspace-configurable dictionary.

Fields:

- id
- workspace_id nullable
- name
- slug
- value_type
- status
- timestamps

Global examples:

- trade_in
- credit
- leasing
- direct_discount

Workspace-defined custom types may be allowed later.

---

# 45. Site Offer Benefits

## site_offer_benefits

Fields:

- id
- site_offer_id
- benefit_type_id
- label_override nullable
- amount nullable
- percentage nullable
- value_text nullable
- sort_order
- status
- metadata_json nullable
- timestamps

This avoids hardcoding every future benefit into `site_offers`.

---

# 46. Vehicle Copy History

Optional but useful later:

## vehicle_imports

Could record:

- source scope/type;
- source ID;
- destination Site/Workspace;
- imported_by;
- imported_at;
- import options.

Not mandatory for MVP.

---

# 47. Popups

## popups

Fields:

- id
- site_id
- name
- status
- settings_json
- timestamps

Popup internal composition may either:

- use Block Instances;
- use a simplified popup content schema.

Preferred long-term direction:

reuse Block infrastructure where safe.

---

# 48. Popup Blocks

If Popups use normal Block composition:

## popup_blocks

Fields similar to page_blocks:

- id
- popup_id
- block_definition_id
- block_version_id
- sort_order
- state_json
- responsive_json nullable
- timestamps

This should be evaluated to avoid duplicate rendering systems.

---

# 49. Forms

## forms

Fields:

- id
- site_id
- name
- slug/key
- status
- settings_json nullable
- timestamps

A Form is reusable within its Site.

Later Workspace-level reusable Forms may be introduced explicitly rather than assumed.

---

# 50. Form Fields

## form_fields

Fields:

- id
- form_id
- key
- type
- label
- placeholder nullable
- is_required
- validation_json nullable
- settings_json nullable
- sort_order
- timestamps

Examples:

- name
- phone
- email
- consent
- text
- select
- hidden/context field

---

# 51. Form Routing

## form_routes

Fields:

- id
- form_id
- destination_type
- integration_profile_id nullable
- site_integration_binding_id nullable
- email_destination nullable
- mapping_json nullable
- settings_json nullable
- status
- sort_order
- timestamps

One Form may have many routes.

---

# 52. Submissions

## submissions

Fields may include:

- id
- site_id
- form_id
- status
- ip_address
- phone_normalized nullable
- email_normalized nullable
- page_id nullable
- popup_id nullable
- source_block_id nullable
- site_vehicle_id nullable
- site_offer_id nullable
- utm_json nullable
- context_json nullable
- submitted_at
- timestamps

Important:

Submission must be persisted before external delivery.

---

# 53. Submission Values

Two possible strategies:

## Option A — JSON payload

`payload_json`

Advantages:

- simple;
- flexible.

## Option B — normalized values

`submission_values`

Fields:

- submission_id
- field_key
- value_text/value_json

Recommendation:

Use a validated JSON snapshot for raw submitted Form fields plus explicit indexed columns for critical query dimensions such as:

- phone_normalized;
- site_id;
- form_id;
- submitted_at;
- status.

This gives flexibility without sacrificing important querying.

---

# 54. Submission Delivery

## submission_deliveries

Fields:

- id
- submission_id
- form_route_id
- destination_type
- status
- attempt_count
- next_retry_at nullable
- delivered_at nullable
- last_attempt_at nullable
- last_http_status nullable
- last_error_code nullable
- last_error_message_safe nullable
- timestamps

Never store secrets in error fields.

---

# 55. Delivery Attempts

Optional detailed table:

## submission_delivery_attempts

Fields:

- id
- submission_delivery_id
- attempt_number
- started_at
- finished_at
- status
- http_status nullable
- response_summary_json nullable
- error_code nullable
- safe_error_message nullable
- timestamps

Useful for debugging/retry history.

Can be introduced when needed.

---

# 56. Workspace Integration Profiles

## integration_profiles

Fields:

- id
- workspace_id
- name
- provider_type
- provider_key nullable
- base_url nullable
- auth_type
- encrypted_credentials
- settings_json nullable
- status
- timestamps
- deleted_at optional

Important:

`encrypted_credentials` must never be serialized directly to the browser.

---

# 57. Site Integration Bindings

## site_integration_bindings

Fields:

- id
- site_id
- integration_profile_id
- name nullable
- overrides_json
- mapping_defaults_json nullable
- status
- timestamps

Examples in `overrides_json`:

- site_id
- dealer_id
- source_id

No need to duplicate shared token/secret.

---

# 58. Integration Provider Definitions

Optional future global registry:

## integration_providers

Fields:

- id
- key
- name
- schema_json
- credential_schema_json
- mapping_schema_json
- status

Useful when Landflow supports many standardized CRM connectors.

Custom API/webhook profiles may not require a predefined provider record.

---

# 59. Security Settings

Site-specific anti-spam/security configuration may be stored in:

## site_security_settings

Fields:

- id
- site_id
- captcha_enabled
- captcha_provider
- rate_limit_settings_json
- duplicate_settings_json
- security_options_json
- timestamps

Do not store provider secrets here if reusable Workspace credentials exist.

---

# 60. Blacklist Entries

## blacklist_entries

Fields:

- id
- scope_type
- workspace_id nullable
- site_id nullable
- entry_type
- normalized_value
- reason nullable
- source
- expires_at nullable
- created_by_user_id nullable
- timestamps

Scopes:

- global
- workspace
- site

Entry types:

- ip
- phone

Constraints must enforce valid scope combinations.

---

# 61. Rate Limiting Storage

Persistent business configuration belongs in database.

High-frequency rate-limit counters should generally use cache/Redis rather than insert a database row for every request.

Database stores policy.

Cache stores counters.

---

# 62. Captcha Configuration

Yandex SmartCaptcha public identifiers may live in Site/Workspace configuration.

Sensitive server key must be stored encrypted and server-side.

Do not expose secret captcha key to public frontend.

Exact table placement will depend on whether credentials are:

- global platform credentials;
- Workspace credentials;
- Site credentials.

---

# 63. Analytics Settings

## site_analytics_settings

Fields:

- id
- site_id
- yandex_metrica_enabled
- yandex_metrica_counter_id nullable
- webvisor_enabled
- event_tracking_json nullable
- timestamps

Potential future provider-specific settings can be moved to an adapter configuration model if required.

---

# 64. Internal Analytics Events

Do not immediately store every page/click event in the relational database.

Primary behavior:

Frontend semantic event
→ analytics adapter
→ Yandex Metrica

If internal analytics is added later, use a separate event pipeline/storage appropriate for volume.

---

# 65. Domains

## site_domains

Fields:

- id
- site_id
- hostname
- type
- status
- is_primary
- verification_token nullable
- verified_at nullable
- ssl_status nullable
- last_checked_at nullable
- timestamps

Types:

- landflow_subdomain
- custom

Unique hostname constraint required globally.

---

# 66. Publishing Tables

Publishing requires dedicated data rather than a boolean `is_published`.

## site_versions

Potential fields:

- id
- site_id
- version_number
- type
- snapshot_json or snapshot reference
- created_by_user_id nullable
- created_at

Types may include:

- draft checkpoint;
- published;
- restore.

Exact implementation will be designed in `PUBLISHING.md`.

---

# 67. Publications

## publications

Fields:

- id
- site_id
- site_version_id
- published_by_user_id
- status
- started_at
- completed_at nullable
- error_message_safe nullable
- metadata_json nullable
- timestamps

A Site references current published version.

---

# 68. Draft Storage Strategy

Three possible strategies:

1. Live normalized Draft tables + Published snapshot.
2. Versioned normalized records.
3. Full JSON document snapshots.

Recommended initial direction:

Keep editable business entities normalized and maintain publication snapshots/version references.

Do not duplicate the entire application model into parallel `draft_*` tables without strong reason.

Exact publishing storage will be decided separately.

---

# 69. Developer Profiles

## developer_profiles

Fields:

- id
- public_id ULID, unique, immutable
- user_id required, unique, immutable (restrict on User delete)
- display_name
- slug unique (lowercase ASCII, digits, single inner hyphens, 3–60)
- status (`active` / `suspended`)
- bio nullable
- payout metadata later (Marketplace work)
- timestamps

No Workspace, plan or subscription relation (D-093). A User may be both customer and developer. Implemented in P9-001.

## developer_profile_permissions

Internal explicit creator grants (D-118, P9-002); no `public_id`.

- id
- developer_profile_id FK (cascade; profiles are not hard-deleted)
- permission (`create_blocks` / `create_templates` / `submit_marketplace_item`)
- timestamps

Unique `developer_profile_id + permission`. Rows survive suspension. Unknown keys never grant access. Existing profiles were backfilled with the current defaults.

---

# 70. Marketplace Listings

## marketplace_listings

Implemented in P10-001 (`App\Models\MarketplaceListing`). A listing is the public Marketplace card of one canonical product; it owns presentation only.

Fields:

- id (internal; never in routes or Inertia props)
- public_id (ULID, unique, route key)
- product_type (`block` / `template`, `App\Enums\MarketplaceProductType`; immutable)
- block_definition_id nullable, unique, FK `block_definitions` RESTRICT (immutable)
- template_id nullable, unique, FK `templates` RESTRICT (immutable)
- developer_profile_id nullable, FK `developer_profiles` RESTRICT (immutable); NULL = official Landflow listing (no fake profile)
- title (≤ 120, trimmed plain text)
- slug (3–80, `^[a-z0-9]+(?:-[a-z0-9]+)*$`, globally unique, immutable)
- description nullable (plain text ≤ 5000, HTML rejected)
- status (`draft` / `published`, `App\Enums\MarketplaceListingStatus`; default `draft`, indexed)
- published_at nullable
- created_by_user_id / updated_by_user_id nullable, FK `users` nullOnDelete
- timestamps

Rules (enforced by the model `saving` / `updating` hooks plus unique indexes; no DB CHECK constraint for cross-database portability):

- Exactly one of `block_definition_id` / `template_id` is set and matches `product_type`; the referenced product must exist.
- At most one listing per product (unique FK columns).
- The owner is derived from the product by the server: platform-owned product → `developer_profile_id = NULL`; developer-owned product → that product's profile. `workspace_private` Blocks cannot be listed. The browser never supplies an owner.
- No pricing, currency, access mode, entitlement or version columns: price and access stay on the canonical product (`access_mode`, `site_price_minor`, `workspace_price_minor`, `currency`, `required_entitlement_key`) per D-121. Installed Versions follow D-122.
- `published_at` is set exactly while `status = published` and holds the start of the current publication; unpublishing clears it.
- Draft listings may exist before the product has a version. Publishing requires ≥ 1 published product version and `access_mode != admin_grant` (licence-only private distribution).
- No soft deletes; listings are not deleted in P10-001.

Public visibility (`MarketplaceListing::scopePubliclyVisible`, fail-closed, recomputed on every read): status `published`, the product exists, has ≥ 1 version, is not `admin_grant`, a listed Block is `platform` / `developer` scope, and the Developer Profile (if any) is `active`. A suspended Developer keeps the listing rows unchanged; they are just hidden until reactivation.

Product types:

- template
- block

---

# 71. Marketplace Reviews / Moderation

Manual moderation is not part of the architecture (D-120): Blocks and Templates publish after the automated ADR-008 checks, and listings publish without an approval queue. There is no `marketplace_reviews` table and no moderation status.

Customer reviews / ratings are `P10-007` (DEFERRED). If they are ever added, they get separate tables designed in that task.

---

# 72. Purchases and Licenses

Future tables may include:

## marketplace_orders
## marketplace_order_items

Not required for MVP. Licenses already live in `catalog_licenses` (D-121); a future purchase creates a `purchase`-source row there instead of a separate license table.

Architecture should avoid assumptions that all premium content is globally unlocked.

---

# 73. Feature Flags

## feature_flags

Fields:

- id
- key
- description nullable
- default_state
- configuration_json nullable
- timestamps

Optional targeting tables can be introduced later.

Feature flags are not subscription entitlements.

---

# 74. Audit Log

Potential central audit table:

## audit_logs

Fields:

- id
- workspace_id nullable
- site_id nullable
- user_id nullable
- action
- entity_type
- entity_id
- before_json nullable
- after_json nullable
- metadata_json nullable
- created_at

Use selectively for meaningful business/sensitive events.

Avoid logging secrets.

---

# 75. Important Indexes

At minimum, implementation should evaluate indexes for:

- workspace_id
- site_id
- user_id
- form_id
- submitted_at
- phone_normalized
- integration_profile_id
- source catalog IDs
- status
- slug combinations
- hostname
- automotive parent foreign keys

Index design should be informed by actual query patterns.

Do not blindly index every column.

---

# 76. Unique Constraints

Examples:

- users.email
- workspace_members(workspace_id, user_id)
- sites(workspace_id, slug) if slugs are Workspace-unique
- pages(site_id, slug)
- site_domains.hostname
- automotive model uniqueness within make
- automotive trim uniqueness based on approved catalog rules

Catalog uniqueness rules require care because manufacturer naming is inconsistent.

---

# 77. Foreign Key Delete Rules

Default rule:

Prefer `RESTRICT` or deliberate soft deletion for high-value shared data.

Avoid broad cascading deletion across business boundaries.

Examples:

Deleting Template:
- must not delete Sites instantiated from it.

Deleting Global Catalog Trim:
- must not delete customer Site Vehicles.

Deleting Integration Profile:
- block deletion or require reassignment if Site bindings exist.

Deleting Workspace:
- destructive operation requires explicit workflow and may cascade within that Workspace after safety checks.

Deleting Site:
- may soft-delete Site-scoped data initially.

---

# 78. Global Catalog Delete Strategy

Global Catalog items should generally use:

- status;
- archived/inactive state;

rather than destructive hard deletion once customer references exist.

Historical source references must remain resolvable when practical.

---

# 79. Source Snapshot Strategy

Because Global Catalog may evolve, imported Workspace/Site vehicles may need source snapshot metadata.

Possible fields:

- source_catalog_version;
- imported_at;
- source_snapshot_json for selected canonical fields.

Do not automatically duplicate the entire Global Catalog tree unless needed.

This design will be refined in `AUTOMOTIVE_DATA.md`.

---

# 80. Copy Semantics

When copying Site A → Site B:

copy customer-owned records.

Examples:

- Site Vehicles;
- Site Offers;
- Block Instance state;
- Form definitions;
- Site Integration Binding references and overrides.

Do not duplicate shared Workspace secrets.

Do not mutate source Site.

---

# 81. Reference Semantics

Shared references include:

- Workspace Integration Profile;
- Workspace Asset;
- Workspace Vehicle;
- global Block Definition;
- Template source metadata.

A reference must be explicit.

Do not pretend shared data is copied if it is actually linked.

---

# 82. Override Semantics

Override tables/fields should be used when:

- base data remains reusable;
- customer needs local differences;
- fallback resolution is clear.

Examples:

Global vehicle image
→ Workspace override
→ Site override

Shared Integration Profile
→ Site override.

---

# 83. Synchronization Semantics

No automatic Site-to-Site synchronization by default.

If synchronization is introduced later, create explicit synchronization entities/jobs rather than reusing copy logic invisibly.

---

# 84. JSON Usage Rules

Good JSON use-cases:

- Block Schema;
- Block Instance state;
- responsive settings;
- Integration mappings;
- provider-specific configuration;
- publication snapshots;
- flexible UI metadata.

Bad JSON use-cases:

- Workspace ownership;
- Site prices;
- user membership;
- Form Submissions as an unqueryable opaque blob only;
- domains;
- critical permissions;
- vehicle hierarchy.

---

# 85. Encrypted Data Rules

Sensitive fields requiring encryption may include:

- CRM tokens;
- API secrets;
- passwords;
- private keys;
- secret captcha keys.

Requirements:

- encrypted at rest;
- not searchable unless specifically designed;
- redacted in logs;
- masked in UI;
- never returned casually via Inertia props.

---

# 86. Phone Normalization

Because anti-spam and Form workflows use phone numbers, persist:

- original entered representation where useful;
- normalized phone value for comparison/indexing.

For Russian phone numbers, normalization logic should be deterministic.

Do not rely on display formatting for duplicate detection.

---

# 87. IP Storage

IP may be stored on Submission/Blacklist/Audit records where operationally required.

Support IPv4 and IPv6.

Do not assume fixed 15-character IPv4-only strings.

---

# 88. Currency

Commercial money values should not use floating-point.

Use integer minor units or fixed decimal according to the chosen money convention.

For RUB:

- integer kopecks is one option;
- DECIMAL is another.

One consistent Money strategy must be chosen before migrations.

---

# 89. Sort Order

Ordered entities should use explicit ordering.

Examples:

- Blocks;
- vehicle images;
- options;
- colors;
- Site Vehicles;
- benefits;
- Pages.

Avoid relying on ID order.

---

# 90. Status Fields

Use explicit status values for domain state instead of many unrelated booleans where lifecycle matters.

Examples:

- Template status;
- Marketplace listing status;
- Subscription status;
- Publication status;
- Delivery status.

Enums/value objects may be used in code.

Database values must remain migration-safe.

---

# 91. Soft Delete Strategy

Soft deletes are appropriate for:

- Sites;
- customer vehicles;
- Integration Profiles;
- Templates/Blocks in some cases.

But soft delete should not be automatically added to every table.

Reference dictionaries and join tables may not need it.

Decision should reflect business recovery needs.

---

# 92. Timestamps

Use standard timestamps consistently.

For business events also use dedicated timestamps:

- published_at
- submitted_at
- delivered_at
- invited_at
- verified_at
- expires_at

Do not infer business event timing solely from `updated_at`.

---

# 93. Database Transactions

Use transactions for operations such as:

- Site creation from Template;
- vehicle import;
- Site duplication;
- publication metadata updates;
- permission assignment;
- Form Submission persistence + initial delivery creation.

External API calls should not be performed while holding long database transactions.

---

# 94. Queue Safety / Idempotency

Queued delivery/import/publication jobs must be safe against duplicate execution where possible.

Potential techniques:

- unique operation keys;
- delivery status checks;
- idempotency tokens;
- guarded state transitions.

This is especially important for CRM/API delivery.

---

# 95. Public IDs

Public frontend URLs and API endpoints should not require exposing predictable internal IDs everywhere.

Potential later approach:

- UUID/ULID public identifiers;
- separate public keys;
- opaque tokens.

Decided (D-085 / ADR-001): internal bigint keys; externally addressed entities expose a ULID `public_id`; secrets use separate random tokens.

---

# 96. Migration Order

When implementation starts, recommended migration order is approximately:

1. identity/auth
2. Workspace
3. roles/permissions foundation
4. plans/entitlements
5. Sites/folders/settings
6. Templates/Blocks
7. Pages/Block Instances
8. media/assets
9. Global Automotive Catalog
10. Workspace Vehicles
11. Site Vehicles/Offers
12. Popups/Forms
13. Submissions
14. Integrations
15. security/blacklists
16. analytics settings
17. domains
18. publishing/versioning
19. developer/marketplace
20. audit/supporting systems

Do not create all migrations in one giant batch.

---

# 97. Database Testing Requirements

Database implementation must test:

- Workspace isolation;
- cross-Workspace access denial;
- Template deletion safety;
- Global Catalog edit restrictions;
- Site Vehicle source linkage;
- Site-to-Site copy independence;
- Integration Profile shared reference behavior;
- Form Submission persistence;
- Delivery retry state;
- unique domain enforcement;
- deletion restrictions.

---

# 98. Schema Changes by Cursor

Cursor agents must not:

- invent new global ownership models;
- merge Global Catalog and Site Vehicles;
- add customer price columns to Global Catalog;
- duplicate Workspace secrets per Site;
- store entire relational domains as JSON;
- add cascading deletes across Global Catalog → customer data;
- change copy semantics into synchronization;
- introduce arbitrary polymorphic tables without justification;
- add packages solely to avoid designing the schema.

Any major schema deviation requires an explicit architecture decision.

---

# 99. Documents Required Before Complex Implementation

Before implementing the deepest parts of these domains, create:

- `TENANCY.md`
- `PERMISSIONS.md`
- `AUTOMOTIVE_DATA.md`
- `BLOCK_SYSTEM.md`
- `FORMS_AND_INTEGRATIONS.md`
- `PUBLISHING.md`
- `SECURITY.md`

These documents may refine table design.

`DATABASE.md` defines the baseline, not an irreversible final migration set.

---

# 100. Final Database Model Summary

Core relationship map:

User
↕
Workspace Member
↓
Workspace
├── Sites
│   ├── Pages
│   │   └── Page Blocks
│   │        └── Block Definition / Version
│   ├── Site Vehicles
│   │   ├── Site Vehicle Images
│   │   ├── Site Vehicle Colors
│   │   └── Site Offers
│   │        └── Offer Benefits
│   ├── Popups
│   ├── Forms
│   │   ├── Form Fields
│   │   ├── Routes
│   │   └── Submissions
│   │        └── Deliveries
│   ├── Site Integration Bindings
│   ├── Security Settings
│   ├── Analytics Settings
│   ├── Domains
│   └── Versions / Publications
│
├── Workspace Integration Profiles
├── Workspace Assets
└── Workspace Vehicle Library

Global:
├── Automotive Catalog
│   ├── Makes
│   ├── Models
│   ├── Series
│   ├── Generations
│   ├── Modifications
│   ├── Trims
│   ├── Characteristics
│   ├── Options
│   ├── Colors
│   └── Images
├── Templates
├── Block Definitions
├── Plans / Entitlements
└── Marketplace

Critical principles:

**Global Catalog is never customer-editable.  
Customer automotive changes live in Workspace/Site scope.  
Site commercial prices never belong to the Global Catalog.  
Secrets are shared by reference and encrypted.  
Form submissions are persisted before external delivery.  
Templates and Block Definitions are reusable sources; Site instances are customer-owned.  
Draft and Published state remain separate.**
