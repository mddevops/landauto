# ADR-012 — Workspace Stock («Склад»)

**Status:** Accepted (owner specification 2026-11-15: multi-page entitlement gate; auto.ru-like add flow for new and used; catalog tables as the only mark→…→equipment source)
**Resolves:** X-037
**Builds on:** ADR-001, ADR-004, ADR-010, ADR-011, D-129
**Replaces product scope of:** deferred P11-007 / P11-008 for dealer inventory (VIN/Stock layer as marketplace inventory)
**Leaves open:** dealer feed dialect into Stock; whether VIN is stored and shown publicly; AI trim-from-photos
**Backlog:** `X-037` (this ADR) → `X-043` … `X-046` → `X-038`

## Context

ADR-011 already separates:

- **Catalog section** — Site Vehicles from the auto.ru catalog family pages;
- **Used / Specials** — concrete cars for sale from a future **Stock**.

There is no Stock yet. The Workspace «Библиотека / Каталог» (`workspace_vehicles`) holds reusable Series configurations (colours, default trims) for copying onto Sites — not mileage/VIN/PTS inventory.

Owner (2026-11-15): add Workspace **Склад**; gate access to Workspaces that may create multi-page Sites; add new and used cars with a UX as convenient as auto.ru / automotive CRMs; every mark → model → generation → body → modification → equipment choice comes from the existing `catalog` connection tables (ADR-010). Screenshots of auto.ru «добавить объявление» are the UX reference.

## Decision

### 1. What Stock is

**Stock** is a Workspace-owned inventory of **concrete vehicles for sale** (new or used). One row = one physical / commercial unit, not a Series family page.

| Concept | Owner | Purpose |
|---|---|---|
| Global catalog (`auto_*`) | Platform | Read-only mark→…→equipment |
| Workspace vehicle library | Workspace | Reusable Series configs → Site Catalog |
| **Stock item** | Workspace | Concrete listing → Used / Specials (and cabinet CRM) |
| Site Vehicle | Site | Published catalog family page |

Stock does **not** mutate the global catalog. Stock does **not** replace Site Vehicles for the Catalog section.

### 2. Access

- **Entitlement gate:** Workspace must allow `multi_page_sites` (same capability that unlocks Multi-page Site creation). Without it, Stock nav and routes are unavailable (404 / omitted), even for Owners.
- **Permission:** new Workspace permission `manage_stock` (Russian UI: manage Склад). Initial role map: Owner and Admin yes; others no unless explicitly granted later. Backend Gates are authoritative; never trust the browser.
- Stock is **Workspace-scoped**, not Site-scoped. Linking an item into Used / Specials of a Site is a later publish/selection step (`X-038`), not ownership transfer.

### 3. Stock item model (main DB)

Table `stock_vehicles` (name may be `stock_items` in code if clearer; product label always «Склад»):

| Field | Rule |
|---|---|
| `public_id` | Immutable ULID (ADR-001); external id |
| `workspace_id` | Immutable ownership |
| `condition` | `new` \| `used` (immutable after create) |
| `catalog_series_ref` | Required ADR-010 Series string when Series is known |
| `catalog_equipment_ref` | Nullable; set when trim chosen (ADR-010) |
| `catalog_modification` | Resolved via Equipment / Modification as in ADR-010; store enough to re-display without inventing schema |
| `year` | Required manufacturing year |
| `body_key` / series body | From catalog Series for the chosen year |
| `color_name` + optional swatch | Required for listing completeness; may reference a catalog colour name or free label |
| `mileage_km` | Required when `used`; null/forbidden when `new` |
| `availability` | `in_stock` \| `on_order` \| `in_transit` |
| `price_minor` / currency | ADR-004; Site Offers are **not** required on Stock; price lives on the item for CRM and later section binding |
| `inner_id` | Optional dealer stock number; unique per Workspace when set; used as public URL last segment when set (ADR-011) |
| `status` | `draft` \| `ready` \| `archived` (only `ready` may appear in Used/Specials) |
| PTS / purchase / warranty / customs | Used-oriented optional structured fields (document type enum, purchase year/month, `on_warranty`, `not_customs_cleared`) |
| `description` | Optional free text |
| Photos | Workspace-owned uploaded images (many), ordered; same storage/quota family as vehicle photos; never platform Series media as the only source for used |

