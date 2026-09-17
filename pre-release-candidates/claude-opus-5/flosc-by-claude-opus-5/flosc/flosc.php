<?php
/**
 * Plugin Name: FLOSC
 * Plugin URI: https://flosc.ai
 * Description: (F)reeline --> (L)ogin --> (O)ffer --> (S)ale --> (C)ontent: try-before-you-buy WordPress journeys.
 * Version: 8.0.0
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Author: Dainis W. Michel
 * Author URI: https://dainis.net
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: flosc
 * Domain Path: /languages
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'FLOSC_VERSION', '8.0.0' );

/*
 * The personality builder versions independently of the plugin. It ships here
 * as the FLOSC edition and will ship standalone at da1.fm as the DA1 edition —
 * one builder, one number, the edition named separately. A version with a
 * letter on the front is not a version anything can compare.
 */
define( 'FLOSC_DA1_BUILDER_VERSION', '3.1.2' );
// v8.0.1: Runtime debug mode override from Administration tab.
// Modes: inherit (follow WP_DEBUG), on (force), off (disable).
$flosc_debug_mode = function_exists( 'get_option' ) ? get_option( 'flosc_debug_mode', 'inherit' ) : 'inherit';
if ( 'on' === $flosc_debug_mode ) {
	$flosc_debug_enabled = true;
} elseif ( 'off' === $flosc_debug_mode ) {
	$flosc_debug_enabled = false;
} else {
	$flosc_debug_enabled = defined( 'WP_DEBUG' ) && WP_DEBUG;
}
define( 'FLOSC_DEBUG', $flosc_debug_enabled );
define( 'FLOSC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLOSC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( ! function_exists( 'flosc_log' ) ) {
	/**
	 * Debug logger: writes under uploads/flosc-logs when FLOSC_DEBUG is on (no error_log).
	 *
	 * @param mixed $msg Message or structure to log.
	 */
	function flosc_log( $msg ) {
		if ( ! defined( 'FLOSC_DEBUG' ) || ! FLOSC_DEBUG ) {
			return;
		}
		$line = is_scalar( $msg ) ? (string) $msg : wp_json_encode( $msg );
		if ( ! is_string( $line ) || '' === $line ) {
			return;
		}
		if ( ! class_exists( 'FLOSC_Filesystem', false ) ) {
			$fs_file = FLOSC_PLUGIN_DIR . 'includes/filesystem/class-flosc-filesystem.php';
			if ( is_readable( $fs_file ) ) {
				require_once $fs_file;
			}
		}
		if ( ! class_exists( 'FLOSC_Filesystem' ) ) {
			return;
		}
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return;
		}
		$dir = trailingslashit( $uploads['basedir'] ) . 'flosc-logs';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$path  = $dir . '/debug.log';
		$entry = '[' . gmdate( 'c' ) . '] ' . $line . "\n";
		$fs    = new FLOSC_Filesystem();
		$fs->protect_uploads_dir_with_htaccess( $dir );
		$existing = $fs->read_file_safely( $path );
		if ( ! is_string( $existing ) ) {
			$existing = '';
		}
		if ( strlen( $existing ) > 524288 ) {
			$existing = substr( $existing, -262144 );
		}
		$fs->write_file_safely( $path, $existing . $entry );
	}
}


// Domain: filesystem helpers then path helpers (write gate needs FLOSC_Filesystem).
require_once FLOSC_PLUGIN_DIR . 'includes/filesystem/class-flosc-filesystem.php';
require_once FLOSC_PLUGIN_DIR . 'includes/filesystem/flosc-data-paths.php';
require_once FLOSC_PLUGIN_DIR . 'includes/flosc-request.php';
require_once FLOSC_PLUGIN_DIR . 'includes/flosc-available-providers.php';
require_once FLOSC_PLUGIN_DIR . 'includes/class-flosc-wp-ai-client.php';
require_once FLOSC_PLUGIN_DIR . 'includes/ai/flosc-model-catalog.php';
require_once FLOSC_PLUGIN_DIR . 'includes/ai/flosc-provider-profiles.php';
require_once FLOSC_PLUGIN_DIR . 'includes/ai/flosc-model-parameters.php';
require_once FLOSC_PLUGIN_DIR . 'includes/ai/flosc-provider-keys.php';
require_once FLOSC_PLUGIN_DIR . 'includes/ai/flosc-provider-identity.php';
require_once FLOSC_PLUGIN_DIR . 'includes/flosc-personality-library.php';
require_once FLOSC_PLUGIN_DIR . 'includes/flosc-knowledge-bases.php';

