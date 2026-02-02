<?php
/**
 * Shortcodes v3.3
 * Multistep survey with progress bar, smooth transitions, and sticky footer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register shortcodes
 */
function korboc_register_shortcodes() {
    add_shortcode('korboc_survey', 'korboc_survey_shortcode');
}

/**
 * Multistep survey shortcode
 */
function korboc_survey_shortcode($atts) {
    // Get editable content from settings
    $greeting = get_option('korboc_greeting', '');
    $enable_sticky = get_option('korboc_enable_sticky_footer', 'yes');
    
    // Sticky footer settings
    $sticky_email_placeholder = get_option('korboc_sticky_footer_email_placeholder', 'jūsu@epasts.lv');
    $sticky_button_text = get_option('korboc_sticky_footer_button_text', 'Izveidot Profilu');
    
    // Get all labels
    $labels = array(
        'first_name' => get_option('korboc_label_first_name', '1. Jūsu vārds (priekšvārds)? *'),
        'last_name' => get_option('korboc_label_last_name', '2. Jūsu uzvārds? *'),
        'how_to_address' => get_option('korboc_label_how_to_address', '3. Kā Jūs uzrunāt? *'),
        'choir_name' => get_option('korboc_label_choir_name', '4. Kā sauc Jūsu kori? *'),
        'rehearsal_location' => get_option('korboc_label_rehearsal_location', '5. Kurā vietā pārsvarā notiek jūsu mēģinājumi?'),
        'role_in_choir' => get_option('korboc_label_role_in_choir', '6. Jūsu loma korī?'),
        'voice_part' => get_option('korboc_label_voice_part', '7. Kādā balsī Jūs dziedat?'),
        'is_soloist' => get_option('korboc_label_is_soloist', '8. Vai esi solists/soliste?'),
        'soloist_repertoire' => get_option('korboc_label_soloist_repertoire', 'Ja vēlies tikt iekļauts/iekļauta korboc.lv solistu datubāzē, lūdzu apraksti, kuras solo partijas esi dziedājis/dziedājusi.'),
        'email' => get_option('korboc_label_email', '9. E-pasta adrese? *'),
        'phone' => get_option('korboc_label_phone', '10. Telefons?'),
        'singer_count' => get_option('korboc_label_singer_count', '11. Apmēram cik dziedātāju dzied Jūsu korī? *'),
    );
    
    // Get survey questions
    $questions = array(
        'q1' => get_option('korboc_question_1', '1.J: Kas būtu vissvarīgākās funkcijas, lai korboc.lv būtu noderīgs Jūsu korim?'),
        'q2' => get_option('korboc_question_2', '2.J: Kas vēl noteikti būtu jāiekļauj, lai šis būtu vērtīgs risinājums Jūsu korim?'),
        'q3' => get_option('korboc_question_3', '3.J: Kā Jums patīk vīzija par dronu gaismas šovu virs Meža parka, kamēr dziedam un dejojam Bitīti — parādot pasaulei, ka spējam nosargāt mūsu robežas, tautu, kultūru, valsti un nākotni?'),
        'q4' => get_option('korboc_question_4', '4.J: Vai €100/korim + €1/dziedātājam gadā šķiet pamatota cena par šādiem pakalpojumiem?'),
        'q5' => get_option('korboc_question_5', '5.J: Lai elektroniskā komunikācija būtu bez maksas, vai tiešām ir labāk tikt galā ar WhatsApp, epastu, un citām tehnoloģijām, kas pelna ar to, ka tās mūs izseko (WhatsApp privātums | Google reklāmas), kādas ir Jūsu domas?'),
    );
    
    $is_logged_in = is_user_logged_in();
    
    ob_start();
    ?>
    <div class="korboc-survey-container multistep">
        <div class="korboc-survey-header">
            <div class="folk-ornament">✻ ✻ ✻</div>
            <h1>Korboc.lv Aptauja</h1>
            <p class="tagline">Veidojam Latvijas koru digitālo nākotni kopā</p>
            <div class="folk-ornament">✻ ✻ ✻</div>
        </div>

        <!-- Progress Bar -->
        <div class="korboc-progress-container">
            <div class="korboc-progress-bar">
                <div class="korboc-progress-fill" style="width: 0%"></div>
            </div>
            <div class="korboc-progress-text">1. lapa no 3</div>
        </div>

        <form id="korboc-survey-form" class="korboc-survey-form">
            <input type="hidden" name="action" value="korboc_submit_survey">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('korboc_survey_nonce'); ?>">
            
            <!-- PAGE 1: Survey Questions + Profile Creation -->
            <div class="survey-page active" data-page="1">

                <?php if ($greeting): ?>
                <div class="korboc-survey-intro">
                    <?php echo wpautop($greeting); ?>
                </div>
                <?php endif; ?>

                <div class="form-section">
                    <h2>Dalāmies ar atmiņām</h2>

                    <!-- NEW: Song Festival Memory (Optional) -->
                    <div class="form-group">
                        <label for="song_festival_memory">Kāda ir Jūsu visskaistākā Dziesmu svētku atmiņa?</label>
                        <p class="field-hint">(Vēlāk varēs pievienot vairākas, bet šobrīd — kurš mirklis Jums ir vistuvākais, vissvētākais, visspilgtākais?)</p>
                        <textarea id="song_festival_memory" name="song_festival_memory" rows="4" placeholder="Jūsu atmiņa..."></textarea>
                        <p class="field-disclaimer" style="color: #998; font-size: 0.9em; margin-top: 0.5rem;">Šī atbilde šobrīd paliks tikai jūsu profilā — vēlāk varēsiet izvēlēties, vai dalīties ar citiem.</p>
                    </div>

                    <!-- Vision Question -->
                    <div class="form-group">
                        <label>Kā Jums patīk vīzija par dronu gaismas šovu virs Meža parka, kamēr dziedam un dejojam Bitīti — parādot pasaulei, ka spējam nosargāt mūsu robežas, tautu, kultūru, valsti un nākotni?</label>
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="vision_opinion[]" value="🔥 Brīnišķīga vīzija...lai top!">
                                <span>🔥 Brīnišķīga vīzija...lai top!</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="vision_opinion[]" value="✨ Patīk! Interesanta ideja.">
                                <span>✨ Patīk! Interesanta ideja.</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="vision_opinion[]" value="👍 Labi, varu atbalstīt.">
                                <span>👍 Labi, varu atbalstīt.</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="vision_opinion[]" value="🤔 Neitrāli / Nav viedokļa.">
                                <span>🤔 Neitrāli / Nav viedokļa.</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="vision_opinion[]" value="😐 Neesmu pārliecināts/a.">
                                <span>😐 Neesmu pārliecināts/a.</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="vision_opinion[]" value="❌ Nepatīk">
                                <span>❌ Nepatīk</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="vision_opinion[]" value="Cits viedoklis:" onchange="toggleVisionOther(this.checked)">
                                <span>Cits viedoklis:</span>
                            </label>
                            <textarea id="vision-other-text" name="vision_opinion_other" rows="3" style="display:none; margin-left: 2rem; margin-top: 0.5rem;"></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2>Izveidosim Jūsu Profilu</h2>

                    <div class="form-group">
                        <label for="first_name"><?php echo esc_html($labels['first_name']); ?></label>
                        <p class="field-hint">Piemēram: Līga, Jānis...</p>
                        <input type="text" id="first_name" name="first_name" required aria-label="Vārds (obligāti)" aria-required="true">
                    </div>

                    <div class="form-group">
                        <label for="last_name"><?php echo esc_html($labels['last_name']); ?></label>
                        <input type="text" id="last_name" name="last_name" required aria-label="Uzvārds (obligāti)" aria-required="true">
                    </div>

                    <div class="form-group">
                        <label for="how_to_address"><?php echo esc_html($labels['how_to_address']); ?></label>
                        <p class="field-hint">Piemēram, kad kāds saka "Labdien...[Līga, Jāni, Mārtiņ, Sarmīte...]?"</p>
                        <input type="text" id="how_to_address" name="how_to_address" required aria-label="Kā Jūs uzrunāt (obligāti)" aria-required="true">
                    </div>

                    <div class="form-group">
                        <label for="email"><?php echo esc_html($labels['email']); ?></label>
                        <p class="field-hint">(Vajadzīga konta izveidei)</p>
                        <input type="email" id="email" name="email" required aria-label="E-pasts (obligāti)" aria-required="true">
                    </div>

                    <div class="form-group">
                        <label for="phone"><?php echo esc_html($labels['phone']); ?></label>
                        <p class="field-hint">(Varat pievienot vēlāk)</p>
                        <input type="tel" id="phone" name="phone">
                    </div>
                </div>
            </div>

            <!-- PAGE 2: Choir Details -->
            <div class="survey-page" data-page="2">
                <div class="form-section">
                    <h2>Par Jūsu Kori</h2>

                    <div class="form-group">
                        <label for="choir_name"><?php echo esc_html($labels['choir_name']); ?></label>
                        <input type="text" id="choir_name" name="choir_name">
                    </div>

                    <div class="form-group">
                        <label for="rehearsal_location"><?php echo esc_html($labels['rehearsal_location']); ?></label>
                        <input type="text" id="rehearsal_location" name="rehearsal_location">
                    </div>

                    <div class="form-group">
                        <label for="role_in_choir"><?php echo esc_html($labels['role_in_choir']); ?></label>
                        <p class="field-hint">Piemēram: diriģents/diriģente, dziedātājs/dziedātāja, valdes loceklis/locekle...</p>
                        <input type="text" id="role_in_choir" name="role_in_choir">
                    </div>

                    <div class="form-group">
                        <label for="voice_part"><?php echo esc_html($labels['voice_part']); ?></label>
                        <p class="field-hint">Piemēram: Soprāns, Alts, Tenors, Bass...</p>
                        <input type="text" id="voice_part" name="voice_part">
                    </div>

                    <div class="form-group">
                        <label><?php echo esc_html($labels['is_soloist']); ?></label>
                        <div class="radio-group">
                            <label class="radio-label">
                                <input type="radio" name="is_soloist" value="yes" onchange="toggleSoloistDetails(true)">
                                <span>Jā</span>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="is_soloist" value="no" onchange="toggleSoloistDetails(false)">
                                <span>Nē</span>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="is_soloist" value="later" onchange="toggleSoloistDetails(false)">
                                <span>Pagaidām nenorādīšu</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group" id="soloist-details" style="display:none;">
                        <label for="soloist_repertoire"><?php echo esc_html($labels['soloist_repertoire']); ?></label>
                        <textarea id="soloist_repertoire" name="soloist_repertoire" rows="4"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="singer_count"><?php echo esc_html($labels['singer_count']); ?></label>
                        <input type="number" id="singer_count" name="singer_count" min="1">
                    </div>
                </div>
            </div>

            <!-- PAGE 3: Additional Survey Questions -->
            <div class="survey-page" data-page="3">
                <div class="form-section">
                    <h2>Papildu Jautājumi</h2>

                    <div class="form-group">
                        <label for="additional_valuable"><?php echo esc_html($questions['q2']); ?></label>
                        <textarea id="additional_valuable" name="additional_valuable" rows="5"></textarea>
                    </div>

                    <div class="form-group">
                        <label><?php echo esc_html($questions['q4']); ?></label>
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="pricing_opinion[]" value="Jā">
                                <span>Jā</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="pricing_opinion[]" value="Varbūt">
                                <span>Varbūt</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="pricing_opinion[]" value="Nē">
                                <span>Nē</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="pricing_opinion[]" value="Cits viedoklis:" onchange="togglePricingOther(this.checked)">
                                <span>Cits viedoklis:</span>
                            </label>
                            <textarea id="pricing-other-text" name="pricing_opinion_other" rows="3" style="display:none; margin-left: 2rem; margin-top: 0.5rem;"></textarea>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="communication_thoughts"><?php echo esc_html($questions['q5']); ?></label>
                        <textarea id="communication_thoughts" name="communication_thoughts" rows="5"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label consent-checkbox">
                            <input type="checkbox" name="gdpr_consent" value="yes" checked aria-label="GDPR piekrišana">
                            <span>Ņemu vērā GDPR datu aizsardzības noteikumus un piekrītu, ka korboc.lv var ar mani sazināties par šo projektu *</span>
                        </label>
                    </div>

                    <div class="consent-info">
                    
                        <p>🔒 Jūsu dati ir droši un netiks izplatīti trešajām personām</p>
                        <p>✅ Jebkurā laikā varat atteikties no komunikācijas</p>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <div class="survey-navigation">
                <button type="button" class="btn-prev" onclick="korbocPrevPage()" style="display:none;">
                    ← Atpakaļ
                </button>
                <button type="button" class="btn-next" onclick="korbocNextPage()">
                    Turpināt →
                </button>
                <button type="submit" class="btn-submit" style="display:none;">
                    <span class="btn-text">Nosūtīt</span>
                    <span class="btn-loading" style="display:none;">⏳</span>
                </button>
            </div>

            <div class="form-message" style="display:none;"></div>
        </form>

        <!-- Sticky Account Creation Footer -->
        <?php if (!$is_logged_in && $enable_sticky === 'yes'): ?>
        <div class="korboc-sticky-footer">
            <div class="footer-content">
                <div class="footer-social-section">
                    <?php echo korboc_render_buddyboss_social_buttons(); ?>
                </div>
                <div class="footer-divider">vai</div>
                <div class="footer-email-section">
                    <!-- Honeypot field (invisible to humans, bots will fill it) -->
                    <input type="text" name="website_url" id="website_url" value="" style="position:absolute;left:-9999px;width:1px;height:1px;" tabindex="-1" autocomplete="off" aria-hidden="true">

                    <input type="email" id="sticky-email" placeholder="<?php echo esc_attr($sticky_email_placeholder); ?>" class="sticky-email-input">
                    <button type="button" class="btn-create-account" onclick="korbocQuickAccountCreation()">
                        <?php echo esc_html($sticky_button_text); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="korboc-survey-footer">
            <div class="folk-ornament">✻ ✻ ✻</div>
            <p>Paldies, par Jūsu līdzdalību!</p>
        </div>
    </div>

    <script>
    // Multistep navigation
    let currentPage = 1;
    const totalPages = 3;

    // Restore page number after login if coming back to survey
    (function() {
        const savedPage = localStorage.getItem('korboc_survey_page');
        if (savedPage && parseInt(savedPage) > 1) {
            currentPage = parseInt(savedPage);
            // Clear the saved page
            localStorage.removeItem('korboc_survey_page');
            // Show the correct page after DOM is ready
            setTimeout(function() {
                showPage(currentPage);
            }, 100);
        }
    })();

    function korbocNextPage() {
        // Validate current page
        if (!validateCurrentPage()) {
            return;
        }

        // v7.8.30: Autosave already happens on field change in survey.js
        // No need for extra save here

        if (currentPage < totalPages) {
            currentPage++;
            showPage(currentPage);
        }
    }

    function korbocPrevPage() {
        if (currentPage > 1) {
            currentPage--;
            showPage(currentPage);
        }
    }

    function showPage(pageNum) {
        // Hide all pages
        document.querySelectorAll('.survey-page').forEach(page => {
            page.classList.remove('active');
        });

        // Show current page
        document.querySelector(`.survey-page[data-page="${pageNum}"]`).classList.add('active');

        // Update progress
        const progress = ((pageNum - 1) / (totalPages - 1)) * 100;
        document.querySelector('.korboc-progress-fill').style.width = progress + '%';
        document.querySelector('.korboc-progress-text').textContent = `${pageNum}. lapa no ${totalPages}`;

        // Update buttons
        document.querySelector('.btn-prev').style.display = pageNum > 1 ? 'inline-block' : 'none';
        
        if (pageNum === 3) {
            document.querySelector('.btn-next').style.display = 'none';
            document.querySelector('.btn-submit').style.display = 'inline-block';
        } else {
            document.querySelector('.btn-next').style.display = 'inline-block';
            document.querySelector('.btn-submit').style.display = 'none';
        }

        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function validateCurrentPage() {
        const currentPageEl = document.querySelector(`.survey-page[data-page="${currentPage}"]`);
        const requiredFields = currentPageEl.querySelectorAll('[required]');

        for (let field of requiredFields) {
            if (!field.value.trim()) {
                field.focus();
                field.classList.add('error');
                setTimeout(() => field.classList.remove('error'), 2000);

                // v6.0.6: Friendly message for email field with emojis
                if (field.name === 'email' && currentPage === 1) {
                    alert('Ievadiet epasta adresi vai izveidojiet kontu ar Jūsu mīļāko soctīkla profīlu lai turpinātu izpildīt anketu! 🙂🇱🇻🎵');
                }
                return false;
            }

            // v6.0.5: Extra validation for email field format
            if (field.type === 'email' && field.value.trim()) {
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailPattern.test(field.value)) {
                    field.focus();
                    field.classList.add('error');
                    setTimeout(() => field.classList.remove('error'), 2000);
                    alert('Lūdzu, ievadiet derīgu e-pasta adresi (piemēram: vards@epasts.lv)');
                    return false;
                }
            }
        }
        return true;
    }

    function toggleSoloistDetails(show) {
        document.getElementById('soloist-details').style.display = show ? 'block' : 'none';
    }
    function toggleOtherFeature(show) {
        document.getElementById('other-feature-text').style.display = show ? 'block' : 'none';
    }
    function toggleVisionOther(show) {
        document.getElementById('vision-other-text').style.display = show ? 'block' : 'none';
    }
    function togglePricingOther(show) {
        document.getElementById('pricing-other-text').style.display = show ? 'block' : 'none';
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        showPage(1);

        // v6.0.8 CRITICAL FIX: Restore form data immediately
        // The field disabling was WIPING restored values!
        if (typeof korbocRestoreFormData === 'function') {
            korbocRestoreFormData();
        }

        // v7.4.1: Social login handler removed from here
        // ALL handling is in survey.js to avoid duplicate handlers
        // The duplicate handler was causing multiple windows/tabs
    });
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Render BuddyBoss social login buttons
 */
function korboc_render_buddyboss_social_buttons() {
    if (!class_exists('BB_SSO') || !method_exists('BB_SSO', 'render_buttons_with_container')) {
        return '<p class="social-note">Social login nav pieejams</p>';
    }
    
    return BB_SSO::render_buttons_with_container(array(
        'label_type' => 'register',
        'style' => 'icon',
    ));
}