Platform-assigned **stock number** (short public code) is generated on create for URL fallback when `inner_id` is empty (ADR-011 §4). Exact format: lowercase alphanumeric, Workspace-unique, not a secret.

### 4. Add-vehicle UX (auto.ru-inspired)

Single progressive wizard in the Workspace shell («Склад» → «Добавить»). Russian UI only. Catalog options are loaded from `catalog` read APIs already used by the platform browser / vehicle wizard — **no duplicated mark lists in PHP**.

**Step flow**

1. **Тип** — «Новый» / «С пробегом» (sets `condition`; changes later steps).
2. **Марка** — search field + popular logo grid + «Все марки».
3. **Модель** — selected mark chip with clear; search + popular/all models grid.
4. **Год** — year pill grid (catalog-valid years for that model).
5. **Кузов** — visual cards (catalog Series image / body label) for that year.
6. **Поколение** — if more than one generation remains after year+body.
7. **Двигатель / Привод / КПП** — chip or row selectors filtered by remaining Modifications.
8. **Модификация** — radio list of remaining Modification labels (power / volume / name as catalog presents).
9. **Цвет** — swatch grid (required).
10. **Наличие** — `В наличии` / `На заказ` / `В пути`.
11. **Пробег** — only for used (required km).
12. **Фото** — multi upload; optional «загрузить позже» keeps item `draft` until photos or explicit ready.
13. **Комплектация** — equipment chips from catalog for the chosen Modification; **«Пропустить»** allowed (nullable equipment). No AI trim helper in MVP.
14. **ПТС** — used-oriented; skippable as a block except document type when product later marks it required. MVP: optional but shown for used.
15. **Цена** + save → `ready` (or stay `draft` if photos deferred).

Clear (×) on mark/model resets dependent steps. Header shows accumulated title («Lada Granta», year, body/generation) like auto.ru.

**New vs used differences**

| | New | Used |
|---|---|---|
| Mileage | Hidden | Required |
| PTS block | Hidden / minimal | Shown |
| Photos | Encouraged; may use catalog colour refs as *starting* copies only if dealer picks a platform colour set, then Workspace-owned | Own photos primary |
| Equipment | Strongly encouraged | Optional / skip |
| Availability | All three values | All three values |

### 5. Cabinet surfaces

- Workspace nav item **«Склад»** only when entitlement + `manage_stock`.
- Index: filters by condition, availability, status, mark; search by `inner_id` / title; Russian empty states.
- Item page: edit fields allowed for Draft/Ready; archive instead of hard-delete when referenced by a published Site snapshot (same class of restrict as published media).
- Not shown inside a Site shell until `X-038` binds Stock → Used/Specials.

### 6. Relation to Used / Specials (`X-038`)

Out of this ADR’s implementation slice, but locked direction:

- Enabling Used shows Stock items with `condition = used` and `status = ready`.
- Enabling Specials shows Stock items with `condition = new` and `status = ready` (optionally a later `is_special` flag — not required for MVP if all ready new Stock can enter Specials).
- Public last path segment = `inner_id` if set, else platform stock number.
- Publish snapshots Stock presentation into the Published Version (ADR-006/011); visitor never reads live Draft Stock.

### 7. Explicit non-goals (MVP)

- Feed import into Stock (separate decision).
- AI / computer-vision trim detection.
- SMS «upload photos later» deep link.
- Full warehouse CRM (tasks, appraisals, trade-in pipeline, telephony).
- Replacing Workspace vehicle library or Site Catalog vehicles.

## Consequences

- New permission, entitlement-gated nav, migrations, catalog-backed selection API reuse, and a dedicated Stock wizard (do not overload the Series-first Catalog `VehicleWizard` — share low-level catalog pickers, not the whole flow).
- Used / Specials remain «Скоро» in UI until `X-038`.
- Money on Stock uses ADR-004; no secrets in Inertia props.

## Resolved by owner (2026-11-15)

1. Stock section exists; gated by multi-page entitlement.
2. Catalog tables drive mark→model→modification→equipment inputs.
3. UX follows auto.ru listing progression for new and used (screenshots provided).
