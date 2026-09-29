# Webflow → Landflow Product Mapping

**Document:** `docs/product/WEBFLOW_TO_LANDFLOW.md`  
**Status:** Product/UX reference  
**Reference baseline:** September 2026  
**Primary reference:** Official Webflow product, pricing, Help Center, templates, Marketplace, components and design-system documentation.

---

# 1. Purpose of This Document

This document defines how Webflow should be used as a product and UX reference for Landflow.

It answers four questions for each major area:

1. What does Webflow conceptually do?
2. What principle should Landflow adopt?
3. How should Landflow change that principle for the automotive market?
4. When should Landflow implement it?

This document does **not** authorize copying:

- Webflow source code;
- proprietary assets;
- trademarks;
- exact visual compositions;
- proprietary text;
- internal implementation details.

Landflow should learn from the product model, information architecture, interaction patterns, and user expectations while implementing an original automotive-focused product.

---

# 2. Core Positioning

Webflow is a general-purpose professional website-building platform.

Landflow is a professional website-building and publishing platform specialized for automotive businesses.

Conceptually:

Webflow:

Workspace  
→ Site  
→ Designer  
→ CMS/content  
→ Preview/staging  
→ Publish

Landflow:

Workspace  
→ Site  
→ Template  
→ Designer  
→ Automotive Catalog / Site Vehicles  
→ Prices / Offers  
→ Forms / Integrations  
→ Preview  
→ Publish

The main Landflow differentiation is the structured automotive layer.

---

# 3. Fundamental Mapping

| Webflow Concept | Landflow Equivalent | Landflow Difference |
|---|---|---|
| Account | User Account | Same conceptual identity boundary |
| Workspace | Workspace | Also owns automotive libraries and integration profiles |
| Site | Site | Contains automotive selection, prices, forms and integrations |
| Dashboard | Workspace Dashboard | Optimized for dealer/agency Site management |
| Site folder | Site Folder | Same organization concept |
| Starter/staging Site | Free/Landflow-hosted Site | Published/staged on `*.landflow.me` |
| Custom domain | Custom Domain | Paid entitlement |
| Template | Automotive Site Template | Designed around dealer/vehicle workflows |
| Component | Block / Component | Schema-driven and automotive-data-aware |
| Component properties | Block Schema fields | Expanded with repeaters, vehicle bindings and actions |
| Shared Library | Workspace Assets / Block Library / Vehicle Library | Also contains reusable automotive content |
| Designer | Landflow Designer | More constrained initially, automotive-specialized |
| CMS | Structured Landflow content/data | Automotive catalog is a first-class platform domain |
| Forms | Landflow Form System | Central routing, CRM/API integration and anti-spam |
| Marketplace | Landflow Marketplace | Templates + Blocks first; automotive specialization |
| Site roles | Site Permissions | Granular permissions including prices/vehicles/integrations |
| Publishing permissions | Publish permission | Explicitly separated from editing |
| Staging subdomain | `*.landflow.me` | Free hosting + staging |
| Site plan | Site capabilities / subscription entitlements | Landflow plans may package Site and team capabilities differently |

---

# 4. Account and Workspace

## Webflow Reference

Webflow separates a user's account from Workspaces.

Sites live inside Workspaces.

A person may participate in more than one Workspace.

Workspace-level collaboration and permissions are separate from individual Site access.

## Landflow Decision

Adopt the same high-level ownership pattern:

User  
→ Workspace Membership  
→ Workspace  
→ Sites

Do not store company ownership directly on User.

A Workspace is the tenant/ownership boundary.

## Automotive Adaptation

Workspace additionally owns reusable business resources:

- Workspace Vehicle Library;
- Integration Profiles;
- shared automotive assets;
- logos;
- banners;
- dealership media;
- potentially reusable forms later (open decision D-082; current architecture: Forms are Site-owned, see `FORMS_AND_INTEGRATIONS.md` §3);
- potentially reusable pricing defaults later.

## Priority

**Foundation / Phase 1**

---

# 5. Workspace Dashboard

## Webflow Reference

Webflow's Dashboard is Workspace-oriented.

Users can:

- create Sites;
- browse Sites;
- organize Sites in folders;
- search;
- sort;
- switch Workspaces;
- access Site actions.

## Landflow Decision

Create a Workspace-centric dashboard.

The user should always understand:

