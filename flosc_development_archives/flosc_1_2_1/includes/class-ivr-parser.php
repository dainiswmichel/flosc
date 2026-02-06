<?php
/**
 * FLOSC IVR Parser
 * 
 * Parses ivr.md markdown format into structured configuration.
 * 
 * @since 7.0.8
 */

if (!defined('ABSPATH')) exit;

class FLOSC_IVR_Parser {
    
    private static $flosc_instance = null;
    private $flosc_config = null;
    
    public static function flosc_instance() {
        if (self::$flosc_instance === null) {
            self::$flosc_instance = new self();
        }
        return self::$flosc_instance;
    }
    
    /**
     * Parse IVR markdown content
     */
    public function flosc_parse($markdown) {
        $config = [
            'styles' => [],
            'variables' => [],
            'conditions' => [],
            'phases' => [
                'freeline' => [],
                'login' => [],
                'offer' => [],
                'sale' => [],
                'content' => [],
            ],
            'messages' => [],
        ];
        
        $lines = explode("\n", $markdown);
        $current_phase = null;
        $current_message = null;
        $in_style_block = false;
        $style_css = '';
        $style_name = '';
        $style_description = '';
        $in_message_content = false;
        $message_content_lines = [];
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            // Skip empty lines unless in message content
            if (empty($trimmed) && !$in_message_content) {
                continue;
            }
            
            // Phase headers - v1.0.9: Support both old and new naming conventions
            // Old: "# Freeline Messages", "# Login Messages", "# Sale Messages", etc.
            // New: "# Freeline Messages", "# Guest Messages", "# Member Messages"
            if (preg_match('/^#\s+(Freeline|Login|Guest|Offer|Sale|Member|Content)\s+Messages/i', $trimmed, $matches)) {
                // Save previous message if exists
                if ($current_message) {
                    if ($in_message_content) {
                        $current_message['content'] = trim(implode("\n", $message_content_lines));
                        $in_message_content = false;
                        $message_content_lines = [];
                    }
                    $this->flosc_add_message_to_config($config, $current_message, $current_phase);
                }
                // Map section names to FLOSC phases
                // v1.1.0: Member Messages → content phase (where most members are)
                //         first_message_after_purchase condition handles sale phase
                $section = strtolower($matches[1]);
                $phase_map = [
                    'freeline' => 'freeline',
                    'login' => 'login',
                    'guest' => 'login',      // v1.0.9: Guest Messages → login phase
                    'offer' => 'offer',
                    'sale' => 'sale',
                    'member' => 'content',   // v1.1.0: Member Messages → content phase
                    'content' => 'content',
                ];
                $current_phase = $phase_map[$section] ?? $section;
                $current_message = null;
                continue;
            }
            
            // MessageStyle block start
            if (preg_match('/^##\s+MessageStyle:\s*(\w+)/i', $trimmed, $matches)) {
                $in_style_block = true;
                $style_name = $matches[1];
                $style_css = '';
                $style_description = '';
                continue;
            }
            
            // Inside style block
            if ($in_style_block) {
                if (strpos($trimmed, 'Description:') === 0) {
                    $style_description = trim(substr($trimmed, 12));
                    continue;
                }
                if (strpos($trimmed, '.flosc-style-') === 0 || strpos($trimmed, '}') !== false || 
                    strpos($trimmed, '{') !== false || preg_match('/^\s*(background|border|padding|font|color|display|align|gap|min-width|text-align|border-radius)/', $trimmed)) {
                    $style_css .= $line . "\n";
                    continue;
                }
                // End of style block
                if (strpos($trimmed, '##') === 0 || strpos($trimmed, '---') === 0) {
                    $config['styles'][$style_name] = [
                        'name' => $style_name,
                        'description' => $style_description,
                        'css' => trim($style_css),
                    ];
                    $in_style_block = false;
                }
            }
            
            // Available Variables section
            if (strpos($trimmed, '## Available Variables') === 0) {
                continue;
            }
            
            // Available Conditions section
            if (strpos($trimmed, '## Available Conditions') === 0) {
                continue;
            }
            
            // Section divider - save current message
            if ($trimmed === '---') {
                if ($current_message) {
                    if ($in_message_content) {
                        $current_message['content'] = trim(implode("\n", $message_content_lines));
                        $in_message_content = false;
                        $message_content_lines = [];
                    }
                    $this->flosc_add_message_to_config($config, $current_message, $current_phase);
                    $current_message = null;
                }
                continue;
            }
            
            // Message header (## MessageName or ## Something descriptive)
            if (preg_match('/^##\s+(.+)$/', $trimmed, $matches) && !$in_style_block) {
                // Save previous message
                if ($current_message) {
                    if ($in_message_content) {
                        $current_message['content'] = trim(implode("\n", $message_content_lines));
                        $in_message_content = false;
                        $message_content_lines = [];
                    }
                    $this->flosc_add_message_to_config($config, $current_message, $current_phase);
                }
                // Start new message
                $current_message = [
                    'title' => $matches[1],
                    'name' => '',
                    'type' => 'auto',
                    'style' => 'pill',
                    'icon' => '',
                    'user_input' => '',
                    'action' => '',
                    'content' => '',
                    'conditions' => 'always',
                    'phase' => $current_phase,
                ];
                continue;
            }
            
            // Message properties
            if ($current_message && !$in_message_content) {
                if (preg_match('/^MessageName:\s*(.+)$/i', $trimmed, $matches)) {
                    $current_message['name'] = trim($matches[1]);
                    continue;
                }
                if (preg_match('/^MessageType:\s*(.+)$/i', $trimmed, $matches)) {
                    $current_message['type'] = trim($matches[1]);
                    continue;
                }
                if (preg_match('/^MessageStyle:\s*(.+)$/i', $trimmed, $matches)) {
                    $current_message['style'] = trim($matches[1]);
                    continue;
                }
                if (preg_match('/^Icon:\s*(.+)$/i', $trimmed, $matches)) {
                    $current_message['icon'] = trim($matches[1]);
                    continue;
                }
                if (preg_match('/^UserInput:\s*(.+)$/i', $trimmed, $matches)) {
                    $current_message['user_input'] = trim($matches[1]);
                    continue;
                }
                if (preg_match('/^Action:\s*(.+)$/i', $trimmed, $matches)) {
                    $current_message['action'] = trim($matches[1]);
                    continue;
                }
                if (preg_match('/^OfferID:\s*(.+)$/i', $trimmed, $matches)) {
                    $current_message['offer_id'] = trim($matches[1]);
                    continue;
                }
                if (preg_match('/^Price:\s*(.+)$/i', $trimmed, $matches)) {
                    $current_message['price'] = trim($matches[1]);
                    continue;
                }
                if (preg_match('/^DiscountPrice:\s*(.+)$/i', $trimmed, $matches)) {
                    $current_message['discount_price'] = trim($matches[1]);
                    continue;
                }
                if (preg_match('/^Timer:\s*(.+)$/i', $trimmed, $matches)) {
                    $current_message['timer'] = intval(trim($matches[1]));
                    continue;
                }
                // MTS-2026-02-03: [DISPLAY-FORMAT] Parse DisplayFormat for offers
                if (preg_match('/^DisplayFormat:\s*(.+)$/i', $trimmed, $matches)) {
                    $current_message['display_format'] = trim($matches[1]);
                    continue;
                }
                if (preg_match('/^MessageConditions:\s*(.+)$/i', $trimmed, $matches)) {
                    $current_message['conditions'] = trim($matches[1]);
                    continue;
                }
                if (preg_match('/^MessageContent:\s*(.*)$/i', $trimmed, $matches)) {
                    $in_message_content = true;
                    $message_content_lines = [];
                    if (!empty(trim($matches[1]))) {
                        $message_content_lines[] = trim($matches[1]);
                    }
                    continue;
                }
            }
            
            // Collecting message content (multi-line)
            if ($in_message_content) {
                // Check if we hit the next property or section
                if (preg_match('/^(MessageName|MessageType|MessageStyle|Icon|UserInput|Action|MessageConditions|##|---):/i', $trimmed) ||
                    strpos($trimmed, '##') === 0 || $trimmed === '---') {
                    // End of content
                    $current_message['content'] = trim(implode("\n", $message_content_lines));
                    $in_message_content = false;
                    $message_content_lines = [];
                    
                    // Re-process this line
                    if (preg_match('/^MessageConditions:\s*(.+)$/i', $trimmed, $matches)) {
                        $current_message['conditions'] = trim($matches[1]);
                    }
                } else {
                    $message_content_lines[] = $line;
                }
            }
        }
        
