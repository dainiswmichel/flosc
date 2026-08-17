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

$flosc_provider_meta = array(
	'anthropic'  => array(
		'label' => 'Anthropic',
		'hint'  => 'Claude API keys',
		'url'   => 'https://console.anthropic.com/settings/keys',
	),
	'openai'     => array(
		'label' => 'OpenAI',
		'hint'  => 'OpenAI API keys',
		'url'   => 'https://platform.openai.com/api-keys',
	),
	'xai'        => array(
		'label' => 'xAI',
		'hint'  => 'Grok API keys',
		'url'   => 'https://console.x.ai/',
	),
	'assemblyai' => array(
		'label' => 'AssemblyAI',
		'hint'  => 'Speech-to-text (audio quizzes)',
		'url'   => 'https://www.assemblyai.com/app/account',
	),
);

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
		<?php echo esc_html__( 'Configure API keys once for this FLOSC install. Any floscFlow can attach them. Personalities are listed here; each flow attaches exactly one (no personality chaining — only APIs chain).', 'flosc' ); ?>
	</p>
</div>

<!-- ========== All Flows AI API Management ========== -->
<div class="flosc-info-box flosc-margin-bottom-20">
	<h3 class="flosc-flow-portability-title">
		<span><?php echo esc_html__( 'All Flows AI API Management', 'flosc' ); ?></span>
	</h3>
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

<!-- ========== Personalities library ========== -->
<div class="flosc-info-box">
	<h3 class="flosc-flow-portability-title">
		<span><?php echo esc_html__( 'All Flows: Personalities', 'flosc' ); ?></span>
	</h3>
	<p class="description">
		<?php echo esc_html__( 'Library of voices. Each floscFlow attaches exactly one. Personalities are never chained — only AI APIs chain on the flow’s AI screen.', 'flosc' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'flosc_save_personality_library' ); ?>
		<input type="hidden" name="action" value="flosc_save_personality_library">
		<input type="hidden" name="flosc_return_ivr" value="<?php echo esc_attr( $flosc_current_ivr ); ?>">

		<?php
		$flosc_i = 0;
		foreach ( $flosc_personas as $flosc_pid => $flosc_p ) :
			$flosc_i++;
			?>
		<div class="flosc-card-soft flosc-margin-bottom-16">
			<h4 class="flosc-card-title-reset">
				<?php echo esc_html( $flosc_p['label'] !== '' ? $flosc_p['label'] : $flosc_pid ); ?>
				<code class="description"><?php echo esc_html( $flosc_pid ); ?></code>
			</h4>
			<input type="hidden" name="persona[<?php echo esc_attr( (string) $flosc_i ); ?>][id]" value="<?php echo esc_attr( $flosc_pid ); ?>">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label><?php echo esc_html__( 'Label', 'flosc' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" name="persona[<?php echo esc_attr( (string) $flosc_i ); ?>][label]" value="<?php echo esc_attr( $flosc_p['label'] ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><label><?php echo esc_html__( 'AI name', 'flosc' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" name="persona[<?php echo esc_attr( (string) $flosc_i ); ?>][ai_personality_name]" value="<?php echo esc_attr( $flosc_p['ai_personality_name'] ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><label><?php echo esc_html__( 'Role', 'flosc' ); ?></label></th>
					<td>
						<input type="text" class="large-text" name="persona[<?php echo esc_attr( (string) $flosc_i ); ?>][ai_personality_role]" value="<?php echo esc_attr( $flosc_p['ai_personality_role'] ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><label><?php echo esc_html__( 'Traits', 'flosc' ); ?></label></th>
					<td>
						<textarea class="large-text" rows="2" name="persona[<?php echo esc_attr( (string) $flosc_i ); ?>][ai_personality_traits]"><?php echo esc_textarea( $flosc_p['ai_personality_traits'] ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><label><?php echo esc_html__( 'Mission', 'flosc' ); ?></label></th>
					<td>
						<textarea class="large-text" rows="2" name="persona[<?php echo esc_attr( (string) $flosc_i ); ?>][ai_mission]"><?php echo esc_textarea( $flosc_p['ai_mission'] ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><label><?php echo esc_html__( 'Boundaries', 'flosc' ); ?></label></th>
					<td>
						<textarea class="large-text" rows="3" name="persona[<?php echo esc_attr( (string) $flosc_i ); ?>][ai_boundaries]"><?php echo esc_textarea( $flosc_p['ai_boundaries'] ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><label><?php echo esc_html__( 'Topic scope', 'flosc' ); ?></label></th>
					<td>
						<textarea class="large-text" rows="2" name="persona[<?php echo esc_attr( (string) $flosc_i ); ?>][ai_topic_scope]"><?php echo esc_textarea( $flosc_p['ai_topic_scope'] ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><label><?php echo esc_html__( 'Base system prompt', 'flosc' ); ?></label></th>
					<td>
						<textarea class="large-text code" rows="4" name="persona[<?php echo esc_attr( (string) $flosc_i ); ?>][ai_base_prompt]"><?php echo esc_textarea( $flosc_p['ai_base_prompt'] ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Remove', 'flosc' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="persona_delete[<?php echo esc_attr( $flosc_pid ); ?>]" value="1">
							<?php echo esc_html__( 'Delete this personality from the library', 'flosc' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>
		<?php endforeach; ?>

		<div class="flosc-card-soft flosc-margin-bottom-16">
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
			<p class="description"><?php echo esc_html__( 'Creates an empty entry; edit fields above after save.', 'flosc' ); ?></p>
		</div>

		<p>
			<button type="submit" class="button button-primary"><?php echo esc_html__( 'Save personalities', 'flosc' ); ?></button>
		</p>
	</form>
</div>
