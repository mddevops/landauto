# Landflow — Product Specification

## 1. Product Vision

Landflow is a SaaS platform for creating, configuring, and publishing professional automotive websites.

The core product flow is:

**Workspace → Create Site → Choose Template → Customize → Configure Vehicles & Prices → Preview → Publish**

Landflow takes strong product and UX inspiration from Webflow, while being specialized for automotive businesses.

Landflow is **not** a traditional dealership CRM. Its primary purpose is website creation, publishing, automotive content management, reusable vehicle data, form collection, integrations, and developer extensibility.

---

## 2. Core Product Principles

1. A customer should be able to create a professional automotive website without programming.
2. Templates define presentation and structure, not customer vehicle ownership.
3. Vehicle data and design must remain logically separated.
4. Commercial vehicle data belongs to the customer's Site or Workspace, not the global catalog.
5. The global automotive catalog is maintained only by Landflow administrators.
6. A customer may copy/import catalog data into their own environment without changing the master catalog.
7. Draft and published states must remain separate.
8. Publishing must always be an explicit action.
9. Workspaces provide the ownership and collaboration boundary.
10. Reusable blocks must be described through a safe schema rather than arbitrary uncontrolled runtime code.
11. Forms, popups, analytics, CAPTCHA, spam protection, and integrations are platform services.
12. External feeds and APIs are future data sources, not the core product itself.
13. Major product behavior must be defined in product/architecture documentation and not invented ad hoc by implementation agents.

---

## 3. Main Product Surfaces

Landflow consists of four main product environments.

### 3.1 Customer Platform

Used by customers to manage:

- Workspaces
- Sites
- templates
- Site settings
- automotive content
- forms
- integrations
- domains
- SEO
- analytics
- publishing

### 3.2 Designer

Visual website editing environment.

### 3.3 Developer Platform

Used by developers/designers to create:

- templates
- reusable blocks
- marketplace products
- schema-driven components

### 3.4 Super Admin

Used internally to manage the entire platform, including the global automotive catalog.

---

# 4. Account

A User represents a person.

A User may eventually participate in multiple Workspaces.

Example:

User: Daler

Workspaces:

- Personal Workspace
- Dealer Group
- Automotive Agency

A user's identity must remain separate from Workspace membership.

## Sign-up and Sign-in

Supported methods (D-095):

- **Email and password.** After registration the user receives a verification email; until the email is verified the account has no full access.
- **Yandex ID (OAuth).** The email returned by Yandex is required and is considered verified; no separate verification email is sent. If Yandex does not provide an email, no account is created: the user sees an explanation in Russian and can register with email or sign in with Yandex again granting access to the email.

Every account has an email.

Two-factor authentication, one-time codes (TOTP) and passkeys are not part of Landflow.

---

# 5. Workspace

Workspace is the primary ownership and collaboration boundary.

A Workspace may contain:

- members
- Sites
- Site folders
- reusable assets
- vehicle library
- integration profiles
- purchased templates
- permissions
- subscription data

Even Free accounts should conceptually use a Workspace so upgrading does not require a different data model.

---

# 6. Team Collaboration

Team plans may allow several users inside the same Workspace.

Example:

Workspace: Automotive Agency

- Daler — Owner
- Ivan — Designer
- Anna — Content Editor
- Sergey — Pricing Manager

Permissions may exist at:

- Workspace level
- Site level

A member may have access to Site A and Site B while having no access to Site C.

---

# 7. Permissions

Authorization must support granular permissions.

Examples:

- manage_workspace
- manage_members
- manage_billing
- create_sites
- delete_sites
- edit_design
- edit_content
- edit_vehicles
- edit_prices
- edit_forms
- edit_integrations
- edit_seo
- manage_domains
- publish_site

Publishing must be a separate permission.

Roles must not be permanently hardcoded into business logic.

Initial roles may include:

- Owner
- Admin
- Designer
- Content Editor

Custom roles may be introduced later.

---

# 8. Sites

A Site is an independently configurable website.

A Workspace may contain multiple Sites depending on subscription entitlements.

Example:

Workspace: Dealer Group

- Changan Moscow
- Changan Kazan
- Haval Moscow
- Geely Moscow

Each Site has independent:

- pages
- design
- content
- selected vehicles
- commercial offers
- SEO
- branding
- integrations
- analytics
- domain
- draft state
- published state

Site types (D-119, refined by D-124; `site_type` is fixed at creation):

- **Лендинг** (`landing`) — blank or compatible Template; one Page; the customer adds, removes, reorders and configures Blocks.
- **Многостраничный сайт** (`multi_page`) — blank or compatible Template; Pages + Blocks; requires the typed `multi_page_sites` entitlement at creation.
- **Квиз** (`quiz`) — compatible Template only; no structural editing; the customer changes only exposed fonts, colors and images.
- **Чат-подбор** (`chat_selection`) — same restrictions as Квиз.

The backend enforces these rules (X-027); today only structural locking for Квиз / Чат-подбор exists.

---

# 9. Site Folders

Sites may be organized into folders.

Example:

Dealer Group

Changan/
- Moscow
- Kazan

Haval/
- Moscow
- Saint Petersburg

Folders are organizational by default and should not silently define vehicle ownership or access permissions.

---

# 10. Site Creation Flow

Expected flow:

Create Site

→ Choose Blank Site or Template

→ Configure initial Site

→ Open Designer

→ Configure Site content

→ Select vehicles

→ Configure prices and benefits

→ Preview

→ Publish

The onboarding UX may evolve while preserving this overall flow.

---

# 11. Templates

Templates provide the initial website structure and design.

Templates must **not own customer vehicles**.

A Template determines how data is displayed.

A Site determines which data is displayed.

Changing a Template must not inherently delete:

- selected vehicles
- prices
- benefits
- SEO data
- Site identity
- forms
- integrations

Template updates must not unexpectedly mutate existing customer Sites.

---

# 12. Template Categories

Potential template categories:

- Official Dealer
- Multi-brand Dealer
- Used Cars
- Automotive Landing Page
- Vehicle Model Landing Page
- Dealer Group
- Special Offers

Categories must remain extensible.

---

# 13. Free and Premium Templates

Templates may be:

- Free
- Paid

Free templates may be provided by Landflow or approved developers.

Premium templates may eventually be sold through Marketplace.

Licensing and commercial rules must remain configurable.

---

# 14. Designer

Designer is Landflow's visual website editing environment.

It should eventually provide:

- canvas
- pages
- section/component tree
- properties panel
- global Site styles
- responsive previews
- section management
- component settings
- content editing
- automotive bindings
- autosave
- undo/redo
- preview
- publishing controls

Designer must be implemented incrementally.

---

# 15. Page and Block Structure

A Page consists of blocks/sections.

Example:

Home

- Header
- Hero
- Benefits
- Vehicle Grid
- Promotions
- Credit
- Trade-in
- Contacts
- Footer

Blocks should eventually support:

- reorder
- hide/show
- duplicate
- remove
- configure
- responsive settings
- data binding
- optional carousel mode
- actions
- nested/repeated content

---

# 16. Site Design System

Each Site should provide global design settings.

Examples:

- logo
- favicon
- primary color
- secondary color
- typography
- button styles
- border radius
- container width
- spacing rules

Components should use global Site styles unless explicitly overridden.

---

# 17. Global Automotive Catalog

Landflow maintains a master automotive catalog.

Only Super Admin or authorized internal catalog managers may edit the master catalog.

The conceptual hierarchy includes:

Make

→ Model

→ Series

→ Generation

→ Modification

→ Trim / Configuration

Related data may include:

- specifications
- characteristics
- equipment/options
- colors
- images
- technical data

The catalog concept should be comparable in depth to large automotive marketplaces such as Auto.ru, while remaining Landflow-owned.

---

# 18. Catalog Ownership

The Global Catalog is authoritative platform data.

Customers must never directly modify it.

A customer selects vehicles from the Global Catalog and imports/copies the required entities into their own Workspace/Site context.

Example:

Global Catalog:

Changan
→ UNI-K
→ Generation
→ 2.0 AT 4WD
→ Luxe
→ specifications
→ equipment
→ colors
→ images

Customer selects this vehicle and imports it.

The customer may then change:

- price
- discounts
- images
- descriptions
- visibility
- ordering
- Site-specific commercial information

These changes must not modify the Global Catalog.

---

