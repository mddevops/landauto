# Landflow — Forms and Integrations Architecture

**Document:** `docs/architecture/FORMS_AND_INTEGRATIONS.md`  
**Status:** Core forms/integration source of truth

## 1. Purpose

This document defines the Landflow Form, Popup, Submission, Integration, anti-spam, delivery, retry, and logging architecture.

Canonical flow:

**Action → Popup (optional) → Form → Validation → Anti-Spam → Persist Submission → Queue → Email / CRM / API / Webhook → Delivery Log / Retry**

The core rule is:

> A valid lead must be stored in Landflow before any external delivery is attempted.

---

## 2. Form and Popup are separate

A Popup is a presentation container.

It controls things such as:

- modal presentation;
- size;
- overlay;
- animation;
- close behavior;
- responsive behavior.

A Form is a data-collection entity.

It controls:

- fields;
- validation;
- consent;
- context;
- submission;
- routing.

One Form may be used in:

- Popup;
- Hero;
- Vehicle Card flow;
- standalone Page section.

A Popup must not own CRM/API delivery logic.

---

## 3. Form ownership

Initial rule:

**Form belongs to Site.**

A Site may contain many Forms.

Examples:

- Callback
- Get Offer
- Test Drive
- Credit
- Trade-in

Future Workspace-level reusable Form templates may be added explicitly.

Do not create hidden cross-Site Form references.

---

## 4. Form fields

Initial field types:

- text
- phone
- email
- textarea
- select
- checkbox
- consent
- hidden/context

Each field may define:

- stable key;
- label;
- placeholder;
- type;
- required;
- validation;
- options;
- sort order.

Example stable keys:

- `name`
- `phone`
- `email`
- `comment`

Stable keys are important for Integration mapping.

---

## 5. Validation

Backend validation is authoritative.

Frontend validation exists only for UX.

Never trust:

- required HTML attributes;
- hidden inputs;
- frontend masks;
- client-calculated prices.

---

## 6. Phone normalization

Store both when useful:

- original value;
- normalized value.

Example:

`+7 (999) 111-22-33`
→ `79991112233`

Normalized phone is used for:

- duplicate detection;
- blacklist;
- rate limiting;
- CRM mapping.

---

## 7. Submission context

Landflow should automatically attach trusted context where available:

- Site;
- Page;
- Block;
- Popup;
- Form;
- Site Vehicle;
- Site Offer;
- Trim;
- Color;
- current trusted price;
- page URL;
- referrer;
- UTM source;
- UTM medium;
- UTM campaign;
- UTM content;
- UTM term.

The visitor should not re-select data already known by the current Site context.

---

## 8. Automotive example

Visitor sees:

- Changan UNI-K
- Luxe
- Black
- 3 890 000 ₽

Clicks:

`Получить предложение`

Flow:

Vehicle Card  
→ Action  
→ Open Popup  
→ Form

Submission context automatically includes the vehicle, offer, trim, selected color, Page and source Block.

The browser may send a public vehicle identifier, but backend resolves the authoritative Site Offer and price.

---

## 9. Canonical submission pipeline

1. Receive public request.
2. Resolve Site and Form.
3. Confirm Form is active.
4. Validate fields.
5. Normalize phone/email.
6. Apply anti-spam rules.
7. Verify CAPTCHA where required.
8. Resolve trusted Site/vehicle context.
9. Persist Submission.
10. Create Delivery records.
11. Dispatch queued delivery jobs.
12. Return success to visitor.
13. Deliver asynchronously to external systems.

External CRM latency must not hold the visitor request open.

---

## 10. Submission states

Potential states:

- received
- delivery_pending
- partially_delivered
- delivered
- failed

Spam-rejected requests may use separate security logging rather than polluting normal leads.

---

## 11. Anti-spam layer

Standard Landflow Forms use centralized protection.

Possible checks:

- honeypot;
- basic interaction timing;
- IP rate limit;
- normalized-phone rate limit;
- duplicate Form + phone;
- IP blacklist;
- phone blacklist;
- Yandex SmartCaptcha.

A Marketplace Block must not bypass this pipeline.

---

## 12. Site Security Policy

Each Site may configure:

- CAPTCHA enabled/disabled;
- IP submission limits;
- phone submission limits;
- duplicate interval;
- blacklist behavior.

