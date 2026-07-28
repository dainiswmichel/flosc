<?php
/**
 * FLOSC Content Tab
 *
 * Merges former Member Levels + Lessons admin into one Content tab (C of FLOSC).
 *
 * Sections:
 * 1. Content labels (optional singular/plural: Lesson, Recipe, …)
 * 2. Member levels (registry)
 * 3. Content groups (quiz → WP category)
 * 4. Content protection
 * 5. Complimentary pool / selection (guests)
 * 6. Guest chat caps
 *
 * Setting keys keep free_lesson_* / lesson_groups for runtime compatibility.
 *
 * @package FLOSC
 * @since 8.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

flosc_tab_header( '📦', 'Content' );

$flosc_flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
$flosc_settings_key  = $GLOBALS['flosc_settings_key'] ?? '';
$flosc_current_ivr   = $GLOBALS['flosc_current_ivr'] ?? '';

$flosc_content_docs_url = add_query_arg(
	[
		'page' => 'flosc-settings',
		'ivr'  => $flosc_current_ivr,
		'tab'  => 'documentation',
		'doc'  => 'ref-admin',
	],
	admin_url( 'admin.php' )
) . '#tab-content';

$flosc_item_singular = (string) ( $flosc_flow_settings['content_item_label_singular'] ?? '' );
$flosc_item_plural   = (string) ( $flosc_flow_settings['content_item_label_plural'] ?? '' );
$flosc_item_s_disp   = $flosc_item_singular !== '' ? $flosc_item_singular : __( 'content item', 'flosc' );
$flosc_item_p_disp   = $flosc_item_plural !== '' ? $flosc_item_plural : __( 'content items', 'flosc' );

$flosc_member_levels = $flosc_flow_settings['member_levels'] ?? [];
if ( empty( $flosc_member_levels ) ) {
	$flosc_member_levels[''] = [ 'slug' => '', 'name' => '', 'description' => '' ];
}

$flosc_categories = get_categories( [ 'hide_empty' => false ] );
$flosc_tags       = get_tags( [ 'hide_empty' => false ] );
if ( ! is_array( $flosc_tags ) ) {
	$flosc_tags = [];
}

$flosc_enabled_quizzes = $flosc_flow_settings['enabled_quizzes'] ?? [ 'flosc_sample_data_numbers_quiz' ];
if ( ! is_array( $flosc_enabled_quizzes ) ) {
	$flosc_enabled_quizzes = [ 'flosc_sample_data_numbers_quiz' ];
}
$flosc_quiz_label_map = [];
if ( class_exists( 'FLOSC_Quiz_Registry' ) ) {
	$flosc_all_quiz_types = FLOSC_Quiz_Registry::get_all_quizzes();
	foreach ( $flosc_all_quiz_types as $flosc_qid => $flosc_qt ) {
		$flosc_quiz_label_map[ $flosc_qid ] = ( $flosc_qt instanceof FLOSC_Abstract_Quiz_Type )
			? $flosc_qt->get_name()
			: ucwords( str_replace( [ '_', 'flosc-', 'flosc_' ], [ ' ', '', '' ], $flosc_qid ) );
	}
}

$flosc_lesson_groups = $flosc_flow_settings['lesson_groups'] ?? [];
if ( empty( $flosc_lesson_groups ) && ! empty( $flosc_flow_settings['lessons_category'] ) ) {
	$flosc_lesson_groups = [
		[ 'quiz_id' => '', 'category' => $flosc_flow_settings['lessons_category'] ],
	];
}
if ( empty( $flosc_lesson_groups ) ) {
	$flosc_lesson_groups = [ [ 'quiz_id' => '', 'category' => '' ] ];
}

$flosc_saved_levels      = $flosc_flow_settings['member_levels'] ?? $flosc_member_levels;
$flosc_protected_items   = $flosc_flow_settings['protected_content'] ?? [];
$flosc_free_lesson_mode  = $flosc_flow_settings['free_lesson_mode'] ?? 'fixed';
$flosc_free_lesson_count = $flosc_flow_settings['free_lesson_count'] ?? 1;
$flosc_free_lesson_proportion = $flosc_flow_settings['free_lesson_proportion'] ?? '1/3';
$flosc_guest_access_days = $flosc_flow_settings['guest_access_days'] ?? 0;
$flosc_free_lesson_guaranteed = $flosc_flow_settings['free_lesson_guaranteed'] ?? 0;
$flosc_free_lesson_pool_category = sanitize_title( (string) ( $flosc_flow_settings['free_lesson_pool_category'] ?? '' ) );
$flosc_pool_exclude = (string) ( $flosc_flow_settings['free_lesson_never_free'] ?? '' );
$flosc_guest_max_chats = isset( $flosc_flow_settings['guest_max_chats'] )
	? max( 0, intval( $flosc_flow_settings['guest_max_chats'] ) )
	: 0;
$flosc_guest_can_delete_chats = ! isset( $flosc_flow_settings['guest_can_delete_chats'] )
	|| ! empty( $flosc_flow_settings['guest_can_delete_chats'] );
$flosc_guest_can_rename_chats = ! isset( $flosc_flow_settings['guest_can_rename_chats'] )
	|| ! empty( $flosc_flow_settings['guest_can_rename_chats'] );
$flosc_guest_new_chat_limit_message = (string) ( $flosc_flow_settings['guest_new_chat_limit_message']
	?? 'Your guest account allows {max} chats listed below. If you would like to start a new chat, you can delete one below.' );
$flosc_chat_list_settings_url = add_query_arg(
	[
		'page' => 'flosc-settings',
		'ivr'  => $flosc_current_ivr,
		'tab'  => 'ui',
		'view' => 'single',
	],
	admin_url( 'admin.php' )
);
?>

<div class="flosc-docs-link-wrap">
	<a href="<?php echo esc_url( $flosc_content_docs_url ); ?>" class="flosc-docs-link"><?php echo esc_html__( 'Docs', 'flosc' ); ?></a>
</div>

<p class="description">
	<?php echo esc_html__( 'Content is the C of FLOSC for this flow: items in WordPress, which member levels unlock them, and the guest complimentary pool/selection. Offers grant levels (Offers tab). Sale checkout is separate. DA1 catalogs are separate (DA1 tab).', 'flosc' ); ?>
</p>

<!-- 1. Labels -->
<h2><?php echo esc_html__( 'What you call content', 'flosc' ); ?></h2>
<p><?php echo esc_html__( 'Optional display labels for this flow (Lesson, Recipe, Module, …). Empty = “content item(s)” in FLOSC chrome.', 'flosc' ); ?></p>
<table class="form-table">
	<tr>
		<th scope="row"><label for="flow_content_item_label_singular"><?php echo esc_html__( 'Singular label', 'flosc' ); ?></label></th>
		<td>
			<input type="text" class="regular-text" id="flow_content_item_label_singular" name="flow_content_item_label_singular"
				value="<?php echo esc_attr( $flosc_item_singular ); ?>"
				placeholder="<?php echo esc_attr__( 'e.g. Lesson or Recipe', 'flosc' ); ?>">
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_content_item_label_plural"><?php echo esc_html__( 'Plural label', 'flosc' ); ?></label></th>
		<td>
			<input type="text" class="regular-text" id="flow_content_item_label_plural" name="flow_content_item_label_plural"
				value="<?php echo esc_attr( $flosc_item_plural ); ?>"
				placeholder="<?php echo esc_attr__( 'e.g. Lessons or Recipes', 'flosc' ); ?>">
		</td>
	</tr>
</table>

<!-- 2. Levels -->
<hr class="flosc-member-levels-divider">
<h2><?php echo esc_html__( 'Member levels', 'flosc' ); ?></h2>
<p><?php echo esc_html__( 'Define membership levels for this flow. Offers grant a level. Protection rules require a level.', 'flosc' ); ?></p>

<table class="widefat flosc-levels-table flosc-member-levels-table">
	<thead>
		<tr>
			<th class="flosc-member-levels-col-slug"><?php echo esc_html__( 'Slug', 'flosc' ); ?> <span class="flosc-note-optional">(<?php echo esc_html__( 'machine name', 'flosc' ); ?>)</span></th>
			<th class="flosc-member-levels-col-name"><?php echo esc_html__( 'Display Name', 'flosc' ); ?></th>
			<th class="flosc-member-levels-col-description"><?php echo esc_html__( 'Description', 'flosc' ); ?> <span class="flosc-note-optional">(<?php echo esc_html__( 'optional', 'flosc' ); ?>)</span></th>
			<th class="flosc-member-levels-col-actions"><?php echo esc_html__( 'Actions', 'flosc' ); ?></th>
		</tr>
	</thead>
	<tbody id="flosc-levels-body">
		<?php foreach ( $flosc_member_levels as $flosc_level_key => $flosc_level ) : ?>
		<tr class="flosc-level-row">
			<td>
				<input type="text" name="level_slug[]"
					value="<?php echo esc_attr( $flosc_level['slug'] ?? $flosc_level_key ); ?>"
					class="regular-text flosc-level-slug"
					placeholder="full_access"
					pattern="[a-z0-9_]+"
					title="<?php echo esc_attr__( 'Lowercase letters, numbers, underscores only', 'flosc' ); ?>">
			</td>
			<td>
				<input type="text" name="level_name[]"
					value="<?php echo esc_attr( $flosc_level['name'] ?? '' ); ?>"
					class="regular-text"
					placeholder="Full Access">
			</td>
			<td>
				<input type="text" name="level_description[]"
					value="<?php echo esc_attr( $flosc_level['description'] ?? '' ); ?>"
					class="regular-text"
					placeholder="<?php echo esc_attr__( 'Complete access to all content', 'flosc' ); ?>">
			</td>
			<td class="flosc-text-center">
				<button type="button" class="button flosc-remove-level" title="<?php echo esc_attr__( 'Remove this level', 'flosc' ); ?>">&times;</button>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<p class="flosc-margin-top-10">
	<button type="button" class="button" id="flosc-add-level"><?php echo esc_html__( '+ Add Level', 'flosc' ); ?></button>
</p>

<!-- 3. Content groups -->
<hr class="flosc-member-levels-divider">
<h2><?php echo esc_html__( 'Content groups', 'flosc' ); ?></h2>
<p>
	<?php
	echo esc_html(
		sprintf(
			/* translators: %s: plural content item label */
			__( 'Map an optional quiz to a WordPress category of %s. Categories without a quiz are standalone libraries.', 'flosc' ),
			$flosc_item_p_disp
		)
	);
	?>
