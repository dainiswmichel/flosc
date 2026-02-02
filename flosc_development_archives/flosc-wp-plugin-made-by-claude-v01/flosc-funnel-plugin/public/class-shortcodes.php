<?php
/**
 * Shortcodes - Complete FLOSC flow pages
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_Shortcodes {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_shortcode('flosc_chatbot', array($this, 'chatbot_shortcode'));
        add_shortcode('flosc_offer', array($this, 'offer_shortcode'));
        add_shortcode('flosc_dashboard', array($this, 'dashboard_shortcode'));
        add_shortcode('flosc_success', array($this, 'success_shortcode'));
    }
    
    /**
     * Chatbot shortcode - FREELINE
     */
    public function chatbot_shortcode($atts) {
        ob_start();
        ?>
        <div class="flosc-chatbot-container">
            <div class="flosc-card">
                <h1 class="flosc-text-center">🎤 Free Pronunciation Analysis</h1>
                
                <div class="flosc-chatbot" id="floscChatbot">
                    <div class="flosc-chat-messages" id="floscChatMessages"></div>
                    
                    <div class="flosc-chat-actions hidden" id="floscInitialActions">
                        <button class="flosc-btn flosc-btn-primary" onclick="floscRespond('yes')">Yes, I'd love that!</button>
                        <button class="flosc-btn flosc-btn-outline" onclick="floscRespond('no')">No thanks</button>
                    </div>
                    
                    <div class="hidden" id="floscCarouselSection">
                        <div class="flosc-carousel" id="floscCarousel">
                            <div class="flosc-carousel-track" id="floscCarouselTrack"></div>
                        </div>
                        
                        <div class="flosc-carousel-controls">
                            <button class="flosc-btn flosc-btn-outline" onclick="floscPrevSlide()">← Previous</button>
                            <button class="flosc-btn flosc-btn-outline" onclick="floscNextSlide()">Next →</button>
                        </div>
                        
                        <div class="flosc-carousel-dots" id="floscCarouselDots"></div>
                        
                        <div class="flosc-recorder">
                            <h3>Record yourself saying:</h3>
                            <div class="flosc-sentence-display" id="floscCurrentSentence"></div>
                            
                            <button class="flosc-record-button" id="floscRecordBtn" onclick="floscToggleRecording()">
                                <span id="floscRecordIcon">🎤</span>
                            </button>
                            
                            <div class="flosc-waveform hidden" id="floscWaveform">
                                <div class="flosc-wave-bar"></div>
                                <div class="flosc-wave-bar"></div>
                                <div class="flosc-wave-bar"></div>
                                <div class="flosc-wave-bar"></div>
                                <div class="flosc-wave-bar"></div>
                            </div>
                            
                            <p id="floscRecordStatus">Click the microphone to start recording</p>
                            
                            <div class="flosc-form-group flosc-mt-md" id="floscEmailSection">
                                <label for="floscEmail">Email (optional - for results):</label>
                                <input type="email" id="floscEmail" placeholder="your@email.com">
                            </div>
                            
                            <button class="flosc-btn flosc-btn-primary flosc-mt-md hidden" id="floscSubmitBtn" onclick="floscSubmitRecording()">
                                Submit Recording
                            </button>
                        </div>
                    </div>
                    
                    <div class="hidden" id="floscCompletionSection">
                        <div class="flosc-alert flosc-alert-success">
                            <h3>✅ Analysis Complete!</h3>
                            <p>Great job! Your recordings have been analyzed.</p>
                        </div>
                        <?php if (is_user_logged_in()): ?>
                            <button class="flosc-btn flosc-btn-accent" onclick="floscGoToResults()">View Your Results</button>
                        <?php else: ?>
                            <button class="flosc-btn flosc-btn-accent" onclick="floscGoToLogin()">Login to View Results</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Offer shortcode - OFFER
     */
    public function offer_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to view your results. <a href="' . wp_login_url(get_permalink()) . '">Log In</a></p>';
        }
        
        $quiz_id = isset($_GET['quiz_id']) ? sanitize_text_field($_GET['quiz_id']) : '';
        
        ob_start();
        ?>
        <div class="flosc-offer-container" data-quiz-id="<?php echo esc_attr($quiz_id); ?>">
            <div class="flosc-card">
                <h1 class="flosc-text-center">📊 Your Analysis Results</h1>
                
                <div id="floscOfferLoading">
                    <div class="flosc-spinner"></div>
                    <p class="flosc-loading-text">Loading your personalized analysis...</p>
                </div>
                
                <div id="floscOfferResults" class="hidden">
                    <div class="flosc-alert flosc-alert-info">
                        <h2>🎯 Pronunciation Analysis Complete!</h2>
                        <p>Based on your recordings, we've identified areas for improvement.</p>
                    </div>
                    
                    <div class="flosc-mb-lg">
                        <h3>Sounds that need attention:</h3>
                        <div class="flosc-phoneme-list" id="floscPhonemeList"></div>
                    </div>
                    
                    <div class="flosc-mb-lg">
                        <h3>🎁 Your FREE Lessons:</h3>
                        <div id="floscFreeLessons"></div>
                    </div>
                    
                    <div class="flosc-offer-box">
                        <h2>🌟 SPECIAL ONE-TIME OFFER</h2>
                        <p>Get the complete course and master ALL pronunciation sounds</p>
                        
                        <div class="flosc-price-comparison">
                            <span class="flosc-old-price">$<?php echo get_option('flosc_full_price'); ?></span>
                            <span class="flosc-new-price">$<?php echo get_option('flosc_oto_price'); ?></span>
                        </div>
                        
                        <p class="flosc-text-center flosc-mb-md">
                            <strong><?php echo get_option('flosc_oto_discount_percent'); ?>%</strong> OFF - 
                            Save <strong>$<?php echo get_option('flosc_full_price') - get_option('flosc_oto_price'); ?>!</strong>
                        </p>
                        
                        <div class="flosc-countdown-timer" id="floscCountdown">
                            ⏰ Offer expires in: <span id="floscTimeRemaining">23:59:59</span>
                        </div>
                        
                        <button class="flosc-btn flosc-btn-accent flosc-mt-lg" id="floscClaimOfferBtn" onclick="floscClaimOffer()">
                            🎉 CLAIM YOUR DISCOUNT NOW
                        </button>
                    </div>
                    
                    <div class="flosc-mt-lg flosc-text-center">
                        <button class="flosc-btn flosc-btn-outline" onclick="floscViewDashboard()">View Free Lessons</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Dashboard shortcode - CONTENT
     */
    public function dashboard_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to access your dashboard. <a href="' . wp_login_url(get_permalink()) . '">Log In</a></p>';
        }
        
        $user_id = get_current_user_id();
        $content_guard = FLOSC_Content_Guard::instance();
        $has_paid = $content_guard->user_has_access($user_id);
        
        ob_start();
        ?>
        <div class="flosc-dashboard-container">
            <div class="flosc-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h1>📚 Your Course</h1>
                    <a href="<?php echo wp_logout_url(home_url()); ?>" class="flosc-btn flosc-btn-outline">Sign Out</a>
                </div>
                
                <?php if ($has_paid): ?>
                    <div class="flosc-alert flosc-alert-success flosc-mb-lg">
                        <strong>✅ FULL ACCESS</strong>
                        <p>You have access to all course materials!</p>
                    </div>
                <?php else: ?>
                    <div class="flosc-alert flosc-alert-info flosc-mb-lg">
                        <strong>🎁 FREE ACCESS</strong>
                        <p>You have access to your personalized free lessons. 
                        <a href="<?php echo get_permalink(get_option('flosc_offer_page')); ?>">Upgrade for full access</a></p>
                    </div>
                <?php endif; ?>
                
                <div id="floscLessonsContainer">
                    <?php $this->render_lessons($has_paid); ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render lessons
     */
    private function render_lessons($has_paid) {
        $args = array(
            'post_type' => 'flosc_lesson',
            'posts_per_page' => -1,
            'orderby' => 'meta_value_num',
            'meta_key' => '_flosc_lesson_order',
            'order' => 'ASC'
        );
        
        if (!$has_paid) {
            $args['meta_query'] = array(
                array(
                    'key' => '_flosc_is_free',
                    'value' => '1'
                )
            );
        }
        
        $lessons = get_posts($args);
        
        if (empty($lessons)) {
            echo '<p>No lessons available yet.</p>';
            return;
        }
        
        foreach ($lessons as $lesson) {
            $is_free = get_post_meta($lesson->ID, '_flosc_is_free', true) === '1';
            $phoneme = get_post_meta($lesson->ID, '_flosc_phoneme_group', true);
            $excerpt = get_the_excerpt($lesson);
            
            ?>
            <div class="flosc-lesson-card">
                <?php if ($is_free || $has_paid): ?>
                    <span class="flosc-lesson-badge">AVAILABLE</span>
                <?php else: ?>
                    <span class="flosc-lesson-badge" style="background: #9ca3af;">LOCKED</span>
                <?php endif; ?>
                
                <h3><?php echo esc_html($lesson->post_title); ?></h3>
                <?php if ($phoneme): ?>
                    <p>Target sound: <strong><?php echo esc_html($phoneme); ?></strong></p>
                <?php endif; ?>
                <?php if ($excerpt): ?>
                    <p><?php echo esc_html($excerpt); ?></p>
                <?php endif; ?>
                
                <?php if ($is_free || $has_paid): ?>
                    <a href="<?php echo get_permalink($lesson->ID); ?>" class="flosc-btn flosc-btn-primary flosc-mt-md">
                        Start Lesson
                    </a>
                <?php else: ?>
                    <button class="flosc-btn flosc-btn-outline flosc-mt-md" disabled>
                        Upgrade to Unlock
                    </button>
                <?php endif; ?>
            </div>
            <?php
        }
    }
    
    /**
     * Success shortcode - SALE SUCCESS
     */
    public function success_shortcode($atts) {
        ob_start();
        ?>
        <div class="flosc-success-container">
            <div class="flosc-card flosc-text-center">
                <div style="font-size: 4rem; margin-bottom: 2rem;">🎉</div>
                <h1>Payment Successful!</h1>
                <p class="flosc-mb-lg">Congratulations! You now have full access to the complete course.</p>
                
                <div class="flosc-alert flosc-alert-success">
                    <h3>What's Next?</h3>
                    <ul style="text-align: left; list-style: none;">
                        <li>✅ Full access to all lessons</li>
                        <li>✅ Advanced pronunciation techniques</li>
                        <li>✅ Downloadable practice materials</li>
                        <li>✅ Lifetime access to updates</li>
                    </ul>
                </div>
                
                <a href="<?php echo get_permalink(get_option('flosc_dashboard_page')); ?>" class="flosc-btn flosc-btn-accent flosc-mt-lg">
                    Access Your Course Now
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
