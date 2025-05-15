<?php
/**
 * Pyro Script Audit - Dequeue Engine
 *
 * This file contains the functionality for conditionally dequeuing scripts
 * based on saved rules and current page context.
 *
 * @package Pyro_Script_Audit
 * @since   3.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Applies dequeue and deregister rules for scripts.
 *
 * Iterates through the list of scripts marked for dequeuing and checks
 * their associated rules against the current context. If rules match,
 * the script is dequeued and deregistered.
 *
 * Hooked to various script-related actions to ensure it runs at the right time.
 *
 * @since 3.0.0 (Originally ps_sa_apply_dequeue)
 */
function pyro_sa_apply_script_dequeue_rules() {
    // Retrieve the list of scripts to be conditionally dequeued.
    // PYRO_SA_DEQUEUED_SCRIPTS_OPT should be defined in the main plugin file.
    $dequeued_scripts_list = get_option( PYRO_SA_DEQUEUED_SCRIPTS_OPT, [] );

    if ( empty( $dequeued_scripts_list ) ) {
        return; // No scripts marked for dequeuing.
    }

    foreach ( $dequeued_scripts_list as $handle => $script_data ) {
        // The rules are stored in $script_data['rules'].
        // If 'rules' key doesn't exist or is not an array, pass an empty array to match_rules.
        $rules_for_handle = $script_data['rules'] ?? [];
        
        if ( pyro_sa_match_rules( $rules_for_handle ) ) {
            wp_dequeue_script( $handle );
            wp_deregister_script( $handle );
            // Optional: Add a log or debug message here if needed.
            // error_log( "Pyro Script Audit: Dequeued script '{$handle}' on " . esc_url( home_url( add_query_arg( null, null ) ) ) );
        }
    }
}

// Hook the dequeue function to run at appropriate times for both front-end and admin.
// Priority 9999 to run very late, after most scripts would have been enqueued.
add_action( 'wp_enqueue_scripts',      'pyro_sa_apply_script_dequeue_rules', 9999 );
add_action( 'admin_enqueue_scripts',   'pyro_sa_apply_script_dequeue_rules', 9999 );

// These hooks run even later and can catch scripts enqueued directly via print actions.
// Priority 0 to run early on these specific print hooks.
add_action( 'wp_print_scripts',        'pyro_sa_apply_script_dequeue_rules', 0 );
add_action( 'wp_print_footer_scripts', 'pyro_sa_apply_script_dequeue_rules', 0 );


/**
 * Matches a set of rules against the current WordPress context.
 *
 * Evaluates conditional tags (e.g., is_singular(), is_home()) based on the
 * provided rules array, supporting 'any' (OR) or 'all' (AND) matching modes,
 * and negation of conditions (prefixed with '__neg:').
 *
 * @since 3.0.0 (Originally ps_sa_match_rules)
 *
 * @param array $rules_data The array of rules to evaluate. Expected format:
 *                          ['__mode' => 'any'|'all', 'callback_name' => params, '__neg:another_cb' => params].
 * @return bool True if the rules match the current context, false otherwise.
 */
function pyro_sa_match_rules( array $rules_data ) : bool {
    // Case 1: No rules defined (empty array after filtering out __mode or initially empty).
    // Default behavior: If rules are explicitly set but empty (e.g. user cleared all conditions),
    // it should mean "don't match any specific condition, so don't act".
    // However, if the 'rules' key was entirely absent for a script in the option,
    // that implies a default (e.g., front-end only for dequeuing).
    // The `?? []` in pyro_sa_apply_script_dequeue_rules handles the "absent rules key" case by passing empty.
    
    // Determine the matching mode (AND/OR). Default to 'any' (OR logic).
    $mode = $rules_data['__mode'] ?? 'any'; // 'any' or 'all'
    
    // Filter out the '__mode' key to get only the actual rule definitions.
    $actual_rules = array_filter( $rules_data, function( $key ) {
        return $key !== '__mode';
    }, ARRAY_FILTER_USE_KEY );

    // If there are no actual rule lines to check (e.g., only __mode was set, or rules were cleared)
    // For dequeuing: if rules are empty, it means no specific condition is met by the page,
    // so we rely on a default behavior. The `ps_sa_default_rules()` used to provide 'ps_sa_front' => true.
    // If the rules array is truly empty here, it means the user wants more specific control than just 'front-end'.
    // So, if no rule lines, it should evaluate to FALSE unless a default is specified.
    // Let's assume if $actual_rules is empty, it means "no conditions are actively met for this page."
    // The only exception is if $rules_data itself was empty, which is handled by the caller usually.
    // Let's refine this: if $rules_data (passed in) was empty, it implies "use default conditions for this script type".
    // If $rules_data had rules but they were all cleared by the user, leaving $actual_rules empty, it means "match nothing".

    if ( empty( $rules_data ) ) { // This means the 'rules' key for the script was missing or an empty array from the start.
        // For dequeuing, the original default was 'front-end only'. Let's maintain that.
        return pyro_sa_condition_is_frontend();
    }
    
    if ( empty( $actual_rules ) ) {
        // If rules were explicitly cleared by the user, it means no specific condition applies.
        // So, don't dequeue unless there's an implicit "always dequeue if no rules" (which is not the case here).
        return false; 
    }

    $any_rule_matched = false; // Flag for 'any' (OR) mode

    foreach ( $actual_rules as $callback_key => $parameter ) {
        $is_negated   = strpos( $callback_key, '__neg:' ) === 0;
        $callback_name = $is_negated ? substr( $callback_key, strlen( '__neg:' ) ) : $callback_key;

        // Check if the base function name is callable.
        if ( ! is_callable( $callback_name ) ) {
            // error_log( "Pyro Script Audit: Rule callback '{$callback_name}' is not callable." );
            // If mode is 'all', an invalid/non-callable rule means the entire set fails.
            if ( $mode === 'all' ) {
                return false; 
            }
            // In 'any' mode, we can skip this invalid rule and check others.
            continue;
        }

        // Call the WordPress conditional tag or custom callback.
        $current_condition_result = false;
        if ( is_array( $parameter ) ) {
            // Spread array parameters: e.g., is_tax( 'category', 'news' )
            $current_condition_result = $callback_name( ...$parameter );
        } elseif ( $parameter === true || $parameter === null ) { 
            // Parameter is true (placeholder for no specific arg) or null: e.g., is_singular()
            $current_condition_result = $callback_name();
        } else {
            // Single parameter: e.g., is_singular( 'post' )
            $current_condition_result = $callback_name( $parameter );
        }

        // Apply negation if '__neg:' prefix was present.
        if ( $is_negated ) {
            $current_condition_result = ! $current_condition_result;
        }

        // Evaluate based on mode.
        if ( $mode === 'any' ) { // OR logic
            if ( $current_condition_result ) {
                $any_rule_matched = true;
                break; // In 'any' mode, one true condition is sufficient to make the whole set true.
            }
        } elseif ( $mode === 'all' ) { // AND logic
            if ( ! $current_condition_result ) {
                // In 'all' mode, one false condition means the entire set fails.
                return false; 
            }
        }
    } // End foreach loop over rules.

    // Final decision based on mode.
    if ( $mode === 'any' ) {
        return $any_rule_matched; // True if any rule matched during the loop, false otherwise.
    } elseif ( $mode === 'all' ) {
        // If the loop completed for 'all' mode without returning false, it means all rules passed.
        return true; 
    }

    // Fallback for an unknown mode (should not happen with current UI).
    // error_log( "Pyro Script Audit: Unknown rule matching mode '{$mode}'." );
    return false; 
}

?>