# 19. Catalog Import / Fork Model

Imported customer automotive data should retain a reference to its Global Catalog origin where useful.

Conceptually:

Global Catalog Vehicle
        ↓
Import / Copy
        ↓
Workspace Vehicle / Site Vehicle

Customer changes apply only to the customer-owned copy/override.

A future feature may notify customers that source catalog data has changed, but customer data must not be silently overwritten.

---

# 20. Three-Level Automotive Data Model

Landflow should conceptually support three levels.

## Level 1 — Global Catalog

Owned by Landflow.

Contains:

- make
- model
- series
- generation
- modification
- trim
- characteristics
- equipment
- colors
- default images

## Level 2 — Workspace Vehicle Library

Reusable automotive content prepared for one Workspace.

May contain:

- selected catalog vehicles
- custom images
- custom descriptions
- reusable assets
- reusable defaults

## Level 3 — Site Vehicle / Site Offer

Contains commercial data for one Site.

May contain:

- active/inactive state
- sort order
- RRP
- current price
- benefits
- badges
- CTA settings
- image overrides
- text overrides
- selected colors
- selected trims

Site commercial data should remain independent unless explicit synchronization is later introduced.

---

# 21. Vehicle Colors

Color is structured automotive data.

A color may contain:

- manufacturer color name
- display name
- color type
- one or more visual swatches
- related images

Landflow must support multi-tone vehicles.

Example:

Name: Arctic White / Black Roof

Primary:
#F4F4F2

Secondary:
#111111

The UI may render this as a split swatch.

Architecture should not assume every vehicle color consists of a single HEX value.

---

# 22. Vehicle Images

Global Catalog may contain vehicle images with transparent backgrounds.

Images may be linked to:

- vehicle
- trim
- color
- angle/view
- ordering

Example:

UNI-K

Black
- front
- side
- rear

White
- front
- side
- rear

White + Black Roof
- front
- side
- rear

Transparent-background images are especially important because templates may place vehicles over different backgrounds.

---

# 23. Image Override Priority

The closest explicit customer override should win.

Conceptually:

Global Catalog image
↓
Workspace override
↓
Site override

A customer replacing an image must not alter the Global Catalog image.

---

# 24. Vehicle Commercial Offers

Commercial information belongs to the customer Site.

Examples:

- RRP
- current price
- direct discount
- trade-in benefit
- credit benefit
- leasing benefit
- government program benefit
- dealer discount
- custom benefit

The system must not assume that only a fixed list of benefit types will ever exist.

---

# 25. Price Display

Display configuration must remain separate from commercial data.

Example Site A may show:

- RRP
- current price
- total benefit
- trade-in benefit

Site B may show:

- current price
- monthly payment

Underlying commercial data may remain the same while presentation differs.

---

# 26. Import Vehicles Between Sites

Authorized users should be able to copy automotive configuration from another Site in the same allowed Workspace context.

Example:

Create Changan Kazan

Import from Changan Moscow

Possible import content:

- vehicles
- trims
- prices
- RRP
- benefits
- photos
- colors
- custom descriptions
- sort order

Import should support selecting individual vehicles.

---

# 27. Import Conflicts

If the target Site already contains the same vehicle/configuration, the UI should support conflict handling.

Potential actions:

- Skip
- Update
- Replace selected fields

Duplicate creation should only be allowed intentionally.

---

# 28. Copy vs Synchronization

Site-to-Site import means **copy by default**.

Example:

Site A: 4,000,000
Copy to Site B: 4,000,000

Later Site A changes to 3,900,000.

Site B remains 4,000,000.

Synchronization may be a separate future feature.

---

# 29. Automotive-Specific Blocks

Landflow should provide automotive blocks such as:

- Vehicle Grid
- Vehicle Card
- Model Hero
- Vehicle Gallery
- Color Selector
- Trim Selector
- Price Block
- Benefits Block
- Specifications
- Equipment
- Credit Calculator
- Trade-in Form

These components distinguish Landflow from a generic website builder.

---

# 30. Developer Platform

Landflow will eventually provide a dedicated developer environment.

