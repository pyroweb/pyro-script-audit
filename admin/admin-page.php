<?php
/**
 * Pyro Script Audit - Admin Page UI
 *
 * This file handles the registration of the admin page and the rendering
 * of its main user interface.
 *
 * @package Pyro_Script_Audit
 * @since   3.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the admin menu page for the Pyro Script Audit plugin.
 *
 * @since 3.0.0 (Originally part of an anonymous function for admin_menu)
 */
function pyro_sa_register_admin_page() {
    add_management_page(
        __( 'Pyro Script Audit Log', 'pyro-script-audit' ), // Page Title
        __( 'Pyro Script Audit', 'pyro-script-audit' ),          // Menu Title
        'manage_options',                                   // Capability
        'pyro-script-audit-log',                            // Menu Slug (changed from ps-script-log for consistency)
        'pyro_sa_render_admin_page'                         // Callback function to render the page
    );
}
add_action( 'admin_menu', 'pyro_sa_register_admin_page' );

/**
 * Renders the main admin page for the Pyro Script Audit plugin.
 *
 * This function generates all the HTML for the different tabs (Found, Dequeued, Manual)
 * and their respective tables and forms.
 *
 * @since 3.0.0 (Originally ps_sa_page)
 */
function pyro_sa_render_admin_page() {
    // Ensure user has capability
    if ( ! pyro_sa_can_manage() ) { // Function from utils.php
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'pyro-script-audit' ) );
    }

    // --- Get current tab and load data ---
    $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'found';
    if ( ! in_array( $current_tab, [ 'found', 'dequeued', 'manual' ], true ) ) {
        $current_tab = 'found';
    }

    $filter_pos = isset( $_GET['pos'] ) ? sanitize_key( $_GET['pos'] ) : ''; // For 'found' tab filtering by position
    $nonce_value = wp_create_nonce( 'pyro_sa_actions_nonce' ); // More specific nonce name

    $table_data = [];
    if ( $current_tab === 'found' ) {
        $table_data = get_option( PYRO_SA_FOUND_SCRIPTS_OPT, [] );
    } elseif ( $current_tab === 'dequeued' ) {
        $table_data = get_option( PYRO_SA_DEQUEUED_SCRIPTS_OPT, [] );
    } elseif ( $current_tab === 'manual' ) {
        $table_data = get_option( PYRO_SA_MANUAL_SCRIPTS_OPT, [] );
    }

    // --- Get column definitions for the current tab (functions from utils.php or admin-utils.php) ---
    $all_columns_for_tab    = pyro_sa_get_all_columns_for_tab( $current_tab );
    $visible_columns_for_tab = pyro_sa_visible_cols_for_tab( $current_tab );

    // Dependents logic (mostly for found/dequeued)
    $dependents = [];
    if ( $current_tab === 'found' || $current_tab === 'dequeued' ) {
        $all_logged_scripts = get_option( PYRO_SA_FOUND_SCRIPTS_OPT, [] ) + get_option( PYRO_SA_DEQUEUED_SCRIPTS_OPT, [] );
        foreach ( $all_logged_scripts as $handle => $script_item ) {
            if ( ! empty( $script_item['deps'] ) && is_array( $script_item['deps'] ) ) {
                foreach ( $script_item['deps'] as $dep_handle ) {
                    $dependents[ $dep_handle ][] = $handle;
                }
            }
        }
    }
    ?>
    <div class="wrap pyro-sa-admin-wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e( 'Pyro Script Audit', 'pyro-script-audit' ); ?></h1>
        
        <div id="pyro-sa-admin-messages"></div> <!-- For potential JS success/error messages -->

        <style>
        <?php
        // Generate CSS for hiding columns based on screen options
        // Get all *possible* column keys across all tabs to ensure CSS rule exists even if not for current tab
        $all_possible_column_keys = array_unique( array_merge(
            array_keys( pyro_sa_get_all_columns_for_tab( 'found' ) ),
            array_keys( pyro_sa_get_all_columns_for_tab( 'dequeued' ) ),
            array_keys( pyro_sa_get_all_columns_for_tab( 'manual' ) )
        ) );
        foreach ( $all_possible_column_keys as $col_key ) {
            if ( ! in_array( $col_key, $visible_columns_for_tab, true ) ) {
                echo '.pyro-sa-main-table .column-' . esc_attr( $col_key ) . ' { display:none; }';
            }
        }
        ?>
        /* Basic spinner styling */
        .pyro-sa-admin-wrap .spinner.is-active { 
            background-image: url(<?php echo esc_url( admin_url( 'images/spinner-2x.gif' ) ); ?>); 
            background-repeat: no-repeat; background-position: center; 
            background-size: 16px 16px; /* Adjusted size */
            width: 16px; height: 16px; 
            display: inline-block; vertical-align: middle;
            margin-left: 5px;
        }
        .pyro-sa-admin-wrap .column-src span { word-break: break-all; }
        </style>

        <h2 class="nav-tab-wrapper" style="margin-bottom: 15px;">
            <a class="nav-tab <?php echo ( $current_tab === 'found' ) ? 'nav-tab-active' : ''; ?>" 
               href="?page=pyro-script-audit-log&tab=found"><?php esc_html_e( 'Found Scripts', 'pyro-script-audit' ); ?></a>
            <a class="nav-tab <?php echo ( $current_tab === 'dequeued' ) ? 'nav-tab-active' : ''; ?>" 
               href="?page=pyro-script-audit-log&tab=dequeued"><?php esc_html_e( 'Dequeued Scripts', 'pyro-script-audit' ); ?></a>
            <a class="nav-tab <?php echo ( $current_tab === 'manual' ) ? 'nav-tab-active' : ''; ?>" 
               href="?page=pyro-script-audit-log&tab=manual"><?php esc_html_e( 'Manually Added Scripts', 'pyro-script-audit' ); ?></a>
        </h2>

        <?php if ( $current_tab === 'found' ) : ?>
            <form class="pyro-sa-filter-form" method="get" style="margin-bottom:15px;">
                <input type="hidden" name="page" value="pyro-script-audit-log">
                <input type="hidden" name="tab" value="found">
                <label for="pyro-sa-pos-filter" class="screen-reader-text"><?php esc_html_e( 'Filter by position', 'pyro-script-audit' ); ?></label>
                <select name="pos" id="pyro-sa-pos-filter" onchange="this.form.submit()">
                    <option value="" <?php selected( '', $filter_pos ); ?>><?php esc_html_e( 'All positions', 'pyro-script-audit' ); ?></option>
                    <option value="header"<?php selected( 'header', $filter_pos ); ?>><?php esc_html_e( 'Header only', 'pyro-script-audit' ); ?></option>
                    <option value="footer"<?php selected( 'footer', $filter_pos ); ?>><?php esc_html_e( 'Footer only', 'pyro-script-audit' ); ?></option>
                </select>
            </form>
        <?php elseif ( $current_tab === 'manual' ) : ?>
            <div style="margin-bottom: 15px;">
                <button type="button" id="pyro-sa-open-add-script-modal-btn" class="button button-primary">
                    <?php esc_html_e( 'Add New Script', 'pyro-script-audit' ); ?>
                </button>
            </div>
        <?php endif; ?>

        <!-- Unified Bulk Actions Form -->
        <form id="pyro-sa-bulk-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:10px;display:inline-block;">
            <label for="pyro-sa-bulk-action-select" class="screen-reader-text"><?php esc_html_e( 'Bulk action', 'pyro-script-audit' ); ?></label>
            <select name="bulk_action" id="pyro-sa-bulk-action-select" required>
                <option value=""><?php esc_html_e( 'Bulk actions…', 'pyro-script-audit' ); ?></option>
                <?php if ( $current_tab === 'found' ) : ?>
                    <option value="dequeue"><?php esc_html_e( 'Dequeue selected', 'pyro-script-audit' ); ?></option>
                    <option value="dequeue_front"><?php esc_html_e( 'Dequeue selected (front-end only)', 'pyro-script-audit' ); ?></option>
                    <option value="delete_log"><?php esc_html_e( 'Delete from log', 'pyro-script-audit' ); ?></option>
                <?php elseif ( $current_tab === 'dequeued' ) : ?>
                    <option value="restore"><?php esc_html_e( 'Restore selected', 'pyro-script-audit' ); ?></option>
                <?php elseif ( $current_tab === 'manual' ) : ?>
                    <option value="remove_manual"><?php esc_html_e( 'Remove selected manual scripts', 'pyro-script-audit' ); ?></option>
                <?php endif; ?>
            </select>
            <button type="submit" class="button action"><?php esc_html_e( 'Apply', 'pyro-script-audit' ); ?></button>
            <input type="hidden" name="action" value="pyro_sa_handle_bulk_unified">
            <input type="hidden" name="current_tab" value="<?php echo esc_attr( $current_tab ); ?>">
            <input type="hidden" name="<?php echo esc_attr( PYRO_SA_NONCE_KEY ); ?>" value="<?php echo esc_attr( $nonce_value ); ?>">
        </form>

        <button type="submit" class="button" form="pyro-sa-export-form"><?php esc_html_e( 'Export CSV', 'pyro-script-audit' ); ?></button>
        <?php if ( 'found' === $current_tab ) : ?>
            <button type="submit" class="button" form="pyro-sa-clear-form"><?php esc_html_e( 'Clear Found Log', 'pyro-script-audit' ); ?></button>
        <?php endif; ?>

        <!-- THE UNIFIED TABLE -->
        <table class="widefat fixed striped pyro-sa-main-table" id="pyro-sa-main-table">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column">
                        <label for="pyro-sa-master-toggle" class="screen-reader-text"><?php esc_html_e( 'Select all items', 'pyro-script-audit' ); ?></label>
                        <input type="checkbox" id="pyro-sa-master-toggle">
                    </td>
                    <?php foreach ( $all_columns_for_tab as $col_key => $col_config ) :
                        $label_text = $col_config[0];
                        $is_numeric = $col_config[1] ?? false; // Default to not numeric if not set
                        ?>
                        <th scope="col" id="column-<?php echo esc_attr( $col_key ); ?>"
                            class="manage-column column-<?php echo esc_attr( $col_key ); ?> sortable <?php echo $is_numeric ? 'num' : 'alpha'; // Class for WP default sort indicators ?>">
                            <a href="#"> <!-- Link for sorting, JS will handle -->
                                <span><?php echo esc_html( $label_text ); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                    <?php endforeach; ?>
                    <th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Actions', 'pyro-script-audit' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $table_data ) ) : ?>
                    <tr><td colspan="<?php echo count( $all_columns_for_tab ) + 2; // +2 for checkbox and actions ?>">
                        <?php
                        if ( $current_tab === 'found' ) { esc_html_e( 'No scripts found in the log yet. Visit your site\'s front-end pages to populate.', 'pyro-script-audit' ); }
                        elseif ( $current_tab === 'dequeued' ) { esc_html_e( 'No scripts have been dequeued yet.', 'pyro-script-audit' ); }
                        elseif ( $current_tab === 'manual' ) { esc_html_e( 'No scripts have been manually added yet. Click "Add New Script" to begin.', 'pyro-script-audit' ); }
                        ?>
                    </td></tr>
                <?php else : ?>
                    <?php foreach ( $table_data as $handle => $item_data ) : ?>
                        <?php
                        // Filter for 'found' tab by position
                        if ( $current_tab === 'found' && $filter_pos && ( $filter_pos === 'header' ) === ( $item_data['in_footer'] ?? false ) ) {
                            continue;
                        }
                        $is_dependency_warn = ( $current_tab === 'found' || $current_tab === 'dequeued' ) && ! empty( $dependents[ $handle ] );
                        ?>
                        <tr data-handle="<?php echo esc_attr( $handle ); ?>">
                            <th scope="row" class="check-column">
                                <label for="cb-select-<?php echo esc_attr( $handle ); ?>" class="screen-reader-text"><?php printf( esc_html__( 'Select %s', 'pyro-script-audit' ), esc_html($handle) ); ?></label>
                                <input type="checkbox" form="pyro-sa-bulk-form" name="handles[]" 
                                       id="cb-select-<?php echo esc_attr( $handle ); ?>" value="<?php echo esc_attr( $handle ); ?>">
                            </th>
                            
                            <?php foreach ( $all_columns_for_tab as $col_key => $col_config ) : ?>
                                <td class="column-<?php echo esc_attr( $col_key ); ?>"
                                    <?php
                                    // Add data-sort-value for accurate sorting
                                    $sort_value = '';
                                    if ($col_key === 'handle') $sort_value = $handle;
                                    elseif ($col_key === 'size' && isset($item_data['size'])) $sort_value = intval($item_data['size']);
                                    elseif ($col_key === 'mtime' && isset($item_data['mtime'])) $sort_value = intval($item_data['mtime']);
                                    elseif ($col_key === 'date' && isset($item_data[$current_tab === 'found' ? 'found' : 'dequeued'])) $sort_value = intval($item_data[$current_tab === 'found' ? 'found' : 'dequeued']);
                                    elseif ($col_key === 'added_on' && isset($item_data['added_on'])) $sort_value = intval($item_data['added_on']);
                                    // For other text columns, innerText is usually fine, but explicit sort_value is better
                                    elseif (isset($item_data[$col_key]) && is_scalar($item_data[$col_key])) $sort_value = $item_data[$col_key];

                                    if ($sort_value !== '') echo ' data-sort-value="' . esc_attr($sort_value) . '"';
                                    ?>>
                                    <?php
                                    // Cell content rendering logic from your previous version
                                    // Ensure this covers all column keys defined in pyro_sa_get_all_columns_for_tab
                                    switch ($col_key) {
                                        case 'handle':    echo '<code>' . esc_html($handle) . '</code>'; break;
                                        case 'src':       echo '<span>' . esc_html($item_data['src'] ?? 'N/A') . '</span>'; break;
                                        case 'ver':       echo esc_html($item_data['ver'] ?? '—'); break;
                                        case 'footer':    echo ($item_data['in_footer'] ?? false) ? esc_html__('Yes', 'pyro-script-audit') : esc_html__('No', 'pyro-script-audit'); break;
                                        case 'strategy':
                                            $strategy_val = strtolower($item_data['strategy'] ?? '');
                                            echo ($strategy_val === '' || $strategy_val === 'none') ? '—' : esc_html(ucfirst($strategy_val));
                                            break;
                                        case 'deps':      
                                            $deps_array = $item_data['deps'] ?? [];
                                            echo esc_html(is_array($deps_array) && !empty($deps_array) ? implode(', ', $deps_array) : '—'); 
                                            break;
                                        case 'size':      echo isset($item_data['size']) ? size_format($item_data['size'], 2) : '—'; break;
                                        case 'mtime':     echo isset($item_data['mtime']) ? date_i18n(get_option('date_format'), $item_data['mtime']) : '—'; break;
                                        case 'date':      echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $item_data[$current_tab === 'found' ? 'found' : 'dequeued'] ?? 0); break;
                                        case 'added_on':  echo isset($item_data['added_on']) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $item_data['added_on']) : '—'; break;
                                        case 'rules':
                                            // (Your existing detailed rules display logic from previous response)
                                            if (!empty($item_data['rules']) && is_array($item_data['rules'])) {
                                                $actual_rules = array_filter($item_data['rules'], function($k){ return $k !== '__mode'; }, ARRAY_FILTER_USE_KEY);
                                                if (empty($actual_rules)) { echo ($current_tab === 'manual' ? esc_html__('Always Load', 'pyro-script-audit') : esc_html__('Default', 'pyro-script-audit')); } 
                                                else {
                                                    $rule_parts = [];
                                                    foreach ($actual_rules as $cbk => $param_v) {
                                                        $is_n = strpos($cbk, '__neg:') === 0;
                                                        $cb_n = $is_n ? substr($cbk, 6) : $cbk;
                                                        $pfx  = $is_n ? 'NOT ' : '';
                                                        $p_s  = '';
                                                        if (is_array($param_v)) { 
                                                            $p_s_parts = [];
                                                            foreach ($param_v as $pv_item) { $p_s_parts[] = is_string($pv_item) ? '"'.esc_html($pv_item).'"' : esc_html($pv_item); }
                                                            $p_s = implode(', ', $p_s_parts);
                                                        }
                                                        elseif ($param_v === true || $param_v === null) { $p_s = ''; }
                                                        else { $p_s = is_string($param_v) ? '"'.esc_html((string)$param_v).'"' : esc_html((string)$param_v); }
                                                        $rule_parts[] = $pfx . esc_html($cb_n) . ($p_s !== '' ? "($p_s)" : "()");
                                                    }
                                                    echo implode(',<br>', $rule_parts);
                                                    if (isset($item_data['rules']['__mode']) && count($actual_rules) > 1) {
                                                        echo '<br><small><em>(' . esc_html__('Match:', 'pyro-script-audit') . ' ' . strtoupper(esc_html($item_data['rules']['__mode'])) . ')</em></small>';
                                                    }
                                                }
                                            } else { echo ($current_tab === 'manual' ? esc_html__('Always Load', 'pyro-script-audit') : esc_html__('Default', 'pyro-script-audit')); }
                                            break;
                                        default:          echo '—'; break;
                                    }
                                    ?>
                                </td>
                            <?php endforeach; ?>

                            <td class="column-actions"> <?php // ACTION BUTTONS ?>
                                <?php if ($current_tab === 'found'): ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" style="display:inline-block; margin-right: 5px;">
                                        <?php wp_nonce_field('pyro_sa_actions_nonce', PYRO_SA_NONCE_KEY);?>
                                        <input type="hidden" name="action" value="pyro_sa_dequeue_script">
                                        <input type="hidden" name="handle" value="<?php echo esc_attr($handle);?>">
                                        <?php $depsDep=esc_attr(implode(', ',$dependents[$handle]??[])); $btn_class=$is_dependency_warn?'button-secondary pyro-sa-warn':'button'; 
                                        printf('<button type="submit" class="%s" data-deps="%s">%s</button>',esc_attr($btn_class),$depsDep, esc_html__('Dequeue', 'pyro-script-audit'));?>
                                    </form>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" style="display:inline-block;">
                                        <?php wp_nonce_field('pyro_sa_actions_nonce', PYRO_SA_NONCE_KEY);?>
                                        <input type="hidden" name="action" value="pyro_sa_delete_log_entry">
                                        <input type="hidden" name="handle" value="<?php echo esc_attr($handle);?>">
                                        <?php submit_button(__('Delete', 'pyro-script-audit'),'delete small','submit',false); // Using WP's delete class for styling ?>
                                    </form>
                                <?php elseif ($current_tab === 'dequeued'): ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" style="display:inline-block; margin-right: 5px;">
                                        <?php wp_nonce_field('pyro_sa_actions_nonce', PYRO_SA_NONCE_KEY);?>
                                        <input type="hidden" name="action" value="pyro_sa_restore_script">
                                        <input type="hidden" name="handle" value="<?php echo esc_attr($handle);?>">
                                        <?php submit_button(__('Restore', 'pyro-script-audit'),'secondary small','submit',false);?>
                                    </form>
                                    <button class="button button-small pyro-sa-conditions-btn" data-handle="<?php echo esc_attr($handle); ?>" data-api-path="pyro-sa/v1/rules/"><?php esc_html_e('Conditions', 'pyro-script-audit'); ?></button>
                                <?php elseif ($current_tab === 'manual'): ?>
                                    <button class="button button-small pyro-sa-edit-manual-script-btn" data-handle="<?php echo esc_attr($handle); ?>" style="margin-right:5px;"><?php esc_html_e('Edit', 'pyro-script-audit'); ?></button>
                                    <button class="button button-small pyro-sa-conditions-btn" data-handle="<?php echo esc_attr($handle); ?>" data-api-path="pyro-sa/v1/manual-rules/" style="margin-right:5px;"><?php esc_html_e('Conditions', 'pyro-script-audit'); ?></button>
                                    <button class="button button-small pyro-sa-remove-manual-script-btn" data-handle="<?php echo esc_attr($handle); ?>"><?php esc_html_e('Remove', 'pyro-script-audit'); ?></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr> <!-- Replicate headers in tfoot for accessibility and long tables -->
                    <td class="manage-column column-cb check-column">
                        <label for="pyro-sa-master-toggle-footer" class="screen-reader-text"><?php esc_html_e( 'Select all items', 'pyro-script-audit' ); ?></label>
                        <input type="checkbox" id="pyro-sa-master-toggle-footer">
                    </td>
                    <?php foreach ( $all_columns_for_tab as $col_key => $col_config ) : ?>
                        <th scope="col" class="manage-column column-<?php echo esc_attr( $col_key ); ?>"><?php echo esc_html( $col_config[0] ); ?></th>
                    <?php endforeach; ?>
                    <th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Actions', 'pyro-script-audit' ); ?></th>
                </tr>
            </tfoot>
        </table>

        <!-- Hidden forms for actions like export/clear -->
        <form id="pyro-sa-export-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="pyro_sa_export_csv">
            <input type="hidden" name="export_tab" value="<?php echo esc_attr( $current_tab ); ?>">
            <input type="hidden" name="export_pos" value="<?php echo esc_attr( $filter_pos ); ?>">
            <input type="hidden" name="<?php echo esc_attr( PYRO_SA_NONCE_KEY ); ?>" value="<?php echo esc_attr( $nonce_value ); ?>">
        </form>
        <form id="pyro-sa-clear-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="pyro_sa_clear_found_log">
            <input type="hidden" name="<?php echo esc_attr( PYRO_SA_NONCE_KEY ); ?>" value="<?php echo esc_attr( $nonce_value ); ?>">
        </form>

        <!-- Modals -->
        <?php pyro_sa_render_rule_builder_modal(); ?>
        <?php pyro_sa_render_manual_script_modal(); ?>

    </div><!-- .wrap -->
    <?php
} // End pyro_sa_render_admin_page()

