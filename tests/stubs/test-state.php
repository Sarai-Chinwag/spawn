<?php
/**
 * Global test state singleton — tests set expectations here.
 *
 * @package Spawn\Tests
 */

declare(strict_types=1);

class SpawnTestState {
	/** @var array<string, mixed> Option store. */
	public static array $options = [];

	/** @var array{to: string, subject: string, message: string}[] Sent emails. */
	public static array $emails = [];

	/** @var array{url: string, args: array}[] HTTP requests made. */
	public static array $http_requests = [];

	/** @var mixed|null Next return value for wp_remote_request. */
	public static mixed $next_http_response = null;

	/** @var string[] Logged error messages. */
	public static array $error_log = [];

	/** @var array<string, mixed> Next return for Database methods (keyed by method). */
	public static array $db_returns = [];

	/** @var array{method: string, args: array}[] Database calls made. */
	public static array $db_calls = [];

	/** @var mixed|null Next return for Provisioner::trigger. */
	public static mixed $provisioner_return = null;

	/** @var array|null Args passed to Provisioner::trigger. */
	public static ?array $provisioner_args = null;

	/** @var mixed|null Next return for Name_Com::register. */
	public static mixed $namecom_return = null;

	/** @var mixed|null Next return for Payment_Helpers::handle_credit_purchase. */
	public static mixed $credit_purchase_return = null;

	/** @var array|null Args passed to Payment_Helpers::handle_credit_purchase. */
	public static ?array $credit_purchase_args = null;

	/** @var bool Whether Cleanup::send_cancellation_email was called. */
	public static bool $cancellation_email_sent = false;

	/** @var mixed|null Next return for Database::schedule_deletion. */
	public static mixed $schedule_deletion_return = null;

	/** @var bool Whether StripeIntegration\StripeClient class exists. */
	public static bool $stripe_available = true;

	/** @var mixed|null Next return for StripeClient::create_refund. */
	public static mixed $refund_return = null;

	/** @var array|null Args passed to StripeClient::create_refund. */
	public static ?array $refund_args = null;

	/**
	 * Reset all state between tests.
	 */
	public static function reset(): void {
		self::$options                 = [];
		self::$emails                  = [];
		self::$http_requests           = [];
		self::$next_http_response      = null;
		self::$error_log               = [];
		self::$db_returns              = [];
		self::$db_calls                = [];
		self::$provisioner_return      = null;
		self::$provisioner_args        = null;
		self::$namecom_return          = null;
		self::$credit_purchase_return  = null;
		self::$credit_purchase_args    = null;
		self::$cancellation_email_sent = false;
		self::$schedule_deletion_return = null;
		self::$stripe_available        = true;
		self::$refund_return           = null;
		self::$refund_args             = null;
	}
}
