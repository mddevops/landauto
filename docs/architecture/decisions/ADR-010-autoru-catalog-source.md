# ADR-010 — auto.ru Catalog as the Global Automotive Catalog Source

**Status:** Accepted (owner decision 2026-11-12)
**Resolves:** D-128
**Supersedes:** ADR-005; the Catalog V2 schema part of D-101 / D-102; the angle list of D-103

## Context

The platform team continuously fills the `catalog` database from auto.ru with its own parser (`auto_marks`, `auto_models`, `auto_generations`, `auto_series`, `auto_modifications`, `auto_equipments`, `auto_characteristics`, `auto_characteristic_values`, `auto_options`, `auto_option_values`). The Catalog V2 schema (public_id ULIDs, editable rows, values on Equipment) was never filled with real data. Numeric row ids of the auto.ru tables differ between environments, because every environment is filled by its own parser run.

## Decision

1. **Source.** Landflow reads the auto.ru tables directly on the `catalog` connection. Landflow never writes, migrates or adds indexes there in production; the schema belongs to the catalog team. The repository keeps an identical schema migration only for tests, E2E and empty local databases (it skips existing tables).
2. **Stable references.** Customer rows never store auto.ru numeric ids. Main-database columns hold string references built from auto.ru external ids:
   - Series: `{generation.external_id}:{series.external_id}` (`catalog_series_ref`). A generation without an external id is not offered.
   - Equipment: `auto_equipments.external_key` (`catalog_equipment_ref`), resolved inside the vehicle's Series (`series_id`, `external_key` is unique).
   - The Modification is reached through the Equipment row. A Modification without named trims is represented by its pseudo-equipment (`external_id = 0`), shown as «Базовая».
3. **Presentation rules.** Named trims are equipment rows with `external_id > 0`. Characteristics belong to the Modification (two-level dictionary ordered by `sort`); options belong to the Equipment (`is_base` = standard, otherwise optional). A Modification label uses its `name` when it contains a digit; otherwise it is built from engine, power, transmission and drive.
4. **Catalog images.** Paths in `logo_*` and `auto_series.image` are relative to the `auto_catalog` disk (`storage/app/public/auto`, synchronised by the catalog team) and are served through Landflow URLs. Published Sites receive copies (see 6).
5. **Series media library.** Platform media sets per Series reference `catalog_series_ref`. A set is a colour (name + swatch) with photos for five angles: `side_left`, `front_3_4_left`, `front`, `front_3_4_right`, `side_right`. Rear and top views are not used. A Series also has an ordered gallery (`series_gallery_images`).
6. **Customer copies (D-115).** When a customer adds a vehicle, the chosen colours, angle and gallery photos are copied into Workspace-owned files; customers may also upload their own photos. Later platform changes never alter customer vehicles.
7. **Prices.** A Workspace vehicle may hold default trims with prices and benefits (`workspace_vehicle_offers`). Copying the vehicle to a Site copies them into Site Offers, which stay authoritative for the Site.
8. **Grouping.** The customer Catalog groups vehicles automatically by mark and model; customers may also create their own collections (`vehicle_collections`). A Workspace may hold several vehicles of the same Series (for example different colour selections).

## Consequences

- The V2 editors (marks/models/dictionary/equipment forms) are removed; the platform catalog becomes a read-only browser plus the media library.
- Existing V2 catalog references are dropped by a migration; staging and production had no customer vehicles at the time of the change.
- Typed numeric filters over characteristics still need a separate decision.
