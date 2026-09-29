# Landflow — Automotive Data Architecture

**Document:** `docs/architecture/AUTOMOTIVE_DATA.md`  
**Status:** Automotive domain source of truth  
**Purpose:** Define the Global Automotive Catalog, customer import/copy model, Workspace/Site overrides, colors, images, specifications, equipment, commercial offers, and automotive data-binding rules.

---

# 1. Automotive Domain Goals

Landflow must provide a structured automotive data layer comparable in depth to professional automotive marketplaces, while remaining optimized for website building.

The system must support:

- Make
- Model
- Series
- Generation
- Modification
- Trim / Configuration
- Characteristics
- Options / Equipment
- Vehicle Colors
- Multi-tone Colors
- Transparent-background Images
- Color-specific Images
- Workspace Vehicle Library
- Site Vehicles
- Site-specific Prices
- Site-specific Benefits
- Site-specific Image Overrides
- Automotive Block Data Binding

The Global Catalog must remain platform-owned and customer-safe.

---

# 2. Core Ownership Model

Automotive data exists in three main layers.

## Level 1 — Global Automotive Catalog

Owned by Landflow.

Maintained by:

- Super Admin
- authorized Catalog Managers

Contains canonical automotive information.

Customers may read/import allowed data but cannot modify the master catalog.

## Level 2 — Workspace Vehicle Library

Owned by a Workspace.

Used to prepare reusable customer vehicle content.

May contain:

- imported vehicles
- custom photos
- custom descriptions
- reusable defaults

## Level 3 — Site Vehicle / Site Offer

Owned by a Site.

Contains the exact commercial representation shown on one Site.

May contain:

- selected vehicle configuration
- active/inactive state
- price
- RRP
- benefits
- badges
- custom images
- custom text
- selected colors
- ordering
- CTA configuration

---

# 3. Global Catalog Hierarchy

Canonical hierarchy:

Make
→ Model
→ Series
→ Generation
→ Modification
→ Trim / Configuration

Example:

Changan
→ UNI-K
→ Series
→ Generation I
→ 2.0 AT 4WD
→ Luxe

Not every brand/model necessarily uses every hierarchy level in the same way.

Therefore:

- Series may be optional.
- Names must not be fabricated to fill empty levels.
- The hierarchy must support incomplete or manufacturer-specific structures.

---

# 4. Make

A Make represents a vehicle manufacturer/brand.

Examples:

- Changan
- Haval
- Geely
- BMW

Potential data:

- name
- slug
- country
- logo
- status
- sort order

Make is global platform data.

---

# 5. Model

Model belongs to a Make.

Examples:

Changan:
- UNI-K
- CS55 Plus
- CS75 Plus

Potential data:

- name
- slug
- status
- sort order
- body family metadata if required

Model does not contain Site pricing.

---

# 6. Series

Series is an optional hierarchy level.

Some manufacturers use recognizable series/platform/family grouping.

Because catalog sources may differ, Landflow must not assume Series is always mandatory.

Series belongs to Model.

---

# 7. Generation

Generation represents a model generation.

Potential data:

- name
- manufacturer/internal code
- production start
- production end
- restyling status later
- body metadata where appropriate

Example:

UNI-K
→ Generation I

---

# 8. Restyling / Facelift

Some vehicles have a restyling/facelift within a Generation.

The architecture should remain capable of representing:

- original generation
- facelift/restyling

Possible implementation approaches:

- explicit restyling field/entity;
- generation variant;
- generation metadata.

The exact table design can be finalized based on real catalog data.

Do not flatten visibly different facelift variants into one configuration if that breaks images/specifications.

---

# 9. Modification

Modification represents a technical vehicle variant.

Typical dimensions:

- engine
- displacement
- power
- transmission
- drivetrain
- fuel type
- body
- hybrid/electric variant

Example:

2.0 Turbo
8AT
AWD
226 hp

Modification should not be confused with Trim.

---

# 10. Trim / Configuration

Trim represents an equipment/commercial configuration.

Examples:

- Comfort
- Luxe
- Tech
- Premium

Trim belongs to a Modification.

