# Landflow — Publishing Architecture

**Document:** `docs/architecture/PUBLISHING.md`  
**Status:** Core publishing source of truth  
**Purpose:** Define Draft, Preview, Published snapshots, publication workflow, Landflow subdomains, custom domains, versioning, rollback, publication validation, caching, public runtime stability, and publishing permissions.

---

# 1. Core Publishing Principle

Landflow must strictly separate:

- Draft
- Preview
- Published Production

The most important rule is:

> **Autosave changes Draft only. Autosave must never directly modify the currently Published Site.**

The public Site changes only after an explicit Publish action.

---

# 2. Publishing State Model

Conceptually:

Draft
→ Preview
→ Validate
→ Publish
→ Published Version

Published Version remains stable until another Publish succeeds.

Editing Draft after publication must not affect production.

---

# 3. Draft

Draft is the current editable Site state.

Draft includes the editable configuration for:

- Pages;
- Block Instances;
- content;
- Site Vehicles;
- Site Offers;
- Popups;
- Forms;
- SEO;
- design tokens;
- Site settings relevant to rendering.

Draft is where Designer autosave writes.

---

# 4. Autosave

Designer should autosave changes to Draft.

Autosave goals:

- prevent user work loss;
- support frequent editing;
- avoid explicit Save button for every field.

Autosave does not:

- publish;
- update production;
- invalidate existing Published Version.

---

# 5. Draft Consistency

Draft should remain internally valid enough to reopen in Designer.

However a partially configured Draft may still fail Publish validation.

Example:

Draft may contain:

- incomplete Form route;
- missing required domain setting;
- invalid Block configuration.

The user can continue editing Draft.

Publish requires stricter validation.

---

# 6. Preview

Preview renders the current Draft.

Preview should match production rendering as closely as possible.

Preview uses:

- current Draft content;
- current Draft Block state;
- Draft Site Vehicles/Offers;
- Draft design tokens;
- same rendering engine as Published Site where practical.

Avoid building a separate Preview renderer that behaves differently from production.

---

# 7. Preview Access

Initial Preview may require authenticated access.

Future secure Preview links may be supported.

A secure Preview link should:

- use an unguessable token;
- be revocable;
- optionally expire;
- grant Preview only;
- never grant Designer/admin access.

---

# 8. Preview vs Staging

Conceptually distinguish:

## Preview

Current editable Draft rendering.

## Staging

A stable Landflow-hosted Site URL suitable for testing/approval.

Depending on implementation, the Landflow subdomain may act as:

- Free public Site;
- staging environment;
- published test environment.

The exact deployment model can evolve, but Draft and Published must remain separate.

---

# 9. Published Version

Published Version is an immutable or logically stable snapshot of the Site state at publication time.

It should contain enough information to reproduce the public Site consistently.

A Published Version must not read arbitrary in-progress Draft state.

---

# 10. Publication

A Publication is the operation that promotes a validated Site state to production.

Conceptual workflow:

User clicks Publish
→ authorize
→ validate
→ build/prepare public version
→ persist publication metadata
→ activate new Published Version
→ invalidate relevant caches
→ mark publication successful

If publication fails:

- old Published Version remains active;
- Draft remains intact;
- user sees a safe error.

---

# 11. Atomic Publication

Publication should behave atomically from visitor perspective.

Visitors should not see:

- half-old / half-new Pages;
- some updated Blocks and some failed Blocks;
- missing assets during activation.

Prepare the new version first, then switch the active Published Version.

---

# 12. Publish Permission

Publishing requires explicit permission:

`publish_site`

Editing permission does not imply Publish.

Example:

Designer:
- may edit Draft;
- may Preview;
- may not Publish.

Publisher/Admin:
- may publish.

---

# 13. Publication Validation

Before Publish, validate at least applicable items such as:

- Site has required Pages;
- home Page exists;
- Block states validate against Block Schema;
- referenced Block Versions are available;
- required assets exist;
- referenced Forms exist;
- referenced Popups belong to same Site;
- Site Vehicle bindings are valid;
- public slugs do not conflict;
- SEO configuration is valid;
- required plan entitlements exist;
- domain/publication configuration is valid.

---

# 14. Validation Severity

Validation results may be:

## Error

Blocks Publish.

Examples:

- missing Home Page;
- invalid Block Schema state;
- broken Form reference.

