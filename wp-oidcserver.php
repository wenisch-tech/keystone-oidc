<?php
/**
 * Plugin Name: WP OIDC Provider
 * Plugin URI: https://github.com/JFWenisch/wp-oidcserver
 * Description: Turn your WordPress site into an OpenID Connect (OIDC) identity provider. Manage clients through the admin panel.
 * Version: 1.0.0
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Author: JFWenisch
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-oidcprovider
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_OIDC_VERSION', '1.0.0' );
define( 'WP_OIDC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_OIDC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_OIDC_PLUGIN_FILE', __FILE__ );

require_once WP_OIDC_PLUGIN_DIR . 'includes/class-client-manager.php';
require_once WP_OIDC_PLUGIN_DIR . 'includes/class-token-manager.php';
require_once WP_OIDC_PLUGIN_DIR . 'includes/class-oidc-server.php';

if ( is_admin() ) {
	require_once WP_OIDC_PLUGIN_DIR . 'admin/class-admin.php';
	new WP_OIDC_Admin();
}

register_activation_hook( __FILE__, array( 'WP_OIDC_Provider', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WP_OIDC_Provider', 'deactivate' ) );

$wp_oidc_provider = new WP_OIDC_Provider();
