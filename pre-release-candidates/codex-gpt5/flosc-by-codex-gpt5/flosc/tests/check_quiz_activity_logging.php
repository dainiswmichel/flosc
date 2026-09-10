<?php
/**
 * Regression contract for quiz activity logging and user-filtered sessions.
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

$root = dirname(__DIR__);
$fail = 0;

function flosc_quiz_log_check($condition, $label) {
    global $fail;
    if (!$condition) {
        $fail++;
    }
    printf("%s %s\n", $condition ? 'ok  ' : 'FAIL', $label);
}

$framework = (string) file_get_contents($root . '/flosc.php');
$client = (string) file_get_contents($root . '/assets/js/flosc-app.js');
$logger_source = (string) file_get_contents($root . '/includes/logging/class-flosc-chat-logger.php');
$screen = (string) file_get_contents($root . '/admin/chat-logs.php');
$settings = (string) file_get_contents($root . '/admin/settings.php');
$first_party_auth = (string) file_get_contents(
    $root . '/includes/first-party-authentication/class-flosc-first-party-authentication.php'
);

flosc_quiz_log_check(
    strpos(
        $framework,
        "add_action('flosc_quiz_completed', [\$this, 'flosc_log_quiz_completion'], 30, 2)"
    ) !== false,
    'quiz logging observes the canonical completion action after lesson assignment'
);
flosc_quiz_log_check(
    strpos($framework, "'response_source'  => 'quiz_completion'") !== false
        && preg_match('/flosc_find_turn\(\s*\$turn_id\s*\)/', $framework),
    'quiz activity has an explicit source and retry deduplication'
);
flosc_quiz_log_check(
    substr_count($client, "flowId: this.config?.flowId || ''") >= 2
        && substr_count($client, 'journeyId: this.getJourneyId()') >= 2,
    'both native quiz storage paths retain flow and journey context'
);
flosc_quiz_log_check(
    strpos($client, 'const quizResultStore = this.storeQuizResults(scorePercent, quizCompletion);') !== false
        && strpos($client, "quizResultStore.then(() => this.authFetch(this.config.apiUrl + '/store-score'") !== false
        && strpos($client, 'completion_id: quizCompletion.id') !== false,
    'the dual multiple-choice writes are serialized around one completion id'
);
flosc_quiz_log_check(
    strpos($framework, "array( 'quiz_completion', \$user_id, \$completion_id )") !== false
        && substr_count($framework, "'completion_id' => \$completion_id") >= 2,
    'server-side retry identity is shared by both multiple-choice endpoints'
);
flosc_quiz_log_check(
    strpos($framework, "'flow_id'              => \$flow_id") !== false
        && strpos($framework, "'journey_id'           => \$journey_id") !== false,
    'email and SSO quiz restore carries context into the completion action'
);
flosc_quiz_log_check(
    substr_count($framework, "'completion_id' => FLOSC_Chat_Logger::flosc_sanitize_turn_id") >= 1
        && strpos($framework, "'flow_id'       => FLOSC_Chat_Logger::flosc_journey_flow_stem") !== false
        && strpos($framework, "'journey_id'    => FLOSC_Chat_Logger::flosc_sanitize_journey_id") !== false
        && strpos($first_party_auth, "'completion_id' => FLOSC_Chat_Logger::flosc_sanitize_turn_id") !== false
        && strpos($first_party_auth, "'flow_id'       => FLOSC_Chat_Logger::flosc_journey_flow_stem") !== false
        && strpos($first_party_auth, "'journey_id'    => FLOSC_Chat_Logger::flosc_sanitize_journey_id") !== false,
    'both signed-cookie fallbacks retain completion, flow, and journey context'
);
flosc_quiz_log_check(
    strpos(
        $screen,
        'flosc_get_sessions($flosc_current_flow_id, 800, $flosc_session_scope, $flosc_selected_user_id)'
    ) !== false,
    'the grouped Sessions view applies its displayed user filter'
);
flosc_quiz_log_check(
    strpos($settings, "['flosc_user_id', 'logview', 'session_scope']") !== false,
    'switching flows preserves an active Chat Logs investigation'
);

// Exercise the grouping behavior: selecting a signed-in user keeps that
// person's pre-login rows from the same journey and drops other conversations.
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        return trim(strip_tags((string) $value));
    }
}
if (!function_exists('absint')) {
    function absint($value) {
        return abs((int) $value);
    }
}
if (!function_exists('wp_cache_get')) {
    function wp_cache_get() {
        return false;
    }
}
if (!function_exists('wp_cache_set')) {
    function wp_cache_set() {
        return true;
    }
}
if (!function_exists('get_option')) {
    function get_option($name, $default = false) {
        return $default;
    }
}

require_once $root . '/includes/logging/class-flosc-chat-logger.php';

class FLOSC_Quiz_Log_Test_DB {
    public $rows = array();
    public $last_query = '';

    public function prepare($query) {
        return $query;
    }

    public function get_results($query) {
        $this->last_query = (string) $query;
        if (strpos($this->last_query, 'journey_id IN') === false) {
            return $this->rows;
        }

        return array_values(array_filter($this->rows, static function($row) {
            return intval($row['user_id'] ?? 0) === 42
                || (
                    intval($row['user_id'] ?? 0) === 0
                    && (string) ($row['journey_id'] ?? '') === 'journey-aaaaaaaa'
                );
        }));
    }
}

class FLOSC_Quiz_Log_Test_Logger extends FLOSC_Chat_Logger {
    public function __construct() {
    }

    public function flosc_ensure_table() {
    }
}

$wpdb = new FLOSC_Quiz_Log_Test_DB();
$wpdb->rows = array(
    array(
        'id' => 5,
        'timestamp' => '2026-09-10 12:05:00',
        'flow_id' => 'lesaep_com_ivr',
        'journey_id' => 'journey-aaaaaaaa',
        'session_id' => 9,
        'user_id' => 99,
        'visitor_ip' => '',
        'user_message' => 'Different signed-in user',
        'ai_response' => 'Must not appear in User #42 results.',
        'response_source' => 'ai',
    ),
    array(
        'id' => 4,
        'timestamp' => '2026-09-10 12:04:00',
        'flow_id' => 'other_ivr',
        'journey_id' => 'journey-bbbbbbbb',
        'session_id' => 8,
        'user_id' => 99,
        'visitor_ip' => '',
        'user_message' => 'Unrelated',
        'ai_response' => 'Unrelated response',
        'response_source' => 'ai',
    ),
    array(
        'id' => 3,
        'timestamp' => '2026-09-10 12:03:00',
        'flow_id' => 'lesaep_com_ivr',
        'journey_id' => 'journey-aaaaaaaa',
        'session_id' => 0,
        'user_id' => 42,
        'visitor_ip' => '',
        'user_message' => '',
        'ai_response' => 'Quiz completed · lesaep_ipa_audio_quiz · Score: 72%',
        'response_source' => 'quiz_completion',
    ),
    array(
        'id' => 2,
        'timestamp' => '2026-09-10 12:02:00',
        'flow_id' => 'lesaep_com_ivr',
        'journey_id' => 'journey-aaaaaaaa',
        'session_id' => 7,
        'user_id' => 0,
        'visitor_ip' => 'visitor-hash',
        'user_message' => 'Start my quiz',
        'ai_response' => 'Opening the quiz.',
        'response_source' => 'ivr',
    ),
);

$logger = new FLOSC_Quiz_Log_Test_Logger();
$table_property = new ReflectionProperty(FLOSC_Chat_Logger::class, 'table_name');
$table_property->setAccessible(true);
$table_property->setValue($logger, 'wp_flosc_chat_logs');

$sessions = $logger->flosc_get_sessions('lesaep_com_ivr', 800, 'active', 42);
flosc_quiz_log_check(
    strpos($wpdb->last_query, 'journey_id IN') !== false,
    'user filtering happens in SQL before the row limit'
);
flosc_quiz_log_check(
    strpos($wpdb->last_query, 'user_id = 0') !== false,
    'journey context admits anonymous rows but not another signed-in user'
);
flosc_quiz_log_check(count($sessions) === 1, 'user filter keeps only matching conversations');
flosc_quiz_log_check(
    isset($sessions[0]['rows']) && count($sessions[0]['rows']) === 2,
    'the matching journey retains its anonymous pre-login row'
);
flosc_quiz_log_check(
    isset($sessions[0]['turns']) && $sessions[0]['turns'] === 1,
    'quiz completion is displayed as activity rather than counted as a chat message'
);

echo $fail ? "\n{$fail} FAILURES\n" : "\nQuiz activity logging contract passed\n";
exit($fail ? 1 : 0);
