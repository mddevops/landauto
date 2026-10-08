# Landflow — Tenancy and Ownership Architecture

**Document:** `docs/architecture/TENANCY.md`  
**Status:** Core tenancy source of truth  
**Purpose:** Define Workspace tenancy, ownership boundaries, access rules, query isolation, cross-Workspace restrictions, Site access behavior, and Super Admin exceptions.

---

# 1. Tenancy Model

Landflow uses a **Workspace-based tenancy model**.

The Workspace is the primary tenant boundary.

A User is not itself a tenant.

A Site is not itself a top-level tenant.

Conceptually:

User
↕
Workspace Membership
↓
Workspace
↓
Workspace-owned resources
↓
Sites and Site-owned resources

---

# 2. Why Workspace Is the Tenant

A Workspace represents the business/team context.

Examples:

- personal account;
- dealership;
- dealer group;
- agency;
- automotive marketing team.

A Workspace may contain:

- multiple Users;
- multiple Sites;
- reusable assets;
- integration profiles;
- vehicle library;
- permissions;
- subscription context.

This makes Workspace the correct isolation boundary.

---

# 3. User vs Workspace

User represents identity.

Workspace represents ownership.

Do not store business ownership directly on User.

Wrong:

User owns Site directly.

Preferred:

User
→ Workspace Membership
→ Workspace
→ Site

A User may belong to multiple Workspaces.

---

# 4. Membership

Workspace access is granted through membership.

Membership should conceptually include:

- User;
- Workspace;
- role/permissions;
- status;
- invitation state;
- join state.

A user can be:

- active;
- invited;
- suspended;
- removed.

Only active membership grants normal access.

---

# 5. Workspace Owner

Each Workspace has an Owner.

Owner has ultimate authority inside the Workspace subject to platform restrictions.

Owner can generally:

- manage Workspace;
- manage members;
- manage billing;
- manage Sites;
- manage integrations;
- assign permissions;
- publish where plan allows.

Ownership transfer may be added later.

---

# 6. Personal Workspace

Free users may have a personal Workspace.

It still uses the same Workspace model.

Do not create a separate architecture for "personal accounts."

This avoids migration complexity when a Free user upgrades to Team later.

---

# 7. Multiple Workspaces

A User may belong to multiple Workspaces.

Example:

Daler:
- Personal Workspace
- Dealer Group
- Agency

UI should provide an explicit Workspace switcher.

The currently active Workspace affects:

- Dashboard;
- Site list;
- assets;
- integrations;
- vehicle library;
- members;
- billing context.

---

# 8. Active Workspace

Active Workspace is a UI/application context, not the sole authorization mechanism.

Never trust only:

- session active_workspace_id;
- frontend Workspace selection.

Every protected backend action must still verify membership/permission.

---

# 9. Workspace-Owned Resources

Examples of Workspace-owned resources:

- Workspace members;
- Workspace roles;
- Workspace assets;
- Workspace Integration Profiles;
- Workspace Vehicle Library;
- Site folders;
- Sites;
- purchases/licenses;
- subscription context.

Every Workspace-owned entity must have a clear workspace relationship.

---

# 10. Site-Owned Resources

A Site belongs to exactly one Workspace.

Site-owned resources include:

- Pages;
- Page Blocks;
- Site Vehicles;
- Site Offers;
- Popups;
- Forms;
- Site integration bindings;
- domains;
- SEO;
- analytics;
- security settings;
- Draft/Published versions.

A Site-owned resource indirectly belongs to the Workspace through Site.

---

# 11. Global Resources

Some resources are global and are not owned by a customer Workspace.

Examples:

- Global Automotive Catalog;
- official Templates;
- official Block Definitions;
- Marketplace public listings;
- platform plans;
- platform feature definitions.

Global resources may be readable by many Workspaces but modified only by authorized platform roles.

---

# 12. Ownership Resolution

For every entity, backend must be able to resolve:

- global;
- Workspace;
- Site.

Example:

Site Offer
→ Site
→ Workspace

Integration Profile
→ Workspace

Automotive Equipment (Catalog V2; formerly "Trim")
→ Global

This ownership chain is required for authorization.

---

# 13. Backend Authorization Is Mandatory

Frontend visibility is not security.

Even if UI hides a button, backend must still reject unauthorized operations.

Every write endpoint/action must validate:

- authenticated User;
- Workspace membership;
- relevant permission;
- resource ownership;
- Site access where applicable.

---

# 14. Never Trust Route IDs Alone

Example request:

`/sites/123/edit`

Backend must not assume User owns Site 123.

It must resolve:

Site 123
→ Workspace
→ User membership
→ Site permission

If membership/access fails:

deny.

---

# 15. Query Scoping

Workspace-scoped queries should use explicit scoping patterns.

Preferred:

- dedicated query scopes;
- repositories/services with Workspace context;
- policy checks;
- parent-child resource resolution.

Avoid raw unscoped queries in product code where tenant ownership matters.

---

# 16. Avoid Hidden Global Scopes as the Only Protection

A global Eloquent scope may help but must not be the only tenancy defense.

Reasons:

- admin tooling may need cross-Workspace access;
- queue jobs may run without normal request context;
- background tasks may use explicit IDs;
- accidental scope removal can become dangerous.

Use defense in depth:

- ownership relationships;
- policies;
- service-level checks;
- explicit scope conditions;
- tests.

---

# 17. Route Model Binding

When using route model binding for tenant resources, binding should ideally verify the correct parent scope.

Example:

Workspace:
`/workspaces/{workspace}/sites/{site}`

Site must belong to that Workspace.

Do not allow:

Workspace A URL
with
Site from Workspace B.

---

# 18. Site Access

Being a Workspace member does not necessarily mean access to every Site forever.

Initial MVP may allow all Workspace members to access all Sites according to role.

Architecture must support future Site-specific access.

Example:

Designer:
- Site A ✓
- Site B ✓
- Site C ✕

---

# 19. Site-Specific Permissions

Future Site access may include:

- can_view;
- can_edit_design;
- can_edit_content;
- can_edit_vehicles;
- can_edit_prices;
- can_edit_forms;
- can_edit_integrations;
- can_manage_domains;
- can_publish.

Site-level permissions override general Workspace defaults where explicitly configured.

---

# 20. Permission Resolution Order

Conceptually:

Platform restriction
→ Workspace membership
→ Workspace role
→ Site access
→ Site-specific override
→ subscription entitlement
→ feature flag

All relevant checks must pass.

Important:

Entitlement is not the same as permission.

Example:

Team plan may include custom domains.

But a Content Editor still may not have permission to manage domains.

---

# 21. Entitlement vs Permission

Entitlement answers:

> Is this feature available for this Workspace/Site plan?

Permission answers:

> Is this User allowed to perform this action?

Both are required.

Example:

custom_domain entitlement = true

User permission manage_domains = false

Result:

User cannot manage domain.

---

# 22. Global Catalog Read Access

Customers may read allowed Global Automotive Catalog data.

They must never directly mutate it.

Customer workflow:

Global Catalog
→ select
→ import/copy
→ customer-owned record

All customer changes happen outside Global Catalog.

---

# 23. Global Catalog Write Access

Only authorized platform roles may:

- create make;
- edit model;
- edit generation;
- edit modification;
- edit trim;
- manage characteristics;
- manage options;
- upload master images;
- manage colors.

This is a Super Admin/catalog manager capability.

> **Catalog V2 (X-016):** the Global Catalog is a separate physical database (connection `catalog`); "edit trim" and "manage colors" above are superseded by Equipment (under Modification) and by the platform Series Media Library (media sets/images per catalog Series, stored in the main database). Customer Site rows (SiteVehicle → Series, SiteOffer → Equipment) reference catalog rows by immutable `public_id`; there are no cross-database foreign keys, so the application validates every catalog reference and the tenant boundary stays on the main-database Site/Workspace chain.

