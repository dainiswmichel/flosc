<?php
/**
 * FLOSC Structured Logger
 * 
 * Provides consistent, secure logging throughout the plugin.
 * Supports log levels, correlation IDs, and sensitive data sanitization.
 * 
 * @since 6.0.1
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Logger {
    
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';
    
    /**
     * Current correlation ID for request tracking
     */
    private static $correlation_id = null;
    
    /**
     * Minimum log level to record
     */
    private static $min_level = self::LEVEL_INFO;
    
    /**
     * Level priority map
     */
    private static $level_priority = [
        self::LEVEL_DEBUG => 0,
        self::LEVEL_INFO => 1,
        self::LEVEL_WARNING => 2,
        self::LEVEL_ERROR => 3,
        self::LEVEL_CRITICAL => 4,
    ];
    
    /**
     * Sensitive keys to redact from logs
     */
    private static $sensitive_keys = [
        'password', 'secret', 'key', 'token', 'api_key', 'apikey',
        'credit_card', 'card_number', 'cvv', 'ssn', 'social_security',
        'auth', 'authorization', 'bearer', 'session', 'cookie',
        'stripe_sk', 'stripe_pk', 'webhook_secret', 'clickbank_secret',
    ];
    
    /**
     * Initialize logger for current request
     */
    public static function init() {
        if (self::$correlation_id === null) {
            self::$correlation_id = self::generate_correlation_id();
        }
        
        // Set minimum level based on WP_DEBUG
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::$min_level = self::LEVEL_DEBUG;
        }
    }
    
    /**
     * Generate unique correlation ID for request tracking
     */
    private static function generate_correlation_id() {
        return sprintf(
            'flosc_%s_%s',
            date('Ymd_His'),
            substr(md5(uniqid(mt_rand(), true)), 0, 8)
        );
    }
    
    /**
     * Get current correlation ID
     */
    public static function get_correlation_id() {
        if (self::$correlation_id === null) {
            self::init();
        }
        return self::$correlation_id;
    }
    
    /**
     * Set correlation ID (for continuing existing request)
     */
    public static function set_correlation_id($id) {
        self::$correlation_id = sanitize_text_field($id);
    }
    
    /**
     * Log a debug message
     */
    public static function debug($message, $context = []) {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * Log an info message
     */
    public static function info($message, $context = []) {
        self::log(self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * Log a warning message
     */
    public static function warning($message, $context = []) {
        self::log(self::LEVEL_WARNING, $message, $context);
    }
    
    /**
     * Log an error message
     */
    public static function error($message, $context = []) {
        self::log(self::LEVEL_ERROR, $message, $context);
    }
    
    /**
     * Log a critical message
     */
    public static function critical($message, $context = []) {
        self::log(self::LEVEL_CRITICAL, $message, $context);
    }
    
    /**
     * Main logging method
     */
    public static function log($level, $message, $context = []) {
        // Check if we should log this level
        if (!self::should_log($level)) {
            return;
        }
        
        // Initialize if needed
        if (self::$correlation_id === null) {
            self::init();
        }
        
        // Sanitize context data
        $safe_context = self::sanitize_context($context);
        
        // Build log entry
        $entry = self::format_entry($level, $message, $safe_context);
        
        // Write to WordPress debug log
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log($entry);
        }
        
        // Store critical errors in options for admin display
        if ($level === self::LEVEL_CRITICAL || $level === self::LEVEL_ERROR) {
            self::store_error($level, $message, $safe_context);
        }
        
        // Fire action for external log handlers
        do_action('flosc_log', $level, $message, $safe_context, self::$correlation_id);
    }
    
    /**
     * Check if level should be logged
     */
    private static function should_log($level) {
        $level_priority = self::$level_priority[$level] ?? 1;
        $min_priority = self::$level_priority[self::$min_level] ?? 1;
        return $level_priority >= $min_priority;
    }
    
    /**
     * Format log entry
     */
    private static function format_entry($level, $message, $context) {
        $timestamp = date('Y-m-d H:i:s');
        $correlation = self::$correlation_id;
        
        $entry = "[FLOSC] [{$timestamp}] [{$level}] [{$correlation}] {$message}";
        
        if (!empty($context)) {
            $entry .= ' | Context: ' . wp_json_encode($context, JSON_UNESCAPED_SLASHES);
        }
        
        return $entry;
    }
    
    /**
     * Sanitize context data to remove sensitive information
     */
    private static function sanitize_context($context) {
        if (!is_array($context)) {
            return $context;
        }
        
        $sanitized = [];
        
        foreach ($context as $key => $value) {
            $lower_key = strtolower($key);
            
            // Check if key contains sensitive terms
            $is_sensitive = false;
            foreach (self::$sensitive_keys as $sensitive) {
                if (strpos($lower_key, $sensitive) !== false) {
                    $is_sensitive = true;
                    break;
                }
            }
            
            if ($is_sensitive) {
                // Redact sensitive values but show partial for debugging
                if (is_string($value) && strlen($value) > 4) {
                    $sanitized[$key] = substr($value, 0, 4) . '****REDACTED****';
                } else {
                    $sanitized[$key] = '****REDACTED****';
                }
            } elseif (is_array($value)) {
                // Recursively sanitize nested arrays
                $sanitized[$key] = self::sanitize_context($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Store error for admin display
     */
    private static function store_error($level, $message, $context) {
        $errors = get_option('flosc_recent_errors', []);
        
        // Keep only last 50 errors
        if (count($errors) >= 50) {
            array_shift($errors);
        }
        
        $errors[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'correlation_id' => self::$correlation_id,
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
        ];
        
        update_option('flosc_recent_errors', $errors, false);
    }
    
    /**
     * Get recent errors for admin display
     */
    public static function get_recent_errors($limit = 20) {
        $errors = get_option('flosc_recent_errors', []);
        return array_slice(array_reverse($errors), 0, $limit);
    }
    
    /**
     * Clear stored errors
     */
    public static function clear_errors() {
        delete_option('flosc_recent_errors');
    }
    
    /**
     * Log payment event (standardized format)
     */
    public static function payment($action, $provider, $user_id, $amount = null, $context = []) {
        $context = array_merge([
            'action' => $action,
            'provider' => $provider,
            'user_id' => $user_id,
            'amount' => $amount,
        ], $context);
        
        self::info("Payment: {$action} via {$provider}", $context);
    }
    
    /**
     * Log security event (standardized format)
     */
    public static function security($event, $context = []) {
        $context = array_merge([
            'ip' => self::get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => get_current_user_id(),
        ], $context);
        
        self::warning("Security: {$event}", $context);
    }
    
    /**
     * Get client IP (handles proxies safely)
     */
    private static function get_client_ip() {
        // Don't trust X-Forwarded-For by default (spoofable)
        // Only use REMOTE_ADDR which is set by the server
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
