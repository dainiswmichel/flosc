# Standing up a WordPress plugin test environment

Instructions to my future self. Written after building one for FLOSC 8.0.0 on
2026-09-23 and finding three bugs with it that every static check had missed.

The reason this document exists: for nine review rounds this plugin was verified
entirely by reading source and running linters. Every one of those passes said
clean. The first time WordPress actually booted and served the plugin over HTTP,
three real bugs surfaced in under an hour. Static analysis cannot find a bug that
only exists when code runs.

---

## What this gets you

A WordPress install you can drive over real HTTP, with the plugin active,
`WP_DEBUG` on, an authenticated admin session, and the official wordpress.org
review tooling pointed at it. No MySQL, no Docker, no network access to
wordpress.org.

---

## 1. Get WordPress without wordpress.org

In a sandboxed container, `wordpress.org` egress is usually blocked, so
`wp-cli core download` and the plugin directory both fail. GitHub mirrors work:

```
git clone --depth 1 --branch 7.1.2 https://github.com/WordPress/WordPress.git /tmp/wp
```

The `WordPress/WordPress` repo is the built release, not the develop tree — it is
what you want. Tags match release numbers exactly.

Check what you actually got rather than trusting the tag:

```
grep -m1 "wp_version =" /tmp/wp/wp-includes/version.php
```

## 2. Database: SQLite, not MySQL

Standing up MySQL costs more than it is worth. WordPress's own SQLite
integration plugin is a drop-in:

```
git clone --depth 1 https://github.com/WordPress/sqlite-database-integration.git \
  /tmp/wp/wp-content/plugins/sqlite-database-integration
cp /tmp/wp/wp-content/plugins/sqlite-database-integration/db.copy /tmp/wp/wp-content/db.php
```

`db.php` in `wp-content/` is a drop-in WordPress loads before anything else. The
`DB_*` constants in `wp-config.php` still have to exist but are ignored — leave
`DB_USER` and `DB_PASSWORD` empty.

## 3. wp-config.php

Copy `wp-config-sample.php` and set, at minimum:

```
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', '/tmp/wp-debug.log' );
define( 'WP_DEBUG_DISPLAY', false );
```

`WP_DEBUG_DISPLAY` false matters. With it on, notices splice into the HTML and
every subsequent assertion about page content is measuring your own debug output.
Send them to a file and read the file.

Replace the salt block with anything — eight random strings are fine for a
throwaway install, and fetching the real ones needs wordpress.org.

## 4. Serve it

```
php -S 127.0.0.1:8099 -t /tmp/wp
```

Run it in the background. The built-in server is single-threaded: a request that
triggers a second request to itself will deadlock. If a page hangs forever, that
is usually why, not a bug in the plugin.

It also dies quietly when the container is reclaimed. Before trusting any
result, confirm it is up:

```
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8099/
```

## 5. Install WordPress and get an admin session

Run the installer over HTTP, then log in with curl and keep the cookie jar.
Every later request reuses it:

```
curl -s -c /tmp/cj.txt -d "log=admin&pwd=adminpass123&wp-submit=Log+In" \
  http://127.0.0.1:8099/wp-login.php
```

Verify the session is real before relying on it — request an admin page and
check the byte count, not just the status code. WordPress returns 200 for a
login redirect page too:

```
curl -s -b /tmp/cj.txt -o /tmp/probe.html \
  -w "%{http_code} %{size_download}\n" \
  "http://127.0.0.1:8099/wp-admin/"
```

A few hundred bytes means you are logged out. A hundred kilobytes means you
are in.

## 6. Install the plugin from its built zip, not from a symlink

Symlinking the source tree in is convenient and wrong. It tests files the zip
will never contain and misses files the zip omits. Build the distribution
archive and unzip that:

```
unzip -q /path/to/flosc.zip -d /tmp/wp/wp-content/plugins/
```

Then confirm the installed tree matches the candidate for every shipped file:

