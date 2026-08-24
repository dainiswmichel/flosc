<?php
/**
 * Install personality library — attach exactly one entry to a floscFlow.
 * No personality chaining (only AI APIs chain).
 *
 * Option: flosc_personality_library
 * Flow bag key: personality_library_id (empty = custom fields on the flow only)
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'flosc_personality_library_option_key' ) ) {
	/**
	 * @return string
	 */
	function flosc_personality_library_option_key() {
		return 'flosc_personality_library';
	}
}

if ( ! function_exists( 'flosc_personality_library_field_keys' ) ) {
	/**
	 * Fields stored on each library entry (and mirrored on the flow when custom).
	 *
	 * @return array<int,string>
	 */
	function flosc_personality_library_field_keys() {
		return array(
			'ai_personality_name',
			'ai_personality_role',
			'ai_personality_traits',
			'ai_base_prompt',
			'ai_mission',
			'ai_boundaries',
			'ai_topic_scope',
			'ai_off_topic_message',
			'ai_off_topic_links',
			'ai_fallback_phrase',
			'workshop_json',
		);
	}
}

if ( ! function_exists( 'flosc_personality_library_default_workshop' ) ) {
	/**
	 * FLOSC Starter workshop: every band populated, one raincloud and one
	 * pool, so a fresh install demonstrates the density sequence end to end.
	 *
	 * @return array<string,mixed>
	 */
	function flosc_personality_library_default_workshop() {
		$on = static function ( $id, $density ) {
			return array(
				'id'          => $id,
				'on'          => true,
				'state'       => 'on',
				'gain'        => 55,
				'density'     => $density,
				'instruction' => '',
			);
		};
		return array(
			'soul'        => array(
				'id'           => 'starter',
				'label'        => 'FLOSC Starter',
				'name'         => 'FLOSC Assistant',
				'role'         => 'Neutral guide for this site’s FLOSC flow',
				'goals'        => 'Help visitors understand and use this flow.',
				'prohibitions' => 'Do not invent products, prices, or contact details.',
				'scope'        => 'This site and this flow’s configured product.',
			),
			'tributaries' => array(
				// Soul · density band ≈ 0–33.
				$on( 'sophia', 6 ),
				$on( 'maat', 14 ),
				$on( 'know_first', 22 ),
				$on( 'witness', 30 ),
				// Character · density band ≈ 34–66.
				$on( 'kind', 38 ),
				$on( 'humor', 44 ),
				$on( 'deborah', 50 ),
				$on( 'relax', 58 ),
				// Behavior · density band ≈ 67–100.
				$on( 'no_fabricate', 70 ),
				$on( 'lie', 74 ),
				$on( 'admit_wrong', 78 ),
				$on( 'good_evil', 82 ),
				$on( 'no_lead', 88 ),
			),
			'clouds'      => array(
				array(
					'id'          => 'cloud_1',
					'name'        => 'Warm clarity',
					'color'       => '#eef4ee',
					'explanation' => 'Kindness that stays truthful — warmth without flattery.',
					'cols'        => 2,
					'members'     => array( 'humor', 'deborah' ),
				),
				array(
					'id'          => 'cloud_2',
					'name'        => 'Never deceive',
					'color'       => '#e8f1ee',
					'explanation' => 'Honesty behaviors held together as one promise.',
					'cols'        => 2,
					'members'     => array( 'no_fabricate', 'lie', 'admit_wrong', 'good_evil' ),
				),
			),
		);
	}
}

if ( ! function_exists( 'flosc_personality_library_defaults' ) ) {
	/**
	 * Seed entries for a fresh install.
	 *
	 * @return array<string,array<string,string>>
	 */
	function flosc_personality_library_defaults() {
		return array(
			'starter'  => array(
				'id'                     => 'starter',
				'label'                  => 'FLOSC Starter',
				'ai_personality_name'    => 'FLOSC Assistant',
				'ai_personality_role'    => 'Neutral guide for this site’s FLOSC flow',
				'ai_personality_traits'  => 'Clear, helpful, professional, not salesy',
				'ai_base_prompt'         => '',
				'ai_mission'             => 'Help visitors understand and use this flow.',
				'ai_boundaries'          => 'Do not invent products, prices, or contact details.',
				'ai_topic_scope'         => 'This site and this flow’s configured product.',
				'ai_off_topic_message'   => '',
				'ai_off_topic_links'     => '',
				'ai_fallback_phrase'     => '',
				'workshop_json'          => wp_json_encode( flosc_personality_library_default_workshop() ),
			),
			'friendly' => array(
				'id'                     => 'friendly',
				'label'                  => 'Friendly Guide',
				'ai_personality_name'    => 'Friendly Guide',
				'ai_personality_role'    => 'Warm, upbeat host who explores with the visitor',
				'ai_personality_traits'  => 'Friendly, encouraging, clear, light humor when it fits',
				'ai_base_prompt'         => '',
				'ai_mission'             => 'Welcome people and help them take the next useful step.',
				'ai_boundaries'          => 'Do not invent facts, prices, or promises.',
				'ai_topic_scope'         => 'This site’s product and visitor goals.',
				'ai_off_topic_message'   => '',
				'ai_off_topic_links'     => '',
				'ai_fallback_phrase'     => '',
				'workshop_json'          => '',
			),
			'tech'     => array(
				'id'                     => 'tech',
				'label'                  => 'Tech Agent',
				'ai_personality_name'    => 'Tech Agent',
				'ai_personality_role'    => 'Direct technical answers agent',
				'ai_personality_traits'  => 'Precise, concise, no fluff, no forced cheer',
				'ai_base_prompt'         => '',
				'ai_mission'             => 'Answer concrete product and setup questions accurately.',
				'ai_boundaries'          => 'If unknown, say so. Do not invent APIs or config steps.',
				'ai_topic_scope'         => 'Technical product use, setup, and troubleshooting.',
				'ai_off_topic_message'   => '',
				'ai_off_topic_links'     => '',
				'ai_fallback_phrase'     => '',
				'workshop_json'          => '',
			),
		);
	}
}

