/**
 * Flow Portability kit picker.
 * Guard: safe to load once via enqueue or inline on flosc-admin.
 */
(function () {
	'use strict';

	if (window.floscPortabilityBound) {
		return;
	}
	window.floscPortabilityBound = true;

	var MAX_TSV = 10;

	function boot() {
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
		var dragDepth = 0;

		function ext(name) {
			var n = String(name || '').toLowerCase();
			var i = n.lastIndexOf('.');
			return i < 0 ? '' : n.slice(i + 1);
		}

		function bytes(n) {
			n = parseInt(n, 10) || 0;
			if (n < 1024) {
				return n + ' B';
			}
			if (n < 1048576) {
				return (n / 1024).toFixed(1) + ' KB';
			}
			return (n / 1048576).toFixed(2) + ' MB';
		}

		function toArr(list) {
			if (!list || !list.length) {
				return [];
			}
			return Array.prototype.slice.call(list, 0);
		}

		function assignFiles(files) {
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
		 * High-contrast drag paint — class + inline (beats admin CSS cache).
		 * @param {boolean} on
		 */
		function paint(on) {
			if (on) {
				zone.classList.add('is-dragover');
				form.classList.add('is-dragover');
				zone.style.setProperty('background', '#4da3e0', 'important');
				zone.style.setProperty('background-color', '#4da3e0', 'important');
				zone.style.setProperty('border', '3px solid #063a5c', 'important');
				zone.style.setProperty('box-shadow', '0 0 0 5px rgba(6, 58, 92, 0.55)', 'important');
				if (titleIdle) {
					titleIdle.hidden = true;
				}
				if (titleDrag) {
					titleDrag.hidden = false;
				}
			} else {
				zone.classList.remove('is-dragover');
				form.classList.remove('is-dragover');
				zone.style.removeProperty('background');
				zone.style.removeProperty('background-color');
				zone.style.removeProperty('border');
				zone.style.removeProperty('box-shadow');
				if (titleIdle) {
					titleIdle.hidden = false;
				}
				if (titleDrag) {
					titleDrag.hidden = true;
				}
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
			if (dragDepth === 0) {
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
				meta.textContent = bytes(f.size);
				li.appendChild(code);
				li.appendChild(document.createTextNode(' '));
				li.appendChild(meta);
				listEl.appendChild(li);
			}
		}

		function stage(fileList) {
			var files = toArr(fileList);
			if (!files.length) {
				return;
			}
			var md = [];
			var tsv = [];
			var i;
			for (i = 0; i < files.length; i++) {
				var e = ext(files[i].name);
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
			assignFiles(pending);
			render();
		}

		function clear() {
			pending = [];
			try {
				input.value = '';
			} catch (err) { /* ignore */ }
			assignFiles([]);
			render();
		}

		function dragEnter(e) {
			e.preventDefault();
			e.stopPropagation();
			dragDepth += 1;
			if (dragDepth === 1) {
				paint(true);
			}
		}

		function dragOver(e) {
			e.preventDefault();
			e.stopPropagation();
			if (e.dataTransfer) {
				e.dataTransfer.dropEffect = 'copy';
			}
			if (dragDepth < 1) {
				dragDepth = 1;
				paint(true);
			}
		}

		function dragLeave(e) {
			e.preventDefault();
			e.stopPropagation();
			dragDepth = Math.max(0, dragDepth - 1);
			if (dragDepth === 0) {
				paint(false);
			}
		}

		function drop(e) {
			e.preventDefault();
			e.stopPropagation();
			dragDepth = 0;
			paint(false);
			var files = e.dataTransfer && e.dataTransfer.files;
			if (files && files.length) {
				stage(files);
			}
		}

		// Full-zone opacity-0 input is the hit target (covers the box). Bind only here
		// so dragDepth is not tripled by zone+form bubble duplicates.
		input.addEventListener('dragenter', dragEnter, false);
		input.addEventListener('dragover', dragOver, false);
		input.addEventListener('dragleave', dragLeave, false);
		input.addEventListener('drop', drop, false);

		// Drops on the staged-files panel (same form) also stage.
		form.addEventListener('dragover', function (e) {
			e.preventDefault();
		});
		form.addEventListener('drop', function (e) {
			if (e.target === input) {
				return;
			}
			drop(e);
		});

		// Block browser open/download for file drops on this admin page.
		window.addEventListener(
			'dragover',
			function (e) {
				e.preventDefault();
			},
			false
		);
		window.addEventListener(
			'drop',
			function (e) {
				e.preventDefault();
			},
			false
		);
		window.addEventListener(
			'dragend',
			function () {
				dragDepth = 0;
				paint(false);
			},
			false
		);

		input.addEventListener('change', function () {
			if (input.files && input.files.length) {
				stage(input.files);
			}
		});

		// Zone click is handled by the covering file input (opacity 0).
		// Keyboard still opens picker.
		zone.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' || e.key === ' ') {
				e.preventDefault();
				input.click();
			}
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
			if (!assignFiles(pending)) {
				e.preventDefault();
				window.alert('Could not attach files. Click the box and select again.');
				return;
			}
			var mdCount = 0;
			var tsvCount = 0;
			var j;
			for (j = 0; j < pending.length; j++) {
				var x = ext(pending[j].name);
				if (x === 'md') {
					mdCount += 1;
				}
				if (x === 'tsv') {
					tsvCount += 1;
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
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
