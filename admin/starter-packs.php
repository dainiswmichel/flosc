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

// Handle install / remove.
if ( isset( $_POST['flosc_sp_action'], $_POST['flosc_sp_slug'] ) ) {
	check_admin_referer( 'flosc_starter_packs' );

	$flosc_sp_slug   = sanitize_key( wp_unslash( $_POST['flosc_sp_slug'] ) );
	$flosc_sp_action = sanitize_key( wp_unslash( $_POST['flosc_sp_action'] ) );

	if ( 'install' === $flosc_sp_action ) {
		$flosc_sp_notice = FLOSC_Starter_Packs::install( $flosc_sp_slug );
	} elseif ( 'remove' === $flosc_sp_action ) {
		$flosc_sp_notice = FLOSC_Starter_Packs::uninstall( $flosc_sp_slug );
	}
}

$flosc_sp_packs = FLOSC_Starter_Packs::discover();
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

	<?php if ( empty( $flosc_sp_packs ) ) : ?>
		<p><?php esc_html_e( 'No starter packs are available in this build.', 'flosc' ); ?></p>
	<?php else : ?>

		<div class="flosc-sp-grid">
			<?php
			foreach ( $flosc_sp_packs as $flosc_sp_pack ) :
				$flosc_sp_installed = FLOSC_Starter_Packs::is_installed( $flosc_sp_pack['slug'] );
				?>
				<div class="flosc-sp-card<?php echo $flosc_sp_installed ? ' is-installed' : ''; ?>">

					<h3>
						<?php echo esc_html( (string) ( $flosc_sp_pack['name'] ?? $flosc_sp_pack['slug'] ) ); ?>
						<?php if ( $flosc_sp_installed ) : ?>
							<span class="flosc-sp-badge"><?php esc_html_e( 'Installed', 'flosc' ); ?></span>
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

<style>
.flosc-sp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(22rem, 1fr)); gap: 16px; margin-top: 18px; max-width: 78rem; }
.flosc-sp-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 18px 20px 20px; display: flex; flex-direction: column; }
.flosc-sp-card.is-installed { border-color: #2c6349; }
.flosc-sp-card h3 { margin: 0 0 8px; font-size: 15px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.flosc-sp-badge { font-size: 11px; letter-spacing: .06em; text-transform: uppercase; font-weight: 600; color: #2c6349; background: #e6f0ea; border: 1px solid #2c6349; border-radius: 3px; padding: 1px 6px; }
.flosc-sp-summary { margin: 0 0 10px; color: #50575e; }
.flosc-sp-conversion { margin: 0 0 12px; font-size: 13px; }
.flosc-sp-conversion span { font-size: 11px; letter-spacing: .07em; text-transform: uppercase; color: #646970; font-weight: 600; margin-right: 6px; }
.flosc-sp-installs { margin: 0 0 16px; padding-left: 18px; font-size: 13px; color: #50575e; }
.flosc-sp-installs li { margin-bottom: 3px; }
.flosc-sp-actions { margin-top: auto; }
</style>
