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
                    'panel' => '', // v1.2.5: intro or prompt
                    'icon' => '',
                    'user_input' => '',
                    'keywords' => '',
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
                // v1.2.5: MessagePanel for IntroPanel vs PromptPanel
                if (preg_match('/^MessagePanel:\s*(.+)$/i', $trimmed, $matches)) {
                    $current_message['panel'] = trim($matches[1]);
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
                // v1.6.3: Keywords for fuzzy IVR matching
                if (preg_match('/^Keywords:\s*(.+)$/i', $trimmed, $matches)) {
                    $current_message['keywords'] = trim($matches[1]);
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
                // v1.6.2: Offer content source fields
                if (preg_match('/^HtmlFile:\s*(.+)$/i', $trimmed, $matches)) {
                    $current_message['html_file'] = trim($matches[1]);
                    continue;
                }
                if (preg_match('/^WooProduct:\s*(.+)$/i', $trimmed, $matches)) {
                    $current_message['woo_product'] = trim($matches[1]);
                    continue;
                }
                if (preg_match('/^PostID:\s*(.+)$/i', $trimmed, $matches)) {
                    $current_message['post_id'] = intval(trim($matches[1]));
                    continue;
                }
                if (preg_match('/^MessageConditions:\s*(.+)$/i', $trimmed, $matches)) {
                    $current_message['conditions'] = trim($matches[1]);
                    continue;
                }
                // v8.0.0: Concierge fields — a keyword-triggered message with an
                // optional password gate. PasswordRetry repeats, one line per try.
                if (preg_match('/^IndividualMessagePassword:\s*(.*)$/i', $trimmed, $matches)) {
                    $current_message['individual_message_password'] = trim($matches[1]);
                    continue;
                }
                if (preg_match('/^PasswordPrompt:\s*(.*)$/i', $trimmed, $matches)) {
                    $current_message['password_prompt'] = trim($matches[1]);
                    continue;
                }
                if (preg_match('/^PasswordSuccess:\s*(.*)$/i', $trimmed, $matches)) {
                    $current_message['password_success'] = trim($matches[1]);
                    continue;
                }
                if (preg_match('/^PasswordMaxTries:\s*(.+)$/i', $trimmed, $matches)) {
                    $current_message['password_max_tries'] = intval(trim($matches[1]));
                    continue;
                }
                if (preg_match('/^PasswordRetry:\s*(.*)$/i', $trimmed, $matches)) {
                    if (!isset($current_message['password_retry_messages']) || !is_array($current_message['password_retry_messages'])) {
                        $current_message['password_retry_messages'] = [];
                    }
                    $current_message['password_retry_messages'][] = trim($matches[1]);
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
                if (preg_match('/^(MessageName|MessageType|MessageStyle|MessagePanel|Icon|UserInput|Keywords|Action|OfferID|Price|DiscountPrice|Timer|DisplayFormat|HtmlFile|WooProduct|PostID|MessageConditions|IndividualMessagePassword|PasswordPrompt|PasswordSuccess|PasswordMaxTries|PasswordRetry|##|---):/i', $trimmed) ||
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
     * v1.2.3: Get IVR file path for current or specified flow.
     * Returns the appropriate ivr.md path based on flow configuration.
     * When $for_write is true, returns a path in uploads (for writes); otherwise,
     * may return a read-only shipped default.
     *
     * @param array|null $flow Flow to retrieve config for (optional)
     * @param bool $for_write If true, return a write target (uploads only)
     * @return string File path, or empty string if unavailable
     */
    private function get_ivr_file_path($flow = null, $for_write = false) {
        // Per WordPress.org policy: writable paths must be in uploads only.
        $writable_dir = function_exists('flosc_data_dir') ? flosc_data_dir() : '';

        // If $for_write is true, return the uploads target (or empty if uploads unavailable).
        // The target is always in uploads, even when the shipped default is all we have;
        // the save will create the uploads copy.
        if ($for_write) {
            if ('' === $writable_dir) {
                return '';
            }
            $filename = $flow && !empty($flow['ivr_file']) ? basename($flow['ivr_file']) : 'flosc_default_technical_ivr.md';
            return $writable_dir . $filename;
        }

        // For reads: check uploads first (per §2: uploads-first read order),
        // then fall back to shipped defaults. Both are allowed for reads.
        if ($flow && !empty($flow['ivr_file'])) {
            if ('' !== $writable_dir) {
                $path = $writable_dir . $flow['ivr_file'];
                if (file_exists($path)) {
                    return $path;
                }
            }
            $path = FLOSC_PLUGIN_DIR . 'ai_configuration_files/' . $flow['ivr_file'];
            if (file_exists($path)) {
                return $path;
            }
        }
        
        // Try to get current flow
        if (function_exists('flosc') && method_exists(flosc(), 'get_current_flow')) {
            $current_flow = flosc()->get_current_flow();
            if ($current_flow && !empty($current_flow['ivr_file'])) {
                if ('' !== $writable_dir) {
                    $path = $writable_dir . $current_flow['ivr_file'];
                    if (file_exists($path)) {
                        return $path;
                    }
                }
                $path = FLOSC_PLUGIN_DIR . 'ai_configuration_files/' . $current_flow['ivr_file'];
                if (file_exists($path)) {
                    return $path;
                }
            }
        }
        
        // Fallback to flosc_default_technical_ivr.md: uploads copy first, then shipped default.
        if ('' !== $writable_dir) {
            $writable_default = $writable_dir . 'flosc_default_technical_ivr.md';
            if (file_exists($writable_default)) {
                return $writable_default;
            }
        }
        return FLOSC_PLUGIN_DIR . 'ai_configuration_files/flosc_default_technical_ivr.md';
    }
    
    public function flosc_load_config() {
        // v1.2.3: Always reload from file - multi-flow aware, no global caching
        
        // v1.2.2: Use flow-aware IVR file path
        $ivr_file = $this->get_ivr_file_path();
        if (file_exists($ivr_file)) {
            $markdown = file_get_contents($ivr_file);
            $this->flosc_config = $this->flosc_parse($markdown);
            
            // v1.2.3: DO NOT sync to global wp_options - that would break multi-flow
            // Each flow has its own IVR file, parsed fresh per-request
        } else {
            $this->flosc_config = $this->get_flosc_default_config();
        }
        
        return $this->flosc_config;
    }
    
    /**
     * Save and re-parse config.
     * v1.2.3: Flow-aware -- can specify target flow for admin editing.
     * Per WordPress.org policy, writes are uploads-only and validated via realpath containment.
     * 
     * @param string $markdown The IVR markdown content
     * @param array|null $target_flow Optional flow to save to (for admin editing)
     * @return array|bool Parsed config on success, false if write failed or uploads unavailable
     */
    public function flosc_save_config($markdown, $target_flow = null) {
        // v1.2.3: Use specified flow or fall back to current flow detection.
        // Pass $for_write = true to get uploads-only path.
        $ivr_file = $this->get_ivr_file_path($target_flow, true);
        
        // If uploads are unavailable, the write path will be empty. Fail safely.
        if ('' === $ivr_file) {
            return false;
        }
        
        // Use the uploads-only write API with realpath validation.
        $write_ok = function_exists('flosc_write_data_file')
            ? flosc_write_data_file($ivr_file, $markdown)
            : false;
        
        if (!$write_ok) {
            return false;
        }
        
        $this->flosc_config = $this->flosc_parse($markdown);
        
        // v1.2.3: DO NOT sync to global wp_options for multi-flow support.
        // Just update the last sync timestamp for admin reference.
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
