/**
 * FLOSC IVR Admin JavaScript
 * v04_03: Admin interface interactions
 */

(function($) {
    'use strict';

    class IVRAdmin {
        constructor() {
            this.form = $('#flosc-ivr-form');
            this.init();
        }

        init() {
            // Toggle phase sections
            $('.ivr-phase-title').on('click', (e) => {
                $(e.currentTarget).closest('.ivr-phase-section').toggleClass('collapsed');
            });

            // Add message buttons
            $('.ivr-add-message').on('click', (e) => this.addMessage(e));

            // Remove message buttons (delegated)
            this.form.on('click', '.ivr-remove-message', (e) => this.removeMessage(e));

            // Form submit
            this.form.on('submit', (e) => this.saveConfig(e));
        }

        addMessage(e) {
            const btn = $(e.currentTarget);
            const phase = btn.data('phase');
            const type = btn.data('type');
            const list = btn.siblings('.ivr-message-list');

            const messageHtml = `
                <div class="ivr-message-item">
                    <div class="ivr-message-text">
                        <textarea class="large-text" rows="2" placeholder="Enter message text..."></textarea>
                    </div>
                    <div class="ivr-message-conditions">
                        <label>Conditions:</label>
                        <input type="number" placeholder="Message count" class="small-text condition-input" data-condition="message_count" />
                        <input type="number" placeholder="Session minutes" class="small-text condition-input" data-condition="session_minutes" />
                        <input type="number" placeholder="Inactivity seconds" class="small-text condition-input" data-condition="inactivity_seconds" />
                    </div>
                    ${type === 'triggered' ? `
                    <div class="ivr-message-action">
                        <label>Action:</label>
                        <select class="action-select">
                            <option value="">None</option>
                            <option value="open_quiz">Open Quiz</option>
                            <option value="open_login">Open Login</option>
                            <option value="open_payment">Open Payment</option>
                            <option value="warn_close">Warn Close</option>
                        </select>
                    </div>
                    ` : ''}
                    <div class="ivr-message-actions">
                        <label>
                            <input type="checkbox" checked />
                            Enabled
                        </label>
                        <button type="button" class="button ivr-remove-message">Remove</button>
                    </div>
                </div>
            `;

            list.append(messageHtml);
        }

        removeMessage(e) {
            if (confirm('Remove this message?')) {
                $(e.currentTarget).closest('.ivr-message-item').remove();
            }
        }

        collectConfig() {
            const config = {};
            const phases = ['freeline', 'login_prompt', 'login', 'offer', 'sale', 'content'];

            phases.forEach(phase => {
                config[phase] = {
                    initial_message: $(`textarea[name="${phase}_initial"]`).val() || '',
                    sequential_messages: [],
                    prompt_cards: [],
                    pool_messages: [],
                    triggered_messages: []
                };

                // Collect prompt cards
                $(`.ivr-prompt-cards[data-phase="${phase}"] .ivr-prompt-card-item`).each(function() {
                    const $item = $(this);
                    const action = $item.find('.ivr-card-header code').text();
                    const userMessage = $item.find('.ivr-card-user-message').val();
                    const assistantResponse = $item.find('.ivr-card-assistant-response').val();
                    const enabled = $item.find('.ivr-card-enabled').is(':checked');
                    const sendToAi = $item.find('.ivr-card-send-to-ai').is(':checked');

                    if (action && userMessage) {
                        config[phase].prompt_cards.push({
                            action: action,
                            user_message: userMessage,
                            assistant_response: assistantResponse,
                            enabled: enabled,
                            send_to_ai: sendToAi
                        });
                    }
                });

                // Collect sequential messages
                $(`.ivr-message-list[data-phase="${phase}"][data-type="sequential"] .ivr-message-item`).each(function() {
                    const $item = $(this);
                    const text = $item.find('textarea').val();
                    const enabled = $item.find('input[type="checkbox"]').is(':checked');

                    if (text.trim()) {
                        config[phase].sequential_messages.push({
                            text: text,
                            enabled: enabled
                        });
                    }
                });

                // Collect pool messages
                $(`.ivr-message-list[data-phase="${phase}"][data-type="pool"] .ivr-message-item`).each(function() {
                    const $item = $(this);
                    const text = $item.find('textarea').val();
                    const enabled = $item.find('input[type="checkbox"]').is(':checked');
                    const conditions = {};

                    $item.find('.condition-input').each(function() {
                        const $input = $(this);
                        const conditionType = $input.data('condition');
                        const value = $input.val();
                        if (value) {
                            conditions[conditionType] = parseInt(value, 10);
                        }
                    });

                    if (text.trim()) {
                        config[phase].pool_messages.push({
                            text: text,
                            conditions: conditions,
                            enabled: enabled
                        });
                    }
                });

                // Collect triggered messages
                $(`.ivr-message-list[data-phase="${phase}"][data-type="triggered"] .ivr-message-item`).each(function() {
                    const $item = $(this);
                    const text = $item.find('textarea').val();
                    const enabled = $item.find('input[type="checkbox"]').is(':checked');
                    const action = $item.find('.action-select').val() || '';
                    const conditions = {};

                    $item.find('.condition-input').each(function() {
                        const $input = $(this);
                        const conditionType = $input.data('condition');
                        const value = $input.val();
                        if (value) {
                            conditions[conditionType] = parseInt(value, 10);
                        }
                    });

                    if (text.trim()) {
                        config[phase].triggered_messages.push({
                            text: text,
                            conditions: conditions,
                            action: action,
                            enabled: enabled
                        });
                    }
                });
            });

            return config;
        }

        saveConfig(e) {
            e.preventDefault();

            const config = this.collectConfig();
            const $status = $('.ivr-save-status');
            const $form = this.form;

            $form.addClass('ivr-loading');
            $status.removeClass('success error').text('Saving...');

            $.ajax({
                url: floscIVR.ajax_url,
                method: 'POST',
                data: {
                    action: 'flosc_save_ivr_config',
                    nonce: floscIVR.nonce,
                    config: JSON.stringify(config)
                },
                success: (response) => {
                    $form.removeClass('ivr-loading');
                    if (response.success) {
                        $status.addClass('success').text('✓ Configuration saved successfully!');
                        setTimeout(() => $status.text(''), 3000);
                    } else {
                        $status.addClass('error').text('✗ Error: ' + (response.data?.message || 'Failed to save'));
                    }
                },
                error: () => {
                    $form.removeClass('ivr-loading');
                    $status.addClass('error').text('✗ Network error. Please try again.');
                }
            });
        }
    }

    // Initialize
    $(document).ready(() => {
        new IVRAdmin();
    });

})(jQuery);
