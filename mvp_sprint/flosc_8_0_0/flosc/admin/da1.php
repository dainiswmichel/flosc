<?php
/**
 * FLOSC Admin — DA1 Tab
 * Works Catalog TSV Editor
 * 2026y-04m-09d
 *
 * Reads and writes /wp-content/uploads/dainis-w-michel-list-of-works.tsv
 * Columns are read from the TSV header row — no hardcoded schema.
 *
 * - List view: read-only table, Edit button per row
 * - Edit modal: full-width labeled fields, Save / Cancel / Delete
 * - Delete only available inside the edit modal (intentional, not accidental)
 * - Sorted by Date descending (michel innovation timestamps)
 */

if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) return;

$tsv_filename = 'dainis-w-michel-list-of-works.tsv';
$upload_dir   = wp_upload_dir();
$tsv_path     = $upload_dir['basedir'] . '/' . $tsv_filename;

$notice_success = '';
$notice_error   = '';

/* ----------------------------------------------------------------- Parser */
function da1_parse_tsv($content) {
    $parsed = [];
    $row    = [];
    $field  = '';
    $in_q   = false;
    $len    = strlen($content);

    for ($i = 0; $i < $len; $i++) {
        $ch = $content[$i];
        if ($in_q) {
            if ($ch === '"') {
                if ($i + 1 < $len && $content[$i + 1] === '"') { $field .= '"'; $i++; }
                else { $in_q = false; }
            } else { $field .= $ch; }
        } elseif ($ch === '"')  { $in_q = true; }
        elseif ($ch === "\t")  { $row[] = $field; $field = ''; }
        elseif ($ch === "\n")  { $row[] = $field; $field = ''; if (!empty($row)) { $parsed[] = $row; } $row = []; }
        elseif ($ch !== "\r")  { $field .= $ch; }
    }
    if ($field !== '' || !empty($row)) { $row[] = $field; $parsed[] = $row; }
    return $parsed;
}

function da1_tsv_cell($val) {
    $val = str_replace(["\r\n", "\r"], "\n", (string) $val);
    if (strpos($val, "\t") !== false || strpos($val, "\n") !== false || strpos($val, '"') !== false) {
        $val = '"' . str_replace('"', '""', $val) . '"';
    }
    return $val;
}

/* ------------------------------------------------------------------ Read */
$columns = [];
$rows    = [];

if (file_exists($tsv_path)) {
    $parsed  = da1_parse_tsv(file_get_contents($tsv_path));
    $columns = array_map('trim', $parsed[0] ?? []);
    $rows    = array_slice($parsed, 1);
    usort($rows, function ($a, $b) {
        return (int) ($b[0] ?? 0) - (int) ($a[0] ?? 0);
    });
}

if (empty($columns)) {
    $columns = ['Date', 'Title', 'Description', 'Lyrics', 'Video', 'Notes'];
}
if (empty($rows)) {
    $rows = [array_fill(0, count($columns), '')];
}

$ncols = count($columns);
foreach ($rows as &$row) {
    while (count($row) < $ncols) $row[] = '';
}
unset($row);

/* Multiline: Lyrics, Media, Video, Score, Description, Notes */
$multiline_idx = [];
foreach ($columns as $ci => $col) {
    if (preg_match('/lyrics|media|video|score|description|notes/i', $col)) $multiline_idx[] = $ci;
}

/* ------------------------------------------------------------------ Save */
if (isset($_POST['da1_save_catalog']) && check_admin_referer('da1_catalog_save')) {

    $saved_columns = $_POST['da1_columns'] ?? $columns;
    $rows_post     = $_POST['da1_rows'] ?? [];
    $lines         = [];
    $lines[]       = implode("\t", array_map('da1_tsv_cell', $saved_columns));

    foreach ($rows_post as $row) {
        $cells     = [];
        $all_empty = true;
        foreach ($saved_columns as $ci => $col) {
            $val = isset($row[$ci]) ? (string) $row[$ci] : '';
            $val = str_replace(["\r\n", "\r"], "\n", $val);
            if (trim($val) !== '') $all_empty = false;
            $cells[] = da1_tsv_cell($val);
        }
        if (!$all_empty) $lines[] = implode("\t", $cells);
    }

    $content = implode("\n", $lines) . "\n";

    if (file_put_contents($tsv_path, $content) !== false) {
        $count          = count($lines) - 1;
        $notice_success = $count . ' work' . ($count !== 1 ? 's' : '') . ' saved — ' . flosc_michel_timestamp();
        $parsed  = da1_parse_tsv($content);
        $columns = array_map('trim', $parsed[0] ?? $columns);
        $ncols   = count($columns);
        $rows    = array_slice($parsed, 1);
        usort($rows, function ($a, $b) { return (int)($b[0] ?? 0) - (int)($a[0] ?? 0); });
        foreach ($rows as &$row) { while (count($row) < $ncols) $row[] = ''; }
        unset($row);
        $multiline_idx = [];
        foreach ($columns as $ci => $col) {
            if (preg_match('/lyrics|media|video|score|description|notes/i', $col)) $multiline_idx[] = $ci;
        }
    } else {
        $notice_error = 'Could not write to ' . esc_html($tsv_path) . '. Check file permissions.';
    }
}

