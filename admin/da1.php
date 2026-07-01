<?php
/**
 * FLOSC Admin — DA1 Tab
 * Works Catalog TSV Editor
 * 2026y-04m-09d
 *
 * Reads and writes /wp-content/uploads/dainis-w-michel-list-of-works.tsv
 * Columns are read from the TSV header row — no hardcoded schema.
 *
 * - Click any cell to edit inline
 * - Lyrics, Media, Video columns get textarea (multi-line)
 * - Sorted by Date descending (michel innovation timestamps)
 * - Save rewrites the TSV; page is live immediately
 */

if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) return;

$flosc_tsv_filename = 'dainis-w-michel-list-of-works.tsv';
$flosc_upload_dir   = wp_upload_dir();
$flosc_tsv_path     = $flosc_upload_dir['basedir'] . '/' . $flosc_tsv_filename;

$flosc_notice_success = '';
$flosc_notice_error   = '';

/* ----------------------------------------------------------------- Parser */
function flosc_da1_parse_tsv($flosc_content) {
    $flosc_parsed = [];
    $flosc_row    = [];
    $field  = '';
    $in_q   = false;
    $len    = strlen($flosc_content);

    for ($i = 0; $i < $len; $i++) {
        $ch = $flosc_content[$i];
        if ($in_q) {
            if ($ch === '"') {
                if ($i + 1 < $len && $flosc_content[$i + 1] === '"') { $field .= '"'; $i++; }
                else { $in_q = false; }
            } else { $field .= $ch; }
        } elseif ($ch === '"')  { $in_q = true; }
        elseif ($ch === "\t")  { $flosc_row[] = $field; $field = ''; }
        elseif ($ch === "\n")  { $flosc_row[] = $field; $field = ''; if (!empty($flosc_row)) { $flosc_parsed[] = $flosc_row; } $flosc_row = []; }
        elseif ($ch !== "\r")  { $field .= $ch; }
    }
    if ($field !== '' || !empty($flosc_row)) { $flosc_row[] = $field; $flosc_parsed[] = $flosc_row; }
    return $flosc_parsed;
}

function flosc_da1_tsv_cell($flosc_val) {
    $flosc_val = str_replace(["\r\n", "\r"], "\n", (string) $flosc_val);
    if (strpos($flosc_val, "\t") !== false || strpos($flosc_val, "\n") !== false || strpos($flosc_val, '"') !== false) {
        $flosc_val = '"' . str_replace('"', '""', $flosc_val) . '"';
    }
    return $flosc_val;
}

/* ------------------------------------------------------------------ Read */
$flosc_columns = [];
$flosc_rows    = [];

if (file_exists($flosc_tsv_path)) {
    $flosc_parsed = flosc_da1_parse_tsv(file_get_contents($flosc_tsv_path));

    /* First row is the header — use it as column definitions */
    $flosc_columns = array_map(function($c) {
        $c = trim($c);
        return ($c === 'Video') ? 'Media' : $c;
    }, $flosc_parsed[0] ?? []);

    $flosc_rows = array_slice($flosc_parsed, 1);
    usort($flosc_rows, function ($a, $b) {
        return (int) ($b[0] ?? 0) - (int) ($a[0] ?? 0);
    });
}

/* Fallback if file missing or empty */
if (empty($flosc_columns)) {
    $flosc_columns = ['Date', 'Title', 'Description', 'Lyrics', 'Media', 'Notes'];
}
if (empty($flosc_rows)) {
    $flosc_rows = [array_fill(0, count($flosc_columns), '')];
}

/* Pad all rows to column count */
$flosc_ncols = count($flosc_columns);
foreach ($flosc_rows as &$flosc_row) {
    while (count($flosc_row) < $flosc_ncols) $flosc_row[] = '';
}
unset($flosc_row);

/* Textarea columns: any column whose name contains Lyrics, Media, or Video */
$flosc_multiline_idx = [];
foreach ($flosc_columns as $flosc_ci => $flosc_col) {
    if (preg_match('/description|lyrics|media|video|notes/i', $flosc_col)) $flosc_multiline_idx[] = $flosc_ci;
}