register_activation_hook( __FILE__, 'flosc_activation_flush' );

/**
 * Mark permalinks as needing a flush, on activation.
 *
 * The flush itself is deferred: FLOSC's rewrite rules are registered on init,
 * and activation runs before that, so flushing here would rebuild the rules
 * without them. The flag is read on the next init instead.
 *
 * @since 1.2.9
 */
function flosc_activation_flush() {
	// Schedule flush for next init (after rewrite rules are registered).
	update_option( 'flosc_needs_flush', true );
	update_option( 'flosc_last_permalink_flush', flosc_michel_timestamp_global() );
}

add_action( 'admin_init', 'flosc_version_flush_check' );

/**
 * Flush permalinks once after the plugin version changes.
 *
 * Activation does not run on an update, so a new version whose rewrite rules
 * differ would otherwise serve 404s until someone visited Settings > Permalinks.
 * This compares FLOSC_VERSION against the last version that flushed and, when it
 * is newer, flushes immediately and backfills any flow options the new version
 * added.
 *
 * @since 1.3.4
 */
function flosc_version_flush_check() {
	$last_flushed_version = get_option( 'flosc_last_flushed_version', '0.0.0' );

	if ( version_compare( FLOSC_VERSION, $last_flushed_version, '>' ) ) {
		flush_rewrite_rules();
		update_option( 'flosc_last_flushed_version', FLOSC_VERSION );
		update_option( 'flosc_last_permalink_flush', flosc_michel_timestamp_global() );

		if ( function_exists( 'flosc' ) && method_exists( flosc(), 'backfill_flow_defaults' ) ) {
			flosc()->backfill_flow_defaults();
		}

		if ( defined( 'FLOSC_DEBUG' ) && FLOSC_DEBUG ) {
			if ( defined( 'FLOSC_DEBUG' ) && FLOSC_DEBUG ) {
				flosc_log( "FLOSC: Version change detected ({$last_flushed_version} → " . FLOSC_VERSION . ') - flushed permalinks' );
			}
		}
	}
}

if ( ! function_exists( 'flosc_legacy_autoprompt_is_sandbox_pill' ) ) {
	/**
	 * Whether an AutoPrompt pill is one of the old sandbox-purchase test pills.
	 *
	 * Early builds shipped AutoPrompts that opened a sandbox purchase, so a
	 * visitor on a live site could be offered a test transaction. They were
	 * removed from the shipped flows, but they persist in the saved AutoPrompts
	 * of any site that installed those builds, which is what the purge below is
	 * for.
	 *
	 * A pill is recognised by its own text rather than by an id, because the
	 * ids differed between builds: every label, input, action and trigger field
	 * is searched for "sandbox" together with one of the purchase phrasings.
	 * Both halves must match, so a pill that merely mentions a sandbox is left
	 * alone.
	 *
	 * @param mixed $pill One saved AutoPrompt pill; anything that is not an
	 *                    array is not a pill.
	 * @return bool True when the pill opens a sandbox purchase.
	 */
	function flosc_legacy_autoprompt_is_sandbox_pill( $pill ) {
		if ( ! is_array( $pill ) ) {
			return false;
		}

		$fields   = array(
			strtolower( trim( (string) ( $pill['label'] ?? '' ) ) ),
			strtolower( trim( (string) ( $pill['user_input'] ?? '' ) ) ),
			strtolower( trim( (string) ( $pill['action'] ?? '' ) ) ),
			strtolower( trim( (string) ( $pill['trigger_type'] ?? '' ) ) ),
			strtolower( trim( (string) ( $pill['trigger_value'] ?? '' ) ) ),
		);
		$haystack = implode(
			' ',
			array_filter(
				$fields,
				static function ( $value ) {
					return '' !== $value;
				}
			)
		);

		if ( '' === $haystack ) {
			return false;
		}

		return (
			false !== strpos( $haystack, 'sandbox' ) &&
			(
				false !== strpos( $haystack, 'test purchase' ) ||
				false !== strpos( $haystack, 'sandbox purchase' ) ||
				false !== strpos( $haystack, 'open_sandbox_purchase' ) ||
				false !== strpos( $haystack, 'sandbox_purchase' )
			)
		);
	}
}

