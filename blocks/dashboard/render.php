<?php
/**
 * Dashboard block render template.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 *
 * @package Spawn
 */

$wrapper_attributes = get_block_wrapper_attributes();
$user_id            = get_current_user_id();
$is_admin           = current_user_can( 'manage_options' );

if ( ! $user_id ) {
	?>
	<div <?php echo $wrapper_attributes; ?>>
		<div class="spawn-dashboard__card">
			<h3><?php echo esc_html__( 'Log In Required', 'spawn' ); ?></h3>
			<p><?php echo esc_html__( 'Please log in to view your dashboard.', 'spawn' ); ?></p>
			<a href="<?php echo esc_url( home_url( '/spawn/login/' ) ); ?>" class="spawn-dashboard__button">
				<?php echo esc_html__( 'Log In', 'spawn' ); ?>
			</a>
		</div>
	</div>
	<?php
	return;
}

$servers  = \Spawn\Database::get_servers_by_user( $user_id );
$domains  = \Spawn\Database::get_domains_by_user( $user_id );
$customer = \Spawn\Database::get_customer_by_user_id( $user_id );

if ( ! $customer && ! $is_admin ) {
	?>
	<div <?php echo $wrapper_attributes; ?>>
		<div class="spawn-dashboard__card">
			<h3><?php echo esc_html__( 'No Active Subscription', 'spawn' ); ?></h3>
			<p><?php echo esc_html__( "You don't have an active Spawn subscription yet.", 'spawn' ); ?></p>
			<a href="<?php echo esc_url( home_url( '/spawn/' ) ); ?>" class="spawn-dashboard__button">
				<?php echo esc_html__( 'Get Started', 'spawn' ); ?>
			</a>
		</div>
	</div>
	<?php
	return;
}

$credit_balance = $customer ? (float) ( $customer['credit_balance'] ?? 0 ) : 0.0;
$server_count   = count( $servers );
$domain_count   = count( $domains );
$active_tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
$active_tab     = in_array( $active_tab, [ 'overview', 'servers', 'domains' ], true ) ? $active_tab : 'overview';

$overview_url       = add_query_arg( [ 'tab' => 'overview' ], home_url( '/spawn/dashboard/' ) );
$servers_url        = add_query_arg( [ 'tab' => 'servers' ], home_url( '/spawn/dashboard/' ) );
$domains_url        = add_query_arg( [ 'tab' => 'domains' ], home_url( '/spawn/dashboard/' ) );
$overview_is_active = ( 'overview' === $active_tab );
$servers_is_active  = ( 'servers' === $active_tab );
$domains_is_active  = ( 'domains' === $active_tab );

