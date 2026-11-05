<?php
/**
 * DadJokesDan rename migration preserves administrator-owned personality data.
 *
 * @package FLOSC
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$root    = dirname( __DIR__ );
$options = array();
$fail    = 0;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}

/** WordPress action stub. */
function add_action() {
}

/** WordPress filter stub. */
function add_filter() {
}

/**
 * WordPress option-read stub.
 *
 * @param string $key      Option key.
 * @param mixed  $fallback Fallback value.
 * @return mixed
 */
function get_option( $key, $fallback = false ) {
	global $options;
	return array_key_exists( $key, $options ) ? $options[ $key ] : $fallback;
}

/**
 * WordPress option-write stub.
 *
 * @param string $key      Option key.
 * @param mixed  $value    Option value.
 * @param mixed  $autoload Autoload preference.
 * @return bool
 */
function update_option( $key, $value, $autoload = null ) {
	global $options;
	if ( null !== $autoload && ! is_bool( $autoload ) ) {
		return false;
	}
	$options[ $key ] = $value;
	return true;
}

/**
 * Escape-output stub for fixed CLI test labels.
 *
 * @param string $value Output value.
 * @return string
 */
function esc_html( $value ) {
	return $value;
}

/**
 * Record one assertion.
 *
 * @param string $label    Assertion label.
 * @param mixed  $actual   Actual value.
 * @param mixed  $expected Expected value.
 * @return void
 */
function ok( $label, $actual, $expected ) {
	global $fail;
	$passed = $actual === $expected;
	if ( ! $passed ) {
		++$fail;
	}
	echo esc_html( ( $passed ? 'ok   ' : 'FAIL ' ) . $label ) . "\n";
}

require_once $root . '/includes/flosc-personality-library.php';

$options['flosc_personality_library'] = array(
	'dadjokedan' => array(
		'id'                   => 'dadjokedan',
		'label'                => 'Dad Joke Dan',
		'ai_personality_name'  => 'DadJokeDan',
		'ai_personality_role'  => 'Administrator-customized role',
		'ai_base_prompt'       => "# Name: Dad Joke Dan\nYou are DadJokeDan.\nCustom instruction.",
		'workshop_json'        => '{"soul":{"label":"Dad Joke Dan","name":"DadJokeDan"},"custom":true}',
		'profile_version'      => '4',
		'profile_hash'         => 'legacy-hash',
		'profile_modified_gmt' => '2026-01-01 00:00:00',
	),
	'custom'     => array(
		'id'                  => 'custom',
		'label'               => 'Dad Joke Dan',
		'ai_personality_name' => 'DadJokeDan',
	),
);
$custom_before                               = $options['flosc_personality_library']['custom'];

flosc_personality_library_migrate_dadjokesdan_name();
$dad    = $options['flosc_personality_library']['dadjokedan'];
$custom = $options['flosc_personality_library']['custom'];

ok( 'legacy library label renamed', $dad['label'], 'Dad Jokes Dan' );
ok( 'legacy runtime name renamed', $dad['ai_personality_name'], 'DadJokesDan' );
ok( 'prompt identity renamed', strpos( $dad['ai_base_prompt'], 'DadJokeDan' ), false );
ok( 'workshop identity renamed', strpos( $dad['workshop_json'], 'DadJokeDan' ), false );
ok( 'administrator role preserved', $dad['ai_personality_role'], 'Administrator-customized role' );
ok( 'administrator prompt text preserved', strpos( $dad['ai_base_prompt'], 'Custom instruction.' ) !== false, true );
ok( 'profile version advanced once', $dad['profile_version'], '5' );
ok( 'profile fingerprint recalculated', $dad['profile_hash'], flosc_personality_fingerprint( $dad['workshop_json'], $dad['ai_base_prompt'] ) );
ok( 'another personality is untouched', $custom, $custom_before );
ok( 'migration marker recorded', $options['flosc_dadjokesdan_name_migrated'], '1' );

$after_first_run = $options;
flosc_personality_library_migrate_dadjokesdan_name();
ok( 'second run is idempotent', $options, $after_first_run );

echo esc_html( $fail ? "\n{$fail} FAILURES\n" : "\nDadJokesDan migration: all checks passed\n" );
exit( $fail ? 1 : 0 );
