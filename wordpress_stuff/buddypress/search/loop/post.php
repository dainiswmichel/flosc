<?php
/**
 * Template for displaying the search results of the post
 *
 * Overrides buddyboss-platform/bp-templates/.../search/loop/post.php
 * Child theme override: yourtheme/buddypress/search/loop/post.php
 *
 * Change from core: replaced the Divi-shortcode-prone content pipeline
 * with a direct raw-content strip approach that always produces clean text.
 *
 * @package BuddyBoss\Core
 * @since   BuddyBoss 1.0.0
 */

$search_post_id = get_the_ID();
$result         = bp_search_is_post_restricted( $search_post_id, get_current_user_id(), 'post' );
?>
<li class="bp-search-item bp-search-item_post <?php echo esc_attr( $result['post_class'] ); ?>">
	<div class="list-wrap">
		<div class="item-avatar">
			<a href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( get_the_title() ); ?>">
				<?php
				if ( $result['has_thumb'] ) {
					?>
					<img src="<?php echo esc_url( $result['post_thumbnail'] ); ?>" class="attachment-post-thumbnail size-post-thumbnail wp-post-image" alt="<?php echo esc_attr( get_the_title() ); ?>"/>
					<?php
				} else {
					?>
					<i class="bb-icon-f <?php echo esc_attr( $result['post_thumbnail'] ); ?>"></i>
					<?php
				}
				?>
			</a>
		</div>

		<div class="item">
			<h3 class="entry-title item-title">
				<a href="<?php the_permalink(); ?>"
				title="
				<?php
					echo esc_attr(
						/* translators: %s title attribute. */
						sprintf( __( 'Permalink to %s', 'buddyboss' ), the_title_attribute( 'echo=0' ) )
					);
					?>
					"
				rel="bookmark"><?php the_title(); ?></a>
			</h3>

			<div class="entry-content entry-summary">
				<?php
				// Get raw post content — bypass all filters to avoid page-builder
				// shortcode output. BuddyBoss removes builder the_content filters
				// before this query, so shortcodes would fall through as literal text.
				$raw = get_post_field( 'post_content', $search_post_id, 'raw' );

				// Primary: strip all registered shortcodes (active builders).
				$raw = strip_shortcodes( $raw );

				// Fallback regex: strip known builder shortcodes from deactivated
				// plugins whose old content remains in the database.
				// Covers: Divi (et_pb), WPBakery (vc), Beaver Builder (fl_builder),
				//         Avada Fusion (fusion_builder), SiteOrigin (siteorigin_widget).
				// Strip individual tags — paired-range regex fails on nested shortcodes,
				// leaving orphaned closing tags like [/et_pb_column][/et_pb_row].
				$bp = 'et_pb|vc|fl_builder|fusion_builder|siteorigin_widget';
				// Opening and self-closing tags — \]? catches truncated fragments.
				$raw = preg_replace( '/\[(?:' . $bp . ')[^\]]*\/?\]?/u', '', $raw );
				// Closing tags: [/et_pb_section] etc.
				$raw = preg_replace( '/\[\/(?:' . $bp . ')[^\]]*\]?/u', '', $raw );

				// Strip any remaining HTML and whitespace.
				$raw = wp_strip_all_tags( $raw );
				$raw = trim( $raw );

				$excerpt = bp_create_excerpt(
					$raw,
					100,
					array(
						'ending' => __( '&hellip;', 'buddyboss' ),
					)
				);

				echo wp_kses_post( $excerpt );
				?>
			</div>

			<div class="entry-meta">
				<span class="author">
					<?php
					/* translators: %s author name */
					printf( esc_html__( 'By %s', 'buddyboss' ), get_the_author_link() );
					?>
				</span>
				<span class="middot">&middot;</span>
				<span class="published">
					<?php echo wp_kses_post( get_the_date() ); ?>
				</span>
			</div>
		</div>
	</div>
</li>
