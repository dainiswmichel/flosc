<?php
/**
 * Named knowledge bases. Files live under uploads kb/{id}/.
 * A floscFlow attaches one or more ids (knowledge_base_ids).
 * Each file has a Visitor / Guest / Member access tier.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'flosc_knowledge_bases_option_key' ) ) {
	/**
	 * Option key for the knowledge-base library.
	 *
	 * @return string
	 */
	function flosc_knowledge_bases_option_key() {
		return 'flosc_knowledge_bases';
	}
}

if ( ! function_exists( 'flosc_knowledge_base_dir' ) ) {
	/**
	 * Directory for one knowledge base. Same layout as the former per-flow basket
	 * (kb/{id}/), so a migrated flow keeps its files in place.
	 *
	 * @param string $kb_id Knowledge base id.
	 * @return string Trailing-slash path or empty.
	 */
	function flosc_knowledge_base_dir( $kb_id ) {
		if ( function_exists( 'flosc_flow_kb_dir' ) ) {
			return flosc_flow_kb_dir( $kb_id );
		}
		return '';
	}
}

if ( ! function_exists( 'flosc_knowledge_bases_get_all' ) ) {
	/**
	 * All named knowledge bases.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function flosc_knowledge_bases_get_all() {
		$all = get_option( flosc_knowledge_bases_option_key(), array() );
		return is_array( $all ) ? $all : array();
	}
}

if ( ! function_exists( 'flosc_knowledge_bases_save_all' ) ) {
	/**
	 * Persist the knowledge-base library.
	 *
	 * @param array<string,array<string,mixed>> $all Library.
	 * @return void
	 */
	function flosc_knowledge_bases_save_all( $all ) {
		update_option( flosc_knowledge_bases_option_key(), $all, false );
	}
}

if ( ! function_exists( 'flosc_knowledge_base_get' ) ) {
	/**
	 * One knowledge base by id.
	 *
	 * @param string $kb_id Knowledge base id.
	 * @return array<string,mixed>|null
	 */
	function flosc_knowledge_base_get( $kb_id ) {
		$kb_id = sanitize_key( $kb_id );
		if ( $kb_id === '' ) {
			return null;
		}
		$all = flosc_knowledge_bases_get_all();
		return isset( $all[ $kb_id ] ) && is_array( $all[ $kb_id ] ) ? $all[ $kb_id ] : null;
	}
}

if ( ! function_exists( 'flosc_knowledge_base_normalize' ) ) {
	/**
	 * Sanitize a knowledge-base row.
	 *
	 * @param array<string,mixed> $row Raw row.
	 * @return array<string,mixed>
	 */
	function flosc_knowledge_base_normalize( $row ) {
		$id     = sanitize_key( (string) ( $row['id'] ?? '' ) );
		$access = array();
		if ( isset( $row['access'] ) && is_array( $row['access'] ) ) {
			foreach ( $row['access'] as $file => $tier ) {
				$file = sanitize_file_name( (string) $file );
				$tier = (string) $tier;
				if ( $tier === 'public' ) {
					$tier = 'visitor';
				}
				if ( $tier === 'members' ) {
					$tier = 'member';
				}
				if ( $file === '' || ! in_array( $tier, array( 'visitor', 'guest', 'member' ), true ) ) {
					continue;
				}
				$access[ $file ] = $tier;
			}
		}
		return array(
			'id'     => $id,
			'label'  => sanitize_text_field( (string) ( $row['label'] ?? $id ) ),
			'access' => $access,
		);
	}
}

if ( ! function_exists( 'flosc_knowledge_base_put' ) ) {
	/**
	 * Insert or update one knowledge base.
	 *
	 * @param array<string,mixed> $row Knowledge base.
	 * @return string Id or empty on failure.
	 */
	function flosc_knowledge_base_put( $row ) {
		$row = flosc_knowledge_base_normalize( $row );
		if ( $row['id'] === '' ) {
			return '';
		}
		$all               = flosc_knowledge_bases_get_all();
		$all[ $row['id'] ] = $row;
		flosc_knowledge_bases_save_all( $all );
		flosc_knowledge_base_dir( $row['id'] );
		return $row['id'];
	}
}

