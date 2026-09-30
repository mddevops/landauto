# ADR-002 — Yandex OAuth Identity Linking and Client

**Status:** Accepted (owner, 2026-09-30)
**Resolves:** D-096, D-097
**Backlog:** X-014 (trigger: before P1-005A)

## Context

Yandex OAuth is the second supported Landflow sign-in method (D-095). Email equality alone is not proof that a Yandex identity and an existing Landflow account have the same owner. The implementation also needs a client strategy that keeps credentials and provider tokens server-side.

## Decision

### Identity and linking

1. `provider_user_id` is the stable authorization key for a Yandex identity. Provider email is a normalized attribute, not an authorization key.
2. A known `provider_user_id` signs in its linked User normally.
3. A new `provider_user_id` with an email not used in Landflow may create a new User and identity through the shared new-account path.
4. A new `provider_user_id` whose email already exists in Landflow is neither signed in nor linked automatically. The user must sign in to the existing Landflow account and explicitly connect Yandex from an authenticated flow.
5. A later provider-email change does not update `users.email` automatically.
6. One Yandex identity belongs to exactly one User. One User may have at most one Yandex identity.
7. A sign-in method cannot be disconnected if it is the User's last available method.

### OAuth client

1. Landflow uses a first-party Yandex OAuth adapter built on Laravel's HTTP client. No third-party Yandex Socialite provider/package is introduced initially.
2. The authorization-code flow is: redirect → state validation → code exchange → profile/email retrieval → normalization → Landflow authentication service.
3. Credentials exist only in environment-backed server configuration. The client secret never reaches frontend props, output or logs.
4. The OAuth access token is not persisted when it is needed only to retrieve the provider profile.
5. `user_auth_identities` is internal-only and has no `public_id`. It uses unique constraints on (`provider`, `provider_user_id`) and (`user_id`, `provider`).

### Passwordless users

Yandex-only Users receive no generated, random or otherwise artificial password. P1-005A must make `users.password` nullable and adapt password-dependent flows and UI safely for passwordless accounts, including password confirmation, password reset/addition, email changes, account deletion, and disconnecting the last sign-in method.

## Consequences

- Email collision never grants access or creates an implicit link.
- Explicit linking requires an already authenticated Landflow account flow.
- Provider identity lookup remains stable when the provider email changes.
- P1-005A owns the nullable-password migration and the associated authentication/UI behavior.
- Current official Yandex endpoints, scopes, profile fields and email guarantees must still be verified during P1-005A implementation.
