# FLOSC 8.0.0 — authoritative package freeze

| Field | Value |
|-------|--------|
| **Version** | 8.0.0 |
| **Git commit** |  |
| **ZIP (operator path)** | `mvp_sprint/flosc_8_0_0/zip-files/flosc.zip` |
| **Build** | `flosc/build-dist-zip.sh` only |
| **SHA-256** | `50999f2da6cba73b1cecf985ad0d3cbffbe32259e6f5e89ecf5d5f3d21d7f89f` |
| **Entries** | 224 |
| **PHP** | 128 files, `php -l` clean |
| **Frozen** | 2026-08-06 |

## Payment gates included in this freeze

- PAY-01: no grant without settled payment (`is_payment_settled`, free path only for free offers)
- PAY-02: `claim_transaction_fulfillment` binds provider+txn → user+offer
- Stripe: metadata offer bind; complete + webhook via `fulfill_settled_purchase`
- PayPal: capture/subscription fulfill with bound offer
- ClickBank: `pay.clickbank.net/?cbitems=`, INS v8 decrypt, redirect not settled
- No plaintext password in purchase/profile welcome emails

## Rule

Any source change after this record requires a **new** ZIP, a **new** SHA, and a full re-audit. Do not upload superseded SHAs (including `3dcb3742…`).
