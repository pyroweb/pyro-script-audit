/**
 * Pyro Script Audit - Admin Page Main Interactions
 *
 * Handles master toggle, dependency warnings, table sorting, and nonce refresh.
 *
 * @since 3.1.0
 */
(function() {
    'use strict';

    // Check if pyroSaMainData is defined (localized from PHP)
    if (typeof pyroSaMainData === 'undefined') {
        console.error('pyroSaMainData is not defined. Nonce refresh and other main UI features might not work correctly.');
        // You might choose to return here if critical features depend on it,
        // or allow other parts of the script to run if they don't.
        // For nonce refresh, it's pretty critical.
    }

    const nonceKey = (typeof pyroSaMainData !== 'undefined' && pyroSaMainData.nonceKey) ? pyroSaMainData.nonceKey : 'pyro_sa_nonce'; // Fallback, but should come from PHP
    const nonceRefreshUrl = (typeof pyroSaMainData !== 'undefined' && pyroSaMainData.nonceRefreshUrl) ? pyroSaMainData.nonceRefreshUrl : '';
    const nonceRefreshIntervalMs = (typeof pyroSaMainData !== 'undefined' && pyroSaMainData.nonceRefreshInterval) ? parseInt(pyroSaMainData.nonceRefreshInterval, 10) : (10 * 60 * 1000); // Default 10 mins

    const unifiedTable = document.getElementById('pyro-sa-main-table');
    const masterToggleCheckbox = document.getElementById('pyro-sa-master-toggle');
    const masterToggleCheckboxFooter = document.getElementById('pyro-sa-master-toggle-footer'); // For the tfoot checkbox

    if (unifiedTable) {
        if (masterToggleCheckbox) {
            masterToggleCheckbox.addEventListener('change', function(e) {
                const isChecked = e.target.checked;
                unifiedTable.querySelectorAll('tbody input[type="checkbox"][name="handles[]"]')
                    .forEach(ch => ch.checked = isChecked);
                if(masterToggleCheckboxFooter) masterToggleCheckboxFooter.checked = isChecked;
            });
        }
        if (masterToggleCheckboxFooter) {
            masterToggleCheckboxFooter.addEventListener('change', function(e) {
                const isChecked = e.target.checked;
                unifiedTable.querySelectorAll('tbody input[type="checkbox"][name="handles[]"]')
                    .forEach(ch => ch.checked = isChecked);
                if(masterToggleCheckbox) masterToggleCheckbox.checked = isChecked;
            });
        }


        // Dependency confirm modal (Event delegation on the table)
        unifiedTable.addEventListener('click', function(e) {
            if (e.target.classList.contains('pyro-sa-warn')) {
                const button = e.target;
                const form = button.closest('form');
                if (!form) return;

                const deps = button.dataset.deps || '';
                const handleInput = form.querySelector('input[name="handle"]');
                const handle = handleInput ? handleInput.value : 'unknown';
                const msg = deps ?
                    `"${handle}" is required by the following script(s):\n\n${deps}\n\nDequeue anyway?` :
                    `Are you sure you want to dequeue the script "${handle}"?`;

                if (!window.confirm(msg)) {
                    e.preventDefault(); // Prevent form submission
                    e.stopImmediatePropagation();
                }
            }
        }, true); // Use capture phase

        // Column sorting
        const tableHeaders = unifiedTable.querySelectorAll('thead th.sortable');
        if (unifiedTable.tBodies && unifiedTable.tBodies.length > 0 && tableHeaders.length > 0) {
            const tbody = unifiedTable.tBodies[0];

            const getCellValue = (tr, cellIndex) => {
                if (tr.cells && tr.cells[cellIndex]) {
                    return tr.cells[cellIndex].dataset.sortValue || tr.cells[cellIndex].innerText || tr.cells[cellIndex].textContent || '';
                }
                return '';
            };

            const createComparer = (index, ascending, isNumeric) => (rowA, rowB) => {
                let valA = getCellValue(ascending ? rowA : rowB, index);
                let valB = getCellValue(ascending ? rowB : rowA, index);

                if (isNumeric) {
                    valA = parseFloat(valA) || 0;
                    valB = parseFloat(valB) || 0;
                    return valA - valB;
                } else {
                    // Locale-compare for strings, case-insensitive
                    return valA.toString().localeCompare(valB.toString(), undefined, { sensitivity: 'base' });
                }
            };

            tableHeaders.forEach(th => {
                th.addEventListener('click', function(e) {
                    // Prevent sorting if clicking on something other than the direct header or its link content (e.g. a checkbox in header)
                    if (e.target.closest('input[type="checkbox"]')) return;
                    if (th.classList.contains('check-column')) return; // Don't sort the checkbox column

                    const cellIndex = th.cellIndex;
                    const currentSort = th.getAttribute('aria-sort');
                    let newSortDirection;

                    if (currentSort === 'ascending') {
                        newSortDirection = 'descending';
                    } else {
                        newSortDirection = 'ascending'; // Default or if was descending
                    }
                    
                    const isAscending = (newSortDirection === 'ascending');
                    const isNumeric = th.dataset.numeric === '1';

                    // Remove sorting indicators from all other headers
                    tableHeaders.forEach(header => {
                        if (header !== th) {
                            header.removeAttribute('aria-sort');
                            header.classList.remove('sorted', 'asc', 'desc');
                        }
                    });

                    // Set current header's sorting state
                    th.setAttribute('aria-sort', newSortDirection);
                    th.classList.add('sorted');
                    th.classList.toggle('asc', isAscending);
                    th.classList.toggle('desc', !isAscending);
                    
                    // Sort and re-append rows
                    Array.from(tbody.rows)
                        .sort(createComparer(cellIndex, isAscending, isNumeric))
                        .forEach(row => tbody.appendChild(row));
                });
            });
        } else {
            if (tableHeaders.length === 0) console.warn("Pyro SA: No sortable table headers found for #pyro-sa-main-table.");
            if (!unifiedTable.tBodies || unifiedTable.tBodies.length === 0) console.warn("Pyro SA: Table #pyro-sa-main-table has no tbody for sorting.");
        }
    } else {
        console.warn("Pyro SA: Main table #pyro-sa-main-table not found. UI features like sorting and master toggle will not be initialized.");
    }


    // --- Nonce Refresh Mechanism ---
    if (nonceRefreshUrl) { // Only run if URL is provided
        let lastNonceRefreshTime = Date.now();

        function refreshAdminNonce(callback) {
            fetch(nonceRefreshUrl, { method: 'POST', credentials: 'same-origin' })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok for nonce refresh.');
                    }
                    return response.text();
                })
                .then(newNonceValue => {
                    if (newNonceValue && newNonceValue !== 'error_cap') {
                        document.querySelectorAll('input[name="' + nonceKey + '"]').forEach(inputField => {
                            inputField.value = newNonceValue;
                        });
                        lastNonceRefreshTime = Date.now();
                    } else if (newNonceValue === 'error_cap') {
                         console.warn('Pyro SA: Nonce refresh failed due to capability check on server.');
                    } else {
                        console.warn('Pyro SA: Nonce refresh returned empty or invalid value.');
                    }
                    if (typeof callback === 'function') callback();
                })
                .catch(error => {
                    console.error('Pyro SA: Nonce refresh fetch error:', error);
                    if (typeof callback === 'function') callback(); // Ensure callback runs even on error
                });
        }

        // 1. Background refresh
        setInterval(() => refreshAdminNonce(), nonceRefreshIntervalMs);

        // 2. Refresh on tab visibility change
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                refreshAdminNonce();
            }
        });

        // 3. Intercept form submissions to ensure fresh nonce
        document.addEventListener('submit', function(e) {
            const form = e.target;
            // Check if the form contains our specific nonce key
            const nonceInput = form.querySelector('input[name="' + nonceKey + '"]');

            if (nonceInput && (Date.now() - lastNonceRefreshTime > nonceRefreshIntervalMs / 2) ) { // Refresh if nonce is older than half the interval
                e.preventDefault(); // Stop submission
                refreshAdminNonce(() => {
                    form.submit(); // Resubmit form after nonce is refreshed
                });
            }
        }, true); // Use capture phase
    } else {
        console.warn("Pyro SA: Nonce refresh URL not provided. Nonce refresh mechanism disabled.");
    }

})();