<?php
/**
 * FLOSC Admin — DA1 Tab
 * Works Catalog TSV Editor
 * 2026y-04m-09d
 *
 * Reads and writes /wp-content/uploads/dainis-w-michel-list-of-works.tsv
 * Columns are read from the TSV header row — no hardcoded schema.
 *
 * - Inline editing directly in table cells (quick edit)
 * - Edit button per row opens a spacious modal (also the only place to delete)
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
        } elseif ($ch === '"') { $in_q = true; }
        elseif ($ch === "\t") { $row[] = $field; $field = ''; }
        elseif ($ch === "\n") { $row[] = $field; $field = ''; if (!empty($row)) $parsed[] = $row; $row = []; }
        elseif ($ch !== "\r") { $field .= $ch; }
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

if (empty($columns)) $columns = ['Date', 'Title', 'Description', 'Lyrics', 'Video', 'Notes'];
if (empty($rows))    $rows    = [array_fill(0, count($columns), '')];

$ncols = count($columns);
foreach ($rows as &$row) { while (count($row) < $ncols) $row[] = ''; }
unset($row);

$multiline_idx = [];
foreach ($columns as $ci => $col) {
    if (preg_match('/lyrics|media|video|score/i', $col)) $multiline_idx[] = $ci;
}

/* ------------------------------------------------------------------ Save */
if (isset($_POST['da1_save_catalog']) && check_admin_referer('da1_catalog_save')) {

    $saved_columns = $_POST['da1_columns'] ?? $columns;
    $rows_post     = $_POST['da1_rows'] ?? [];
    $lines         = [implode("\t", array_map('da1_tsv_cell', $saved_columns))];

    foreach ($rows_post as $row) {
        $cells = []; $all_empty = true;
        foreach ($saved_columns as $ci => $col) {
            $val = str_replace(["\r\n", "\r"], "\n", (string) ($row[$ci] ?? ''));
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
            if (preg_match('/lyrics|media|video|score/i', $col)) $multiline_idx[] = $ci;
        }
    } else {
        $notice_error = 'Could not write to ' . esc_html($tsv_path) . '. Check file permissions.';
    }
}

$total = count($rows);
?>

<style>
/* ---- table inputs ---- */
.da1-inp {
    width:100%; box-sizing:border-box;
    border:1px solid rgba(0,0,0,0.12);
    border-radius:2px; padding:4px 6px; font-size:13px;
    font-family:inherit; background:inherit; color:#1d2327;
}
.da1-inp:focus {
    border-color:#2271b1; background:#fff; outline:none;
}
textarea.da1-inp { resize:vertical; line-height:1.45; }

/* ---- edit modal ---- */
#da1-modal-overlay {
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,.55); z-index:100000;
    align-items:center; justify-content:center;
}
#da1-modal-overlay.open { display:flex; }
#da1-modal {
    background:#fff; border-radius:4px; width:620px; max-width:96vw;
    max-height:90vh; display:flex; flex-direction:column;
    box-shadow:0 8px 40px rgba(0,0,0,.35);
}
#da1-modal-head {
    padding:16px 20px 14px; border-bottom:1px solid #ddd;
    font-size:15px; font-weight:600; color:#1d2327;
    display:flex; justify-content:space-between; align-items:center;
    flex-shrink:0;
}
#da1-modal-close {
    background:none; border:none; cursor:pointer;
    font-size:22px; color:#787c82; line-height:1; padding:0 2px;
}
#da1-modal-close:hover { color:#1d2327; }
#da1-modal-body {
    padding:18px 20px; overflow-y:auto; flex:1;
    display:flex; flex-direction:column; gap:14px;
}
.da1-mfield label {
    display:block; font-size:11px; font-weight:600;
    text-transform:uppercase; letter-spacing:.05em;
    color:#50575e; margin-bottom:5px;
}
.da1-mfield input[type=text],
.da1-mfield textarea {
    width:100%; box-sizing:border-box; border:1px solid #8c8f94;
    border-radius:3px; padding:8px 10px; font-size:13px;
    font-family:inherit; color:#1d2327; line-height:1.5;
}
.da1-mfield input[type=text]:focus,
.da1-mfield textarea:focus {
    border-color:#2271b1; outline:2px solid rgba(34,113,177,.18);
}
.da1-mfield textarea { resize:vertical; }
#da1-modal-foot {
    padding:12px 20px; border-top:1px solid #ddd; flex-shrink:0;
    display:flex; justify-content:space-between; align-items:center;
}
#da1-modal-delete {
    background:none; border:1px solid #b32d2e; color:#b32d2e;
    border-radius:3px; padding:5px 14px; font-size:12px; cursor:pointer;
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
        <span style="font-size:12px;color:#787c82;">Click any cell to edit inline</span>
        <button type="button" id="da1-add-row" class="button">+ Add Work</button>
        <button type="submit" form="da1-catalog-form" class="button button-primary">Save Catalog</button>
    </div>
