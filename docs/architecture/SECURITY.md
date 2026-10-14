# Landflow — Security Architecture

> **X-025 Native boundary:** exact-source human approval is the trust boundary; token-aware JavaScript policy checks are defense-in-depth. Native code runs only on stateless published Site origins. Production rejects a `SESSION_DOMAIN` containing the published wildcard domain. The immutable same-origin module needs no `unsafe-eval`; the full Metrica/SmartCaptcha-compatible public CSP remains X-029.

**Document:** `docs/architecture/SECURITY.md`  
**Status:** Core security source of truth  
**Purpose:** Define security boundaries for tenancy, authentication, authorization, public endpoints, forms, uploads, Blocks, RichText, integrations, secrets, admin access, publishing, Marketplace extensions, and auditing.

---

# 1. Security Principles

Landflow security must follow these principles:

1. Backend is authoritative.
2. Workspace isolation is mandatory.
3. Public input is always untrusted.
4. Secrets remain server-side.
5. Published output must never expose private Workspace data.
6. Developer Blocks must run inside controlled platform boundaries.
7. Form/Integration delivery must be centralized.
8. Sensitive operations require explicit permission.
9. Destructive and high-risk actions should be auditable.
10. Security must be testable, not assumed.

---

# 2. Trust Boundaries

Primary trust zones:

## Public Visitor

Untrusted.

Can access only:

- Published Site content;
- public assets;
- public Form endpoints;
- intentionally public automotive data.

## Authenticated Customer User

Trusted only within granted Workspace/Site permissions.

Must not access another Workspace.

## Developer / Marketplace Author

Trusted only for approved development surfaces.

Must not receive arbitrary privileged runtime access.

## Platform Staff / Super Admin

Highly privileged but still explicitly authorized and auditable.

---

# 3. Authentication

Landflow uses standard authenticated user accounts.

Supported sign-in methods (D-095):

- email + password (Fortify) with mandatory email verification before full access;
- Yandex OAuth; the email returned by Yandex is required and counts as verified; without an email no account is created.

Two-factor authentication, TOTP, passkeys and WebAuthn are not Landflow features (D-095; the starter-kit implementation was removed in P1-002).

Unverified email/password accounts (P1-002) may use only: the verification notice, the signed verification link, resend verification (throttled), logout, the `settings` redirect, profile view/update (to correct a mistyped email) and Fortify password confirmation (`password.confirm`, `password.confirm.store`, `password.confirmation`). Every other authenticated route requires `verified`, including account deletion. New authenticated routes require `verified` unless this list is deliberately extended.

Account email rules (P1-002):

- emails are stored trimmed and lowercased; uniqueness is checked on the normalized value;
- changing the email requires the current password, clears verification, sends a new verification email to the new address and an informational notice (no link, no new address) to the old address; `profile.update` is rate-limited;
- a successful password reset ends all of the user's database sessions and rotates the remember token.

Authentication security should include:

- secure password hashing;
- mandatory email verification for email/password accounts;
- session protection;
- rate limiting;
- for OAuth: `state` validation, server-side code exchange, client secret only in server configuration, no account linking by plain email match (D-096).

P1-005A implements Yandex OAuth with one-time 10-minute session state, PKCE S256, rate-limited redirect/callback, server-side token/profile requests, provider `client_id` verification, no stored access token and session rotation after login. OAuth-only Users have a nullable password; adding one requires the email-confirmed password-reset flow. They cannot change email or delete the account until an alternative password method exists.

Authentication alone never grants Workspace access.

---

# 4. Session Security

Session configuration should use secure defaults.

Production should require:

- HTTPS;
- secure cookies;
- HttpOnly cookies;
- appropriate SameSite policy;
- session rotation after login;
- invalidation after sensitive auth changes where appropriate.

Do not expose authentication tokens to arbitrary frontend storage.

---

# 5. CSRF

Authenticated state-changing browser requests must use CSRF protection.

Do not disable CSRF globally for convenience.

