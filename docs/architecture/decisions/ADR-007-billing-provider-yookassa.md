# ADR-007 — Billing Provider: YooKassa with Landflow-Owned Subscriptions

**Status:** Accepted (owner approval, 2026-10-06: "Owner decision now APPROVED: Billing provider: YooKassa / ЮKassa")
**Resolves:** D-078 (billing provider)
**Builds on:** ADR-001 (public IDs), ADR-004 (money in integer minor units), D-099 (active Site counting), D-100 (default Free plan), D-111 / D-112 (live `custom_domain` / `remove_branding` effects)
**Leaves open:** exact retry cadence, plan prices, plan-change / proration rules, refunds, 54-FZ fiscal configuration, billing-history retention (D-094) — see §13
**Backlog:** P7-008 (gate for P7-009, which stays DEFERRED)

## Context

Phase 7 delivered paid capabilities driven by typed Workspace entitlements (`max_sites`, `custom_domain`, `remove_branding`; `max_members` is defined). Entitlements currently come from a Plan assigned directly through `workspaces.plan_id` (P1-009) and are read only through `WorkspaceEntitlements`. Real subscriptions need a payment provider, a billing owner, a subscription lifecycle and a trustworthy confirmation path. This ADR fixes those choices. It adds no code, migrations or configuration; P7-009 implements it.

Provider facts below were checked against the official YooKassa developer documentation on 2026-10-06 (notifications, autopayments). P7-009 must re-verify the current official documentation before implementing.

## Decision

### 1. Provider

YooKassa (ЮKassa) is the initial Landflow billing / payment provider. Landflow is a single merchant charging its own SaaS customers. The YooKassa OAuth Partner API and any marketplace payment platform are out of scope.

The architecture stays provider-abstracted: provider-specific code lives behind one contract (§11), and entitlements never depend on provider objects, so another provider can be added later without rewriting entitlements.

### 2. Billing customer = Workspace

- The billing customer is the **Workspace**, never a User, Site or Workspace member.
- Plans and paid entitlements apply to a Workspace. A User owning several Workspaces sees independent billing state per Workspace.
- A Free Workspace (D-100) needs no Subscription and no payment data.
- Managing billing (checkout, saved method, invoices / history, cancellation) requires the existing `manage_billing` permission, currently held only by the Owner role. Permission and entitlement stay separate; the backend derives the Workspace from context, never from browser input.

### 3. Landflow owns the subscription; YooKassa executes payments

- The Landflow **Subscription** record is the source of truth for plan, period and status. YooKassa does not define Landflow entitlement periods.
- YooKassa autopayments are merchant-initiated: Landflow decides when to charge. A provider subscription object is not required; `provider_subscription_id` stays nullable and unused initially.
- Entitlements keep flowing through `workspaces.plan_id` → `WorkspaceEntitlements`. The billing lifecycle changes `workspaces.plan_id` in the same transaction as the Subscription change (paid Plan on activation, Free plan on downgrade). No business code reads Subscription or provider state to decide capabilities, and no code branches on plan keys or names.

### 4. Conceptual entities (implemented in P7-009, no migrations now)

Externally addressable records get a ULID `public_id` (ADR-001). Money follows ADR-004 (`*_minor` `BIGINT UNSIGNED`, `CHAR(3)` currency). Initial billing currency: `RUB`.

- **Workspace billing identity** — the Workspace itself; a provider customer ID is optional (`provider_customer_id` nullable, YooKassa does not require one).
- **Subscription** — one current Subscription per Workspace: `workspace_id`, `plan_id`, `provider`, `provider_customer_id` (nullable), `provider_subscription_id` (nullable), `status`, `current_period_start`, `current_period_end`, `cancel_at_period_end`, `canceled_at`, timestamps. Statuses at least `active`, `past_due`, `ended`; the exact enum is fixed in P7-009.
- **BillingPayment** — one row per charge attempt (initial or renewal), created *before* the provider call: Workspace, Subscription, Plan, `amount_minor`, `currency`, billing period start / end, `provider`, `provider_payment_id`, idempotence key, status, timestamps. Immutable history: corrections are new records, never edits of settled ones.
- **BillingInvoice / order record** — Landflow's internal immutable record of what was billed for which period (may be the same record as the successful BillingPayment if P7-009 finds a separate table unnecessary). It is not a fiscal receipt (§10).
- **SavedPaymentMethod** — reference only: Workspace, `provider` = `yookassa`, `payment_method_id`, status (`active` / `inactive`), optional provider-supplied masked display (for example card type and last 4 digits), timestamps.