</div>

<form id="da1-catalog-form" method="post">
    <?php wp_nonce_field('da1_catalog_save'); ?>
    <input type="hidden" name="da1_save_catalog" value="1">
    <?php foreach ($columns as $ci => $col): ?>
        <input type="hidden" name="da1_columns[<?php echo $ci; ?>]" value="<?php echo esc_attr($col); ?>">
    <?php endforeach; ?>

    <div style="overflow-x:auto;border:1px solid #c3c4c7;border-radius:2px;">
    <table id="da1-table" style="width:100%;border-collapse:collapse;font-size:13px;table-layout:auto;">
        <thead>
            <tr style="background:#1d2327;">
                <?php foreach ($columns as $ci => $col): ?>
                <th style="padding:8px 10px;color:#f0f0f1;font-size:11px;text-transform:uppercase;letter-spacing:0.04em;white-space:nowrap;text-align:left;"><?php echo esc_html($col); ?></th>
                <?php endforeach; ?>
                <th style="padding:8px 10px;width:52px;"></th>
            </tr>
        </thead>
        <tbody id="da1-tbody">
        <?php foreach ($rows as $ri => $row): ?>
            <tr class="da1-row" style="border-bottom:1px solid #e0e0e0;<?php echo ($ri % 2 === 0) ? 'background:#fff;' : 'background:#f9f9f9;'; ?>">
                <?php foreach ($columns as $ci => $col):
                    $val      = $row[$ci] ?? '';
                    $is_multi = in_array($ci, $multiline_idx);
                    $name     = 'da1_rows[' . $ri . '][' . $ci . ']';
                ?>
                <td style="padding:3px 5px;vertical-align:top;">
                    <?php if ($is_multi): ?>
                        <textarea name="<?php echo $name; ?>" rows="3"
                            class="da1-inp"
                            onfocus="this.closest('tr').style.background='#f0f6fc';"
                            onblur="this.closest('tr').style.background='';"
                        ><?php echo esc_textarea($val); ?></textarea>
                    <?php else: ?>
                        <input type="text" name="<?php echo $name; ?>" value="<?php echo esc_attr($val); ?>"
                            class="da1-inp"
                            onfocus="this.closest('tr').style.background='#f0f6fc';"
                            onblur="this.closest('tr').style.background='';"
                        >
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
                <td style="padding:3px 5px;vertical-align:middle;text-align:right;">
                    <button type="button" class="da1-open-modal button button-small"
                        style="font-size:11px;padding:2px 8px;">Edit</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <div style="margin-top:14px;display:flex;justify-content:space-between;align-items:center;">
        <button type="button" id="da1-add-row-bottom" class="button">+ Add Work</button>
        <button type="submit" class="button button-primary button-large">Save Catalog</button>
    </div>
</form>

</div>

