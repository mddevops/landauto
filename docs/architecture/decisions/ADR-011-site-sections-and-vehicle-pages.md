# ADR-011 — Site Sections and Generated Vehicle Pages

**Status:** Proposed. Owner answers recorded 2026-11-14 (D-129); the technical details below await owner acceptance.
**Resolves:** D-129
**Relates to:** D-119 (Site types), ADR-006 (publishing runtime), D-127 (path-based address), ADR-010 (auto.ru catalog)

## Context

Customers choose one of four Site types (D-119). Chat selection and Quiz are single locked pages. Landing is a single page and Multi-page Sites have flat Pages (`/{slug}`). A vehicle card page (colours, trims, modifications, characteristics, options) can only be built by hand, one Page per vehicle, with every vehicle Block bound to that vehicle. Dealers expect catalog sections like `auto-malinovskogo.ru/catalog/baic/x35/…`: list, mark, model and vehicle pages that appear automatically for every vehicle on the Site.

## Owner requirements (2026-11-14)

1. Chat selection and Quiz stay single-page.
2. Landing is one page and may add one vehicle card page reached from its vehicle cards.
3. A Multi-page Site keeps unlimited regular Pages (credit, trade-in, promotions, jobs, contacts, insurance, services, …) and adds sections:
   - **Catalog** — new vehicles from the auto.ru catalog (Site Vehicles): `catalog/{mark}/{model}/{vehicle}`, e.g. `catalog/baic/x35/i-restayling-sedan`;
   - **Used** — used cars from the future Stock: `used/{mark}/{model}/{vehicle}`;
   - **Specials** — new cars / special offers from the future Stock: `specials/{mark}/{model}/{vehicle}`.
4. The customer can rename a section prefix (`catalog` → `autos`).
5. A model is not a vehicle card: one model may have several generations / bodies, so the model page lists them and the card is one level deeper.
6. The vehicle part of the address is a readable slug generated automatically and editable by the customer.
7. One card template per section, shared by all vehicles of that section and edited in the Designer.
8. Order: sections, the Catalog section and prefix settings first; Stock (Used / Specials) later.

## Decision (proposed details)

1. **Section entity.** `site_sections` belongs to a Site: `public_id`, `type` (`catalog` | `used` | `specials`, backed enum), `title`, `prefix`, `enabled`, `sort_order`. At most one section per type per Site. Only Multi-page Sites may hold sections; Landing holds only the implicit vehicle card page (see 7).
2. **Prefix rules.** Lowercase Latin letters, digits and hyphens, 1–60 characters, unique among the Site's sections and Page slugs, not in the reserved list (`_landflow`, `sitemap.xml`, `robots.txt`, `api`, `media`, …). A prefix change keeps the old prefix in `site_section_redirects`; the public runtime 301-redirects old addresses with the rest of the path.
3. **Generated pages per section.** Four template Pages owned by the section and edited in the Designer like regular Pages: `index` (`/{prefix}`), `mark` (`/{prefix}/{mark}`), `model` (`/{prefix}/{mark}/{model}`) and `item` (`/{prefix}/{mark}/{model}/{vehicle}`). Template Pages are not listed as regular Pages and have no slug of their own.
4. **Address parts.** Mark and model use the auto.ru `url` values (`auto_marks.url`, `auto_models.url`), never numeric ids. The vehicle slug is generated from generation and body (`i-restayling-sedan`); it is unique within the Site section and the model (suffix `-2`, `-3` on clash). The customer may edit it; previous slugs stay in a history table and 301-redirect.
5. **Page context.** On a generated page the runtime provides a context (section, mark, model, vehicle). Vehicle Blocks get the binding option «Автомобиль страницы»: the Block takes the context vehicle instead of a fixed one. Listing Blocks (`vehicle-grid`) get the source «Автомобили раздела» filtered by the context mark / model. Card links point to the item page automatically.
6. **Content sources.** Catalog section: Site Vehicles (ADR-010). Used and Specials sections: Stock items (separate ADR, see Consequences). A vehicle hidden or archived on the Site has no generated page (404).
7. **Landing.** A Landing may enable one vehicle card template Page. Its addresses follow the Catalog pattern with a fixed prefix `catalog` (editable), but index, mark and model pages are not generated; card links from the landing open the item page.
8. **Publishing.** A Published Version snapshots section settings, template Pages, vehicle slugs and redirects. The public runtime resolves section addresses from the snapshot only; the Draft never leaks. The sitemap lists every generated page of enabled sections. Canonical URLs use the current prefix and slug.
9. **Authorization.** Section settings and prefix changes use the existing Page management permissions; template Pages use the Designer permissions. Backend validates every prefix and slug.

## Consequences

- New tables: `site_sections`, `site_section_redirects`, vehicle slug history; template Pages linked to sections.
- Vehicle Block schemas gain a context binding (new Block versions; existing instances keep fixed bindings).
- Stock («Склад авто»: concrete vehicles for sale, new or used, created like an auto.ru listing) needs its own ADR before Used / Specials; it replaces the deferred P11-007 / P11-008 scope for this product.
- Breadcrumbs and menus can link sections like regular Pages.

## Open questions for owner acceptance

- Reserved prefix list and the 60-character limit.
- Whether Landing card addresses should be `catalog/{mark}/{model}/{vehicle}` (proposed) or shorter.
