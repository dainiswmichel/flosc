/**
 * FLOSC Settings, AI tab: provider and model controls, personality attach,
 * connection tests, the request-parameter menu, and the accuracy test runner.
 *
 * Enqueued by admin/ai-configuration.php. Every server value arrives through
 * wp_localize_script() as floscAdminAi: nonces, the working flow file, the
 * provider profile tables (nested, so their JSON types are kept) and the
 * translated strings under floscAdminAi.i18n.
 *
 * @package FLOSC
 */
/* global floscAdminAi, ajaxurl */
jQuery(document).ready(function($) {
	// --- Provider section show/hide ---
	// Which provider refuses which setting is data, declared once in
	// includes/ai/flosc-provider-profiles.php. The tab reads it rather than
	// naming a provider here, so filling in a row there is all it takes.
	// Sampling controls a provider refuses on the same request. Anthropic takes
	// temperature alone and top_p alone, and answers 400 to both together —
	// which is how visitor chat came to fail while the panel showed a request
	// that looked fine. The runtime holds one back; this is so the page says so
	// rather than displaying a request it knows will not be sent as written.
	var floscSamplingExclusive = floscAdminAi.samplingExclusive;

	var floscProviderRejects = floscAdminAi.providerRejects;

	// Model first, provider second — the same order the PHP resolver uses.
	// Sonnet 4.5 takes temperature and Sonnet 5 refuses it, and both are
	// Anthropic, so hiding the control by provider hid a control that works.
	// floscModelNoteFor already does the longest-prefix model match; this only
	// asks it the question.
	function floscModelRejectsParam(provider, model, param) {
		var note = (typeof floscModelNoteFor === 'function') ? floscModelNoteFor(provider, model) : null;

		if (note) {
			if ((note.accepts || []).indexOf(param) !== -1) { return false; }
			if ((note.refuses || []).indexOf(param) !== -1) { return true; }
		}

		// Same reason as above: this runs during setup, before the map exists.
		var wide = (floscProviderRejects && floscProviderRejects[provider]) || { params: [] };

		return (wide.params || []).indexOf(param) !== -1;
	}

	function updateProviderSections() {
		var selected = $('#flow_ai_provider').val();
		var rejects = floscProviderRejects[selected] || { params: [], note: '' };
		var selectedModelField = (typeof floscModelFieldFor === 'object' && floscModelFieldFor)
			? floscModelFieldFor[selected]
			: '';
		var selectedModel = selectedModelField ? String($('#' + selectedModelField).val() || '').trim() : '';
		var noTemperature = floscModelRejectsParam(selected, selectedModel, 'temperature');

		// A control the provider will refuse is not a control. Hide it rather
		// than leave it on screen with a paragraph explaining it does nothing.
		$('#flosc-temperature-row').prop('hidden', noTemperature);
		$('#flosc-temperature-absent').prop('hidden', !noTemperature);
		$('#flosc-temperature-absent-note').text(rejects.note || '');
		// Showing only the selected provider hides the other key fields, which
		// makes storing a second key look impossible. It never was — the keys
		// are kept separately — so this reveals them on request.
		var showAll = $('#flosc-show-all-providers').is(':checked');

		$('.flosc-ai-provider-section').each(function() {
			var provider = $(this).data('provider');
			var keep = (provider === selected) || (showAll && provider !== 'ivr');

			if (keep) {
				$(this).removeClass('is-hidden');
			} else {
				$(this).addClass('is-hidden');
			}
		});
	}
	$('#flow_ai_provider').on('change', updateProviderSections);
	$('#flosc-show-all-providers').on('change', updateProviderSections);
	updateProviderSections();

	var attachSel = $('#flow_personality_library_id');
	var attachNote = $('#flosc-personality-attach-note');
	var attachSaved = attachSel.val();
	var floscAttach = {
		nonce: String(floscAdminAi.attachNonce),
		ivr: String(floscAdminAi.ivr),
		fallback: String(floscAdminAi.i18n.attachFallback)
	};
	/*
	 * The confirmation has to outlive the reload that this control triggers.
	 *
	 * It used to set the note to "Attached X. Reloading…" and call
	 * location.reload() on the next statement. The browser never painted it, so
	 * what a floscAdmin saw was "Attaching…" and then a page reload — an
	 * indefinite state with no outcome, on the one control where being sure
	 * matters most. The reload itself is needed: the designer below is rendered
	 * from the attached row.
	 *
	 * So the confirmation is stashed before reloading and shown afterwards,
	 * beside a dropdown that now reads the same name. Choose DadJokesDan, reload,
	 * see DadJokesDan attached and a line saying when it was written.
	 */
	var floscAttachStash = 'flosc_attach_confirmation';

	function floscShowStashedAttachConfirmation() {
		if (!attachNote.length) {
			return;
		}
		var raw = null;
		try {
			raw = window.sessionStorage.getItem(floscAttachStash);
			window.sessionStorage.removeItem(floscAttachStash);
		} catch (e) {
			raw = null;
		}
		if (!raw) {
			return;
		}
		try {
			var stash = JSON.parse(raw);
			if (stash && stash.text) {
				attachNote.removeClass('flosc-hidden').addClass('flosc-attach-ok').text(stash.text);
			}
		} catch (e) {
			// A confirmation we cannot read is not worth a broken page.
		}
	}
	floscShowStashedAttachConfirmation();

	attachSel.on('change', function () {
		var nextVal = attachSel.val();
		var nextLabel = attachSel.find('option:selected').text().trim();
		if (!attachNote.length || nextVal === attachSaved) {
			return;
		}
		attachSel.prop('disabled', true);
		attachNote.removeClass('flosc-hidden flosc-attach-ok flosc-attach-bad').text(String(floscAdminAi.i18n.attaching));
		$.ajax({
			url: ajaxurl,
			method: 'POST',
			timeout: 20000,
			dataType: 'json',
			data: {
				action: 'flosc_attach_personality',
				nonce: floscAttach.nonce,
				ivr: floscAttach.ivr,
				persona: nextVal
			}
		}).done(function (res) {
			if (res && res.success && res.data) {
				attachSaved = res.data.persona;
				var confirmed = res.data.persona
					? String(floscAdminAi.i18n.attached) + ' ' + (res.data.label || nextLabel || res.data.persona)
					: String(floscAdminAi.i18n.attachmentCleared);
				if (res.data.saved_at) {
					confirmed += ' · ' + String(floscAdminAi.i18n.saved) + ' ' + res.data.saved_at;
				}
				try {
					window.sessionStorage.setItem(floscAttachStash, JSON.stringify({ text: confirmed }));
				} catch (e) {
					// Without storage the reload eats the confirmation, which is
					// where this started — so say it here and pause long enough
					// to be read before reloading.
					attachNote.addClass('flosc-attach-ok').text(confirmed);
					window.setTimeout(function () { window.location.reload(); }, 1200);
					return;
				}
				window.location.reload();
			} else {
				attachSel.prop('disabled', false);
				var failed = (res && res.data && res.data.message) ? res.data.message : floscAttach.fallback;
				attachNote.addClass('flosc-attach-bad').text(failed);
			}
		}).fail(function (xhr, textStatus) {
			// The row is stored before the file mirror. A lost or late body
			// still leaves that row in place, so reload onto it instead of
			// sitting on "Attaching…". A refused attach (4xx/5xx) stays here
			// and says why.
			if (textStatus === 'timeout' || (xhr && xhr.status === 200)) {
				window.location.reload();
				return;
			}
			attachSel.prop('disabled', false);
			var msg = floscAttach.fallback;
			if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
				msg = xhr.responseJSON.data.message;
			}
			attachNote.addClass('flosc-attach-bad').text(msg);
		});
	});

	// --- Chaining toggle ---
	$('#flow_ai_enable_chaining').on('change', function() {
		$('#flosc-chain-config').toggle(this.checked);
	});

	// --- The provider's model list, as something to click ---
	// Two lists matter and only one of them works here: what the API key can
	// see at the provider, and what the installed WordPress AI Provider plugin
	// carries. A model can be live at the provider and absent from the plugin's
	// catalog — which is the failure that reads as a bad key and is not one.
	//
	// A list nobody can click is not a choice. The ids go into the datalist for
	// typing, and into a visible row of buttons that fill the field on click.
	var floscModelFieldFor = {
		anthropic: 'flow_ai_anthropic_model',
		openai:    'flow_ai_openai_model',
		xai:       'flow_ai_xai_model',
		gemini:    'flow_ai_gemini_model'
	};

	function floscRenderModelPicker($picker, targetId, models) {
		var $field = $('#' + targetId);

		$picker.empty();

		if (!$field.length || !models || !models.length) {
			$picker.attr('hidden', true);
			return 0;
		}

		var current = String($field.val() || '');
		var heading = models.length + (models.length === 1 ? ' model' : ' models') + ' this key can use';

		$('<p>').addClass('flosc-model-picker__intro').text(heading + '. Click one to use it:').appendTo($picker);

		var $list = $('<div>').addClass('flosc-model-picker__list').appendTo($picker);

		models.forEach(function (m) {
			var $b = $('<button>')
				.attr('type', 'button')
				.addClass('button flosc-model-choice')
				.attr('data-id', m.id)
				.attr('data-target', targetId)
				.text(m.id);

			if (m.label && m.label !== m.id) {
				$('<span>').addClass('flosc-model-choice__label').text(m.label).appendTo($b);
			}

			if (m.id === current) {
				$b.addClass('flosc-model-choice--current');
			}

			$b.appendTo($list);
		});

		$('<p>')
			.addClass('flosc-model-picker__note')
			.text('Clicking one saves it for this flow straight away.')
			.appendTo($picker);

		$picker.removeAttr('hidden');

		// Typing still works, and the suggestions now match reality.
		var listId = $field.attr('list');

		if (listId) {
			var $dl = $('#' + listId).empty();

			models.forEach(function (m) {
				$('<option>').attr('value', m.id).text(m.label || m.id).appendTo($dl);
			});
		}

		return models.length;
	}

	// Choosing from the fetched list has to be the end of the job, so the pick
	// is saved on the spot. A choice that only fills a field, and is lost
	// unless a page-wide Save is found afterwards, is not a choice.
	$(document).on('click', '.flosc-model-choice', function () {
		var $b = $(this);
		var targetId = $b.data('target');
		var id = String($b.data('id'));
		var $field = $('#' + targetId);
		var provider = String(targetId).replace(/^flow_ai_/, '').replace(/_model$/, '');
		var $status = $('.flosc-model-fetch-status[data-for="' + targetId + '"]');

		if (!$field.length) { return; }

		$field.val(id).trigger('change').trigger('input');
		$('.flosc-model-choice[data-target="' + targetId + '"]').removeClass('flosc-model-choice--current');
		$b.addClass('flosc-model-choice--current');
		$status.removeClass('flosc-model-fetch-status--bad flosc-save-key-status--ok').text('Saving ' + id + '…');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'flosc_save_ai_provider_model',
				nonce: String(floscAdminAi.testAiNonce),
				provider: provider,
				ivr: String(floscAdminAi.ivr),
				model: id
			},
			success: function (response) {
				if (!response || !response.success) {
					$status.addClass('flosc-model-fetch-status--bad')
						.text((response && response.data && response.data.message) || 'Could not save the model.');
					return;
				}

				$status.addClass('flosc-save-key-status--ok').text('\u2713 Model saved — ' + id);

				// The test reads saved settings; this model is now one of them.
				if (typeof floscSavedAi === 'object' && floscSavedAi) {
					floscSavedAi[targetId] = id;
				}

				// Describe it now, unasked. What this model can do, and how long
				// a reply it can give, is exactly what an operator needs at the
				// moment they choose it — not after finding another button.
				floscDescribeModel(provider, targetId, id, null, false);
			},
			error: function () {
				$status.addClass('flosc-model-fetch-status--bad').text('Could not reach the server.');
			}
		});
	});

	// Save one key on its own. The full-page Save still works; this exists so
	// "did the key save?" has an answer next to the field, at the moment it is
	// pasted, instead of a banner at the top of a long tab.
	$('.flosc-save-key').on('click', function () {
		var $btn = $(this);
		var provider = $btn.data('provider');
		var targetId = $btn.data('target');
		var $status = $('.flosc-save-key-status[data-for="' + targetId + '"]');
		var key = String($('#' + targetId).val() || '').trim();

		if (!key) {
			$status.removeClass('flosc-save-key-status--ok')
				.addClass('flosc-model-fetch-status--bad').text('Paste a key first.');
			return;
		}

		$btn.prop('disabled', true);
		$status.removeClass('flosc-model-fetch-status--bad flosc-save-key-status--ok').text('Saving…');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'flosc_save_ai_provider_key',
				nonce: String(floscAdminAi.testAiNonce),
				provider: provider,
				ivr: String(floscAdminAi.ivr),
				api_key: key
			},
			success: function (response) {
				if (!response || !response.success) {
					$status.addClass('flosc-model-fetch-status--bad')
						.text((response && response.data && response.data.message) || 'Could not save the key.');
					return;
				}

				var d = response.data || {};
				$status.removeClass('flosc-model-fetch-status--bad')
					.addClass('flosc-save-key-status--ok')
					.text('✓ Saved' + (d.suffix ? ' — ends ' + d.suffix : '') + '.');

				// The test reads saved settings; this key is now one of them, so
				// the unsaved-settings guard must stop calling it unsaved.
				if (typeof floscSavedAi === 'object' && floscSavedAi) {
					floscSavedAi[targetId] = $('#' + targetId).val();
				}

				var $line = $('#' + targetId).closest('td').find('.flosc-key-state');

				if ($line.length) {
					$line.removeClass('flosc-key-state--none').addClass('flosc-key-state--ok')
						.text('Saved on this flow' + (d.suffix ? ' — ends ' + d.suffix : ''));
				}
			},
			error: function () {
				$status.addClass('flosc-model-fetch-status--bad').text('Could not reach the server.');
			},
			complete: function () {
				$btn.prop('disabled', false);
			}
		});
	});

	// Ask the provider to describe the chosen model. Everything rendered comes
	// from the provider; FLOSC ranks nothing and invents nothing. The real
	// max output also raises the Max Tokens ceiling, which used to be a
	// hardcoded 4096 belonging to no model.
	function floscDescribeModel(provider, targetId, model, $btn, announce) {
		var $detail = $('.flosc-model-detail[data-for="' + targetId + '"]');
		var $status = $('.flosc-model-fetch-status[data-for="' + targetId + '"]');

		if (!model) {
			$status.addClass('flosc-model-fetch-status--bad').text('Choose or type a model first.');
			return;
		}

		if ($btn) { $btn.prop('disabled', true); }

		if (announce) {
			$status.removeClass('flosc-model-fetch-status--bad flosc-save-key-status--ok')
				.text('Asking the provider about ' + model + '…');
		}

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'flosc_describe_ai_model',
				nonce: String(floscAdminAi.testAiNonce),
				provider: provider,
				ivr: String(floscAdminAi.ivr),
				model: model,
				api_key: $('#flow_' + provider + '_api_key').val() || ''
			},
			success: function (response) {
				if (!response || !response.success) {
					$detail.attr('hidden', true).empty();
					$status.addClass('flosc-model-fetch-status--bad')
						.text((response && response.data && response.data.message) || 'Could not describe the model.');
					return;
				}

				var d = response.data || {};
				var n = function (v) { return Number(v || 0).toLocaleString(); };

				$detail.empty();
				$('<p>').addClass('flosc-model-detail__name')
					.text((d.display_name || d.id) + ' — as described by the provider').appendTo($detail);

				var $ul = $('<ul>').addClass('flosc-model-detail__list').appendTo($detail);
				$('<li>').text('Context window: ' + n(d.max_input_tokens) + ' tokens').appendTo($ul);
				$('<li>').text('Longest reply it can produce: ' + n(d.max_tokens) + ' tokens').appendTo($ul);

				if (d.features && d.features.length) {
					$('<li>').text('Can also: ' + d.features.join(', ')).appendTo($ul);
				}

				if (d.effort_levels && d.effort_levels.length) {
					$('<li>').text('Effort levels: ' + d.effort_levels.join(', ')).appendTo($ul);
				}

				// Let Max Tokens go as high as this model really allows.
				var $mt = $('#flow_ai_max_tokens');

				if ($mt.length && d.max_tokens > 0) {
					$mt.attr('max', d.max_tokens);

					$('<p>').addClass('flosc-model-detail__note')
						.text('Temperature is not listed here because Anthropic does not publish sampling support, and FLOSC does not send it to Claude at all.')
						.appendTo($detail);

					// Say it again where the number is actually typed.
					var current = parseInt($mt.val(), 10) || 0;
					var $mtNote = $('#flosc-max-tokens-live');

					if (!$mtNote.length) {
						$mtNote = $('<p>').attr('id', 'flosc-max-tokens-live').addClass('flosc-key-state');
						$mt.closest('td').append($mtNote);
					}

					if (current > d.max_tokens) {
						$mtNote.removeClass('flosc-key-state--ok').addClass('flosc-key-state--none')
							.text((d.display_name || d.id) + ' accepts at most ' + n(d.max_tokens) +
									' — ' + n(current) + ' is above that. Lower it, or the provider will reject the request.');
					} else {
						$mtNote.removeClass('flosc-key-state--none').addClass('flosc-key-state--ok')
							.text((d.display_name || d.id) + ' accepts up to ' + n(d.max_tokens) +
									' tokens per reply. Yours is ' + n(current) + '.');
					}
				}

				$detail.removeAttr('hidden');

				if (announce) {
					$status.addClass('flosc-save-key-status--ok').text('\u2713 Described by the provider.');
				}
			},
			error: function () {
				$status.addClass('flosc-model-fetch-status--bad').text('Could not reach the server.');
			},
			complete: function () { if ($btn) { $btn.prop('disabled', false); } }
		});
	}

	// Check what was typed before it is saved. The provider decides whether a
	// parameter is real; this only catches what could never reach a provider —
	// a line that is not name: value, or JSON that does not parse.
	// What each parameter does, so the answer is in the field rather than in a
	// provider's documentation in another tab. Never a gate: a name absent from
	// here is still sent, and says so.
	var floscParamRef = floscAdminAi.paramRef;

	// The same reference, split by provider, plus the whole-set recipes and the
	// measured per-model notes. All four providers are handed over at once so
	// that changing provider or model re-renders the menu from data already in
	// the page rather than from another round trip.
	var floscParamsByProvider = floscAdminAi.paramsByProvider;

	var floscParamRecipes = floscAdminAi.paramRecipes;

	var floscParamModelNotes = floscAdminAi.paramModelNotes;

	// Where each provider explains one parameter, in its own words. A template
	// taking the parameter name where the anchor scheme has been read off the
	// live page, '' where it has not — and the caller falls back to the
	// provider's page rather than inventing an anchor.
	var floscProviderParamDocs = floscAdminAi.providerParamDocs;

	var floscProviderDocs = floscAdminAi.providerDocs;

	var floscProviderLabels = {
		anthropic: 'Anthropic',
		openai:    'OpenAI',
		xai:       'xAI',
		gemini:    'Gemini'
	};

	// Locked, because it is a statement of what will be sent. Unlocking it is a
	// deliberate act, and from that point the operator's text is the truth.
	var floscParamsUnlocked = false;

	// Two states, and a way back from each. Editing is not a door that shuts
	// behind you: a save returns the request to rest, locked and complete,
	// with Edit alive again for whoever wants another go at it.
	function floscUnlockParams(focus) {
		if (floscParamsUnlocked) {
			if (focus) { $('#flow_ai_model_params').trigger('focus'); }
			return;
		}

		floscParamsUnlocked = true;
		$('#flow_ai_model_params').prop('readonly', false).removeClass('flosc-params-locked');
		if (focus) { $('#flow_ai_model_params').trigger('focus'); }
		$('#flosc-params-edit').prop('disabled', true).text('Editing…');
		$('#flosc-params-lock-note').text(
			'Editing. What you write is what FLOSC sends — Temperature and Max Tokens above follow it as ' +
			'you type. It saves itself when you click away.'
		);
	}

	function floscLockParams() {
		floscParamsUnlocked = false;
		$('#flow_ai_model_params').prop('readonly', true).addClass('flosc-params-locked');
		$('#flosc-params-edit').prop('disabled', false).text('Edit the request');
		$('#flosc-params-lock-note').text(
			'This is what will be sent. Built from the fields above — click Edit the request to add ' +
			'anything they cannot express.'
		);
	}

	$('#flosc-params-edit').on('click', function () { floscUnlockParams(true); });

	// Compose a first request when there is none. Never replace one that
	// exists: the text in that box is the operator's, and rebuilding it from
	// the fields is what silently dropped a temperature line on a provider
	// known to refuse temperature. Field edits reach the text through
	// floscSetParamValue, which changes one line and leaves the rest alone.
	function floscRebuildPreview() {
		var $box = $('#flow_ai_model_params');

		if (!$box.length || String($box.val() || '').trim() !== '') { return; }

		var provider = $('#flow_ai_provider').val();
		var rejects = (floscProviderRejects[provider] || { params: [] }).params;
		var lines = [];
		var t = String($('#flow_ai_temperature').val() || '').trim();
		var m = String($('#flow_ai_max_tokens').val() || '').trim();

		if (t && rejects.indexOf('temperature') === -1) { lines.push('temperature: ' + t); }
		if (m) { lines.push('max_tokens: ' + m); }

		// Anything already stored that no field represents.
		String($box.data('extra') || '').split(/\r\n|\r|\n/).forEach(function (line) {
			if (line.trim()) { lines.push(line.trim()); }
		});

		$box.val(lines.join('\n'));
		floscCheckParams();
	}

	// Guards the two-way sync between a field and the line that carries it, so
	// writing one does not bounce back and rewrite the other.
	var floscSyncing = false;

	// Set one parameter in the request and leave every other line exactly as
	// the operator wrote it — same name, same place, same ordering. An empty
	// field removes its line rather than sending an empty value.
	function floscSetParamValue(name, value) {
		var $box = $('#flow_ai_model_params');

		if (!$box.length || floscSyncing) { return; }

		floscSyncing = true;

		var current = String($box.val() || '').trim();
		value = String(value).trim();

		if (current.charAt(0) === '{') {
			var obj = null;

			try { obj = JSON.parse(current); } catch (e) { obj = null; }

			if (obj) {
				if (value === '') {
					delete obj[name];
				} else {
					var typed;

					try { typed = JSON.parse(value); } catch (e2) { typed = value; }

					obj[name] = typed;
				}

				$box.val(JSON.stringify(obj, null, 2));
				floscSyncing = false;
				floscCheckParams();
				return;
			}
		}

		var lines = current ? current.split(/\r\n|\r|\n/) : [];
		var found = false;

		lines = lines.filter(function (line) {
			var bit = floscSplitParamLine(line);

			if (!bit || bit.name !== name) { return true; }

			found = true;

			return value !== '';
		}).map(function (line) {
			var bit = floscSplitParamLine(line);

			return (bit && bit.name === name) ? name + ': ' + value : line;
		});

		if (!found && value !== '') {
			lines.push(name + ': ' + value);
		}

		$box.val(lines.filter(function (l) { return l.trim(); }).join('\n'));
		floscSyncing = false;
		floscCheckParams();
	}

	$('#flow_ai_temperature').on('input change', function () {
		var provider = floscCurrentProvider();

		// A provider that refuses this one never carries it, typed or not.
		var field = (typeof floscModelFieldFor === 'object' && floscModelFieldFor)
			? floscModelFieldFor[provider]
			: '';
		var model = field ? String($('#' + field).val() || '').trim() : '';

		if (floscModelRejectsParam(provider, model, 'temperature')) {
			return;
		}

		floscSetParamValue('temperature', $(this).val());
	});

	$('#flow_ai_max_tokens').on('input change', function () {
		floscSetParamValue('max_tokens', $(this).val());
	});

	$('#flow_ai_provider').on('change', floscRebuildPreview);

	// ---- Saving Step 2b where it is typed -----------------------------------
	//
	// The page-wide Save at the foot of Settings still writes these. This is
	// the same write, reachable from the controls themselves, and it runs on
	// its own when a value is committed — a green flash on the field that
	// changed, so the answer to "did that take?" is visible without scrolling
	// to find out.

	var floscTuningTimer = null;
	var floscTuningInFlight = false;

	// One slot for the save that has to happen next. A save already running is
	// not a reason to throw the next one away — it is a reason to make it wait.
	// Manual outranks automatic: a deliberate press is stronger intent than a
	// background write, so it takes the slot even from an autosave already in it.
	var floscTuningPending = null;

	// What the last completed save stored, so a no-op can still report a real
	// state instead of leaving the button mid-sentence.
	var floscTuningLastSavedAt = '';
	var floscTuningLastSaveSource = 'manual';

	// Raised on pointerdown, which happens before the textarea loses focus.
	// Asking afterwards whether Save has focus cannot work: pressing Save
	// disables Save, and disabling a focused element takes its focus away — so
	// the check answered "they didn't click Save" about the click that was
	// running it. Intent is captured, never inferred after the fact.
	var floscManualSaveArmed = false;

	function floscQueueTuningSave($flash, source) {
		source = (source === 'manual') ? 'manual' : 'auto';

		if (!floscTuningPending || source === 'manual') {
			floscTuningPending = { source: source, flash: $flash };
		}
	}

	// The save button is the state, rather than a button beside a state: at
	// rest it is a green statement that the tuning is stored, and it turns
	// back into something to press the moment that stops being true.
	function floscTuningState(state, detail, source) {
		var $btn = $('#flosc-save-tuning');
		var $status = $('#flosc-tuning-status');
		var auto = (source === 'auto');

		$btn.removeClass('button-primary flosc-tuning-save--saved flosc-tuning-save--bad')
			.prop('disabled', false);
		$status.attr('class', 'flosc-tuning-status').text('');

		if (state === 'saved') {
			// How the save happened, not whether a timestamp came back. Every
			// successful save returns one, so reading the stamp as the source
			// labelled a deliberate press of Save as something the page did on
			// its own — which is exactly the confidence this is meant to give.
			$btn.addClass('flosc-tuning-save--saved')
				.html(auto ? '&#10003; Autosaved' : '&#10003; Saved')
				.attr('title', auto
					? 'Saved on its own. Press to save again.'
					: 'Saved on this flow. Press to save again.');

			// The stamp sits beside the button rather than inside it: it is a
			// full Michel Time Stamp, and a button that changes width every
			// time it is pressed moves the page under whoever pressed it.
			if (detail) {
				$status.addClass('flosc-tuning-status--ok').text(detail);
			}

			return;
		}

		if (state === 'saving') {
			$btn.prop('disabled', true).text(auto ? 'Autosaving…' : 'Saving…');
			return;
		}

		if (state === 'bad') {
			$btn.addClass('flosc-tuning-save--bad').text('Save Model Tuning')
				.attr('title', 'Not saved.');
			$status.addClass('flosc-tuning-status--bad').text('\u2717 ' + (detail || 'Not saved.'));
			return;
		}

		// Dirty: something changed and has not reached the database yet.
		$btn.addClass('button-primary').text('Save Model Tuning')
			.attr('title', 'Save this flow\u2019s tuning now.');
		$status.text(detail || 'Unsaved changes.');
	}

	// The page was rendered from what is stored, so at load that is exactly
	// what the controls are showing. Nothing was autosaved to get here.
	floscTuningState('saved', '', 'manual');

	// What was last written, so an autosave that would rewrite the identical
	// values does not make a second round trip. A deliberate press always goes.
	var floscTuningLastSaved = '';

	function floscTuningFingerprintOf(provider, temperature, maxTokens, params) {
		return JSON.stringify({
			provider: String(provider || ''),
			temperature: String(temperature || ''),
			max_tokens: String(maxTokens || ''),
			params: String(params || '')
		});
	}

	function floscTuningFingerprint() {
		return floscTuningFingerprintOf(
			floscCurrentProvider(),
			$('#flow_ai_temperature').val(),
			$('#flow_ai_max_tokens').val(),
			$('#flow_ai_model_params').val()
		);
	}

	// One place that schedules an unattended save, so cancelling one is one call.
	function floscScheduleTuningAutosave($field, delay) {
		window.clearTimeout(floscTuningTimer);
		floscTuningTimer = window.setTimeout(function () {
			floscSaveTuning($field, 'auto');
		}, (typeof delay === 'number') ? delay : 1400);
	}

	floscTuningLastSaved = floscTuningFingerprint();

	function floscFlashSaved($el) {
		if (!$el || !$el.length) { return; }

		$el.removeClass('flosc-saved-flash');

		// Reading offsetWidth restarts the CSS transition on an element that
		// was flashed a moment ago; without it a second save shows nothing.
		$el[0].offsetWidth;
		$el.addClass('flosc-saved-flash');

		window.setTimeout(function () { $el.removeClass('flosc-saved-flash'); }, 1600);
	}

	function floscSaveTuning($flash, source) {
		source = (source === 'manual') ? 'manual' : 'auto';

		var provider = floscCurrentProvider();
		var $status = $('#flosc-tuning-status');

		if (!provider || provider === 'ivr') {
			$status.attr('class', 'flosc-tuning-status flosc-tuning-status--bad')
				.text('Pick an AI provider for this flow first.');
			return;
		}

		if (floscTuningInFlight) {
			floscQueueTuningSave($flash, source);
			$('#flosc-tuning-status').attr('class', 'flosc-tuning-status')
				.text(source === 'manual' ? 'Save queued…' : 'Latest changes waiting to save…');
			return;
		}

		// A pause in typing and the blur that follows it are one edit, not two.
		// Writing the same values twice is a wasted round trip and makes the
		// indicator flicker for no reason anyone can see.
		var fingerprint = floscTuningFingerprint();

		// Already stored. Nothing to write — but the button may be sitting in
		// Saving from the call that got us here, so say so rather than
		// returning into silence. No path out of a save may leave it there.
		//
		// The label describes the action that just finished, not the write that
		// happened to be last. Reporting the stored source instead attributed a
		// recipe click — an automatic path — to whatever had saved before it,
		// so clicking Use this could read Saved when nobody had pressed
		// anything. An autosave that finds the work already stored has still
		// done an autosave's job, and says so.
		if (fingerprint === floscTuningLastSaved) {
			floscTuningLastSaveSource = source;

			floscTuningState('saved', floscTuningLastSavedAt, source);
			return;
		}

		floscTuningInFlight = true;
		floscTuningState('saving', '', source);

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'flosc_save_model_tuning',
				nonce: String(floscAdminAi.testAiNonce),
				ivr: String(floscAdminAi.ivr),
				provider: provider,
				temperature: String($('#flow_ai_temperature').val() || ''),
				max_tokens: String($('#flow_ai_max_tokens').val() || ''),
				params: String($('#flow_ai_model_params').val() || '')
			},
			success: function (response) {
				if (!response || !response.success) {
					// Almost always a request that will not parse, and the
					// message names the line that broke it. Nothing was written,
					// so nothing goes green and the request stays open for
					// editing — re-locking it here would hide the mistake.
					floscTuningState('bad', (response && response.data && response.data.message) || 'Not saved.', source);
					return;
				}

				var d = response.data;

				// Stamped by the server, which is the machine that did the
				// writing. A browser clock can be wrong by hours, and "saved
				// at" is a claim only the writer can make.
				// What this request stored, taken from the answer rather than
				// from what the browser assumed before sending it.
				floscTuningLastSaved = floscTuningFingerprintOf(
					provider, d.temperature, d.max_tokens, d.params
				);

				// This answer describes the form as it was when the request
				// left. If newer work is already waiting, that answer is now
				// older than the screen, and painting it back would undo an
				// edit the operator can see. Leave the screen alone and let the
				// queued save — which carries the newer values — have the last
				// word, including the last word on the label.
				floscTuningLastSavedAt = d.saved_at || '';
				floscTuningLastSaveSource = source;

				if (floscTuningPending) {
					// This answer is older than the screen, so it must not be
					// painted back over newer work — but it must still hand the
					// button back. The queued save has the last word on the
					// label; until it lands, the page says what is true.
					$('#flosc-tuning-status').attr('class', 'flosc-tuning-status')
						.text('Latest changes waiting to save…');
					$('#flosc-save-tuning').prop('disabled', false)
						.removeClass('flosc-tuning-save--saved flosc-tuning-save--bad')
						.addClass('button-primary')
						.text('Save Model Tuning');
					return;
				}

				floscTuningState('saved', d.saved_at || '', source);

				// What came back is what is stored, after the same fold the
				// page-wide save performs. Showing it rather than what was
				// typed is the whole point: the page now displays the request
				// that will actually be sent.
				floscSyncing = true;

				if (!$('#flow_ai_temperature').is(':focus')) {
					$('#flow_ai_temperature').val(d.temperature);
				}

				if (!$('#flow_ai_max_tokens').is(':focus')) {
					$('#flow_ai_max_tokens').val(d.max_tokens);
				}

				if (!$('#flow_ai_model_params').is(':focus') && d.preview !== undefined) {
					$('#flow_ai_model_params').val(d.preview).data('extra', d.params);
				}

				floscSyncing = false;
				floscCheckParams();

				floscFlashSaved($flash && $flash.length ? $flash : $('#flow_ai_model_params'));

				// Stored and complete, so the request goes back to being a
				// statement of what will be sent — unless the operator is still
				// inside it, in which case taking the box away mid-sentence
				// would be the rudest thing this page could do.
				if (floscParamsUnlocked && !$('#flow_ai_model_params').is(':focus')) {
					floscLockParams();
				}
			},
			error: function () {
				// Through the state machine, not around it: the button was
				// disabled to say Saving, and something has to give it back.
				floscTuningState('bad', 'The save request did not complete.', source);
			},
			complete: function () {
				floscTuningInFlight = false;

				// Whatever was waiting goes now, on the next turn of the event
				// loop so this handler is off the stack first.
				if (floscTuningPending) {
					var pending = floscTuningPending;
					floscTuningPending = null;

					window.setTimeout(function () {
						floscSaveTuning(pending.flash, pending.source);
					}, 0);
				}
			}
		});
	}

	$('#flosc-save-tuning').on('pointerdown mousedown touchstart', function () {
		floscManualSaveArmed = true;
		window.clearTimeout(floscTuningTimer);
	});

	$('#flosc-save-tuning').on('click', function (event) {
		event.preventDefault();
		window.clearTimeout(floscTuningTimer);
		floscSaveTuning($('#flow_ai_model_params'), 'manual');

		// Held through the focus transition that produced this click, then
		// released so the next blur is an ordinary blur again.
		window.setTimeout(function () { floscManualSaveArmed = false; }, 0);
	});

	// Any edit makes the form dirty, including one made while a save is
	// running. An in-flight request is an older snapshot of the form, not a
	// reason to stop noticing that the form has moved on since — so newer work
	// is marked, and queued behind the request that is already going.
	$('#flow_ai_temperature, #flow_ai_max_tokens, #flow_ai_model_params').on('input', function () {
		var $field = $(this);

		if (floscSyncing) { return; }

		// Typing by hand cancels an autosave a chip or recipe had scheduled.
		// Half a handwritten line is not something to send anywhere.
		if ($field.is('#flow_ai_model_params')) {
			window.clearTimeout(floscTuningTimer);
		}

		floscTuningState('dirty');

		if (floscTuningInFlight) {
			floscQueueTuningSave($field, 'auto');
		}
	});

	// The numeric fields save after a pause in typing. The request box does
	// not: half-written YAML would be refused on every keystroke and paint the
	// status red under somebody still writing it.
	$('#flow_ai_temperature, #flow_ai_max_tokens').on('input', function () {
		if (floscSyncing) { return; }

		floscScheduleTuningAutosave($(this), 1400);
	});

	$('#flow_ai_temperature, #flow_ai_max_tokens').on('change', function () {
		window.clearTimeout(floscTuningTimer);
		floscSaveTuning($(this), 'auto');
	});

	// Leaving the request saves it — unless you left it by reaching for Save.
	//
	// This is where the label was going wrong, and it was never the label's
	// fault. Pressing Save blurs the textarea first, so the textarea's own
	// change handler started an autosave, disabled the button, and the click
	// that caused all of it arrived to find a save already running. The press
	// was discarded and the page reported Autosaved for a deliberate save.
	//
	// focusout fires before focus lands, so the answer to "where did they go?"
	// is one turn of the event loop away. Waiting for it lets the click be the
	// save it was meant to be.
	$('#flow_ai_model_params').on('focusout', function (event) {
		var $field = $(this);

		window.clearTimeout(floscTuningTimer);

		// Leaving by way of Save is a manual save, and it is already running.
		// relatedTarget names where focus is going, before it gets there.
		var next = event.relatedTarget || null;

		if (floscManualSaveArmed || (next && next.id === 'flosc-save-tuning')) {
			return;
		}

		floscTuningTimer = window.setTimeout(function () {
			if (floscManualSaveArmed) { return; }

			floscSaveTuning($field, 'auto');
		}, 150);
	});

	// ---- The parameter menu -------------------------------------------------
	//
	// Pullable, visible, explained, usable: every parameter FLOSC has a note on
	// is a button that writes itself into the request, the note is beside it,
	// the whole-set recipes are one click, and for the parameter that arrived
	// after this plugin did there is the provider's own reference and the
	// provider's own model to ask.

	function floscCurrentProvider() {
		return String($('#flow_ai_provider').val() || '');
	}

	function floscCurrentModel() {
		var field = floscModelFieldFor[floscCurrentProvider()];

		return field ? String($('#' + field).val() || '').trim() : '';
	}

	// Same longest-prefix rule the PHP side uses: providers date their model
	// ids, so a note measured on claude-sonnet-4-5 covers -20250929.
	function floscModelNoteFor(provider, model) {
		// These maps are `var`s assigned further down this block, but callers
		// run during setup — updateProviderSections() is invoked long before
		// this line is reached. Hoisting makes the function callable while its
		// data is still undefined, and reading a property off that threw, which
		// aborted setup and left every handler after it unbound. A guard here
		// rather than a rule about ordering: this must answer safely whenever
		// it is asked, including before the page has finished assembling.
		var notes = (floscParamModelNotes && floscParamModelNotes[provider]) || {};
		var best = null, bestLen = -1;

		model = String(model || '');

		Object.keys(notes).forEach(function (prefix) {
			if (model.indexOf(prefix) === 0 && prefix.length > bestLen) {
				best = notes[prefix];
				bestLen = prefix.length;
			}
		});

		return best;
	}

	// One 'name: value' line into either notation the box may be holding.
	function floscSplitParamLine(line) {
		var i = String(line).indexOf(':');

		if (i === -1) { return null; }

		return {
			name: line.slice(0, i).trim(),
			raw:  line.slice(i + 1).trim()
		};
	}

	function floscInsertParamLines(text) {
		var $box = $('#flow_ai_model_params');

		if (!$box.length) { return; }

		floscUnlockParams(false);

		var current = String($box.val() || '').trim();
		var incoming = String(text).split(/\r\n|\r|\n/).filter(function (l) { return l.trim(); });

		if (current.charAt(0) === '{') {
			// Already JSON. Put the parameters inside the object rather than
			// appending lines that would stop it parsing.
			var obj;

			try { obj = JSON.parse(current); } catch (e) { obj = null; }

			if (obj) {
				incoming.forEach(function (line) {
					var bit = floscSplitParamLine(line);

					if (!bit) { return; }

					var value;

					try { value = JSON.parse(bit.raw); } catch (e2) { value = bit.raw; }

					obj[bit.name] = value;
				});

				$box.val(JSON.stringify(obj, null, 2));
				floscCheckParams();
				floscTuningState('dirty');
				// FLOSC wrote this line, so it is known-good syntax and can
				// save itself. A short pause, so clicking three chips is one
				// save rather than three.
				floscScheduleTuningAutosave($box, 900);
				$box.trigger('focus');
				return;
			}
		}

		var lines = current ? current.split(/\r\n|\r|\n/) : [];
		var have = {};

		lines.forEach(function (line) {
			var bit = floscSplitParamLine(line);

			if (bit) { have[bit.name] = true; }
		});

		incoming.forEach(function (line) {
			var bit = floscSplitParamLine(line);

			if (bit && have[bit.name]) {
				// Replace rather than duplicate — two lines naming the same
				// parameter is not a request any provider can read.
				lines = lines.map(function (existing) {
					var e = floscSplitParamLine(existing);

					return (e && e.name === bit.name) ? line : existing;
				});
				return;
			}

			lines.push(line);

			if (bit) { have[bit.name] = true; }
		});

		$box.val(lines.filter(function (l) { return l.trim(); }).join('\n'));
		floscCheckParams();
		floscTuningState('dirty');
		floscScheduleTuningAutosave($box, 900);
		$box.trigger('focus');
	}

	function floscRenderParamMenu() {
		var provider = floscCurrentProvider();
		var model = floscCurrentModel();

		// IVR is the scripted local flow. It sends no request, so there is no
		// request to add parameters to.
		if (!provider || provider === 'ivr') {
			$('#flosc-param-menu').attr('hidden', true);
			return;
		}

		$('#flosc-param-menu').removeAttr('hidden');
		var label = floscProviderLabels[provider] || provider || 'this provider';
		var rows = floscParamsByProvider[provider] || {};
		var names = Object.keys(rows);

		$('#flosc-param-menu-where').text(
			model ? '— ' + label + ' · ' + model : '— ' + label
		);

		// What has actually been watched happen on this model, which is a
		// different and better fact than what the provider takes in general.
		var note = floscModelNoteFor(provider, model);
		var $measured = $('#flosc-param-menu-measured').empty();

		if (note && ((note.accepts && note.accepts.length) || (note.refuses && note.refuses.length))) {
			var parts = [];

			if (note.accepts && note.accepts.length) { parts.push('accepts ' + note.accepts.join(', ')); }
			if (note.refuses && note.refuses.length) { parts.push('refuses ' + note.refuses.join(', ')); }

			$measured.text('Measured on this model: ' + parts.join(' · ') + '.').removeAttr('hidden');
		} else if (model) {
			$measured.text(
				'Nothing has been measured on ' + model + ' here. The list below is what ' + label +
				' is known to take; the model decides, and its answer comes back in Step 3.'
			).removeAttr('hidden');
		} else {
			$measured.attr('hidden', true);
		}

		var refused = {};

		if (note && note.refuses) {
			note.refuses.forEach(function (n) { refused[n] = true; });
		}

		var $chips = $('#flosc-param-menu-chips').empty();

		if (!names.length) {
			$('<p>').addClass('description').text('FLOSC has no notes for this provider yet.').appendTo($chips);
		}

		names.forEach(function (name) {
			var ref = rows[name];
			var $chip = $('<button>')
				.attr('type', 'button')
				.addClass('button flosc-param-chip')
				.attr('data-param', name)
				.attr('data-insert', ref.example || (name + ': '))
				.text(name);

			if (refused[name]) {
				$chip.addClass('flosc-param-chip--refused')
					.attr('title', model + ' refuses this one.');
			}

			$chip.appendTo($chips);
		});

		var $recipes = $('#flosc-param-menu-recipes').empty();
		var recipes = floscParamRecipes[provider] || [];

		if (!recipes.length) {
			$('<p>').addClass('description')
				.text('No setups for ' + label + ' yet — none of its models have been measured here, and a plausible guess is worth less than nothing.')
				.appendTo($recipes);
		}

		recipes.forEach(function (recipe, i) {
			var $r = $('<div>').addClass('flosc-param-recipe').appendTo($recipes);
			var $head = $('<div>').addClass('flosc-param-recipe__head').appendTo($r);

			$('<strong>').text(recipe.name).appendTo($head);
			$('<button>')
				.attr('type', 'button')
				.addClass('button button-small flosc-param-recipe__use')
				.attr('data-recipe', i)
				.text('Use this')
				.appendTo($head);

			$('<p>').addClass('flosc-param-recipe__why').text(recipe.why).appendTo($r);
			$('<pre>').addClass('flosc-param-recipe__params').text(recipe.params).appendTo($r);

			if (recipe.models) {
				$('<p>').addClass('flosc-param-recipe__models').text(recipe.models).appendTo($r);
			}
		});

		var docs = floscProviderDocs[provider] || '';
		var $docs = $('#flosc-param-docs');

		if (docs) {
			$docs.attr('href', docs).text('Open ' + label + '\u2019s own request reference \u2197').removeAttr('hidden');
		} else {
			$docs.attr('hidden', true);
		}

		$('#flosc-param-menu-note').attr('hidden', true).empty();
	}

	$('#flosc-param-menu-chips').on('click', '.flosc-param-chip', function () {
		var name = $(this).data('param');
		var ref = (floscParamsByProvider[floscCurrentProvider()] || {})[name];

		floscInsertParamLines(String($(this).data('insert')));

		// The explanation lands where the click did, not only in the list of
		// what is currently typed further up.
		var $note = $('#flosc-param-menu-note').empty();

		if (ref) {
			$('<code>').text(name).appendTo($note);
			$('<span>').addClass('flosc-param-menu__what').text(' ' + ref.what).appendTo($note);

			var $meta = $('<div>').addClass('flosc-param-menu__meta')
				.text('Range: ' + ref.range + ' · ' + ref.providers +
						(ref.measured ? ' · measured against the live API' : ''))
				.appendTo($note);
			var $link = floscParamDocLink(name);

			if ($link) { $meta.append(document.createTextNode(' ')).append($link); }

			$note.removeAttr('hidden');
		}
	});

	$('#flosc-param-menu-recipes').on('click', '.flosc-param-recipe__use', function () {
		var recipes = floscParamRecipes[floscCurrentProvider()] || [];
		var recipe = recipes[parseInt($(this).data('recipe'), 10)];

		if (recipe) { floscInsertParamLines(recipe.params); }
	});

	// The parameter FLOSC has never heard of: ask the provider's own model,
	// through the key already configured on this flow. Labelled as the model's
	// answer, because a model can be wrong about its own API.
	$('#flosc-param-ask').on('click', function () {
		var $btn = $(this);
		var name = String($('#flosc-param-ask-name').val() || '').trim();
		var $out = $('#flosc-param-answer').removeClass('flosc-param-menu__answer--bad');

		if (!name) {
			$out.text('Type a parameter name first.').addClass('flosc-param-menu__answer--bad').removeAttr('hidden');
			return;
		}

		$btn.prop('disabled', true);
		$out.text('Asking ' + (floscCurrentModel() || 'the model') + '\u2026').removeAttr('hidden');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'flosc_explain_ai_parameter',
				nonce: String(floscAdminAi.testAiNonce),
				ivr: String(floscAdminAi.ivr),
				param: name
			},
			success: function (response) {
				if (!response || !response.success) {
					$out.text((response && response.data && response.data.message) || 'The provider did not answer.')
						.addClass('flosc-param-menu__answer--bad');
					return;
				}

				var d = response.data;

				$out.empty();
				$('<div>').addClass('flosc-param-menu__answersrc')
					.text('Answered by ' + (d.model || d.provider) + ' just now — this is the model\u2019s account of its own API, not FLOSC\u2019s.')
					.appendTo($out);
				$('<p>').text(d.answer).appendTo($out);

				var $row = $('<p>').appendTo($out);

				$('<button>')
					.attr('type', 'button')
					.addClass('button button-small')
					.text('Add ' + d.param + ' to the request')
					.on('click', function () { floscInsertParamLines(d.param + ': '); })
					.appendTo($row);

				// Somewhere to check the model against, which matters most for
				// exactly the parameter nobody here has a note on.
				var $link = floscParamDocLink(d.param);

				if ($link) { $row.append(document.createTextNode(' ')).append($link); }
			},
			error: function () {
				$out.text('The request did not complete.').addClass('flosc-param-menu__answer--bad');
			},
			complete: function () {
				$btn.prop('disabled', false);
			}
		});
	});

	$('#flow_ai_provider, #flow_ai_anthropic_model, #flow_ai_openai_model, #flow_ai_xai_model, #flow_ai_gemini_model')
		.on('input change', floscRenderParamMenu);

	// Changing the model can change which controls apply — Sonnet 4.5 takes
	// temperature where Sonnet 5 refuses it — so the rows are re-decided too.
	$('#flow_ai_anthropic_model, #flow_ai_openai_model, #flow_ai_xai_model, #flow_ai_gemini_model')
		.on('input change', function () {
			if (typeof updateProviderSections === 'function') { updateProviderSections(); }
		});

	floscRenderParamMenu();

	// The field and the line that carries it are two views of one value, so
	// they are kept equal as they are typed rather than argued over at save
	// time. Nothing is struck through and nothing is warned about, because
	// there is never a moment where the two disagree.
	function floscMarkOverrides(names, values) {
		if (floscSyncing) { return; }

		floscSyncing = true;

		['temperature', 'max_tokens'].forEach(function (field) {
			var $input = $('#flow_ai_' + field);

			$('#flosc-overridden-' + field).attr('hidden', true).text('');
			$input.removeClass('flosc-input-overridden');

			// Never rewrite a field while it is being typed into: the value
			// is half-entered, and correcting "0.30" to "0.3" under the cursor
			// is the sort of help nobody asked for.
			if (!$input.length || $input.is(':focus') || names.indexOf(field) === -1) { return; }

			var value = values ? values[field] : undefined;

			if (value === undefined || value === null || typeof value === 'object') { return; }

			if (String($input.val()) !== String(value)) {
				$input.val(String(value));
			}
		});

		floscSyncing = false;
	}

	// FLOSC's note on a parameter is a paraphrase written for an operator, not
	// the authority. Every note carries the way out to the provider's own
	// entry for that exact parameter, in a new tab so nothing typed is lost.
	function floscParamDocLink(name) {
		var provider = floscCurrentProvider();
		var template = floscProviderParamDocs[provider] || '';
		var label = floscProviderLabels[provider] || provider;

		if (template) {
			return $('<a>')
				.addClass('flosc-param-doc')
				.attr('href', template.replace('%s', encodeURIComponent(name)))
				.attr('target', '_blank')
				.attr('rel', 'noopener noreferrer')
				.text(label + ' on ' + name + ' \u2197');
		}

		var page = floscProviderDocs[provider] || '';

		if (page) {
			// No anchor scheme read off that provider's page, so the reader is
			// sent to the page and told that is what this link is.
			return $('<a>')
				.addClass('flosc-param-doc')
				.attr('href', page)
				.attr('target', '_blank')
				.attr('rel', 'noopener noreferrer')
				.text(label + '\u2019s request reference \u2197');
		}

		// Nowhere to send them. Say so in words rather than leave the note
		// trailing off: an operator who finds nothing assumes there is nothing
		// to find, and there is — it is just not somewhere FLOSC can point at.
		return $('<span>')
			.addClass('flosc-param-doc flosc-param-doc--none')
			.text('For more on this parameter, see ' + label + '\u2019s own API reference.');
	}

	// Two sampling controls the provider will not take together. Named in the
	// order they appear, because that is the order the request applies them in:
	// the first one reaches the model and the rest are held back.
	function floscExplainSamplingClash($help, names) {
		var provider = floscCurrentProvider();
		var group = floscSamplingExclusive[provider] || [];

		if (group.length < 2) { return; }

		var present = names.filter(function (n) { return group.indexOf(n) !== -1; });

		if (present.length < 2) { return; }

		var kept = present[0];
		var held = present.slice(1);
		var label = floscProviderLabels[provider] || provider;
		var $row = $('<div>').addClass('flosc-param-help__row flosc-param-clash').prependTo($help);

		$('<strong>').text(label + ' will not take ' + present.join(' and ') + ' on the same request.').appendTo($row);
		$('<div>').addClass('flosc-param-help__meta').text(
			'It answers 400 to both, so FLOSC sends ' + kept + ' and holds back ' +
			held.join(', ') + '. Remove ' + held.join(' and ') +
			' to make this request say what is actually sent.'
		).appendTo($row);
	}

	function floscExplainParams(names) {
		var $help = $('#flosc-param-help').empty();

		if (!names.length) { $help.attr('hidden', true); return; }

		names.forEach(function (name) {
			var ref = floscParamRef[name];
			var $row = $('<div>').addClass('flosc-param-help__row').appendTo($help);
			var $link = floscParamDocLink(name);

			$('<code>').addClass('flosc-param-help__name').text(name).appendTo($row);

			if (!ref) {
				$('<span>').addClass('flosc-param-help__unknown')
					.text('FLOSC has no note on this one. It will be sent as written and ' +
							'the provider decides — its answer appears in Step 3.')
					.appendTo($row);

				if ($link) {
					$('<div>').addClass('flosc-param-help__meta').append($link).appendTo($row);
				}

				return;
			}

			$('<span>').addClass('flosc-param-help__what').text(ref.what).appendTo($row);

			var $meta = $('<div>').addClass('flosc-param-help__meta').appendTo($row);
			$('<span>').text('Range: ' + ref.range).appendTo($meta);
			$('<span>').text(ref.providers).appendTo($meta);

			if (ref.measured) {
				$('<span>').addClass('flosc-param-help__measured')
					.text('measured against the live API').appendTo($meta);
			}

			if ($link) { $link.appendTo($meta); }
		});

		floscExplainSamplingClash($help, names);

		$help.removeAttr('hidden');
	}

	function floscCheckParams() {
		var $box = $('#flow_ai_model_params');
		var $out = $('#flosc-params-status');

		if (!$box.length) { return; }

		var raw = String($box.val() || '').trim();

		$out.removeClass('flosc-key-state--none flosc-key-state--ok');

		if (!raw) { $out.text(''); floscExplainParams([]); floscMarkOverrides([], {}); return; }

		if (raw.charAt(0) === '{') {
			try {
				var obj = JSON.parse(raw);
				var n = Object.keys(obj).length;
				$out.addClass('flosc-key-state--ok').text('\u2713 Valid JSON — ' + n + (n === 1 ? ' parameter' : ' parameters') + ' will be sent.');
				floscExplainParams(Object.keys(obj));
				floscMarkOverrides(Object.keys(obj), obj);
			} catch (e) {
				$out.addClass('flosc-key-state--none').text('\u2717 Not valid JSON: ' + e.message);
				floscExplainParams([]);
				floscMarkOverrides([], {});
			}
			return;
		}

		var lines = raw.split(/\r\n|\r|\n/), names = [], values = {}, bad = null;

		lines.forEach(function (line) {
			line = line.trim();
			if (!line || line.charAt(0) === '#' || line.indexOf('//') === 0) { return; }
			var i = line.indexOf(':');
			if (i < 1) { bad = bad || line; return; }
			var nm = line.slice(0, i).trim();
			names.push(nm);
			values[nm] = line.slice(i + 1).trim().replace(/,$/, '');
		});

		if (bad) {
			$out.addClass('flosc-key-state--none').text('\u2717 This line is not "name: value" — ' + bad);
			floscExplainParams([]);
			floscMarkOverrides([], {});
			return;
		}

		if (!names.length) { $out.text(''); floscExplainParams([]); floscMarkOverrides([], {}); return; }

		$out.addClass('flosc-key-state--ok').text('\u2713 ' + names.length + (names.length === 1 ? ' parameter' : ' parameters') + ' will be sent.');
		floscExplainParams(names);
		floscMarkOverrides(names, values);
	}

	$('#flow_ai_model_params').on('input change', floscCheckParams);
	floscCheckParams();

	$('.flosc-describe-model').on('click', function () {
		var $btn = $(this);
		floscDescribeModel($btn.data('provider'), $btn.data('target'), String($('#' + $btn.data('target')).val() || '').trim(), $btn, true);
	});

	$('.flosc-fetch-models').on('click', function () {
		var $btn = $(this);
		var provider = $btn.data('provider');
		var targetId = $btn.data('target');
		var $status = $('.flosc-model-fetch-status[data-for="' + targetId + '"]');
		var $picker = $('.flosc-model-picker[data-for="' + targetId + '"]');

		$btn.prop('disabled', true);
		$status.removeClass('flosc-model-fetch-status--bad').text('Asking the provider…');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'flosc_fetch_ai_models',
				nonce: String(floscAdminAi.testAiNonce),
				provider: provider,
				ivr: String(floscAdminAi.ivr),
				api_key: $('#flow_' + provider + '_api_key').val() || ''
			},
			success: function (response) {
				if (!response || !response.success) {
					$picker.attr('hidden', true).empty();
					$status.addClass('flosc-model-fetch-status--bad')
						.text((response && response.data && response.data.message) || 'Could not fetch the list.');
					return;
				}

				var all = response.data.models || [];

				if (floscRenderModelPicker($picker, targetId, all) === 0) {
					$status.addClass('flosc-model-fetch-status--bad')
						.text('The provider returned no models for this key.');
					return;
				}

				$status.text('Your key reached the provider. Pick a model below.');
			},
			error: function () {
				$picker.attr('hidden', true).empty();
				$status.addClass('flosc-model-fetch-status--bad').text('Could not reach the server.');
			},
			complete: function () {
				$btn.prop('disabled', false);
			}
		});
	});

	// --- Connection test ---
	// The test runs against saved settings. Anything typed and not yet saved is
	// invisible to it, so testing then would diagnose the old value and blame
	// the provider for it. Remember what was on the page when it loaded, and
	// stop the test if the operator has changed it since.
	var floscSavedAi = {};
	$('.flosc-ai-key-input, #flow_ai_provider, .flosc-ai-model-select').each(function () {
		floscSavedAi[this.id] = $(this).val();
	});

	function floscUnsavedAiFields() {
		var changed = [];
		$('.flosc-ai-key-input, #flow_ai_provider, .flosc-ai-model-select').each(function () {
			if (floscSavedAi[this.id] !== undefined && floscSavedAi[this.id] !== $(this).val()) {
				var label = $('label[for="' + this.id + '"]').text().replace(/\s+/g, ' ').trim();
				changed.push(label || this.id);
			}
		});
		return changed;
	}

	// The test asks the provider for its model list before it tries to generate.
	// That call is the only step that isolates the key from everything built on
	// top of it, so its result is reported whether the test passed or failed —
	// and the ids it returns are rendered as buttons, not prose.
	function floscTestModelReport(d, lines) {
		var $picker = $('#test-model-picker').attr('hidden', true).empty();

		if (!d || !d.models_probed) { return; }

		if (d.models_error) {
			lines.push('');
			lines.push('Asking ' + (d.provider || 'the provider') + ' which models this key can use: ' + d.models_error);
			return;
		}

		var models = d.models || [];
		var targetId = floscModelFieldFor[d.provider];

		lines.push('');
		lines.push('\u2713 The key reached ' + (d.provider || 'the provider') + ', which lists ' +
			models.length + (models.length === 1 ? ' model' : ' models') + ' for it.');

		if (d.model && models.length) {
			var listed = models.some(function (m) { return m.id === d.model; });

			if (!listed) {
				lines.push('\u2717 "' + d.model + '" is not one of them.');
			}
		}

		if (targetId) {
			$picker.attr('data-for', targetId);
			floscRenderModelPicker($picker, targetId, models);
		}
	}

	$('#test-ai-connection').on('click', function() {
		var $btn = $(this);
		var $loading = $('#test-loading');
		var $results = $('#test-results');
		var $flosc_status = $('#test-status');
		var $details = $('#test-details');

		var unsaved = floscUnsavedAiFields();
		if (unsaved.length) {
			$results.show();
			$flosc_status.html('<span class="flosc-pass-status flosc-pass-status--fail">\u2717 Unsaved AI settings</span>');
			$details.text(
				'These were changed but not saved yet:\n  ' + unsaved.join('\n  ') +
				'\n\nThe test runs against saved settings, so it would test the old values and blame the provider for it.' +
				'\n\nSave AI Settings, then Test again.'
			);
			return;
		}

		$btn.prop('disabled', true);
		$loading.show();
		$results.hide();
		$('#test-model-picker').attr('hidden', true).empty();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'flosc_test_ai_connection',
				nonce: String(floscAdminAi.testAiNonce),
				ivr: String(floscAdminAi.ivr)
			},
			success: function(response) {
				$loading.hide();
				$results.show();
				
				if (response.success) {
					var d = response.data || {};
					var lines = [];
					lines.push('Result: LIVE external API call succeeded (not IVR scripted mode)');
					if (d.provider) { lines.push('Provider: ' + d.provider); }
					if (d.model) { lines.push('Model used: ' + d.model); }
					if (d.model_configured && d.model_configured !== d.model) {
						lines.push('Model configured: ' + d.model_configured);
					}
					if (d.endpoint) { lines.push('Endpoint: ' + d.endpoint); }
					if (d.api_key_present) {
						lines.push('API key: present' + (d.api_key_suffix ? (' (…' + d.api_key_suffix + ')') : ''));
					} else {
						lines.push('API key: missing in saved settings');
					}
					if (d.flow_label || d.flow_ivr) {
						lines.push('Flow: ' + (d.flow_label || d.flow_ivr) + (d.flow_ivr && d.flow_label ? (' (' + d.flow_ivr + ')') : ''));
					}
					if (d.response_time != null) { lines.push('Latency: ' + d.response_time + ' ms'); }
					if (d.tokens_total > 0 || d.tokens_in > 0 || d.tokens_out > 0) {
						lines.push(
							'Tokens: in ' + (d.tokens_in || 0) +
							' / out ' + (d.tokens_out || 0) +
							' / total ' + (d.tokens_total || ((d.tokens_in || 0) + (d.tokens_out || 0)))
						);
					} else {
						lines.push('Tokens: (provider did not return usage on this call)');
					}
					if (d.billing_source) { lines.push('Billing source: ' + d.billing_source); }
					if (d.model_configured && d.model && d.model_configured !== d.model) {
						lines.push('');
						lines.push('Note: "' + d.model_configured + '" is not in the installed provider plugin\'s catalog,');
						lines.push('so the provider answered with ' + d.model + ' instead. Pick a model the plugin offers,');
						lines.push('or update the provider plugin, to use the one you configured.');
					}
					if (d.params_configured && d.params_configured.length) {
						lines.push('Extra parameters configured: ' + d.params_configured.join(', '));
					}
					if (d.params_applied && d.params_applied.length) {
						lines.push('\u2713 Carried by this request: ' + d.params_applied.join(', '));
					}
					if (d.params_unapplied && d.params_unapplied.length) {
						lines.push('\u2717 Left out, with the reason each was refused:');
						d.params_unapplied.forEach(function (p) { lines.push('    ' + p); });
					}
					lines.push('Model reply: ' + (d.response || '(empty)'));
					floscTestModelReport(d, lines);
					$flosc_status.html('<span class="flosc-pass-status flosc-pass-status--pass">✓ External API OK — key + model path verified</span>');
					$details.text(lines.join('\n'));
				} else {
					var ed = response.data || {};
					var elines = [];
					$flosc_status.html('<span class="flosc-pass-status flosc-pass-status--fail">✗ Connection Failed</span>');
					// Report the layer that actually failed. Everything above the
					// failure is confirmed; only the last line is the diagnosis.
					elines.push('\u2713 FLOSC configuration loaded');
					if (ed.provider) { elines.push('\u2713 Provider selected: ' + ed.provider); }
					if (ed.api_key_present === false) {
						elines.push('\u2717 No API key in the saved settings');
						elines.push('');
						elines.push('Paste the key above, then Save Settings, then Test again. A key that is typed but not saved is not yet a key.');
					} else {
						if (ed.api_key_suffix) {
							elines.push('\u2713 API key saved (\u2026' + ed.api_key_suffix + ')');
						} else if (ed.api_key_present) {
							elines.push('\u2713 API key saved');
						}
						if (ed.model) { elines.push('\u2713 Model requested: ' + ed.model); }
						if (ed.endpoint) { elines.push('\u2713 Endpoint reached: ' + ed.endpoint); }
						elines.push('\u2717 The request was rejected before a reply came back');
						elines.push('');
						elines.push('What the provider integration said:');
					}
					elines.push(ed.message || 'Unknown error');
					if (ed.flow_ivr || ed.response_time != null) { elines.push(''); }
					if (ed.flow_ivr) { elines.push('Flow: ' + ed.flow_ivr); }
					if (ed.response_time != null) { elines.push('Round trip: ' + ed.response_time + ' ms'); }
					floscTestModelReport(ed, elines);
					$details.text(elines.join('\n'));
				}
			},
			error: function() {
				$loading.hide();
				$results.show();
				$flosc_status.html('<span class="flosc-pass-status flosc-pass-status--fail">✗ Request Failed</span>');
				$details.text('Could not reach the server. Please try again.');
			},
			complete: function() {
				$btn.prop('disabled', false);
			}
		});
	});
});

