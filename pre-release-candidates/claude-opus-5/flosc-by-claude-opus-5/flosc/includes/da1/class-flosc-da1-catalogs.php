<?php
/**
 * FLOSC DA1 content-agnostic catalog runtime (TSV-backed).
 *
 * DA1 stores catalog structure and access/delivery controls. Catalog payload
 * columns are intentionally unrestricted. Dublin Core-compatible field names
 * (Title, Creator, Subject, Description, Publisher, Contributor, Date, Type,
 * Format, Identifier, Source, Language, Relation, Coverage, Rights) are
 * recognized when present, but are never required or injected into a catalog.
 *
 * @package FLOSC
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FLOSC_DA1_Catalogs {

    /**
     * Canonical DA1 control columns. Everything else is catalog payload.
     */
    private const CONTROL_COLUMNS = array(
        'Row Key',
        'Parent Key',
        'Catalog Key',
        'Item Type',
        'Flow Scope',
        'VGM',
        'Delivery Instruction',
        'Delivery Rule',
        'Fallback Order',
        'Status',
    );

    /**
     * Build a deterministic catalog reply when the user clearly asks about an
     * assigned DA1 catalog or names an available item.
     *
     * @param string $message      User message.
     * @param string $flow_id      Current flow id/stem.
     * @param string $ivr_file     Current IVR filename.
     * @param string $access_level visitor|guest|member.
     * @return string
     */
    public function build_catalog_reply( $message, $flow_id, $ivr_file, $access_level = 'visitor' ) {
        $rows = $this->load_rows_for_flow( $flow_id, $ivr_file, $access_level );
        if ( empty( $rows ) ) {
            return '';
        }

        $items = $this->extract_items( $rows );
        if ( empty( $items ) || ! $this->is_catalog_query( $message, $items ) ) {
            return '';
        }

        if ( $this->is_count_request( $message ) ) {
            $reply = sprintf(
                /* translators: %d: number of DA1 catalog items available to the current user */
                _n(
                    'You currently have access to %d catalog item.',
                    'You currently have access to %d catalog items.',
                    count( $items ),
                    'flosc'
                ),
                count( $items )
            );
            return $this->limit_chat_response_length( $reply );
        }

        $matches = $this->find_matching_items( $message, $items );

        if ( $this->is_full_list_request( $message ) ) {
            $slice = array_slice( $items, 0, 20 );
            $lines = array();
            foreach ( $slice as $index => $item ) {
                $lines[] = ( $index + 1 ) . '. ' . $this->get_item_label( $item );
            }
            if ( count( $items ) > count( $slice ) ) {
                $lines[] = sprintf(
                    /* translators: 1: number shown, 2: total available catalog items */
                    __( 'Showing %1$d of %2$d available items. Ask for a title, creator, category, subject, tag, or a few recommendations.', 'flosc' ),
                    count( $slice ),
                    count( $items )
                );
            }
            return $this->limit_chat_response_length( implode( "\n", $lines ) );
        }

        $max_items = $this->detect_batch_size( $message );
        $slice     = ! empty( $matches ) ? array_slice( $matches, 0, $max_items ) : array_slice( $items, 0, $max_items );

        $lines = array(
            1 === count( $slice )
                ? __( 'Here is one catalog item:', 'flosc' )
                : sprintf(
                    /* translators: %d: number of catalog items */
                    __( 'Here are %d catalog items:', 'flosc' ),
                    count( $slice )
                ),
        );

        foreach ( $slice as $index => $item ) {
            $lines = array_merge( $lines, $this->render_item_lines( $item, $index + 1 ) );
        }

        return $this->limit_chat_response_length( implode( "\n", $lines ) );
    }

    /**
     * Conservative catalog-intent check. The runtime is called on every chat
     * turn, so it must not hijack unrelated conversation.
     */
    public function is_catalog_query( $message, $items = array() ) {
        $text = $this->normalize_search_text( $message );
        if ( '' === $text ) {
            return false;
        }

        if ( preg_match( '/\b(catalog|catalogue|item|items|browse|recommend|recommendation|recommendations|suggest|suggestion|suggestions|full list|complete list|show all|how many|count|total)\b/u', $text ) ) {
            return true;
        }

        $query_tokens = $this->search_tokens( $text );
        foreach ( (array) $items as $item ) {
            $label = $this->normalize_search_text( $this->get_item_label( $item ) );
            if ( '' !== $label && false !== strpos( $text, $label ) ) {
                return true;
            }

            $label_tokens = $this->search_tokens( $label );
            if ( count( array_intersect( $query_tokens, $label_tokens ) ) >= 2 ) {
                return true;
            }

            $item_type = $this->normalize_search_text( (string) ( $item['controls']['Item Type'] ?? '' ) );
            if ( '' !== $item_type ) {
                if ( false !== strpos( $text, $item_type ) ) {
                    return true;
                }
                foreach ( $this->search_tokens( $item_type ) as $item_type_token ) {
                    if ( false !== strpos( $text, $item_type_token ) ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function is_count_request( $message ) {
        $text = $this->normalize_search_text( $message );
        return (bool) preg_match( '/\b(how many|number of|count|total|cik)\b/u', $text );
    }

    public function is_full_list_request( $message ) {
        $text = $this->normalize_search_text( $message );
        return (bool) preg_match( '/\b(full list|complete list|entire catalog|entire catalogue|show all|list all|everything)\b/u', $text );
    }

    public function detect_batch_size( $message ) {
        $text = $this->normalize_search_text( $message );
        if ( preg_match( '/\b(one|1|single)\b/u', $text ) ) {
            return 1;
        }
        if ( preg_match( '/\b(three|3|few|some|several|options|suggestions|recommendations)\b/u', $text ) ) {
            return 3;
        }
        return 2;
    }

    /**
     * Load assigned catalog rows and enforce DA1 controls before any row can be
     * exposed to the chat layer.
     */
    public function load_rows_for_flow( $flow_id, $ivr_file, $access_level = 'visitor' ) {
        $upload_dir = wp_upload_dir();
        $catalog_dir = trailingslashit( (string) ( $upload_dir['basedir'] ?? '' ) ) . 'flosc-catalogs';
        if ( ! is_dir( $catalog_dir ) ) {
            return array();
        }

        $assignments  = get_option( 'flosc_da1_flow_catalogs', array() );
        $catalog_keys = array();
        if ( is_array( $assignments ) && '' !== $ivr_file && ! empty( $assignments[ $ivr_file ] ) && is_array( $assignments[ $ivr_file ] ) ) {
            $catalog_keys = array_values(
                array_unique(
                    array_filter(
                        array_map(
                            static function ( $key ) {
                                return preg_replace( '/[^a-z0-9._-]/i', '', strtolower( trim( (string) $key ) ) );
                            },
                            $assignments[ $ivr_file ]
                        )
                    )
                )
            );
        }
        if ( empty( $catalog_keys ) ) {
            return array();
        }

        $flow_scope_tokens = array();
        if ( '' !== $flow_id ) {
            $flow_scope_tokens[] = strtolower( trim( (string) $flow_id ) );
        }
        if ( '' !== $ivr_file ) {
            $flow_scope_tokens[] = strtolower( trim( (string) pathinfo( $ivr_file, PATHINFO_FILENAME ) ) );
        }
        $flow_scope_tokens = array_values( array_unique( array_filter( $flow_scope_tokens ) ) );

        $access_level = $this->normalize_access_level( $access_level );
        $rows_out     = array();
        $fs           = class_exists( 'FLOSC_Filesystem' ) ? new FLOSC_Filesystem() : null;

        foreach ( $catalog_keys as $catalog_key ) {
            $path = trailingslashit( $catalog_dir ) . 'flosc_da1_catalog_' . $catalog_key . '.tsv';
            if ( ! file_exists( $path ) ) {
                continue;
            }

            $content = $fs ? $fs->read_file_safely( $path ) : false;
            if ( false === $content || '' === trim( (string) $content ) ) {
                continue;
            }

            $parsed = $this->parse_tsv_content( $content );
            if ( count( $parsed ) < 2 ) {
                continue;
            }

            $header = array_map( array( $this, 'canonicalize_column_name' ), (array) $parsed[0] );
            $catalog_rows = array();
            $status_by_key = array();
            $parent_by_key = array();

            foreach ( array_slice( $parsed, 1 ) as $row ) {
                if ( ! is_array( $row ) ) {
                    continue;
                }

                $assoc = array();
                foreach ( $header as $index => $column ) {
                    if ( '' === $column ) {
                        continue;
                    }
                    $assoc[ $column ] = isset( $row[ $index ] ) ? trim( (string) $row[ $index ] ) : '';
                }

                $row_key = trim( (string) ( $assoc['Row Key'] ?? '' ) );
                if ( '' !== $row_key ) {
                    $status_by_key[ $row_key ] = strtolower( trim( (string) ( $assoc['Status'] ?? 'active' ) ) );
                    $parent_by_key[ $row_key ] = trim( (string) ( $assoc['Parent Key'] ?? '' ) );
                }
                $catalog_rows[] = $assoc;
            }

            foreach ( $catalog_rows as $assoc ) {
                $status = strtolower( trim( (string) ( $assoc['Status'] ?? 'active' ) ) );
                if ( '' !== $status && 'active' !== $status ) {
                    continue;
                }

                if ( $this->has_inactive_ancestor( $assoc, $status_by_key, $parent_by_key ) ) {
                    continue;
                }

                if ( ! $this->row_matches_flow_scope( $assoc, $flow_scope_tokens ) ) {
                    continue;
                }

                if ( ! $this->row_allows_audience( $assoc, $access_level ) ) {
                    continue;
                }

                $rows_out[] = $assoc;
            }
        }

        return $rows_out;
    }

    /**
     * Convert visible rows into content-agnostic catalog items.
     */
    public function extract_items( $rows ) {
        $children_by_parent = array();
        foreach ( (array) $rows as $row ) {
            $parent_key = trim( (string) ( $row['Parent Key'] ?? '' ) );
            if ( '' === $parent_key ) {
                continue;
            }
            $children_by_parent[ $parent_key ][] = $row;
        }

        $items = array();
        foreach ( (array) $rows as $row ) {
            $parent_key = trim( (string) ( $row['Parent Key'] ?? '' ) );
            if ( '' !== $parent_key ) {
                continue;
            }

            $controls = array();
            $payload  = array();
            foreach ( $row as $column => $value ) {
                $column = $this->canonicalize_column_name( $column );
                if ( in_array( $column, self::CONTROL_COLUMNS, true ) ) {
                    $controls[ $column ] = (string) $value;
                } else {
                    $payload[ $column ] = (string) $value;
                }
            }

            $row_key = trim( (string) ( $controls['Row Key'] ?? '' ) );
            $children = array();
            foreach ( (array) ( $children_by_parent[ $row_key ] ?? array() ) as $child ) {
                $child_payload = array();
                foreach ( $child as $column => $value ) {
                    $column = $this->canonicalize_column_name( $column );
                    if ( ! in_array( $column, self::CONTROL_COLUMNS, true ) ) {
                        $child_payload[ $column ] = (string) $value;
                    }
                }
                $children[] = $child_payload;
            }

            $items[] = array(
                'controls'    => $controls,
                'payload'     => $payload,
                'dublin_core' => $this->extract_dublin_core_metadata( $payload ),
                'children'    => $children,
            );
        }

        return $items;
    }

    /**
     * Dublin Core compatibility layer. It recognizes DC/DCMI field names without
     * forcing them into the source catalog. Categories and Tags can supplement
     * Subject when a catalog chooses to use those DA1-friendly fields.
     */
    public function extract_dublin_core_metadata( $payload ) {
        $dc_fields = array(
            'title', 'creator', 'subject', 'description', 'publisher',
            'contributor', 'date', 'type', 'format', 'identifier', 'source',
            'language', 'relation', 'coverage', 'rights',
        );
        $dc = array();

        foreach ( (array) $payload as $column => $value ) {
            $key = strtolower( trim( (string) $column ) );
            $key = preg_replace( '/^(dc:|dcterms:)/', '', $key );
            if ( in_array( $key, $dc_fields, true ) && '' !== trim( (string) $value ) ) {
                $dc[ $key ] = trim( (string) $value );
            }
        }

        if ( empty( $dc['subject'] ) ) {
            $subject_parts = array();
            foreach ( array( 'Categories', 'Category', 'Tags', 'Tag' ) as $column ) {
                if ( ! empty( $payload[ $column ] ) ) {
                    $subject_parts[] = trim( (string) $payload[ $column ] );
                }
            }
            if ( ! empty( $subject_parts ) ) {
                $dc['subject'] = implode( '; ', array_unique( $subject_parts ) );
            }
        }

        return $dc;
    }

    public function find_matching_items( $message, $items ) {
        $query_tokens = $this->search_tokens( $message );
        if ( empty( $query_tokens ) ) {
            return array();
        }

        $scored = array();
        foreach ( (array) $items as $index => $item ) {
            $label       = $this->normalize_search_text( $this->get_item_label( $item ) );
            $item_type   = $this->normalize_search_text( (string) ( $item['controls']['Item Type'] ?? '' ) );
            $dc          = (array) ( $item['dublin_core'] ?? array() );
            $subject     = $this->normalize_search_text( (string) ( $dc['subject'] ?? '' ) );
            $description = $this->normalize_search_text( (string) ( $dc['description'] ?? '' ) );
            $payload     = $this->normalize_search_text( implode( ' ', array_values( (array) ( $item['payload'] ?? array() ) ) ) );
            $score       = 0;

            foreach ( $query_tokens as $token ) {
                if ( false !== strpos( $label, $token ) ) {
                    $score += 8;
                }
                if ( '' !== $item_type && false !== strpos( $item_type, $token ) ) {
                    $score += 5;
                }
                if ( '' !== $subject && false !== strpos( $subject, $token ) ) {
                    $score += 4;
                }
                if ( '' !== $description && false !== strpos( $description, $token ) ) {
                    $score += 2;
                }
                if ( '' !== $payload && false !== strpos( $payload, $token ) ) {
                    $score += 1;
                }
            }

            if ( $score > 0 ) {
                $scored[] = array(
                    'score' => $score,
                    'index' => $index,
                    'item'  => $item,
                );
            }
        }

        usort(
            $scored,
            static function ( $a, $b ) {
                if ( $a['score'] === $b['score'] ) {
                    return $a['index'] <=> $b['index'];
                }
                return $b['score'] <=> $a['score'];
            }
        );

        return array_values( array_map( static function ( $entry ) { return $entry['item']; }, $scored ) );
    }

    public function parse_tsv_content( $content ) {
        $rows      = array();
        $row       = array();
        $field     = '';
        $in_quotes = false;
        $len       = strlen( (string) $content );

        for ( $i = 0; $i < $len; $i++ ) {
            $ch = $content[ $i ];
            if ( $in_quotes ) {
                if ( '"' === $ch ) {
                    if ( $i + 1 < $len && '"' === $content[ $i + 1 ] ) {
                        $field .= '"';
                        $i++;
                    } else {
                        $in_quotes = false;
                    }
                } else {
                    $field .= $ch;
                }
            } elseif ( '"' === $ch ) {
                $in_quotes = true;
            } elseif ( "\t" === $ch ) {
                $row[] = $field;
                $field = '';
            } elseif ( "\n" === $ch ) {
                $row[] = $field;
                $rows[] = $row;
                $row = array();
                $field = '';
            } elseif ( "\r" !== $ch ) {
                $field .= $ch;
            }
        }

        if ( '' !== $field || ! empty( $row ) ) {
            $row[] = $field;
            $rows[] = $row;
        }

        return $rows;
    }

    public function shorten_text( $text, $limit ) {
        $text = trim( (string) $text );
        if ( '' === $text ) {
            return '';
        }
        if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
            if ( mb_strlen( $text, 'UTF-8' ) <= $limit ) {
                return $text;
            }
            return rtrim( mb_substr( $text, 0, max( 1, $limit - 1 ), 'UTF-8' ) ) . '...';
        }
        if ( strlen( $text ) <= $limit ) {
            return $text;
        }
        return rtrim( substr( $text, 0, max( 1, $limit - 1 ) ) ) . '...';
    }

    public function limit_chat_response_length( $text ) {
        $raw_limit = (string) flosc_get_setting( 'ai_max_response_length', '' );
        $numeric   = preg_replace( '/[^0-9]/', '', $raw_limit );
        $max       = intval( $numeric );
        if ( $max < 240 || $max > 4000 ) {
            $max = 900;
        }
        return $this->shorten_text( $text, $max );
    }

    private function canonicalize_column_name( $column ) {
        $column = trim( (string) $column );
        if ( 'Record Type' === $column ) {
            return 'Item Type';
        }
        return $column;
    }

    private function normalize_access_level( $access_level ) {
        $access_level = strtolower( trim( (string) $access_level ) );
        return in_array( $access_level, array( 'visitor', 'guest', 'member' ), true ) ? $access_level : 'visitor';
    }

    /**
     * Numeric rank for an access level. Higher sees more.
     *
     * @param string $access_level visitor|guest|member.
     * @return int
     */
    private function access_rank( $access_level ) {
        $ranks = array( 'visitor' => 1, 'guest' => 2, 'member' => 3 );
        $key   = $this->normalize_access_level( $access_level );
        return $ranks[ $key ];
    }

    /**
     * Rank a VGM cell demands.
     *
     * VGM names the LOWEST tier that may see the row, so a Guest row is also
     * visible to Members. An empty cell, or "all", leaves the row ungated so
     * that catalogs which do not use access control still work. A non-empty
     * value that is not recognised is treated as member-only: somebody meant
     * to gate that row and mistyped it, and hiding it is the safe way to fail.
     *
     * @param string $vgm Raw cell value.
     * @return int
     */
    private function vgm_rank( $vgm ) {
        $value = strtolower( trim( (string) $vgm ) );

        if ( '' === $value || 'all' === $value ) {
            return 1;
        }

        $tokens = array_filter( array_map( 'trim', (array) preg_split( '/[\s,|\/]+/', $value ) ) );
        $lowest = null;

        foreach ( $tokens as $token ) {
            switch ( $token ) {
                case 'v':
                case 'visitor':
                case 'freeline':
                case 'public':
                case 'all':
                    $rank = 1;
                    break;
                case 'g':
                case 'guest':
                    $rank = 2;
                    break;
                case 'm':
                case 'member':
                    $rank = 3;
                    break;
                default:
                    $rank = 3;
            }

            if ( null === $lowest || $rank < $lowest ) {
                $lowest = $rank;
            }
        }

        return null === $lowest ? 3 : (int) $lowest;
    }

    private function row_allows_audience( $row, $access_level ) {
        return $this->access_rank( $access_level ) >= $this->vgm_rank( $row['VGM'] ?? '' );
    }

    /**
     * Whether any ancestor of a row is not active.
     *
     * Parent Key describes a hierarchy, so pausing a parent has to hide its
     * whole subtree, not just its immediate children. The seen-map keeps a
     * malformed catalog with a cycle from looping forever.
     *
     * @param array<string,string> $row           Row being considered.
     * @param array<string,string> $status_by_key Row Key => status.
     * @param array<string,string> $parent_by_key Row Key => Parent Key.
     * @return bool
     */
    private function has_inactive_ancestor( $row, $status_by_key, $parent_by_key ) {
        $parent_key = trim( (string) ( $row['Parent Key'] ?? '' ) );
        $seen       = array();

        while ( '' !== $parent_key && ! isset( $seen[ $parent_key ] ) ) {
            $seen[ $parent_key ] = true;

            if ( isset( $status_by_key[ $parent_key ] ) && 'active' !== $status_by_key[ $parent_key ] ) {
                return true;
            }

            $parent_key = isset( $parent_by_key[ $parent_key ] ) ? trim( (string) $parent_by_key[ $parent_key ] ) : '';
        }

        return false;
    }

    private function row_matches_flow_scope( $row, $flow_scope_tokens ) {
        $scope = strtolower( trim( (string) ( $row['Flow Scope'] ?? 'all' ) ) );
        if ( '' === $scope || 'all' === $scope ) {
            return true;
        }
        if ( empty( $flow_scope_tokens ) ) {
            return false;
        }

        $allowed_scopes = array_filter( array_map( 'trim', explode( ',', $scope ) ) );
        foreach ( $allowed_scopes as $allowed_scope ) {
            if ( in_array( $allowed_scope, $flow_scope_tokens, true ) ) {
                return true;
            }
        }
        return false;
    }

    private function render_item_lines( $item, $number ) {
        $label = $this->get_item_label( $item );
        $dc    = (array) ( $item['dublin_core'] ?? array() );
        $lines = array( $number . '. ' . $label );

        if ( ! empty( $dc['creator'] ) ) {
            $lines[0] .= ' — ' . $dc['creator'];
        }

        if ( ! empty( $dc['description'] ) ) {
            $lines[] = $this->shorten_text( $dc['description'], 180 );
        }

        $url = $this->extract_primary_url_from_payload( (array) ( $item['payload'] ?? array() ) );
        if ( '' !== $url ) {
            $lines[] = 'Link: ' . $url;
        }

        return $lines;
    }

    private function get_item_label( $item ) {
        $dc      = (array) ( $item['dublin_core'] ?? array() );
        $payload = (array) ( $item['payload'] ?? array() );

        if ( ! empty( $dc['title'] ) ) {
            return (string) $dc['title'];
        }
        foreach ( array( 'Title', 'Name', 'Label' ) as $field ) {
            if ( ! empty( $payload[ $field ] ) ) {
                return trim( (string) $payload[ $field ] );
            }
        }
        if ( ! empty( $dc['identifier'] ) ) {
            return (string) $dc['identifier'];
        }

        $row_key = trim( (string) ( $item['controls']['Row Key'] ?? '' ) );
        return '' !== $row_key ? 'Item ' . $row_key : 'Catalog item';
    }

    private function extract_primary_url_from_payload( $payload ) {
        foreach ( (array) $payload as $value ) {
            $text = trim( (string) $value );
            if ( '' !== $text && preg_match( '/https?:\/\/[^\s"<>]+/i', $text, $match ) ) {
                return rtrim( (string) $match[0], '.,;!?)' );
            }
        }
        return '';
    }

    private function normalize_search_text( $text ) {
        $text = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text );
        $text = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text );
        return trim( (string) preg_replace( '/\s+/', ' ', (string) $text ) );
    }

    private function search_tokens( $text ) {
        $normalized = $this->normalize_search_text( $text );
        if ( '' === $normalized ) {
            return array();
        }

        $stopwords = array(
            'the', 'and', 'for', 'with', 'that', 'this', 'from', 'show', 'give',
            'tell', 'about', 'some', 'item', 'items', 'catalog', 'catalogue',
            'please', 'would', 'could', 'want', 'need', 'have', 'your', 'what',
        );
        $tokens = preg_split( '/\s+/u', $normalized );
        $tokens = array_filter(
            (array) $tokens,
            static function ( $token ) use ( $stopwords ) {
                $length = function_exists( 'mb_strlen' ) ? mb_strlen( $token, 'UTF-8' ) : strlen( $token );
                return $length >= 3 && ! in_array( $token, $stopwords, true );
            }
        );

        return array_values( array_unique( $tokens ) );
    }
}