---

# 24. Customer Vehicle Ownership

A customer's imported vehicle belongs to:

Workspace
or
Site

depending on context.

Customer editing:

- price;
- photo;
- description;
- active state;
- benefits;

must never update source Global Catalog records.

---

# 25. Cross-Workspace Vehicle Access

Workspace A must never access private Workspace Vehicle data of Workspace B.

This includes:

- photos;
- descriptions;
- custom content;
- custom pricing defaults;
- private media.

Global Catalog remains separately shared.

---

# 26. Workspace Vehicle Sharing

Workspace Vehicle Library may be reused by Sites inside the same authorized Workspace.

Default rule:

Workspace Vehicle
→ reusable within same Workspace

It is not automatically visible to other Workspaces.

---

# 27. Cross-Site Copy

Copy between Sites is allowed only when User has permission in both source and destination contexts.

Example requirements:

- access source Site;
- read vehicles/configuration;
- access destination Site;
- edit destination vehicles/configuration.

Do not allow copying merely because both Site IDs are known.

---

# 28. Site Copy Semantics

Copy means new destination-owned data.

Example:

Site A price: 4,000,000

Copy to Site B.

Site B gets its own price record.

Later modifying Site A does not change Site B.

---

# 29. Shared Reference Semantics

Not everything should be copied.

Example:

Workspace Integration Profile.

Site A:
→ references Workspace CRM Profile

Site B:
→ references same Workspace CRM Profile

Credentials remain shared.

Site-specific parameters remain local overrides.

---

# 30. Integration Profile Ownership

Integration Profile belongs to one Workspace.

It contains sensitive configuration such as:

- API token;
- secret;
- base URL;
- auth details.

Only authorized members of that Workspace may manage it.

---

# 31. Site Integration Binding

A Site may reference only Integration Profiles available to its own Workspace.

Forbidden:

Site in Workspace A
→ Integration Profile in Workspace B.

Backend must enforce this relationship.

---

# 32. Form Submission Ownership

Submission belongs to Site.

Through Site it belongs to Workspace.

Only authorized Workspace/Site users may view Submission data.

Future privacy rules may further restrict which roles may see personal customer data.

---

# 33. Submission Delivery

Delivery jobs must resolve integration configuration server-side.

Never trust:

- integration_profile_id from public browser;
- destination token from client;
- arbitrary webhook URL from form submission payload.

Authoritative routing comes from Site/Form configuration.

---

# 34. Public Site Access

Published Sites are public.

Public visitor does not become a Workspace member.

Public runtime must expose only intentionally public data.

Examples:

Public:
- published content;
- vehicle offers;
- public images;
- Form endpoints.

Private:
- Workspace members;
- integration secrets;
- unpublished Draft;
- internal submission logs;
- billing.

---

# 35. Draft Access

Draft is private by default.

Access requires authenticated authorization unless explicit secure preview links are introduced.

Do not expose Draft merely because Site has a public domain.

---

# 36. Preview Access

Preview may be:

- authenticated-only;
- secure token link later.

If token-based preview is added:

- token must be unguessable;
- token may expire;
- token must not grant Designer access;
- token should expose only Preview.

---

# 37. Published Snapshot Access

Published version is the public source.

Public runtime must not accidentally read in-progress Draft values.

This isolation is required for trustworthy publishing.

---

# 38. Workspace Deletion

Deleting a Workspace is a highly destructive action.

Requirements should eventually include:

- Owner authorization;
- confirmation;
- handling active subscriptions;
- handling domains;
- handling Sites;
- handling integrations;
- delayed hard deletion/recovery window where appropriate.

Do not use a simple unguarded cascade from UI.

---

# 39. Site Deletion

Deleting Site should initially prefer soft deletion/archive behavior.

Reason:

- accidental deletion;
- domains;
- submissions;
- published history;
- integrations.

Hard deletion may occur later through retention workflow.

---

# 40. User Removal from Workspace

Removing a User from Workspace should:

- revoke access immediately;
- preserve content they created;
- preserve audit attribution;
- not delete Sites/Blocks/vehicles created by them.

Business data belongs to Workspace, not creator User.

---

# 41. User Account Deletion

User account deletion must not automatically destroy Workspace-owned business data.

If User is sole Workspace Owner, ownership transfer/Workspace handling is required before destructive deletion.

Implemented account-deletion rule:

- an empty Workspace where the deleting User is the only member is treated as that account's personal Workspace and deleted;
- membership in a Workspace is removed when another active Owner remains;
- deletion is blocked when the User is the sole active Owner of a Workspace with other members, until ownership is transferred or those members are removed;
- the operation and session cleanup are transactional at the application layer.

---

# 42. Workspace Transfer

Future functionality may allow:

- transfer Workspace ownership;
- transfer Site between Workspaces.

These operations are complex and must be explicit.

Do not assume changing `workspace_id` is sufficient.

Potential dependencies:

- assets;
- vehicles;
- integration profiles;
- licenses;
- billing;
- domains;
- member access.

---

# 43. Site Transfer Between Workspaces

Future Site transfer requires a dedicated workflow.

Potential actions:

- copy/move Site;
- resolve Workspace Vehicle references;
- resolve Integration Profiles;
- copy assets;
- validate licenses;
- reset Site-specific permissions.

Do not implement casually.

---

# 44. Workspace Duplication

Future agency workflows may duplicate Workspace/Site structures.

This must distinguish:

- shared global resources;
- copied Workspace resources;
- secrets;
- integrations;
- subscriptions.

Not MVP.

---

# 45. Super Admin

Super Admin is outside normal customer tenancy restrictions but must use controlled platform authorization.

Super Admin may need to:

- inspect Workspace;
- inspect Site;
- manage Global Catalog;
- moderate Marketplace;
- manage plans;
- diagnose issues.

Super Admin access must not be implemented by pretending the admin belongs to every Workspace.

---

# 46. Super Admin Impersonation

If impersonation is introduced later:

- it must be explicit;
- clearly visible in UI;
- auditable;
- reversible;
- restricted to authorized admins;
- sensitive actions may require stronger controls.

Do not silently impersonate customers.

---

# 47. Super Admin Data Access

Even Super Admin access should follow least-privilege principles.

Not every admin role requires:

- CRM secrets;
- submission personal data;
- billing data.

Future platform admin roles may separate:

- catalog manager;
- support;
- finance;
- marketplace moderator;
- super admin.

---

# 48. Support Access

Support agents may need temporary access to diagnose Sites.

Future support access should be:

- explicit;
- permissioned;
- auditable;
- optionally time-limited.

Do not solve support by removing tenancy checks globally.

---

# 49. Background Jobs

Queue jobs run outside normal browser session.

Every job touching tenant data must include explicit identifiers.

Example:

DeliverSubmissionJob:

- submission_id

Job resolves:

Submission
→ Site
→ Workspace
→ configured routes

Never rely on "current active Workspace" inside queue jobs.

---

# 50. Scheduled Tasks

Scheduled commands must explicitly scope tenant operations.

Example:

verify custom domains

Query:
eligible Site domains

Do not assume request tenant context exists.

---

# 51. Notifications

Notifications concerning Workspace data must verify intended recipient relationship.

Example:

"Site published"

Recipients should be authorized Workspace members, not arbitrary User IDs.

---

# 52. File Storage Isolation

File paths should be organized to reduce cross-tenant mistakes.

Potential pattern:

workspace/{workspace_id}/...
site/{site_id}/...

Global Catalog media:

global/catalog/...

Storage path alone is not authorization.

Backend must still enforce access rules.

---

# 53. Signed URLs

Private assets may use signed/temporary URLs depending on storage provider.

Public published assets may be served publicly/CDN.

Do not expose private Workspace assets simply because a physical file URL exists.

---

# 54. Domain Ownership

Custom Domain belongs to Site.

