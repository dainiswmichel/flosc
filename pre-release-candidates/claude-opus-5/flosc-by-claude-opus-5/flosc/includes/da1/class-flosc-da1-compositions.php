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
    class FLOSC_DA1_Compositions extends FLOSC_DA1_Catalogs {
        /**
         * @deprecated Use build_catalog_reply().
         */
        public function build_composition_reply( $message, $flow_id, $ivr_file, $access_level = 'visitor' ) {
            return $this->build_catalog_reply( $message, $flow_id, $ivr_file, $access_level );
        }

        /**
         * @deprecated Use is_catalog_query().
         */
        public function is_composition_query( $message ) {
            return $this->is_catalog_query( $message );
        }
    }
}
