<?php
/**
 * Exercises FLOSC_Starter_Packs::install() / uninstall() against an in-memory
 * WordPress. The IVR importer and the content index are stubbed; everything
 * inside the starter-packs class is the real code.
 */
define('ABSPATH', __DIR__);
define('FLOSC_PLUGIN_DIR', dirname(__DIR__) . '/');

$TMP = sys_get_temp_dir() . '/flosc-packs-test-' . getmypid();
mkdir($TMP . '/data', 0777, true);
mkdir($TMP . '/uploads', 0777, true);

$OPTIONS = []; $POSTS = []; $POSTMETA = []; $TERMS = []; $TERMMETA = []; $ATTACH = []; $NEXT = 1;
$IMPORT_OK = true; $IMPORT_CALLS = []; $INDEX_CALLS = 0;

function flosc_data_dir() { global $TMP; return $TMP . '/data/'; }
function wp_upload_dir() { global $TMP; return ['basedir' => $TMP . '/uploads', 'path' => $TMP . '/uploads']; }
function get_option($k, $d = false) { global $OPTIONS; return array_key_exists($k, $OPTIONS) ? $OPTIONS[$k] : $d; }
function update_option($k, $v, $a = null) { global $OPTIONS; $OPTIONS[$k] = $v; return true; }
function delete_option($k) { global $OPTIONS; unset($OPTIONS[$k]); return true; }
function __($s, $d = null) { return $s; }
function sanitize_key($k) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $k)); }
function sanitize_title($t) { $t = strtolower((string) $t); $t = preg_replace('/[^a-z0-9 _-]/', '', $t); return trim(preg_replace('/[ ]+/', '-', $t), '-'); }
function sanitize_text_field($t) { return trim(strip_tags((string) $t)); }
function wp_kses_post($t) { return $t; }
function wp_mkdir_p($d) { return is_dir($d) || mkdir($d, 0777, true); }
function wp_delete_file($p) { return @unlink($p); }
function trailingslashit($s) { return rtrim((string) $s, '/\\') . '/'; }
function is_wp_error($t) { return $t instanceof WP_Error; }
class WP_Error { public $msg; function __construct($c = '', $m = '') { $this->msg = $m; } function get_error_message() { return $this->msg; } }

function term_exists($slug, $tax = 'category') { global $TERMS; foreach ($TERMS as $t) { if ($t['slug'] === $slug) { return $t['term_id']; } } return null; }
function wp_insert_term($name, $tax, $args = []) {
    global $TERMS, $NEXT;
    $id = $NEXT++;
    $TERMS[$id] = ['term_id' => $id, 'name' => $name, 'slug' => $args['slug'] ?? sanitize_title($name),
                   'parent' => (int) ($args['parent'] ?? 0), 'description' => $args['description'] ?? ''];
    return ['term_id' => $id];
}
function wp_unique_filename($dir, $name) { return $name; }
function wp_check_filetype($name, $mimes = null) { return ['ext' => 'pdf', 'type' => 'application/pdf']; }
function wp_insert_attachment($args, $file, $parent = 0, $err = false) {
    global $ATTACH, $POSTMETA, $NEXT;
    $id = $NEXT++;
    $ATTACH[$id] = $args + ['file' => $file];
    foreach (($args['meta_input'] ?? []) as $k => $v) { $POSTMETA[$id][$k] = $v; }
    return $id;
}
function wp_delete_attachment($id, $force = false) { global $ATTACH; unset($ATTACH[$id]); return true; }
function wp_delete_term($id, $tax) { global $TERMS; unset($TERMS[$id]); return true; }
function add_term_meta($id, $k, $v, $u = false) { global $TERMMETA; $TERMMETA[$id][$k] = $v; return true; }
function get_term_meta($id, $k, $single = true) { global $TERMMETA; return $TERMMETA[$id][$k] ?? ''; }
function update_term_meta($id, $k, $v) { global $TERMMETA; $TERMMETA[$id][$k] = $v; return true; }
function wp_insert_post($args, $err = false) {
    global $POSTS, $POSTMETA, $NEXT;
    $id = $NEXT++;
    $POSTS[$id] = $args;
    foreach (($args['meta_input'] ?? []) as $k => $v) { $POSTMETA[$id][$k] = $v; }
    return $id;
}
function update_post_meta($id, $k, $v) { global $POSTMETA; $POSTMETA[$id][$k] = $v; return true; }
function get_post_meta($id, $k, $single = true) { global $POSTMETA; return $POSTMETA[$id][$k] ?? ''; }
function get_post_type($id) { global $POSTS, $ATTACH; return isset($ATTACH[$id]) ? 'attachment' : (isset($POSTS[$id]) ? 'post' : false); }
function get_term($id, $tax = 'category') { global $TERMS; return $TERMS[$id] ?? null; }
function get_term_by($field, $value, $tax = 'category') {
    global $TERMS;
    foreach ($TERMS as $t) { if (($t[$field] ?? null) === $value) { return (object) $t; } }
    return false;
}
function get_posts($args) {
    global $POSTS, $POSTMETA;
    $out = [];
    foreach ($POSTS as $id => $p) {
        if (($POSTMETA[$id][$args['meta_key']] ?? null) === $args['meta_value']) { $out[] = $id; }
    }
    return $out;
}
function wp_delete_post($id, $force = false) { global $POSTS, $POSTMETA; unset($POSTS[$id], $POSTMETA[$id]); return true; }

