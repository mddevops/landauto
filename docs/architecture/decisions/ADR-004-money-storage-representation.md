# ADR-004 — Money Storage Representation

**Status:** Accepted (owner, 2026-10-03 — D-084 approved in the autonomous build instruction)
**Resolves:** D-084
**Backlog:** X-008 (trigger: before P3-009)

## Context

Site Offers, prices, benefits and future commercial data need one exact, float-free money representation before any money column exists.

## Decision

1. Money is stored as **integer minor units**. Floating point is never used. A `DECIMAL` currency amount is not used in the main commercial domain.
   - Example: 1 850 000.00 RUB is stored as `185000000`, because the RUB minor unit is the kopeck.
2. Amount columns are `BIGINT UNSIGNED` and use the `_minor` suffix, for example `price_minor`, `rrp_minor`, `amount_minor`.
3. Currency is stored next to the amount as `CHAR(3)`, an uppercase ISO 4217 code (for example `RUB`).
4. Arithmetic happens on integer minor units. Formatting happens only at the UI boundary.
5. **Rounding.** External decimal money is converted to minor units using the currency's minor-unit rules before persistence. JavaScript floating-point numbers are never rounded silently.
6. Frontend forms may send human decimal strings. The backend parses and validates them into minor units; the browser never supplies already-converted authoritative values.
7. Percentages are never stored as floats. If a percentage benefit is introduced, it uses integer basis points (or an equivalent integer scale).

## Consequences

- Money columns may now be introduced by the tasks that need them (Site Offer, P3-009), following this ADR.
- A shared parsing/formatting helper is added with the first money-bearing feature; it is not created speculatively.