<!-- Edit / Delete modal -->
<div id="da1-modal-overlay">
    <div id="da1-modal" role="dialog" aria-modal="true" aria-labelledby="da1-modal-title">
        <div id="da1-modal-head">
            <span id="da1-modal-title">Edit Work</span>
            <button type="button" id="da1-modal-close" aria-label="Close">&times;</button>
        </div>
        <div id="da1-modal-body"></div>
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
    var NCOLS     = <?php echo (int) $ncols; ?>;
    var overlay   = document.getElementById('da1-modal-overlay');
    var modalBody = document.getElementById('da1-modal-body');
    var modalTitle= document.getElementById('da1-modal-title');
    var activeRow = null;

    /* ---- read / write row inputs ---- */
    function getRowInputs(tr) {
        /* Returns array of the actual input/textarea elements in the row */
        return Array.from(tr.querySelectorAll('input.da1-inp, textarea.da1-inp'));
    }

    /* ---- open modal — populates from the row's live inputs ---- */
    function openModal(tr) {
        activeRow    = tr;
        var inputs   = getRowInputs(tr);
        var titleVal = inputs[1] ? inputs[1].value.trim() : '';
        modalTitle.textContent = titleVal || 'Edit Work';
        modalBody.innerHTML    = '';

        COLUMNS.forEach(function (col, ci) {
            var val  = inputs[ci] ? inputs[ci].value : '';
            var wrap = document.createElement('div');
            wrap.className = 'da1-mfield';

            var lbl  = document.createElement('label');
            lbl.textContent      = col;
            lbl.setAttribute('for', 'da1m-' + ci);
            wrap.appendChild(lbl);

            var field;
            if (MULTILINE.indexOf(ci) !== -1) {
                field      = document.createElement('textarea');
                field.rows = 6;
            } else {
                field      = document.createElement('input');
                field.type = 'text';
            }
            field.id    = 'da1m-' + ci;
            field.value = val;
            field.setAttribute('data-ci', ci);
            wrap.appendChild(field);
            modalBody.appendChild(wrap);
        });

        overlay.classList.add('open');
        var first = modalBody.querySelector('input, textarea');
        if (first) { first.focus(); first.select(); }
    }

    function closeModal() {
        overlay.classList.remove('open');
        activeRow = null;
    }

    /* ---- save: write modal values back into the row's inputs ---- */
    function saveModal() {
        if (!activeRow) return;
        var inputs = getRowInputs(activeRow);
        modalBody.querySelectorAll('[data-ci]').forEach(function (field) {
            var ci = parseInt(field.getAttribute('data-ci'));
            if (inputs[ci]) inputs[ci].value = field.value;
        });
        closeModal();
    }

    /* ---- add new row ---- */
    function makeRow() {
        var tbody  = document.getElementById('da1-tbody');
        var ri     = tbody.querySelectorAll('.da1-row').length;
        var isEven = ri % 2 === 0;
        var tr     = document.createElement('tr');
        tr.className     = 'da1-row';
        tr.style.cssText = 'border-bottom:1px solid #e0e0e0;background:' + (isEven ? '#fff' : '#f9f9f9') + ';';

        var base    = 'width:100%;box-sizing:border-box;border:1px solid transparent;border-radius:2px;padding:4px 6px;font-size:13px;font-family:inherit;background:transparent;color:#1d2327;';
        var onFocus = "this.closest('tr').style.background='#f0f6fc';";
        var onBlur  = "this.closest('tr').style.background='';";

        for (var ci = 0; ci < NCOLS; ci++) {
            var td   = document.createElement('td');
            td.style.cssText = 'padding:3px 5px;vertical-align:top;';
            var name = 'da1_rows[' + ri + '][' + ci + ']';
            var field;
            if (MULTILINE.indexOf(ci) !== -1) {
                field       = document.createElement('textarea');
                field.rows  = 3;
                field.style.cssText = base + 'resize:vertical;line-height:1.45;';
            } else {
                field       = document.createElement('input');
                field.type  = 'text';
                field.style.cssText = base;
            }
            field.name  = name;
            field.className = 'da1-inp';
            field.setAttribute('onfocus', onFocus);
            field.setAttribute('onblur',  onBlur);
            td.appendChild(field);
            tr.appendChild(td);
        }

        var tdBtn = document.createElement('td');
        tdBtn.style.cssText = 'padding:3px 5px;vertical-align:middle;text-align:right;';
        var btn   = document.createElement('button');
        btn.type  = 'button';
        btn.className = 'da1-open-modal button button-small';
        btn.style.cssText = 'font-size:11px;padding:2px 8px;';
        btn.textContent = 'Edit';
        tdBtn.appendChild(btn);
        tr.appendChild(tdBtn);
        tbody.appendChild(tr);
        return tr;
    }

    function addRow() {
        var tr = makeRow();
        openModal(tr);
    }

    /* ---- events ---- */
    document.getElementById('da1-add-row').addEventListener('click', addRow);
    document.getElementById('da1-add-row-bottom').addEventListener('click', addRow);

    document.getElementById('da1-tbody').addEventListener('click', function (e) {
        var btn = e.target.closest('.da1-open-modal');
        if (btn) openModal(btn.closest('.da1-row'));
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

    document.getElementById('da1-modal-body').addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
            e.preventDefault();
            saveModal();
        }
    });

    document.getElementById('da1-modal-delete').addEventListener('click', function () {
        if (!activeRow) return;
        var inputs = getRowInputs(activeRow);
        var title  = inputs[1] ? inputs[1].value.trim() : '';
        var label  = title ? '\u201c' + title + '\u201d' : 'this work';
        if (!confirm('Delete ' + label + '?\n\nThis takes effect when you Save Catalog.')) return;
        activeRow.remove();
        closeModal();
    });
})();
</script>
