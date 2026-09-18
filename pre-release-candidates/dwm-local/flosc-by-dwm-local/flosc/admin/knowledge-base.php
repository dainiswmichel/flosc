<?php
/**
 * Knowledge Base tab: named bases, drag-drop files, VGM per file, attach to this flow.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

flosc_tab_header( '📚', 'Knowledge Base' );

$flosc_current_ivr   = (string) ( $GLOBALS['flosc_current_ivr'] ?? '' );
$flosc_get           = isset( $GLOBALS['flosc_get'] ) && is_array( $GLOBALS['flosc_get'] ) ? $GLOBALS['flosc_get'] : array();
$flosc_kb_view       = isset( $flosc_get['view'] ) ? sanitize_key( (string) $flosc_get['view'] ) : 'single';
if ( ! in_array( $flosc_kb_view, array( 'single', 'all' ), true ) ) {
	$flosc_kb_view = 'single';
}

$flosc_stem = sanitize_key( pathinfo( $flosc_current_ivr, PATHINFO_FILENAME ) );
if ( function_exists( 'flosc_knowledge_bases_migrate_legacy_flow' ) ) {
	flosc_knowledge_bases_migrate_legacy_flow( $flosc_stem );
}

$flosc_all_kbs  = function_exists( 'flosc_knowledge_bases_get_all' ) ? flosc_knowledge_bases_get_all() : array();
$flosc_attached = function_exists( 'flosc_flow_knowledge_base_ids' ) ? flosc_flow_knowledge_base_ids( $flosc_stem ) : array();
$flosc_editing  = isset( $flosc_get['kb_edit'] ) ? sanitize_file_name( (string) $flosc_get['kb_edit'] ) : '';
$flosc_edit_kb  = isset( $flosc_get['kb_id'] ) ? sanitize_key( (string) $flosc_get['kb_id'] ) : '';

$flosc_single_url = add_query_arg(
	array(
		'page' => 'flosc-settings',
		'ivr'  => $flosc_current_ivr,
		'tab'  => 'knowledge-base',
		'view' => 'single',
	),
	admin_url( 'admin.php' )
);
$flosc_all_url    = add_query_arg(
	array(
		'page' => 'flosc-settings',
		'ivr'  => $flosc_current_ivr,
		'tab'  => 'knowledge-base',
		'view' => 'all',
	),
	admin_url( 'admin.php' )
);

$flosc_kb_notice = get_transient( 'flosc_kb_notice_' . get_current_user_id() );
if ( is_array( $flosc_kb_notice ) ) {
	delete_transient( 'flosc_kb_notice_' . get_current_user_id() );
	$flosc_kb_action = sanitize_key( (string) ( $flosc_kb_notice['action'] ?? '' ) );
	$flosc_kb_error  = sanitize_text_field( (string) ( $flosc_kb_notice['error'] ?? '' ) );
} else {
	$flosc_kb_action = '';
	$flosc_kb_error  = '';
}
$flosc_kb_msg = '';
if ( 'uploaded' === $flosc_kb_action ) {
	$flosc_kb_msg = __( 'File uploaded.', 'flosc' );
} elseif ( 'deleted' === $flosc_kb_action ) {
	$flosc_kb_msg = __( 'File deleted.', 'flosc' );
} elseif ( 'toggled' === $flosc_kb_action ) {
	$flosc_kb_msg = __( 'Access level updated.', 'flosc' );
} elseif ( 'saved' === $flosc_kb_action ) {
	$flosc_kb_msg = __( 'File saved.', 'flosc' );
} elseif ( 'created' === $flosc_kb_action ) {
	$flosc_kb_msg = __( 'Knowledge base created.', 'flosc' );
} elseif ( 'error' === $flosc_kb_action ) {
	$flosc_kb_msg = ( '' !== $flosc_kb_error ) ? $flosc_kb_error : __( 'An error occurred.', 'flosc' );
}

$flosc_vgm = array(
	'visitor' => array( __( 'Visitor', 'flosc' ), 'flosc-inline-badge flosc-inline-badge--visitor' ),
	'guest'   => array( __( 'Guest', 'flosc' ), 'flosc-inline-badge flosc-inline-badge--guest' ),
	'member'  => array( __( 'Member', 'flosc' ), 'flosc-inline-badge flosc-inline-badge--member' ),
);
?>

<div class="flosc-view-toggle-row">
	<a href="<?php echo esc_url( $flosc_single_url ); ?>" class="button <?php echo 'single' === $flosc_kb_view ? 'button-primary' : ''; ?>"><?php echo esc_html__( 'This flow', 'flosc' ); ?></a>
	<a href="<?php echo esc_url( $flosc_all_url ); ?>" class="button <?php echo 'all' === $flosc_kb_view ? 'button-primary' : ''; ?>"><?php echo esc_html__( 'All knowledge bases', 'flosc' ); ?></a>
</div>

<?php if ( '' !== $flosc_kb_msg ) : ?>
<div class="notice <?php echo 'error' === $flosc_kb_action ? 'notice-error' : 'notice-success'; ?> inline"><p><?php echo esc_html( $flosc_kb_msg ); ?></p></div>
<?php endif; ?>

<p class="description">
	<?php echo esc_html__( 'A knowledge base is a named set of markdown files. Attach bases to this floscFlow. Each file is Visitor, Guest, or Member (cumulative). Chat injects only files this user may see.', 'flosc' ); ?>
</p>

<?php if ( 'single' === $flosc_kb_view ) : ?>
<h3><?php echo esc_html__( 'Attached to this flow', 'flosc' ); ?></h3>
<p class="description"><?php echo esc_html__( 'Save Settings after changing checkboxes.', 'flosc' ); ?></p>
<table class="widefat striped">
	<thead>
		<tr>
			<th><?php echo esc_html__( 'Attach', 'flosc' ); ?></th>
			<th><?php echo esc_html__( 'Knowledge base', 'flosc' ); ?></th>
			<th><?php echo esc_html__( 'Id', 'flosc' ); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php if ( array() === $flosc_all_kbs ) : ?>
		<tr><td colspan="3"><?php echo esc_html__( 'No knowledge bases yet. Create one below (All knowledge bases view).', 'flosc' ); ?></td></tr>
	<?php else : ?>
		<?php foreach ( $flosc_all_kbs as $flosc_kid => $flosc_krow ) : ?>
		<tr>
			<td>
				<label>
					<input type="checkbox" name="flow_knowledge_base_ids[]" value="<?php echo esc_attr( $flosc_kid ); ?>" form="flosc-settings-form" <?php checked( in_array( $flosc_kid, $flosc_attached, true ) ); ?>>
				</label>
			</td>
			<td><?php echo esc_html( (string) ( $flosc_krow['label'] ?? $flosc_kid ) ); ?></td>
			<td><code><?php echo esc_html( $flosc_kid ); ?></code></td>
		</tr>
		<?php endforeach; ?>
	<?php endif; ?>
	</tbody>
</table>
<?php endif; ?>

<?php
if ( empty( $GLOBALS['flosc_settings_form_closed_early'] ) ) {
	echo '</form>';
	$GLOBALS['flosc_settings_form_closed_early'] = true;
}
?>

<h3><?php echo 'all' === $flosc_kb_view ? esc_html__( 'Create a knowledge base', 'flosc' ) : esc_html__( 'Files in attached bases', 'flosc' ); ?></h3>

<?php if ( 'all' === $flosc_kb_view ) : ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flosc-margin-bottom-20">
	<?php wp_nonce_field( 'flosc_kb_create', 'flosc_kb_create_nonce' ); ?>
	<input type="hidden" name="action" value="flosc_kb_create">
	<input type="hidden" name="flosc_return_ivr" value="<?php echo esc_attr( $flosc_current_ivr ); ?>">
	<p>
		<label for="flosc_kb_new_label"><?php echo esc_html__( 'Name', 'flosc' ); ?></label>
		<input type="text" id="flosc_kb_new_label" name="kb_label" class="regular-text" required placeholder="<?php echo esc_attr__( 'e.g. Guitar playing secrets', 'flosc' ); ?>">
		<button type="submit" class="button button-primary"><?php echo esc_html__( 'Create knowledge base', 'flosc' ); ?></button>
	</p>
</form>
<?php endif; ?>

<?php
$flosc_list_ids = ( 'all' === $flosc_kb_view ) ? array_keys( $flosc_all_kbs ) : $flosc_attached;
foreach ( $flosc_list_ids as $flosc_kid ) :
	$flosc_krow   = isset( $flosc_all_kbs[ $flosc_kid ] ) ? $flosc_all_kbs[ $flosc_kid ] : array(
		'id'     => $flosc_kid,
		'label'  => $flosc_kid,
		'access' => array(),
	);
	$flosc_kdir   = function_exists( 'flosc_knowledge_base_dir' ) ? flosc_knowledge_base_dir( $flosc_kid ) : '';
	$flosc_kfiles = array();
	if ( '' !== $flosc_kdir && is_dir( $flosc_kdir ) ) {
		$flosc_found = glob( $flosc_kdir . '*.{md,txt}', GLOB_BRACE );
		if ( ! is_array( $flosc_found ) ) {
			$flosc_found = array();
		}
		foreach ( $flosc_found as $flosc_fp ) {
			$flosc_kfiles[] = basename( $flosc_fp );
		}
	}
	sort( $flosc_kfiles );
	$flosc_drop_id = 'flosc-kb-drop-' . $flosc_kid;
	?>
<div class="card flosc-card-max-full flosc-margin-bottom-20" data-kb-id="<?php echo esc_attr( $flosc_kid ); ?>">
	<h2><?php echo esc_html( (string) ( $flosc_krow['label'] ?? $flosc_kid ) ); ?> <code><?php echo esc_html( $flosc_kid ); ?></code></h2>

	<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flosc-kb-upload-form">
		<?php wp_nonce_field( 'flosc_kb_upload', 'flosc_kb_upload_nonce' ); ?>
		<input type="hidden" name="action" value="flosc_kb_upload">
		<input type="hidden" name="flosc_return_ivr" value="<?php echo esc_attr( $flosc_current_ivr ); ?>">
		<input type="hidden" name="kb_id" value="<?php echo esc_attr( $flosc_kid ); ?>">
		<p>
			<label><?php echo esc_html__( 'Access for new files', 'flosc' ); ?>
				<select name="file_access_level">
					<option value="visitor"><?php echo esc_html__( 'Visitor', 'flosc' ); ?></option>
					<option value="guest"><?php echo esc_html__( 'Guest', 'flosc' ); ?></option>
					<option value="member"><?php echo esc_html__( 'Member', 'flosc' ); ?></option>
				</select>
			</label>
		</p>
		<div id="<?php echo esc_attr( $flosc_drop_id ); ?>" class="flosc-kb-dropzone">
			<p><?php echo esc_html__( 'Drop .md or .txt files here, or choose files.', 'flosc' ); ?></p>
			<label class="screen-reader-text" for="<?php echo esc_attr( $flosc_drop_id ); ?>-files"><?php echo esc_html__( 'Knowledge files', 'flosc' ); ?></label>
			<input id="<?php echo esc_attr( $flosc_drop_id ); ?>-files" type="file" name="orientation_file[]" accept=".md,.txt" multiple>
		</div>
		<p><button type="submit" class="button"><?php echo esc_html__( 'Upload', 'flosc' ); ?></button></p>
	</form>

	<?php if ( array() === $flosc_kfiles ) : ?>
	<p class="description"><?php echo esc_html__( 'No files in this knowledge base yet.', 'flosc' ); ?></p>
	<?php else : ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'File', 'flosc' ); ?></th>
				<th><?php echo esc_html__( 'Access', 'flosc' ); ?></th>
				<th><?php echo esc_html__( 'Actions', 'flosc' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php
		foreach ( $flosc_kfiles as $flosc_kbf ) :
			$flosc_tier   = function_exists( 'flosc_knowledge_base_file_access' ) ? flosc_knowledge_base_file_access( $flosc_kid, $flosc_kbf ) : 'visitor';
			$flosc_badge  = isset( $flosc_vgm[ $flosc_tier ] ) ? $flosc_vgm[ $flosc_tier ] : $flosc_vgm['visitor'];
			$flosc_toggle = wp_nonce_url(
				admin_url( 'admin-post.php?action=flosc_kb_toggle&kb_id=' . rawurlencode( $flosc_kid ) . '&kb_file=' . rawurlencode( $flosc_kbf ) . '&return_ivr=' . rawurlencode( $flosc_current_ivr ) ),
				'flosc_kb_toggle_' . $flosc_kid . '_' . $flosc_kbf
			);
			$flosc_delete = wp_nonce_url(
				admin_url( 'admin-post.php?action=flosc_kb_delete&kb_id=' . rawurlencode( $flosc_kid ) . '&kb_file=' . rawurlencode( $flosc_kbf ) . '&return_ivr=' . rawurlencode( $flosc_current_ivr ) ),
				'flosc_kb_delete_' . $flosc_kid . '_' . $flosc_kbf
			);
			$flosc_edit   = add_query_arg(
				array(
					'page'    => 'flosc-settings',
					'ivr'     => $flosc_current_ivr,
					'tab'     => 'knowledge-base',
					'view'    => $flosc_kb_view,
					'kb_id'   => $flosc_kid,
					'kb_edit' => $flosc_kbf,
				),
				admin_url( 'admin.php' )
			);
			?>
			<tr>
				<td><strong><?php echo esc_html( $flosc_kbf ); ?></strong></td>
				<td><span class="<?php echo esc_attr( $flosc_badge[1] ); ?>"><?php echo esc_html( $flosc_badge[0] ); ?></span></td>
				<td>
					<a class="button button-small" href="<?php echo esc_url( $flosc_edit ); ?>"><?php echo esc_html__( 'Edit', 'flosc' ); ?></a>
					<a class="button button-small" href="<?php echo esc_url( $flosc_toggle ); ?>"><?php echo esc_html__( 'Toggle Access', 'flosc' ); ?></a>
					<a class="button button-small" href="<?php echo esc_url( $flosc_delete ); ?>" data-confirm-message="<?php echo esc_attr( sprintf( /* translators: %s: filename */ __( 'Delete %s? This cannot be undone.', 'flosc' ), $flosc_kbf ) ); ?>"><?php echo esc_html__( 'Delete', 'flosc' ); ?></a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>

	<?php
	if ( '' !== $flosc_editing && $flosc_kid === $flosc_edit_kb && '' !== $flosc_kdir ) :
		$flosc_edit_path = $flosc_kdir . $flosc_editing;
		$flosc_edit_body = ( file_exists( $flosc_edit_path ) && function_exists( 'flosc_fs_get_contents' ) ) ? flosc_fs_get_contents( $flosc_edit_path ) : '';
		?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'flosc_kb_save_edit', 'flosc_kb_save_edit_nonce' ); ?>
		<input type="hidden" name="action" value="flosc_kb_save_edit">
		<input type="hidden" name="flosc_return_ivr" value="<?php echo esc_attr( $flosc_current_ivr ); ?>">
		<input type="hidden" name="kb_id" value="<?php echo esc_attr( $flosc_kid ); ?>">
		<input type="hidden" name="editing_file" value="<?php echo esc_attr( $flosc_editing ); ?>">
		<h4><?php echo esc_html( sprintf( /* translators: %s: filename */ __( 'Editing %s', 'flosc' ), $flosc_editing ) ); ?></h4>
		<textarea name="file_content" rows="24" class="large-text code"><?php echo esc_textarea( (string) $flosc_edit_body ); ?></textarea>
		<p>
			<button type="submit" class="button button-primary"><?php echo esc_html__( 'Save file', 'flosc' ); ?></button>
			<a class="button" href="<?php echo esc_url( 'all' === $flosc_kb_view ? $flosc_all_url : $flosc_single_url ); ?>"><?php echo esc_html__( 'Cancel', 'flosc' ); ?></a>
		</p>
	</form>
	<?php endif; ?>
</div>
<?php endforeach; ?>

<?php
ob_start();
?>
(function () {
	document.querySelectorAll('.flosc-kb-dropzone').forEach(function (zone) {
		var input = zone.querySelector('input[type="file"]');
		if (!input) return;
		zone.addEventListener('dragover', function (e) {
			e.preventDefault();
			zone.classList.add('is-drag');
		});
		zone.addEventListener('dragleave', function () {
			zone.classList.remove('is-drag');
		});
		zone.addEventListener('drop', function (e) {
			e.preventDefault();
			zone.classList.remove('is-drag');
			if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
				input.files = e.dataTransfer.files;
				var form = zone.closest('form');
				if (form) form.submit();
			}
		});
	});
})();
<?php
wp_add_inline_script( 'flosc-admin', ob_get_clean() );
