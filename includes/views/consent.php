<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'Authorize Application', 'wp-oidcserver' ); ?> &mdash; <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
	<style>
		*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
			background: #f0f2f5;
			display: flex;
			align-items: center;
			justify-content: center;
			min-height: 100vh;
			padding: 20px;
		}
		.card {
			background: #fff;
			border-radius: 8px;
			box-shadow: 0 2px 16px rgba(0,0,0,.12);
			max-width: 420px;
			width: 100%;
			padding: 40px 32px 32px;
			text-align: center;
		}
		.site-icon {
			width: 64px;
			height: 64px;
			border-radius: 50%;
			background: #0073aa;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			margin-bottom: 16px;
		}
		.site-icon svg { fill: #fff; width: 36px; height: 36px; }
		h1 { font-size: 20px; font-weight: 600; color: #1d2327; margin-bottom: 6px; }
		.subtitle { color: #646970; font-size: 14px; margin-bottom: 24px; }
		.subtitle strong { color: #1d2327; }
		.scopes {
			background: #f6f7f7;
			border: 1px solid #e0e0e0;
			border-radius: 6px;
			padding: 16px 20px;
			margin-bottom: 24px;
			text-align: left;
		}
		.scopes h2 { font-size: 12px; text-transform: uppercase; letter-spacing: .06em; color: #646970; margin-bottom: 10px; }
		.scope-item {
			display: flex;
			align-items: center;
			gap: 10px;
			padding: 6px 0;
			font-size: 14px;
			color: #1d2327;
			border-bottom: 1px solid #e8e8e8;
		}
		.scope-item:last-child { border-bottom: none; }
		.scope-item::before {
			content: '✓';
			flex-shrink: 0;
			width: 20px;
			height: 20px;
			background: #00a32a;
			color: #fff;
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 11px;
			font-weight: bold;
		}
		.user-info { font-size: 13px; color: #646970; margin-bottom: 20px; }
		.user-info strong { color: #1d2327; }
		.actions { display: flex; gap: 12px; }
		.btn {
			flex: 1;
			padding: 10px 16px;
			border: none;
			border-radius: 4px;
			font-size: 14px;
			font-weight: 500;
			cursor: pointer;
			transition: background .15s;
		}
		.btn-primary { background: #0073aa; color: #fff; }
		.btn-primary:hover { background: #005a87; }
		.btn-secondary { background: #f6f7f7; color: #1d2327; border: 1px solid #ccc; }
		.btn-secondary:hover { background: #eee; }
		.notice { font-size: 12px; color: #646970; margin-top: 16px; }
	</style>
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
			esc_html__( 'wants to access your %s account', 'wp-oidcserver' ),
			'<strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong>'
		);
		?>
	</p>

	<div class="scopes">
		<h2><?php esc_html_e( 'This application will be able to:', 'wp-oidcserver' ); ?></h2>
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
			esc_html__( 'Signed in as %s', 'wp-oidcserver' ),
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
				<?php esc_html_e( 'Deny', 'wp-oidcserver' ); ?>
			</button>
			<button type="submit" name="authorize" value="allow" class="btn btn-primary">
				<?php esc_html_e( 'Allow Access', 'wp-oidcserver' ); ?>
			</button>
		</div>
	</form>

	<p class="notice">
		<?php esc_html_e( 'You can revoke this access at any time from your account settings.', 'wp-oidcserver' ); ?>
	</p>
</div>
</body>
</html>
