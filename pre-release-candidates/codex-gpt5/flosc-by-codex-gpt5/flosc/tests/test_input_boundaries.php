<?php
/**
 * Behavioral checks for structured request and authored-file sanitizers.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	private $message;

	public function __construct( $code, $message ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
		$this->message = $message;
	}

	public function get_error_message() {
		return $this->message;
	}
}

function __( $text ) {
	return $text;
}

function add_action() {}
function add_filter() {}
function add_shortcode() {}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function absint( $value ) {
	return abs( (int) $value );
}

function sanitize_text_field( $value ) {
	$value = wp_check_invalid_utf8( (string) $value );
	$value = strip_tags( $value );
	$value = preg_replace( '/[\r\n\t ]+/', ' ', $value );
	return trim( is_string( $value ) ? $value : '' );
}

function sanitize_textarea_field( $value ) {
	$value = wp_check_invalid_utf8( (string) $value );
	$value = strip_tags( $value );
	$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value );
	return is_string( $value ) ? $value : '';
}

function wp_check_invalid_utf8( $value ) {
	return preg_match( '//u', (string) $value ) ? (string) $value : '';
}

function wp_json_encode( $value ) {
	return json_encode( $value );
}

require dirname( __DIR__ ) . '/includes/flosc-content-sanitizers.php';
require dirname( __DIR__ ) . '/includes/class-quiz-manager.php';
require dirname( __DIR__ ) . '/includes/flosc-personality-library.php';

$flosc_test_failures = 0;

function flosc_boundary_ok( $label, $actual, $expected ) {
	global $flosc_test_failures;
	$pass = $actual === $expected;
	if ( ! $pass ) {
		++$flosc_test_failures;
	}
	printf(
		"%s %-62s %s%s\n",
		$pass ? 'ok  ' : 'FAIL',
		$label,
		var_export( $actual, true ),
		$pass ? '' : ' (want ' . var_export( $expected, true ) . ')'
	);
}

echo "Markdown file boundary\n";
$markdown = "# Heading\r\n\tBody\x01 <?php echo 'no';";
$clean_markdown = flosc_sanitize_ivr_markdown( $markdown, 500000 );
flosc_boundary_ok( 'Markdown headings and tabs survive', strpos( $clean_markdown, "# Heading\n\tBody" ) === 0, true );
flosc_boundary_ok( 'control bytes are removed', strpos( $clean_markdown, "\x01" ), false );
flosc_boundary_ok( 'PHP opening tags are removed', stripos( $clean_markdown, '<?' ), false );
flosc_boundary_ok( 'null bytes reject the complete write', is_wp_error( flosc_sanitize_ivr_markdown( "bad\0body" ) ), true );
flosc_boundary_ok( 'stored-size limit is enforced after normalization', is_wp_error( flosc_sanitize_ivr_markdown( '12345', 4 ) ), true );

echo "\nAudio-container boundary\n";
flosc_boundary_ok( 'WebM EBML signature is accepted', flosc_uploaded_audio_container_matches_format( "\x1A\x45\xDF\xA3body", 'webm' ), true );
flosc_boundary_ok( 'Ogg signature is accepted', flosc_uploaded_audio_container_matches_format( 'OggSbody', 'ogg' ), true );
flosc_boundary_ok( 'MP4 ftyp box is accepted', flosc_uploaded_audio_container_matches_format( "\0\0\0\x18ftypisom", 'mp4' ), true );
flosc_boundary_ok( 'an extension cannot disguise arbitrary bytes', flosc_uploaded_audio_container_matches_format( '<?php payload', 'webm' ), false );
flosc_boundary_ok( 'a valid container cannot be relabelled', flosc_uploaded_audio_container_matches_format( 'OggSbody', 'webm' ), false );

echo "\nExternal quiz boundary\n";
$quiz = FLOSC_Quiz_Manager::sanitize_score_data(
	array(
		'score'           => '87.5',
		'correct_items'   => array( 'q1', '<b>q2</b>' ),
		'incorrect_items' => array( 'q3' ),
		'answers'         => array( 'q<script>1</script>' => "first\n<script>alert(1)</script>" ),
		'time_spent'      => '42',
		'passed'          => true,
	)
);
flosc_boundary_ok( 'valid payload remains an array', is_array( $quiz ), true );
flosc_boundary_ok( 'numeric score is validated and typed', $quiz['score'], 87.5 );
flosc_boundary_ok( 'question identifiers are field-sanitized', $quiz['correct_items'], array( 'q1', 'q2' ) );
flosc_boundary_ok( 'nested keys are field-sanitized', array_keys( $quiz['answers'] ), array( 'q1' ) );
flosc_boundary_ok( 'nested strings contain no markup', $quiz['answers']['q1'], "first\nalert(1)" );
flosc_boundary_ok( 'documented integer fields are typed', $quiz['time_spent'], 42 );
flosc_boundary_ok( 'boolean extension fields retain their type', $quiz['passed'], true );
flosc_boundary_ok( 'scores below zero are rejected', FLOSC_Quiz_Manager::sanitize_score_data( array( 'score' => -1 ) ), false );
flosc_boundary_ok( 'scores above 100 are rejected', FLOSC_Quiz_Manager::sanitize_score_data( array( 'score' => 101 ) ), false );
flosc_boundary_ok( 'structured question IDs are rejected', FLOSC_Quiz_Manager::sanitize_score_data( array( 'score' => 50, 'correct_items' => array( array( 'bad' ) ) ) ), false );
flosc_boundary_ok( 'object values are rejected', FLOSC_Quiz_Manager::sanitize_score_data( array( 'score' => 50, 'payload' => (object) array( 'bad' => true ) ) ), false );

$deep = 'value';
for ( $i = 0; $i < 10; ++$i ) {
	$deep = array( 'level' => $deep );
}
flosc_boundary_ok( 'excessive nesting rejects the complete payload', FLOSC_Quiz_Manager::sanitize_score_data( array( 'score' => 50, 'deep' => $deep ) ), false );
flosc_boundary_ok( 'oversized identifier lists are rejected', FLOSC_Quiz_Manager::sanitize_score_data( array( 'score' => 50, 'correct_items' => array_fill( 0, 501, 'q' ) ) ), false );
flosc_boundary_ok( 'oversized payloads are rejected', FLOSC_Quiz_Manager::sanitize_score_data( array( 'score' => 50, 'notes' => str_repeat( 'x', 200001 ) ) ), false );

echo "\nPersonality-workshop boundary\n";
$workshop = flosc_sanitize_personality_workshop(
	wp_json_encode(
		array(
			'written_at' => 'changes every save',
			'derived'    => array( 'provider' => 'copy' ),
			'identity'   => array(
				'<b>voice</b>' => "# Bright\x01\nvoice",
				'enabled'      => true,
				'gain'         => 0.75,
			),
		)
	)
);
$workshop = json_decode( $workshop, true );
flosc_boundary_ok( 'derived export fields are not persisted', isset( $workshop['derived'] ), false );
flosc_boundary_ok( 'volatile export fields are not persisted', isset( $workshop['written_at'] ), false );
flosc_boundary_ok( 'nested workshop keys are sanitized', isset( $workshop['identity']['voice'] ), true );
flosc_boundary_ok( 'Markdown survives nested workshop sanitization', $workshop['identity']['voice'], "# Bright\nvoice" );
flosc_boundary_ok( 'workshop booleans retain their type', $workshop['identity']['enabled'], true );
flosc_boundary_ok( 'workshop numbers retain their type', $workshop['identity']['gain'], 0.75 );

$deep_workshop = 'value';
for ( $i = 0; $i < 34; ++$i ) {
	$deep_workshop = array( 'level' => $deep_workshop );
}
flosc_boundary_ok( 'excessively deep workshops reject the complete document', flosc_sanitize_personality_workshop( wp_json_encode( $deep_workshop ) ), '' );

echo "\nRequest-to-storage wiring\n";
$main_source   = (string) file_get_contents( dirname( __DIR__ ) . '/flosc.php' );
$bridge_source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-bridge-data-manager.php' );
flosc_boundary_ok( 'knowledge uploads cross the Markdown boundary before write', strpos( $main_source, 'flosc_sanitize_ivr_markdown($uploaded_body, 500000)' ) !== false, true );
flosc_boundary_ok( 'knowledge edits use the same Markdown boundary as uploads', substr_count( $main_source, 'flosc_sanitize_ivr_markdown(' ) >= 2, true );
flosc_boundary_ok( 'quiz stashes sanitize phrase results before transient storage', strpos( $main_source, "'phraseResults' => \$phrase_results" ) !== false, true );
flosc_boundary_ok( 'pre-login result lists cross the numeric-ID boundary', substr_count( $main_source, 'flosc_sanitize_quiz_id_list($request->get_param(' ) >= 2, true );
flosc_boundary_ok( 'target IPA is sanitized before metadata storage', strpos( $main_source, "'target_ipa' => \$target_ipa" ) !== false, true );
flosc_boundary_ok( 'visitor audio bytes match their requested container', strpos( $main_source, 'flosc_uploaded_audio_container_matches_format($uploaded_audio, $format)' ) !== false, true );
flosc_boundary_ok( 'public offer tracking state is allowlisted', strpos( $main_source, "['shown', 'dismissed', 'purchased']" ) !== false, true );
flosc_boundary_ok( 'direct external-quiz hooks sanitize before bridge storage', strpos( $bridge_source, 'FLOSC_Quiz_Manager::sanitize_score_data($score_data)' ) !== false, true );

echo $flosc_test_failures ? "\n{$flosc_test_failures} FAILURES\n" : "\nInput-boundary checks passed\n";
exit( $flosc_test_failures ? 1 : 0 );