```
diff -rq /path/to/candidate/flosc /tmp/wp/wp-content/plugins/flosc
```

Anything under "Only in candidate" should be a dev-only file the packager
excludes on purpose. Anything that "differ"s is a packaging bug.

Activate it and watch the debug log:

```
: > /tmp/wp-debug.log
```

Activate over HTTP, then read the log. An empty log after activation is the
first real signal the plugin is sound.

## 7. Official review tooling

The wordpress.org Plugin Check plugin carries the real rulesets:

```
git clone --depth 1 https://github.com/WordPress/plugin-check.git \
  /tmp/wp/wp-content/plugins/plugin-check
composer install -d /tmp/wp/wp-content/plugins/plugin-check
```

Its rulesets live at `phpcs-rulesets/plugin-review.xml` and
`phpcs-rulesets/plugin-check.ruleset.xml`. **PHPCS cannot find the sniffs those
rulesets reference unless `installed_paths` is set**, and when it cannot, it
reports nothing rather than erroring usefully:

```
P=/tmp/wp/wp-content/plugins/plugin-check
php $P/vendor/bin/phpcs \
  --runtime-set installed_paths "$P/vendor/wp-coding-standards/wpcs,$P/phpcs-sniffs" \
  --standard=$P/phpcs-rulesets/plugin-review.xml \
  --extensions=php --report=summary -s flosc
```

Run that from `wp-content/plugins`, with the plugin directory name as the target.

---

## Traps that cost me hours

**A scan of zero files reports zero errors.** A `phpcs.xml.dist` with an
`<exclude-pattern>` matching the directory you are scanning produces a clean
run in ~90ms that means nothing. Always print the file count and fail the run
if it is implausibly low. `--report=summary` shows it at the bottom; read it
every time.

**Negative-control every ruleset.** Before believing a pass, reintroduce a
defect you know the ruleset catches and confirm it fails. I put `7.0.4` back
into `readme.txt` and confirmed `plugin_readme` flagged it. A ruleset that has
never failed has never been shown to work.

**Verify the artifact, not the source tree.** The zip is what gets submitted.
I verified the source tree for seven consecutive versions while the committed
zip stayed frozen at an older build and still contained the exact string the
reviewer had rejected. Unpack the zip and grep inside it.

**Scrape nonces from rendered pages; do not mint them.** `wp_create_nonce()`
from CLI produces a nonce bound to a different session than your cookie jar, so
it fails verification and you conclude the handler is secure when you never
reached it. Fetch the admin page over HTTP and pull the nonce out of the HTML
the browser would receive.

**`filter_input(INPUT_GET, ...)` reads the original request, not `$_GET`.**
Code paths behind it cannot be driven by setting `$_GET` in a CLI harness. They
require real HTTP. This is a large blind spot in any CLI-based test script.

**Tab templates cannot send headers.** If a template is included while the admin
page is already streaming, `header()` calls are discarded and any body bytes it
writes splice into the surrounding HTML. Download handlers must run on an early
hook such as `admin_init`, before output starts.

**WPCS cannot follow a control across a function boundary.** A nonce check
inside a helper called by the handler satisfies a human reviewer and not the
sniff; a nonce check anywhere in the enclosing scope satisfies the sniff even
when it runs *after* the read. The sniff measures scope, the reviewer reads
execution order. Where they disagree, the reviewer is right.

---

## Test the refusals, not just the happy path

For each security-relevant entry point, drive it over HTTP in every state and
record the response code and body:

- valid nonce, authorized user
- wrong nonce
- no nonce at all
- valid nonce, unauthorized user
- hostile payloads in every field, with a bad nonce

That last row is the one that matters. If a handler refuses a bad nonce but
only *after* reading and dispatching on the body, the refusal is decoration.
Check the option or table afterwards to confirm nothing was written.

A capability check answers who is asking. A nonce answers whether they meant
to. Both, in that order, before anything else happens.
