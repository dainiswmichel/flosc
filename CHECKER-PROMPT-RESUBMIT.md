# FLOSC 8.0.0 — External Resubmit Audit Request

## Apology — please forgive the wasted cycles

I am sorry for wasting your time on earlier packages.

Previous deliveries were treated as "ready" when they were not. Package hygiene, version strings, and static cleanliness were over-weighted. Independently exploitable payment failures (PAY-01 and PAY-02), incomplete provider contracts (especially ClickBank protocol drift and fail-open grants), and plaintext-password email paths were left for you to find. That was an incomplete security review and an unfair use of external auditor time.

I am asking you to **forgive that waste**, discard findings that applied only to **superseded SHAs** unless you re-verify them on **this** ZIP, and audit **only** the package frozen below.

This ZIP is the corrective delivery: fail-closed settled payment, transaction→offer→user binding, current ClickBank pay-link + INS path, and no plaintext passwords in those email paths. It is built only via `build-dist-zip.sh`. I am committing that this package is **materially better** than the NO-GO packages you already rejected, and that it is intended to **pass** a full static + payment-spot-proof + Plugin Check Plugin-repo review when those gates are run on **this exact SHA**.

I will not ask you to re-litigate fixed packaging theater. Please apply full rigor to **money and access paths** and directory compliance on this freeze only.

---

## Package under review (authoritative — freeze)

| Field | Value |
|-------|--------|
| **ZIP** | `mvp_sprint/flosc_8_0_0/zip-files/flosc.zip` |
| **SHA-256** | `50999f2da6cba73b1cecf985ad0d3cbffbe32259e6f5e89ecf5d5f3d21d7f89f` |
| **Git** | `c7ee9214ef9398bdccb62a3f65df66def611c2e1` on GitHub dainiswmichel/flosc (payment architecture 89c2741 + freeze record) |
| **Version** | `8.0.0` (plugin header, `FLOSC_VERSION`, Stable tag) |
| **Top-level** | single `flosc/` |
| **Archive** | 224 ZIP entries |
| **PHP** | 128 files — all pass `php -l` |
| **Docs** | full `flosc_documentation/` + `admin/docs/` |
| **Prohibited absent** | `vendor/`, Composer project files, `.git/`, worknotes, `sample-data/`, `create-sample-data.php`, `Repository URI`, `8.0.8` |

**Rule:** any code change after this ZIP invalidates the SHA. Rebuild only with `flosc/build-dist-zip.sh`.

**Superseded — do not upload or re-score as current:** SHA `3dcb3742…662a` and any earlier submission ZIP.

---

## Why this ZIP should pass (claims — verify, do not trust)

### PAY-01 — no access without settled payment
1. Paid offer + omitted `provider` → `provider_required` (no free grant).
2. Paid offer + `method=free` → `not_free`.
3. Free grant only for free/active offers; deterministic free txn id for idempotency.
4. ClickBank redirect initiation returns `pending` / `success: false` — **no grant**.
5. Stripe `client_secret` / `requires_action` / non-`succeeded` — **no grant** via `process_purchase` or `complete_purchase`.
6. `FLOSC_Sale_Manager::is_payment_settled()` is fail-closed.

### PAY-02 — transaction bound to offer + user
1. `claim_transaction_fulfillment(provider, txn, user, offer)` — atomic claim.
2. Same txn, different offer → `transaction_reuse`.
3. Same txn, same user+offer → idempotent `already`.
4. Stripe complete/webhook: PI metadata `offer_id` + `user_id` required; only `succeeded`.
5. PayPal capture: order `custom_id.offer_id` must match; then fulfill claim.
6. PayPal subscription activate: fulfill via claimed subscription id.
7. ClickBank: grant only after INS decrypt or legacy cverify; receipt claimed once.

### ClickBank (current seller docs)
- Live: `https://VENDOR.pay.clickbank.net/?cbitems=ITEM` (`vtid` tracking only).
- INS v6+/v8: encrypted JSON `notification` + `iv`, AES-256-CBC per ClickBank PHP sample.
- Legacy form IPN still accepted with cverify.
- Redirect alone never unlocks.

### Email
- Profile password confirmation and ClickBank welcome: **no plaintext password** (reset/login guidance only).

### Primary files
```
includes/sale/class-sale-manager.php
includes/sale/class-flosc-checkout-rest.php
includes/sale/providers/class-stripe-provider.php
includes/sale/providers/class-clickbank-provider.php
includes/sale/providers/class-paypal-provider.php
flosc.php  (PayPal capture/activate; profile email)
readme.txt (ClickBank external services)
```

---

## Evidence protocol (minimum for GO)

### Phase 0 — freeze
- [ ] `shasum -a 256` equals `50999f2da6cba73b1cecf985ad0d3cbffbe32259e6f5e89ecf5d5f3d21d7f89f`
- [ ] single top-level `flosc/`
- [ ] version `8.0.0` in three locations
- [ ] docs present; prohibited material absent

### Phase 1 — automated
- [ ] Plugin Check **Plugin repo**: **0 errors** on this exact ZIP
- [ ] PHP lint 128/128
- [ ] JS syntax on shipped `assets/js/*`
- [ ] Prohibited API + package hygiene greps

### Phase 2 — payment spot-proofs (blockers)
- [ ] Paid + empty provider → no grant
- [ ] Paid + method=free → no grant
- [ ] Free active offer → grant once
- [ ] ClickBank redirect → entitlement unchanged
- [ ] Stripe incomplete states → no grant
- [ ] Stripe wrong offer_id → offer_mismatch
- [ ] Stripe correct metadata → grant once; replay → already
- [ ] One PI cannot unlock second offer
- [ ] PayPal wrong offer_id → offer_mismatch
- [ ] PayPal bound capture → grant once
- [ ] ClickBank INS/IPN path: valid → once; tamper → reject
- [ ] No plaintext password in those email code paths (grep + mail capture if available)

### Phase 3 — smoke
- [ ] Clean install + activate with WP_DEBUG
- [ ] Settings / Flows / Sales load
- [ ] Public chat shell loads

---

## Go / No-go

**GO only if:** SHA matches; PAY-01/PAY-02 proofs pass; Plugin Check Plugin-repo **0 errors**; no plaintext password emails in those paths; ClickBank is correct or clearly non-default.

**NO-GO if:** any grant without settled payment; any offer substitution with a valid processor txn; any Plugin-repo error on this ZIP.

---

## Operator notes

- Prefer line-level citations (file + function).
- Separate **true blockers** from **stale findings** already fixed on prior commits.
- Facts only. No "looks fine" without path proof.
- If Plugin Check runtime is unavailable in your sandbox, state that and still require the operator to run Plugin-repo on this SHA before upload.

---

## Commitment from the ship side

This package is frozen for review. It is the payment-architecture corrective ZIP. It is better than the packages that wasted your time. It is submitted with the expectation that it **will pass** the gates above when those gates are executed against **this SHA only**. If it fails, the failure must be against this code — not against an old archive.
