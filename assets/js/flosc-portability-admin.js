/**
 * Flow Portability — kit file picker (list-then-submit).
 *
 * Industry pattern:
 * - Window-level preventDefault on file dragover/drop (stop browser open/download)
 * - dragenter/dragleave depth counter for stable is-dragover (HTML5 DnD)
 * - Visually hidden <input type="file">; zone click opens picker
 * - pendingFiles is UI source of truth; DataTransfer assigns input.files on submit
 *
 * @package FLOSC
 */
(function () {
	'use strict';

	var MAX_TSV = 10;

	/**
	 * @param {function(): void} fn
	 */
	function onReady(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	/**
	 * @param {DataTransfer|null|undefined} dt
	 * @return {boolean}
	 */
	function dataTransferHasFiles(dt) {
		if (!dt || !dt.types) {
			return false;
		}
		var types = dt.types;
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
	 * @param {string} name
	 * @return {string}
	 */
	function extensionOf(name) {
		var n = String(name || '').toLowerCase();
		var dot = n.lastIndexOf('.');
		return dot === -1 ? '' : n.slice(dot + 1);
	}

	/**
	 * @param {FileList|File[]|null|undefined} list
	 * @return {File[]}
	 */
	function fileArray(list) {
		if (!list || !list.length) {
			return [];
		}
		return Array.prototype.slice.call(list, 0);
	}

	/**
	 * @param {number} bytes
	 * @return {string}
	 */
	function formatBytes(bytes) {
		var n = parseInt(bytes, 10) || 0;
		if (n < 1024) {
			return n + ' B';
		}
		if (n < 1048576) {
			return (n / 1024).toFixed(1) + ' KB';
		}
		return (n / 1048576).toFixed(2) + ' MB';
	}

	/**
	 * Assign File[] onto an <input type="file"> for multipart POST.
	 * @param {HTMLInputElement} input
	 * @param {File[]} files
	 * @return {boolean}
	 */
	function assignInputFiles(input, files) {
		if (typeof DataTransfer === 'undefined') {
			return !!(input.files && input.files.length === files.length);
		}
		try {
			var dt = new DataTransfer();
			var i;
			for (i = 0; i < files.length; i++) {
				dt.items.add(files[i]);
			}
			input.files = dt.files;
			return input.files.length === files.length;
		} catch (err) {
			return false;
		}
	}

	onReady(function () {
		var form = document.getElementById('flosc-ivr-dropzone-upload-form');
		var zone = document.getElementById('flosc-ivr-dropzone');
		var input = document.getElementById('flosc-ivr-file-input');
		var listEl = document.getElementById('flosc-portability-file-list');
		var countEl = document.getElementById('flosc-portability-file-count');
		var btnClear = document.getElementById('flosc-portability-clear-files');
		var btnCreate = document.getElementById('flosc-portability-btn-create');
		var btnApply = document.getElementById('flosc-portability-btn-apply');
		var titleIdle = document.getElementById('flosc-dropzone-title-idle');
		var titleDrag = document.getElementById('flosc-dropzone-title-drag');

		if (!form || !zone || !input || !listEl) {
			return;
		}

		/** @type {File[]} */
		var pending = [];
		/** Nested dragenter count on zone (standard anti-flicker technique). */
		var dragDepth = 0;

		/**
		 * @param {boolean} over
		 */
		function setOver(over) {
			zone.classList.toggle('is-dragover', over);
			form.classList.toggle('is-dragover', over);
			if (titleIdle && titleDrag) {
				titleIdle.hidden = over;
				titleDrag.hidden = !over;
			}
		}

		function render() {
			listEl.textContent = '';
			var n = pending.length;
			if (countEl) {
				countEl.textContent = n ? n + (n === 1 ? ' file' : ' files') : 'None selected';
			}
			if (btnClear) {
				btnClear.disabled = n === 0;
			}
			zone.classList.toggle('is-has-file', n > 0 && dragDepth === 0);

			var i;
			for (i = 0; i < n; i++) {
				var f = pending[i];
				var li = document.createElement('li');
				li.className = 'flosc-portability-file-list__item';

				var code = document.createElement('code');
				code.textContent = f.name || '(unnamed)';

				var meta = document.createElement('span');
				meta.className = 'description';
				meta.textContent = formatBytes(f.size);

				li.appendChild(code);
				li.appendChild(document.createTextNode(' '));
				li.appendChild(meta);
				listEl.appendChild(li);
			}
		}

		function clearPending() {
			pending = [];
			try {
				input.value = '';
			} catch (e) { /* ignore */ }
			assignInputFiles(input, []);
			render();
		}

		/**
		 * Validate and stage files (replace current selection).
		 * @param {FileList|File[]} fileList
		 */
		function stageFiles(fileList) {
			var files = fileArray(fileList);
			if (!files.length) {
				return;
			}

			var md = [];
			var tsv = [];
			var i;
			for (i = 0; i < files.length; i++) {
				var ext = extensionOf(files[i].name);
				if (ext === 'md') {
					md.push(files[i]);
				} else if (ext === 'tsv') {
					tsv.push(files[i]);
				}
			}

			if (!md.length && !tsv.length) {
				window.alert('Use one .md (flow) and/or .tsv (DA1) catalog files.');
				return;
			}
			if (md.length > 1) {
				window.alert('Only one .md flow file per upload — never two .md files.');
				return;
			}
			if (tsv.length > MAX_TSV) {
				window.alert('At most ' + MAX_TSV + ' DA1 .tsv catalogs per upload.');
				return;
			}
			if (tsv.length > 5 && !window.confirm('Select ' + tsv.length + ' DA1 catalogs?')) {
				return;
			}

			pending = md.concat(tsv);
			assignInputFiles(input, pending);
			render();
		}

		/* —— Window: never let the browser navigate/download on file drop —— */
		window.addEventListener(
			'dragover',
			function (e) {
				if (dataTransferHasFiles(e.dataTransfer)) {
					e.preventDefault();
				}
			},
			false
		);
		window.addEventListener(
			'drop',
			function (e) {
				if (dataTransferHasFiles(e.dataTransfer)) {
					e.preventDefault();
				}
			},
			false
		);

		/* —— Zone: drag depth counter (enter/leave child elements) —— */
		zone.addEventListener('dragenter', function (e) {
			if (!dataTransferHasFiles(e.dataTransfer)) {
				return;
			}
			e.preventDefault();
			dragDepth += 1;
			if (dragDepth === 1) {
				setOver(true);
			}
		});

		zone.addEventListener('dragleave', function (e) {
			if (!dataTransferHasFiles(e.dataTransfer)) {
				return;
			}
			e.preventDefault();
			dragDepth = Math.max(0, dragDepth - 1);
			if (dragDepth === 0) {
				setOver(false);
			}
		});

		zone.addEventListener('dragover', function (e) {
			if (!dataTransferHasFiles(e.dataTransfer)) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			if (e.dataTransfer) {
				e.dataTransfer.dropEffect = 'copy';
			}
			if (dragDepth === 0) {
				dragDepth = 1;
				setOver(true);
			}
		});

		zone.addEventListener('drop', function (e) {
			if (!dataTransferHasFiles(e.dataTransfer)) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			dragDepth = 0;
			setOver(false);
			if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
				stageFiles(e.dataTransfer.files);
			}
		});

		/* Reset if OS cancels the drag */
		window.addEventListener('dragend', function () {
			dragDepth = 0;
			setOver(false);
		});

		/* Click / keyboard → native file picker */
		zone.addEventListener('click', function (e) {
			if (e.target === input) {
				return;
			}
			e.preventDefault();
			input.click();
		});

		zone.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' || e.key === ' ') {
				e.preventDefault();
				input.click();
			}
		});

		input.addEventListener('click', function (e) {
			e.stopPropagation();
		});

		input.addEventListener('change', function () {
			if (input.files && input.files.length) {
				stageFiles(input.files);
			}
		});

		if (btnClear) {
			btnClear.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				clearPending();
			});
		}

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

			if (!pending.length && input.files && input.files.length) {
				stageFiles(input.files);
			}

			if (!pending.length) {
				e.preventDefault();
				window.alert('Select files first (drop or click the box), then Create or Apply.');
				return;
			}

			if (!assignInputFiles(input, pending)) {
				e.preventDefault();
				window.alert('Could not attach files to the upload field. Click the box to select files again, then Create.');
				return;
			}

			var mdCount = 0;
			var tsvCount = 0;
			var j;
			for (j = 0; j < pending.length; j++) {
				var ext = extensionOf(pending[j].name);
				if (ext === 'md') {
					mdCount += 1;
				}
				if (ext === 'tsv') {
					tsvCount += 1;
				}
			}

			if (mdCount > 1) {
				e.preventDefault();
				window.alert('Only one .md flow file per upload.');
				return;
			}
			if (tsvCount > MAX_TSV) {
				e.preventDefault();
				window.alert('At most ' + MAX_TSV + ' DA1 .tsv catalogs per upload.');
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

		render();
	});
})();
