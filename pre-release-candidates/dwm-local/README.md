# FLOSC — dwm-local v184.2

**Agent:** Dainis W. Michel
**Plugin version:** 8.0.0
**Internal Iteration:** v184.2
**Github HashID:** a1d9c1310b46777bb269d95425d093f5b88e0e01
**Parent:** `a1d9c1310b46777bb269d95425d093f5b88e0e01` (v184, on main)

Installable tree. `flosc-by-dwm-local/flosc` is the canonical local plugin at that commit, plus the uncommitted `starter-packs/da1-catalog-sales/flow_ivr.md`. Tests and dev files are not in this tree. This candidate is not promoted and not deployed.

## Artifact

```text
pre-release-candidates/dwm-local/flosc.zip
sha256  273029a7548c50a7adba64aaed4578003d6899a080af0977901f2041d1773448
size    2,838,949
files   242
entries 282
root    flosc/
tests/  0
version 8.0.0
Requires at least: 7.1
Requires PHP: 7.4
Author: Dainis W. Michel
```

## Delta from v184

- `flosc.php` — Internal Iteration v184.2. Github HashID is the v184 commit. Author remains Dainis W. Michel.
- `starter-packs/da1-catalog-sales/flow_ivr.md` — local sales copy. Visitor welcome states the visiting tier. The login pill carries "Guests guess less, Members know more!" and the 10000-token registration line. That token line is not in the welcome card. `guest_token_grant` is 10000.

## Verification

- `php tests/check-da1-chat-routing.php` on the canonical tree returned `DA1 chat routing: ok`.
- PHPCS was not run.
- Plugin Check was not run.
