<?php
/**
 * Spawn Abilities API registration.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

/**
 * Registers all Spawn abilities.
 */
class Abilities {

	/**
	 * Initialize abilities.
	 */
	public static function init(): void {
		add_action( 'wp_abilities_api_categories_init', [ __CLASS__, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

	/**
	 * Register the Spawn ability category.
	 */
	public static function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category( 'spawn', [
			'label'       => __( 'Spawn Service', 'spawn' ),
			'description' => __( 'Manage AI-powered website service', 'spawn' ),
		] );
	}

	/**
	 * Register all Spawn abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		// Get Status
		wp_register_ability( 'spawn_get_status', [
			'label'       => __( 'Get Customer Status', 'spawn' ),
			'description' => __( 'Get current subscription status and credit balance', 'spawn' ),
			'category'    => 'spawn',
			'callback'    => [ Ability_Get_Status::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'customer_id' => [
						'type'        => 'integer',
						'description' => 'Customer ID (defaults to current user)',
					],
				],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'vps_tier'       => [ 'type' => 'string' ],
					'status'         => [ 'type' => 'string' ],
					'domain'         => [ 'type' => 'string' ],
					'server_ip'      => [ 'type' => 'string' ],
					'credit_balance' => [ 'type' => 'number' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_customer_permission' ],
		] );

		// Scale VPS
		wp_register_ability( 'spawn_scale_vps', [
			'label'       => __( 'Scale VPS', 'spawn' ),
			'description' => __( 'Upgrade or downgrade server resources', 'spawn' ),
			'category'    => 'spawn',
			'callback'    => [ Ability_Scale_VPS::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'new_tier' ],
				'properties' => [
					'customer_id' => [ 'type' => 'integer' ],
					'new_tier'    => [
						'type' => 'string',
						'enum' => \Spawn\Config::get_valid_hetzner_types(),
					],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_customer_permission' ],
		] );

		// Add Credits
		wp_register_ability( 'spawn_add_credits', [
			'label'       => __( 'Add Credits', 'spawn' ),
			'description' => __( 'Add credits to customer balance', 'spawn' ),
			'category'    => 'spawn',
			'callback'    => [ Ability_Add_Credits::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'amount' ],
				'properties' => [
					'customer_id' => [ 'type' => 'integer' ],
					'amount'      => [
						'type'        => 'number',
						'description' => 'Amount in dollars to add',
					],
				],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success'     => [ 'type' => 'boolean' ],
					'added'       => [ 'type' => 'number' ],
					'new_balance' => [ 'type' => 'number' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
		] );

		// Get Usage
		wp_register_ability( 'spawn_get_usage', [
			'label'       => __( 'Get Usage', 'spawn' ),
			'description' => __( 'Get usage statistics', 'spawn' ),
			'category'    => 'spawn',
			'callback'    => [ Ability_Get_Usage::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'customer_id' => [ 'type' => 'integer' ],
					'period'      => [
						'type'    => 'string',
						'enum'    => [ 'current', 'last_month' ],
						'default' => 'current',
					],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_customer_permission' ],
		] );

		// Cancel
		wp_register_ability( 'spawn_cancel', [
			'label'       => __( 'Cancel Subscription', 'spawn' ),
			'description' => __( 'Cancel subscription and schedule VPS deletion after grace period. Provides export instructions.', 'spawn' ),
			'category'    => 'spawn',
			'callback'    => [ Ability_Cancel::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'customer_id' => [
						'type'        => 'integer',
						'description' => 'Customer ID (defaults to current user)',
					],
					'reason'      => [
						'type'        => 'string',
						'description' => 'Optional reason for cancellation',
					],
					'confirm'     => [
						'type'        => 'boolean',
						'description' => 'Must be true to proceed with cancellation',
						'default'     => false,
					],
				],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success'             => [ 'type' => 'boolean' ],
					'status'              => [ 'type' => 'string' ],
					'scheduled_deletion'  => [ 'type' => 'string' ],
					'grace_period_days'   => [ 'type' => 'integer' ],
					'message'             => [ 'type' => 'string' ],
					'export_instructions' => [ 'type' => 'object' ],
					'can_reactivate'      => [ 'type' => 'boolean' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_customer_permission' ],
		] );

		// Export Site
		wp_register_ability( 'spawn_export_site', [
			'label'       => __( 'Export Site', 'spawn' ),
			'description' => __( 'Get instructions to export/backup your WordPress site', 'spawn' ),
			'category'    => 'spawn',
			'callback'    => [ Ability_Export_Site::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'customer_id' => [
						'type'        => 'integer',
						'description' => 'Customer ID (defaults to current user)',
					],
					'format'      => [
						'type'        => 'string',
						'enum'        => [ 'full', 'xml', 'database' ],
						'default'     => 'full',
						'description' => 'Export format: full (ZIP), xml (WordPress export), database (SQL dump)',
					],
				],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success'      => [ 'type' => 'boolean' ],
					'format'       => [ 'type' => 'string' ],
					'description'  => [ 'type' => 'string' ],
					'instructions' => [ 'type' => 'object' ],
					'download_url' => [ 'type' => 'string' ],
					'notes'        => [ 'type' => 'array' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_customer_permission' ],
		] );

		// Manage Billing
		wp_register_ability( 'spawn_manage_billing', [
			'label'       => __( 'Manage Billing', 'spawn' ),
			'description' => __( 'Get Stripe customer portal URL', 'spawn' ),
			'category'    => 'spawn',
			'callback'    => [ Ability_Manage_Billing::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'customer_id' => [ 'type' => 'integer' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_customer_permission' ],
		] );

		// Set Auto-Refill
		wp_register_ability( 'spawn_set_auto_refill', [
			'label'       => __( 'Set Auto-Refill', 'spawn' ),
			'description' => __( 'Configure automatic credit refill when balance is low', 'spawn' ),
			'category'    => 'spawn',
			'callback'    => [ Ability_Set_Auto_Refill::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'customer_id' => [
						'type'        => 'integer',
						'description' => 'Customer ID (defaults to current user)',
					],
					'enabled'     => [
						'type'        => 'boolean',
						'description' => 'Enable or disable auto-refill',
					],
					'threshold'   => [
						'type'        => 'number',
						'description' => 'Trigger refill when balance falls below this amount (in dollars, $1-$100)',
						'minimum'     => 1,
						'maximum'     => 100,
					],
					'amount'      => [
						'type'        => 'number',
						'description' => 'Amount to refill (in dollars, $10-$100)',
						'minimum'     => 10,
						'maximum'     => 100,
					],
				],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'customer_id' => [ 'type' => 'integer' ],
					'enabled'     => [ 'type' => 'boolean' ],
					'threshold'   => [ 'type' => 'number' ],
					'amount'      => [ 'type' => 'number' ],
					'message'     => [ 'type' => 'string' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_customer_permission' ],
		] );

		// Get Domain Renewal Info
		wp_register_ability( 'spawn_get_domain_renewal_info', [
			'label'       => __( 'Get Domain Renewal Info', 'spawn' ),
			'description' => __( 'Get domain expiration info and renewal pricing', 'spawn' ),
			'category'    => 'spawn',
			'callback'    => [ Ability_Get_Domain_Renewal_Info::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'customer_id' => [
						'type'        => 'integer',
						'description' => 'Customer ID (defaults to current user)',
					],
				],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'renewable'           => [ 'type' => 'boolean' ],
					'domain'              => [ 'type' => 'string' ],
					'domain_type'         => [ 'type' => 'string' ],
					'expires_at'          => [ 'type' => 'string' ],
					'expires_formatted'   => [ 'type' => 'string' ],
					'days_until_expiry'   => [ 'type' => 'integer' ],
					'renewal_price'       => [ 'type' => 'number' ],
					'is_expired'          => [ 'type' => 'boolean' ],
					'is_expiring_soon'    => [ 'type' => 'boolean' ],
					'auto_renew_enabled'  => [ 'type' => 'boolean' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_customer_permission' ],
		] );

		// Renew Domain
		wp_register_ability( 'spawn_renew_domain', [
			'label'       => __( 'Renew Domain', 'spawn' ),
			'description' => __( 'Initiate domain renewal checkout', 'spawn' ),
			'category'    => 'spawn',
			'callback'    => [ Ability_Renew_Domain::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'customer_id' => [
						'type'        => 'integer',
						'description' => 'Customer ID (defaults to current user)',
					],
				],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success'       => [ 'type' => 'boolean' ],
					'checkout_url'  => [ 'type' => 'string' ],
					'session_id'    => [ 'type' => 'string' ],
					'domain'        => [ 'type' => 'string' ],
					'renewal_price' => [ 'type' => 'number' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_customer_permission' ],
		] );
	}

	/**
	 * Check if current user can access customer data.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether user has permission.
	 */
	public static function check_customer_permission( array $input ): bool {
		// Admins can access anything.
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		// Must be logged in.
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// If customer_id provided, verify ownership.
		if ( ! empty( $input['customer_id'] ) ) {
			$customer = \Spawn\Database::get_customer( (int) $input['customer_id'] );
			if ( ! $customer ) {
				return false;
			}
			return (int) $customer['user_id'] === get_current_user_id();
		}

		// Default: allow for logged-in users (will resolve their own customer).
		return true;
	}

	/**
	 * Check if current user is admin.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether user is admin.
	 */
	public static function check_admin_permission( array $input ): bool {
		return current_user_can( 'manage_options' );
	}
}
