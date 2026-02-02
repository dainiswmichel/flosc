<?php
/**
 * IVR Manager Class
 * Handles Interactive Voice Response / Chat Flow Configuration
 * v04_03: FLOSC Phase-Aware Message System
 */

if (!defined('ABSPATH')) exit;

class FLOSC_IVR_Manager {

    private static $instance = null;
    private $config = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);

        // AJAX endpoints
        add_action('wp_ajax_flosc_save_ivr_config', [$this, 'ajax_save_config']);
        add_action('wp_ajax_flosc_load_ivr_config', [$this, 'ajax_load_config']);

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    /**
     * Add IVR settings page to admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'flosc-settings',
            'IVR Configuration',
            'IVR Messages',
            'manage_options',
            'flosc-ivr',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ('flosc_page_flosc-ivr' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'flosc-ivr-admin',
            FLOSC_PLUGIN_URL . 'assets/css/ivr-admin.css',
            [],
            FLOSC_VERSION
        );

        wp_enqueue_script(
            'flosc-ivr-admin',
            FLOSC_PLUGIN_URL . 'assets/js/ivr-admin.js',
            ['jquery'],
            FLOSC_VERSION,
            true
        );

        wp_localize_script('flosc-ivr-admin', 'floscIVR', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('flosc_ivr_nonce')
        ]);
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        include FLOSC_PLUGIN_DIR . 'templates/admin/ivr-settings.php';
    }

    /**
     * Get IVR configuration (with defaults if not set)
     */
    public function get_config() {
        if (null !== $this->config) {
            return $this->config;
        }

        $saved = get_option('flosc_ivr_config', []);
        $defaults = $this->get_default_config();

        $this->config = array_replace_recursive($defaults, $saved);
        return $this->config;
    }

    /**
     * Get default IVR configuration
     */
    private function get_default_config() {
        return [
            // Freeline Phase (Visitor, pre-quiz)
            'freeline' => [
                'initial_message' => "Hi! I'm your FLOSC pronunciation coach. I'm here to help you master English pronunciation through personalized practice. Ready to discover exactly where you can improve?",

                'sequential_messages' => [],

                'pool_messages' => [
                    [
                        'text' => "What are your biggest challenges when pronouncing English?",
                        'conditions' => ['message_count' => 2],
                        'enabled' => true
                    ],
                    [
                        'text' => "Which English sounds give you the most trouble?",
                        'conditions' => ['message_count' => 3],
                        'enabled' => true
                    ]
                ],

                'triggered_messages' => [
                    [
                        'text' => "Ready to see where you stand? Take the free quiz! It takes just 30 seconds and shows you exactly where you can improve.",
                        'conditions' => ['message_count' => 5, 'quiz_taken' => false],
                        'action' => 'open_quiz',
                        'enabled' => true
                    ],
                    [
                        'text' => "Still there? Let me know if you need help!",
                        'conditions' => ['inactivity_seconds' => 120],
                        'enabled' => true
                    ],
                    [
                        'text' => "Chat will close in 30 seconds due to inactivity...",
                        'conditions' => ['inactivity_seconds' => 300],
                        'action' => 'warn_close',
                        'enabled' => true
                    ]
                ]
            ],

            // Login Prompt Phase (Post-quiz, pre-login)
            'login_prompt' => [
                'initial_message' => "Great job completing the quiz! Create your free profile to see your personalized results and unlock your free lesson.",

                'pool_messages' => [
                    [
                        'text' => "Want to save your progress and see your detailed results? Create a free account now!",
                        'conditions' => ['message_count' => 2],
                        'enabled' => true
                    ]
                ],

                'triggered_messages' => [
                    [
                        'text' => "Your results are ready! Just create a quick profile to view them.",
                        'conditions' => ['message_count' => 5],
                        'action' => 'open_login',
                        'enabled' => true
                    ]
                ]
            ],

            // Login Phase (Logged-in user)
            'login' => [
                'initial_message' => "Welcome back, {name}! 🎉\n\nYour quiz score: **{score}%**\n\nBased on what you need to practice, I've prepared a **FREE lesson** just for you. Let me show you!",

                'sequential_messages' => [
                    [
                        'text' => "How was that lesson? Any questions about what we covered?",
                        'enabled' => true
                    ]
                ],

                'pool_messages' => [
                    [
                        'text' => "You're making great progress! Want to see more lessons like this?",
                        'conditions' => ['message_count' => 3],
                        'enabled' => true
                    ],
                    [
                        'text' => "This is just the beginning. The full course has 50+ targeted exercises designed for your specific needs.",
                        'conditions' => ['session_minutes' => 2],
                        'enabled' => true
                    ]
                ],

                'triggered_messages' => []
            ],

            // Offer Phase (Sales pitch)
            'offer' => [
                'initial_message' => "You've experienced the power of personalized learning. **Ready to master everything?** 🚀\n\nGet **{product_name}** for just **{price}**\n\n{product_description}\n\nYou've taken the first step with your free lesson. The rest of your personalized learning path is waiting!",

                'sequential_messages' => [
                    [
                        'text' => "This is a limited-time offer - save 50% if you join today. Are you ready to unlock your full potential?",
                        'enabled' => true
                    ]
                ],

                'pool_messages' => [
                    [
                        'text' => "Have questions about what's included? I'm here to help!",
                        'conditions' => ['message_count' => 2],
                        'enabled' => true
                    ],
                    [
                        'text' => "Join {customer_count}+ students who have already transformed their pronunciation!",
                        'conditions' => ['message_count' => 4],
                        'enabled' => true
                    ]
                ],

                'triggered_messages' => [
                    [
                        'text' => "This offer expires soon. Don't miss your chance to unlock everything!",
                        'conditions' => ['session_minutes' => 5],
                        'action' => 'open_payment',
                        'enabled' => true
                    ]
                ]
            ],

            // Sale Phase (Post-purchase)
            'sale' => [
                'initial_message' => "Congratulations on your purchase! 🎉\n\nYou've just unlocked the complete **{product_name}**. Here's what happens next:\n\n1. Full access to all lessons\n2. Unlimited practice sessions\n3. Progress tracking dashboard\n4. Priority support\n\nLet's get started!",

                'sequential_messages' => [
                    [
                        'text' => "Your personalized learning path is ready. Where would you like to begin?",
                        'enabled' => true
                    ]
                ],

                'pool_messages' => [
                    [
                        'text' => "Need help navigating your new dashboard? I can show you around!",
                        'conditions' => ['message_count' => 2],
                        'enabled' => true
                    ]
                ],

                'triggered_messages' => []
            ],

            // Content Phase (Ongoing access)
            'content' => [
                'initial_message' => "Welcome back! Ready to continue your learning journey? Pick up where you left off or explore new lessons.",

                'sequential_messages' => [],

                'pool_messages' => [
                    [
                        'text' => "You're making great progress! Keep up the momentum!",
                        'conditions' => ['message_count' => 5],
                        'enabled' => true
                    ],
                    [
                        'text' => "Remember: consistent practice is the key to mastering pronunciation. You're doing great!",
                        'conditions' => ['session_minutes' => 10],
                        'enabled' => true
                    ]
                ],

                'triggered_messages' => [
                    [
                        'text' => "Haven't seen you in a while! Ready to continue where you left off?",
                        'conditions' => ['inactivity_seconds' => 180],
                        'enabled' => true
                    ]
                ]
            ]
        ];
    }

    /**
     * AJAX: Save IVR configuration
     */
    public function ajax_save_config() {
        check_ajax_referer('flosc_ivr_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        $config = isset($_POST['config']) ? json_decode(stripslashes($_POST['config']), true) : [];

        if (empty($config)) {
            wp_send_json_error(['message' => 'Invalid configuration']);
            return;
        }

        // Sanitize config
        $config = $this->sanitize_config($config);

        // Save
        update_option('flosc_ivr_config', $config);

        // Clear cached config
        $this->config = null;

        wp_send_json_success(['message' => 'Configuration saved successfully']);
    }

    /**
     * AJAX: Load IVR configuration
     */
    public function ajax_load_config() {
        check_ajax_referer('flosc_ivr_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        wp_send_json_success(['config' => $this->get_config()]);
    }

    /**
     * Sanitize configuration data
     */
    private function sanitize_config($config) {
        $sanitized = [];

        foreach ($config as $phase => $phase_config) {
            $sanitized[$phase] = [];

            if (isset($phase_config['initial_message'])) {
                $sanitized[$phase]['initial_message'] = wp_kses_post($phase_config['initial_message']);
            }

            if (isset($phase_config['sequential_messages']) && is_array($phase_config['sequential_messages'])) {
                $sanitized[$phase]['sequential_messages'] = array_map(function($msg) {
                    return [
                        'text' => wp_kses_post($msg['text'] ?? ''),
                        'enabled' => (bool)($msg['enabled'] ?? true)
                    ];
                }, $phase_config['sequential_messages']);
            }

            if (isset($phase_config['pool_messages']) && is_array($phase_config['pool_messages'])) {
                $sanitized[$phase]['pool_messages'] = array_map(function($msg) {
                    return [
                        'text' => wp_kses_post($msg['text'] ?? ''),
                        'conditions' => $msg['conditions'] ?? [],
                        'enabled' => (bool)($msg['enabled'] ?? true)
                    ];
                }, $phase_config['pool_messages']);
            }

            if (isset($phase_config['triggered_messages']) && is_array($phase_config['triggered_messages'])) {
                $sanitized[$phase]['triggered_messages'] = array_map(function($msg) {
                    return [
                        'text' => wp_kses_post($msg['text'] ?? ''),
                        'conditions' => $msg['conditions'] ?? [],
                        'action' => sanitize_text_field($msg['action'] ?? ''),
                        'enabled' => (bool)($msg['enabled'] ?? true)
                    ];
                }, $phase_config['triggered_messages']);
            }
        }

        return $sanitized;
    }

    /**
     * Get IVR config for frontend (JavaScript)
     */
    public function get_frontend_config() {
        return $this->get_config();
    }
}

// Initialize
FLOSC_IVR_Manager::get_instance();
