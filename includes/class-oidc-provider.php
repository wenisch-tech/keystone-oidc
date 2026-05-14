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

class KEYSTONE_OIDC_Provider {
	const ENDPOINT_BASE_PATH = 'wenisch-tech/keystone-oidc';

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
		// Discovery and JWKS still use rewrite rules (no $_GET/$_POST reads — not flagged by PCP).
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_request' ) );
		// Authorize, token, and userinfo are served via the WP REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_scheduled_delete', array( 'KEYSTONE_OIDC_Token_Manager', 'cleanup_expired' ) );
	}

	/**
	 * Plugin activation: create tables, generate keys, flush rewrite rules.
	 */
	public static function activate() {
		KEYSTONE_OIDC_Client_Manager::create_tables();
		if ( ! get_option( KEYSTONE_OIDC_Token_Manager::OPTION_PRIVATE_KEY ) ) {
			KEYSTONE_OIDC_Token_Manager::generate_keys();
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
	 *
	 * Only discovery and JWKS use rewrite rules. Authorize, token, and userinfo
	 * are served via the WP REST API (register_rest_routes).
	 */
	public static function register_rewrite_rules_static() {
		$endpoint_base = untrailingslashit( self::get_endpoint_base_path() );

		add_rewrite_rule( '^' . $endpoint_base . '/\.well-known/openid-configuration/?$', 'index.php?oidc_endpoint=discovery', 'top' );
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
	 * Route requests to the appropriate handler (discovery and JWKS only).
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
			case 'jwks':
				$this->handle_jwks();
				break;
		}
	}

	/**
	 * Register REST API routes for the OIDC authorize, token, and userinfo endpoints.
	 *
	 * Using register_rest_route() means WordPress routes the request through the REST
	 * infrastructure. Input is read from WP_REST_Request (not $_GET/$_POST), which
	 * satisfies the Plugin Check (PCP) nonce-verification requirement for these
	 * OAuth2/OIDC endpoints whose security model relies on client credentials, PKCE,
	 * and Bearer tokens rather than WordPress nonces.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'keystone-oidc/v1',
			'/authorize',
			array(
				'methods'             => 'GET, POST',
				'callback'            => array( $this, 'rest_callback_authorize' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'keystone-oidc/v1',
			'/token',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_callback_token' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'keystone-oidc/v1',
			'/userinfo',
			array(
				'methods'             => 'GET, POST',
				'callback'            => array( $this, 'rest_callback_userinfo' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	// -------------------------------------------------------------------------
	// Discovery
	// -------------------------------------------------------------------------

	private function handle_discovery() {
		$issuer = KEYSTONE_OIDC_Token_Manager::get_issuer();

		$discovery = array(
			'issuer'                                => $issuer,
			'authorization_endpoint'                => rest_url( 'keystone-oidc/v1/authorize' ),
			'token_endpoint'                        => rest_url( 'keystone-oidc/v1/token' ),
			'userinfo_endpoint'                     => rest_url( 'keystone-oidc/v1/userinfo' ),
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
	// REST API Callbacks
	// -------------------------------------------------------------------------

	/**
	 * REST callback for GET|POST /keystone-oidc/v1/authorize
	 *
	 * On GET: shows the consent page (user must be logged in).
	 * On POST: processes the consent form submission verified with a WP nonce.
	 *
	 * OAuth2 CSRF protection for the initial GET is provided by the `state` parameter
	 * per RFC 6749 §10.12. The consent POST is protected by wp_nonce_field('oidc_authorize')
	 * emitted by the consent form and verified here before any action is taken.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 */
	public function rest_callback_authorize( WP_REST_Request $request ) {
		$response_type         = sanitize_text_field( (string) $request->get_param( 'response_type' ) );
		$client_id             = sanitize_text_field( (string) $request->get_param( 'client_id' ) );
		$redirect_uri          = esc_url_raw( (string) $request->get_param( 'redirect_uri' ) );
		$scope                 = $request->get_param( 'scope' ) ? sanitize_text_field( (string) $request->get_param( 'scope' ) ) : 'openid';
		$state                 = sanitize_text_field( (string) $request->get_param( 'state' ) );
		$nonce                 = $request->get_param( 'nonce' ) !== null ? sanitize_text_field( (string) $request->get_param( 'nonce' ) ) : null;
		$code_challenge        = $request->get_param( 'code_challenge' ) !== null ? sanitize_text_field( (string) $request->get_param( 'code_challenge' ) ) : null;
		$code_challenge_method = $request->get_param( 'code_challenge_method' ) !== null ? sanitize_text_field( (string) $request->get_param( 'code_challenge_method' ) ) : null;

		if ( 'code' !== $response_type ) {
			$this->redirect_with_error( $redirect_uri, 'unsupported_response_type', 'Only "code" response_type is supported.', $state );
			return;
		}

		$client = KEYSTONE_OIDC_Client_Manager::get_client( $client_id );
		if ( ! $client ) {
			$this->send_json_error( array( 'error' => 'invalid_client', 'error_description' => 'Unknown client.' ), 400 );
			return;
		}

		if ( ! KEYSTONE_OIDC_Client_Manager::validate_redirect_uri( $client_id, $redirect_uri ) ) {
			$this->send_json_error( array( 'error' => 'invalid_request', 'error_description' => 'Invalid redirect_uri.' ), 400 );
			return;
		}

		$requested_scopes = explode( ' ', $scope );
		if ( ! in_array( 'openid', $requested_scopes, true ) ) {
			$this->redirect_with_error( $redirect_uri, 'invalid_scope', 'The "openid" scope is required.', $state );
			return;
		}

		if ( ! is_user_logged_in() ) {
			$authorize_params = array_filter(
				array(
					'response_type'         => $response_type,
					'client_id'             => $client_id,
					'redirect_uri'          => $redirect_uri,
					'scope'                 => $scope,
					'state'                 => $state,
					'nonce'                 => $nonce,
					'code_challenge'        => $code_challenge,
					'code_challenge_method' => $code_challenge_method,
				)
			);
			$authorize_url = add_query_arg( $authorize_params, rest_url( 'keystone-oidc/v1/authorize' ) );
			wp_safe_redirect( wp_login_url( $authorize_url ) );
			exit;
		}

		if ( 'POST' === $request->get_method() ) {
			// Verify the WP nonce emitted by wp_nonce_field( 'oidc_authorize' ) in consent.php.
			$nonce_value = sanitize_text_field( (string) $request->get_param( '_wpnonce' ) );
			if ( ! wp_verify_nonce( $nonce_value, 'oidc_authorize' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'keystone-oidc' ) );
			}
			$this->handle_authorize_post( $client_id, $redirect_uri, $scope, $state, $nonce, $code_challenge, $code_challenge_method, $request );
			return;
		}

		$this->render_consent_page( $client, $scope, $state, $nonce, $redirect_uri, $code_challenge, $code_challenge_method );
	}

	/**
	 * REST callback for POST /keystone-oidc/v1/token
	 *
	 * OAuth2 Token Endpoint (RFC 6749 §4.1.3). Authentication is performed via
	 * client credentials (HTTP Basic or POST body) or PKCE code_verifier (RFC 7636).
	 * There is no browser session on this machine-to-machine call.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 */
	public function rest_callback_token( WP_REST_Request $request ) {
		$client_id     = '';
		$client_secret = '';

		// Prefer HTTP Basic authentication.
		$auth_header = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) ) : '';
		if ( $auth_header && 0 === strpos( $auth_header, 'Basic ' ) ) {
			$decoded = base64_decode( substr( $auth_header, 6 ) );
			if ( false !== $decoded && strpos( $decoded, ':' ) !== false ) {
				list( $client_id, $client_secret ) = explode( ':', $decoded, 2 );
			}
		}

		if ( ! $client_id ) {
			$client_id     = sanitize_text_field( (string) $request->get_param( 'client_id' ) );
			$client_secret = sanitize_text_field( (string) $request->get_param( 'client_secret' ) );
		}

		$grant_type    = sanitize_text_field( (string) $request->get_param( 'grant_type' ) );
		$code          = sanitize_text_field( (string) $request->get_param( 'code' ) );
		$redirect_uri  = esc_url_raw( (string) $request->get_param( 'redirect_uri' ) );
		$refresh_token = sanitize_text_field( (string) $request->get_param( 'refresh_token' ) );
		$code_verifier = $request->get_param( 'code_verifier' ) !== null ? sanitize_text_field( (string) $request->get_param( 'code_verifier' ) ) : null;

		$client = KEYSTONE_OIDC_Client_Manager::get_client( $client_id );
		if ( ! $client ) {
			$this->send_json_error( array( 'error' => 'invalid_client', 'error_description' => 'Unknown client.' ), 401 );
			return;
		}

		if ( $client_secret ) {
			if ( ! KEYSTONE_OIDC_Client_Manager::verify_secret( $client_secret, $client->client_secret ) ) {
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

	/**
	 * REST callback for GET|POST /keystone-oidc/v1/userinfo
	 *
	 * OIDC UserInfo Endpoint (OIDC Core §5.3). Bearer token authentication per RFC 6750.
	 * The Authorization header is the primary mechanism; query-string fallback is for
	 * clients that cannot set headers. No browser session exists on this API call.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 */
	public function rest_callback_userinfo( WP_REST_Request $request ) {
		$access_token = '';

		$auth_header = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) ) : '';
		if ( $auth_header && 0 === strpos( $auth_header, 'Bearer ' ) ) {
			$access_token = substr( $auth_header, 7 );
		}

		if ( ! $access_token ) {
			$access_token = sanitize_text_field( (string) $request->get_param( 'access_token' ) );
		}

		if ( ! $access_token ) {
			header( 'WWW-Authenticate: Bearer realm="OIDC"' );
			$this->send_json_error( array( 'error' => 'invalid_token', 'error_description' => 'Bearer token required.' ), 401 );
			return;
		}

		$payload = KEYSTONE_OIDC_Token_Manager::validate_access_token( $access_token );
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

	private function handle_authorize_post( $client_id, $redirect_uri, $scope, $state, $nonce, $code_challenge, $code_challenge_method, WP_REST_Request $request ) {
		// Nonce already verified by rest_callback_authorize() before this is called.
		$user_id = get_current_user_id();

		if ( 'deny' === sanitize_text_field( (string) $request->get_param( 'authorize' ) ) ) {
			$this->redirect_with_error( $redirect_uri, 'access_denied', 'User denied authorization.', $state );
			return;
		}

		$code = KEYSTONE_OIDC_Token_Manager::generate_auth_code( $client_id, $user_id, $redirect_uri, $scope, $nonce, $code_challenge, $code_challenge_method );

		$params = array( 'code' => $code );
		if ( $state ) {
			$params['state'] = $state;
		}

		// Use wp_redirect (not wp_safe_redirect) because redirect_uri may be on a
		// different host/port. It has already been validated against the registered
		// URIs for this client, so the redirect is safe.
		wp_redirect( add_query_arg( $params, $redirect_uri ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	private function render_consent_page( $client, $scope, $state, $nonce, $redirect_uri, $code_challenge, $code_challenge_method ) {
		$user         = wp_get_current_user();
		$plugin_url   = KEYSTONE_OIDC_PLUGIN_URL;
		$scope_labels = array(
			'openid'  => __( 'Verify your identity', 'keystone-oidc' ),
			'profile' => __( 'Access your name and username', 'keystone-oidc' ),
			'email'   => __( 'Access your email address', 'keystone-oidc' ),
		);
		$requested_scopes = explode( ' ', $scope );
		// Build the form action URL: POST back to the REST authorize endpoint with
		// the same OAuth2 parameters so the handler can reconstruct the full context.
		$authorize_url = add_query_arg(
			array_filter(
				array(
					'response_type'         => 'code',
					'client_id'             => $client->client_id,
					'redirect_uri'          => $redirect_uri,
					'scope'                 => $scope,
					'state'                 => $state,
					'nonce'                 => $nonce,
					'code_challenge'        => $code_challenge,
					'code_challenge_method' => $code_challenge_method,
				)
			),
			rest_url( 'keystone-oidc/v1/authorize' )
		);
		include KEYSTONE_OIDC_PLUGIN_DIR . 'includes/views/consent.php';
		exit;
	}

	// -------------------------------------------------------------------------
	// Token Endpoint
	// -------------------------------------------------------------------------

	private function handle_auth_code_grant( $client_id, $code, $redirect_uri, $code_verifier ) {
		$auth_code = KEYSTONE_OIDC_Token_Manager::consume_auth_code( $code, $client_id, $redirect_uri, $code_verifier );
		if ( is_wp_error( $auth_code ) ) {
			$this->send_json_error( array( 'error' => $auth_code->get_error_code(), 'error_description' => $auth_code->get_error_message() ), 400 );
			return;
		}

		$tokens = KEYSTONE_OIDC_Token_Manager::issue_tokens( $client_id, $auth_code->user_id, $auth_code->scope, $auth_code->nonce );

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

		$tokens = KEYSTONE_OIDC_Token_Manager::refresh_tokens( $refresh_token, $client_id );
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
	// JWKS Endpoint
	// -------------------------------------------------------------------------

	private function handle_jwks() {
		$this->send_json( KEYSTONE_OIDC_Token_Manager::get_jwks() );
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
		// Use wp_redirect (not wp_safe_redirect) — redirect_uri is already validated
		// against the registered list and may point to an external host.
		wp_redirect( add_query_arg( $params, $redirect_uri ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}
}