if ( ! function_exists( 'flosc_personality_library_get_all' ) ) {
	/**
	 * @return array<string,array<string,string>>
	 */
	function flosc_personality_library_get_all() {
		$key = flosc_personality_library_option_key();
		$raw = get_option( $key, false );
		if ( false === $raw ) {
			$raw = flosc_personality_library_defaults();
			add_option( $key, $raw, '', false );
		}
		if ( ! is_array( $raw ) ) {
			$raw = flosc_personality_library_defaults();
		}
		$out = array();
		foreach ( $raw as $id => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = sanitize_key( (string) ( $row['id'] ?? $id ) );
			if ( $id === '' ) {
				continue;
			}
			$entry = array(
				'id'    => $id,
				'label' => isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : $id,
			);
			foreach ( flosc_personality_library_field_keys() as $fk ) {
				$entry[ $fk ] = isset( $row[ $fk ] ) ? (string) $row[ $fk ] : '';
			}
			$out[ $id ] = $entry;
		}
		return $out;
	}
}

if ( ! function_exists( 'flosc_personality_library_get' ) ) {
	/**
	 * @param string $id Personality id.
	 * @return array<string,string>|null
	 */
	function flosc_personality_library_get( $id ) {
		$id  = sanitize_key( (string) $id );
		$all = flosc_personality_library_get_all();
		return ( $id !== '' && isset( $all[ $id ] ) ) ? $all[ $id ] : null;
	}
}

if ( ! function_exists( 'flosc_personality_library_save_all' ) ) {
	/**
	 * @param array<string,array<string,mixed>> $library Full map.
	 * @return void
	 */
	function flosc_personality_library_save_all( $library ) {
		if ( ! is_array( $library ) ) {
			return;
		}
		$previous = flosc_personality_library_get_all();
		$clean    = array();
		foreach ( $library as $id => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = sanitize_key( (string) ( $row['id'] ?? $id ) );
			if ( $id === '' ) {
				continue;
			}
			$prior = isset( $previous[ $id ] ) && is_array( $previous[ $id ] ) ? $previous[ $id ] : array();
			$entry = array(
				'id'    => $id,
				'label' => isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : $id,
			);
			foreach ( flosc_personality_library_field_keys() as $fk ) {
				if ( 'workshop_json' === $fk ) {
					if ( array_key_exists( 'workshop_json', $row ) ) {
						$entry[ $fk ] = flosc_sanitize_personality_workshop( (string) $row['workshop_json'] );
					} else {
						$entry[ $fk ] = isset( $prior['workshop_json'] ) ? (string) $prior['workshop_json'] : '';
					}
					continue;
				}
				$val = isset( $row[ $fk ] ) ? (string) $row[ $fk ] : '';
				if ( 'ai_base_prompt' === $fk ) {
					$entry[ $fk ] = flosc_sanitize_personality_profile_text( $val );
				} elseif ( in_array( $fk, array( 'ai_mission', 'ai_boundaries', 'ai_topic_scope', 'ai_off_topic_message', 'ai_off_topic_links' ), true ) ) {
					$entry[ $fk ] = sanitize_textarea_field( $val );
				} else {
					$entry[ $fk ] = sanitize_text_field( $val );
				}
			}
			$clean[ $id ] = $entry;
		}
		update_option( flosc_personality_library_option_key(), $clean, false );
	}
}

if ( ! function_exists( 'flosc_personality_library_resolve_field' ) ) {
	/**
	 * Value for a personality field: attached library entry wins when non-empty; else flow setting.
	 *
	 * @param string      $field   Field key (e.g. ai_personality_name).
	 * @param mixed       $default Default.
	 * @param string|null $flow_id Optional flow stem.
	 * @return mixed
	 */
	function flosc_personality_library_resolve_field( $field, $default = '', $flow_id = null ) {
		$field = (string) $field;
		$pid   = '';
		if ( function_exists( 'flosc_get_setting' ) ) {
			$pid = sanitize_key( (string) flosc_get_setting( 'personality_library_id', '', $flow_id ) );
		}
		if ( $pid === '' && function_exists( 'flosc_personality_library_id_for_flow' ) ) {
			$pid = flosc_personality_library_id_for_flow( $flow_id );
		}
		if ( $pid !== '' ) {
			$entry = flosc_personality_library_get( $pid );
			if ( is_array( $entry ) && isset( $entry[ $field ] ) && trim( (string) $entry[ $field ] ) !== '' ) {
				return $entry[ $field ];
			}
		}
		if ( function_exists( 'flosc_get_setting' ) ) {
			return flosc_get_setting( $field, $default, $flow_id );
		}
		return $default;
	}
}

if ( ! function_exists( 'flosc_flow_name' ) ) {
	/**
	 * Flow Name (Identity). Switch Flow pull-down only. Not the chat header.
	 *
	 * @param string|null $flow_id Optional flow stem.
	 * @return string
	 */
	function flosc_flow_name( $flow_id = null ) {
		$name = '';
		if ( function_exists( 'flosc_get_setting' ) ) {
			$name = trim( (string) flosc_get_setting( 'name', '', $flow_id ) );
		}
		if ( $name === '' && $flow_id === null && function_exists( 'flosc' ) ) {
			$inst = flosc();
			if ( is_object( $inst ) && method_exists( $inst, 'get_floscflow_identity' ) ) {
				$id   = $inst->get_floscflow_identity();
				$name = is_array( $id ) ? trim( (string) ( $id['name'] ?? '' ) ) : '';
			}
		}
		return $name;
	}
}

if ( ! function_exists( 'flosc_personality_name' ) ) {
	/**
	 * Personality Name. Chat header, composer, landing H1, compiled “You are {name}.”
	 * Attached library row only — never Flow Name.
	 *
	 * @param string|null $flow_id Optional flow stem.
	 * @return string
	 */
	function flosc_personality_name( $flow_id = null ) {
		$name = '';
		if ( function_exists( 'flosc_personality_library_resolve_field' ) ) {
			$name = trim( (string) flosc_personality_library_resolve_field( 'ai_personality_name', '', $flow_id ) );
		}
		if ( $name === '' && function_exists( 'flosc_get_setting' ) ) {
			$name = trim( (string) flosc_get_setting( 'ai_personality_name', '', $flow_id ) );
		}
		return $name !== '' ? $name : 'FLOSC';
	}
}

