<?php
/**
 * FLOSC Documentation Tab
 * 
 * In-admin reference manual. Content sections are keyed by topic ID
 * to support future helpID deep-linking from other admin tabs.
 * 
 * Content status: ✅ = written, 🔲 = placeholder (heading structure only)
 * 
 * @since 8.0.1
 */

if (!defined('ABSPATH')) exit;

// Topic registry: each entry has an id, title, status ('ready' or 'pending'), and group.
// This registry will later support helpID lookups from inline help links elsewhere in the admin.
$flosc_doc_topics = [
    // Part 1: The Journey
    ['id' => 'the-journey',           'group' => 'journey', 'title' => 'The Journey — Why FLOSC Exists',        'status' => 'ready'],
    // Part 2: Architecture
    ['id' => 'architecture',          'group' => 'architecture', 'title' => 'Architecture Overview',            'status' => 'pending'],
    // Part 3: Reference
    ['id' => 'ref-ivr',              'group' => 'reference', 'title' => 'IVR System Reference',                'status' => 'pending'],
    ['id' => 'ref-core',             'group' => 'reference', 'title' => 'Core (flosc.php) Reference',          'status' => 'ready'],
    ['id' => 'ref-frontend',         'group' => 'reference', 'title' => 'Frontend (JS & CSS) Reference',       'status' => 'pending'],
    ['id' => 'ref-quiz',             'group' => 'reference', 'title' => 'Quiz System Reference',               'status' => 'ready'],
    ['id' => 'ref-audio-quiz-flow',  'group' => 'reference', 'title' => 'Audio Quiz Flow',                     'status' => 'ready'],
    ['id' => 'ref-admin',            'group' => 'reference', 'title' => 'Admin Pages Reference',               'status' => 'ready'],
    ['id' => 'ref-ai',               'group' => 'reference', 'title' => 'AI & RAG Reference',                  'status' => 'pending'],
    ['id' => 'ref-ai-config',        'group' => 'reference', 'title' => 'AI Configuration Guide',               'status' => 'ready'],
    ['id' => 'ref-payments',         'group' => 'reference', 'title' => 'Payments & Offers Reference',         'status' => 'pending'],
    ['id' => 'ref-access',           'group' => 'reference', 'title' => 'Access Control Reference',            'status' => 'pending'],
    ['id' => 'ref-sso',              'group' => 'reference', 'title' => 'SSO & OAuth Reference',               'status' => 'pending'],
    // Part 4: Security
    ['id' => 'security',             'group' => 'security', 'title' => 'Security',                             'status' => 'pending'],
    // Part 5: Glossary
    ['id' => 'glossary',             'group' => 'glossary', 'title' => 'Glossary — Every FLOSC Term Defined',  'status' => 'ready'],
];

// Which topic is currently selected?
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only query parameters used for documentation navigation
$get = wp_unslash($_GET);
$doc_topic = isset($get['doc']) ? sanitize_text_field($get['doc']) : '';

// Group labels for the sidebar
$group_labels = [
    'journey'      => 'Part 1: The Journey',
    'architecture' => 'Part 2: Architecture',
    'reference'    => 'Part 3: Reference',
    'security'     => 'Part 4: Security',
    'glossary'     => 'Part 5: Glossary',
];
?>

