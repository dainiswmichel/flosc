<?php
/**
 * The global accessors for FLOSC's singleton managers.
 *
 * Each of these used to sit at the foot of the class file it returns. That put a
 * function declaration and a class declaration in one file, which WordPress
 * asks you not to do, and they were identical enough that collecting them here
 * reads better than three one-line tails.
 *
 * FLOSC\SSO\flosc_sso() is not here. It is declared inside the FLOSC\SSO
 * namespace, so moving it to this file would create a differently named
 * function -- \flosc_sso() rather than \FLOSC\SSO\flosc_sso() -- which is a
 * change to an API rather than a move. It has its own file beside its class.
 *
 * Nothing is loaded by this file. Each body resolves its class only when the
 * function is called, so this can be required as early as the plugin likes --
 * which matters, because flosc_flows() is called during activation, before the
 * framework has loaded anything.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The flow manager: every flow on the site, and who may reach which.
 *
 * @return FLOSC_Flow_Manager The manager.
 */
function flosc_flows() {
	return FLOSC_Flow_Manager::instance();
}

/**
 * The site content index: what the RAG retrieval searches over.
 *
 * Calling this also registers the index's admin-post handlers, so the framework
 * calls it once during boot for that side effect as well as for the object.
 *
 * @return FLOSC_Site_Content_Index The index.
 */
function flosc_site_content_index() {
	return FLOSC_Site_Content_Index::instance();
}

/**
 * The sale manager: offers, payment providers and entitlements.
 *
 * @return FLOSC_Sale_Manager The manager.
 */
function flosc_sale() {
	return FLOSC_Sale_Manager::instance();
}
