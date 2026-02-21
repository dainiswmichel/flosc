<?php
/**
 * Template part for displaying posts
 * BuddyBoss Child Theme override — 2026-02m-18d
 *
 * Changes from parent:
 * - Blog list: 150x150 thumbnail LEFT of title/excerpt
 * - Single post: 150x150 thumbnail floated RIGHT of content
 * - No blank space when no featured image
 * - BuddyBoss default full-width featured image hidden via CSS
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 * @package BuddyBoss_Theme
 */
?>

<?php
global $post;
$has_thumb = has_post_thumbnail();
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

	<?php if ( ! is_single() || is_related_posts() ) { ?>
		<div class="post-inner-wrap">

		<?php if ( $has_thumb ) { ?>
			<div class="entry-thumbnail-col">
				<a href="<?php the_permalink(); ?>">
					<?php the_post_thumbnail( 'thumbnail' ); ?>
				</a>
			</div>
		<?php } ?>

		<div class="entry-content-col">
	<?php } ?>

	<?php
	if ( ( ! is_single() || is_related_posts() ) && function_exists( 'buddyboss_theme_entry_header' ) ) {
		if ( ! $has_thumb ) {
			buddyboss_theme_entry_header( $post );
		}
	}
	?>

	<div class="entry-content-wrap primary-entry-content">
		<?php
		$featured_img_style = buddyboss_theme_get_option( 'blog_featured_img' );

		if ( has_post_format( 'link' ) && ( ! is_singular() || is_related_posts() ) ) {
			echo '<span class="post-format-icon" aria-label="' . esc_attr__( 'Link', 'buddyboss-theme' ) . '"><i class="bb-icon-l bb-icon-link"></i></span>';
		}

		if ( has_post_format( 'quote' ) && ( ! is_singular() || is_related_posts() ) ) {
			echo '<span class="post-format-icon white" aria-label="' . esc_attr__( 'Quote', 'buddyboss-theme' ) . '"><i class="bb-icon-l bb-icon-quote-left"></i></span>';
		}

		if ( ! is_singular( 'lesson' ) && ! is_singular( 'llms_assignment' ) && ! is_singular( 'sfwd-lessons' ) ) :
		?>
			<header class="entry-header">
				<?php
				if ( is_singular() && ! is_related_posts() ) :
					the_title( '<h1 class="entry-title">', '</h1>' );
				else :
					$prefix = '';
					if ( has_post_format( 'link' ) ) {
						$prefix = __( '[Link]', 'buddyboss-theme' ) . ' ';
					}
					the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">' . esc_html( $prefix ), '</a></h2>' );
				endif;

				if ( has_post_format( 'link' ) && function_exists( 'buddyboss_theme_get_first_url_content' ) ) :
					$first_url = buddyboss_theme_get_first_url_content( $post->post_content );
					if ( ! empty( $first_url ) ) : ?>
						<p class="post-main-link"><?php echo esc_url( $first_url ); ?></p>
					<?php endif;
				endif;
				?>
			</header><!-- .entry-header -->
		<?php endif; ?>

		<?php if ( ! is_singular() || is_related_posts() ) { ?>
			<div class="entry-content">
				<?php
				if ( empty( $post->post_excerpt ) ) {
					the_excerpt();
				} else {
					echo bb_get_excerpt( $post->post_excerpt, 150 );
				}
				?>
			</div>
		<?php } ?>

		<?php
		if ( ( isset( $post->post_type ) && 'post' === $post->post_type ) || ( ! has_post_format( 'quote' ) && is_singular( 'post' ) ) ) :
			get_template_part( 'template-parts/entry-meta' );
		endif;
		?>

		<?php if ( is_singular() && ! is_related_posts() ) { ?>
			<div class="entry-content">
				<?php if ( $has_thumb ) { ?>
					<div class="entry-thumbnail-single">
						<?php the_post_thumbnail( 'thumbnail' ); ?>
					</div>
				<?php } ?>
				<?php
				the_content(
					sprintf(
						wp_kses(
							__( 'Continue reading<span class="screen-reader-text"> "%s"</span>', 'buddyboss-theme' ),
							array( 'span' => array( 'class' => array() ) )
						),
						get_the_title()
					)
				);

				wp_link_pages( array(
					'before'      => '<nav class="page-links bb-page-links">',
					'after'       => '</nav>',
					'link_before' => '',
					'link_after'  => '',
				) );
				?>
			</div><!-- .entry-content -->
		<?php } ?>
	</div>

	<?php if ( ! is_single() || is_related_posts() ) { ?>
		</div><!-- .entry-content-col -->
		</div><!-- .post-inner-wrap -->
	<?php } ?>

	<?php if ( is_single() && ! is_related_posts() ) { ?>
		<footer class="entry-footer">
			<?php buddyboss_theme_entry_footer(); ?>
		</footer><!-- .entry-footer -->
	<?php } ?>

</article><!-- #post-<?php the_ID(); ?> -->