if ( ! function_exists( 'flosc_legacy_autoprompt_purge_sandbox_pills' ) ) {
	/**
	 * Drop the legacy sandbox pills from a saved AutoPrompts structure.
	 *
	 * Returns all three visitor states whether or not the input held them, so
	 * the caller can save the result without checking. Order within each state
	 * is preserved for the pills that survive.
	 *
	 * @param mixed $autoprompts Saved AutoPrompts, keyed by visitor state.
	 * @return array<string, array> The same structure without the sandbox pills.
	 */
	function flosc_legacy_autoprompt_purge_sandbox_pills( $autoprompts ) {
		if ( ! is_array( $autoprompts ) ) {
			return array();
		}

		$cleaned = array();
		foreach ( array( 'visitor', 'guest', 'member' ) as $state ) {
			$cleaned[ $state ] = array();
			foreach ( ( $autoprompts[ $state ] ?? array() ) as $pill ) {
				if ( flosc_legacy_autoprompt_is_sandbox_pill( $pill ) ) {
					continue;
				}
				$cleaned[ $state ][] = $pill;
			}
		}

		return $cleaned;
	}
}

add_action( 'init', 'flosc_purge_legacy_sandbox_autoprompts', 4 );

/**
 * One-time migration: remove the legacy sandbox-purchase AutoPrompts.
 *
 * Walks every flow that has an IVR file on disk and strips the pills described
 * in flosc_legacy_autoprompt_is_sandbox_pill() from its saved settings. Runs
 * once per site; the flosc_legacy_sandbox_autoprompt_purged option records that
 * it has, so it costs one option read on every later request and nothing more.
 *
 * On init at priority 4, ahead of anything that reads AutoPrompts, so a visitor
 * on the request that performs the migration is served the cleaned set rather
 * than the old one.
 */
function flosc_purge_legacy_sandbox_autoprompts() {
	if ( get_option( 'flosc_legacy_sandbox_autoprompt_purged' ) ) {
		return;
	}

	$ivr_dir = defined( 'FLOSC_PLUGIN_DIR' ) ? FLOSC_PLUGIN_DIR . 'ai_configuration_files/' : '';
	if ( ! $ivr_dir || ! is_dir( $ivr_dir ) ) {
		return;
	}

	// glob() returns false when the directory cannot be read, and an empty
	// array when nothing matches. array_merge() accepts neither false nor a
	// mixed pair, so each result is named and normalised first.
	$suffix_named = glob( $ivr_dir . '*_ivr.md' );
	$prefix_named = glob( $ivr_dir . 'ivr*.md' );
	$files        = array_merge(
		$suffix_named ? $suffix_named : array(),
		$prefix_named ? $prefix_named : array()
	);

	$changed = false;
	foreach ( array_unique( $files ) as $ivr_file ) {
		$fname = basename( $ivr_file );
		$key   = 'flosc_flow_' . sanitize_key( pathinfo( $fname, PATHINFO_FILENAME ) );
		$fs    = get_option( $key, array() );
		if ( empty( $fs ) || empty( $fs['autoprompts'] ) || ! is_array( $fs['autoprompts'] ) ) {
			continue;
		}

		$cleaned = flosc_legacy_autoprompt_purge_sandbox_pills( $fs['autoprompts'] );
		if ( $cleaned !== $fs['autoprompts'] ) {
			$fs['autoprompts'] = $cleaned;
			update_option( $key, $fs );
			$changed = true;
		}
	}

	if ( $changed ) {
		update_option( 'flosc_legacy_sandbox_autoprompt_purged', true );
		if ( defined( 'FLOSC_DEBUG' ) && FLOSC_DEBUG ) {
			flosc_log( 'FLOSC: Purged legacy sandbox autoprompts from flow settings' );
		}
	} else {
		update_option( 'flosc_legacy_sandbox_autoprompt_purged', true );
	}
}

