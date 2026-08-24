<?php
/**
 * FLOSC Personality Storage — WordPress-native home for the designer tree.
 *
 * One hierarchical taxonomy holds each personality as a root term. Every
 * container (the eleven standard layers, plus admin-made clouds, rainclouds,
 * and pools) is a descendant term. Every Topic (aspect = post) is a
 * flosc_topic post filed under exactly one term.
 *
 * Density attaches to the heading: containers carry theirs in term meta,
 * topics in menu_order (with an optional exact-decimal override in post
 * meta). Subdensity is the same slot read as "order within my immediate
 * parent heading" when the parent is a cloud.
 *
 * The sync is additive only: it creates and updates; it never deletes. A
 * personality that has never been saved from the designer simply has no
 * terms yet.
 *
 * @package flosc
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FLOSC_PERSONALITY_TAXONOMY = 'flosc_personality';
const FLOSC_TOPIC_POST_TYPE      = 'flosc_topic';

/**
 * Register the personality taxonomy and the topic post type.
 */
function flosc_personality_storage_register() {
	register_taxonomy(
		FLOSC_PERSONALITY_TAXONOMY,
		array( FLOSC_TOPIC_POST_TYPE ),
		array(
			'labels'            => array(
				'name'          => __( 'Personalities', 'flosc' ),
				'singular_name' => __( 'Personality', 'flosc' ),
			),
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_in_menu'      => false,
			'show_in_rest'      => true,
			'public'            => false,
			'capabilities'      => array( 'manage_terms' => 'manage_options', 'edit_terms' => 'manage_options', 'delete_terms' => 'manage_options', 'assign_terms' => 'edit_posts' ),
		)
	);

	register_post_type(
		FLOSC_TOPIC_POST_TYPE,
		array(
			'labels'       => array(
				'name'          => __( 'Topics', 'flosc' ),
				'singular_name' => __( 'Topic', 'flosc' ),
			),
			'public'       => false,
			'show_ui'      => false,
			'show_in_rest' => true,
			'supports'     => array( 'title', 'editor', 'page-attributes' ),
			'capabilities' => array( 'create_posts' => 'manage_options', 'edit_posts' => 'manage_options', 'edit_others_posts' => 'manage_options', 'publish_posts' => 'manage_options', 'read_private_posts' => 'manage_options', 'delete_posts' => 'manage_options' ),
			'map_meta_cap' => false,
		)
	);
}
add_action( 'init', 'flosc_personality_storage_register' );

/**
 * Register the meta keys this subsystem reads and writes.
 */
function flosc_personality_storage_meta() {
	$term_meta = array( '_flosc_density', '_flosc_gain', '_flosc_origin', '_flosc_kind' );
	foreach ( $term_meta as $key ) {
		register_term_meta( FLOSC_PERSONALITY_TAXONOMY, $key, array( 'show_in_rest' => true, 'single' => true, 'type' => 'string' ) );
	}

	$post_meta = array( '_flosc_short', '_flosc_character_note', '_flosc_binding', '_flosc_gain', '_flosc_shape2', '_flosc_shape3', '_flosc_trajectory', '_flosc_hue', '_flosc_on', '_flosc_density_exact', '_flosc_trib_id' );
	foreach ( $post_meta as $key ) {
		register_post_meta( FLOSC_TOPIC_POST_TYPE, $key, array( 'show_in_rest' => true, 'single' => true, 'type' => 'string' ) );
	}
}
add_action( 'init', 'flosc_personality_storage_meta' );

/**
 * Find or create the root term for a personality library row.
 *
 * @param string $plid Personality id (library row key).
 * @return int|WP_Error Term id.
 */
function flosc_personality_root_term( $plid ) {
	$plid   = sanitize_key( (string) $plid );
	$existing = term_exists( $plid, FLOSC_PERSONALITY_TAXONOMY );
	if ( $existing && ! is_wp_error( $existing ) ) {
		return is_array( $existing ) ? (int) $existing['term_id'] : (int) $existing;
	}
	$created = wp_insert_term( $plid, FLOSC_PERSONALITY_TAXONOMY, array( 'slug' => $plid ) );
	if ( is_wp_error( $created ) ) {
		return $created;
	}
	return (int) $created['term_id'];
}

