<?php
/**
 * Admin view: Clients list / Edit client
 *
 * Variables available:
 *   $client  – DB row (object) when editing, null when creating.
 *   $clients – Array of all client DB rows (list page only).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_edit  = isset( $client ) && $client;
$page     = $is_edit ? 'wp-oidc-clients' : 'wp-oidc-add-client';
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$created      = isset( $_GET['created'] ) && '1' === $_GET['created'];
$updated      = isset( $_GET['updated'] ) && '1' === $_GET['updated'];
$secret_reset = isset( $_GET['secret_reset'] ) && '1' === $_GET['secret_reset'];
$error        = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
// phpcs:enable

$new_secret = '';
if ( $is_edit && ( $created || $secret_reset ) ) {
	$new_secret = get_transient( 'wp_oidc_new_secret_' . $client->client_id );
	if ( $new_secret ) {
		delete_transient( 'wp_oidc_new_secret_' . $client->client_id );
	}
}

$redirect_uris_text = '';
if ( $is_edit ) {
	$uris = WP_OIDC_Client_Manager::get_redirect_uris( $client );
	$redirect_uris_text = implode( "\n", $uris );
}

$all_scopes = array(
	'openid'  => __( 'openid – Required for OIDC', 'wp-oidcserver' ),
	'profile' => __( 'profile – Name, username', 'wp-oidcserver' ),
	'email'   => __( 'email – Email address', 'wp-oidcserver' ),
);

$active_scopes = $is_edit ? explode( ' ', $client->allowed_scopes ) : array( 'openid', 'profile', 'email' );

$title = $is_edit
	? sprintf( __( 'Edit Client: %s', 'wp-oidcserver' ), esc_html( $client->client_name ) )
	: __( 'Add New Client', 'wp-oidcserver' );
?>
<div class="wrap wp-oidc-wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>
	<?php if ( ! $is_edit ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-oidc-clients' ) ); ?>" class="page-title-action">
			&larr; <?php esc_html_e( 'Back to Clients', 'wp-oidcserver' ); ?>
		</a>
	<?php endif; ?>
	<hr class="wp-header-end">

	<?php if ( $error === 'missing_fields' ) : ?>
		<div class="notice notice-error"><p><?php esc_html_e( 'Please fill in all required fields.', 'wp-oidcserver' ); ?></p></div>
	<?php elseif ( $error === 'db_error' ) : ?>
		<div class="notice notice-error"><p><?php esc_html_e( 'A database error occurred. Please try again.', 'wp-oidcserver' ); ?></p></div>
	<?php elseif ( $error === 'reset_failed' ) : ?>
		<div class="notice notice-error"><p><?php esc_html_e( 'Failed to reset client secret.', 'wp-oidcserver' ); ?></p></div>
	<?php endif; ?>

	<?php if ( $updated ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Client updated successfully.', 'wp-oidcserver' ); ?></p></div>
	<?php endif; ?>

	<?php if ( $new_secret ) : ?>
		<div class="notice notice-warning wp-oidc-secret-notice">
			<p>
				<strong><?php echo $created ? esc_html__( 'Client created!', 'wp-oidcserver' ) : esc_html__( 'Secret reset!', 'wp-oidcserver' ); ?></strong>
				<?php esc_html_e( 'Copy your client secret now – it will not be shown again.', 'wp-oidcserver' ); ?>
			</p>
			<div class="wp-oidc-secret-box">
				<code id="client-secret-value"><?php echo esc_html( $new_secret ); ?></code>
				<button type="button" class="button button-small wp-oidc-copy-btn" data-target="client-secret-value">
					<?php esc_html_e( 'Copy', 'wp-oidcserver' ); ?>
				</button>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $is_edit ) : ?>
		<!-- Client credentials display -->
		<div class="wp-oidc-credentials-card">
			<h2><?php esc_html_e( 'Client Credentials', 'wp-oidcserver' ); ?></h2>
			<table class="wp-oidc-credentials-table">
				<tr>
					<th><?php esc_html_e( 'Client ID', 'wp-oidcserver' ); ?></th>
					<td>
						<code id="client-id-value"><?php echo esc_html( $client->client_id ); ?></code>
						<button type="button" class="button button-small wp-oidc-copy-btn" data-target="client-id-value">
							<?php esc_html_e( 'Copy', 'wp-oidcserver' ); ?>
						</button>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Client Secret', 'wp-oidcserver' ); ?></th>
					<td>
						<span class="wp-oidc-secret-masked"><?php esc_html_e( '(hidden – reset to reveal)', 'wp-oidcserver' ); ?></span>
						<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wp-oidc-inline-form"
							onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure? This will invalidate the current secret.', 'wp-oidcserver' ) ); ?>')">
							<?php wp_nonce_field( 'wp_oidc_reset_secret' ); ?>
							<input type="hidden" name="action" value="wp_oidc_reset_secret">
							<input type="hidden" name="client_id" value="<?php echo esc_attr( $client->client_id ); ?>">
							<button type="submit" class="button button-small button-link-delete">
								<?php esc_html_e( 'Reset Secret', 'wp-oidcserver' ); ?>
							</button>
						</form>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Discovery URL', 'wp-oidcserver' ); ?></th>
					<td>
						<code id="discovery-url"><?php echo esc_html( trailingslashit( get_bloginfo( 'url' ) ) . '.well-known/openid-configuration' ); ?></code>
						<button type="button" class="button button-small wp-oidc-copy-btn" data-target="discovery-url">
							<?php esc_html_e( 'Copy', 'wp-oidcserver' ); ?>
						</button>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Created', 'wp-oidcserver' ); ?></th>
					<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $client->created_at ) ) ); ?></td>
				</tr>
			</table>
		</div>
	<?php endif; ?>

	<!-- Edit / Create form -->
	<div class="wp-oidc-form-card">
		<h2><?php $is_edit ? esc_html_e( 'Configuration', 'wp-oidcserver' ) : esc_html_e( 'Client Details', 'wp-oidcserver' ); ?></h2>
		<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wp_oidc_save_client' ); ?>
			<input type="hidden" name="action" value="wp_oidc_save_client">
			<?php if ( $is_edit ) : ?>
				<input type="hidden" name="client_id" value="<?php echo esc_attr( $client->client_id ); ?>">
			<?php endif; ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="client_name"><?php esc_html_e( 'Application Name', 'wp-oidcserver' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="text" id="client_name" name="client_name" class="regular-text"
							value="<?php echo $is_edit ? esc_attr( $client->client_name ) : ''; ?>"
							placeholder="<?php esc_attr_e( 'My Application', 'wp-oidcserver' ); ?>" required>
						<p class="description"><?php esc_html_e( 'A friendly name for this client application.', 'wp-oidcserver' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="redirect_uris"><?php esc_html_e( 'Redirect URIs', 'wp-oidcserver' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<textarea id="redirect_uris" name="redirect_uris" rows="4" class="large-text code"
							placeholder="https://example.com/callback" required><?php echo esc_textarea( $redirect_uris_text ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One URI per line. These are the allowed callback URLs after authorization.', 'wp-oidcserver' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Allowed Scopes', 'wp-oidcserver' ); ?></th>
					<td>
						<?php foreach ( $all_scopes as $scope_key => $scope_label ) : ?>
							<label class="wp-oidc-scope-label">
								<input type="checkbox" name="scope_<?php echo esc_attr( $scope_key ); ?>" value="1"
									<?php checked( in_array( $scope_key, $active_scopes, true ) ); ?>
									<?php if ( 'openid' === $scope_key ) { echo 'disabled checked'; } ?>>
								<?php echo esc_html( $scope_label ); ?>
							</label><br>
						<?php endforeach; ?>
						<input type="hidden" name="allowed_scopes" id="allowed_scopes_hidden" value="<?php echo esc_attr( implode( ' ', $active_scopes ) ); ?>">
						<p class="description"><?php esc_html_e( 'The "openid" scope is always required.', 'wp-oidcserver' ); ?></p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php $is_edit ? esc_html_e( 'Save Changes', 'wp-oidcserver' ) : esc_html_e( 'Create Client', 'wp-oidcserver' ); ?>
				</button>
				<?php if ( $is_edit ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-oidc-clients' ) ); ?>" class="button">
						<?php esc_html_e( 'Cancel', 'wp-oidcserver' ); ?>
					</a>
				<?php endif; ?>
			</p>
		</form>
	</div>

	<?php if ( $is_edit ) : ?>
		<div class="wp-oidc-danger-zone">
			<h2><?php esc_html_e( 'Danger Zone', 'wp-oidcserver' ); ?></h2>
			<p><?php esc_html_e( 'Deleting a client is permanent and will revoke all associated tokens.', 'wp-oidcserver' ); ?></p>
			<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				onsubmit="return confirm('<?php echo esc_js( __( 'Delete this client and all its tokens? This cannot be undone.', 'wp-oidcserver' ) ); ?>')">
				<?php wp_nonce_field( 'wp_oidc_delete_client' ); ?>
				<input type="hidden" name="action" value="wp_oidc_delete_client">
				<input type="hidden" name="client_id" value="<?php echo esc_attr( $client->client_id ); ?>">
				<button type="submit" class="button button-link-delete">
					<?php esc_html_e( 'Delete Client', 'wp-oidcserver' ); ?>
				</button>
			</form>
		</div>
	<?php endif; ?>
</div>

<script>
(function() {
	// Build scopes string from checkboxes.
	function updateScopes() {
		var scopes = ['openid'];
		['profile', 'email'].forEach(function(s) {
			var cb = document.querySelector('[name="scope_' + s + '"]');
			if (cb && cb.checked) scopes.push(s);
		});
		var hidden = document.getElementById('allowed_scopes_hidden');
		if (hidden) hidden.value = scopes.join(' ');
	}
	document.querySelectorAll('[name^="scope_"]').forEach(function(el) {
		el.addEventListener('change', updateScopes);
	});

	// Copy-to-clipboard buttons.
	document.querySelectorAll('.wp-oidc-copy-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var target = document.getElementById(btn.dataset.target);
			if (!target) return;
			navigator.clipboard.writeText(target.textContent).then(function() {
				var orig = btn.textContent;
				btn.textContent = '<?php echo esc_js( __( 'Copied!', 'wp-oidcserver' ) ); ?>';
				setTimeout(function() { btn.textContent = orig; }, 2000);
			});
		});
	});
})();
</script>
