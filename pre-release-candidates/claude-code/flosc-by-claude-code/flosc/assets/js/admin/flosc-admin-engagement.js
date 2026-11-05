/**
 * Engagement tab: condition chips, add/remove rules, reindex form fields.
 *
 * Moved out of the PHP template so the page enqueues a static file; every
 * value that used to be printed into the script now arrives through
 * wp_localize_script().
 */
(function () {
	var lastCond = null;

	function slugify(s) {
		return String(s || '')
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, '_')
			.replace(/^_+|_+$/g, '')
			.substring(0, 48) || 'rule';
	}

	function reindexRules() {
		var rules = document.querySelectorAll('.flosc-eng-rule-list .flosc-eng-rule');
		rules.forEach(function (rule, i) {
			rule.setAttribute('data-rule-index', String(i));
			var en = rule.querySelector('.flosc-eng-rule-enabled');
			if (en) en.name = 'engagement_rule_enabled[' + i + ']';
			var ach = rule.querySelector('.flosc-eng-rule-action-chat');
			if (ach) ach.name = 'engagement_rule_action_chat[' + i + ']';
			var aem = rule.querySelector('.flosc-eng-rule-action-email');
			if (aem) aem.name = 'engagement_rule_action_email[' + i + ']';
			var idInput = rule.querySelector('.flosc-eng-rule-id');
			var titleInput = rule.querySelector('.flosc-eng-rule-title');
			if (idInput && (!idInput.value || idInput.value.indexOf('rule_') === 0 || idInput.value === '')) {
				var t = titleInput ? titleInput.value : '';
				idInput.value = slugify(t) + '_' + i;
			}
			var idLab = rule.querySelector('.flosc-eng-rule-id-label');
			if (idLab && idInput) idLab.textContent = idInput.value;
			var sumTitle = rule.querySelector('.flosc-eng-rule__summary-title');
			if (sumTitle && titleInput) sumTitle.textContent = titleInput.value || idInput.value || ('Rule ' + (i + 1));
		});
	}

	function refreshAudienceCounts() {
		document.querySelectorAll('.flosc-eng-accordion').forEach(function (acc) {
			var list = acc.querySelector('.flosc-eng-rule-list');
			if (!list) return;
			var n = 0;
			list.querySelectorAll('.flosc-eng-rule').forEach(function (rule) {
				var en = rule.querySelector('.flosc-eng-rule-enabled');
				if (en && en.checked) n++;
			});
			var sub = acc.querySelector('.flosc-eng-accordion__sub');
			if (!sub) return;
			var base = sub.textContent.replace(/\d+\s*active rule\(s\)/, '').replace(/·\s*$/, '').trim();
			sub.textContent = base + ' · ' + n + ' active rule(s)';
		});
	}

	document.addEventListener('focusin', function (e) {
		if (e.target && e.target.classList && e.target.classList.contains('flosc-eng-condition-input')) {
			lastCond = e.target;
		}
	});

	document.addEventListener('input', function (e) {
		if (e.target && e.target.classList && e.target.classList.contains('flosc-eng-rule-title')) {
			var rule = e.target.closest('.flosc-eng-rule');
			if (!rule) return;
			var sum = rule.querySelector('.flosc-eng-rule__summary-title');
			if (sum) sum.textContent = e.target.value || 'New rule';
			var idInput = rule.querySelector('.flosc-eng-rule-id');
			if (idInput) {
				var idx = rule.getAttribute('data-rule-index') || '0';
				idInput.value = slugify(e.target.value) + '_' + idx;
				var idLab = rule.querySelector('.flosc-eng-rule-id-label');
				if (idLab) idLab.textContent = idInput.value;
			}
		}
	});

	document.addEventListener('change', function (e) {
		if (e.target && e.target.classList && e.target.classList.contains('flosc-eng-rule-enabled')) {
			refreshAudienceCounts();
			var rule = e.target.closest('.flosc-eng-rule');
			if (rule) {
				var prev = rule.querySelector('.flosc-eng-rule__summary-preview');
				if (prev) {
					var t = prev.textContent.replace(/\b(active|off)\b/, e.target.checked ? 'active' : 'off');
					prev.textContent = t;
				}
			}
		}
	});

	document.addEventListener('click', function (e) {
		var chip = e.target.closest('.flosc-condition-copy');
		if (chip) {
			e.preventDefault();
			var condition = chip.getAttribute('data-condition') || '';
			if (!condition) return;
			var input = lastCond;
			if (!input || !document.body.contains(input)) {
				input = document.querySelector('.flosc-eng-condition-input');
			}
			if (input) {
				var cur = (input.value || '').trim();
				input.value = cur ? (cur + ' && ' + condition) : condition;
				input.focus();
				lastCond = input;
			}
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(condition);
			}
			return;
		}

		var addBtn = e.target.closest('.flosc-eng-add-rule');
		if (addBtn) {
			e.preventDefault();
			var audience = addBtn.getAttribute('data-audience') || 'guest';
			var list = document.querySelector('.flosc-eng-rule-list[data-audience="' + audience + '"]');
			var tpl = document.getElementById('flosc-eng-rule-template');
			if (!list || !tpl) return;
			var node = tpl.content.cloneNode(true);
			var rule = node.querySelector('.flosc-eng-rule');
			if (!rule) return;
			rule.setAttribute('data-audience', audience);
			var audInput = rule.querySelector('.flosc-eng-rule-audience');
			if (audInput) audInput.value = audience;
			var titleInput = rule.querySelector('.flosc-eng-rule-title');
			if (titleInput) {
				var label = audience.charAt(0).toUpperCase() + audience.slice(1) + ' · new rule';
				titleInput.value = label;
			}
			var sumTitle = rule.querySelector('.flosc-eng-rule__summary-title');
			if (sumTitle && titleInput) sumTitle.textContent = titleInput.value;
			var idInput = rule.querySelector('.flosc-eng-rule-id');
			if (idInput) idInput.value = '';
			var cond = rule.querySelector('.flosc-eng-condition-input');
			if (cond) {
				if (audience === 'visitor') cond.value = 'is_visitor';
				else if (audience === 'guest') cond.value = 'is_guest';
				else if (audience === 'member') cond.value = 'is_member';
			}
			rule.setAttribute('open', '');
			list.appendChild(rule);
			reindexRules();
			refreshAudienceCounts();
			return;
		}

		var rem = e.target.closest('.flosc-eng-rule-remove');
		if (rem) {
			e.preventDefault();
			var ruleEl = rem.closest('.flosc-eng-rule');
			if (!ruleEl) return;
			var listEl = ruleEl.closest('.flosc-eng-rule-list');
			if (listEl && listEl.querySelectorAll('.flosc-eng-rule').length <= 1) {
				// Keep at least one shell: clear fields instead of removing last.
				var t = ruleEl.querySelector('.flosc-eng-rule-title');
				if (t) t.value = 'New rule';
				var c = ruleEl.querySelector('.flosc-eng-condition-input');
				if (c) c.value = '';
				var m = ruleEl.querySelector('.flosc-eng-rule-chat-message');
				if (m) m.value = '';
				var en = ruleEl.querySelector('.flosc-eng-rule-enabled');
				if (en) en.checked = false;
				var sum = ruleEl.querySelector('.flosc-eng-rule__summary-title');
				if (sum) sum.textContent = 'New rule';
				reindexRules();
				refreshAudienceCounts();
				return;
			}
			ruleEl.remove();
			reindexRules();
			refreshAudienceCounts();
		}
	});

	reindexRules();
})();
