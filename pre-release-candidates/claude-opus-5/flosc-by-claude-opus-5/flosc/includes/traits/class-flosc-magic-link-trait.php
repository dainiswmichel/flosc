<?php
/**
 * Compatibility shim — implementation lives at includes/magic-link/class-flosc-magic-link-trait.php
 *
 * @package FLOSC
 */
if (!defined('ABSPATH')) {
    exit;
}
if (!defined('FLOSC_PLUGIN_DIR')) {
    return;
}
require_once FLOSC_PLUGIN_DIR . 'includes/magic-link/class-flosc-magic-link-trait.php';
