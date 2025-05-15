<?php
/**
 * Pyro Script Audit - Admin Screen Options
 *
 * This file handles the integration with WordPress Screen Options for
 * customizing the visibility of columns in the plugin's admin tables.
 *
 * @package Pyro_Script_Audit
 * @since   3.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Adds column visibility checkboxes to the "Screen Options" tab.
 *
 * This function is hooked to the 'screen_settings' filter.
 *
 * @since 3.1.0 (Consolidates logic from previous versions)
 *
 * @param string    $screen_settings_html Existing screen settings HTML.
 * @param WP_Screen $screen            The current WP_Screen object.
 * @return string Modified screen settings HTML.
 */
function pyro_sa_add_column_screen_options( $screen_settings_html, $screen ) {
    // Target only our plugin's admin page.
    // Ensure the menu slug 'pyro-script-audit-log' matches what's in add_management_page().
    if ( ! is_object( $screen ) || $screen->id !== 'tools_page_pyro-script-audit-log' ) {
        return $screen_settings_html;
    }

    $current_tab             = pyro_sa_get_current_tab_for_screen_options(); // From utils.php
    $all_columns_for_this_tab = pyro_sa_get_all_columns_for_tab( $current_tab );    // From utils.php
    $visible_cols_for_this_tab = pyro_sa_visible_cols_for_tab( $current_tab );     // From utils.php

    ob_start();
    ?>
    <fieldset class="metabox-prefs pyro-sa-screen-options-fieldset">
        <legend><?php printf( esc_html__( 'Columns for "%s Scripts" Tab', 'pyro-script-audit' ), ucfirst( esc_html( $current_tab ) ) ); ?></legend>
        
        <!-- These hidden fields are essential for the WordPress screen options saving mechanism -->
        <input type="hidden" name="wp_screen_options[option]" value="pyro_sa_cols_option_proxy_<?php echo esc_attr($current_tab); ?>">
        <input type="hidden" name="wp_screen_options[value]" value="<?php echo esc_attr($current_tab); // Pass current tab to save handler ?>">

        <?php if ( empty( $all_columns_for_this_tab ) ) : ?>
            <p><?php esc_html_e( 'No columns available for this view.', 'pyro-script-audit' ); ?></p>
        <?php else : ?>
            <?php foreach ( $all_columns_for_this_tab as $column_key => $column_config ) :
                $label_text = $column_config[0];
                // Generate a unique ID for each checkbox to ensure label 'for' attribute works correctly.
                $checkbox_id = 'pyro-sa-col-' . esc_attr( $column_key ) . '-' . esc_attr( $current_tab );
                ?>
                <label for="<?php echo $checkbox_id; ?>">
                    <input class="hide-column-tog" type="checkbox"
                           id="<?php echo $checkbox_id; ?>"
                           name="pyro_sa_cols_<?php echo esc_attr($current_tab); ?>[]"  <?php // Tab-specific name for submitted data ?>
                           value="<?php echo esc_attr( $column_key ); ?>"
                           <?php checked( in_array( $column_key, $visible_cols_for_this_tab, true ) ); ?>>
                    <?php echo esc_html( $label_text ); ?>
                </label>
            <?php endforeach; ?>
        <?php endif; ?>
    </fieldset>
    <p class="submit">
        <?php // The 'screen-options-nonce' is added automatically by WordPress when this form is part of screen options. ?>
        <input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Apply', 'pyro-script-audit' ); ?>">
    </p>    
    <?php
    return $screen_settings_html . ob_get_clean();
}
add_filter( 'screen_settings', 'pyro_sa_add_column_screen_options', 10, 2 );


/**
 * Saves the user's column visibility preferences for a specific tab.
 *
 * This function is hooked to 'admin_init' but should only fire when
 * our specific screen options are being submitted.
 *
 * @since 3.1.0 (Consolidates logic from previous versions)
 */
function pyro_sa_save_column_screen_options() {
    // Check if our specific screen options form was submitted.
    // The 'wp_screen_options[option]' value must match what's set in pyro_sa_add_column_screen_options().
    // We use a dynamic proxy option name including the tab slug.
    if ( ! isset( $_POST['screenoptionnonce'], $_POST['wp_screen_options']['option'], $_POST['wp_screen_options']['value'] ) ) {
        return;
    }

    $submitted_option_proxy = sanitize_text_field( wp_unslash( $_POST['wp_screen_options']['option'] ) );
    $submitted_tab_slug     = sanitize_key( wp_unslash( $_POST['wp_screen_options']['value'] ) ); // Tab slug passed in 'value'

    // Ensure this save action is for one of our plugin's screen option sets.
    if ( strpos( $submitted_option_proxy, 'pyro_sa_cols_option_proxy_' ) !== 0 ) {
        return;
    }
    
    // Extract tab slug from the proxy option name if not directly from value (fallback, less reliable)
    if (empty($submitted_tab_slug)) {
        $submitted_tab_slug = str_replace('pyro_sa_cols_option_proxy_', '', $submitted_option_proxy);
    }

    if ( ! in_array( $submitted_tab_slug, [ 'found', 'dequeued', 'manual' ], true ) ) {
        // Invalid tab slug identified from the submission.
        return;
    }

    // Verify user capability and nonce.
    if ( ! pyro_sa_can_manage() ) { // Function from utils.php
        wp_die( esc_html__( 'Permissions check failed.', 'pyro-script-audit' ) );
    }
    check_admin_referer( 'screen-options-nonce', 'screenoptionnonce' ); // WordPress handles this nonce.

    // Construct the actual user option name based on the tab.
    $user_option_name_for_tab = 'pyro_sa_cols_' . $submitted_tab_slug;

    // Get the submitted columns for the specific tab.
    // The name attribute of checkboxes is 'pyro_sa_cols_{tab_slug}[]'.
    $post_key_for_cols = 'pyro_sa_cols_' . $submitted_tab_slug;
    $submitted_cols_for_tab = isset( $_POST[ $post_key_for_cols ] ) ? (array) wp_unslash( $_POST[ $post_key_for_cols ] ) : [];
    $sanitized_cols_for_tab = array_map( 'sanitize_text_field', $submitted_cols_for_tab );
    
    // Get all valid columns for this tab to ensure we only save valid ones.
    $valid_cols_for_this_tab = array_keys( pyro_sa_get_all_columns_for_tab( $submitted_tab_slug ) );
    $final_cols_to_save = array_intersect( $valid_cols_for_this_tab, $sanitized_cols_for_tab );

    update_user_option( get_current_user_id(), $user_option_name_for_tab, $final_cols_to_save );

    // Redirect back to the same page and tab to see changes.
    // wp_get_referer() should point back to the admin page.
    $redirect_url = add_query_arg( 'tab', $submitted_tab_slug, wp_get_referer() );
    if ( $redirect_url ) {
        wp_safe_redirect( $redirect_url );
        exit;
    }
    // Fallback redirect if referer is lost.
    wp_safe_redirect( admin_url( 'tools.php?page=pyro-script-audit-log&tab=' . $submitted_tab_slug ) );
    exit;
}
// Hook later in admin_init to ensure current screen might be set, though referer is more reliable for tab.
add_action( 'admin_init', 'pyro_sa_save_column_screen_options', 20 );

?>