/* ------------------------------------------------------------------ Save */
if (isset($_POST['da1_save_catalog'])) {
    $post = wp_unslash($_POST);
    check_admin_referer('da1_catalog_save');

    /* Columns come from hidden fields so we preserve the schema on save */
    $flosc_saved_columns = $post['da1_columns'] ?? $flosc_columns;
    $flosc_rows_post     = $post['da1_rows'] ?? [];
    $flosc_lines         = [];
    $flosc_lines[]       = implode("\t", array_map('flosc_da1_tsv_cell', $flosc_saved_columns));

    foreach ($flosc_rows_post as $flosc_row) {
        $flosc_cells     = [];
        $flosc_all_empty = true;
        foreach ($flosc_saved_columns as $flosc_ci => $flosc_col) {
            $flosc_raw = isset($flosc_row[$flosc_ci]) ? (string) $flosc_row[$flosc_ci] : '';
            $flosc_val = in_array($flosc_ci, $flosc_multiline_idx, true)
                ? sanitize_textarea_field($flosc_raw)
                : sanitize_text_field($flosc_raw);
            $flosc_val = str_replace(["\r\n", "\r"], "\n", $flosc_val);
            if (trim($flosc_val) !== '') $flosc_all_empty = false;
            $flosc_cells[] = flosc_da1_tsv_cell($flosc_val);
        }
        if (!$flosc_all_empty) $flosc_lines[] = implode("\t", $flosc_cells);
    }

    $flosc_content = implode("\n", $flosc_lines) . "\n";

    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- admin-authenticated save to explicit DA1 TSV path
    if (file_put_contents($flosc_tsv_path, $flosc_content) !== false) {
        $flosc_count          = count($flosc_lines) - 1;
        $flosc_notice_success = $flosc_count . ' work' . ($flosc_count !== 1 ? 's' : '') . ' saved — ' . flosc_michel_timestamp();
        /* Re-read so the editor reflects what was just written */
        $flosc_parsed  = flosc_da1_parse_tsv($flosc_content);
        $flosc_columns = array_map('trim', $flosc_parsed[0] ?? $flosc_columns);
        $flosc_ncols   = count($flosc_columns);
        $flosc_rows    = array_slice($flosc_parsed, 1);
        usort($flosc_rows, function ($a, $b) { return (int)($b[0] ?? 0) - (int)($a[0] ?? 0); });
        foreach ($flosc_rows as &$flosc_row) { while (count($flosc_row) < $flosc_ncols) $flosc_row[] = ''; }
        unset($flosc_row);
        $flosc_multiline_idx = [];
        foreach ($flosc_columns as $flosc_ci => $flosc_col) {
            if (preg_match('/description|lyrics|media|video|notes/i', $flosc_col)) $flosc_multiline_idx[] = $flosc_ci;
        }
    } else {
        $flosc_notice_error = 'Could not write to ' . esc_html($flosc_tsv_path) . '. Check file permissions.';
    }
}

$flosc_total = count($flosc_rows);
?>

<?php // §12: #da1-table styles moved to assets/css/flosc-admin.css (enqueued on FLOSC admin pages). ?>

<div class="flosc-da1-wrap">

<?php if ($flosc_notice_success): ?>
    <div class="notice notice-success is-dismissible flosc-da1-notice">
        <p><?php echo esc_html($flosc_notice_success); ?></p>
    </div>
<?php endif; ?>
<?php if ($flosc_notice_error): ?>
    <div class="notice notice-error flosc-da1-notice">
        <p><?php echo esc_html( $flosc_notice_error ); ?></p>
    </div>
<?php endif; ?>

<!-- Header bar -->
<div class="flosc-da1-header">
    <div>
        <h2 class="flosc-da1-title">DA1 Catalog</h2>
        <p class="flosc-da1-meta">
            <?php echo esc_html( (string) $flosc_total ); ?> works &mdash;
            <code class="flosc-da1-filename"><?php echo esc_html($flosc_tsv_filename); ?></code>
        </p>
    </div>
    <div class="flosc-da1-actions-top">
        <span class="flosc-da1-hint">Click any cell to edit &mdash; Enter works in all fields</span>
        <button type="button" id="da1-add-row" class="button">+ Add Work</button>
        <button type="button" class="button button-primary" data-flosc-action="submit-form" data-form-id="da1-catalog-form">Save Catalog</button>
    </div>