## Warning

Publish may continue.

Examples:

- missing meta description;
- no Open Graph image;
- no analytics configured.

The exact rules may evolve.

---

# 15. Publish Scope

Future Landflow may support:

- Publish entire Site;
- Publish selected Page.

Initial implementation should prefer **whole-Site Publish** for consistency unless a clear need exists for partial publishing.

Do not overcomplicate MVP with granular publishing.

---

# 16. Publication Record

Every successful or failed Publish attempt should have a Publication record.

Potential metadata:

- Site;
- Site Version;
- actor User;
- status;
- started_at;
- completed_at;
- error summary;
- metadata.

This supports audit and diagnostics.

---

# 17. Site Version

A Site Version represents a restorable Site state or publication snapshot.

Potential fields:

- Site ID;
- version number;
- type;
- snapshot/reference;
- created by;
- created at.

Types may include:

- published;
- restore point;
- manual snapshot later.

Exact storage strategy can evolve.

---

# 18. Version Numbering

Each Site may use monotonically increasing version numbers.

Example:

- v1
- v2
- v3

Human UI may additionally show:

- publication date;
- publisher;
- note;
- status.

---

# 19. Version History

Paid/Team plans may expose richer version history.

Potential capabilities:

- list versions;
- compare metadata;
- restore;
- identify current production;
- identify who published.

Architecture should support version history even if Free plan exposes only minimal history.

---

# 20. Restore

Recommended Restore behavior:

Version 12
→ Restore to Draft
→ user reviews
→ Publish

Do not immediately switch production simply because a historical version was selected.

This preserves explicit Publish control.

---

# 21. Rollback

Emergency rollback may later support:

Current Published v15
→ rollback to v14

If implemented, rollback should still create a Publication event and audit entry.

Do not silently mutate version pointers without history.

---

# 22. Draft After Publish

After successful Publish:

Draft may remain editable.

It may initially match Published Version, then diverge as user continues editing.

The UI should eventually show:

- Published;
- Unpublished Changes.

---

# 23. Unpublished Changes Indicator

Site Dashboard/Designer should be able to tell when Draft differs from Published Version.

Possible state:

`Published • Unpublished changes`

Exact diff implementation may be simple initially.

---

# 24. Landflow Subdomain

Every eligible Site may receive a Landflow hostname.

Example:

`dealer-name.landflow.me`

Uses:

- Free public hosting;
- staging;
- preview/testing depending on plan/workflow.

Hostname must be unique.

---

# 25. Subdomain Generation

Site creation may suggest a subdomain from Site name.

Example:

`Changan Moscow`
→ `changan-moscow.landflow.me`

User may edit if available.

Reserved names must be blocked.

Examples:

- www
- admin
- api
- app
- support
- static

Final reserved list belongs in implementation.

---

# 26. Subdomain Stability

Changing Site display name must not automatically change existing subdomain.

Subdomain change should be explicit because it affects URLs and SEO.

---

# 27. Free Site Publishing

Conceptual Free plan:

- publish to `*.landflow.me`;
- Landflow branding required;
- no custom domain unless entitlement says otherwise.

The exact Free Site limit comes from entitlements.

---

# 28. Landflow Branding

Free Sites may display mandatory Landflow branding.

Branding visibility should be controlled through entitlement:

`remove_branding`

Do not hardcode branding logic into each Template.

Public runtime should inject/render platform branding consistently where required.

---

# 29. Custom Domains

Paid capability may allow a Site to use custom domains.

Examples:

- dealer.ru
- www.dealer.ru

A Site may have:

- Landflow subdomain;
- one or more custom domains;
- one primary public domain.

---

# 30. Domain Ownership

Custom Domain belongs to Site.

The hostname must be globally unique among active Landflow Site domains.

A Site may only add a custom domain when:

- User has `manage_domains`;
- Workspace/Site entitlement allows custom domains.

---

# 31. Domain Verification

Before activating custom domain, Landflow should verify control via DNS.

Potential flow:

Add domain
→ show DNS instructions
→ periodic/manual verification
→ verified
→ SSL provisioning
→ ready
→ publish/activate

Exact DNS records depend on deployment architecture.

---

# 32. DNS Status

Potential statuses:

- pending
- detected
- misconfigured
- verified

UI should show actionable setup instructions.

---

# 33. SSL

