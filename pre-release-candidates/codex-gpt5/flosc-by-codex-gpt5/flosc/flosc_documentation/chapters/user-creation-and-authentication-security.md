# User Creation and Authentication Security

Michel Date Stamp: 2026-11m-05d

This document provides the technical justification and security boundaries for FLOSC code that creates, updates, or signs in WordPress users. FLOSC uses WordPress core user and cookie APIs; it does not write password hashes or authentication cookies itself.

## Why a WordPress account is necessary

FLOSC sells and gates WordPress content. A durable WordPress user ID is the join key for purchase fulfillment, member level, quiz state, token balance, protected-content access, and account recovery. An application password cannot represent a customer session and cannot bind a purchase to front-end content access. For that reason, the following explicitly enabled journeys may create a nonprivileged account:

- Email registration creates a pending account and activates it only after a one-time email-verification token is consumed.
- SSO creates an account only after an administrator-enabled provider completes its OAuth callback and supplies a valid normalized profile.
- PayPal or ClickBank fulfillment may create the purchaser's account only after the provider notification is authenticated and the purchase passes fulfillment checks.
- Ordinary MagicLink issuance never creates an account; it is limited to an existing WordPress user.

## Account-creation invariants

- Every new account role passes through `flosc_registration_role()`. Privileged and unknown roles are refused; the default is `subscriber`.
- Email addresses use `sanitize_email()` and `is_email()`. Usernames use strict `sanitize_user()` handling and WordPress uniqueness checks.
- Passwords are generated with `wp_generate_password()` and are never logged, returned in an API response, or emailed in plaintext.
- SSO data is sanitized again after third-party filters run, so an extension cannot inject raw profile fields or a privileged role.
- Payment fulfillment is idempotent: a replay cannot repeatedly create accounts or repeatedly grant the same purchase.
- Pending email accounts do not receive registration token grants until verification succeeds.
- Creation failures remain `WP_Error` values and do not fall through to authentication.

## Login journeys and their proof

### Email verification

The browser presents a high-entropy, one-time transient token. FLOSC rate-limits consumption, validates the stored user ID and email against the current account, uses `hash_equals()` when a current token is stored, deletes the transient only after activation succeeds, and then asks WordPress to set the current user and authentication cookie.

### MagicLink

MagicLink is disabled by default and must also be enabled for the individual flow. It resolves only an existing user. Tokens are high entropy, short lived, rate limited, single use, and bound to stored payload data before WordPress receives the user ID. Invalid and stale tokens fail closed.

### SSO

SSO is administrator configured. The callback verifies provider state and resolves the return URL from the flow ID carried in signed OAuth state rather than from `HTTP_HOST`. Provider identity data is normalized and validated before account linking or creation. Automatic linking requires a provider-verified email unless the administrator has explicitly enabled the narrower exception.

### Purchase and cross-domain continuation

Server-to-server payment notifications may create or update a purchaser but do not log an arbitrary browser in. Optional post-purchase continuation uses a short-lived, single-use token bound to the fulfilled user. The feature is off unless enabled by the administrator.

## WordPress integration

FLOSC calls `wp_insert_user()`, `wp_update_user()`, `wp_set_current_user()`, `wp_set_auth_cookie()`, and `wp_set_password()` so WordPress owns validation, password hashing, session tokens, cookie flags, and standard user hooks. FLOSC never constructs an authentication cookie or password hash.

Token-based authentication is not a password attempt, so WordPress password-attempt throttles do not apply to the token itself. FLOSC therefore supplies journey-specific rate limits, expiry, one-time consumption, nonce or signed-state validation, and capability checks at those boundaries. Where the completed journey fires `wp_login`, security and membership extensions can observe the authenticated session. The OAuth callback intentionally completes FLOSC redirect resolution itself; its provider-state, token, and email checks are the authentication proof for that non-password journey.

## Update-only calls

Some reported `wp_update_user()` calls do not create or authenticate anyone. They update the already-resolved purchaser's sanitized display name, the authenticated user's own profile, or fulfillment metadata. Password changes require the authenticated account boundary and use `wp_set_password()` so core invalidates earlier sessions.

## Review map

- `includes/magic-link/class-flosc-magic-link-trait.php`: pending email registration, verification, MagicLink, and one-time continuation tokens.
- `includes/sso/class-user-linker.php`: normalized SSO account creation and linking.
- `includes/sso/class-oauth2-handler.php`: verified OAuth callback session establishment.
- `includes/sale/providers/class-flosc-clickbank-provider.php`: authenticated purchase fulfillment.
- `includes/class-flosc-framework.php`: first-party registration, payment fulfillment, account-profile updates, and bounded continuation.
- `includes/filesystem/flosc-data-paths.php`: existing-user WordPress session synchronization after a proven FLOSC authentication event.

No pathway creates an administrator account, no convenience access link creates an account, and no server-to-server payment notification logs a browser in by itself.
