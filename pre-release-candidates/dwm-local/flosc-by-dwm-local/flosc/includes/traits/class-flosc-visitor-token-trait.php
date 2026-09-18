<?php
/**
 * Compatibility shim — implementation lives at includes/tokens/class-flosc-visitor-token-trait.php
 *
 * @package FLOSC
 */
if (!defined('ABSPATH')) {
    exit;
}
if (!defined('FLOSC_PLUGIN_DIR')) {
    return;
}
require_once FLOSC_PLUGIN_DIR . 'includes/tokens/class-flosc-visitor-token-trait.php';