Current direction (D-125, 2026-10-08): there is no open third-party Developer platform. Blocks and Templates are produced by the Landflow team and internal authorized developers / designers / admins (AI is a code-generation tool, not a trusted author). Developer Profiles are granted manually and serve these internal creators. Sales, earnings, commissions, payouts and public Developer registration are deferred. Approved first-party Blocks render natively on published Sites (ADR-009); future external authors would need a separate security model and never get that trust automatically.

Developer Dashboard may include:

- My Templates
- My Blocks
- Assets
- Marketplace
- Sales
- Earnings
- Developer Settings

---

# 31. Developer Blocks

Developers should be able to create reusable blocks.

Examples:

- Hero
- Vehicle Grid
- Vehicle Card
- Promotions
- Credit
- Trade-in
- Contacts

A block may be free or paid in Marketplace.

---

# 32. Block Schema

A developer should not have to manually build a custom admin panel for every block.

Each block must expose editable properties through a structured schema.

Potential field types:

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
- vehicle
- vehicle_model
- vehicle_trim
- vehicle_color
- group
- repeater

Landflow Designer automatically generates the Properties Panel from this schema.

---

# 33. Repeater Fields

Blocks must support repeatable collections.

Example:

Benefits

- icon
- title
- description

User can:

- add
- delete
- reorder
- edit items

Repeaters may contain nested groups.

Example:

Slides

- image
- title
- text
- button
  - label
  - URL
- vehicle

---

# 34. Conditional Block Fields

Block Schema should eventually support conditional fields.

Example:

show_button = true

Then show:

- button_text
- button_url
- button_action

If false, those settings are hidden.

---

# 35. Automatic Block Understanding

Landflow should not rely on AI guessing editable fields at runtime for every page render.

The reliable source of truth must be a deterministic Block Schema.

A future developer workflow may use AI to analyze developer markup and propose an editable schema.

Example:

Developer uploads/creates a block.

AI detects:

- heading
- subtitle
- image
- CTA
- repeated benefits

Landflow proposes fields.

Developer confirms.

After confirmation, the block uses the saved deterministic schema.

AI-assisted schema generation is a future convenience layer, not the runtime foundation.

---

# 36. Automotive Data Binding

Blocks should be able to bind fields to structured Landflow automotive data.

Example Vehicle Card:

title → vehicle.model.name

price → site_offer.price

image → selected_color.images

trims → vehicle.trims

colors → vehicle.colors

One block should therefore work with any compatible Site Vehicle.

---

# 37. Block Data Sources

Repeaters/collections may use:

- Manual data
- Site Vehicles
- Workspace data
- future structured sources

Example:

Vehicle Grid
Data Source: Site Vehicles

The block automatically renders matching Site vehicles.

---

# 38. Carousel / Slider Capability

Carousel behavior should be a platform capability, not hardcoded to a single external library.

A compatible block may support:

- Static
- Grid
- Carousel

Potential responsive settings:

- slides per view
- gap
- arrows
- pagination
- autoplay
- loop
- speed

Settings may vary for:

- Desktop
- Tablet
- Mobile

The architecture must remain independent from a specific implementation library such as Swiper.

---

# 39. Repeater + Carousel

Repeated block content may be rendered as a carousel.

Examples:

- benefits
- promotions
- testimonials
- vehicles
- banners
- trims
- galleries

A developer should be able to declare that a repeated collection supports carousel presentation.

---

# 40. Gallery

Gallery is a reusable media collection.

A Gallery may contain:

- images
- future video support
- ordering
- metadata

Gallery presentation may use:

- grid
- carousel
- thumbnails

---

# 41. Lightbox

Lightbox is a media viewing capability.

It is different from a business Popup.

Example:

Vehicle Gallery
→ click image
→ Lightbox
→ large image
→ next/previous controls

Architecture should not be permanently tied to Fancybox.

---

# 42. Popup System

Popup is a reusable Site-level entity.

Examples:

- Get Offer
- Credit Calculation
- Trade-in
- Test Drive
- Callback

A single Popup may be opened from multiple blocks.

Example:

Hero CTA
Vehicle Card CTA
Sticky Button
Price Block CTA

→ same "Get Offer" Popup

---

# 43. Popup Designer

A Popup may contain configurable content such as:

- image
- title
- subtitle
- vehicle information
- price
- Form
- submit button

Potential popup settings:

- width
- position
- overlay
- close button
- close on outside click
- animation
- mobile behavior

Later triggers may include:

- button click
- delay
- scroll percentage
- exit intent
- session frequency rules

MVP should prioritize explicit user actions such as Open Popup.

---

# 44. Form System

Form must be a separate entity from Popup.

Popup is a display container.

Form is data collection.

A Form may be reused in:

- Popup
- Hero
- Vehicle Card
- standalone section

Examples:

- Callback
- Test Drive
- Get Offer
- Credit
- Trade-in

---

# 45. Action System

Buttons and interactive elements should use a common Action System.

Potential actions:

- Open URL
- Open Page
- Scroll to Section
- Open Popup
- Submit Form
- Phone
- Email
- future custom safe actions

This prevents every block from implementing click behavior differently.

---

# 46. Context Passing

When a button opens a Popup or Form from automotive content, Landflow should pass context.

Example:

Vehicle:
Changan UNI-K

Trim:
Luxe

Color:
Black

Price:
3,890,000

Source Block:
vehicle-card

This context should be available to the Form and later Integration Layer.

---

# 47. Form Submission Context

A submission may include contextual fields automatically.

Examples:

- Site
- Page
- block
- vehicle
- model
- generation
- modification
- trim
- color
- price
- UTM source
- UTM medium
- UTM campaign
- page URL

Users should not need to manually re-enter information already known by the platform.

---

# 48. Workspace Integration Profiles

Integrations should be reusable at Workspace level.

Example:

Workspace Integration Profiles

- Dealer CRM
- Custom API
- Email
- Webhook

A reusable profile stores common connection details.

Example:

Base URL
Token
Secret
Timeout

Several Sites may use the same profile.

---

# 49. Site Integration Overrides

A Site may override Site-specific parameters without duplicating shared credentials.

Example:

Workspace CRM Profile:

Token: shared
API URL: shared

Site A:

site_id = 123

Site B:

site_id = 456

This allows multiple Sites to use one integration configuration while changing only required Site parameters.

---

# 50. Integration Copy Between Sites

When a Site is copied or configuration is imported, users may choose to copy:

- integration profile reference
- field mapping
- form routing
- Site-specific overrides

Shared secrets should remain referenced from the Workspace Integration Profile rather than duplicated unnecessarily.

---

# 51. Form Routing

Form submissions may be routed to one or several destinations.

Example:

Get Offer Form

Send to:

- Email
- Dealer CRM
- Custom API

One submission may be delivered to several destinations.

---

# 52. Field Mapping

Landflow fields may be mapped to destination fields.

Example:

Landflow:
name
phone
vehicle
trim

CRM:
client_name
telephone
car_id
configuration_id

Static Site values may also be included.

Example:

site_id = 152
source = landflow

---

# 53. Submission Persistence

Valid submissions must be saved before delivery to external services.

Conceptually:

Form Submitted
↓
Validate / Anti-Spam
↓
Save Submission
↓
Queue Delivery
↓
CRM / API / Email

A temporary external CRM failure must not cause a valid lead to disappear.

---

# 54. Delivery Logs and Retry

Landflow should maintain delivery status.

Example:

Submission #1842

Email — Delivered

Dealer CRM — 200 OK

Webhook — Failed

Failed deliveries should support retry according to system rules.

---

# 55. Integration Secrets

Sensitive values include:

- API tokens
- client secrets
- passwords
- private keys

Security requirements:

- encrypt at rest where appropriate
- never expose full saved secrets back to frontend unnecessarily
- mask values in UI
- do not place secrets in logs
- do not embed secrets in published frontend code
- do not include secrets in normal Site export data

---

# 56. Anti-Spam Layer

Forms must be protected by a centralized platform anti-spam system.

Protection may include:

- honeypot/basic bot checks
- rate limiting
- IP blacklist
- phone blacklist
- duplicate submission rules
- Yandex SmartCaptcha

Individual template developers must not implement independent anti-spam behavior.

---

# 57. Yandex SmartCaptcha

For the Russian-oriented deployment/product configuration, Landflow should support Yandex SmartCaptcha.

Captcha should be configured centrally through Site/Workspace security settings and applied through the Landflow Form System.

Exact technical integration must be implemented according to the current official Yandex documentation at implementation time.