if ( ! function_exists( 'flosc_visitor_assistant_name' ) ) {
	/**
	 * Alias of flosc_personality_name().
	 *
	 * @param string|null $flow_id Optional flow stem.
	 * @return string
	 */
	function flosc_visitor_assistant_name( $flow_id = null ) {
		return flosc_personality_name( $flow_id );
	}
}

if ( ! function_exists( 'flosc_flow_public_title' ) ) {
	/**
	 * Public public title for this floscFlow (landing under the personality, browser-tab suffix, AI facts).
	 * Not the operator floscFlow name and not the personality name.
	 *
	 * @param string|null $flow_id Optional flow stem.
	 * @return string
	 */
	function flosc_flow_public_title( $flow_id = null ) {
		$title = '';
		if ( function_exists( 'flosc_get_setting' ) ) {
			$title = trim( (string) flosc_get_setting( 'title', '', $flow_id ) );
		}
		if ( $title === '' && $flow_id === null && function_exists( 'flosc' ) ) {
			$inst = flosc();
			if ( is_object( $inst ) && method_exists( $inst, 'get_floscflow_identity' ) ) {
				$id    = $inst->get_floscflow_identity();
				$title = is_array( $id ) ? trim( (string) ( $id['title'] ?? '' ) ) : '';
			}
		}
		return $title;
	}
}

if ( ! function_exists( 'flosc_flow_public_tagline' ) ) {
	/**
	 * One-line expansion of the Public Title. Not the FLOSC acronym, not the personality.
	 *
	 * @param string|null $flow_id Optional flow stem.
	 * @return string
	 */
	function flosc_flow_public_tagline( $flow_id = null ) {
		$tagline = '';
		if ( function_exists( 'flosc_get_setting' ) ) {
			$tagline = trim( (string) flosc_get_setting( 'tagline', '', $flow_id ) );
		}
		if ( $tagline === '' && $flow_id === null && function_exists( 'flosc' ) ) {
			$inst = flosc();
			if ( is_object( $inst ) && method_exists( $inst, 'get_floscflow_identity' ) ) {
				$id      = $inst->get_floscflow_identity();
				$tagline = is_array( $id ) ? trim( (string) ( $id['tagline'] ?? '' ) ) : '';
			}
		}
		return $tagline;
	}
}

if ( ! function_exists( 'flosc_admin_save_personality_library' ) ) {
	/**
	 * admin-post.php?action=flosc_save_personality_library
	 *
	 * @return void
	 */
	function flosc_admin_save_personality_library() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage personalities.', 'flosc' ) );
		}
		check_admin_referer( 'flosc_save_personality_library' );

		$existing = flosc_personality_library_get_all();
		$posted   = array();
		if ( isset( $_POST['persona'] ) && is_array( $_POST['persona'] ) ) {
			foreach ( wp_unslash( $_POST['persona'] ) as $row ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- keys/labels sanitized below.
				if ( ! is_array( $row ) ) {
					continue;
				}
				$id = isset( $row['id'] ) ? sanitize_key( (string) $row['id'] ) : '';
				if ( $id === '' ) {
					continue;
				}
				$label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
				$posted[ $id ] = array(
					'id'    => $id,
					'label' => $label !== '' ? $label : $id,
				);
			}
		}
		$delete = array();
		if ( isset( $_POST['persona_delete'] ) && is_array( $_POST['persona_delete'] ) ) {
			foreach ( wp_unslash( $_POST['persona_delete'] ) as $did => $on ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- key sanitized, value is a flag.
				$did = sanitize_key( (string) $did );
				if ( $did !== '' && $on ) {
					$delete[ $did ] = true;
				}
			}
		}
		$new_id  = isset( $_POST['new_persona_id'] ) ? sanitize_key( wp_unslash( (string) $_POST['new_persona_id'] ) ) : '';
		$new_lab = isset( $_POST['new_persona_label'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['new_persona_label'] ) ) : '';

		$lib = array();
		foreach ( $posted as $id => $row ) {
			if ( ! empty( $delete[ $id ] ) ) {
				continue;
			}
			if ( isset( $existing[ $id ] ) && is_array( $existing[ $id ] ) ) {
				$lib[ $id ]          = $existing[ $id ];
				$lib[ $id ]['id']    = $id;
				$lib[ $id ]['label'] = $row['label'];
			}
		}

		if ( $new_id !== '' && ! isset( $lib[ $new_id ] ) && empty( $delete[ $new_id ] ) ) {
			$lib[ $new_id ] = array(
				'id'    => $new_id,
				'label' => $new_lab !== '' ? $new_lab : $new_id,
			);
			foreach ( flosc_personality_library_field_keys() as $fk ) {
				$lib[ $new_id ][ $fk ] = '';
			}
		}

		if ( $posted === array() && $new_id === '' && $delete === array() ) {
			$lib = null;
		}
		if ( is_array( $lib ) ) {
			flosc_personality_library_save_all( $lib );
		}

		set_transient(
			'flosc_ai_all_notice_' . get_current_user_id(),
			array(
				'message' => __( 'Personalities saved.', 'flosc' ),
				'type'    => 'success',
			),
			60
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$ivr = isset( $_POST['flosc_return_ivr'] ) ? sanitize_file_name( wp_unslash( (string) $_POST['flosc_return_ivr'] ) ) : '';
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'flosc-settings',
					'tab'  => 'ai',
					'view' => 'all',
					'ivr'  => $ivr,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
	add_action( 'admin_post_flosc_save_personality_library', 'flosc_admin_save_personality_library' );
}

if ( ! function_exists( 'flosc_personality_profile_max_bytes' ) ) {
	/**
	 * Compiled personality profile size cap (bytes).
	 *
	 * @return int
	 */
	function flosc_personality_profile_max_bytes() {
		return 200000;
	}
}