if ( ! function_exists( 'flosc_knowledge_base_file_access' ) ) {
	/**
	 * Visitor / Guest / Member tier for one file.
	 *
	 * @param string $kb_id    Knowledge base id.
	 * @param string $filename File basename.
	 * @return string visitor|guest|member
	 */
	function flosc_knowledge_base_file_access( $kb_id, $filename ) {
		$kb       = flosc_knowledge_base_get( $kb_id );
		$filename = sanitize_file_name( $filename );
		$tier     = '';
		if ( is_array( $kb ) && isset( $kb['access'][ $filename ] ) ) {
			$tier = (string) $kb['access'][ $filename ];
		}
		if ( $tier === 'public' ) {
			$tier = 'visitor';
		}
		if ( $tier === 'members' ) {
			$tier = 'member';
		}
		return in_array( $tier, array( 'visitor', 'guest', 'member' ), true ) ? $tier : 'visitor';
	}
}

if ( ! function_exists( 'flosc_knowledge_base_set_file_access' ) ) {
	/**
	 * Set Visitor / Guest / Member tier for one file.
	 *
	 * @param string $kb_id    Knowledge base id.
	 * @param string $filename File basename.
	 * @param string $tier     visitor|guest|member.
	 * @return void
	 */
	function flosc_knowledge_base_set_file_access( $kb_id, $filename, $tier ) {
		$kb_id    = sanitize_key( $kb_id );
		$filename = sanitize_file_name( $filename );
		if ( $tier === 'public' ) {
			$tier = 'visitor';
		}
		if ( $tier === 'members' ) {
			$tier = 'member';
		}
		if ( $kb_id === '' || $filename === '' || ! in_array( $tier, array( 'visitor', 'guest', 'member' ), true ) ) {
			return;
		}
		$kb = flosc_knowledge_base_get( $kb_id );
		if ( ! is_array( $kb ) ) {
			$kb = array(
				'id'     => $kb_id,
				'label'  => $kb_id,
				'access' => array(),
			);
		}
		if ( ! isset( $kb['access'] ) || ! is_array( $kb['access'] ) ) {
			$kb['access'] = array();
		}
		$kb['access'][ $filename ] = $tier;
		flosc_knowledge_base_put( $kb );
	}
}

