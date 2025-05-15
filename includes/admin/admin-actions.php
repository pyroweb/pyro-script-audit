<?php
/**
 * Pyro Script Audit - Admin Post Action Handlers
 *
 * This file contains functions that handle form submissions via admin-post.php
 * for various administrative actions within the plugin.
 *
 * @package Pyro_Script_Audit
 * @since   3.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Verifies nonce and capability for admin actions.
 * Dies if verification fails.
 *
 * @since 3.1.0
 * @param string $action_name The nonce action name to verify. Default 'pyro_sa_actions_nonce'.
 */
function pyro_sa_verify_action_nonce( $action_name = 'pyro_sa_actions_nonce' ) {
    if ( ! isset( $_REQUEST[ PYRO_SA_NONCE_KEY ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ PYRO_SA_NONCE_KEY ] ) ), $action_name ) ) {
        wp_die( esc_html__( 'Nonce verification failed. Action aborted.', 'pyro-script-audit' ), esc_html__( 'Security Check Failed', 'pyro-script-audit' ), 403 );
    }
    if ( ! pyro_sa_can_manage() ) { // Function from utils.php
        wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'pyro-script-audit' ), esc_html__( 'Permission Denied', 'pyro-script-audit' ), 403 );
    }
}

/**
 * Redirects the user back to the referring page, typically the plugin admin page.
 *
 * @since 3.1.0
 */
function pyro_sa_admin_redirect_back() {
    $referer = wp_get_referer();
    if ( $referer ) {
        wp_safe_redirect( $referer );
    } else {
        // Fallback if referer is not set (should not happen with admin-post actions)
        wp_safe_redirect( admin_url( 'tools.php?page=pyro-script-audit-log' ) );
    }
    exit;
}

/**
 * Handles dequeuing a single script from the "Found" log.
 * Moves the script from the found log to the dequeued log with default rules.
 *
 * Action: pyro_sa_dequeue_script
 *
 * @since 3.1.0 (Adapted from ps_sa_handle_dequeue)
 */
function pyro_sa_handle_dequeue_script_action() {
    pyro_sa_verify_action_nonce();
    $handle_to_dequeue = isset( $_POST['handle'] ) ? sanitize_text_field( wp_unslash( $_POST['handle'] ) ) : '';

    if ( empty( $handle_to_dequeue ) ) {
        // Optionally add an admin notice here if you have a system for it.
        pyro_sa_admin_redirect_back();
    }

    $found_scripts    = get_option( PYRO_SA_FOUND_SCRIPTS_OPT, [] );
    $dequeued_scripts = get_option( PYRO_SA_DEQUEUED_SCRIPTS_OPT, [] );

    if ( isset( $found_scripts[ $handle_to_dequeue ] ) ) {
        // Move to dequeued list with default rules (front-end only)
        $dequeued_scripts[ $handle_to_dequeue ] = $found_scripts[ $handle_to_dequeue ]; // Copy data
        $dequeued_scripts[ $handle_to_dequeue ]['dequeued'] = time();
        $dequeued_scripts[ $handle_to_dequeue ]['rules']    = pyro_sa_get_default_conditional_rules(); // from utils.php

        unset( $found_scripts[ $handle_to_dequeue ] ); // Remove from found list

        update_option( PYRO_SA_FOUND_SCRIPTS_OPT, $found_scripts, false );
        update_option( PYRO_SA_DEQUEUED_SCRIPTS_OPT, $dequeued_scripts, false );
    }
    pyro_sa_admin_redirect_back();
}
add_action( 'admin_post_pyro_sa_dequeue_script', 'pyro_sa_handle_dequeue_script_action' );


/**
 * Handles restoring a single script from the "Dequeued" log.
 * This effectively removes it from the dequeued list, allowing it to be
 * logged by the crawler again if still present.
 *
 * Action: pyro_sa_restore_script
 *
 * @since 3.1.0 (Adapted from ps_sa_handle_restore)
 */