if ( ! function_exists( 'flosc_personality_workshop_max_bytes' ) ) {
	/**
	 * Workshop JSON size cap (bytes).
	 *
	 * @return int
	 */
	function flosc_personality_workshop_max_bytes() {
		return 800000;
	}
}

if ( ! function_exists( 'flosc_sanitize_personality_profile_text' ) ) {
	/**
	 * Keep Markdown structure for compiled personalities. Do not use
	 * sanitize_textarea_field() — it collapses whitespace and strips hashes.
	 *
	 * @param string $text Raw profile.
	 * @return string
	 */
	function flosc_sanitize_personality_profile_text( $text ) {
		$text = (string) $text;
		$text = wp_check_invalid_utf8( $text );
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text );
		if ( ! is_string( $text ) ) {
			$text = '';
		}
		$max = flosc_personality_profile_max_bytes();
		if ( strlen( $text ) > $max ) {
			$text = substr( $text, 0, $max );
		}
		return $text;
	}
}

if ( ! function_exists( 'flosc_sanitize_personality_workshop' ) ) {
	/**
	 * Accept only a JSON object. Strip derived provider packs (not used in FLOSC).
	 *
	 * @param string $raw Raw JSON.
	 * @return string Empty string or re-encoded JSON object.
	 */
	function flosc_sanitize_personality_workshop( $raw ) {
		$raw = (string) $raw;
		$raw = wp_check_invalid_utf8( $raw );
		$raw = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $raw );
		if ( ! is_string( $raw ) ) {
			return '';
		}
		$raw = trim( $raw );
		if ( $raw === '' ) {
			return '';
		}
		$max = flosc_personality_workshop_max_bytes();
		if ( strlen( $raw ) > $max ) {
			return '';
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || $decoded === array() ) {
			return '';
		}
		$keys = array_keys( $decoded );
		if ( $keys === range( 0, count( $decoded ) - 1 ) ) {
			return '';
		}
		if ( isset( $decoded['derived'] ) && is_array( $decoded['derived'] ) ) {
			unset( $decoded['derived']['provider_packs'] );
		}
		$encoded = wp_json_encode( $decoded );
		if ( ! is_string( $encoded ) || $encoded === '' ) {
			return '';
		}
		if ( strlen( $encoded ) > $max ) {
			return '';
		}
		return $encoded;
	}
}

if ( ! function_exists( 'flosc_personality_compiled_profile' ) ) {
	/**
	 * Compiled personality Markdown for this flow (library attach or custom).
	 *
	 * @param string|null $flow_id Optional flow stem.
	 * @return string
	 */
	function flosc_personality_compiled_profile( $flow_id = null ) {
		$profile = '';
		if ( function_exists( 'flosc_personality_library_resolve_field' ) ) {
			$profile = (string) flosc_personality_library_resolve_field( 'ai_base_prompt', '', $flow_id );
		} elseif ( function_exists( 'flosc_get_setting' ) ) {
			$profile = (string) flosc_get_setting( 'ai_base_prompt', '', $flow_id );
		}
		return trim( $profile );
	}
}

if ( ! function_exists( 'flosc_personality_builder_asset_url' ) ) {
	/**
	 * Standalone workshop HTML (DA1). FLOSC admin renders the markup in-page.
	 *
	 * @return string
	 */
	function flosc_personality_builder_asset_url() {
		return FLOSC_PLUGIN_URL . 'assets/personality-builder/flosc-personality-builder.html';
	}
}

if ( ! function_exists( 'flosc_personality_builder_request_context' ) ) {
	/**
	 * Persona and IVR from the designer admin request.
	 *
	 * Mirrors the fallback chain in admin/settings.php ($_GET ivr → user default ivr →
	 * first available flow file) so the assets enqueued here always match the flow the
	 * AI tab actually renders, even when the URL carries no ivr param.
	 *
	 * @return array{persona:string,ivr:string}
	 */
	function flosc_personality_builder_request_context() {
		$ivr_files = array();
		if ( function_exists( 'flosc_config_glob' ) ) {
			$found = flosc_config_glob( array( '*_ivr.md', 'ivr*.md' ) );
			sort( $found );
			foreach ( $found as $file ) {
				$name = basename( (string) $file );
				if ( strpos( $name, 'backup' ) === false ) {
					$ivr_files[] = $name;
				}
			}
			$ivr_files = array_values( array_unique( $ivr_files ) );
		}

		$ivr_raw = filter_input( INPUT_GET, 'ivr', FILTER_UNSAFE_RAW );
		$ivr     = is_string( $ivr_raw ) ? sanitize_file_name( wp_unslash( $ivr_raw ) ) : '';
		if ( $ivr !== '' && ! empty( $ivr_files ) && ! in_array( $ivr, $ivr_files, true ) ) {
			$ivr = '';
		}
		if ( $ivr === '' && function_exists( 'get_current_user_id' ) ) {
			$user_default = sanitize_file_name( (string) get_user_meta( get_current_user_id(), '_flosc_admin_default_ivr', true ) );
			if ( $user_default !== '' && ( empty( $ivr_files ) || in_array( $user_default, $ivr_files, true ) ) ) {
				$ivr = $user_default;
			}
		}
		if ( $ivr === '' && ! empty( $ivr_files ) ) {
			$ivr = $ivr_files[0];
		}

		$persona = '';
		if ( $ivr !== '' && function_exists( 'flosc_personality_library_id_for_flow' ) ) {
			$persona = flosc_personality_library_id_for_flow( sanitize_key( pathinfo( $ivr, PATHINFO_FILENAME ) ) );
		}
		return array(
			'persona' => $persona,
			'ivr'     => $ivr,
		);
	}
}

