<?php
/**
 * AI tab — view=all: All Flows AI API Management + Personalities library.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$flosc_current_ivr = $GLOBALS['flosc_current_ivr'] ?? '';
$flosc_avail       = function_exists( 'flosc_available_providers_get_all' ) ? flosc_available_providers_get_all() : array();
$flosc_personas    = function_exists( 'flosc_personality_library_get_all' ) ? flosc_personality_library_get_all() : array();
$flosc_attached_pid = '';
if ( function_exists( 'flosc_personality_library_id_for_flow' ) ) {
	$flosc_attached_pid = flosc_personality_library_id_for_flow(
		sanitize_key( pathinfo( (string) $flosc_current_ivr, PATHINFO_FILENAME ) )
	);
}

$flosc_provider_meta = function_exists( 'flosc_install_provider_catalog' ) ? flosc_install_provider_catalog() : array();

// Notices after PRG.
$flosc_ai_all_notice = get_transient( 'flosc_ai_all_notice_' . get_current_user_id() );
if ( is_array( $flosc_ai_all_notice ) ) {
	delete_transient( 'flosc_ai_all_notice_' . get_current_user_id() );
	$flosc_n_type = ( isset( $flosc_ai_all_notice['type'] ) && 'error' === $flosc_ai_all_notice['type'] ) ? 'notice-error' : 'notice-success';
	$flosc_n_msg  = isset( $flosc_ai_all_notice['message'] ) ? (string) $flosc_ai_all_notice['message'] : '';
	if ( $flosc_n_msg !== '' ) {
		echo '<div class="notice ' . esc_attr( $flosc_n_type ) . ' inline"><p>' . esc_html( $flosc_n_msg ) . '</p></div>';
	}
}
?>

<div class="flosc-info-box flosc-margin-bottom-20">
	<p class="flosc-text-zero-margin">
		<?php echo esc_html__( 'Install API keys here. Chat: Anthropic, OpenAI, xAI, Gemini. Speech-to-text: AssemblyAI (and OpenAI Whisper on This flow). Author a personality in Personality Designer. Attach one personality and one chat API on This flow.', 'flosc' ); ?>
	</p>
</div>

<details class="flosc-ai-acc">
<summary class="flosc-ai-acc__summary">
	<span class="flosc-ai-acc__title"><?php echo esc_html__( 'Install API keys', 'flosc' ); ?></span>
	<span class="flosc-ai-acc__hint"><?php echo esc_html__( 'One pool for every floscFlow. Attach a provider on This flow.', 'flosc' ); ?></span>
</summary>
<div class="flosc-ai-acc__body">
	<p class="description">
		<?php echo esc_html__( 'Keys saved here are available to every current and future floscFlow. On a flow’s AI screen, choose primary provider and optional API chain — that is attachment, not a second secret store.', 'flosc' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'flosc_save_available_providers' ); ?>
		<input type="hidden" name="action" value="flosc_save_available_providers">
		<input type="hidden" name="flosc_return_ivr" value="<?php echo esc_attr( $flosc_current_ivr ); ?>">

		<table class="form-table" role="presentation">
			<?php foreach ( $flosc_provider_meta as $flosc_slug => $flosc_meta ) :
				$row = $flosc_avail[ $flosc_slug ] ?? array();
				$has = ! empty( $row['api_key'] );
				?>
			<tr>
				<th scope="row">
					<label for="avail_<?php echo esc_attr( $flosc_slug ); ?>_key"><?php echo esc_html( $flosc_meta['label'] ); ?></label>
				</th>
				<td>
					<input
						type="password"
						class="regular-text"
						id="avail_<?php echo esc_attr( $flosc_slug ); ?>_key"
						name="avail_api_key[<?php echo esc_attr( $flosc_slug ); ?>]"
						value=""
						autocomplete="new-password"
						placeholder="<?php echo $has ? esc_attr__( '•••• key on file — leave blank to keep', 'flosc' ) : esc_attr__( 'Paste API key', 'flosc' ); ?>"
					>
					<p class="description">
						<?php
						echo $has
							? esc_html__( 'Status: available for any floscFlow.', 'flosc' )
							: esc_html__( 'Status: not set.', 'flosc' );
						?>
						<?php if ( ! empty( $flosc_meta['url'] ) ) : ?>
							<a href="<?php echo esc_url( $flosc_meta['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Get a key', 'flosc' ); ?></a>
						<?php endif; ?>
						— <?php echo esc_html( $flosc_meta['hint'] ); ?>
					</p>
					<?php if ( $has ) : ?>
					<label>
						<input type="checkbox" name="avail_clear[<?php echo esc_attr( $flosc_slug ); ?>]" value="1">
						<?php echo esc_html__( 'Clear this key from the install pool', 'flosc' ); ?>
					</label>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</table>
		<p>
			<button type="submit" class="button button-primary"><?php echo esc_html__( 'Save available providers', 'flosc' ); ?></button>
		</p>
	</form>
</div>
</details>

<details class="flosc-ai-acc flosc-ai-acc--designer" id="flosc-personality-library" open>
<summary class="flosc-ai-acc__summary">
	<span class="flosc-ai-acc__title"><?php echo esc_html__( 'Personalities', 'flosc' ); ?></span>
	<span class="flosc-ai-acc__hint"><?php echo esc_html__( 'Inventory. Design is this flow’s attached row.', 'flosc' ); ?></span>
</summary>
<div class="flosc-ai-acc__body">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'flosc_save_personality_library' ); ?>
		<input type="hidden" name="action" value="flosc_save_personality_library">
		<input type="hidden" name="flosc_return_ivr" value="<?php echo esc_attr( $flosc_current_ivr ); ?>">

		<table class="widefat striped flosc-personality-inventory">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Label', 'flosc' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Id', 'flosc' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Status', 'flosc' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Design', 'flosc' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Remove', 'flosc' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
			$flosc_i = 0;
			foreach ( $flosc_personas as $flosc_pid => $flosc_p ) :
				$flosc_i++;
				$flosc_on_file = ! empty( $flosc_p['workshop_json'] ) || ! empty( $flosc_p['ai_base_prompt'] );
				?>
				<tr>
					<td>
						<input type="hidden" name="persona[<?php echo esc_attr( (string) $flosc_i ); ?>][id]" value="<?php echo esc_attr( $flosc_pid ); ?>">
						<input type="text" class="regular-text" name="persona[<?php echo esc_attr( (string) $flosc_i ); ?>][label]" value="<?php echo esc_attr( $flosc_p['label'] ); ?>">
					</td>
					<td><code><?php echo esc_html( $flosc_pid ); ?></code></td>
					<td><?php echo $flosc_on_file ? esc_html__( 'On file', 'flosc' ) : esc_html__( 'Empty', 'flosc' ); ?></td>
					<td>
						<?php if ( current_user_can( 'manage_options' ) && $flosc_pid === $flosc_attached_pid ) : ?>
						<a class="button button-primary" href="<?php echo esc_url( flosc_personality_builder_url( $flosc_pid, $flosc_current_ivr ) ); ?>">
							<?php echo esc_html__( 'Design this flow', 'flosc' ); ?>
						</a>
						<?php else : ?>
						<span class="description"><?php echo esc_html__( 'Attach on This flow', 'flosc' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<label>
							<input type="checkbox" name="persona_delete[<?php echo esc_attr( $flosc_pid ); ?>]" value="1">
							<?php echo esc_html__( 'Delete', 'flosc' ); ?>
						</label>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<div class="flosc-card-soft flosc-margin-bottom-16 flosc-margin-top-16">
			<h4 class="flosc-card-title-reset"><?php echo esc_html__( 'Add personality', 'flosc' ); ?></h4>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="new_persona_id"><?php echo esc_html__( 'Id (slug)', 'flosc' ); ?></label></th>
					<td><input type="text" class="regular-text" id="new_persona_id" name="new_persona_id" placeholder="e.g. support_host"></td>
				</tr>
				<tr>
					<th scope="row"><label for="new_persona_label"><?php echo esc_html__( 'Label', 'flosc' ); ?></label></th>
					<td><input type="text" class="regular-text" id="new_persona_label" name="new_persona_label" placeholder="e.g. Support host"></td>
				</tr>
			</table>
			<p class="description"><?php echo esc_html__( 'Adds an empty row. Open Design to author it, then attach it on This flow.', 'flosc' ); ?></p>
		</div>

		<p>
			<button type="submit" class="button button-primary"><?php echo esc_html__( 'Save personalities', 'flosc' ); ?></button>
		</p>
	</form>
</div>
</details>