        // Save last message
        if ($current_message) {
            if ($in_message_content) {
                $current_message['content'] = trim(implode("\n", $message_content_lines));
            }
            $this->flosc_add_message_to_config($config, $current_message, $current_phase);
        }
        
        $this->flosc_config = $config;
        return $config;
    }
    
    /**
     * Add message to config
     */
    private function flosc_add_message_to_config(&$config, $message, $phase) {
        if (empty($message['name'])) {
            return;
        }
        
        $config['messages'][$message['name']] = $message;
        
        if ($phase && isset($config['phases'][$phase])) {
            $config['phases'][$phase][] = $message['name'];
        }
    }
    
    /**
     * Get parsed config
     */
    public function get_flosc_config() {
        if ($this->flosc_config === null) {
            $this->flosc_load_config();
        }
        return $this->flosc_config;
    }
    
    /**
     * Load config from file or option
     * v1.1.0: Also sync to database options for REST API consistency
     */
    public function flosc_load_config() {
        // Always reload from file to avoid stale cached configs
        
        // Parse from file
        $ivr_file = FLOSC_PLUGIN_DIR . 'ai_configuration_files/ivr.md';
        if (file_exists($ivr_file)) {
            $markdown = file_get_contents($ivr_file);
            $this->flosc_config = $this->flosc_parse($markdown);
            
            // v1.1.0: Sync to ALL database options (REST API uses these)
            update_option('flosc_ivr_config', $this->flosc_config);
            update_option('flosc_ivr_messages', $this->flosc_config['messages'] ?? []);
            update_option('flosc_ivr_phases', $this->flosc_config['phases'] ?? []);
            update_option('flosc_ivr_styles', $this->flosc_config['styles'] ?? []);
        } else {
            $this->flosc_config = $this->get_flosc_default_config();
        }
        
        return $this->flosc_config;
    }
    
    /**
     * Save and re-parse config
     * v9.2.4: Auto-sync to database for immediate frontend updates
     */
    public function flosc_save_config($markdown) {
        $ivr_file = FLOSC_PLUGIN_DIR . 'ai_configuration_files/ivr.md';
        file_put_contents($ivr_file, $markdown);
        $this->flosc_config = $this->flosc_parse($markdown);
        
        // Sync to ALL database options (v9.2.4: full persistence)
        update_option('flosc_ivr_config', $this->flosc_config);
        update_option('flosc_ivr_messages', $this->flosc_config['messages'] ?? []);
        update_option('flosc_ivr_phases', $this->flosc_config['phases'] ?? []);
        update_option('flosc_ivr_styles', $this->flosc_config['styles'] ?? []);
        update_option('flosc_ivr_last_sync', current_time('mysql'));
        
        return $this->flosc_config;
    }
    
    /**
     * Get messages for a phase
     */
    public function get_flosc_phase_messages($phase) {
        $config = $this->get_flosc_config();
        if (!isset($config['phases'][$phase])) {
            return [];
        }
        
        $messages = [];
        foreach ($config['phases'][$phase] as $name) {
            if (isset($config['messages'][$name])) {
                $messages[] = $config['messages'][$name];
            }
        }
        return $messages;
    }
    
    /**
     * Get message by name
     */
    public function get_flosc_message($name) {
        $config = $this->get_flosc_config();
        return $config['messages'][$name] ?? null;
    }
    
    /**
     * Get all styles
     */
    public function get_flosc_styles() {
        $config = $this->get_flosc_config();
        return $config['styles'] ?? [];
    }
    
    /**
     * Get CSS for all styles
     */
    public function get_flosc_styles_css() {
        $styles = $this->get_flosc_styles();
        $css = '';
        foreach ($styles as $style) {
            $css .= $style['css'] . "\n";
        }
        return $css;
    }
    
    /**
     * Get messages by type
     */
    public function get_flosc_messages_by_type($type) {
        $config = $this->get_flosc_config();
        $messages = [];
        foreach ($config['messages'] as $message) {
            if ($message['type'] === $type) {
                $messages[] = $message;
            }
        }
        return $messages;
    }
    
    /**
     * Get user autoprompts for a phase
     */
    public function get_flosc_user_autoprompts($phase) {
        $messages = $this->get_flosc_phase_messages($phase);
        return array_filter($messages, function($m) {
            return $m['type'] === 'suggested_user_autoprompt';
        });
    }
    
    /**
     * Get auto messages for a phase
     */
    public function get_flosc_auto_messages($phase) {
        $messages = $this->get_flosc_phase_messages($phase);
        return array_filter($messages, function($m) {
            return $m['type'] === 'auto';
        });
    }
    
    /**
     * Default config if no ivr.md exists
     */
    private function get_flosc_default_config() {
        return [
            'styles' => [],
            'variables' => [],
            'conditions' => [],
            'phases' => [
                'freeline' => [],
                'login' => [],
                'offer' => [],
                'sale' => [],
                'content' => [],
            ],
            'messages' => [],
        ];
    }
}
