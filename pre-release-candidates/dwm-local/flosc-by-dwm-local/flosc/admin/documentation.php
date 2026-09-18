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
    ['id' => 'ref-settings-fields',  'group' => 'reference', 'title' => 'Flow Settings Fields (Portable)',      'status' => 'ready'],
    ['id' => 'ref-ai',               'group' => 'reference', 'title' => 'AI & RAG Reference',                  'status' => 'pending'],
    ['id' => 'ref-ai-config',        'group' => 'reference', 'title' => 'AI Configuration Guide',               'status' => 'ready'],
    ['id' => 'ref-payments',         'group' => 'reference', 'title' => 'Payments & Offers Reference',         'status' => 'pending'],
    ['id' => 'ref-access',           'group' => 'reference', 'title' => 'Access Control Reference',            'status' => 'pending'],
    ['id' => 'ref-sso',              'group' => 'reference', 'title' => 'SSO & OAuth Reference',               'status' => 'pending'],
    // Part 4: Security
    ['id' => 'security',             'group' => 'security', 'title' => 'Security',                             'status' => 'pending'],
    // Part 5: Glossary
    ['id' => 'glossary',             'group' => 'glossary', 'title' => 'Glossary — Every FLOSC Term Defined',  'status' => 'ready'],
    // Part 6: Development
    ['id' => 'development-team',     'group' => 'development', 'title' => 'Team',                                               'status' => 'ready', 'anchor' => 'team'],
    ['id' => 'development-devnotes', 'group' => 'development', 'title' => 'Devnotes',                                           'status' => 'ready', 'anchor' => 'devnotes'],
    ['id' => 'development-future',   'group' => 'development', 'title' => 'Future Features',                                    'status' => 'ready', 'anchor' => 'future-features'],
    ['id' => 'development-wishlist', 'group' => 'development', 'title' => 'User Wishlist',                                      'status' => 'ready', 'anchor' => 'user-wishlist'],
];

// Which topic is currently selected? ($flosc_get from settings.php when included).
if ( ! isset( $flosc_get ) || ! is_array( $flosc_get ) ) {
	$flosc_get = array();
}
$flosc_doc_topic = isset( $flosc_get['doc'] ) ? sanitize_text_field( (string) $flosc_get['doc'] ) : '';

// Group labels for the sidebar
$flosc_group_labels = [
    'journey'      => 'Part 1: The Journey',
    'architecture' => 'Part 2: Architecture',
    'reference'    => 'Part 3: Reference',
    'security'     => 'Part 4: Security',
    'glossary'     => 'Part 5: Glossary',
    'development'  => 'Part 6: Development',
];

// First topic per group (used for clickable part headings and landing cards).
$flosc_group_first_topic = [];
foreach ($flosc_doc_topics as $flosc_topic) {
    if (!isset($flosc_group_first_topic[$flosc_topic['group']])) {
        $flosc_group_first_topic[$flosc_topic['group']] = $flosc_topic;
    }
}
?>