Pricing is Plan configuration / business data (amount in minor units, currency, period), added in P7-009. No final prices are hardcoded. The charged amount is recorded on every BillingPayment, so later price changes never rewrite history.

### 5. Saved payment method

- The initial paid checkout creates a YooKassa payment with `save_payment_method = true` and the confirmation redirect flow.
- After the provider confirms success (§8), Landflow stores `provider = yookassa` and the `payment_method_id`.
- Never stored: PAN, CVV / CVC, full card data or anything beyond the provider-supplied masked display.
- `payment_method_id` is server-side sensitive billing data: encrypted at rest, never sent to React props, public runtime, published output, exports or logs.
- The user must be told, before saving, how often and how much will be charged and how to opt out (YooKassa autopayment consent requirement). The consent / offer text is a legal / business deliverable, not invented by agents.
- YooKassa cannot delete a saved method on its side; disabling autopay means Landflow marks the method inactive and stops charging it. A bank-side revocation shows up as failed / canceled payments and is handled as a failed renewal.

### 6. Recurring billing

- Landflow controls the schedule. A server-side scheduled job (Laravel scheduler + queue, database driver; no Redis requirement) finds Subscriptions due for renewal and charges the saved method.
- Each renewal attempt first creates a BillingPayment, then calls YooKassa with `payment_method_id` and a stable unique `Idempotence-Key` derived from that BillingPayment. A retried request for the same attempt reuses the key; a new attempt gets a new record and key. Renewal processing for a Workspace is serialized (row lock) so one period is never charged twice.
- Retries are bounded and idempotent and happen while the paid period is still running. The exact cadence is finalized in P7-009.

### 7. Entitlement lifecycle

| Event | Effect |
|---|---|
| Free | Workspace uses the Free plan entitlements; no Subscription. |
| Initial payment succeeded (provider-confirmed) | Subscription `active`, paid period set, `workspaces.plan_id` = selected paid Plan, its entitlements apply. |
| Renewal payment succeeded | `current_period_end` extended by one period; status `active`. |
| Renewal payment failed, paid period still running | Subscription `past_due`; already-paid entitlements stay until `current_period_end`. |
| `current_period_end` reached without a successful renewal | Subscription `ended`; Workspace downgraded to the Free plan. |

A downgrade never deletes Workspaces, Sites, domains or content. Existing Phase 7 behavior then applies:

- `custom_domain` lost → the custom host stops being effective and the Site falls back to its Landflow subdomain (rows kept);
- `remove_branding` lost → Landflow branding returns on the next request;
- `max_sites` lowered → existing active Sites stay live and published; creating more Sites is blocked (D-099).

### 8. Cancellation

- User cancellation sets `cancel_at_period_end = true` (and `canceled_at`); already-paid access is kept.
- At `current_period_end` no further charge is made and the Workspace is downgraded to Free.
- Immediate cancellation and refund policy are not defined here and need a separate decision.

### 9. Webhooks and payment confirmation

Future endpoint: `POST /billing/webhooks/yookassa` — public and unauthenticated by session (CSRF-exempt, rate-limited), but every event is provider-verified. YooKassa Basic Auth integrations configure notifications in the merchant profile; the URL must be HTTPS on port 443 or 8443.

The webhook body is never the sole authority:

1. HTTPS only.
2. Parse only known events; acknowledge and ignore everything else.
3. Check the sender IP against the current official YooKassa notification ranges (kept in configuration and re-verified against the docs, not hardcoded from memory) where operationally appropriate.
4. Fetch the referenced payment from the YooKassa API with merchant credentials.
5. Act only on the actual provider status.
6. Verify amount, currency and Landflow metadata (BillingPayment `public_id`) against the Landflow record.
7. Apply the transition idempotently inside a transaction; a duplicate or late webhook is harmless.
8. Reply HTTP 200 once processed or safely ignored. Non-200 makes YooKassa retry for 24 hours, so transient failures may return non-200.

The browser return from checkout is UX only (it shows «Платёж обрабатывается» and reads server state). Entitlements are never activated because the browser came back. A reconciliation job also re-checks pending BillingPayments through the API, so a lost webhook cannot leave a paid Workspace unactivated.

Initial events: `payment.succeeded`, `payment.canceled`. Refund events (`refund.succeeded`) only when a refund workflow is approved. Unused events are not implemented.

### 10. 54-FZ receipts

YooKassa supports receipt / fiscalization workflows for 54-FZ. The fiscal configuration (VAT rate, tax system, `payment_subject`, `payment_mode`, receipt texts) depends on the Landflow legal entity, tax regime and production merchant setup, and is not decided here. Agents must not fabricate it.

The billing architecture keeps a **receipt payload adapter point**: payment creation asks a receipt builder for an optional receipt payload, driven entirely by configuration. It stays disabled until business / accounting configuration exists. A fiscal receipt is not the same thing as the Landflow invoice / history record.

### 11. Provider abstraction

P7-009 defines a `BillingProvider` contract with a YooKassa implementation and a fake for tests and E2E (selected by configuration, fake only in testing / e2e environments). Conceptual operations:

- `createCheckout(...)` — initial payment with saved method, returns the confirmation URL;
- `getPayment(...)` — authoritative provider state (used by webhooks and reconciliation);
- `chargeSavedMethod(...)` — merchant-initiated renewal with an idempotence key;
- disable saved method — local deactivation; provider call only if ever supported or required;
- `refund(...)` — later, only with an approved refund policy.

Provider HTTP calls use the Laravel HTTP client with timeouts, a fixed official API base URL and no customer-supplied URLs.

### 12. Credentials and authentication

- YooKassa merchant credentials are **platform infrastructure secrets**. They never belong to Workspace Integration Profiles, Site Integrations, Published Manifests, React props, exports or logs.
- Initial authentication: merchant HTTP Basic Auth with `shop_id` and `secret_key`, unless the official docs materially change before P7-009.
- Future configuration concept: `BILLING_PROVIDER=yookassa`, `YOOKASSA_SHOP_ID=`, `YOOKASSA_SECRET_KEY=`. No real values in the repository; `.env.example` gets empty placeholders only when P7-009 adds the config.
- Logs carry only safe metadata (Workspace / BillingPayment public IDs, provider payment ID, event, status, error class); never the secret key, `payment_method_id`, card display data or request bodies.

### 13. Prerequisites and open items

- **Production prerequisite:** YooKassa autopayments are available only in the demo store by default; recurring / autopay must be enabled for the production merchant account (through the YooKassa manager) before a real subscription launch. This is a deployment / business prerequisite, not a blocker for this ADR.
- **Before production launch (business / accounting):** plan prices, offer and autopayment consent texts, 54-FZ receipt configuration.
- **For P7-009 to finalize:** exact renewal retry cadence (bounded), exact Subscription status enum, whether a separate invoice table is needed.
- **Need separate decisions, not invented here:** paid-plan changes and proration, immediate cancellation and refunds, trials and coupons, billing-history retention (D-094).

## Consequences

- P7-009 can implement real subscriptions without reopening provider, ownership, lifecycle or confirmation rules. It stays DEFERRED until scheduled.
- Entitlement consumers (custom domains, branding, Site limits) need no change: billing only switches the Workspace Plan.
- Webhook handling is a new public endpoint and must get the security review threshold treatment (provider verification, idempotency, safe logs).
- Billing history is append-only and must survive plan changes and downgrades.
