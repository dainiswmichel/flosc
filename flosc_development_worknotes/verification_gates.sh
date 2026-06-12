#!/usr/bin/env bash
# FLOSC verification gates — run from the plugin root.
# Each gate prints a header, its output, and the expectation so the result
# is unambiguous even when output is piped or captured.

set -euo pipefail
cd "$(dirname "$0")/.."

echo "=== gate 1: no deepgram references in runtime files ==="
grep -RIn -i "deepgram" flosc.php readme.txt admin includes assets sample-data ai_configuration_files 2>/dev/null || true
echo "expect: no output"
echo ""

echo "=== gate 2: FLOSC_PLUGIN_DIR . 'ai_configuration_files' — only read-resolver and legacy migration ==="
grep -n "FLOSC_PLUGIN_DIR . 'ai_configuration_files" flosc.php admin/*.php includes/*.php 2>/dev/null || true
echo "expect: read-resolver and legacy one-time migration reads only"
echo ""

echo "=== gate 3: wp_set_auth_cookie — only the five sanctioned sites ==="
grep -n "wp_set_auth_cookie" flosc.php
echo "expect: five sites — flosc_issue_post_purchase_session, handle_login_token (magic-link), cross-domain sync, login-token continuation, post-wp_set_password. Locate by symbol, not line number."

echo "=== gate 3b: the binding token is registered server-side (not minted in the browser) ==="
grep -n "flosc_checkout_binding_create" flosc.php
echo "expect: the function definition AND at least one real caller (handle_checkout_binding). Definition-only means instant login is non-functional."
echo ""

echo "=== gate 4: every REST route uses a named check_* callback or __return_true with comment ==="
grep -n "permission_callback" flosc.php | grep -v "check_"
echo "expect: no output (all routes use a named check_* callback)"
echo ""

echo "=== gate 5: no echo '<style' in admin or includes ==="
grep -RIn "echo '<style" admin includes 2>/dev/null || true
echo "expect: no output"
echo ""

echo "=== gate 6: no broad wp_unslash superglobal snapshots in flow-edit or offers ==="
grep -n "wp_unslash(\$_GET)\|wp_unslash(\$_POST)" admin/flow-edit.php admin/offers.php 2>/dev/null || true
echo "expect: no output"
echo ""

echo "=== gate 7: PHP syntax check on all runtime PHP files ==="
for f in flosc.php uninstall.php admin/*.php includes/*.php includes/**/*.php; do
    [ -f "$f" ] && php -l "$f" | grep -v "No syntax errors" || true
done
echo "expect: no output"
echo ""

echo "=== all gates complete ==="