Custom domains require HTTPS.

Potential SSL statuses:

- pending
- provisioning
- active
- failed

SSL should be automated where infrastructure allows.

Do not let Sites remain production-ready over insecure HTTP by default.

---

# 34. Primary Domain

A Site may have several hostnames.

One should be primary.

Example:

- `dealer.ru` → primary
- `www.dealer.ru` → redirects to primary
- `dealer.landflow.me` → staging or redirect depending settings

Canonical URLs should use primary public domain.

---

# 35. Domain Redirects

Potential behavior:

alternate domains
→ 301 redirect
→ primary domain

Redirect logic belongs to publishing/domain runtime, not individual Pages.

---

# 36. Domain Changes

Changing primary domain is a sensitive operation.

Requires:

- `manage_domains`;
- valid entitlement;
- verified domain.

Should be auditable.

---

# 37. Domain Removal

Removing a custom domain must not delete the Site.

Site remains accessible through allowed Landflow subdomain according to product rules.

---

# 38. Staging Indexing

Landflow-hosted staging environments should support:

- `noindex`;
- potentially authentication/private access.

Avoid accidental indexing of test/staging versions.

---

# 39. Production SEO

Published Site should support:

- Page title;
- meta description;
- canonical;
- robots directives;
- Open Graph;
- sitemap;
- structured URLs.

SEO rendering must use Published Version data.

---

# 40. Sitemap

Published Site should generate/update sitemap based on public Pages and dynamic automotive Pages when applicable.

Only indexable public URLs belong in sitemap.

---

# 41. Robots

Site settings may control indexing.

Examples:

- production index enabled;
- staging noindex;
- selected Page noindex.

Robots behavior must not expose Draft URLs.

---

# 42. Canonical URL

Canonical should resolve against primary production domain where appropriate.

Avoid staging subdomain becoming canonical for a paid custom-domain Site.

---

# 43. Dynamic Automotive Pages

Future published Sites may support:

`/cars/{vehicle-slug}`

These URLs should resolve from Published automotive Site state.

Changing Global Catalog source slug must not silently break a customer's published URL.

---

# 44. Published Automotive Data

Public Site should render Site-owned automotive data.

Examples:

- Site Vehicle;
- Site Offer;
- selected images/colors;
- benefits.

It must not accidentally switch to updated Draft price before Publish.

---

# 45. Price Publication

If a user changes Site Offer price in Draft:

Production retains old Published price until Publish.

This rule is important for commercial control.

No "live DB read from Draft" should bypass publishing semantics.

---

# 46. Forms on Published Site

Form definition/configuration exposed publicly must come from Published Site state or a clearly defined operational Form configuration.

Important nuance:

Submission persistence and Integration delivery are live operational systems.

Published rendering may freeze:

- Form fields;
- labels;
- CTA;
- Popup layout.

Sensitive Integration Profile credentials remain live server-side references.

---

# 47. Integration Changes vs Publish

Not every operational Integration credential change should require republishing HTML.

Recommended conceptual distinction:

## Published content/configuration

- Form visible fields;
- Popup layout;
- Block routes/references.

## Live operational configuration

- replaced CRM token;
- retry policy;
- Integration secret rotation.

A token replacement should not require republishing the Site.

This separation must be designed explicitly.

---

# 48. Security Settings vs Publish

Similarly, urgent security changes such as:

- blacklist;
- rate limits;
- CAPTCHA server credentials

should generally take effect operationally without requiring Site republish.

Public rendering may still depend on Published CAPTCHA enablement/config where appropriate.

---

# 49. Analytics Configuration

Yandex Metrica Site configuration may be part of published rendering.

Example:

Counter ID changed
→ may require Publish if inserted into public output.

Operational analytics adapter settings may be live.

Exact behavior must be documented during implementation.

---

# 50. Snapshot Boundaries

Do not snapshot every operational record blindly.

Potential Published snapshot contains/render references to:

- Pages;
- Block Instance state;
- design tokens;
- SEO;
- public Site settings;
- public automotive presentation;
- Popup/Form presentation config.

Operational live systems remain outside snapshot:

- Submission records;
- Delivery attempts;
- secret credentials;
- queues;
- blacklist entries;
- audit logs.

---

# 51. Snapshot Strategy

Potential approaches:

1. normalized version references;
2. serialized Site manifest;
3. generated static/public artifact;
4. hybrid.

