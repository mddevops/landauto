# Landflow — Permissions and Authorization Model

**Document:** `docs/architecture/PERMISSIONS.md`  
**Status:** Authorization source of truth  
**Purpose:** Define roles, permissions, Site-level access, entitlement interaction, platform roles, and authorization resolution rules before implementation.

---

# 1. Authorization Goals

Landflow authorization must support:

- Workspace-level roles;
- Site-specific access;
- granular permissions;
- separate publishing permission;
- separate price-editing permission;
- separate integration-management permission;
- separate submission/lead access;
- future custom roles;
- platform/Super Admin roles;
- plan entitlements without mixing them with permissions.

Authorization must be explicit, testable, and enforced on the backend.

---

# 2. Permission vs Entitlement

These concepts are different.

## Permission

Answers:

> Is this User allowed to perform this action?

Example:

`edit_prices = true`

## Entitlement

Answers:

> Is this capability included in the Workspace/Site subscription?

Example:

`custom_domain = true`

Both must pass when relevant.

---

# 3. Authorization Resolution

Conceptually:

Platform restriction
→ User authentication
→ Workspace membership
→ Workspace role permissions
→ Site access
→ Site-specific permissions
→ Subscription entitlement
→ Feature flag
→ domain/business invariant

If any required check fails, the action is denied.

---

# 4. Default Deny Principle

When permission is unclear:

**deny by default**.

Do not infer permission from:

- UI visibility;
- ownership assumptions;
- creator identity;
- Site ID knowledge;
- route existence.

---

# 5. Workspace Roles

Initial conceptual roles:

- Owner
- Admin
- Designer
- Content Editor

Additional roles may later include:

- Pricing Manager
- Lead Manager
- Integrations Manager
- Publisher
- Analyst

Roles are collections of permissions.

Business logic must use permissions rather than hardcoded role-name checks where possible.

---

# 6. Owner Role

Owner is the highest customer Workspace role.

Typical permissions:

- manage_workspace
- manage_members
- manage_roles
- manage_billing
- create_sites
- delete_site
- edit_design
- edit_content
- edit_vehicles
- edit_prices
- edit_forms
- manage_integrations
- view_submissions
- export_submissions
- edit_seo
- manage_domains
- publish_site
- manage_workspace_assets
- manage_workspace_vehicle_library

Owner status itself may remain a special ownership property, but day-to-day access should still resolve through permissions.

---

# 7. Admin Role

Admin generally manages most Workspace operations except highly sensitive ownership/billing actions depending on product policy.

Typical permissions:

- create_sites
- delete_site
- edit_design
- edit_content
- edit_popups (D-108)
- edit_vehicles
- edit_prices
- edit_forms (also Site form security and Site blacklist, D-108)
- view_integrations, manage_integrations, edit_form_routes, view_delivery_logs, retry_deliveries (D-109)
- view_submissions
- edit_seo
- manage_domains
- publish_site
- manage_members

Potentially excluded by default:

- transfer_workspace_ownership
- delete_workspace
- manage_billing

Exact defaults may evolve.

---

# 8. Designer Role

Designer focuses on Site appearance and structure.

Typical permissions:

- view_site
- edit_design
- edit_content
- manage_assets
- edit_popups

Not automatically granted:

- edit_prices
- manage_integrations
- view_submissions
- publish_site
- manage_domains
- manage_billing

Designer should not gain commercial/operational permissions merely because they edit pages.

---

# 9. Content Editor Role

Typical permissions:

- view_site
- edit_content
- edit_text
- edit_images
- edit_seo_basic
- maybe edit_vehicle_descriptions

Not automatically granted:

- edit_layout
- edit_prices
- manage_integrations
- manage_domains
- publish_site
- view_submissions

---

**Implemented in Phase 8 (P8-004, D-114).** Sections 10–13 below are the original design notes; the code matrix in `WorkspacePermissionResolver` is authoritative:

- `pricing_manager`: view_site, view_vehicles, edit_prices, edit_benefits.
- `lead_manager`: view_site, view_submissions, view_delivery_logs, retry_deliveries — no `export_submissions` while D-094 is open.
- `integrations_manager`: view_site, view_integrations, manage_integrations, edit_form_routes, view_delivery_logs, retry_deliveries — no `view_submissions`; always `all_sites`.
- `publisher`: view_site, preview_site, publish_site — no `restore_version`.
- Admin additionally has `import_vehicles`. Role changes need `manage_roles` (Owner); Owner is never assignable. Site access scope (`all_sites` / `selected_sites`) limits which Sites a role applies to; there are no Site-specific roles (D-114 supersedes the future pattern in §80–§82 for Phase 8).