jQuery(document).ready(function($) {
	function floscAccVarMap() {
		var raw = $('#flosc-accuracy-rows').attr('data-flosc-acc-map') || '{}';
		try { return JSON.parse(raw); } catch (e) { return {}; }
	}
	function floscAccExpand(text, map) {
		var out = String(text || '');
		$.each(map || {}, function(k, v) {
			out = out.split(k).join(v);
		});
		return out;
	}
	function floscAccRefreshPreviews() {
		var map = floscAccVarMap();
		$('#flosc-accuracy-rows .flosc-acc-row').each(function() {
			var t = $(this).find('.flosc-acc-row__input').val() || '';
			$(this).find('.flosc-acc-row__preview').text(floscAccExpand(t, map));
		});
	}
	function floscSyncAccuracyHidden() {
		var lines = [];
		$('#flosc-accuracy-rows .flosc-acc-row__input').each(function() {
			var t = $.trim($(this).val() || '');
			if (t) { lines.push(t); }
		});
		$('#flow_ai_accuracy_test_questions').val(lines.join('\n'));
		$('#flosc-test-msg-total').text(Math.max(1, lines.length));
		floscAccRefreshPreviews();
	}
	function floscParseAccuracyQuestions() {
		floscSyncAccuracyHidden();
		var map = floscAccVarMap();
		var msgs = [];
		$('#flosc-accuracy-rows .flosc-acc-row__input').each(function() {
			var t = $.trim($(this).val() || '');
			if (t) { msgs.push(floscAccExpand(t, map)); }
		});
		return msgs;
	}

	$('#flosc-accuracy-rows').on('input change', '.flosc-acc-row__input', floscSyncAccuracyHidden);

	$('#flosc-accuracy-rows').on('click', '.flosc-acc-reset-row', function() {
		var $row = $(this).closest('.flosc-acc-row');
		var $inp = $row.find('.flosc-acc-row__input');
		var tpl = $inp.attr('data-acc-template') || '';
		$inp.val(tpl);
		floscSyncAccuracyHidden();
	});

	$('#flosc-accuracy-reset-defaults').on('click', function() {
		$('#flosc-accuracy-rows .flosc-acc-row__input').each(function() {
			$(this).val($(this).attr('data-acc-template') || '');
		});
		floscSyncAccuracyHidden();
	});

	floscSyncAccuracyHidden();

	$('#flosc-run-accuracy-test').on('click', function() {
		var testMessages = floscParseAccuracyQuestions();
		if (!testMessages.length) {
			window.alert(String(floscAdminAi.i18n.addQuestion));
			return;
		}

		var $btn = $(this);
		$btn.prop('disabled', true);
		$('#flosc-test-msg-total').text(testMessages.length);
		$('#flosc-test-progress').show();
		$('#flosc-accuracy-results').hide();
		$('#flosc-accuracy-tbody').empty();
		$('#flosc-accuracy-summary').empty();

		var history = [];
		var totalTokensIn = 0;
		var passes = 0;
		var feedback = 0;
		function accuracyFailureMessage(payload, fallback) {
			var data = payload && payload.data ? payload.data : payload;
			if (typeof data === 'string' && data) {
				return data;
			}
			if (data && typeof data.message === 'string' && data.message) {
				return data.message;
			}
			return fallback;
		}

		function appendAccuracyFailure(idx, message) {
			$('#flosc-accuracy-tbody').append(
				'<tr>'
				+ '<td>' + (idx + 1) + '</td>'
				+ '<td class="flosc-text-12">' + $('<div>').text(testMessages[idx]).html() + '</td>'
				+ '<td class="flosc-text-12 flosc-request-failed">' + $('<div>').text(message).html() + '</td>'
				+ '<td>0</td>'
				+ '<td class="flosc-pass-status flosc-pass-status--fail">✗</td>'
				+ '</tr>'
			);
		}

		function runMessage(idx) {
			if (idx >= testMessages.length) {
				var summaryHtml = '<strong>Session Summary:</strong> '
					+ passes + '/' + testMessages.length + ' completed without hard fail | '
					+ 'Total input tokens: ' + totalTokensIn + ' | '
					+ 'Flags: ' + feedback;
				$('#flosc-accuracy-summary').html(summaryHtml);
				$('#flosc-accuracy-results').show();
				$('#flosc-test-progress').hide();
				$btn.prop('disabled', false);
				return;
			}
			$('#flosc-test-msg-num').text(idx + 1);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'flosc_accuracy_test_message',
					nonce: String(floscAdminAi.accuracyNonce),
					ivr: String(floscAdminAi.ivr),
					message: testMessages[idx],
					message_index: idx,
					history: JSON.stringify(history)
				},
				success: function(resp) {
					if (!resp || resp.success !== true) {
						appendAccuracyFailure(idx, accuracyFailureMessage(resp, 'The AI provider request failed.'));
						runMessage(idx + 1);
						return;
					}
					var r = resp.data || {};
					var response_text = r.response || '(no response)';
					var tokens_in = r.tokens_in || 0;
					var pass = r.pass !== false;
					var corrected = r.corrected || false;

					totalTokensIn += tokens_in;
					if (pass) passes++;
					if (corrected) feedback++;

					var passCell = pass
						? '<td class="flosc-pass-status flosc-pass-status--pass">✓</td>'
						: '<td class="flosc-pass-status flosc-pass-status--fail">✗</td>';
					if (corrected) passCell = '<td class="flosc-pass-status flosc-pass-status--corrected">⚡</td>';

					var snippet = response_text.length > 200
						? response_text.substring(0, 200) + '…'
						: response_text;

					$('#flosc-accuracy-tbody').append(
						'<tr>'
						+ '<td>' + (idx + 1) + '</td>'
						+ '<td class="flosc-text-12">' + $('<div>').text(testMessages[idx]).html() + '</td>'
						+ '<td class="flosc-text-12">' + $('<div>').text(snippet).html() + '</td>'
						+ '<td>' + tokens_in + '</td>'
						+ passCell
						+ '</tr>'
					);

					history.push({ role: 'user', content: testMessages[idx] });
					history.push({ role: 'assistant', content: response_text });

					runMessage(idx + 1);
				},
				error: function(xhr) {
					appendAccuracyFailure(
						idx,
						accuracyFailureMessage(xhr && xhr.responseJSON ? xhr.responseJSON : null, 'The AI provider request failed.')
					);
					runMessage(idx + 1);
				}
			});
		}

		runMessage(0);
	});
});
