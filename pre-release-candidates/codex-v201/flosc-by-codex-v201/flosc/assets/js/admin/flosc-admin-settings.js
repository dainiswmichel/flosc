/**
 * FLOSC Settings: working-flow switcher, flow-selector details toggle, and the
 * browser tab title for the active settings tab.
 *
 * Enqueued by admin/settings.php. The active tab arrives through
 * wp_localize_script() as floscAdminSettings.tab.
 *
 * @package FLOSC
 */
/* global floscAdminSettings */
function switchIVR(ivr) {
	const form = document.getElementById('flosc-working-flow-form');
	const field = document.getElementById('flosc-working-flow-ivr');
	if (!form || !field) {
		return;
	}
	field.value = ivr;
	form.submit();
}

(function () {
	const ivrSelect = document.getElementById('ivr-select');
	if (ivrSelect) {
		ivrSelect.addEventListener('change', function () {
			switchIVR(this.value);
		});
	}

	const detailsToggle = document.getElementById('flosc-flow-selector-details-toggle');
	const detailsPanel = document.getElementById('flosc-flow-selector-details-panel');
	if (detailsToggle && detailsPanel) {
		detailsToggle.addEventListener('click', function () {
			const isOpen = detailsToggle.getAttribute('aria-expanded') === 'true';
			const nextOpen = !isOpen;
			detailsToggle.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
			detailsToggle.classList.toggle('is-open', nextOpen);
			if (nextOpen) {
				detailsPanel.removeAttribute('hidden');
			} else {
				detailsPanel.setAttribute('hidden', '');
			}
		});
	}

	const tab = (typeof floscAdminSettings !== 'undefined' && floscAdminSettings.tab) ? String(floscAdminSettings.tab) : '';
	const tabTitles = {
		'flow': 'Flow',
		'identity': 'Identity',
		'ivr-messages': 'IVR Management',
		'autoprompts': 'AutoPrompt Panel',
		'content': 'Content',
		'member-levels': 'Content',
		'lessons': 'Content',
		'knowledge-base': 'Knowledge Base',
		'trajectories': 'Trajectories',
		'offers': 'Offers',
		'login': 'Register and Login',
		'style': 'Style and Nav',
		'ui': 'Profile Bar',
		'ai': 'AI',
		'token-management': 'Token Management',
		'concierge': 'Concierge',
		'quiz': 'Quiz',
		'email': 'Email',
		'contact-form': 'Contact Form',
		'payments': 'Payments',

		'sso': 'SSO',
		'engagement': 'Engagement',
		'chat-logs': 'Chat Logs',
		'administration': 'Administration',
		'documentation': 'Docs',
		'da1': 'DA1'
	};

	const section = tabTitles[tab] || 'Settings';
	document.title = section + ' < Settings < FLOSC';
})();
