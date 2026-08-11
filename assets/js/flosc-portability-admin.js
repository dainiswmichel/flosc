/**
 * Flow Portability kit picker: drop/choose → list → Create/Apply.
 * pendingFiles is source of truth; input.files synced for multipart POST.
 *
 * Drag UX: hit-test zone/form via getBoundingClientRect (not dragleave), so
 * highlight stays solid while the OS file drag is over the drop target.
 * Capture preventDefault so Chromium does not open/download dropped files.
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

	/**
	 * @param {DragEvent|Event} e
	 * @return {boolean}
	 */
	function isFileDrag(e) {
		var dt = e.dataTransfer;
		if (!dt) {
			return false;
		}
		// types empty on some dragenter frames in Safari — treat as possible file drag
		if (!dt.types || !dt.types.length) {
			return true;
		}
		var types = dt.types;
		if (typeof types.includes === 'function') {
			return types.includes('Files') || types.includes('application/x-moz-file');
		}
		if (typeof types.contains === 'function') {
			return types.contains('Files') || types.contains('application/x-moz-file');
		}
		var i;
		for (i = 0; i < types.length; i++) {
			if (types[i] === 'Files' || types[i] === 'application/x-moz-file') {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param {FileList|File[]|null|undefined} fileList
	 * @return {File[]}
	 */
	function toFileArray(fileList) {
		if (!fileList || !fileList.length) {
			return [];
		}
		var out = [];
		var i;
		for (i = 0; i < fileList.length; i++) {
			out.push(fileList[i]);
		}
		return out;
	}

	/**
	 * @param {HTMLElement} el
	 * @param {number} x
	 * @param {number} y
	 * @return {boolean}
	 */
	function pointInElement(el, x, y) {
		if (!el || x == null || y == null) {
			return false;
		}
		var r = el.getBoundingClientRect();
		return x >= r.left && x <= r.right && y >= r.top && y <= r.bottom;
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
		var titleIdle = zone ? zone.querySelector('.flosc-ivr-dropzone__title--idle') : null;
		var titleDrag = zone ? zone.querySelector('.flosc-ivr-dropzone__title--drag') : null;

		if (!form || !zone || !input || !listEl) {
			return;
		}

		/** @type {File[]} */
		var pendingFiles = [];
		var maxTsv = 10;
		var dragActive = false;
		var overZone = false;

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
			zone.classList.toggle('is-has-file', n > 0 && !overZone);
			var i;
			for (i = 0; i < n; i++) {
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
		 * @return {boolean}
		 */
		function syncInputFromPending() {
			if (typeof DataTransfer === 'undefined') {
				return !!(input.files && input.files.length === pendingFiles.length && pendingFiles.length > 0);
			}
			try {
				var dt = new DataTransfer();
				var i;
				for (i = 0; i < pendingFiles.length; i++) {
					dt.items.add(pendingFiles[i]);
				}
				input.files = dt.files;
				return !!(input.files && input.files.length === pendingFiles.length);
			} catch (err) {
				return !!(input.files && input.files.length > 0 && pendingFiles.length > 0);
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
		 * @param {FileList|File[]|null|undefined} fileList
		 */
		function acceptFiles(fileList) {
			var files = toFileArray(fileList);
			if (!files.length) {
				return;
			}
			var mdFiles = [];
			var tsvFiles = [];
			var i;
			for (i = 0; i < files.length; i++) {
				var lower = (files[i].name || '').toLowerCase();
				if (lower.slice(-3) === '.md') {
					mdFiles.push(files[i]);
				} else if (lower.slice(-4) === '.tsv') {
					tsvFiles.push(files[i]);
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
			pendingFiles = mdFiles.concat(tsvFiles);
			renderList();
			syncInputFromPending();
		}

		/**
		 * @param {boolean} active page-level file drag
		 * @param {boolean} zoneHit pointer over drop target
		 */
		function setDragUi(active, zoneHit) {
			dragActive = !!active;
			overZone = !!zoneHit;
			form.classList.toggle('is-file-dragging', dragActive);
			zone.classList.toggle('is-dragover', overZone);
			document.documentElement.classList.toggle('flosc-portability-dragging', dragActive);
			if (titleIdle && titleDrag) {
				titleIdle.hidden = overZone;
				titleDrag.hidden = !overZone;
			}
			if (!overZone && pendingFiles.length) {
				zone.classList.add('is-has-file');
			}
		}

		function endDragUi() {
			setDragUi(false, false);
		}

		/**
		 * Drop target = zone (and the staged-files panel under it for larger hit area).
		 * @param {DragEvent} e
		 * @return {boolean}
		 */
		function isOverDropTarget(e) {
			var x = e.clientX;
			var y = e.clientY;
			if (pointInElement(zone, x, y)) {
				return true;
			}
			var panel = document.getElementById('flosc-portability-file-panel');
			if (panel && pointInElement(panel, x, y)) {
				return true;
			}
			return false;
		}

		/**
		 * @param {DragEvent} e
		 */
		function takeDroppedFiles(e) {
			e.preventDefault();
			if (typeof e.stopPropagation === 'function') {
				e.stopPropagation();
			}
			var files = e.dataTransfer && e.dataTransfer.files;
			endDragUi();
			if (files && files.length) {
				acceptFiles(files);
			}
		}

		document.addEventListener(
			'dragenter',
			function (e) {
				if (!isFileDrag(e)) {
					return;
				}
				e.preventDefault();
				setDragUi(true, isOverDropTarget(e));
			},
			true
		);

		document.addEventListener(
			'dragover',
			function (e) {
				if (!isFileDrag(e)) {
					return;
				}
				var hit = isOverDropTarget(e);
				// Always preventDefault over our form so drop can fire; also over zone/panel.
				if (hit || pointInElement(form, e.clientX, e.clientY)) {
					e.preventDefault();
					if (e.dataTransfer) {
						e.dataTransfer.dropEffect = 'copy';
					}
				}
				setDragUi(true, hit);
			},
			true
		);

		document.addEventListener(
			'drop',
			function (e) {
				if (!isFileDrag(e)) {
					endDragUi();
					return;
				}
				if (isOverDropTarget(e) || pointInElement(form, e.clientX, e.clientY)) {
					takeDroppedFiles(e);
					return;
				}
				endDragUi();
			},
			true
		);

		document.addEventListener('dragend', endDragUi, true);
		window.addEventListener('blur', endDragUi);

		// If drag leaves the window entirely.
		document.addEventListener(
			'dragleave',
			function (e) {
				if (!isFileDrag(e)) {
					return;
				}
				// relatedTarget null + leaving document → clear
				if (!e.relatedTarget || e.relatedTarget === document.documentElement || e.relatedTarget === document.body) {
					if (e.clientX <= 0 || e.clientY <= 0 || e.clientX >= window.innerWidth || e.clientY >= window.innerHeight) {
						endDragUi();
					}
				}
			},
			true
		);

		if (btnClear) {
			btnClear.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				clearFiles();
			});
		}

		input.addEventListener('change', function () {
			if (input.files && input.files.length) {
				acceptFiles(input.files);
			}
		});

		zone.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' || e.key === ' ') {
				e.preventDefault();
				input.click();
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

			if (!pendingFiles.length && input.files && input.files.length) {
				acceptFiles(input.files);
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
