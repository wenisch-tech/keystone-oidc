<?php
/**
 * Plugin Name: Keystone OIDC
 * Plugin URI: https://github.com/wenisch-tech/keystone-oidc
 * Description: Turn your WordPress site into an OpenID Connect (OIDC) identity provider. Manage clients through the admin panel.
 * Version: 1.0.0
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Author: Jean-Fabian Wenisch
 * Author URI: https://wenisch.tech
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: keystone-oidc
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KEYSTONE_OIDC_VERSION', '1.0.0' );
define( 'KEYSTONE_OIDC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KEYSTONE_OIDC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KEYSTONE_OIDC_PLUGIN_FILE', __FILE__ );

require_once KEYSTONE_OIDC_PLUGIN_DIR . 'includes/class-client-manager.php';
require_once KEYSTONE_OIDC_PLUGIN_DIR . 'includes/class-token-manager.php';
require_once KEYSTONE_OIDC_PLUGIN_DIR . 'includes/class-oidc-provider.php';

if ( is_admin() ) {
	require_once KEYSTONE_OIDC_PLUGIN_DIR . 'admin/class-admin.php';
	new KEYSTONE_OIDC_Admin();
}

register_activation_hook( __FILE__, array( 'KEYSTONE_OIDC_Provider', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'KEYSTONE_OIDC_Provider', 'deactivate' ) );

$KEYSTONE_OIDC_Provider = new KEYSTONE_OIDC_Provider();