</p>
<table class="widefat flosc-lesson-groups-table flosc-table-max-860">
	<thead>
		<tr>
			<th class="flosc-col-quiz"><?php echo esc_html__( 'Quiz', 'flosc' ); ?> <span class="flosc-note-optional">(<?php echo esc_html__( 'optional', 'flosc' ); ?>)</span></th>
			<th class="flosc-col-category"><?php echo esc_html__( 'Category', 'flosc' ); ?> <span class="flosc-note-optional">(<?php echo esc_html__( 'required', 'flosc' ); ?>)</span></th>
			<th class="flosc-col-actions"><?php echo esc_html__( 'Actions', 'flosc' ); ?></th>
		</tr>
	</thead>
	<tbody id="flosc-lesson-groups-body">
		<?php foreach ( $flosc_lesson_groups as $flosc_group ) : ?>
		<tr class="flosc-lesson-group-row">
			<td>
				<select name="lesson_group_quiz[]" class="regular-text flosc-width-full">
					<option value=""><?php echo esc_html__( '— No Quiz (Standalone) —', 'flosc' ); ?></option>
					<?php foreach ( $flosc_enabled_quizzes as $flosc_eq ) : ?>
						<option value="<?php echo esc_attr( $flosc_eq ); ?>" <?php selected( $flosc_group['quiz_id'] ?? '', $flosc_eq ); ?>>
							<?php echo esc_html( $flosc_quiz_label_map[ $flosc_eq ] ?? $flosc_eq ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
			<td>
				<select name="lesson_group_category[]" class="regular-text flosc-width-full">
					<option value=""><?php echo esc_html__( '— Select Category —', 'flosc' ); ?></option>
					<?php foreach ( $flosc_categories as $cat ) : ?>
						<option value="<?php echo esc_attr( $cat->slug ); ?>" <?php selected( $flosc_group['category'] ?? '', $cat->slug ); ?>>
							<?php echo esc_html( $cat->name ); ?> (<?php echo esc_html( (string) $cat->count ); ?> posts)
						</option>
					<?php endforeach; ?>
				</select>
			</td>
			<td class="flosc-text-center">
				<button type="button" class="button flosc-remove-lesson-group" title="<?php echo esc_attr__( 'Remove this group', 'flosc' ); ?>">&times;</button>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<p class="flosc-margin-top-10">
	<button type="button" class="button" id="flosc-add-lesson-group"><?php echo esc_html__( '+ Add Content Group', 'flosc' ); ?></button>
</p>
<p class="description flosc-margin-top-6">
	<?php echo esc_html__( 'Posts in the category may use number tags (e.g. 5, phoneme-5) so quiz misses map to the matching item.', 'flosc' ); ?>
</p>

<!-- 4. Protection -->
<hr class="flosc-member-levels-divider">
<h2><?php echo esc_html__( 'Content protection', 'flosc' ); ?></h2>
<p><?php echo esc_html__( 'Assign WordPress content to member levels. Guests only open complimentary selection from the pool below until Sale grants a level.', 'flosc' ); ?></p>
<table class="widefat flosc-protection-table flosc-member-protection-table">
	<thead>
		<tr>
			<th class="flosc-member-protection-col-type"><?php echo esc_html__( 'Type', 'flosc' ); ?></th>
			<th class="flosc-member-protection-col-content"><?php echo esc_html__( 'Content', 'flosc' ); ?></th>
			<th class="flosc-member-protection-col-level"><?php echo esc_html__( 'Required Level', 'flosc' ); ?></th>
			<th class="flosc-member-protection-col-actions"><?php echo esc_html__( 'Actions', 'flosc' ); ?></th>
		</tr>
	</thead>
	<tbody id="flosc-protection-body">
		<?php if ( ! empty( $flosc_protected_items ) ) : ?>
			<?php foreach ( $flosc_protected_items as $flosc_item ) : ?>
			<tr class="flosc-protection-row">
				<td>
					<select name="protection_type[]" class="flosc-protection-type flosc-width-full">
						<option value="category" <?php selected( $flosc_item['type'] ?? '', 'category' ); ?>><?php echo esc_html__( 'Category', 'flosc' ); ?></option>
						<option value="tag" <?php selected( $flosc_item['type'] ?? '', 'tag' ); ?>><?php echo esc_html__( 'Tag', 'flosc' ); ?></option>
						<option value="post" <?php selected( $flosc_item['type'] ?? '', 'post' ); ?>><?php echo esc_html__( 'Post (ID)', 'flosc' ); ?></option>
						<option value="page" <?php selected( $flosc_item['type'] ?? '', 'page' ); ?>><?php echo esc_html__( 'Page (ID)', 'flosc' ); ?></option>
					</select>
				</td>
				<td>
					<?php
					$flosc_item_type = $flosc_item['type'] ?? 'category';
					if ( in_array( $flosc_item_type, [ 'post', 'page' ], true ) ) :
						?>
						<input type="text" name="protection_value[]"
							value="<?php echo esc_attr( $flosc_item['id'] ?? '' ); ?>"
							class="regular-text flosc-protection-value"
							placeholder="<?php echo esc_attr__( 'Post/Page ID', 'flosc' ); ?>">
					<?php else : ?>
						<select name="protection_value[]" class="flosc-protection-value flosc-width-full">
							<option value=""><?php echo esc_html__( '— Select —', 'flosc' ); ?></option>
							<?php if ( 'category' === $flosc_item_type ) : ?>
								<?php foreach ( $flosc_categories as $cat ) : ?>
									<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $flosc_item['id'] ?? '', $cat->term_id ); ?>>
										<?php echo esc_html( $cat->name ); ?> (<?php echo esc_html( (string) $cat->count ); ?> posts)
									</option>
								<?php endforeach; ?>
							<?php elseif ( 'tag' === $flosc_item_type ) : ?>
								<?php foreach ( $flosc_tags as $tag ) : ?>
									<option value="<?php echo esc_attr( $tag->term_id ); ?>" <?php selected( $flosc_item['id'] ?? '', $tag->term_id ); ?>>
										<?php echo esc_html( $tag->name ); ?> (<?php echo esc_html( (string) $tag->count ); ?> posts)
									</option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>
					<?php endif; ?>
				</td>
				<td>
					<select name="protection_level[]" class="flosc-protection-level-select flosc-width-full">
						<option value=""><?php echo esc_html__( '— Any Member —', 'flosc' ); ?></option>
						<?php
						foreach ( $flosc_saved_levels as $flosc_lk => $flosc_lv ) :
							$flosc_slug = $flosc_lv['slug'] ?? $flosc_lk;
							if ( empty( $flosc_slug ) ) {
								continue;
							}
							?>
							<option value="<?php echo esc_attr( $flosc_slug ); ?>" <?php selected( $flosc_item['level'] ?? '', $flosc_slug ); ?>>
								<?php echo esc_html( ( $flosc_lv['name'] ?? '' ) ?: $flosc_slug ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
				<td class="flosc-text-center">
					<button type="button" class="button flosc-remove-protection-row" title="<?php echo esc_attr__( 'Remove', 'flosc' ); ?>">&times;</button>
				</td>
			</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>
<p class="flosc-margin-top-10">
	<button type="button" class="button" id="flosc-add-protection"><?php echo esc_html__( '+ Add Protection Rule', 'flosc' ); ?></button>
</p>

<!-- 5. Pool / selection -->
<hr class="flosc-member-levels-divider">
<h2><?php echo esc_html__( 'Complimentary pool & selection (guests)', 'flosc' ); ?></h2>
<p>
	<?php
	echo esc_html(
		sprintf(
			/* translators: 1: content items plural, 2: example count */
			__( 'Content is the full set. The pool is the complimentary subset. Selection is how many a Guest receives (e.g. %2$s of your %1$s). More requires Member via Sale.', 'flosc' ),
			$flosc_item_p_disp,
			'2'
		)
	);
	?>
</p>
<table class="form-table">
	<tr>
		<th scope="row"><label for="flow_free_lesson_pool_category"><?php echo esc_html__( 'Pool (category)', 'flosc' ); ?></label></th>
		<td>
			<select name="flow_free_lesson_pool_category" id="flow_free_lesson_pool_category" class="regular-text">
				<option value=""><?php echo esc_html__( '— Same as content group category —', 'flosc' ); ?></option>
				<?php foreach ( $flosc_categories as $cat ) :
					$label = $cat->name;
					if ( ! empty( $cat->parent ) ) {
						$parent = get_category( (int) $cat->parent );
						if ( $parent && ! is_wp_error( $parent ) ) {
							$label = $parent->name . ' → ' . $cat->name;
						}
					}
					?>
					<option value="<?php echo esc_attr( $cat->slug ); ?>" <?php selected( $flosc_free_lesson_pool_category, $cat->slug ); ?>>
						<?php echo esc_html( $label ); ?> (<?php echo esc_html( (string) $cat->count ); ?> posts)
					</option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php echo esc_html__( 'Only published posts in this category (with content numbers) can be complimentary samples.', 'flosc' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_free_lesson_never_free"><?php echo esc_html__( 'Exclude from pool (numbers)', 'flosc' ); ?></label></th>
		<td>
			<input type="text" id="flow_free_lesson_never_free" name="flow_free_lesson_never_free"
				value="<?php echo esc_attr( $flosc_pool_exclude ); ?>"
				class="large-text" placeholder="e.g. 1, 2, 7, 12">
			<p class="description"><?php echo esc_html__( 'Comma-separated numbers that stay out of complimentary selection even if in the pool category.', 'flosc' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_free_lesson_mode"><?php echo esc_html__( 'Selection mode', 'flosc' ); ?></label></th>
		<td>
			<select name="flow_free_lesson_mode" id="flow_free_lesson_mode">
				<option value="fixed" <?php selected( $flosc_free_lesson_mode, 'fixed' ); ?>><?php echo esc_html__( 'Fixed number', 'flosc' ); ?></option>
				<option value="proportion" <?php selected( $flosc_free_lesson_mode, 'proportion' ); ?>><?php echo esc_html__( 'Proportion of missed (non-IPA)', 'flosc' ); ?></option>
			</select>
		</td>
	</tr>
	<tr id="flow_free_lesson_count_row">
		<th scope="row"><label for="flow_free_lesson_count"><?php echo esc_html__( 'Guest selection count', 'flosc' ); ?></label></th>
		<td>
			<input type="number" id="flow_free_lesson_count" name="flow_free_lesson_count"
				value="<?php echo esc_attr( (string) $flosc_free_lesson_count ); ?>" min="1" max="50" class="small-text">
			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: plural content label */
						__( 'How many %s a Guest receives from the pool (e.g. 2).', 'flosc' ),
						$flosc_item_p_disp
					)
				);
				?>
			</p>
		</td>
	</tr>
	<tr id="flow_free_lesson_proportion_row">
		<th scope="row"><label for="flow_free_lesson_proportion"><?php echo esc_html__( 'Proportion of missed', 'flosc' ); ?></label></th>
		<td>
			<select name="flow_free_lesson_proportion" id="flow_free_lesson_proportion">
				<option value="1/5" <?php selected( $flosc_free_lesson_proportion, '1/5' ); ?>>1/5</option>
				<option value="1/4" <?php selected( $flosc_free_lesson_proportion, '1/4' ); ?>>1/4</option>
				<option value="1/3" <?php selected( $flosc_free_lesson_proportion, '1/3' ); ?>>1/3</option>
				<option value="1/2" <?php selected( $flosc_free_lesson_proportion, '1/2' ); ?>>1/2</option>
			</select>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_free_lesson_guaranteed"><?php echo esc_html__( 'Bonus item number', 'flosc' ); ?></label></th>
		<td>
			<input type="number" id="flow_free_lesson_guaranteed" name="flow_free_lesson_guaranteed"
				value="<?php echo esc_attr( (string) $flosc_free_lesson_guaranteed ); ?>" min="0" max="9999" class="small-text">
			<p class="description"><?php echo esc_html__( 'Optional extra number always included when in pool and not excluded. 0 = off.', 'flosc' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_guest_access_days"><?php echo esc_html__( 'Guest access duration', 'flosc' ); ?></label></th>
		<td>
			<input type="number" id="flow_guest_access_days" name="flow_guest_access_days"
				value="<?php echo esc_attr( (string) $flosc_guest_access_days ); ?>" min="0" max="365" class="small-text">
			<?php echo esc_html__( 'days', 'flosc' ); ?>
			<p class="description"><?php echo esc_html__( '0 = unlimited.', 'flosc' ); ?></p>
		</td>
	</tr>
</table>

<!-- 6. Guest chats -->
<hr class="flosc-member-levels-divider">
<h2><?php echo esc_html__( 'Guest chats', 'flosc' ); ?></h2>
<p><?php echo esc_html__( 'Chat list caps for guests (not content delivery). Profile bar labels live under Profile Bar / UI.', 'flosc' ); ?></p>
<table class="form-table">
	<tr>
		<th scope="row"><label for="flow_guest_max_chats"><?php echo esc_html__( 'Guest max chats', 'flosc' ); ?></label></th>
		<td>
			<input type="number" id="flow_guest_max_chats" name="flow_guest_max_chats"
				value="<?php echo esc_attr( (string) $flosc_guest_max_chats ); ?>" min="0" max="9999" class="small-text">
			<p class="description"><?php echo esc_html__( '0 = unlimited.', 'flosc' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php echo esc_html__( 'Guest chat management', 'flosc' ); ?></th>
		<td>
			<label for="flow_guest_can_delete_chats">
				<input type="checkbox" id="flow_guest_can_delete_chats" name="flow_guest_can_delete_chats" value="1" <?php checked( $flosc_guest_can_delete_chats ); ?>>
				<?php echo esc_html__( 'Guest may delete chats', 'flosc' ); ?>
			</label>
			<br>
			<label for="flow_guest_can_rename_chats">
				<input type="checkbox" id="flow_guest_can_rename_chats" name="flow_guest_can_rename_chats" value="1" <?php checked( $flosc_guest_can_rename_chats ); ?>>
				<?php echo esc_html__( 'Guest may rename chats', 'flosc' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_guest_new_chat_limit_message"><?php echo esc_html__( 'Guest New chat limit message', 'flosc' ); ?></label></th>
		<td>
			<textarea id="flow_guest_new_chat_limit_message" name="flow_guest_new_chat_limit_message" class="large-text" rows="3"><?php echo esc_textarea( $flosc_guest_new_chat_limit_message ); ?></textarea>
			<p class="description"><?php echo esc_html__( 'Placeholders: {max}, {count}, {flow_name}, {name}.', 'flosc' ); ?>
				<a href="<?php echo esc_url( $flosc_chat_list_settings_url ); ?>"><?php echo esc_html__( 'Profile Bar / Chat Navigation', 'flosc' ); ?></a>
			</p>
		</td>
	</tr>
</table>

<div class="flosc-member-levels-info-box">
	<h3 class="flosc-member-levels-info-box__title"><?php echo esc_html__( 'How Content + Sale work together', 'flosc' ); ?></h3>
	<p class="flosc-member-levels-info-box__lead">
		<strong>1.</strong> <?php echo esc_html__( 'Define levels and content groups.', 'flosc' ); ?><br>
		<strong>2.</strong> <?php echo esc_html__( 'Protect content by level.', 'flosc' ); ?><br>
		<strong>3.</strong> <?php echo esc_html__( 'Set guest pool + selection count.', 'flosc' ); ?><br>
		<strong>4.</strong> <?php echo esc_html__( 'Offers grant a level on Sale (PayPal / Access Code).', 'flosc' ); ?><br>
		<strong>5.</strong> <?php echo esc_html__( 'Member unlocks the rest of content.', 'flosc' ); ?>
	</p>
</div>

<?php
ob_start();
?>
jQuery(document).ready(function($) {
	$('#flosc-add-level').on('click', function() {
		var row = '<tr class="flosc-level-row">'
			+ '<td><input type="text" name="level_slug[]" class="regular-text flosc-level-slug" placeholder="full_access" pattern="[a-z0-9_]+"></td>'
			+ '<td><input type="text" name="level_name[]" class="regular-text" placeholder="Full Access"></td>'
			+ '<td><input type="text" name="level_description[]" class="regular-text" placeholder=""></td>'
			+ '<td class="flosc-text-center"><button type="button" class="button flosc-remove-level" title="Remove">&times;</button></td>'
			+ '</tr>';
		$('#flosc-levels-body').append(row);
	});
	$(document).on('click', '.flosc-remove-level', function() {
		if ($('.flosc-level-row').length > 1) {
			$(this).closest('tr').remove();
		} else {
			$(this).closest('tr').find('input').val('');
		}
	});
	$(document).on('input', '.flosc-level-row input[name="level_name[]"]', function() {
		var $slug = $(this).closest('tr').find('.flosc-level-slug');
		if ($slug.val() === '') {
			$slug.val($(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, ''));
		}
	});
	$(document).on('blur', '.flosc-level-slug', function() {
		$(this).val($(this).val().toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_|_$/g, ''));
	});

	var quizOptions = <?php
		$flosc_opts = '<option value="">— No Quiz (Standalone) —</option>';
		foreach ( $flosc_enabled_quizzes as $flosc_eq ) {
			$flosc_label = esc_html( $flosc_quiz_label_map[ $flosc_eq ] ?? $flosc_eq );
			$flosc_opts .= '<option value="' . esc_attr( $flosc_eq ) . '">' . $flosc_label . '</option>';
		}
		echo wp_json_encode( $flosc_opts );
	?>;
	var catOptions = <?php
		$flosc_opts = '<option value="">— Select Category —</option>';
		foreach ( $flosc_categories as $cat ) {
			$flosc_opts .= '<option value="' . esc_attr( $cat->slug ) . '">' . esc_html( $cat->name ) . ' (' . esc_html( (string) $cat->count ) . ' posts)</option>';
		}
		echo wp_json_encode( $flosc_opts );
	?>;
	$('#flosc-add-lesson-group').on('click', function() {
		var row = '<tr class="flosc-lesson-group-row">'
			+ '<td><select name="lesson_group_quiz[]" class="regular-text flosc-width-full">' + quizOptions + '</select></td>'
			+ '<td><select name="lesson_group_category[]" class="regular-text flosc-width-full">' + catOptions + '</select></td>'
			+ '<td class="flosc-text-center"><button type="button" class="button flosc-remove-lesson-group" title="Remove">&times;</button></td>'
			+ '</tr>';
		$('#flosc-lesson-groups-body').append(row);
	});
	$(document).on('click', '.flosc-remove-lesson-group', function() {
		if ($('.flosc-lesson-group-row').length > 1) {
			$(this).closest('tr').remove();
		}
	});

	var categoryOptions = <?php
		$flosc_opts = '<option value="">— Select —</option>';
		foreach ( $flosc_categories as $cat ) {
			$flosc_opts .= '<option value="' . esc_attr( $cat->term_id ) . '">' . esc_html( $cat->name ) . '</option>';
		}
		echo wp_json_encode( $flosc_opts );
	?>;
	var tagOptions = <?php
		$flosc_opts = '<option value="">— Select —</option>';
		foreach ( $flosc_tags as $tag ) {
			$flosc_opts .= '<option value="' . esc_attr( $tag->term_id ) . '">' . esc_html( $tag->name ) . '</option>';
		}
		echo wp_json_encode( $flosc_opts );
	?>;
	var levelOptions = <?php
		$flosc_opts = '<option value="">— Any Member —</option>';
		foreach ( $flosc_saved_levels as $flosc_lk => $flosc_lv ) {
			$flosc_slug = $flosc_lv['slug'] ?? $flosc_lk;
			if ( empty( $flosc_slug ) ) {
				continue;
			}
			$flosc_label = ( $flosc_lv['name'] ?? '' ) ?: $flosc_slug;
			$flosc_opts .= '<option value="' . esc_attr( $flosc_slug ) . '">' . esc_html( $flosc_label ) . '</option>';
		}
		echo wp_json_encode( $flosc_opts );
	?>;
	function buildContentField(type) {
		if (type === 'post' || type === 'page') {
			return '<input type="text" name="protection_value[]" class="regular-text flosc-protection-value" placeholder="Post/Page ID">';
		}
		if (type === 'tag') {
			return '<select name="protection_value[]" class="flosc-protection-value flosc-width-full">' + tagOptions + '</select>';
		}
		return '<select name="protection_value[]" class="flosc-protection-value flosc-width-full">' + categoryOptions + '</select>';
	}
	$('#flosc-add-protection').on('click', function() {
		var row = '<tr class="flosc-protection-row">'
			+ '<td><select name="protection_type[]" class="flosc-protection-type flosc-width-full"><option value="category">Category</option><option value="tag">Tag</option><option value="post">Post (ID)</option><option value="page">Page (ID)</option></select></td>'
			+ '<td>' + buildContentField('category') + '</td>'
			+ '<td><select name="protection_level[]" class="flosc-protection-level-select flosc-width-full">' + levelOptions + '</select></td>'
			+ '<td class="flosc-text-center"><button type="button" class="button flosc-remove-protection-row">&times;</button></td>'
			+ '</tr>';
		$('#flosc-protection-body').append(row);
	});
	$(document).on('change', '.flosc-protection-type', function() {
		$(this).closest('tr').find('td:eq(1)').html(buildContentField($(this).val()));
	});
	$(document).on('click', '.flosc-remove-protection-row', function() {
		$(this).closest('tr').remove();
	});

	function toggleFreeLessonFields() {
		var mode = $('#flow_free_lesson_mode').val();
		if (mode === 'fixed') {
			$('#flow_free_lesson_count_row').show();
			$('#flow_free_lesson_proportion_row').hide();
		} else {
			$('#flow_free_lesson_count_row').hide();
			$('#flow_free_lesson_proportion_row').show();
		}
	}
	toggleFreeLessonFields();
	$('#flow_free_lesson_mode').on('change', toggleFreeLessonFields);
});
<?php
wp_add_inline_script( 'flosc-admin', ob_get_clean() );
