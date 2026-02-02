<?php
/**
 * FLOSC RAG Manager
 * Retrieval Augmented Generation - AI search tools
 * 
 * STATUS: ✅ WORDPRESS SEARCH FUNCTIONAL | ⚙️ AI INTEGRATION OPTIONAL
 * 
 * FULLY FUNCTIONAL:
 * - search_posts() searches flosc_sample_data category ✅
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
     * @return string Tool result
     */
    public function execute_tool($tool_name, $input, $access_level) {
        
        error_log("FLOSC RAG: Executing tool '{$tool_name}' for access level '{$access_level}'");
        error_log("FLOSC RAG: Input - " . json_encode($input));
        
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
                        $access_level
                    );
                    
                case 'get_lesson_content':
                    $lesson_num = $input['lesson_number'] ?? null;
                    $post_id = $input['post_id'] ?? null;
                    return $this->get_lesson_content($lesson_num, $post_id, $access_level);
                    
                default:
                    return "Unknown tool: {$tool_name}";
            }
        } catch (Exception $e) {
            error_log("FLOSC RAG Error: " . $e->getMessage());
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
        $kb_path = WP_CONTENT_DIR . '/uploads/flosc-knowledge/';
        
        // PSEUDOCODE: For now, check if directory exists
        // In future, admin will upload files here via interface
        if (!is_dir($kb_path)) {
            error_log("FLOSC RAG: Knowledge base directory not found at {$kb_path}");
            return "Knowledge base not yet configured. Please add content files.";
        }
        
        // Search markdown files
        $files = glob($kb_path . '*.md');
        
        if (empty($files)) {
            return "No knowledge base files found.";
        }
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            
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
     * Search WordPress posts in flosc_sample_data category
     * 
     * @param string $keywords
     * @param int $limit
     * @param string $access_level
     * @return string
     */
    private function search_posts($keywords, $limit, $access_level) {
        
        // Search posts in 'flosc_sample_data' category
        $args = [
            's' => $keywords,
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'category_name' => 'flosc_sample_data'
        ];
        
        // Also support searching by lesson number (e.g., "1", "2", "10")
        if (is_numeric($keywords) && $keywords >= 1 && $keywords <= 10) {
            $args['meta_query'] = [
                [
                    'key' => '_flosc_lesson_number',
                    'value' => intval($keywords),
                    'compare' => '='
                ]
            ];
            unset($args['s']); // Don't use text search if searching by number
        }
        
        $posts = get_posts($args);
        
        if (empty($posts)) {
            return "No posts found for: {$keywords}";
        }
        
        $results = "**Found " . count($posts) . " lessons:**\n\n";
        
        foreach ($posts as $post) {
            
            // Get lesson number and access level from meta
            $lesson_num = get_post_meta($post->ID, '_flosc_lesson_number', true);
            $required_access = get_post_meta($post->ID, '_flosc_access_level', true) ?: 'member';
            
            $lesson_label = $lesson_num ? "Lesson {$lesson_num}: " : "";
            
            // Check if user has access to this post
            if (!$this->content_filter->has_access($required_access, $access_level)) {
                $results .= "**{$lesson_label}{$post->post_title}** 🔒\n";
                $results .= "Access: " . ucfirst($required_access) . " required\n\n";
                continue;
            }
            
            // Filter content by access level
            $content = $this->content_filter->filter_post_content($post->post_content, $access_level);
            $excerpt = $this->content_filter->get_excerpt($content, 40);
            
            // Build result
            $results .= "**{$lesson_label}{$post->post_title}**\n";
            $results .= "ID: {$post->ID} | URL: " . get_permalink($post->ID) . "\n";
            $results .= "{$excerpt}\n\n";
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
            $args = [
                'post_type' => 'post', // FUTURE: 'flosc_lesson'
                'posts_per_page' => 1,
                'meta_query' => [
                    [
                        'key' => '_flosc_lesson_number',
                        'value' => $lesson_number,
                        'compare' => '='
                    ]
                ]
            ];
            
            $posts = get_posts($args);
            if (!empty($posts)) {
                $post = $posts[0];
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
        
        // PSEUDOCODE: Query all flosc_lesson posts
        $args = [
            'post_type' => 'post', // FUTURE: 'flosc_lesson'
            'posts_per_page' => -1,
            'orderby' => 'meta_value_num',
            'meta_key' => '_flosc_lesson_number',
            'order' => 'ASC'
        ];
        
        $posts = get_posts($args);
        
        if (empty($posts)) {
            return "No lessons configured yet.";
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
