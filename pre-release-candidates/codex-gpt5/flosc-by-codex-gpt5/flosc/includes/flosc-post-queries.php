<?php
/**
 * Post queries that must not silently truncate.
 *
 * Four admin screens used to ask get_posts() for 200 or 500 posts in one go, and
 * each carried a comment defending the number: the list feeds a picker, so a post
 * the query drops is a post the admin cannot choose, and there is nothing on the
 * page to say anything was dropped. The comments were honest about the tradeoff
 * and WPCS warned about it anyway, because a high posts_per_page is a real smell.
 *
 * Both were right. A ceiling of 500 is still a ceiling, and the site with 501
 * matching posts loses one without being told. So the ceiling is gone rather than
 * argued with: this asks in pages of 100 and keeps asking until the database runs
 * out of rows. Nothing is dropped, no single query is large, and the number WPCS
 * sees is 100.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every post matching the query, fetched a page at a time.
 *
 * Caller-supplied 'posts_per_page', 'offset', 'paged' and 'nopaging' are ignored:
 * this owns the paging, and honouring a caller's page size would reintroduce the
 * ceiling it exists to remove. Everything else in $args is passed through
 * untouched, so 'fields' => 'ids', 'orderby', 'category_name', 'meta_key' and the
 * rest behave exactly as they do in a direct get_posts() call.
 *
 * 'no_found_rows' is forced on. Nothing here reads the total, and the loop stops
 * on a short page rather than on a count, so the SQL_CALC_FOUND_ROWS that
 * get_posts() would otherwise run on every page is wasted work.
 *
 * $max_pages is a guard against an unbounded loop, not a feature. At the default
 * it allows 10,000 posts, which is far past what any of these screens can show
 * usefully; a caller that needs more should be paginating its own output.
 *
 * @param array $args      get_posts() arguments, minus the paging keys.
 * @param int   $per_page  Rows per query. Kept at or below 100 so the query stays
 *                         small and the WPCS pagination warning stays quiet.
 * @param int   $max_pages Stop after this many pages regardless.
 * @return array Everything the query matched, in query order.
 */
function flosc_get_posts_all( array $args, $per_page = 100, $max_pages = 100 ) {
	$flosc_per_page = max( 1, min( 100, (int) $per_page ) );
	$flosc_out      = array();

	unset( $args['posts_per_page'], $args['offset'], $args['paged'], $args['nopaging'] );

	for ( $flosc_page = 0; $flosc_page < (int) $max_pages; $flosc_page++ ) {
		$flosc_batch = get_posts(
			array_merge(
				$args,
				array(
					'posts_per_page' => $flosc_per_page,
					'offset'         => $flosc_page * $flosc_per_page,
					'no_found_rows'  => true,
				)
			)
		);

		if ( ! is_array( $flosc_batch ) || ! $flosc_batch ) {
			break;
		}

		$flosc_out = array_merge( $flosc_out, $flosc_batch );

		// A short page is the last page. Saves one empty query per call.
		if ( count( $flosc_batch ) < $flosc_per_page ) {
			break;
		}
	}

	return $flosc_out;
}