A hostname must not be assigned to two active Sites simultaneously.

Adding a Domain requires permission for:

- Site;
- Workspace;
- entitlement.

Domain verification proves control of hostname, not Workspace membership.

Both are necessary.

---

# 55. Subscription Ownership

Subscription belongs to Workspace unless later commercial rules introduce Site-level billing.

Billing provider identifiers must not define tenancy.

Workspace remains the product ownership boundary.

---

# 56. Plan Limits

Plan limit checks must occur against the correct Workspace.

Example:

max_sites = 5

Count Sites belonging to that Workspace according to approved active/deleted rules.

Do not count User's Sites across unrelated Workspaces unless product explicitly says so.

---

# 57. Free Plan

Conceptual Free rule:

- one personal Workspace;
- limited Sites;
- no Team members beyond product-defined limit.

Architecture remains identical to paid Workspace.

---

# 58. Team Plan

Team adds capabilities, not a new tenancy model.

Same Workspace entity.

Additional entitlements may enable:

- members;
- custom roles;
- Site-level access;
- more Sites;
- shared vehicle library;
- advanced versions.

---

# 59. Developer Platform Tenancy

Developer profile is not automatically a customer Workspace.

A Developer may:

- create public Block;
- create public Template;
- maintain Marketplace products.

Private Workspace Blocks may still belong to Workspace.

Public Developer assets belong to Developer/Marketplace domain.

Do not confuse customer tenancy with creator ownership.

Approved (D-093, implemented in P9-001): a Developer Profile is a User-owned creator identity (one per User), not a Workspace. It grants no Workspace membership, Site access, Workspace permission, entitlement or platform permission, and the Developer Platform (`/developer`) never uses Workspace context. Access is controlled by a Super Admin for now (no self-service registration). Developer creator permissions (D-118, P9-002) are explicit per-profile grants, separate from Workspace and platform permissions; they never grant Workspace membership or Site access.

---

# 60. Private Blocks

Workspace-private Block definitions may be accessible only to that Workspace.

Another Workspace must not discover/use them unless explicitly shared/published.

---

# 61. Marketplace Blocks

Approved public Marketplace Blocks may be visible globally.

Usage rights may depend on:

- license;
- purchase;
- entitlement.

Visibility is global.

Customer instance data remains Site-owned.

---

# 62. Template License Access

Future paid Template use requires license validation.

Workspace A purchasing a Template does not automatically grant Workspace B access even if same User belongs to both, unless licensing terms explicitly say so.

Licensing scope must be explicit.

---

# 63. API Keys

If Workspace API keys are introduced later:

- key belongs to Workspace;
- key permissions/scopes explicit;
- key must not automatically access all platform data;
- Site-specific keys may be possible later.

Never treat API key as Super Admin.

---

# 64. Public API

Future public/developer APIs must enforce tenant ownership using token scopes and resource checks.

Example:

Workspace API token
→ may list that Workspace's Sites

Not:
→ arbitrary Site by ID.

---

# 65. Cross-Tenant IDs

Knowing an internal ID must never grant access.

All operations must verify ownership.

This applies to:

- Site IDs;
- Form IDs;
- Submission IDs;
- Asset IDs;
- Vehicle IDs;
- Integration IDs;
- Domain IDs.

---

# 66. Mass Assignment

Models containing `workspace_id`, `site_id`, or owner foreign keys must not accept ownership reassignment casually from client payload.

Ownership should normally be assigned server-side from authorized context.

---

# 67. Import Payloads

Import/copy workflows must validate every source entity.

Do not accept browser payload:

source_site_id = arbitrary
vehicle_ids = arbitrary

without verifying User access to the source.

Destination ownership must also be validated.

---

# 68. Global Resource References

When Site references global resources:

Example:

Block Definition
Global Automotive Trim
Template

Backend must validate:

- resource exists;
- resource is active/usable;
- license/access requirements satisfied where relevant.

Global does not mean unrestricted modification.

---

# 69. Workspace Resource References

