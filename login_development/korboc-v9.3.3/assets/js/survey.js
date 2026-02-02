/**
 * Korboc Survey v7.9.7 - Session Table Migration Fix
 *
 * WORKING FEATURES (DO NOT BREAK):
 * - Social login (Facebook, Google) with event capture
 * - Popup fallback with countdown overlay
 * - Data persistence across OAuth flow
 * - Email signup/login
 * - Email sync between sticky footer and form
 *
 * FIXED IN v7.9.7:
 * - "Turpināt" button now works (removed call to non-existent function)
 * - Multi-page survey navigation functional
 */
(function($) {
    'use strict';

    var formId = 'korboc-survey-form';
    var sessionId = null;

    $(document).ready(function() {
        if (korbocSurvey.debug) console.log('[Korboc v7.9.7] Loaded - Social login + Email signup');

        // ============================================================
        // INITIALIZATION - Bug 14 FIX: Proper sequencing to prevent race conditions
        // ============================================================
        // Step 1: Initialize session (must complete before loading data)
        initSession();

        // Step 2: Set up email for logged-in users (independent, can run in parallel)
        setupEmailForLoggedInUser();
        setupEmailSync();

        // Step 3: Load saved data (depends on session being initialized)
        // Session ID must be set before this runs
        loadSavedData();

        // Also try after delay (catches dynamically loaded forms) - Wait 500ms for BuddyBoss to finish rendering dynamic elements
        setTimeout(setupEmailForLoggedInUser, 500);

        // ============================================================
        // FORM AUTO-SAVE
        // ============================================================
        $('#' + formId + ' input, #' + formId + ' textarea, #' + formId + ' select').on('change', function() {
            saveData();
        });

        // ============================================================
        // FORM SUBMISSION
        // ============================================================
        // Use event delegation for submit button (works even if button added dynamically)
        $(document).on('click', '.btn-submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            submitSurvey();
        });

        // ============================================================
        // POPUP FALLBACK: If we're IN a popup after login, show countdown
        // ============================================================
        if (window.opener && !window.opener.closed) {
            var returnUrl = getCookie('korboc_return_url') || localStorage.getItem('korboc_return_url');

            if (korbocSurvey.isLoggedIn && returnUrl && returnUrl.includes('korboc-aptauja')) {
                console.log('[Korboc v7.9.7] IN POPUP - logged in, showing countdown');
                showPopupCountdown(returnUrl);
            }
        }

        // ============================================================
        // POST-LOGIN REDIRECT (for same-window flow)
        // ============================================================
        if (korbocSurvey.isLoggedIn && !window.opener) {
            var returnUrl = getCookie('korboc_return_url') || localStorage.getItem('korboc_return_url');
            var currentUrl = window.location.href;

            console.log('[Korboc v7.9.7] Logged in (same window), returnURL:', returnUrl);

            if (returnUrl && returnUrl.includes('korboc-aptauja') && !currentUrl.includes('korboc-aptauja')) {
                console.log('[Korboc v7.9.7] Redirecting to survey');
                deleteCookie('korboc_return_url');
                localStorage.removeItem('korboc_return_url');
                window.location.href = returnUrl;
            } else if (returnUrl && currentUrl.includes('korboc-aptauja')) {
                console.log('[Korboc v7.9.7] Already on survey, cleaning up returnURL');
                deleteCookie('korboc_return_url');
                localStorage.removeItem('korboc_return_url');
            }
        }
    });

    // ============================================================
    // SOCIAL LOGIN HANDLER - EVENT CAPTURE (runs BEFORE BuddyBoss)
    // ============================================================
    // Uses capture phase (addEventListener with true) to intercept clicks
    // before BuddyBoss handlers run. This prevents popup in most browsers.
    // If popup opens anyway, countdown overlay handles it.
    document.addEventListener('click', function(e) {
        var target = e.target;

        // Walk up the DOM to find social login link
        while (target && target !== document) {
            var isButton = target.classList && (
                target.classList.contains('bb-social-login-button') ||
                target.classList.contains('social-login-button')
            );

            var href = target.getAttribute && target.getAttribute('href');
            var isSocialLink = href && (
                href.includes('oauth') ||
                href.includes('facebook') ||
                href.includes('google')
            );

            if (isButton || isSocialLink) {
                console.log('[Korboc v7.9.7] CAPTURED social login click BEFORE BuddyBoss');

                // STOP ALL other handlers (including BuddyBoss)
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                // Get form data
                var formData = getFormData();
                var fieldCount = Object.keys(formData).length;

                // Only save returnURL if user has filled fields
                if (fieldCount > 2) {
                    var returnUrl = window.location.href;

                    // Save to BOTH cookie and localStorage (belt and suspenders)
                    setCookie('korboc_return_url', returnUrl, 1);
                    localStorage.setItem('korboc_return_url', returnUrl);
                    console.log('[Korboc v7.9.7] Saved returnURL:', returnUrl);

                    // Save data synchronously (async: false ensures save completes before redirect)
                    $.ajax({
                        url: korbocSurvey.ajaxUrl,
                        type: 'POST',
                        async: false,
                        data: {
                            action: 'korboc_autosave',
                            nonce: korbocSurvey.nonce,
                            session_id: sessionId,
                            field_data: JSON.stringify(formData)
                        },
                        success: function(response) {
                            if (response.success && response.data.session_id) {
                                sessionId = response.data.session_id;
                                setCookie('korboc_session_id', sessionId, 1);
                                console.log('[Korboc v7.9.7] Session saved:', sessionId);
                            }
                        },
                        error: function() {
                            console.error('[Korboc v7.9.7] AJAX save failed');
                        }
                    });
                }

                // Redirect to OAuth (same window - no popup)
                console.log('[Korboc v7.9.7] Redirecting to OAuth (same window):', href);
                window.location.href = href;

                return false;
            }

            target = target.parentElement;
        }
    }, true); // TRUE = CAPTURE PHASE (runs before bubble handlers)

    // ============================================================
    // EMAIL SIGNUP - Creates account from email only
    // ============================================================
    // Called by sticky footer button: onclick="korbocQuickAccountCreation()"
    // Creates minimal account, auto-logs in, BuddyBoss sends welcome email
    window.korbocQuickAccountCreation = function() {
        var email = $('#sticky-email').val();

        console.log('[Korboc v7.9.7] Email signup clicked:', email);

        // Validate email
        if (!email || !isValidEmail(email)) {
            alert('Lūdzu, ievadiet derīgu e-pasta adresi');
            return;
        }

        // Save form data before creating account (so it's available after login)
        var formData = getFormData();
        
        // CRITICAL: Explicitly add email to formData (in case form field name differs or sync failed)
        formData['email'] = email;
        
        console.log('[Korboc v7.9.7] Saving data with email:', email, 'Fields:', Object.keys(formData).length);
        
        // Always save (even if no other fields - email is required)
        $.ajax({
            url: korbocSurvey.ajaxUrl,
            type: 'POST',
            async: false,
            data: {
                action: 'korboc_autosave',
                nonce: korbocSurvey.nonce,
                session_id: sessionId,
                field_data: JSON.stringify(formData)
            },
            success: function(response) {
                if (response.success && response.data.session_id) {
                    sessionId = response.data.session_id;
                    setCookie('korboc_session_id', sessionId, 1);
                    console.log('[Korboc v7.9.7] Data saved with session:', sessionId);
                }
            },
            error: function() {
                console.error('[Korboc v7.9.7] Failed to save data before account creation');
            }
        })

        // Create account via AJAX
        $.ajax({
            url: korbocSurvey.ajaxUrl,
            type: 'POST',
            data: {
                action: 'korboc_quick_account',
                email: email,
                nonce: korbocSurvey.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('[Korboc v7.9.7] Account created, reloading page');
                    // User is now logged in, reload page to show logged-in state
                    window.location.reload();
                } else {
                    alert(response.data.message || 'Kļūda izveidojot kontu');
                }
            },
            error: function() {
                alert('Radās kļūda. Lūdzu, mēģiniet vēlreiz.');
            }
        });
    };

    // ============================================================
    // SET EMAIL FOR LOGGED IN USER
    // ============================================================
    // If user is logged in, pre-fill email and make readonly
    function setupEmailForLoggedInUser() {
        if (!korbocSurvey.isLoggedIn || !korbocSurvey.userEmail) {
            return;
        }

        // Find all possible email fields
        var $allEmailFields = $('input[name="email"], input[type="email"], input#email, #sticky-email');
        
        if ($allEmailFields.length > 0) {
            $allEmailFields.each(function() {
                var $field = $(this);
                // Only set if empty or different value
                if (!$field.val() || $field.val() !== korbocSurvey.userEmail) {
                    $field.val(korbocSurvey.userEmail);
                    $field.prop('readonly', true).css('background-color', '#f5f5f5');
                }
            });
        }
    }

    // ============================================================
    // EMAIL SYNC - Sync between sticky footer and form field
    // ============================================================
    // When user types in sticky footer, copy to form field (and vice versa)
    // Only works if not logged in (logged in users can't change email)
    function setupEmailSync() {
        if (korbocSurvey.isLoggedIn) {
            return; // Don't sync if logged in - email is readonly
        }

        // Sticky footer → Form field
        $('#sticky-email').on('input', function() {
            var email = $(this).val();
            $('input[name="email"]').val(email);
            console.log('[Korboc v7.9.7] Email synced to form:', email);
        });

        // Form field → Sticky footer
        $('input[name="email"]').on('input', function() {
            var email = $(this).val();
            $('#sticky-email').val(email);
            console.log('[Korboc v7.9.7] Email synced to sticky footer:', email);
        });
    }

    // ============================================================
    // EMAIL VALIDATION
    // ============================================================
    function isValidEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    // ============================================================
    // POPUP COUNTDOWN OVERLAY
    // ============================================================
    // Shows full-screen overlay in popup window after successful login
    // Auto-closes after 7 seconds or when user clicks button
    function showPopupCountdown(returnUrl) {
        var overlay = $('<div id="korboc-popup-overlay" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.95);z-index:999999;display:flex;align-items:center;justify-content:center;color:white;font-family:Arial,sans-serif;"><div style="text-align:center;"><h1 style="font-size:32px;margin-bottom:20px;">✓ Ielogošanās veiksmīga!</h1><button id="korboc-continue-btn" style="font-size:24px;padding:20px 40px;background:#4CAF50;color:white;border:none;border-radius:8px;cursor:pointer;margin-bottom:20px;">Turpināt aptauju (<span id="korboc-countdown">7</span>s)</button><p style="font-size:14px;opacity:0.7;">Šis logs aizvērsies automātiski</p></div></div>');

        $('body').append(overlay);

        var countdown = 7;
        var interval = setInterval(function() {
            countdown--;
            $('#korboc-countdown').text(countdown);
            if (countdown <= 0) {
                clearInterval(interval);
                closePopupAndReturn(returnUrl);
            }
        }, 1000);

        $('#korboc-continue-btn').on('click', function() {
            clearInterval(interval);
            closePopupAndReturn(returnUrl);
        });
    }

    // ============================================================
    // CLOSE POPUP AND RETURN TO SURVEY
    // ============================================================
    function closePopupAndReturn(returnUrl) {
        // Try to signal parent window (may fail due to cross-origin)
        if (window.opener && !window.opener.closed) {
            try {
                window.opener.postMessage({
                    type: 'korboc_login_complete',
                    returnUrl: returnUrl
                }, '*');
            } catch(e) {
                console.log('[Korboc v7.9.7] Could not message parent:', e);
            }
        }

        // Close popup
        window.close();

        // If close failed (some browsers block it), redirect in popup
        setTimeout(function() {
            window.location.href = returnUrl;
        }, 500);
    }

    // ============================================================
    // COOKIE HELPERS
    // ============================================================
    // SameSite=Lax allows cookies to survive OAuth redirects
    function setCookie(name, value, days) {
        var expires = '';
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
    }

    function getCookie(name) {
        var nameEQ = name + '=';
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return decodeURIComponent(c.substring(nameEQ.length, c.length));
        }
        return null;
    }

    function deleteCookie(name) {
        document.cookie = name + '=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC; SameSite=Lax';
    }

    // ============================================================
    // SESSION INITIALIZATION
    // ============================================================
    // Reads session ID from cookie (set by PHP or previous JS save)
    function initSession() {
        sessionId = getCookie('korboc_session_id');
        if (sessionId) {
            console.log('[Korboc v7.9.7] Session ID found:', sessionId);
        }
    }

    // ============================================================
    // GET FORM DATA
    // ============================================================
    // Extracts all form fields into key-value object
    // Searches broadly - not just within formId (catches all survey fields)
    function getFormData() {
        var data = {};
        
        // Search broadly for all input/textarea/select elements with names
        $('input[name], textarea[name], select[name]').each(function() {
            var $field = $(this);
            var name = $field.attr('name');
            
            // Skip WordPress/system fields
            if (name.indexOf('wp-') === 0 || name.indexOf('_wp') === 0) {
                return;
            }

            if ($field.attr('type') === 'checkbox') {
                if ($field.is(':checked')) {
                    if (!data[name]) data[name] = [];
                    data[name].push($field.val());
                }
            } else if ($field.attr('type') === 'radio') {
                if ($field.is(':checked')) {
                    data[name] = $field.val();
                }
            } else {
                var val = $field.val();
                if (val) { // Only save non-empty values
                    data[name] = val;
                }
            }
        });
        return data;
    }

    // ============================================================
    // AUTO-SAVE ON FIELD CHANGE
    // ============================================================
    // Saves form data to server when user changes any field
    // Only saves if more than 2 fields filled (prevents empty saves)
    function saveData() {
        var formData = getFormData();
        if (Object.keys(formData).length <= 2) return;

        $.ajax({
            url: korbocSurvey.ajaxUrl,
            type: 'POST',
            data: {
                action: 'korboc_autosave',
                nonce: korbocSurvey.nonce,
                session_id: sessionId,
                field_data: JSON.stringify(formData)
            },
            success: function(response) {
                if (response.success && response.data.session_id) {
                    sessionId = response.data.session_id;
                    setCookie('korboc_session_id', sessionId, 1);
                }
            }
        });
    }

    // ============================================================
    // LOAD SAVED DATA
    // ============================================================
    // Loads draft data from server on page load
    // If logged in: loads from user_meta
    // If not logged in: loads from sessions table by session_id
    function loadSavedData() {
        $.ajax({
            url: korbocSurvey.ajaxUrl,
            type: 'POST',
            data: {
                action: 'korboc_load_draft',
                nonce: korbocSurvey.nonce,
                session_id: sessionId
            },
            success: function(response) {
                if (response.success && response.data.data) {
                    var data = response.data.data;
                    console.log('[Korboc v7.9.7] Loading', Object.keys(data).length, 'fields');

                    $.each(data, function(name, value) {
                        // Escape square brackets in field names for jQuery selector (e.g., vision_opinion[] becomes vision_opinion\[\])
                        var escapedName = name.replace(/\[/g, '\\[').replace(/\]/g, '\\]');
                        var $field = $('[name="' + escapedName + '"]');
                        if ($field.attr('type') === 'checkbox' || $field.attr('type') === 'radio') {
                            // Multiple checkboxes can be selected (e.g., vision_opinion[]), saved as an array.
                            // Iterate through the array to check all previously selected checkboxes.
                            if (Array.isArray(value)) {
                                value.forEach(function(v) {
                                    $field.filter('[value="' + v + '"]').prop('checked', true);
                                });
                            } else {
                                $field.filter('[value="' + value + '"]').prop('checked', true);
                            }
                        } else {
                            $field.val(value);
                        }
                    });

                    // Trigger email sync after loading
                    if (data.email) {
                        $('#sticky-email').val(data.email);
                    }
                }
                
                // Set email again after data loads (for logged-in users)
                setupEmailForLoggedInUser();
            }
        });
    }

    // ============================================================
    // SUBMIT SURVEY
    // ============================================================
    /**
     * v7.9.7: Submits survey, marks as complete, shows thank you, redirects
     *
     * FIXES:
     * - Bug 11: Added client-side validation before submission
     * - Bug 12: Added null check before clearInterval
     * - Bug 13: Made contact URL configurable via korbocSurvey.contactUrl
     * - Bug 15: Added 30-second timeout to AJAX call
     * - Bug 16: Wrapped localStorage in try-catch for private browsing
     * - Issue 17: Wrapped console.log in debug flag check
     */
    function submitSurvey() {
        if (korbocSurvey.debug) console.log('[v7.9.7] submitSurvey called');

        // Bug 11 FIX: Validate required fields before submission
        var $form = $('#korboc-survey-form');
        var isValid = true;
        var firstInvalidField = null;

        $form.find('[required]').each(function() {
            var $field = $(this);
            var value = $field.val();

            // Check if field is empty
            if (!value || (typeof value === 'string' && !value.trim())) {
                isValid = false;
                if (!firstInvalidField) {
                    firstInvalidField = $field;
                }
                $field.addClass('error');
                setTimeout(function() { $field.removeClass('error'); }, 2000);
            }

            // Extra validation for email format
            if ($field.attr('type') === 'email' && value && value.trim()) {
                var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailPattern.test(value)) {
                    isValid = false;
                    if (!firstInvalidField) {
                        firstInvalidField = $field;
                    }
                    $field.addClass('error');
                    setTimeout(function() { $field.removeClass('error'); }, 2000);
                    alert('Lūdzu, ievadiet derīgu e-pasta adresi (piemēram: vards@epasts.lv)');
                }
            }
        });

        if (!isValid) {
            if (firstInvalidField) {
                firstInvalidField.focus();
            }
            alert('Lūdzu, aizpildiet visus obligātos laukus (atzīmētus ar *)');
            return;
        }

        // Disable submit button and show loading state
        var $submitBtn = $('.btn-submit');
        var $btnText = $submitBtn.find('.btn-text');
        $submitBtn.prop('disabled', true).addClass('disabled');

        // Animate dots: Nosūtīt. → Nosūtīt.. → Nosūtīt... (loops)
        var dotCount = 1;
        var loadingInterval = setInterval(function() {
            var dots = '.'.repeat(dotCount);
            $btnText.text('Nosūtīt' + dots);
            dotCount = (dotCount % 3) + 1; // Cycles 1, 2, 3, 1, 2, 3...
        }, 300);

        // Get form data - use FRESH nonce from JavaScript (form nonce may be stale after login)
        var formData = $form.serialize();

        // Remove old nonce from serialized data and add fresh one
        formData = formData.replace(/&?nonce=[^&]*/, '');
        formData += '&nonce=' + encodeURIComponent(korbocSurvey.nonce);
        formData += '&action=korboc_submit_survey';

        $.ajax({
            url: korbocSurvey.ajaxUrl,
            type: 'POST',
            data: formData,
            timeout: 30000, // Bug 15 FIX: 30-second timeout prevents infinite loading
            success: function(response) {
                // Bug 12 FIX: Null check before clearing interval
                if (loadingInterval) {
                    clearInterval(loadingInterval);
                }

                if (korbocSurvey.debug) console.log('[v7.9.7] AJAX success:', response);

                if (response.success) {
                    // Hide all form sections
                    $('.form-section').not('.thank-you-section').hide();

                    // Show thank you section
                    $('.thank-you-section').show();

                    // Update thank you message
                    $('.thank-you-section h2').text('🎉 ' + response.data.message);

                    // Bug 16 FIX: Wrap localStorage in try-catch for private browsing
                    try {
                        localStorage.removeItem('korboc_return_url');
                    } catch (e) {
                        if (korbocSurvey.debug) console.warn('[v7.9.7] localStorage not available:', e);
                    }

                    // Clean up cookies
                    deleteCookie('korboc_return_url');
                    deleteCookie('korboc_session_id');

                    // Give user 3 seconds to read thank you message before redirect
                    setTimeout(function() {
                        window.location.href = response.data.redirect || '/';
                    }, 3000);
                } else {
                    $submitBtn.prop('disabled', false).removeClass('disabled');
                    $btnText.text('Nosūtīt');
                    alert('Kļūda: ' + (response.data.message || 'Nezināma kļūda'));
                }
            },
            error: function(xhr, status, error) {
                // Bug 12 FIX: Null check before clearing interval
                if (loadingInterval) {
                    clearInterval(loadingInterval);
                }

                if (korbocSurvey.debug) console.error('[v7.9.7] AJAX error:', xhr, status, error);

                $submitBtn.prop('disabled', false).removeClass('disabled');
                $btnText.text('Nosūtīt');

                // Bug 13 FIX: Use configurable contact URL from PHP
                var contactUrl = korbocSurvey.contactUrl || 'https://dainis.net/chat';
                var errorMsg = 'Radās kļūda. ';

                if (status === 'timeout') {
                    errorMsg += 'Pieprasījums pārsniedza laika limitu. ';
                }

                errorMsg += 'Lūdzu, sazinājaties ar Daini šeit ' + contactUrl;
                alert(errorMsg);
            }
        });
    }

})(jQuery);
