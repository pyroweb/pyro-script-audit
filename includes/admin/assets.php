<?php
/**
 * Pyro Script Audit - Admin Assets
 *
 * This file handles the enqueuing of JavaScript and CSS assets
 * specifically for the plugin's admin pages.
 *
 * @package Pyro_Script_Audit
 * @since   3.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueues scripts and styles for the Pyro Script Audit admin page.
 *
 * Also localizes data for JavaScript, such as REST API nonces and URLs.
 *
 * @since 3.1.0
 * @param string $hook_suffix The hook suffix of the current admin page.
 */
function pyro_sa_enqueue_admin_assets( $hook_suffix ) {
    // Target only our plugin's admin page.
    // 'tools_page_pyro-script-audit-log' should match the slug from add_management_page().
    if ( 'tools_page_pyro-script-audit-log' !== $hook_suffix ) {
        return;
    }

    // --- Styles ---
    // If you have custom CSS for the admin page:    
    wp_enqueue_style(
        'pyro-sa-admin-styles', // Handle
        PYRO_SA_ASSETS_URL . 'css/admin-styles.css', // Path to your CSS file (defined in main plugin file)
        [], // Dependencies
        PYRO_SA_VERSION // Version number (defined in main plugin file)
    );
    

    // --- JavaScript ---

    // Main admin page script (for sorting, master toggle, basic UI interactions, nonce refresh)
    wp_enqueue_script(
        'pyro-sa-admin-main', // Handle
        PYRO_SA_ASSETS_URL . 'js/admin-page-main.js', // Path to your JS file
        [], // Dependencies (e.g., 'jquery' if this script uses it directly, though your current one is vanilla)
        PYRO_SA_VERSION,
        true // Load in footer
    );
    // Data for admin-page-main.js (e.g., nonce key, nonce refresh URL)
    // PYRO_SA_NONCE_KEY and PYRO_SA_NONCE_AJAX_REFRESH are defined in main plugin file
    wp_localize_script(
        'pyro-sa-admin-main',
        'pyroSaMainData', // Object name in JavaScript
        [
            'nonceKey'           => PYRO_SA_NONCE_KEY,
            'nonceRefreshUrl'    => esc_url_raw( admin_url( 'admin-post.php?action=pyro_sa_refresh_nonce' ) ),
            'nonceRefreshInterval' => PYRO_SA_NONCE_AJAX_REFRESH * 1000, // Convert seconds to milliseconds
        ]
    );


    // Rule Builder script
    wp_enqueue_script(
        'pyro-sa-rule-builder',
        PYRO_SA_ASSETS_URL . 'js/admin-rule-builder.js',
        [], // No explicit dependencies other than wpApiSettings which is localized below
        PYRO_SA_VERSION,
        true
    );

    // Manual Script Add/Edit Modal script (depends on jQuery)
    wp_enqueue_script(
        'pyro-sa-manual-script-modal',
        PYRO_SA_ASSETS_URL . 'js/admin-manual-script-modal.js',
        ['pyro-sa-rule-builder'], // Dependency for pyroSaWpApiSettings
        PYRO_SA_VERSION,
        true
    );

    // Localize data needed by multiple admin scripts, especially REST API settings.
    // This creates `pyroSaWpApiSettings` object in JavaScript.
    // This should be localized to a script that loads before others needing it, or all of them.
    // Let's attach it to 'pyro-sa-rule-builder' as it definitely needs it.
    // If other scripts also need it, ensure 'pyro-sa-rule-builder' is a dependency for them or localize to them too.
    wp_localize_script(
        'pyro-sa-rule-builder', // Handle of the script to attach data to
        'pyroSaWpApiSettings',   // Object name available in JavaScript
        [
            'root'      => esc_url_raw( rest_url() ), // Base for REST API (e.g., https://site.com/wp-json/)
            'nonce'     => wp_create_nonce( 'wp_rest' ), // WP REST API Nonce
            'namespace' => 'pyro-sa/v1', // Your plugin's REST namespace
            'postTypes' => pyro_sa_get_post_type_options_for_js(),
            'taxes'     => pyro_sa_get_taxonomy_options_for_js(),
            'templates' => pyro_sa_get_template_options_for_js(),
            'i18n' => [
                'saving' => __('Saving...', 'pyro-script-audit'),
                'loading' => __('Loading...', 'pyro-script-audit'),
                'saveConditions' => __('Save Conditions', 'pyro-script-audit'),
                'chooseRuleType' => __('Please choose a rule type.', 'pyro-script-audit'),
                'taxonomyRequired' => __('Taxonomy is required.', 'pyro-script-audit'),
                'templateRequired' => __('Please select a template file.', 'pyro-script-audit'),
                'errorMissingPath' => __('Error: Handle or API path is missing. Cannot save.', 'pyro-script-audit'),
                'errorSavingDefault' => __('Could not save rules.', 'pyro-script-audit'),
                'errorLoadingRules' => __('Could not load existing rules. You can add new ones.', 'pyro-script-audit'),
                'postType' => __('Post type:', 'pyro-script-audit'),
                'taxonomy' => __('Taxonomy:', 'pyro-script-audit'),
                'term' => __('Term:', 'pyro-script-audit'),
                'termPlaceholder' => __('Term slug/ID (optional)', 'pyro-script-audit'),
                'template' => __('Template:', 'pyro-script-audit'),
                'condition' => __('Condition:', 'pyro-script-audit'),
                'is' => __('Is', 'pyro-script-audit'),
                'isNot' => __('Is Not', 'pyro-script-audit'),
            ]
        ]        
    );
}
add_action( 'admin_enqueue_scripts', 'pyro_sa_enqueue_admin_assets' );


// Helper functions to generate <option> strings for JS localization
// These can also live in utils.php or admin-utils.php if preferred

/**
 * Gets HTML <option> elements for all public post types.
 * @return string HTML string of <option> tags.
 */
function pyro_sa_get_post_type_options_for_js(): string {
    $options = '';
    $post_types = get_post_types( ['public' => true], 'objects' );
    foreach ( $post_types as $pt ) {
        $options .= sprintf( '<option value="%s">%s</option>', esc_attr( $pt->name ), esc_html( $pt->labels->singular_name ) );
    }
    return $options;
}

/**
 * Gets HTML <option> elements for all public taxonomies.
 * @return string HTML string of <option> tags.
 */
function pyro_sa_get_taxonomy_options_for_js(): string {
    $options = '';
    $taxonomies = get_taxonomies( ['public' => true], 'objects' );
    foreach ( $taxonomies as $tax ) {
        $options .= sprintf( '<option value="%s">%s</option>', esc_attr( $tax->name ), esc_html( $tax->labels->singular_name ) );
    }
    return $options;
}

/**
 * Gets HTML <option> elements for all page templates.
 * @return string HTML string of <option> tags.
 */
function pyro_sa_get_template_options_for_js(): string {
    $options = '';
    $templates = wp_get_theme()->get_page_templates();
    if ( ! empty( $templates ) ) {
        foreach ( $templates as $file => $name ) {
            $options .= sprintf( '<option value="%s">%s</option>', esc_attr( $file ), esc_html( $name ) );
        }
    }
    return $options;
}

?>