- which Workspace is active;
- which Sites belong to it;
- Site status;
- publication/domain state;
- subscription/limits where relevant.

## Recommended Landflow Dashboard

Left/top Workspace switcher.

Main areas:

- Sites
- Templates
- Vehicle Library
- Assets
- Forms
- Integrations
- Members
- Billing/Plan
- Developer area where permitted

Site cards may show:

- Site name;
- preview thumbnail;
- domain;
- staging URL;
- Draft / Published status;
- last edited;
- last published;
- template;
- quick actions.

## Site Organization

Support:

- grid view;
- list view later;
- search;
- sort;
- folders.

## Automotive Adaptation

Useful later filters:

- brand;
- dealer;
- city;
- project type;
- active/published state.

Do not overcomplicate the first MVP.

## Priority

**Phase 1**

---

# 6. Site Creation

## Webflow Reference

Webflow allows users to start from:

- blank Site;
- template.

## Landflow Decision

Landflow should support:

- Blank Site;
- Template.

The main onboarding path should strongly favor Templates.

## Landflow Flow

Create Site  
→ Site name  
→ optional folder  
→ choose Template / Blank  
→ Site initialized  
→ open Site setup or Designer

## Automotive Adaptation

Optional future onboarding may ask:

- dealer brand;
- city;
- primary make;
- multi-brand vs mono-brand;
- new/used;
- preferred Site type.

These answers could preconfigure content but must not make onboarding too long.

## Priority

**Phase 1**

---

# 7. Site vs Workspace Subscription Concepts

## Webflow Reference

Webflow currently distinguishes Workspace plans from Site plans.

Workspace plans primarily affect staging/collaboration features.

Individual Sites can receive paid Site capabilities such as custom domains and increased Site limits.

## Landflow Decision

Do not blindly copy Webflow billing mechanics.

Adopt the **separation of concerns**, not necessarily the same commercial packaging.

Landflow should represent capabilities through entitlements.

Examples:

- max_sites;
- max_members;
- custom_domain;
- remove_branding;
- advanced_seo;
- version_history;
- site_vehicle_import;
- workspace_vehicle_library;
- developer_access.

## Reason

Pricing strategy will change.

Business logic must not contain scattered checks such as:

`if plan == "team"`.

## Priority

**Architecture-critical from Foundation**

Billing UI itself: later.

---

# 8. Free/Staging Sites

## Webflow Reference

Webflow provides a platform-controlled staging subdomain for Sites.

The staging URL is separate from a custom production domain.

## Landflow Decision

Every eligible Site should have a Landflow-hosted URL.

Example:

`dealer-name.landflow.me`

This URL may serve as:

- free public Site;
- staging Site;
- preview/approval environment.

## Free Plan

Free Sites may be publicly accessible at a Landflow subdomain and include mandatory Landflow branding.

## Paid Sites

Paid Sites may also keep a Landflow staging URL while attaching a custom domain.

## Search Indexing

Provide a setting to prevent staging environments from being indexed.

## Priority

**Publishing phase**

---

# 9. Custom Domains

## Webflow Reference

A paid Webflow Site may attach custom domains while the platform staging subdomain remains available.

## Landflow Decision

Adopt this pattern.

Site:

- `project.landflow.me`
- `dealer.ru`

Potential domain features:

- add domain;
- verify DNS;
- status checks;
- SSL;
- primary domain;
- redirect alternate hostnames;
- publish state.

## Priority

**Paid Site Features**

---

# 10. Designer Philosophy

## Webflow Reference

Webflow Designer provides a professional editing environment with Canvas and dedicated panels for structure, assets, properties, components, variables and related tools.

## Landflow Decision

Use the same **professional editor mental model**, but do not initially recreate the entire low-level Webflow CSS/DOM editor.

Landflow should begin as a schema/component-driven builder.

This drastically reduces complexity and increases reliability.

## Initial Landflow Designer

Core layout:

- top bar;
- left sidebar/panels;
- central canvas;
- right properties panel.

Potential left panels:

- Pages;
- Blocks;
- Navigator;
- Assets;
- Site Vehicles;
- Popups;
- Forms.

Right panel:

- Content;
- Design;
- Data;
- Actions;
- Responsive settings.

## Future

More advanced layout/style controls may be introduced after the schema-driven system is stable.

## Priority

**Phase 2**

---

# 11. Navigator / Page Structure

## Webflow Reference

