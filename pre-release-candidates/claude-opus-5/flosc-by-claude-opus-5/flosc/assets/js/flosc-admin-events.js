(function() {
    'use strict';

    function toggleDetailsById(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.open = !el.open;
    }

    function callGlobalFn(name, args) {
        const fn = window[name];
        if (typeof fn === 'function') {
            fn.apply(window, args);
        }
    }

    document.addEventListener('submit', function(event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;

        const message = form.dataset.confirmMessage || '';
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    document.addEventListener('change', function(event) {
        const target = event.target;
        if (!(target instanceof Element)) return;

        const action = target.getAttribute('data-flosc-action');
        if (!action) return;

        if (action === 'redirect-on-change') {
            const value = target.value || '';
            if (value) {
                window.location = value;
            }
            return;
        }

        if (action === 'sync-color-target') {
            const targetId = target.getAttribute('data-sync-target');
            if (!targetId) return;
            const colorInput = document.getElementById(targetId);
            if (colorInput) {
                colorInput.value = target.value;
            }
            return;
        }

        if (action === 'toggle-offer-fields') {
            const messageId = target.getAttribute('data-msg-id') || '';
            callGlobalFn('floscToggleOfferFields', [target, messageId]);
            return;
        }

        if (action === 'toggle-processor-fields') {
            const safeId = target.getAttribute('data-offer-id') || '';
            callGlobalFn('floscToggleProcessorFields', [safeId, target.value]);
            return;
        }

        if (action === 'toggle-after-offer-row') {
            const safeId = target.getAttribute('data-offer-safe-id') || '';
            const row = document.getElementById('offer-after-offer-row-' + safeId);
            if (row) {
                if (target.value === 'after_offer') {
                    row.classList.remove('flosc-hidden');
                } else {
                    row.classList.add('flosc-hidden');
                }
            }
            return;
        }

        if (action === 'toggle-format-card') {
            callGlobalFn('floscToggleFmt', [target]);
        }
    });

    document.addEventListener('click', function(event) {
        const clickable = event.target.closest('[data-confirm-message], [data-flosc-action], [data-stop-propagation]');
        if (!clickable) return;

        if (clickable.hasAttribute('data-stop-propagation')) {
            event.stopPropagation();
        }

        const confirmMessage = clickable.getAttribute('data-confirm-message');
        if (confirmMessage && !window.confirm(confirmMessage)) {
            event.preventDefault();
            event.stopPropagation();
            return;
        }

        const action = clickable.getAttribute('data-flosc-action');
        if (!action) return;

        if (action === 'perform-ivr-action') {
            event.preventDefault();
            const ivrAction = clickable.getAttribute('data-ivr-action') || '';
            if (window.floscAppInstance && typeof window.floscAppInstance.performIVRAction === 'function') {
                window.floscAppInstance.performIVRAction(ivrAction);
            }
            return;
        }

        if (action === 'open-access-code-payment') {
            event.preventDefault();
            if (window.floscAppInstance && typeof window.floscAppInstance._showAccessCodeInput === 'function') {
                window.floscAppInstance._showAccessCodeInput('payment');
            }
            return;
        }

        if (action === 'submit-form') {
            event.preventDefault();
            const formId = clickable.getAttribute('data-form-id') || '';
            const form = document.getElementById(formId);
            if (form instanceof HTMLFormElement) {
                form.requestSubmit();
            }
            return;
        }

        if (action === 'copy-text') {
            event.preventDefault();
            const copyText = clickable.getAttribute('data-copy-text') || '';
            if (!copyText || !navigator.clipboard) return;
            navigator.clipboard.writeText(copyText).then(function() {
                const original = clickable.textContent;
                clickable.textContent = 'Copied!';
                window.setTimeout(function() {
                    clickable.textContent = original;
                }, 2000);
            }).catch(function() {});
            return;
        }

        if (action === 'copy-element-text') {
            event.preventDefault();
            const sourceId = clickable.getAttribute('data-source-id') || '';
            const source = sourceId ? document.getElementById(sourceId) : null;
            if (!source || !navigator.clipboard) return;
            navigator.clipboard.writeText(source.textContent || '').then(function() {
                const alertMsg = clickable.getAttribute('data-alert-message') || '';
                if (alertMsg) {
                    window.alert(alertMsg);
                }
            }).catch(function() {});
            return;
        }

        if (action === 'toggle-offer-card') {
            const offerId = clickable.getAttribute('data-offer-id') || '';
            callGlobalFn('floscToggleOffer', [offerId]);
            return;
        }

        if (action === 'toggle-msg-card') {
            const msgId = clickable.getAttribute('data-msg-id') || '';
            callGlobalFn('floscToggleMsg', [msgId]);
            return;
        }

        if (action === 'toggle-new-editor') {
            const phaseId = clickable.getAttribute('data-phase-id') || '';
            const shouldOpen = clickable.getAttribute('data-open') === '1';
            callGlobalFn('floscToggleNewEditor', [phaseId, shouldOpen]);
            return;
        }

        if (action === 'toggle-condition-ref') {
            event.preventDefault();
            const detailsId = clickable.getAttribute('data-details-id') || '';
            if (detailsId) {
                toggleDetailsById(detailsId);
            }
            return;
        }

        if (action === 'test-api-endpoint') {
            event.preventDefault();
            callGlobalFn('floscTestAPI', []);
        }
    });
})();
