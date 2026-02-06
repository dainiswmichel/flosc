<?php
/**
 * FLOSC Admin Settings Page
 */

if (!defined('ABSPATH')) exit;

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'product';
$slug = get_option('flosc_app_slug', 'flosc');
$app_url = home_url('/' . $slug . '/');
?>
<div class="wrap flosc-admin">
    <h1>FLOSC Settings</h1>
    
    <div class="flosc-admin-header">
        <p>Your app is live at: <a href="<?php echo esc_url($app_url); ?>" target="_blank"><?php echo esc_html($app_url); ?></a></p>
    </div>
    
    <nav class="nav-tab-wrapper">
        <a href="?page=flosc-settings&tab=product" class="nav-tab <?php echo $active_tab === 'product' ? 'nav-tab-active' : ''; ?>">Product</a>
        <a href="?page=flosc-settings&tab=ivr-messages" class="nav-tab <?php echo $active_tab === 'ivr-messages' ? 'nav-tab-active' : ''; ?>">IVR Messages</a>
        <a href="?page=flosc-settings&tab=style" class="nav-tab <?php echo $active_tab === 'style' ? 'nav-tab-active' : ''; ?>">Chat Styling</a>
        <a href="?page=flosc-settings&tab=ai" class="nav-tab <?php echo $active_tab === 'ai' ? 'nav-tab-active' : ''; ?>">AI Configuration</a>
        <a href="?page=flosc-settings&tab=quiz" class="nav-tab <?php echo $active_tab === 'quiz' ? 'nav-tab-active' : ''; ?>">Quiz</a>
        <a href="?page=flosc-settings&tab=email" class="nav-tab <?php echo $active_tab === 'email' ? 'nav-tab-active' : ''; ?>">Email</a>
        <a href="?page=flosc-settings&tab=ai-knowledge" class="nav-tab <?php echo $active_tab === 'ai-knowledge' ? 'nav-tab-active' : ''; ?>">AI Knowledge</a>
        <a href="?page=flosc-settings&tab=offers" class="nav-tab <?php echo $active_tab === 'offers' ? 'nav-tab-active' : ''; ?>">Offers</a>
        <a href="?page=flosc-settings&tab=payments" class="nav-tab <?php echo $active_tab === 'payments' ? 'nav-tab-active' : ''; ?>">Payments</a>
        <a href="?page=flosc-settings&tab=lessons" class="nav-tab <?php echo $active_tab === 'lessons' ? 'nav-tab-active' : ''; ?>">Lessons</a>
        <a href="?page=flosc-settings&tab=bridge" class="nav-tab <?php echo $active_tab === 'bridge' ? 'nav-tab-active' : ''; ?>">Bridge Analytics</a>
    </nav>
    
    <form method="post" action="options.php">
        <?php settings_fields('flosc_settings'); ?>
        
        <?php if ($active_tab === 'product'): ?>
            <?php include plugin_dir_path(__FILE__) . 'product.php'; ?>

        <?php elseif ($active_tab === 'ivr-messages'): ?>
            <?php include plugin_dir_path(__FILE__) . 'ivr-messages.php'; ?>

        <?php elseif ($active_tab === 'style'): ?>
            <?php include plugin_dir_path(__FILE__) . 'chat-styling.php'; ?>

        <?php elseif ($active_tab === 'ai'): ?>
            <?php include plugin_dir_path(__FILE__) . 'ai-configuration.php'; ?>

        <?php elseif ($active_tab === 'quiz'): ?>
            <?php include plugin_dir_path(__FILE__) . 'quiz.php'; ?>

        <?php elseif ($active_tab === 'lessons'): ?>
            <?php include plugin_dir_path(__FILE__) . 'lessons.php'; ?>

        <?php elseif ($active_tab === 'email'): ?>
            <?php include plugin_dir_path(__FILE__) . 'email.php'; ?>

        <?php elseif ($active_tab === 'ai-knowledge'): ?>
            <?php include plugin_dir_path(__FILE__) . 'ai-knowledge.php'; ?>

        <?php elseif ($active_tab === 'offers'): ?>
            <?php include plugin_dir_path(__FILE__) . 'offers.php'; ?>

        <?php elseif ($active_tab === 'payments'): ?>
            <?php include plugin_dir_path(__FILE__) . 'payments.php'; ?>

        <?php elseif ($active_tab === 'bridge'): ?>
            <?php include plugin_dir_path(__FILE__) . 'bridge-analytics.php'; ?>

        <?php endif; ?>
        
        <?php submit_button(); ?>
    </form>
</div>

<style>
.flosc-admin { max-width: 900px; }
.flosc-admin-header { background: #f0f0f1; padding: 15px; margin: 20px 0; border-radius: 4px; }
.flosc-admin .nav-tab-wrapper { margin-bottom: 20px; }
.flosc-info-box { background: #f9f9f9; border-left: 4px solid #2271b1; padding: 15px 20px; margin: 20px 0; }
.flosc-info-box ol { margin: 10px 0 0 20px; }
.flosc-info-box ul { margin: 5px 0 5px 20px; list-style-type: disc; }
</style>
