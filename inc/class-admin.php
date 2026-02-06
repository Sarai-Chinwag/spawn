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
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );

		// Self-Spawn action handlers.
		add_action( 'admin_post_spawn_self_install', [ __CLASS__, 'handle_self_install' ] );
		add_action( 'admin_post_spawn_self_start', [ __CLASS__, 'handle_self_start' ] );
		add_action( 'admin_post_spawn_self_stop', [ __CLASS__, 'handle_self_stop' ] );
		add_action( 'admin_post_spawn_self_restart', [ __CLASS__, 'handle_self_restart' ] );
		add_action( 'admin_post_spawn_self_uninstall', [ __CLASS__, 'handle_self_uninstall' ] );

		// Admin notices for Self-Spawn actions.
		add_action( 'admin_notices', [ __CLASS__, 'show_self_spawn_notices' ] );
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
			[ __CLASS__, 'render_customers_page' ],
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
			[ __CLASS__, 'render_customers_page' ]
		);

		// Settings submenu.
		add_submenu_page(
			'spawn',
			__( 'Spawn Settings', 'spawn' ),
			__( 'Settings', 'spawn' ),
			'manage_options',
			'spawn-settings',
			[ __CLASS__, 'render_settings_page' ]
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

		// Sweatpants settings.
		register_setting( 'spawn_settings', 'spawn_sweatpants_url' );
		register_setting( 'spawn_settings', 'spawn_sweatpants_token' );

		// OpenClaw settings (for admin chat with control plane).
		register_setting( 'spawn_settings', 'spawn_openclaw_gateway_url' );
		register_setting( 'spawn_settings', 'spawn_openclaw_token' );

		// Branding settings.
		register_setting( 'spawn_settings', 'spawn_subdomain_suffix' );
		register_setting( 'spawn_settings', 'spawn_brand_name' );
		register_setting( 'spawn_settings', 'spawn_brand_logo_url' );
		register_setting( 'spawn_settings', 'spawn_api_base_url' );

		// Google OAuth settings.
		register_setting( 'spawn_settings', 'spawn_google_client_id', [
			'sanitize_callback' => function( $value ) {
				return sanitize_text_field( wp_unslash( $value ) );
			},
		] );
		register_setting( 'spawn_settings', 'spawn_google_client_secret', [
			'sanitize_callback' => function( $value ) {
				return sanitize_text_field( wp_unslash( $value ) );
			},
		] );

		// AI Provider credentials (used by self-spawn when wp-ai-client not installed).
		register_setting( 'spawn_settings', 'spawn_anthropic_api_key' );
		register_setting( 'spawn_settings', 'spawn_openai_api_key' );
		register_setting( 'spawn_settings', 'spawn_google_ai_api_key' );

		// Stripe section - now links to stripe-integration settings.
		add_settings_section(
			'spawn_stripe_section',
			__( 'Stripe Configuration', 'spawn' ),
			[ __CLASS__, 'render_stripe_section_description' ],
			'spawn-settings'
		);

		// Price IDs (spawn-specific).
		add_settings_field(
			'spawn_stripe_price_starter',
			__( 'Starter Price ID', 'spawn' ),
			[ __CLASS__, 'render_text_field' ],
			'spawn-settings',
			'spawn_stripe_section',
			[ 'name' => 'spawn_stripe_price_starter', 'description' => __( 'Stripe Price ID for Starter tier', 'spawn' ) ]
		);

		add_settings_field(
			'spawn_stripe_price_pro',
			__( 'Pro Price ID', 'spawn' ),
			[ __CLASS__, 'render_text_field' ],
			'spawn-settings',
			'spawn_stripe_section',
			[ 'name' => 'spawn_stripe_price_pro', 'description' => __( 'Stripe Price ID for Pro tier', 'spawn' ) ]
		);

		add_settings_field(
			'spawn_stripe_price_business',
			__( 'Business Price ID', 'spawn' ),
			[ __CLASS__, 'render_text_field' ],
			'spawn-settings',
			'spawn_stripe_section',
			[ 'name' => 'spawn_stripe_price_business', 'description' => __( 'Stripe Price ID for Business tier', 'spawn' ) ]
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
			[ __CLASS__, 'render_text_field' ],
			'spawn-settings',
			'spawn_namecom_section',
			[ 'name' => 'spawn_namecom_username' ]
		);

		add_settings_field(
			'spawn_namecom_token',
			__( 'API Token', 'spawn' ),
			[ __CLASS__, 'render_text_field' ],
			'spawn-settings',
			'spawn_namecom_section',
			[ 'name' => 'spawn_namecom_token', 'type' => 'password' ]
		);

		// Sweatpants section.
		add_settings_section(
			'spawn_sweatpants_section',
			__( 'Sweatpants Configuration', 'spawn' ),
			function() {
				echo '<p>' . esc_html__( 'Configure Sweatpants for VPS provisioning.', 'spawn' ) . '</p>';
			},
			'spawn-settings'
		);

		add_settings_field(
			'spawn_sweatpants_url',
			__( 'API URL', 'spawn' ),
			[ __CLASS__, 'render_text_field' ],
			'spawn-settings',
			'spawn_sweatpants_section',
			[ 'name' => 'spawn_sweatpants_url', 'placeholder' => 'http://localhost:8585' ]
		);

		add_settings_field(
			'spawn_sweatpants_token',
			__( 'API Token', 'spawn' ),
			[ __CLASS__, 'render_text_field' ],
			'spawn-settings',
			'spawn_sweatpants_section',
			[ 'name' => 'spawn_sweatpants_token', 'type' => 'password' ]
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
			[ __CLASS__, 'render_text_field' ],
			'spawn-settings',
			'spawn_openclaw_section',
			[ 'name' => 'spawn_openclaw_gateway_url', 'placeholder' => 'http://127.0.0.1:18789' ]
		);

		add_settings_field(
			'spawn_openclaw_token',
			__( 'Auth Token', 'spawn' ),
			[ __CLASS__, 'render_text_field' ],
			'spawn-settings',
			'spawn_openclaw_section',
			[ 'name' => 'spawn_openclaw_token', 'type' => 'password' ]
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
			[ __CLASS__, 'render_text_field' ],
			'spawn-settings',
			'spawn_branding_section',
			[ 'name' => 'spawn_subdomain_suffix', 'placeholder' => 'example.com' ]
		);

		add_settings_field(
			'spawn_brand_name',
			__( 'Brand Name', 'spawn' ),
			[ __CLASS__, 'render_text_field' ],
			'spawn-settings',
			'spawn_branding_section',
			[ 'name' => 'spawn_brand_name', 'placeholder' => 'Spawn' ]
		);

		add_settings_field(
			'spawn_brand_logo_url',
			__( 'Brand Logo URL', 'spawn' ),
			[ __CLASS__, 'render_text_field' ],
			'spawn-settings',
			'spawn_branding_section',
			[ 'name' => 'spawn_brand_logo_url', 'placeholder' => 'https://example.com/logo.png' ]
		);

		add_settings_field(
			'spawn_api_base_url',
			__( 'API Base URL', 'spawn' ),
			[ __CLASS__, 'render_text_field' ],
			'spawn-settings',
			'spawn_branding_section',
			[ 'name' => 'spawn_api_base_url', 'placeholder' => 'https://api.example.com' ]
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
			[ __CLASS__, 'render_text_field' ],
			'spawn-settings',
			'spawn_google_oauth_section',
			[ 'name' => 'spawn_google_client_id' ]
		);

		add_settings_field(
			'spawn_google_client_secret',
			__( 'Client Secret', 'spawn' ),
			[ __CLASS__, 'render_text_field' ],
			'spawn-settings',
			'spawn_google_oauth_section',
			[ 'name' => 'spawn_google_client_secret', 'type' => 'password' ]
		);

		// AI Credentials section (fallback when wp-ai-client not installed).
		add_settings_section(
			'spawn_ai_credentials_section',
			__( 'AI Provider Credentials', 'spawn' ),
			[ __CLASS__, 'render_ai_credentials_section_description' ],
			'spawn-settings'
		);

		add_settings_field(
			'spawn_anthropic_api_key',
			__( 'Anthropic API Key', 'spawn' ),
			[ __CLASS__, 'render_text_field' ],
			'spawn-settings',
			'spawn_ai_credentials_section',
			[ 'name' => 'spawn_anthropic_api_key', 'type' => 'password', 'description' => __( 'For Claude models', 'spawn' ) ]
		);

		add_settings_field(
			'spawn_openai_api_key',
			__( 'OpenAI API Key', 'spawn' ),
			[ __CLASS__, 'render_text_field' ],
			'spawn-settings',
			'spawn_ai_credentials_section',
			[ 'name' => 'spawn_openai_api_key', 'type' => 'password', 'description' => __( 'For GPT models', 'spawn' ) ]
		);

		add_settings_field(
			'spawn_google_ai_api_key',
			__( 'Google AI API Key', 'spawn' ),
			[ __CLASS__, 'render_text_field' ],
			'spawn-settings',
			'spawn_ai_credentials_section',
			[ 'name' => 'spawn_google_ai_api_key', 'type' => 'password', 'description' => __( 'For Gemini models', 'spawn' ) ]
		);

		// Self-Spawn section.
		add_settings_section(
			'spawn_self_spawn_section',
			__( 'Self-Spawn: Deploy AI Agent', 'spawn' ),
			[ __CLASS__, 'render_self_spawn_section' ],
			'spawn-settings'
		);
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
	 * Render AI credentials section description.
	 */
	public static function render_ai_credentials_section_description(): void {
		$has_wp_ai_client = WP_AI_Client_Bridge::is_wp_ai_client_active();

		if ( $has_wp_ai_client ) {
			echo '<p>';
			esc_html_e( 'AI credentials detected from wp-ai-client plugin. You can override them here if needed.', 'spawn' );
			echo '</p>';
		} else {
			echo '<p>';
			esc_html_e( 'Enter your AI provider API keys for self-spawn. At least one provider is required.', 'spawn' );
			echo '</p>';
		}
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
								<td><?php echo esc_html( $customer['vps_tier'] ); ?></td>
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

	/**
	 * Render Self-Spawn section.
	 *
	 * Displays environment checks, credential status, and action buttons.
	 */
	public static function render_self_spawn_section(): void {
		$env_check       = Environment_Detector::check();
		$provider_status = WP_AI_Client_Bridge::get_provider_status();
		$openclaw_status = Self_Spawn::get_status();
		$credentials_url = WP_AI_Client_Bridge::get_credentials_page_url();
		?>
		<div class="spawn-self-spawn-section">
			<p class="description" style="font-size: 14px; margin-bottom: 20px;">
				<?php
				esc_html_e(
					'Self-spawn installs an AI agent (OpenClaw) directly on this server. ' .
					'You provide your own API keys — no subscription or proxy required.',
					'spawn'
				);
				?>
			</p>

			<div class="notice notice-info inline" style="margin: 0 0 20px; padding: 10px 15px;">
				<strong><?php esc_html_e( 'Quick Start:', 'spawn' ); ?></strong>
				<ol style="margin: 10px 0 0 20px;">
					<li><?php esc_html_e( 'Configure your AI API key below (Anthropic, OpenAI, or Google)', 'spawn' ); ?></li>
					<li><?php esc_html_e( 'Verify all environment checks pass (green checkmarks)', 'spawn' ); ?></li>
					<li><?php esc_html_e( 'Click "Install OpenClaw" then "Start"', 'spawn' ); ?></li>
					<li><?php esc_html_e( 'Use the Spawn chat block to talk to your AI', 'spawn' ); ?></li>
				</ol>
			</div>

			<h4><?php esc_html_e( 'Environment Check', 'spawn' ); ?></h4>
			<table class="form-table spawn-env-checks">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Node.js', 'spawn' ); ?></th>
						<td>
							<?php if ( $env_check['node_available'] ) : ?>
								<span style="color: green;">✅</span>
								<?php
								printf(
									/* translators: %s: Node.js version */
									esc_html__( 'Node.js %s installed', 'spawn' ),
									esc_html( $env_check['node_version'] ?? '' )
								);
								?>
							<?php else : ?>
								<span style="color: red;">❌</span>
								<?php if ( $env_check['node_version'] ) : ?>
									<?php
									printf(
										/* translators: %s: Node.js version */
										esc_html__( 'Node.js %s is too old (v18+ required)', 'spawn' ),
										esc_html( $env_check['node_version'] )
									);
									?>
								<?php else : ?>
									<?php esc_html_e( 'Node.js not installed (v18+ required)', 'spawn' ); ?>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Shell Access', 'spawn' ); ?></th>
						<td>
							<?php if ( $env_check['shell_access'] ) : ?>
								<span style="color: green;">✅</span>
								<?php esc_html_e( 'Shell access available', 'spawn' ); ?>
							<?php else : ?>
								<span style="color: red;">❌</span>
								<?php esc_html_e( 'Shell access not available (shell_exec disabled)', 'spawn' ); ?>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Server Type', 'spawn' ); ?></th>
						<td>
							<?php if ( $env_check['is_vps'] ) : ?>
								<span style="color: green;">✅</span>
								<?php esc_html_e( 'VPS detected (can run services)', 'spawn' ); ?>
							<?php else : ?>
								<span style="color: red;">❌</span>
								<?php esc_html_e( 'Shared hosting detected (VPS required)', 'spawn' ); ?>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Home Directory', 'spawn' ); ?></th>
						<td>
							<?php if ( $env_check['writable_home'] ) : ?>
								<span style="color: green;">✅</span>
								<?php esc_html_e( 'Home directory writable', 'spawn' ); ?>
							<?php else : ?>
								<span style="color: red;">❌</span>
								<?php esc_html_e( 'Home directory not writable', 'spawn' ); ?>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'systemd', 'spawn' ); ?></th>
						<td>
							<?php if ( $env_check['systemd'] ) : ?>
								<span style="color: green;">✅</span>
								<?php esc_html_e( 'systemd available', 'spawn' ); ?>
							<?php else : ?>
								<span style="color: orange;">⚠️</span>
								<?php esc_html_e( 'systemd not available (will use background process)', 'spawn' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>

			<h4><?php esc_html_e( 'AI Credentials', 'spawn' ); ?></h4>
			<p class="description">
				<?php
				esc_html_e(
					'OpenClaw needs API keys to communicate with AI providers. ' .
					'At least one provider must be configured.',
					'spawn'
				);
				?>
			</p>
			<table class="form-table spawn-credential-checks">
				<tbody>
					<?php foreach ( $provider_status as $provider_id => $status ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( ucfirst( $provider_id ) ); ?></th>
							<td>
								<?php if ( $status['configured'] ) : ?>
									<span style="color: green;">✅</span>
									<?php esc_html_e( 'API key configured', 'spawn' ); ?>
								<?php else : ?>
									<span style="color: orange;">⚠️</span>
									<?php esc_html_e( 'API key not configured', 'spawn' ); ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( '' !== $credentials_url ) : ?>
				<p>
					<a href="<?php echo esc_url( $credentials_url ); ?>" class="button">
						<?php esc_html_e( 'Configure AI Credentials', 'spawn' ); ?>
					</a>
				</p>
			<?php elseif ( ! WP_AI_Client_Bridge::is_wp_ai_client_active() ) : ?>
				<p class="description">
					<?php esc_html_e( 'Install the WP AI Client plugin to manage AI credentials.', 'spawn' ); ?>
				</p>
			<?php endif; ?>

			<hr />

			<h4><?php esc_html_e( 'OpenClaw Status', 'spawn' ); ?></h4>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Installation', 'spawn' ); ?></th>
						<td>
							<?php if ( $openclaw_status['installed'] ) : ?>
								<span style="color: green;">✅</span>
								<?php
								printf(
									/* translators: %s: OpenClaw version */
									esc_html__( 'OpenClaw %s installed', 'spawn' ),
									esc_html( $openclaw_status['version'] ?? '' )
								);
								?>
							<?php else : ?>
								<span style="color: orange;">⚠️</span>
								<?php esc_html_e( 'Not installed', 'spawn' ); ?>
							<?php endif; ?>
						</td>
					</tr>
					<?php if ( $openclaw_status['installed'] ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Gateway', 'spawn' ); ?></th>
							<td>
								<?php if ( $openclaw_status['running'] ) : ?>
									<span style="color: green;">✅</span>
									<?php esc_html_e( 'Running', 'spawn' ); ?>
								<?php else : ?>
									<span style="color: red;">❌</span>
									<?php esc_html_e( 'Stopped', 'spawn' ); ?>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Gateway URL', 'spawn' ); ?></th>
							<td>
								<code><?php echo esc_html( $openclaw_status['gateway_url'] ); ?></code>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<div class="spawn-self-spawn-actions">
				<?php if ( ! $openclaw_status['installed'] ) : ?>
					<?php if ( $env_check['can_install'] ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
							<?php wp_nonce_field( 'spawn_self_install', 'spawn_self_nonce' ); ?>
							<input type="hidden" name="action" value="spawn_self_install" />
							<button type="submit" class="button button-primary">
								<?php esc_html_e( 'Install OpenClaw', 'spawn' ); ?>
							</button>
						</form>
					<?php else : ?>
						<p class="description" style="color: red;">
							<?php esc_html_e( 'Cannot install: ', 'spawn' ); ?>
							<?php echo esc_html( implode( ' ', $env_check['blockers'] ) ); ?>
						</p>
					<?php endif; ?>
				<?php else : ?>
					<?php if ( ! $openclaw_status['running'] ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
							<?php wp_nonce_field( 'spawn_self_start', 'spawn_self_nonce' ); ?>
							<input type="hidden" name="action" value="spawn_self_start" />
							<button type="submit" class="button button-primary">
								<?php esc_html_e( 'Start', 'spawn' ); ?>
							</button>
						</form>
					<?php else : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
							<?php wp_nonce_field( 'spawn_self_stop', 'spawn_self_nonce' ); ?>
							<input type="hidden" name="action" value="spawn_self_stop" />
							<button type="submit" class="button">
								<?php esc_html_e( 'Stop', 'spawn' ); ?>
							</button>
						</form>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
							<?php wp_nonce_field( 'spawn_self_restart', 'spawn_self_nonce' ); ?>
							<input type="hidden" name="action" value="spawn_self_restart" />
							<button type="submit" class="button">
								<?php esc_html_e( 'Restart', 'spawn' ); ?>
							</button>
						</form>
					<?php endif; ?>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline; margin-left: 20px;">
						<?php wp_nonce_field( 'spawn_self_uninstall', 'spawn_self_nonce' ); ?>
						<input type="hidden" name="action" value="spawn_self_uninstall" />
						<button type="submit" class="button" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to uninstall OpenClaw?', 'spawn' ) ); ?>');">
							<?php esc_html_e( 'Uninstall', 'spawn' ); ?>
						</button>
					</form>
				<?php endif; ?>
			</div>
		</div>

		<style>
			.spawn-self-spawn-section h4 {
				margin: 1.5em 0 0.5em;
			}
			.spawn-self-spawn-section h4:first-child {
				margin-top: 0;
			}
			.spawn-self-spawn-section hr {
				margin: 1.5em 0;
			}
			.spawn-self-spawn-actions {
				margin-top: 1em;
			}
			.spawn-self-spawn-actions form {
				margin-right: 8px;
			}
		</style>
		<?php
	}

	/**
	 * Show admin notices for Self-Spawn actions.
	 */
	public static function show_self_spawn_notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['spawn_self_message'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = sanitize_text_field( wp_unslash( $_GET['spawn_self_message'] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type = isset( $_GET['spawn_self_status'] ) && 'error' === $_GET['spawn_self_status'] ? 'error' : 'success';

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	/**
	 * Handle Self-Spawn install action.
	 */
	public static function handle_self_install(): void {
		self::verify_self_spawn_request( 'spawn_self_install' );

		$result = Self_Spawn::install();

		// Also configure if install succeeded.
		if ( $result['success'] ) {
			$configure_result = Self_Spawn::configure();
			if ( ! $configure_result['success'] ) {
				// Installation succeeded but configuration failed - still report success with warning.
				$result['message'] .= ' ' . $configure_result['message'];
			}
		}

		self::redirect_with_message( $result['message'], $result['success'] ? 'success' : 'error' );
	}

	/**
	 * Handle Self-Spawn start action.
	 */
	public static function handle_self_start(): void {
		self::verify_self_spawn_request( 'spawn_self_start' );

		// Configure before starting to ensure credentials are up to date.
		Self_Spawn::configure();

		$result = Self_Spawn::start_service();
		self::redirect_with_message( $result['message'], $result['success'] ? 'success' : 'error' );
	}

	/**
	 * Handle Self-Spawn stop action.
	 */
	public static function handle_self_stop(): void {
		self::verify_self_spawn_request( 'spawn_self_stop' );

		$result = Self_Spawn::stop_service();
		self::redirect_with_message( $result['message'], $result['success'] ? 'success' : 'error' );
	}

	/**
	 * Handle Self-Spawn restart action.
	 */
	public static function handle_self_restart(): void {
		self::verify_self_spawn_request( 'spawn_self_restart' );

		// Configure before restarting to ensure credentials are up to date.
		Self_Spawn::configure();

		$result = Self_Spawn::restart_service();
		self::redirect_with_message( $result['message'], $result['success'] ? 'success' : 'error' );
	}

	/**
	 * Handle Self-Spawn uninstall action.
	 */
	public static function handle_self_uninstall(): void {
		self::verify_self_spawn_request( 'spawn_self_uninstall' );

		$result = Self_Spawn::uninstall();
		self::redirect_with_message( $result['message'], $result['success'] ? 'success' : 'error' );
	}

	/**
	 * Verify Self-Spawn request (nonce + capability).
	 *
	 * @param string $action The action name for nonce verification.
	 */
	private static function verify_self_spawn_request( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'spawn' ) );
		}

		if ( ! isset( $_POST['spawn_self_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spawn_self_nonce'] ) ), $action )
		) {
			wp_die( esc_html__( 'Security check failed. Please try again.', 'spawn' ) );
		}
	}

	/**
	 * Redirect back to settings page with message.
	 *
	 * @param string $message The message to display.
	 * @param string $status  Either 'success' or 'error'.
	 */
	private static function redirect_with_message( string $message, string $status ): void {
		$redirect_url = add_query_arg(
			[
				'page'               => 'spawn-settings',
				'spawn_self_message' => rawurlencode( $message ),
				'spawn_self_status'  => $status,
			],
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}
}
