/**
 * DA1 tab: single-catalog row editor.
 *
 * Moved out of the PHP template so the page enqueues a static file; every
 * value that used to be printed into the script now arrives through
 * wp_localize_script().
 */
/* global floscAdminDa1 */
(function () {
	var COLUMNS = floscAdminDa1.columns;
	var NCOLS = (parseInt(floscAdminDa1.ncols, 10) || 0);
	var MULTILINE = floscAdminDa1.multiline;

	function colIndex(name) {
		return COLUMNS.indexOf(name);
	}

	function nextIndex() {
		return document.querySelectorAll('#da1-tbody .da1-row').length;
	}

	function nextParentKey() {
		var iRow = colIndex('Row Key');
		var maxVal = 0;
		document.querySelectorAll('#da1-tbody .da1-row').forEach(function (tr) {
			var input = tr.querySelector('[name*="[' + iRow + ']"]');
			if (!input) return;
			var v = (input.value || '').trim();
			if (/^[0-9]+$/.test(v)) {
				var n = parseInt(v, 10);
				if (n > maxVal) maxVal = n;
			}
		});
		return String(maxVal + 1);
	}

	function nextChildKey(parentKey) {
		var iRow = colIndex('Row Key');
		var maxSuffix = 0;
		var pattern = new RegExp('^' + parentKey.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\.(\\d+)$');
		document.querySelectorAll('#da1-tbody .da1-row').forEach(function (tr) {
			var input = tr.querySelector('[name*="[' + iRow + ']"]');
			if (!input) return;
			var m = (input.value || '').trim().match(pattern);
			if (m) {
				var s = parseInt(m[1], 10);
				if (s > maxSuffix) maxSuffix = s;
			}
		});
		return parentKey + '.' + String(maxSuffix + 1);
	}

	function createInput(ci, name, value) {
		if (MULTILINE.indexOf(ci) !== -1) {
			var ta = document.createElement('textarea');
			ta.name = name;
			ta.rows = 3;
			ta.className = 'flosc-da1-input flosc-da1-textarea';
			ta.value = value || '';
			return ta;
		}
		var inp = document.createElement('input');
		inp.type = 'text';
		inp.name = name;
		inp.className = 'flosc-da1-input';
		inp.value = value || '';
		return inp;
	}

	function makeRow(seed) {
		var ri = nextIndex();
		var tr = document.createElement('tr');
		tr.className = 'da1-row flosc-da1-row';

		for (var ci = 0; ci < NCOLS; ci++) {
			var td = document.createElement('td');
			td.className = 'flosc-da1-cell';
			var name = 'da1_rows[' + ri + '][' + ci + ']';
			var col = COLUMNS[ci];
			var val = (seed && seed[col]) ? seed[col] : '';
			td.appendChild(createInput(ci, name, val));
			tr.appendChild(td);
		}

		var tdBtn = document.createElement('td');
		tdBtn.className = 'flosc-da1-cell flosc-da1-cell-actions';
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'da1-open-modal button button-small flosc-da1-edit-btn';
		btn.textContent = 'Edit';
		tdBtn.appendChild(btn);
		tr.appendChild(tdBtn);

		return tr;
	}

	function readRowObj(tr) {
		var obj = {};
		COLUMNS.forEach(function (col, ci) {
			var f = tr.querySelector('[name*="[' + ci + ']"]');
			obj[col] = f ? f.value : '';
		});
		return obj;
	}

	function addEntryRow() {
		var rowKey = nextParentKey();
		var seed = {
			'Row Key': rowKey,
			'Parent Key': '',
			'Item Type': 'item',
			'Status': 'active',
			'VGM': 'Visitor Guest Member',
			'Flow Scope': 'all'
		};
		var tr = makeRow(seed);
		document.getElementById('da1-tbody').appendChild(tr);
		openModal(tr);
	}

	function addRelatedRowFrom(baseTr) {
		var data = readRowObj(baseTr);
		var parent = (data['Parent Key'] || '').trim();
		var rowKey = (data['Row Key'] || '').trim();
		if (rowKey === '') {
			rowKey = nextParentKey();
			var iRowKey = colIndex('Row Key');
			var keyInput = baseTr.querySelector('[name*="[' + iRowKey + ']"]');
			if (keyInput) {
				keyInput.value = rowKey;
			}
		}
		var parentKey = parent !== '' ? parent : rowKey;
		if (parentKey === '') {
			alert('Entry key is required before adding a child entry.');
			return;
		}
		var seed = {
			'Row Key': nextChildKey(parentKey),
			'Parent Key': parentKey,
			'Catalog Key': data['Catalog Key'] || '',
			'Item Type': data['Item Type'] || 'item',
			'Flow Scope': data['Flow Scope'] || 'all',
			'VGM': data['VGM'] || 'Visitor Guest Member',
			'Delivery Instruction': data['Delivery Instruction'] || 'intent match',
			'Delivery Rule': data['Delivery Rule'] || 'preference',
			'Fallback Order': data['Fallback Order'] || 'text',
			'Status': 'active'
		};
		var tr = makeRow(seed);
		document.getElementById('da1-tbody').appendChild(tr);
		openModal(tr);
	}

	var overlay = null;
	var body = null;
	var titleNode = null;
	var activeRow = null;

	function ensureModal() {
		if (overlay) return;
		overlay = document.createElement('div');
		overlay.className = 'flosc-da1-modal-overlay';
		overlay.id = 'da1-modal-overlay';
		overlay.innerHTML = [
			'<div class="flosc-da1-modal">',
			'<div class="flosc-da1-modal-header">',
			'<span id="da1-modal-title">Edit Row</span>',
			'<button type="button" id="da1-modal-close" class="flosc-da1-modal-close">&times;</button>',
			'</div>',
			'<div id="da1-modal-body" class="flosc-da1-modal-body"></div>',
			'<div class="flosc-da1-modal-footer">',
			'<button type="button" id="da1-modal-delete" class="flosc-da1-modal-delete">Delete this row</button>',
			'<div class="flosc-da1-modal-actions">',
			'<button type="button" id="da1-modal-cancel" class="button">Cancel</button>',
			'<button type="button" id="da1-modal-save" class="button button-primary">Save</button>',
			'</div>',
			'</div>',
			'</div>'
		].join('');
		document.body.appendChild(overlay);
		body = document.getElementById('da1-modal-body');
		titleNode = document.getElementById('da1-modal-title');

		document.getElementById('da1-modal-save').addEventListener('click', saveModal);
		document.getElementById('da1-modal-cancel').addEventListener('click', closeModal);
		document.getElementById('da1-modal-close').addEventListener('click', closeModal);
		document.getElementById('da1-modal-delete').addEventListener('click', function () {
			if (activeRow) {
				activeRow.remove();
			}
			closeModal();
		});
		overlay.addEventListener('click', function (e) {
			if (e.target === overlay) {
				closeModal();
			}
		});
	}

	function getRowFields(tr) {
		return Array.prototype.slice.call(tr.querySelectorAll('input[name*="da1_rows"], textarea[name*="da1_rows"]'));
	}

	function openModal(tr) {
		ensureModal();
		activeRow = tr;
		body.innerHTML = '';
		var fields = getRowFields(tr);
		var title = '';
		var tIdx = colIndex('Title');
		if (tIdx >= 0 && fields[tIdx]) {
			title = fields[tIdx].value.trim();
		}
		titleNode.textContent = title || 'Edit Row';

		COLUMNS.forEach(function (col, ci) {
			var wrap = document.createElement('div');
			wrap.className = 'flosc-da1-modal-field';
			var lbl = document.createElement('label');
			lbl.className = 'flosc-da1-modal-label';
			lbl.setAttribute('for', 'da1m-' + ci);
			lbl.textContent = col;
			wrap.appendChild(lbl);

			var input;
			if (MULTILINE.indexOf(ci) !== -1) {
				input = document.createElement('textarea');
				input.rows = 6;
				input.className = 'flosc-da1-modal-input flosc-da1-modal-textarea';
			} else {
				input = document.createElement('input');
				input.type = 'text';
				input.className = 'flosc-da1-modal-input';
			}
			input.id = 'da1m-' + ci;
			input.setAttribute('data-ci', String(ci));
			input.value = fields[ci] ? fields[ci].value : '';
			wrap.appendChild(input);

			if (col === 'Status') {
				var hint = document.createElement('p');
				hint.className = 'flosc-da1-modal-hint';
				hint.textContent = 'Allowed values: active, paused.';
				wrap.appendChild(hint);
			}
			body.appendChild(wrap);
		});

		overlay.style.display = 'flex';
	}

	function saveModal() {
		if (!activeRow) return;
		var fields = getRowFields(activeRow);
		body.querySelectorAll('[data-ci]').forEach(function (el) {
			var ci = parseInt(el.getAttribute('data-ci'), 10);
			if (fields[ci]) {
				fields[ci].value = el.value;
			}
		});
		closeModal();
	}

	function closeModal() {
		if (overlay) {
			overlay.style.display = 'none';
		}
		activeRow = null;
	}

	var btnAddEntry = document.getElementById('da1-add-entry-bottom');
	if (btnAddEntry) {
		btnAddEntry.addEventListener('click', addEntryRow);
	}

	document.getElementById('da1-tbody').addEventListener('click', function (e) {
		var row = e.target.closest('.da1-row');
		if (row) {
			document.querySelectorAll('#da1-tbody .da1-row.is-da1-active-row').forEach(function (r) {
				r.classList.remove('is-da1-active-row');
			});
			row.classList.add('is-da1-active-row');
		}
		var edit = e.target.closest('.da1-open-modal');
		if (edit) {
			openModal(edit.closest('.da1-row'));
			return;
		}
		var plus = e.target.closest('.da1-add-related');
		if (plus) {
			addRelatedRowFrom(plus.closest('.da1-row'));
		}
	});

	// Save: serialize the whole table into one JSON field and disable the
	// per-cell inputs, so the POST carries a few fields instead of thousands
	// (PHP max_input_vars). Works for a catalog of any size.
	Array.prototype.forEach.call(document.querySelectorAll('.da1-save-trigger'), function (btn) {
		btn.addEventListener('click', function () {
			var form = document.getElementById('da1-catalog-form');
			if (!form) return;
			var rows = [];
			document.querySelectorAll('#da1-tbody .da1-row').forEach(function (tr) {
				var cells = [];
				tr.querySelectorAll('[name^="da1_rows"]').forEach(function (f) { cells.push(f.value); });
				rows.push(cells);
			});
			var payload = form.querySelector('input[name="da1_payload"]');
			if (payload) { payload.value = JSON.stringify({ columns: COLUMNS, rows: rows }); }
			form.querySelectorAll('[name^="da1_rows"], [name^="da1_columns"]').forEach(function (el) { el.disabled = true; });
			form.submit();
		});
	});
})();
