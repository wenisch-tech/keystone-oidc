<?php
/**
 * OIDC Provider Admin
 *
 * Registers the admin menu and handles client management UI.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KEYSTONE_OIDC_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_keystone_oidc_save_client', array( $this, 'handle_save_client' ) );
		add_action( 'admin_post_keystone_oidc_delete_client', array( $this, 'handle_delete_client' ) );
		add_action( 'admin_post_keystone_oidc_reset_secret', array( $this, 'handle_reset_secret' ) );
		add_action( 'admin_post_keystone_oidc_rotate_keys', array( $this, 'handle_rotate_keys' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( KEYSTONE_OIDC_PLUGIN_FILE ), array( $this, 'add_plugin_action_links' ) );
	}

	// -------------------------------------------------------------------------
	// Plugin action links
	// -------------------------------------------------------------------------

	/**
	 * Add Settings link to plugin action links on plugins page.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array
	 */
	public function add_plugin_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=wp-oidc-settings' ) ),
			esc_html__( 'Settings', 'keystone-oidc' )
		);
		return array_merge( array( $settings_link ), $links );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	public function register_menu() {
		add_menu_page(
			__( 'OIDC Provider', 'keystone-oidc' ),
			__( 'OIDC Provider', 'keystone-oidc' ),
			'manage_options',
			'wp-oidc-clients',
			array( $this, 'page_clients' ),
			'dashicons-shield',
			81
		);

		add_submenu_page(
			'wp-oidc-clients',
			__( 'Clients', 'keystone-oidc' ),
			__( 'Clients', 'keystone-oidc' ),
			'manage_options',
			'wp-oidc-clients',
			array( $this, 'page_clients' )
		);

		add_submenu_page(
			'wp-oidc-clients',
			__( 'Add Client', 'keystone-oidc' ),
			__( 'Add Client', 'keystone-oidc' ),
			'manage_options',
			'wp-oidc-add-client',
			array( $this, 'page_add_client' )
		);

		add_submenu_page(
			'wp-oidc-clients',
			__( 'Settings', 'keystone-oidc' ),
			__( 'Settings', 'keystone-oidc' ),
			'manage_options',
			'wp-oidc-settings',
			array( $this, 'page_settings' )
		);
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	public function enqueue_assets( $hook ) {
		$oidc_pages = array(
			'toplevel_page_wp-oidc-clients',
			'oidc-server_page_wp-oidc-add-client',
			'oidc-server_page_wp-oidc-settings',
		);

		if ( ! in_array( $hook, $oidc_pages, true ) && false === strpos( $hook, 'wp-oidc' ) ) {
			return;
		}

		wp_enqueue_style( 'keystone-oidc-admin', KEYSTONE_OIDC_PLUGIN_URL . 'admin/css/admin.css', array(), KEYSTONE_OIDC_VERSION );

		wp_register_script( 'keystone-oidc-admin', KEYSTONE_OIDC_PLUGIN_URL . 'admin/js/keystone-oidc-admin.js', array(), KEYSTONE_OIDC_VERSION, true );
		wp_localize_script(
			'keystone-oidc-admin',
			'keystoneOidc',
			array(
				'copied' => __( 'Copied!', 'keystone-oidc' ),
			)
		);
		wp_enqueue_script( 'keystone-oidc-admin' );
	}

	// -------------------------------------------------------------------------
	// Pages
	// -------------------------------------------------------------------------

	public function page_clients() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'keystone-oidc' ) );
		}

		// Handle edit sub-page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$client_id = isset( $_GET['client_id'] ) ? sanitize_text_field( wp_unslash( $_GET['client_id'] ) ) : '';
		if ( $client_id ) {
			$client = KEYSTONE_OIDC_Client_Manager::get_client( $client_id );
			if ( $client ) {
				include KEYSTONE_OIDC_PLUGIN_DIR . 'admin/views/page-client-edit.php';
				return;
			}
		}

		$clients = KEYSTONE_OIDC_Client_Manager::get_all_clients();
		include KEYSTONE_OIDC_PLUGIN_DIR . 'admin/views/page-clients.php';
	}

	public function page_add_client() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'keystone-oidc' ) );
		}
		$client = null;
		include KEYSTONE_OIDC_PLUGIN_DIR . 'admin/views/page-client-edit.php';
	}

	public function page_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'keystone-oidc' ) );
		}
		include KEYSTONE_OIDC_PLUGIN_DIR . 'admin/views/page-settings.php';
	}

	// -------------------------------------------------------------------------
	// Handlers
	// -------------------------------------------------------------------------

	public function handle_save_client() {
		check_admin_referer( 'keystone_oidc_save_client' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'keystone-oidc' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$client_id     = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
		$name          = isset( $_POST['client_name'] ) ? sanitize_text_field( wp_unslash( $_POST['client_name'] ) ) : '';
		$redirect_uris = isset( $_POST['redirect_uris'] ) ? sanitize_textarea_field( wp_unslash( $_POST['redirect_uris'] ) ) : '';
		$scopes        = isset( $_POST['allowed_scopes'] ) ? sanitize_text_field( wp_unslash( $_POST['allowed_scopes'] ) ) : 'openid profile email';
		// phpcs:enable

		if ( empty( $name ) || empty( $redirect_uris ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => $client_id ? 'wp-oidc-clients' : 'wp-oidc-add-client',
						'client_id' => $client_id,
						'error'   => 'missing_fields',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$uris = array_filter( array_map( 'trim', explode( "\n", $redirect_uris ) ) );

		if ( $client_id ) {
			// Update existing.
			$result = KEYSTONE_OIDC_Client_Manager::update_client( $client_id, $name, $uris, $scopes );
			if ( is_wp_error( $result ) ) {
				wp_safe_redirect( add_query_arg( array( 'page' => 'wp-oidc-clients', 'client_id' => $client_id, 'error' => 'db_error' ), admin_url( 'admin.php' ) ) );
				exit;
			}
			wp_safe_redirect( add_query_arg( array( 'page' => 'wp-oidc-clients', 'client_id' => $client_id, 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		} else {
			// Create new.
			$result = KEYSTONE_OIDC_Client_Manager::create_client( $name, $uris, $scopes );
			if ( is_wp_error( $result ) ) {
				wp_safe_redirect( add_query_arg( array( 'page' => 'wp-oidc-add-client', 'error' => 'db_error' ), admin_url( 'admin.php' ) ) );
				exit;
			}

			// Store plaintext secret in a transient for one-time display.
			set_transient( 'keystone_oidc_new_secret_' . $result['client_id'], $result['client_secret'], 300 );

			wp_safe_redirect(
				add_query_arg(
					array(
						'page'      => 'wp-oidc-clients',
						'client_id' => $result['client_id'],
						'created'   => '1',
					),
					admin_url( 'admin.php' )
				)
			);
		}
		exit;
	}

	public function handle_delete_client() {
		check_admin_referer( 'keystone_oidc_delete_client' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'keystone-oidc' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$client_id = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
		KEYSTONE_OIDC_Client_Manager::delete_client( $client_id );

		wp_safe_redirect( add_query_arg( array( 'page' => 'wp-oidc-clients', 'deleted' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_reset_secret() {
		check_admin_referer( 'keystone_oidc_reset_secret' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'keystone-oidc' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$client_id = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
		$result    = KEYSTONE_OIDC_Client_Manager::reset_secret( $client_id );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'wp-oidc-clients', 'client_id' => $client_id, 'error' => 'reset_failed' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// Store new plaintext secret in a transient for one-time display.
		set_transient( 'keystone_oidc_new_secret_' . $client_id, $result, 300 );

		wp_safe_redirect( add_query_arg( array( 'page' => 'wp-oidc-clients', 'client_id' => $client_id, 'secret_reset' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_rotate_keys() {
		check_admin_referer( 'keystone_oidc_rotate_keys' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'keystone-oidc' ) );
		}

		$result = KEYSTONE_OIDC_Token_Manager::generate_keys();
		$status = $result ? 'keys_rotated' : 'key_error';

		wp_safe_redirect( add_query_arg( array( 'page' => 'wp-oidc-settings', $status => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
