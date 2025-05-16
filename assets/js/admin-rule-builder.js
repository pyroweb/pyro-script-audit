/**
 * Pyro Script Audit - Admin Rule Builder
 *
 * Manages the modal UI for creating and editing context-aware rules
 * for scripts (and potentially styles in the future).
 *
 * @since 3.1.0
 */
window.PsSaRuleBuilder = (function() {
    'use strict';

    // Check if pyroSaWpApiSettings is defined (localized from PHP)
    if (typeof pyroSaWpApiSettings === 'undefined') {
        console.error('pyroSaWpApiSettings is not defined. Rule builder cannot function without API settings.');
        // Return a dummy object or throw an error to prevent further issues
        return { open: function() { alert('Rule Builder Error: Missing API settings. Check console.'); } };
    }

    // DOM References for the #pyro-sa-modal
    const modal            = document.getElementById('pyro-sa-modal');
    const typeSel          = document.getElementById('pyro-sa-type');
    const extraDiv         = document.getElementById('pyro-sa-extra');
    const listBox          = document.getElementById('pyro-sa-list');
    const modeSel          = document.getElementById('pyro-sa-mode');
    const currentHandleSpan= document.getElementById('pyro-sa-current');
    const addRuleButton    = document.getElementById('pyro-sa-add');
    const saveRulesButton  = document.getElementById('pyro-sa-save');
    const closeRulesButton = document.getElementById('pyro-sa-close');
    const messageArea      = document.getElementById('pyro-sa-rule-modal-messages'); // For displaying messages

    // API settings from localized data
    const restApiBaseUrl   = pyroSaWpApiSettings.root; // e.g., https://site.com/wp-json/
    const restApiNonce     = pyroSaWpApiSettings.nonce;
    // const restApiNamespace = pyroSaWpApiSettings.namespace; // e.g., pyro-sa/v1 - used in apiSubPath

    // HTML options for dynamic dropdowns from localized data
    const postTypeOptions  = pyroSaWpApiSettings.postTypes || '';
    const taxonomyOptions  = pyroSaWpApiSettings.taxes || '';
    const templateOptions  = pyroSaWpApiSettings.templates || '';

    // Internal state for the current modal session
    const _modalState = {
        currentHandle: '',
        currentRules: {}, // Plain object to store rules
        currentApiFullRootPath: '' // e.g., https://site.com/wp-json/pyro-sa/v1/rules/scripts/
    };

    function showModalMessage(message, isError = false) {
        if (messageArea) {
            messageArea.textContent = message;
            messageArea.className = 'notice ' + (isError ? 'notice-error is-dismissible' : 'notice-success is-dismissible');
            messageArea.style.display = 'block';
            // Auto-hide success messages after a delay
            if (!isError) {
                setTimeout(() => {
                    if (messageArea.textContent === message) { // Only hide if message hasn't changed
                         messageArea.style.display = 'none';
                    }
                }, 4000);
            }
        } else {
            alert(message); // Fallback
        }
    }
    
    function clearModalMessages() {
        if (messageArea) {
            messageArea.textContent = '';
            messageArea.style.display = 'none';
        }
    }


    function fmtRuleDisplay(cb_key, p) {
        let display_cb = cb_key;
        let prefix = '';
        if (cb_key.startsWith('__neg:')) {
            display_cb = cb_key.substring(6);
            prefix = 'NOT ';
        }
        let params_string = '';
        if (Array.isArray(p)) {
            params_string = p.map(s_param => {
                if (typeof s_param === 'string') {
                    // Basic quoting, consider more robust CSV-like quoting if params can contain quotes/commas
                    return (s_param === '' || s_param.includes(',')) ? `"${s_param}"` : s_param;
                }
                return s_param; // numbers, booleans
            }).join(', ');
        } else if (p === true || p === null) {
            params_string = '';
        } else {
            params_string = (typeof p === 'string' && (p === '' || p.includes(','))) ? `"${p}"` : p;
        }
        return `${prefix}${display_cb}(${params_string})`;
    }

    function renderCurrentRules() {
        listBox.innerHTML = '';
        Object.keys(_modalState.currentRules).filter(k => k !== '__mode').forEach(k => {
            listBox.insertAdjacentHTML('beforeend',
                `<div class="rule-item">
                   <span>${fmtRuleDisplay(k, _modalState.currentRules[k])}</span>
                   <button type="button" class="button button-small button-link-delete del" data-k="${k}" aria-label="Remove this rule">×</button>
                 </div>`);
        });
    }

    typeSel.onchange = () => {
        extraDiv.innerHTML = ''; // Clear previous extra fields
        const selectedRuleType = typeSel.value;
        let extraHtmlInputs = '';

        if (selectedRuleType === 'posttype') {
            extraHtmlInputs = `<label for="rb-val-posttype">${pyroSaWpApiSettings.i18n?.postType || 'Post type:'}</label>
                               <select id="rb-val-posttype"><option value="">all</option>${postTypeOptions}</select>`;
        } else if (selectedRuleType === 'tax' || selectedRuleType === 'has_term') {
            extraHtmlInputs = `<label for="rb-tax-select">${pyroSaWpApiSettings.i18n?.taxonomy || 'Taxonomy:'}</label>
                               <select id="rb-tax-select">${taxonomyOptions}</select>
                               <label for="rb-term-input" class="screen-reader-text">${pyroSaWpApiSettings.i18n?.term || 'Term:'}</label>
                               <input id="rb-term-input" type="text" placeholder="${pyroSaWpApiSettings.i18n?.termPlaceholder || 'Term slug/ID (optional)'}" style="margin-left:5px; width: calc(100% - 100px - 10px);">`;
        } else if (selectedRuleType === 'template') {
            extraHtmlInputs = `<label for="rb-val-template">${pyroSaWpApiSettings.i18n?.template || 'Template:'}</label>
                               <select id="rb-val-template"><option value="">— select template —</option>${templateOptions}</select>`;
        }

        if (selectedRuleType) { // If a rule type is selected, add the negation dropdown
            extraDiv.innerHTML = `<div style="margin-bottom:8px;">${extraHtmlInputs}</div>
                                  <div>
                                      <label for="rb-neg-select">${pyroSaWpApiSettings.i18n?.condition || 'Condition:'}</label>
                                      <select id="rb-neg-select" style="margin-left: 5px;">
                                          <option value="is">${pyroSaWpApiSettings.i18n?.is || 'Is'}</option>
                                          <option value="not">${pyroSaWpApiSettings.i18n?.isNot || 'Is Not'}</option>
                                      </select>
                                  </div>`;
        }
    };

    addRuleButton.onclick = () => {
        clearModalMessages();
        const selectedRuleType = typeSel.value;
        if (!selectedRuleType) { showModalMessage(pyroSaWpApiSettings.i18n?.chooseRuleType || 'Please choose a rule type.', true); return; }

        let base_cb_name = selectedRuleType.replace('()','');
        let param = true;

        // DOM elements for parameters, get them *after* typeSel.onchange has run
        const valSelector = document.getElementById('rb-val-' + base_cb_name) || document.getElementById('rb-val-' + selectedRuleType) || document.getElementById('rb-val'); // More specific IDs
        const taxSelector = document.getElementById('rb-tax-select');
        const termInput = document.getElementById('rb-term-input');
        const negSelector = document.getElementById('rb-neg-select');

        if (selectedRuleType === 'posttype') {
            base_cb_name = 'is_singular';
            param = valSelector && valSelector.value ? valSelector.value : true;
        } else if (selectedRuleType === 'tax') {
            base_cb_name = 'is_tax';
            const tax_val = taxSelector ? taxSelector.value : '';
            const term_val = termInput ? termInput.value.trim() : '';
            if (!tax_val) { showModalMessage(pyroSaWpApiSettings.i18n?.taxonomyRequired || 'Taxonomy is required for is_tax.', true); return; }
            param = term_val ? [tax_val, term_val] : tax_val;
        } else if (selectedRuleType === 'has_term') {
            // base_cb_name is already 'has_term'
            const tax_val = taxSelector ? taxSelector.value : '';
            const term_val = termInput ? termInput.value.trim() : '';
            if (!tax_val) { showModalMessage(pyroSaWpApiSettings.i18n?.taxonomyRequired || 'Taxonomy is required for has_term.', true); return; }
            // has_term expects term first, then taxonomy
            param = [term_val || '', tax_val];
        } else if (selectedRuleType === 'template') {
            base_cb_name = 'is_page_template';
            param = valSelector && valSelector.value ? valSelector.value : '';
            if (!param) { showModalMessage(pyroSaWpApiSettings.i18n?.templateRequired || 'Please select a template file.', true); return; }
        }

        let final_cb_key = base_cb_name;
        if (negSelector && negSelector.value === 'not') {
            final_cb_key = '__neg:' + base_cb_name;
        }

        _modalState.currentRules[final_cb_key] = param;
        renderCurrentRules();
        typeSel.value = ''; // Reset main dropdown
        extraDiv.innerHTML = ''; // Clear parameter fields
    };

    listBox.addEventListener('click', e => {
        if (e.target.classList.contains('del')) {
            clearModalMessages();
            delete _modalState.currentRules[e.target.dataset.k];
            renderCurrentRules();
        }
    });

    saveRulesButton.onclick = () => {
        clearModalMessages();
        _modalState.currentRules.__mode = modeSel.value;

        if (!_modalState.currentHandle || !_modalState.currentApiFullRootPath) {
            console.error("Rule Builder Save Error: Handle or API path is missing.");
            showModalMessage(pyroSaWpApiSettings.i18n?.errorMissingPath || "Error: Handle or API path is missing. Cannot save.", true);
            return;
        }

        saveRulesButton.disabled = true;
        saveRulesButton.innerHTML = (pyroSaWpApiSettings.i18n?.saving || 'Saving...') + ' <span class="spinner is-active"></span>';

        fetch(_modalState.currentApiFullRootPath + _modalState.currentHandle, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-WP-Nonce': restApiNonce, 'Content-Type': 'application/json' },
            body: JSON.stringify(_modalState.currentRules)
        })
        .then(response => {
            if (response.ok) {
                // showModalMessage(pyroSaWpApiSettings.i18n?.rulesSaved || 'Rules saved successfully!', false);
                // Wait a moment for user to see message then reload, or just reload.
                // setTimeout(() => location.reload(), 1000);
                location.reload();
            } else {
                return response.json().then(errData => { // Try to parse error from server
                    throw errData; // Re-throw to be caught by .catch
                });
            }
        })
        .catch(errorData => { // Catches network errors or re-thrown JSON error
            let errorDetail = pyroSaWpApiSettings.i18n?.errorSavingDefault || 'Could not save rules.';
            if (errorData && errorData.message) {
                errorDetail = errorData.message;
                if (errorData.data && errorData.data.status) {
                     errorDetail += ` (Status: ${errorData.data.status})`;
                }
            } else if (errorData instanceof Error) { // Network error
                errorDetail = errorData.message;
            }
            showModalMessage(errorDetail, true);
            console.error('Error saving rules:', errorData);
        })
        .finally(() => {
            saveRulesButton.disabled = false;
            saveRulesButton.innerHTML = pyroSaWpApiSettings.i18n?.saveConditions || 'Save Conditions';
        });
    };

    closeRulesButton.onclick = () => {
        modal.style.display = 'none';
        _modalState.currentRules = {}; // Reset state
        typeSel.value = '';
        extraDiv.innerHTML = '';
        clearModalMessages();
    };

    function openModalForHandle(handleToEdit, apiSubPath) {
        if (!modal) {
            console.error("Rule builder modal element (#pyro-sa-modal) not found.");
            return;
        }
        clearModalMessages();
        _modalState.currentHandle = handleToEdit;
        // apiSubPath is like 'pyro-sa/v1/rules/scripts/'
        _modalState.currentApiFullRootPath = restApiBaseUrl + (apiSubPath.endsWith('/') ? apiSubPath : apiSubPath + '/');

        currentHandleSpan.textContent = _modalState.currentHandle;
        typeSel.value = '';
        extraDiv.innerHTML = '';
        listBox.innerHTML = '';
        modeSel.value = 'any'; // Default mode

        saveRulesButton.disabled = true;
        saveRulesButton.innerHTML = (pyroSaWpApiSettings.i18n?.loading || 'Loading...') + ' <span class="spinner is-active"></span>';
        modal.style.display = 'flex'; // Show modal while loading

        fetch(_modalState.currentApiFullRootPath + _modalState.currentHandle, {
            credentials: 'same-origin',
            headers: { 'X-WP-Nonce': restApiNonce }
        })
        .then(response => {
            if (!response.ok) {
                if (response.status === 404) return {}; // No rules found, treat as empty
                throw new Error(`HTTP error ${response.status}`);
            }
            return response.json();
        })
        .then(fetchedRules => {
            let rulesObject = {};
            if (Array.isArray(fetchedRules) && fetchedRules.length === 0) {
                rulesObject = {};
            } else if (typeof fetchedRules === 'object' && fetchedRules !== null && !Array.isArray(fetchedRules)) {
                rulesObject = fetchedRules;
            } else if (fetchedRules === null) {
                 rulesObject = {};
            } else {
                console.warn("Rule Builder: Fetched rules in unexpected format, defaulting to empty. Received:", fetchedRules);
                rulesObject = {};
            }
            _modalState.currentRules = rulesObject;
            modeSel.value = _modalState.currentRules.__mode || 'any';
            renderCurrentRules();
        })
        .catch(error => {
            console.error(`Rule Builder: Error fetching rules for "${_modalState.currentHandle}":`, error.message);
            showModalMessage((pyroSaWpApiSettings.i18n?.errorLoadingRules || 'Could not load existing rules. You can add new ones.') + ` (${error.message})`, true);
            _modalState.currentRules = {}; // Reset on error
            renderCurrentRules(); // Show empty list
        })
        .finally(() => {
            saveRulesButton.disabled = false;
            saveRulesButton.innerHTML = pyroSaWpApiSettings.i18n?.saveConditions || 'Save Conditions';
        });
    }

    // Expose only the open method
    return {
        open: openModalForHandle
    };
})();