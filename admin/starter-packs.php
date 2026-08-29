<?php
/**
 * Starter Packs tab — install a complete working journey in one click.
 *
 * Deliberately plain: a card, a button, and the two steps that follow. Anything
 * an operator has to decide before their bot talks is a step between them and
 * seeing FLOSC work.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$flosc_sp_notice = array();

// Handle install / remove. Nonce first, then read anything from the request.
if ( isset( $_POST['flosc_sp_action'] ) || isset( $_POST['flosc_sp_slug'] ) ) {
	check_admin_referer( 'flosc_starter_packs' );

	$flosc_sp_slug   = isset( $_POST['flosc_sp_slug'] ) ? sanitize_key( wp_unslash( $_POST['flosc_sp_slug'] ) ) : '';
	$flosc_sp_action = isset( $_POST['flosc_sp_action'] ) ? sanitize_key( wp_unslash( $_POST['flosc_sp_action'] ) ) : '';

	if ( 'install' === $flosc_sp_action ) {
		$flosc_sp_notice = FLOSC_Starter_Packs::install( $flosc_sp_slug );
	} elseif ( 'remove' === $flosc_sp_action ) {
		$flosc_sp_notice = FLOSC_Starter_Packs::uninstall( $flosc_sp_slug );
	} elseif ( 'repair' === $flosc_sp_action ) {
		$flosc_sp_notice = FLOSC_Starter_Packs::repair( $flosc_sp_slug );
	} elseif ( 'personality' === $flosc_sp_action ) {
		$flosc_sp_personality = isset( $_POST['flosc_sp_personality'] )
			? sanitize_key( wp_unslash( $_POST['flosc_sp_personality'] ) )
			: '';
		$flosc_sp_notice = FLOSC_Starter_Packs::set_personality( $flosc_sp_slug, $flosc_sp_personality );
	}
}

$flosc_sp_packs   = FLOSC_Starter_Packs::discover();
$flosc_sp_state   = FLOSC_Starter_Packs::state();
$flosc_sp_voices  = function_exists( 'flosc_personality_library_get_all' ) ? flosc_personality_library_get_all() : array();

if ( ! function_exists( 'flosc_sp_tab_url' ) ) {
	/**
	 * Admin URL for one FLOSC settings tab, optionally scoped to a flow.
	 *
	 * @param string $tab      Tab id as registered in admin/settings.php.
	 * @param string $ivr_file Flow file the tab should open against.
	 * @return string
	 */
	function flosc_sp_tab_url( $tab, $ivr_file = '' ) {
		$args = array(
			'page' => 'flosc-settings',
			'tab'  => $tab,
		);

		if ( '' !== $ivr_file ) {
			$args['ivr'] = $ivr_file;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}
}
?>