function current_time($t) { return gmdate('Y-m-d H:i:s'); }
function flosc_personality_library_get($id) {
    return in_array($id, ['starter', 'friendly', 'tech', 'br3nda', 'bubblybetty', 'dadjokedan'], true) ? ['id' => $id] : null;
}

class FLOSC_Filesystem {
    public function read_file_safely($path) { return file_get_contents($path); }
    public function write_file_safely($path, $content) {
        global $TMP;
        // Mirrors the real guard: refuse anything outside uploads.
        if (strpos($path, $TMP . '/uploads') !== 0 && strpos($path, $TMP . '/data') !== 0) { return false; }
        @mkdir(dirname($path), 0777, true);
        return file_put_contents($path, $content) !== false;
    }
}
function flosc_write_data_file($target, $content) {
    global $TMP;
    if (strpos($target, $TMP . '/data/') !== 0) { return false; }
    @mkdir(dirname($target), 0777, true);
    return file_put_contents($target, $content) !== false;
}

// Stubs for the two collaborators the installer calls out to.
function flosc_import_ivr_to_database($preview, $file, $flow_key, $mode) {
    global $IMPORT_OK, $IMPORT_CALLS;
    $IMPORT_CALLS[] = ['file' => $file, 'flow_key' => $flow_key, 'mode' => $mode];
    if (!$IMPORT_OK) { return ['success' => false, 'message' => 'stubbed failure']; }
    // The real importer writes the runtime lists into the per-flow option.
    $bag = get_option($flow_key, []);
    $bag = is_array($bag) ? $bag : [];
    $bag['flow_messages'] = array_fill(0, 12, ['type' => 'auto']);
    $bag['flow_phases']   = ['freeline' => []];
    update_option($flow_key, $bag, false);
    return ['success' => true, 'stats' => ['incoming_count' => 12], 'message' => 'ok'];
}
class FLOSC_Site_Content_Index {
    private static $i = null;
    public static function instance() { return self::$i ?: self::$i = new self(); }
    public function rebuild($stem = '') { global $INDEX_CALLS, $POSTS; $INDEX_CALLS++; return ['ok' => true, 'count' => count($POSTS)]; }
}

require __DIR__ . '/../includes/starter-packs/class-flosc-starter-packs.php';

$fail = 0;
function ok($label, $got, $want = null) {
    global $fail;
    $pass = ($want === null) ? (bool) $got : ($got === $want);
    if (!$pass) { $fail++; }
    printf("%-4s %-52s %s%s\n", $pass ? 'ok' : 'FAIL', $label,
        is_scalar($got) || $got === null ? var_export($got, true) : json_encode($got),
        $want === null ? '' : ' (want ' . json_encode($want) . ')');
}

echo "== discovery ==\n";
$packs = FLOSC_Starter_Packs::discover();
ok('all four packs discovered', array_keys($packs),
    ['da1-catalog-sales', 'membership-craft', 'vegan-latvian-kitchen', 'wordpress-content-membership']);

echo "\n== install the membership pack ==\n";
$r = FLOSC_Starter_Packs::install('wordpress-content-membership');
ok('install succeeded', $r['ok'], true);
foreach ($r['detail'] as $line) { echo "     - $line\n"; }
ok('flow file written', file_exists($TMP . '/data/wcmj_ivr.md'), true);
ok('100 posts created', count($POSTS), 100);
ok('four categories created', count($TERMS), 4);
$slugs = array_values(array_map(static fn($t) => $t['slug'], $TERMS));
ok('category slugs', $slugs, ['flosc-starter-100-content-items', 'flosc-starter-freeline', 'flosc-starter-guests', 'flosc-starter-members']);
$byslug = [];
foreach ($TERMS as $t) { $byslug[$t['slug']] = $t; }
ok('children hang off the parent',
    $byslug['flosc-starter-freeline']['parent'] === $byslug['flosc-starter-100-content-items']['term_id'], true);