$servers_by_id = [];
foreach ( $servers as $server ) {
	if ( empty( $server['id'] ) ) {
		continue;
	}
	$servers_by_id[ (int) $server['id'] ] = $server['name'] ?? '';
}
?>
<div <?php echo $wrapper_attributes; ?> data-active-tab="<?php echo esc_attr( $active_tab ); ?>">
	<header class="spawn-dashboard__header">
		<div class="spawn-dashboard__header-text">
			<p class="spawn-dashboard__eyebrow"><?php echo esc_html__( 'Spawn Dashboard', 'spawn' ); ?></p>
			<h2 class="spawn-dashboard__title"><?php echo esc_html__( 'Manage your AI servers', 'spawn' ); ?></h2>
		</div>
		<div class="spawn-dashboard__credit">
			<span class="spawn-dashboard__credit-label"><?php echo esc_html__( 'Credit Balance', 'spawn' ); ?></span>
			<span class="spawn-dashboard__credit-value">
				<?php echo esc_html( number_format_i18n( $credit_balance, 2 ) ); ?>
				<span class="spawn-dashboard__credit-unit"><?php echo esc_html__( 'credits', 'spawn' ); ?></span>
			</span>
		</div>
	</header>

	<nav class="spawn-dashboard__tabs" aria-label="<?php echo esc_attr__( 'Dashboard tabs', 'spawn' ); ?>">
		<a class="spawn-dashboard__tab<?php echo $overview_is_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $overview_url ); ?>" data-tab="overview" aria-current="<?php echo esc_attr( $overview_is_active ? 'page' : 'false' ); ?>">
			<?php echo esc_html__( 'Overview', 'spawn' ); ?>
		</a>
		<a class="spawn-dashboard__tab<?php echo $servers_is_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $servers_url ); ?>" data-tab="servers" aria-current="<?php echo esc_attr( $servers_is_active ? 'page' : 'false' ); ?>">
			<?php echo esc_html__( 'Servers', 'spawn' ); ?>
		</a>
		<a class="spawn-dashboard__tab<?php echo $domains_is_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $domains_url ); ?>" data-tab="domains" aria-current="<?php echo esc_attr( $domains_is_active ? 'page' : 'false' ); ?>">
			<?php echo esc_html__( 'Domains', 'spawn' ); ?>
		</a>
	</nav>

	<section class="spawn-dashboard__panel<?php echo $overview_is_active ? ' is-active' : ''; ?>" data-panel="overview">
		<div class="spawn-dashboard__grid">
			<div class="spawn-dashboard__card spawn-dashboard__card--balance">
				<h3><?php echo esc_html__( 'Credit Balance', 'spawn' ); ?></h3>
				<p class="spawn-dashboard__balance">
					<?php echo esc_html( number_format_i18n( $credit_balance, 2 ) ); ?>
					<span><?php echo esc_html__( 'credits', 'spawn' ); ?></span>
				</p>
				<p class="spawn-dashboard__muted"><?php echo esc_html__( 'Top up anytime to keep your AI online.', 'spawn' ); ?></p>
				<a class="spawn-dashboard__button" href="<?php echo esc_url( home_url( '/spawn/account/' ) ); ?>">
					<?php echo esc_html__( 'Add Credits', 'spawn' ); ?>
				</a>
			</div>
			<div class="spawn-dashboard__card">
				<h3><?php echo esc_html__( 'Servers', 'spawn' ); ?></h3>
				<p class="spawn-dashboard__balance">
					<?php echo esc_html( number_format_i18n( $server_count ) ); ?>
					<span><?php echo esc_html__( 'active', 'spawn' ); ?></span>
				</p>
				<p class="spawn-dashboard__muted"><?php echo esc_html__( 'Manage tiers, status, and WordPress installs.', 'spawn' ); ?></p>
			</div>
			<div class="spawn-dashboard__card">
				<h3><?php echo esc_html__( 'Domains', 'spawn' ); ?></h3>
				<p class="spawn-dashboard__balance">
					<?php echo esc_html( number_format_i18n( $domain_count ) ); ?>
					<span><?php echo esc_html__( 'registered', 'spawn' ); ?></span>
				</p>
				<p class="spawn-dashboard__muted"><?php echo esc_html__( 'Track renewals and assign domains.', 'spawn' ); ?></p>
			</div>
			<div class="spawn-dashboard__card">
				<h3><?php echo esc_html__( 'Quick Actions', 'spawn' ); ?></h3>
				<div class="spawn-dashboard__actions">
					<a class="spawn-dashboard__button" href="<?php echo esc_url( home_url( '/spawn/checkout/' ) ); ?>">
						<?php echo esc_html__( 'Spawn New AI', 'spawn' ); ?>
					</a>
					<a class="spawn-dashboard__button spawn-dashboard__button--ghost" href="<?php echo esc_url( home_url( '/spawn/account/' ) ); ?>">
						<?php echo esc_html__( 'Manage Account', 'spawn' ); ?>
					</a>
				</div>
			</div>
		</div>
	</section>

	<section class="spawn-dashboard__panel<?php echo $servers_is_active ? ' is-active' : ''; ?>" data-panel="servers">
		<?php if ( empty( $servers ) ) : ?>
			<div class="spawn-dashboard__empty">
				<div>
					<h3><?php echo esc_html__( 'No servers yet', 'spawn' ); ?></h3>
					<p class="spawn-dashboard__muted"><?php echo esc_html__( 'Create your first AI server to start building.', 'spawn' ); ?></p>
				</div>
				<a class="spawn-dashboard__button" href="<?php echo esc_url( home_url( '/spawn/checkout/' ) ); ?>">
					<?php echo esc_html__( 'Spawn New AI', 'spawn' ); ?>
				</a>
			</div>
		<?php else : ?>
			<div class="spawn-dashboard__grid">
				<?php foreach ( $servers as $server ) : ?>
					<?php
						$server_name = ! empty( $server['name'] ) ? $server['name'] : __( 'Untitled Server', 'spawn' );
						$tier_label  = ! empty( $server['tier'] ) ? $server['tier'] : __( 'Unknown', 'spawn' );
						$status_raw   = ! empty( $server['status'] ) ? $server['status'] : __( 'Unknown', 'spawn' );
						$status_label = ucwords( str_replace( '_', ' ', (string) $status_raw ) );
						$server_ip   = ! empty( $server['server_ip'] ) ? $server['server_ip'] : __( 'Not assigned', 'spawn' );
						$wordpress_label = ! empty( $server['has_wordpress'] ) ? __( 'Enabled', 'spawn' ) : __( 'Not installed', 'spawn' );
						$tier_label       = ucwords( str_replace( '_', ' ', (string) $tier_label ) );
						$status_key       = sanitize_key( (string) $status_raw );
						$status_is_active = in_array( $status_key, [ 'active', 'running', 'online', 'ready' ], true );
						$status_class     = 'spawn-dashboard__tab' . ( $status_is_active ? ' is-active' : '' );
					?>
					<div class="spawn-dashboard__card">
						<h3><?php echo esc_html( $server_name ); ?></h3>
						<p class="spawn-dashboard__muted">
							<?php echo esc_html__( 'Tier', 'spawn' ); ?>:
							<span class="spawn-dashboard__credit-unit"><?php echo esc_html( $tier_label ); ?></span>
						</p>
						<p class="spawn-dashboard__muted">
							<?php echo esc_html__( 'Status', 'spawn' ); ?>:
							<span class="<?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
						</p>
						<p class="spawn-dashboard__muted">
							<?php echo esc_html__( 'IP', 'spawn' ); ?>:
							<span class="spawn-dashboard__credit-unit"><?php echo esc_html( $server_ip ); ?></span>
						</p>
						<p class="spawn-dashboard__muted">
							<?php echo esc_html__( 'WordPress', 'spawn' ); ?>:
							<span class="spawn-dashboard__credit-unit"><?php echo esc_html( $wordpress_label ); ?></span>
						</p>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>

	<section class="spawn-dashboard__panel<?php echo $domains_is_active ? ' is-active' : ''; ?>" data-panel="domains">
		<?php if ( empty( $domains ) ) : ?>
			<div class="spawn-dashboard__empty">
				<div>
					<h3><?php echo esc_html__( 'No domains yet', 'spawn' ); ?></h3>
					<p class="spawn-dashboard__muted"><?php echo esc_html__( 'Register a domain when you are ready to go live.', 'spawn' ); ?></p>
				</div>
				<a class="spawn-dashboard__button" href="<?php echo esc_url( home_url( '/spawn/domain-search/' ) ); ?>">
					<?php echo esc_html__( 'Register Domain', 'spawn' ); ?>
				</a>
			</div>
		<?php else : ?>
			<div class="spawn-dashboard__card">
				<table>
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Domain', 'spawn' ); ?></th>
							<th><?php echo esc_html__( 'Assigned Server', 'spawn' ); ?></th>
							<th><?php echo esc_html__( 'Expires At', 'spawn' ); ?></th>
							<th><?php echo esc_html__( 'Auto Renew', 'spawn' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $domains as $domain ) : ?>
							<?php
								$domain_name    = ! empty( $domain['domain'] ) ? $domain['domain'] : __( 'Unknown', 'spawn' );
								$domain_server  = __( 'Unassigned', 'spawn' );
								$server_id      = ! empty( $domain['server_id'] ) ? (int) $domain['server_id'] : 0;
								$expires_label  = ! empty( $domain['expires_at'] ) ? mysql2date( get_option( 'date_format' ), $domain['expires_at'] ) : __( 'Not set', 'spawn' );
								$auto_renew     = ! empty( $domain['auto_renew'] );
								if ( $server_id && isset( $servers_by_id[ $server_id ] ) && '' !== $servers_by_id[ $server_id ] ) {
									$domain_server = $servers_by_id[ $server_id ];
								}
							?>
							<tr>
								<td><?php echo esc_html( $domain_name ); ?></td>
								<td><?php echo esc_html( $domain_server ); ?></td>
								<td><?php echo esc_html( $expires_label ); ?></td>
								<td>
									<input type="checkbox" disabled="disabled" <?php echo checked( $auto_renew, true, false ); ?> />
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
</div>