if ( ! function_exists( 'flosc_knowledge_bases_migrate_legacy_flow' ) ) {
	/**
	 * First visit: if this flow still has files in kb/{stem}/ and no attach list, register that folder as a KB and attach it.
	 *
	 * @param string $flow_stem Flow stem.
	 * @return void
	 */
	function flosc_knowledge_bases_migrate_legacy_flow( $flow_stem ) {
		$flow_stem = sanitize_key( $flow_stem );
		if ( $flow_stem === '' ) {
			return;
		}
		$settings_key = 'flosc_flow_' . $flow_stem;
		$settings     = get_option( $settings_key, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		if ( array_key_exists( 'knowledge_base_ids', $settings ) ) {
			return;
		}

		$dir   = function_exists( 'flosc_flow_kb_dir' ) ? flosc_flow_kb_dir( $flow_stem ) : '';
		$files = array();
		if ( $dir !== '' && is_dir( $dir ) ) {
			$found = glob( $dir . '*.{md,txt}', GLOB_BRACE );
			if ( ! is_array( $found ) ) {
				$found = array();
			}
			foreach ( $found as $path ) {
				$files[] = basename( $path );
			}
		}

		if ( $files === array() ) {
			$settings['knowledge_base_ids'] = array();
			update_option( $settings_key, $settings );
			return;
		}

		$kb_id = $flow_stem;
		$kb    = flosc_knowledge_base_get( $kb_id );
		if ( ! is_array( $kb ) ) {
			$label = '';
			if ( isset( $settings['identity'] ) && is_array( $settings['identity'] ) ) {
				$label = trim( (string) ( $settings['identity']['name'] ?? '' ) );
			}
			if ( $label === '' ) {
				$label = trim( (string) ( $settings['name'] ?? '' ) );
			}
			if ( $label === '' ) {
				$label = $flow_stem;
			}
			$access = array();
			foreach ( $files as $file ) {
				$tier = (string) ( $settings[ 'knowledge_access_' . md5( $file ) ] ?? 'visitor' );
				if ( $tier === 'public' ) {
					$tier = 'visitor';
				}
				if ( $tier === 'members' ) {
					$tier = 'member';
				}
				if ( ! in_array( $tier, array( 'visitor', 'guest', 'member' ), true ) ) {
					$tier = 'visitor';
				}
				$access[ $file ] = $tier;
			}
			flosc_knowledge_base_put(
				array(
					'id'     => $kb_id,
					'label'  => $label,
					'access' => $access,
				)
			);
		}

		$settings['knowledge_base_ids'] = array( $kb_id );
		update_option( $settings_key, $settings );
	}
}

if ( ! function_exists( 'flosc_flow_knowledge_base_ids' ) ) {
	/**
	 * Knowledge bases attached to this flow.
	 *
	 * @param string $flow_stem Flow stem.
	 * @return array<int,string>
	 */
	function flosc_flow_knowledge_base_ids( $flow_stem ) {
		$flow_stem = sanitize_key( $flow_stem );
		flosc_knowledge_bases_migrate_legacy_flow( $flow_stem );
		$settings = get_option( 'flosc_flow_' . $flow_stem, array() );
		$ids      = ( is_array( $settings ) && isset( $settings['knowledge_base_ids'] ) && is_array( $settings['knowledge_base_ids'] ) )
			? $settings['knowledge_base_ids']
			: array();
		$out      = array();
		foreach ( $ids as $id ) {
			$id = sanitize_key( (string) $id );
			if ( $id !== '' && ! in_array( $id, $out, true ) ) {
				$out[] = $id;
			}
		}
		return $out;
	}
}

if ( ! function_exists( 'flosc_knowledge_bases_prompt_text' ) ) {
	/**
	 * Markdown for the AI prompt: attached KBs only, VGM-filtered.
	 *
	 * @param string $flow_stem  Flow stem.
	 * @param string $user_level visitor|guest|member.
	 * @return string
	 */
	function flosc_knowledge_bases_prompt_text( $flow_stem, $user_level ) {
		$flow_stem = sanitize_key( $flow_stem );
		$ids       = flosc_flow_knowledge_base_ids( $flow_stem );
		if ( $ids === array() ) {
			return '';
		}

		$rank      = array(
			'visitor' => 0,
			'guest'   => 1,
			'member'  => 2,
		);
		$user_rank = isset( $rank[ $user_level ] ) ? $rank[ $user_level ] : 0;
		$kb_limit  = function_exists( 'flosc_get_setting' ) ? (int) flosc_get_setting( 'ai_kb_file_limit', 10000 ) : 10000;
		if ( $kb_limit < 1000 ) {
			$kb_limit = 10000;
		}

		$content = '';
		foreach ( $ids as $kb_id ) {
			$kb  = flosc_knowledge_base_get( $kb_id );
			$dir = flosc_knowledge_base_dir( $kb_id );
			if ( '' === $dir || ! is_dir( $dir ) ) {
				continue;
			}
			$kb_label = is_array( $kb ) ? (string) ( $kb['label'] ?? $kb_id ) : $kb_id;
			$found = glob( $dir . '*.{md,txt}', GLOB_BRACE );
			if ( ! is_array( $found ) ) {
				$found = array();
			}
			foreach ( $found as $path ) {
				$filename = basename( $path );
				$tier     = flosc_knowledge_base_file_access( $kb_id, $filename );
				if ( ( $rank[ $tier ] ?? 0 ) > $user_rank ) {
					continue;
				}
				$file_content = function_exists( 'flosc_fs_get_contents' ) ? flosc_fs_get_contents( $path ) : false;
				if ( false === $file_content || '' === $file_content ) {
					continue;
				}
				if ( strlen( $file_content ) > $kb_limit ) {
					$file_content = substr( $file_content, 0, $kb_limit ) . "\n[...truncated]";
				}
				$content .= '### ' . $kb_label . ' / ' . $filename . "\n" . $file_content . "\n\n";
			}
		}

		return $content;
	}
}
