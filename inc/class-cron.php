<?php
/**
 * Cron jobs for Spawn.
 *
 * @package Spawn
 */

declare(strict_types=1);

namespace Spawn;

use WP_Error;

/**
 * Handles scheduled cron jobs.
 */
class Cron {

	/**
	 * Cron hook name for domain renewal.
	 */
	public const RENEWAL_HOOK = 'spawn_domain_renewal_check';

	/**
	 * Warning intervals in days before expiry.
	 */
	private const WARNING_INTERVALS = [ 30, 14, 7, 1 ];

	/**
	 * Days before expiry to trigger auto-renewal (if enabled).
	 */
	private const AUTO_RENEW_THRESHOLD_DAYS = 7;

	/**
	 * Low balance threshold for warning email (in dollars).
	 */
	private const LOW_BALANCE_WARNING_THRESHOLD = 2.00;

	/**
	 * Initialize cron handlers.
	 */
	public static function init(): void {
		add_action( self::RENEWAL_HOOK, [ __CLASS__, 'process_domain_renewals' ] );

		// Auto-refill handlers.
		add_action( 'spawn_credits_auto_refill_needed', [ __CLASS__, 'handle_auto_refill_needed' ], 10, 2 );
		add_action( 'spawn_auto_refill_success', [ __CLASS__, 'send_auto_refill_success_email' ], 10, 3 );
		add_action( 'spawn_auto_refill_failed', [ __CLASS__, 'send_auto_refill_failed_email' ], 10, 2 );
	}

	/**
	 * Schedule cron events on plugin activation.
	 */
	public static function schedule_events(): void {
		if ( ! wp_next_scheduled( self::RENEWAL_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::RENEWAL_HOOK );
		}
	}