Webflow exposes a hierarchy/navigation view of elements on the current page.

## Landflow Decision

Landflow should expose a hierarchical block tree.

Example:

Home

- Header
- Hero
  - Content
  - CTA
- Benefits
- Vehicle Grid
- Credit
- Contacts
- Footer

Users should be able to:

- select a Block;
- reorder Blocks;
- hide/show;
- duplicate;
- delete;
- navigate nested repeatable structures where appropriate.

## Important Difference

The initial Landflow Navigator should reflect Landflow Blocks/components rather than every raw DOM node.

## Priority

**Phase 2**

---

# 12. Components → Landflow Blocks

## Webflow Reference

Webflow Components are reusable layouts whose instances can share structure while exposing customizable properties.

## Landflow Decision

This is one of the most important Webflow ideas to adopt.

Landflow calls the primary reusable user-facing unit a **Block**.

Examples:

- Header;
- Hero;
- Benefits;
- Vehicle Grid;
- Vehicle Card;
- Trade-in;
- Credit;
- Footer.

## Difference

Landflow Blocks are driven by a deterministic **Block Schema**.

The Block Schema tells Landflow which properties a customer may edit.

## Priority

**Architecture-critical / Phase 2**

---

# 13. Component Properties → Block Schema

## Webflow Reference

Reusable components can expose properties so instances can contain unique content without changing the shared layout.

## Landflow Decision

Landflow expands this into a formal typed schema.

Supported conceptual field types:

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

Designer automatically generates editing UI from schema.

## Example

Hero Block:

- title: text
- subtitle: textarea
- background: image
- vehicle: vehicle
- show_price: boolean
- button: group

The developer should not have to separately program an administration screen.

## Priority

**Phase 2**

---

# 14. Slots, Nested Content and Repeaters

## Webflow Reference

Modern component systems support configurable content within reusable structures.

## Landflow Decision

Landflow must explicitly support:

- groups;
- repeaters;
- nested schema fields.

## Example

Benefits:

Repeater:
- icon
- title
- description

User can:

- add item;
- remove item;
- reorder;
- edit.

## Automotive Example

Featured trims:

Repeater:
- trim
- badge
- custom benefit
- CTA

## Priority

**Phase 2**

---

# 15. Variables and Design System

## Webflow Reference

Webflow design systems use reusable variables, styles, components and shared libraries.

## Landflow Decision

Each Site should have a Site Design System.

Initial tokens/settings may include:

- primary color;
- secondary color;
- text color;
- background color;
- typography;
- heading font;
- body font;
- border radius;
- container width;
- spacing;
- button styles.

Blocks should reference Site tokens by default.

Per-block overrides may be allowed where schema permits.

## Goal

Changing the Site brand should update compatible Blocks consistently.

## Priority

**Phase 2**

---

# 16. Shared Libraries

## Webflow Reference

Webflow Shared Libraries can distribute reusable components, variables, fonts and assets across Sites in a Workspace.

## Landflow Decision

Adopt the idea but separate it into clearer automotive concepts.

Landflow Workspace may eventually contain:

### Workspace Assets
- logos;
- images;
- banners;
- documents;
- icons.

### Workspace Block Library
- approved reusable Blocks;
- organization-specific Blocks.

### Workspace Vehicle Library
- imported/prepared automotive data;
- photos;
- reusable descriptions.

## Important Difference

Vehicle data should not be treated as a generic visual asset.

It remains a structured automotive domain.

## Priority

Assets: earlier.

Shared Block Library and Vehicle Library: Team/later.

---

# 17. Assets

## Webflow Reference

Webflow has an Asset panel for uploaded media and shared assets.

## Landflow Decision

Landflow Designer needs an Asset browser.

Sources may include:

- Site Assets;
- Workspace Assets;
- Global automotive vehicle media where relevant.

## Automotive Adaptation

Image chooser may show:

- Workspace uploads;
- Site uploads;
- vehicle images;
- color-specific vehicle images.

The user should not need to manually download a Global Catalog car image and re-upload it.

## Priority

**Designer + Automotive phases**

---

# 18. Templates

## Webflow Reference

Webflow provides free and premium templates as starting points that users can customize.

## Landflow Decision

Adopt this model.

Landflow Templates are starting points for automotive Sites.

Templates may contain:

- Pages;
- Blocks;
- design tokens;
- default content;
- forms;
- popup definitions;
- expected automotive data bindings.

