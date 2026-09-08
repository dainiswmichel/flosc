<?php
/**
 * Content-item freeline schema (platform keys).
 *
 * Replaces free_lesson_* / lessons_category / lesson_groups freeline identifiers.
 * Product nouns (lesson, recipe, …) live in content_types labels only.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Map: canonical (new) key => legacy (old) key for one-time migrate + dual-read.
 *
 * @return array<string,string>
 */
function flosc_content_item_option_key_map() {
	return array(
		'free_content_item_count'          => 'free_lesson_count',
		'free_content_item_pool_category'  => 'free_lesson_pool_category',
		'free_content_item_mode'           => 'free_lesson_mode',
		'free_content_item_proportion'     => 'free_lesson_proportion',
		'free_content_item_guaranteed'     => 'free_lesson_guaranteed',
		'exclude_items_from_freeline'      => 'free_lesson_never_free',
		'content_item_category'            => 'lessons_category',
		'content_item_groups'              => 'lesson_groups',
	);
}

/**
 * User-meta: new => old.
 *
 * @return array<string,string>
 */
function flosc_content_item_user_meta_key_map() {
	return array(
		'_flosc_free_content_item_delivered' => '_flosc_free_lesson_delivered',
		'_flosc_free_content_item_number'    => '_flosc_free_lesson_number',
		'_flosc_free_content_item_numbers'   => '_flosc_free_lesson_numbers',
		'_flosc_free_content_item_id'        => '_flosc_free_lesson_id',
		'_flosc_free_content_item_quiz_id'   => '_flosc_free_lesson_quiz_id',
		'_flosc_free_content_item_offered'   => '_flosc_free_lesson_offered',
	);
}

/**
 * Whether a flow settings value is "present" for migrate-from-legacy.
 *
 * @param mixed $value
 * @return bool
 */
function flosc_content_item_value_present( $value ) {
	if ( $value === null || $value === '' ) {
		return false;
	}
	if ( is_array( $value ) && $value === array() ) {
		return false;
	}
	return true;
}

/**
 * Normalize flow option bag to content-item keys; drop legacy freeline keys.
 *
 * @param array  $fs          Flow settings.
 * @param string $option_key  If non-empty and bag changed, persist via update_option.
 * @return array
 */
function flosc_normalize_content_item_flow_settings( array $fs, $option_key = '' ) {
	$map     = flosc_content_item_option_key_map();
	$changed = false;

	foreach ( $map as $new_key => $old_key ) {
		$has_new = array_key_exists( $new_key, $fs ) && flosc_content_item_value_present( $fs[ $new_key ] );
		$has_old = array_key_exists( $old_key, $fs ) && flosc_content_item_value_present( $fs[ $old_key ] );

		if ( ! $has_new && $has_old ) {
			$fs[ $new_key ] = $fs[ $old_key ];
			$changed        = true;
		}
		if ( array_key_exists( $old_key, $fs ) ) {
			unset( $fs[ $old_key ] );
			$changed = true;
		}
	}

	// Legacy single category already mapped into content_item_category; ensure groups exist.
	if ( empty( $fs['content_item_groups'] ) && ! empty( $fs['content_item_category'] ) ) {
		$fs['content_item_groups'] = array(
			array(
				'quiz_id'  => '',
				'category' => sanitize_title( (string) $fs['content_item_category'] ),
			),
		);
		$changed = true;
	}
	if ( ! empty( $fs['content_item_groups'] ) && is_array( $fs['content_item_groups'] ) && empty( $fs['content_item_category'] ) ) {
		$first = reset( $fs['content_item_groups'] );
		if ( is_array( $first ) && ! empty( $first['category'] ) ) {
			$fs['content_item_category'] = sanitize_title( (string) $first['category'] );
			$changed                     = true;
		}
	}

	if ( $changed && $option_key !== '' && strpos( (string) $option_key, 'flosc_flow_' ) === 0 ) {
		update_option( $option_key, $fs, false );
	}

	return $fs;
}

/**
 * Resolve setting key for reads: accept legacy key name, return canonical.
 *
 * @param string $key
 * @return string
 */
function flosc_content_item_canonical_option_key( $key ) {
	$key = (string) $key;
	$map = flosc_content_item_option_key_map();
	if ( isset( $map[ $key ] ) ) {
		return $key;
	}
	$flip = array_flip( $map );
	if ( isset( $flip[ $key ] ) ) {
		return $flip[ $key ];
	}
	return $key;
}

/**
 * get_user_meta with legacy freeline meta fallback.
 *
 * @param int    $user_id
 * @param string $new_key Canonical meta key.
 * @param bool   $single
 * @return mixed
 */
function flosc_content_item_get_user_meta( $user_id, $new_key, $single = true ) {
	$user_id = absint( $user_id );
	$new_key = (string) $new_key;
	$val     = get_user_meta( $user_id, $new_key, $single );
	if ( flosc_content_item_value_present( $val ) ) {
		return $val;
	}
	$map = flosc_content_item_user_meta_key_map();
	$old = $map[ $new_key ] ?? '';
	if ( $old === '' ) {
		return $val;
	}
	$legacy = get_user_meta( $user_id, $old, $single );
	if ( flosc_content_item_value_present( $legacy ) ) {
		return $legacy;
	}
	return $val;
}

/**
 * update_user_meta for freeline state (writes new key only).
 *
 * @param int    $user_id
 * @param string $new_key
 * @param mixed  $value
 * @return int|bool
 */
function flosc_content_item_update_user_meta( $user_id, $new_key, $value ) {
	return update_user_meta( absint( $user_id ), (string) $new_key, $value );
}