Trim may determine:

- equipment list
- available colors
- specific images
- characteristics if they differ
- default manufacturer data

Customer Site pricing does not belong to the Global Trim.

---

# 11. Canonical vs Commercial Data

The Global Catalog stores canonical vehicle facts.

Examples:

- dimensions
- engine power
- drivetrain
- standard equipment
- available colors
- manufacturer images

The customer Site stores commercial data.

Examples:

- RRP
- selling price
- dealer discount
- trade-in benefit
- credit benefit
- monthly payment
- stock/availability
- badge
- CTA

Never mix these responsibilities.

---

# 12. Characteristic System

Characteristics must be structured.

Examples:

- Length
- Width
- Height
- Wheelbase
- Ground clearance
- Trunk volume
- Engine volume
- Power
- Torque
- Acceleration 0–100
- Maximum speed
- Fuel consumption
- Battery capacity
- Electric range

Each characteristic should have:

- name
- key/slug
- data type
- unit
- category
- sort order
- status

---

# 13. Characteristic Categories

Examples:

- Dimensions
- Engine
- Transmission
- Performance
- Fuel Economy
- Battery / EV
- Suspension
- Safety
- Capacity

Categories control display grouping.

They should not determine technical ownership.

---

# 14. Characteristic Value Scope

A characteristic value may belong to the level where it is genuinely defined.

Examples:

Generation:
- dimensions

Modification:
- engine
- power
- transmission

Trim:
- some configuration-specific value

Avoid duplicating the same value unnecessarily across all Trims.

Rendering should resolve inherited automotive data from the correct source level.

---

# 15. Characteristic Inheritance

Conceptually:

Trim
→ Modification
→ Generation
→ Model

When displaying a Trim, Landflow may resolve relevant inherited values.

Example:

Trim has no custom length.

Length comes from Generation.

Do not physically copy every Generation characteristic to every Trim unless required for snapshot/import behavior.

---

# 16. Characteristic Override

Global Catalog managers may define a more specific value when necessary.

Example:

Generation width = 1900 mm

Specific Modification/Trim differs.

More specific canonical value wins.

This resolution is within the Global Catalog only.

Customer overrides are a separate layer.

---

# 17. Options / Equipment

Equipment must be structured rather than one long text string.

Examples:

- Heated Steering Wheel
- Heated Front Seats
- Adaptive Cruise Control
- Panoramic Roof
- 360 Camera
- Blind Spot Monitoring

Each option should have:

- name
- category
- description optional
- icon optional
- status

---

# 18. Equipment Categories

Examples:

- Comfort
- Exterior
- Interior
- Safety
- Multimedia
- Driver Assistance
- Climate
- Lighting

This allows templates to render grouped equipment lists automatically.

---

# 19. Trim Equipment

Trim is associated with Options.

Potential equipment states later:

- standard
- optional
- unavailable

Initial implementation may only require included/not included.

Do not encode all equipment as arbitrary HTML.

---

# 20. Vehicle Color

Vehicle color is a structured automotive entity.

It may include:

- manufacturer name
- customer-facing name
- manufacturer code
- color type
- visual swatches
- associated media
- availability by Trim

Examples:

- Black
- Arctic White
- Graphite Grey
- White + Black Roof

---

# 21. Multi-Tone Colors

Landflow must support multi-tone colors.

A color must not be modeled as one required HEX field.

Example:

Name:
White / Black Roof

Swatches:

1. White
2. Black

UI can represent this as a divided circle/swatch.

Future vehicles may require:

- two-tone
- three-tone
- texture/metallic preview

The data model must remain extensible.

---

# 22. Color Swatches

A color may have one or multiple swatch entries.

Potential fields:

- hex
- image/texture
- order
- percentage/proportion
- semantic part later

Example:

Body = white
Roof = black

Do not require exact physical body-area modeling in MVP.

---

# 23. Color Availability

A color may be available only for:

- specific Trim
- specific Modification
- specific market/version

Initial Landflow relation should primarily support Trim-color availability.

If real catalog data demands broader scope, extend deliberately.

---

