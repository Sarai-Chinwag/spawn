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
		wp_register_ability( 'spawn/get-status', [
			'label'       => __( 'Get Customer Status', 'spawn' ),
			'description' => __( 'Get current subscription status and credit balance', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Get_Status::class, 'execute' ],
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
		wp_register_ability( 'spawn/scale-vps', [
			'label'       => __( 'Scale VPS', 'spawn' ),
			'description' => __( 'Upgrade or downgrade server resources', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Scale_VPS::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'new_tier' ],
				'properties' => [
					'customer_id' => [ 'type' => 'integer' ],
					'new_tier'    => [
						'type' => 'string',
						'enum' => \Spawn\Config::get_tier_ids(),
					],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_customer_permission' ],
		] );

		// Add Credits
		wp_register_ability( 'spawn/add-credits', [
			'label'       => __( 'Add Credits', 'spawn' ),
			'description' => __( 'Add credits to customer balance', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Add_Credits::class, 'execute' ],
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
		wp_register_ability( 'spawn/get-usage', [
			'label'       => __( 'Get Usage', 'spawn' ),
			'description' => __( 'Get usage statistics', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Get_Usage::class, 'execute' ],
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
		wp_register_ability( 'spawn/cancel', [
			'label'       => __( 'Cancel Subscription', 'spawn' ),
			'description' => __( 'Cancel subscription and schedule VPS deletion after grace period. Provides export instructions.', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Cancel::class, 'execute' ],
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
		wp_register_ability( 'spawn/export-site', [
			'label'       => __( 'Export Site', 'spawn' ),
			'description' => __( 'Get instructions to export/backup your WordPress site', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Export_Site::class, 'execute' ],
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
		wp_register_ability( 'spawn/manage-billing', [
			'label'       => __( 'Manage Billing', 'spawn' ),
			'description' => __( 'Get Stripe customer portal URL', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Manage_Billing::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'customer_id' => [ 'type' => 'integer' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_customer_permission' ],
		] );

		// Set Auto-Refill
		wp_register_ability( 'spawn/set-auto-refill', [
			'label'       => __( 'Set Auto-Refill', 'spawn' ),
			'description' => __( 'Configure automatic credit refill when balance is low', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Set_Auto_Refill::class, 'execute' ],
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
		wp_register_ability( 'spawn/get-domain-renewal-info', [
			'label'       => __( 'Get Domain Renewal Info', 'spawn' ),
			'description' => __( 'Get domain expiration info and renewal pricing', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Get_Domain_Renewal_Info::class, 'execute' ],
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
		wp_register_ability( 'spawn/renew-domain', [
			'label'       => __( 'Renew Domain', 'spawn' ),
			'description' => __( 'Initiate domain renewal checkout', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Renew_Domain::class, 'execute' ],
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

		// Search Domain
		wp_register_ability( 'spawn/search-domain', [
			'label'       => __( 'Search Domain', 'spawn' ),
			'description' => __( 'Check domain availability and pricing', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Search_Domain::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'domain' => [
						'type'        => 'string',
						'description' => 'Domain name to search (e.g., example.com)',
						'required'    => true,
					],
				],
				'required'   => [ 'domain' ],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'domain'    => [ 'type' => 'string' ],
					'available' => [ 'type' => 'boolean' ],
					'price'     => [ 'type' => 'number' ],
					'renewal'   => [ 'type' => 'number' ],
					'premium'   => [ 'type' => 'boolean' ],
					'message'   => [ 'type' => 'string' ],
					'next_step' => [ 'type' => 'string' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_customer_permission' ],
		] );

		// Register Domain
		wp_register_ability( 'spawn/register-domain', [
			'label'       => __( 'Register Domain', 'spawn' ),
			'description' => __( 'Purchase and register a new domain', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Register_Domain::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'domain' => [
						'type'        => 'string',
						'description' => 'Domain name to register',
						'required'    => true,
					],
					'server_id' => [
						'type'        => 'integer',
						'description' => 'Server to assign domain to (optional)',
					],
					'customer_id' => [
						'type'        => 'integer',
						'description' => 'Customer ID (defaults to current user)',
					],
				],
				'required'   => [ 'domain' ],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success'      => [ 'type' => 'boolean' ],
					'domain'       => [ 'type' => 'string' ],
					'price'        => [ 'type' => 'number' ],
					'checkout_url' => [ 'type' => 'string' ],
					'message'      => [ 'type' => 'string' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_customer_permission' ],
		] );

		// Configure BYOD (Bring Your Own Domain)
		wp_register_ability( 'spawn/configure-byod', [
			'label'       => __( 'Configure Your Own Domain', 'spawn' ),
			'description' => __( 'Get DNS instructions to connect your own domain', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Configure_BYOD::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'domain' => [
						'type'        => 'string',
						'description' => 'Your domain name to connect',
						'required'    => true,
					],
					'server_id' => [
						'type'        => 'integer',
						'description' => 'Server to connect domain to (optional)',
					],
					'customer_id' => [
						'type'        => 'integer',
						'description' => 'Customer ID (defaults to current user)',
					],
				],
				'required'   => [ 'domain' ],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success'      => [ 'type' => 'boolean' ],
					'domain'       => [ 'type' => 'string' ],
					'server_ip'    => [ 'type' => 'string' ],
					'instructions' => [ 'type' => 'object' ],
					'next_steps'   => [ 'type' => 'array' ],
					'verification' => [ 'type' => 'object' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_customer_permission' ],
		] );

		// ===== SELF-SPAWN ABILITIES =====

		// Check Environment
		wp_register_ability( 'spawn/self-check-environment', [
			'label'       => __( 'Check Environment', 'spawn' ),
			'description' => __( 'Check if server environment supports self-spawn OpenClaw installation', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Self_Spawn::class, 'check_environment' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'environment'     => [ 'type' => 'object' ],
					'credentials'     => [ 'type' => 'object' ],
					'has_credentials' => [ 'type' => 'boolean' ],
					'can_install'     => [ 'type' => 'boolean' ],
					'blockers'        => [ 'type' => 'array' ],
					'credentials_url' => [ 'type' => 'string' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
		] );

		// Get Self-Spawn Status
		wp_register_ability( 'spawn/self-get-status', [
			'label'       => __( 'Get OpenClaw Status', 'spawn' ),
			'description' => __( 'Get status of locally installed OpenClaw', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Self_Spawn::class, 'get_status' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'installed'     => [ 'type' => 'boolean' ],
					'running'       => [ 'type' => 'boolean' ],
					'gateway_url'   => [ 'type' => 'string' ],
					'version'       => [ 'type' => 'string' ],
					'config_exists' => [ 'type' => 'boolean' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
		] );

		// Install OpenClaw
		wp_register_ability( 'spawn/self-install', [
			'label'       => __( 'Install OpenClaw', 'spawn' ),
			'description' => __( 'Install OpenClaw locally on this server (self-spawn)', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Self_Spawn::class, 'install' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success'  => [ 'type' => 'boolean' ],
					'message'  => [ 'type' => 'string' ],
					'blockers' => [ 'type' => 'array' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
		] );

		// Configure OpenClaw
		wp_register_ability( 'spawn/self-configure', [
			'label'       => __( 'Configure OpenClaw', 'spawn' ),
			'description' => __( 'Update OpenClaw configuration with current AI credentials', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Self_Spawn::class, 'configure' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success' => [ 'type' => 'boolean' ],
					'message' => [ 'type' => 'string' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
		] );

		// Start OpenClaw
		wp_register_ability( 'spawn/self-start', [
			'label'       => __( 'Start OpenClaw', 'spawn' ),
			'description' => __( 'Start the OpenClaw gateway service', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Self_Spawn::class, 'start' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success' => [ 'type' => 'boolean' ],
					'message' => [ 'type' => 'string' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
		] );

		// Stop OpenClaw
		wp_register_ability( 'spawn/self-stop', [
			'label'       => __( 'Stop OpenClaw', 'spawn' ),
			'description' => __( 'Stop the OpenClaw gateway service', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Self_Spawn::class, 'stop' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success' => [ 'type' => 'boolean' ],
					'message' => [ 'type' => 'string' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
		] );

		// Restart OpenClaw
		wp_register_ability( 'spawn/self-restart', [
			'label'       => __( 'Restart OpenClaw', 'spawn' ),
			'description' => __( 'Restart the OpenClaw gateway service', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Self_Spawn::class, 'restart' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success' => [ 'type' => 'boolean' ],
					'message' => [ 'type' => 'string' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
		] );

		// Uninstall OpenClaw
		wp_register_ability( 'spawn/self-uninstall', [
			'label'       => __( 'Uninstall OpenClaw', 'spawn' ),
			'description' => __( 'Remove OpenClaw from this server', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Self_Spawn::class, 'uninstall' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'confirm' => [
						'type'        => 'boolean',
						'description' => 'Must be true to confirm uninstallation',
						'default'     => false,
					],
				],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success' => [ 'type' => 'boolean' ],
					'message' => [ 'type' => 'string' ],
					'warning' => [ 'type' => 'string' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
		] );

		// ===== ADMIN DATA ABILITIES =====

		// List Customers
		wp_register_ability( 'spawn/list-customers', [
			'label'       => __( 'List Customers', 'spawn' ),
			'description' => __( 'List all Spawn customers with filtering and pagination', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_List_Customers::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'status' => [
						'type'        => 'string',
						'description' => 'Filter by status (pending, active, failed, cancelling, deleted)',
						'enum'        => [ 'pending', 'active', 'failed', 'cancelling', 'deleted' ],
					],
					'limit'  => [
						'type'        => 'integer',
						'description' => 'Maximum number of results (default 50, max 100)',
						'default'     => 50,
						'minimum'     => 1,
						'maximum'     => 100,
					],
					'offset' => [
						'type'        => 'integer',
						'description' => 'Offset for pagination (default 0)',
						'default'     => 0,
						'minimum'     => 0,
					],
				],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'customers' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'id'             => [ 'type' => 'integer' ],
								'email'          => [ 'type' => 'string' ],
								'domain'         => [ 'type' => 'string' ],
								'status'         => [ 'type' => 'string' ],
								'tier'           => [ 'type' => 'string' ],
								'credit_balance' => [ 'type' => 'number' ],
								'created_at'     => [ 'type' => 'string' ],
								'server_ip'      => [ 'type' => 'string' ],
							],
						],
					],
					'total'     => [ 'type' => 'integer' ],
					'limit'     => [ 'type' => 'integer' ],
					'offset'    => [ 'type' => 'integer' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
		] );

		// Get Customer Details
		wp_register_ability( 'spawn/get-customer', [
			'label'       => __( 'Get Customer', 'spawn' ),
			'description' => __( 'Get full customer record by ID or email', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Get_Customer_Details::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'customer_id' => [
						'type'        => 'integer',
						'description' => 'Customer ID (provide this or email)',
					],
					'email'       => [
						'type'        => 'string',
						'format'      => 'email',
						'description' => 'Customer email (provide this or customer_id)',
					],
				],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'id'                    => [ 'type' => 'integer' ],
					'user_id'               => [ 'type' => 'integer' ],
					'email'                 => [ 'type' => 'string' ],
					'domain'                => [ 'type' => 'string' ],
					'subdomain'             => [ 'type' => 'boolean' ],
					'domain_type'           => [ 'type' => 'string' ],
					'tier'                  => [ 'type' => 'string' ],
					'status'                => [ 'type' => 'string' ],
					'server_ip'             => [ 'type' => 'string' ],
					'credit_balance'        => [ 'type' => 'number' ],
					'auto_refill_enabled'   => [ 'type' => 'boolean' ],
					'auto_refill_threshold' => [ 'type' => 'number' ],
					'auto_refill_amount'    => [ 'type' => 'number' ],
					'created_at'            => [ 'type' => 'string' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
		] );

		// Get Customer Credits
		wp_register_ability( 'spawn/get-customer-credits', [
			'label'       => __( 'Get Customer Credits', 'spawn' ),
			'description' => __( 'Get credit balance and usage summary for a customer', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Get_Customer_Credits::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'customer_id' ],
				'properties' => [
					'customer_id' => [
						'type'        => 'integer',
						'description' => 'Customer ID',
					],
				],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'customer_id'     => [ 'type' => 'integer' ],
					'current_credits' => [ 'type' => 'number' ],
					'total_purchased' => [ 'type' => 'number' ],
					'total_used'      => [ 'type' => 'number' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
		] );

		// Get Metrics
		wp_register_ability( 'spawn/get-metrics', [
			'label'       => __( 'Get Metrics', 'spawn' ),
			'description' => __( 'Get aggregate metrics for the Spawn service', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Get_Metrics::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'days' => [
						'type'        => 'integer',
						'description' => 'Number of days to include in period metrics (default 30, max 365)',
						'default'     => 30,
						'minimum'     => 1,
						'maximum'     => 365,
					],
				],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'period_days'               => [ 'type' => 'integer' ],
					'total_customers'           => [ 'type' => 'integer' ],
					'active_customers'          => [ 'type' => 'integer' ],
					'new_customers_period'      => [ 'type' => 'integer' ],
					'total_credits_balance'     => [ 'type' => 'number' ],
					'domain_revenue_period'     => [ 'type' => 'number' ],
					'provisioning_success_rate' => [ 'type' => 'number' ],
					'domains_registered'        => [ 'type' => 'integer' ],
					'by_status'                 => [ 'type' => 'object' ],
					'by_tier'                   => [ 'type' => 'object' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
		] );

		// List Domains
		wp_register_ability( 'spawn/list-domains', [
			'label'       => __( 'List Domains', 'spawn' ),
			'description' => __( 'List registered domains with optional customer filtering', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_List_Domains::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'customer_id' => [
						'type'        => 'integer',
						'description' => 'Filter by customer ID (optional)',
					],
					'limit'       => [
						'type'        => 'integer',
						'description' => 'Maximum number of results (default 50, max 100)',
						'default'     => 50,
						'minimum'     => 1,
						'maximum'     => 100,
					],
				],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'domains' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'domain'        => [ 'type' => 'string' ],
								'customer_id'   => [ 'type' => 'integer' ],
								'user_id'       => [ 'type' => 'integer' ],
								'registered_at' => [ 'type' => 'string' ],
								'expires_at'    => [ 'type' => 'string' ],
								'auto_renew'    => [ 'type' => 'boolean' ],
							],
						],
					],
					'count'   => [ 'type' => 'integer' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
		] );

		// Get Provisioning Status
		wp_register_ability( 'spawn/get-provisioning-status', [
			'label'       => __( 'Get Provisioning Status', 'spawn' ),
			'description' => __( 'Get detailed provisioning status for a customer', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Get_Provisioning_Status::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'customer_id' ],
				'properties' => [
					'customer_id' => [
						'type'        => 'integer',
						'description' => 'Customer ID',
					],
				],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'customer_id'        => [ 'type' => 'integer' ],
					'status'             => [ 'type' => 'string' ],
					'domain'             => [ 'type' => 'string' ],
					'server_ip'          => [ 'type' => 'string' ],
					'hetzner_server_id'  => [ 'type' => 'string' ],
					'tier'               => [ 'type' => 'string' ],
					'wants_website'      => [ 'type' => 'boolean' ],
					'hetzner_type'       => [ 'type' => 'string' ],
					'hetzner_location'   => [ 'type' => 'string' ],
					'created_at'         => [ 'type' => 'string' ],
					'last_error'         => [ 'type' => 'string' ],
					'job_status'         => [ 'type' => 'object' ],
					'provisioning_state' => [ 'type' => 'string' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_admin_permission' ],
		] );

		// Send Message to Customer's Agent
		wp_register_ability( 'spawn/send-message', [
			'label'       => __( 'Send Message to Agent', 'spawn' ),
			'description' => __( 'Send a message to the customer\'s AI agent', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Send_Message::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'message' ],
				'properties' => [
					'message' => [
						'type'        => 'string',
						'description' => 'Message to send to the agent',
					],
					'customer_id' => [
						'type'        => 'integer',
						'description' => 'Customer ID (defaults to current user\'s customer account)',
					],
					'session_key' => [
						'type'        => 'string',
						'description' => 'Optional session key for conversation continuity',
					],
					'system_note' => [
						'type'        => 'string',
						'description' => 'Optional context note for the system prompt',
					],
				],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success'     => [ 'type' => 'boolean' ],
					'customer_id' => [ 'type' => 'integer' ],
					'reply'       => [ 'type' => 'string' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_customer_permission' ],
		] );

		// Grant Support Access
		wp_register_ability( 'spawn/grant-support-access', [
			'label'       => __( 'Grant Support Access', 'spawn' ),
			'description' => __( 'Grant Spawn support team temporary SSH access to your server', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Grant_Support_Access::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'duration_hours' => [
						'type'        => 'integer',
						'description' => 'How long to grant access (1-72 hours, default 24)',
						'default'     => 24,
						'minimum'     => 1,
						'maximum'     => 72,
					],
					'customer_id' => [
						'type'        => 'integer',
						'description' => 'Customer ID (defaults to current user\'s customer account)',
					],
				],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success'        => [ 'type' => 'boolean' ],
					'customer_id'    => [ 'type' => 'integer' ],
					'duration_hours' => [ 'type' => 'integer' ],
					'expires_at'     => [ 'type' => 'string' ],
					'agent_reply'    => [ 'type' => 'string' ],
				],
			],
			'permission_callback' => [ __CLASS__, 'check_customer_permission' ],
		] );

		// Revoke Support Access
		wp_register_ability( 'spawn/revoke-support-access', [
			'label'       => __( 'Revoke Support Access', 'spawn' ),
			'description' => __( 'Remove Spawn support team SSH access from your server', 'spawn' ),
			'category'    => 'spawn',
			'execute_callback'    => [ Ability_Revoke_Support_Access::class, 'execute' ],
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'customer_id' => [
						'type'        => 'integer',
						'description' => 'Customer ID (defaults to current user\'s customer account)',
					],
				],
			],
			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success'     => [ 'type' => 'boolean' ],
					'customer_id' => [ 'type' => 'integer' ],
					'revoked_at'  => [ 'type' => 'string' ],
					'agent_reply' => [ 'type' => 'string' ],
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