Example:

- 5 submissions / 10 minutes / IP
- 2 submissions / 30 minutes / phone
- duplicate same phone + Form blocked for 15 minutes

Exact defaults remain configurable.

High-frequency counters should use cache/Redis when available rather than creating one database row per request.

---

## 13. Blacklists

Scopes:

- Global Landflow
- Workspace
- Site

Types:

- IP
- phone

Entry metadata may contain:

- normalized value;
- reason;
- source;
- created by;
- created at;
- expiration.

Global blacklist changes must be auditable.

---

## 14. Yandex SmartCaptcha

Landflow must support **Yandex SmartCaptcha** for the Russian-oriented product configuration.

Architecture:

Form  
→ Security Policy  
→ CAPTCHA Provider Adapter  
→ Yandex SmartCaptcha

Blocks/Templates do not integrate CAPTCHA directly.

Sensitive server key must:

- remain server-side;
- be encrypted where appropriate;
- never appear in Block state;
- never appear in logs;
- never be sent through ordinary Inertia props.

Exact provider API implementation must be verified against current official Yandex documentation when coding begins.

---

## 15. Workspace Integration Profile

Reusable credentials/configuration belong to Workspace.

Example:

**Dealer CRM Production**

Shared values:

- Base URL
- auth type
- API token
- secret
- timeout
- default headers
- default mapping

A Workspace Profile may be reused by several Sites.

---

## 16. Site Integration Binding

Site references a Workspace Integration Profile and stores only Site-specific overrides where possible.

Example:

Workspace Integration Profile:

- Base URL: shared
- Token: shared

Site Moscow:

- `site_id = 101`
- `dealer_id = 22`

Site Kazan:

- `site_id = 202`
- `dealer_id = 22`

The token must not be duplicated for each Site.

---

## 17. Ownership rule

A Site may only use Integration Profiles from its own Workspace.

Forbidden:

Workspace A Site  
→ Workspace B Integration Profile

Backend must enforce this relationship.

---

## 18. Integration destination types

Initial conceptual destination types:

- Email
- Webhook
- Custom API
- dedicated CRM Adapter

Future providers may be added through adapters.

---

## 19. Email route

A Form may deliver to one or more email recipients.

Configurable values may include:

- recipients;
- subject template;
- included fields;
- safe reply-to behavior.

Email delivery should be queued just like CRM/API delivery when appropriate.

---

## 20. Webhook / Custom API

Configuration may contain:

- URL;
- HTTP method;
- headers;
- auth via Integration Profile;
- field mapping;
- timeout;
- success rules.

Landflow should provide a constrained safe configuration rather than an unrestricted HTTP scripting engine.

The public visitor must never choose the destination URL.

All outbound HTTP to customer-configured destinations, including `Проверить подключение`, must follow the mandatory SSRF policy in `SECURITY.md` §16.

---

## 21. CRM Adapter

Known CRMs may use dedicated provider adapters.

A provider adapter may define:

- credential schema;
- Site override schema;
- field mapping;
- payload builder;
- success detection;
- retry classification;
- safe error parser.

Provider-specific implementation must not leak into Form Blocks.

---

## 22. Field Mapping

Landflow values can map to destination fields.

Example:

`name`
→ `client_name`

`phone`
→ `telephone`

`vehicle.model.name`
→ `model`

`offer.price`
→ `price`

`site.integration.site_id`
→ `site_id`

Mapping sources may be:

- Form Field;
- Submission Context;
- Site Vehicle;
- Site Offer;
- Site setting;
- Site Integration Override;
- static constant.

---

## 23. Static mapping values

Example:

`source = "landflow"`

Static values belong to trusted configuration, not public request payload.

---

## 24. Form Routing

One Form may have several routes.

Example:

Get Offer:

1. Email Sales
2. Dealer CRM
3. Custom Webhook

Each route is independent.

One failed destination must not erase or invalidate successful destinations.

---

## 25. Delivery records

For every Submission + Route, create a Delivery record.

Potential fields:

- destination;
- status;
- attempt count;
- next retry;
- last attempt;
- delivered at;
- HTTP/provider status;
- safe error summary.

Potential states:

- pending
- processing
- delivered
- retry_scheduled
- failed
- cancelled

