<?php
/**
 * Pyro Script Audit - Script Crawler
 *
 * This file contains the functionality for crawling the WordPress script queue
 * on the front-end and logging newly found script handles.
 *
 * @package Pyro_Script_Audit
 * @since   3.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Crawls the global $wp_scripts queue on front-end page loads to discover
 * and log new script handles that haven't been seen or dequeued before.
 *
 * This function only runs for users who can 'manage_options' and only on the front-end.
 *
 * @since 3.0.0 (Originally ps_sa_crawl_queue)
 */
function pyro_sa_crawl_script_queue() {
    // Only run on the front-end for users with appropriate capabilities.
    // Using pyro_sa_is_frontend() to be explicit, and pyro_sa_can_manage() for capability check.
    if ( ! pyro_sa_is_frontend() || ! pyro_sa_can_manage() ) {
        return;
    }

    global $wp_scripts;

    // Ensure $wp_scripts is an object and the queue is not empty.
    if ( ! is_object( $wp_scripts ) || empty( $wp_scripts->queue ) || ! is_array( $wp_scripts->queue ) ) {
        return;
    }

    // Retrieve existing found and dequeued scripts from options.
    // Constants are defined in the main plugin file (e.g., pyro-script-audit.php)
    $found_scripts    = get_option( PYRO_SA_FOUND_SCRIPTS_OPT, [] );
    $dequeued_scripts = get_option( PYRO_SA_DEQUEUED_SCRIPTS_OPT, [] );
    $manually_added_scripts = get_option( PYRO_SA_MANUAL_SCRIPTS_OPT, [] ); // Check against manual too

    $newly_found_scripts_data = []; // To collect new data before updating option once.
    $changed = false;

    foreach ( $wp_scripts->queue as $handle ) {
        // Skip if already logged in 'found', 'dequeued', or 'manually_added' lists.
        if ( isset( $found_scripts[ $handle ] ) || isset( $dequeued_scripts[ $handle ] ) || isset($manually_added_scripts[ $handle ]) ) {
            continue;
        }

        // Skip if the script is not registered (shouldn't happen if it's in queue, but good check).
        if ( empty( $wp_scripts->registered[ $handle ] ) ) {
            continue;
        }

        $script_object = $wp_scripts->registered[ $handle ];
        $source_url    = $script_object->src;
        $version       = $script_object->ver ?? ''; // Fallback to empty string if ver is null
        $dependencies  = $script_object->deps ?? [];
        $in_footer     = ! empty( $script_object->extra['group'] ); // 'group' extra data indicates footer

        // Attempt to get file size and modification time for local scripts.
        $file_size  = null;
        $file_mtime = null;
        if ( $source_url && strpos( $source_url, site_url() ) === 0 ) { // Check if it's a local URL
            $parsed_path = wp_parse_url( $source_url, PHP_URL_PATH );
            if ( $parsed_path ) {
                // wp_make_link_relative is good, but ensure path starts from ABSPATH.
                // A more robust way to get local file path:
                $relative_path = ltrim( str_replace( content_url(), '', $source_url ), '/' );
                $file_path = WP_CONTENT_DIR . '/' . $relative_path; // Common for plugins/themes

                // If not in WP_CONTENT_DIR, try from ABSPATH (less common for typical scripts)
                if ( ! @is_readable( $file_path ) ) {
                     $file_path_from_root = ABSPATH . ltrim( wp_make_link_relative( $parsed_path ), '/' );
                     if (@is_readable( $file_path_from_root )) {
                        $file_path = $file_path_from_root;
                     } else {
                        $file_path = null; // Could not resolve to a readable local file
                     }
                }

                if ( $file_path && @is_readable( $file_path ) ) {
                    $file_size  = @filesize( $file_path );
                    $file_mtime = @filemtime( $file_path );
                }
            }
        }

        // Get script loading strategy (async/defer) if available.
        $strategy = 'none'; // Default
        if ( method_exists( $wp_scripts, 'get_data' ) ) {
            $strategy_data = $wp_scripts->get_data( $handle, 'strategy' );
            if ( $strategy_data && in_array( $strategy_data, [ 'async', 'defer' ], true ) ) {
                $strategy = $strategy_data;
            }
        } elseif ( isset( $script_object->extra['strategy'] ) && in_array( $script_object->extra['strategy'], [ 'async', 'defer' ], true ) ) {
            // Fallback for older WP versions that might store it in 'extra' (less common now)
            $strategy = $script_object->extra['strategy'];
        }


        // Add to our collection for this run
        $newly_found_scripts_data[ $handle ] = [
            'src'       => $source_url,
            'ver'       => $version,
            'in_footer' => $in_footer,
            'strategy'  => $strategy,
            'deps'      => $dependencies,
            'size'      => $file_size,
            'mtime'     => $file_mtime,
            'found'     => time(), // Timestamp when it was first found by this plugin
        ];
        $changed = true;
    }

    // If any new scripts were found, update the option.
    if ( $changed ) {
        $updated_found_scripts = array_merge( $found_scripts, $newly_found_scripts_data );
        update_option( PYRO_SA_FOUND_SCRIPTS_OPT, $updated_found_scripts, false ); // 'false' for autoload
    }
}
// Hook the crawler to run late on wp_print_scripts.
// This ensures most scripts have been enqueued.
add_action( 'wp_print_scripts', 'pyro_sa_crawl_script_queue', 999 );

?>