---

# 10. Pricing Manager Role

Potential Team role.

Typical permissions:

- view_site
- view_vehicles
- edit_prices
- edit_benefits
- edit_vehicle_availability

Not automatically granted:

- edit_design
- manage_integrations
- publish_site

---

# 11. Lead Manager Role

Potential Team role.

Typical permissions:

- view_submissions
- export_submissions
- retry_delivery if permitted
- view_delivery_status

Not automatically granted:

- edit_design
- edit_prices
- manage_integrations credentials
- publish_site

---

# 12. Integrations Manager Role

Potential Team role.

Typical permissions:

- manage_integrations
- edit_form_routes
- view_delivery_logs
- retry_deliveries

Potentially excluded:

- view_submission_content unless separately granted.

This separation is important because API credentials and lead personal data are different security domains.

---

# 13. Publisher Role

Potential role for agencies.

Typical permissions:

- view_site
- preview_site
- publish_site
- view_publication_history

Not automatically granted:

- edit_design
- edit_prices
- manage_integrations

---

# 14. Permission Categories

Permissions should be grouped conceptually.

## Workspace

- view_workspace
- edit_workspace
- manage_members
- manage_roles
- manage_billing
- delete_workspace
- transfer_workspace_ownership

## Sites

- create_sites
- view_site
- edit_site_settings
- duplicate_site
- delete_site

## Design

- edit_design
- edit_content
- manage_assets
- edit_popups
- edit_forms

## Automotive

- view_vehicles
- edit_vehicles
- edit_prices
- edit_benefits
- import_vehicles
- manage_workspace_vehicle_library

## Integrations

- view_integrations
- manage_integrations
- edit_form_routes
- view_delivery_logs
- retry_deliveries

## Leads / Submissions

- view_submissions
- export_submissions
- delete_submissions if ever allowed

## SEO / Domain

- edit_seo
- manage_domains

## Publishing

- preview_site
- publish_site
- restore_version

## Developer

- access_developer_platform
- create_blocks
- create_templates
- submit_marketplace_item

---

# 15. Fine-Grained Permissions

Do not make roles too broad if the product needs separation.

Especially important permissions:

- edit_prices
- manage_integrations
- view_submissions
- publish_site
- manage_domains

These must remain independently assignable.

---

# 16. Site-Level Access

A Workspace member may have:

- all Sites access;
- selected Sites only.

Future Team plans may support Site-specific assignment.

Conceptually:

Workspace Member
→ Site Access
→ Site Permission Overrides

---

# 17. Site Access Modes

Potential modes:

- all_sites
- selected_sites

If `selected_sites`, membership requires explicit Site access records.

Exact implementation belongs in database/auth design.

---

# 18. Site Permission Overrides

Site-level configuration may:

- grant a permission;
- remove a permission;
- assign a Site-specific role.

Avoid overly complex deny/allow matrices in MVP.

Recommended initial model:

Workspace role defines baseline.

Site assignment determines whether User may enter Site.

Optional Site-specific role may define effective Site permissions.

---

# 19. Recommended MVP Permission Resolution

For initial Team implementation:

1. User must be active Workspace member.
2. Workspace role grants general permissions.
3. If Site restrictions are enabled, User must have Site access.
4. Optional Site role can replace/limit Site-related Workspace permissions.
5. Required entitlement must exist.

Avoid per-permission allow/deny overrides initially unless clearly needed.

---

# 20. Future Custom Roles

Team plan may eventually allow custom roles.

Example:

Role: Vehicle Content Manager

Permissions:

- view_site
- edit_vehicles
- edit_vehicle_descriptions
- edit_vehicle_images
- no edit_prices
- no publish

Custom role support should use the same permission catalog.

---

# 21. System Roles vs Custom Roles

System roles:

- maintained by Landflow;
- have default permissions;
- may evolve carefully.

Custom roles:

- Workspace-owned;
- configured by authorized users.

Avoid making custom role names drive business logic.

---

# 22. Owner Special Rules

Workspace must always have at least one Owner.

Owner cannot be removed/demoted if doing so leaves Workspace without an Owner.

Ownership transfer must be explicit.

---

# 23. Invite Permissions

Permission to invite users:

`manage_members`

Invite creator may choose allowed role only within their own authority.

Example:

Admin should not create an Owner unless product policy explicitly permits it.

---

# 24. Role Escalation Protection