</div>

<form id="da1-catalog-form" method="post">
    <?php wp_nonce_field('da1_catalog_save'); ?>
    <input type="hidden" name="da1_save_catalog" value="1">
    <?php foreach ($flosc_columns as $flosc_ci => $flosc_col): ?>
        <input type="hidden" name="da1_columns[<?php echo esc_attr( (string) $flosc_ci ); ?>]" value="<?php echo esc_attr($flosc_col); ?>">
    <?php endforeach; ?>

    <div class="flosc-da1-table-wrap">
    <table id="da1-table" class="flosc-da1-table">
        <thead>
            <tr class="flosc-da1-head-row">
                <?php foreach ($flosc_columns as $flosc_ci => $flosc_col): ?>
                <th class="flosc-da1-head-cell"><?php echo esc_html($flosc_col); ?></th>
                <?php endforeach; ?>
                <th class="flosc-da1-head-cell flosc-da1-head-cell-actions"></th>
            </tr>
        </thead>
        <tbody id="da1-tbody">
        <?php foreach ($flosc_rows as $flosc_ri => $flosc_row): ?>
            <tr class="da1-row flosc-da1-row">
                <?php foreach ($flosc_columns as $flosc_ci => $flosc_col):
                    $flosc_val      = stripslashes($flosc_row[$flosc_ci] ?? '');
                    $flosc_is_multi = in_array($flosc_ci, $flosc_multiline_idx);
                    $flosc_name     = 'da1_rows[' . $flosc_ri . '][' . $flosc_ci . ']';
                ?>
                <td class="flosc-da1-cell">
                    <?php if ($flosc_is_multi): ?>
                        <textarea name="<?php echo esc_attr( $flosc_name ); ?>" rows="3"
                            class="flosc-da1-input flosc-da1-textarea <?php echo preg_match('/media|video/i', $flosc_col) ? 'da1-media' : ''; ?>"
                        ><?php echo esc_textarea($flosc_val); ?></textarea>
                    <?php else: ?>
                        <input type="text" name="<?php echo esc_attr( $flosc_name ); ?>" value="<?php echo esc_attr($flosc_val); ?>"
                            class="flosc-da1-input"
                        >
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
                <td class="flosc-da1-cell flosc-da1-cell-actions">
                    <button type="button" class="da1-open-modal button button-small flosc-da1-edit-btn">Edit</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <div class="flosc-da1-actions-bottom">
        <button type="button" id="da1-add-row-bottom" class="button">+ Add Work</button>
        <button type="submit" class="button button-primary button-large">Save Catalog</button>
    </div>
</form>

</div>

<div id="da1-modal-overlay" class="flosc-da1-modal-overlay">
    <div class="flosc-da1-modal">
        <div class="flosc-da1-modal-header">
            <span id="da1-modal-title">Edit Work</span>
            <button type="button" id="da1-modal-close" class="flosc-da1-modal-close">&times;</button>
        </div>
        <div id="da1-modal-body" class="flosc-da1-modal-body"></div>
        <div class="flosc-da1-modal-footer">
            <button type="button" id="da1-modal-delete" class="flosc-da1-modal-delete">Delete this work</button>
            <div class="flosc-da1-modal-actions">
                <button type="button" id="da1-modal-cancel" class="button">Cancel</button>
                <button type="button" id="da1-modal-save" class="button button-primary">Save</button>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