## Critical Rule

Template must not own customer automotive data.

Template says **how vehicle data is rendered**.

Site says **which vehicles and commercial offers exist**.

## Priority

Free official templates: early.

Marketplace/premium templates: later.

---

# 19. Template Instantiation

When a user chooses a Template:

Template
→ instantiate/copy required Site structure
→ Site becomes independently editable

A later Template update must not unexpectedly overwrite an existing Site.

Any future update system must be explicit and safe.

## Priority

**Phase 1/2**

---

# 20. Marketplace

## Webflow Reference

Webflow Marketplace includes templates and reusable/community resources.

Templates may be free or premium, and Webflow operates a creator submission/review ecosystem.

## Landflow Decision

Landflow Marketplace should initially focus on two product types:

1. Templates
2. Blocks

Possible later extensions:

- libraries;
- integrations;
- specialized automotive widgets.

## Automotive Categories

Examples:

- Official Dealer;
- Multi-brand;
- Chinese brands;
- Used Cars;
- Model Landing;
- Credit;
- Trade-in;
- Vehicle Grid;
- Vehicle Card;
- Promotion Hero.

## Priority

**Later**

---

# 21. Developer Creator Flow

## Webflow Reference

Webflow allows creators to submit templates for review and distribute/sell them.

## Landflow Decision

Landflow Developer Platform should have a controlled publishing lifecycle:

Draft  
→ Test  
→ Submit  
→ Review  
→ Approved  
→ Published

## Developer Dashboard

Potential navigation:

- My Templates
- My Blocks
- Assets
- Marketplace
- Sales
- Earnings
- Settings

## Priority

**Developer Platform phase**

---

# 22. Developer Safety

Landflow must diverge from a generic "upload arbitrary PHP/JS" extension model.

Developers should not receive unrestricted code execution on Landflow infrastructure.

Use:

- controlled Block Schema;
- approved rendering APIs;
- safe action definitions;
- safe data-binding interfaces.

AI may help infer a schema during block authoring, but the saved deterministic schema becomes the source of truth.

## Priority

**Architecture-critical before Marketplace**

---

# 23. Automotive Catalog vs Webflow CMS

## Webflow Reference

Webflow provides structured content systems for repeatable dynamic content.

## Landflow Decision

Do not model the automotive catalog merely as generic CMS rows.

The automotive catalog is a first-class bounded domain.

Hierarchy:

Make  
→ Model  
→ Series  
→ Generation  
→ Modification  
→ Trim

With:

- characteristics;
- options;
- colors;
- images;
- technical data.

## Why

Automotive relationships, variants, commercial offers, colors and image sets are too important to be represented only as arbitrary generic fields.

## Priority

**Phase 3**

---

# 24. Global Catalog Ownership

Only Landflow Super Admin / authorized platform catalog managers may modify the Global Automotive Catalog.

Customer:

Global Catalog  
→ selects vehicle  
→ imports/copies it  
→ customizes customer-owned representation

Customer modifications never mutate the master catalog.

## Priority

**Phase 3**

---

# 25. Vehicle Images and Colors

Landflow must exceed generic Webflow image/content handling for automotive use.

Support:

- transparent-background vehicle images;
- color-specific image sets;
- multiple angles;
- ordering;
- trim linkage where required;
- multi-tone colors.

Example:

White Body + Black Roof

Color UI should be able to display a divided/multi-part swatch.

## Priority

**Phase 3**

---

# 26. Site Vehicle Offers

The Site should control commercial representation.

Potential fields:

- RRP;
- current price;
- benefits;
- badges;
- availability;
- sort order;
- custom text;
- CTA;
- Site image overrides.

These values do not belong to the Global Catalog.

## Priority

**Phase 3**

---

# 27. Data Binding

## Webflow Reference

Reusable components and structured content can be connected so layouts display changing data.

## Landflow Decision

Blocks should support explicit Landflow Data Sources.

Example:

Vehicle Card:

- title → vehicle.model.name
- trim → site_vehicle.trim
- price → site_offer.price
- image → selected_color.images
- colors → vehicle.colors

## Sources

Possible sources:

- Manual;
- Site Vehicles;
- selected vehicle;
- Workspace data;
- future external normalized data.

## Priority

**Phase 2 architecture / Phase 3 implementation**

---

# 28. Collection Rendering

