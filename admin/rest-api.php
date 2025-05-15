<?php
/**
 * Pyro Script Audit - REST API Endpoints
 *
 * This file registers custom REST API routes and their callback functions
 * for managing script rules and manually added scripts.
 *
 * @package Pyro_Script_Audit
 * @since   3.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers all custom REST API routes for the Pyro Script Audit plugin.
 *
 * Hooked to 'rest_api_init'.
 *
 * @since 3.1.0
 */
function pyro_sa_register_rest_api_routes() {
    $namespace = 'pyro-sa/v1'; // Consistent namespace

    // --- Rules for DEQUEUED Scripts ---
    register_rest_route( $namespace, '/rules/scripts/(?P<handle>[^/]+)', [ // Added /scripts/ for clarity
        'methods'  => [ WP_REST_Server::READABLE, WP_REST_Server::EDITABLE, WP_REST_Server::DELETABLE ], // GET, POST/PUT, DELETE
        'callback' => 'pyro_sa_rest_handle_dequeued_script_rules',
        'permission_callback' => 'pyro_sa_can_manage', // From utils.php
        'args'     => [
            'handle' => [
                'required' => true,
                'type'     => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function( $param, $request, $key ) {
                    return ! empty( $param );
                }
            ],
        ],
    ] );

    // --- CRUD for MANUALLY ADDED Scripts ---
    register_rest_route( $namespace, '/manual-scripts', [
        [ // GET all manually added scripts
            'methods'  => WP_REST_Server::READABLE,
            'callback' => 'pyro_sa_rest_get_all_manual_scripts',
            'permission_callback' => 'pyro_sa_can_manage',
        ],
        [ // POST to add a new manual script
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => 'pyro_sa_rest_add_manual_script',
            'permission_callback' => 'pyro_sa_can_manage',
            'args'     => pyro_sa_get_manual_script_args_schema( true ), // Pass true for 'add' context
        ],
    ] );

    register_rest_route( $namespace, '/manual-scripts/(?P<handle>[a-zA-Z0-9_-]+)', [
        [ // GET a single manually added script
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'pyro_sa_rest_get_single_manual_script',
            'permission_callback' => 'pyro_sa_can_manage',
            'args' => [ 'handle' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ] ],
        ],
        [ // POST/PUT to update an existing manual script
            'methods'  => WP_REST_Server::EDITABLE, // Covers POST, PUT, PATCH
            'callback' => 'pyro_sa_rest_update_manual_script',
            'permission_callback' => 'pyro_sa_can_manage',
            'args'     => pyro_sa_get_manual_script_args_schema( false ), // Pass false for 'edit' context
        ],
        [ // DELETE a manually added script
            'methods'  => WP_REST_Server::DELETABLE,
            'callback' => 'pyro_sa_rest_delete_manual_script',
            'permission_callback' => 'pyro_sa_can_manage',
            'args'     => [ 'handle' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ] ],
        ],
    ] );

    // --- Rules for MANUALLY ADDED Scripts ---
    register_rest_route( $namespace, '/manual-rules/scripts/(?P<handle>[^/]+)', [ // Added /scripts/
        'methods'  => [ WP_REST_Server::READABLE, WP_REST_Server::EDITABLE, WP_REST_Server::DELETABLE ],
        'callback' => 'pyro_sa_rest_handle_manual_script_rules',
        'permission_callback' => 'pyro_sa_can_manage',
        'args'     => [
            'handle' => [
                'required' => true,
                'type'     => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
    ] );
}
add_action( 'rest_api_init', 'pyro_sa_register_rest_api_routes' );


/**
 * Defines the arguments schema for manual script creation/updates.
 *
 * @param bool $is_creating True if for creation (handle is required in body), false for update (handle from URL).
 * @return array The arguments schema.
 */
function pyro_sa_get_manual_script_args_schema( bool $is_creating = true ): array {
    $args = [
        'src'       => [ 'required' => true,  'type' => 'string', 'format' => 'url', 'sanitize_callback' => 'esc_url_raw' ],
        'ver'       => [ 'type' => 'string', 'default' => null, 'sanitize_callback' => 'sanitize_text_field' ],
        'in_footer' => [ 'type' => 'boolean', 'default' => false, 'sanitize_callback' => 'wp_validate_boolean' ],
        'strategy'  => [ 'type' => 'string', 'default' => 'none', 'enum' => ['none', 'async', 'defer'], 'sanitize_callback' => 'sanitize_key' ],
        'deps'      => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ], // Comma-separated string
    ];
    if ($is_creating) {
        $args['handle'] = [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ];
    } else {
        // For updates, handle is part of the URL, not body args necessarily, but can be validated from request object.
        // The 'handle' in register_rest_route for the update endpoint already covers this.
    }
    return $args;
}