(function () {
    var NCOLS     = <?php echo (int) $flosc_ncols; ?>;
    var MULTILINE = <?php echo json_encode(array_values($flosc_multiline_idx)); ?>;

    function nextIndex() {
        return document.querySelectorAll('#da1-tbody .da1-row').length;
    }

    function makeRow() {
        var ri = nextIndex();
        var tr = document.createElement('tr');
        tr.className     = 'da1-row flosc-da1-row';

        for (var ci = 0; ci < NCOLS; ci++) {
            var td = document.createElement('td');
            td.className = 'flosc-da1-cell';
            var name = 'da1_rows[' + ri + '][' + ci + ']';
            if (MULTILINE.indexOf(ci) !== -1) {
                var ta = document.createElement('textarea');
                ta.name = name; ta.rows = 3;
                ta.className = 'flosc-da1-input flosc-da1-textarea';
                if (/media|video/i.test(COLUMNS[ci])) ta.className += ' da1-media';
                td.appendChild(ta);
            } else {
                var inp = document.createElement('input');
                inp.type = 'text'; inp.name = name;
                inp.className = 'flosc-da1-input';
                td.appendChild(inp);
            }
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

    function addRow() {
        var tr = makeRow();
        document.getElementById('da1-tbody').appendChild(tr);
        openModal(tr);
    }

    /* ---- modal ---- */
    var COLUMNS   = <?php echo json_encode(array_values($flosc_columns)); ?>;
    var overlay   = document.getElementById('da1-modal-overlay');
    var modalBody = document.getElementById('da1-modal-body');
    var modalTitle= document.getElementById('da1-modal-title');
    var activeRow = null;

    function getInputs(tr) {
        return Array.from(tr.querySelectorAll('input[name*="da1_rows"], textarea[name*="da1_rows"]'));
    }

    function openModal(tr) {
        activeRow  = tr;
        var inputs = getInputs(tr);
        var titleVal = inputs[1] ? inputs[1].value.trim() : '';
        modalTitle.textContent = titleVal || 'Edit Work';
        modalBody.innerHTML = '';
        COLUMNS.forEach(function (col, ci) {
            var val  = inputs[ci] ? inputs[ci].value : '';
            var wrap = document.createElement('div');
            wrap.className = 'flosc-da1-modal-field';
            var lbl  = document.createElement('label');
            lbl.textContent = col;
            lbl.setAttribute('for', 'da1m-' + ci);
            lbl.className = 'flosc-da1-modal-label';
            wrap.appendChild(lbl);
            var field;
            if (MULTILINE.indexOf(ci) !== -1) {
                field = document.createElement('textarea');
                field.rows = 6;
                field.className = 'flosc-da1-modal-input flosc-da1-modal-textarea';
            } else {
                field = document.createElement('input');
                field.type = 'text';
                field.className = 'flosc-da1-modal-input';
            }
            field.id    = 'da1m-' + ci;
            field.value = val;
            field.setAttribute('data-ci', ci);
            wrap.appendChild(field);
            if (/media/i.test(col)) {
                var hint = document.createElement('p');
                hint.className = 'flosc-da1-modal-hint';
                hint.textContent = 'One URL per line. Shown left to right in carousel in the same sequence as top to bottom.';
                wrap.appendChild(hint);
            }
            modalBody.appendChild(wrap);
        });
        overlay.style.display = 'flex';
        var first = modalBody.querySelector('input,textarea');
        if (first) first.focus();
    }

    function closeModal() {
        overlay.style.display = 'none';
        activeRow = null;
    }

    function saveModal() {
        if (!activeRow) return;
        var inputs = getInputs(activeRow);
        modalBody.querySelectorAll('[data-ci]').forEach(function (f) {
            var ci = parseInt(f.getAttribute('data-ci'));
            if (inputs[ci]) inputs[ci].value = f.value;
        });
        closeModal();
        document.getElementById('da1-catalog-form').submit();
    }


    document.getElementById('da1-add-row').addEventListener('click', addRow);
    document.getElementById('da1-add-row-bottom').addEventListener('click', addRow);

    document.getElementById('da1-tbody').addEventListener('click', function (e) {
        var btn = e.target.closest('.da1-open-modal');
        if (btn) openModal(btn.closest('.da1-row'));
    });

    document.getElementById('da1-modal-save').addEventListener('click', saveModal);
    document.getElementById('da1-modal-cancel').addEventListener('click', closeModal);
    document.getElementById('da1-modal-close').addEventListener('click', closeModal);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.style.display === 'flex') closeModal();
    });
    document.getElementById('da1-modal-body').addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT') { e.preventDefault(); saveModal(); }
    });
    document.getElementById('da1-modal-delete').addEventListener('click', function () {
        if (!activeRow) return;
        var inputs = getInputs(activeRow);
        var title  = inputs[1] ? inputs[1].value.trim() : '';
        var label  = title ? '\u201c' + title + '\u201d' : 'this work';
        if (!confirm('Delete ' + label + '?\n\nTakes effect when you Save Catalog.')) return;
        activeRow.remove();
        closeModal();
    });
})();
<?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>