/**
 * Find or create one container term beneath a parent.
 *
 * @param int    $parent_id Parent term id.
 * @param string $slug      Container id (stable key from the designer).
 * @param string $name      Heading name.
 * @param string $desc      Description paragraph.
 * @param array  $meta      density / gain / origin / kind values.
 * @return int|WP_Error Term id.
 */
function flosc_personality_container_term( $parent_id, $slug, $name, $desc, $meta ) {
	$existing = get_term_by( 'slug', sanitize_title( $slug ), FLOSC_PERSONALITY_TAXONOMY );
	if ( $existing && ! is_wp_error( $existing ) && (int) $existing->parent === (int) $parent_id ) {
		$term_id = (int) $existing->term_id;
	} else {
		$created = wp_insert_term( $name, FLOSC_PERSONALITY_TAXONOMY, array(
			'slug'        => sanitize_title( $slug ),
			'parent'      => (int) $parent_id,
			'description' => (string) $desc,
		) );
		if ( is_wp_error( $created ) ) {
			return $created;
		}
		$term_id = (int) $created['term_id'];
	}
	wp_update_term( $term_id, FLOSC_PERSONALITY_TAXONOMY, array(
		'name'        => $name,
		'description' => (string) $desc,
		'parent'      => (int) $parent_id,
	) );
	update_term_meta( $term_id, '_flosc_density', (string) ( isset( $meta['density'] ) ? $meta['density'] : 0 ) );
	update_term_meta( $term_id, '_flosc_gain', (string) ( isset( $meta['gain'] ) ? $meta['gain'] : 0 ) );
	update_term_meta( $term_id, '_flosc_origin', (string) ( isset( $meta['origin'] ) ? $meta['origin'] : 'user' ) );
	update_term_meta( $term_id, '_flosc_kind', (string) ( isset( $meta['kind'] ) ? $meta['kind'] : 'layer' ) );
	return $term_id;
}

/**
 * Find or create the topic post for one designer aspect and sync its fields.
 *
 * @param int    $root_id Root personality term id.
 * @param int    $container_term_id Immediate parent container term id.
 * @param string $trib_id Stable aspect id from the designer.
 * @param array  $t       Aspect fields (label, instruction, density, gain, ...).
 * @return int|WP_Error Post id.
 */