# 24. Global Vehicle Images

Global Catalog may store manufacturer-quality vehicle media.

Important use case:

**transparent-background exterior vehicle images**

These allow templates to place a vehicle over any Hero/background.

Images should have explicit metadata.

---

# 25. Image Metadata

Potential image metadata:

- color
- Trim
- Modification
- Generation
- angle
- view type
- transparent background flag
- width
- height
- sort order
- status

Examples of view type:

- front
- front_3_4
- side
- rear_3_4
- rear
- interior
- dashboard
- detail

---

# 26. Color-Specific Images

Images may depend on selected color.

Example:

UNI-K
→ Luxe
→ White / Black Roof
→ white-black vehicle image set

When a customer chooses the color, compatible Blocks should be able to display the correct image set automatically.

---

# 27. Image Resolution Priority

For a Site Vehicle, image resolution should conceptually be:

1. Site Vehicle override
2. Workspace Vehicle override
3. Global Catalog image

The closest valid override wins.

---

# 28. Image Overrides

Customer can replace a vehicle image.

Example:

Global:
manufacturer transparent image

Workspace:
dealer studio photo

Site:
campaign-specific image

Changing Workspace/Site media never modifies Global Catalog media.

---

# 29. Media Storage vs Automotive Semantics

Physical file storage and automotive meaning must remain separated.

Example:

Media:
`file.webp`

Automotive Image record:
- Trim = Luxe
- Color = Black
- View = front_3_4
- transparent = true

Do not infer automotive meaning from filename/path.

---

# 30. Global Catalog Status

Catalog records should support statuses such as:

- active
- inactive
- archived

Once customer records reference catalog entries, prefer archive/inactive over destructive deletion.

---

# 31. Catalog Version Awareness

Global Catalog should be version-aware enough to support future update comparison.

Potential metadata:

- updated_at
- source version
- catalog revision
- import revision

Customer import may remember source revision.

This does not imply automatic synchronization.

---

# 32. Customer Import Flow

Customer workflow:

Global Catalog
→ search/filter
→ select Model/Trim
→ select required Colors/Images if applicable
→ Import
→ customer-owned vehicle created

The exact destination may be:

- Workspace Vehicle Library
- directly Site Vehicle

depending on product flow.

---

# 33. Recommended Import Strategy

For simple customers:

Global Catalog
→ Site Vehicle

For Team/reuse workflows:

Global Catalog
→ Workspace Vehicle Library
→ Site Vehicle

Landflow should support both concepts without duplicating master catalog data unnecessarily.

---

# 34. Import Snapshot

When importing, customer-owned record should preserve enough source context to remain stable.

Potential source references:

- make_id
- model_id
- generation_id
- modification_id
- trim_id

Potential source metadata:

- imported_at
- source revision

A controlled snapshot of selected display fields may also be stored if publishing stability requires it.

---

# 35. Import Does Not Grant Edit Rights to Global Catalog

After import:

Customer edits their own copy/override.

They never gain permission to edit:

- Make
- Model
- Generation
- Modification
- Trim
- Global Characteristic
- Global Option
- Global Image

---

# 36. Customer Vehicle Customization

Customer-owned vehicle may allow:

- custom display name
- custom description
- custom images
- selected colors
- selected equipment visibility
- availability
- badges
- Site-specific content

Exact editable fields may differ between Workspace and Site.

---

# 37. Workspace Vehicle Library

Workspace Vehicle Library exists to prevent repeated preparation.

Example:

Dealer Group imports UNI-K once.

Workspace Vehicle contains:

- chosen Trim
- preferred images
- dealer description
- configured color selection

Then:

Site Moscow
→ uses Workspace Vehicle

Site Kazan
→ uses same Workspace Vehicle

Commercial prices remain Site-specific.

---

# 38. Workspace Vehicle Pricing

By default, commercial Site prices belong to Site.

Workspace Vehicle Library may later support default/reference prices, but they must not silently synchronize Sites.

Recommended initial rule:

Workspace Vehicle:
content/media reuse

Site Offer:
commercial price

---

# 39. Site Vehicle