A User must not grant permissions exceeding their own authority where policy prohibits it.

Example:

Content Editor cannot assign Admin role.

Role-management operations require explicit checks.

---

# 25. Self-Modification Rules

Users should not be able to escalate their own permissions.

Example:

Admin edits own membership
→ cannot add Owner-only capabilities.

Owner may modify others according to product policy.

---

# 26. Site Creation

Required:

- active Workspace membership;
- `create_sites` permission;
- `max_sites` entitlement limit not exceeded.

Both authorization and entitlement checks are mandatory.

Only active Sites count toward `max_sites`. Archived Sites do not consume a slot. A future restore from archived to active must re-check the effective limit and deny restoration when the active-Site limit is already reached (D-099).

---

# 27. Site Deletion

Required:

- `delete_site`;
- access to Site;
- destructive-action confirmation;
- domain/publication safety checks.

Potentially restrict deletion to Owner/Admin by default.

---

# 28. Site Duplication

Required:

- read access to source Site;
- create permission in destination Workspace;
- feature entitlement if duplication is paid;
- access to copied dependent resources.

---

# 29. Design Editing

`edit_design` may allow:

- add/remove/reorder Blocks;
- modify layout;
- modify visual properties;
- responsive settings.

It should not imply:

- edit_prices;
- manage_integrations;
- publish_site.

---

# 30. Content Editing

`edit_content` may allow:

- text;
- standard images;
- labels;
- descriptions;
- non-sensitive content fields.

It should not automatically allow structural layout changes if `edit_design` is absent.

---

# 31. Automotive Vehicle Editing

`edit_vehicles` may allow:

- import from Global Catalog;
- activate/deactivate Site Vehicle;
- edit Site vehicle description;
- change customer vehicle photos;
- choose colors/trims.

Price changes require separate `edit_prices`.

---

# 32. Price Editing

`edit_prices` controls:

- RRP;
- selling price;
- benefit values;
- monthly payment values;
- commercial price-related fields.

Do not bundle this with generic content editing.

Price changes should eventually be auditable.

---

# 33. Benefit Editing

Initially `edit_prices` may include benefits.

If customers later need separation, introduce:

`edit_benefits`

without redesigning whole authorization model.

---

# 34. Global Catalog Permissions

Customer permissions never grant Global Catalog writes.

Global permissions may include:

- manage_catalog
- manage_catalog_makes
- manage_catalog_models
- manage_catalog_trims
- manage_catalog_media

These belong to platform roles.

> **Catalog V2 (X-016) — authoritative platform permission keys:** `view_catalog`, `edit_catalog`, `manage_catalog_media`. They replace the `manage_catalog_*` (Make/Trim) keys above, which are SUPERSEDED. Platform roles are persistent explicit assignments on the User (`super_admin`, `catalog_manager`), never derived from a user ID, an email or Workspace membership. Workspace Owner/Admin permissions never include catalog mutation. `edit_catalog` covers the ten technical catalog tables; `manage_catalog_media` covers the platform Series Media Library.

---

# 35. Form Editing

`edit_forms` controls:

- form field structure;
- labels;
- validation;
- consent text;
- Form configuration.

It should not automatically expose Integration credentials.

---

# 36. Form Routing

`edit_form_routes` controls:

- which destinations receive a Form;
- mapping;
- routing configuration.

If Site user lacks `manage_integrations`, they may use existing approved profiles but not view/replace credentials depending on product policy.

---

# 37. Integration Management

`manage_integrations` controls:

- create Integration Profile;
- replace credentials;
- edit auth settings;
- delete Integration Profile;
- Site overrides.

This is sensitive.

---

# 38. Integration Secret Visibility

Even users with `manage_integrations` should normally see masked secrets after save.

Permission to manage does not require re-displaying the original secret.

---

# 39. Submission Access

`view_submissions` controls access to customer lead data.

This includes:

- names;
- phones;
- emails;
- vehicle interest;
- Form payload.

This permission is separate because Submission data can contain personal information.

---

# 40. Submission Export

`export_submissions` should be separate from `view_submissions`.

A user may view leads but not bulk-export them.

---

# 41. Delivery Logs

`view_delivery_logs` may expose:

- delivery destination name;
- status;
- HTTP status;
- safe error message.

It must not expose secret tokens.

---

# 42. Retry Delivery

`retry_deliveries` controls manual retry of failed CRM/API/email delivery.

Retry should not edit the original Submission.

---

# 43. SEO Permissions

`edit_seo` controls:

