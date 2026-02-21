# DA1NI5 BuddyBoss Child Theme — Changelog

**Theme:** DA1NI5 BuddyBoss Child Theme  
**Parent:** BuddyBoss Theme v2.18.0  
**Date format:** Michel Date Stamp Innovation (`YYYY-MMm-DDd`)

---

## 2026-02m-18d — v1.0.1

### Fixed
- **Header gap** — Sticky header was offset ~37px down and right for all visitors. Root cause: BuddyBoss sets `position: fixed` on `.sticky-header .site-header` but never declares `top: 0; left: 0`, so the header inherited body padding offset. Added explicit `top: 0 !important; left: 0 !important` and `body { padding: 0 !important; margin: 0 !important; }` in `custom.css`.
- **Blog excerpt & "Read more..." link** — BuddyBoss uses `the_excerpt()` on blog list, which ignores `<!--more-->` tags and showed no "Read more" link (previously handled by Advanced Excerpt plugin, which was deleted because it broke search). Added three filters in `functions.php`: (1) excerpt length set to 30 words, (2) `[...]` replaced with "Read more..." link, (3) posts with `<!--more-->` tag use content before the tag as the excerpt.
- **BuddyBoss search results** — Activity search returned incorrect counts and broken excerpts. Root cause: Advanced Excerpt plugin was corrupting BP search output. Plugin removed; added defensive PHP filters in `functions.php` as safety net.
- **Activity count mismatch** — Search showed inflated activity counts. Added `dainis_exclude_activity_comments_from_search()` to exclude `activity_comment` types from search SQL.
- **"Read more" link on search** — Stray "Read more" links appeared on search result pages. Added CSS fallback to hide them.

### Changed
- **Theme renamed** from "BuddyBoss Child Theme" to "DA1NI5 BuddyBoss Child Theme" v1.0.1 in `style.css`.
- **Font override** — Atkinson Hyperlegible Next applied globally at 111% base size.
- **Blog thumbnails** — 150x150 floated right with text wrap on list pages; CSS Grid layout on single posts.
- **da1ni5.com** — Added as parked/alias domain in cPanel, sharing document root with dainis.net. Nameservers pointed to ChemiCloud (`ns1/ns2/ns3.serverhostgroup.com`). Pending DNS propagation.

### TODO
- [ ] **Geolocated "Read more" translations** — Use GeoLite2-Country.mmdb (available at `/var/lib/GeoIP/`) + Mailster's bundled MaxMind PHP reader to detect visitor country and serve localized "Read more" text. Priority: Latvia (`LV`) → "Lasīt vairāk..." — just for fun.
- [ ] Update theme screenshot (`screenshot.png`) — currently using BuddyBoss default
- [ ] FLOSC avatar test
- [ ] Delete old child theme folder from server if still present
