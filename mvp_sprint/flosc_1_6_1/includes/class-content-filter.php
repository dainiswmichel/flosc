<?php
/**
 * FLOSC Content Filter
 * Filters content based on user access level
 * 
 * IMPORTANT: This filter ONLY applies to posts that explicitly contain
 * the <!--flosc_read_more--> tag. All other WordPress content is left untouched.
 * 
 * @since 9.1.6
 * @updated 1.0.0 - Added safeguards to prevent affecting non-FLOSC content
 */

if (!defined('ABSPATH')) exit;

class flosc_content_filter {
    
    private static $instance = null;
    
    private function __construct() {
        // Register WordPress content filter hook
        // Only applies when content contains FLOSC markers
        add_filter('the_content', [$this, 'apply_content_filter'], 10);
    }
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * WordPress the_content filter wrapper
     * Automatically detects user access level and filters content
     * 
     * CRITICAL: Only processes content that contains FLOSC-specific markers.
     * Regular WordPress posts are returned unchanged immediately.
     * 
     * @param string $content Post content from WordPress
     * @return string Filtered content (or original if no FLOSC markers)
     */
    public function apply_content_filter($content) {
        // SAFEGUARD 1: Skip if we're in the admin area
        if (is_admin()) {
            return $content;
        }

        // SAFEGUARD 2: Admin users on the frontend see everything unfiltered
        if (current_user_can('manage_options')) {
            return $content;
        }

        // SAFEGUARD 3: Only filter on singular pages, not archives/indexes
        if (!is_singular()) {
            return $content;
        }

        // SAFEGUARD 4: Skip if this is a REST API request (unless FLOSC-specific)
        if (defined('REST_REQUEST') && REST_REQUEST) {
            // Only process if this is a FLOSC REST endpoint
            $request_uri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($request_uri, '/flosc/') === false) {
                return $content;
            }
        }

        // SAFEGUARD 5: Skip if content doesn't contain FLOSC markers
        // This is the PRIMARY check - if no FLOSC tag, return content unchanged
        if (strpos($content, '<!--flosc_read_more') === false &&
            strpos($content, '### ACCESS LEVEL:') === false) {
            return $content;
        }

