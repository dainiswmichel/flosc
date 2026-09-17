<?php
/**
 * The global accessor for the SSO manager.
 *
 * It sits in its own file rather than at the foot of class-sso-manager.php,
 * because WordPress asks that a file hold either functions or classes and not
 * both. It keeps the FLOSC\SSO namespace: its fully qualified name is
 * FLOSC\SSO\flosc_sso(), and declaring it at global scope instead would create
 * a different function under a name that looks the same.
 *
 * @package FLOSC
 */

namespace FLOSC\SSO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The SSO manager: the providers, and the OAuth2 round trip.
 *
 * @return SSO_Manager The manager.
 */
function flosc_sso() {
	return SSO_Manager::get_instance();
}
