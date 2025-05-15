<?php
// uninstall.php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
// IMPORTANT: Check if the user has opted to remove data,
// perhaps via a setting you'll add later.
// $options = get_option('pyro_sa_settings');
// if ( empty( $options['delete_data_on_uninstall'] ) ) {
// return; // Don't delete if not explicitly requested
// }

delete_option( 'pyro_sa_found_scripts' );
delete_option( 'pyro_sa_dequeued_scripts' );
delete_option( 'pyro_sa_manual_scripts' );
delete_option( 'pyro_sa_version' );
// ... delete other options ...

// Delete user meta for column preferences
$users = get_users( [ 'fields' => 'ID' ] );
foreach ( $users as $user_id ) {
    delete_user_option( $user_id, 'pyro_sa_cols_found' );
    delete_user_option( $user_id, 'pyro_sa_cols_dequeued' );
    delete_user_option( $user_id, 'pyro_sa_cols_manual' );
}