/**
 * Renders the HTML for the Rule Builder Modal.
 * Can be called from pyro_sa_render_admin_page().
 * @since 3.1.0
 */
function pyro_sa_render_rule_builder_modal() {
    // All the HTML for your #pyro-sa-modal goes here
    // This helps keep pyro_sa_render_admin_page cleaner
    ?>
    <div id="pyro-sa-modal" style="display:none" aria-labelledby="pyro-sa-modal-title" role="dialog" aria-modal="true">
      <div class="box">
        <h3 id="pyro-sa-modal-title"><?php esc_html_e('Edit context rules for', 'pyro-script-audit'); ?> <span id="pyro-sa-current" style="font-weight:bold;"></span></h3>        
        
        <div id="pyro-sa-rule-modal-messages" class="notice" style="display:none; margin-bottom:10px; padding: 10px;"></div>

        <p><label for="pyro-sa-type"><?php esc_html_e('Rule type:', 'pyro-script-audit'); ?></label>
          <select id="pyro-sa-type">
            <option value=""><?php esc_html_e('— choose —', 'pyro-script-audit'); ?></option>
            <optgroup label="<?php esc_attr_e('Simple', 'pyro-script-audit'); ?>">
              <option value="is_home()"><?php esc_html_e('is_home()', 'pyro-script-audit'); ?></option>
              <option value="is_front_page()"><?php esc_html_e('is_front_page()', 'pyro-script-audit'); ?></option>
              <option value="comments_open()"><?php esc_html_e('comments_open()', 'pyro-script-audit'); ?></option>
              <option value="is_user_logged_in()"><?php esc_html_e('is_user_logged_in()', 'pyro-script-audit'); ?></option>
              <option value="is_search()"><?php esc_html_e('is_search()', 'pyro-script-audit'); ?></option>
              <option value="is_404()"><?php esc_html_e('is_404()', 'pyro-script-audit'); ?></option>
              <option value="is_privacy_policy()"><?php esc_html_e('is_privacy_policy()', 'pyro-script-audit'); ?></option>
              <option value="is_paged()"><?php esc_html_e('is_paged()', 'pyro-script-audit'); ?></option>
            </optgroup>
            <optgroup label="<?php esc_attr_e('Post type', 'pyro-script-audit'); ?>"><option value="posttype"><?php esc_html_e('is_singular( post_type )', 'pyro-script-audit'); ?></option></optgroup>
            <optgroup label="<?php esc_attr_e('Taxonomy / Archive', 'pyro-script-audit'); ?>">
              <option value="is_archive()"><?php esc_html_e('Archive (all)', 'pyro-script-audit'); ?></option>
              <option value="tax"><?php esc_html_e('is_tax( taxonomy [,term] )', 'pyro-script-audit'); ?></option>
            </optgroup>
            <optgroup label="<?php esc_attr_e('Post has term', 'pyro-script-audit'); ?>"><option value="has_term"><?php esc_html_e('has_term( term, taxonomy )', 'pyro-script-audit'); ?></option></optgroup>
            <optgroup label="<?php esc_attr_e('Page template', 'pyro-script-audit'); ?>"><option value="template"><?php esc_html_e('is_page_template( file )', 'pyro-script-audit'); ?></option></optgroup>
            <?php if ( class_exists( 'WooCommerce' ) ) : ?>
            <optgroup label="<?php esc_attr_e('WooCommerce', 'pyro-script-audit'); ?>">
              <option value="is_woocommerce()"><?php esc_html_e('is_woocommerce()', 'pyro-script-audit'); ?></option>
              <option value="is_shop()"><?php esc_html_e('is_shop()', 'pyro-script-audit'); ?></option>
              <option value="is_product_category()"><?php esc_html_e('is_product_category()', 'pyro-script-audit'); ?></option>
              <option value="is_product_tag()"><?php esc_html_e('is_product_tag()', 'pyro-script-audit'); ?></option>
              <option value="is_product()"><?php esc_html_e('is_product()', 'pyro-script-audit'); ?></option>
              <option value="is_cart()"><?php esc_html_e('is_cart()', 'pyro-script-audit'); ?></option>
              <option value="is_checkout()"><?php esc_html_e('is_checkout()', 'pyro-script-audit'); ?></option>
              <option value="is_account_page()"><?php esc_html_e('is_account_page()', 'pyro-script-audit'); ?></option>
              <option value="is_wc_endpoint_url()"><?php esc_html_e('is_wc_endpoint_url()', 'pyro-script-audit'); ?></option>
            </optgroup>
            <?php endif; ?>
          </select>
        </p>
        <div id="pyro-sa-extra" style="margin-top:8px; margin-bottom: 10px;"></div>
        <button type="button" class="button" id="pyro-sa-add"><?php esc_html_e('Add rule', 'pyro-script-audit'); ?></button>

        <p>
            <label for="pyro-sa-mode" style="display:block;margin:15px 0 5px;"><?php esc_html_e('Matching mode:', 'pyro-script-audit'); ?></label>
            <select id="pyro-sa-mode">
                <option value="any"><?php esc_html_e('ANY rule may match (OR)', 'pyro-script-audit'); ?></option>
                <option value="all"><?php esc_html_e('ALL rules must match (AND)', 'pyro-script-audit'); ?></option>
            </select>
        </p>

        <p style="margin-bottom: 5px; margin-top:15px; font-weight:bold;"><?php esc_html_e('Current Rules:', 'pyro-script-audit'); ?></p>
        <div id="pyro-sa-list" class="rule-list-container"></div>

        <p class="submit" style="margin-top:20px; display:flex; justify-content:space-between;">
          <button type="button" class="button button-primary" id="pyro-sa-save"><?php esc_html_e('Save Conditions', 'pyro-script-audit'); ?></button>
          <button type="button" class="button" id="pyro-sa-close"><?php esc_html_e('Cancel', 'pyro-script-audit'); ?></button>
        </p>
      </div>
    </div>
    <?php
}