$total = count($rows);
?>

<style>
/* ---- DA1 list table ---- */
#da1-table td { padding:8px 10px; vertical-align:middle; }
#da1-table .da1-cell-text {
    max-width:240px; overflow:hidden; text-overflow:ellipsis;
    white-space:nowrap; color:#1d2327; font-size:13px;
}
#da1-table .da1-cell-text.is-multi {
    white-space:pre-line; max-height:3.6em; display:-webkit-box;
    -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden;
}
#da1-table .da1-edit-btn {
    font-size:12px; padding:3px 10px; white-space:nowrap;
}
#da1-tbody tr { cursor:default; }
#da1-tbody tr:hover { background:#f0f6fc !important; }

/* ---- Edit modal ---- */
#da1-modal-overlay {
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,.55); z-index:100000;
    align-items:center; justify-content:center;
}
#da1-modal-overlay.open { display:flex; }
#da1-modal {
    background:#fff; border-radius:4px; width:600px; max-width:96vw;
    max-height:90vh; display:flex; flex-direction:column;
    box-shadow:0 8px 40px rgba(0,0,0,.35);
}
#da1-modal-head {
    padding:16px 20px 14px; border-bottom:1px solid #ddd;
    font-size:15px; font-weight:600; color:#1d2327;
    display:flex; justify-content:space-between; align-items:center;
}
#da1-modal-close {
    background:none; border:none; cursor:pointer;
    font-size:20px; color:#787c82; line-height:1; padding:0 4px;
}
#da1-modal-close:hover { color:#1d2327; }
#da1-modal-body {
    padding:18px 20px; overflow-y:auto; flex:1;
    display:flex; flex-direction:column; gap:14px;
}
.da1-field label {
    display:block; font-size:12px; font-weight:600; text-transform:uppercase;
    letter-spacing:.04em; color:#50575e; margin-bottom:4px;
}
.da1-field input[type=text],
.da1-field textarea {
    width:100%; box-sizing:border-box; border:1px solid #8c8f94;
    border-radius:3px; padding:7px 9px; font-size:13px;
    font-family:inherit; color:#1d2327; line-height:1.5;
}
.da1-field input[type=text]:focus,
.da1-field textarea:focus {
    border-color:#2271b1; outline:2px solid rgba(34,113,177,.2);
}
.da1-field textarea { resize:vertical; }
#da1-modal-foot {
    padding:12px 20px; border-top:1px solid #ddd;
    display:flex; justify-content:space-between; align-items:center;
}
#da1-modal-delete {
    background:none; border:1px solid #b32d2e; color:#b32d2e;
    border-radius:3px; padding:5px 12px; font-size:12px; cursor:pointer;
}
#da1-modal-delete:hover { background:#b32d2e; color:#fff; }
</style>

<div style="max-width:1300px;">

<?php if ($notice_success): ?>
    <div class="notice notice-success is-dismissible" style="margin:0 0 16px;">
        <p><?php echo esc_html($notice_success); ?></p>
    </div>
<?php endif; ?>
<?php if ($notice_error): ?>
    <div class="notice notice-error" style="margin:0 0 16px;">
        <p><?php echo $notice_error; ?></p>
    </div>
<?php endif; ?>

<!-- Header bar -->
<div style="background:#f0f0f1;border:1px solid #c3c4c7;padding:12px 18px;border-radius:2px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
    <div>
        <h2 style="margin:0;color:#1d2327;font-size:16px;">DA1 Catalog</h2>
        <p style="margin:3px 0 0;color:#50575e;font-size:13px;">
            <?php echo $total; ?> works &mdash;
            <code style="background:#e0e0e0;padding:2px 8px;border-radius:2px;color:#1d2327;font-size:11px;"><?php echo esc_html($tsv_filename); ?></code>
        </p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <button type="button" id="da1-add-row" class="button button-primary">+ Add Work</button>
        <button type="submit" form="da1-catalog-form" class="button">Save Catalog</button>
    </div>
</div>

