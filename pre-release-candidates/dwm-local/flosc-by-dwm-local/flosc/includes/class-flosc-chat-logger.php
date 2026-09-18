<?php
/**
 * Compatibility shim — implementation lives at includes/logging/class-flosc-chat-logger.php
 *
 * @package FLOSC
 */
if (!defined('ABSPATH')) {
    exit;
}
if (!defined('FLOSC_PLUGIN_DIR')) {
    return;
}
require_once FLOSC_PLUGIN_DIR . 'includes/logging/class-flosc-chat-logger.php';