// v8.0.0: One-time IVR re-parse — fixes guest_upgrade action (was show_offer_full_access → checkout_flow_full_offer)
// Runs once on next page load, then sets a flag so it never runs again.
if ( ! get_option( 'flosc_ivr_reparse_800' ) ) {
	add_action(
		'init',
		function () {
			$ivr_dir = defined( 'FLOSC_PLUGIN_DIR' ) ? FLOSC_PLUGIN_DIR . 'ai_configuration_files/' : '';
			if ( $ivr_dir && is_dir( $ivr_dir ) ) {
				require_once FLOSC_PLUGIN_DIR . 'includes/portability/class-ivr-parser.php';
				$parser = FLOSC_IVR_Parser::flosc_instance();
				// See flosc_sync_flow_options_from_ivr_files(): glob() can
				// return false, so each result is named and normalised before
				// the merge.
				$suffix_named = glob( $ivr_dir . '*_ivr.md' );
				$prefix_named = glob( $ivr_dir . 'ivr*.md' );
				$files        = array_merge(
					$suffix_named ? $suffix_named : array(),
					$prefix_named ? $prefix_named : array()
				);
				foreach ( array_unique( $files ) as $ivr_file ) {
					$fname    = basename( $ivr_file );
					$key      = 'flosc_flow_' . sanitize_key( pathinfo( $fname, PATHINFO_FILENAME ) );
					$fs       = get_option( $key, array() );
					$markdown = flosc_fs_get_contents( $ivr_file );
					if ( ! $markdown ) {
						continue;
					}
					$config   = $parser->flosc_parse( $markdown );
					$messages = $config['messages'] ?? array();
					$pills    = array(
						'visitor' => array(),
						'guest'   => array(),
						'member'  => array(),
					);
					foreach ( $messages as $msg ) {
						if ( 'suggested_user_autoprompt' !== ( $msg['type'] ?? '' ) ) {
							continue;
						}
						$cond = $msg['conditions'] ?? $msg['condition'] ?? '';
						foreach ( array( 'visitor', 'guest', 'member' ) as $s ) {
							if ( 'always' === $cond || false !== strpos( $cond, 'is_' . $s ) ) {
								$pills[ $s ][] = array(
									'icon'          => $msg['icon'] ?? '',
									'label'         => $msg['label'] ?? ( $msg['name'] ?? '' ),
									'user_input'    => $msg['user_input'] ?? ( $msg['label'] ?? '' ),
									'trigger_type'  => $msg['trigger_type'] ?? 'ai',
									'trigger_value' => $msg['trigger_value'] ?? '',
									'action'        => $msg['action'] ?? '',
									'conditions'    => $cond,
									'style'         => $msg['style'] ?? ( $msg['message_style'] ?? 'pill' ),
								);
							}
						}
					}
					$fs['autoprompts'] = $pills;
					// Runtime lists: flow_messages / flow_phases / flow_styles only.
					if ( function_exists( 'flosc_flow_set_runtime' ) ) {
						flosc_flow_set_runtime(
							$fs,
							$messages,
							$config['phases'] ?? array(),
							$config['styles'] ?? array()
						);
					} else {
						$fs['flow_messages'] = $messages;
						$fs['flow_phases']   = $config['phases'] ?? array();
						$fs['flow_styles']   = $config['styles'] ?? array();
						unset( $fs['ivr_messages'], $fs['ivr_phases'], $fs['ivr_styles'] );
					}
					// Fresh install: re-parse used to write messages-only options, skipping
					// admin seed (empty() false) and hiding View Flow (needs status+slug).
					$stem_slug = strtolower( preg_replace( '/[^a-z0-9_-]/i', '', pathinfo( $fname, PATHINFO_FILENAME ) ) );
					if ( '' === $stem_slug ) {
						$stem_slug = 'flosc';
					}
					if ( empty( $fs['slug'] ) || ! is_string( $fs['slug'] ) ) {
						$fs['slug'] = $stem_slug;
					}
					if ( empty( $fs['status'] ) || ! is_string( $fs['status'] ) ) {
						$fs['status'] = 'active';
					} elseif ( ! in_array( $fs['status'], array( 'active', 'draft' ), true ) ) {
						$fs['status'] = 'active';
					}
					if ( empty( $fs['name'] ) || ! is_string( $fs['name'] ) ) {
						$shipped    = function_exists( 'flosc_shipped_flow_display_name' )
						? flosc_shipped_flow_display_name( $fname )
						: '';
						$fs['name'] = '' !== $shipped
						? $shipped
						: ucwords( str_replace( array( '_', '-', 'ivr', '.md' ), array( ' ', ' ', '', '' ), $fname ) );
					}
					if ( ! isset( $fs['primary_color'] ) || '' === $fs['primary_color'] ) {
						$fs['primary_color'] = '#4f46e5';
					}
					update_option( $key, $fs );
				}
			}
			update_option( 'flosc_ivr_reparse_800', true );
			if ( defined( 'FLOSC_DEBUG' ) && FLOSC_DEBUG ) {
				flosc_log( 'FLOSC v8.0.0: One-time IVR re-parse complete' );
			}
		},
		5
	);
}