When Site references Workspace resource:

Example:

Workspace Asset
Workspace Vehicle
Integration Profile

Backend must ensure:

resource.workspace_id == site.workspace_id

This rule should have automated tests.

---

# 70. Site Resource References

When one Site-owned entity references another Site-owned entity, ensure same Site unless explicitly cross-Site.

Example:

Popup references Form.

Preferred default:

popup.site_id == form.site_id

Do not accidentally let Site A's Popup submit Site B's Form.

---

# 71. Cross-Site Shared Forms

If Workspace-wide reusable Forms are desired later, create explicit Workspace Form concept.

Do not achieve sharing by allowing arbitrary cross-Site Form references.

---

# 72. Cross-Site Shared Popups

Same principle.

Popup is currently Site-owned.

If reusable Workspace Popups are introduced later, model them explicitly.

---

# 73. Audit Scope

Audit events should include enough context to identify:

- actor User;
- Workspace;
- Site if relevant;
- action;
- resource.

This is important for Team environments.

---

# 74. Tenant-Aware Logging

Operational logs may include safe identifiers:

- workspace_id;
- site_id;
- submission_id.

Never log:

- full API tokens;
- secret credentials;
- sensitive personal payload unnecessarily.

---

# 75. Error Messages

Authorization errors must not leak whether a resource from another Workspace exists.

Prefer:

404 or generic authorization denial

rather than:

"Site 123 belongs to Workspace X."

---

# 76. Enumeration Protection

Public endpoints should avoid making it easy to enumerate:

- private Sites;
- Forms;
- Submissions;
- Integration Profiles.

Use appropriate public identifiers/tokens and authorization.

---

# 77. Public Site Identifier

Published runtime may use:

- domain;
- subdomain;
- public Site key.

It should not rely solely on predictable internal primary key in URLs.

---

# 78. Public Form Identifier

Public Form submission endpoint should use a safe public identifier.

Server resolves:

public form key
→ Form
→ Site
→ Security Policy

Client must not choose arbitrary Site ownership.

---

# 79. Tenant Context Service

Application may use an explicit Tenant/Workspace Context service for request-level convenience.

Responsibilities:

- active Workspace;
- membership;
- permission context.

But it is helper infrastructure, not sole security.

---

# 80. Policy Layer

Laravel Policies/Gates are recommended for entity-level authorization.

Examples:

- SitePolicy;
- WorkspacePolicy;
- IntegrationProfilePolicy;
- SubmissionPolicy.

Policies should delegate complex business logic to domain services where needed.

---

# 81. Service Layer

Complex tenant operations should use services/actions.

Examples:

- CreateSite;
- ImportVehicleToWorkspace;
- CopySiteVehicles;
- BindIntegrationToSite;
- PublishSite.

Service should receive explicit actor/context and verify invariants.

---

# 82. Controller Principle

Controllers should not contain large tenant logic.

Controller:

- authorize;
- validate request;
- call domain action/service;
- return response.

This makes autonomous testing and maintenance safer.

---

# 83. Inertia Shared Props

Do not share full User/Workspace models globally to every Inertia page.

Shared props should be minimal.

Potential shared context:

- authenticated User safe fields;
- current Workspace summary;
- membership capabilities;
- flash.

Never include secrets.

---

# 84. Workspace Switching Security

When User switches Workspace:

Backend verifies membership.

Then active Workspace context may be updated.

Never allow arbitrary `workspace_id` to be stored in session without membership validation.

---

# 85. Last Workspace

Application may remember last active Workspace.

If membership later removed:

next request must detect invalid membership and fall back to an allowed Workspace or onboarding.

---

# 86. Invite Flow

Future invite:

Workspace admin
→ invite email
→ pending membership
→ User accepts
→ active membership

Invitation token must be scoped to:

- Workspace;
- email or intended identity;
- expiry.

Accepting one invite must not grant access elsewhere.

---

# 87. Pending Users

Invited email may not yet have Landflow account.

System may store invitation separately from active membership.