A Block may render a collection.

Example:

Vehicle Grid

Source:
Site Vehicles

Filter:
Active only

Sort:
Site-defined ordering

Renderer:
Vehicle Card Block

Presentation:
Grid or Carousel

## Priority

**Automotive + Designer**

---

# 29. Carousel

Webflow is only a UX reference here; Landflow should define its own platform abstraction.

Do not couple product logic to Swiper, Splide or any specific library.

Landflow capability:

Carousel

Settings may include:

- slides per view;
- gap;
- arrows;
- pagination;
- loop;
- autoplay;
- speed;
- responsive settings.

Blocks may opt into carousel support.

## Example

Benefits:
Grid / Carousel

Vehicles:
Grid / Carousel

Promotions:
Grid / Carousel

## Priority

**Interactive components phase**

---

# 30. Gallery and Lightbox

Landflow should distinguish:

- Gallery = media collection;
- Carousel = presentation;
- Lightbox = media viewer;
- Popup = business/content modal.

Vehicle gallery may:

Gallery  
→ Carousel  
→ click image  
→ Lightbox

Do not permanently bind architecture to Fancybox.

## Priority

**Interactive components phase**

---

# 31. Popup System

A Popup is a reusable Site entity.

Examples:

- Callback;
- Get Offer;
- Test Drive;
- Credit;
- Trade-in.

Many Blocks may open the same Popup.

## Difference from generic Webflow patterns

Landflow Popup should deeply integrate with:

- Form System;
- automotive context;
- Site Offers;
- integrations;
- analytics.

## Priority

**Interactive components phase**

---

# 32. Action System

Every interactive Block should use a standardized Action System.

Potential actions:

- Open URL;
- Open Page;
- Scroll to Section;
- Open Popup;
- Phone;
- Email;
- Submit Form.

Developers should not implement unique click handling for standard actions.

## Priority

**Phase 2 foundation**

---

# 33. Automotive Context Passing

Example:

User clicks "Get Offer" on:

Changan UNI-K  
Luxe  
Black  
3,890,000 ₽

Landflow Action opens a Popup and passes:

- Site Vehicle;
- trim;
- color;
- price;
- source Block;
- page.

The Form inherits this context.

## Priority

**Interactive components phase**

---

# 34. Forms

## Webflow Reference

Forms are a platform feature rather than something every template creator reinvents.

## Landflow Decision

Adopt this principle and extend it substantially.

Form should be reusable independently from Popup.

Example:

Form "Get Offer"

May appear:

- inside Hero;
- inside Popup;
- in Vehicle Card flow;
- standalone section.

## Priority

**Interactive components phase**

---

# 35. Form Routing and Integrations

This is a Landflow specialization.

Submission flow:

Form  
→ validation  
→ anti-spam  
→ save Submission  
→ routing  
→ delivery destinations

Destinations:

- Email;
- CRM;
- API;
- Webhook.

One submission may have multiple destinations.

## Priority

**Integrations phase**

---

# 36. Workspace Integration Profiles

A reusable Integration Profile should live at Workspace level.

Example:

Dealer CRM

Shared:

- Base URL;
- Token;
- Secret;
- timeout;
- default mapping.

Site-specific override:

- site_id;
- dealer_id;
- source_id.

This supports multi-Site dealer groups without duplicating credentials.

## Priority

**Integrations phase**

---

# 37. Site-to-Site Reuse

Webflow's reusable Workspace resources inspire this principle, but Landflow extends it to automotive and integration data.

When creating another Site, authorized users may reuse/copy:

- Site Vehicles;
- prices;
- images;
- forms;
- integration references;
- field mapping;
- configuration.

Default behavior is copy, not silent synchronization.

## Priority

**Team phase**

---

# 38. Roles and Permissions

## Webflow Reference

Webflow distinguishes Workspace roles and Site roles and supports Site-specific access and publishing permissions on relevant plans.

## Landflow Decision

Adopt the same separation.

### Workspace permissions

Examples:

- manage Workspace;
- manage members;
- billing;
- integrations;
- shared resources.

### Site permissions

Examples:

- access Site;
- edit design;
- edit content;
- edit vehicles;
- edit prices;
- edit forms;
- edit integrations;
- edit SEO;
- manage domains;
- publish.

## Important Automotive Difference

Someone may be allowed to edit content but **not prices**.

A pricing manager may edit prices without changing design.