function pyro_sa_handle_restore_script_action() {
    pyro_sa_verify_action_nonce();
    $handle_to_restore = isset( $_POST['handle'] ) ? sanitize_text_field( wp_unslash( $_POST['handle'] ) ) : '';

    if ( empty( $handle_to_restore ) ) {
        pyro_sa_admin_redirect_back();
    }

    $dequeued_scripts = get_option( PYRO_SA_DEQUEUED_SCRIPTS_OPT, [] );
    if ( isset( $dequeued_scripts[ $handle_to_restore ] ) ) {
        unset( $dequeued_scripts[ $handle_to_restore ] );
        update_option( PYRO_SA_DEQUEUED_SCRIPTS_OPT, $dequeued_scripts, false );
    }
    pyro_sa_admin_redirect_back();
}
add_action( 'admin_post_pyro_sa_restore_script', 'pyro_sa_handle_restore_script_action' );

/**
 * Handles deleting a script entry from the "Found" log.
 * This permanently removes it from being tracked as "found".
 *
 * Action: pyro_sa_delete_log_entry
 *
 * @since 3.1.0 (Adapted from ps_sa_handle_delete)
 */
function pyro_sa_handle_delete_log_entry_action() {
    pyro_sa_verify_action_nonce();
    $handle_to_delete = isset( $_POST['handle'] ) ? sanitize_text_field( wp_unslash( $_POST['handle'] ) ) : '';

    if ( empty( $handle_to_delete ) ) {
        pyro_sa_admin_redirect_back();
    }

    $found_scripts = get_option( PYRO_SA_FOUND_SCRIPTS_OPT, [] );
    if ( isset( $found_scripts[ $handle_to_delete ] ) ) {
        unset( $found_scripts[ $handle_to_delete ] );
        update_option( PYRO_SA_FOUND_SCRIPTS_OPT, $found_scripts, false );
    }
    pyro_sa_admin_redirect_back();
}
add_action( 'admin_post_pyro_sa_delete_log_entry', 'pyro_sa_handle_delete_log_entry_action' );


/**
 * Handles clearing the entire "Found" scripts log.
 *
 * Action: pyro_sa_clear_found_log
 *
 * @since 3.1.0 (Adapted from ps_sa_handle_clear)
 */
function pyro_sa_handle_clear_found_log_action() {
    pyro_sa_verify_action_nonce();
    delete_option( PYRO_SA_FOUND_SCRIPTS_OPT );
    pyro_sa_admin_redirect_back();
}
add_action( 'admin_post_pyro_sa_clear_found_log', 'pyro_sa_handle_clear_found_log_action' );


/**
 * Handles exporting logged data (Found or Dequeued) to a CSV file.
 *
 * Action: pyro_sa_export_csv
 *
 * @since 3.1.0 (Adapted from ps_sa_handle_csv)
 */
