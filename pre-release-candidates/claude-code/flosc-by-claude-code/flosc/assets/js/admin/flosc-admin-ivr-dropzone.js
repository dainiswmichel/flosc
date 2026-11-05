/**
 * FLOSC Settings, IVR Management (All Flows view): drag-and-drop for the IVR
 * markdown upload form.
 *
 * Enqueued by admin/ivr-messages.php only in the All Flows view.
 *
 * @package FLOSC
 */
(function () {
	var form = document.getElementById('flosc-ivr-upload-form');
	var zone = document.getElementById('flosc-ivr-dropzone');
	var input = document.getElementById('flosc-ivr-file-input');
	var nameEl = document.getElementById('flosc-ivr-dropzone-filename');
	if (!form || !zone || !input) {
		return;
	}
	function setFile(file) {
		if (!file) {
			return;
		}
		var lower = (file.name || '').toLowerCase();
		if (lower.slice(-3) !== '.md') {
			window.alert('Only .md IVR files are allowed.');
			return;
		}
		try {
			var dt = new DataTransfer();
			dt.items.add(file);
			input.files = dt.files;
		} catch (e) {
			// Older browsers: user must use the file input.
		}
		if (nameEl) {
			nameEl.hidden = false;
			nameEl.textContent = file.name;
		}
		zone.classList.add('is-has-file');
	}
	function onDrag(e) {
		e.preventDefault();
		e.stopPropagation();
	}
	['dragenter', 'dragover'].forEach(function (ev) {
		zone.addEventListener(ev, function (e) {
			onDrag(e);
			zone.classList.add('is-dragover');
		});
	});
	['dragleave', 'drop'].forEach(function (ev) {
		zone.addEventListener(ev, function (e) {
			onDrag(e);
			zone.classList.remove('is-dragover');
		});
	});
	zone.addEventListener('drop', function (e) {
		var files = e.dataTransfer && e.dataTransfer.files;
		if (files && files.length) {
			setFile(files[0]);
		}
	});
	zone.addEventListener('click', function () {
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
		if (input.files && input.files[0]) {
			setFile(input.files[0]);
		}
	});
})();
