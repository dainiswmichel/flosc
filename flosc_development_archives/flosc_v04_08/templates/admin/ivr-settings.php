<?php
/**
 * IVR Configuration Admin Page
 * v04_03: FLOSC Phase-Aware Message System
 */

if (!defined('ABSPATH')) exit;

$ivr_config = FLOSC_IVR_Manager::get_instance()->get_config();
?>

<div class="wrap flosc-ivr-admin">
    <h1>FLOSC IVR Configuration</h1>
    <p class="description">Configure messages for each phase of the FLOSC funnel. Messages are delivered based on user state and conditions.</p>

    <div class="flosc-ivr-notice">
        <strong>Understanding FLOSC Phases:</strong>
        <ul>
            <li><strong>Freeline:</strong> Visitor (not logged in) - Goal: Get them to take the quiz</li>
            <li><strong>Login Prompt:</strong> Post-quiz (not logged in) - Goal: Get them to create an account</li>
            <li><strong>Login:</strong> Logged-in user - Goal: Present the offer</li>
            <li><strong>Offer:</strong> Sales pitch via chat - Goal: Get them to purchase</li>
            <li><strong>Sale:</strong> Post-purchase - Goal: Onboard to content</li>
            <li><strong>Content:</strong> Ongoing access - Goal: Support and encourage</li>
        </ul>
    </div>

    <form id="flosc-ivr-form" class="flosc-ivr-config">

        <!-- Freeline Phase -->
        <div class="ivr-phase-section">
            <h2 class="ivr-phase-title">
                <span class="dashicons dashicons-arrow-down-alt2 ivr-toggle"></span>
                Freeline Phase (Visitor)
            </h2>
            <div class="ivr-phase-content">
                <div class="ivr-message-group">
                    <h3>Initial Message</h3>
                    <p class="description">First message shown to visitor when they land on the app</p>
                    <textarea name="freeline_initial" class="large-text" rows="4"><?php echo esc_textarea($ivr_config['freeline']['initial_message'] ?? ''); ?></textarea>
                </div>

                <div class="ivr-message-group">
                    <h3>IntroPanel Prompt Cards</h3>
                    <p class="description">Configure the messages triggered by clicking IntroPanel cards (Get started, How does it work, etc.)</p>
                    <div class="ivr-prompt-cards" data-phase="freeline">
                        <?php foreach (($ivr_config['freeline']['prompt_cards'] ?? []) as $i => $card): ?>
                        <div class="ivr-prompt-card-item">
                            <div class="ivr-card-header">
                                <strong>Card Action:</strong> <code><?php echo esc_html($card['action'] ?? ''); ?></code>
                            </div>
                            <div class="ivr-card-field">
                                <label>User Message (displayed as user input):</label>
                                <input type="text" class="regular-text ivr-card-user-message" value="<?php echo esc_attr($card['user_message'] ?? ''); ?>" />
                            </div>
                            <div class="ivr-card-field">
                                <label>Assistant Response:</label>
                                <textarea class="large-text ivr-card-assistant-response" rows="4"><?php echo esc_textarea($card['assistant_response'] ?? ''); ?></textarea>
                            </div>
                            <div class="ivr-card-actions">
                                <label>
                                    <input type="checkbox" class="ivr-card-enabled" <?php checked($card['enabled'] ?? true); ?> />
                                    Enabled
                                </label>
                                <label style="margin-left: 20px;">
                                    <input type="checkbox" class="ivr-card-send-to-ai" <?php checked($card['send_to_ai'] ?? false); ?> />
                                    Send to AI (ignore configured response)
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="ivr-message-group">
                    <h3>Pool Messages (Conditional)</h3>
                    <p class="description">Messages shown based on conditions (message count, time, etc.)</p>
                    <div class="ivr-message-list" data-phase="freeline" data-type="pool">
                        <?php foreach (($ivr_config['freeline']['pool_messages'] ?? []) as $i => $msg): ?>
                        <div class="ivr-message-item">
                            <div class="ivr-message-text">
                                <textarea class="large-text" rows="2"><?php echo esc_textarea($msg['text'] ?? ''); ?></textarea>
                            </div>
                            <div class="ivr-message-conditions">
                                <label>Conditions:</label>
                                <?php if (!empty($msg['conditions']['message_count'])): ?>
                                <span class="condition-tag">Message count: <?php echo esc_html($msg['conditions']['message_count']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($msg['conditions']['session_minutes'])): ?>
                                <span class="condition-tag">Session time: <?php echo esc_html($msg['conditions']['session_minutes']); ?> min</span>
                                <?php endif; ?>
                            </div>
                            <div class="ivr-message-actions">
                                <label>
                                    <input type="checkbox" <?php checked($msg['enabled'] ?? true); ?> />
                                    Enabled
                                </label>
                                <button type="button" class="button ivr-remove-message">Remove</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button ivr-add-message" data-phase="freeline" data-type="pool">Add Pool Message</button>
                </div>

                <div class="ivr-message-group">
                    <h3>Triggered Messages</h3>
                    <p class="description">Messages that fire on specific triggers (push to quiz, inactivity, etc.)</p>
                    <div class="ivr-message-list" data-phase="freeline" data-type="triggered">
                        <?php foreach (($ivr_config['freeline']['triggered_messages'] ?? []) as $i => $msg): ?>
                        <div class="ivr-message-item">
                            <div class="ivr-message-text">
                                <textarea class="large-text" rows="2"><?php echo esc_textarea($msg['text'] ?? ''); ?></textarea>
                            </div>
                            <div class="ivr-message-conditions">
                                <label>Conditions:</label>
                                <?php if (!empty($msg['conditions']['message_count'])): ?>
                                <span class="condition-tag">Message count: <?php echo esc_html($msg['conditions']['message_count']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($msg['conditions']['inactivity_seconds'])): ?>
                                <span class="condition-tag">Inactivity: <?php echo esc_html($msg['conditions']['inactivity_seconds']); ?>s</span>
                                <?php endif; ?>
                                <?php if (isset($msg['conditions']['quiz_taken'])): ?>
                                <span class="condition-tag">Quiz taken: <?php echo $msg['conditions']['quiz_taken'] ? 'Yes' : 'No'; ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($msg['action'])): ?>
                            <div class="ivr-message-action">
                                <label>Action:</label>
                                <span class="action-tag"><?php echo esc_html($msg['action']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="ivr-message-actions">
                                <label>
                                    <input type="checkbox" <?php checked($msg['enabled'] ?? true); ?> />
                                    Enabled
                                </label>
                                <button type="button" class="button ivr-remove-message">Remove</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button ivr-add-message" data-phase="freeline" data-type="triggered">Add Triggered Message</button>
                </div>
            </div>
        </div>

        <!-- Login Prompt Phase -->
        <div class="ivr-phase-section">
            <h2 class="ivr-phase-title">
                <span class="dashicons dashicons-arrow-down-alt2 ivr-toggle"></span>
                Login Prompt Phase (Post-Quiz)
            </h2>
            <div class="ivr-phase-content">
                <div class="ivr-message-group">
                    <h3>Initial Message</h3>
                    <p class="description">Message shown after quiz completion, prompting user to create account</p>
                    <textarea name="login_prompt_initial" class="large-text" rows="3"><?php echo esc_textarea($ivr_config['login_prompt']['initial_message'] ?? ''); ?></textarea>
                </div>

                <div class="ivr-message-group">
                    <h3>Pool Messages (Conditional)</h3>
                    <div class="ivr-message-list" data-phase="login_prompt" data-type="pool">
                        <?php foreach (($ivr_config['login_prompt']['pool_messages'] ?? []) as $msg): ?>
                        <div class="ivr-message-item">
                            <textarea class="large-text" rows="2"><?php echo esc_textarea($msg['text'] ?? ''); ?></textarea>
                            <label>
                                <input type="checkbox" <?php checked($msg['enabled'] ?? true); ?> />
                                Enabled
                            </label>
                            <button type="button" class="button ivr-remove-message">Remove</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button ivr-add-message" data-phase="login_prompt" data-type="pool">Add Message</button>
                </div>
            </div>
        </div>

        <!-- Login Phase -->
        <div class="ivr-phase-section">
            <h2 class="ivr-phase-title">
                <span class="dashicons dashicons-arrow-down-alt2 ivr-toggle"></span>
                Login Phase (Logged-In User)
            </h2>
            <div class="ivr-phase-content">
                <div class="ivr-message-group">
                    <h3>Initial Message</h3>
                    <p class="description">Welcome message with quiz results. Variables: {name}, {score}</p>
                    <textarea name="login_initial" class="large-text" rows="4"><?php echo esc_textarea($ivr_config['login']['initial_message'] ?? ''); ?></textarea>
                </div>

                <div class="ivr-message-group">
                    <h3>Sequential Messages</h3>
                    <p class="description">Messages delivered in order after initial message</p>
                    <div class="ivr-message-list" data-phase="login" data-type="sequential">
                        <?php foreach (($ivr_config['login']['sequential_messages'] ?? []) as $msg): ?>
                        <div class="ivr-message-item">
                            <textarea class="large-text" rows="2"><?php echo esc_textarea($msg['text'] ?? ''); ?></textarea>
                            <label>
                                <input type="checkbox" <?php checked($msg['enabled'] ?? true); ?> />
                                Enabled
                            </label>
                            <button type="button" class="button ivr-remove-message">Remove</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button ivr-add-message" data-phase="login" data-type="sequential">Add Sequential Message</button>
                </div>

                <div class="ivr-message-group">
                    <h3>Pool Messages (Conditional)</h3>
                    <div class="ivr-message-list" data-phase="login" data-type="pool">
                        <?php foreach (($ivr_config['login']['pool_messages'] ?? []) as $msg): ?>
                        <div class="ivr-message-item">
                            <textarea class="large-text" rows="2"><?php echo esc_textarea($msg['text'] ?? ''); ?></textarea>
                            <label>
                                <input type="checkbox" <?php checked($msg['enabled'] ?? true); ?> />
                                Enabled
                            </label>
                            <button type="button" class="button ivr-remove-message">Remove</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button ivr-add-message" data-phase="login" data-type="pool">Add Message</button>
                </div>
            </div>
        </div>

        <!-- Offer Phase -->
        <div class="ivr-phase-section">
            <h2 class="ivr-phase-title">
                <span class="dashicons dashicons-arrow-down-alt2 ivr-toggle"></span>
                Offer Phase (Sales Pitch)
            </h2>
            <div class="ivr-phase-content">
                <div class="ivr-message-group">
                    <h3>Initial Message</h3>
                    <p class="description">Sales pitch introduction. Variables: {product_name}, {price}, {product_description}</p>
                    <textarea name="offer_initial" class="large-text" rows="5"><?php echo esc_textarea($ivr_config['offer']['initial_message'] ?? ''); ?></textarea>
                </div>

                <div class="ivr-message-group">
                    <h3>Sequential Messages</h3>
                    <div class="ivr-message-list" data-phase="offer" data-type="sequential">
                        <?php foreach (($ivr_config['offer']['sequential_messages'] ?? []) as $msg): ?>
                        <div class="ivr-message-item">
                            <textarea class="large-text" rows="2"><?php echo esc_textarea($msg['text'] ?? ''); ?></textarea>
                            <label>
                                <input type="checkbox" <?php checked($msg['enabled'] ?? true); ?> />
                                Enabled
                            </label>
                            <button type="button" class="button ivr-remove-message">Remove</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button ivr-add-message" data-phase="offer" data-type="sequential">Add Sequential Message</button>
                </div>

                <div class="ivr-message-group">
                    <h3>Pool Messages (Objection Handlers)</h3>
                    <div class="ivr-message-list" data-phase="offer" data-type="pool">
                        <?php foreach (($ivr_config['offer']['pool_messages'] ?? []) as $msg): ?>
                        <div class="ivr-message-item">
                            <textarea class="large-text" rows="2"><?php echo esc_textarea($msg['text'] ?? ''); ?></textarea>
                            <label>
                                <input type="checkbox" <?php checked($msg['enabled'] ?? true); ?> />
                                Enabled
                            </label>
                            <button type="button" class="button ivr-remove-message">Remove</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button ivr-add-message" data-phase="offer" data-type="pool">Add Message</button>
                </div>
            </div>
        </div>

        <!-- Sale Phase -->
        <div class="ivr-phase-section">
            <h2 class="ivr-phase-title">
                <span class="dashicons dashicons-arrow-down-alt2 ivr-toggle"></span>
                Sale Phase (Post-Purchase)
            </h2>
            <div class="ivr-phase-content">
                <div class="ivr-message-group">
                    <h3>Initial Message</h3>
                    <p class="description">Congratulations and next steps. Variables: {product_name}</p>
                    <textarea name="sale_initial" class="large-text" rows="5"><?php echo esc_textarea($ivr_config['sale']['initial_message'] ?? ''); ?></textarea>
                </div>

                <div class="ivr-message-group">
                    <h3>Sequential Messages</h3>
                    <div class="ivr-message-list" data-phase="sale" data-type="sequential">
                        <?php foreach (($ivr_config['sale']['sequential_messages'] ?? []) as $msg): ?>
                        <div class="ivr-message-item">
                            <textarea class="large-text" rows="2"><?php echo esc_textarea($msg['text'] ?? ''); ?></textarea>
                            <label>
                                <input type="checkbox" <?php checked($msg['enabled'] ?? true); ?> />
                                Enabled
                            </label>
                            <button type="button" class="button ivr-remove-message">Remove</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button ivr-add-message" data-phase="sale" data-type="sequential">Add Sequential Message</button>
                </div>
            </div>
        </div>

        <!-- Content Phase -->
        <div class="ivr-phase-section">
            <h2 class="ivr-phase-title">
                <span class="dashicons dashicons-arrow-down-alt2 ivr-toggle"></span>
                Content Phase (Ongoing Access)
            </h2>
            <div class="ivr-phase-content">
                <div class="ivr-message-group">
                    <h3>Initial Message</h3>
                    <p class="description">Welcome back message for returning users</p>
                    <textarea name="content_initial" class="large-text" rows="3"><?php echo esc_textarea($ivr_config['content']['initial_message'] ?? ''); ?></textarea>
                </div>

                <div class="ivr-message-group">
                    <h3>Pool Messages (Support & Encouragement)</h3>
                    <div class="ivr-message-list" data-phase="content" data-type="pool">
                        <?php foreach (($ivr_config['content']['pool_messages'] ?? []) as $msg): ?>
                        <div class="ivr-message-item">
                            <textarea class="large-text" rows="2"><?php echo esc_textarea($msg['text'] ?? ''); ?></textarea>
                            <label>
                                <input type="checkbox" <?php checked($msg['enabled'] ?? true); ?> />
                                Enabled
                            </label>
                            <button type="button" class="button ivr-remove-message">Remove</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button ivr-add-message" data-phase="content" data-type="pool">Add Message</button>
                </div>
            </div>
        </div>

        <div class="ivr-save-section">
            <button type="submit" class="button button-primary button-large">Save IVR Configuration</button>
            <span class="ivr-save-status"></span>
        </div>

    </form>
</div>
