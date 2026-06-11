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
	<link rel="stylesheet" href="<?php echo esc_url( KEYSTONE_OIDC_PLUGIN_URL . 'includes/css/consent.css?ver=' . KEYSTONE_OIDC_VERSION ); ?>">
</head>
<body>
<main class="auth-shell">
	<div class="orb orb-one" aria-hidden="true"></div>
	<div class="orb orb-two" aria-hidden="true"></div>

	<section class="card" aria-labelledby="keystone-oidc-title">
		<div class="brand-row">
			<div class="site-icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
					<path d="M12 2.25 4.5 5.55v5.38c0 4.63 3.19 8.96 7.5 10.07 4.31-1.11 7.5-5.44 7.5-10.07V5.55L12 2.25Zm0 2.46 5.25 2.31v3.91c0 3.33-2.08 6.51-5.25 7.76-3.17-1.25-5.25-4.43-5.25-7.76V7.02L12 4.71Zm3.48 4.46-4.42 4.42-2.04-2.04-1.24 1.24 3.28 3.28 5.66-5.66-1.24-1.24Z"/>
				</svg>
			</div>
			<div>
				<p class="eyebrow"><?php esc_html_e( 'Secure sign-in request', 'keystone-oidc' ); ?></p>
				<p class="site-name"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
			</div>
		</div>

		<div class="hero-copy">
			<h1 id="keystone-oidc-title"><?php echo esc_html( $client->client_name ); ?></h1>
			<p class="subtitle">
				<?php
				printf(
					/* translators: %s: site name */
					esc_html__( 'wants to access your %s account', 'keystone-oidc' ),
					'<strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong>'
				);
				?>
			</p>
		</div>

		<div class="scopes">
			<h2><?php esc_html_e( 'Requested access', 'keystone-oidc' ); ?></h2>
			<p class="scope-intro"><?php esc_html_e( 'Review what this application can use before continuing.', 'keystone-oidc' ); ?></p>
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

		<form method="POST" action="<?php echo esc_url( $authorize_url ); ?>">
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
	</section>
</main>
</body>
</html>