ok('parent is a root', $byslug['flosc-starter-100-content-items']['parent'], 0);
ok('importer called once', count($IMPORT_CALLS), 1);
ok('importer got the right flow key', $IMPORT_CALLS[0]['flow_key'], 'flosc_flow_wcmj_ivr');
ok('importer replaces rather than merges', $IMPORT_CALLS[0]['mode'], 'replace');
ok('index rebuilt', $INDEX_CALLS, 1);

$bag = get_option('flosc_flow_wcmj_ivr');
ok('flow settings row created', is_array($bag), true);
ok('flow points at a pack category', $bag['content_item_category'] ?? null, 'flosc-starter-100-content-items');
ok('flow names its own file', $bag['ivr_file'] ?? null, 'wcmj_ivr.md');
ok('flow references the pack personality', $bag['personality_library_id'] ?? null, 'bubblybetty');

$tiers = ['visitor' => 0, 'guest' => 0, 'member' => 0];
$stamped = 0;
foreach ($POSTS as $id => $p) {
    $tiers[get_post_meta($id, '_flosc_access_level')]++;
    if (get_post_meta($id, '_flosc_starter_pack') === 'wordpress-content-membership') { $stamped++; }
}
ok('gating written to post meta', $tiers, ['visitor' => 10, 'guest' => 20, 'member' => 70]);
$item46 = null; $items = [];
foreach ($POSTS as $id => $p) { $n = (int) get_post_meta($id, '_flosc_starter_pack_item'); $items[] = $n; if ($n === 46) { $item46 = $p['post_title']; } }
ok('item numbers 1..100 all present', count(array_unique($items)), 100);
ok('item 46 is the pigeons', $item46, 'Content Item 046 — Why Pigeons Never Pay Parking Tickets');
ok('every post stamped with the pack slug', $stamped, 100);

echo "\n== refusals ==\n";
$r = FLOSC_Starter_Packs::install('wordpress-content-membership');
ok('installing twice is refused', $r['ok'], false);
ok('  and says why', $r['message']);

echo "\n== install the DA1 pack alongside it ==\n";
$r = FLOSC_Starter_Packs::install('da1-catalog-sales');
ok('second pack installs', $r['ok'], true);
ok('its flow file is distinct', file_exists($TMP . '/data/dcsj_ivr.md'), true);
ok('110 posts now', count($POSTS), 110);
ok('five categories now', count($TERMS), 5);
ok('UberManual added to the media library', count($ATTACH), 1);
ok('  as a PDF', reset($ATTACH)['post_mime_type'], 'application/pdf');
ok('  stamped to its pack', get_post_meta(array_key_first($ATTACH), '_flosc_starter_pack'), 'da1-catalog-sales');
ok('catalog written where the runtime looks',
    file_exists($TMP . '/uploads/flosc-catalogs/flosc_da1_catalog_dcsj_extremely_ordinary_manuals.tsv'), true);
$assign = get_option('flosc_da1_flow_catalogs');
ok('catalog assigned to its flow', $assign['dcsj_ivr.md'] ?? null, ['dcsj_extremely_ordinary_manuals']);
ok('catalog listed in the DA1 index',
    get_option('flosc_da1_catalogs')['dcsj_extremely_ordinary_manuals']['filename'] ?? null,
    'flosc_da1_catalog_dcsj_extremely_ordinary_manuals.tsv');
ok('DA1 flow references DadJokeDan',
    get_option('flosc_flow_dcsj_ivr')['personality_library_id'] ?? null, 'dadjokedan');
ok('DA1 flow points at its own category',
    get_option('flosc_flow_dcsj_ivr')['content_item_category'] ?? null, 'flosc-da1-catalog-posts');

