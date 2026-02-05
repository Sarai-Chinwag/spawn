<?php
/**
 * Cancel ability.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Cleanup;
use Spawn\Database;
use StripeIntegration\StripeClient;
use WP_Error;

/**
 * Cancels subscription with grace period for data export.
 */
class Ability_Cancel {

	/**
	 * Execute the ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public static function execute( array $input ): array|WP_Error {
		$reason  = $input['reason'] ?? '';
		$confirm = $input['confirm'] ?? false;

		// Get customer.
		$customer = self::get_customer( $input );
		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		// Check current status.
		if ( 'deleted' === $customer['status'] ) {
			return new WP_Error( 'already_deleted', __( 'This account has already been deleted', 'spawn' ) );
		}

		if ( 'cancelling' === $customer['status'] ) {
			$deletion_date = wp_date( 'F j, Y \a\t g:i A', strtotime( $customer['scheduled_deletion_at'] ) );
			return [
				'status'              => 'already_cancelling',
				'scheduled_deletion'  => $customer['scheduled_deletion_at'],
				'message'             => sprintf(
					__( 'Cancellation already in progress. Your site will be deleted on %s. Export your data before then!', 'spawn' ),
					$deletion_date
				),
				'export_instructions' => self::get_export_instructions(),
				'can_reactivate'      => true,
			];
		}

		// If not confirmed, return warning with export instructions.
		if ( ! $confirm ) {
			return [
				'status'              => 'confirmation_required',
				'grace_period_days'   => Cleanup::GRACE_PERIOD_DAYS,
				'message'             => sprintf(
					__( 'Are you sure you want to cancel? Your site and ALL DATA will be permanently deleted after %d days. This cannot be undone. Please export your data first.', 'spawn' ),
					Cleanup::GRACE_PERIOD_DAYS
				),
				'export_instructions' => self::get_export_instructions(),
				'confirm_prompt'      => __( 'To proceed, call this ability again with confirm: true', 'spawn' ),
			];
		}

		// Cancel Stripe subscription via shared stripe-integration plugin.
		if ( ! empty( $customer['stripe_subscription'] ) ) {
			$result = StripeClient::cancel_subscription( $customer['stripe_subscription'] );
			if ( is_wp_error( $result ) ) {
				// Log but continue - Stripe webhook will also handle this.
				error_log( sprintf( '[Spawn] Stripe cancel via ability: %s', $result->get_error_message() ) );
			}
		}

		// Schedule deletion with grace period.
		$scheduled = Database::schedule_deletion( (int) $customer['id'], Cleanup::GRACE_PERIOD_DAYS );

		if ( ! $scheduled ) {
			return new WP_Error( 'schedule_failed', __( 'Failed to schedule cancellation', 'spawn' ) );
		}

		// Refresh customer data.
		$customer = Database::get_customer( (int) $customer['id'] );

		// Send cancellation email.
		Cleanup::send_cancellation_email( $customer );

		$deletion_date = wp_date( 'F j, Y \a\t g:i A', strtotime( $customer['scheduled_deletion_at'] ) );

		return [
			'success'             => true,
			'status'              => 'cancellation_scheduled',
			'cancelled_at'        => $customer['cancelled_at'],
			'scheduled_deletion'  => $customer['scheduled_deletion_at'],
			'grace_period_days'   => Cleanup::GRACE_PERIOD_DAYS,
			'message'             => sprintf(
				__( 'Your subscription has been cancelled. Your site will remain accessible until %s. Please export your data before then.', 'spawn' ),
				$deletion_date
			),
			'export_instructions' => self::get_export_instructions(),
			'can_reactivate'      => true,
			'reactivate_prompt'   => __( 'Changed your mind? You can reactivate before the deletion date.', 'spawn' ),
		];
	}

	/**
	 * Get export instructions for the customer.
	 *
	 * @return array Export instructions.
	 */
	private static function get_export_instructions(): array {
		return [
			'methods' => [
				[
					'name'        => 'Full Site Backup',
					'description' => __( 'Download a complete backup of your WordPress site', 'spawn' ),
					'command'     => 'export-site',
					'details'     => __( 'Creates a downloadable ZIP with your database and files', 'spawn' ),
				],
				[
					'name'        => 'WordPress Export (XML)',
					'description' => __( 'Export posts, pages, and media as WordPress XML', 'spawn' ),
					'command'     => 'wp export',
					'details'     => __( 'Standard WordPress export format, importable anywhere', 'spawn' ),
				],
				[
					'name'        => 'Direct File Access (SFTP)',
					'description' => __( 'Connect via SFTP to download files directly', 'spawn' ),
					'command'     => 'get-sftp-credentials',
					'details'     => __( 'Full access to wp-content, themes, plugins, uploads', 'spawn' ),
				],
			],
			'important' => __( 'After deletion, your data CANNOT be recovered. Export everything you need!', 'spawn' ),
		];
	}

	/**
	 * Get customer from input or current user.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Customer data or error.
	 */
	private static function get_customer( array $input ): array|WP_Error {
		if ( ! empty( $input['customer_id'] ) ) {
			$customer = Database::get_customer( (int) $input['customer_id'] );
		} else {
			$user = wp_get_current_user();
			if ( ! $user->ID ) {
				return new WP_Error( 'not_logged_in', __( 'You must be logged in', 'spawn' ) );
			}
			$customer = Database::get_customer_by_user_id( $user->ID );
		}

		if ( ! $customer ) {
			return new WP_Error( 'not_found', __( 'Customer not found', 'spawn' ) );
		}

		return $customer;
	}
}
