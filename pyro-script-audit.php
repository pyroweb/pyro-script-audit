<?php
/**
 * Plugin Name:       Pyro Script & Stylesheet Audit
 * Plugin URI:        https://pyroweb.co.uk/plugins/pyro-script-audit/
 * Description:       Tracks JavaScript & Stylesheet handles, allows context-aware dequeuing/enqueuing, bulk management, and more.
 * Version:           3.1.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            PyroWeb 
 * Author URI:        https://pyroweb.co.uk/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pyro-script-audit
 * Domain Path:       /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'PYRO_SA_VERSION', '3.1.0' );
define( 'PYRO_SA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) ); // PYRO_SA_PLUGIN_DIR will have a trailing slash
define( 'PYRO_SA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );   // PYRO_SA_PLUGIN_URL will have a trailing slash
define( 'PYRO_SA_INC_DIR', PYRO_SA_PLUGIN_DIR . 'includes/' );
define( 'PYRO_SA_ASSETS_URL', PYRO_SA_PLUGIN_URL . 'assets/' );

// Option names - using a prefix helps avoid collisions and makes them easy to find
define( 'PYRO_SA_OPT_PREFIX', 'pyro_sa_' );
define( 'PYRO_SA_FOUND_SCRIPTS_OPT', PYRO_SA_OPT_PREFIX . 'found_scripts' );
define( 'PYRO_SA_DEQUEUED_SCRIPTS_OPT', PYRO_SA_OPT_PREFIX . 'dequeued_scripts' );
define( 'PYRO_SA_MANUAL_SCRIPTS_OPT', PYRO_SA_OPT_PREFIX . 'manual_scripts' );
// Add more for stylesheets later, e.g., PYRO_SA_FOUND_STYLES_OPT

// Nonce related
define( 'PYRO_SA_NONCE_KEY', PYRO_SA_OPT_PREFIX . 'nonce' );
define( 'PYRO_SA_NONCE_LIFE', 12 * HOUR_IN_SECONDS );
define( 'PYRO_SA_NONCE_AJAX_REFRESH', 10 * MINUTE_IN_SECONDS );

/**
 * Load plugin textdomain for internationalization.
 */
function pyro_sa_load_textdomain() {
    load_plugin_textdomain( 'pyro-script-audit', false, basename( PYRO_SA_PLUGIN_DIR ) . '/languages' );
}
add_action( 'plugins_loaded', 'pyro_sa_load_textdomain' );

/**
 * Include core plugin files.
 *
 * We will group them by functionality.
 */
function pyro_sa_include_files() {
    // Core logic
    require_once PYRO_SA_INC_DIR . 'core/utils.php';                // General utility functions (like pyro_sa_cap)
    require_once PYRO_SA_INC_DIR . 'core/crawler.php';              // Script/Style discovery
    require_once PYRO_SA_INC_DIR . 'core/dequeue-engine.php';       // Conditional dequeuing
    require_once PYRO_SA_INC_DIR . 'core/manual-add-engine.php';    // Conditional manual enqueuing

    // Admin specific functionality
    if ( is_admin() ) {
        require_once PYRO_SA_INC_DIR . 'admin/admin-utils.php';         // Admin utility functions
        require_once PYRO_SA_INC_DIR . 'admin/admin-page.php';          // Main admin page UI
        require_once PYRO_SA_INC_DIR . 'admin/admin-actions.php';       // admin-post.php handlers
        require_once PYRO_SA_INC_DIR . 'admin/rest-api.php';            // REST API endpoints
        require_once PYRO_SA_INC_DIR . 'admin/screen-options.php';      // Screen options integration
        require_once PYRO_SA_INC_DIR . 'admin/assets.php';              // Enqueuing admin scripts/styles
        // require_once PYRO_SA_INC_DIR . 'admin/settings-page.php';    // For future settings page
    }
}
// Hook to include files early, but after plugins_loaded for textdomain and other plugins to hook in.
// 'init' is a common hook for this, or a custom action if needed.
add_action( 'plugins_loaded', 'pyro_sa_include_files', 15 ); // After textdomain load at priority 10

/**
 * Activation hook.
 * Could be used for setting default options, checking requirements.
 */
function pyro_sa_activate() {
    // Example: Set a default version option if it doesn't exist
    if ( false === get_option( PYRO_SA_OPT_PREFIX . 'version' ) ) {
        update_option( PYRO_SA_OPT_PREFIX . 'version', PYRO_SA_VERSION );
    }
    // Could also set default values for other options if needed.
}
register_activation_hook( __FILE__, 'pyro_sa_activate' );

/**
 * Deactivation hook.
 * Could be used for cleanup if necessary (e.g., removing scheduled tasks).
 * Generally, options are NOT deleted on deactivation unless explicitly requested.
 */
function pyro_sa_deactivate() {
    // No specific actions needed for now.
}
register_deactivation_hook( __FILE__, 'pyro_sa_deactivate' );

// Example of how hooks might be called from included files, or defined here:
// If hooks are defined *inside* the included files, they will just run when included.
// If you prefer to centralize main hook registrations here, you could do:
// add_action( 'admin_menu', 'pyro_sa_register_admin_page' ); // where pyro_sa_register_admin_page is in admin-page.php
// add_action( 'rest_api_init', 'pyro_sa_register_rest_routes' ); // where pyro_sa_register_rest_routes is in rest-api.php
// However, it's often cleaner to let the included files manage their own hooks if they are specific to that file's functionality.


// === Global Helper Functions (if any are truly needed globally AFTER includes) ===
// Your `ps_sa_cap` can now be `pyro_sa_cap` and live in `includes/core/utils.php`
// function pyro_sa_cap() { return current_user_can('manage_options'); } // Moved to utils.php
// function pyro_sa_get_array_value($a,$k,$d=null){return $a[$k]??$d;} // Example utility, moved to utils.php

?>