# FLOSC 8.0.0 — package freeze

| Field | Value |
|-------|--------|
| **Version** | 8.0.0 |
| **ZIP** | `mvp_sprint/flosc_8_0_0/zip-files/flosc.zip` |
| **Build** | `flosc/build-dist-zip.sh` only |
| **SHA-256** | `cb4d47bbb64b2fa1faff6cf3fbe1183ac0172b166383c3792a49baf9bbcca2a3` |
| **Frozen** | 2026-08-07 |

## Included in this freeze

### WPORG-01
- Removed seeded `$197` “FLOSC Plugin – Full Access” offer and `flosc_plugin*` product maps
- readme: **Included Features**

### PayPal (industry standard purchase intent)
- **Subscriptions:** `POST /paypal/prepare-subscription` → intent UUID in PayPal `custom_id` → activate requires ACTIVE + plan/offer/amount from intent
- **One-time orders:** create_order mints intent, `custom_id` = purchase_uuid; capture resolves offer/amount from intent
- Fail-closed buyer email when logged in; claim via `fulfill_settled_purchase`

### Also
- ClickBank: SHA-1 IPN only; mandatory vendor + product item
- Token/affiliate: atomic debit under row lock

## Rule
Any code change requires a new zip, new SHA, and re-freeze. Do not upload superseded SHAs.