Public Form endpoints may use a separate safe design appropriate to public submissions, but they still require:

- validation;
- rate limiting;
- CAPTCHA/anti-spam;
- origin/context controls where useful.

---

# 6. Authorization

Every protected backend action must verify:

- authenticated User;
- Workspace membership;
- Site access if applicable;
- required permission;
- relevant entitlement;
- business invariant.

Frontend button visibility is not authorization.

---

# 7. Tenant Isolation

Workspace is the tenant boundary.

Security requirements:

- Workspace-scoped records must not leak across Workspaces;
- Site-owned data inherits Workspace isolation;
- private assets must remain tenant-scoped;
- integration credentials are Workspace-scoped;
- Submissions are Site/Workspace private.

Cross-Workspace access must fail even when internal IDs are known.

---

# 8. ID Enumeration

Knowing a numeric/internal ID must never grant access.

Protect:

- Site IDs;
- Form IDs;
- Submission IDs;
- Asset IDs;
- Integration IDs;
- Workspace Vehicle IDs;
- Domain IDs.

Public routes should use safe public identifiers where appropriate.

---

# 9. Route Binding Security

Nested routes must validate ownership relationships.

Example:

Workspace A
→ Site B from Workspace B

must be rejected.

Do not rely on URL nesting alone.

---

# 10. Mass Assignment

Ownership fields should not be freely mass-assignable from client input.

Examples:

- workspace_id;
- site_id;
- user_id;
- integration_profile_id;
- role_id.

Server should derive ownership from authorized context.

---

# 11. Input Validation

All public and authenticated input must be validated server-side.

This includes:

- strings;
- URLs;
- IDs;
- prices;
- JSON state;
- Block Schema;
- Form fields;
- integration mappings;
- domain names;
- file uploads.

Frontend validation is UX only.

---

# 12. XSS Protection

Landflow is especially exposed to XSS risk because users edit Site content and developers create Blocks.

Security requirements:

- escape plain text by default;
- sanitize RichText;
- validate URLs;
- restrict arbitrary HTML;
- sanitize SVG;
- restrict executable script injection;
- avoid rendering raw untrusted HTML.

Every raw HTML render path (e.g. React `dangerouslySetInnerHTML`) requires a documented safe source (`.cursor/rules/70-security.mdc` §19–20).

Current documented safe sources:

- none currently (the starter two-factor QR-code component was removed in P1-002, D-095).

---

# 13. RichText Sanitization

RichText content must pass a controlled sanitizer.

Allowed content may include:

- paragraphs;
- headings;
- strong/emphasis;
- lists;
- safe links.

Disallowed by default:

- script;
- iframe unless explicitly approved;
- event handler attributes;
- inline JavaScript URLs;
- unsafe style injection.

---

# 14. URL Validation

Any user-configurable URL must be validated.

Examples:

- CTA URLs;
- webhook URLs;
- external links;
- redirect URLs.

Disallow dangerous schemes such as:

- `javascript:`
- unsafe `data:` forms where not explicitly required.

---

# 15. Open Redirect Protection

Post-submit redirect or CTA redirect must not become an open redirect vulnerability.

Allowed redirect behavior should use:

- internal Page reference;
- validated external URL;
- approved domain rules if needed.

---

# 16. SSRF Protection

Custom API/Webhook integrations create SSRF risk.

Do not allow arbitrary unrestricted server requests to:

- localhost;
- metadata endpoints;
- private internal networks;
- control-plane services.

Implementation must validate/limit destination URLs.

Mandatory protections (`.cursor/rules/70-security.mdc` §23–28, D-062):

- one centralized outbound HTTP policy for all customer-configured destinations; adapters must not implement their own URL checks;
- protocol allowlist (`https`, `http` only where product permits);
- block loopback, private (RFC 1918 and IPv6 unique-local), link-local and cloud metadata addresses, for both IPv4 and IPv6;
- validate the resolved IP addresses, not only the hostname string;
- follow redirects only deliberately, with a small redirect limit, and revalidate every redirect hop against the same policy;
- connect and read timeouts on every request;
- `Проверить подключение` (Test Connection) uses the same or a stricter policy than actual Delivery;
- TLS certificate verification stays enabled.

