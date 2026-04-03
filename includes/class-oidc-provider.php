<?php
/**
 * OIDC Provider - Main Class
 *
 * Registers rewrite rules, handles activation/deactivation,
 * and dispatches OIDC endpoint requests.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_OIDC_Provider {
	const ENDPOINT_BASE_PATH = 'wenisch-tech/wp-oidcprovider';

	/**
	 * Get endpoint base path relative to site root.
	 *
	 * @return string
	 */
	public static function get_endpoint_base_path() {
		return trailingslashit( self::ENDPOINT_BASE_PATH );
	}

	/**
	 * Build an absolute endpoint URL.
	 *
	 * @param string $relative_path Endpoint path relative to the endpoint base path.
	 * @return string
	 */
	public static function get_endpoint_url( $relative_path ) {
		$site_base = trailingslashit( get_bloginfo( 'url' ) );
		return $site_base . self::get_endpoint_base_path() . ltrim( $relative_path, '/' );
	}

	public function __construct() {
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_request' ) );
		add_action( 'wp_scheduled_delete', array( 'WP_OIDC_Token_Manager', 'cleanup_expired' ) );
	}

	/**
	 * Plugin activation: create tables, generate keys, flush rewrite rules.
	 */
	public static function activate() {
		WP_OIDC_Client_Manager::create_tables();
		if ( ! get_option( WP_OIDC_Token_Manager::OPTION_PRIVATE_KEY ) ) {
			WP_OIDC_Token_Manager::generate_keys();
		}
		self::register_rewrite_rules_static();
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation: flush rewrite rules.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Register OIDC rewrite rules (instance method for 'init' hook).
	 */
	public function register_rewrite_rules() {
		self::register_rewrite_rules_static();
	}

	/**
	 * Register OIDC rewrite rules (static, so activation can call it too).
	 */
	public static function register_rewrite_rules_static() {
		$endpoint_base = untrailingslashit( self::get_endpoint_base_path() );

		add_rewrite_rule( '^' . $endpoint_base . '/\.well-known/openid-configuration/?$', 'index.php?oidc_endpoint=discovery', 'top' );
		add_rewrite_rule( '^' . $endpoint_base . '/oauth/authorize/?$', 'index.php?oidc_endpoint=authorize', 'top' );
		add_rewrite_rule( '^' . $endpoint_base . '/oauth/token/?$', 'index.php?oidc_endpoint=token', 'top' );
		add_rewrite_rule( '^' . $endpoint_base . '/oauth/userinfo/?$', 'index.php?oidc_endpoint=userinfo', 'top' );
		add_rewrite_rule( '^' . $endpoint_base . '/oauth/jwks/?$', 'index.php?oidc_endpoint=jwks', 'top' );
	}

	/**
	 * Register the custom query variable.
	 *
	 * @param array $vars Existing query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'oidc_endpoint';
		return $vars;
	}

	/**
	 * Route requests to the appropriate handler.
	 */
	public function handle_request() {
		$endpoint = get_query_var( 'oidc_endpoint' );
		if ( ! $endpoint ) {
			return;
		}

		switch ( $endpoint ) {
			case 'discovery':
				$this->handle_discovery();
				break;
			case 'authorize':
				$this->handle_authorize();
				break;
			case 'token':
				$this->handle_token();
				break;
			case 'userinfo':
				$this->handle_userinfo();
				break;
			case 'jwks':
				$this->handle_jwks();
				break;
			default:
				$this->send_json_error( array( 'error' => 'not_found' ), 404 );
		}
	}

	// -------------------------------------------------------------------------
	// Discovery
	// -------------------------------------------------------------------------

	private function handle_discovery() {
		$issuer = WP_OIDC_Token_Manager::get_issuer();

		$discovery = array(
			'issuer'                                => $issuer,
			'authorization_endpoint'                => self::get_endpoint_url( 'oauth/authorize' ),
			'token_endpoint'                        => self::get_endpoint_url( 'oauth/token' ),
			'userinfo_endpoint'                     => self::get_endpoint_url( 'oauth/userinfo' ),
			'jwks_uri'                              => self::get_endpoint_url( 'oauth/jwks' ),
			'scopes_supported'                      => array( 'openid', 'profile', 'email' ),
			'response_types_supported'              => array( 'code' ),
			'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
			'subject_types_supported'               => array( 'public' ),
			'id_token_signing_alg_values_supported' => array( 'RS256' ),
			'token_endpoint_auth_methods_supported' => array( 'client_secret_basic', 'client_secret_post' ),
			'claims_supported'                      => array( 'sub', 'iss', 'aud', 'iat', 'exp', 'name', 'given_name', 'family_name', 'preferred_username', 'email', 'email_verified' ),
			'code_challenge_methods_supported'      => array( 'S256', 'plain' ),
		);

		$this->send_json( $discovery );
	}

	// -------------------------------------------------------------------------
	// Authorization Endpoint
	// -------------------------------------------------------------------------

	private function handle_authorize() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$response_type         = isset( $_GET['response_type'] ) ? sanitize_text_field( wp_unslash( $_GET['response_type'] ) ) : '';
		$client_id             = isset( $_GET['client_id'] ) ? sanitize_text_field( wp_unslash( $_GET['client_id'] ) ) : '';
		$redirect_uri          = isset( $_GET['redirect_uri'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_uri'] ) ) : '';
		$scope                 = isset( $_GET['scope'] ) ? sanitize_text_field( wp_unslash( $_GET['scope'] ) ) : 'openid';
		$state                 = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$nonce                 = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : null;
		$code_challenge        = isset( $_GET['code_challenge'] ) ? sanitize_text_field( wp_unslash( $_GET['code_challenge'] ) ) : null;
		$code_challenge_method = isset( $_GET['code_challenge_method'] ) ? sanitize_text_field( wp_unslash( $_GET['code_challenge_method'] ) ) : null;
		// phpcs:enable

		if ( 'code' !== $response_type ) {
			$this->redirect_with_error( $redirect_uri, 'unsupported_response_type', 'Only "code" response_type is supported.', $state );
			return;
		}

		$client = WP_OIDC_Client_Manager::get_client( $client_id );
		if ( ! $client ) {
			$this->send_json_error( array( 'error' => 'invalid_client', 'error_description' => 'Unknown client.' ), 400 );
			return;
		}

		if ( ! WP_OIDC_Client_Manager::validate_redirect_uri( $client_id, $redirect_uri ) ) {
			$this->send_json_error( array( 'error' => 'invalid_request', 'error_description' => 'Invalid redirect_uri.' ), 400 );
			return;
		}

		// Ensure openid scope is present.
		$requested_scopes = explode( ' ', $scope );
		if ( ! in_array( 'openid', $requested_scopes, true ) ) {
			$this->redirect_with_error( $redirect_uri, 'invalid_scope', 'The "openid" scope is required.', $state );
			return;
		}

		// Require user to be logged in.
		if ( ! is_user_logged_in() ) {
			$authorize_url = add_query_arg( $_GET, self::get_endpoint_url( 'oauth/authorize' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( wp_login_url( $authorize_url ) );
			exit;
		}

		// Handle POST (form submit for consent).
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$this->handle_authorize_post( $client_id, $redirect_uri, $scope, $state, $nonce, $code_challenge, $code_challenge_method );
			return;
		}

		// Show consent page.
		$this->render_consent_page( $client, $scope, $state, $nonce, $redirect_uri, $code_challenge, $code_challenge_method );
	}

	private function handle_authorize_post( $client_id, $redirect_uri, $scope, $state, $nonce, $code_challenge, $code_challenge_method ) {
		check_admin_referer( 'oidc_authorize' );

		$user_id = get_current_user_id();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['authorize'] ) && 'deny' === sanitize_text_field( wp_unslash( $_POST['authorize'] ) ) ) {
			$this->redirect_with_error( $redirect_uri, 'access_denied', 'User denied authorization.', $state );
			return;
		}

		$code = WP_OIDC_Token_Manager::generate_auth_code( $client_id, $user_id, $redirect_uri, $scope, $nonce, $code_challenge, $code_challenge_method );

		$params = array( 'code' => $code );
		if ( $state ) {
			$params['state'] = $state;
		}

		wp_safe_redirect( add_query_arg( $params, $redirect_uri ) );
		exit;
	}

	private function render_consent_page( $client, $scope, $state, $nonce, $redirect_uri, $code_challenge, $code_challenge_method ) {
		$user        = wp_get_current_user();
		$plugin_url  = WP_OIDC_PLUGIN_URL;
		$scope_labels = array(
			'openid'  => __( 'Verify your identity', 'wp-oidcprovider' ),
			'profile' => __( 'Access your name and username', 'wp-oidcprovider' ),
			'email'   => __( 'Access your email address', 'wp-oidcprovider' ),
		);
		$requested_scopes = explode( ' ', $scope );
		include WP_OIDC_PLUGIN_DIR . 'includes/views/consent.php';
		exit;
	}

	// -------------------------------------------------------------------------
	// Token Endpoint
	// -------------------------------------------------------------------------

	private function handle_token() {
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			$this->send_json_error( array( 'error' => 'invalid_request', 'error_description' => 'POST required.' ), 405 );
			return;
		}

		// Client authentication: HTTP Basic or POST body.
		$client_id     = '';
		$client_secret = '';

		$auth_header = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) ) : '';
		if ( $auth_header && 0 === strpos( $auth_header, 'Basic ' ) ) {
			$decoded = base64_decode( substr( $auth_header, 6 ) );
			if ( false !== $decoded && strpos( $decoded, ':' ) !== false ) {
				list( $client_id, $client_secret ) = explode( ':', $decoded, 2 );
			}
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! $client_id ) {
			$client_id     = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
			$client_secret = isset( $_POST['client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ) : '';
		}

		$grant_type    = isset( $_POST['grant_type'] ) ? sanitize_text_field( wp_unslash( $_POST['grant_type'] ) ) : '';
		$code          = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		$redirect_uri  = isset( $_POST['redirect_uri'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_uri'] ) ) : '';
		$refresh_token = isset( $_POST['refresh_token'] ) ? sanitize_text_field( wp_unslash( $_POST['refresh_token'] ) ) : '';
		$code_verifier = isset( $_POST['code_verifier'] ) ? sanitize_text_field( wp_unslash( $_POST['code_verifier'] ) ) : null;
		// phpcs:enable

		$client = WP_OIDC_Client_Manager::get_client( $client_id );
		if ( ! $client ) {
			$this->send_json_error( array( 'error' => 'invalid_client', 'error_description' => 'Unknown client.' ), 401 );
			return;
		}

		// Verify client secret (only when provided — public clients may use PKCE without secret).
		if ( $client_secret ) {
			if ( ! WP_OIDC_Client_Manager::verify_secret( $client_secret, $client->client_secret ) ) {
				$this->send_json_error( array( 'error' => 'invalid_client', 'error_description' => 'Invalid client credentials.' ), 401 );
				return;
			}
		}

		switch ( $grant_type ) {
			case 'authorization_code':
				$this->handle_auth_code_grant( $client_id, $code, $redirect_uri, $code_verifier );
				break;
			case 'refresh_token':
				$this->handle_refresh_token_grant( $client_id, $refresh_token );
				break;
			default:
				$this->send_json_error( array( 'error' => 'unsupported_grant_type' ), 400 );
		}
	}

	private function handle_auth_code_grant( $client_id, $code, $redirect_uri, $code_verifier ) {
		$auth_code = WP_OIDC_Token_Manager::consume_auth_code( $code, $client_id, $redirect_uri, $code_verifier );
		if ( is_wp_error( $auth_code ) ) {
			$this->send_json_error( array( 'error' => $auth_code->get_error_code(), 'error_description' => $auth_code->get_error_message() ), 400 );
			return;
		}

		$tokens = WP_OIDC_Token_Manager::issue_tokens( $client_id, $auth_code->user_id, $auth_code->scope, $auth_code->nonce );

		$this->send_json(
			array(
				'access_token'  => $tokens['access_token'],
				'token_type'    => 'Bearer',
				'expires_in'    => $tokens['expires_in'],
				'id_token'      => $tokens['id_token'],
				'refresh_token' => $tokens['refresh_token'],
				'scope'         => $auth_code->scope,
			)
		);
	}

	private function handle_refresh_token_grant( $client_id, $refresh_token ) {
		if ( ! $refresh_token ) {
			$this->send_json_error( array( 'error' => 'invalid_request', 'error_description' => 'refresh_token required.' ), 400 );
			return;
		}

		$tokens = WP_OIDC_Token_Manager::refresh_tokens( $refresh_token, $client_id );
		if ( is_wp_error( $tokens ) ) {
			$this->send_json_error( array( 'error' => $tokens->get_error_code(), 'error_description' => $tokens->get_error_message() ), 400 );
			return;
		}

		$this->send_json(
			array(
				'access_token'  => $tokens['access_token'],
				'token_type'    => 'Bearer',
				'expires_in'    => $tokens['expires_in'],
				'id_token'      => $tokens['id_token'],
				'refresh_token' => $tokens['refresh_token'],
			)
		);
	}

	// -------------------------------------------------------------------------
	// UserInfo Endpoint
	// -------------------------------------------------------------------------

	private function handle_userinfo() {
		$access_token = '';

		$auth_header = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) ) : '';
		if ( $auth_header && 0 === strpos( $auth_header, 'Bearer ' ) ) {
			$access_token = substr( $auth_header, 7 );
		}

		if ( ! $access_token ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$access_token = isset( $_GET['access_token'] ) ? sanitize_text_field( wp_unslash( $_GET['access_token'] ) ) : '';
		}

		if ( ! $access_token ) {
			header( 'WWW-Authenticate: Bearer realm="OIDC"' );
			$this->send_json_error( array( 'error' => 'invalid_token', 'error_description' => 'Bearer token required.' ), 401 );
			return;
		}

		$payload = WP_OIDC_Token_Manager::validate_access_token( $access_token );
		if ( is_wp_error( $payload ) ) {
			header( 'WWW-Authenticate: Bearer error="' . esc_attr( $payload->get_error_code() ) . '"' );
			$this->send_json_error( array( 'error' => $payload->get_error_code(), 'error_description' => $payload->get_error_message() ), 401 );
			return;
		}

		$user_id = intval( $payload['sub'] );
		$scopes  = isset( $payload['scope'] ) ? explode( ' ', $payload['scope'] ) : array();
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			$this->send_json_error( array( 'error' => 'invalid_token', 'error_description' => 'User not found.' ), 401 );
			return;
		}

		$userinfo = array( 'sub' => (string) $user_id );

		if ( in_array( 'profile', $scopes, true ) ) {
			$userinfo['name']               = $user->display_name;
			$userinfo['given_name']         = $user->first_name;
			$userinfo['family_name']        = $user->last_name;
			$userinfo['preferred_username'] = $user->user_login;
		}

		if ( in_array( 'email', $scopes, true ) ) {
			$userinfo['email']          = $user->user_email;
			$userinfo['email_verified'] = true;
		}

		$this->send_json( $userinfo );
	}

	// -------------------------------------------------------------------------
	// JWKS Endpoint
	// -------------------------------------------------------------------------

	private function handle_jwks() {
		$this->send_json( WP_OIDC_Token_Manager::get_jwks() );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function send_json( $data, $status = 200 ) {
		status_header( $status );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Cache-Control: no-store' );
		header( 'Pragma: no-cache' );
		echo wp_json_encode( $data );
		exit;
	}

	private function send_json_error( $data, $status = 400 ) {
		$this->send_json( $data, $status );
	}

	private function redirect_with_error( $redirect_uri, $error, $description, $state ) {
		if ( ! $redirect_uri ) {
			$this->send_json_error( array( 'error' => $error, 'error_description' => $description ), 400 );
			return;
		}
		$params = array(
			'error'             => $error,
			'error_description' => $description,
		);
		if ( $state ) {
			$params['state'] = $state;
		}
		wp_safe_redirect( add_query_arg( $params, $redirect_uri ) );
		exit;
	}
}