- title;
- description;
- canonical;
- index/noindex;
- OG settings.

A future `edit_advanced_seo` may be introduced if needed.

---

# 44. Domain Management

`manage_domains` controls:

- add/remove custom domain;
- primary domain;
- redirects;
- verification;
- domain settings.

It also requires appropriate entitlement.

---

# 45. Publishing

`publish_site` is independent.

Required:

- Site access;
- publish permission;
- Site entitlement;
- publication validation success.

Editing Draft does not grant publishing.

---

# 46. Preview

`preview_site` may be more widely granted than publish.

Typical editors can Preview.

Preview must not modify production.

---

# 47. Version Restore

`restore_version` should be separate or bundled with publishing depending on final UX.

Restoring a version may create a new Draft rather than instantly changing production.

Recommended:

restore → Draft → review → Publish.

---

# 48. Workspace Assets

`manage_workspace_assets` controls adding/removing reusable Workspace files.

Site-only editors may still use permitted existing Workspace Assets.

---

# 49. Site Assets

Site-specific asset upload may be covered by:

- `edit_content`;
- `manage_assets`.

Exact split should remain simple for MVP.

---

# 50. Workspace Vehicle Library

`manage_workspace_vehicle_library` controls reusable customer vehicle data.

Site editor may import from library if `edit_vehicles` and product entitlement allow.

---

# 51. Cross-Site Vehicle Copy

Required permissions:

Source:

- view source Site;
- view source vehicles.

Destination:

- access destination Site;
- edit_vehicles.

If prices copied:

- edit_prices on destination;
- permission to read price source where restricted.

---

# 52. Integration Copy Across Sites

User needs:

- access source configuration;
- manage or use integrations at destination;
- both Sites in allowed Workspace context.

Shared credentials should remain references.

---

# 53. Subscription Permissions

`manage_billing` controls:

- plan changes;
- billing method;
- invoices;
- subscription cancellation.

Not all Admins need it.

---

# 54. Entitlement Administration

Customer roles cannot modify plan entitlements directly.

Entitlements come from plan/subscription/platform logic.

Super Admin may manage plan definitions.

---

# 55. Feature Flags

Users cannot bypass disabled feature flags through permissions.

If a feature is globally disabled:

permission alone does not enable it.

---

# 56. Platform Roles

Separate from Workspace roles.

Potential platform roles:

- Super Admin
- Catalog Manager
- Support
- Marketplace Moderator
- Finance Admin

These are Landflow internal roles.

---

# 57. Super Admin

Super Admin has broad platform access.

Must use explicit platform permission checks.

Never implement as:

- user ID check;
- hardcoded email;
- automatic membership in all Workspaces.

---

# 58. Catalog Manager

Typical platform permissions:

- view_catalog
- edit_catalog
- manage_catalog_media

Not automatically granted:

- billing;
- customer submissions;
- Workspace secrets.

---

# 59. Marketplace Moderator

Typical:

- review Marketplace submissions;
- approve/reject;
- suspend listing.

Not automatically granted Global Catalog write access.

---

# 60. Support Role

Potential support permissions:

- view Workspace metadata;
- inspect Site configuration;
- maybe impersonate with explicit audit.

Should not automatically reveal:

- secret tokens;
- sensitive Submission content.

---

# 61. Finance Role

Potential:

- view subscription;
- invoices;
- payment status.

No need for Designer or catalog access.

---

# 62. Platform Permission Isolation

Platform role permissions and Workspace role permissions are separate namespaces/concepts.

A customer Workspace Admin is not a Landflow Platform Admin.

---

# 63. Public Visitors

Public Site visitors have no authenticated Workspace permissions.

They may only access:

- published Site content;
- public vehicle data;
- public Forms;
- permitted public assets.

Public visitor permissions are controlled by public endpoint design, not Workspace roles.

---

# 64. Public Form Submission

No Workspace permission required.

But endpoint must validate:

- published/valid Site/Form;
- anti-spam;
- captcha;
- rate limit;
- payload validation.

Visitor cannot choose routing or integration.

---

# 65. Developer Platform Permissions

Potential permissions:

- access_developer_platform
- create_blocks
- create_templates
- submit_marketplace_item
- view_sales
- manage_developer_profile

Some may require developer account approval/entitlement.

---

# 66. Private Developer Assets

A private Block/Template may be Workspace-owned or Developer-owned.

Access must be explicit.

Public Marketplace approval does not automatically expose source-level secrets/private development data.

---

# 67. Marketplace Purchase Rights

Future purchase/licensing is an entitlement/license check.