Recommended direction:

Use a **versioned published manifest/snapshot** that resolves to stable public output, while core editable business data remains normalized.

Exact implementation requires an ADR before coding the publishing engine.

---

# 52. Published Manifest

A Published manifest could conceptually include:

- Site metadata;
- Pages;
- Block Version references;
- Block state;
- Design Tokens;
- public asset references;
- Site Vehicle public representation;
- Site Offer values;
- Popup/Form public definitions;
- SEO;
- runtime feature config.

Do not include secrets.

---

# 53. Immutable Publication Artifacts

If Landflow later generates static artifacts or cached render output, each publication should be identifiable by version.

Example:

`site/123/version/42/...`

Then activation switches current production pointer to Version 42.

This supports atomic publication and rollback.

---

# 54. Public Rendering Strategy

Exact rendering may eventually use:

- Laravel server rendering;
- React SSR/static generation;
- generated HTML/assets;
- hybrid.

Do not decide this solely in Product code.

A dedicated ADR should select the initial public rendering engine after requirements/testing.

---

# 55. SEO Requirement on Rendering

Whatever strategy is chosen must support:

- crawlable HTML;
- correct metadata;
- custom domains;
- performant initial response;
- stable public URLs.

Avoid a purely client-only runtime if it harms required SEO.

---

# 56. Asset Publication

Published Site may reference media stored in shared object storage/CDN.

Publication should verify required assets exist.

Do not duplicate every media file per publication unless infrastructure requires it.

Version stability may use immutable media references or media-version semantics.

---

# 57. Replaced Assets

If customer overwrites a media file at same logical record, old Published Version must not unexpectedly show new pixels if snapshot stability requires the old version.

Therefore media replacement strategy should favor immutable file objects/versioned paths rather than destructive replacement.

---

# 58. Media Immutability

Recommended:

Upload new media
→ new storage object/reference
→ Draft points to new media
→ old Published Version retains old reference
→ Publish activates new reference

This improves version stability.

---

# 59. Block Version Stability

Published snapshot must pin Block Version.

If Developer releases Block v2, a Site published with Block v1 must continue rendering correctly.

Marketplace updates must not silently change production.

---

# 60. Template Independence

Published Site must not dynamically depend on mutable Template definition.

Template is creation source.

Site owns instantiated configuration.

Template deletion/update cannot break already-created Sites.

---

# 61. Global Catalog Update Stability

If Global Catalog image/spec changes after Site publication:

Published Site should not unexpectedly change if the customer's published representation is snapshot/version controlled.

Exact source fallback behavior must preserve production stability.

Customer can later update Draft and Publish intentionally.

---

# 62. Operational vs Editorial Data

Publishing architecture should distinguish:

## Editorial/Presentation

Requires Publish:
- Block content;
- Site design;
- Site Offer displayed price;
- SEO;
- Page structure;
- public vehicle selection.

## Operational

Usually live:
- Submission records;
- delivery attempts;
- blacklist;
- secret credentials;
- queue state.

This distinction prevents republishing for backend operations.

---

# 63. Publish Failure

If new publication build fails:

- do not switch active production pointer;
- retain current public Site;
- mark Publication failed;
- show actionable error;
- preserve Draft.

---

# 64. Validation Failure

If validation fails before build:

- no new Published Version activated;
- no production changes;
- return field/Page/Block-level validation messages.

---

# 65. Build Failure

If a rendering/build step fails after snapshot creation:

- snapshot may remain as failed/inactive diagnostic version;
- production pointer remains unchanged.

Do not expose failed partial artifact publicly.

---

# 66. Cache

Public Site caching is expected.

Possible cache layers:

- Site/domain resolution;
- Published manifest;
- rendered Page;
- automotive public data;
- CDN assets.

Cache is never source of truth.

---

# 67. Cache Invalidation

Successful Publish should invalidate only relevant production caches.

Examples:

- Site manifest;
- changed Pages;
- sitemap;
- domain-related page cache.

Avoid globally flushing all customer Site caches.

---

# 68. Domain Routing Cache

Hostname
→ Site
→ current Published Version

may be cached for performance.

Domain change/activation must invalidate routing cache.

---

# 69. Publication Concurrency

Prevent two conflicting Publish operations from activating simultaneously.

Potential approaches:

- lock per Site;
- publication state guard;
- queue serialization.

One Site should have one active publication transition at a time.

---

# 70. Publish Idempotency

Duplicate click/retry should not create inconsistent production state.

Publication operation should use idempotent state transitions where practical.

---

# 71. Long-Running Publication

If Publish later becomes expensive:

- persist Publication;
- queue build;
- UI polls/subscribes for status.

Do not keep an HTTP request open indefinitely.

MVP may publish synchronously if fast, but data model should support asynchronous evolution.

---

# 72. Publish Status UI

Potential states:

- validating;
- building;
- activating;
- published;
- failed.

UI should show:

- current production version;
- last published time;
- publisher;
- unpublished changes.

---

# 73. Publication Notes

Future Team feature may let user add a publication note.

Example:

"Updated October campaign prices"

Useful for version history.

Not required for MVP.

---

# 74. Audit

Publishing is an auditable action.

Record:

- User;
- Workspace;
- Site;
- version;
- timestamp;
- result.

Domain changes and rollbacks should also be auditable.

---

# 75. Free Plan Versioning

Free plan may retain limited publication history.

Example product policy later:

- latest version only;
- small number of restore points.

Exact limits belong to entitlements.

Architecture should not hardcode one version forever.

---

# 76. Team Versioning

Team may expose:

- longer history;
- version restore;
- publication actor;
- notes;
- comparisons later.

Still uses same underlying version architecture.

---

# 77. Version Comparison

Future feature may compare:

- Pages;
- Block state;
- Site Offers;
- SEO;
- Design Tokens.

Do not make diff engine an MVP blocker.

---

# 78. Site Duplication and Published State

When duplicating a Site:

Default should duplicate editable Site configuration into a new Draft.

Do not automatically clone production domain ownership.

New Site receives its own:

- subdomain;
- domains;
- publication history.

---

# 79. Domain Copy Restrictions

Site duplication must not copy custom domain as active ownership.

A hostname can belong to only one active Site.

---

# 80. Form Submission Continuity During Publish

Publishing a new Site version must not interrupt Form Submission persistence.

Operational Form endpoint should resolve the correct active public Form definition consistently.

A visitor loading an old page during deployment should not be sent into an invalid half-version state.

---

# 81. Graceful Version Transition

If necessary, public requests may include/resolve Publication Version so Forms/Actions remain compatible during a version transition.

Exact mechanism depends on runtime architecture.

---

# 82. Popup/Action References

Publish validation must ensure:

- Block Action references existing Popup;
- Popup references valid Form;
- all referenced entities belong to the same Site;
- public-safe versions are included.

---

# 83. Broken References

Draft may temporarily contain broken references during editing.

Publish must block if required public references are broken.

---

# 84. Automotive Binding Validation

Publish should verify:

- bound Site Vehicle exists;
- active data sources resolve;
- required Site Offer exists if Block requires price;
- referenced color/image exists where mandatory.

Dynamic collection Blocks may allow empty results if Schema permits.

---

# 85. Empty States

Blocks should define safe public empty-state behavior.

Example:

Vehicle Grid has no matching vehicles.

Possible behavior:

- hide Block;
- show configured empty message.

Do not render broken UI.

---

# 86. Public Error Handling

Production Site should not expose stack traces or internal IDs.

Unexpected runtime failure should show safe fallback/log diagnostic information internally.

---

# 87. Availability

Published runtime should be designed for high read volume compared to authenticated editing.

Architectural priorities:

- fast reads;
- caching;
- stable snapshots;
- minimal database work where practical.

---

# 88. Multi-Site Scale

Many Sites may share:

- same Block Definition;
- same Template origin;
- same Workspace Integration Profile;
- same Global Catalog source.

Published runtime must not duplicate global definitions unnecessarily while preserving version stability.

---

# 89. Custom Domain Entitlement Loss

If a paid subscription expires or entitlement is lost, product policy must define grace behavior.

Possible future states:

- grace period;
- warning;
- custom domain disabled after date;
- fallback to Landflow subdomain.

Do not immediately destroy domain records.

---

# 90. Landflow Branding Entitlement Loss

If `remove_branding` entitlement ends, future publication/runtime may restore Landflow branding according to billing policy.

Exact grace rules belong to subscription policy.

---

# 91. Publication and Entitlements

Publish validation must verify current entitlements for features being published.

