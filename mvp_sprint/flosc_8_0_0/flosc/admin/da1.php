<?php
/**
 * FLOSC Admin — DA1 Tab
 * Works Catalog TSV Editor
 * 2026y-04m-09d
 *
 * Reads and writes /wp-content/uploads/dainis-w-michel-list-of-works.tsv
 * Columns (8): Date | Title | Description | Language | Lyrics | Media | Score | Notes
 *
 * - Click any cell to edit inline
 * - Lyrics and Media are textarea (multi-line, Enter key works naturally)
 * - Sorted by Date descending (michel innovation timestamps)
 * - Save rewrites the TSV; page is live immediately
 */

if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) return;

$tsv_filename  = 'dainis-w-michel-list-of-works.tsv';
$upload_dir    = wp_upload_dir();
$tsv_path      = $upload_dir['basedir'] . '/' . $tsv_filename;

$columns       = ['Date', 'Title', 'Description', 'Language', 'Lyrics', 'Media', 'Score', 'Notes'];
$multiline_idx = [4, 5]; /* Lyrics, Media */

$notice_success = '';
$notice_error   = '';

/* ------------------------------------------------------------------ Save */
if (isset($_POST['da1_save_catalog']) && check_admin_referer('da1_catalog_save')) {

    $rows_post = $_POST['da1_rows'] ?? [];
    $lines     = [];
    $lines[]   = implode("\t", $columns); /* header */

    foreach ($rows_post as $row) {
        $cells    = [];
        $all_empty = true;
        foreach ($columns as $ci => $col) {
            $val = isset($row[$ci]) ? (string) $row[$ci] : '';
            $val = str_replace(["\r\n", "\r"], "\n", $val);
            if (trim($val) !== '') $all_empty = false;
            /* RFC-4180-style quoting for TSV */
            if (strpos($val, "\t") !== false || strpos($val, "\n") !== false || strpos($val, '"') !== false) {
                $val = '"' . str_replace('"', '""', $val) . '"';
            }
            $cells[] = $val;
        }
        if (!$all_empty) {
            $lines[] = implode("\t", $cells);
        }
    }

    $content = implode("\n", $lines) . "\n";

    if (file_put_contents($tsv_path, $content) !== false) {
        $count          = count($lines) - 1;
        $notice_success = $count . ' work' . ($count !== 1 ? 's' : '') . ' saved — ' . flosc_michel_timestamp();
    } else {
        $notice_error = 'Could not write to ' . esc_html($tsv_path) . '. Check file permissions.';
    }
}

/* ------------------------------------------------------------------ Read */
$rows = [];

if (file_exists($tsv_path)) {
    $content  = file_get_contents($tsv_path);
    $parsed   = [];
    $row      = [];
    $field    = '';
    $in_q     = false;
    $len      = strlen($content);

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

    /* Skip header row, sort by date desc */
    $rows = array_slice($parsed, 1);
    usort($rows, function ($a, $b) {
        return (int) ($b[0] ?? 0) - (int) ($a[0] ?? 0);
    });
}

if (empty($rows)) {
    $rows = [array_fill(0, count($columns), '')];
}

/* Pad all rows to 8 columns */
foreach ($rows as &$row) {
    while (count($row) < count($columns)) $row[] = '';
}
unset($row);

$total = count($rows);
$ncols = count($columns);
?>

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
        <span style="font-size:12px;color:#787c82;">Click any cell to edit &mdash; Enter works in all fields</span>
        <button type="button" id="da1-add-row" class="button">+ Add Work</button>
        <button type="submit" form="da1-catalog-form" class="button button-primary">Save Catalog</button>
    </div>
</div>