if ( ! function_exists( 'flosc_personality_builder_url' ) ) {
	/**
	 * Admin URL for Personality Designer on the AI tab.
	 *
	 * @param string $persona_id Library id.
	 * @param string $ivr        Optional current IVR filename.
	 * @return string
	 */
	function flosc_personality_builder_url( $persona_id, $ivr = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- signature kept for callers.
		$args = array(
			'page' => 'flosc-settings',
			'tab'  => 'ai',
			'view' => 'single',
		);
		$ivr = sanitize_file_name( (string) $ivr );
		if ( $ivr !== '' ) {
			$args['ivr'] = $ivr;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) ) . '#flosc-personality-designer';
	}
}

if ( ! function_exists( 'flosc_personality_library_url' ) ) {
	/**
	 * @param string $ivr Optional current IVR filename.
	 * @return string
	 */
	function flosc_personality_library_url( $ivr = '' ) {
		$args = array(
			'page' => 'flosc-settings',
			'tab'  => 'ai',
			'view' => 'all',
		);
		$ivr = sanitize_file_name( (string) $ivr );
		if ( $ivr !== '' ) {
			$args['ivr'] = $ivr;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) ) . '#flosc-personality-library';
	}
}

if ( ! function_exists( 'flosc_render_ai_tab_nav' ) ) {
	/**
	 * This flow / All Flows buttons on the AI tab.
	 *
	 * @param string $current_view single|all
	 * @param string $ivr          Optional current IVR filename.
	 * @return void
	 */
	function flosc_render_ai_tab_nav( $current_view, $ivr = '' ) {
		$current_view = sanitize_key( (string) $current_view );
		$ivr          = sanitize_file_name( (string) $ivr );
		$single_args  = array(
			'page' => 'flosc-settings',
			'tab'  => 'ai',
			'view' => 'single',
		);
		$all_args     = array(
			'page' => 'flosc-settings',
			'tab'  => 'ai',
			'view' => 'all',
		);
		if ( $ivr !== '' ) {
			$single_args['ivr'] = $ivr;
			$all_args['ivr']    = $ivr;
		}
		$single_url = add_query_arg( $single_args, admin_url( 'admin.php' ) );
		$all_url    = add_query_arg( $all_args, admin_url( 'admin.php' ) );
		?>
<div class="flosc-ivr-actions-row flosc-margin-bottom-16">
	<a href="<?php echo esc_url( $single_url ); ?>" class="button<?php echo 'single' === $current_view ? ' button-primary' : ''; ?>">
		<?php echo esc_html__( 'This flow: AI settings', 'flosc' ); ?>
	</a>
	<a href="<?php echo esc_url( $all_url ); ?>" class="button<?php echo 'all' === $current_view ? ' button-primary' : ''; ?>">
		<?php echo esc_html__( 'All Flows AI API Management', 'flosc' ); ?>
	</a>
</div>
		<?php
	}
}

if ( ! function_exists( 'flosc_personality_library_update_entry' ) ) {
	/**
	 * Merge fields into one library row.
	 *
	 * @param string               $id     Persona id.
	 * @param array<string,string> $fields Fields to merge.
	 * @return bool
	 */
	function flosc_personality_library_update_entry( $id, $fields ) {
		$id = sanitize_key( (string) $id );
		if ( $id === '' || ! is_array( $fields ) ) {
			return false;
		}
		$lib = flosc_personality_library_get_all();
		if ( ! isset( $lib[ $id ] ) || ! is_array( $lib[ $id ] ) ) {
			$lib[ $id ] = array(
				'id'    => $id,
				'label' => $id,
			);
			foreach ( flosc_personality_library_field_keys() as $fk ) {
				$lib[ $id ][ $fk ] = '';
			}
		}
		if ( isset( $fields['label'] ) ) {
			$lib[ $id ]['label'] = sanitize_text_field( (string) $fields['label'] );
		}
		foreach ( flosc_personality_library_field_keys() as $fk ) {
			if ( ! array_key_exists( $fk, $fields ) ) {
				continue;
			}
			$lib[ $id ][ $fk ] = (string) $fields[ $fk ];
		}
		flosc_personality_library_save_all( $lib );
		return true;
	}
}

if ( ! function_exists( 'flosc_personality_library_promote_custom_flow_voices' ) ) {
	/**
	 * Lift custom-on-flow voices into the library and attach them.
	 * Shipped personality-sample IVRs attach the seed rows. Does not invent soul text.
	 *
	 * @return void
	 */
	function flosc_personality_library_promote_custom_flow_voices() {
		static $ran = false;
		if ( $ran ) {
			return;
		}
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( get_option( 'flosc_personality_library_promoted', '' ) === '1' ) {
			$ran = true;
			return;
		}
		if ( ! function_exists( 'flosc_config_glob' ) ) {
			return;
		}
		$ran = true;

		$paths = flosc_config_glob( array( '*_ivr.md', 'ivr*.md' ) );
		if ( ! is_array( $paths ) ) {
			return;
		}
		$lib       = flosc_personality_library_get_all();
		$lib_dirty = false;

		foreach ( $paths as $path ) {
			$file = basename( (string) $path );
			if ( $file === '' || false !== strpos( $file, 'backup' ) || false !== strpos( $file, '_bak_' ) ) {
				continue;
			}
			$picked = flosc_personality_flow_settings_for_ivr( $file );
			$fs     = $picked['settings'];
			$key    = $picked['option_key'];
			if ( $key === '' || ! is_array( $fs ) ) {
				continue;
			}
			$attached = sanitize_key( (string) ( $fs['personality_library_id'] ?? '' ) );
			$id       = function_exists( 'flosc_implied_personality_library_id' )
				? flosc_implied_personality_library_id( $file )
				: '';
			if ( $id === '' ) {
				$name = trim( (string) ( $fs['ai_personality_name'] ?? '' ) );
				if ( $name === '' ) {
					continue;
				}
				$id = sanitize_key( $name );
			}
			if ( $id === '' ) {
				continue;
			}
			if ( $attached !== '' && isset( $lib[ $attached ] ) && $attached === $id ) {
				continue;
			}

			$name = trim( (string) ( $fs['ai_personality_name'] ?? '' ) );
			if ( $name === '' && isset( $fs['identity'] ) && is_array( $fs['identity'] ) ) {
				$name = trim( (string) ( $fs['identity']['name'] ?? '' ) );
			}
			if ( $name === '' ) {
				$name = trim( (string) ( $fs['name'] ?? '' ) );
			}
			if ( $name === '' ) {
				$name = $id;
			}

			if ( ! isset( $lib[ $id ] ) ) {
				$entry = array(
					'id'    => $id,
					'label' => $name,
				);
				foreach ( flosc_personality_library_field_keys() as $fk ) {
					if ( 'workshop_json' === $fk ) {
						$entry[ $fk ] = '';
						continue;
					}
					$entry[ $fk ] = isset( $fs[ $fk ] ) ? (string) $fs[ $fk ] : '';
				}
				if ( $entry['ai_personality_name'] === '' ) {
					$entry['ai_personality_name'] = $name;
				}
				$lib[ $id ] = $entry;
				$lib_dirty  = true;
			}

			if ( $attached !== $id ) {
				$fs['personality_library_id'] = $id;
				update_option( $key, $fs, false );
			}
		}

		if ( $lib_dirty ) {
			flosc_personality_library_save_all( $lib );
		}
		update_option( 'flosc_personality_library_promoted', '1', false );
	}
}