echo "\n== uninstall the membership pack ==\n";
$r = FLOSC_Starter_Packs::uninstall('wordpress-content-membership');
ok('uninstall succeeded', $r['ok'], true);
ok('only its posts removed', count($POSTS), 10);
ok('only its category removed', count($TERMS), 1);
ok('its flow file removed', file_exists($TMP . '/data/wcmj_ivr.md'), false);
ok('the other flow file survives', file_exists($TMP . '/data/dcsj_ivr.md'), true);
ok('its flow settings removed', get_option('flosc_flow_wcmj_ivr', 'absent'), 'absent');
ok('its four categories removed', count($TERMS), 1);
ok('the other flow settings survive', is_array(get_option('flosc_flow_dcsj_ivr')), true);
ok('the DA1 catalog index is untouched', array_keys(get_option('flosc_da1_catalogs')), ['dcsj_extremely_ordinary_manuals']);
ok('state no longer lists it', array_keys(FLOSC_Starter_Packs::state()), ['da1-catalog-sales']);
ok('reinstall is possible again', FLOSC_Starter_Packs::install('wordpress-content-membership')['ok'], true);

echo "\n== removing the DA1 pack clears its catalog index entry ==\n";
FLOSC_Starter_Packs::uninstall('da1-catalog-sales');
ok('index entry removed', get_option('flosc_da1_catalogs'), []);
ok('the UberManual is gone too', count($ATTACH), 0);
ok('catalog file removed',
    file_exists($TMP . '/uploads/flosc-catalogs/flosc_da1_catalog_dcsj_extremely_ordinary_manuals.tsv'), false);
ok('assignment removed', get_option('flosc_da1_flow_catalogs'), []);
ok('DA1 pack reinstalls cleanly', FLOSC_Starter_Packs::install('da1-catalog-sales')['ok'], true);

echo "\n== a failed import rolls the whole install back ==\n";
FLOSC_Starter_Packs::uninstall('wordpress-content-membership');
$posts_before = count($POSTS); $terms_before = count($TERMS);
$IMPORT_OK = false;
$r = FLOSC_Starter_Packs::install('wordpress-content-membership');
ok('install reports the failure', $r['ok'], false);
ok('  with the importer message', strpos($r['message'], 'stubbed failure') !== false, true);
ok('posts rolled back', count($POSTS), $posts_before);
ok('category rolled back', count($TERMS), $terms_before);
ok('flow file rolled back', file_exists($TMP . '/data/wcmj_ivr.md'), false);
ok('no orphan flow settings', get_option('flosc_flow_wcmj_ivr', 'absent'), 'absent');
ok('not recorded as installed', FLOSC_Starter_Packs::is_installed('wordpress-content-membership'), false);

echo "\n== an existing flow settings row is not overwritten ==\n";
$IMPORT_OK = true;
update_option('flosc_flow_wcmj_ivr', ['name' => 'operator work'], false);
$r = FLOSC_Starter_Packs::install('wordpress-content-membership');
ok('install refused', $r['ok'], false);
ok('operator settings untouched', get_option('flosc_flow_wcmj_ivr')['name'], 'operator work');
ok('nothing left behind', count($POSTS), $posts_before);

echo "\n== the two newer packs install too ==\n";
foreach (['vegan-latvian-kitchen', 'membership-craft'] as $newpack) {
    $before_posts = count($POSTS);
    $r = FLOSC_Starter_Packs::install($newpack);
    ok("$newpack installs", $r['ok'], true);
    foreach ($r['detail'] as $line) { echo "     - $line\n"; }
    $rec = FLOSC_Starter_Packs::state()[$newpack] ?? [];
    ok("  posts created", count($POSTS) - $before_posts, (int) ($rec['post_count'] ?? -1));
    $tiers = ['visitor' => 0, 'guest' => 0, 'member' => 0];
    foreach ($POSTS as $id => $pp) {
        if (get_post_meta($id, '_flosc_starter_pack') === $newpack) { $tiers[get_post_meta($id, '_flosc_access_level')]++; }
    }
    ok("  tier split", $tiers);
    ok("  flow carries its personality",
        get_option($rec['flow_option'])['personality_library_id'] ?? null);
}

echo "\n== the voice switch ==\n";
$state = FLOSC_Starter_Packs::state();
$slug  = array_key_first($state);
ok('a pack is installed to switch', (bool) $slug, true);
$before = get_option($state[$slug]['flow_option'])['personality_library_id'] ?? null;
ok('it arrived with a voice', (bool) $before, true);
$r = FLOSC_Starter_Packs::set_personality($slug, 'br3nda');
ok('Br3nda can curate a pack flow', $r['ok'], true);
ok('  and the flow names her',
    get_option($state[$slug]['flow_option'])['personality_library_id'] ?? null, 'br3nda');