/**
 * Renders the HTML for the Add/Edit Manual Script Modal.
 * @since 3.1.0
 */
function pyro_sa_render_manual_script_modal() {
    // All the HTML for your #pyro-sa-manual-script-entry-modal goes here
    ?>
    <div id="pyro-sa-manual-script-entry-modal" style="display:none;" aria-labelledby="pyro-sa-manual-script-modal-title-h2" role="dialog" aria-modal="true">
      <div class="box">
        <h2 id="pyro-sa-manual-script-modal-title-h2" style="margin-top:0;"><?php esc_html_e('Add New Script', 'pyro-script-audit');?></h2>
        <form id="pyro-sa-manual-script-form">
          <input type="hidden" id="pyro-sa-manual-script-modal-mode" value="add">
          <input type="hidden" id="pyro-sa-manual-script-original-handle" value="">

          <p><label for="pyro-sa-manual-handle"><strong><?php esc_html_e('Handle:', 'pyro-script-audit'); ?></strong> <span style="color:red;">*</span></label><br>
             <input type="text" id="pyro-sa-manual-handle" name="handle" class="widefat" required pattern="[a-zA-Z0-9_-]+" title="<?php esc_attr_e('Alphanumeric, underscores, hyphens only. No spaces.', 'pyro-script-audit');?>">
             <small><?php esc_html_e('A unique identifier (e.g., my-custom-script). Cannot be changed after creation.', 'pyro-script-audit');?></small></p>

          <p><label for="pyro-sa-manual-src"><strong><?php esc_html_e('Source URL (src):', 'pyro-script-audit'); ?></strong> <span style="color:red;">*</span></label><br>
             <input type="url" id="pyro-sa-manual-src" name="src" class="widefat" required placeholder="https://example.com/path/to/script.js"></p>

          <p><label for="pyro-sa-manual-ver"><?php esc_html_e('Version (ver):', 'pyro-script-audit'); ?></label><br>
             <input type="text" id="pyro-sa-manual-ver" name="ver" class="widefat" placeholder="1.0.0"></p>

          <p><label for="pyro-sa-manual-deps"><?php esc_html_e('Dependencies (deps, comma-separated):', 'pyro-script-audit'); ?></label><br>
             <input type="text" id="pyro-sa-manual-deps" name="deps" class="widefat" placeholder="jquery, other-handle">
             <small><?php esc_html_e('Enter script handles, e.g., jquery.', 'pyro-script-audit');?></small></p>

          <p style="margin-bottom: 5px;"><label><input type="checkbox" id="pyro-sa-manual-in-footer" name="in_footer" value="1"> <?php esc_html_e('Load in footer?', 'pyro-script-audit'); ?></label></p>

          <p><label for="pyro-sa-manual-strategy"><?php esc_html_e('Strategy:', 'pyro-script-audit'); ?></label><br>
             <select id="pyro-sa-manual-strategy" name="strategy" class="widefat">
               <option value="none"><?php esc_html_e('None (default)', 'pyro-script-audit'); ?></option>
               <option value="defer"><?php esc_html_e('Defer', 'pyro-script-audit'); ?></option>
               <option value="async"><?php esc_html_e('Async', 'pyro-script-audit'); ?></option>
             </select></p>

          <hr style="margin: 20px 0;">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <button type="submit" class="button button-primary" id="pyro-sa-save-manual-script-btn"><?php esc_html_e('Save Script', 'pyro-script-audit'); ?></button>
            <button type="button" id="pyro-sa-manual-script-modal-close-btn" class="button"><?php esc_html_e('Cancel', 'pyro-script-audit'); ?></button>
          </div>
        </form>
      </div>
    </div>
    <?php
}

?>