// -----------------------------------------------------------------------------
// REST API Callback Functions
// -----------------------------------------------------------------------------

/**
 * Callback for managing rules for DEQUEUED scripts.
 * Handles GET (retrieve), POST (update), DELETE (clear) rules.
 *
 * @param WP_REST_Request $request The REST API request object.
 * @return WP_REST_Response|WP_Error Response object or error.
 */
function pyro_sa_rest_handle_dequeued_script_rules( WP_REST_Request $request ) {
    $handle = $request['handle']; // Already sanitized by 'args' in route definition
    $dequeued_scripts = get_option( PYRO_SA_DEQUEUED_SCRIPTS_OPT, [] );

    if ( ! isset( $dequeued_scripts[ $handle ] ) ) {
        return new WP_Error( 'not_found', __( 'Handle not found in dequeued scripts list.', 'pyro-script-audit' ), [ 'status' => 404 ] );
    }

    switch ( $request->get_method() ) {
        case 'GET':
            return new WP_REST_Response( $dequeued_scripts[ $handle ]['rules'] ?? [], 200 );

        case 'POST': // Also covers PUT/PATCH if methods defined as EDITABLE
            $rules_from_request = $request->get_json_params();
            if ( $rules_from_request === null && json_last_error() !== JSON_ERROR_NONE ) {
                return new WP_Error( 'invalid_json', __( 'Invalid JSON data provided for rules.', 'pyro-script-audit' ), [ 'status' => 400 ] );
            }
            $dequeued_scripts[ $handle ]['rules'] = (array) $rules_from_request;
            update_option( PYRO_SA_DEQUEUED_SCRIPTS_OPT, $dequeued_scripts, false );
            return new WP_REST_Response( $dequeued_scripts[ $handle ]['rules'], 200 );

        case 'DELETE':
            unset( $dequeued_scripts[ $handle ]['rules'] );
            update_option( PYRO_SA_DEQUEUED_SCRIPTS_OPT, $dequeued_scripts, false );
            return new WP_REST_Response( [], 200 ); // Return empty array, indicating rules cleared
    }
    // Should not be reached if methods are correctly defined in route
    return new WP_Error( 'invalid_method', __( 'Method not supported for this endpoint.', 'pyro-script-audit' ), [ 'status' => 405 ] );
}


/**
 * Callback for managing rules for MANUALLY ADDED scripts.
 * Handles GET (retrieve), POST (update), DELETE (clear) rules.
 *
 * @param WP_REST_Request $request The REST API request object.
 * @return WP_REST_Response|WP_Error Response object or error.
 */
function pyro_sa_rest_handle_manual_script_rules( WP_REST_Request $request ) {
    $handle = $request['handle']; // Already sanitized
    $manual_scripts = get_option( PYRO_SA_MANUAL_SCRIPTS_OPT, [] );

    if ( ! isset( $manual_scripts[ $handle ] ) ) {
        return new WP_Error( 'not_found', __( 'Handle not found in manually added scripts list.', 'pyro-script-audit' ), [ 'status' => 404 ] );
    }

    switch ( $request->get_method() ) {
        case 'GET':
            return new WP_REST_Response( $manual_scripts[ $handle ]['rules'] ?? [], 200 );
        case 'POST':
            $rules_from_request = $request->get_json_params();
            if ( $rules_from_request === null && json_last_error() !== JSON_ERROR_NONE ) {
                return new WP_Error( 'invalid_json', __( 'Invalid JSON data provided for manual script rules.', 'pyro-script-audit' ), [ 'status' => 400 ] );
            }
            $manual_scripts[ $handle ]['rules'] = (array) $rules_from_request;
            update_option( PYRO_SA_MANUAL_SCRIPTS_OPT, $manual_scripts, false );
            return new WP_REST_Response( $manual_scripts[ $handle ]['rules'], 200 );
        case 'DELETE':
            unset( $manual_scripts[ $handle ]['rules'] );
            update_option( PYRO_SA_MANUAL_SCRIPTS_OPT, $manual_scripts, false );
            return new WP_REST_Response( [], 200 );
    }
    return new WP_Error( 'invalid_method', __( 'Method not supported for this endpoint.', 'pyro-script-audit' ), [ 'status' => 405 ] );
}


