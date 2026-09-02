<?php
/**
 * Compatibility shim — implementation lives at includes/companion-mode/class-companion-widget.php
 *
 * @package FLOSC
 */
if (!defined('ABSPATH')) {
    exit;
}
if (!defined('FLOSC_PLUGIN_DIR')) {
    return;
}
require_once FLOSC_PLUGIN_DIR . 'includes/companion-mode/class-companion-widget.php';
