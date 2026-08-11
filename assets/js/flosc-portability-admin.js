/**
 * Flow Portability — kit file picker (list-then-submit).
 *
 * DnD notes (Chromium + OS file drag):
 * - Always preventDefault on dragover (zone + window) or the browser opens files.
 * - Do not gate highlight on dataTransfer.types === "Files" (often empty on early frames).
 * - dragover continuously sets over-state; leave when pointer exits zone rect.
 *
 * @package FLOSC
 */
(function () {
	'use strict';

	var MAX_TSV = 10;

	/** Inline styles so drag state cannot lose to admin CSS cascade. */
	var STYLE_IDLE = {
		backgroundColor: '',
		borderColor: '',
		borderStyle: '',
		borderWidth: '',
		boxShadow: '',
		color: '',
		transform: ''
	};
	var STYLE_OVER = {
		backgroundColor: '#8ec8f0',
		borderColor: '#0a4b78',
		borderStyle: 'solid',
		borderWidth: '3px',
		boxShadow: '0 0 0 4px rgba(10, 75, 120, 0.45)',
		color: '#0a4b78',
		transform: 'scale(1.01)'
	};

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
	function looksLikeFileDrag(dt) {
		if (!dt) {
			return false;
		}
		// During OS→browser drag, types is often empty on first events — still a file drag.
		if (!dt.types || !dt.types.length) {
			return true;
		}
		var types = dt.types;
		var i;
		for (i = 0; i < types.length; i++) {
			var t = types[i];
			if (
				t === 'Files' ||
				t === 'application/x-moz-file' ||
				t === 'public.file-url' ||
				t === 'text/uri-list'
			) {
				return true;
			}
		}
		// Non-file drags (e.g. text) usually have text/plain / text/html only.
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

	/**
	 * @param {HTMLElement} el
	 * @param {number} x
	 * @param {number} y
	 * @return {boolean}
	 */
	function pointIn(el, x, y) {
		if (!el || x == null || y == null || Number.isNaN(x) || Number.isNaN(y)) {
			return false;
		}
		var r = el.getBoundingClientRect();
		return x >= r.left && x <= r.right && y >= r.top && y <= r.bottom;
	}

	/**
	 * @param {HTMLElement} el
	 * @param {Object} styles
	 */
	function applyStyles(el, styles) {
		var k;
		for (k in styles) {
			if (Object.prototype.hasOwnProperty.call(styles, k)) {
				el.style[k] = styles[k];
			}
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

		form.setAttribute('data-flosc-portability-ready', '1');

		/** @type {File[]} */
		var pending = [];
		var isOver = false;

		/**
		 * @param {boolean} over
		 */
		function setOver(over) {
			over = !!over;
			if (over === isOver) {
				return;
			}
			isOver = over;
			zone.classList.toggle('is-dragover', over);
			form.classList.toggle('is-dragover', over);
			applyStyles(zone, over ? STYLE_OVER : STYLE_IDLE);
			if (titleIdle && titleDrag) {
				titleIdle.hidden = over;
				titleDrag.hidden = !over;
			}
			if (!over) {
				zone.classList.toggle('is-has-file', pending.length > 0);
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
			zone.classList.toggle('is-has-file', n > 0 && !isOver);

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

		/* Window: stop browser from opening/downloading dropped files anywhere on the page. */
		window.addEventListener(
			'dragover',
			function (e) {
				if (looksLikeFileDrag(e.dataTransfer)) {
					e.preventDefault();
					if (e.dataTransfer) {
						e.dataTransfer.dropEffect = pointIn(zone, e.clientX, e.clientY) ? 'copy' : 'none';
					}
					// Drive highlight from coordinates (stable vs enter/leave).
					setOver(pointIn(zone, e.clientX, e.clientY));
				}
			},
			false
		);

		window.addEventListener(
			'drop',
			function (e) {
				if (!looksLikeFileDrag(e.dataTransfer)) {
					setOver(false);
					return;
				}
				e.preventDefault();
				if (pointIn(zone, e.clientX, e.clientY) || pointIn(form, e.clientX, e.clientY)) {
					var files = e.dataTransfer && e.dataTransfer.files;
					setOver(false);
					if (files && files.length) {
						stageFiles(files);
					}
					return;
				}
				setOver(false);
			},
			false
		);

		window.addEventListener('dragend', function () {
			setOver(false);
		});

		window.addEventListener('blur', function () {
			setOver(false);
		});

		/* Zone handlers (backup path if window listener is delayed). */
		zone.addEventListener('dragenter', function (e) {
			e.preventDefault();
			setOver(true);
		});

		zone.addEventListener('dragover', function (e) {
			e.preventDefault();
			e.stopPropagation();
			if (e.dataTransfer) {
				e.dataTransfer.dropEffect = 'copy';
			}
			setOver(true);
		});

		zone.addEventListener('dragleave', function (e) {
			// Leave only when pointer exits the zone box.
			if (!pointIn(zone, e.clientX, e.clientY)) {
				setOver(false);
			}
		});

		zone.addEventListener('drop', function (e) {
			e.preventDefault();
			e.stopPropagation();
			setOver(false);
			if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
				stageFiles(e.dataTransfer.files);
			}
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
