<?php
/**
 * FLOSC RAG Manager
 * Retrieval Augmented Generation - AI search tools
 *
 * STATUS: ✅ WORDPRESS SEARCH FUNCTIONAL | ⚙️ AI INTEGRATION OPTIONAL
 *
 * FULLY FUNCTIONAL:
 * - search_posts() searches flow's configured WP category ✅
 * - Searches by lesson number (1-10) or keywords ✅
 * - Filters by access level (visitor/guest/member) ✅
 * - Returns post title, excerpt, URL ✅
 *
 * OPTIONAL (Requires AI API):
 * - search_knowledge_base() for markdown files ⚙️
 * - get_lesson_content() for full post delivery ⚙️
 * - AI tool calling via Anthropic Claude API ⚙️
 *
 * NOTE: WordPress search works WITHOUT AI configured!
 *
 * @since 9.1.6
 * @since 1.9.0 Dynamic category ID (no longer hardcoded flosc_sample_data)
 */

if (!defined('ABSPATH')) exit;

class FLOSC_RAG_Manager {
    
    private static $instance = null;
    private $content_filter;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->content_filter = flosc_content_filter::instance();
    }
    
    /**
     * Get AI tools definition for Anthropic API
     * These tools allow AI to search WordPress
     * 
     * @return array
     */
    public function get_ai_tools() {
        return [
            [
                'name' => 'search_knowledge_base',
                'description' => 'Search the FLOSC knowledge base for information about pronunciation, numbers, or lessons. Use this when you need specific information to help the user.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Search query (e.g., "number 7", "pronunciation lesson")'
                        ],
                        'category' => [
                            'type' => 'string',
                            'description' => 'Optional category to narrow search',
                            'enum' => ['numbers', 'pronunciation', 'lessons', 'all']
                        ]
                    ],
                    'required' => ['query']
                ]
            ],
            [
                'name' => 'search_posts',
                'description' => 'Search WordPress posts for lessons and content',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'keywords' => [
                            'type' => 'string',
                            'description' => 'Keywords to search for'
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Number of results (default 5)',
                            'default' => 5
                        ]
                    ],
                    'required' => ['keywords']
                ]
            ],
            [
                'name' => 'get_lesson_content',
                'description' => 'Get full content of a specific lesson by number or ID',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'lesson_number' => [
                            'type' => 'integer',
                            'description' => 'Lesson number (1-10)'
                        ],
                        'post_id' => [
                            'type' => 'integer',
                            'description' => 'WordPress post ID'
                        ]
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Execute a tool call from the AI
     *
     * @param string $tool_name
     * @param array $input Tool parameters
     * @param string $access_level User's access level
     * @param int $category_id WordPress category ID for the flow (0 for none)
     * @return string Tool result
     */
    public function execute_tool($tool_name, $input, $access_level, $category_id = 0) {

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC RAG: Executing tool '{$tool_name}' for access level '{$access_level}' in category '{$category_id}'");
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC RAG: Input - " . wp_json_encode($input));
        }

        try {
            switch ($tool_name) {
                case 'search_knowledge_base':
                    return $this->search_knowledge_base(
                        $input['query'],
                        $access_level,
                        $input['category'] ?? 'all'
                    );

                case 'search_posts':
                    return $this->search_posts(
                        $input['keywords'],
                        $input['limit'] ?? 5,
                        $access_level,
                        $category_id
                    );

                case 'get_lesson_content':
                    $lesson_num = $input['lesson_number'] ?? null;
                    $post_id = $input['post_id'] ?? null;
                    return $this->get_lesson_content($lesson_num, $post_id, $access_level);

                default:
                    return "Unknown tool: {$tool_name}";
            }
        } catch (Exception $e) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC RAG Error: " . $e->getMessage());
            return "Error executing search: " . $e->getMessage();
        }
    }
    
    /**
     * Search knowledge base files
     * 
     * @param string $query
     * @param string $access_level
     * @param string $category
     * @return string
     */
    private function search_knowledge_base($query, $access_level, $category = 'all') {
        
        $results = [];
        
        // Get knowledge base path
        $kb_path = trailingslashit(wp_upload_dir()['basedir']) . 'flosc-knowledge/';
        
        // Check if knowledge-base directory exists
        // In future, admin will upload files here via interface
        if (!is_dir($kb_path)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC RAG: Knowledge base directory not found at {$kb_path}");
            return "Knowledge base not yet configured. Please add content files.";
        }
        
        // Search markdown files
        $files = glob($kb_path . '*.md');
        
        if (empty($files)) {
            return "No knowledge base files found.";
        }
        
        foreach ($files as $file) {
            $content = flosc_fs_get_contents($file);
            
            // Simple keyword search (case-insensitive)
            if (stripos($content, $query) !== false) {
                
                // Filter by access level
                $filtered = $this->content_filter->filter_markdown_by_access($content, $access_level);
                
                // Extract relevant section
                $relevant = $this->content_filter->extract_relevant_section($filtered, $query, 800);
                
                $results[] = [
                    'source' => basename($file),
                    'content' => $relevant
                ];
            }
        }
        
        if (empty($results)) {
            return "No results found in knowledge base for: {$query}";
        }
        
        // Format results
        $formatted = "**Knowledge Base Search Results for '{$query}':**\n\n";
        foreach ($results as $idx => $result) {
            $formatted .= "**Source: {$result['source']}**\n";
            $formatted .= $result['content'] . "\n\n";
            $formatted .= "---\n\n";
        }
        
        return $formatted;
    }
    
    /**
     * Search WordPress posts in flow's configured category
     *
     * @param string $keywords
     * @param int $limit
     * @param string $access_level
     * @param int $category_id WordPress category ID (0 for all categories)
     * @return string
     */
    private function search_posts($keywords, $limit, $access_level, $category_id = 0) {

        // Prefer the site content index when built for this flow (full bodies, selective).
        if ( class_exists( 'FLOSC_Site_Content_Index' ) ) {
            $index = FLOSC_Site_Content_Index::instance();
            $stem  = '';
            if ( function_exists( 'flosc' ) ) {
                $flow = flosc()->get_current_flow();
                if ( is_array( $flow ) && ! empty( $flow['ivr_file'] ) ) {
                    $stem = $index->stem_from_ivr( (string) $flow['ivr_file'] );
                } elseif ( is_array( $flow ) && ! empty( $flow['id'] ) ) {
                    $stem = sanitize_key( (string) $flow['id'] );
                }
            }
            if ( $stem === '' && ! empty( $GLOBALS['flosc_current_ivr'] ) ) {
                $stem = $index->stem_from_ivr( (string) $GLOBALS['flosc_current_ivr'] );
            }
            if ( $stem !== '' ) {
                $doc = $index->load( $stem );
                if ( ! empty( $doc['posts'] ) ) {
                    $from_index = $index->search( $stem, (string) $keywords, (string) $access_level, (int) $limit );
                    if ( is_string( $from_index ) && $from_index !== '' ) {
                        return $from_index;
                    }
                }
            }
        }

        // Fallback: live WordPress search in the flow category.
        $args = [
            's' => $keywords,
            'posts_per_page' => $limit,
            'post_status' => 'publish'
        ];

        if ($category_id > 0) {
            $args['cat'] = $category_id;
        }

        if (is_numeric($keywords) && $keywords >= 1 && $keywords <= 10) {
            unset($args['s']);
            $pids = function_exists( 'flosc_get_post_ids_for_meta' )
                ? flosc_get_post_ids_for_meta( '_flosc_lesson_number', (string) intval( $keywords ), 20 )
                : array();
            if ( empty( $pids ) ) {
                return "No posts found for: {$keywords}";
            }
            $args['post__in'] = $pids;
            $args['orderby']  = 'post__in';
        }

        $posts = get_posts($args);

        if (empty($posts)) {
            return "No posts found for: {$keywords}";
        }

        $results = "**Found " . count($posts) . " posts:**\n\n";

        foreach ($posts as $post) {

            $lesson_num = get_post_meta($post->ID, '_flosc_lesson_number', true);
            $required_access = get_post_meta($post->ID, '_flosc_access_level', true) ?: 'member';

            $lesson_label = $lesson_num ? "Lesson {$lesson_num}: " : "";

            if (!$this->content_filter->has_access($required_access, $access_level)) {
                $results .= "**{$lesson_label}{$post->post_title}** 🔒\n";
                $results .= "Access: " . ucfirst($required_access) . " required\n\n";
                continue;
            }

            // Full post body (access-filtered), not excerpt-only.
            $content = $this->content_filter->filter_post_content($post->post_content, $access_level);
            $content = wp_strip_all_tags( (string) $content );
            $content = preg_replace( '/\s+/u', ' ', $content );
            $content = is_string( $content ) ? trim( $content ) : '';

            $results .= "**{$lesson_label}{$post->post_title}**\n";
            $results .= "ID: {$post->ID} | URL: " . get_permalink($post->ID) . "\n\n";
            $results .= $content . "\n\n---\n\n";
        }

        return $results;
    }
    
    /**
     * Get specific lesson content
     * 
     * @param int $lesson_number
     * @param int $post_id
     * @param string $access_level
     * @return string
     */
    private function get_lesson_content($lesson_number, $post_id, $access_level) {
        
        $post = null;
        
        // Try to find by lesson number first
        if ($lesson_number) {
            $pids = function_exists( 'flosc_get_post_ids_for_meta' )
                ? flosc_get_post_ids_for_meta( '_flosc_lesson_number', (string) $lesson_number, 1 )
                : array();
            if ( ! empty( $pids ) ) {
                $post = get_post( (int) $pids[0] );
            }
        }
        
        // Fall back to post ID
        if (!$post && $post_id) {
            $post = get_post($post_id);
        }
        
        if (!$post) {
            return "Lesson not found.";
        }
        
        // Filter content by access level
        $content = $this->content_filter->filter_post_content($post->post_content, $access_level);
        
        // Build response
        $response = "**{$post->post_title}**\n\n";
        $response .= "URL: " . get_permalink($post->ID) . "\n\n";
        $response .= "---\n\n";
        $response .= $content;
        
        return $response;
    }
    
    /**
     * Get available lessons list
     * Used to tell AI what content exists
     * 
     * @param string $access_level
     * @return string
     */
    public function get_available_lessons($access_level) {

        // Scope to THIS flow's lesson categories. Without this the query returns
        // EVERY flow's lessons site-wide, so one chatbot (e.g. the WordPress host) would
        // surface another flow's lessons (e.g. pronunciation) — a cross-flow
        // bleed. Flow isolation is the whole point, so we resolve the flow's
        // categories the same way FLOSC_Lesson_Manager does and filter on them.
        $categories = [];
        if (function_exists('flosc')) {
            $flow = flosc()->get_current_flow();
            if ($flow) {
                if (!empty($flow['content_item_groups']) && is_array($flow['content_item_groups'])) {
                    foreach ($flow['content_item_groups'] as $group) {
                        if (!empty($group['category'])) {
                            $categories[] = $group['category'];
                        }
                    }
                }
                if (empty($categories) && !empty($flow['content_item_category'])) {
                    $categories[] = $flow['content_item_category'];
                }
            }
        }
        if (empty($categories)) {
            $global = get_option('flosc_content_item_category', '');
            if ($global !== '') {
                $categories[] = $global;
            }
        }

        // Resolve slugs / ids → term ids (mirrors FLOSC_Lesson_Manager::get_all_lessons).
        $cat_ids = [];
        foreach (array_unique($categories) as $cat) {
            if (is_numeric($cat)) {
                $cat_ids[] = intval($cat);
            } else {
                $term = get_term_by('slug', sanitize_title($cat), 'category');
                if ($term && !is_wp_error($term)) {
                    $cat_ids[] = $term->term_id;
                }
            }
        }
        if (empty($cat_ids)) {
            // This flow has no lessons of its own — say so rather than borrowing
            // another flow's library.
            return "No lessons configured for this flow.";
        }

        $posts = get_posts(
            array(
                'post_type'              => 'post', // FUTURE: 'flosc_lesson'
                'posts_per_page'         => -1,
                'category__in'           => $cat_ids, // flow-scoped
                'orderby'                => 'date',
                'order'                  => 'ASC',
                'update_post_meta_cache' => true,
            )
        );
        usort(
            $posts,
            static function ( $a, $b ) {
                $na = (float) get_post_meta( $a->ID, '_flosc_lesson_number', true );
                $nb = (float) get_post_meta( $b->ID, '_flosc_lesson_number', true );
                return $na <=> $nb;
            }
        );

        if (empty($posts)) {
            return "No lessons configured for this flow.";
        }
        
        $list = "**Available Lessons:**\n\n";
        
        foreach ($posts as $post) {
            $lesson_num = get_post_meta($post->ID, '_flosc_lesson_number', true);
            $lesson_access = get_post_meta($post->ID, '_flosc_access_level', true) ?: 'member';
            
            // Check if user can access
            $can_access = $this->can_user_access_level($access_level, $lesson_access);
            $status = $can_access ? "✓" : "🔒";
            
            $list .= "{$status} Lesson {$lesson_num}: {$post->post_title}\n";
            $list .= "   URL: " . get_permalink($post->ID) . "\n";
            $list .= "   Access: {$lesson_access}\n\n";
        }
        
        return $list;
    }
    
    /**
     * Helper to check access hierarchy
     * 
     * @param string $user_level
     * @param string $required_level
     * @return bool
     */
    private function can_user_access_level($user_level, $required_level) {
        $hierarchy = [
            'visitor' => 1,
            'guest' => 2,
            'member' => 3
        ];
        
        return ($hierarchy[$user_level] ?? 1) >= ($hierarchy[$required_level] ?? 1);
    }
}