Site Vehicle represents one vehicle/configuration used on one Site.

It may reference:

- Workspace Vehicle
or
- Global Catalog Trim directly

Site Vehicle stores Site-specific selection/overrides.

---

# 40. Site Vehicle Activation

A Site Vehicle may be:

- active
- inactive
- archived

Only active vehicles normally appear in public collection Blocks unless Block query explicitly says otherwise.

---

# 41. Site Vehicle Sorting

Site must have explicit vehicle ordering.

Possible options later:

- manual
- price ascending
- price descending
- model name
- custom query

Manual order should be supported.

---

# 42. Site Offer

Site Offer represents commercial terms for a Site Vehicle.

Core fields may include:

- currency
- RRP
- current price
- monthly payment
- availability
- badge
- CTA

Prices must use a safe money representation.

No floating-point.

---

# 43. Benefits

Benefits must be extensible.

Examples:

- direct discount
- trade-in
- credit
- leasing
- government program
- dealer benefit
- custom

Do not permanently encode every type as a dedicated Site Offer column.

---

# 44. Benefit Type

A Benefit Type defines semantic meaning.

Potential fields:

- name
- key
- value type
- default label
- scope
- status

Some types may be global.

Custom Workspace types may be added later.

---

# 45. Benefit Value

Site Offer Benefit may include:

- amount
- percentage
- text value
- custom label
- sort order
- visible/hidden state

Example:

Trade-in benefit:
300,000 ₽

---

# 46. Total Benefit

If Landflow shows "Total Benefit", calculation rules must be explicit.

Do not automatically sum incompatible benefit types unless product logic defines that behavior.

Some benefits may be mutually exclusive.

This should be configurable later if needed.

---

# 47. Price Display Rules

Underlying Site Offer data and display settings are separate.

Example:

Site Offer stores:

- RRP
- price
- trade-in
- credit

Block may show:

- only price

Another Block:

- RRP + total benefit + price

Do not remove data merely because one Block hides it.

---

# 48. Availability

Potential availability states:

- in_stock
- available_to_order
- expected
- sold
- unavailable

Exact states can be refined later.

Availability may control public visibility and CTA behavior.

---

# 49. Stock Count

If future feed/import workflows provide stock quantity, support it separately from canonical vehicle data.

Stock is customer commercial/operational data.

It does not belong to Global Catalog.

---

# 50. VIN-Level Vehicles

Landflow initial architecture focuses on catalog/configuration-level vehicles.

Future used/new inventory feeds may introduce VIN/stock-unit entities.

Do not force VIN-level inventory into the Global Catalog hierarchy.

Potential future layer:

Vehicle Stock Item
→ references Site Vehicle / Trim
→ VIN
→ mileage
→ exact images
→ stock price

This is future unless explicitly scheduled.

---

# 51. New vs Used Cars

Global Catalog provides canonical make/model/specification structure useful for both.

Used vehicles may require additional Site/inventory data:

- year
- mileage
- VIN
- owners
- condition
- exact photos

Do not pollute canonical Trim with used-car state.

---

# 52. Automotive Block Data Binding

Blocks must consume automotive data through standardized bindings.

Example:

Vehicle Card:

- vehicle.make.name
- vehicle.model.name
- vehicle.trim.name
- offer.price
- offer.rrp
- selected_color
- primary_image
- benefits

Developer Block must not query arbitrary database tables directly.

---

# 53. Binding Context

Automotive rendering should use an approved View Model / data context.

Example:

VehicleCardContext:

- vehicle identity
- display title
- current offer
- images
- colors
- benefits
- public characteristics
- actions

This isolates frontend Blocks from raw database structure.

---

# 54. Collection Data Source

Blocks such as Vehicle Grid may use:

Data Source:
Site Vehicles

Potential filters:

- active only
- selected models
- selected makes
- selected body type
- price range
- manual selection

Potential sorting:

- manual order
- price
- name

MVP should begin simple.

---

# 55. Manual vs Dynamic Vehicle Selection

A Block may support:

## Manual

Customer chooses exact vehicles.

## Dynamic