<!-- Hidden form — holds all data, submitted on Save Catalog -->
<form id="da1-catalog-form" method="post" style="display:none;">
    <?php wp_nonce_field('da1_catalog_save'); ?>
    <input type="hidden" name="da1_save_catalog" value="1">
    <?php foreach ($columns as $ci => $col): ?>
        <input type="hidden" name="da1_columns[<?php echo $ci; ?>]" value="<?php echo esc_attr($col); ?>">
    <?php endforeach; ?>
    <div id="da1-hidden-rows">
    <?php foreach ($rows as $ri => $row): ?>
        <div class="da1-row-data" data-ri="<?php echo $ri; ?>">
        <?php foreach ($columns as $ci => $col): ?>
            <input type="hidden" name="da1_rows[<?php echo $ri; ?>][<?php echo $ci; ?>]"
                   value="<?php echo esc_attr($row[$ci] ?? ''); ?>">
        <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    </div>
</form>

<!-- List table -->
<div style="overflow-x:auto;border:1px solid #c3c4c7;border-radius:2px;">
<table id="da1-table" style="width:100%;border-collapse:collapse;font-size:13px;">
    <thead>
        <tr style="background:#1d2327;">
            <?php foreach ($columns as $ci => $col): ?>
            <th style="padding:8px 10px;color:#f0f0f1;font-size:11px;text-transform:uppercase;letter-spacing:0.04em;white-space:nowrap;text-align:left;"><?php echo esc_html($col); ?></th>
            <?php endforeach; ?>
            <th style="padding:8px 10px;width:60px;"></th>
        </tr>
    </thead>
    <tbody id="da1-tbody">
    <?php foreach ($rows as $ri => $row): ?>
        <tr class="da1-row" data-ri="<?php echo $ri; ?>" style="border-bottom:1px solid #e0e0e0;<?php echo ($ri % 2 === 0) ? 'background:#fff;' : 'background:#f9f9f9;'; ?>">
            <?php foreach ($columns as $ci => $col):
                $val      = $row[$ci] ?? '';
                $is_multi = in_array($ci, $multiline_idx);
            ?>
            <td>
                <span class="da1-cell-text<?php echo $is_multi ? ' is-multi' : ''; ?>"><?php echo esc_html($val); ?></span>
            </td>
            <?php endforeach; ?>
            <td style="text-align:right;">
                <button type="button" class="da1-edit-btn button button-small" data-ri="<?php echo $ri; ?>">Edit</button>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<div style="margin-top:14px;display:flex;justify-content:space-between;align-items:center;">
    <button type="button" id="da1-add-row-bottom" class="button button-primary">+ Add Work</button>
    <button type="submit" form="da1-catalog-form" class="button button-large">Save Catalog</button>
</div>

</div><!-- /max-width wrapper -->

