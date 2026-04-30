<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'Authorize Application', 'keystone-oidc' ); ?> &mdash; <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
	<?php wp_head(); ?>
</head>
<body>
<div class="card">
	<div class="site-icon">
		<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
			<path d="M10 0C4.5 0 0 4.5 0 10s4.5 10 10 10 10-4.5 10-10S15.5 0 10 0zm0 18c-4.4 0-8-3.6-8-8s3.6-8 8-8 8 3.6 8 8-3.6 8-8 8zm-1-13h2v6H9V5zm0 8h2v2H9v-2z"/>
		</svg>
	</div>

	<h1><?php echo esc_html( $client->client_name ); ?></h1>
	<p class="subtitle">
		<?php
		printf(
			/* translators: %s: site name */
			esc_html__( 'wants to access your %s account', 'keystone-oidc' ),
			'<strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong>'
		);
		?>
	</p>

	<div class="scopes">
		<h2><?php esc_html_e( 'This application will be able to:', 'keystone-oidc' ); ?></h2>
		<?php foreach ( $requested_scopes as $s ) : ?>
			<?php if ( isset( $scope_labels[ $s ] ) ) : ?>
				<div class="scope-item"><?php echo esc_html( $scope_labels[ $s ] ); ?></div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>

	<p class="user-info">
		<?php
		printf(
			/* translators: %s: username */
			esc_html__( 'Signed in as %s', 'keystone-oidc' ),
			'<strong>' . esc_html( $user->user_login ) . '</strong>'
		);
		?>
	</p>

	<form method="POST" action="">
		<?php wp_nonce_field( 'oidc_authorize' ); ?>
		<?php if ( $nonce ) : ?>
			<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">
		<?php endif; ?>
		<?php if ( $code_challenge ) : ?>
			<input type="hidden" name="code_challenge" value="<?php echo esc_attr( $code_challenge ); ?>">
			<input type="hidden" name="code_challenge_method" value="<?php echo esc_attr( $code_challenge_method ); ?>">
		<?php endif; ?>
		<div class="actions">
			<button type="submit" name="authorize" value="deny" class="btn btn-secondary">
				<?php esc_html_e( 'Deny', 'keystone-oidc' ); ?>
			</button>
			<button type="submit" name="authorize" value="allow" class="btn btn-primary">
				<?php esc_html_e( 'Allow Access', 'keystone-oidc' ); ?>
			</button>
		</div>
	</form>

	<p class="notice">
		<?php esc_html_e( 'You can revoke this access at any time from your account settings.', 'keystone-oidc' ); ?>
	</p>
</div>
</body>
</html>
