<?php
/**
 * Pyro Script Audit - Manual Script Enqueue Engine
 *
 * This file contains the functionality for conditionally enqueuing scripts
 * that have been manually added by the administrator via the plugin's UI.
 *
 * @package Pyro_Script_Audit
 * @since   3.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueues manually added scripts based on their stored conditional rules.
 *
 * Iterates through the list of manually added scripts, checks their associated
 * rules against the current context using pyro_sa_match_rules(). If the rules
 * pass (or if no rules are set, implying "always enqueue for this script"),
 * the script is registered and enqueued.
 *
 * @since 3.0.0 (Originally ps_sa_enqueue_manual_scripts)
 */
function pyro_sa_enqueue_manual_scripts() {
    // Retrieve the list of manually added scripts.
    // PYRO_SA_MANUAL_SCRIPTS_OPT should be defined in the main plugin file.
    $manual_scripts_list = get_option( PYRO_SA_MANUAL_SCRIPTS_OPT, [] );

    if ( empty( $manual_scripts_list ) ) {
        return; // No scripts have been manually added.
    }

    global $wp_scripts; // Needed for script registration and strategy data.

    foreach ( $manual_scripts_list as $handle => $script_data ) {
        $should_enqueue_script = true; // Default to enqueueing if no rules are present.

        // Check conditions if rules are set for this manually added script.
        // The rules determine IF THE SCRIPT IS ENQUEUED on the current page.
        if ( ! empty( $script_data['rules'] ) && is_array( $script_data['rules'] ) ) {
            $should_enqueue_script = pyro_sa_match_rules( $script_data['rules'] );
        } elseif ( ! empty( $script_data['rules'] ) && ! is_array( $script_data['rules'] ) ) {
            // This indicates a potential data corruption for this script's rules.
            $should_enqueue_script = false; // Safely default to not enqueuing.
            // Consider logging this error if it's important to track.
            // error_log( "Pyro Script Audit: Malformed rules for manually added script '{$handle}'. Expected array, got " . gettype($script_data['rules']) );
        }
        // If $script_data['rules'] is empty or not set, $should_enqueue_script remains true (default behavior).

        if ( $should_enqueue_script ) {
            // Prepare script parameters from stored data.
            $src_url   = $script_data['src'] ?? '';
            $version   = !empty($script_data['ver']) ? (string)$script_data['ver'] : false; // false for no version
            $in_footer = isset($script_data['in_footer']) ? (bool)$script_data['in_footer'] : false;
            $strategy  = $script_data['strategy'] ?? 'none'; // 'none', 'async', or 'defer'

            // Prepare dependencies: stored as comma-separated string, needs to be an array.
            $dependencies_raw  = $script_data['deps'] ?? '';
            $dependencies      = [];
            if ( ! empty( $dependencies_raw ) && is_string( $dependencies_raw ) ) {
                $dependencies = array_map( 'trim', explode( ',', $dependencies_raw ) );
                $dependencies = array_filter( $dependencies ); // Remove any empty elements after explode
            } elseif ( is_array( $dependencies_raw ) ) { // Should not happen with current save logic but good check
                $dependencies = array_filter( array_map( 'trim', $dependencies_raw ) );
            }


            // Validate essential data before proceeding.
            if ( empty( $handle ) || ! is_string( $handle ) ) {
                // error_log( "Pyro Script Audit: Invalid handle for manually added script. Data: " . print_r($script_data, true) );
                continue; // Skip this script if handle is invalid.
            }
            if ( empty( $src_url ) || ! filter_var( $src_url, FILTER_VALIDATE_URL ) ) {
                // error_log( "Pyro Script Audit: Invalid source URL for manually added script '{$handle}'. URL: {$src_url}" );
                continue; // Skip this script if source URL is invalid.
            }

            // Register and enqueue the script.
            wp_register_script( $handle, esc_url( $src_url ), $dependencies, $version, $in_footer );
            wp_enqueue_script( $handle );

            // Apply async/defer strategy if specified and $wp_scripts object is available.
            if ( ($strategy === 'async' || $strategy === 'defer') && is_object( $wp_scripts ) ) {
                if ( method_exists( $wp_scripts, 'add_data' ) ) {
                    $wp_scripts->add_data( $handle, 'strategy', $strategy );
                }
                // For very old WP, one might filter 'script_loader_tag', but add_data is > WP 4.1
            }
        }
    }
}
// Hook to enqueue manually added scripts.
// Priority 5 is chosen to run relatively early, but after most core/theme setup,
// allowing dependencies like jQuery to be registered if manual scripts rely on them.
// This should run on both front-end and admin, as conditions will control where it loads.
add_action( 'wp_enqueue_scripts',    'pyro_sa_enqueue_manual_scripts', 5 );
add_action( 'admin_enqueue_scripts', 'pyro_sa_enqueue_manual_scripts', 5 );
// If you want them to also work with login pages or other contexts:
// add_action( 'login_enqueue_scripts', 'pyro_sa_enqueue_manual_scripts', 5 );

?>