if ( ! function_exists( 'flosc_personality_flow_settings_for_ivr' ) ) {
	/**
	 * Pick the flow option bag that actually holds this IVR’s personality fields.
	 *
	 * @param string $ivr_filename IVR filename.
	 * @return array{option_key:string,settings:array<string,mixed>}
	 */
	function flosc_personality_flow_settings_for_ivr( $ivr_filename ) {
		$ivr_filename = basename( (string) $ivr_filename );
		$stem         = sanitize_key( pathinfo( $ivr_filename, PATHINFO_FILENAME ) );
		$candidates   = array();
		if ( function_exists( 'flosc_resolve_flow_option_key_for_ivr' ) ) {
			$candidates[] = flosc_resolve_flow_option_key_for_ivr( $ivr_filename );
		}
		$candidates[] = 'flosc_flow_' . $stem;
		if ( substr( $stem, -4 ) === '_ivr' ) {
			$candidates[] = 'flosc_flow_' . substr( $stem, 0, -4 );
		}
		$best_key   = '';
		$best       = array();
		$best_score = -1;
		$seen       = array();
		foreach ( $candidates as $key ) {
			$key = (string) $key;
			if ( $key === '' || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$fs           = get_option( $key, array() );
			if ( ! is_array( $fs ) ) {
				continue;
			}
			$score  = 0;
			$score += trim( (string) ( $fs['ai_personality_name'] ?? '' ) ) !== '' ? 80 : 0;
			$score += trim( (string) ( $fs['ai_base_prompt'] ?? '' ) ) !== '' ? 80 : 0;
			$score += trim( (string) ( $fs['ai_personality_role'] ?? '' ) ) !== '' ? 20 : 0;
			$ident  = '';
			if ( isset( $fs['identity'] ) && is_array( $fs['identity'] ) ) {
				$ident = trim( (string) ( $fs['identity']['name'] ?? '' ) );
			}
			if ( $ident === '' ) {
				$ident = trim( (string) ( $fs['name'] ?? '' ) );
			}
			$score += $ident !== '' ? 20 : 0;
			if ( $score > $best_score ) {
				$best_score = $score;
				$best_key   = $key;
				$best       = $fs;
			}
		}
		return array(
			'option_key' => $best_key,
			'settings'   => $best,
		);
	}
}

if ( ! function_exists( 'flosc_personality_library_id_for_flow' ) ) {
	/**
	 * Attached library id for a flow, including the Br3nda → dainis.net mapping.
	 *
	 * @param string|null $flow_id Flow stem.
	 * @return string
	 */
	function flosc_personality_library_id_for_flow( $flow_id = null ) {
		$pid = '';
		if ( function_exists( 'flosc_get_setting' ) ) {
			$pid = sanitize_key( (string) flosc_get_setting( 'personality_library_id', '', $flow_id ) );
		}
		if ( $pid !== '' ) {
			return $pid;
		}
		$stem = sanitize_key( (string) $flow_id );
		if ( $stem === '' && function_exists( 'flosc' ) ) {
			$inst = flosc();
			if ( is_object( $inst ) && method_exists( $inst, 'get_current_flow' ) ) {
				$flow = $inst->get_current_flow();
				if ( is_array( $flow ) ) {
					$ivr  = (string) ( $flow['ivr_file'] ?? $flow['id'] ?? '' );
					$stem = sanitize_key( pathinfo( basename( $ivr ), PATHINFO_FILENAME ) );
					if ( $stem === '' ) {
						$stem = sanitize_key( (string) ( $flow['id'] ?? '' ) );
					}
				}
			}
		}
		if ( $stem === '' || ! function_exists( 'flosc_implied_personality_library_id' ) ) {
			return '';
		}
		return flosc_implied_personality_library_id( $stem );
	}
}

add_action( 'admin_init', 'flosc_personality_library_promote_custom_flow_voices', 30 );

if ( ! function_exists( 'flosc_ajax_save_personality_design' ) ) {
	/**
	 * AJAX: save designer output into a library row.
	 *
	 * @return void
	 */
	function flosc_ajax_save_personality_design() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to save personalities.', 'flosc' ) ), 403 );
		}
		check_ajax_referer( 'flosc_personality_design', 'nonce' );

		$id = isset( $_POST['persona_id'] ) ? sanitize_key( wp_unslash( (string) $_POST['persona_id'] ) ) : '';
		if ( $id === '' ) {
			wp_send_json_error( array( 'message' => __( 'Missing personality id.', 'flosc' ) ), 400 );
		}

		$fields = array();
		if ( isset( $_POST['label'] ) ) {
			$fields['label'] = sanitize_text_field( wp_unslash( (string) $_POST['label'] ) );
		}
		if ( isset( $_POST['ai_personality_name'] ) ) {
			$fields['ai_personality_name'] = sanitize_text_field( wp_unslash( (string) $_POST['ai_personality_name'] ) );
		}
		if ( isset( $_POST['ai_personality_role'] ) ) {
			$fields['ai_personality_role'] = sanitize_text_field( wp_unslash( (string) $_POST['ai_personality_role'] ) );
		}
		if ( isset( $_POST['ai_base_prompt'] ) && is_string( $_POST['ai_base_prompt'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- flosc_sanitize_personality_profile_text keeps Markdown.
			$fields['ai_base_prompt'] = flosc_sanitize_personality_profile_text( wp_unslash( $_POST['ai_base_prompt'] ) );
		}
		if ( isset( $_POST['workshop_json'] ) && is_string( $_POST['workshop_json'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- flosc_sanitize_personality_workshop validates JSON object.
			$workshop_raw             = wp_unslash( $_POST['workshop_json'] );
			$fields['workshop_json'] = flosc_sanitize_personality_workshop( $workshop_raw );
			if ( $fields['workshop_json'] === '' && trim( $workshop_raw ) !== '' ) {
				wp_send_json_error( array( 'message' => __( 'Workshop file was not valid JSON.', 'flosc' ) ), 400 );
			}
		}

		if ( ! flosc_personality_library_update_entry( $id, $fields ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not save that personality.', 'flosc' ) ), 500 );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Personality saved to the FLOSC library.', 'flosc' ),
				'id'      => $id,
			)
		);
	}
	add_action( 'wp_ajax_flosc_save_personality_design', 'flosc_ajax_save_personality_design' );
}

if ( ! function_exists( 'flosc_personality_builder_boot_json' ) ) {
	/**
	 * Config object injected into the designer page.
	 *
	 * @param string $persona_id Library id.
	 * @param string $ivr        Optional IVR filename.
	 * @return array<string,mixed>
	 */
	function flosc_personality_builder_boot_json( $persona_id, $ivr = '' ) {
		$entry = flosc_personality_library_get( $persona_id );
		if ( ! is_array( $entry ) ) {
			$entry = array(
				'id'                  => $persona_id,
				'label'               => $persona_id,
				'ai_personality_name' => '',
				'ai_personality_role' => '',
				'ai_base_prompt'      => '',
				'workshop_json'       => '',
			);
		}
		$workshop = null;
		if ( ! empty( $entry['workshop_json'] ) ) {
			$decoded = json_decode( (string) $entry['workshop_json'], true );
			if ( is_array( $decoded ) ) {
				$workshop = $decoded;
			}
		}
		return array(
			'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'flosc_personality_design' ),
			'personaId'         => $persona_id,
			'libraryUrl'        => flosc_personality_library_url( $ivr ),
			'hideProviderPacks' => true,
			'i18n'              => array(
				'saving' => __( 'Saving…', 'flosc' ),
				'saved'  => __( 'Saved to library', 'flosc' ),
				'error'  => __( 'Could not save. Try again.', 'flosc' ),
			),
			'entry'             => array(
				'id'      => $persona_id,
				'label'   => isset( $entry['label'] ) ? (string) $entry['label'] : $persona_id,
				'name'    => isset( $entry['ai_personality_name'] ) ? (string) $entry['ai_personality_name'] : '',
				'role'    => isset( $entry['ai_personality_role'] ) ? (string) $entry['ai_personality_role'] : '',
				'profile' => isset( $entry['ai_base_prompt'] ) ? (string) $entry['ai_base_prompt'] : '',
			),
			'workshop'          => $workshop,
		);
	}
}

if ( ! function_exists( 'flosc_enqueue_personality_builder_assets' ) ) {
	/**
	 * Enqueue designer bridge on AI → This flow.
	 *
	 * @return void
	 */
	function flosc_enqueue_personality_builder_assets() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$ctx     = function_exists( 'flosc_personality_builder_request_context' ) ? flosc_personality_builder_request_context() : array();
		$persona = isset( $ctx['persona'] ) ? (string) $ctx['persona'] : '';
		$ivr     = isset( $ctx['ivr'] ) ? (string) $ctx['ivr'] : '';
		// Do NOT bail when persona resolves empty. The accordion markup renders
		// regardless (its persona comes from the current flow's saved settings —
		// a DIFFERENT resolution than this request-context re-derivation, and the
		// two can disagree). Bailing here shipped a page with full static markup
		// but no CSS and no JS: empty #cols/#editor panels and a silent console.
		// With an empty persona the boot JSON still builds a valid default entry
		// and the save bridge refuses gracefully, so loading assets is always safe.

		$css_path = FLOSC_PLUGIN_DIR . 'assets/css/flosc-personality-builder.css';
		$js_path  = FLOSC_PLUGIN_DIR . 'assets/js/flosc-personality-builder.js';
		$wp_path  = FLOSC_PLUGIN_DIR . 'assets/js/flosc-personality-builder-wp.js';
		if ( ! file_exists( $js_path ) || ! file_exists( $wp_path ) ) {
			return;
		}
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				'flosc-personality-builder',
				FLOSC_PLUGIN_URL . 'assets/css/flosc-personality-builder.css',
				array( 'flosc-admin' ),
				(string) filemtime( $css_path )
			);
		}
		wp_enqueue_script(
			'flosc-personality-builder',
			FLOSC_PLUGIN_URL . 'assets/js/flosc-personality-builder.js',
			array(),
			(string) filemtime( $js_path ),
			true
		);
		$boot       = flosc_personality_builder_boot_json( $persona, $ivr );
		$json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
		wp_add_inline_script(
			'flosc-personality-builder',
			'window.floscPersonalityWp = ' . wp_json_encode( $boot, $json_flags ) . ';',
			'before'
		);
		wp_enqueue_script(
			'flosc-personality-builder-wp',
			FLOSC_PLUGIN_URL . 'assets/js/flosc-personality-builder-wp.js',
			array( 'flosc-personality-builder' ),
			(string) filemtime( $wp_path ),
			true
		);
	}
}

