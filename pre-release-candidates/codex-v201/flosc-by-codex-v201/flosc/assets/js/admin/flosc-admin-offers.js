/**
 * FLOSC Settings, Offers tab: offer-card accordion and the offer editor's
 * format and token-grant controls.
 *
 * Enqueued by admin/offers.php.
 *
 * @package FLOSC
 */
function floscToggleOffer(id) {
	const card = document.getElementById('offer-' + id);
	if (card) card.classList.toggle('is-open');
}
function floscToggleFmt(checkbox) {
	const card = checkbox.closest('.flosc-fmt-card');
	card.classList.toggle('is-enabled', checkbox.checked);
}
function floscToggleProcessorFields(safeId, processor) {
	const processors = ['paypal', 'stripe', 'redirect'];
	processors.forEach(function(p) {
		const el = document.getElementById('proc-' + p + '-' + safeId);
		if (el) el.style.display = (p === processor) ? 'block' : 'none';
	});
}
// Condition reference: click to copy to clipboard and nearest condition input
document.addEventListener('click', function(e) {
	const btn = e.target.closest('.flosc-condition-copy');
	if (!btn) return;
	const condition = btn.dataset.condition;
	if (!condition) return;

	// Copy to clipboard
	navigator.clipboard.writeText(condition).then(function() {
		const original = btn.innerHTML;
		btn.innerHTML = '<code>✓ Copied!</code>';
		btn.classList.add('copied');
		setTimeout(function() {
			btn.innerHTML = original;
			btn.classList.remove('copied');
		}, 1500);
	});

	// Also paste into the nearest offer's global condition input
	const offerCard = btn.closest('.flosc-offer-editor');
	if (offerCard) {
		const condInput = offerCard.querySelector('input[name="offer_condition"]');
		if (condInput) {
			// Append with && if there's already a value
			if (condInput.value.trim()) {
				condInput.value = condInput.value.trim() + ' && ' + condition;
			} else {
				condInput.value = condition;
			}
			condInput.focus();
			condInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}
	}
});
document.addEventListener('DOMContentLoaded', function() {
	const open = document.querySelector('.flosc-offer-card.is-open');
	if (open) open.scrollIntoView({ behavior: 'smooth', block: 'start' });

	document.querySelectorAll('form').forEach(function(form) {
		var src = form.querySelector('[data-flosc-offer-token-source]');
		if (!src) return;

		var offer = src.getAttribute('data-flosc-offer');
		var custom = form.querySelector('[data-flosc-offer-token-custom="' + offer + '"]');
		var capMode = form.querySelector('[data-flosc-offer-token-cap-mode]');
		var capInput = form.querySelector('[data-flosc-offer-token-cap-input="' + offer + '"]');

		function syncSource() {
			if (custom) custom.classList.toggle('flosc-is-disabled', src.value !== 'custom');
		}

		function syncCap() {
			if (capInput) capInput.classList.toggle('flosc-is-disabled', !capMode || capMode.value !== 'custom');
		}

		src.addEventListener('change', syncSource);
		if (capMode) {
			capMode.addEventListener('change', syncCap);
		}

		syncSource();
		syncCap();
	});
});