/**
 * REST callback: Get all manually added scripts.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function pyro_sa_rest_get_all_manual_scripts( WP_REST_Request $request ) {
    return new WP_REST_Response( get_option( PYRO_SA_MANUAL_SCRIPTS_OPT, [] ), 200 );
}

/**
 * REST callback: Get a single manually added script by handle.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function pyro_sa_rest_get_single_manual_script( WP_REST_Request $request ) {
    $handle = $request['handle']; // Sanitized by route arg definition
    $scripts = get_option( PYRO_SA_MANUAL_SCRIPTS_OPT, [] );
    if ( isset( $scripts[ $handle ] ) ) {
        return new WP_REST_Response( $scripts[ $handle ], 200 );
    }
    return new WP_Error( 'not_found', __( 'Manual script not found.', 'pyro-script-audit' ), [ 'status' => 404 ] );
}

/**
 * REST callback: Add a new manual script.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function pyro_sa_rest_add_manual_script( WP_REST_Request $request ) {
    $params = $request->get_params(); // Parameters are already sanitized by 'args' in route definition
    $handle = $params['handle'];
    $src    = $params['src'];
    
    $scripts = get_option( PYRO_SA_MANUAL_SCRIPTS_OPT, [] );

    // Additional validation (handle uniqueness across WP and plugin)
    if ( isset( $scripts[ $handle ] ) || wp_script_is( $handle, 'registered' ) || wp_script_is( $handle, 'enqueued' ) ) {
        return new WP_Error( 'duplicate_handle', __( 'Script handle already exists or is registered by WordPress/theme/another plugin.', 'pyro-script-audit' ), [ 'status' => 409 ] );
    }

    $new_script_data = [
        'src'       => $src,
        'ver'       => $params['ver'] ?? null,
        'in_footer' => $params['in_footer'] ?? false,
        'strategy'  => $params['strategy'] ?? 'none',
        'deps'      => $params['deps'] ?? '',
        'rules'     => [], // Initialize with empty rules
        'added_on'  => time(),
    ];
    $scripts[ $handle ] = $new_script_data;
    update_option( PYRO_SA_MANUAL_SCRIPTS_OPT, $scripts, false );
    return new WP_REST_Response( $new_script_data, 201 ); // 201 Created
}

/**
 * REST callback: Update an existing manual script.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function pyro_sa_rest_update_manual_script( WP_REST_Request $request ) {
    $handle_from_url = $request['handle']; // From URL, sanitized by route arg
    $params = $request->get_params();      // Body parameters, sanitized by route args

    $scripts = get_option( PYRO_SA_MANUAL_SCRIPTS_OPT, [] );

    if ( ! isset( $scripts[ $handle_from_url ] ) ) {
        return new WP_Error( 'not_found', __( 'Manual script handle not found for update.', 'pyro-script-audit' ), [ 'status' => 404 ] );
    }
    // Src is required for update as well (from args schema)
    if ( empty( $params['src'] ) ) {
         return new WP_Error( 'missing_src', __( 'Source URL is required for update.', 'pyro-script-audit' ), [ 'status' => 400 ] );
    }

    // Preserve existing 'rules' and 'added_on', only update specified fields.
    $scripts[ $handle_from_url ] = [
        'src'       => $params['src'],
        'ver'       => $params['ver'] ?? $scripts[ $handle_from_url ]['ver'],
        'in_footer' => $params['in_footer'] ?? $scripts[ $handle_from_url ]['in_footer'],
        'strategy'  => $params['strategy'] ?? $scripts[ $handle_from_url ]['strategy'],
        'deps'      => $params['deps'] ?? $scripts[ $handle_from_url ]['deps'],
        'rules'     => $scripts[ $handle_from_url ]['rules'] ?? [], // Rules are managed by their own endpoint
        'added_on'  => $scripts[ $handle_from_url ]['added_on'] ?? time(), // Preserve original, or set if somehow missing
        'updated_on'=> time(),
    ];
    update_option( PYRO_SA_MANUAL_SCRIPTS_OPT, $scripts, false );
    return new WP_REST_Response( $scripts[ $handle_from_url ], 200 );
}

/**
 * REST callback: Delete a manual script.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function pyro_sa_rest_delete_manual_script( WP_REST_Request $request ) {
    $handle  = $request['handle']; // From URL, sanitized by route arg
    $scripts = get_option( PYRO_SA_MANUAL_SCRIPTS_OPT, [] );

    if ( ! isset( $scripts[ $handle ] ) ) {
        return new WP_Error( 'not_found', __( 'Manual script handle not found for deletion.', 'pyro-script-audit' ), [ 'status' => 404 ] );
    }

    unset( $scripts[ $handle ] );
    update_option( PYRO_SA_MANUAL_SCRIPTS_OPT, $scripts, false );
    return new WP_REST_Response( [ 'message' => __( 'Manual script deleted successfully.', 'pyro-script-audit' ), 'handle' => $handle ], 200 );
}

?>