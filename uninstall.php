<?php
/**
 * Uninstall WP OIDC Server
 *
 * This file runs when the plugin is uninstalled (deleted) from WordPress.
 * It removes all plugin data: database tables and options.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-client-manager.php';

WP_OIDC_Client_Manager::drop_tables();

delete_option( 'wp_oidc_private_key' );
delete_option( 'wp_oidc_public_key' );
delete_option( 'wp_oidc_key_id' );
delete_option( 'wp_oidc_issuer' );