---

# 58. Rate Limiting

Sites should support configurable form submission limits.

Examples:

From one IP:
5 submissions / 10 minutes

From one phone:
2 submissions / 30 minutes

Duplicate phone + form:
block for 15 minutes

Exact defaults must remain configurable.

Phone numbers should be normalized before anti-spam comparison.

---

# 59. Blacklists

Landflow should support blacklist levels such as:

- Global Landflow Blacklist
- Workspace Blacklist
- Site Blacklist

Possible blacklist types:

- IP
- phone number

Blacklist entries may include:

- value
- reason
- source
- created by
- created at
- expiration where applicable

Global blocking actions should be auditable.

---

# 60. Temporary Automatic Blocking

Future anti-abuse behavior may include temporary automatic blocking.

Example escalation:

1 hour
→ 6 hours
→ 24 hours

Permanent automatic blocking should not be used casually.

---

# 61. Analytics Layer

Analytics should be implemented as a platform service.

For the Russian-oriented deployment, primary analytics integration should support Yandex Metrica.

Users should configure analytics through Site Settings rather than manually pasting scripts where possible.

---

# 62. Yandex Metrica

Potential Site settings:

- Counter ID
- Webvisor
- Track Forms
- Track Phone Clicks
- Track CTA Clicks

Exact integration must follow current official Yandex documentation at implementation time.

---

# 63. Internal Site Events

Landflow should expose a common internal event model.

Potential events:

- page.view
- vehicle.view
- vehicle.click
- popup.open
- popup.close
- form.start
- form.submit
- form.success
- form.error
- phone.click
- cta.click

These events may be forwarded to Yandex Metrica and future analytics destinations.

---

# 64. Centralized Analytics Responsibility

Template and block developers should not independently implement analytics libraries for standard Landflow behavior.

Landflow should provide the event layer.

This allows:

Landflow Event
→ Yandex Metrica
→ future internal analytics
→ future external providers

without rewriting every template.

---

# 65. Workspace Assets

Workspace should eventually provide a reusable asset library.

Examples:

- logos
- banners
- backgrounds
- vehicle images
- icons
- promotional media
- documents

Assets may be reused across authorized Sites.

---

# 66. Draft State

Editing a Site must not immediately change the public production Site.

Conceptually:

Published Site
+
Draft

Users edit Draft.

Published remains unchanged until Publish.

---

# 67. Autosave

Designer changes should autosave to Draft.

Autosave does not equal Publish.

---

# 68. Preview

Users must be able to preview the current Draft before publication.

Preview should match the future published result as closely as possible.

---

# 69. Publishing

Publishing is explicit.

Draft
→ Validation
→ Publish
→ Published Version

Publishing permissions may differ from editing permissions.

---

# 70. Version History

Paid/Team plans may provide version history.

Conceptually:

Version 15 — Current
Version 14
Version 13

Users may eventually restore an earlier version.

Architecture should be versioning-ready even if advanced history is implemented later.

---

# 71. Landflow Subdomains

Landflow provides hosted subdomains.

Example:

dealer-name.landflow.me

Free Sites may publish to a Landflow subdomain.

Paid Sites may also use this as staging/preview.

---

# 72. Custom Domains

Paid plans may support custom domains.

Potential functionality:

- domain verification
- DNS status
- SSL
- primary domain
- redirects
- publication state

---

# 73. Landflow Branding

Free published Sites may show Landflow branding.

Paid plans may allow branding removal.

Branding rules must be controlled through subscription entitlements, not hardcoded into templates.

---

# 74. SEO

Site/Page SEO may include:

- URL slug
- title
- meta description
- H1
- canonical URL
- index/noindex
- Open Graph title
- Open Graph description
- Open Graph image

Automotive pages may later support dynamic SEO templates.

Example:

Buy {model} {trim} in {city} — from {price}

---

# 75. Subscription Model

Initial conceptual plans:

- Free
- Pro
- Team

Exact pricing and limits remain configurable.

---

# 76. Free Plan

Conceptual capabilities:

- one personal Workspace
- up to two free Sites
- free templates
- Landflow hosted subdomain
- Landflow branding
- limited resources
- limited collaboration

Exact limits may change before launch.

---

# 77. Pro Plan

