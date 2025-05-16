/**
 * Pyro Script Audit - Manual Script Add/Edit Modal (Vanilla JS)
 *
 * Handles interactions for the modal used to add new manual scripts
 * or edit existing ones.
 *
 * @since 3.1.0
 */
(function() {
    'use strict';

    // Wait for the DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {

        // Check if pyroSaWpApiSettings is defined
        if (typeof pyroSaWpApiSettings === 'undefined' || typeof pyroSaWpApiSettings.i18n === 'undefined') {
            console.error('pyroSaWpApiSettings or pyroSaWpApiSettings.i18n is not defined. Manual script modal may not function correctly.');
            pyroSaWpApiSettings = pyroSaWpApiSettings || {};
            pyroSaWpApiSettings.i18n = pyroSaWpApiSettings.i18n || { /* ... (same default strings as jQuery version) ... */
                addScriptTitle: 'Add New Script', editScriptTitle: 'Edit Script: ', saveScript: 'Save Script',
                savingScript: 'Saving...', scriptAddedSuccess: 'Script added successfully!',
                scriptUpdatedSuccess: 'Script updated successfully!', errorSavingScript: 'Error saving script.',
                errorLoadingData: 'Could not load script data for editing.',
                confirmRemove: 'Are you sure you want to remove the script "{handle}"? This cannot be undone.',
                scriptRemovedSuccess: 'Script removed successfully!', errorRemovingScript: 'Error removing script.',
                loading: 'Loading...', editAction: 'Edit', removeAction: 'Remove'
            };
        }

        // Modal and form elements
        const manualScriptModal   = document.getElementById('pyro-sa-manual-script-entry-modal');
        const form                = document.getElementById('pyro-sa-manual-script-form');
        const modalTitleEl        = document.getElementById('pyro-sa-manual-script-modal-title-h2');
        const modeField           = document.getElementById('pyro-sa-manual-script-modal-mode');
        const originalHandleField = document.getElementById('pyro-sa-manual-script-original-handle');
        const handleField         = document.getElementById('pyro-sa-manual-handle');
        const srcField            = document.getElementById('pyro-sa-manual-src');
        const verField            = document.getElementById('pyro-sa-manual-ver');
        const depsField           = document.getElementById('pyro-sa-manual-deps');
        const inFooterCheckbox    = document.getElementById('pyro-sa-manual-in-footer');
        const strategySelect      = document.getElementById('pyro-sa-manual-strategy');
        const saveButton          = document.getElementById('pyro-sa-save-manual-script-btn');
        const openModalButton     = document.getElementById('pyro-sa-open-add-script-modal-btn');
        const closeModalButton    = document.getElementById('pyro-sa-manual-script-modal-close-btn');
        const messageArea         = document.getElementById('pyro-sa-manual-modal-messages'); // For messages in modal
        const mainTable           = document.getElementById('pyro-sa-main-table'); // For event delegation

        // REST API settings
        const restApiBaseUrl        = pyroSaWpApiSettings.root;
        const restApiNonce          = pyroSaWpApiSettings.nonce;
        const manualScriptsEndpoint = restApiBaseUrl + (pyroSaWpApiSettings.namespace || 'pyro-sa/v1') + '/manual-scripts/';

        // Ensure all crucial elements exist before proceeding
        if (!manualScriptModal || !form || !openModalButton || !closeModalButton || !mainTable || !saveButton) {
            console.error('Pyro SA: One or more essential elements for the manual script modal are missing from the DOM.');
            return;
        }

        function showFormMessage(message, isError = false) {
            if (messageArea) {
                messageArea.textContent = message;
                messageArea.className = 'notice ' + (isError ? 'notice-error is-dismissible' : 'notice-success is-dismissible');
                messageArea.style.display = 'block';
                if (!isError) {
                    setTimeout(() => {
                        if (messageArea.textContent === message) {
                            messageArea.style.display = 'none';
                            messageArea.textContent = '';
                        }
                    }, 4000);
                }
            } else {
                alert(message);
            }
        }

        function clearFormMessages() {
            if (messageArea) {
                messageArea.style.display = 'none';
                messageArea.textContent = '';
            }
        }

        function openManualScriptModal(mode = 'add', scriptData = {}) {
            clearFormMessages();
            modeField.value = mode;
            form.reset(); // Resets form fields to default values

            if (mode === 'add') {
                modalTitleEl.textContent = pyroSaWpApiSettings.i18n.addScriptTitle;
                if (handleField) handleField.disabled = false;
                if (originalHandleField) originalHandleField.value = '';
                if (strategySelect) strategySelect.value = 'none'; // Default strategy
            } else { // 'edit' mode
                modalTitleEl.textContent = pyroSaWpApiSettings.i18n.editScriptTitle + (scriptData.handle || '');
                if (handleField) { handleField.value = scriptData.handle || ''; handleField.disabled = true; }
                if (originalHandleField) originalHandleField.value = scriptData.handle || '';
                if (srcField) srcField.value = scriptData.src || '';
                if (verField) verField.value = scriptData.ver || '';
                if (depsField) depsField.value = scriptData.deps || '';
                if (inFooterCheckbox) inFooterCheckbox.checked = scriptData.in_footer || false;
                if (strategySelect) strategySelect.value = scriptData.strategy || 'none';
            }
            manualScriptModal.style.display = 'flex';
            if(srcField) srcField.focus();
        }

        function closeManualScriptModal() {
            manualScriptModal.style.display = 'none';
            clearFormMessages();
        }

        openModalButton.addEventListener('click', function() {
            openManualScriptModal('add');
        });

        closeModalButton.addEventListener('click', closeManualScriptModal);

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            clearFormMessages();

            const formData = new FormData(form);
            let submissionData = {};
            for (let [key, value] of formData.entries()) {
                submissionData[key] = value;
            }
            submissionData.in_footer = inFooterCheckbox ? inFooterCheckbox.checked : false;

            // Handle is not part of form data for edit, get from originalHandleField
            if (modeField.value === 'edit' && originalHandleField) {
                submissionData.handle = originalHandleField.value;
            } else if (modeField.value === 'add' && handleField) {
                 submissionData.handle = handleField.value; // Ensure handle is included for add
            }


            let ajaxUrl = manualScriptsEndpoint;
            // For edit, the handle is part of the URL, not necessarily in submissionData if disabled
            if (modeField.value === 'edit' && originalHandleField && originalHandleField.value) {
                ajaxUrl += originalHandleField.value;
            }

            saveButton.disabled = true;
            saveButton.innerHTML = (pyroSaWpApiSettings.i18n.savingScript) + ' <span class="spinner is-active"></span>';

            fetch(ajaxUrl, {
                method: 'POST', // WordPress REST API often uses POST for create and update
                headers: {
                    'X-WP-Nonce': restApiNonce,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(submissionData)
            })
            .then(response => {
                if (response.ok) {
                    return response.json(); // Or just proceed if no useful data in response
                }
                // If not ok, try to parse error as JSON
                return response.json().then(errData => {
                    // Attach status to distinguish from network errors in catch
                    errData.status = response.status; 
                    throw errData; 
                });
            })
            .then(data => { // Success path
                const successMessage = modeField.value === 'add' ?
                                       pyroSaWpApiSettings.i18n.scriptAddedSuccess :
                                       pyroSaWpApiSettings.i18n.scriptUpdatedSuccess;
                alert(successMessage); // Alert is more reliable before reload
                location.reload();
            })
            .catch(errorData => { // Catches network errors or errors thrown from .then(response => ...)
                let errorMsg = pyroSaWpApiSettings.i18n.errorSavingScript;
                if (errorData && errorData.message) {
                    errorMsg += '\n' + errorData.message;
                    if (errorData.data && errorData.data.details) {
                        for (const field in errorData.data.details) {
                            errorMsg += `\n- ${field}: ${errorData.data.details[field].join(', ')}`;
                        }
                    }
                } else if (errorData instanceof Error) { // Generic JS Error (e.g. network)
                    errorMsg += '\n' + errorData.message;
                } else {
                    errorMsg += `\nStatus: ${errorData.status || 'Unknown'}`;
                }
                showFormMessage(errorMsg, true);
                console.error("Error saving manual script:", errorData);
            })
            .finally(() => {
                saveButton.disabled = false;
                saveButton.innerHTML = pyroSaWpApiSettings.i18n.saveScript;
            });
        });

        // Event delegation for "Edit" and "Remove" buttons on the main table
        mainTable.addEventListener('click', function(e) {
            const targetButton = e.target.closest('button');
            if (!targetButton) return;

            const handle = targetButton.dataset.handle;
            if (!handle) return;

            if (targetButton.classList.contains('pyro-sa-edit-manual-script-btn')) {
                targetButton.disabled = true;
                targetButton.innerHTML = (pyroSaWpApiSettings.i18n.loading) + ' <span class="spinner is-active"></span>';
                clearFormMessages();

                fetch(manualScriptsEndpoint + handle, {
                    method: 'GET',
                    headers: { 'X-WP-Nonce': restApiNonce }
                })
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error ${response.status}`);
                    return response.json();
                })
                .then(scriptData => {
                    openManualScriptModal('edit', { ...scriptData, handle: handle });
                })
                .catch(error => {
                    showFormMessage(pyroSaWpApiSettings.i18n.errorLoadingData + ` (${error.message})`, true);
                    console.error("Error loading manual script data:", error);
                })
                .finally(() => {
                    targetButton.disabled = false;
                    targetButton.innerHTML = pyroSaWpApiSettings.i18n.editAction;
                });

            } else if (targetButton.classList.contains('pyro-sa-remove-manual-script-btn')) {
                const confirmMessage = (pyroSaWpApiSettings.i18n.confirmRemove).replace('{handle}', handle);

                if (window.confirm(confirmMessage)) {
                    targetButton.disabled = true;
                    targetButton.textContent = pyroSaWpApiSettings.i18n.removing;

                    fetch(manualScriptsEndpoint + handle, {
                        method: 'DELETE',
                        headers: { 'X-WP-Nonce': restApiNonce }
                    })
                    .then(response => {
                        if (!response.ok) {
                           return response.json().then(errData => {throw errData;});
                        }
                        return response.json(); // Or just check response.ok if no body on success
                    })
                    .then(data => {
                        alert(pyroSaWpApiSettings.i18n.scriptRemovedSuccess);
                        location.reload();
                    })
                    .catch(errorData => {
                        let errorMsg = pyroSaWpApiSettings.i18n.errorRemovingScript;
                        if (errorData && errorData.message) errorMsg += '\n' + errorData.message;
                        alert(errorMsg);
                        console.error("Error removing manual script:", errorData);
                        targetButton.disabled = false;
                        targetButton.textContent = pyroSaWpApiSettings.i18n.removeAction;
                    });
                }
            }
        });

    }); // End DOMContentLoaded
})();