## Priority

Basic permissions: Phase 1.

Granular Team roles: Team phase.

---

# 39. Publishing Permission

Publishing must not be implied by edit permission.

Designer can edit Draft.

Another role may approve/publish.

This is especially important for agencies and dealer groups.

## Priority

Architecture from Phase 1.

Advanced workflows later.

---

# 40. Draft, Preview, Staging and Production

## Webflow Reference

Webflow separates editing from publication and provides staging and production publishing controls.

## Landflow Decision

Landflow must separate:

- Draft;
- Preview;
- Staging/Landflow-hosted version;
- Published Production version.

Autosave writes Draft.

Autosave never equals Publish.

## Priority

**Publishing phase**

---

# 41. Versioning

Webflow's professional publishing model reinforces the need for safe release workflows.

Landflow should be architecture-ready for:

- publication snapshots;
- version history;
- restore;
- audit trail.

Do not implement Git-like complexity initially.

## Priority

Basic snapshot: Publishing.

Rich history: Team/paid later.

---

# 42. Site Settings

Landflow should have a dedicated Site Settings environment separate from Designer.

Potential areas:

- General;
- Branding;
- Domain;
- SEO;
- Vehicles;
- Forms;
- Integrations;
- Analytics;
- Security;
- Publishing;
- Members/access;
- Plan.

Designer handles visual composition.

Site Settings handles Site-level operational configuration.

## Priority

Incremental across phases.

---

# 43. SEO

Adopt professional Site/Page SEO management.

Potential fields:

- slug;
- title;
- meta description;
- H1;
- canonical;
- index/noindex;
- Open Graph data.

## Automotive Adaptation

Future dynamic templates:

`Купить {model} {trim} в {city} — от {price}`

SEO values must still be reviewable/editable by the customer.

## Priority

Basic SEO before production launch.

Advanced SEO on paid tiers later.

---

# 44. Analytics

Landflow should not require every Template developer to paste analytics scripts.

Provide platform-level analytics events.

Primary Russian-oriented integration:

Yandex Metrica.

Common Landflow events may include:

- page.view;
- vehicle.view;
- vehicle.click;
- popup.open;
- form.start;
- form.submit;
- form.success;
- phone.click;
- cta.click.

## Priority

**Integrations/analytics phase**

---

# 45. Russian Service Orientation

For the intended Russian-market Site ecosystem, default supported services should prioritize appropriate Russian services where this is a product requirement.

Confirmed product requirements include:

- Yandex Metrica;
- Yandex SmartCaptcha.

Exact implementation must be based on current official vendor documentation when built.

Do not embed service-specific logic deeply into Block implementations.

Use platform adapters/services.

## Priority

Relevant infrastructure phases.

---

# 46. Anti-Spam

Landflow should improve on a basic generic form flow through centralized anti-abuse protection.

Layers:

- honeypot/basic checks;
- rate limit;
- IP blacklist;
- phone blacklist;
- duplicate submission rules;
- Yandex SmartCaptcha.

Protection must be platform-controlled.

A Template developer should not be able to accidentally create an unprotected standard Landflow Form.

## Priority

**Forms phase**

---

# 47. Submission Reliability

Unlike a purely visual builder, Landflow serves lead-generation automotive Sites.

A lead must not disappear because the customer's CRM is temporarily unavailable.

Flow:

Validate  
→ Anti-Spam  
→ Save Submission  
→ Queue delivery  
→ CRM/API/Email  
→ status  
→ retry if needed

Maintain Delivery Logs.

## Priority

**Integrations phase**

---

# 48. Site Duplication / Copy

A useful professional builder pattern is rapid Site reuse.

Landflow should eventually support Site duplication or selective configuration import.

Possible selections:

- design;
- Pages;
- content;
- vehicles;
- prices;
- forms;
- integration profile references;
- mappings;
- SEO;
- assets.

Secret credentials should be referenced safely rather than copied into plain configuration.

## Priority

**Team / efficiency phase**

---

# 49. What Landflow Should NOT Copy from Webflow Initially

Landflow should not attempt an immediate 1:1 recreation of every Webflow capability.

Do **not** make these MVP blockers:

- arbitrary low-level HTML element authoring;
- complete CSS property editor;
- custom raw JavaScript ecosystem;
- full generic CMS;
- ecommerce;
- localization;
- enterprise-grade approval workflows;
- arbitrary app marketplace;
- full real-time multiplayer Designer;
- code export;
- every animation/interactions feature;
- branch-based publishing.