Provider allowlists for known adapters may add further restrictions.

---

# 17. Webhook Security

Webhook destination configuration belongs to authorized Site/Workspace users.

Public visitor must never control:

- destination URL;
- auth header;
- token;
- HTTP method.

Webhook requests should have:

- timeouts;
- safe retry rules;
- optional signing later.

---

# 18. Secrets

Sensitive values include:

- CRM token;
- API key;
- client secret;
- private key;
- SMTP password;
- CAPTCHA secret.

Rules:

- encrypt at rest where appropriate;
- never log plaintext;
- never send via standard Inertia props;
- never store in Block state;
- never include in published snapshot;
- never include in Site export;
- display only masked value after save.

---

# 19. Secret Rotation

Secret replacement should:

- update server-side encrypted value;
- preserve Site references;
- audit change;
- avoid exposing previous secret.

---

# 20. Logging

Application logs must not contain:

- passwords;
- API tokens;
- Authorization headers;
- secret keys;
- full sensitive Form payload by default.

Safe identifiers may include:

- workspace_id;
- site_id;
- submission_id;
- delivery_id.

---

# 21. Personal Data

Submissions may contain personal data.

Access requires explicit permission.

Security requirements:

- Workspace isolation;
- Site access;
- restricted exports;
- safe logs;
- no Marketplace Block access;
- no public lookup endpoint.

Retention policy must be defined before production launch.

---

# 22. Form Endpoint Security

Public Form endpoint must:

- resolve Site/Form safely;
- verify active published configuration;
- validate payload;
- normalize phone/email;
- apply rate limits;
- apply blacklist;
- verify CAPTCHA;
- resolve trusted vehicle/offer context;
- persist Submission;
- queue Delivery.

Public request must not choose integration routing.

---

# 23. CAPTCHA

Yandex SmartCaptcha integration must use server-side verification.

Frontend success alone is insufficient.

Secret verification key remains server-side.

---

# 24. Rate Limiting

Rate limits should protect:

- login;
- registration;
- password reset;
- OAuth sign-in redirect/callback;
- public Form submission;
- API/Webhook configuration tests;
- domain verification endpoints;
- high-risk admin actions where appropriate.

---

# 25. Brute Force Protection

Authentication endpoints should have rate limiting and standard Laravel security protections.

Do not expose detailed login failure reasons that enable account enumeration unnecessarily.

---

# 26. Account Enumeration

Where practical, authentication/recovery messages should avoid confirming whether an email/account exists.

Exact UX may follow framework/provider best practices.

---

# 27. File Upload Security

Uploads are untrusted.

Validate:

- MIME/type;
- extension;
- size;
- dimensions where appropriate;
- image decode success;
- dangerous content.

Do not trust filename extension alone.

---

# 28. Image Uploads

Allowed image types should be explicit.

Examples:

- JPEG;
- PNG;
- WebP;
- AVIF later.

SVG requires special handling because it may contain script or unsafe markup.

---

# 29. SVG Security

If SVG upload is allowed:

- sanitize;
- remove scripts;
- remove event handlers;
- disallow unsafe external references;
- validate XML structure.

Alternative:

disallow user SVG upload initially and provide controlled icons.

---

# 30. File Names

Never use raw user filename as authoritative storage path.

Generate safe unique storage names.

Original filename may be stored as metadata for display.

---

# 31. Storage Isolation

Storage layout should separate scopes where practical.

Example:

- global/catalog/...
- workspace/{id}/...
- site/{id}/...

Path separation does not replace authorization.

---

# 32. Private Assets

Private Workspace assets may require signed URLs or authenticated delivery.

Do not make every uploaded file public by default.

Published public Site assets may use public/CDN URLs.

---

# 33. Malware Scanning

Future file types beyond images/documents may require malware scanning.