<div class="flosc-starter-packs">

	<h2><?php esc_html_e( 'Starter Packs', 'flosc' ); ?></h2>
	<p class="description" style="max-width:44rem">
		<?php esc_html_e( 'A starter pack installs a complete journey — the flow, the content it talks about, and the access gating — so you can see FLOSC working before you configure anything. Install one, choose a personality, connect an AI provider, and your bot is live.', 'flosc' ); ?>
	</p>

	<?php if ( ! empty( $flosc_sp_notice ) ) : ?>
		<div class="notice <?php echo esc_attr( $flosc_sp_notice['ok'] ? 'notice-success' : 'notice-error' ); ?>">
			<p><strong><?php echo esc_html( $flosc_sp_notice['message'] ); ?></strong></p>
			<?php foreach ( (array) $flosc_sp_notice['detail'] as $flosc_sp_line ) : ?>
				<p style="margin:.2em 0"><?php echo esc_html( $flosc_sp_line ); ?></p>
			<?php endforeach; ?>
			<?php if ( $flosc_sp_notice['ok'] ) : ?>
				<p>
					<?php esc_html_e( 'Next: choose a personality on the Identity tab, then add an API key on the AI tab.', 'flosc' ); ?>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $flosc_sp_voices ) ) : ?>
		<div class="flosc-sp-voices-shipped">
			<h3><?php esc_html_e( 'Voices that ship with FLOSC', 'flosc' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'A personality is separate from any flow — one voice can curate any journey, and any content on this site. Install a pack, then switch its voice on the card and watch the same content get a different host.', 'flosc' ); ?>
			</p>
			<ul>
				<?php foreach ( $flosc_sp_voices as $flosc_sp_v_id => $flosc_sp_v ) : ?>
					<li>
						<strong><?php echo esc_html( (string) ( $flosc_sp_v['label'] ?? $flosc_sp_v_id ) ); ?></strong>
						<span><?php echo esc_html( (string) ( $flosc_sp_v['ai_personality_role'] ?? '' ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( empty( $flosc_sp_packs ) ) : ?>
		<p><?php esc_html_e( 'No starter packs are available in this build.', 'flosc' ); ?></p>
	<?php else : ?>

		<div class="flosc-sp-grid">
			<?php
			foreach ( $flosc_sp_packs as $flosc_sp_pack ) :
				$flosc_sp_installed = FLOSC_Starter_Packs::is_installed( $flosc_sp_pack['slug'] );
				$flosc_sp_status    = FLOSC_Starter_Packs::status( $flosc_sp_pack['slug'] );
				$flosc_sp_labels    = array(
					'installed'           => __( 'Installed', 'flosc' ),
					'needs_configuration' => __( 'Needs configuration', 'flosc' ),
					'needs_repair'        => __( 'Needs repair', 'flosc' ),
				);
				?>
				<div class="<?php echo esc_attr( $flosc_sp_installed ? 'flosc-sp-card is-installed' : 'flosc-sp-card' ); ?>">

					<h3>
						<?php echo esc_html( (string) ( $flosc_sp_pack['name'] ?? $flosc_sp_pack['slug'] ) ); ?>
						<?php if ( $flosc_sp_installed && isset( $flosc_sp_labels[ $flosc_sp_status['state'] ] ) ) : ?>
							<span class="<?php echo esc_attr( 'flosc-sp-badge is-' . str_replace( '_', '-', $flosc_sp_status['state'] ) ); ?>">
								<?php echo esc_html( $flosc_sp_labels[ $flosc_sp_status['state'] ] ); ?>
							</span>
						<?php endif; ?>
					</h3>

					<?php if ( ! empty( $flosc_sp_pack['summary'] ) ) : ?>
						<p class="flosc-sp-summary"><?php echo esc_html( (string) $flosc_sp_pack['summary'] ); ?></p>
					<?php endif; ?>

					<?php if ( ! empty( $flosc_sp_pack['conversion'] ) ) : ?>
						<p class="flosc-sp-conversion">
							<span><?php esc_html_e( 'Sells', 'flosc' ); ?></span>
							<?php echo esc_html( (string) $flosc_sp_pack['conversion'] ); ?>
						</p>
					<?php endif; ?>

					<?php if ( ! empty( $flosc_sp_pack['installs'] ) && is_array( $flosc_sp_pack['installs'] ) ) : ?>
						<ul class="flosc-sp-installs">
							<?php foreach ( $flosc_sp_pack['installs'] as $flosc_sp_item ) : ?>
								<li><?php echo esc_html( (string) $flosc_sp_item ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<?php
					if ( $flosc_sp_installed ) :
						$flosc_sp_record = isset( $flosc_sp_state[ $flosc_sp_pack['slug'] ] ) ? $flosc_sp_state[ $flosc_sp_pack['slug'] ] : array();
						$flosc_sp_flow   = (string) ( $flosc_sp_record['flow_file'] ?? '' );
						$flosc_sp_bag    = ! empty( $flosc_sp_record['flow_option'] ) ? get_option( (string) $flosc_sp_record['flow_option'], array() ) : array();
						$flosc_sp_voice  = is_array( $flosc_sp_bag ) ? (string) ( $flosc_sp_bag['personality_library_id'] ?? '' ) : '';
						?>

						<?php if ( ! empty( $flosc_sp_voices ) && '' !== $flosc_sp_flow ) : ?>
							<div class="flosc-sp-voice">
								<form method="post">
									<?php wp_nonce_field( 'flosc_starter_packs' ); ?>
									<input type="hidden" name="flosc_sp_slug" value="<?php echo esc_attr( $flosc_sp_pack['slug'] ); ?>">
									<input type="hidden" name="flosc_sp_action" value="personality">

									<label for="flosc-sp-voice-<?php echo esc_attr( $flosc_sp_pack['slug'] ); ?>">
										<?php esc_html_e( 'Curated by', 'flosc' ); ?>
									</label>

									<select name="flosc_sp_personality" id="flosc-sp-voice-<?php echo esc_attr( $flosc_sp_pack['slug'] ); ?>">
										<?php foreach ( $flosc_sp_voices as $flosc_sp_voice_id => $flosc_sp_voice_row ) : ?>
											<option value="<?php echo esc_attr( $flosc_sp_voice_id ); ?>" <?php selected( $flosc_sp_voice, $flosc_sp_voice_id ); ?>>
												<?php echo esc_html( (string) ( $flosc_sp_voice_row['label'] ?? $flosc_sp_voice_id ) ); ?>
											</option>
										<?php endforeach; ?>
									</select>

									<button type="submit" class="button"><?php esc_html_e( 'Switch voice', 'flosc' ); ?></button>
								</form>
								<p class="description">
									<?php esc_html_e( 'Same posts, same journey, different host. Switch, then open the flow and talk to it.', 'flosc' ); ?>
								</p>
							</div>
						<?php endif; ?>

						<ul class="flosc-sp-next">
							<?php if ( '' !== $flosc_sp_flow ) : ?>
								<li><a href="<?php echo esc_url( flosc_sp_tab_url( 'flow', $flosc_sp_flow ) ); ?>"><?php esc_html_e( 'Open the flow', 'flosc' ); ?></a></li>
								<li><a href="<?php echo esc_url( flosc_sp_tab_url( 'ai', $flosc_sp_flow ) ); ?>"><?php esc_html_e( 'Connect AI', 'flosc' ); ?></a></li>
								<li><a href="<?php echo esc_url( flosc_sp_tab_url( 'offers', $flosc_sp_flow ) ); ?>"><?php esc_html_e( 'Configure the offer', 'flosc' ); ?></a></li>
							<?php endif; ?>

							<?php if ( ! empty( $flosc_sp_record['catalog_key'] ) ) : ?>
								<li><a href="<?php echo esc_url( flosc_sp_tab_url( 'da1' ) ); ?>"><?php esc_html_e( 'Open the DA1 catalog', 'flosc' ); ?></a></li>
							<?php endif; ?>

							<?php
							if ( ! empty( $flosc_sp_record['categories'] ) && is_array( $flosc_sp_record['categories'] ) ) :
								foreach ( $flosc_sp_record['categories'] as $flosc_sp_cat ) :
									?>
									<li>
										<a href="<?php echo esc_url( admin_url( 'edit.php?cat=' . (int) $flosc_sp_cat['id'] ) ); ?>">
											<?php
											printf(
												/* translators: 1: number of posts, 2: category name. */
												esc_html__( '%1$d posts in %2$s', 'flosc' ),
												(int) $flosc_sp_cat['count'],
												esc_html( (string) $flosc_sp_cat['name'] )
											);
											?>
										</a>
									</li>
									<?php
								endforeach;
							endif;
							?>
						</ul>

						<?php if ( ! empty( $flosc_sp_status['missing'] ) ) : ?>
							<div class="flosc-sp-repair">
								<p>
									<strong><?php esc_html_e( 'Missing since install:', 'flosc' ); ?></strong>
									<?php echo esc_html( implode( ', ', $flosc_sp_status['missing'] ) ); ?>
								</p>
								<form method="post">
									<?php wp_nonce_field( 'flosc_starter_packs' ); ?>
									<input type="hidden" name="flosc_sp_slug" value="<?php echo esc_attr( $flosc_sp_pack['slug'] ); ?>">
									<input type="hidden" name="flosc_sp_action" value="repair">
									<button type="submit" class="button"><?php esc_html_e( 'Repair', 'flosc' ); ?></button>
									<span class="description"><?php esc_html_e( 'Puts back only what is gone. Anything you edited is left alone.', 'flosc' ); ?></span>
								</form>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $flosc_sp_pack['needs_configuration'] ) ) : ?>
							<p class="flosc-sp-config"><?php echo esc_html( (string) $flosc_sp_pack['needs_configuration'] ); ?></p>
						<?php endif; ?>
					<?php endif; ?>

					<form method="post" class="flosc-sp-actions"
						<?php if ( $flosc_sp_installed ) : ?>
							onsubmit="return confirm('<?php echo esc_js( __( 'Remove this starter pack and everything it created?', 'flosc' ) ); ?>');"
						<?php endif; ?>
					>
						<?php wp_nonce_field( 'flosc_starter_packs' ); ?>
						<input type="hidden" name="flosc_sp_slug" value="<?php echo esc_attr( $flosc_sp_pack['slug'] ); ?>">

						<?php if ( $flosc_sp_installed ) : ?>
							<input type="hidden" name="flosc_sp_action" value="remove">
							<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Remove', 'flosc' ); ?></button>
						<?php else : ?>
							<input type="hidden" name="flosc_sp_action" value="install">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Extract &amp; Install', 'flosc' ); ?></button>
						<?php endif; ?>
					</form>

				</div>
			<?php endforeach; ?>
		</div>

	<?php endif; ?>
</div>

<?php // Starter Packs styles (.flosc-sp-*) live in assets/css/flosc-admin.css, enqueued on FLOSC admin pages. ?>
