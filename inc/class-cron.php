<?php
/**
 * Cron jobs for Spawn.
 *
 * @package Spawn
 */

declare(strict_types=1);

namespace Spawn;

use StripeIntegration\StripeClient;
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
	 * Days before expiry to attempt renewal.
	 */
	private const RENEWAL_WINDOW_DAYS = 30;

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
	 * Process domain renewals for expiring domains.
	 */
	public static function process_domain_renewals(): void {
		$expiring = Database::get_expiring_domains( self::RENEWAL_WINDOW_DAYS );

		if ( empty( $expiring ) ) {
			self::log( 'No domains expiring within ' . self::RENEWAL_WINDOW_DAYS . ' days' );
			return;
		}

		self::log( sprintf( 'Found %d domains expiring soon', count( $expiring ) ) );

		foreach ( $expiring as $customer ) {
			self::process_single_renewal( $customer );
		}
	}

	/**
	 * Process renewal for a single customer.
	 *
	 * @param array $customer Customer data from database.
	 */
	private static function process_single_renewal( array $customer ): void {
		$customer_id = (int) $customer['id'];
		$domain      = $customer['domain'];
		$email       = $customer['email'];
		$expires_at  = $customer['domain_expires_at'];

		self::log( sprintf(
			'Processing renewal for customer #%d, domain: %s, expires: %s',
			$customer_id,
			$domain,
			$expires_at
		) );

		// Get renewal price from Name.com.
		$renewal_price = Name_Com::get_renewal_price( $domain );

		if ( is_wp_error( $renewal_price ) ) {
			self::log( sprintf(
				'Failed to get renewal price for %s: %s',
				$domain,
				$renewal_price->get_error_message()
			), 'error' );
			self::send_failure_notification( $customer, $renewal_price->get_error_message() );
			return;
		}

		self::log( sprintf( 'Renewal price for %s: $%.2f', $domain, $renewal_price ) );

		// Charge customer's payment method.
		$charge_result = self::charge_for_renewal( $customer, $renewal_price );

		if ( is_wp_error( $charge_result ) ) {
			self::log( sprintf(
				'Payment failed for %s: %s',
				$domain,
				$charge_result->get_error_message()
			), 'error' );
			self::send_failure_notification( $customer, 'Payment failed: ' . $charge_result->get_error_message() );
			return;
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
			self::send_failure_notification(
				$customer,
				'Domain renewal API failed after successful payment. Manual intervention required: ' . $renewal_result->get_error_message()
			);
			return;
		}

		// Update database with new expiration.
		$db_updated = Database::renew_domain( $customer_id );

		if ( ! $db_updated ) {
			self::log( sprintf( 'Failed to update database for customer #%d', $customer_id ), 'error' );
		}

		self::log( sprintf(
			'Successfully renewed %s for customer #%d, new expiration: %s',
			$domain,
			$customer_id,
			$renewal_result['expires_at'] ?? 'unknown'
		) );

		// Send success notification.
		self::send_success_notification( $customer, $renewal_price, $renewal_result['expires_at'] ?? null );

		/**
		 * Fires after a domain is successfully renewed.
		 *
		 * @param int    $customer_id   Spawn customer ID.
		 * @param string $domain        Domain name.
		 * @param float  $renewal_price Price charged for renewal.
		 * @param array  $renewal_result Result from Name.com API.
		 */
		do_action( 'spawn_domain_renewed', $customer_id, $domain, $renewal_price, $renewal_result );
	}

	/**
	 * Charge customer for domain renewal.
	 *
	 * @param array $customer      Customer data.
	 * @param float $renewal_price Price in dollars.
	 * @return array|WP_Error PaymentIntent result or error.
	 */
	private static function charge_for_renewal( array $customer, float $renewal_price ): array|WP_Error {
		$stripe_customer_id = $customer['stripe_customer'] ?? '';
		$payment_method_id  = $customer['stripe_payment_method'] ?? '';
		$customer_id        = (int) $customer['id'];

		if ( empty( $stripe_customer_id ) ) {
			return new WP_Error(
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

		return StripeClient::create_payment_intent( [
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
				'type'              => 'domain_renewal',
				'domain'            => $customer['domain'],
				'spawn_customer_id' => $customer_id,
				'source'            => 'spawn',
			],
		] );
	}

	/**
	 * Send renewal success notification email.
	 *
	 * @param array       $customer      Customer data.
	 * @param float       $renewal_price Price charged.
	 * @param string|null $new_expires   New expiration date.
	 */
	private static function send_success_notification( array $customer, float $renewal_price, ?string $new_expires ): void {
		$email   = $customer['email'];
		$domain  = $customer['domain'];
		$subject = sprintf(
			/* translators: %s: domain name */
			__( 'Your domain %s has been renewed', 'spawn' ),
			$domain
		);

		$expires_formatted = $new_expires
			? wp_date( 'F j, Y', strtotime( $new_expires ) )
			: __( 'in approximately one year', 'spawn' );

		$message = sprintf(
			/* translators: 1: domain name, 2: price, 3: expiration date */
			__(
				"Hello,\n\nYour domain %1\$s has been automatically renewed for one year.\n\nRenewal cost: $%2\$.2f\nNew expiration date: %3\$s\n\nThank you for using Spawn!\n\n—The Spawn Team",
				'spawn'
			),
			$domain,
			$renewal_price,
			$expires_formatted
		);

		$sent = wp_mail( $email, $subject, $message );

		if ( ! $sent ) {
			self::log( sprintf( 'Failed to send success email to %s', $email ), 'error' );
		}
	}

	/**
	 * Send renewal failure notification email.
	 *
	 * @param array  $customer Customer data.
	 * @param string $reason   Failure reason.
	 */
	private static function send_failure_notification( array $customer, string $reason ): void {
		$email   = $customer['email'];
		$domain  = $customer['domain'];
		$expires = $customer['domain_expires_at'];
		$subject = sprintf(
			/* translators: %s: domain name */
			__( 'Action required: Domain renewal failed for %s', 'spawn' ),
			$domain
		);

		$expires_formatted = wp_date( 'F j, Y', strtotime( $expires ) );

		$message = sprintf(
			/* translators: 1: domain name, 2: expiration date, 3: failure reason */
			__(
				"Hello,\n\nWe were unable to automatically renew your domain %1\$s.\n\nCurrent expiration date: %2\$s\n\nReason: %3\$s\n\nPlease log in to your dashboard to update your payment method or contact support.\n\n—The Spawn Team",
				'spawn'
			),
			$domain,
			$expires_formatted,
			$reason
		);

		$sent = wp_mail( $email, $subject, $message );

		if ( ! $sent ) {
			self::log( sprintf( 'Failed to send failure email to %s', $email ), 'error' );
		}

		// Also notify admin.
		$admin_email = get_option( 'admin_email' );
		wp_mail(
			$admin_email,
			sprintf( '[Spawn] Domain renewal failed: %s', $domain ),
			sprintf(
				"Domain renewal failed for customer #%d\n\nDomain: %s\nEmail: %s\nExpires: %s\nReason: %s",
				$customer['id'],
				$domain,
				$email,
				$expires_formatted,
				$reason
			)
		);
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