function pyro_sa_handle_export_csv_action() {
    pyro_sa_verify_action_nonce();

    $tab_to_export = isset( $_POST['export_tab'] ) ? sanitize_key( $_POST['export_tab'] ) : 'found';
    $filter_pos    = isset( $_POST['export_pos'] ) ? sanitize_key( $_POST['export_pos'] ) : ''; // For 'found' tab

    $data_to_export = [];
    $filename_part = $tab_to_export;
    $date_key = 'found'; // Default date key for CSV

    if ( $tab_to_export === 'found' ) {
        $data_to_export = get_option( PYRO_SA_FOUND_SCRIPTS_OPT, [] );
    } elseif ( $tab_to_export === 'dequeued' ) {
        $data_to_export = get_option( PYRO_SA_DEQUEUED_SCRIPTS_OPT, [] );
        $date_key = 'dequeued';
    } elseif ( $tab_to_export === 'manual' ) {
        $data_to_export = get_option( PYRO_SA_MANUAL_SCRIPTS_OPT, [] );
        $date_key = 'added_on'; // Or 'updated_on' if you prefer
    } else {
        // Invalid tab, redirect back.
        pyro_sa_admin_redirect_back();
    }

    // Define CSV headers based on the tab, using pyro_sa_get_all_columns_for_tab
    $all_columns_for_tab = pyro_sa_get_all_columns_for_tab( $tab_to_export );
    $csv_headers = [];
    foreach ( $all_columns_for_tab as $col_key => $col_config ) {
        $csv_headers[$col_key] = $col_config[0]; // Use the label as header
    }
    // Add any extra headers if needed (e.g. a raw timestamp if 'date' is formatted)

    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=pyro-script-audit-' . $filename_part . '-' . date( 'Y-m-d' ) . '.csv' );

    $output_stream = fopen( 'php://output', 'w' );
    fputcsv( $output_stream, array_values($csv_headers) ); // Write header row

    foreach ( $data_to_export as $handle => $item_data ) {
        // Filter for 'found' tab by position, if applicable
        if ( $tab_to_export === 'found' && $filter_pos && ( $filter_pos === 'header' ) === ( $item_data['in_footer'] ?? false ) ) {
            continue;
        }

        $csv_row = [];
        foreach (array_keys($csv_headers) as $col_key) { // Iterate in header order
            $cell_value = '—'; // Default
            switch ($col_key) {
                case 'handle':    $cell_value = $handle; break;
                case 'src':       $cell_value = $item_data['src'] ?? ''; break;
                case 'ver':       $cell_value = $item_data['ver'] ?? ''; break;
                case 'footer':    $cell_value = ($item_data['in_footer'] ?? false) ? 'Yes' : 'No'; break;
                case 'strategy':  $cell_value = ucfirst($item_data['strategy'] ?? 'none'); break;
                case 'deps':      $cell_value = is_array($item_data['deps'] ?? null) ? implode('|', $item_data['deps']) : ($item_data['deps'] ?? ''); break;
                case 'size':      $cell_value = $item_data['size'] ?? ''; break; // Raw size
                case 'mtime':     $cell_value = $item_data['mtime'] ? date('c', $item_data['mtime']) : ''; break; // ISO 8601 date
                case 'date':      $cell_value = isset($item_data[$date_key]) ? date('c', $item_data[$date_key]) : ''; break;
                case 'added_on':  $cell_value = isset($item_data['added_on']) ? date('c', $item_data['added_on']) : ''; break;
                case 'rules':
                    if (!empty($item_data['rules']) && is_array($item_data['rules'])) {
                        $actual_rules = array_filter($item_data['rules'], function($k){ return $k !== '__mode'; }, ARRAY_FILTER_USE_KEY);
                        $rule_parts = [];
                        foreach ($actual_rules as $cbk => $param_v) {
                             $is_n = strpos($cbk, '__neg:') === 0; $cb_n = $is_n ? substr($cbk, 6) : $cbk; $pfx = $is_n ? 'NOT ' : '';
                             $p_s = '';
                             if (is_array($param_v)) { $p_s = implode('/', $param_v); } // Simple separator for CSV
                             elseif ($param_v !== true && $param_v !== null) { $p_s = (string)$param_v; }
                             $rule_parts[] = $pfx . $cb_n . ($p_s ? "($p_s)" : "()");
                        }
                        $cell_value = implode(' | ', $rule_parts);
                        if (isset($item_data['rules']['__mode'])) { $cell_value .= ' (Mode: ' . strtoupper($item_data['rules']['__mode']) . ')'; }
                    } else { $cell_value = '';}
                    break;
            }
            $csv_row[] = $cell_value;
        }
        fputcsv( $output_stream, $csv_row );
    }
    fclose( $output_stream );
    exit;
}
add_action( 'admin_post_pyro_sa_export_csv', 'pyro_sa_handle_export_csv_action' );


/**
 * Handles unified bulk actions from the admin table.
 * Determines action based on current tab and selected bulk_action.
 *
 * Action: pyro_sa_handle_bulk_unified
 *
 * @since 3.1.0 (Replaces ps_sa_handle_bulk)
 */
