/**
 * Flow Portability — single kit drop zone.
 * Stages files into #flosc-portability-file-list, then Create/Apply.
 */
(function () {
	'use strict';

	if (window.floscPortabilityBound) {
		return;
	}
	window.floscPortabilityBound = true;

	var MAX_TSV = 10;

	function onReady(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	function extOf(name) {
		var n = String(name || '').toLowerCase();
		var i = n.lastIndexOf('.');
		return i < 0 ? '' : n.slice(i + 1);
	}

	function toFiles(list) {
		if (!list || !list.length) {
			return [];
		}
		return Array.prototype.slice.call(list, 0);
	}

	function formatBytes(n) {
		n = parseInt(n, 10) || 0;
		if (n < 1024) {
			return n + ' B';
		}
		if (n < 1048576) {
			return (n / 1024).toFixed(1) + ' KB';
		}
		return (n / 1048576).toFixed(2) + ' MB';
	}

	function setInputFiles(input, files) {
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

		form.setAttribute('data-flosc-portability-ready', '1');

		/** @type {File[]} */
		var pending = [];
		var depth = 0;

		function setDrag(active) {
			zone.classList.toggle('is-dragover', !!active);
			if (titleIdle) {
				titleIdle.hidden = !!active;
			}
			if (titleDrag) {
				titleDrag.hidden = !active;
			}
			if (!active) {
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
			if (depth === 0) {
				zone.classList.toggle('is-has-file', n > 0);
			}

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

		function stage(fileList) {
			var files = toFiles(fileList);
			if (!files.length) {
				return;
			}

			var md = [];
			var tsv = [];
			var i;
			for (i = 0; i < files.length; i++) {
				var e = extOf(files[i].name);
				if (e === 'md') {
					md.push(files[i]);
				} else if (e === 'tsv') {
					tsv.push(files[i]);
				}
			}

			if (!md.length && !tsv.length) {
				window.alert('Use one .md (flow) and/or .tsv (DA1) catalog files.');
				return;
			}
			if (md.length > 1) {
				window.alert('Only one .md flow file per upload.');
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
			setInputFiles(input, pending);
			render();
		}

		function clear() {
			pending = [];
			try {
				input.value = '';
			} catch (err) { /* ignore */ }
			setInputFiles(input, []);
			render();
		}

		// Zone is the only interactive surface (input is clipped + pointer-events:none).
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

		zone.addEventListener('dragenter', function (e) {
			e.preventDefault();
			e.stopPropagation();
			depth += 1;
			if (depth === 1) {
				setDrag(true);
			}
		});

		zone.addEventListener('dragover', function (e) {
			e.preventDefault();
			e.stopPropagation();
			if (e.dataTransfer) {
				e.dataTransfer.dropEffect = 'copy';
			}
			if (depth < 1) {
				depth = 1;
				setDrag(true);
			}
		});

		zone.addEventListener('dragleave', function (e) {
			e.preventDefault();
			e.stopPropagation();
			// Leaving to a child still inside zone: keep highlight.
			var rel = e.relatedTarget;
			if (rel && zone.contains(rel)) {
				return;
			}
			depth = Math.max(0, depth - 1);
			if (depth === 0) {
				setDrag(false);
			}
		});

		zone.addEventListener('drop', function (e) {
			e.preventDefault();
			e.stopPropagation();
			depth = 0;
			setDrag(false);
			if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
				stage(e.dataTransfer.files);
			}
		});

		input.addEventListener('change', function () {
			if (input.files && input.files.length) {
				stage(input.files);
			}
		});

		if (input.files && input.files.length) {
			stage(input.files);
		}

		// Prevent browser open/download when files are dropped on this admin page.
		window.addEventListener('dragover', function (e) {
			e.preventDefault();
		});
		window.addEventListener('drop', function (e) {
			e.preventDefault();
		});
		window.addEventListener('dragend', function () {
			depth = 0;
			setDrag(false);
		});

		if (btnClear) {
			btnClear.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				clear();
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
				stage(input.files);
			}
			if (!pending.length) {
				e.preventDefault();
				window.alert('Select files first (drop or click the box), then Create or Apply.');
				return;
			}
			if (!setInputFiles(input, pending)) {
				e.preventDefault();
				window.alert('Could not attach files. Click the box and select again.');
				return;
			}
			var mdCount = 0;
			var j;
			for (j = 0; j < pending.length; j++) {
				if (extOf(pending[j].name) === 'md') {
					mdCount += 1;
				}
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
