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
			),
		);
	}
}

if ( ! function_exists( 'flosc_personality_library_get_all' ) ) {
	/**
	 * @return array<string,array<string,string>>
	 */
	function flosc_personality_library_get_all() {
		$raw = get_option( flosc_personality_library_option_key(), null );
		if ( ! is_array( $raw ) || $raw === array() ) {
			$raw = flosc_personality_library_defaults();
			update_option( flosc_personality_library_option_key(), $raw, false );
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
		$clean = array();
		foreach ( $library as $id => $row ) {
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
				$val = isset( $row[ $fk ] ) ? (string) $row[ $fk ] : '';
				// Long fields: textarea sanitize.
				if ( in_array( $fk, array( 'ai_base_prompt', 'ai_mission', 'ai_boundaries', 'ai_topic_scope', 'ai_off_topic_message', 'ai_off_topic_links' ), true ) ) {
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

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$persona = isset( $_POST['persona'] ) && is_array( $_POST['persona'] ) ? wp_unslash( $_POST['persona'] ) : array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$delete  = isset( $_POST['persona_delete'] ) && is_array( $_POST['persona_delete'] ) ? wp_unslash( $_POST['persona_delete'] ) : array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$new_id  = isset( $_POST['new_persona_id'] ) ? sanitize_key( wp_unslash( (string) $_POST['new_persona_id'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$new_lab = isset( $_POST['new_persona_label'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['new_persona_label'] ) ) : '';

		$lib = array();
		foreach ( $persona as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = sanitize_key( (string) ( $row['id'] ?? '' ) );
			if ( $id === '' || ! empty( $delete[ $id ] ) ) {
				continue;
			}
			$lib[ $id ] = $row;
			$lib[ $id ]['id'] = $id;
		}

		if ( $new_id !== '' && ! isset( $lib[ $new_id ] ) ) {
			$lib[ $new_id ] = array(
				'id'    => $new_id,
				'label' => $new_lab !== '' ? $new_lab : $new_id,
			);
			foreach ( flosc_personality_library_field_keys() as $fk ) {
				$lib[ $new_id ][ $fk ] = '';
			}
		}

		if ( $lib === array() ) {
			$lib = flosc_personality_library_defaults();
		}
		flosc_personality_library_save_all( $lib );

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