These may be reconsidered only when they solve an actual Landflow customer problem.

---

# 50. What Landflow Should Do Better for Automotive

Landflow should outperform a generic builder in workflows such as:

### Vehicle selection

Global Catalog  
→ find model  
→ select trims  
→ import

### Commercial setup

Vehicle  
→ RRP  
→ price  
→ benefits  
→ badge  
→ CTA

### Vehicle visual setup

Vehicle  
→ color  
→ transparent images  
→ Site override

### Reuse

Workspace Vehicle Library  
→ multiple Sites

### Dealer-group rollout

Copy Site configuration  
→ change city/site_id/prices  
→ publish another dealer Site

### Lead integration

Vehicle CTA  
→ contextual Popup  
→ Form  
→ CRM/API/email

### Analytics

Standard automotive events automatically mapped into Yandex Metrica.

These flows are core Landflow differentiation.

---

# 51. UX Principle: Progressive Complexity

Webflow is powerful but professional tools can become complex.

Landflow should preserve a simpler default experience.

Beginner/customer:

- choose Template;
- edit visible content;
- choose vehicles;
- change prices;
- configure forms;
- publish.

Advanced designer:

- edit Blocks;
- responsive settings;
- design tokens;
- layout options;
- data bindings.

Developer:

- build Blocks;
- define schema;
- configure safe bindings;
- create Templates.

Do not show developer-level complexity to normal customers by default.

---

# 52. UX Principle: Contextual Editing

When a user clicks a Block, the right panel should show only relevant properties.

Example Vehicle Grid:

Content:
- heading
- subtitle

Data:
- source
- vehicles/filter
- ordering

Layout:
- grid/carousel
- columns
- gap

Design:
- typography
- spacing
- colors

Actions:
- card CTA behavior

Do not expose irrelevant global settings.

---

# 53. UX Principle: Safe Editing

Users should be able to customize a template without easily destroying its structure.

Prefer:

- schema-controlled fields;
- defined layout choices;
- safe responsive controls;
- reusable tokens.

Advanced freedom can grow later.

This is a deliberate difference from attempting to reproduce every low-level Designer control immediately.

---

# 54. UX Principle: Preview Must Be Trustworthy

The customer should be confident that Preview reflects the published result.

Preview should use:

- actual Site data;
- actual selected vehicles;
- current Draft;
- actual responsive rules;
- safe simulation of Forms where necessary.

Browser QA must test Preview and Published output.

---

# 55. UX Principle: No Hidden Synchronization

Whenever data is reused, the UI must communicate whether it is:

- linked/shared;
- copied;
- overridden.

Examples:

Global vehicle → customer import:
customer can override.

Site A → Site B:
copy by default.

Workspace Integration Profile:
shared reference + Site overrides.

This prevents confusing cross-Site changes.

---

# 56. Landflow Product Hierarchy

The main hierarchy should remain understandable:

Account  
→ Workspace  
→ Site  
→ Page  
→ Block

Automotive hierarchy:

Global Catalog  
→ Workspace Vehicle Library  
→ Site Vehicle / Site Offer

Interaction hierarchy:

Block  
→ Action  
→ Popup  
→ Form  
→ Submission  
→ Integration Routing

Developer hierarchy:

Developer  
→ Block / Template  
→ Review  
→ Marketplace

---

# 57. Implementation Priority Mapping

## Foundation

- Workspace model
- Site model
- entitlement architecture
- product rules
- testing and automation

## Core Product

- Workspace Dashboard
- Site creation
- Templates
- basic Site permissions

## Designer

- Pages
- Blocks
- Block Schema
- Navigator
- Properties Panel
- Design System
- Assets
- Action System

## Automotive

- Global Catalog
- vehicle hierarchy
- colors/images
- customer import/fork
- Site Vehicles
- Site Offers
- automotive bindings

## Interactive

- Popup
- Forms
- Gallery
- Lightbox
- Carousel
- context passing
- anti-spam

## Publishing

- Draft
- Preview
- staging
- production publish
- Landflow subdomains

## Integrations

- Integration Profiles
- Site overrides
- field mapping
- delivery queue/logs
- Yandex Metrica

## Paid / Team

