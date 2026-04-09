<?php
/**
 * Search results template — Activity items
 *
 * Overrides buddyboss-platform/.../search/loop/activity.php
 * Child theme override: yourtheme/buddypress/search/loop/activity.php
 *
 * Change from core: strips page-builder shortcodes (Divi, WPBakery, etc.)
 * from the activity content body before displaying the excerpt.
 * Core's bp_get_activity_content_body() pipeline leaves raw [et_pb_*] etc.
 * shortcodes visible when Divi/builder content is stored in the activity.
 *
 * @package BuddyBoss\Core
 * @since   BuddyBoss 1.0.0
 */
?>

<li class="bp-search-item bp-search-item_activity <?php bp_activity_css_class(); ?>" id="activity-<?php bp_activity_id(); ?>" data-bp-activity-id="<?php bp_activity_id(); ?>" data-bp-timestamp="<?php bp_nouveau_activity_timestamp(); ?>">
	<div class="list-wrap">
		<div class="activity-avatar item-avatar">
			<a href="<?php bp_activity_user_link(); ?>" data-bb-hp-profile="<?php echo esc_attr( bp_get_activity_user_id() ); ?>">
				<?php bp_activity_avatar( array( 'type' => 'full' ) ); ?>
			</a>
		</div>

		<div class="item activity-content">
			<div class="activity-header">
				<?php echo wp_kses_post( bp_get_activity_action( array( 'no_timestamp' => true ) ) ); ?>
			</div>

			<?php
			if ( bb_activity_has_post_title() ) {
				?>
				<div class="activity-title bb-activity-search-title">
					<h2><?php echo wp_kses_post( bb_activity_post_title() ); ?></h2>
				</div>
				<?php
			}
			?>

			<?php if ( bp_nouveau_activity_has_content() ) : ?>
				<?php
				// Get activity content and strip page-builder shortcodes before display.
				// BuddyBoss stores raw Divi/builder content in activity->content when
				// blog posts are published — this strips all builder shortcodes cleanly.
				$content = dainis_strip_builder_shortcodes_from( bp_get_activity_content_body() );
				$content = wp_strip_all_tags( $content );
				$content = trim( $content );
				if ( $content ) :
				?>
				<div class="activity-inner">
					<?php
					echo wp_kses_post( bp_create_excerpt(
						$content,
						100,
						array( 'ending' => '&hellip;' )
					) );
					?>
				</div>
				<?php endif; ?>
			<?php endif; ?>

			<div class="item-meta">
				<a href="<?php bp_activity_thread_permalink(); ?>">
					<?php
					printf(
						'<time class="time-since" data-livestamp="%1$s">%2$s</time>',
						bp_core_get_iso8601_date( bp_get_activity_date_recorded() ),
						bp_core_time_since( bp_get_activity_date_recorded() )
					);
					?>
				</a>
			</div>
		</div>
	</div>
</li>