Block displays all matching Site Vehicles.

Example:

All active Changan vehicles.

This behavior should be defined in Block Schema/Data Source configuration.

---

# 56. Vehicle Card Reuse

One Vehicle Card Block Definition should work with many Site Vehicles.

Do not generate a unique coded component per vehicle.

Template presentation remains reusable.

Data changes.

---

# 57. Selected Color in Public UI

A Vehicle Card/Detail page may expose color selection.

Changing selected color should update:

- swatch state
- compatible image
- possibly availability
- possibly Trim compatibility later

Price should change only if Site Offer logic explicitly varies by color.

---

# 58. Color-Specific Pricing

Some automotive markets may price certain colors differently.

Architecture should be able to support future color surcharges.

Do not hardcode price solely to color in MVP unless required.

Potential future model:

Site Offer Option / Color Adjustment.

---

# 59. Trim Selector

Vehicle-related Block may expose Trim Selector.

Changing Trim may update:

- price
- benefits
- equipment
- characteristics
- colors
- images

This is why Trim and Site Offer relationships must remain structured.

---

# 60. Modification Selector

Some Sites may expose Modification before Trim.

Example:

2WD
4WD

then Trim.

Blocks should be capable of reading hierarchy relationships.

MVP may use simpler flows depending on template.

---

# 61. Vehicle Detail Page

Future Site may have generated/detail Pages per vehicle.

Potential model:

Site Vehicle
→ Vehicle Detail Page

The Page can use dynamic automotive bindings.

Do not duplicate all vehicle content manually into Block state.

---

# 62. Dynamic Automotive Pages

Future architecture may support one Page Template rendering multiple Site Vehicles.

Example:

`/cars/{vehicle-slug}`

This should use Site Vehicle context.

Exact routing/rendering belongs in publishing architecture.

---

# 63. SEO for Automotive Pages

Automotive SEO may use bindings.

Example:

`Купить {make} {model} {trim} в {city} — цена {price}`

Bindings must resolve from trusted Site data.

User should be able to override generated SEO.

---

# 64. Vehicle URL Slug

Site Vehicle may have a public slug.

Potential uniqueness:

within Site.

Example:

`/cars/changan-uni-k-luxe`

Changing source Global Catalog slug must not silently break published customer URLs.

---

# 65. Catalog Search

Super Admin and customers need catalog search.

Search dimensions may include:

- Make
- Model
- Generation
- Modification
- Trim

Future search:

- body type
- fuel
- power

Start with relational/database search unless scale justifies dedicated search engine.

---

# 66. Catalog Filters

Customer import UX should support cascading filters:

Make
→ Model
→ Series/Generation
→ Modification
→ Trim

This mirrors automotive mental models.

---

# 67. Admin Catalog Editing

Super Admin should be able to manage:

- Make
- Model
- Series
- Generation
- Modification
- Trim
- Characteristics
- Options
- Colors
- Images

Admin changes require validation.

---

# 68. Admin Catalog Bulk Operations

Future useful tools:

- import CSV/XLSX
- feed/API import
- bulk image upload
- bulk characteristic mapping

These are admin productivity features, not the initial customer workflow.

---

# 69. External Catalog Sources

Future Global Catalog data may be populated from external providers.

Flow:

External Source
→ Import Adapter
→ Normalize
→ Validate
→ Global Catalog

External provider shape must not become Landflow's internal schema.

---

# 70. Catalog Source Mapping

If importing third-party automotive data, store provider/source identifiers separately.

Example:

provider = external_vendor
external_trim_id = 12345

Do not use vendor ID as Landflow primary identity.

---

# 71. Catalog Merge

When multiple sources provide the same vehicle, merging must be explicit.

Do not automatically merge records solely by display name.

Potential identity keys:

- manufacturer code
- generation
- engine
- transmission
- drivetrain
- trim code

This is a future catalog-management challenge.

---

# 72. Duplicate Prevention

Catalog admin UI should help detect duplicate:

- Makes
- Models
- Generations
- Trims

But exact same names do not always mean same technical vehicle.

Do not enforce simplistic global uniqueness.

