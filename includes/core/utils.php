<?php
/**
 * Pyro Script Audit - Utility Functions
 *
 * @package Pyro_Script_Audit
 * @since   3.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Checks if the current user has the 'manage_options' capability.
 *
 * This is a common capability check for accessing plugin settings and admin areas.
 *
 * @since 3.0.0 (Originally ps_sa_cap)
 * @return bool True if the user can manage options, false otherwise.
 */
function pyro_sa_can_manage() : bool {
    return current_user_can( 'manage_options' );
}

/**
 * Safely retrieves a value from an array, with a default fallback.
 *
 * Similar to array_key_exists but returns a default value if the key is not found.
 * Uses the null coalescing operator.
 *
 * @since 3.0.0 (Originally ps_sa)
 *
 * @param array      $array   The array to retrieve the value from.
 * @param string|int $key     The key to look for in the array.
 * @param mixed      $default Optional. The default value to return if the key is not found. Default null.
 * @return mixed The value from the array if the key exists, or the default value.
 */
function pyro_sa_get_array_value( array $array, $key, $default = null ) {
    return $array[$key] ?? $default;
}

/**
 * Helper function to determine if the current context is the WordPress admin area.
 * Excludes AJAX requests.
 *
 * @since 3.1.0
 * @return bool True if in the WordPress admin area (not AJAX), false otherwise.
 */
function pyro_sa_is_admin_area() : bool {
    return is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX );
}


/**
 * Helper function to determine if the current context is the front-end of the site.
 *
 * @since 3.1.0 (Originally part of ps_sa_front, now more generic)
 * @return bool True if on the front-end, false otherwise.
 */
function pyro_sa_is_frontend() : bool {
    return ! is_admin(); // is_admin() is false for front-end, true for admin, true for AJAX in admin
}

/**
 * Conditional callback: Checks if the current view is on the front-end.
 * Used for rule matching.
 *
 * @since 3.0.0 (Originally ps_sa_front)
 * @return bool True if on the front-end, false otherwise.
 */
function pyro_sa_condition_is_frontend() : bool {
    return pyro_sa_is_frontend();
}

/**
 * Conditional callback: Checks if the current user is logged out.
 * Used for rule matching.
 *
 * @since 3.0.0 (Originally ps_sa_loggedout)
 * @return bool True if the user is logged out, false otherwise.
 */
function pyro_sa_condition_is_logged_out() : bool {
    return ! is_user_logged_in();
}

/**
 * Conditional callback: Checks if the current device is considered mobile by WordPress.
 * Used for rule matching. Relies on wp_is_mobile().
 *
 * @since 3.0.0 (Originally ps_sa_mobile)
 * @return bool True if wp_is_mobile() returns true, false otherwise.
 */
function pyro_sa_condition_is_mobile() : bool {
    // wp_is_mobile() might not always be perfectly accurate or might be disabled by some themes/plugins.
    return function_exists( 'wp_is_mobile' ) && wp_is_mobile();
}

/**
 * Provides the default rules array for scripts/styles when they are first dequeued
 * or when conditions are reset.
 *
 * @since 3.0.0 (Originally ps_sa_default_rules)
 * @return array The default rules array.
 */
function pyro_sa_get_default_conditional_rules() : array {
    // The key here 'pyro_sa_condition_is_frontend' must match the actual function name
    // that will be called by the rule matching engine.
    return [ 'pyro_sa_condition_is_frontend' => true ];
}


// You might add other general utility functions here as your plugin grows.
// For example:
// - Functions to sanitize specific types of input if they are complex.
// - Functions to format data for display if used in multiple places.

?>