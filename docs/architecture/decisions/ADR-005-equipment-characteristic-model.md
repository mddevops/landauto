# ADR-005 — Equipment Characteristic Model

**Status:** Accepted (owner Catalog V2, 04.10.2026; automotive autopilot instruction 2026-10-04)
**Resolves:** D-086
**Backlog:** X-009 (trigger: before P3-001)

## Context

D-086 asked how characteristic values are stored across Generation / Modification / Trim and how inheritance and overrides resolve. Catalog V2 (`docs/architecture/AUTO_CATALOG_SCHEMA.md`) removes the Trim/Configuration level and attaches technical data to Equipment.

## Decision

1. **No inheritance engine.** Values do not inherit across Generation / Modification / Equipment, and there are no override layers inside the catalog.
2. **Values belong to Equipment.** `auto_characteristic_values` has one row per (`equipment_id`, `characteristic_id`) (unique pair). The same trim on two Modifications is two Equipment rows with their own values.
3. **Two-level dictionary.** `auto_characteristics` is a tree with exactly two levels:
   - a root row (`parent_id = NULL`) is a group, and its `unit` is `NULL`;
   - a child row is a parameter whose parent is a root group.
   Deeper nesting and cycles are rejected. Values may reference parameters only. `code` is stable and unique across groups and parameters.
4. **Value is TEXT without a unit.** The unit comes from the parameter definition and is the same for all values of that parameter; source values are converted on import. Numbers are stored canonically, with a dot and no thousands separators. Values are not compared as numbers without a separate typed-field decision.
5. **No fake empty rows.** Missing data means no row. An empty string, a dash or a placeholder is never stored; clearing a value deletes the row.
6. **No editable duplicates.** The main filter numbers live on Modification: `engine_volume`, `engine_power`, `consumption_100_km`, `acceleration_0_100`. Country is read from Mark and class from Model. Characteristic parameters must not duplicate these as independently editable values, so their codes are reserved.
7. **Presentation.** A vehicle's characteristics are the Equipment values grouped by parameter group, followed by the Modification fields shown as read-only facts.

## Consequences

- P3-003 implements exactly these two tables and validation; P3-012 view models read Modification fields plus Equipment values.
- Typed numeric characteristic filters, measurement methodologies and market/edition variants need a later decision before import.
