<?php
/**
 * Uninstall: removes plugin data only when the site opted in (Settings → Data).
 *
 * @package FavrDirectory
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$favr_settings = get_option( 'favr_directory_settings', array() );

if ( is_array( $favr_settings ) && ! empty( $favr_settings['delete_data'] ) && '0' !== $favr_settings['delete_data'] ) {
	global $wpdb;

	// Businesses (and their meta, via wp_delete_post).
	$favr_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'favr_business' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( $favr_ids as $favr_id ) {
		wp_delete_post( (int) $favr_id, true );
	}

	// Terms of our taxonomies (registered ad hoc so term APIs work during uninstall).
	foreach ( array( 'favr_business_cat', 'favr_member_level' ) as $favr_taxonomy ) {
		register_taxonomy( $favr_taxonomy, 'favr_business' );
		$favr_terms = get_terms(
			array(
				'taxonomy'   => $favr_taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( is_array( $favr_terms ) ) {
			foreach ( $favr_terms as $favr_term ) {
				wp_delete_term( (int) $favr_term, $favr_taxonomy );
			}
		}
	}

	delete_option( 'favr_directory_settings' );
	delete_option( 'favr_directory_version' );
	delete_option( 'favr_directory_flush_rewrite' );

	// Capabilities and the Directory Manager role.
	$favr_caps = array(
		'edit_favr_businesses',
		'edit_others_favr_businesses',
		'edit_private_favr_businesses',
		'edit_published_favr_businesses',
		'publish_favr_businesses',
		'read_private_favr_businesses',
		'delete_favr_businesses',
		'delete_others_favr_businesses',
		'delete_private_favr_businesses',
		'delete_published_favr_businesses',
		'manage_favr_business_terms',
		'manage_favr_directory',
	);
	foreach ( wp_roles()->role_objects as $favr_role ) {
		foreach ( $favr_caps as $favr_cap ) {
			$favr_role->remove_cap( $favr_cap );
		}
	}
	remove_role( 'favr_directory_manager' );
}