if ( ! function_exists( 'flosc_render_personality_designer_accordion' ) ) {
	/**
	 * Personality Designer workshop as an AI-tab accordion.
	 *
	 * @param string $persona_id Library id.
	 * @param string $ivr        Optional IVR filename.
	 * @return void
	 */
	function flosc_render_personality_designer_accordion( $persona_id, $ivr = '' ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$persona_id = sanitize_key( (string) $persona_id );
		$ivr        = sanitize_file_name( (string) $ivr );
		$entry      = ( $persona_id !== '' && function_exists( 'flosc_personality_library_get' ) ) ? flosc_personality_library_get( $persona_id ) : null;
		$label      = '';
		if ( is_array( $entry ) && isset( $entry['label'] ) && (string) $entry['label'] !== '' ) {
			$label = (string) $entry['label'];
		} elseif ( $persona_id !== '' ) {
			$label = $persona_id;
		}
		?>
<details class="flosc-ai-acc flosc-ai-acc--designer" id="flosc-personality-designer" open>
<summary class="flosc-ai-acc__summary">
	<span class="flosc-ai-acc__title"><?php echo esc_html__( 'Personality Designer', 'flosc' ); ?></span>
	<span class="flosc-ai-acc__hint"><?php echo $label !== ''
		? esc_html( sprintf( /* translators: %s: attached personality label */ __( 'This flow: %s', 'flosc' ), $label ) )
		: esc_html__( 'Attach a library personality above to design it here.', 'flosc' ); ?></span>
</summary>
<div class="flosc-ai-acc__body">
	<?php if ( $persona_id !== '' ) : ?>
	<p class="flosc-personality-builder-toolbar">
		<button type="button" class="button button-primary" id="flosc-personality-builder-save">
			<?php
			echo $label !== ''
				? esc_html( sprintf( /* translators: %s: personality name */ __( 'Save changes to %s', 'flosc' ), $label ) )
				: esc_html__( 'Save to FLOSC library', 'flosc' );
			?>
		</button>
		<span id="flosc-personality-builder-status" class="flosc-personality-builder-status" role="status" aria-live="polite"></span>
	</p>
		<?php
		flosc_render_personality_designer_canvas( $persona_id, $ivr );
	else :
		echo '<p>' . esc_html__( 'Attach one library personality on this flow. The designer follows that selection.', 'flosc' ) . '</p>';
	endif;
	?>
</div>
</details>
		<?php
	}
}