---

## 26. Delivery attempts

Each actual provider call may record an Attempt.

Useful fields:

- attempt number;
- started/finished time;
- HTTP status;
- provider error code;
- safe error summary.

Never store tokens, passwords, Authorization headers, or raw sensitive payloads in logs.

---

## 27. Retry

Transient failures may retry.

Examples:

- timeout;
- network failure;
- 502/503;
- temporary CRM outage.

Conceptual backoff example:

1 minute  
→ 5 minutes  
→ 15 minutes  
→ 1 hour

Exact retry policy is an implementation decision.

Permanent validation/auth failures should not loop forever.

---

## 28. Manual retry

Authorized users may retry a failed Delivery.

Required permission:

`retry_deliveries`

Manual retry creates a new attempt and does not rewrite the original Submission.

---

## 29. Idempotency

Delivery jobs should resist accidental duplication.

Potential approaches:

- Delivery state guards;
- unique operation key;
- provider-supported idempotency keys.

This is especially important when creating CRM leads.

---

## 30. Visitor success response

Once Submission is safely persisted, Landflow may immediately show success.

Example:

`Спасибо! Мы свяжемся с вами.`

Visitor should not wait for CRM response.

Post-submit behavior may include:

- success message;
- close Popup;
- open success Popup;
- safe redirect;
- analytics event.

---

## 31. Submission UI

Landflow may provide lightweight lead management:

- list;
- search;
- filter by Form;
- filter by status;
- Submission detail;
- Delivery status;
- Retry.

This does **not** turn Landflow into a full CRM.

Do not add sales pipelines, calls, tasks or internal chat without a separate product decision.

---

## 32. Historical stability

If a Form changes later, old Submissions must remain understandable.

Therefore Submission should preserve submitted values and enough field/schema metadata for historical display.

Deleting a Form Field must not delete old values.

Archiving a Form must not delete its historical Submissions.

---

## 33. Integration secret storage

Sensitive values include:

- API token;
- client secret;
- password;
- private key;
- auth header.

Rules:

- encrypted at rest;
- masked after save;
- never exposed to public frontend;
- never placed in Site export;
- never placed in Block state;
- never logged in plaintext.

UI example:

`API Token: ••••••••••••7K2F`

Action:

`Replace token`

Do not re-display original secret after save.

---

## 34. Test Connection

Authorized user may test an Integration Profile.

The test runs server-side and returns only safe status information.

It must not expose:

- token;
- request Authorization header;
- full sensitive provider response.

---

## 35. Site-to-Site copy

When copying Site configuration, user may choose:

- Forms;
- Form routes;
- Integration Profile references;
- field mapping;
- Site-specific overrides.

Example:

Source Site:
`site_id = 101`

Destination Site:
same Workspace Integration Profile
but:
`site_id = 202`

Shared token remains unchanged and is not copied.

---

## 36. Cross-Workspace copy

Do not silently transfer Integration secrets between Workspaces.

If a Site is moved/copied across Workspaces, the destination Workspace must explicitly configure or reconnect an Integration Profile.

---

## 37. Popup context

Reusable Popup must receive context from the Action that opened it.

Example:

Vehicle Card A
→ same "Get Offer" Popup
→ context A

Vehicle Card B
→ same Popup
→ context B

The Popup/Form must not be statically tied to one vehicle.

---

## 38. Hidden fields

Hidden Form inputs are not trusted.

Where possible, values such as:

- Site;
- vehicle;
- price;
- Form route;
- dealer/site IDs

must be resolved server-side from trusted configuration/context.

---

## 39. Analytics events

Form System emits semantic Landflow events:

- form.start
- form.submit
- form.validation_error
- form.success

Popup:

- popup.open
- popup.close

Automotive:

- vehicle.form_submit

Platform Analytics Layer may forward these events to Yandex Metrica.

Standard Blocks must not paste independent Metrica scripts.

---

## 40. Permissions

Relevant permissions:

- `edit_forms`
- `view_integrations`
- `manage_integrations`
- `edit_form_routes`
- `view_submissions`
- `export_submissions`
- `view_delivery_logs`
- `retry_deliveries`

Designer role must not automatically receive Integration secrets or lead access.

---

## 41. Developer Block boundary

