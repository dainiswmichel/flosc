# FLOSC 8.0.0 — authoritative package freeze

| Field | Value |
|-------|--------|
| **Version** | 8.0.0 |
| **Git commit** | `aa0f7d237188ee5d9c552737ce73756182e0d5cf` |
| **ZIP (operator path)** | `mvp_sprint/flosc_8_0_0/zip-files/flosc.zip` |
| **Build** | `flosc/build-dist-zip.sh` only |
| **SHA-256** | `1324a03139a4181ed9ad97c58ba7ab29597dfa46830807027b17f893fedc1cb5` |
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
