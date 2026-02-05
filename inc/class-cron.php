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
	 * Initialize cron handlers.
	 */
	public static function init(): void {
		add_action( self::RENEWAL_HOOK, [ __CLASS__, 'process_domain_renewals' ] );
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
}