---

# 73. Customer Import Duplicate Detection

When importing to Workspace/Site, detect whether the same source Trim already exists.

Options:

- use existing
- update selected fields
- import another explicit copy only if allowed

Avoid accidental duplicates.

---

# 74. Site-to-Site Vehicle Copy

Within allowed Workspace:

Site A
→ select vehicles
→ copy to Site B

Possible copied data:

- Site Vehicle
- selected colors
- images
- descriptions
- Site Offer
- benefits
- sort order

Copy must create destination-owned records.

---

# 75. Site-to-Site Copy Conflicts

If destination already contains vehicle:

- Skip
- Update
- Replace selected fields

Exact UX later.

Do not silently overwrite destination prices.

---

# 76. No Silent Site Synchronization

After copy:

Site A and Site B are independent.

Changing price on A must not update B.

If future synchronization is introduced, it must be explicit and visible.

---

# 77. Workspace Vehicle Reuse

Unlike Site copy, Workspace Vehicle may be a shared reference.

Site-specific mutable fields must still remain local.

Example:

Workspace photo updated.

Future policy may decide whether Sites automatically see that shared photo.

This behavior must be explicit.

For MVP, prefer predictable fallback/reference behavior and clear overrides.

---

# 78. Override Resolution

General automotive customer resolution order:

Site explicit override
→ Workspace reusable override
→ Global Catalog canonical data

This can apply to:

- images
- descriptions where allowed
- display names

Commercial prices do not use Global Catalog fallback.

---

# 79. Clearing an Override

Customer should be able to "Reset to source" where appropriate.

Example:

Site image override removed
→ falls back to Workspace
→ then Global

This is better than copying master image into Site record unnecessarily.

---

# 80. Source Update Notification

Future feature:

Global Catalog updated.

Customer vehicle references older revision.

Landflow may show:

"Catalog update available."

Customer can review differences.

Do not auto-apply updates.

---

# 81. Update Comparison

Potential compare categories:

- characteristics
- equipment
- colors
- images
- naming

Commercial Site data is not part of Global update.

---

# 82. Source Deactivation

If a Global Trim becomes inactive:

Existing customer Site Vehicle should not disappear automatically.

Possible behavior:

- keep existing customer data
- show admin warning
- prevent new imports if archived

Preserve customer production stability.

---

# 83. Catalog Deletion

Hard deletion should be rare once referenced.

Prefer:

- inactive
- archived

Customer source references remain meaningful.

---

# 84. Image Processing

Global/Workspace/Site vehicle images may require:

- resize
- thumbnails
- WebP
- AVIF later
- optimization
- transparent alpha preservation

Image processing must not destroy transparency.

---

# 85. Original Media

Where practical, preserve original uploaded media.

Derived optimized versions may be generated.

Do not repeatedly recompress already optimized source in a destructive chain.

---

# 86. Hero Image Requirements

Because automotive Sites use large Hero banners and vehicle compositions, media system should support:

- high-resolution source
- optimized public format
- responsive sizes
- transparent PNG/WebP where transparency required

Exact media pipeline is implementation-specific.

---

# 87. Customer Photo Replacement

User may replace default Global image.

Possible sources:

- upload
- Workspace Asset
- Workspace Vehicle media
- future media library

Site replacement affects only that Site unless saved to Workspace intentionally.

---

# 88. Save to Workspace

Future UX may allow:

"Use this image for all Workspace Sites"

This should intentionally create/update Workspace override.

Do not infer Workspace-wide changes from Site editing.

---

# 89. Characteristics Display Settings

Site/Block may choose which characteristics to show.

Example:

Vehicle Card:
- power
- engine
- drivetrain

Detail Page:
- full characteristics

Canonical data stays the same.

Presentation selection belongs to Block/Site configuration.

---

# 90. Equipment Display Settings

Likewise:

Block may show:

- top 5 options
- specific categories
- full equipment list

Do not duplicate equipment text into every Block.

---

# 91. Public Automotive View Model

Published frontend should receive only required public automotive data.

Do not expose:

