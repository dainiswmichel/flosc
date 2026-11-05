/**
 * Chat Navigation tab: visitor, guest and member menu repeaters.
 *
 * Moved out of the PHP template so the page enqueues a static file; every
 * value that used to be printed into the script now arrives through
 * wp_localize_script().
 */
/* global floscAdminChatNavigation */
(function() {
	const tbody = document.getElementById('flosc-menu-items-body');
	const addBtn = document.getElementById('flosc-add-menu-item');
	const actions = floscAdminChatNavigation.actions;

	if (!tbody || !addBtn) {
		return;
	}

	function buildActionOptions(selected) {
		let html = '';
		for (const [val, desc] of Object.entries(actions)) {
			const sel = val === selected ? ' selected' : '';
			html += `<option value="${val}"${sel}>${desc}</option>`;
		}
		return html;
	}

	function renumber() {
		tbody.querySelectorAll('.flosc-menu-row').forEach((row, i) => {
			row.querySelector('.flosc-menu-row-number').textContent = i + 1;
		});
	}

	addBtn.addEventListener('click', function() {
		const tr = document.createElement('tr');
		tr.className = 'flosc-menu-row';
		tr.innerHTML = `
			<td class="flosc-menu-row-number"></td>
			<td><input type="text" name="visitor_menu_label[]" class="regular-text" placeholder="Menu label" required></td>
			<td><select name="visitor_menu_action[]" class="flosc-ui-action-select">${buildActionOptions('open_quiz')}</select></td>
			<td><button type="button" class="button flosc-remove-menu-item" title="Remove item">&times;</button></td>
		`;
		tbody.appendChild(tr);
		renumber();
		tr.querySelector('input').focus();
	});

	tbody.addEventListener('click', function(e) {
		if (e.target.classList.contains('flosc-remove-menu-item')) {
			e.target.closest('.flosc-menu-row').remove();
			renumber();
		}
	});
})();

(function() {
	const tbody = document.getElementById('flosc-guest-menu-items-body');
	const addBtn = document.getElementById('flosc-guest-add-menu-item');
	const actions = floscAdminChatNavigation.actions;

	if (!tbody || !addBtn) {
		return;
	}

	function buildActionOptions(selected) {
		let html = '';
		for (const [val, desc] of Object.entries(actions)) {
			const sel = val === selected ? ' selected' : '';
			html += `<option value="${val}"${sel}>${desc}</option>`;
		}
		return html;
	}

	function renumber() {
		tbody.querySelectorAll('.flosc-guest-menu-row').forEach((row, i) => {
			row.querySelector('.flosc-guest-menu-row-number').textContent = i + 1;
		});
	}

	addBtn.addEventListener('click', function() {
		const tr = document.createElement('tr');
		tr.className = 'flosc-guest-menu-row';
		tr.innerHTML = `
			<td class="flosc-guest-menu-row-number"></td>
			<td><input type="text" name="guest_menu_label[]" class="regular-text" placeholder="Menu label" required></td>
			<td><select name="guest_menu_action[]" class="flosc-ui-action-select">${buildActionOptions('open_quiz')}</select></td>
			<td><button type="button" class="button flosc-guest-remove-menu-item" title="Remove item">&times;</button></td>
		`;
		tbody.appendChild(tr);
		renumber();
		tr.querySelector('input').focus();
	});

	tbody.addEventListener('click', function(e) {
		if (e.target.classList.contains('flosc-guest-remove-menu-item')) {
			e.target.closest('.flosc-guest-menu-row').remove();
			renumber();
		}
	});
})();

(function() {
	const tbody = document.getElementById('flosc-member-menu-items-body');
	const addBtn = document.getElementById('flosc-member-add-menu-item');
	const actions = floscAdminChatNavigation.actions;

	if (!tbody || !addBtn) {
		return;
	}

	function buildActionOptions(selected) {
		let html = '';
		for (const [val, desc] of Object.entries(actions)) {
			const sel = val === selected ? ' selected' : '';
			html += `<option value="${val}"${sel}>${desc}</option>`;
		}
		return html;
	}

	function renumber() {
		tbody.querySelectorAll('.flosc-member-menu-row').forEach((row, i) => {
			row.querySelector('.flosc-member-menu-row-number').textContent = i + 1;
		});
	}

	addBtn.addEventListener('click', function() {
		const tr = document.createElement('tr');
		tr.className = 'flosc-member-menu-row';
		tr.innerHTML = `
			<td class="flosc-member-menu-row-number"></td>
			<td><input type="text" name="member_menu_label[]" class="regular-text" placeholder="Menu label" required></td>
			<td><select name="member_menu_action[]" class="flosc-ui-action-select">${buildActionOptions('open_lesson_library')}</select></td>
			<td><button type="button" class="button flosc-member-remove-menu-item" title="Remove item">&times;</button></td>
		`;
		tbody.appendChild(tr);
		renumber();
		tr.querySelector('input').focus();
	});

	tbody.addEventListener('click', function(e) {
		if (e.target.classList.contains('flosc-member-remove-menu-item')) {
			e.target.closest('.flosc-member-menu-row').remove();
			renumber();
		}
	});
})();