function pyro_sa_handle_bulk_unified_action() {
    pyro_sa_verify_action_nonce();

    $handles_to_process = isset( $_POST['handles'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['handles'] ) ) : [];
    $bulk_action_selected = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
    $current_tab          = isset( $_POST['current_tab'] ) ? sanitize_key( $_POST['current_tab'] ) : 'found';

    if ( empty( $handles_to_process ) || empty( $bulk_action_selected ) ) {
        pyro_sa_admin_redirect_back(); // Nothing to do
    }

    $found_scripts    = get_option( PYRO_SA_FOUND_SCRIPTS_OPT, [] );
    $dequeued_scripts = get_option( PYRO_SA_DEQUEUED_SCRIPTS_OPT, [] );
    $manual_scripts   = get_option( PYRO_SA_MANUAL_SCRIPTS_OPT, [] );

    foreach ( $handles_to_process as $handle ) {
        if ( $current_tab === 'found' ) {
            if ( 'dequeue' === $bulk_action_selected && isset( $found_scripts[ $handle ] ) ) {
                $dequeued_scripts[ $handle ] = $found_scripts[ $handle ] + [ 'dequeued' => time(), 'rules' => pyro_sa_get_default_conditional_rules() ];
                unset( $found_scripts[ $handle ] );
            } elseif ( 'dequeue_front' === $bulk_action_selected && isset( $found_scripts[ $handle ] ) ) {
                $dequeued_scripts[ $handle ] = $found_scripts[ $handle ] + [ 'dequeued' => time(), 'rules' => [ 'pyro_sa_condition_is_frontend' => true ] ];
                unset( $found_scripts[ $handle ] );
            } elseif ( 'delete_log' === $bulk_action_selected && isset( $found_scripts[ $handle ] ) ) {
                unset( $found_scripts[ $handle ] );
            }
        } elseif ( $current_tab === 'dequeued' ) {
            if ( 'restore' === $bulk_action_selected && isset( $dequeued_scripts[ $handle ] ) ) {
                unset( $dequeued_scripts[ $handle ] );
            }
        } elseif ( $current_tab === 'manual' ) {
            if ( 'remove_manual' === $bulk_action_selected && isset( $manual_scripts[ $handle ] ) ) {
                unset( $manual_scripts[ $handle ] );
            }
        }
    }

    // Update options that might have changed
    if ( $current_tab === 'found' || $bulk_action_selected === 'dequeue' || $bulk_action_selected === 'dequeue_front' ) {
        update_option( PYRO_SA_FOUND_SCRIPTS_OPT, $found_scripts, false );
    }
    if ( $current_tab === 'dequeued' || $bulk_action_selected === 'dequeue' || $bulk_action_selected === 'dequeue_front' || $bulk_action_selected === 'restore' ) {
        update_option( PYRO_SA_DEQUEUED_SCRIPTS_OPT, $dequeued_scripts, false );
    }
    if ( $current_tab === 'manual' ) {
        update_option( PYRO_SA_MANUAL_SCRIPTS_OPT, $manual_scripts, false );
    }
    
    pyro_sa_admin_redirect_back();
}
add_action( 'admin_post_pyro_sa_handle_bulk_unified', 'pyro_sa_handle_bulk_unified_action' );


/**
 * AJAX handler for refreshing the nonce value.
 * Used by the admin page to keep nonces fresh for long-open pages.
 *
 * Action: wp_ajax_pyro_sa_refresh_nonce (admin-post.php is simpler here, but AJAX could be used)
 * Using admin-post for simplicity as it's already set up.
 *
 * @since 3.0.0 (Originally ps_sa_ajax_nonce, changed from AJAX to admin-post)
 */
function pyro_sa_admin_post_refresh_nonce() {
    // No nonce check needed here, as we are *generating* a new one.
    // Capability check is still good practice.
    if ( pyro_sa_can_manage() ) {
        // The action name for the nonce being created should match what pyro_sa_verify_action_nonce expects
        echo wp_create_nonce( 'pyro_sa_actions_nonce' ); 
    } else {
        echo 'error_cap'; // Or handle error more gracefully
    }
    exit;
}
add_action( 'admin_post_pyro_sa_refresh_nonce', 'pyro_sa_admin_post_refresh_nonce' ); 
// The JS should call admin_url('admin-post.php?action=pyro_sa_refresh_nonce')

?>