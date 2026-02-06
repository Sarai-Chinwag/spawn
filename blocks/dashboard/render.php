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

use Spawn\Branding;

$wrapper_attributes = get_block_wrapper_attributes();
$user_id            = get_current_user_id();
$is_admin           = current_user_can( 'manage_options' );
$brand_name         = Branding::get_brand_name();
$brand_logo_url     = Branding::get_brand_logo_url();

if ( ! $user_id ) {
	?>
	<div <?php echo $wrapper_attributes; ?>>
		<!-- Top navigation bar (Spawn branding) - always show -->
		<nav class="spawn-topnav">
			<a href="<?php echo esc_url( home_url( '/spawn/' ) ); ?>" class="spawn-topnav__logo" title="Back to Spawn">
				<?php if ( '' !== $brand_logo_url ) : ?>
					<img src="<?php echo esc_url( $brand_logo_url ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>" width="32" height="32" />
				<?php endif; ?>
				<span><?php echo esc_html( $brand_name ); ?></span>
			</a>
			<div class="spawn-topnav__links">
				<a href="<?php echo esc_url( home_url( '/spawn/login/' ) ); ?>">Log in</a>
			</div>
		</nav>
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

// Admin self-reflection: when admin views without being a customer,
// show aggregate data for all customers (site management mode).
$is_admin_mode = $is_admin && ! $customer;
if ( $is_admin_mode ) {
	// Get aggregate stats for all customers.
	global $wpdb;
	$customers_table = $wpdb->prefix . 'spawn_customers';
	$usage_table     = $wpdb->prefix . 'spawn_usage';

	// Total credit balance across all customers.
	$total_credits = (float) $wpdb->get_var( "SELECT SUM(credit_balance) FROM $customers_table" );

	// Total usage this month.
	$period_start = gmdate( 'Y-m-01' );
	$total_usage  = $wpdb->get_row( $wpdb->prepare(
		"SELECT SUM(credits_used) as credits_used, SUM(requests_count) as requests_count, 
		        SUM(tokens_input) as tokens_input, SUM(tokens_output) as tokens_output 
		 FROM $usage_table WHERE period_start = %s",
		$period_start
	), ARRAY_A );

	// Customer count.
	$customer_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $customers_table WHERE status = 'active'" );

	// Server and domain counts (all).
	$servers_table = $wpdb->prefix . 'spawn_servers';
	$domains_table = $wpdb->prefix . 'spawn_domains';
	$servers       = $wpdb->get_results( "SELECT * FROM $servers_table", ARRAY_A ) ?: [];
	$domains       = $wpdb->get_results( "SELECT * FROM $domains_table", ARRAY_A ) ?: [];

	// Create synthetic admin "customer" for display.
	$customer = [
		'id'             => 0,
		'tier'           => 'admin',
		'credit_balance' => $total_credits ?: 0,
		'status'         => 'admin',
	];
}

if ( ! $customer && ! $is_admin ) {
	?>
	<div <?php echo $wrapper_attributes; ?>>
		<!-- Top navigation bar (Spawn branding) - always show -->
		<nav class="spawn-topnav">
			<a href="<?php echo esc_url( home_url( '/spawn/' ) ); ?>" class="spawn-topnav__logo" title="Back to Spawn">
				<?php if ( '' !== $brand_logo_url ) : ?>
					<img src="<?php echo esc_url( $brand_logo_url ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>" width="32" height="32" />
				<?php endif; ?>
				<span><?php echo esc_html( $brand_name ); ?></span>
			</a>
			<div class="spawn-topnav__links">
				<a href="<?php echo esc_url( home_url( '/spawn/chat/' ) ); ?>">Chat</a>
				<a href="<?php echo esc_url( wp_logout_url( home_url( '/spawn/' ) ) ); ?>">Log out</a>
			</div>
		</nav>
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

// Fetch AI usage data for this month.
if ( $is_admin_mode ) {
	// Admin mode: use aggregate data already fetched.
	$tier             = 'admin';
	$included_credits = 0; // No limit for admin view.
	$credits_used     = (float) ( $total_usage['credits_used'] ?? 0 );
	$requests_count   = (int) ( $total_usage['requests_count'] ?? 0 );
	$tokens_input     = (int) ( $total_usage['tokens_input'] ?? 0 );
	$tokens_output    = (int) ( $total_usage['tokens_output'] ?? 0 );
	$usage_percent    = 0; // No percentage for unlimited admin.
} else {
	$tier             = $customer ? ( $customer['tier'] ?? 'starter' ) : 'starter';
	$tier_config      = \Spawn\Config::get_tier( $tier );
	$included_credits = $tier_config['included_credits'] ?? 5.0;
	$usage_data       = $customer ? \Spawn\Database::get_server_usage( (int) $customer['id'], 1 ) : [];
	$current_usage    = $usage_data[0] ?? null;
	$credits_used     = (float) ( $current_usage['credits_used'] ?? 0 );
	$requests_count   = (int) ( $current_usage['requests_count'] ?? 0 );
	$tokens_input     = (int) ( $current_usage['tokens_input'] ?? 0 );
	$tokens_output    = (int) ( $current_usage['tokens_output'] ?? 0 );
	$usage_percent    = $included_credits > 0 ? min( 100, ( $credits_used / $included_credits ) * 100 ) : 0;
}

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
	<!-- Top navigation bar (Spawn branding) -->
	<nav class="spawn-topnav">
		<a href="<?php echo esc_url( home_url( '/spawn/' ) ); ?>" class="spawn-topnav__logo" title="Back to Spawn">
			<?php if ( '' !== $brand_logo_url ) : ?>
				<img src="<?php echo esc_url( $brand_logo_url ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>" width="32" height="32" />
			<?php endif; ?>
			<span><?php echo esc_html( $brand_name ); ?></span>
		</a>
		<div class="spawn-topnav__links">
			<a href="<?php echo esc_url( home_url( '/spawn/chat/' ) ); ?>">Chat</a>
			<a href="<?php echo esc_url( home_url( '/spawn/dashboard/' ) ); ?>" class="is-active">Dashboard</a>
			<a href="<?php echo esc_url( wp_logout_url( home_url( '/spawn/' ) ) ); ?>">Log out</a>
		</div>
	</nav>

	<header class="spawn-dashboard__header">
		<div class="spawn-dashboard__header-text">
			<?php if ( $is_admin_mode ) : ?>
				<p class="spawn-dashboard__eyebrow"><?php echo esc_html__( 'Admin Dashboard', 'spawn' ); ?></p>
				<h2 class="spawn-dashboard__title"><?php echo esc_html__( 'Site Management', 'spawn' ); ?></h2>
			<?php else : ?>
				<p class="spawn-dashboard__eyebrow"><?php echo esc_html__( 'Spawn Dashboard', 'spawn' ); ?></p>
				<h2 class="spawn-dashboard__title"><?php echo esc_html__( 'Manage your AI servers', 'spawn' ); ?></h2>
			<?php endif; ?>
		</div>
		<div class="spawn-dashboard__credit">
			<span class="spawn-dashboard__credit-label">
				<?php echo $is_admin_mode ? esc_html__( 'Total Customer Credits', 'spawn' ) : esc_html__( 'Credit Balance', 'spawn' ); ?>
			</span>
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
			<div class="spawn-dashboard__card spawn-dashboard__card--chat spawn-dashboard__card--featured">
				<h3><?php echo esc_html__( 'Chat with your AI', 'spawn' ); ?></h3>
				<p class="spawn-dashboard__muted"><?php echo esc_html__( 'Your AI assistant is ready to help build and manage your site.', 'spawn' ); ?></p>
				<a class="spawn-dashboard__button spawn-dashboard__button--primary" href="<?php echo esc_url( home_url( '/spawn/chat/' ) ); ?>">
					<?php echo esc_html__( 'Open Chat', 'spawn' ); ?>
				</a>
			</div>
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
			<div class="spawn-dashboard__card spawn-dashboard__card--usage">
				<h3><?php echo $is_admin_mode ? esc_html__( 'Total AI Usage This Month', 'spawn' ) : esc_html__( 'AI Usage This Month', 'spawn' ); ?></h3>
				<div class="spawn-dashboard__usage-stats">
					<div class="spawn-dashboard__usage-main">
						<span class="spawn-dashboard__usage-value">$<?php echo esc_html( number_format( $credits_used, 2 ) ); ?></span>
						<?php if ( ! $is_admin_mode ) : ?>
							<span class="spawn-dashboard__usage-of"><?php echo esc_html__( 'of', 'spawn' ); ?></span>
							<span class="spawn-dashboard__usage-total">$<?php echo esc_html( number_format( $included_credits, 2 ) ); ?></span>
							<span class="spawn-dashboard__usage-label"><?php echo esc_html__( 'included', 'spawn' ); ?></span>
						<?php else : ?>
							<span class="spawn-dashboard__usage-label"><?php echo esc_html__( 'Anthropic cost', 'spawn' ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( ! $is_admin_mode ) : ?>
						<div class="spawn-dashboard__usage-bar">
							<div class="spawn-dashboard__usage-bar-fill" style="width: <?php echo esc_attr( $usage_percent ); ?>%;"></div>
						</div>
					<?php endif; ?>
					<div class="spawn-dashboard__usage-details">
						<span title="<?php echo esc_attr__( 'API Requests', 'spawn' ); ?>">
							<strong><?php echo esc_html( number_format_i18n( $requests_count ) ); ?></strong> <?php echo esc_html__( 'requests', 'spawn' ); ?>
						</span>
						<span title="<?php echo esc_attr__( 'Input Tokens', 'spawn' ); ?>">
							<strong><?php echo esc_html( number_format_i18n( $tokens_input ) ); ?></strong> <?php echo esc_html__( 'in', 'spawn' ); ?>
						</span>
						<span title="<?php echo esc_attr__( 'Output Tokens', 'spawn' ); ?>">
							<strong><?php echo esc_html( number_format_i18n( $tokens_output ) ); ?></strong> <?php echo esc_html__( 'out', 'spawn' ); ?>
						</span>
					</div>
				</div>
				<?php if ( ! $is_admin_mode && $credits_used >= $included_credits ) : ?>
					<p class="spawn-dashboard__usage-warning"><?php echo esc_html__( 'You\'ve used your included credits. Additional usage draws from your balance.', 'spawn' ); ?></p>
				<?php endif; ?>
				<?php if ( $is_admin_mode && isset( $customer_count ) ) : ?>
					<p class="spawn-dashboard__muted"><?php echo sprintf( esc_html__( 'Across %d active customers', 'spawn' ), $customer_count ); ?></p>
				<?php endif; ?>
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
					<p class="spawn-dashboard__muted"><?php echo esc_html__( 'Your AI runs on a free subdomain. When you\'re ready, ask your AI to help you register a custom domain or connect one you already own.', 'spawn' ); ?></p>
				</div>
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