Example:

User has permission to install templates.

But Workspace lacks license.

Result:

cannot install paid Template.

---

# 68. Permission Caching

Permission resolution may be cached.

Cache must be invalidated when:

- membership changes;
- role changes;
- Site access changes;
- subscription/entitlement changes.

Never let stale permission cache grant long-lived unauthorized access.

---

# 69. Permission Naming

Use stable semantic names.

Preferred:

`edit_prices`

Not:

`can_click_price_button`

Permission names describe business capability.

---

# 70. Permission Granularity Principle

Do not create a permission for every UI control.

Create permissions for meaningful business capabilities.

Bad:

- change_button_color
- edit_header_text

Good:

- edit_design
- edit_content
- edit_prices
- manage_domains

---

# 71. Authorization Placement

Authorization should exist at multiple levels:

- route/controller;
- Policy/Gate;
- service/action;
- domain invariants.

Do not rely only on one middleware for all complex rules.

---

# 72. Policies

Recommended Laravel Policies:

- WorkspacePolicy
- SitePolicy
- IntegrationProfilePolicy
- SubmissionPolicy
- WorkspaceVehiclePolicy
- DomainPolicy
- FormPolicy

Policies should remain readable and delegate complex logic when necessary.

---

# 73. Action-Level Authorization

Domain actions should validate permissions too.

Example:

`PublishSite`

should not assume caller was authorized only because controller called it correctly.

This improves safety for:

- CLI;
- jobs;
- future APIs;
- tests.

---

# 74. Queue Jobs

Background jobs do not have normal browser User context.

Jobs should act on authorized configuration created earlier.

Example:

Submission Delivery Job

does not re-check whether editor still has `manage_integrations`.

It executes the persisted routing authorized at configuration time.

But administrative jobs initiated by a User should preserve actor metadata for audit where relevant.

---

# 75. Audit Events

Sensitive authorization-related actions should be auditable.

Examples:

- member invited;
- role changed;
- member removed;
- price changed;
- integration credential replaced;
- domain changed;
- Site published;
- blacklist edited.

---

# 76. Permission Changes

Changing a role should take effect immediately for subsequent requests.

Existing sessions must not preserve old authorization indefinitely.

---

# 77. Membership Removal

When member is removed:

- Workspace access stops;
- Site access stops;
- private asset access stops;
- Submission access stops.

User's other Workspace memberships remain unchanged.

---

# 78. Suspended Membership

Suspended member should be denied access without deleting historical attribution.

---

# 79. Ownership Transfer

Only current Owner or authorized platform workflow may transfer Workspace ownership.

Transfer must:

- validate target membership;
- maintain at least one Owner;
- audit action.

---

# 80. Site-Specific Role Example

Example Workspace:

Daler — Owner  
Ivan — Designer

Ivan:

Site A:
Designer

Site B:
No access

Site C:
Content Editor

Architecture should support this future pattern.

---

# 81. Effective Permission Example

User:

Workspace role = Designer

Designer permissions:

- edit_design
- edit_content

Site A role = Pricing Manager

Depending on final model, Site role may:

- replace Site-relevant permissions;
or
- merge selected permissions.

Recommended future rule:

Site-specific role defines effective Site permissions for that Site, while Workspace-level administrative permissions remain separate.

This avoids confusing additive privilege escalation.

---

# 82. Site Access Model Recommendation

For Team implementation:

Separate permissions into:

## Workspace Permissions

Examples:

- manage_members
- manage_billing
- manage_integrations
- manage_workspace_vehicle_library

## Site Permissions

Examples:

- view_site
- edit_design
- edit_content
- edit_vehicles
- edit_prices
- view_submissions
- edit_seo
- manage_domains
- publish_site

A Workspace role may contain both.

A Site-specific role should control only Site permissions.

---

# 83. No Access Means No Discovery

If User lacks access to Site, normal customer UI should not reveal:

- Site name;
- domain;
- submissions;
- vehicle data;
- thumbnail.

Unless product explicitly allows Workspace-wide metadata visibility.

Default: no discovery.

---

# 84. Read vs Write

Where necessary distinguish:

- view_integrations vs manage_integrations
- view_submissions vs export_submissions
- view_vehicle_prices vs edit_prices

Do not introduce all possible read permissions prematurely.

Add them when real workflow demands them.

---

# 85. Pricing Visibility

It may be useful later to let some members view vehicles but not confidential pricing.

Architecture should be able to add:

- view_prices

without redesigning Site ownership.

Not required initially.

