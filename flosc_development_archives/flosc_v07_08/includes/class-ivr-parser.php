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
    
    private static $instance = null;
    private $config = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Parse IVR markdown content
     */
    public function parse($markdown) {
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
            
            // Phase headers
            if (preg_match('/^#\s+(Freeline|Login|Offer|Sale|Content)\s+Messages/i', $trimmed, $matches)) {
                // Save previous message if exists
                if ($current_message) {
                    if ($in_message_content) {
                        $current_message['content'] = trim(implode("\n", $message_content_lines));
                        $in_message_content = false;
                        $message_content_lines = [];
                    }
                    $this->add_message_to_config($config, $current_message, $current_phase);
                }
                $current_phase = strtolower($matches[1]);
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
                    $this->add_message_to_config($config, $current_message, $current_phase);
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
                    $this->add_message_to_config($config, $current_message, $current_phase);
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
            $this->add_message_to_config($config, $current_message, $current_phase);
        }
        
        $this->config = $config;
        return $config;
    }
    
    /**
     * Add message to config
     */
    private function add_message_to_config(&$config, $message, $phase) {
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
    public function get_config() {
        if ($this->config === null) {
            $this->load_config();
        }
        return $this->config;
    }
    
    /**
     * Load config from file or option
     */
    public function load_config() {
        // Try to get from option first
        $cached = get_option('flosc_ivr_config');
        if ($cached && is_array($cached)) {
            $this->config = $cached;
            return $this->config;
        }
        
        // Parse from file
        $ivr_file = FLOSC_PLUGIN_DIR . 'ai_configuration_files/ivr.md';
        if (file_exists($ivr_file)) {
            $markdown = file_get_contents($ivr_file);
            $this->config = $this->parse($markdown);
            update_option('flosc_ivr_config', $this->config);
        } else {
            $this->config = $this->get_default_config();
        }
        
        return $this->config;
    }
    
    /**
     * Save and re-parse config
     */
    public function save_config($markdown) {
        $ivr_file = FLOSC_PLUGIN_DIR . 'ai_configuration_files/ivr.md';
        file_put_contents($ivr_file, $markdown);
        $this->config = $this->parse($markdown);
        update_option('flosc_ivr_config', $this->config);
        return $this->config;
    }
    
    /**
     * Get messages for a phase
     */
    public function get_phase_messages($phase) {
        $config = $this->get_config();
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
    public function get_message($name) {
        $config = $this->get_config();
        return $config['messages'][$name] ?? null;
    }
    
    /**
     * Get all styles
     */
    public function get_styles() {
        $config = $this->get_config();
        return $config['styles'] ?? [];
    }
    
    /**
     * Get CSS for all styles
     */
    public function get_styles_css() {
        $styles = $this->get_styles();
        $css = '';
        foreach ($styles as $style) {
            $css .= $style['css'] . "\n";
        }
        return $css;
    }
    
    /**
     * Get messages by type
     */
    public function get_messages_by_type($type) {
        $config = $this->get_config();
        $messages = [];
        foreach ($config['messages'] as $message) {
            if ($message['type'] === $type) {
                $messages[] = $message;
            }
        }
        return $messages;
    }
    
    /**
     * Get suggested replies for a phase
     */
    public function get_suggested_replies($phase) {
        $messages = $this->get_phase_messages($phase);
        return array_filter($messages, function($m) {
            return $m['type'] === 'suggested_reply';
        });
    }
    
    /**
     * Get auto messages for a phase
     */
    public function get_auto_messages($phase) {
        $messages = $this->get_phase_messages($phase);
        return array_filter($messages, function($m) {
            return $m['type'] === 'auto';
        });
    }
    
    /**
     * Default config if no ivr.md exists
     */
    private function get_default_config() {
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