Examples:

- custom domain;
- premium Template/Block license;
- remove branding;
- advanced feature.

But an entitlement change should not corrupt historical versions.

---

# 92. Marketplace License Validation

If Site uses paid Marketplace Block/Template:

Publish may need to verify valid license.

Existing production behavior during expired/revoked license must follow explicit marketplace policy, not disappear unexpectedly.

This is future.

---

# 93. Security

Published output must never contain:

- CRM/API secrets;
- private Workspace data;
- Submission data;
- admin permissions;
- internal audit logs;
- unpublished Draft state.

---

# 94. Public Identifiers

Published Site should use public-safe identifiers for:

- Site;
- Form;
- Site Vehicle where needed;
- Actions.

Avoid exposing predictable internal database IDs when unnecessary.

---

# 95. Publishing Tests

Mandatory test categories:

1. Autosave changes Draft only.
2. Existing production remains unchanged before Publish.
3. Successful Publish activates new Version atomically.
4. Failed Publish keeps previous Version active.
5. User without `publish_site` cannot publish.
6. Site price Draft change does not reach production until Publish.
7. Block Version is pinned in Published snapshot.
8. Template update does not mutate Published Site.
9. Custom domain resolves correct Site.
10. Duplicate hostname is rejected.
11. Staging can be configured `noindex`.
12. Rollback/restore does not lose Draft unexpectedly.
13. Published output contains no Integration secrets.
14. Broken Popup/Form reference blocks Publish.
15. Site A cannot publish Site B assets/private data.
16. Cache invalidates after successful Publish.
17. Failed version is never publicly activated.

---

# 96. Cursor Rules

Cursor agents must never:

- make autosave publish automatically;
- render production directly from mutable Draft rows without an approved strategy;
- change Site price live in production before Publish;
- let Template updates mutate Site production;
- let Block updates silently replace pinned versions;
- copy custom domain during Site duplication;
- put secrets in publication snapshots;
- destroy current production on Publish failure;
- globally flush all Site caches for one Publish;
- require republish just to retry a failed CRM delivery;
- implement client-only SEO if it prevents crawlable public output.

---

# 97. Implementation Order

Recommended sequence:

1. Draft model
2. Published Version concept
3. basic Preview
4. Publish authorization
5. validation
6. Publication record
7. atomic activation
8. Landflow subdomain
9. public runtime routing
10. SEO metadata
11. sitemap/robots
12. cache strategy
13. version history
14. restore
15. custom domains
16. DNS verification
17. SSL automation
18. primary domain/redirects
19. richer rollback/history
20. advanced partial publishing only if required

---

# 98. ADR Required Before Runtime Engine

Before implementing the production rendering engine, create an ADR deciding:

- server-rendered vs generated/static/hybrid;
- snapshot storage format;
- public runtime data access;
- asset versioning;
- cache strategy;
- deployment/activation strategy.

Do not let an implementation agent choose this accidentally inside an unrelated task.

---

# 99. Source of Truth

This document refines:

- `PRODUCT.md`
- `ARCHITECTURE.md`
- `DATABASE.md`
- `TENANCY.md`
- `PERMISSIONS.md`
- `AUTOMOTIVE_DATA.md`
- `BLOCK_SYSTEM.md`
- `FORMS_AND_INTEGRATIONS.md`

If publishing semantics need to change, record an explicit architecture decision first.

---

# 100. Final Publishing Model

```text
EDITOR
  ↓
Draft
  ↓ autosave
Draft
  ↓
Preview
  ↓
Publish
  ↓
Authorization
  ↓
Validation
  ↓
Build / Snapshot
  ↓
Atomic Activation
  ↓
Published Version
  ↓
Public Runtime
  ├── landflow.me subdomain
  └── custom domain
```

Version flow:

```text
Published v12
     ↑ remains live
Draft changes
     ↓
Publish v13
     ├── failure → v12 stays live
     └── success → activate v13
```

Critical rules:

**Draft is editable.  
Preview renders Draft.  
Published Version is stable.  
Autosave never changes production.  
Publish is explicit and permission-controlled.  
Failed Publish never removes current production.  
Prices/content change publicly only through Publish.  
Operational systems such as CRM delivery and blacklists remain live and separate from editorial snapshots.  
Custom domains belong to Site and require verification/entitlement.  
Published output must never contain secrets.**