- custom domains
- SEO
- branding controls
- member roles
- Site-specific access
- Vehicle Library
- Site-to-Site copy
- version history

## Developer / Marketplace

- Block authoring
- Template authoring
- schema assistance
- review
- distribution
- payments/licensing later

---

# 58. Rules for Cursor and Future Agents

When implementing Landflow, agents must use this document as a **reference map**, not a mandate to reproduce all of Webflow.

Agents must:

1. Follow the current Landflow roadmap/task.
2. Preserve the concepts defined in `PRODUCT.md`.
3. Use Webflow-inspired UX patterns only where documented or appropriate.
4. Never introduce a major Webflow feature merely because Webflow has it.
5. Never copy proprietary Webflow source/assets.
6. Prefer Landflow's automotive-specific workflow when generic Webflow behavior conflicts with automotive usability.
7. Preserve Workspace/Site ownership boundaries.
8. Preserve Global Catalog/customer override boundaries.
9. Preserve Draft/Publish separation.
10. Preserve centralized Form/Security/Analytics systems.
11. Report architecture/product contradictions rather than silently redesigning the product.

---

# 59. Reference Facts Verified in September 2026

The following Webflow concepts were used as current reference points when this document was written:

- Webflow accounts operate with Workspaces and Sites.
- A Site lives inside a Workspace.
- Webflow provides a free Workspace tier.
- Workspace plans and Site plans are separate concepts.
- Webflow provides staging sites/subdomains.
- Custom domains are associated with paid Site capabilities.
- Workspace and Site roles are distinct.
- Site-specific access and publishing permissions exist on relevant Workspace tiers.
- Dashboard supports Site creation, search, sorting and folders.
- Webflow Components support reusable layouts with customizable instance content/properties.
- Webflow design systems use variables, components, templates and shared Libraries.
- Shared Libraries can reuse components/variables/assets across Sites.
- Webflow has free and premium Templates.
- Webflow operates a Marketplace and a template creator submission/review workflow.

These facts are references only. Landflow commercial rules remain independent.

---

# 60. Official Webflow Sources Used for This Mapping

Official references checked during preparation:

- Webflow Pricing  
  https://webflow.com/pricing

- Webflow Dashboard  
  https://help.webflow.com/hc/en-us/articles/33961328364691

- Webflow Workspace roles and permissions  
  https://help.webflow.com/hc/en-us/articles/41015530193811-Workspace-roles-and-permissions

- Webflow Site roles and permissions  
  https://help.webflow.com/hc/en-us/articles/41015796747667-Site-roles-and-permissions

- Webflow Publishing  
  https://help.webflow.com/hc/en-us/articles/33961351954579-How-do-I-publish-or-unpublish-a-Webflow-site

- Webflow Staging Subdomain  
  https://help.webflow.com/hc/en-us/articles/33961419650067-Webflow-staging-subdomain

- Webflow Components  
  https://help.webflow.com/hc/en-us/articles/33961303934611-Components-overview

- Webflow Design Systems  
  https://help.webflow.com/hc/en-us/articles/41959932025235-Using-a-design-system-in-Webflow

- Webflow Libraries  
  https://help.webflow.com/hc/en-us/articles/33961343551763-Libraries

- Webflow Marketplace  
  https://webflow.com/marketplace

- Webflow Marketplace overview  
  https://help.webflow.com/hc/en-us/articles/33961398704915-Webflow-Marketplace-overview

- Webflow Templates  
  https://webflow.com/templates

- Webflow Template Submission  
  https://webflow.com/templates/submit-a-template

Before implementing behavior that depends on current Webflow functionality, pricing, or limits, agents should verify current official documentation rather than assume this September 2026 snapshot remains unchanged.

---

# 61. Final Landflow Interpretation

The goal is **not**:

> Clone Webflow and add cars.

The goal is:

> Build a professional Webflow-inspired SaaS operating model specifically around creating, managing and publishing automotive websites.

Landflow should feel familiar to users of professional visual builders while making automotive workflows dramatically easier.

The defining flow is:

**Workspace  
→ Site  
→ Template  
→ Designer  
→ Automotive Catalog  
→ Site Vehicles & Offers  
→ Forms & Integrations  
→ Preview  
→ Publish**

And the key architectural principle remains:

**Design is reusable.  
Automotive data is structured.  
Commercial data is Site-specific.  
Integrations are reusable.  
Publishing is controlled.**