/**
 * A sortable UTC timestamp string, in the format FLOSC records operations with.
 *
 * Reads as 2026y-09m-17d-UTC14h-32m-05s. It lives at global scope rather than
 * on the framework class because the activation hook runs before the class is
 * loaded.
 *
 * @since 1.2.9
 *
 * @return string The current UTC time in that format.
 */
function flosc_michel_timestamp_global() {
	return gmdate( 'Y' ) . 'y-' . gmdate( 'm' ) . 'm-' . gmdate( 'd' ) . 'd-UTC' . gmdate( 'H' ) . 'h-' . gmdate( 'i' ) . 'm-' . gmdate( 's' ) . 's';
}

/**
 * Friendly display names for shipped sample IVR stems (Identity / sidebar).
 *
 * @param string $ivr_filename_or_stem e.g. flosc_default_technical_ivr.md.
 * @return string Empty if not a known shipped sample (caller falls back).
 */
function flosc_shipped_flow_display_name( $ivr_filename_or_stem ) {
	$stem = sanitize_key( pathinfo( basename( (string) $ivr_filename_or_stem ), PATHINFO_FILENAME ) );
	if ( '' === $stem ) {
		$stem = sanitize_key( (string) $ivr_filename_or_stem );
	}
	$map = array(
		'flosc_default_technical_ivr' => 'Tech Agent',
		'flosc_default_friendly_ivr'  => 'Friendly Guide',
		'flosc_default_ivr'           => 'FLOSC Starter',
	);
	return $map[ $stem ] ?? '';
}

/**
 * Shipped IVR files that are personality flavors, not product journeys.
 * They belong in the Personalities library, not in Switch Flow.
 *
 * @param string $ivr_filename_or_stem Filename or stem.
 * @return bool
 */
function flosc_is_shipped_personality_sample_ivr( $ivr_filename_or_stem ) {
	$stem = sanitize_key( pathinfo( basename( (string) $ivr_filename_or_stem ), PATHINFO_FILENAME ) );
	if ( '' === $stem ) {
		$stem = sanitize_key( (string) $ivr_filename_or_stem );
	}
	$samples = array(
		'flosc_default_ivr',
		'flosc_default_friendly_ivr',
		'flosc_default_technical_ivr',
		'flosc_default_br3nda_emotional_support_ivr',
	);
	return in_array( $stem, $samples, true );
}

/**
 * Seed library id for a shipped personality-sample IVR, if any.
 *
 * @param string $ivr_filename_or_stem Filename or stem.
 * @return string
 */
function flosc_shipped_personality_sample_library_id( $ivr_filename_or_stem ) {
	$stem = sanitize_key( pathinfo( basename( (string) $ivr_filename_or_stem ), PATHINFO_FILENAME ) );
	$map  = array(
		'flosc_default_ivr'           => 'friendly',
		'flosc_default_friendly_ivr'  => 'friendly',
		'flosc_default_technical_ivr' => 'tech',
	);
	return $map[ $stem ] ?? '';
}

/**
 * Library personality implied by a shipped sample IVR, if any. Real flows
 * carry their attachment in the per-flow settings bag — never hardcode
 * flow→personality pairs here.
 *
 * @param string $ivr_filename_or_stem Filename or stem.
 * @return string Library id or empty.
 */
function flosc_implied_personality_library_id( $ivr_filename_or_stem ) {
	$id = flosc_shipped_personality_sample_library_id( $ivr_filename_or_stem );
	return sanitize_key( (string) apply_filters( 'flosc_implied_personality_library_id', $id, $ivr_filename_or_stem ) );
}

/**
 * Switch Flow lists journeys. Drop personality-sample IVRs when real flows exist.
 *
 * @param array<int,string> $files IVR filenames.
 * @param string            $keep  Filename to keep even if it is a sample (currently selected).
 * @return array<int,string>
 */