function flosc_personality_sync_topic( $root_id, $container_term_id, $trib_id, $t ) {
	$existing = get_posts( array(
		'post_type'      => FLOSC_TOPIC_POST_TYPE,
		'meta_key'       => '_flosc_trib_id',
		'meta_value'     => (string) $trib_id,
		'post_status'    => 'any',
		'numberposts'    => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	if ( ! empty( $existing ) ) {
		$post_id = (int) $existing[0];
	} else {
		$post_id = (int) wp_insert_post( array(
			'post_type'   => FLOSC_TOPIC_POST_TYPE,
			'post_status' => 'publish',
			'post_author' => get_current_user_id(),
		), true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		update_post_meta( $post_id, '_flosc_trib_id', (string) $trib_id );
	}

	$density = isset( $t['density'] ) && is_numeric( $t['density'] ) ? (float) $t['density'] : 0;
	wp_update_post( array(
		'ID'           => $post_id,
		'post_title'   => (string) ( isset( $t['label'] ) ? $t['label'] : $trib_id ),
		'post_content' => (string) ( isset( $t['instruction'] ) ? $t['instruction'] : '' ),
		'menu_order'   => (int) floor( $density ),
	) );
	update_post_meta( $post_id, '_flosc_density_exact', (string) $density );
	update_post_meta( $post_id, '_flosc_short', (string) ( isset( $t['short'] ) ? $t['short'] : '' ) );
	$comments = isset( $t['comments'] ) && is_array( $t['comments'] ) ? $t['comments'] : array();
	update_post_meta( $post_id, '_flosc_character_note', (string) ( isset( $comments['character'] ) ? $comments['character'] : '' ) );
	update_post_meta( $post_id, '_flosc_binding', (string) ( isset( $t['binding'] ) ? $t['binding'] : '' ) );
	update_post_meta( $post_id, '_flosc_gain', (string) ( isset( $t['gain'] ) ? $t['gain'] : '' ) );
	update_post_meta( $post_id, '_flosc_shape2', (string) ( isset( $t['shape_2d'] ) ? $t['shape_2d'] : '' ) );
	update_post_meta( $post_id, '_flosc_shape3', (string) ( isset( $t['shape_3d'] ) ? $t['shape_3d'] : '' ) );
	update_post_meta( $post_id, '_flosc_trajectory', (string) ( isset( $t['trajectory'] ) ? $t['trajectory'] : '' ) );
	update_post_meta( $post_id, '_flosc_hue', (string) ( isset( $t['hue'] ) ? $t['hue'] : '' ) );
	update_post_meta( $post_id, '_flosc_on', empty( $t['on'] ) ? '0' : '1' );

	wp_set_object_terms( $post_id, array( (int) $container_term_id ), FLOSC_PERSONALITY_TAXONOMY, false );
	return $post_id;
}

/**
 * Sync one saved designer genome into WordPress terms and posts.
 *
 * Additive only: containers become child terms, topics become posts under
 * their immediate container term, clouds become grandchild terms whose
 * members live beneath them. Nothing is deleted.
 *
 * @param string $plid Personality id.
 * @param array  $shop Decoded workshop payload (containers/placement/clouds/tributaries).
 * @return true|WP_Error
 */
function flosc_personality_sync_tree( $plid, $shop ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return new WP_Error( 'flosc_forbidden', __( 'Administrator capability required.', 'flosc' ) );
	}
	if ( ! is_array( $shop ) ) {
		return new WP_Error( 'flosc_payload', __( 'Workshop payload missing.', 'flosc' ) );
	}
	$root = flosc_personality_root_term( $plid );
	if ( is_wp_error( $root ) ) {
		return $root;
	}

	$containers  = isset( $shop['containers'] ) && is_array( $shop['containers'] ) ? $shop['containers'] : array();
	$placement   = isset( $shop['placement'] ) && is_array( $shop['placement'] ) ? $shop['placement'] : array();
	$clouds      = isset( $shop['clouds'] ) && is_array( $shop['clouds'] ) ? $shop['clouds'] : array();
	$tributaries = array();
	if ( isset( $shop['tributaries'] ) && is_array( $shop['tributaries'] ) ) {
		foreach ( $shop['tributaries'] as $t ) {
			if ( isset( $t['id'] ) ) {
				$tributaries[ (string) $t['id'] ] = $t;
			}
		}
	}

	/* Pass 1: every container becomes a direct child of the root. */
	$term_for_container = array();
	foreach ( $containers as $c ) {
		if ( empty( $c['id'] ) ) {
			continue;
		}
		$cid = (string) $c['id'];
		$term = flosc_personality_container_term( $root, $cid, (string) ( isset( $c['label'] ) ? $c['label'] : $cid ), (string) ( isset( $c['desc'] ) ? $c['desc'] : '' ), array(
			'density' => isset( $c['density'] ) ? $c['density'] : 0,
			'gain'    => isset( $c['gain'] ) ? $c['gain'] : 0,
			'origin'  => isset( $c['origin'] ) ? $c['origin'] : 'user',
			'kind'    => isset( $c['kind'] ) ? $c['kind'] : 'layer',
		) );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		$term_for_container[ $cid ] = (int) $term;
	}

	/* Pass 2: clouds nest inside their parent layer term. */
	$term_for_cloud = array();
	foreach ( $clouds as $cl ) {
		if ( empty( $cl['id'] ) ) {
			continue;
		}
		$cid    = (string) $cl['id'];
		$parent = isset( $cl['parent'] ) && isset( $term_for_container[ (string) $cl['parent'] ] )
			? $term_for_container[ (string) $cl['parent'] ]
			: $root;
		$members = isset( $cl['members'] ) && is_array( $cl['members'] ) ? $cl['members'] : array();
		$density = isset( $cl['density'] ) && is_numeric( $cl['density'] ) ? (float) $cl['density'] : null;
		if ( null === $density && $members ) {
			$ds = array();
			foreach ( $members as $mid ) {
				if ( isset( $tributaries[ (string) $mid ] ['density'] ) && is_numeric( $tributaries[ (string) $mid ]['density'] ) ) {
					$ds[] = (float) $tributaries[ (string) $mid ]['density'];
				}
			}
			$density = $ds ? min( $ds ) : 0;
		}
		$term = flosc_personality_container_term( $parent, $cid, (string) ( isset( $cl['name'] ) ? $cl['name'] : $cid ), (string) ( isset( $cl['explanation'] ) ? $cl['explanation'] : '' ), array(
			'density' => $density,
			'gain'    => isset( $cl['gain'] ) ? $cl['gain'] : 0,
			'origin'  => 'user',
			'kind'    => 'cloud',
		) );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		$term_for_cloud[ $cid ] = (int) $term;
	}

	/* Pass 3: topics file under their immediate heading. */
	foreach ( $tributaries as $tid => $t ) {
		$home = isset( $placement[ $tid ] ) ? (string) $placement[ $tid ] : '';
		$container_term = $root;
		if ( 0 === strpos( $home, 'cloud:' ) && isset( $term_for_cloud[ substr( $home, 6 ) ] ) ) {
			$container_term = $term_for_cloud[ substr( $home, 6 ) ];
		} elseif ( 0 === strpos( $home, 'layer:' ) && isset( $term_for_container[ substr( $home, 6 ) ] ) ) {
			$container_term = $term_for_container[ substr( $home, 6 ) ];
		}
		$synced = flosc_personality_sync_topic( $root, $container_term, (string) $tid, is_array( $t ) ? $t : array() );
		if ( is_wp_error( $synced ) ) {
			return $synced;
		}
	}

	return true;
}

/**
 * Read a mirrored personality tree back as designer-shaped arrays.
 *
 * Returns array( 'containers' => […], 'placement' => … ) or null when this
 * personality has never been mirrored. The boot handler uses it to hand the
 * designer the same tree wp-admin shows.
 *
 * @param string $plid Personality id.
 * @return array|null
 */
function flosc_personality_read_tree_overlay( $plid ) {
	$root = flosc_personality_root_term( $plid );
	if ( is_wp_error( $root ) ) {
		return null;
	}
	$children = get_terms( array(
		'taxonomy'   => FLOSC_PERSONALITY_TAXONOMY,
		'parent'     => $root,
		'hide_empty' => false,
	) );
	if ( is_wp_error( $children ) || empty( $children ) ) {
		return null;
	}

	$containers = array();
	$cloud_slugs = array();
	foreach ( $children as $term ) {
		$containers[] = array(
			'id'      => $term->slug,
			'label'   => $term->name,
			'desc'    => $term->description,
			'density' => (float) get_term_meta( $term->term_id, '_flosc_density', true ),
			'gain'    => (float) get_term_meta( $term->term_id, '_flosc_gain', true ),
			'origin'  => get_term_meta( $term->term_id, '_flosc_origin', true ),
			'kind'    => get_term_meta( $term->term_id, '_flosc_kind', true ),
		);
		$grandkids = get_terms( array(
			'taxonomy'   => FLOSC_PERSONALITY_TAXONOMY,
			'parent'     => $term->term_id,
			'hide_empty' => false,
		) );
		if ( ! is_wp_error( $grandkids ) ) {
			foreach ( $grandkids as $gk ) {
				$cloud_slugs[ $gk->slug ] = $term->slug; /* cloud → its layer */
				$containers[] = array(
					'id'      => $gk->slug,
					'label'   => $gk->name,
					'desc'    => $gk->description,
					'density' => (float) get_term_meta( $gk->term_id, '_flosc_density', true ),
					'gain'    => (float) get_term_meta( $gk->term_id, '_flosc_gain', true ),
					'origin'  => 'user',
					'kind'    => 'cloud',
				);
			}
		}
	}

	$posts = get_posts( array(
		'post_type'      => FLOSC_TOPIC_POST_TYPE,
		'numberposts'    => -1,
		'post_status'    => 'publish',
		'no_found_rows'  => true,
	) );
	$placement = array();
	foreach ( $posts as $p ) {
		$trib_id = get_post_meta( $p->ID, '_flosc_trib_id', true );
		if ( ! $trib_id ) {
			continue;
		}
		$terms = wp_get_object_terms( $p->ID, FLOSC_PERSONALITY_TAXONOMY, array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $terms ) || ! $terms ) {
			continue;
		}
		$slug = (string) $terms[0];
		if ( isset( $cloud_slugs[ $slug ] ) ) {
			$placement[ $trib_id ] = 'cloud:' . $slug;
		} else {
			$placement[ $trib_id ] = 'layer:' . $slug;
		}
	}

	return array( 'containers' => $containers, 'placement' => $placement );
}
