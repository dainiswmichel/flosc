/**
 * Companion tab: display mode, targeting and validation hints.
 *
 * Moved out of the PHP template so the page enqueues a static file; every
 * value that used to be printed into the script now arrives through
 * wp_localize_script().
 */
jQuery(document).ready(function($) {
	var $modeRadios = $('input[name="flow_companion_content_display_mode"]');

	// Toggle widget settings visibility based on display mode
	function toggleCompanionSettings() {
		var mode = $('input[name="flow_companion_content_display_mode"]:checked').val();
		var $settings = $('#companion-widget-settings');
		
		if (mode === 'in_chat') {
			$settings.addClass('flosc-companion-disabled');
		} else {
			$settings.removeClass('flosc-companion-disabled');
		}
		
		// Highlight selected radio card
		$('.flosc-companion-mode-card').removeClass('is-active');
		$('input[name="flow_companion_content_display_mode"]:checked').closest('.flosc-companion-mode-card').addClass('is-active');

		// Companion and Both modes require the widget shell; auto-enable to avoid hidden misconfiguration.
		if ((mode === 'companion' || mode === 'both') && !$('#flow_companion_enabled').is(':checked')) {
			$('#flow_companion_enabled').prop('checked', true);
		}
	}
	
	$('input[name="flow_companion_content_display_mode"]').on('change', toggleCompanionSettings);
	
	// Sync color picker with hex display + preview via CSS custom property (no per-element .css())
	$('#flow_companion_accent_color').on('input', function() {
		var color = $(this).val();
		var preview = document.querySelector('.flosc-companion-preview');
		$('#companion_accent_hex').val(color);
		if (preview) {
			preview.style.setProperty('--flosc-companion-accent', color);
		}
	});

	$('#flosc-companion-color-reset').on('click', function() {
		$('#flow_companion_accent_color').val('#6366f1').trigger('input');
	});

	function updateCompanionValidationHints() {
		var hints = [];
		var autoOpenEnabled = $('#flow_companion_auto_open_enabled').is(':checked');
		var autoOpenDelay = parseInt($('#flow_companion_auto_open_delay_ms').val(), 10) || 0;
		var autoOpenOnce = $('#flow_companion_auto_open_once_per_session').is(':checked');
		var launchOnExit = $('#flow_companion_launch_on_exit_intent').is(':checked');
		var launchOnScroll = $('#flow_companion_launch_on_scroll_threshold').is(':checked');
		var launchScrollPercent = parseInt($('#flow_companion_launch_on_scroll_percent').val(), 10) || 0;
		var cooldownMs = parseInt($('#flow_companion_trigger_cooldown_ms').val(), 10) || 0;
		var rememberOpen = $('#flow_companion_remember_open_state').is(':checked');
		var stateStorage = String($('#flow_companion_state_storage').val() || 'session');

		if (autoOpenEnabled && autoOpenDelay < 500) {
			hints.push('Auto Open Delay is below 500ms. Consider 1000ms+ to reduce intrusive launches.');
		}

		if (!autoOpenEnabled && autoOpenOnce) {
			hints.push('Timed Auto Open Once Per Session is enabled while Auto Open is off. This frequency setting has no effect until Auto Open is enabled.');
		}

		if ((launchOnExit || launchOnScroll) && cooldownMs > 0 && cooldownMs < 5000) {
			hints.push('Trigger Cooldown is below 5000ms while behavior triggers are active. Consider a longer cooldown to avoid repeated opens.');
		}

		if (launchOnScroll && launchScrollPercent === 0) {
			hints.push('Scroll Trigger threshold is set to 0%. Companion can open as soon as scrolling starts.');
		}

		if (!rememberOpen && stateStorage === 'local' && cooldownMs > 0) {
			hints.push('State Storage is set to Local while Remember Open State is off. Trigger cooldown will still persist across browser restarts.');
		} else if (!rememberOpen && stateStorage === 'local') {
			hints.push('State Storage is set to Local while Remember Open State is off. Storage selection mainly affects trigger cooldown behavior in this combination.');
		}

		if (rememberOpen && stateStorage === 'local') {
			hints.push('Remember Open State + Local storage persists companion state across browser restarts for this flow.');
		}

		if (hints.length > 0) {
			$('#flosc-companion-validation-hints-text').html(hints.join('<br>'));
			$('#flosc-companion-validation-hints').show();
		} else {
			$('#flosc-companion-validation-hints').hide();
			$('#flosc-companion-validation-hints-text').empty();
		}
	}

	$('#flow_companion_auto_open_enabled, #flow_companion_auto_open_delay_ms, #flow_companion_auto_open_once_per_session, #flow_companion_launch_on_exit_intent, #flow_companion_launch_on_scroll_threshold, #flow_companion_launch_on_scroll_percent, #flow_companion_trigger_cooldown_ms, #flow_companion_remember_open_state, #flow_companion_state_storage')
		.on('change input', updateCompanionValidationHints);

	function updateCompanionTargetMode() {
		var mode = $('input[name="flow_companion_target_mode"]:checked').val() || 'sitewide';
		var advancedEnabled = $('#flow_companion_targeting_customize').is(':checked');
		var disableIncludes = (mode === 'sitewide') || !advancedEnabled;
		var disableExcludes = !advancedEnabled;

		$('#flosc-companion-target-groups').toggleClass('flosc-companion-targets-disabled', !advancedEnabled);
		$('#flosc-companion-targets-disabled-note').toggle(!advancedEnabled);
		$('#flosc-companion-target-include-custom-row').toggle(advancedEnabled && mode !== 'sitewide');
		$('#flosc-companion-target-exclude-custom-row').toggle(advancedEnabled);

		$('#flow_companion_include_pages, #flow_companion_include_posts, #flow_companion_include_categories, #flow_companion_include_tags, #flow_companion_target_include_custom')
			.prop('disabled', disableIncludes);
		$('#flow_companion_exclude_pages, #flow_companion_exclude_posts, #flow_companion_exclude_categories, #flow_companion_exclude_tags, #flow_companion_target_exclude_custom')
			.prop('disabled', disableExcludes);
	}

	function updateCompanionRoutingMode() {
		var mode = String($('#flow_companion_routing_mode').val() || 'hub');
		$('.flosc-hub-routing-row').toggle(mode === 'hub');
	}

	$('input[name="flow_companion_target_mode"]').on('change', updateCompanionTargetMode);
	$('#flow_companion_targeting_customize').on('change', updateCompanionTargetMode);
	$('#flow_companion_routing_mode').on('change', updateCompanionRoutingMode);

	$('.flosc-settings-form').on('submit', function() {
		var mode = $('input[name="flow_companion_target_mode"]:checked').val() || 'sitewide';
		var advancedEnabled = $('#flow_companion_targeting_customize').is(':checked');

		if (!advancedEnabled) {
			$('#flow_companion_include_pages option, #flow_companion_include_posts option, #flow_companion_include_categories option, #flow_companion_include_tags option').prop('selected', false);
			$('#flow_companion_exclude_pages option, #flow_companion_exclude_posts option, #flow_companion_exclude_categories option, #flow_companion_exclude_tags option').prop('selected', false);
			$('#flow_companion_target_include_custom').val('');
			$('#flow_companion_target_exclude_custom').val('');
			return;
		}

		if (mode !== 'sitewide') {
			return;
		}

		$('#flow_companion_include_pages option, #flow_companion_include_posts option, #flow_companion_include_categories option, #flow_companion_include_tags option')
			.prop('selected', false);
		$('#flow_companion_target_include_custom').val('');
	});

	$('#flow_companion_accent_color').trigger('input');
	$modeRadios.prop('disabled', false);
	updateCompanionValidationHints();
	updateCompanionTargetMode();
	updateCompanionRoutingMode();
});