function flosc_filter_switch_flow_ivr_files( $files, $keep = '' ) {
	if ( ! is_array( $files ) ) {
		return array();
	}
	$keep    = basename( (string) $keep );
	$real    = array();
	$samples = array();
	foreach ( $files as $file ) {
		$file = basename( (string) $file );
		if ( '' === $file ) {
			continue;
		}
		if ( function_exists( 'flosc_is_shipped_personality_sample_ivr' ) && flosc_is_shipped_personality_sample_ivr( $file ) ) {
			$samples[] = $file;
		} else {
			$real[] = $file;
		}
	}
	if ( array() === $real ) {
		return array_values( array_unique( $files ) );
	}
	if ( '' !== $keep && in_array( $keep, $samples, true ) && ! in_array( $keep, $real, true ) ) {
		$real[] = $keep;
	}
	return array_values( array_unique( $real ) );
}

require_once FLOSC_PLUGIN_DIR . 'includes/content-item/flosc-content-item-keys.php';
require_once FLOSC_PLUGIN_DIR . 'includes/flosc-rest.php';
require_once FLOSC_PLUGIN_DIR . 'includes/flosc-admin.php';
require_once FLOSC_PLUGIN_DIR . 'admin/settings-helper.php';
require_once FLOSC_PLUGIN_DIR . 'includes/tokens/class-flosc-visitor-token-trait.php';
require_once FLOSC_PLUGIN_DIR . 'includes/magic-link/class-flosc-magic-link-trait.php';
// FLOSC_Filesystem already required above (before flosc-data-paths.php).
require_once FLOSC_PLUGIN_DIR . 'includes/request-guard/class-flosc-request-guard.php';
require_once FLOSC_PLUGIN_DIR . 'includes/da1/class-flosc-da1-catalogs.php';
require_once FLOSC_PLUGIN_DIR . 'includes/starter-packs/class-flosc-starter-packs.php';
require_once FLOSC_PLUGIN_DIR . 'includes/chat-turn/trait-flosc-chat-turn.php';
require_once FLOSC_PLUGIN_DIR . 'includes/companion-mode/class-flosc-companion-mode.php';
require_once FLOSC_PLUGIN_DIR . 'includes/full-page-mode/class-flosc-full-page-mode.php';
require_once FLOSC_PLUGIN_DIR . 'includes/first-party-authentication/class-flosc-first-party-authentication.php';
require_once FLOSC_PLUGIN_DIR . 'includes/email/flosc-guest-followup-slots.php';
require_once FLOSC_PLUGIN_DIR . 'includes/email/class-flosc-email.php';
require_once FLOSC_PLUGIN_DIR . 'includes/sale/class-flosc-checkout-rest.php';
require_once FLOSC_PLUGIN_DIR . 'includes/tokens/class-flosc-token-ledger.php';
require_once FLOSC_PLUGIN_DIR . 'includes/sessions/class-flosc-session-rest.php';
// WordPress.org package: define('FLOSC_ENABLE_MAGIC_ACCESS_LINKS', false) in wp-config.php
// (or filter flosc_enable_magic_access_links) to shelve guest MagicLink without deleting code.


/*
 * The framework class itself. It is required here, at the point in the file
 * where it used to be declared, so nothing that follows sees a different
 * load order than before.
 */
require_once FLOSC_PLUGIN_DIR . 'includes/class-flosc-framework.php';

/**
 * The FLOSC framework instance.
 *
 * The plugin's entry point: every other file reaches the runtime through this
 * rather than through the class name. Safe to call repeatedly -- the instance
 * is built once and returned thereafter.
 *
 * @return FLOSC_Framework The single framework instance.
 */
function flosc() {
	return FLOSC_Framework::instance();
}

/**
 * Lighten or darken a hex colour by a percentage.
 *
 * Used to derive hover and border shades from the one brand colour the operator
 * sets, so a flow's palette stays consistent without asking for five colours.
 *
 * @param string $hex     Colour as #rgb or #rrggbb; the leading # is optional.
 * @param float  $percent Between -1 and 1. Positive lightens toward white,
 *                        negative darkens toward black.
 * @return string The adjusted colour as #rrggbb.
 */