        // Content has FLOSC markers, proceed with filtering
        try {
            require_once FLOSC_PLUGIN_DIR . 'includes/class-user-access-manager.php';
            $access_manager = FLOSC_User_Access_Manager::instance();

            // Get current user's access level
            $user_context = $access_manager->get_user_context();
            $access_level = $user_context['access_level'] ?? 'visitor';

            // Apply filtering using existing method
            return $this->filter_post_content($content, $access_level);
        } catch (\Throwable $e) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                error_log('FLOSC Content Filter error: ' . $e->getMessage());
            }
            return $content;
        }
    }
    
    /**
     * Filter markdown content by access level
     * Looks for ### ACCESS LEVEL: VISITOR/GUEST/MEMBER markers
     * 
     * @param string $content Raw markdown content
     * @param string $access_level 'visitor', 'guest', or 'member'
     * @return string Filtered content
     */
    public function filter_markdown_by_access($content, $access_level) {
        
        $sections = $this->parse_markdown_sections($content);
        
        // Determine which sections user can access
        $allowed_sections = [];
        switch ($access_level) {
            case 'visitor':
                $allowed_sections = ['visitor'];
                break;
            case 'guest':
                $allowed_sections = ['visitor', 'guest'];
                break;
            case 'member':
                $allowed_sections = ['visitor', 'guest', 'member'];
                break;
        }
        
        // Combine allowed sections
        $filtered = [];
        foreach ($allowed_sections as $level) {
            if (isset($sections[$level]) && !empty($sections[$level])) {
                $filtered = array_merge($filtered, $sections[$level]);
            }
        }
        
        return implode("\n\n---\n\n", $filtered);
    }
    
    /**
     * Parse markdown content into sections by access level
     * 
     * @param string $content
     * @return array Sections organized by level
     */
    private function parse_markdown_sections($content) {
        
        $sections = [
            'visitor' => [],
            'guest' => [],
            'member' => []
        ];
        
        $current_level = 'visitor'; // Default
        $current_content = '';
        
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            // Check for access level marker
            // Matches: ### ACCESS LEVEL: VISITOR or ## ACCESS LEVEL: MEMBER etc
            if (preg_match('/^###?\s*ACCESS LEVEL:\s*(VISITOR|GUEST|MEMBER)/i', $line, $matches)) {
                
                // Save previous section
                if (trim($current_content)) {
                    $sections[$current_level][] = trim($current_content);
                }
                
                // Start new section
                $current_level = strtolower($matches[1]);
                $current_content = '';
                continue;
            }
            
            $current_content .= $line . "\n";
        }
        
        // Save last section
        if (trim($current_content)) {
            $sections[$current_level][] = trim($current_content);
        }
        
        return $sections;
    }
    
    /**
     * Check if user has access to content requiring specific level
     * 
     * @param string $required_level Level required for content
     * @param string $user_level User's current level
     * @return bool
     */
    public function has_access($required_level, $user_level) {
        $hierarchy = ['visitor' => 0, 'guest' => 1, 'member' => 2];
        
        $required = $hierarchy[$required_level] ?? 0;
        $current = $hierarchy[$user_level] ?? 0;
        
        return $current >= $required;
    }
    
    /**
     * Filter WordPress post content by access level
     * Handles <!--flosc_read_more--> tag for member-only content
     * Uses custom tag to avoid conflicts with WordPress core <!--more-->
     * 
     * @param string $content Post content
     * @param string $access_level
     * @return string Filtered content
     */
    public function filter_post_content($content, $access_level) {
        
        // Split by <!--flosc_read_more--> tag, fall back to WordPress <!--more-->
        $parts = preg_split('/<!--flosc_read_more(.*?)?-->/', $content, -1);
        
        if (count($parts) <= 1) {
            // v1.5.5: Fallback to WordPress <!--more--> tag for compatibility
            $parts = preg_split('/<!--more(.*?)?-->/', $content, -1);
        }
        
        if (count($parts) <= 1) {
            // No read-more tag found at all
            return $content;
        }
        
        $public_content = $parts[0];
        $member_content = implode("\n\n", array_slice($parts, 1));
        
        switch ($access_level) {
            case 'visitor':
                // Only public content
                return $public_content;
                
            case 'guest':
                // Public + teaser
                return $public_content . "\n\n" . 
                       "🔒 **Member-only content available below**\n" .
                       "Complete the quiz to unlock full access!";
                
            case 'member':
                // Everything
                return $content;
        }
        
        return $public_content;
    }
    
    /**
     * Extract relevant section from content based on query
     * Used for RAG to return focused results
     * 
     * @param string $content
     * @param string $query
     * @param int $context_chars Number of characters of context
     * @return string
     */
    public function extract_relevant_section($content, $query, $context_chars = 500) {
        
        $query_lower = strtolower($query);
        $content_lower = strtolower($content);
        
        // Find position of query
        $pos = strpos($content_lower, $query_lower);
        
        if ($pos === false) {
            // Query not found, return beginning
            return substr($content, 0, $context_chars) . '...';
        }
        
        // Get context around the query
        $start = max(0, $pos - ($context_chars / 2));
        $extracted = substr($content, $start, $context_chars);
        
        // Add ellipsis if truncated
        if ($start > 0) {
            $extracted = '...' . $extracted;
        }
        if (strlen($content) > ($start + $context_chars)) {
            $extracted .= '...';
        }
        
        return $extracted;
    }
    
    /**
     * Get excerpt from content
     * 
     * @param string $content
     * @param int $length Word count
     * @return string
     */
    public function get_excerpt($content, $length = 50) {
        
        // Strip HTML and shortcodes
        $content = wp_strip_all_tags($content);
        $content = strip_shortcodes($content);
        
        // Get words
        $words = explode(' ', $content);
        
        if (count($words) <= $length) {
            return $content;
        }
        
        $excerpt = implode(' ', array_slice($words, 0, $length));
        return $excerpt . '...';
    }
}
