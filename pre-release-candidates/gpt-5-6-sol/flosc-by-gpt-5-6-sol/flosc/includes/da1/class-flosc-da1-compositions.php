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
	 * Coordinate FLOSC DA1 Compositions behavior and the WordPress services used by its methods.
	 */
class FLOSC_DA1_Compositions extends FLOSC_DA1_Catalogs {
		/**
 * Build the structured value consumed by composition reply.
 *
		 * @deprecated Use build_catalog_reply().
 * @param mixed $message Input consumed by the Build the structured value consumed by composition reply. operation.
 * @param mixed $flow_id Flow identifier used to resolve flow-scoped configuration and state.
 * @param mixed $ivr_file IVR identifier or filename used to select the flow configuration.
 * @param mixed $access_level Input consumed by the Build the structured value consumed by composition reply. operation.
 * @return mixed Result produced by the composition reply operation.
		 */
		public function build_composition_reply( $message, $flow_id, $ivr_file, $access_level = 'visitor' ) {
			return $this->build_catalog_reply( $message, $flow_id, $ivr_file, $access_level );
		}

		/**
 * Determine whether the current state satisfies composition query.
 *
		 * @deprecated Use is_catalog_query().
 * @param mixed $message Input consumed by the Determine whether the current state satisfies composition query. operation.
 * @return bool Whether composition query applies to the current state.
		 */
		public function is_composition_query( $message ) {
			return $this->is_catalog_query( $message );
		}
	}
}
