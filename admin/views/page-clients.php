<?php
/**
 * Admin view: Clients list page
 *
 * Variables available:
 *   $clients – Array of client DB rows.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$deleted = isset( $_GET['deleted'] ) && '1' === $_GET['deleted'];
// phpcs:enable
?>
<div class="wrap wp-oidc-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'OIDC Clients', 'keystone-oidc' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-oidc-add-client' ) ); ?>" class="page-title-action">
		<?php esc_html_e( '+ Add Client', 'keystone-oidc' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php if ( $deleted ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Client deleted successfully.', 'keystone-oidc' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="wp-oidc-info-banner">
		<p>
			<strong><?php esc_html_e( 'Discovery URL:', 'keystone-oidc' ); ?></strong>
			<code><?php echo esc_html( KEYSTONE_OIDC_Provider::get_endpoint_url( '.well-known/openid-configuration' ) ); ?></code>
		</p>
		<p class="description">
			<?php esc_html_e( 'Share this URL with your OIDC client applications so they can auto-configure the endpoints.', 'keystone-oidc' ); ?>
		</p>
	</div>

	<?php if ( empty( $clients ) ) : ?>
		<div class="wp-oidc-empty-state">
			<span class="dashicons dashicons-shield" style="font-size:64px;width:64px;height:64px;color:#ccd0d4;"></span>
			<h2><?php esc_html_e( 'No clients yet', 'keystone-oidc' ); ?></h2>
			<p><?php esc_html_e( 'Create your first OIDC client to get started.', 'keystone-oidc' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-oidc-add-client' ) ); ?>" class="button button-primary button-hero">
				<?php esc_html_e( 'Add Your First Client', 'keystone-oidc' ); ?>
			</a>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col" class="column-name"><?php esc_html_e( 'Application Name', 'keystone-oidc' ); ?></th>
					<th scope="col" class="column-client-id"><?php esc_html_e( 'Client ID', 'keystone-oidc' ); ?></th>
					<th scope="col" class="column-scopes"><?php esc_html_e( 'Scopes', 'keystone-oidc' ); ?></th>
					<th scope="col" class="column-created"><?php esc_html_e( 'Created', 'keystone-oidc' ); ?></th>
					<th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'keystone-oidc' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $clients as $row ) : ?>
					<tr>
						<td class="column-name">
							<strong>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-oidc-clients&client_id=' . urlencode( $row->client_id ) ) ); ?>">
									<?php echo esc_html( $row->client_name ); ?>
								</a>
							</strong>
						</td>
						<td class="column-client-id">
							<code><?php echo esc_html( $row->client_id ); ?></code>
						</td>
						<td class="column-scopes">
							<?php
							$scopes = explode( ' ', $row->allowed_scopes );
							foreach ( $scopes as $s ) {
								echo '<span class="wp-oidc-scope-badge">' . esc_html( $s ) . '</span> ';
							}
							?>
						</td>
						<td class="column-created">
							<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $row->created_at ) ) ); ?>
						</td>
						<td class="column-actions">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-oidc-clients&client_id=' . urlencode( $row->client_id ) ) ); ?>" class="button button-small">
								<?php esc_html_e( 'Edit', 'keystone-oidc' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