<form id="da1-catalog-form" method="post">
    <?php wp_nonce_field('da1_catalog_save'); ?>
    <input type="hidden" name="da1_save_catalog" value="1">

    <div style="overflow-x:auto;border:1px solid #c3c4c7;border-radius:2px;">
    <table id="da1-table" style="width:100%;border-collapse:collapse;font-size:13px;table-layout:auto;">
        <thead>
            <tr style="background:#1d2327;">
                <th style="padding:8px 10px;color:#f0f0f1;font-size:11px;text-transform:uppercase;letter-spacing:0.04em;width:60px;white-space:nowrap;">Date</th>
                <th style="padding:8px 10px;color:#f0f0f1;font-size:11px;text-transform:uppercase;letter-spacing:0.04em;min-width:150px;">Title</th>
                <th style="padding:8px 10px;color:#f0f0f1;font-size:11px;text-transform:uppercase;letter-spacing:0.04em;min-width:180px;">Description</th>
                <th style="padding:8px 10px;color:#f0f0f1;font-size:11px;text-transform:uppercase;letter-spacing:0.04em;width:70px;white-space:nowrap;">Lang</th>
                <th style="padding:8px 10px;color:#f0f0f1;font-size:11px;text-transform:uppercase;letter-spacing:0.04em;min-width:160px;">Lyrics</th>
                <th style="padding:8px 10px;color:#f0f0f1;font-size:11px;text-transform:uppercase;letter-spacing:0.04em;min-width:180px;">Media</th>
                <th style="padding:8px 10px;color:#f0f0f1;font-size:11px;text-transform:uppercase;letter-spacing:0.04em;min-width:140px;">Score</th>
                <th style="padding:8px 10px;color:#f0f0f1;font-size:11px;text-transform:uppercase;letter-spacing:0.04em;min-width:140px;">Notes</th>
                <th style="padding:8px 10px;width:36px;"></th>
            </tr>
        </thead>
        <tbody id="da1-tbody">
        <?php foreach ($rows as $ri => $row): ?>
            <tr class="da1-row" style="border-bottom:1px solid #e0e0e0;<?php echo ($ri % 2 === 0) ? 'background:#fff;' : 'background:#f9f9f9;'; ?>">
                <?php foreach ($columns as $ci => $col):
                    $val      = $row[$ci] ?? '';
                    $is_multi = in_array($ci, $multiline_idx);
                    $name     = 'da1_rows[' . $ri . '][' . $ci . ']';
                    $cell_style = 'padding:3px 5px;vertical-align:top;';
                    $base   = 'width:100%;box-sizing:border-box;border:1px solid transparent;border-radius:2px;padding:4px 6px;font-size:13px;font-family:inherit;background:transparent;color:#1d2327;';
                    $focus  = "this.style.borderColor='#2271b1';this.style.background='#fff';this.closest('tr').style.background='#f0f6fc';";
                    $blur   = "this.style.borderColor='transparent';this.style.background='transparent';";
                ?>
                <td style="<?php echo $cell_style; ?>">
                    <?php if ($is_multi): ?>
                        <textarea name="<?php echo $name; ?>" rows="3"
                            style="<?php echo $base; ?>resize:vertical;line-height:1.45;"
                            onfocus="<?php echo $focus; ?>"
                            onblur="<?php echo $blur; ?>"
                        ><?php echo esc_textarea($val); ?></textarea>
                    <?php else: ?>
                        <input type="text" name="<?php echo $name; ?>" value="<?php echo esc_attr($val); ?>"
                            style="<?php echo $base; ?>"
                            onfocus="<?php echo $focus; ?>"
                            onblur="<?php echo $blur; ?>"
                        >
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
                <td style="padding:3px 5px;vertical-align:top;text-align:center;">
                    <button type="button" class="da1-delete button button-small"
                        style="color:#b32d2e;border-color:#b32d2e;padding:2px 7px;"
                        title="Delete this work">&times;</button>
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

<script>
(function () {
    var NCOLS      = <?php echo $ncols; ?>;
    var MULTILINE  = <?php echo json_encode($multiline_idx); ?>;
    var base       = 'width:100%;box-sizing:border-box;border:1px solid transparent;border-radius:2px;padding:4px 6px;font-size:13px;font-family:inherit;background:transparent;color:#1d2327;';
    var focusIn    = "this.style.borderColor='#2271b1';this.style.background='#fff';this.closest('tr').style.background='#f0f6fc';";
    var focusOut   = "this.style.borderColor='transparent';this.style.background='transparent';";

    function nextIndex() {
        return document.querySelectorAll('#da1-tbody .da1-row').length;
    }

    function makeRow() {
        var ri  = nextIndex();
        var tr  = document.createElement('tr');
        tr.className  = 'da1-row';
        tr.style.cssText = 'border-bottom:1px solid #e0e0e0;background:#fff;';

        for (var ci = 0; ci < NCOLS; ci++) {
            var td = document.createElement('td');
            td.style.cssText = 'padding:3px 5px;vertical-align:top;';
            var name = 'da1_rows[' + ri + '][' + ci + ']';
            if (MULTILINE.indexOf(ci) !== -1) {
                var ta = document.createElement('textarea');
                ta.name = name; ta.rows = 3;
                ta.style.cssText = base + 'resize:vertical;line-height:1.45;';
                ta.setAttribute('onfocus', focusIn);
                ta.setAttribute('onblur',  focusOut);
                td.appendChild(ta);
            } else {
                var inp = document.createElement('input');
                inp.type = 'text'; inp.name = name;
                inp.style.cssText = base;
                inp.setAttribute('onfocus', focusIn);
                inp.setAttribute('onblur',  focusOut);
                td.appendChild(inp);
            }
            tr.appendChild(td);
        }

        var tdDel = document.createElement('td');
        tdDel.style.cssText = 'padding:3px 5px;vertical-align:top;text-align:center;';
        var btn   = document.createElement('button');
        btn.type  = 'button';
        btn.className    = 'da1-delete button button-small';
        btn.style.cssText = 'color:#b32d2e;border-color:#b32d2e;padding:2px 7px;';
        btn.title        = 'Delete this work';
        btn.textContent  = '\u00d7';
        tdDel.appendChild(btn);
        tr.appendChild(tdDel);
        return tr;
    }

    function addRow() {
        var tr = makeRow();
        document.getElementById('da1-tbody').appendChild(tr);
        tr.querySelector('input,textarea').focus();
    }

    document.getElementById('da1-add-row').addEventListener('click', addRow);
    document.getElementById('da1-add-row-bottom').addEventListener('click', addRow);

    document.getElementById('da1-tbody').addEventListener('click', function (e) {
        var btn = e.target.closest('.da1-delete');
        if (!btn) return;
        var row = btn.closest('.da1-row');
        var title = row.querySelector('input[name*="[1]"]');
        var label = (title && title.value.trim()) ? '\u201c' + title.value.trim() + '\u201d' : 'this work';
        if (confirm('Delete ' + label + '?')) row.remove();
    });
})();
</script>
