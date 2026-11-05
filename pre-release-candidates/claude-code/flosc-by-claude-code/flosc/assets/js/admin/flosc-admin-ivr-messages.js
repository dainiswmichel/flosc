/**
 * FLOSC Settings, IVR Management tab: the REST endpoint test, message-card
 * accordion, offer/concierge field toggles and the new-message editor toggle.
 *
 * The functions stay global because assets/js/flosc-admin-events.js calls
 * them from its shared [data-flosc-action] handler.
 *
 * Enqueued by admin/settings.php's IVR tab (admin/ivr-messages.php). The REST
 * URL arrives through wp_localize_script() as floscAdminIvrMessages.apiTestUrl.
 *
 * @package FLOSC
 */
/* global floscAdminIvrMessages */
function floscAppendAPIResultNode(parent, tagName, text) {
	const node = tagName ? document.createElement(tagName) : document.createTextNode(String(text));
	if (tagName) {
		node.textContent = String(text);
	}
	parent.appendChild(node);
	return node;
}

function floscAppendAPIResultBreaks(parent, count) {
	for (let i = 0; i < count; i++) {
		parent.appendChild(document.createElement('br'));
	}
}

function floscBeginAPIResult(parent, icon, title) {
	parent.textContent = '';
	floscAppendAPIResultNode(parent, '', icon + ' ');
	floscAppendAPIResultNode(parent, 'strong', title);
}

function floscAppendAPIResultLine(parent, label, value) {
	floscAppendAPIResultNode(parent, 'strong', label);
	floscAppendAPIResultNode(parent, '', ' ' + String(value));
	floscAppendAPIResultBreaks(parent, 1);
}

function floscAppendAPIResponseDetails(parent, data) {
	const details = document.createElement('details');
	floscAppendAPIResultNode(details, 'summary', 'Full response');
	floscAppendAPIResultNode(details, 'pre', JSON.stringify(data, null, 2));
	parent.appendChild(details);
}

function floscTestAPI() {
	const resultDiv = document.getElementById('flosc-api-test-result');
	const btn = document.getElementById('flosc-test-api');

	resultDiv.style.display = 'block';
	resultDiv.style.background = '#e9ecef';
	resultDiv.textContent = '⏳ Testing API endpoint...';
	btn.disabled = true;

	const apiUrl = String(floscAdminIvrMessages.apiTestUrl);

	fetch(apiUrl)
		.then(response => response.json())
		.then(data => {
			btn.disabled = false;

			if (data.success && data.messages) {
				const msgCount = data.messages.length;
				const msgNames = data.messages.map(m => m.name || 'unnamed').join(', ');

				if (msgCount > 0) {
					resultDiv.style.background = '#d4edda';
					floscBeginAPIResult(resultDiv, '✅', 'API Working!');
					floscAppendAPIResultBreaks(resultDiv, 2);
					floscAppendAPIResultLine(resultDiv, 'Messages returned:', msgCount);
					floscAppendAPIResultLine(resultDiv, 'Names:', msgNames);
					floscAppendAPIResultLine(resultDiv, 'User context:', JSON.stringify(data.user_context));
					floscAppendAPIResultBreaks(resultDiv, 1);
					floscAppendAPIResponseDetails(resultDiv, data);
				} else {
					resultDiv.style.background = '#fff3cd';
					floscBeginAPIResult(resultDiv, '⚠️', 'API responded but returned 0 messages');
					floscAppendAPIResultBreaks(resultDiv, 2);
					floscAppendAPIResultNode(resultDiv, '', 'This usually means condition evaluation is filtering everything out.');
					floscAppendAPIResultBreaks(resultDiv, 1);
					floscAppendAPIResultLine(resultDiv, 'User context:', JSON.stringify(data.user_context));
					floscAppendAPIResultBreaks(resultDiv, 1);
					floscAppendAPIResultNode(resultDiv, '', 'Check that conditions like "is_visitor" are being evaluated correctly.');
				}
			} else {
				resultDiv.style.background = '#f8d7da';
				floscBeginAPIResult(resultDiv, '❌', 'API Error');
				floscAppendAPIResultBreaks(resultDiv, 1);
				floscAppendAPIResultNode(resultDiv, 'pre', JSON.stringify(data, null, 2));
			}
		})
		.catch(error => {
			btn.disabled = false;
			resultDiv.style.background = '#f8d7da';
			floscBeginAPIResult(resultDiv, '❌', 'Fetch failed:');
			floscAppendAPIResultNode(resultDiv, '', ' ' + error.message);
		});
}

/*
 * There is no click listener here on purpose.
 *
 * This page used to carry its own document-level [data-flosc-action] handler
 * alongside the shared one in assets/js/flosc-admin-events.js, which is
 * enqueued on every FLOSC admin screen. Both matched the same element and both
 * called the same function, so one click ran floscToggleMsg() twice: the card
 * opened and closed in the same frame and the accordion looked dead. The same
 * double-fire hit toggle-new-editor and test-api-endpoint.
 *
 * flosc-admin-events.js handles all three, plus delete-message generically via
 * data-confirm-message and data-stop-propagation. The change listener for
 * toggle-offer-fields was doubled the same way; it never showed because
 * floscToggleOfferFields() sets the class from an explicit boolean and running
 * it twice lands in the same place. It was one refactor away from behaving
 * like the accordion.
 *
 * The submit listener went the same way, and that one was visible: a form
 * carrying data-confirm-message asked twice, because both handlers called
 * confirm(). Two dialogs for one Delete.
 *
 * Adding a second listener for an action or attribute that
 * flosc-admin-events.js already owns is how all of this returns.
 */

function floscToggleMsg(id) {
	const card = document.getElementById('card-' + id);
	if (!card) return;
	card.classList.toggle('is-open');
}
function floscToggleOfferFields(selectEl, msgId) {
	const panel = document.getElementById('offer-fields-' + msgId);
	if (panel) panel.classList.toggle('flosc-hidden', selectEl.value !== 'offer');
	const cpanel = document.getElementById('concierge-fields-' + msgId);
	if (cpanel) cpanel.classList.toggle('flosc-hidden', selectEl.value !== 'concierge');
}
function floscToggleNewEditor(phaseId, shouldOpen) {
	const editor = document.getElementById('editor-new-' + phaseId);
	const button = document.getElementById('add-btn-' + phaseId);
	if (editor) {
		editor.classList.toggle('flosc-hidden', !shouldOpen);
	}
	if (button) {
		button.classList.toggle('flosc-hidden', !!shouldOpen);
	}
}
// Auto-scroll to expanded message on page load
document.addEventListener('DOMContentLoaded', function() {
	const open = document.querySelector('.flosc-msg-card.is-open');
	if (open) open.scrollIntoView({ behavior: 'smooth', block: 'center' });
});
