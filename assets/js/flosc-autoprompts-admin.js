(function() {
    'use strict';

    const config = window.floscAutopromptsAdminConfig || {};
    const conditionOptions = config.conditions || {};
    const actionOptions = config.actions || {};
    const offerOptions = config.offers || {};

    function buildOptions(opts, selected) {
        return Object.entries(opts)
            .map(([value, label]) => `<option value="${value}"${value === selected ? ' selected' : ''}>${label}</option>`)
            .join('');
    }

    function resolveCardRows(row) {
        if (!row) return null;
        if (row.classList.contains('flosc-ap-row-primary')) {
            return {
                primary: row,
                secondary: row.nextElementSibling,
                expected: row.nextElementSibling ? row.nextElementSibling.nextElementSibling : null
            };
        }
        if (row.classList.contains('flosc-ap-row-secondary')) {
            return {
                primary: row.previousElementSibling,
                secondary: row,
                expected: row.nextElementSibling
            };
        }
        if (row.classList.contains('flosc-ap-expected-row')) {
            return {
                primary: row.previousElementSibling ? row.previousElementSibling.previousElementSibling : null,
                secondary: row.previousElementSibling,
                expected: row
            };
        }
        return null;
    }

    window.floscToggleTriggerValue = function(select) {
        const row = select.closest('tr');
        if (!row) return;

        const ai = row.querySelector('.flosc-tv-ai');
        const offer = row.querySelector('.flosc-tv-offer');
        const action = row.querySelector('.flosc-tv-action');
        const hidden = row.querySelector('.flosc-tv-hidden');
        const value = select.value;

        if (ai) ai.classList.toggle('flosc-hidden', value !== 'ai');
        if (offer) offer.classList.toggle('flosc-hidden', value !== 'offer');
        if (action) action.classList.toggle('flosc-hidden', value !== 'action');

        if (value === 'ai' && hidden) hidden.value = '';
        if (value === 'offer' && offer && hidden) hidden.value = offer.value;
        if (value === 'action' && action && hidden) hidden.value = action.value;
    };

    function buildExpectedBehaviorText(state, row) {
        const label = (row.querySelector(`input[name="${state}_pill_label[]"]`)?.value || '').trim();
        const userInput = (row.querySelector(`input[name="${state}_pill_user_input[]"]`)?.value || '').trim();
        const triggerType = (row.querySelector(`select[name="${state}_pill_trigger_type[]"]`)?.value || 'ai').trim().toLowerCase();
        const triggerValue = (row.querySelector(`input[name="${state}_pill_trigger_value[]"]`)?.value || '').trim();
        const condition = (row.querySelector(`select[name="${state}_pill_conditions[]"]`)?.value || (`is_${state}`)).trim();

        const visibleLabel = label || '(empty label)';
        const sentInput = userInput || label || '(empty input)';

        let triggerText = 'The AI trigger runs and sends this text into the chat pipeline for a conversational response.';
        if (triggerType === 'offer') {
            triggerText = `The offer flow runs and attempts to display offer id "${triggerValue || 'full_access'}" in chat.`;
        } else if (triggerType === 'action') {
            triggerText = `The action trigger runs with value "${triggerValue || 'open_quiz'}" and should open the matching in-chat UI flow.`;
        }

        return `User sees a pill labeled "${visibleLabel}" when condition "${condition || `is_${state}`}" is true. On click, "${sentInput}" is sent as user text. ${triggerText}`;
    }

    function updateExpectedBehaviorRow(row) {
        const card = resolveCardRows(row);
        if (!card || !card.primary || !card.secondary || !card.expected) return;

        const iconInput = card.primary.querySelector('input[name$="_pill_icon[]"]');
        const stateMatch = iconInput && iconInput.name ? iconInput.name.match(/^([a-z_]+)_pill_icon\[\]$/) : null;
        const state = stateMatch ? stateMatch[1] : '';
        if (!state) return;

        const expectedText = card.expected.querySelector('.flosc-ap-expected-text');
        if (!expectedText) return;

        const composite = {
            querySelector: function(selector) {
                return card.primary.querySelector(selector) || card.secondary.querySelector(selector);
            }
        };
        expectedText.textContent = buildExpectedBehaviorText(state, composite);
    }

    function applyAutopromptStriping() {
        document.querySelectorAll('.flosc-ap-table tbody').forEach(function(tbody) {
            const primaryRows = tbody.querySelectorAll('.flosc-ap-row-primary');
            primaryRows.forEach(function(primaryRow, index) {
                const card = resolveCardRows(primaryRow);
                if (!card) return;

                [card.primary, card.secondary, card.expected].forEach(function(r) {
                    if (r) r.classList.remove('flosc-ap-pair-even', 'flosc-ap-pair-odd');
                });

                const pairClass = index % 2 === 0 ? 'flosc-ap-pair-even' : 'flosc-ap-pair-odd';
                [card.primary, card.secondary, card.expected].forEach(function(r) {
                    if (r) r.classList.add(pairClass);
                });
            });
        });
    }

    function buildRow(state, defaultCond) {
        const offerOpts = Object.keys(offerOptions).length
            ? buildOptions(offerOptions, 'full_access')
            : '<option value="full_access">full_access (default)</option>';
        const actionOpts = buildOptions(actionOptions, 'open_quiz');
        const condOpts = buildOptions(conditionOptions, defaultCond);
        const expectedText = `User sees a pill labeled "(empty label)" when condition "${defaultCond}" is true. On click, "(empty input)" is sent as user text. The AI trigger runs and sends this text into the chat pipeline for a conversational response.`;

        return `
        <tr class="flosc-ap-row flosc-ap-row-primary">
            <td class="flosc-ap-cell" rowspan="2"><div class="flosc-ap-microlabel">Icon</div><input type="text" name="${state}_pill_icon[]" class="flosc-ap-icon-input" placeholder="💊"></td>
            <td class="flosc-ap-cell"><div class="flosc-ap-microlabel">Label</div><input type="text" name="${state}_pill_label[]" class="flosc-ap-input" placeholder="Pill label"></td>
            <td class="flosc-ap-cell" colspan="3"><div class="flosc-ap-microlabel">User Input</div><input type="text" name="${state}_pill_user_input[]" class="flosc-ap-input flosc-ap-input--w400" placeholder="Same as label"></td>
            <td class="flosc-ap-cell" rowspan="2"><button type="button" class="button flosc-ap-remove" title="Remove">&times;</button></td>
        </tr>
        <tr class="flosc-ap-row flosc-ap-row-secondary">
            <td class="flosc-ap-cell"><div class="flosc-ap-microlabel">Conditions</div><select name="${state}_pill_conditions[]" class="flosc-ap-select">${condOpts}</select></td>
            <td class="flosc-ap-cell flosc-ap-cell--w150">
                <div class="flosc-ap-microlabel">Trigger</div>
                <select name="${state}_pill_trigger_type[]" class="flosc-ap-select flosc-ap-select--w150 flosc-ap-trigger-type">
                    <option value="ai" selected>💬 AI</option>
                    <option value="offer">💰 Offer</option>
                    <option value="action">⚡ Action</option>
                </select>
            </td>
            <td class="flosc-trigger-value-cell">
                <div class="flosc-ap-microlabel">Trigger Value</div>
                <span class="flosc-tv-ai flosc-tv-hint">Sends user_input to AI</span>
                <select name="${state}_pill_trigger_value_offer[]" class="flosc-ap-select flosc-ap-select--w200 flosc-tv-offer flosc-hidden">${offerOpts}</select>
                <select name="${state}_pill_trigger_value_action[]" class="flosc-ap-select flosc-ap-select--w200 flosc-tv-action flosc-hidden">${actionOpts}</select>
                <input type="hidden" name="${state}_pill_trigger_value[]" value="" class="flosc-tv-hidden">
                <input type="hidden" name="${state}_pill_style[]" value="pill">
            </td>
        </tr>
        <tr class="flosc-ap-expected-row">
            <td colspan="6" class="flosc-ap-expected-cell"><span class="flosc-ap-expected-label">Expected behavior:</span> <span class="flosc-ap-expected-text">${expectedText}</span></td>
        </tr>`;
    }

    function buildRowWithData(state, pill) {
        const ttype = pill.trigger_type || 'ai';
        const tval = pill.trigger_value || '';
        const cond = pill.condition || `is_${state}`;

        const offerOpts = Object.keys(offerOptions).length
            ? buildOptions(offerOptions, ttype === 'offer' ? tval : 'full_access')
            : `<option value="full_access"${ttype === 'offer' && tval === 'full_access' ? ' selected' : ''}>full_access (default)</option>`;
        const actionOpts = buildOptions(actionOptions, ttype === 'action' ? tval : 'open_quiz');
        const condOpts = buildOptions(conditionOptions, cond);

        const aiHidden = ttype === 'ai' ? '' : ' flosc-hidden';
        const offerHidden = ttype === 'offer' ? '' : ' flosc-hidden';
        const actionHidden = ttype === 'action' ? '' : ' flosc-hidden';

        const esc = s => String(s || '').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        const fakeRow = {
            querySelector: function(selector) {
                const map = {
                    [`input[name="${state}_pill_label[]"]`]: { value: pill.label || '' },
                    [`input[name="${state}_pill_user_input[]"]`]: { value: pill.user_input || '' },
                    [`select[name="${state}_pill_trigger_type[]"]`]: { value: ttype },
                    [`input[name="${state}_pill_trigger_value[]"]`]: { value: tval },
                    [`select[name="${state}_pill_conditions[]"]`]: { value: cond }
                };
                return map[selector] || null;
            }
        };
        const expectedText = buildExpectedBehaviorText(state, fakeRow);

        return `
        <tr class="flosc-ap-row flosc-ap-row-primary">
            <td class="flosc-ap-cell" rowspan="2"><div class="flosc-ap-microlabel">Icon</div><input type="text" name="${state}_pill_icon[]" value="${esc(pill.icon || '')}" class="flosc-ap-icon-input" placeholder="💊"></td>
            <td class="flosc-ap-cell"><div class="flosc-ap-microlabel">Label</div><input type="text" name="${state}_pill_label[]" value="${esc(pill.label || '')}" class="flosc-ap-input" placeholder="Pill label"></td>
            <td class="flosc-ap-cell" colspan="3"><div class="flosc-ap-microlabel">User Input</div><input type="text" name="${state}_pill_user_input[]" value="${esc(pill.user_input || '')}" class="flosc-ap-input flosc-ap-input--w400" placeholder="Same as label"></td>
            <td class="flosc-ap-cell" rowspan="2"><button type="button" class="button flosc-ap-remove" title="Remove">&times;</button></td>
        </tr>
        <tr class="flosc-ap-row flosc-ap-row-secondary">
            <td class="flosc-ap-cell"><div class="flosc-ap-microlabel">Conditions</div><select name="${state}_pill_conditions[]" class="flosc-ap-select">${condOpts}</select></td>
            <td class="flosc-ap-cell flosc-ap-cell--w150">
                <div class="flosc-ap-microlabel">Trigger</div>
                <select name="${state}_pill_trigger_type[]" class="flosc-ap-select flosc-ap-select--w150 flosc-ap-trigger-type">
                    <option value="ai"${ttype === 'ai' ? ' selected' : ''}>💬 AI</option>
                    <option value="offer"${ttype === 'offer' ? ' selected' : ''}>💰 Offer</option>
                    <option value="action"${ttype === 'action' ? ' selected' : ''}>⚡ Action</option>
                </select>
            </td>
            <td class="flosc-trigger-value-cell">
                <div class="flosc-ap-microlabel">Trigger Value</div>
                <span class="flosc-tv-ai flosc-tv-hint${aiHidden}">Sends user_input to AI</span>
                <select name="${state}_pill_trigger_value_offer[]" class="flosc-ap-select flosc-ap-select--w200 flosc-tv-offer${offerHidden}">${offerOpts}</select>
                <select name="${state}_pill_trigger_value_action[]" class="flosc-ap-select flosc-ap-select--w200 flosc-tv-action${actionHidden}">${actionOpts}</select>
                <input type="hidden" name="${state}_pill_trigger_value[]" value="${esc(tval)}" class="flosc-tv-hidden">
                <input type="hidden" name="${state}_pill_style[]" value="pill">
            </td>
        </tr>
        <tr class="flosc-ap-expected-row">
            <td colspan="6" class="flosc-ap-expected-cell"><span class="flosc-ap-expected-label">Expected behavior:</span> <span class="flosc-ap-expected-text">${esc(expectedText)}</span></td>
        </tr>`;
    }

    function appendDemoPills(state, pills) {
        const tbody = document.getElementById(`tbody-${state}`);
        if (!tbody || !pills.length) return 0;

        pills.forEach(function(pill) {
            const tmpDiv = document.createElement('div');
            tmpDiv.innerHTML = buildRowWithData(state, pill);
            const rows = tmpDiv.querySelectorAll('tr');
            rows.forEach(function(row) { tbody.appendChild(row); });
            updateExpectedBehaviorRow(rows[0]);
        });

        applyAutopromptStriping();
        tbody.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return pills.length;
    }

    document.addEventListener('change', function(event) {
        const target = event.target;

        if (target.classList.contains('flosc-ap-trigger-type')) {
            window.floscToggleTriggerValue(target);
        }

        if (target.classList.contains('flosc-tv-offer') || target.classList.contains('flosc-tv-action')) {
            const hidden = target.closest('td')?.querySelector('.flosc-tv-hidden');
            if (hidden) hidden.value = target.value;
        }
    });

    document.addEventListener('input', function(event) {
        const target = event.target;

        if (target.classList.contains('flosc-ap-header-input')) {
            const state = target.dataset.state || '';
            const preview = state ? document.getElementById(`header-preview-${state}`) : null;
            if (preview) preview.textContent = target.value;
        }

        const row = target.closest('tr');
        const card = resolveCardRows(row);
        if (card && card.primary) {
            updateExpectedBehaviorRow(card.primary);
        }
    });

    document.addEventListener('click', function(event) {
        const addBtn = event.target.closest('.flosc-ap-add');
        if (addBtn) {
            const state = addBtn.dataset.state;
            const defaultCond = addBtn.dataset.defaultCond || `is_${state}`;
            const tbody = document.getElementById(`tbody-${state}`);
            if (!tbody) return;

            const tmpDiv = document.createElement('div');
            tmpDiv.innerHTML = buildRow(state, defaultCond);
            const rows = tmpDiv.querySelectorAll('tr');
            rows.forEach(function(row) { tbody.appendChild(row); });

            const primaryRow = rows[0];
            updateExpectedBehaviorRow(primaryRow);
            applyAutopromptStriping();
            primaryRow.querySelector('input[name$="_pill_label[]"]')?.focus();
            return;
        }

        const removeBtn = event.target.closest('.flosc-ap-remove');
        if (removeBtn) {
            const sourceRow = removeBtn.closest('tr');
            const card = resolveCardRows(sourceRow);
            if (!card) return;

            if (card.primary) card.primary.remove();
            if (card.secondary) card.secondary.remove();
            if (card.expected) card.expected.remove();
            applyAutopromptStriping();
            return;
        }

        const loadSetBtn = event.target.closest('.flosc-load-pill-set');
        if (loadSetBtn) {
            const state = loadSetBtn.dataset.state;
            const pills = JSON.parse(loadSetBtn.dataset.pills || '[]');
            const count = appendDemoPills(state, pills);
            if (!count) return;

            const original = loadSetBtn.textContent;
            loadSetBtn.textContent = `✓ ${count} pills loaded!`;
            loadSetBtn.disabled = true;
            setTimeout(function() {
                loadSetBtn.textContent = original;
                loadSetBtn.disabled = false;
            }, 2000);
            return;
        }

        const loadItemBtn = event.target.closest('.flosc-load-pill-item');
        if (loadItemBtn) {
            const state = loadItemBtn.dataset.state;
            const pill = JSON.parse(loadItemBtn.dataset.pill || '{}');
            if (!pill || Object.keys(pill).length === 0) return;

            const count = appendDemoPills(state, [pill]);
            if (!count) return;

            const original = loadItemBtn.textContent;
            loadItemBtn.textContent = '✓ Loaded';
            loadItemBtn.disabled = true;
            setTimeout(function() {
                loadItemBtn.textContent = original;
                loadItemBtn.disabled = false;
            }, 1400);
        }
    });

    document.querySelectorAll('.flosc-ap-trigger-type').forEach(function(select) {
        window.floscToggleTriggerValue(select);
    });

    document.querySelectorAll('.flosc-ap-row-primary').forEach(function(row) {
        updateExpectedBehaviorRow(row);
    });

    applyAutopromptStriping();
})();