<!-- Edit modal -->
<div id="da1-modal-overlay">
    <div id="da1-modal" role="dialog" aria-modal="true" aria-labelledby="da1-modal-title">
        <div id="da1-modal-head">
            <span id="da1-modal-title">Edit Work</span>
            <button type="button" id="da1-modal-close" aria-label="Close">&times;</button>
        </div>
        <div id="da1-modal-body">
            <!-- fields injected by JS -->
        </div>
        <div id="da1-modal-foot">
            <button type="button" id="da1-modal-delete">Delete this work</button>
            <div style="display:flex;gap:8px;">
                <button type="button" id="da1-modal-cancel" class="button">Cancel</button>
                <button type="button" id="da1-modal-save" class="button button-primary">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var COLUMNS   = <?php echo json_encode(array_values($columns)); ?>;
    var MULTILINE = <?php echo json_encode(array_values($multiline_idx)); ?>;
    var overlay   = document.getElementById('da1-modal-overlay');
    var modalBody = document.getElementById('da1-modal-body');
    var modalTitle= document.getElementById('da1-modal-title');
    var activeRow = null; /* the .da1-row tr currently being edited */
    var activeRi  = null;

    /* ---- helpers ---- */
    function getRowData(ri) {
        var data = {};
        var container = document.querySelector('.da1-row-data[data-ri="' + ri + '"]');
        if (!container) return data;
        container.querySelectorAll('input[type=hidden]').forEach(function (inp) {
            var m = inp.name.match(/\[(\d+)\]$/);
            if (m) data[parseInt(m[1])] = inp.value;
        });
        return data;
    }

    function setRowData(ri, data) {
        var container = document.querySelector('.da1-row-data[data-ri="' + ri + '"]');
        if (!container) return;
        Object.keys(data).forEach(function (ci) {
            var inp = container.querySelector('input[name*="[' + ci + ']"]');
            if (inp) inp.value = data[ci];
        });
    }

    function refreshRowDisplay(tr, data) {
        var cells = tr.querySelectorAll('.da1-cell-text');
        cells.forEach(function (span, ci) {
            span.textContent = data[ci] !== undefined ? data[ci] : '';
        });
    }

    /* ---- open modal ---- */
    function openModal(ri, tr) {
        activeRow = tr;
        activeRi  = ri;
        var data  = getRowData(ri);
        var title = (data[1] && data[1].trim()) ? data[1].trim() : 'New Work';
        modalTitle.textContent = title;

        modalBody.innerHTML = '';
        COLUMNS.forEach(function (col, ci) {
            var val   = data[ci] !== undefined ? data[ci] : '';
            var div   = document.createElement('div');
            div.className = 'da1-field';
            var label = document.createElement('label');
            label.textContent = col;
            label.setAttribute('for', 'da1-field-' + ci);
            div.appendChild(label);

            var field;
            if (MULTILINE.indexOf(ci) !== -1) {
                field = document.createElement('textarea');
                field.rows = 5;
            } else {
                field = document.createElement('input');
                field.type = 'text';
            }
            field.id    = 'da1-field-' + ci;
            field.value = val;
            field.setAttribute('data-ci', ci);
            div.appendChild(field);
            modalBody.appendChild(div);
        });

        overlay.classList.add('open');
        var first = modalBody.querySelector('input,textarea');
        if (first) { first.focus(); first.select(); }
    }

    function closeModal() {
        overlay.classList.remove('open');
        activeRow = null;
        activeRi  = null;
    }

    function saveModal() {
        if (activeRi === null) return;
        var data = {};
        modalBody.querySelectorAll('[data-ci]').forEach(function (field) {
            data[field.getAttribute('data-ci')] = field.value;
        });
        setRowData(activeRi, data);
        if (activeRow) refreshRowDisplay(activeRow, data);
        closeModal();
    }

    /* ---- add new row ---- */
    function addWork() {
        /* Find next ri — one past current max */
        var allData   = document.querySelectorAll('.da1-row-data');
        var nextRi    = allData.length;
        var hiddenDiv = document.getElementById('da1-hidden-rows');

        /* Create hidden data container */
        var container = document.createElement('div');
        container.className = 'da1-row-data';
        container.setAttribute('data-ri', nextRi);
        COLUMNS.forEach(function (col, ci) {
            var inp   = document.createElement('input');
            inp.type  = 'hidden';
            inp.name  = 'da1_rows[' + nextRi + '][' + ci + ']';
            inp.value = '';
            container.appendChild(inp);
        });
        hiddenDiv.appendChild(container);

        /* Create visible table row */
        var tbody = document.getElementById('da1-tbody');
        var count = tbody.querySelectorAll('.da1-row').length;
        var tr    = document.createElement('tr');
        tr.className     = 'da1-row';
        tr.setAttribute('data-ri', nextRi);
        tr.style.cssText = 'border-bottom:1px solid #e0e0e0;background:' + (count % 2 === 0 ? '#fff' : '#f9f9f9') + ';';

        COLUMNS.forEach(function () {
            var td   = document.createElement('td');
            td.innerHTML = '<span class="da1-cell-text"></span>';
            tr.appendChild(td);
        });

        var tdBtn = document.createElement('td');
        tdBtn.style.textAlign = 'right';
        var btn   = document.createElement('button');
        btn.type  = 'button';
        btn.className = 'da1-edit-btn button button-small';
        btn.setAttribute('data-ri', nextRi);
        btn.textContent = 'Edit';
        tdBtn.appendChild(btn);
        tr.appendChild(tdBtn);
        tbody.appendChild(tr);

        openModal(nextRi, tr);
    }

    /* ---- events ---- */
    document.getElementById('da1-add-row').addEventListener('click', addWork);
    document.getElementById('da1-add-row-bottom').addEventListener('click', addWork);

    document.getElementById('da1-tbody').addEventListener('click', function (e) {
        var btn = e.target.closest('.da1-edit-btn');
        if (!btn) return;
        var tr = btn.closest('.da1-row');
        openModal(parseInt(btn.getAttribute('data-ri')), tr);
    });

    document.getElementById('da1-modal-save').addEventListener('click', saveModal);
    document.getElementById('da1-modal-cancel').addEventListener('click', closeModal);
    document.getElementById('da1-modal-close').addEventListener('click', closeModal);

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('open')) closeModal();
    });

    document.getElementById('da1-modal-delete').addEventListener('click', function () {
        if (activeRi === null) return;
        var title = '';
        var t = modalBody.querySelector('[data-ci="1"]');
        if (t) title = t.value.trim();
        var label = title ? '\u201c' + title + '\u201d' : 'this work';
        if (!confirm('Delete ' + label + '? This cannot be undone until you Save Catalog.')) return;

        /* Remove hidden data */
        var container = document.querySelector('.da1-row-data[data-ri="' + activeRi + '"]');
        if (container) container.remove();

        /* Remove table row */
        if (activeRow) activeRow.remove();

        closeModal();
    });

    /* Enter in single-line fields saves */
    modalBody.addEventListener && document.getElementById('da1-modal-body').addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
            e.preventDefault();
            saveModal();
        }
    });
})();
</script>
