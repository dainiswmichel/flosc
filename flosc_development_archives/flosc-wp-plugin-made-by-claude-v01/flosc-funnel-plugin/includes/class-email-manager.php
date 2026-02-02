<?php
/**
 * Email Manager - Handles all email communications
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_Email_Manager {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Send OTO offer email
     */
    public function send_offer_email($to_email, $quiz_id, $flagged_phonemes, $free_lessons) {
        $product_name = get_option('flosc_product_name');
        $full_price = get_option('flosc_full_price');
        $oto_price = get_option('flosc_oto_price');
        $discount_percent = get_option('flosc_oto_discount_percent');
        
        $offer_url = add_query_arg('quiz_id', $quiz_id, get_permalink(get_option('flosc_offer_page')));
        $oto_duration = get_option('flosc_oto_duration_hours', 24);
        $expires_at = date('F j, Y \a\t g:i A', strtotime('+' . $oto_duration . ' hours'));
        
        // Build lessons HTML
        $lessons_html = '';
        foreach ($free_lessons as $lesson) {
            $lessons_html .= '<li><strong>' . esc_html($lesson['title']) . '</strong></li>';
        }
        
        $subject = sprintf(__('Your %s Analysis + Special Offer', 'flosc-funnel'), $product_name);
        
        $message = $this->get_email_template(array(
            'product_name' => $product_name,
            'phoneme_count' => count($flagged_phonemes),
            'phonemes_list' => implode(', ', $flagged_phonemes),
            'lessons_html' => $lessons_html,
            'full_price' => $full_price,
            'oto_price' => $oto_price,
            'discount_percent' => $discount_percent,
            'savings' => $full_price - $oto_price,
            'offer_url' => $offer_url,
            'expires_at' => $expires_at
        ));
        
        return $this->send_email($to_email, $subject, $message);
    }
    
    /**
     * Get email template
     */
    private function get_email_template($vars) {
        extract($vars);
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2563eb; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { padding: 20px; background: #f9fafb; border-radius: 0 0 8px 8px; }
                .phoneme-list { background: #fee2e2; padding: 15px; border-left: 4px solid #dc2626; margin: 15px 0; }
                .offer { background: #dcfce7; padding: 20px; border: 2px solid #16a34a; margin: 20px 0; border-radius: 8px; }
                .cta { display: inline-block; background: #16a34a; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; }
                .timer { color: #dc2626; font-weight: bold; }
                ul { padding-left: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Your Pronunciation Analysis Results</h1>
                </div>
                <div class="content">
                    <h2>Analysis Complete!</h2>
                    <p>Based on your recordings, we've identified <strong><?php echo $phoneme_count; ?> sound(s)</strong> that need attention:</p>
                    
                    <div class="phoneme-list">
                        <p><strong>Sounds to work on:</strong> <?php echo esc_html($phonemes_list); ?></p>
                    </div>
                    
                    <h3>Your FREE Lessons:</h3>
                    <ul>
                        <?php echo $lessons_html; ?>
                    </ul>
                    
                    <div class="offer">
                        <h2>🎁 SPECIAL ONE-TIME OFFER</h2>
                        <p>Get the complete <strong><?php echo esc_html($product_name); ?></strong> course at <strong><?php echo $discount_percent; ?>% OFF!</strong></p>
                        <p style="font-size: 24px;"><s>$<?php echo $full_price; ?></s> <strong style="color: #16a34a;">$<?php echo $oto_price; ?></strong></p>
                        <p>Save $<?php echo $savings; ?>!</p>
                        <p class="timer">⏰ This offer expires in <?php echo get_option('flosc_oto_duration_hours', 24); ?> hours!</p>
                        <p style="text-align: center;">
                            <a href="<?php echo esc_url($offer_url); ?>" class="cta">CLAIM YOUR DISCOUNT NOW</a>
                        </p>
                    </div>
                    
                    <p><small>Offer expires: <?php echo $expires_at; ?></small></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Send email
     */
    private function send_email($to, $subject, $message) {
        $from_name = get_option('flosc_email_from_name', get_bloginfo('name'));
        $from_email = get_option('flosc_email_from_email', get_option('admin_email'));
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        );
        
        $provider = get_option('flosc_email_provider', 'wordpress');
        
        if ($provider === 'brevo') {
            return $this->send_via_brevo($to, $subject, $message);
        } elseif ($provider === 'sendgrid') {
            return $this->send_via_sendgrid($to, $subject, $message);
        } else {
            return wp_mail($to, $subject, $message, $headers);
        }
    }
    
    /**
     * Send via Brevo API
     */
    private function send_via_brevo($to, $subject, $html) {
        $api_key = get_option('flosc_brevo_api_key');
        if (empty($api_key)) {
            return false;
        }
        
        $response = wp_remote_post('https://api.brevo.com/v3/smtp/email', array(
            'headers' => array(
                'api-key' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'sender' => array(
                    'email' => get_option('flosc_email_from_email'),
                    'name' => get_option('flosc_email_from_name')
                ),
                'to' => array(array('email' => $to)),
                'subject' => $subject,
                'htmlContent' => $html
            ))
        ));
        
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 201;
    }
    
    /**
     * Send via SendGrid API
     */
    private function send_via_sendgrid($to, $subject, $html) {
        $api_key = get_option('flosc_sendgrid_api_key');
        if (empty($api_key)) {
            return false;
        }
        
        $response = wp_remote_post('https://api.sendgrid.com/v3/mail/send', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'personalizations' => array(
                    array('to' => array(array('email' => $to)))
                ),
                'from' => array(
                    'email' => get_option('flosc_email_from_email'),
                    'name' => get_option('flosc_email_from_name')
                ),
                'subject' => $subject,
                'content' => array(
                    array('type' => 'text/html', 'value' => $html)
                )
            ))
        ));
        
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 202;
    }
}
