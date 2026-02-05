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
	}

	/**
	 * Add admin menu.
	 */
	public static function add_menu(): void {
		add_options_page(
			__( 'Spawn Settings', 'spawn' ),
			__( 'Spawn', 'spawn' ),
			'manage_options',
			'spawn',
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

		// Stripe section - now links to stripe-integration settings.
		add_settings_section(
			'spawn_stripe_section',
			__( 'Stripe Configuration', 'spawn' ),
			[ __CLASS__, 'render_stripe_section_description' ],
			'spawn'
		);

		// Price IDs (spawn-specific).
		add_settings_field(
			'spawn_stripe_price_starter',
			__( 'Starter Price ID', 'spawn' ),
			[ __CLASS__, 'render_text_field' ],
			'spawn',
			'spawn_stripe_section',
			[ 'name' => 'spawn_stripe_price_starter', 'description' => __( 'Stripe Price ID for Starter tier', 'spawn' ) ]
		);

		add_settings_field(
			'spawn_stripe_price_pro',
			__( 'Pro Price ID', 'spawn' ),
			[ __CLASS__, 'render_text_field' ],
			'spawn',
			'spawn_stripe_section',
			[ 'name' => 'spawn_stripe_price_pro', 'description' => __( 'Stripe Price ID for Pro tier', 'spawn' ) ]
		);

		add_settings_field(
			'spawn_stripe_price_business',
			__( 'Business Price ID', 'spawn' ),
			[ __CLASS__, 'render_text_field' ],
			'spawn',
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
			'spawn'
		);

		add_settings_field(
			'spawn_namecom_username',
			__( 'Username', 'spawn' ),
			[ __CLASS__, 'render_text_field' ],
			'spawn',
			'spawn_namecom_section',
			[ 'name' => 'spawn_namecom_username' ]
		);

		add_settings_field(
			'spawn_namecom_token',
			__( 'API Token', 'spawn' ),
			[ __CLASS__, 'render_text_field' ],
			'spawn',
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
			'spawn'
		);

		add_settings_field(
			'spawn_sweatpants_url',
			__( 'API URL', 'spawn' ),
			[ __CLASS__, 'render_text_field' ],
			'spawn',
			'spawn_sweatpants_section',
			[ 'name' => 'spawn_sweatpants_url', 'placeholder' => 'http://localhost:8585' ]
		);

		add_settings_field(
			'spawn_sweatpants_token',
			__( 'API Token', 'spawn' ),
			[ __CLASS__, 'render_text_field' ],
			'spawn',
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
			'spawn'
		);

		add_settings_field(
			'spawn_openclaw_gateway_url',
			__( 'Gateway URL', 'spawn' ),
			[ __CLASS__, 'render_text_field' ],
			'spawn',
			'spawn_openclaw_section',
			[ 'name' => 'spawn_openclaw_gateway_url', 'placeholder' => 'http://127.0.0.1:18789' ]
		);

		add_settings_field(
			'spawn_openclaw_token',
			__( 'Auth Token', 'spawn' ),
			[ __CLASS__, 'render_text_field' ],
			'spawn',
			'spawn_openclaw_section',
			[ 'name' => 'spawn_openclaw_token', 'type' => 'password' ]
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
				do_settings_sections( 'spawn' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