Do not fake active User rows solely for invitations unless auth design intentionally supports it.

---

# 88. Member Suspension

Workspace may suspend a member without deleting User account.

Suspension removes access to that Workspace only.

Other Workspace memberships remain unaffected.

---

# 89. Workspace-Level Blacklist

Workspace anti-spam blacklist applies only to Sites inside that Workspace.

It must not affect other customers.

Global blacklist is separate and platform-admin controlled.

---

# 90. Site-Level Blacklist

Site blacklist affects only that Site.

Resolution order may be:

Global block
→ Workspace block
→ Site block

Each scope remains explicit.

---

# 91. Analytics Isolation

Yandex Metrica configuration belongs to Site.

Site A Counter ID must not accidentally render on Site B.

Shared Workspace defaults may be introduced later only through explicit inheritance.

---

# 92. Submission Privacy

Submission contains customer personal data.

Access should be more restrictive than ordinary Site visual content.

Future roles may include:

- can_view_submissions;
- can_export_submissions.

Do not assume every Designer needs lead access.

---

# 93. Integration Secret Privacy

Integration secrets should be more restrictive than normal Site editing.

Potential separate permission:

- manage_integrations.

A Content Editor may configure Form text but should not necessarily view/replace CRM token.

---

# 94. Price Editing Permission

Automotive price is sensitive commercial data.

Use distinct permission:

- edit_prices.

Designer role should not automatically imply price editing.

---

# 95. Publishing Permission

Use distinct permission:

- publish_site.

Editing Draft must not automatically allow production publication.

---

# 96. Domain Permission

Use distinct permission:

- manage_domains.

Changing DNS/domain configuration is operationally sensitive.

---

# 97. Super Admin Bypass Rule

Super Admin may bypass normal Workspace membership only through explicit platform authorization logic.

Do not sprinkle:

`if user.id == 1`

or

`if email == ...`

through code.

Use dedicated platform roles/permissions.

---

# 98. Tenant Tests

Mandatory tenancy test categories:

1. User cannot view Site from another Workspace.
2. User cannot update Site from another Workspace.
3. User cannot use another Workspace's Integration Profile.
4. User cannot access another Workspace's Submission.
5. User cannot attach another Workspace's Asset.
6. User cannot import private Workspace Vehicle from another Workspace.
7. Site A cannot bind Site B's Form.
8. Workspace switch rejects non-member Workspace.
9. Removed member immediately loses access.
10. Super Admin access works only with explicit platform authorization.

---

# 99. Cursor Tenancy Rules

Cursor agents must never:

- create direct User → Site ownership as primary model;
- skip Workspace ownership checks;
- trust frontend tenant IDs;
- assume active session Workspace is sufficient authorization;
- expose Integration secrets across Workspace boundaries;
- permit arbitrary cross-Site references;
- bypass policies for convenience;
- solve Super Admin by disabling tenancy globally;
- turn Team plan into a separate tenant architecture;
- make shared/copy semantics ambiguous.

If ownership is unclear, stop and document the ambiguity before implementation.

---

# 100. Final Tenancy Model

Landflow tenancy should always be understood as:

User
   ↕ membership
Workspace
   ├── members
   ├── assets
   ├── integration profiles
   ├── vehicle library
   └── Sites
        ├── pages
        ├── blocks
        ├── vehicles
        ├── prices
        ├── forms
        ├── submissions
        ├── integrations
        ├── domains
        └── publishing

Separate global platform data:

- Global Automotive Catalog;
- official Templates;
- official Blocks;
- Marketplace;
- platform Plans.

Core rules:

**Workspace is the tenant.  
User is identity.  
Site belongs to Workspace.  
Backend always verifies ownership.  
Global Catalog is shared read-only for customers.  
Workspace-private data never crosses tenant boundaries implicitly.  
Cross-Site copy requires access to both sides.  
Shared credentials stay Workspace-scoped.  
Super Admin uses explicit platform authorization, not disabled tenancy.**
