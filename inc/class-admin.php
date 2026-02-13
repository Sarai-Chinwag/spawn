<?php
/**
 * Admin settings page.
 *
 * @package Spawn
 */

declare(strict_types=1);

namespace Spawn;

/**
 * Handles admin settings for Spawn.
 */
class Admin {

	/**
	 * Initialize admin.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

		// Self-Spawn action handlers.
		add_action( 'admin_post_spawn_self_install', array( __CLASS__, 'handle_self_install' ) );
		add_action( 'admin_post_spawn_self_start', array( __CLASS__, 'handle_self_start' ) );
		add_action( 'admin_post_spawn_self_stop', array( __CLASS__, 'handle_self_stop' ) );
		add_action( 'admin_post_spawn_self_restart', array( __CLASS__, 'handle_self_restart' ) );
		add_action( 'admin_post_spawn_self_uninstall', array( __CLASS__, 'handle_self_uninstall' ) );

		// Self-spawn admin UI removed — OpenClaw installed externally.
	}

	/**
	 * Add admin menu.
	 */
	public static function add_menu(): void {
		// Main Spawn menu.
		add_menu_page(
			__( 'Spawn', 'spawn' ),
			__( 'Spawn', 'spawn' ),
			'manage_options',
			'spawn',
			array( __CLASS__, 'render_customers_page' ),
			'dashicons-cloud',
			30
		);

		// Customers submenu (default).
		add_submenu_page(
			'spawn',
			__( 'Customers', 'spawn' ),
			__( 'Customers', 'spawn' ),
			'manage_options',
			'spawn',
			array( __CLASS__, 'render_customers_page' )
		);

		// Settings submenu.
		add_submenu_page(
			'spawn',
			__( 'Spawn Settings', 'spawn' ),
			__( 'Settings', 'spawn' ),
			'manage_options',
			'spawn-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public static function register_settings(): void {
		// Stripe Price IDs (API keys are in stripe-integration plugin).
		register_setting( 'spawn_settings', 'spawn_stripe_price_starter' );
		register_setting( 'spawn_settings', 'spawn_stripe_price_pro' );
		register_setting( 'spawn_settings', 'spawn_stripe_price_business' );

		// Name.com settings.
		register_setting( 'spawn_settings', 'spawn_namecom_username' );
		register_setting( 'spawn_settings', 'spawn_namecom_token' );

		// Provisioner settings (can be Sweatpants, Lambda, or any HTTP endpoint).
		register_setting( 'spawn_settings', 'spawn_provisioner_url' );
		register_setting( 'spawn_settings', 'spawn_provisioner_token' );

		// OpenClaw settings (for admin chat with control plane).
		register_setting( 'spawn_settings', 'spawn_openclaw_gateway_url' );
		register_setting( 'spawn_settings', 'spawn_openclaw_token' );

		// Branding settings.
		register_setting( 'spawn_settings', 'spawn_subdomain_suffix' );
		register_setting( 'spawn_settings', 'spawn_brand_name' );
		register_setting( 'spawn_settings', 'spawn_brand_logo_url' );
		register_setting( 'spawn_settings', 'spawn_api_base_url' );

		// Google OAuth settings.
		register_setting( 'spawn_settings', 'spawn_google_client_id', array(
			'sanitize_callback' => function( $value ) {
				return sanitize_text_field( wp_unslash( $value ) );
			},
		) );
		register_setting( 'spawn_settings', 'spawn_google_client_secret', array(
			'sanitize_callback' => function( $value ) {
				return sanitize_text_field( wp_unslash( $value ) );
			},
		) );

		// Stripe section - now links to stripe-integration settings.
		add_settings_section(
			'spawn_stripe_section',
			__( 'Stripe Configuration', 'spawn' ),
			array( __CLASS__, 'render_stripe_section_description' ),
			'spawn-settings'
		);

		// Price IDs (spawn-specific).
		add_settings_field(
			'spawn_stripe_price_starter',
			__( 'Starter Price ID', 'spawn' ),
			array( __CLASS__, 'render_text_field' ),
			'spawn-settings',
			'spawn_stripe_section',
			array(
				'name'        => 'spawn_stripe_price_starter',
				'description' => __( 'Stripe Price ID for Starter tier', 'spawn' ),
			)
		);

		add_settings_field(
			'spawn_stripe_price_pro',
			__( 'Pro Price ID', 'spawn' ),
			array( __CLASS__, 'render_text_field' ),
			'spawn-settings',
			'spawn_stripe_section',
			array(
				'name'        => 'spawn_stripe_price_pro',
				'description' => __( 'Stripe Price ID for Pro tier', 'spawn' ),
			)
		);

		add_settings_field(
			'spawn_stripe_price_business',
			__( 'Business Price ID', 'spawn' ),
			array( __CLASS__, 'render_text_field' ),
			'spawn-settings',
			'spawn_stripe_section',
			array(
				'name'        => 'spawn_stripe_price_business',
				'description' => __( 'Stripe Price ID for Business tier', 'spawn' ),
			)
		);

		// Name.com section.
		add_settings_section(
			'spawn_namecom_section',
			__( 'Name.com Configuration', 'spawn' ),
			function() {
				echo '<p>' . esc_html__( 'Configure Name.com API for domain registration.', 'spawn' ) . '</p>';
			},
			'spawn-settings'
		);

		add_settings_field(
			'spawn_namecom_username',
			__( 'Username', 'spawn' ),
			array( __CLASS__, 'render_text_field' ),
			'spawn-settings',
			'spawn_namecom_section',
			array( 'name' => 'spawn_namecom_username' )
		);

		add_settings_field(
			'spawn_namecom_token',
			__( 'API Token', 'spawn' ),
			array( __CLASS__, 'render_text_field' ),
			'spawn-settings',
			'spawn_namecom_section',
			array(
				'name' => 'spawn_namecom_token',
				'type' => 'password',
			)
		);

		// Provisioner section.
		add_settings_section(
			'spawn_provisioner_section',
			__( 'Provisioner Configuration', 'spawn' ),
			function() {
				echo '<p>' . esc_html__( 'Configure the provisioning service for VPS creation. This can be Sweatpants, AWS Lambda, or any HTTP endpoint that accepts job requests.', 'spawn' ) . '</p>';
			},
			'spawn-settings'
		);

		add_settings_field(
			'spawn_provisioner_url',
			__( 'API URL', 'spawn' ),
			array( __CLASS__, 'render_text_field' ),
			'spawn-settings',
			'spawn_provisioner_section',
			array(
				'name'        => 'spawn_provisioner_url',
				'placeholder' => 'http://localhost:8420',
			)
		);

		add_settings_field(
			'spawn_provisioner_token',
			__( 'API Token', 'spawn' ),
			array( __CLASS__, 'render_text_field' ),
			'spawn-settings',
			'spawn_provisioner_section',
			array(
				'name' => 'spawn_provisioner_token',
				'type' => 'password',
			)
		);

		// OpenClaw section (admin chat with control plane).
		add_settings_section(
			'spawn_openclaw_section',
			__( 'OpenClaw (Control Plane)', 'spawn' ),
			function() {
				echo '<p>' . esc_html__( 'Configure your OpenClaw gateway for admin chat. This lets you (the SaaS operator) chat with your own agent.', 'spawn' ) . '</p>';
			},
			'spawn-settings'
		);

		add_settings_field(
			'spawn_openclaw_gateway_url',
			__( 'Gateway URL', 'spawn' ),
			array( __CLASS__, 'render_text_field' ),
			'spawn-settings',
			'spawn_openclaw_section',
			array(
				'name'        => 'spawn_openclaw_gateway_url',
				'placeholder' => 'http://127.0.0.1:18789',
			)
		);

		add_settings_field(
			'spawn_openclaw_token',
			__( 'Auth Token', 'spawn' ),
			array( __CLASS__, 'render_text_field' ),
			'spawn-settings',
			'spawn_openclaw_section',
			array(
				'name' => 'spawn_openclaw_token',
				'type' => 'password',
			)
		);

		// Branding section.
		add_settings_section(
			'spawn_branding_section',
			__( 'Branding', 'spawn' ),
			function() {
				echo '<p>' . esc_html__( 'Customize branding, subdomain suffix, and API base URL.', 'spawn' ) . '</p>';
			},
			'spawn-settings'
		);

		add_settings_field(
			'spawn_subdomain_suffix',
			__( 'Subdomain Suffix', 'spawn' ),
			array( __CLASS__, 'render_text_field' ),
			'spawn-settings',
			'spawn_branding_section',
			array(
				'name'        => 'spawn_subdomain_suffix',
				'placeholder' => 'example.com',
			)
		);

		add_settings_field(
			'spawn_brand_name',
			__( 'Brand Name', 'spawn' ),
			array( __CLASS__, 'render_text_field' ),
			'spawn-settings',
			'spawn_branding_section',
			array(
				'name'        => 'spawn_brand_name',
				'placeholder' => 'Spawn',
			)
		);

		add_settings_field(
			'spawn_brand_logo_url',
			__( 'Brand Logo URL', 'spawn' ),
			array( __CLASS__, 'render_text_field' ),
			'spawn-settings',
			'spawn_branding_section',
			array(
				'name'        => 'spawn_brand_logo_url',
				'placeholder' => 'https://example.com/logo.png',
			)
		);

		add_settings_field(
			'spawn_api_base_url',
			__( 'API Base URL', 'spawn' ),
			array( __CLASS__, 'render_text_field' ),
			'spawn-settings',
			'spawn_branding_section',
			array(
				'name'        => 'spawn_api_base_url',
				'placeholder' => 'https://api.example.com',
			)
		);

		// Google OAuth section.
		add_settings_section(
			'spawn_google_oauth_section',
			__( 'Google OAuth', 'spawn' ),
			function() {
				echo '<p>' . esc_html__( 'Configure Google OAuth for customer sign-in. Create credentials at console.cloud.google.com', 'spawn' ) . '</p>';
			},
			'spawn-settings'
		);

		add_settings_field(
			'spawn_google_client_id',
			__( 'Client ID', 'spawn' ),
			array( __CLASS__, 'render_text_field' ),
			'spawn-settings',
			'spawn_google_oauth_section',
			array( 'name' => 'spawn_google_client_id' )
		);

		add_settings_field(
			'spawn_google_client_secret',
			__( 'Client Secret', 'spawn' ),
			array( __CLASS__, 'render_text_field' ),
			'spawn-settings',
			'spawn_google_oauth_section',
			array(
				'name' => 'spawn_google_client_secret',
				'type' => 'password',
			)
		);

		// Self-spawn section removed.
	}

	/**
	 * Render Stripe section description with link to stripe-integration settings.
	 */
	public static function render_stripe_section_description(): void {
		$stripe_settings_url = admin_url( 'options-general.php?page=stripe-integration' );

		echo '<p>';
		printf(
			/* translators: %s: URL to Stripe Integration settings */
			esc_html__( 'Stripe API keys are managed in the %s plugin. Configure your Price IDs below.', 'spawn' ),
			'<a href="' . esc_url( $stripe_settings_url ) . '">' . esc_html__( 'Stripe Integration', 'spawn' ) . '</a>'
		);
		echo '</p>';
	}

	/**
	 * Render text field.
	 *
	 * @param array $args Field arguments.
	 */
	public static function render_text_field( array $args ): void {
		$name        = $args['name'];
		$type        = $args['type'] ?? 'text';
		$placeholder = $args['placeholder'] ?? '';
		$description = $args['description'] ?? '';
		$value       = get_option( $name, '' );

		printf(
			'<input type="%s" name="%s" value="%s" placeholder="%s" class="regular-text" />',
			esc_attr( $type ),
			esc_attr( $name ),
			esc_attr( $value ),
			esc_attr( $placeholder )
		);

		if ( $description ) {
			printf( '<p class="description">%s</p>', esc_html( $description ) );
		}
	}

	/**
	 * Render settings page.
	 */
	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'spawn_settings' );
				do_settings_sections( 'spawn-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render customers page.
	 */
	public static function render_customers_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$customers = Database::get_all_customers();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Spawn Customers', 'spawn' ); ?></h1>

			<?php if ( empty( $customers ) ) : ?>
				<p><?php esc_html_e( 'No customers yet.', 'spawn' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'ID', 'spawn' ); ?></th>
							<th><?php esc_html_e( 'Email', 'spawn' ); ?></th>
							<th><?php esc_html_e( 'Domain', 'spawn' ); ?></th>
							<th><?php esc_html_e( 'Tier', 'spawn' ); ?></th>
							<th><?php esc_html_e( 'Status', 'spawn' ); ?></th>
							<th><?php esc_html_e( 'Credits', 'spawn' ); ?></th>
							<th><?php esc_html_e( 'Created', 'spawn' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'spawn' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $customers as $customer ) : ?>
							<?php
							$status_class = self::get_status_class( $customer['status'] );
							$is_subdomain = ! empty( $customer['subdomain'] );
							$full_domain  = $is_subdomain
								? $customer['domain'] . '.' . Branding::get_subdomain_suffix()
								: $customer['domain'];
							?>
							<tr>
								<td><?php echo esc_html( $customer['id'] ); ?></td>
								<td><?php echo esc_html( $customer['email'] ); ?></td>
								<td>
									<?php if ( 'active' === $customer['status'] && ! empty( $customer['server_ip'] ) ) : ?>
										<a href="https://<?php echo esc_attr( $full_domain ); ?>" target="_blank">
											<?php echo esc_html( $full_domain ); ?>
										</a>
									<?php else : ?>
										<?php echo esc_html( $full_domain ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $customer['server_type'] ); ?></td>
								<td>
									<span class="spawn-status spawn-status--<?php echo esc_attr( $status_class ); ?>">
										<?php echo esc_html( ucfirst( $customer['status'] ) ); ?>
									</span>
								</td>
								<td>$<?php echo esc_html( number_format( (float) $customer['credit_balance'], 2 ) ); ?></td>
								<td><?php echo esc_html( $customer['created_at'] ); ?></td>
								<td>
									<?php if ( ! empty( $customer['server_ip'] ) ) : ?>
										<a href="https://<?php echo esc_attr( $full_domain ); ?>/wp-admin/" target="_blank" class="button button-small">
											<?php esc_html_e( 'WP Admin', 'spawn' ); ?>
										</a>
									<?php endif; ?>
									<?php if ( ! empty( $customer['stripe_customer'] ) ) : ?>
										<a href="https://dashboard.stripe.com/customers/<?php echo esc_attr( $customer['stripe_customer'] ); ?>" target="_blank" class="button button-small">
											<?php esc_html_e( 'Stripe', 'spawn' ); ?>
										</a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<style>
					.spawn-status {
						display: inline-block;
						padding: 3px 8px;
						border-radius: 3px;
						font-size: 12px;
						font-weight: 500;
					}
					.spawn-status--success { background: #d4edda; color: #155724; }
					.spawn-status--warning { background: #fff3cd; color: #856404; }
					.spawn-status--danger { background: #f8d7da; color: #721c24; }
					.spawn-status--info { background: #d1ecf1; color: #0c5460; }
				</style>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get status CSS class.
	 *
	 * @param string $status Customer status.
	 * @return string CSS class.
	 */
	private static function get_status_class( string $status ): string {
		return match ( $status ) {
			'active'        => 'success',
			'provisioning'  => 'info',
			'pending'       => 'info',
			'payment_failed', 'failed' => 'danger',
			'cancelling', 'cancelled' => 'warning',
			default         => 'info',
		};
	}
}
