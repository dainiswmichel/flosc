<?php
/**
 * Backward-compatibility shim for pre-content-agnostic DA1 integrations.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-flosc-da1-catalogs.php';

if ( ! class_exists( 'FLOSC_DA1_Compositions' ) ) {
	/**
	 * Compositions.
	 */
	class FLOSC_DA1_Compositions extends FLOSC_DA1_Catalogs {
		/**
		 * Build composition reply.
		 *
		 * @deprecated Use build_catalog_reply().
		 *
		 * @param mixed  $message      Message.
		 * @param mixed  $flow_id      Flow ID.
		 * @param mixed  $ivr_file     IVR file.
		 * @param string $access_level Access level.
		 */
		public function build_composition_reply( $message, $flow_id, $ivr_file, $access_level = 'visitor' ) {
			return $this->build_catalog_reply( $message, $flow_id, $ivr_file, $access_level );
		}

		/**
		 * Is composition query.
		 *
		 * @deprecated Use is_catalog_query().
		 *
		 * @param mixed $message Message.
		 */
		public function is_composition_query( $message ) {
			return $this->is_catalog_query( $message );
		}
	}
}
