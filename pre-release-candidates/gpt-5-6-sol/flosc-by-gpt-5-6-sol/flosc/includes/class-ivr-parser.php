<?php
/**
 * Compatibility shim — implementation lives at includes/portability/class-flosc-ivr-parser.php
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'FLOSC_PLUGIN_DIR' ) ) {
	return;
}
require_once FLOSC_PLUGIN_DIR . 'includes/portability/class-flosc-ivr-parser.php';
