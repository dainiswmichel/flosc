<?php
/**
 * Flow-tab field boundaries and target-bound nonce actions.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return string
	 */
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return string
	 */
	function sanitize_key( $value ) {
		return strtolower( (string) preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return string
	 */
	function sanitize_title( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return trim( (string) preg_replace( '/[^a-z0-9]+/', '-', $value ), '-' );
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return string
	 */
	function sanitize_file_name( $value ) {
		return (string) preg_replace( '/[^A-Za-z0-9._-]/', '', basename( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_hex_color' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return string|null
	 */
	function sanitize_hex_color( $value ) {
		return preg_match( '/^#[0-9a-f]{6}$/i', (string) $value ) ? (string) $value : null;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return string
	 */
	function esc_url_raw( $value ) {
		return (string) $value;
	}
}

require_once dirname( __DIR__ ) . '/includes/flosc-admin.php';

/**
 * Expose the private flow-data builder.
 */
class FLOSC_Flow_Data_Test_Double {
	use FLOSC_Admin_Trait;

	/**
	 * @param array $post   Submitted fields.
	 * @param bool  $is_new Whether this creates a new flow.
	 * @return array
	 */
	public function build_flow_data( $post, $is_new ) {
		return $this->flosc_flow_record_data( $post, $is_new );
	}
}

$flosc_fail = 0;

/**
 * @param string $label Assertion label.
 * @param bool   $pass  Whether it passed.
 * @return void
 */
function flosc_v149_ok( $label, $pass ) {
	global $flosc_fail;
	if ( ! $pass ) {
		++$flosc_fail;
	}
	fwrite( STDOUT, sprintf( "%s %s\n", $pass ? 'ok  ' : 'FAIL', $label ) );
}

$flosc_builder  = new FLOSC_Flow_Data_Test_Double();
$flosc_existing = array(
	'slug'           => 'kept-slug',
	'custom_domain'  => 'kept.example',
	'status'         => 'active',
	'identity'       => array( 'name' => 'Kept name' ),
	'ivr_file'       => 'old_ivr.md',
	'wp_category_id' => 42,
	'quiz_type'      => 'kept-quiz',
);

$flosc_ivr_update = $flosc_builder->build_flow_data( array( 'ivr_file' => 'new_ivr.md' ), false );
$flosc_ivr_merged = array_merge( $flosc_existing, $flosc_ivr_update );
flosc_v149_ok( 'IVR form writes only ivr_file', array( 'ivr_file' ) === array_keys( $flosc_ivr_update ) );
flosc_v149_ok(
	'IVR save preserves identity, slug, status, category and quiz',
	$flosc_existing['identity'] === $flosc_ivr_merged['identity']
	&& $flosc_existing['slug'] === $flosc_ivr_merged['slug']
	&& $flosc_existing['status'] === $flosc_ivr_merged['status']
	&& $flosc_existing['wp_category_id'] === $flosc_ivr_merged['wp_category_id']
	&& $flosc_existing['quiz_type'] === $flosc_ivr_merged['quiz_type']
);

$flosc_content_update = $flosc_builder->build_flow_data(
	array(
		'wp_category' => '7',
		'quiz_type'   => 'assessment',
	),
	false
);
$flosc_content_merged = array_merge( $flosc_existing, $flosc_content_update );
flosc_v149_ok( 'content form writes only category and quiz', array( 'wp_category_id', 'quiz_type' ) === array_keys( $flosc_content_update ) );
flosc_v149_ok(
	'content save preserves identity, slug, status and IVR',
	$flosc_existing['identity'] === $flosc_content_merged['identity']
	&& $flosc_existing['slug'] === $flosc_content_merged['slug']
	&& $flosc_existing['status'] === $flosc_content_merged['status']
	&& $flosc_existing['ivr_file'] === $flosc_content_merged['ivr_file']
);

$flosc_identity_update = $flosc_builder->build_flow_data(
	array(
		'flow_slug'      => 'Changed Slug',
		'flow_domain'    => 'changed.example',
		'flow_status'    => 'active',
		'floscflow_name' => 'Changed name',
	),
	false
);
flosc_v149_ok( 'identity form writes only the identity-owned group', array( 'slug', 'custom_domain', 'status', 'identity' ) === array_keys( $flosc_identity_update ) );

$flosc_new = $flosc_builder->build_flow_data( array(), true );
flosc_v149_ok( 'new flow receives all seven field groups', array( 'slug', 'custom_domain', 'status', 'identity', 'ivr_file', 'wp_category_id', 'quiz_type' ) === array_keys( $flosc_new ) );

$flosc_kb_screen  = (string) file_get_contents( dirname( __DIR__ ) . '/admin/knowledge-base.php' );
$flosc_framework  = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-flosc-framework.php' );
$flosc_magic      = (string) file_get_contents( dirname( __DIR__ ) . '/includes/magic-link/class-flosc-magic-link-trait.php' );
$flosc_terms      = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-flosc-site-content-index.php' );

flosc_v149_ok( 'KB toggle URL carries static and target-bound nonces', false !== strpos( $flosc_kb_screen, "'flosc_kb_toggle_' . \$flosc_kid . '_' . \$flosc_kbf" ) && false !== strpos( $flosc_kb_screen, "'flosc_kb_toggle_target_nonce'" ) );
flosc_v149_ok( 'KB delete URL carries static and target-bound nonces', false !== strpos( $flosc_kb_screen, "'flosc_kb_delete_' . \$flosc_kid . '_' . \$flosc_kbf" ) && false !== strpos( $flosc_kb_screen, "'flosc_kb_delete_target_nonce'" ) );
flosc_v149_ok( 'KB handlers verify the exact file target', false !== strpos( $flosc_framework, "check_admin_referer( 'flosc_kb_toggle_' . \$kb_id . '_' . \$file, 'flosc_kb_toggle_target_nonce' )" ) && false !== strpos( $flosc_framework, "check_admin_referer( 'flosc_kb_delete_' . \$kb_id . '_' . \$file, 'flosc_kb_delete_target_nonce' )" ) );
flosc_v149_ok( 'activation URL and handler bind the same user id', false !== strpos( $flosc_magic, "'flosc_activate_email_' . (int) \$user->ID" ) && false !== strpos( $flosc_magic, "check_admin_referer( 'flosc_activate_email_' . \$user_id, 'flosc_activate_email_target_nonce' )" ) );
flosc_v149_ok( 'term nonce and values reject non-scalar input', false !== strpos( $flosc_terms, "! is_string( \$_POST['flosc_term_vgm_nonce'] )" ) && false !== strpos( $flosc_terms, '! is_string( $_POST[ $field ] )' ) );

if ( $flosc_fail > 0 ) {
	fwrite( STDERR, "{$flosc_fail} failing assertion(s)\n" );
	exit( 1 );
}

fwrite( STDOUT, "check_nonce_scope_and_flow_preservation v149.grok: ok\n" );
exit( 0 );