Developer Block may:

- render/reference Landflow Form;
- trigger approved Action;
- receive safe public context;
- emit semantic analytics event.

Developer Block may not:

- access Integration credentials;
- send directly to CRM;
- choose arbitrary webhook;
- bypass CAPTCHA;
- bypass rate limit;
- query Submission history.

---

## 42. Public Form endpoint

Public endpoint must:

1. resolve public Site/Form identifier;
2. verify ownership relationship;
3. validate payload;
4. run anti-spam;
5. verify CAPTCHA;
6. normalize data;
7. resolve trusted context;
8. persist valid Submission;
9. create Delivery records;
10. queue jobs.

Public payload must never contain authoritative credentials or destination configuration.

---

## 43. Required tests

Mandatory scenarios:

1. Valid Submission is saved before CRM delivery.
2. CRM outage does not lose the lead.
3. One Submission can route to several destinations.
4. A Site cannot use another Workspace's Integration Profile.
5. Site override changes `site_id` without duplicating token.
6. Secret never appears in public/Inertia output.
7. Phone normalization powers duplicate detection.
8. IP and phone blacklist scopes work correctly.
9. CAPTCHA failure prevents delivery.
10. Public visitor cannot choose arbitrary webhook URL.
11. Vehicle price is resolved server-side.
12. Retry behavior works.
13. Permanent failure does not retry forever.
14. Manual Retry creates a new attempt.
15. Historical Submission survives Form edits.
16. Custom Block cannot bypass central Form pipeline.

---

## 44. Cursor rules

Cursor agents must never:

- submit directly from browser to CRM;
- store lead only after CRM success;
- put tokens in React;
- duplicate Workspace credentials into every Site;
- trust hidden `price`, `site_id` or integration fields;
- let visitor choose destination;
- bypass anti-spam from a custom Block;
- log credentials;
- make Form synonymous with Popup;
- make a CRM outage lose a valid lead;
- turn Submission management into a full dealership CRM without product approval.

---

## 45. Implementation order

Recommended sequence:

1. Form
2. Form Fields
3. public Form identifier
4. backend validation
5. Submission persistence
6. phone normalization
7. anti-spam policy
8. blacklist
9. rate limiting
10. SmartCaptcha adapter
11. Workspace Integration Profile
12. Site Integration Binding
13. Form Routes
14. field mapping
15. queued Delivery
16. Delivery Logs
17. retry/backoff
18. Email adapter
19. Webhook/Custom API adapter
20. dedicated CRM adapters
21. Submission UI
22. Site-to-Site integration copy
23. analytics events

---

## 46. Vendor verification rule

External services change.

Before implementing:

- Yandex SmartCaptcha;
- Yandex Metrica;
- CRM APIs;
- email providers;

Cursor must verify the current official provider documentation and record any API/version requirements.

Landflow architecture stays provider-abstracted.

---

# 47. Final Model

```text
Block / CTA
    ↓
Action
    ↓
Popup (optional)
    ↓
Form
    ↓
Validation
    ↓
Anti-Spam
├── Honeypot
├── Rate Limit
├── IP Blacklist
├── Phone Blacklist
└── Yandex SmartCaptcha
    ↓
Trusted Context Resolution
    ↓
Persist Submission
    ↓
Create Delivery Records
    ↓
Queue
├── Email
├── CRM Adapter
├── Custom API
└── Webhook
    ↓
Delivery Log
    ↓
Retry / Final Status
```

Integration hierarchy:

```text
Workspace
└── Integration Profile
    ├── Base URL
    ├── Authentication
    ├── Encrypted Credentials
    └── Shared Defaults
         ↓
Site
└── Integration Binding
    ├── site_id
    ├── dealer_id
    ├── source_id
    └── other overrides
         ↓
Form Route
└── Field Mapping
         ↓
Delivery
```

Critical rules:

**Form and Popup are separate.  
Valid leads are persisted before external delivery.  
Workspace stores reusable credentials.  
Site stores local overrides.  
Secrets remain server-side.  
One Submission may have several destinations.  
Delivery is asynchronous and retryable.  
Anti-spam is centralized.  
Yandex SmartCaptcha is a platform adapter.  
Developer Blocks cannot bypass Form security or read Integration secrets.**
