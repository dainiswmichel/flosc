<?php
/**
 * FLOSC Content Filter
 * Filters content based on user access level
 * 
 * @since 9.1.6
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Content_Filter {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
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
     * Also supports legacy <!--more--> for backwards compatibility
     * 
     * @param string $content Post content
     * @param string $access_level
     * @return string Filtered content
     */
    public function filter_post_content($content, $access_level) {
        
        // Split by <!--flosc_read_more--> tag (primary)
        // Also check for legacy <!--more--> tag (backwards compatibility)
        $parts = preg_split('/<!--flosc_read_more(.*?)?-->|<!--more(.*?)?-->/', $content, -1);
        
        if (count($parts) <= 1) {
            // No split tag found, return all content if member
            return $access_level === 'member' ? $content : $content;
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