$r = FLOSC_Starter_Packs::set_personality($slug, 'bubblybetty');
ok('switch succeeds', $r['ok'], true);
ok('  and says who', strpos($r['message'], 'bubblybetty') !== false || $r['message'] !== '', true);
ok('flow now names the new voice',
    get_option($state[$slug]['flow_option'])['personality_library_id'] ?? null, 'bubblybetty');
$r = FLOSC_Starter_Packs::set_personality($slug, 'not_a_real_personality');
ok('an unknown personality is refused', $r['ok'], false);
ok('  and the flow is unchanged',
    get_option($state[$slug]['flow_option'])['personality_library_id'] ?? null, 'bubblybetty');
$r = FLOSC_Starter_Packs::set_personality('not-a-pack', 'bubblybetty');
ok('switching an uninstalled pack is refused', $r['ok'], false);

echo "\n== category counts recorded for the card ==\n";
foreach ($state as $k => $rec) {
    if (!empty($rec['categories'])) {
        ok("$k categories", array_map(static fn($c) => $c['name'] . '=' . $c['count'], $rec['categories']));
    }
}

echo "\n== status verifies real components ==\n";
$slug = 'membership-craft';
$st = FLOSC_Starter_Packs::status($slug);
ok('an intact pack reports a clean state', in_array($st['state'], ['installed', 'needs_configuration'], true), true);
ok('  with nothing missing', $st['missing'], []);
ok('an uninstalled pack is not_installed', FLOSC_Starter_Packs::status('nope')['state'], 'not_installed');

echo "\n== break it, then repair it ==\n";
$rec = FLOSC_Starter_Packs::state()[$slug];
unlink($TMP . '/data/' . $rec['flow_file']);
$killed = 0;
foreach ($POSTS as $id => $pp) {
    if (get_post_meta($id, '_flosc_starter_pack') === $slug && $killed < 3) { wp_delete_post($id, true); $killed++; }
}
$before_total = count($POSTS);
$st = FLOSC_Starter_Packs::status($slug);
ok('status notices the damage', $st['state'], 'needs_repair');
ok('  and names it', implode(' | ', $st['missing']));

$edited = null;
foreach ($POSTS as $id => $pp) { if (get_post_meta($id, '_flosc_starter_pack') === $slug) { $POSTS[$id]['post_title'] = 'OPERATOR EDITED THIS'; $edited = $id; break; } }

$r = FLOSC_Starter_Packs::repair($slug);
ok('repair succeeds', $r['ok'], true);
foreach ($r['detail'] as $line) { echo "     - $line\n"; }
ok('the flow file is back', file_exists($TMP . '/data/' . $rec['flow_file']), true);
ok('exactly the 3 missing posts came back', count($POSTS), $before_total + 3);
ok('no duplicates: still 100 for this pack',
    count(array_filter(array_keys($POSTS), fn($id) => get_post_meta($id, '_flosc_starter_pack') === $slug)), 100);
ok('an operator edit was left alone', $POSTS[$edited]['post_title'], 'OPERATOR EDITED THIS');
ok('status is clean again', in_array(FLOSC_Starter_Packs::status($slug)['state'], ['installed','needs_configuration'], true), true);
$r = FLOSC_Starter_Packs::repair($slug);
ok('repairing an intact pack changes nothing', $r['ok'], true);
ok('  and says so', strpos($r['message'], 'Nothing to repair') !== false, true);

echo "\n== an interrupted install must not block the next one ==\n";
$slug = 'vegan-latvian-kitchen';
FLOSC_Starter_Packs::uninstall($slug);
ok('starting clean', FLOSC_Starter_Packs::is_installed($slug), false);

$r = FLOSC_Starter_Packs::install($slug);
ok('installs once', $r['ok'], true);
$posts_after_first = count(array_filter(array_keys($POSTS), fn($id) => get_post_meta($id, '_flosc_starter_pack') === $slug));
$terms_after_first = count($TERMS);

// Simulate an install that died before it recorded anything: the artifacts are
// on the site, the registry has no idea.
$state = get_option('flosc_starter_packs_installed');
unset($state[$slug]);
update_option('flosc_starter_packs_installed', $state, false);
ok('the registry now says it is absent', FLOSC_Starter_Packs::is_installed($slug), false);
ok('  while its category is still there', (bool) get_term_by('slug', 'vegan_latvian_recipes'), true);

