<?php
/**
 * FLOSC Offers Configuration Tab
 * 
 * Create and manage product offers:
 * - One-Time Offers (OTO) shown after quiz/lesson completion
 * - Upsells and cross-sells
 * - Pricing tiers and bundles
 * - Offer triggers and conditions
 * - Copy/messaging for each offer
 * 
 * Offers integrate with:
 * - Quiz completion flow (OTO after quiz results)
 * - Lesson completion (upgrade prompts)
 * - Email automation (upgrade offers)
 * - IVR messages (conditional pricing mentions)
 * 
 * BACKEND STATUS: ✅ FULLY WIRED (v1.0.2)
 */

if (!defined('ABSPATH')) exit;

// Handle offer save on init
add_action('init', 'flosc_handle_offer_save');
function flosc_handle_offer_save() {
    if (!isset($_POST['save_offer']) || !wp_verify_nonce($_POST['flosc_save_offer_nonce'], 'flosc_save_offer')) {
        return;
    }
    
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $offer_id = sanitize_text_field($_POST['offer_id']);
    if ($offer_id === 'new') {
        $offer_id = 'offer_' . wp_generate_password(8, false);
    }
    
    $offer_data = [
        'id' => $offer_id,
        'name' => sanitize_text_field($_POST['offer_name']),
        'type' => sanitize_text_field($_POST['offer_type']),
        'price' => floatval($_POST['offer_price']),
        'original_price' => floatval($_POST['offer_original_price']),
        'display_price' => '$' . number_format(floatval($_POST['offer_price']), 2),
        'headline' => sanitize_text_field($_POST['offer_headline']),
        'description' => sanitize_textarea_field($_POST['offer_description']),
        'features' => sanitize_textarea_field($_POST['offer_features']),
        'cta' => sanitize_text_field($_POST['offer_cta']),
        'trigger' => sanitize_text_field($_POST['offer_trigger']),
        'condition' => sanitize_text_field($_POST['offer_condition']),
        'grants_level' => sanitize_key($_POST['offer_grants_level'] ?? ''),
        'grants' => [
            'features' => ['full_access'],
            'level' => sanitize_key($_POST['offer_grants_level'] ?? ''),
        ],
        'timer_minutes' => intval($_POST['offer_timer']),
        'active' => isset($_POST['offer_active']),
        'conversions' => 0,
        'views' => 0,
        'created' => current_time('mysql'),
        'updated' => current_time('mysql'),
    ];
    
    // Preserve existing stats if editing
    $all_offers = get_option('flosc_offers', []);
    if (isset($all_offers[$offer_id])) {
        $offer_data['conversions'] = $all_offers[$offer_id]['conversions'] ?? 0;
        $offer_data['views'] = $all_offers[$offer_id]['views'] ?? 0;
        $offer_data['created'] = $all_offers[$offer_id]['created'] ?? current_time('mysql');
    }
    
    $all_offers[$offer_id] = $offer_data;
    update_option('flosc_offers', $all_offers);
    
    wp_redirect(admin_url('admin.php?page=flosc-settings&tab=offers&saved=1'));
    exit;
}

$offers = flosc()->sale()->offers()->get_all_offers();
$editing_offer_id = isset($_GET['edit_offer']) ? sanitize_text_field($_GET['edit_offer']) : '';
$editing_offer = null;

if ($editing_offer_id) {
    foreach ($offers as $offer) {
        if ($offer['id'] === $editing_offer_id) {
            $editing_offer = $offer;
            break;
        }
    }
}
?>

</form>
<h2>Offers & Pricing Configuration</h2>
<p>Create and manage your product offers with pricing, descriptions, and trigger conditions.</p>

