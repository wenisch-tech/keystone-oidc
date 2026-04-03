<?php
/**
 * Admin view: Settings page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$keys_rotated = isset( $_GET['keys_rotated'] ) && '1' === $_GET['keys_rotated'];
$key_error    = isset( $_GET['key_error'] ) && '1' === $_GET['key_error'];
// phpcs:enable

$issuer = WP_OIDC_Token_Manager::get_issuer();
$kid    = WP_OIDC_Token_Manager::get_key_id();
?>
<div class="wrap wp-oidc-wrap">
	<h1><?php esc_html_e( 'OIDC Provider Settings', 'wp-oidcprovider' ); ?></h1>
	<hr class="wp-header-end">

	<?php if ( $keys_rotated ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Signing keys rotated successfully. Existing tokens will no longer validate.', 'wp-oidcprovider' ); ?></p>
		</div>
	<?php endif; ?>
	<?php if ( $key_error ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'Failed to generate new signing keys. Check that OpenSSL is available.', 'wp-oidcprovider' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="wp-oidc-form-card">
		<h2><?php esc_html_e( 'Server Information', 'wp-oidcprovider' ); ?></h2>
		<table class="wp-oidc-credentials-table">
			<tr>
				<th><?php esc_html_e( 'Issuer URL', 'wp-oidcprovider' ); ?></th>
				<td><code><?php echo esc_html( $issuer ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Discovery Endpoint', 'wp-oidcprovider' ); ?></th>
				<td><code><?php echo esc_html( WP_OIDC_Provider::get_endpoint_url( '.well-known/openid-configuration' ) ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Authorization Endpoint', 'wp-oidcprovider' ); ?></th>
				<td><code><?php echo esc_html( WP_OIDC_Provider::get_endpoint_url( 'oauth/authorize' ) ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Token Endpoint', 'wp-oidcprovider' ); ?></th>
				<td><code><?php echo esc_html( WP_OIDC_Provider::get_endpoint_url( 'oauth/token' ) ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'UserInfo Endpoint', 'wp-oidcprovider' ); ?></th>
				<td><code><?php echo esc_html( WP_OIDC_Provider::get_endpoint_url( 'oauth/userinfo' ) ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'JWKS URI', 'wp-oidcprovider' ); ?></th>
				<td><code><?php echo esc_html( WP_OIDC_Provider::get_endpoint_url( 'oauth/jwks' ) ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Current Key ID (kid)', 'wp-oidcprovider' ); ?></th>
				<td><code><?php echo esc_html( $kid ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Plugin Version', 'wp-oidcprovider' ); ?></th>
				<td><?php echo esc_html( WP_OIDC_VERSION ); ?></td>
			</tr>
		</table>
	</div>

	<div class="wp-oidc-danger-zone">
		<h2><?php esc_html_e( 'Signing Key Management', 'wp-oidcprovider' ); ?></h2>
		<p><?php esc_html_e( 'Rotating the signing keys will generate a new RSA key pair. All previously issued tokens will become invalid immediately. Use with caution.', 'wp-oidcprovider' ); ?></p>
		<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			onsubmit="return confirm('<?php echo esc_js( __( 'This will invalidate all existing tokens. Are you sure?', 'wp-oidcprovider' ) ); ?>')">
			<?php wp_nonce_field( 'wp_oidc_rotate_keys' ); ?>
			<input type="hidden" name="action" value="wp_oidc_rotate_keys">
			<button type="submit" class="button button-link-delete">
				<?php esc_html_e( 'Rotate Signing Keys', 'wp-oidcprovider' ); ?>
			</button>
		</form>
	</div>
</div>
