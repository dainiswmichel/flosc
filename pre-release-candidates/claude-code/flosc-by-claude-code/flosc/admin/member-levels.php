<?php
/**
 * FLOSC Member Levels Tab — Level Registry + Content Protection
 *
 * SINGLE SOURCE OF TRUTH for member levels in a flow.
 * Other tabs (Offers, Lessons) reference this registry via dropdown.
 *
 * Sections:
 * 1. Level Registry — admin defines level slugs + display names
 * 2. Content Protection — assign categories, tags, posts, pages to levels
 * 3. Guest Access — freeline count/mode, access duration, max chats / management
 *
 * Extracted from Lessons tab (v8.0.0 → v8.1.0) where content protection
 * and guest access were previously mixed with lesson group configuration.
 *
 * @package FLOSC
 * @since 8.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

flosc_tab_header( '🔐', 'Member Levels' );

$flosc_flow_settings = $GLOBALS['flosc_current_settings'] ?? array();
$flosc_settings_key  = $GLOBALS['flosc_settings_key'] ?? '';
$flosc_current_ivr   = $GLOBALS['flosc_current_ivr'] ?? '';

$flosc_member_levels_docs_url = add_query_arg(
	array(
		'page' => 'flosc-settings',
		'ivr'  => $flosc_current_ivr,
		'tab'  => 'documentation',
		'doc'  => 'ref-admin',
	),
	admin_url( 'admin.php' )
) . '#tab-member-levels';

// ─── 1. Level Registry ──────────────────────────────────────────────────────

$flosc_member_levels = $flosc_flow_settings['member_levels'] ?? array();

// Empty row so the admin has something to fill in on first visit.
if ( empty( $flosc_member_levels ) ) {
	$flosc_member_levels[''] = array(
		'slug'        => '',
		'name'        => '',
		'description' => '',
	);
}

?>

<div class="flosc-docs-link-wrap">
	<a href="<?php echo esc_url( $flosc_member_levels_docs_url ); ?>" class="flosc-docs-link">Docs</a>
</div>

<!-- ─── Level Registry ────────────────────────────────────────────────── -->
<h2>Level Registry</h2>
<p>Define all membership levels available in this flow. Offers will grant these levels. Content protection will require them. The AI uses this to know what content each member can access.</p>

<table class="widefat flosc-levels-table flosc-member-levels-table">
	<thead>
		<tr>
			<th class="flosc-member-levels-col-slug">Slug <span class="flosc-note-optional">(machine name)</span></th>
			<th class="flosc-member-levels-col-name">Display Name</th>
			<th class="flosc-member-levels-col-description">Description <span class="flosc-note-optional">(optional)</span></th>
			<th class="flosc-member-levels-col-actions">Actions</th>
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
						title="Lowercase letters, numbers, underscores only"
						>
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
						placeholder="Complete access to all course content">
			</td>
			<td class="flosc-text-center">
				<button type="button" class="button flosc-remove-level" title="Remove this level">&times;</button>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<p class="flosc-margin-top-10">
	<button type="button" class="button" id="flosc-add-level">+ Add Level</button>
</p>
<p class="description flosc-margin-top-6">
	Slugs must be unique, lowercase, and use underscores (e.g., <code>full_access</code>, <code>trial</code>, <code>premium</code>).
	These levels appear as dropdown options in the Offers tab and Content Protection below.
</p>

<?php
// ─── 2. Content Protection ──────────────────────────────────────────────────

// Build the level list for dropdowns (from saved levels, not from the form — form hasn't been submitted yet).
$flosc_saved_levels = $flosc_flow_settings['member_levels'] ?? $flosc_member_levels;

// Gather all WordPress categories and tags.
$flosc_categories = get_categories( array( 'hide_empty' => false ) );
$flosc_tags       = get_tags( array( 'hide_empty' => false ) );

// Load existing protection data.
$flosc_protected_items = $flosc_flow_settings['protected_content'] ?? array();

/*
 * Same two vocabularies as the Content tab, from the same helper.
 *
 * This screen and the Content tab post the SAME repeater into the same save
 * handler. A column present on one and missing on the other means saving from
 * the screen that is missing it silently resets that column on every rule. One
 * source, both screens.
 */
$flosc_vgm_tiers  = flosc_vgm_tier_labels();
$flosc_vgm_depths = flosc_vgm_depth_labels();
?>

<hr class="flosc-member-levels-divider">
<h2>Content Protection</h2>
<p>Assign WordPress content to member levels. Protected content is blocked for non-members and available to the AI for members with the required level.</p>

