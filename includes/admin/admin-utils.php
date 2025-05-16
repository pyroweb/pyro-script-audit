<?php
/**
 * Pyro Script Audit - Admin Utility Functions
 *
 * Helper functions specifically for the admin interface, such as
 * managing tabs, columns, screen options, and data for JS localization.
 *
 * @package Pyro_Script_Audit
 * @since   3.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Retrieves the defined columns for a specific tab in the admin interface.
 *
 * @since 3.1.0
 * @param string $tab_slug The slug of the tab ('found', 'dequeued', 'manual').
 * @return array An associative array defining columns: [key => [label, is_numeric_sort]].
 */
function pyro_sa_get_all_columns_for_tab(string $tab_slug): array {
    $columns = [
        // Consistently available columns first
        'handle'   => [ __( 'Handle', 'pyro-script-audit' ),   false ], // Label, is_numeric_for_sort
        'src'      => [ __( 'Src', 'pyro-script-audit' ),      false ],
        'ver'      => [ __( 'Version', 'pyro-script-audit' ),  false ],
        'deps'     => [ __( 'Dependencies', 'pyro-script-audit' ),false ], // Changed from "Dependants"
    ];

    if ($tab_slug === 'found') {
        $columns += [
            'footer'   => [ __( 'In Footer', 'pyro-script-audit' ),false ],
            'strategy' => [ __( 'Strategy', 'pyro-script-audit' ), false ],
            'size'     => [ __( 'Size', 'pyro-script-audit' ),     true  ],
            'mtime'    => [ __( 'Date Updated', 'pyro-script-audit' ), true  ],
            'date'     => [ __( 'Date Found', 'pyro-script-audit' ), true  ],
            // 'rules' column is not typically shown for 'found' scripts
        ];
    } elseif ($tab_slug === 'dequeued') {
        $columns += [
            'footer'   => [ __( 'In Footer', 'pyro-script-audit' ),false ],
            'strategy' => [ __( 'Strategy', 'pyro-script-audit' ), false ],
            'size'     => [ __( 'Size', 'pyro-script-audit' ),     true ],
            'mtime'    => [ __( 'Date Updated', 'pyro-script-audit' ), true ],
            'date'     => [ __( 'Date Dequeued', 'pyro-script-audit' ), true ],
            'rules'    => [ __( 'Dequeue Conditions', 'pyro-script-audit' ), false ], 
        ];
    } elseif ($tab_slug === 'manual') {
         $columns += [
            'footer'   => [ __( 'In Footer', 'pyro-script-audit' ),false ],
            'strategy' => [ __( 'Strategy', 'pyro-script-audit' ), false ],
            'rules'    => [ __( 'Enqueue Conditions', 'pyro-script-audit' ), false ], 
            'added_on' => [ __( 'Date Added', 'pyro-script-audit' ), true ],
        ];
    }
    return $columns;
}

/**
 * Helper function to determine the current tab slug, especially for screen options.
 * Needs to work both when displaying the page and when saving options via admin_init.
 *
 * @since 3.1.0
 * @return string The current tab slug ('found', 'dequeued', 'manual') or 'found' as default.
 */
function pyro_sa_get_current_tab_for_screen_options(): string {
    // Priority 1: Check $_GET['tab'] if we are on the correct admin page during page load.
    $current_screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($current_screen && $current_screen->id === 'tools_page_pyro-script-audit-log' && isset($_GET['tab'])) {
        $tab_slug = sanitize_key($_GET['tab']);
        if (in_array($tab_slug, ['found', 'dequeued', 'manual'], true)) {
            return $tab_slug;
        }
    }

    // Priority 2: Check $_POST['current_tab'] if it was submitted (e.g., from bulk actions form)
    if (isset($_POST['current_tab'])) {
        $tab_slug = sanitize_key($_POST['current_tab']);
         if (in_array($tab_slug, ['found', 'dequeued', 'manual'], true)) {
            return $tab_slug;
        }
    }
    
    // Priority 3: Check $_POST['wp_screen_options']['value'] when saving screen options
    if (isset($_POST['screenoptionnonce'], $_POST['wp_screen_options']['option'], $_POST['wp_screen_options']['value'])) {
        $tab_slug = sanitize_key(wp_unslash($_POST['wp_screen_options']['value']));
        if (in_array($tab_slug, ['found', 'dequeued', 'manual'], true)) {
            return $tab_slug;
        }
    }

    // Priority 4: Check the HTTP referer as a last resort when saving screen options,
    if (isset($_POST['screenoptionnonce'], $_POST['wp_screen_options']['option']) && ($referer = wp_get_referer())) {
        $query_args = [];
        $referer_query = wp_parse_url($referer, PHP_URL_QUERY);
        if ($referer_query) {
             wp_parse_str($referer_query, $query_args);
             if (isset($query_args['tab']) && in_array($query_args['tab'], ['found', 'dequeued', 'manual'], true)) {
                return sanitize_key($query_args['tab']);
             }
        }
    }
    return 'found'; // Fallback
}

/**
 * Retrieves the list of visible columns for a specific tab, based on user preference.
 *
 * @since 3.1.0
 * @param string $tab_slug The slug of the tab.
 * @return array An array of column keys that should be visible.
 */
function pyro_sa_visible_cols_for_tab(string $tab_slug): array {
    $all_for_tab = array_keys(pyro_sa_get_all_columns_for_tab($tab_slug));
    $option_name = PYRO_SA_OPT_PREFIX . 'cols_' . $tab_slug; // e.g., 'pyro_sa_cols_found'
    $user_saved_cols = get_user_option($option_name); 
    
    if ($user_saved_cols && is_array($user_saved_cols)) {
        return array_intersect($all_for_tab, $user_saved_cols);
    }
    return $all_for_tab; // Default: show all columns for the tab
}


// --- Helper functions for JS Localization (used in assets.php) ---

/**
 * Gets HTML <option> elements for all public post types.
 * @since 3.1.0
 * @return string HTML string of <option> tags.
 */
function pyro_sa_get_post_type_options_for_js(): string {
    $options = '';
    $post_types = get_post_types( ['public' => true], 'objects' );
    if ($post_types) {
        foreach ( $post_types as $pt ) {
            $options .= sprintf( '<option value="%s">%s</option>', esc_attr( $pt->name ), esc_html( $pt->labels->singular_name ) );
        }
    }
    return $options;
}

/**
 * Gets HTML <option> elements for all public taxonomies.
 * @since 3.1.0
 * @return string HTML string of <option> tags.
 */
function pyro_sa_get_taxonomy_options_for_js(): string {
    $options = '';
    $taxonomies = get_taxonomies( ['public' => true], 'objects' );
    if ($taxonomies) {
        foreach ( $taxonomies as $tax ) {
            $options .= sprintf( '<option value="%s">%s</option>', esc_attr( $tax->name ), esc_html( $tax->labels->singular_name ) );
        }
    }
    return $options;
}

/**
 * Gets HTML <option> elements for all page templates from the active theme.
 * @since 3.1.0
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