---

# 86. Secret Access

There should generally be no permission named `view_secret`.

Saved secrets should be masked even for authorized managers.

Managers can replace secrets without retrieving plaintext.

---

# 87. Consent and Legal Text

Editing Form consent/legal text may use `edit_forms` or future dedicated legal permission.

Do not overcomplicate initially.

---

# 88. Marketplace Permissions

Developer Marketplace submission requires:

- developer approval;
- create Template/Block permission;
- submit permission;
- platform feature enabled.

Public publication requires moderator approval.

Developer cannot self-approve.

---

# 89. Super Admin Overrides

Super Admin may perform customer actions for support/admin purposes.

Such actions should:

- be explicit;
- record actor;
- record target Workspace/Site;
- preserve audit trail.

---

# 90. Impersonation Permissions

If implemented:

`impersonate_user` or equivalent platform permission.

Only authorized platform roles.

Impersonation UI must clearly indicate active impersonation.

---

# 91. Permission Tests

Mandatory automated scenarios:

1. Content Editor cannot edit prices.
2. Designer cannot publish without publish permission.
3. Pricing Manager cannot edit design.
4. User without Site access cannot open Site.
5. User cannot access another Workspace's Integration Profile.
6. User with view_submissions but without export cannot bulk export.
7. User with custom_domain entitlement but without manage_domains cannot edit domains.
8. User with manage_domains but without custom_domain entitlement cannot attach paid custom domain.
9. Removed member immediately loses access.
10. Customer Admin cannot write Global Catalog.
11. Marketplace developer cannot self-approve listing.
12. Super Admin bypass works only through platform permission.

---

# 92. UI Behavior

UI should hide/disable unavailable actions for usability.

However backend remains authoritative.

Example:

No `edit_prices`:
- price fields read-only or hidden.

No `publish_site`:
- Publish button hidden/disabled.

No `manage_integrations`:
- credentials management hidden.

---

# 93. Permission Error UX

Prefer clear but safe messages.

Examples:

- «У вас нет прав на публикацию этого сайта.»
- «Ваш тариф не включает подключение собственных доменов.»

User-facing messages are Russian only (D-092).

Distinguish permission failure from entitlement failure where helpful.

---

# 94. API Authorization

Future API endpoints must use the same permission model.

Do not create separate weaker authorization rules for API.

Token scope + Workspace/Site permission must both be considered.

---

# 95. CLI / Automation

Internal automation may perform platform operations under system context.

System context must be explicit.

Do not impersonate arbitrary User silently.

---

# 96. Cursor Rules

Cursor agents must never:

- authorize by role name alone when permission is intended;
- merge entitlement and permission into one concept;
- grant Designer price access by default;
- grant Content Editor integration secret access;
- let edit permission imply publish;
- expose Submission data to all members;
- let customer roles modify Global Catalog;
- hardcode Super Admin by ID/email;
- bypass backend authorization because UI hides an action;
- invent complex per-button permissions without product need.

---

# 97. Implementation Order

Recommended authorization implementation sequence:

1. Workspace membership.
2. Basic Workspace system roles.
3. Site access.
4. Core Site permissions.
5. Publishing permission.
6. Automotive price permission.
7. Integration permission.
8. Submission access.
9. Team Site-specific roles.
10. Custom roles.
11. Platform admin roles.
12. Developer/Marketplace permissions.

Do not implement full enterprise ACL before core workflows exist.

---

# 98. Permission Catalog Governance

Maintain a centralized permission catalog.

Do not scatter magic strings across the codebase.

Potential implementation:

- enum/value object/constants;
- permission seeder/registry;
- role templates.

Exact technical approach belongs in implementation.

---

# 99. Source of Truth

This document defines authorization behavior.

If implementation conflicts:

- product decisions;
- `PRODUCT.md`;
- `TENANCY.md`;
- this `PERMISSIONS.md`;
- approved ADRs

take precedence over accidental existing code.

---

# 100. Final Authorization Model

Landflow authorization should be understood as:

User
→ active Workspace Membership
→ Workspace permissions
→ Site access
→ Site permissions
→ Entitlement
→ Feature availability
→ Domain invariant

Critical rules:

**Permission and entitlement are separate.  
Editing does not imply publishing.  
Design access does not imply price access.  
Form editing does not imply Integration secret access.  
Site access may be narrower than Workspace membership.  
Submission data requires explicit permission.  
Global Catalog writes are platform-only.  
Super Admin is a separate platform authorization domain.  
Backend is always authoritative.**