function flosc_adjust_brightness( $hex, $percent ) {
	$hex = ltrim( $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	$r = hexdec( substr( $hex, 0, 2 ) );
	$g = hexdec( substr( $hex, 2, 2 ) );
	$b = hexdec( substr( $hex, 4, 2 ) );

	$r = max( 0, min( 255, $r + ( $r * $percent / 100 ) ) );
	$g = max( 0, min( 255, $g + ( $g * $percent / 100 ) ) );
	$b = max( 0, min( 255, $b + ( $b * $percent / 100 ) ) );

	return sprintf( '#%02x%02x%02x', (int) $r, (int) $g, (int) $b );
}

/**
 * IVR import/export/sync hooks were extracted to a dedicated include to keep
 * this bootstrap file smaller and easier to maintain.
 * Flow runtime (bag-first load + flow_* dual-read helpers) must load before
 * sync/admin/shell callers that use flosc_resolve_flow_runtime / flosc_flow_*.
 */
require_once FLOSC_PLUGIN_DIR . 'includes/flosc-flow-runtime.php';
require_once FLOSC_PLUGIN_DIR . 'includes/portability/flosc-ivr-sync.php';
require_once FLOSC_PLUGIN_DIR . 'includes/flosc-lifecycle.php';

/**
 * Lifecycle hooks are loaded from a dedicated include to keep this bootstrap
 * file focused on framework bootstrapping.
 */

// Register activation hook.
register_activation_hook( __FILE__, 'flosc_activate' );
register_deactivation_hook( __FILE__, 'flosc_deactivate' );

// Translations load automatically on WordPress.org-hosted plugins (WP 4.6+);
// no load_plugin_textdomain() call is needed.

// Start the plugin.
add_action( 'plugins_loaded', 'flosc' );

/**
 * Global helper function for flow-aware settings
 *
 * Usage: flosc_get_setting('ai_provider', 'ivr')
 * Checks: flow[$key] → get_option('flosc_' . $key) → $default
 *
 * @param string      $key      Setting key, without the 'flosc_' prefix.
 * @param mixed       $fallback Returned when neither the flow nor the global option holds a value.
 * @param string|null $flow_id  Read this flow instead of the one the request resolves to.
 * @return mixed The stored value, or $fallback.
 * @since 1.2.4
 */
function flosc_get_setting( $key, $fallback = '', $flow_id = null ) {
	return FLOSC_Framework::instance()->get_setting( $key, $fallback, $flow_id );
}

/**
 * The flow's favicon URL, for the browser tab.
 *
 * Reads favicon_url from the flow's identity settings. When the flow has not set
 * one, falls back to the icon bundled with the plugin.
 *
 * @param string $size Size suffix on the bundled icon, e.g. '32' for
 *                     flosc-icon-32.png. Empty for the unsuffixed default. Only
 *                     applies to the fallback; a flow's own favicon_url is
 *                     returned as set.
 * @return string Absolute URL of the favicon.
 */
function flosc_get_favicon_url( $size = '' ) {
	$identity = FLOSC_Framework::instance()->get_floscflow_identity();
	if ( ! empty( $identity['favicon_url'] ) ) {
		return $identity['favicon_url'];
	}
	$suffix = $size ? "-{$size}" : '';
	return FLOSC_PLUGIN_URL . "assets/img/flosc-icon{$suffix}.png";
}

/**
 * Resolve Chat Logo URL from a flow settings array (admin or runtime).
 * Prefer identity.chatlogo_url, then flat chatlogo_url. Does not invent a brand logo.
 *
 * @param array|null $flow_settings Flow option / current settings. Null = current runtime identity.
 * @param bool       $use_plugin_default When true and nothing set, return bundled FLOSC icon.
 * @return string URL or empty string when $use_plugin_default is false and no logo is configured.
 */
function flosc_resolve_chatlogo_url( $flow_settings = null, $use_plugin_default = true ) {
	$url = '';

	if ( is_array( $flow_settings ) ) {
		if ( ! empty( $flow_settings['identity'] ) && is_array( $flow_settings['identity'] ) ) {
			$url = (string) ( $flow_settings['identity']['chatlogo_url'] ?? '' );
		}
		if ( '' === $url && ! empty( $flow_settings['chatlogo_url'] ) ) {
			$url = (string) $flow_settings['chatlogo_url'];
		}
	}

	if ( '' === $url && function_exists( 'flosc' ) && is_object( flosc() ) && method_exists( flosc(), 'get_floscflow_identity' ) ) {
		$identity = flosc()->get_floscflow_identity();
		if ( is_array( $identity ) && ! empty( $identity['chatlogo_url'] ) ) {
			$url = (string) $identity['chatlogo_url'];
		}
	}

	$url = esc_url_raw( trim( $url ) );
	if ( '' !== $url ) {
		return $url;
	}

	if ( $use_plugin_default ) {
		return FLOSC_PLUGIN_URL . 'assets/img/flosc-icon.png';
	}

	return '';
}

/**
 * Get the flow's chatLogo URL (landing state header image, sidebar logo).
 * Reads chatlogo_url from flow identity. Falls back to bundled FLOSC default icon.
 */
function flosc_get_chatlogo_url() {
	return flosc_resolve_chatlogo_url( null, true );
}

/**
 * Expanded Sticky-for-User prompt fragment, or empty.
 *
 * Enable is per attached personality. Content is per WordPress user.
 *
 * @param int   $user_id WordPress user ID.
 * @param array $context Turn context (flow_id, access_level, flow_name).
 * @return string
 */
function flosc_get_user_sticky_prompt( $user_id, $context = array() ) {
	$user_id = absint( $user_id );
	if ( $user_id <= 0 || ! is_user_logged_in() || get_current_user_id() !== $user_id ) {
		return '';
	}
	$flow_id = sanitize_key( (string) ( $context['flow_id'] ?? '' ) );
	$enabled = function_exists( 'flosc_personality_library_resolve_field' )
		? (string) flosc_personality_library_resolve_field( 'enable_user_sticky', '', '' !== $flow_id ? $flow_id : null )
		: '';
	if ( '1' !== $enabled ) {
		return '';
	}
	$sticky = trim( (string) get_user_meta( $user_id, '_flosc_user_sticky', true ) );
	if ( '' === $sticky ) {
		return '';
	}
	$sticky = substr( $sticky, 0, 4000 );

	$user         = get_userdata( $user_id );
	$quiz_data    = get_user_meta( $user_id, '_flosc_last_quiz_data', true );
	$weakest      = is_array( $quiz_data ) && is_array( $quiz_data['ranked_phonemes'] ?? null )
		? implode( ', ', array_map( 'sanitize_text_field', $quiz_data['ranked_phonemes'] ) )
		: '';
	$flow_name    = trim( (string) ( $context['flow_name'] ?? '' ) );
	$access_level = sanitize_key( (string) ( $context['access_level'] ?? '' ) );
	$member_level = sanitize_key( (string) get_user_meta( $user_id, '_flosc_member_level', true ) );
	if ( '' === $flow_name && function_exists( 'flosc_get_setting' ) ) {
		$flow_name = trim( (string) flosc_get_setting( 'title', '', '' !== $flow_id ? $flow_id : null ) );
	}

	$variables = array(
		'{userName}'        => $user ? (string) $user->display_name : '',
		'{firstName}'       => $user ? (string) $user->first_name : '',
		'{lastName}'        => $user ? (string) $user->last_name : '',
		'{email}'           => $user ? (string) $user->user_email : '',
		'{userId}'          => (string) $user_id,
		'{accessLevel}'     => $access_level,
		'{memberLevel}'     => $member_level,
		'{quizScore}'       => (string) get_user_meta( $user_id, '_flosc_last_quiz_score', true ),
		'{weakestPhonemes}' => $weakest,
		'{flowName}'        => $flow_name,
		'{siteName}'        => (string) get_bloginfo( 'name' ),
	);
	$sticky    = strtr( $sticky, $variables );

	return "Private administrator guidance for this signed-in user. Use it when relevant; do not recite it, mention this field, or claim it applies to anyone else.\n\n"
		. $sticky;
}

/**
 * Personality labels that have Enable Sticky for User on.
 *
 * @return string[]
 */
function flosc_get_user_sticky_enabled_personalities() {
	if ( ! function_exists( 'flosc_personality_library_get_all' ) ) {
		return array();
	}
	$out = array();
	foreach ( (array) flosc_personality_library_get_all() as $id => $row ) {
		if ( ! is_array( $row ) || empty( $row['enable_user_sticky'] ) ) {
			continue;
		}
		$label = trim( (string) ( $row['label'] ?? $row['ai_personality_name'] ?? $id ) );
		$out[] = '' !== $label ? $label : (string) $id;
	}
	return array_values( array_unique( $out ) );
}