<!-- ============================================ -->
<!-- CURRENT OFFERS LIST -->
<!-- ============================================ -->
<div class="card" style="max-width: 100%; margin-bottom: 20px;">
    <h3>Your Offers (<?php echo count($offers); ?>)</h3>
    
    <?php if (empty($offers)): ?>
        <p style="color: #667; font-style: italic;">No offers configured yet. Create your first offer below.</p>
        
        <div style="background: #e7f3ff; border-left: 4px solid #2196f3; padding: 15px; margin-top: 15px;">
            <strong>💡 What are Offers?</strong>
            <p style="margin: 10px 0 0 0;">Offers are products you sell to users at different points in their journey:</p>
            <ul style="margin: 10px 0 0 20px;">
                <li><strong>OTO (One-Time Offer):</strong> Shown immediately after quiz completion - "Limited time: Get full access for 50% off!"</li>
                <li><strong>Upsell:</strong> Premium tier after free lesson - "Upgrade to unlock all 50 lessons"</li>
                <li><strong>Bundle:</strong> Multiple products together - "Complete pronunciation mastery bundle"</li>
            </ul>
        </div>
        
    <?php else: ?>
        <table class="widefat" style="margin-top: 10px;">
            <thead>
                <tr>
                    <th style="width: 25%;">Offer Name</th>
                    <th style="width: 12%;">Price</th>
                    <th style="width: 12%;">Type</th>
                    <th style="width: 10%;">Status</th>
                    <th style="width: 15%;">Trigger</th>
                    <th style="width: 10%;">Conversions</th>
                    <th style="width: 16%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($offers as $offer): ?>
                    <?php
                    $is_active = $offer['active'] ?? true;
                    $conversions = $offer['conversions'] ?? 0;
                    $views = $offer['views'] ?? 0;
                    $conversion_rate = $views > 0 ? round(($conversions / $views) * 100, 1) : 0;
                    ?>
                    <tr style="<?php echo !$is_active ? 'opacity: 0.6;' : ''; ?>">
                        <td>
                            <strong><?php echo esc_html($offer['name']); ?></strong>
                            <?php if (!empty($offer['description'])): ?>
                                <br><small style="color: #667;"><?php echo esc_html(wp_trim_words($offer['description'], 10)); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo esc_html($offer['display_price']); ?></strong>
                            <?php if (!empty($offer['original_price']) && $offer['original_price'] !== $offer['price']): ?>
                                <br><small style="text-decoration: line-through; color: #999;"><?php echo esc_html($offer['original_price']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(ucfirst($offer['type'] ?? 'one-time')); ?></td>
                        <td>
                            <?php if ($is_active): ?>
                                <span style="color: #10b981;">● Active</span>
                            <?php else: ?>
                                <span style="color: #999;">○ Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small><?php echo esc_html($offer['trigger'] ?? 'Manual'); ?></small>
                        </td>
                        <td>
                            <?php echo esc_html($conversions); ?> / <?php echo esc_html($views); ?>
                            <br><small style="color: <?php echo $conversion_rate >= 5 ? '#10b981' : '#999'; ?>;"><?php echo $conversion_rate; ?>%</small>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=flosc-settings&tab=offers&edit_offer=' . urlencode($offer['id'])); ?>" class="button button-small">
                                Edit
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=flosc-settings&tab=offers&toggle_status=' . urlencode($offer['id'])); ?>" class="button button-small">
                                <?php echo $is_active ? 'Deactivate' : 'Activate'; ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <p style="margin-top: 15px;">
        <a href="<?php echo admin_url('admin.php?page=flosc-settings&tab=offers&edit_offer=new'); ?>" class="button button-primary">+ Create New Offer</a>
    </p>
</div>

<!-- ============================================ -->
<!-- OFFER EDITOR (if creating/editing) -->
<!-- ============================================ -->
<?php if ($editing_offer_id): ?>
    <div class="card" style="max-width: 100%; margin-top: 20px;">
        <h3><?php echo $editing_offer ? 'Edit Offer: ' . esc_html($editing_offer['name']) : 'Create New Offer'; ?></h3>
        
        <form method="post">
            <?php wp_nonce_field('flosc_save_offer', 'flosc_save_offer_nonce'); ?>
            <input type="hidden" name="offer_id" value="<?php echo esc_attr($editing_offer_id); ?>">
            
            <table class="form-table">
                <!-- Offer Name -->
                <tr>
                    <th scope="row"><label for="offer_name">Offer Name</label></th>
                    <td>
                        <input type="text" id="offer_name" name="offer_name" 
                               value="<?php echo esc_attr($editing_offer['name'] ?? ''); ?>" 
                               class="large-text" required>
                        <p class="description">Internal name for this offer (e.g., "Premium Annual - 50% Off OTO")</p>
                    </td>
                </tr>
                
                <!-- Offer Type -->
                <tr>
                    <th scope="row"><label for="offer_type">Offer Type</label></th>
                    <td>
                        <select id="offer_type" name="offer_type" class="regular-text">
                            <option value="one-time" <?php selected($editing_offer['type'] ?? '', 'one-time'); ?>>One-Time Purchase</option>
                            <option value="subscription" <?php selected($editing_offer['type'] ?? '', 'subscription'); ?>>Recurring Subscription</option>
                            <option value="bundle" <?php selected($editing_offer['type'] ?? '', 'bundle'); ?>>Bundle (Multiple Products)</option>
                            <option value="upsell" <?php selected($editing_offer['type'] ?? '', 'upsell'); ?>>Upsell / Cross-sell</option>
                        </select>
                    </td>
                </tr>
                
                <!-- Pricing -->
                <tr>
                    <th scope="row">Pricing</th>
                    <td>
                        <label>
                            Price: $<input type="number" name="offer_price" 
                                   value="<?php echo esc_attr($editing_offer['price'] ?? ''); ?>" 
                                   step="0.01" min="0" class="small-text" required>
                        </label>
                        <label style="margin-left: 20px;">
                            Original Price (optional): $<input type="number" name="offer_original_price" 
                                   value="<?php echo esc_attr($editing_offer['original_price'] ?? ''); ?>" 
                                   step="0.01" min="0" class="small-text">
                        </label>
                        <p class="description">Show a strikethrough price to create urgency (e.g., was $99, now $49)</p>
                    </td>
                </tr>
                
                <!-- Headline -->
                <tr>
                    <th scope="row"><label for="offer_headline">Sales Headline</label></th>
                    <td>
                        <input type="text" id="offer_headline" name="offer_headline" 
                               value="<?php echo esc_attr($editing_offer['headline'] ?? ''); ?>" 
                               class="large-text" placeholder="Get Full Access - Limited Time 50% Off!">
                        <p class="description">Attention-grabbing headline shown to users</p>
                    </td>
                </tr>
                
                <!-- Description -->
                <tr>
                    <th scope="row"><label for="offer_description">Offer Description</label></th>
                    <td>
                        <textarea id="offer_description" name="offer_description" rows="5" class="large-text"><?php 
                            echo esc_textarea($editing_offer['description'] ?? ''); 
                        ?></textarea>
                        <p class="description">What's included in this offer? Why should they buy?</p>
                    </td>
                </tr>
                
                <!-- Features List -->
                <tr>
                    <th scope="row"><label for="offer_features">Features (one per line)</label></th>
                    <td>
                        <textarea id="offer_features" name="offer_features" rows="6" class="large-text" placeholder="Complete access to all 50+ lessons&#10;AI-powered pronunciation feedback&#10;Certificate of completion&#10;Lifetime updates"><?php 
                            echo esc_textarea($editing_offer['features'] ?? ''); 
                        ?></textarea>
                        <p class="description">Bullet points of what's included. One feature per line.</p>
                    </td>
                </tr>
                
                <!-- CTA Button Text -->
                <tr>
                    <th scope="row"><label for="offer_cta">Call-to-Action Button</label></th>
                    <td>
                        <input type="text" id="offer_cta" name="offer_cta" 
                               value="<?php echo esc_attr($editing_offer['cta'] ?? 'Get Access Now'); ?>" 
                               class="regular-text">
                        <p class="description">Button text (e.g., "Claim Your Discount", "Start Learning Now")</p>
                    </td>
                </tr>
                
                <!-- Trigger Condition -->
                <tr>
                    <th scope="row"><label for="offer_trigger">Show Offer When</label></th>
                    <td>
                        <select id="offer_trigger" name="offer_trigger" class="large-text">
                            <option value="manual" <?php selected($editing_offer['trigger'] ?? '', 'manual'); ?>>Manual (Admin shows it)</option>
                            <option value="quiz_complete" <?php selected($editing_offer['trigger'] ?? '', 'quiz_complete'); ?>>Quiz Completed</option>
                            <option value="lesson_complete" <?php selected($editing_offer['trigger'] ?? '', 'lesson_complete'); ?>>First Free Lesson Completed</option>
                            <option value="login_phase" <?php selected($editing_offer['trigger'] ?? '', 'login_phase'); ?>>User Enters Login Phase</option>
                            <option value="inactivity" <?php selected($editing_offer['trigger'] ?? '', 'inactivity'); ?>>After 7 Days Inactivity</option>
                        </select>
                        <p class="description">When should this offer be presented to users?</p>
                    </td>
                </tr>
                
                <!-- Condition -->
                <tr>
                    <th scope="row"><label for="offer_condition">Additional Condition (optional)</label></th>
                    <td>
                        <input type="text" id="offer_condition" name="offer_condition" 
                               value="<?php echo esc_attr($editing_offer['condition'] ?? ''); ?>" 
                               class="large-text" placeholder="score >= 70">
                        <p class="description">IVR-style condition for fine-grained targeting (e.g., "score >= 70", "!purchased")</p>
                    </td>
                </tr>
                
                <!-- Grants Membership Level (v1.0.1) -->
                <tr>
                    <th scope="row"><label for="offer_grants_level">Grants Membership Level</label></th>
                    <td>
                        <input type="text" id="offer_grants_level" name="offer_grants_level" 
                               value="<?php echo esc_attr($editing_offer['grants_level'] ?? ''); ?>" 
                               class="regular-text" placeholder="course110">
                        <p class="description">When purchased, grants <code>_flosc_memberlevel_{level}</code> to user. Use lowercase, no spaces (e.g., "course110", "pronunciation_basics")</p>
                    </td>
                </tr>
                
                <!-- Urgency Timer -->
                <tr>
                    <th scope="row"><label for="offer_timer">Urgency Timer (minutes)</label></th>
                    <td>
                        <input type="number" id="offer_timer" name="offer_timer" 
                               value="<?php echo esc_attr($editing_offer['timer_minutes'] ?? ''); ?>" 
                               min="0" class="small-text" placeholder="15">
                        <p class="description">Optional countdown timer to create urgency (leave empty for no timer)</p>
                    </td>
                </tr>
                
                <!-- Status -->
                <tr>
                    <th scope="row"><label for="offer_active">Status</label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="offer_active" name="offer_active" value="1" 
                                   <?php checked($editing_offer['active'] ?? true); ?>>
                            Active (users can see this offer)
                        </label>
                    </td>
                </tr>
            </table>
            
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                <?php submit_button('Save Offer', 'primary', 'save_offer', false); ?>
                <a href="<?php echo admin_url('admin.php?page=flosc-settings&tab=offers'); ?>" class="button" style="margin-left: 10px;">Cancel</a>
                
                <?php if ($editing_offer): ?>
                    <a href="<?php echo admin_url('admin.php?page=flosc-settings&tab=offers&delete_offer=' . urlencode($editing_offer['id'])); ?>" 
                       class="button" 
                       onclick="return confirm('Delete this offer permanently? This cannot be undone.');"
                       style="color: #d63638; margin-left: 20px;">
                        Delete Offer
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- ============================================ -->
<!-- OFFER TEMPLATES & BEST PRACTICES -->
<!-- ============================================ -->
<?php if (!$editing_offer_id): ?>
<hr style="margin: 40px 0;">
<h3>High-Converting Offer Templates (Copy & Paste)</h3>
<p class="description">Proven offer copy you can customize and use. These templates follow battle-tested conversion principles.</p>

<div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #f59e0b;">
    <h4 style="margin-top: 0;">🎯 Template 1: Post-Quiz OTO (One-Time Offer)</h4>
    <p><strong>Trigger:</strong> Quiz Completed | <strong>Price:</strong> $49 (was $99) | <strong>Timer:</strong> 15 minutes</p>
    <button type="button" class="button button-small" onclick="navigator.clipboard.writeText(document.getElementById('template-oto').textContent); alert('Template copied to clipboard!');" style="margin-bottom: 10px;">Copy Full Template</button>
    <div id="template-oto" style="background: white; padding: 15px; border-radius: 4px; font-family: -apple-system, sans-serif;">
<strong style="font-size: 24px; color: #f59e0b;">⏰ SPECIAL ONE-TIME OFFER - 50% OFF!</strong>

<p style="font-size: 18px; margin: 15px 0;"><strong>Get Full Access to All Lessons - Normally $99, Today Just $49</strong></p>

<p>Based on your quiz results, you're ready to start improving immediately. But you need the right guidance.</p>

<p>Right now, you have a <strong>limited-time opportunity</strong> to get our complete pronunciation mastery program at 50% off the regular price.</p>

<p style="background: #fff3cd; padding: 10px; border-radius: 4px;"><strong>⏰ This offer expires in 15 minutes.</strong> After that, the price goes back to $99.</p>

<p><strong>Here's everything you get when you claim this offer:</strong></p>

<p><strong style="font-size: 16px;">✓ Complete Lesson Library</strong><br>
All 50+ pronunciation lessons covering every sound, pattern, and technique you need</p>

<p><strong style="font-size: 16px;">✓ AI-Powered Pronunciation Feedback</strong><br>
Record yourself speaking - get instant, specific feedback on what to improve</p>

<p><strong style="font-size: 16px;">✓ Personalized Learning Path</strong><br>
Custom curriculum built from your quiz results - focus on YOUR weak points first</p>

<p><strong style="font-size: 16px;">✓ Progress Tracking & Analytics</strong><br>
Watch your improvement over time with detailed charts and milestone badges</p>

<p><strong style="font-size: 16px;">✓ Certificate of Completion</strong><br>
Prove your pronunciation mastery - share on LinkedIn, resume, etc.</p>

<p><strong style="font-size: 16px;">✓ Lifetime Access + Updates</strong><br>
One payment, forever access. All future lessons and features included at no extra cost</p>

<p style="background: #e7f3ff; padding: 15px; border-radius: 4px; margin: 20px 0;">
<strong>Your Investment Today:</strong><br>
<span style="text-decoration: line-through; color: #999;">$99.00</span> <strong style="font-size: 28px; color: #2196f3;">$49.00</strong> (Save 50%)<br>
<em>One-time payment. No recurring charges.</em>
</p>

<p><strong>Why are we offering this discount?</strong></p>

<p>Simple: We know that students who start immediately after taking the quiz are 3x more likely to succeed. This special price is our way of rewarding people who are serious about improvement.</p>

<p><strong>100% Money-Back Guarantee</strong></p>

<p>Try the program for 30 days. If you don't see real improvement in your pronunciation, email us and we'll refund every penny. No questions asked.</p>

<p style="background: #fff3cd; padding: 15px; border-radius: 4px; margin: 20px 0; text-align: center;">
<strong>⏰ Remember: This 50% discount expires in 15 minutes!</strong><br>
After that, you'll pay full price ($99) for the same program.
</p>

<div style="text-align: center; margin: 30px 0;">
<a href="#" style="display: inline-block; background: #f59e0b; color: white; padding: 15px 40px; font-size: 18px; font-weight: bold; text-decoration: none; border-radius: 8px;">CLAIM MY 50% DISCOUNT NOW →</a>
</div>

<p style="text-align: center; color: #667; font-size: 12px;">
Secure checkout • Instant access • 30-day money-back guarantee
</p>
    </div>
</div>

<div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #8b5cf6;">
    <h4 style="margin-top: 0;">💎 Template 2: Premium Upsell (After Free Lesson)</h4>
    <p><strong>Trigger:</strong> First Free Lesson Completed | <strong>Price:</strong> $299/year or $39/month | <strong>Timer:</strong> None</p>
    <button type="button" class="button button-small" onclick="navigator.clipboard.writeText(document.getElementById('template-premium').textContent); alert('Template copied to clipboard!');" style="margin-bottom: 10px;">Copy Full Template</button>
    <div id="template-premium" style="background: white; padding: 15px; border-radius: 4px; font-family: -apple-system, sans-serif;">
<strong style="font-size: 24px; color: #8b5cf6;">You've Proven You're Serious. Ready to Go All In?</strong>

<p style="font-size: 18px; margin: 15px 0;"><strong>Upgrade to Premium and Master Pronunciation in Weeks, Not Years</strong></p>

<p>You just completed your first lesson. That took commitment.</p>

<p>Most people never get this far. They take the quiz, say they'll "come back later," and never do.</p>

<p>But you're different. You showed up. You did the work. And that means you're ready for the next level.</p>

<p><strong>Here's the truth:</strong> That free lesson gave you a taste. But to truly master pronunciation, you need the complete system.</p>

<p>Premium gives you everything:</p>

<p><strong style="font-size: 16px;">✓ All 50+ Lessons Unlocked</strong><br>
From beginner sounds to advanced accent reduction. Nothing held back.</p>

<p><strong style="font-size: 16px;">✓ Unlimited AI Pronunciation Analysis</strong><br>
Record as much as you want. Get detailed feedback every single time.</p>

<p><strong style="font-size: 16px;">✓ Weekly Live Coaching Sessions</strong><br>
Join our pronunciation expert every week for Q&A, practice, and personalized tips.</p>

<p><strong style="font-size: 16px;">✓ Priority Email Support</strong><br>
Stuck? Get answers in under 24 hours (usually much faster).</p>

<p><strong style="font-size: 16px;">✓ Advanced Progress Analytics</strong><br>
See exactly how much you've improved. Track your weak points. Celebrate milestones.</p>

<p><strong style="font-size: 16px;">✓ Certificate of Mastery</strong><br>
Complete the program, get certified. Proof you've mastered pronunciation.</p>

<p style="background: #f3e5ff; padding: 15px; border-radius: 4px; margin: 20px 0;">
<strong>Choose Your Plan:</strong><br><br>
<strong>Annual:</strong> $299/year <span style="background: #8b5cf6; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px;">SAVE $169</span><br>
<em>Just $24.92/month - Best value</em><br><br>
<strong>Monthly:</strong> $39/month<br>
<em>Cancel anytime. No commitment.</em>
</p>

<p><strong>Here's what Premium members are saying:</strong></p>

<p style="background: #f9f9f9; padding: 10px; border-left: 3px solid #8b5cf6; font-style: italic;">
"I went from struggling with basic sounds to confidently presenting in English at work. Premium was worth every penny." - Maria S.
</p>

<p style="background: #f9f9f9; padding: 10px; border-left: 3px solid #8b5cf6; font-style: italic;">
"The AI feedback is incredible. It's like having a personal coach available 24/7." - David L.
</p>

<p><strong>30-Day Money-Back Guarantee</strong></p>

<p>Try Premium for a full month. If you don't see dramatic improvement, we'll refund you completely. No hassle. No questions.</p>

<div style="text-align: center; margin: 30px 0;">
<a href="#" style="display: inline-block; background: #8b5cf6; color: white; padding: 15px 40px; font-size: 18px; font-weight: bold; text-decoration: none; border-radius: 8px;">UPGRADE TO PREMIUM NOW →</a>
</div>

<p style="text-align: center; color: #667; font-size: 12px;">
Join 10,000+ students mastering pronunciation
</p>
    </div>
</div>

<div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #10b981;">
    <h4 style="margin-top: 0;">📦 Template 3: High Performer Bundle (Quiz Score 80%+)</h4>
    <p><strong>Trigger:</strong> Quiz Score >= 80 | <strong>Price:</strong> $399 (was $597) | <strong>Timer:</strong> 24 hours</p>
    <button type="button" class="button button-small" onclick="navigator.clipboard.writeText(document.getElementById('template-bundle').textContent); alert('Template copied to clipboard!');" style="margin-bottom: 10px;">Copy Full Template</button>
    <div id="template-bundle" style="background: white; padding: 15px; border-radius: 4px; font-family: -apple-system, sans-serif;">
<strong style="font-size: 24px; color: #10b981;">🏆 You Scored 80%+ - You're a High Performer!</strong>

<p style="font-size: 18px; margin: 15px 0;"><strong>Skip the Basics. Get Our Complete Advanced Package.</strong></p>

<p>Your quiz score puts you in the <strong>top 10%</strong> of all students.</p>

<p>That means you don't need beginner lessons. You need advanced techniques, business English, and accent reduction.</p>

<p>We have a special package designed specifically for high performers like you.</p>

<p style="background: #d1fae5; padding: 15px; border-radius: 4px;">
<strong>High Performer Bundle - Everything You Need to Reach True Mastery</strong><br>
<span style="text-decoration: line-through; color: #999;">Regular Price: $597</span><br>
<strong style="font-size: 24px; color: #10b981;">Your Price: $399</strong> (33% savings)
</p>

<p><strong>This bundle includes:</strong></p>

<p><strong style="font-size: 16px;">✓ Complete Pronunciation Course</strong> ($299 value)<br>
All 50+ lessons - beginner through advanced. Skip what you know, focus on what you need.</p>

<p><strong style="font-size: 16px;">✓ Advanced Accent Reduction Module</strong> ($99 value)<br>
Fine-tune your accent. Sound more native. Perfect for business and professional settings.</p>

<p><strong style="font-size: 16px;">✓ Business English Pronunciation</strong> ($99 value)<br>
Master the specific sounds, intonation, and delivery needed for presentations, meetings, and negotiations.</p>

<p><strong style="font-size: 16px;">✓ Private 1-on-1 Coaching Session</strong> ($100 value)<br>
60 minutes with our pronunciation expert. Get personalized feedback on your specific challenges.</p>

<p><strong style="font-size: 16px;">✓ VIP Email Support</strong> (Priceless)<br>
Jump to the front of the line. Get answers to your questions within hours, not days.</p>

<p><strong style="font-size: 16px;">✓ Lifetime Access to Everything</strong><br>
One payment. Forever access. All future courses and updates included automatically.</p>

<p style="background: #fff3cd; padding: 15px; border-radius: 4px; margin: 20px 0;">
<strong>⏰ Limited Availability - 24 Hours Only</strong><br>
This bundle is only offered to our highest-scoring quiz takers, and only for 24 hours after completion.
</p>

<p><strong>Why is this package perfect for you?</strong></p>

<p>Because you're <strong>already ahead of 90% of students</strong>. You don't need to waste time on basics. You need targeted, advanced training that takes you from good to exceptional.</p>

<p>That's exactly what this bundle delivers.</p>

<p><strong>Total Value:</strong> $597<br>
<strong>Your Investment Today:</strong> $399<br>
<strong>You Save:</strong> $198 (33% off)</p>

<div style="text-align: center; margin: 30px 0;">
<a href="#" style="display: inline-block; background: #10b981; color: white; padding: 15px 40px; font-size: 18px; font-weight: bold; text-decoration: none; border-radius: 8px;">GET THE HIGH PERFORMER BUNDLE →</a>
</div>

<p style="text-align: center; color: #667; font-size: 12px;">
One-time payment • Lifetime access • 30-day money-back guarantee
</p>
    </div>
</div>

<div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #ec4899;">
    <h4 style="margin-top: 0;">🔄 Template 4: Re-engagement Offer (Inactive Users)</h4>
    <p><strong>Trigger:</strong> 7+ Days Inactivity | <strong>Price:</strong> $29/month (was $49) | <strong>Timer:</strong> None</p>
    <button type="button" class="button button-small" onclick="navigator.clipboard.writeText(document.getElementById('template-comeback').textContent); alert('Template copied to clipboard!');" style="margin-bottom: 10px;">Copy Full Template</button>
    <div id="template-comeback" style="background: white; padding: 15px; border-radius: 4px; font-family: -apple-system, sans-serif;">
<strong style="font-size: 24px; color: #ec4899;">We Miss You! Come Back with 40% Off</strong>

<p style="font-size: 18px; margin: 15px 0;"><strong>Your Progress Is Saved. Your Goals Are Waiting.</strong></p>

<p>You started strong. You took the quiz. You saw what's possible.</p>

<p>Then... life happened.</p>

<p>We get it. Everyone gets busy. But here's the thing:</p>

<p><strong>The students who succeed aren't the ones with the most time.</strong><br>
They're the ones who come back. Who show up. Even if it's been a week.</p>

<p>Your progress is saved. Your personalized lesson plan is ready. All you need to do is click one button.</p>

<p style="background: #fce7f3; padding: 15px; border-radius: 4px;">
<strong>Welcome Back Offer - 40% Off Monthly Membership</strong><br>
<span style="text-decoration: line-through; color: #999;">Regular Price: $49/month</span><br>
<strong style="font-size: 24px; color: #ec4899;">Your Price: $29/month</strong>
</p>

<p><strong>Here's what you get:</strong></p>

<p>✓ Access to all lessons (start where you left off)<br>
✓ AI pronunciation feedback<br>
✓ Progress tracking and analytics<br>
✓ Cancel anytime - no long-term commitment</p>

<p><strong>No pressure. No judgment. Just results.</strong></p>

<p>Month-to-month means you can try it again without committing to a year. See if it fits your schedule. If it does, great. If not, cancel with one click.</p>

<p style="background: #fff3cd; padding: 10px; border-radius: 4px; margin: 20px 0;">
💡 <strong>Pro Tip:</strong> Set aside just 10 minutes a day. That's all it takes. Most people spend more time scrolling social media.
</p>

<p><strong>What happens if you don't come back?</strong></p>

<p>Nothing dramatic. But here's what you'll miss:</p>

<p>• The confidence that comes from speaking clearly<br>
• The career opportunities that require strong communication<br>
• The satisfaction of achieving a goal you set for yourself</p>

<p>All because you didn't give yourself 10 minutes a day.</p>

<div style="text-align: center; margin: 30px 0;">
<a href="#" style="display: inline-block; background: #ec4899; color: white; padding: 15px 40px; font-size: 18px; font-weight: bold; text-decoration: none; border-radius: 8px;">RESTART MY LEARNING JOURNEY →</a>
</div>

<p style="text-align: center; color: #667; font-size: 12px;">
$29/month • Cancel anytime • Pick up where you left off
</p>
    </div>
</div>

<div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #0ea5e9;">
    <h4 style="margin-top: 0;">⚡ Template 5: Flash Sale / Seasonal Offer</h4>
    <p><strong>Trigger:</strong> Manual / Seasonal Campaign | <strong>Price:</strong> $199 (was $399) | <strong>Timer:</strong> 72 hours</p>
    <button type="button" class="button button-small" onclick="navigator.clipboard.writeText(document.getElementById('template-flash').textContent); alert('Template copied to clipboard!');" style="margin-bottom: 10px;">Copy Full Template</button>
    <div id="template-flash" style="background: white; padding: 15px; border-radius: 4px; font-family: -apple-system, sans-serif;">
<strong style="font-size: 24px; color: #0ea5e9;">⚡ 72-Hour Flash Sale - Lifetime Access 50% Off!</strong>

<p style="font-size: 18px; margin: 15px 0;"><strong>Our Biggest Sale of the Year - This Weekend Only</strong></p>

<p>Once a year, we do something crazy.</p>

<p>We take our complete pronunciation mastery program - normally $399 for lifetime access - and cut the price in half.</p>

<p>Why? Because we want to help as many people as possible master pronunciation. And we know price is sometimes the only thing standing in the way.</p>

<p style="background: #e0f2fe; padding: 15px; border-radius: 4px; margin: 20px 0; text-align: center;">
<strong>Flash Sale Price</strong><br>
<span style="text-decoration: line-through; color: #999; font-size: 18px;">$399</span><br>
<strong style="font-size: 36px; color: #0ea5e9;">$199</strong><br>
<strong style="color: #dc2626;">Save $200 - This Weekend Only!</strong>
</p>

<p><strong>Here's everything included:</strong></p>

<p>✓ All 50+ pronunciation lessons (lifetime access)<br>
✓ AI-powered feedback on every recording<br>
✓ Personalized learning path<br>
✓ Progress tracking and analytics<br>
✓ Certificate of completion<br>
✓ All future updates and new lessons (free forever)<br>
✓ 30-day money-back guarantee</p>

<p style="background: #fef3c7; padding: 15px; border-radius: 4px; margin: 20px 0;">
<strong>⏰ Sale Ends in:</strong><br>
<span style="font-size: 32px; font-weight: bold;">72 HOURS</span><br>
After that, the price goes back to $399. No exceptions.
</p>

<p><strong>Who is this for?</strong></p>

<p>This sale is perfect if you've been on the fence. If you wanted to join but couldn't justify the investment. If you were waiting for the "right time."</p>

<p>This is the right time. This is the lowest price we'll ever offer.</p>

<p><strong>Real Student Results:</strong></p>

<p style="background: #f9f9f9; padding: 10px; border-left: 3px solid #0ea5e9; font-style: italic;">
"I bought during last year's sale and it's the best $199 I've ever spent. My pronunciation is unrecognizable from where I started." - Alex T.
</p>

<p style="background: #f9f9f9; padding: 10px; border-left: 3px solid #0ea5e9; font-style: italic;">
"I was skeptical about the 'lifetime access' claim, but they really mean it. I've been learning for 8 months now and haven't paid a penny more." - Priya M.
</p>

<div style="text-align: center; margin: 30px 0;">
<a href="#" style="display: inline-block; background: #0ea5e9; color: white; padding: 15px 40px; font-size: 18px; font-weight: bold; text-decoration: none; border-radius: 8px;">CLAIM YOUR FLASH SALE DISCOUNT →</a>
</div>

<p style="text-align: center; color: #667; font-size: 12px;">
One-time payment • Lifetime access • Offer ends in 72 hours
</p>
    </div>
</div>

<div style="background: #e7f3ff; border-left: 4px solid #2196f3; padding: 15px; margin-top: 30px;">
    <strong>💡 Offer Copywriting Tips:</strong>
    <ul style="margin: 10px 0 0 20px;">
        <li><strong>Lead with the result, not the features:</strong> "Master pronunciation in weeks" beats "50+ lessons"</li>
        <li><strong>Use specific numbers:</strong> "80% of students see improvement in 2 weeks" is more credible than "most students improve quickly"</li>
        <li><strong>Address objections directly:</strong> Price, time commitment, skepticism - call them out and answer them</li>
        <li><strong>Create urgency (but be honest):</strong> Timers work, but only if they're real. Don't fake scarcity.</li>
        <li><strong>Always include a guarantee:</strong> 30-day money-back is standard. It removes risk and increases conversions.</li>
        <li><strong>Show the math:</strong> "Was $99, now $49 = Save $50" makes the discount tangible</li>
        <li><strong>End with social proof:</strong> Testimonials, student counts, success stats</li>
    </ul>
</div>
<?php endif; ?>

<!--
BACKEND IMPLEMENTATION NOTES:
=============================

Offer system integrates with quiz completion, lesson delivery, and email automation.

OFFER PERSISTENCE (needs implementation):

add_action('init', 'flosc_handle_offer_save');
function flosc_handle_offer_save() {
    if (!isset($_POST['save_offer']) || !wp_verify_nonce($_POST['flosc_save_offer_nonce'], 'flosc_save_offer')) {
        return;
    }
    
    $offer_id = sanitize_text_field($_POST['offer_id']);
    if ($offer_id === 'new') {
        $offer_id = 'offer_' . wp_generate_password(8, false);
    }
    
    $offer_data = [
        'id' => $offer_id,
        'name' => sanitize_text_field($_POST['offer_name']),
        'type' => sanitize_text_field($_POST['offer_type']),
        'price' => floatval($_POST['offer_price']),
        'original_price' => floatval($_POST['offer_original_price']),
        'display_price' => '$' . number_format(floatval($_POST['offer_price']), 2),
        'headline' => sanitize_text_field($_POST['offer_headline']),
        'description' => sanitize_textarea_field($_POST['offer_description']),
        'features' => sanitize_textarea_field($_POST['offer_features']),
        'cta' => sanitize_text_field($_POST['offer_cta']),
        'trigger' => sanitize_text_field($_POST['offer_trigger']),
        'condition' => sanitize_text_field($_POST['offer_condition']),
        'grants_level' => sanitize_key($_POST['offer_grants_level'] ?? ''), // v1.0.1: Membership level granted on purchase
        'timer_minutes' => intval($_POST['offer_timer']),
        'active' => isset($_POST['offer_active']),
        'created' => current_time('mysql'),
        'updated' => current_time('mysql'),
    ];
    
    // Save to database or option
    $all_offers = get_option('flosc_offers', []);
    $all_offers[$offer_id] = $offer_data;
    update_option('flosc_offers', $all_offers);
    
    wp_redirect(admin_url('admin.php?page=flosc-settings&tab=offers&saved=1'));
    exit;
}

OFFER TRIGGERING (pseudocode):

function flosc_check_offer_triggers($user_data, $event) {
    $offers = flosc()->sale()->offers()->get_all_offers();
    
    foreach ($offers as $offer) {
        if (!$offer['active']) continue;
        
        // Check trigger matches event
        if ($offer['trigger'] !== $event) continue;
        
        // Check additional condition
        if (!empty($offer['condition'])) {
            if (!flosc_evaluate_condition($offer['condition'], $user_data)) {
                continue;
            }
        }
        
        // Don't show if already purchased
        if (flosc_user_purchased_offer($user_data['id'], $offer['id'])) {
            continue;
        }
        
        // Track view
        flosc_track_offer_view($offer['id']);
        
        // Show offer
        return $offer;
    }
    
    return null;
}

// Hook into events
add_action('flosc_quiz_completed', function($user_data) {
    $offer = flosc_check_offer_triggers($user_data, 'quiz_complete');
    if ($offer) {
        flosc_display_offer_modal($offer);
    }
});

add_action('flosc_lesson_completed', function($user_data, $lesson_id) {
    if (flosc_is_first_free_lesson($lesson_id)) {
        $offer = flosc_check_offer_triggers($user_data, 'lesson_complete');
        if ($offer) {
            flosc_display_offer_modal($offer);
        }
    }
}, 10, 2);

CONVERSION TRACKING:

function flosc_track_offer_conversion($offer_id, $user_id, $amount) {
    $offers = get_option('flosc_offers', []);
    if (isset($offers[$offer_id])) {
        $offers[$offer_id]['conversions'] = ($offers[$offer_id]['conversions'] ?? 0) + 1;
        update_option('flosc_offers', $offers);
        
        // Log transaction
        flosc_log_sale([
            'offer_id' => $offer_id,
            'user_id' => $user_id,
            'amount' => $amount,
            'timestamp' => current_time('mysql'),
        ]);
    }
}
-->

<form method="post" action="options.php">
<?php settings_fields('flosc_settings'); ?>
