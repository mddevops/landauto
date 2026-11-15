# ADR-013 — Site Components (shared Header / Footer / Banners)

**Status:** Accepted (owner request to implement X-041, 2026-11-16)
**Resolves:** X-041; D-130 Component rules
**Relates to:** ADR-006 (publishing), ADR-009 (native blocks), D-117 (block ownership)
**Backlog:** `X-041`

## Context

D-130: a Block Definition may be a Component. On a Site there is one shared instance: appear on every Page (including Pages created later), at most once; editing anywhere updates all; header first, footer last; other Components keep a free position. Publish, restore and Templates must carry Components.

Today Block Instances live only on `page_blocks` (required `page_id`). Section template Pages copy home `header` / `footer` once (`SectionPages::SHARED_BLOCKS`) — not live sync. The Designer «Компоненты» tab only filters library categories.

## Decision

### 1. Definition flags

On `block_definitions`:

- `is_component` (bool, default false)
- `component_position` nullable enum: `first` | `last` | `free` (required when `is_component`)

Official `header` → `first`; official `footer` → `last`. Platform (and later Developer) may mark other Definitions as Components. Flags are editable by Super Admin / studio settings for platform Blocks; changing `is_component` off while Sites use the Component is refused until instances are removed.

### 2. Storage — Site-owned rows

New table `site_components` (not page copies):

| Column | Rule |
|---|---|
| `public_id` | Immutable ULID (ADR-001) |
| `site_id` | Immutable ownership |
| `block_version_id` | Pinned version (same as page Blocks) |
| `sort_order` | Order among `free` Components only |
| `is_hidden` | Site-wide hide |
| `state_json` | Shared state |

At most one Component per Block Definition per Site (enforced in the place path via the definition of the pinned version). Regular page Blocks must not use Component Definitions.

### 3. Compose-on-read

Designer, Preview and Publish build each Page’s block list as:

1. all Site Components with position `first` (definition order / id);
2. the Page’s own `page_blocks` (non-component Definitions only);
3. Site Components with position `free` (`sort_order`);
4. Site Components with position `last`.

The same Component `public_id` appears on every composed Page. New Pages get Components automatically without copying rows. `SectionPages` stops copying header/footer from home.

### 4. Designer behaviour

- Placing a Component Definition creates or refuses (already present) a `site_components` row.
- Editing state / visibility / version upgrade of that `public_id` updates the Site row.
- Duplicate of a Component is refused.
- Move: `first` / `last` cannot reorder relative to page Blocks; `free` reorder among free only.
- Remove deletes the Site Component (all Pages).

### 5. Publish / restore

- Public manifest pages receive the **composed** block list (Components injected).
- Draft snapshot stores `components: [...]` once at Site level (same shape as page blocks without `page`).
- Restore restores Site Components then Pages; page snapshots must not resurrect old per-page header/footer copies of Component Definitions.

### 6. Templates (MVP)

- Template `content_json` may include top-level `components[]` (same fields as site components).
- Install creates Site Components once for the new Site, then page Blocks as today.
- Template authoring of Components can follow the Site Designer rules in a follow-up; empty `components` is valid.

### 7. Migration of existing Sites

On migrate: for each Site, take the home Page’s `header` and `footer` instances (if any) as the Site Components; delete every page-level instance whose Definition is a Component. Divergent states on non-home Pages are discarded (home wins).

### 8. Explicit non-goals (MVP)

- Per-Page opt-out of a Site Component.
- Free Component remembered coordinates per Page.
- Automatic screenshot / AI placement.

## Consequences

- `PageBlockReferences::existingBlocks` (and publish references) must see Site Component public ids for scroll targets.
- Quiz / Chat Sites may still hold Site Components; locked Site-type editing rules still apply to their schemas.
- Catalog / Used / Specials template Pages no longer need SHARED_BLOCKS copies.

## Resolved on acceptance

1. Storage = `site_components`, compose-on-read.
2. Path key / identity of the shared row = its ULID `public_id` (same on every Page in the composed list).
3. Existing Sites: home header/footer win.
