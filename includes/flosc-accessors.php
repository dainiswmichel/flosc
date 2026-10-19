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
 * Whether a value already taken from a request is one piece of text or a number.
 *
 * A PHP array or object is not. The caller has already checked the form token
 * and the capability, and has already copied the field into a local variable.
 * This function does not read $_POST, $_GET, or $_REQUEST.
 *
 * @param mixed $value Request value already copied out of the request.
 * @return bool
 */
function flosc_submitted_value_is_text( $value ) {
	return is_string( $value ) || is_numeric( $value );
}

/**
 * Whether every present field in a copied request is text or a number.
 *
 * A missing key is left to the caller. A present array or object fails the check.
 *
 * @param array $source Request fields already copied out of the request.
 * @param array $keys   Field names that must be one text value when present.
 * @return bool
 */
function flosc_submitted_fields_are_text( array $source, array $keys ) {
	foreach ( $keys as $key ) {
		if ( ! array_key_exists( $key, $source ) ) {
			continue;
		}
		if ( ! flosc_submitted_value_is_text( $source[ $key ] ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Whether every present named REST parameter is one text value or a number.
 *
 * Missing optional parameters are allowed. Arrays and objects are refused
 * before a handler can cast them to the word "Array" or persist a fallback.
 *
 * @param mixed $request REST request-like object.
 * @param array $keys    Parameter names that must be scalar when present.
 * @return bool
 */
function flosc_rest_fields_are_text( $request, array $keys ) {
	if ( ! is_object( $request ) || ! method_exists( $request, 'get_param' ) ) {
		return false;
	}
	foreach ( $keys as $key ) {
		$value = $request->get_param( $key );
		if ( null !== $value && ! flosc_submitted_value_is_text( $value ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Whether a copied value is a list and every entry in it is text or a number.
 *
 * A string is not a list. A list entry that is itself a list is not text.
 * This function does not read $_POST, $_GET, or $_REQUEST.
 *
 * @param mixed $value Request value already copied out of the request.
 * @return bool
 */
function flosc_submitted_list_is_text( $value ) {
	if ( ! is_array( $value ) ) {
		return false;
	}
	return flosc_submitted_fields_are_text( $value, array_keys( $value ) );
}

/**
 * Whether a copied request can be stored without turning a list into text.
 *
 * Keys in $list_keys must be lists of text. Every other present key must be
 * one text value, except keys in $ignored_keys, which the caller handles.
 * A missing key is left to the caller. This function does not read a
 * superglobal and does not change the copied values.
 *
 * @param array $source       Request fields already copied out of the request.
 * @param array $list_keys    Field names that are real lists of text.
 * @param array $ignored_keys Field names this check does not judge.
 * @return bool
 */
function flosc_submitted_request_is_text( array $source, array $list_keys = array(), array $ignored_keys = array() ) {
	foreach ( $source as $key => $value ) {
		if ( in_array( $key, $ignored_keys, true ) ) {
			continue;
		}
		if ( in_array( $key, $list_keys, true ) ) {
			if ( ! flosc_submitted_list_is_text( $value ) ) {
				return false;
			}
			continue;
		}
		if ( ! flosc_submitted_value_is_text( $value ) ) {
			return false;
		}
	}
	return true;
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
