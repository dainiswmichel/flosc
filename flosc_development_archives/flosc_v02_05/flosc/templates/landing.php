<?php
/**
 * =============================================================================
 * FLOSC LANDING PAGE TEMPLATE
 * =============================================================================
 * 
 * Minimal template that renders ONLY the chat interface.
 * Used by Domain Landing Mode feature.
 * 
 * When lesaep.com is configured in landing_domains, this template
 * renders at the root path (/) without any WordPress theme.
 * 
 * =============================================================================
 */

if (!defined('ABSPATH')) exit;

// Get FLOSC instance
global $flosc_framework;
if (!isset($flosc_framework)) {
    $flosc_framework = new FLOSC_Framework();
}

// Force enqueue assets (normally only loads on pages with shortcode)
wp_enqueue_style('flosc', FLOSC_PLUGIN_URL . 'assets/css/flosc.css', [], FLOSC_VERSION);
wp_enqueue_script('flosc', FLOSC_PLUGIN_URL . 'assets/js/flosc.js', [], FLOSC_VERSION, true);

// Get config for page title
$bot_name = $flosc_framework->get_config('chat_bot_name') ?: 'FLOSC';
$page_title = $bot_name . ' - Quick Assessment';

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    
    <title><?php echo esc_html($page_title); ?></title>
    
    <!-- Favicon -->
    <?php if (has_site_icon()) : ?>
        <?php wp_site_icon(); ?>
    <?php endif; ?>
    
    <!-- WordPress Head -->
    <?php wp_head(); ?>
    
    <style>
        /* =================================================================
         * FULLPAGE LANDING STYLES
         * Override any WordPress theme styles
         * ================================================================= */
        
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            height: 100% !important;
            overflow: hidden !important;
            background: #f8fafc !important;
        }
        
        /* Hide any WordPress theme elements */
        header, footer, nav, aside, .site-header, .site-footer, 
        .wp-site-blocks > header, .wp-site-blocks > footer {
            display: none !important;
        }
        
        /* Make chat fullscreen */
        #flosc-container {
            max-width: 100% !important;
            width: 100% !important;
            height: 100vh !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .flosc-chat-window {
            height: 100vh !important;
            max-height: 100vh !important;
            border-radius: 0 !important;
            border: none !important;
            box-shadow: none !important;
        }
        
        .flosc-messages {
            height: calc(100vh - 180px) !important;
        }
        
        /* On larger screens, center the chat with max-width */
        @media (min-width: 768px) {
            body {
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%) !important;
            }
            
            #flosc-container {
                max-width: 480px !important;
                height: auto !important;
                max-height: 90vh !important;
                margin: 20px !important;
            }
            
            .flosc-chat-window {
                height: 700px !important;
                max-height: 85vh !important;
                border-radius: 24px !important;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
            }
            
            .flosc-messages {
                height: calc(700px - 180px) !important;
                max-height: calc(85vh - 180px) !important;
            }
        }
        
        /* Mobile: truly fullscreen */
        @media (max-width: 767px) {
            body {
                background: #ffffff !important;
            }
        }
    </style>
</head>
<body class="flosc-landing-page">
    
    <?php
    // Render the chat interface
    echo $flosc_framework->render_chat(['fullpage' => 'yes']);
    ?>
    
    <?php wp_footer(); ?>
    
</body>
</html>