Potential capabilities:

- more Sites
- custom domains
- remove Landflow branding
- complete SEO
- more storage
- premium features
- improved publishing/version capabilities

---

# 78. Team Plan

Designed for:

- agencies
- dealer groups
- larger automotive businesses

Potential capabilities:

- multiple members
- multiple Workspaces depending on plan
- many Sites
- roles
- Site-specific access
- shared assets
- Workspace Vehicle Library
- Site-to-Site vehicle import
- version history
- collaboration

---

# 79. Subscription Entitlements

Product behavior should use capabilities/entitlements rather than scattered direct plan-name checks.

Examples:

- max_sites
- max_members
- custom_domain
- remove_branding
- advanced_seo
- version_history
- workspace_vehicle_library
- site_vehicle_import
- developer_access

This allows plan structure to change without rewriting business logic.

---

# 80. Template Marketplace

Future Marketplace may contain:

- Templates
- Blocks

Marketplace items may include:

- author
- title
- description
- category
- screenshots
- preview
- demo
- version
- price
- free/paid status
- compatibility
- license
- ratings/reviews later

Marketplace implementation is not required for initial MVP unless scheduled.

Current scope (D-125, D-126): the public third-party Marketplace is deferred. The active product is Landflow's own Block / Template catalog. Free items are available to everyone; «По тарифу» items are available only to explicitly mapped active Plans; paid items may be purchased from any Plan, including Free; administrator grants work regardless of Plan. CatalogLicense scopes (D-121) and grandfathered installed versions (D-122) remain in force. Marketplace Listings (P10-001) exist as internal infrastructure only.

---

# 81. Marketplace Security

Marketplace developers must not automatically receive unrestricted arbitrary server-side execution rights.

Landflow should define a controlled component/block execution model.

Example Block Schema:

VehicleHero

Fields:

- title
- subtitle
- background
- vehicle
- show_price
- show_colors
- show_trims
- button_text
- button_action

Security boundaries must be defined before Marketplace launch.

Defined so far: untrusted authored code runs only in the opaque-origin sandbox (ADR-008); approved first-party code may run natively in the published Site after explicit approval of the exact source (ADR-009, D-123).

---

# 82. Developer Template Lifecycle

Potential lifecycle:

Draft
→ Testing
→ Submit for Review
→ Landflow Review
→ Published
→ Marketplace

Superseded direction: there is no review queue (D-120). Block lifecycle today: Draft → autosave → automated checks → sandbox preview → publish. Target for Native Blocks (D-123): the same, with an explicit «Одобрить и опубликовать» of the exact Draft revision / source hash by an authorized internal actor — a security trust step, not moderation.

---

# 83. Super Admin

Super Admin should eventually manage:

- Users
- Workspaces
- Sites
- Plans
- Subscriptions
- Templates
- Blocks
- Marketplace
- Developers
- Marketplace moderation
- Automotive Catalog
- Makes
- Models
- Series
- Generations
- Modifications
- Trims
- Characteristics
- Equipment
- Colors
- Images
- Domains
- Integrations
- feature flags
- platform settings

---

# 84. External Vehicle Sources

Future external automotive data sources may include:

- XML
- dealer feeds
- APIs
- import files
- external automotive systems

Conceptually:

External Source
→ Normalize
→ Automotive Data
→ Workspace/Site

Exact synchronization behavior must be separately designed.

---

# 85. Product Scope Exclusions

Landflow must not silently turn into an unrelated dealership CRM.

Unless explicitly added to the roadmap, core Landflow does not include:

- call center
- telephony
- employee task management
- internal team chat
- full warehouse CRM
- full lead sales pipeline CRM

Adjacent functionality required for websites is valid.

Example:

Form submissions are valid.

Building a full sales CRM around those submissions is a separate product decision.

---

# 86. UX Reference

Webflow is the primary UX/product reference.

Landflow should study concepts such as:

- Dashboard
- Workspaces
- Sites
- folders
- Templates
- Marketplace
- Designer
- Site Settings
- permissions
- publishing
- domains
- plans

Landflow uses these as references, not as copied proprietary source code or proprietary assets.

---

# 87. Automotive Differentiation

Landflow combines:

**professional visual website building**

with

**structured automotive data**

Example:

Global Catalog
→ Select UNI-K
→ select trims
→ configure Site prices
→ configure benefits
→ choose colors/images
→ Vehicle Card renders automatically

Templates determine presentation.

Automotive data determines structured content.

Site Offers determine customer-specific commercial data.

---

# 88. Central Platform Responsibility

The following systems should be controlled by Landflow itself rather than reimplemented differently by every developer block:

- Forms
- Popup actions
- CAPTCHA
- anti-spam
- analytics
- integration delivery
- action handling
- automotive bindings
- carousel capability
- lightbox behavior where applicable

This is essential for security and consistency.

---

# 89. MVP Principle

The existence of a feature in this document does **not** mean an implementation agent should build it immediately.

Every feature must be scheduled through the roadmap/backlog.

Implementation agents must only build currently assigned scope.

---

# 90. Proposed Delivery Phases

## Phase 0 — Foundation

- product documentation
- Webflow reference mapping
- architecture
- database design
- Cursor agent rules
- Definition of Done
- automated tests
- browser QA
- CI
- autonomous development workflow

## Phase 1 — Core Platform

- authentication (email/password with verification, Yandex ID)
- Workspace foundation
- Sites
- dashboard
- Site creation
- template selection
- basic permissions

## Phase 2 — Designer Foundation

- Designer shell
- pages
- blocks
- Block Schema
- Properties Panel
- global styles
- responsive behavior
- autosave
- preview
- basic Action System

## Phase 3 — Automotive Foundation

- Global Automotive Catalog
- makes
- models
- series
- generations
- modifications
- trims
- characteristics
- equipment
- colors
- multi-tone colors
- transparent vehicle images
- catalog import
- Site vehicle selection
- Site commercial offers

## Phase 4 — Forms and Interactive Components

- Popup System
- Form System
- context passing
- Gallery
- Lightbox
- Carousel capability
- anti-spam foundation
- SmartCaptcha
- submission persistence

## Phase 5 — Publishing

- Draft
- Preview
- Publish
- Landflow subdomains
- publication validation
- basic versioning

## Phase 6 — Integrations and Analytics

- Workspace Integration Profiles
- Site overrides
- field mapping
- form routing
- email
- API/webhook
- delivery logs
- retry
- Yandex Metrica
- internal event layer

## Phase 7 — Paid Site Features

- custom domains
- advanced SEO
- branding removal
- subscription entitlements

## Phase 8 — Team

- invitations
- roles
- permissions
- Site access
- Workspace assets
- Workspace Vehicle Library
- Site-to-Site vehicle import
- richer version history

## Phase 9 — Developer Platform

- Developer Dashboard
- Template creation
- Block creation
- AI-assisted schema proposal
- submission/review workflow

## Phase 10 — Marketplace

- free Marketplace
- paid Marketplace
- purchases
- licenses
- developer earnings

## Phase 11 — External Integrations

- vehicle feeds
- external APIs
- webhooks
- external CRM connectors
- advanced synchronization

The phase order may be refined after architecture design.

---

# 91. Product Source of Truth

This document defines **what Landflow is intended to become**.

It does not define exact technical implementation.

Technical decisions belong in architecture documentation.

Detailed UI behavior belongs in UI/UX specifications.

Current implementation status belongs in PROJECT_STATE.

Task ordering belongs in MASTER_PLAN / BACKLOG.

If an implementation agent finds a contradiction between a task and this Product Specification, it must report the contradiction rather than silently make a major product decision.

---

# 92. Current Product Definition

Landflow can currently be summarized as:

> A Webflow-inspired SaaS platform for creating, customizing, and publishing automotive websites using reusable templates, schema-driven blocks, structured automotive data, Site-specific commercial offers, reusable integrations, centralized forms/security/analytics, and future developer marketplace capabilities.

Primary customer workflow:

**Workspace → Create Site → Choose Template → Customize → Import/Configure Vehicles → Set Prices → Configure Forms/Integrations → Preview → Publish**

Primary developer workflow:

**Developer → Create Template/Block → Define/Confirm Schema → Test → Submit → Publish → Marketplace**

Primary administrative workflow:

**Super Admin → Manage Platform → Maintain Global Automotive Catalog → Moderate Developer Content → Control Plans/Features**
