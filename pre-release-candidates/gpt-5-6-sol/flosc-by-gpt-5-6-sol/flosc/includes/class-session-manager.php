<?php
/**
 * Compatibility shim — implementation lives at includes/sessions/class-flosc-session-manager.php
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'FLOSC_PLUGIN_DIR' ) ) {
	return;
}
require_once FLOSC_PLUGIN_DIR . 'includes/sessions/class-flosc-session-manager.php';