	/**
	 * Unschedule cron events on plugin deactivation.
	 */
	public static function unschedule_events(): void {
		$timestamp = wp_next_scheduled( self::RENEWAL_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::RENEWAL_HOOK );
		}
	}

	/**
	 * Process domain renewals - sends warning emails for expiring domains.
	 *
	 * Does NOT auto-renew. Customers must renew manually via dashboard.
	 */
	public static function process_domain_renewals(): void {
		// Get all domains expiring within 30 days.
		$expiring = Database::get_expiring_domains( 30 );

		if ( empty( $expiring ) ) {
			self::log( 'No domains expiring within 30 days' );
			return;
		}

		self::log( sprintf( 'Found %d domains expiring soon', count( $expiring ) ) );

		foreach ( $expiring as $customer ) {
			self::process_renewal_warnings( $customer );
		}
	}

	/**
	 * Process renewal warnings for a single customer.
	 *
	 * Behavior depends on domain_auto_renew setting:
	 * - OFF: Sends warning emails at 30, 14, 7, and 1 day(s) before expiry
	 * - ON: Sends warnings at 30, 14 days; auto-renews at 7 days
	 *
	 * Each warning level is only sent once.
	 *
	 * @param array $customer Customer data from database.
	 */
	private static function process_renewal_warnings( array $customer ): void {
		$customer_id    = (int) $customer['id'];
		$domain         = $customer['domain'];
		$expires_at     = $customer['domain_expires_at'];
		$auto_renew     = (bool) ( $customer['domain_auto_renew'] ?? false );

		// Calculate days until expiry.
		$expires_timestamp = strtotime( $expires_at );
		$now_timestamp     = time();
		$days_until_expiry = (int) ceil( ( $expires_timestamp - $now_timestamp ) / DAY_IN_SECONDS );

		self::log( sprintf(
			'Checking renewal for customer #%d, domain: %s, expires: %s (in %d days), auto_renew: %s',
			$customer_id,
			$domain,
			$expires_at,
			$days_until_expiry,
			$auto_renew ? 'ON' : 'OFF'
		) );

		// Check if auto-renewal should be attempted.
		if ( $auto_renew && $days_until_expiry <= self::AUTO_RENEW_THRESHOLD_DAYS ) {
			$renewed = self::attempt_auto_renewal( $customer );
			if ( $renewed ) {
				// Success - no need for further warnings.
				return;
			}
			// Auto-renewal failed - fall through to send warning emails.
			self::log( sprintf(
				'Auto-renewal failed for %s, falling back to warning emails',
				$domain
			), 'warning' );
		}

		// Get warnings already sent.
		$warnings_sent = self::get_warnings_sent( $customer_id );

		// Determine which warning to send (if any).
		$warning_to_send = null;
		foreach ( self::WARNING_INTERVALS as $interval ) {
			// Send warning if we're at or past this threshold and haven't sent it yet.
			if ( $days_until_expiry <= $interval && ! in_array( $interval, $warnings_sent, true ) ) {
				$warning_to_send = $interval;
				break; // Send the most urgent unsent warning.
			}
		}

		if ( null === $warning_to_send ) {
			self::log( sprintf(
				'No new warnings needed for %s (days left: %d, warnings sent: %s)',
				$domain,
				$days_until_expiry,
				implode( ', ', $warnings_sent ) ?: 'none'
			) );
			return;
		}

		// Send the warning email.
		$sent = self::send_renewal_warning( $customer, $warning_to_send, $days_until_expiry, $auto_renew );

		if ( $sent ) {
			// Record that this warning was sent.
			$warnings_sent[] = $warning_to_send;
			self::set_warnings_sent( $customer_id, $warnings_sent );

			self::log( sprintf(
				'Sent %d-day warning email for %s to %s',
				$warning_to_send,
				$domain,
				$customer['email']
			) );
		}
	}

	/**
	 * Attempt automatic domain renewal.
	 *
	 * Charges the customer's payment method and renews the domain via Name.com.
	 *
	 * @param array $customer Customer data.
	 * @return bool True if renewal succeeded, false otherwise.
	 */
	private static function attempt_auto_renewal( array $customer ): bool {
		$customer_id = (int) $customer['id'];
		$domain      = $customer['domain'];
		$email       = $customer['email'];

		self::log( sprintf(
			'Attempting auto-renewal for customer #%d, domain: %s',
			$customer_id,
			$domain
		) );

		// Get renewal price from Name.com.
		$renewal_price = Name_Com::get_renewal_price( $domain );

		if ( is_wp_error( $renewal_price ) ) {
			self::log( sprintf(
				'Failed to get renewal price for %s: %s',
				$domain,
				$renewal_price->get_error_message()
			), 'error' );
			self::send_auto_renewal_failure_notification( $customer, $renewal_price->get_error_message() );
			return false;
		}

		// Apply markup.
		$markup        = (float) get_option( 'spawn_domain_markup', 1.5 );
		$marked_up_price = round( $renewal_price * $markup, 2 );

		self::log( sprintf( 'Renewal price for %s: $%.2f (with markup)', $domain, $marked_up_price ) );

		// Charge customer's payment method.
		$charge_result = self::charge_for_renewal( $customer, $marked_up_price );

		if ( is_wp_error( $charge_result ) ) {
			self::log( sprintf(
				'Payment failed for %s: %s',
				$domain,
				$charge_result->get_error_message()
			), 'error' );
			self::send_auto_renewal_failure_notification( $customer, 'Payment failed: ' . $charge_result->get_error_message() );
			return false;
		}

		self::log( sprintf( 'Payment successful for %s, PaymentIntent: %s', $domain, $charge_result['id'] ?? 'unknown' ) );

		// Renew domain via Name.com API.
		$renewal_result = Name_Com::renew( $domain, 1 );

		if ( is_wp_error( $renewal_result ) ) {
			self::log( sprintf(
				'Name.com renewal failed for %s: %s',
				$domain,
				$renewal_result->get_error_message()
			), 'error' );
			// Payment succeeded but renewal failed - this needs manual intervention.
			self::send_auto_renewal_failure_notification(
				$customer,
				'Domain renewal API failed after successful payment. Manual intervention required: ' . $renewal_result->get_error_message()
			);
			// Notify admin urgently.
			$admin_email = get_option( 'admin_email' );
			wp_mail(
				$admin_email,
				sprintf( '[Spawn] URGENT: Auto-renewal failed after payment: %s', $domain ),
				sprintf(
					"Domain auto-renewal API call failed after successful payment.\n\n" .
					"Customer ID: %d\nDomain: %s\nEmail: %s\nError: %s\n\n" .
					"MANUAL INTERVENTION REQUIRED: Renew the domain manually via Name.com dashboard.",
					$customer_id,
					$domain,
					$email,
					$renewal_result->get_error_message()
				)
			);
			return false;
		}

		// Update database with new expiration.
		$db_updated = Database::renew_domain( $customer_id );

		if ( ! $db_updated ) {
			self::log( sprintf( 'Failed to update database for customer #%d', $customer_id ), 'error' );
		}

		// Clear renewal warnings.
		self::clear_warnings_sent( $customer_id );

		self::log( sprintf(
			'Successfully auto-renewed %s for customer #%d, new expiration: %s',
			$domain,
			$customer_id,
			$renewal_result['expires_at'] ?? 'unknown'
		) );

		// Send success notification.
		self::send_auto_renewal_success_notification( $customer, $marked_up_price, $renewal_result['expires_at'] ?? null );

		/**
		 * Fires after a domain is successfully auto-renewed.
		 *
		 * @param int    $customer_id   Spawn customer ID.
		 * @param string $domain        Domain name.
		 * @param float  $renewal_price Price charged for renewal.
		 * @param array  $renewal_result Result from Name.com API.
		 */
		do_action( 'spawn_domain_auto_renewed', $customer_id, $domain, $marked_up_price, $renewal_result );

		return true;
	}

	/**
	 * Charge customer for domain renewal.
	 *
	 * @param array $customer      Customer data.
	 * @param float $renewal_price Price in dollars.
	 * @return array|\WP_Error PaymentIntent result or error.
	 */
	private static function charge_for_renewal( array $customer, float $renewal_price ): array|\WP_Error {
		$stripe_customer_id = $customer['stripe_customer'] ?? '';
		$payment_method_id  = $customer['stripe_payment_method'] ?? '';
		$customer_id        = (int) $customer['id'];

		if ( empty( $stripe_customer_id ) ) {
			return new \WP_Error(
				'no_stripe_customer',
				__( 'No Stripe customer ID on file', 'spawn' )
			);
		}

		// Get payment method if not stored locally.
		if ( empty( $payment_method_id ) ) {
			$payment_method_id = Payment_Helpers::get_default_payment_method( $stripe_customer_id );

			if ( is_wp_error( $payment_method_id ) ) {
				return $payment_method_id;
			}

			// Cache it for future use.
			Database::update_payment_method( $customer_id, $payment_method_id );
		}

		$amount_cents = (int) round( $renewal_price * 100 );

		return \StripeIntegration\StripeClient::create_payment_intent( [
			'amount'         => $amount_cents,
			'currency'       => 'usd',
			'customer'       => $stripe_customer_id,
			'payment_method' => $payment_method_id,
			'off_session'    => true,
			'confirm'        => true,
			'description'    => sprintf(
				/* translators: %s: domain name */
				__( 'Domain renewal: %s (1 year)', 'spawn' ),
				$customer['domain']
			),
			'metadata'       => [
				'type'              => 'domain_auto_renewal',
				'domain'            => $customer['domain'],
				'spawn_customer_id' => $customer_id,
				'source'            => 'spawn',
			],
		] );
	}

	/**
	 * Send auto-renewal success notification email.
	 *
	 * @param array       $customer      Customer data.
	 * @param float       $renewal_price Price charged.
	 * @param string|null $new_expires   New expiration date.
	 */
	private static function send_auto_renewal_success_notification( array $customer, float $renewal_price, ?string $new_expires ): void {
		$email   = $customer['email'];
		$domain  = $customer['domain'];
		$subject = sprintf(
			/* translators: %s: domain name */
			__( 'Your domain %s has been automatically renewed', 'spawn' ),
			$domain
		);

		$expires_formatted = $new_expires
			? wp_date( 'F j, Y', strtotime( $new_expires ) )
			: __( 'in approximately one year', 'spawn' );

		$message = sprintf(
			/* translators: 1: domain name, 2: price, 3: expiration date */
			__(
				"Hello,\n\n" .
				"Your domain %1\$s has been automatically renewed for one year.\n\n" .
				"Amount charged: $%2\$.2f\n" .
				"New expiration date: %3\$s\n\n" .
				"You can manage your auto-renewal settings in your dashboard under Settings → Domain.\n\n" .
				"Thank you for using Spawn!\n\n" .
				"—The Spawn Team",
				'spawn'
			),
			$domain,
			$renewal_price,
			$expires_formatted
		);

		$sent = wp_mail( $email, $subject, $message );

		if ( ! $sent ) {
			self::log( sprintf( 'Failed to send auto-renewal success email to %s', $email ), 'error' );
		}
	}

	/**
	 * Send auto-renewal failure notification email.
	 *
	 * @param array  $customer Customer data.
	 * @param string $reason   Failure reason.
	 */
	private static function send_auto_renewal_failure_notification( array $customer, string $reason ): void {
		$email   = $customer['email'];
		$domain  = $customer['domain'];
		$expires = $customer['domain_expires_at'];
		$subject = sprintf(
			/* translators: %s: domain name */
			__( 'Action required: Domain auto-renewal failed for %s', 'spawn' ),
			$domain
		);

		$expires_formatted = wp_date( 'F j, Y', strtotime( $expires ) );
		$renewal_url       = add_query_arg( [
			'action' => 'renew',
			'domain' => $domain,
		], home_url( '/spawn/dashboard/' ) );

		$message = sprintf(
			/* translators: 1: domain name, 2: expiration date, 3: failure reason, 4: renewal URL */
			__(
				"Hello,\n\n" .
				"We were unable to automatically renew your domain %1\$s.\n\n" .
				"Current expiration date: %2\$s\n" .
				"Reason: %3\$s\n\n" .
				"Please renew manually to avoid losing your domain:\n%4\$s\n\n" .
				"You may need to update your payment method in your dashboard under Settings → Billing.\n\n" .
				"—The Spawn Team",
				'spawn'
			),
			$domain,
			$expires_formatted,
			$reason,
			$renewal_url
		);

		$sent = wp_mail( $email, $subject, $message );

		if ( ! $sent ) {
			self::log( sprintf( 'Failed to send auto-renewal failure email to %s', $email ), 'error' );
		}

		// Also notify admin.
		$admin_email = get_option( 'admin_email' );
		wp_mail(
			$admin_email,
			sprintf( '[Spawn] Domain auto-renewal failed: %s', $domain ),
			sprintf(
				"Domain auto-renewal failed for customer #%d\n\n" .
				"Domain: %s\nEmail: %s\nExpires: %s\nReason: %s",
				$customer['id'],
				$domain,
				$email,
				$expires_formatted,
				$reason
			)
		);
	}

	/**
	 * Get list of warning intervals already sent for a customer.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array Array of warning intervals (e.g., [30, 14]).
	 */
	private static function get_warnings_sent( int $customer_id ): array {
		$customer = Database::get_customer( $customer_id );
		if ( ! $customer ) {
			return [];
		}

		$warnings_json = $customer['renewal_warnings_sent'] ?? '[]';
		$warnings      = json_decode( $warnings_json, true );

		return is_array( $warnings ) ? $warnings : [];
	}

	/**
	 * Set list of warning intervals sent for a customer.
	 *
	 * @param int   $customer_id Customer ID.
	 * @param array $warnings    Array of warning intervals sent.
	 */
	private static function set_warnings_sent( int $customer_id, array $warnings ): void {
		Database::update_customer( $customer_id, [
			'renewal_warnings_sent' => wp_json_encode( array_values( array_unique( $warnings ) ) ),
		] );
	}

	/**
	 * Clear warnings sent (called after successful manual renewal).
	 *
	 * @param int $customer_id Customer ID.
	 */
	public static function clear_warnings_sent( int $customer_id ): void {
		self::set_warnings_sent( $customer_id, [] );
	}

	/**
	 * Send renewal warning email.
	 *
	 * @param array $customer           Customer data.
	 * @param int   $warning_interval   Warning interval (30, 14, 7, or 1).
	 * @param int   $days_until_expiry  Actual days until expiry.
	 * @param bool  $auto_renew_enabled Whether auto-renewal is enabled for this customer.
	 * @return bool Whether the email was sent.
	 */
	private static function send_renewal_warning( array $customer, int $warning_interval, int $days_until_expiry, bool $auto_renew_enabled = false ): bool {
		$email   = $customer['email'];
		$domain  = $customer['domain'];
		$expires = $customer['domain_expires_at'];

		// Build subject line based on urgency.
		$subject = match ( $warning_interval ) {
			30      => sprintf( __( 'Your domain %s expires in 30 days', 'spawn' ), $domain ),
			14      => sprintf( __( 'Your domain %s expires in 2 weeks', 'spawn' ), $domain ),
			7       => sprintf( __( 'URGENT: Your domain %s expires in 7 days', 'spawn' ), $domain ),
			1       => sprintf( __( 'FINAL WARNING: Your domain %s expires tomorrow', 'spawn' ), $domain ),
			default => sprintf( __( 'Your domain %s is expiring soon', 'spawn' ), $domain ),
		};

		// Build renewal URL.
		$renewal_url = add_query_arg( [
			'action' => 'renew',
			'domain' => $domain,
		], home_url( '/spawn/dashboard/' ) );

		$expires_formatted = wp_date( 'F j, Y', strtotime( $expires ) );

		// Build message based on urgency.
		$urgency_intro = match ( $warning_interval ) {
			30      => __( "This is a friendly reminder that your domain registration is coming up for renewal.", 'spawn' ),
			14      => __( "Your domain registration will expire in approximately two weeks.", 'spawn' ),
			7       => __( "Your domain registration expires in just one week. Please take action soon to avoid losing your domain.", 'spawn' ),
			1       => __( "Your domain expires TOMORROW. If you don't renew today, you may lose your domain.", 'spawn' ),
			default => __( "Your domain registration is expiring soon.", 'spawn' ),
		};

		// Add auto-renewal status info.
		$auto_renew_note = '';
		if ( $auto_renew_enabled ) {
			if ( $warning_interval > self::AUTO_RENEW_THRESHOLD_DAYS ) {
				$auto_renew_note = sprintf(
					__( "\n\nNote: You have auto-renewal enabled. Your domain will be automatically renewed when it reaches %d days before expiry, provided your payment method is valid.", 'spawn' ),
					self::AUTO_RENEW_THRESHOLD_DAYS
				);
			} else {
				// Auto-renewal was supposed to happen but failed.
				$auto_renew_note = __( "\n\nNote: Auto-renewal was attempted but failed. Please renew manually or update your payment method.", 'spawn' );
			}
		} else {
			$auto_renew_note = __( "\n\nTip: Enable auto-renewal in your dashboard to automatically renew your domain before it expires.", 'spawn' );
		}

		$message = sprintf(
			/* translators: 1: urgency intro, 2: domain name, 3: expiration date, 4: renewal URL, 5: auto-renew note */
			__(
				"Hello,\n\n" .
				"%1\$s\n\n" .
				"Domain: %2\$s\n" .
				"Expiration date: %3\$s\n\n" .
				"To renew your domain, please visit your dashboard:\n" .
				"%4\$s\n\n" .
				"If you do not renew before the expiration date, your domain may become available for others to register.%5\$s\n\n" .
				"If you have any questions, please contact support.\n\n" .
				"—The Spawn Team",
				'spawn'
			),
			$urgency_intro,
			$domain,
			$expires_formatted,
			$renewal_url,
			$auto_renew_note
		);

		// Set content type for potential HTML in future.
		$headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

		$sent = wp_mail( $email, $subject, $message, $headers );

		if ( ! $sent ) {
			self::log( sprintf( 'Failed to send %d-day warning email to %s', $warning_interval, $email ), 'error' );
		}

		return $sent;
	}

	/**
	 * Log a message with Spawn prefix.
	 *
	 * @param string $message Log message.
	 * @param string $level   Log level (info, error, warning).
	 */
	private static function log( string $message, string $level = 'info' ): void {
		$prefix = '[Spawn Cron]';
		if ( 'error' === $level ) {
			$prefix .= ' ERROR:';
		} elseif ( 'warning' === $level ) {
			$prefix .= ' WARNING:';
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional logging.
		error_log( sprintf( '%s %s', $prefix, $message ) );
	}

	/**
	 * Handle auto-refill needed action.
	 *
	 * Called when a customer's balance falls below their auto-refill threshold.
	 *
	 * @param int   $customer_id Customer ID.
	 * @param array $settings    Auto-refill settings (enabled, threshold, amount).
	 */
	public static function handle_auto_refill_needed( int $customer_id, array $settings ): void {
		self::log( sprintf(
			'Auto-refill triggered for customer #%d (threshold: $%.2f, amount: $%.2f)',
			$customer_id,
			$settings['threshold'] ?? 0,
			$settings['amount'] ?? 0
		) );

		// Verify auto-refill is actually enabled.
		if ( empty( $settings['enabled'] ) ) {
			self::log( sprintf( 'Auto-refill not enabled for customer #%d, checking for low balance warning', $customer_id ) );
			self::maybe_send_low_balance_warning( $customer_id );
			return;
		}

		// Process the auto-refill.
		$result = Payment_Helpers::process_auto_refill( $customer_id, $settings );

		if ( is_wp_error( $result ) ) {
			self::log( sprintf(
				'Auto-refill failed for customer #%d: %s',
				$customer_id,
				$result->get_error_message()
			), 'error' );
		} else {
			self::log( sprintf(
				'Auto-refill succeeded for customer #%d, added $%.2f',
				$customer_id,
				$settings['amount'] ?? 0
			) );
		}
	}

	/**
	 * Check and send low balance warning if needed.
	 *
	 * Only sends if balance < $2 and auto-refill is disabled.
	 *
	 * @param int $customer_id Customer ID.
	 */
	private static function maybe_send_low_balance_warning( int $customer_id ): void {
		$customer = Database::get_customer( $customer_id );
		if ( ! $customer ) {
			return;
		}

		$balance = (float) $customer['credit_balance'];

		// Only warn if balance is below threshold.
		if ( $balance >= self::LOW_BALANCE_WARNING_THRESHOLD ) {
			return;
		}

		// Check if we've already sent a warning recently (within 24 hours).
		$last_warning = get_transient( 'spawn_low_balance_warning_' . $customer_id );
		if ( $last_warning ) {
			return;
		}

		self::send_low_balance_warning( $customer, $balance );

		// Mark that we've sent a warning (don't send again for 24 hours).
		set_transient( 'spawn_low_balance_warning_' . $customer_id, time(), DAY_IN_SECONDS );
	}

	/**
	 * Send low balance warning email.
	 *
	 * @param array $customer Customer data.
	 * @param float $balance  Current balance.
	 */
	private static function send_low_balance_warning( array $customer, float $balance ): void {
		$email   = $customer['email'];
		$domain  = $customer['domain'];
		$subject = __( 'Low credit balance warning - Spawn', 'spawn' );

		$message = sprintf(
			/* translators: 1: domain, 2: balance amount */
			__(
				"Hello,\n\n" .
				"Your Spawn credit balance for %1\$s is running low.\n\n" .
				"Current balance: $%2\$.2f\n\n" .
				"When your balance reaches $0, your AI assistant will stop responding until you add more credits.\n\n" .
				"You can:\n" .
				"1. Purchase credits from your dashboard\n" .
				"2. Enable auto-refill to automatically top up when balance gets low\n\n" .
				"To enable auto-refill, visit your dashboard and go to Settings → Credits.\n\n" .
				"—The Spawn Team",
				'spawn'
			),
			$domain,
			$balance
		);

		$sent = wp_mail( $email, $subject, $message );

		if ( $sent ) {
			self::log( sprintf( 'Sent low balance warning to %s (balance: $%.2f)', $email, $balance ) );
		} else {
			self::log( sprintf( 'Failed to send low balance warning to %s', $email ), 'error' );
		}
	}

	/**
	 * Send auto-refill success email notification.
	 *
	 * @param int   $customer_id Customer ID.
	 * @param int   $credits     Credits added.
	 * @param array $result      Stripe payment result.
	 */
	public static function send_auto_refill_success_email( int $customer_id, int $credits, array $result ): void {
		$customer = Database::get_customer( $customer_id );
		if ( ! $customer ) {
			return;
		}

		$email       = $customer['email'];
		$domain      = $customer['domain'];
		$new_balance = Database::get_credit_balance( $customer_id );
		$amount      = $credits / 100; // Convert credits to dollars.
		$subject     = __( 'Credits auto-refilled - Spawn', 'spawn' );

		$message = sprintf(
			/* translators: 1: domain, 2: amount charged, 3: credits added, 4: new balance */
			__(
				"Hello,\n\n" .
				"Your Spawn credits for %1\$s have been automatically refilled.\n\n" .
				"Amount charged: $%2\$.2f\n" .
				"Credits added: %3\$d\n" .
				"New balance: $%4\$.2f\n\n" .
				"You can manage your auto-refill settings in your dashboard under Settings → Credits.\n\n" .
				"—The Spawn Team",
				'spawn'
			),
			$domain,
			$amount,
			$credits,
			$new_balance ?? 0
		);

		$sent = wp_mail( $email, $subject, $message );

		if ( $sent ) {
			self::log( sprintf( 'Sent auto-refill success email to %s', $email ) );
		} else {
			self::log( sprintf( 'Failed to send auto-refill success email to %s', $email ), 'error' );
		}
	}

	/**
	 * Send auto-refill failure email notification.
	 *
	 * @param int      $customer_id Customer ID.
	 * @param WP_Error $error       The error that occurred.
	 */
	public static function send_auto_refill_failed_email( int $customer_id, WP_Error $error ): void {
		$customer = Database::get_customer( $customer_id );
		if ( ! $customer ) {
			return;
		}

		$email   = $customer['email'];
		$domain  = $customer['domain'];
		$balance = (float) $customer['credit_balance'];
		$subject = __( 'Credit auto-refill failed - Action required', 'spawn' );

		$message = sprintf(
			/* translators: 1: domain, 2: error message, 3: current balance */
			__(
				"Hello,\n\n" .
				"We were unable to automatically refill your Spawn credits for %1\$s.\n\n" .
				"Reason: %2\$s\n\n" .
				"Current balance: $%3\$.2f\n\n" .
				"Please update your payment method or manually purchase credits to continue using your AI assistant.\n\n" .
				"You can do this from your dashboard under Settings → Billing.\n\n" .
				"—The Spawn Team",
				'spawn'
			),
			$domain,
			$error->get_error_message(),
			$balance
		);

		$sent = wp_mail( $email, $subject, $message );

		if ( $sent ) {
			self::log( sprintf( 'Sent auto-refill failure email to %s', $email ) );
		} else {
			self::log( sprintf( 'Failed to send auto-refill failure email to %s', $email ), 'error' );
		}

		// Also notify admin of payment failures.
		$admin_email = get_option( 'admin_email' );
		wp_mail(
			$admin_email,
			sprintf( '[Spawn] Auto-refill failed: Customer #%d', $customer_id ),
			sprintf(
				"Auto-refill payment failed\n\nCustomer: #%d\nDomain: %s\nEmail: %s\nBalance: $%.2f\nError: %s",
				$customer_id,
				$domain,
				$email,
				$balance,
				$error->get_error_message()
			)
		);
	}
}