<div class="flosc-docs-wrap flosc-docs-layout">

    <!-- Sidebar TOC -->
    <nav class="flosc-docs-sidebar flosc-docs-sidebar-layout">
        <div class="flosc-docs-sidebar-sticky">
            <h3 class="flosc-docs-sidebar-title">📖 Documentation</h3>
            <p class="flosc-docs-sidebar-legend">✅ Written &nbsp; 🔲 Coming soon</p>
            <?php
            $flosc_current_group = '';
            foreach ($flosc_doc_topics as $flosc_topic):
                if ($flosc_topic['group'] !== $flosc_current_group):
                    $flosc_current_group = $flosc_topic['group'];
                    $flosc_group_heading_url = '';
                    if (isset($flosc_group_first_topic[$flosc_current_group])) {
                        $flosc_group_heading_topic = $flosc_group_first_topic[$flosc_current_group];
                        $flosc_group_heading_url = add_query_arg([
                            'page' => 'flosc-settings',
                            'ivr'  => isset($selected_ivr) ? $selected_ivr : '',
                            'tab'  => 'documentation',
                            'doc'  => $flosc_group_heading_topic['id'],
                        ], admin_url('admin.php'));
                        if (!empty($flosc_group_heading_topic['anchor'])) {
                            $flosc_group_heading_url .= '#' . sanitize_key($flosc_group_heading_topic['anchor']);
                        }
                    }
                    ?>
                    <div class="flosc-docs-group-title">
                        <?php if (!empty($flosc_group_heading_url)): ?>
                            <a href="<?php echo esc_url($flosc_group_heading_url); ?>" class="flosc-docs-group-link">
                                <?php echo esc_html($flosc_group_labels[$flosc_current_group]); ?>
                            </a>
                        <?php else: ?>
                            <?php echo esc_html($flosc_group_labels[$flosc_current_group]); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php
                $flosc_is_active = ($flosc_doc_topic === $flosc_topic['id']);
                $flosc_status_icon = $flosc_topic['status'] === 'ready' ? '✅' : '🔲';
                $flosc_link_url = add_query_arg([
                    'page' => 'flosc-settings',
                    'ivr'  => isset($selected_ivr) ? $selected_ivr : '',
                    'tab'  => 'documentation',
                    'doc'  => $flosc_topic['id'],
                ], admin_url('admin.php'));
                if (!empty($flosc_topic['anchor'])) {
                    $flosc_link_url .= '#' . sanitize_key($flosc_topic['anchor']);
                }
                ?>
                     <a href="<?php echo esc_url($flosc_link_url); ?>"
                         class="flosc-docs-topic-link <?php echo esc_attr( $flosc_is_active ? 'flosc-docs-topic-link--active' : '' ); ?>">
                          <?php echo esc_html($flosc_status_icon) . ' ' . esc_html($flosc_topic['title']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <!-- Content area -->
    <div class="flosc-docs-content flosc-docs-content-layout">
        <?php if (empty($flosc_doc_topic)): ?>
            <!-- Landing page -->
            <div class="flosc-doc-card">
                <h2 class="flosc-doc-card-title">FLOSC Documentation</h2>
                <p>Reference manual for the FLOSC conversational learning platform. Select a topic from the sidebar, or start here:</p>

                <div class="flosc-doc-card-grid">
                    <?php foreach ($flosc_group_labels as $flosc_gid => $flosc_glabel):
                        // Find first topic in this group
                        $flosc_first = isset($flosc_group_first_topic[$flosc_gid]) ? $flosc_group_first_topic[$flosc_gid] : null;
                        if (!$flosc_first) continue;
                        $flosc_ready_count = 0;
                        $flosc_total_count = 0;
                        foreach ($flosc_doc_topics as $flosc_t) {
                            if ($flosc_t['group'] === $flosc_gid) {
                                $flosc_total_count++;
                                if ($flosc_t['status'] === 'ready') $flosc_ready_count++;
                            }
                        }
                        $flosc_card_url = add_query_arg([
                            'page' => 'flosc-settings',
                            'ivr'  => isset($selected_ivr) ? $selected_ivr : '',
                            'tab'  => 'documentation',
                            'doc'  => $flosc_first['id'],
                        ], admin_url('admin.php'));
                    ?>
                        <a href="<?php echo esc_url($flosc_card_url); ?>" class="flosc-doc-card-link">
                            <strong class="flosc-doc-card-link-title"><?php echo esc_html($flosc_glabel); ?></strong>
                            <span class="flosc-doc-card-link-meta"><?php echo esc_html((string) $flosc_ready_count); ?>/<?php echo esc_html((string) $flosc_total_count); ?> sections written</span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="flosc-doc-help-note">
                    <strong>Help links:</strong> Throughout the FLOSC admin, you'll see expandable reference panels (like the Conditions and Actions references in IVR Messages). Those inline references will link to the full documentation here as sections are completed.
                </div>
            </div>

        <?php elseif ($flosc_doc_topic === 'the-journey'): ?>
            <div class="flosc-doc-article flosc-doc-card">
                <?php include FLOSC_PLUGIN_DIR . 'admin/docs/part1-journey.php'; ?>
            </div>

        <?php elseif ($flosc_doc_topic === 'ref-quiz'): ?>
            <div class="flosc-doc-article flosc-doc-card">
                <?php include FLOSC_PLUGIN_DIR . 'admin/docs/part3-ref-quiz.php'; ?>
            </div>

        <?php elseif ($flosc_doc_topic === 'ref-audio-quiz-flow'): ?>
            <div class="flosc-doc-article flosc-doc-card">
                <?php include FLOSC_PLUGIN_DIR . 'admin/docs/part3-ref-audio-quiz-flow.php'; ?>
            </div>

        <?php elseif ($flosc_doc_topic === 'glossary'): ?>
            <div class="flosc-doc-article flosc-doc-card">
                <?php include FLOSC_PLUGIN_DIR . 'admin/docs/part5-glossary.php'; ?>
            </div>

        <?php elseif (in_array($flosc_doc_topic, ['development-team', 'development-devnotes', 'development-future', 'development-wishlist'], true)): ?>
            <div class="flosc-doc-article flosc-doc-card">
                <?php include FLOSC_PLUGIN_DIR . 'admin/docs/part6-development.php'; ?>
            </div>

        <?php elseif ($flosc_doc_topic === 'ref-core'): ?>
            <div class="flosc-doc-article flosc-doc-card">
                <?php include FLOSC_PLUGIN_DIR . 'admin/docs/ref_core_skeleton.php'; ?>
            </div>

        <?php elseif ($flosc_doc_topic === 'ref-admin'): ?>
            <div class="flosc-doc-article flosc-doc-card">
                <?php include FLOSC_PLUGIN_DIR . 'admin/docs/ref_admin_skeleton.php'; ?>
            </div>

        <?php elseif ($flosc_doc_topic === 'ref-settings-fields'): ?>
            <div class="flosc-doc-article flosc-doc-card">
                <?php include FLOSC_PLUGIN_DIR . 'admin/docs/part3-ref-settings-fields.php'; ?>
            </div>

        <?php elseif ($flosc_doc_topic === 'ref-ai-config'): ?>
            <div class="flosc-doc-article flosc-doc-card">
                <?php
                // Suppress the tab header when including guide from documentation
                $GLOBALS['flosc_suppress_tab_header'] = true;
                include FLOSC_PLUGIN_DIR . 'admin/ai-configuration-guide.php';
                unset($GLOBALS['flosc_suppress_tab_header']);
                ?></div>

        <?php else: ?>
            <?php
            // Pending topic — show heading skeleton
            $flosc_current_topic = null;
            foreach ($flosc_doc_topics as $flosc_t) {
                if ($flosc_t['id'] === $flosc_doc_topic) { $flosc_current_topic = $flosc_t; break; }
            }
            if ($flosc_current_topic):
            ?>
            <div class="flosc-doc-card">
                <h2 class="flosc-doc-card-title"><?php echo esc_html($flosc_current_topic['title']); ?></h2>
                <div class="flosc-doc-pending-note">
                    <strong>🔲 Content pending</strong> — This section has a heading structure prepared. Content will be written as the corresponding features stabilize.
                </div>
                <?php
                // Load the skeleton file if it exists
                $flosc_skeleton_file = FLOSC_PLUGIN_DIR . 'admin/docs/' . str_replace('-', '_', $flosc_doc_topic) . '_skeleton.php';
                if (file_exists($flosc_skeleton_file)) {
                    include $flosc_skeleton_file;
                }
                ?>
            </div>
            <?php else: ?>
                <div class="flosc-doc-card">
                    <p>Topic not found. Select a topic from the sidebar.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php // §12: .flosc-doc-article styles moved to assets/css/flosc-admin.css (enqueued on FLOSC admin pages). ?>