<table class="widefat flosc-protection-table flosc-member-protection-table">
	<thead>
		<tr>
			<th class="flosc-member-protection-col-type">Type</th>
			<th class="flosc-member-protection-col-content">Content</th>
			<th class="flosc-member-protection-col-vgm">Who</th>
			<th class="flosc-member-protection-col-depth">Available</th>
			<th class="flosc-member-protection-col-level">Required Level</th>
			<th class="flosc-member-protection-col-actions">Actions</th>
		</tr>
	</thead>
	<tbody id="flosc-protection-body">
		<?php if ( ! empty( $flosc_protected_items ) ) : ?>
			<?php foreach ( $flosc_protected_items as $flosc_i => $flosc_item ) : ?>
			<tr class="flosc-protection-row">
				<td>
					<select name="protection_type[]" class="flosc-protection-type flosc-width-full">
						<option value="category" <?php selected( $flosc_item['type'] ?? '', 'category' ); ?>>Category</option>
						<option value="tag" <?php selected( $flosc_item['type'] ?? '', 'tag' ); ?>>Tag</option>
						<option value="post" <?php selected( $flosc_item['type'] ?? '', 'post' ); ?>>Post (ID)</option>
						<option value="page" <?php selected( $flosc_item['type'] ?? '', 'page' ); ?>>Page (ID)</option>
					</select>
				</td>
				<td>
					<?php
					$flosc_item_type = $flosc_item['type'] ?? 'category';
					if ( in_array( $flosc_item_type, array( 'post', 'page' ), true ) ) :
						?>
						<input type="text" name="protection_value[]" 
								value="<?php echo esc_attr( $flosc_item['id'] ?? '' ); ?>" 
								class="regular-text flosc-protection-value" 
								placeholder="Post/Page ID">
					<?php else : ?>
							<select name="protection_value[]" class="flosc-protection-value flosc-width-full">
							<option value="">— Select —</option>
							<?php if ( 'category' === $flosc_item_type ) : ?>
								<?php foreach ( $flosc_categories as $flosc_cat ) : ?>
									<option value="<?php echo esc_attr( $flosc_cat->term_id ); ?>" 
											<?php selected( $flosc_item['id'] ?? '', $flosc_cat->term_id ); ?>>
										<?php echo esc_html( $flosc_cat->name ); ?> (<?php echo esc_html( (string) $flosc_cat->count ); ?> posts)
									</option>
								<?php endforeach; ?>
							<?php elseif ( 'tag' === $flosc_item_type ) : ?>
								<?php foreach ( $flosc_tags as $flosc_tag ) : ?>
									<option value="<?php echo esc_attr( $flosc_tag->term_id ); ?>" 
											<?php selected( $flosc_item['id'] ?? '', $flosc_tag->term_id ); ?>>
										<?php echo esc_html( $flosc_tag->name ); ?> (<?php echo esc_html( (string) $flosc_tag->count ); ?> posts)
									</option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>
					<?php endif; ?>
				</td>
				<td>
					<select name="protection_vgm[]" class="flosc-protection-vgm flosc-width-full">
						<?php echo wp_kses( flosc_vgm_options_markup( $flosc_vgm_tiers, (string) ( $flosc_item['vgm'] ?? 'member' ) ), flosc_vgm_option_allowed_html() ); ?>
					</select>
				</td>
				<td>
					<select name="protection_depth[]" class="flosc-protection-depth flosc-width-full">
						<?php echo wp_kses( flosc_vgm_options_markup( $flosc_vgm_depths, (string) ( $flosc_item['depth'] ?? 'full' ) ), flosc_vgm_option_allowed_html() ); ?>
					</select>
				</td>
				<td>
					<select name="protection_level[]" class="flosc-protection-level-select flosc-width-full">
						<option value="">— Any Member —</option>
						<?php
						foreach ( $flosc_saved_levels as $flosc_lk => $flosc_lv ) :
							$flosc_slug = $flosc_lv['slug'] ?? $flosc_lk;
							if ( empty( $flosc_slug ) ) {
								continue;
							}
							?>
							<option value="<?php echo esc_attr( $flosc_slug ); ?>" 
									<?php selected( $flosc_item['level'] ?? '', $flosc_slug ); ?>>
								<?php echo esc_html( ( $flosc_lv['name'] ?? '' ) ? $flosc_lv['name'] : $flosc_slug ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
				<td class="flosc-text-center">
					<button type="button" class="button flosc-remove-protection-row" title="Remove">&times;</button>
				</td>
			</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>
<p class="flosc-margin-top-10">
	<button type="button" class="button" id="flosc-add-protection">+ Add Protection Rule</button>
</p>
<p class="description flosc-margin-top-6">
	<strong>Category/Tag:</strong> All posts in that category or tag are protected.<br>
	<strong>Post/Page ID:</strong> Protect a specific post or page by its WordPress ID.<br>
	<strong>"Any Member":</strong> Any logged-in member can access, regardless of level.
</p>

<?php
// ─── 3. Guest Access, Freeline & Chats ──────────────────────────────────────

$flosc_free_content_item_mode       = $flosc_flow_settings['free_content_item_mode'] ?? 'fixed';
$flosc_free_content_item_count      = $flosc_flow_settings['free_content_item_count'] ?? 1;
$flosc_free_content_item_proportion = $flosc_flow_settings['free_content_item_proportion'] ?? '1/3';
$flosc_guest_access_days            = $flosc_flow_settings['guest_access_days'] ?? 0;
$flosc_guest_max_chats              = isset( $flosc_flow_settings['guest_max_chats'] )
	? max( 0, intval( $flosc_flow_settings['guest_max_chats'] ) )
	: 0;
$flosc_guest_can_delete_chats       = ! isset( $flosc_flow_settings['guest_can_delete_chats'] )
	|| ! empty( $flosc_flow_settings['guest_can_delete_chats'] );
$flosc_guest_can_rename_chats       = ! isset( $flosc_flow_settings['guest_can_rename_chats'] )
	|| ! empty( $flosc_flow_settings['guest_can_rename_chats'] );
$flosc_guest_new_chat_limit_message = (string) ( $flosc_flow_settings['guest_new_chat_limit_message']
	?? 'Your guest account allows {max} chats listed below. If you would like to start a new chat, you can delete one below.' );
$flosc_chat_list_settings_url       = add_query_arg(
	array(
		'page' => 'flosc-settings',
		'ivr'  => $flosc_current_ivr,
		'tab'  => 'ui',
		'view' => 'single',
	),
	admin_url( 'admin.php' )
);
?>

<hr class="flosc-member-levels-divider">
<h2>Guest Access, Freeline &amp; Chats</h2>
<p>Configure freeline item count, guest access duration, and how many saved chats guests may keep (a taste of membership). Visitors never get multi-chat management — only guests and members do. Primary freeline pool / exclude controls live on the Content tab.</p>

<table class="form-table">
	<tr>
		<th scope="row"><label for="flow_free_content_item_mode">Freeline selection mode</label></th>
		<td>
			<select name="flow_free_content_item_mode" id="flow_free_content_item_mode">
				<option value="fixed" <?php selected( $flosc_free_content_item_mode, 'fixed' ); ?>>Fixed Number</option>
				<option value="proportion" <?php selected( $flosc_free_content_item_mode, 'proportion' ); ?>>Proportion of Missed</option>
			</select>
			<p class="description">How to calculate how many freeline content items guests receive.</p>
		</td>
	</tr>
	<tr id="flow_free_content_item_count_row">
		<th scope="row"><label for="flow_free_content_item_count">Guest selection count</label></th>
		<td>
			<input type="number" id="flow_free_content_item_count" name="flow_free_content_item_count" 
					value="<?php echo esc_attr( $flosc_free_content_item_count ); ?>" min="1" max="50" class="small-text">
			<p class="description">Number of freeline items for guests (Fixed Number mode). Same setting as Content → Guest selection count.</p>
		</td>
	</tr>
	<tr id="flow_free_content_item_proportion_row">
		<th scope="row"><label for="flow_free_content_item_proportion">Proportion of missed</label></th>
		<td>
			<select name="flow_free_content_item_proportion" id="flow_free_content_item_proportion">
				<option value="1/5" <?php selected( $flosc_free_content_item_proportion, '1/5' ); ?>>1/5 of missed items</option>
				<option value="1/4" <?php selected( $flosc_free_content_item_proportion, '1/4' ); ?>>1/4 of missed items</option>
				<option value="1/3" <?php selected( $flosc_free_content_item_proportion, '1/3' ); ?>>1/3 of missed items</option>
				<option value="1/2" <?php selected( $flosc_free_content_item_proportion, '1/2' ); ?>>1/2 of missed items</option>
			</select>
			<p class="description">Proportion of missed quiz items to grant as freeline (Proportion mode).</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_guest_access_days">Guest Access Duration</label></th>
		<td>
			<input type="number" id="flow_guest_access_days" name="flow_guest_access_days" 
					value="<?php echo esc_attr( $flosc_guest_access_days ); ?>" min="0" max="365" class="small-text"> days
			<p class="description">How long guests can access freeline items. Set to 0 for unlimited access.</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_guest_max_chats">Guest max chats</label></th>
		<td>
			<input type="number" id="flow_guest_max_chats" name="flow_guest_max_chats"
					value="<?php echo esc_attr( (string) $flosc_guest_max_chats ); ?>" min="0" max="9999" class="small-text">
			<p class="description">Maximum saved chats for guests. <strong>0 = unlimited</strong>. Example: set <code>3</code> for a flagship taste-of-membership cap.</p>
		</td>
	</tr>
	<tr>
		<th scope="row">Guest chat management</th>
		<td>
			<label for="flow_guest_can_delete_chats">
				<input type="checkbox" id="flow_guest_can_delete_chats" name="flow_guest_can_delete_chats" value="1" <?php checked( $flosc_guest_can_delete_chats ); ?>>
				Guest may delete chats
			</label>
			<br>
			<label for="flow_guest_can_rename_chats">
				<input type="checkbox" id="flow_guest_can_rename_chats" name="flow_guest_can_rename_chats" value="1" <?php checked( $flosc_guest_can_rename_chats ); ?>>
				Guest may rename chats
			</label>
			<p class="description">Rename/delete help guests manage a capped list and feel ownership. Members keep full manage regardless.</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="flow_guest_new_chat_limit_message">Guest New chat limit message</label></th>
		<td>
			<textarea id="flow_guest_new_chat_limit_message" name="flow_guest_new_chat_limit_message" class="large-text" rows="3"><?php echo esc_textarea( $flosc_guest_new_chat_limit_message ); ?></textarea>
			<p class="description">Shown when a guest hits the chat cap. Placeholders: <code>{max}</code>, <code>{count}</code>, <code>{flow_name}</code>, <code>{name}</code>.</p>
			<p class="description">New-chat welcome copy and button labels: <a href="<?php echo esc_url( $flosc_chat_list_settings_url ); ?>">Profile Bar / Chat Navigation</a>.</p>
		</td>
	</tr>
</table>

<!-- ─── How It Works ──────────────────────────────────────────────────── -->
<div class="flosc-member-levels-info-box">
	<h3 class="flosc-member-levels-info-box__title">How Member Levels Work</h3>
	<p class="flosc-member-levels-info-box__lead">
		<strong>1. Define levels</strong> in the registry above (e.g., <code>full_access</code>).<br>
		<strong>2. Protect content</strong> — assign categories, tags, posts, or pages to a level.<br>
		<strong>3. Create an offer</strong> (Offers tab) that <em>grants</em> that level on purchase.<br>
		<strong>4. Purchase completes</strong> → user gets <code>_flosc_memberlevel_{level} = yes</code> → content unlocks.<br>
		<strong>5. AI knows</strong> what content each member can access and serves it in chat.
	</p>
</div>

<!-- ─── JavaScript ────────────────────────────────────────────────────── -->
<?php
$flosc_js_categories = array();
foreach ( (array) $flosc_categories as $flosc_js_term ) {
	$flosc_js_categories[] = array(
		'id'    => (string) $flosc_js_term->term_id,
		'slug'  => (string) $flosc_js_term->slug,
		'name'  => wp_specialchars_decode( (string) $flosc_js_term->name, ENT_QUOTES ),
		'count' => (int) $flosc_js_term->count,
	);
}
$flosc_js_tags = array();
foreach ( (array) $flosc_tags as $flosc_js_term ) {
	$flosc_js_tags[] = array(
		'id'    => (string) $flosc_js_term->term_id,
		'slug'  => (string) $flosc_js_term->slug,
		'name'  => wp_specialchars_decode( (string) $flosc_js_term->name, ENT_QUOTES ),
		'count' => (int) $flosc_js_term->count,
	);
}
$flosc_js_levels = array();
foreach ( (array) $flosc_saved_levels as $flosc_js_level_key => $flosc_js_level ) {
	$flosc_js_level_slug = $flosc_js_level['slug'] ?? $flosc_js_level_key;
	if ( empty( $flosc_js_level_slug ) ) {
		continue;
	}
	$flosc_js_level_label = ( $flosc_js_level['name'] ?? '' ) ? $flosc_js_level['name'] : $flosc_js_level_slug;
	$flosc_js_levels[]    = array(
		'slug'  => (string) $flosc_js_level_slug,
		'label' => wp_specialchars_decode( (string) $flosc_js_level_label, ENT_QUOTES ),
	);
}
wp_enqueue_script( 'flosc-admin-member-levels', FLOSC_PLUGIN_URL . 'assets/js/admin/flosc-admin-member-levels.js', array( 'jquery', 'flosc-admin' ), FLOSC_VERSION, true );
wp_localize_script(
	'flosc-admin-member-levels',
	'floscAdminMemberLevels',
	array(
		'categories' => $flosc_js_categories,
		'tags'       => $flosc_js_tags,
		'levels'     => $flosc_js_levels,
		'vgmTiers'   => array_map( null, array_keys( (array) $flosc_vgm_tiers ), array_values( (array) $flosc_vgm_tiers ) ),
		'vgmDepths'  => array_map( null, array_keys( (array) $flosc_vgm_depths ), array_values( (array) $flosc_vgm_depths ) ),
	)
); ?>
