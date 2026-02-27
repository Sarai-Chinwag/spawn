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
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
	}

	/**
	 * Register the Spawn ability category.
	 */
	public static function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category( 'spawn', array(
			'label'       => __( 'Spawn Service', 'spawn' ),
			'description' => __( 'Manage AI-powered website service', 'spawn' ),
		) );
	}

	/**
	 * Register all Spawn abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		// Get Status
		wp_register_ability( 'spawn/get-status', array(
			'label'               => __( 'Get Customer Status', 'spawn' ),
			'description'         => __( 'Get current subscription status and credit balance', 'spawn' ),
			'category'            => 'spawn',
			'execute_callback'    => array( Ability_Get_Status::class, 'execute' ),
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => 'Customer ID (defaults to current user)',
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'server_type'       => array( 'type' => 'string' ),
					'status'         => array( 'type' => 'string' ),
					'domain'         => array( 'type' => 'string' ),
					'server_ip'      => array( 'type' => 'string' ),
					'credit_balance' => array( 'type' => 'number' ),
				),
			),
			'permission_callback' => array( __CLASS__, 'check_customer_permission' ),
		) );

		// Scale VPS
		wp_register_ability( 'spawn/scale-vps', array(
			'label'               => __( 'Scale VPS', 'spawn' ),
			'description'         => __( 'Upgrade or downgrade server resources', 'spawn' ),
			'category'            => 'spawn',
			'execute_callback'    => array( Ability_Scale_VPS::class, 'execute' ),
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'new_tier' ),
				'properties' => array(
					'customer_id' => array( 'type' => 'integer' ),
					'new_tier'    => array(
						'type' => 'string',
						'enum' => \Spawn\Config::get_tier_ids(),
					),
				),
			),
			'permission_callback' => array( __CLASS__, 'check_customer_permission' ),
		) );

		// Add Credits
		wp_register_ability( 'spawn/add-credits', array(
			'label'               => __( 'Add Credits', 'spawn' ),
			'description'         => __( 'Add credits to customer balance', 'spawn' ),
			'category'            => 'spawn',
			'execute_callback'    => array( Ability_Add_Credits::class, 'execute' ),
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'amount' ),
				'properties' => array(
					'customer_id' => array( 'type' => 'integer' ),
					'amount'      => array(
						'type'        => 'number',
						'description' => 'Amount in dollars to add',
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'     => array( 'type' => 'boolean' ),
					'added'       => array( 'type' => 'number' ),
					'new_balance' => array( 'type' => 'number' ),
				),
			),
			'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
		) );

		// Comp Customer (create free customer)
		wp_register_ability( 'spawn/comp-customer', array(
			'label'               => __( 'Comp Customer', 'spawn' ),
			'description'         => __( 'Create a comped (free) customer with VPS but no Stripe subscription. Admin use only.', 'spawn' ),
			'category'            => 'spawn',
			'execute_callback'    => array( Ability_Comp_Customer::class, 'execute' ),
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'email' ),
				'properties' => array(
					'email'           => array(
						'type'        => 'string',
						'format'      => 'email',
						'description' => 'Customer email address',
					),
					'tier'            => array(
						'type'        => 'string',
						'enum'        => \Spawn\Config::get_tier_ids(),
						'default'     => 'starter',
						'description' => 'Tier (starter, pro, business)',
					),
					'wants_website'  => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Whether customer wants a website',
					),
					'domain'         => array(
						'type'        => 'string',
						'description' => 'Optional domain for the customer',
					),
					'domain_type'    => array(
						'type'        => 'string',
						'enum'        => array( 'subdomain', 'register', 'byod' ),
						'default'     => 'subdomain',
						'description' => 'Domain type: subdomain, register, or byod',
					),
					'customer_region' => array(
						'type'        => 'string',
						'enum'        => array( 'us', 'eu' ),
						'default'     => 'us',
						'description' => 'Customer region (us or eu)',
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'              => array( 'type' => 'boolean' ),
					'customer_id'          => array( 'type' => 'integer' ),
					'email'               => array( 'type' => 'string' ),
					'tier'                => array( 'type' => 'string' ),
					'billing_type'       => array( 'type' => 'string' ),
					'status'              => array( 'type' => 'string' ),
					'domain'              => array( 'type' => 'string' ),
					'domain_type'        => array( 'type' => 'string' ),
					'provisioning_job_id' => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
		) );

		// Get Usage
		wp_register_ability( 'spawn/get-usage', array(
			'label'               => __( 'Get Usage', 'spawn' ),
			'description'         => __( 'Get usage statistics', 'spawn' ),
			'category'            => 'spawn',
			'execute_callback'    => array( Ability_Get_Usage::class, 'execute' ),
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'customer_id' => array( 'type' => 'integer' ),
					'period'      => array(
						'type'    => 'string',
						'enum'    => array( 'current', 'last_month' ),
						'default' => 'current',
					),
				),
			),
			'permission_callback' => array( __CLASS__, 'check_customer_permission' ),
		) );

		// Cancel
		wp_register_ability( 'spawn/cancel', array(
			'label'               => __( 'Cancel Subscription', 'spawn' ),
			'description'         => __( 'Cancel subscription and schedule VPS deletion after grace period. Provides export instructions.', 'spawn' ),
			'category'            => 'spawn',
			'execute_callback'    => array( Ability_Cancel::class, 'execute' ),
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => 'Customer ID (defaults to current user)',
					),
					'reason'      => array(
						'type'        => 'string',
						'description' => 'Optional reason for cancellation',
					),
					'confirm'     => array(
						'type'        => 'boolean',
						'description' => 'Must be true to proceed with cancellation',
						'default'     => false,
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'             => array( 'type' => 'boolean' ),
					'status'              => array( 'type' => 'string' ),
					'scheduled_deletion'  => array( 'type' => 'string' ),
					'grace_period_days'   => array( 'type' => 'integer' ),
					'message'             => array( 'type' => 'string' ),
					'export_instructions' => array( 'type' => 'object' ),
					'can_reactivate'      => array( 'type' => 'boolean' ),
				),
			),
			'permission_callback' => array( __CLASS__, 'check_customer_permission' ),
		) );

		// Export Site
		wp_register_ability( 'spawn/export-site', array(
			'label'               => __( 'Export Site', 'spawn' ),
			'description'         => __( 'Get instructions to export/backup your WordPress site', 'spawn' ),
			'category'            => 'spawn',
			'execute_callback'    => array( Ability_Export_Site::class, 'execute' ),
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => 'Customer ID (defaults to current user)',
					),
					'format'      => array(
						'type'        => 'string',
						'enum'        => array( 'full', 'xml', 'database' ),
						'default'     => 'full',
						'description' => 'Export format: full (ZIP), xml (WordPress export), database (SQL dump)',
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'      => array( 'type' => 'boolean' ),
					'format'       => array( 'type' => 'string' ),
					'description'  => array( 'type' => 'string' ),
					'instructions' => array( 'type' => 'object' ),
					'download_url' => array( 'type' => 'string' ),
					'notes'        => array( 'type' => 'array' ),
				),
			),
			'permission_callback' => array( __CLASS__, 'check_customer_permission' ),
		) );

		// Manage Billing
		wp_register_ability( 'spawn/manage-billing', array(
			'label'               => __( 'Manage Billing', 'spawn' ),
			'description'         => __( 'Get Stripe customer portal URL', 'spawn' ),
			'category'            => 'spawn',
			'execute_callback'    => array( Ability_Manage_Billing::class, 'execute' ),
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'customer_id' => array( 'type' => 'integer' ),
				),
			),
			'permission_callback' => array( __CLASS__, 'check_customer_permission' ),
		) );

		// Set Auto-Refill
		wp_register_ability( 'spawn/set-auto-refill', array(
			'label'               => __( 'Set Auto-Refill', 'spawn' ),
			'description'         => __( 'Configure automatic credit refill when balance is low', 'spawn' ),
			'category'            => 'spawn',
			'execute_callback'    => array( Ability_Set_Auto_Refill::class, 'execute' ),
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => 'Customer ID (defaults to current user)',
					),
					'enabled'     => array(
						'type'        => 'boolean',
						'description' => 'Enable or disable auto-refill',
					),
					'threshold'   => array(
						'type'        => 'number',
						'description' => 'Trigger refill when balance falls below this amount (in dollars, $1-$100)',
						'minimum'     => 1,
						'maximum'     => 100,
					),
					'amount'      => array(
						'type'        => 'number',
						'description' => 'Amount to refill (in dollars, $10-$100)',
						'minimum'     => 10,
						'maximum'     => 100,
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'customer_id' => array( 'type' => 'integer' ),
					'enabled'     => array( 'type' => 'boolean' ),
					'threshold'   => array( 'type' => 'number' ),
					'amount'      => array( 'type' => 'number' ),
					'message'     => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => array( __CLASS__, 'check_customer_permission' ),
		) );

		// Get Domain Renewal Info
		wp_register_ability( 'spawn/get-domain-renewal-info', array(
			'label'               => __( 'Get Domain Renewal Info', 'spawn' ),
			'description'         => __( 'Get domain expiration info and renewal pricing', 'spawn' ),
			'category'            => 'spawn',
			'execute_callback'    => array( Ability_Get_Domain_Renewal_Info::class, 'execute' ),
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => 'Customer ID (defaults to current user)',
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'renewable'          => array( 'type' => 'boolean' ),
					'domain'             => array( 'type' => 'string' ),
					'domain_type'        => array( 'type' => 'string' ),
					'expires_at'         => array( 'type' => 'string' ),
					'expires_formatted'  => array( 'type' => 'string' ),
					'days_until_expiry'  => array( 'type' => 'integer' ),
					'renewal_price'      => array( 'type' => 'number' ),
					'is_expired'         => array( 'type' => 'boolean' ),
					'is_expiring_soon'   => array( 'type' => 'boolean' ),
					'auto_renew_enabled' => array( 'type' => 'boolean' ),
				),
			),
			'permission_callback' => array( __CLASS__, 'check_customer_permission' ),
		) );

		// Renew Domain
		wp_register_ability( 'spawn/renew-domain', array(
			'label'               => __( 'Renew Domain', 'spawn' ),
			'description'         => __( 'Initiate domain renewal checkout', 'spawn' ),
			'category'            => 'spawn',
			'execute_callback'    => array( Ability_Renew_Domain::class, 'execute' ),
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => 'Customer ID (defaults to current user)',
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'       => array( 'type' => 'boolean' ),
					'checkout_url'  => array( 'type' => 'string' ),
					'session_id'    => array( 'type' => 'string' ),
					'domain'        => array( 'type' => 'string' ),
					'renewal_price' => array( 'type' => 'number' ),
				),
			),
			'permission_callback' => array( __CLASS__, 'check_customer_permission' ),
		) );

		// Search Domain
		wp_register_ability( 'spawn/search-domain', array(
			'label'               => __( 'Search Domain', 'spawn' ),
			'description'         => __( 'Check domain availability and pricing', 'spawn' ),
			'category'            => 'spawn',
			'execute_callback'    => array( Ability_Search_Domain::class, 'execute' ),
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'domain' => array(
						'type'        => 'string',
						'description' => 'Domain name to search (e.g., example.com)',
						'required'    => true,
					),
				),
				'required'   => array( 'domain' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'domain'    => array( 'type' => 'string' ),
					'available' => array( 'type' => 'boolean' ),
					'price'     => array( 'type' => 'number' ),
					'renewal'   => array( 'type' => 'number' ),
					'premium'   => array( 'type' => 'boolean' ),
					'message'   => array( 'type' => 'string' ),
					'next_step' => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => array( __CLASS__, 'check_customer_permission' ),
		) );

		// Register Domain
		wp_register_ability( 'spawn/register-domain', array(
			'label'               => __( 'Register Domain', 'spawn' ),
			'description'         => __( 'Purchase and register a new domain', 'spawn' ),
			'category'            => 'spawn',
			'execute_callback'    => array( Ability_Register_Domain::class, 'execute' ),
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'domain'      => array(
						'type'        => 'string',
						'description' => 'Domain name to register',
						'required'    => true,
					),
					'server_id'   => array(
						'type'        => 'integer',
						'description' => 'Server to assign domain to (optional)',
					),
					'customer_id' => array(
						'type'        => 'integer',
						'description' => 'Customer ID (defaults to current user)',
					),
				),
				'required'   => array( 'domain' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'      => array( 'type' => 'boolean' ),
					'domain'       => array( 'type' => 'string' ),
					'price'        => array( 'type' => 'number' ),
					'checkout_url' => array( 'type' => 'string' ),
					'message'      => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => array( __CLASS__, 'check_customer_permission' ),
		) );

		// Configure BYOD (Bring Your Own Domain)
		wp_register_ability( 'spawn/configure-byod', array(
			'label'               => __( 'Configure Your Own Domain', 'spawn' ),
			'description'         => __( 'Get DNS instructions to connect your own domain', 'spawn' ),
			'category'            => 'spawn',
			'execute_callback'    => array( Ability_Configure_BYOD::class, 'execute' ),
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'domain'      => array(
						'type'        => 'string',
						'description' => 'Your domain name to connect',
						'required'    => true,
					),
					'server_id'   => array(
						'type'        => 'integer',
						'description' => 'Server to connect domain to (optional)',
					),
					'customer_id' => array(
						'type'        => 'integer',
						'description' => 'Customer ID (defaults to current user)',
					),
				),
				'required'   => array( 'domain' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'      => array( 'type' => 'boolean' ),
					'domain'       => array( 'type' => 'string' ),
					'server_ip'    => array( 'type' => 'string' ),
					'instructions' => array( 'type' => 'object' ),
					'next_steps'   => array( 'type' => 'array' ),
					'verification' => array( 'type' => 'object' ),
				),
			),
			'permission_callback' => array( __CLASS__, 'check_customer_permission' ),
		) );

		// Self-spawn abilities removed — agent is installed externally via provisioner.

		// ===== ADMIN DATA ABILITIES =====

		// List Customers
		wp_register_ability( 'spawn/list-customers', array(
			'label'               => __( 'List Customers', 'spawn' ),
			'description'         => __( 'List all Spawn customers with filtering and pagination', 'spawn' ),
			'category'            => 'spawn',
			'execute_callback'    => array( Ability_List_Customers::class, 'execute' ),
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'status' => array(
						'type'        => 'string',
						'description' => 'Filter by status (pending, active, failed, cancelling, deleted)',
						'enum'        => array( 'pending', 'active', 'failed', 'cancelling', 'deleted' ),
					),
					'limit'  => array(
						'type'        => 'integer',
						'description' => 'Maximum number of results (default 50, max 100)',
						'default'     => 50,
						'minimum'     => 1,
						'maximum'     => 100,
					),
					'offset' => array(
						'type'        => 'integer',
						'description' => 'Offset for pagination (default 0)',
						'default'     => 0,
						'minimum'     => 0,
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'customers' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'id'             => array( 'type' => 'integer' ),
								'email'          => array( 'type' => 'string' ),
								'domain'         => array( 'type' => 'string' ),
								'status'         => array( 'type' => 'string' ),
								'tier'           => array( 'type' => 'string' ),
								'credit_balance' => array( 'type' => 'number' ),
								'created_at'     => array( 'type' => 'string' ),
								'server_ip'      => array( 'type' => 'string' ),
							),
						),
					),
					'total'     => array( 'type' => 'integer' ),
					'limit'     => array( 'type' => 'integer' ),
					'offset'    => array( 'type' => 'integer' ),
				),
			),
			'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
		) );

		// Get Customer Details
		wp_register_ability( 'spawn/get-customer', array(
			'label'               => __( 'Get Customer', 'spawn' ),
			'description'         => __( 'Get full customer record by ID or email', 'spawn' ),
			'category'            => 'spawn',
			'execute_callback'    => array( Ability_Get_Customer_Details::class, 'execute' ),
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => 'Customer ID (provide this or email)',
					),
					'email'       => array(
						'type'        => 'string',
						'format'      => 'email',
						'description' => 'Customer email (provide this or customer_id)',
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'id'                    => array( 'type' => 'integer' ),
					'user_id'               => array( 'type' => 'integer' ),
					'email'                 => array( 'type' => 'string' ),
					'domain'                => array( 'type' => 'string' ),
					'subdomain'             => array( 'type' => 'boolean' ),
					'domain_type'           => array( 'type' => 'string' ),
					'tier'                  => array( 'type' => 'string' ),
					'status'                => array( 'type' => 'string' ),
					'server_ip'             => array( 'type' => 'string' ),
					'credit_balance'        => array( 'type' => 'number' ),
					'auto_refill_enabled'   => array( 'type' => 'boolean' ),
					'auto_refill_threshold' => array( 'type' => 'number' ),
					'auto_refill_amount'    => array( 'type' => 'number' ),
					'created_at'            => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
		) );

		// Get Customer Credits
		wp_register_ability( 'spawn/get-customer-credits', array(
			'label'               => __( 'Get Customer Credits', 'spawn' ),
			'description'         => __( 'Get credit balance and usage summary for a customer', 'spawn' ),
			'category'            => 'spawn',
			'execute_callback'    => array( Ability_Get_Customer_Credits::class, 'execute' ),
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'customer_id' ),
				'properties' => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => 'Customer ID',
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'customer_id'     => array( 'type' => 'integer' ),
					'current_credits' => array( 'type' => 'number' ),
					'total_purchased' => array( 'type' => 'number' ),
					'total_used'      => array( 'type' => 'number' ),
				),
			),
			'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
		) );

		// Get Metrics
		wp_register_ability( 'spawn/get-metrics', array(
			'label'               => __( 'Get Metrics', 'spawn' ),
			'description'         => __( 'Get aggregate metrics for the Spawn service', 'spawn' ),
			'category'            => 'spawn',
			'execute_callback'    => array( Ability_Get_Metrics::class, 'execute' ),
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'days' => array(
						'type'        => 'integer',
						'description' => 'Number of days to include in period metrics (default 30, max 365)',
						'default'     => 30,
						'minimum'     => 1,
						'maximum'     => 365,
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'period_days'               => array( 'type' => 'integer' ),
					'total_customers'           => array( 'type' => 'integer' ),
					'active_customers'          => array( 'type' => 'integer' ),
					'new_customers_period'      => array( 'type' => 'integer' ),
					'total_credits_balance'     => array( 'type' => 'number' ),
					'domain_revenue_period'     => array( 'type' => 'number' ),
					'provisioning_success_rate' => array( 'type' => 'number' ),
					'domains_registered'        => array( 'type' => 'integer' ),
					'by_status'                 => array( 'type' => 'object' ),
					'by_tier'                   => array( 'type' => 'object' ),
				),
			),
			'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
		) );

		// List Domains
		wp_register_ability( 'spawn/list-domains', array(
			'label'               => __( 'List Domains', 'spawn' ),
			'description'         => __( 'List registered domains with optional customer filtering', 'spawn' ),
			'category'            => 'spawn',
			'execute_callback'    => array( Ability_List_Domains::class, 'execute' ),
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => 'Filter by customer ID (optional)',
					),
					'limit'       => array(
						'type'        => 'integer',
						'description' => 'Maximum number of results (default 50, max 100)',
						'default'     => 50,
						'minimum'     => 1,
						'maximum'     => 100,
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'domains' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'domain'        => array( 'type' => 'string' ),
								'customer_id'   => array( 'type' => 'integer' ),
								'user_id'       => array( 'type' => 'integer' ),
								'registered_at' => array( 'type' => 'string' ),
								'expires_at'    => array( 'type' => 'string' ),
								'auto_renew'    => array( 'type' => 'boolean' ),
							),
						),
					),
					'count'   => array( 'type' => 'integer' ),
				),
			),
			'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
		) );

		// Get Provisioning Status
		wp_register_ability( 'spawn/get-provisioning-status', array(
			'label'               => __( 'Get Provisioning Status', 'spawn' ),
			'description'         => __( 'Get detailed provisioning status for a customer', 'spawn' ),
			'category'            => 'spawn',
			'execute_callback'    => array( Ability_Get_Provisioning_Status::class, 'execute' ),
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'customer_id' ),
				'properties' => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => 'Customer ID',
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'customer_id'        => array( 'type' => 'integer' ),
					'status'             => array( 'type' => 'string' ),
					'domain'             => array( 'type' => 'string' ),
					'server_ip'          => array( 'type' => 'string' ),
					'provider_server_id'  => array( 'type' => 'string' ),
					'tier'               => array( 'type' => 'string' ),
					'wants_website'      => array( 'type' => 'boolean' ),
					'server_type'       => array( 'type' => 'string' ),
					'server_location'   => array( 'type' => 'string' ),
					'created_at'         => array( 'type' => 'string' ),
					'last_error'         => array( 'type' => 'string' ),
					'job_status'         => array( 'type' => 'object' ),
					'provisioning_state' => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
		) );

		// Send Message to Customer's Agent
		wp_register_ability( 'spawn/send-message', array(
			'label'               => __( 'Send Message to Agent', 'spawn' ),
			'description'         => __( 'Send a message to the customer\'s AI agent', 'spawn' ),
			'category'            => 'spawn',
			'execute_callback'    => array( Ability_Send_Message::class, 'execute' ),
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'message' ),
				'properties' => array(
					'message'     => array(
						'type'        => 'string',
						'description' => 'Message to send to the agent',
					),
					'customer_id' => array(
						'type'        => 'integer',
						'description' => 'Customer ID (defaults to current user\'s customer account)',
					),
					'session_key' => array(
						'type'        => 'string',
						'description' => 'Optional session key for conversation continuity',
					),
					'system_note' => array(
						'type'        => 'string',
						'description' => 'Optional context note for the system prompt',
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'     => array( 'type' => 'boolean' ),
					'customer_id' => array( 'type' => 'integer' ),
					'reply'       => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => array( __CLASS__, 'check_customer_permission' ),
		) );

		// Grant Support Access
		wp_register_ability( 'spawn/grant-support-access', array(
			'label'               => __( 'Grant Support Access', 'spawn' ),
			'description'         => __( 'Grant Spawn support team temporary SSH access to your server', 'spawn' ),
			'category'            => 'spawn',
			'execute_callback'    => array( Ability_Grant_Support_Access::class, 'execute' ),
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'duration_hours' => array(
						'type'        => 'integer',
						'description' => 'How long to grant access (1-72 hours, default 24)',
						'default'     => 24,
						'minimum'     => 1,
						'maximum'     => 72,
					),
					'customer_id'    => array(
						'type'        => 'integer',
						'description' => 'Customer ID (defaults to current user\'s customer account)',
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'        => array( 'type' => 'boolean' ),
					'customer_id'    => array( 'type' => 'integer' ),
					'duration_hours' => array( 'type' => 'integer' ),
					'expires_at'     => array( 'type' => 'string' ),
					'agent_reply'    => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => array( __CLASS__, 'check_customer_permission' ),
		) );

		// Revoke Support Access
		wp_register_ability( 'spawn/revoke-support-access', array(
			'label'               => __( 'Revoke Support Access', 'spawn' ),
			'description'         => __( 'Remove Spawn support team SSH access from your server', 'spawn' ),
			'category'            => 'spawn',
			'execute_callback'    => array( Ability_Revoke_Support_Access::class, 'execute' ),
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => 'Customer ID (defaults to current user\'s customer account)',
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'     => array( 'type' => 'boolean' ),
					'customer_id' => array( 'type' => 'integer' ),
					'revoked_at'  => array( 'type' => 'string' ),
					'agent_reply' => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => array( __CLASS__, 'check_customer_permission' ),
		) );
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