<div class="flosc-docs-wrap" style="display: flex; gap: 20px; margin-top: 15px;">

    <!-- Sidebar TOC -->
    <nav class="flosc-docs-sidebar" style="min-width: 240px; max-width: 260px; flex-shrink: 0;">
        <div style="position: sticky; top: 32px;">
            <h3 style="margin: 0 0 12px; font-size: 14px; color: #1d2327;">📖 Documentation</h3>
            <p style="font-size: 11px; color: #888; margin: 0 0 12px;">✅ Written &nbsp; 🔲 Coming soon</p>
            <?php
            $current_group = '';
            foreach ($flosc_doc_topics as $topic):
                if ($topic['group'] !== $current_group):
                    $current_group = $topic['group'];
                    ?>
                    <div style="font-size: 11px; font-weight: 600; color: #666; margin: 14px 0 4px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <?php echo esc_html($group_labels[$current_group]); ?>
                    </div>
                <?php endif; ?>
                <?php
                $is_active = ($doc_topic === $topic['id']);
                $status_icon = $topic['status'] === 'ready' ? '✅' : '🔲';
                $link_url = add_query_arg([
                    'page' => 'flosc-settings',
                    'ivr'  => isset($selected_ivr) ? $selected_ivr : '',
                    'tab'  => 'documentation',
                    'doc'  => $topic['id'],
                ], admin_url('admin.php'));
                ?>
                     <a href="<?php echo esc_url($link_url); ?>"
                         style="display: block; padding: 5px 10px; margin: 1px 0; font-size: 13px; text-decoration: none; border-radius: 4px; color: <?php echo esc_attr($is_active ? '#fff' : '#2271b1'); ?>; background: <?php echo esc_attr($is_active ? '#2271b1' : 'transparent'); ?>;">
                          <?php echo esc_html($status_icon) . ' ' . esc_html($topic['title']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <!-- Content area -->
    <div class="flosc-docs-content" style="flex: 1; min-width: 0; max-width: 900px;">
        <?php if (empty($doc_topic)): ?>
            <!-- Landing page -->
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 24px;">
                <h2 style="margin-top: 0;">FLOSC Documentation</h2>
                <p>Reference manual for the FLOSC conversational learning platform. Select a topic from the sidebar, or start here:</p>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px;">
                    <?php foreach ($group_labels as $gid => $glabel):
                        // Find first topic in this group
                        $first = null;
                        foreach ($flosc_doc_topics as $t) {
                            if ($t['group'] === $gid) { $first = $t; break; }
                        }
                        if (!$first) continue;
                        $ready_count = 0;
                        $total_count = 0;
                        foreach ($flosc_doc_topics as $t) {
                            if ($t['group'] === $gid) {
                                $total_count++;
                                if ($t['status'] === 'ready') $ready_count++;
                            }
                        }
                        $card_url = add_query_arg([
                            'page' => 'flosc-settings',
                            'ivr'  => isset($selected_ivr) ? $selected_ivr : '',
                            'tab'  => 'documentation',
                            'doc'  => $first['id'],
                        ], admin_url('admin.php'));
                    ?>
                        <a href="<?php echo esc_url($card_url); ?>" style="display: block; padding: 16px; background: #f6f7f7; border: 1px solid #ddd; border-radius: 6px; text-decoration: none; color: #1d2327;">
                            <strong style="font-size: 14px;"><?php echo esc_html($glabel); ?></strong>
                            <span style="display: block; font-size: 12px; color: #888; margin-top: 4px;"><?php echo esc_html((string) $ready_count); ?>/<?php echo esc_html((string) $total_count); ?> sections written</span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 20px; padding: 12px 16px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 0 4px 4px 0; font-size: 13px;">
                    <strong>Help links:</strong> Throughout the FLOSC admin, you'll see expandable reference panels (like the Conditions and Actions references in IVR Messages). Those inline references will link to the full documentation here as sections are completed.
                </div>
            </div>

        <?php elseif ($doc_topic === 'the-journey'): ?>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 24px;" class="flosc-doc-article">
                <?php include FLOSC_PLUGIN_DIR . 'admin/docs/part1-journey.php'; ?>
            </div>

        <?php elseif ($doc_topic === 'ref-quiz'): ?>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 24px;" class="flosc-doc-article">
                <?php include FLOSC_PLUGIN_DIR . 'admin/docs/part3-ref-quiz.php'; ?>
            </div>

        <?php elseif ($doc_topic === 'ref-audio-quiz-flow'): ?>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 24px;" class="flosc-doc-article">
                <?php include FLOSC_PLUGIN_DIR . 'admin/docs/part3-ref-audio-quiz-flow.php'; ?>
            </div>

        <?php elseif ($doc_topic === 'glossary'): ?>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 24px;" class="flosc-doc-article">
                <?php include FLOSC_PLUGIN_DIR . 'admin/docs/part5-glossary.php'; ?>
            </div>

        <?php elseif ($doc_topic === 'ref-core'): ?>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 24px;" class="flosc-doc-article">
                <?php include FLOSC_PLUGIN_DIR . 'admin/docs/ref_core_skeleton.php'; ?>
            </div>

        <?php elseif ($doc_topic === 'ref-admin'): ?>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 24px;" class="flosc-doc-article">
                <?php include FLOSC_PLUGIN_DIR . 'admin/docs/ref_admin_skeleton.php'; ?>
            </div>

        <?php elseif ($doc_topic === 'ref-ai-config'): ?>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 24px;" class="flosc-doc-article">
                <?php
                // Suppress the tab header when including guide from documentation
                $GLOBALS['flosc_suppress_tab_header'] = true;
                include FLOSC_PLUGIN_DIR . 'admin/ai-configuration-guide.php';
                unset($GLOBALS['flosc_suppress_tab_header']);
                ?></div>

        <?php else: ?>
            <?php
            // Pending topic — show heading skeleton
            $current_topic = null;
            foreach ($flosc_doc_topics as $t) {
                if ($t['id'] === $doc_topic) { $current_topic = $t; break; }
            }
            if ($current_topic):
            ?>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 24px;">
                <h2 style="margin-top: 0;"><?php echo esc_html($current_topic['title']); ?></h2>
                <div style="padding: 16px; background: #fff8e5; border: 1px solid #f0c36d; border-radius: 4px; margin-bottom: 20px;">
                    <strong>🔲 Content pending</strong> — This section has a heading structure prepared. Content will be written as the corresponding features stabilize.
                </div>
                <?php
                // Load the skeleton file if it exists
                $skeleton_file = FLOSC_PLUGIN_DIR . 'admin/docs/' . str_replace('-', '_', $doc_topic) . '_skeleton.php';
                if (file_exists($skeleton_file)) {
                    include $skeleton_file;
                }
                ?>
            </div>
            <?php else: ?>
                <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 24px;">
                    <p>Topic not found. Select a topic from the sidebar.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php // §12: .flosc-doc-article styles moved to assets/css/flosc-admin.css (enqueued on FLOSC admin pages). ?>
