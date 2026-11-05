/**
 * Email tab: follow-up row repeater.
 *
 * Moved out of the PHP template so the page enqueues a static file; every
 * value that used to be printed into the script now arrives through
 * wp_localize_script().
 */
(function () {
	function addRow(prefix) {
		var table = document.querySelector('.flosc-fu-table[data-prefix="' + prefix + '"]');
		if (!table) { return; }
		var tbody = table.querySelector('.flosc-fu-body');
		var tr = document.createElement('tr');
		tr.className = 'flosc-fu-row';
		tr.innerHTML =
			'<td><input type="number" min="0" max="365" class="small-text" name="' + prefix + '_fu_day[]" value="0"></td>' +
			'<td><input type="text" name="' + prefix + '_fu_subject[]" value="" class="flosc-width-full"></td>' +
			'<td><textarea name="' + prefix + '_fu_body[]" rows="3" class="flosc-width-full"></textarea></td>' +
			'<td class="flosc-text-center"><button type="button" class="button flosc-fu-remove" title="Remove">&times;</button></td>';
		tbody.appendChild(tr);
	}
	document.addEventListener('click', function (e) {
		if (e.target && e.target.classList.contains('flosc-fu-add')) {
			e.preventDefault();
			addRow(e.target.getAttribute('data-prefix'));
		} else if (e.target && e.target.classList.contains('flosc-fu-remove')) {
			e.preventDefault();
			var row = e.target.closest('.flosc-fu-row');
			if (row) { row.parentNode.removeChild(row); }
		}
	});
})();