if ( ! function_exists( 'flosc_admin_personality_builder_page' ) ) {
	/**
	 * Legacy slug: send leftover bookmarks to the AI-tab accordion.
	 *
	 * @return void
	 */
	function flosc_admin_personality_builder_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to design personalities.', 'flosc' ) );
		}
		$ctx = flosc_personality_builder_request_context();
		wp_safe_redirect( flosc_personality_builder_url( $ctx['persona'], $ctx['ivr'] ) );
		exit;
	}
}

if ( ! function_exists( 'flosc_personality_builder_admin_body_class' ) ) {
	/**
	 * @param string $classes Body classes.
	 * @return string
	 */
	function flosc_personality_builder_admin_body_class( $classes ) {
		$page_raw = filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW );
		$page     = is_string( $page_raw ) ? sanitize_key( wp_unslash( $page_raw ) ) : '';
		$tab_raw  = filter_input( INPUT_GET, 'tab', FILTER_UNSAFE_RAW );
		$tab      = is_string( $tab_raw ) ? sanitize_key( wp_unslash( $tab_raw ) ) : '';
		$view_raw = filter_input( INPUT_GET, 'view', FILTER_UNSAFE_RAW );
		$view     = is_string( $view_raw ) ? sanitize_key( wp_unslash( $view_raw ) ) : '';
		if ( $page === 'flosc-settings' && $tab === 'ai' && $view !== 'all' ) {
			$classes .= ' flosc-personality-builder-admin';
		}
		return $classes;
	}
	add_filter( 'admin_body_class', 'flosc_personality_builder_admin_body_class' );
}

if ( ! function_exists( 'flosc_redirect_nested_personality_designer' ) ) {
	/**
	 * Old designer page and view=design URLs go to the AI-tab accordion.
	 *
	 * @return void
	 */
	function flosc_redirect_nested_personality_designer() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$page_raw = filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW );
		$page     = is_string( $page_raw ) ? sanitize_key( wp_unslash( $page_raw ) ) : '';
		$tab_raw  = filter_input( INPUT_GET, 'tab', FILTER_UNSAFE_RAW );
		$tab      = is_string( $tab_raw ) ? sanitize_key( wp_unslash( $tab_raw ) ) : '';
		$view_raw = filter_input( INPUT_GET, 'view', FILTER_UNSAFE_RAW );
		$view     = is_string( $view_raw ) ? sanitize_key( wp_unslash( $view_raw ) ) : '';
		$legacy   = ( $page === 'flosc-personality-builder' ) || ( $page === 'flosc-settings' && $tab === 'ai' && $view === 'design' );
		if ( ! $legacy ) {
			return;
		}
		$ctx = flosc_personality_builder_request_context();
		wp_safe_redirect( flosc_personality_builder_url( $ctx['persona'], $ctx['ivr'] ) );
		exit;
	}
	add_action( 'admin_init', 'flosc_redirect_nested_personality_designer', 1 );
}

if ( ! function_exists( 'flosc_render_personality_designer_canvas' ) ) {
	/**
	 * Workshop markup on the FLOSC admin page. Caller already checked caps.
	 *
	 * @param string $persona_id Library id.
	 * @param string $ivr        Optional IVR filename.
	 * @return void
	 */
	function flosc_render_personality_designer_canvas( $persona_id, $ivr = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- signature kept for callers.
		$markup = FLOSC_PLUGIN_DIR . 'assets/personality-builder/flosc-personality-builder-markup.php';
		if ( ! is_readable( $markup ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The personality designer file is missing.', 'flosc' ) . '</p></div>';
			return;
		}
		echo '<div class="flosc-personality-workshop is-hosted">';
		include $markup;
		echo '</div>';
	}
}
