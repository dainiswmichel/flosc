/**
 * Flow Portability kit picker: drop/choose → list → Create/Apply submit.
 * pendingFiles is source of truth; input.files is synced only for POST.
 */
(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	ready(function () {
		var form = document.getElementById('flosc-ivr-dropzone-upload-form');
		var zone = document.getElementById('flosc-ivr-dropzone');
		var input = document.getElementById('flosc-ivr-file-input');
		var listEl = document.getElementById('flosc-portability-file-list');
		var countEl = document.getElementById('flosc-portability-file-count');
		var btnClear = document.getElementById('flosc-portability-clear-files');
		var btnCreate = document.getElementById('flosc-portability-btn-create');
		var btnApply = document.getElementById('flosc-portability-btn-apply');

		if (!form || !zone || !input || !listEl) {
			return;
		}

		/** @type {File[]} */
		var pendingFiles = [];
		var maxTsv = 10;

		function formatSize(bytes) {
			var n = parseInt(bytes, 10) || 0;
			if (n < 1024) {
				return n + ' B';
			}
			if (n < 1024 * 1024) {
				return (n / 1024).toFixed(1) + ' KB';
			}
			return (n / (1024 * 1024)).toFixed(2) + ' MB';
		}

		function renderList() {
			listEl.innerHTML = '';
			var n = pendingFiles.length;
			if (countEl) {
				countEl.textContent = n ? n + (n === 1 ? ' file' : ' files') : 'None selected';
			}
			if (btnClear) {
				btnClear.disabled = n === 0;
			}
			zone.classList.toggle('is-has-file', n > 0);
			for (var i = 0; i < n; i++) {
				var f = pendingFiles[i];
				var li = document.createElement('li');
				li.className = 'flosc-portability-file-list__item';
				var name = document.createElement('code');
				name.textContent = f.name || '(unnamed)';
				var meta = document.createElement('span');
				meta.className = 'description';
				meta.textContent = formatSize(f.size);
				li.appendChild(name);
				li.appendChild(document.createTextNode(' '));
				li.appendChild(meta);
				listEl.appendChild(li);
			}
		}

		/**
		 * Copy pendingFiles onto the file input for multipart POST.
		 * @return {boolean}
		 */
		function syncInputFromPending() {
			if (typeof DataTransfer === 'undefined') {
				return false;
			}
			try {
				var dt = new DataTransfer();
				for (var i = 0; i < pendingFiles.length; i++) {
					dt.items.add(pendingFiles[i]);
				}
				input.files = dt.files;
				return input.files && input.files.length === pendingFiles.length;
			} catch (err) {
				return false;
			}
		}

		function clearFiles() {
			pendingFiles = [];
			try {
				input.value = '';
			} catch (e1) { /* ignore */ }
			if (typeof DataTransfer !== 'undefined') {
				try {
					input.files = new DataTransfer().files;
				} catch (e2) { /* ignore */ }
			}
			renderList();
		}

		/**
		 * @param {FileList|File[]} fileList
		 */
		function acceptFiles(fileList) {
			if (!fileList || !fileList.length) {
				return;
			}
			var mdFiles = [];
			var tsvFiles = [];
			var i;
			for (i = 0; i < fileList.length; i++) {
				var lower = (fileList[i].name || '').toLowerCase();
				if (lower.slice(-3) === '.md') {
					mdFiles.push(fileList[i]);
				} else if (lower.slice(-4) === '.tsv') {
					tsvFiles.push(fileList[i]);
				}
			}
			if (!mdFiles.length && !tsvFiles.length) {
				window.alert('Use one .md (flow) and/or .tsv (DA1) catalog files.');
				return;
			}
			if (mdFiles.length > 1) {
				window.alert('Only one .md flow file per upload — never two .md files.');
				return;
			}
			if (tsvFiles.length > maxTsv) {
				window.alert('At most ' + maxTsv + ' DA1 .tsv catalogs per upload.');
				return;
			}
			if (tsvFiles.length > 5 && !window.confirm('Select ' + tsvFiles.length + ' DA1 catalogs?')) {
				return;
			}
			// Replace selection with validated set (standard picker UX).
			pendingFiles = mdFiles.concat(tsvFiles);
			renderList();
			// Best-effort early sync (required again on submit).
			syncInputFromPending();
		}

		function preventDrag(e) {
			e.preventDefault();
			e.stopPropagation();
		}

		['dragenter', 'dragover'].forEach(function (ev) {
			zone.addEventListener(ev, function (e) {
				preventDrag(e);
				if (e.dataTransfer) {
					e.dataTransfer.dropEffect = 'copy';
				}
				zone.classList.add('is-dragover');
			});
		});

		zone.addEventListener('dragleave', function (e) {
			preventDrag(e);
			// Only clear highlight when leaving the zone itself.
			if (e.target === zone) {
				zone.classList.remove('is-dragover');
			}
		});

		zone.addEventListener('drop', function (e) {
			preventDrag(e);
			zone.classList.remove('is-dragover');
			var files = e.dataTransfer && e.dataTransfer.files;
			if (files && files.length) {
				acceptFiles(files);
			}
		});

		// Also accept drops on the whole form panel (list area).
		form.addEventListener('dragover', preventDrag);
		form.addEventListener('drop', function (e) {
			if (e.target === input) {
				return;
			}
			preventDrag(e);
			zone.classList.remove('is-dragover');
			var files = e.dataTransfer && e.dataTransfer.files;
			if (files && files.length) {
				acceptFiles(files);
			}
		});

		if (btnClear) {
			btnClear.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				clearFiles();
			});
		}

		// One control: click zone → file picker; drop on zone → same list.
		zone.addEventListener('click', function () {
			input.click();
		});
		zone.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' || e.key === ' ') {
				e.preventDefault();
				input.click();
			}
		});

		input.addEventListener('change', function () {
			if (input.files && input.files.length) {
				acceptFiles(input.files);
			}
		});

		form.addEventListener('submit', function (e) {
			var submitter = e.submitter || document.activeElement;
			var act = submitter && submitter.value ? String(submitter.value) : 'create';
			if (act !== 'create' && act !== 'apply') {
				act = 'create';
			}
			if (act === 'apply' && btnApply && btnApply.disabled) {
				e.preventDefault();
				window.alert('Select a current flow in Switch Flow first.');
				return;
			}

			if (!pendingFiles.length) {
				e.preventDefault();
				window.alert('Select files first (drop or click the box), then Create or Apply.');
				return;
			}

			if (!syncInputFromPending()) {
				e.preventDefault();
				window.alert('Could not attach files to the upload field. Click the box to select files again, then Create.');
				return;
			}

			var mdCount = 0;
			var tsvCount = 0;
			var j;
			for (j = 0; j < pendingFiles.length; j++) {
				var nm = (pendingFiles[j].name || '').toLowerCase();
				if (nm.slice(-3) === '.md') {
					mdCount++;
				}
				if (nm.slice(-4) === '.tsv') {
					tsvCount++;
				}
			}
			if (mdCount > 1) {
				e.preventDefault();
				window.alert('Only one .md flow file per upload.');
				return;
			}
			if (tsvCount > maxTsv) {
				e.preventDefault();
				window.alert('At most ' + maxTsv + ' DA1 .tsv catalogs per upload.');
				return;
			}
			if (tsvCount > 5 && !window.confirm('Upload ' + tsvCount + ' DA1 catalogs?')) {
				e.preventDefault();
				return;
			}
			if (act === 'create' && mdCount < 1) {
				e.preventDefault();
				window.alert('Create new flow needs one .md file (optional .tsv with it).');
				return;
			}
			if (btnCreate) {
				btnCreate.disabled = true;
			}
			if (btnApply) {
				btnApply.disabled = true;
			}
		});

		document.addEventListener('submit', function (event) {
			var formEl = event.target.closest('form[data-confirm-message]');
			if (!formEl) {
				return;
			}
			if (!window.confirm(formEl.dataset.confirmMessage || 'Are you sure?')) {
				event.preventDefault();
				event.stopPropagation();
			}
		});

		renderList();
	});
})();