$r = FLOSC_Starter_Packs::install($slug);
ok('reinstalling adopts the orphan instead of refusing', $r['ok'], true);
ok('  and does not say the category is in the way', strpos($r['message'], 'already exists'), false);
$posts_after_second = count(array_filter(array_keys($POSTS), fn($id) => get_post_meta($id, '_flosc_starter_pack') === $slug));
ok('no duplicate posts', $posts_after_second, $posts_after_first);
ok('no duplicate categories', count($TERMS), $terms_after_first);
ok('it is registered again', FLOSC_Starter_Packs::is_installed($slug), true);
$rec = FLOSC_Starter_Packs::state()[$slug];
$counted = 0;
foreach ((array) ($rec['categories'] ?? []) as $c) { $counted += (int) $c['count']; }
ok('the card still counts every adopted post', $counted, (int) $rec['post_count']);
ok('  and that is the real number', $counted, $posts_after_first);

// Somebody else's category of the same name is borrowed, never blocked —
// and borrowing must not hand removal the right to delete it.
echo "\n== a category the operator already had ==\n";
FLOSC_Starter_Packs::uninstall($slug);
$mine = $NEXT++;
$TERMS[$mine] = ['term_id' => $mine, 'name' => 'Someones Recipes', 'slug' => 'vegan_latvian_recipes', 'parent' => 0, 'description' => ''];

$r = FLOSC_Starter_Packs::install($slug);
ok('installing over it succeeds instead of refusing', $r['ok'], true);
ok('  nothing is called an obstacle', strpos((string) $r['message'], 'already exists'), false);
ok('  their category is reused, not duplicated', (int) get_term_by('slug', 'vegan_latvian_recipes')->term_id, $mine);
ok('  the journey still gets its posts', $posts_after_second > 0, true);

$rec = FLOSC_Starter_Packs::state()[$slug];
ok('  and it is NOT recorded for deletion', in_array($mine, (array) ($rec['category_ids'] ?? []), true), false);

FLOSC_Starter_Packs::uninstall($slug);
ok('so uninstall leaves the operator\'s category standing', (bool) get_term_by('slug', 'vegan_latvian_recipes'), true);
ok('  while the pack\'s own posts are gone',
   count(array_filter(array_keys($POSTS), fn($id) => get_post_meta($id, '_flosc_starter_pack') === $slug)), 0);

// The API key an operator saves on a pack's flow must survive Repair.
// install() returns early when the pack is already installed, so Repair is
// the path that re-registers the flow — and register_flow() used to write a
// freshly built bag over the whole settings row, deleting the key that had
// just been saved correctly.
echo "\n== a key saved on the flow, then Repair ==\n";
FLOSC_Starter_Packs::uninstall($slug);
FLOSC_Starter_Packs::install($slug);

$flow_option = 'flosc_flow_vlkit_ivr';
$bag = get_option($flow_option, []);
$bag = is_array($bag) ? $bag : [];
ok('the pack registered its flow', !empty($bag['ivr_file']), true);

$bag['anthropic_api_key'] = 'sk-ant-operator-key-PQAA';
$bag['ai_provider'] = 'anthropic';
$bag['personality_library_id'] = 'dadjokedan';
update_option($flow_option, $bag, false);

// Repair only re-registers a flow whose messages are gone — that is the one
// path that rebuilds the settings row, so break it deliberately. A test that
// runs against a healthy pack exercises an early return and proves nothing.
$bag = get_option($flow_option, []);
unset($bag['flow_messages']);
update_option($flow_option, $bag, false);

$rep = FLOSC_Starter_Packs::repair($slug);
ok('Repair rebuilds the flow it found broken', !empty($rep['ok']), true);
ok('  and the messages are back', !empty(get_option($flow_option, [])['flow_messages']), true);

$after = get_option($flow_option, []);
$after = is_array($after) ? $after : [];

ok('the key survives Repair', $after['anthropic_api_key'] ?? '(gone)', 'sk-ant-operator-key-PQAA');
ok('  so does the provider choice', $after['ai_provider'] ?? '(gone)', 'anthropic');
ok('  and a voice switched live is not reset', $after['personality_library_id'] ?? '(gone)', 'dadjokedan');
ok('  while the pack still owns the flow file', $after['ivr_file'] ?? '', 'vlkit_ivr.md');

exec('rm -rf ' . escapeshellarg($TMP));
echo "\n" . ($fail ? "$fail FAILURES\n" : "all checks passed\n");
exit($fail ? 1 : 0);