Not necessarily MVP, but architecture should not assume every upload is harmless.

---

# 34. Media Processing

Image processing must happen in controlled server/worker code.

Do not allow user-controlled shell arguments or executable paths.

Preserve alpha transparency when needed.

---

# 35. Block Security

Block Schema may contain data/configuration only.

It must never allow arbitrary:

- PHP;
- SQL;
- shell commands;
- server-side code strings.

Developer Blocks use approved runtime interfaces.

---

# 36. Marketplace Sandbox

Marketplace Blocks require stronger security review than internal official Blocks.

They must not access:

- raw DB;
- filesystem outside approved media API;
- integration secrets;
- other Workspace data;
- authenticated admin tokens.

Catalog licenses (D-121) are authorization data: only the backend resolves them (a Site license or the Site's Workspace license), only Super Admins with `manage_catalog_licenses` create or revoke them, and browser input never selects the Workspace or Site that benefits. Installation grants (`site_block_version_grants`, D-122) are internal provenance with no browser-controlled endpoint; they never move between Sites.

---

# 37. Arbitrary JavaScript

Unrestricted third-party JavaScript is high risk.

Authored Block code runs only under ADR-008 (owner-confirmed 2026-10-08): opaque-origin iframe with `sandbox="allow-scripts"` and no `allow-same-origin`, Landflow-built `srcdoc` with CSP `connect-src 'none'`, allowlisted `postMessage` bridge with source / origin / type checks. Automated checks replace manual review (D-120). Weakening any of these needs a new ADR.

ADR-009 (Accepted 2026-10-08, D-123) is that ADR for one case only. **Current:** all authored code is `sandboxed`. **Target (X-024 / X-025):** approved first-party Native Block Versions run HTML / scoped CSS / JS in the published Site host document. Native JS is trusted, not sandboxed: it can reach the host DOM and same-origin capabilities allowed by the page CSP, and `mount(root, props, api) => cleanup` is not a security boundary. Therefore:

- static checks and AI generation never make code trusted; an authorized internal actor with a separate deny-by-default capability must approve the exact revision / source hash (`approved_revision`, `approved_source_hash`, `approved_by_user_id`, `approved_at`); any Draft change invalidates it;
- Native JS never runs in the Landflow application origin (dashboard, Designer, Studio, authenticated Preview); published Site hosts stay cookie / session-isolated from the application host;
- Native HTML is compiled with context-aware escaping, safe URLs, no inline handlers / `javascript:` / script-style-meta-base-embed injection; CSS is AST-scoped per Block;
- third-party / untrusted authors never get Native trust automatically; their code stays in this sandbox;
- legacy sandboxed versions keep the sandbox; Embeds may use iframes with provider allowlists, `sandbox`, `referrerpolicy` and CSP `frame-src`.

## Native HTML (X-024)

Implemented: Native HTML / scoped CSS / host actions; no Native JS (a Native version with non-empty `js` is refused at Publish and never executed). No production path creates Native versions before the X-025 approval flow.

`dangerouslySetInnerHTML` is used in exactly one place, `resources/js/blocks/native-block.tsx`, and only with `blocks[].native.html` from a Published Version payload — output of the server-side `NativeTemplateCompiler` / `NativeTemplateRenderer` built at Publish time. It is never fed Draft source, browser-supplied HTML, raw state or unsanitized authored HTML; a feature test fails if another file uses it. The compiler guarantees:

- parser-based HTML (libxml) with an element / attribute allowlist; forbidden `script`, `style`, `link`, `meta`, `base`, `iframe`, `frame`, `object`, `embed`, `form`, `portal`; no `on*` or `style` attributes;
- context-aware escaping of every substituted value (text, double-quoted attribute); URL attributes re-checked after substitution (`javascript:`, `vbscript:`, `data:`, protocol-relative and unknown schemes rejected); images only from trusted Published Media URLs;
- reserved `data-landflow-*` / `id` namespaces so authored markup cannot impersonate the host root or block anchors;
- CSS scoped by an AST-based compiler: no `@import`, `@font-face`, `url()`, `expression()`, `behavior`; `:root` / `html` / `body` map to the Block root; keyframes namespaced.

Authenticated application surfaces (Studio, Designer, Site Preview) keep rendering Native versions in the ADR-008 sandbox frame. Raw Native source stays in the server-side manifest; the browser receives only compiled output.

---

# 38. Content Security Policy

Published Sites and Landflow dashboard should eventually use a deliberate CSP strategy.

Goals:

- reduce XSS impact;
- restrict script origins;
- control iframe/media sources;
- support required Yandex services.

Exact CSP implementation depends on runtime architecture and must be tested carefully. The published-Site CSP for approved Native JS and Embed `frame-src` is part of X-025 / X-029 (ADR-009).

---

# 39. Inline Scripts

Avoid uncontrolled inline scripts.

If runtime requires inline bootstrap data/scripts, use safe serialization and CSP-compatible techniques.

Never inject raw user content into script context.

---

# 40. JSON Serialization

When embedding JSON into HTML:

- use safe serializer;
- escape closing tags/control characters;
- never concatenate raw user strings into JavaScript.

---

# 41. Block State Validation

Block Instance state must validate against the pinned Block Schema.

Reject:

- unknown privileged fields;
- invalid types;
- invalid references;
- cross-Site Popup/Form IDs;
- cross-Workspace assets.

---

# 42. Data Binding Security

Bindings may only use approved semantic paths.

Developer cannot bind:

- integration.credentials;
- submission.private_data;
- workspace.members.password;
- arbitrary internal model property.

Binding registry is an allowlist.

---

# 43. Action Security

Action types are allowlisted.

Allowed examples:

- open_url;
- open_page;
- scroll_to;
- open_popup;
- phone;
- email;
- submit_form.

Do not support arbitrary executable action strings.

---

# 44. Popup/Form Reference Security

A Site Block may reference only Popups/Forms belonging to the same Site unless an explicit Workspace-shared feature exists.

Cross-Site private reference should be rejected.

---

# 45. Integration Security

Integration delivery must be server-side.

Never call CRM with secret token directly from public browser JavaScript.

Benefits:

- hides credentials;
- centralizes retry;
- validates payload;
- preserves Submission.

---

# 46. SSRF in Integration Test

"Test Connection" is also an SSRF-sensitive feature.

It must use the same outbound request policy as real delivery.

Do not let authenticated low-privilege users scan internal networks through custom URLs.

---

# 47. Timeout and Resource Limits

External requests must have:

- connection timeout;
- request timeout;
- response size limits where appropriate.

Do not allow external provider to exhaust worker resources indefinitely.

---

# 48. Queue Job Security

Queue jobs must use explicit tenant/resource IDs.

Do not rely on current session Workspace.

Before action, resolve the persisted authorized configuration.

Queued payload should not contain plaintext secret when it can reference an Integration Profile instead.

---

# 49. Idempotency

Sensitive asynchronous operations should resist duplicate execution.

Examples:

- CRM lead creation;
- publication activation;
- billing webhook handling later.

Use idempotent state transitions/keys where appropriate.

---

# 50. Publishing Security

Published snapshot must exclude:

- integration secrets;
- private Workspace data;
- Submission records;
- permissions;
- admin-only metadata;
- Draft-only content.

---

# 51. Draft Security

Draft is private by default.

Do not expose Draft data through public Site endpoints.

Secure Preview links, if added, must be scoped and revocable.

---

# 52. Domain Security

Custom domain operations require:

- permission;
- entitlement;
- hostname validation;
- DNS verification.

Do not allow a User to claim another customer's active hostname.

---

# 53. Domain Validation

Normalize hostnames.

Reject malformed or unsafe values.

Consider:

- punycode/IDN handling;
- trailing dots;
- case normalization;
- wildcard policy.

Exact rules belong in domain implementation.

---

# 54. DNS Rebinding / Verification

Domain verification should not rely on one unsafe HTTP fetch alone.

Prefer explicit DNS ownership verification patterns appropriate to infrastructure.

---

# 55. SSL Security

Production custom domains should use HTTPS.

Certificates/private keys should be managed by infrastructure, not customer-visible application fields.

---

# 56. Super Admin Security

Super Admin must use platform roles/permissions.

Never use:

- `user_id == 1`;
- hardcoded email;
- hidden frontend-only flag.

---

# 57. Super Admin Least Privilege

Separate platform roles may include:

- Catalog Manager
- Support
- Marketplace Moderator
- Finance Admin
- Super Admin

Not every platform employee needs every sensitive permission.

---

# 58. Impersonation

If implemented later:

- explicit platform permission;
- visible banner;
- audit start/end;
- optional reason;
- easy exit;
- no silent background impersonation.

Sensitive actions may be blocked or separately confirmed while impersonating.

---

# 59. Audit Log

Audit high-risk events such as:

- role changes;
- member removal;
- publishing;
- domain changes;
- integration credential replacement;
- blacklist changes;
- price changes;
- Super Admin actions;
- impersonation.

Do not log secrets in before/after snapshots.

---

# 60. Price Integrity

Commercial prices are sensitive.

Public visitor may send a Site Vehicle identifier.

Backend resolves trusted current/published Site Offer.

Never trust user-submitted price as authoritative.

---

# 61. Catalog Integrity

Customers cannot write Global Catalog.

Catalog Manager actions should validate:

- hierarchy;
- references;
- media ownership;
- status transitions.

Hard deletion should be restricted when customer references exist.

---

# 62. Cross-Workspace Asset Security

A Site may only use:

- Global public assets;
- same Workspace assets;
- Site-owned assets.

Reject Workspace B private asset reference in Workspace A Site.

---

# 63. Cross-Workspace Vehicle Security

Workspace Vehicle Library is private.

A customer cannot guess/import another Workspace's private vehicle record.

Only Global Catalog is broadly shared.

---

# 64. Export Security

Exports may contain sensitive business/personal data.

Submission export requires explicit permission.

Site export must omit:

- integration secrets;
- API tokens;
- hidden admin config;
- private unrelated Workspace data.

---

# 65. Backup Security

Production backups must be protected as sensitive infrastructure data.

They may contain:

- user data;
- Submissions;
- encrypted secrets.

Access to backups must be restricted outside normal application user access.

---

# 66. Encryption Keys

Application encryption keys are infrastructure secrets.

Do not store them in database records, repo, or frontend.

Key rotation strategy should be planned before mature production if needed.

---

# 67. Environment Configuration

Production secrets belong in secure environment/secret management.

Never commit:

- APP_KEY;
- database password;
- CRM platform credentials;
- storage secrets;
- Yandex server secrets.

---

# 68. Repository Security

`.env` and secret files must remain ignored.

Do not place real production tokens in:

- fixtures;
- tests;
- screenshots;
- docs;
- Cursor prompts.

Use clearly fake examples.

---

# 69. Dependency Security

Packages should be minimized.

Before adding a dependency, evaluate:

- maintenance;
- license;
- security history;
- necessity;
- bundle/runtime impact.

Do not install packages simply because an agent prefers them.

---

# 70. Dependency Updates

Security updates should be applied deliberately.

Automated dependency update tooling may be introduced later.

Do not auto-merge major upgrades without tests.

---

# 71. Framework Security

Prefer standard Laravel/React security mechanisms over custom implementations where possible.

Examples:

- Laravel auth/session;
- validation;
- CSRF;
- encryption;
- queues;
- signed URLs where appropriate.

---

# 72. SQL Injection

Use ORM/query builder parameter binding.

Do not interpolate user input into raw SQL.

Raw SQL, when necessary, must use bound parameters and code review.

Marketplace Blocks never execute raw SQL.

---

# 73. Command Injection

Never concatenate user-controlled values into shell commands.

Media/tool commands require strict argument handling and allowlists.

---

# 74. Path Traversal

File operations must not trust user-supplied paths.

Use storage abstraction and generated identifiers.

Reject `../`-style traversal.

---

# 75. Deserialization

Do not use unsafe object deserialization on untrusted input.

Flexible state uses validated JSON, not executable serialized objects.

---

# 76. JSON Schema Limits

Block/Form dynamic JSON should have:

- maximum payload size;
- nesting limits;
- field count limits;
- Repeater item limits.

This reduces abuse and accidental extreme payloads.

---

# 77. Denial of Service

Protect high-cost endpoints.

Examples:

- image upload/processing;
- catalog bulk import;
- Form submit;
- Preview generation;
- Publish;
- Integration Test.

Use:

- limits;
- queues;
- timeouts;
- rate limits.

---

# 78. Image Bomb Protection

Image decoding may be vulnerable to huge dimensions/compression bombs.

Validate:

- pixel dimensions;
- file size;
- decoder behavior.

Do not trust compressed size only.

---

# 79. Public Cache Security

Never cache private authenticated pages into shared public cache.

Cache keys must include correct tenant/site/version context.

---

# 80. Cache Poisoning

Published Site cache must derive host/Site identity from validated domain routing.

Do not let arbitrary Host/header values poison another Site's cache.

---

# 81. Host Header

Validate recognized hosts/domains.

Domain routing should use normalized configured hostnames.

Do not blindly trust Host for security-sensitive URLs.

---

# 82. CORS

Do not enable permissive `*` CORS globally.

Only configure CORS where an API/public runtime genuinely requires cross-origin access.

Credentials + wildcard origins must never be combined.

---

# 83. API Tokens

Future API tokens should:

- belong to Workspace/User/Developer explicitly;
- have scopes;
- be revocable;
- be hashed or securely stored where appropriate;
- show secret only once on creation if applicable.

---

# 84. Webhook Signatures

Future outbound/inbound webhook systems should support signing where appropriate.

Inbound webhooks from providers must verify provider authenticity.

---

# 85. Billing Webhooks

When billing is implemented:

- verify provider signature;
- use idempotency;
- never trust client-reported payment status.

---

# 86. Yandex Metrica Security

Metrica Counter ID may be public by design.

Do not confuse public Counter ID with secret server credential.

Analytics integration must not expose unrelated Site/Workspace settings.

---

# 87. Privacy in Analytics

Do not send unnecessary personal Form data to analytics events.

Avoid sending:

- phone;
- email;
- secret/internal IDs

to Yandex Metrica unless explicitly justified and legally appropriate.

Prefer semantic events.

---

# 88. Error Handling

Production errors must not expose:

- stack traces;
- SQL;
- filesystem paths;
- secrets;
- internal network information.

Log safe diagnostic context internally.

---

# 89. Security Headers

Public Sites and dashboard should eventually use appropriate headers:

- Content-Security-Policy;
- X-Content-Type-Options;
- Referrer-Policy;
- frame-ancestors / X-Frame-Options where relevant;
- HSTS in production.

Exact policy must be compatible with required services.

---

# 90. Clickjacking

Landflow dashboard should not be frameable by arbitrary origins.

Published customer Sites may have different requirements depending on embeds.

Control this deliberately.

---

# 91. Preview Tokens

Secure Preview token requirements:

- high entropy;
- revocable;
- optional expiry;
- Site-specific;
- read-only;
- no editor privilege.

Do not put sensitive data in token itself if avoidable.

---

# 92. Password/Secret Fields in UI

Browsers should not receive saved secret plaintext.

UI displays masked placeholder and supports replacement.

Do not populate password input with real stored value.

---

# 93. Security Testing

Mandatory test categories:

1. User cannot access another Workspace Site.
2. User cannot access another Workspace Submission.
3. Site cannot bind another Workspace Integration Profile.
4. Customer cannot edit Global Catalog.
5. Public Form cannot choose arbitrary destination.
6. Public Form cannot trust submitted price.
7. XSS payload in text/RichText is escaped/sanitized.
8. `javascript:` URL is rejected.
9. Cross-Site Popup/Form reference is rejected.
10. Block state rejects unknown privileged fields.
11. Integration secret is not returned in Inertia props.
12. Logs redact Authorization token.
13. File upload rejects disallowed MIME/content.
14. SVG sanitizer rejects script/event handlers if SVG is supported.
15. Webhook blocks forbidden private-network target.
16. Rate limits and blacklist work.
17. CAPTCHA failure blocks Submission delivery.
18. User without publish permission cannot Publish.
19. Super Admin bypass requires explicit platform permission.
20. Failed Publish does not expose Draft.
21. Published snapshot contains no secrets.

---

# 94. Security Review Gates

Before production launch, explicitly review:

- authentication;
- tenancy;
- permissions;
- public Form endpoint;
- integration outbound HTTP;
- file uploads;
- publishing;
- domain routing;
- Developer/Marketplace runtime;
- admin/impersonation;
- secrets/configuration.

Marketplace should receive an additional dedicated security review before public third-party code/content is enabled.

---

# 95. Incident Readiness

Production should eventually support:

- centralized logs;
- alerting;
- audit lookup;
- token rotation;
- session invalidation;
- disabling compromised Integration Profile;
- disabling Marketplace Block;
- disabling Site/public Form if necessary.

Do not wait for an incident to invent every control.

---

# 96. Cursor Security Rules

Cursor agents must never:

- disable auth/authorization to make tests pass;
- trust frontend Workspace/Site IDs;
- expose secrets to React;
- log credentials;
- allow arbitrary PHP/SQL/JS in Block Schema;
- disable CSRF globally;
- accept arbitrary webhook/private-network URLs without security review;
- store unsanitized RichText as trusted HTML;
- trust uploaded file extension;
- make all uploads public;
- bypass anti-spam;
- hardcode Super Admin user ID/email;
- expose Draft publicly;
- weaken tenant isolation for admin convenience.

---

# 97. Implementation Order

Recommended security implementation sequence:

1. authentication baseline
2. Workspace membership isolation
3. Policies/permissions
4. CSRF/session hardening
5. public Form validation/rate limits
6. CAPTCHA
7. blacklist
8. secret encryption/redaction
9. upload validation
10. RichText sanitization
11. URL/action validation
12. integration outbound request policy / SSRF protection
13. publishing isolation
14. domain validation
15. audit logs
16. admin role separation
17. Preview tokens
18. Marketplace sandbox/security review

---

# 98. Security ADRs

Create ADRs before introducing major security-sensitive mechanisms such as:

- Marketplace custom scripting;
- server-side code execution;
- public developer API;
- cross-Workspace sharing;
- custom upload file types;
- embedded third-party scripts;
- advanced domain proxying.

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
- `PUBLISHING.md`

If an implementation change weakens a boundary defined here, it requires explicit architectural review.

---

# 100. Final Security Model

Landflow security should always be understood as:

```text
PUBLIC VISITOR
  ↓ untrusted
Published Site / Public Form API
  ↓ validate + anti-spam + safe public data
Landflow Backend
  ↓
Workspace Ownership + Permission Checks
  ↓
Domain Services
  ├── Automotive
  ├── Forms
  ├── Integrations
  ├── Publishing
  └── Assets

Secrets
→ encrypted/server-side only

Developer Blocks
→ approved Schema + Actions + Bindings
→ no raw privileged access

Super Admin
→ explicit platform permissions
→ audit
```

Critical rules:

**Backend is authoritative.  
Workspace is the tenant boundary.  
Public input is untrusted.  
Secrets never reach public frontend.  
Rich content is sanitized.  
Uploads are validated.  
Integrations are server-side and SSRF-aware.  
Developer Blocks use allowlisted capabilities.  
Published output contains no private data.  
Super Admin is explicit and auditable.  
Security protections are never disabled for convenience.**