- internal source metadata
- admin-only fields
- inactive/private media
- private Workspace notes

Use dedicated public serialization/view models.

---

# 92. Form Context Integrity

When a visitor submits interest in a vehicle:

Client may send vehicle public identifier.

Server resolves trusted:

- Site Vehicle
- Site Offer
- Trim
- current price
- selected color if valid

Do not trust arbitrary submitted `price`.

---

# 93. Analytics Events

Automotive semantic events may include:

- vehicle.view
- vehicle.card_click
- vehicle.color_select
- vehicle.trim_select
- vehicle.offer_click
- vehicle.gallery_open
- vehicle.form_submit

These feed Yandex Metrica through platform Analytics Layer.

---

# 94. Permissions

Customer automotive permissions should distinguish:

- view_vehicles
- edit_vehicles
- edit_prices
- import_vehicles
- manage_workspace_vehicle_library

Global Catalog editing remains platform-only.

---

# 95. Audit

Sensitive automotive changes should eventually be auditable.

Especially:

- price
- RRP
- benefits
- availability
- source import/update
- vehicle deletion/archive

---

# 96. Automotive Tests

Required test categories:

1. Customer cannot edit Global Catalog.
2. Import creates customer-owned data.
3. Site price does not modify Global Trim.
4. Workspace A cannot access Workspace B vehicle library.
5. Site override wins over Workspace/Global image.
6. Removing Site override restores fallback.
7. Two-tone color preserves multiple swatches.
8. Site-to-Site copy creates independent price.
9. Archived Global Trim does not delete Site Vehicle.
10. Public Form resolves trusted Site Offer price server-side.
11. Vehicle Grid only renders allowed/active Site Vehicles.
12. Block binding does not expose private catalog/admin fields.

---

# 97. Cursor Rules

Cursor agents must never:

- add customer price to Global Catalog;
- let customer edits mutate master automotive records;
- collapse Modification and Trim into one concept without explicit decision;
- assume every color has one HEX;
- store equipment only as HTML;
- infer image color solely from filename;
- silently synchronize Site prices;
- delete customer vehicles because source catalog was archived;
- trust browser-submitted price;
- query raw automotive tables directly inside arbitrary developer Blocks;
- duplicate entire catalog data into every Block state.

---

# 98. Implementation Order

Recommended implementation sequence:

1. Make
2. Model
3. Series
4. Generation
5. Modification
6. Trim
7. Characteristics
8. Options / Equipment
9. Colors / Swatches
10. Images
11. Catalog admin
12. customer import
13. Workspace Vehicle Library
14. Site Vehicles
15. Site Offers
16. Benefits
17. automotive Block bindings
18. Site-to-Site copy
19. update comparison later
20. external catalog imports later

---

# 99. Source of Truth

This document refines:

- `PRODUCT.md`
- `ARCHITECTURE.md`
- `DATABASE.md`
- `TENANCY.md`
- `PERMISSIONS.md`

If implementation requires changing these automotive semantics, create an explicit architecture/product decision first.

---

# 100. Final Automotive Model

The Landflow automotive domain should always be understood as:

Global Automotive Catalog
├── Make
│   └── Model
│       └── Series (optional)
│           └── Generation
│               └── Modification
│                   └── Trim
│                       ├── Characteristics
│                       ├── Equipment
│                       ├── Colors
│                       │   └── Swatches
│                       └── Images
│
↓ Import / Source Reference
│
Workspace Vehicle Library
├── reusable customer content
├── customer images
└── reusable automotive preparation
│
↓ Use / Copy
│
Site Vehicle
├── Site-specific content
├── selected colors
├── Site image overrides
└── Site Offer
    ├── RRP
    ├── price
    ├── benefits
    ├── availability
    └── CTA

Critical rules:

**Global Catalog is platform-owned.  
Customers never modify master data.  
Workspace/Site overrides are customer-owned.  
Commercial pricing belongs to Site.  
Colors may be multi-tone.  
Vehicle media may be color-specific and transparent.  
Site-to-Site copy creates independent commercial data.  
Automotive Blocks consume structured bindings, not duplicated manual content.**
