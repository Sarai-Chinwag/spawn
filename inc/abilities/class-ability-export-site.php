<?php
/**
 * Export Site ability.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use Spawn\Database;
use WP_Error;

/**
 * Exports customer's WordPress site for download.
 */
class Ability_Export_Site {

	/**
	 * Execute the ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public static function execute( array $input ): array|WP_Error {
		$format = $input['format'] ?? 'full'; // full, xml, database.

		// Get customer.
		$customer = self::get_customer( $input );
		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		// Check if customer has an active server.
		if ( empty( $customer['server_ip'] ) ) {
			return new WP_Error( 'no_server', __( 'No active server found for this account', 'spawn' ) );
		}

		// This ability returns instructions for the customer's AI agent to execute.
		// The actual export runs on the customer's VPS, not here.
		switch ( $format ) {
			case 'xml':
				return self::get_xml_export_instructions( $customer );

			case 'database':
				return self::get_database_export_instructions( $customer );

			case 'full':
			default:
				return self::get_full_export_instructions( $customer );
		}
	}

	/**
	 * Get instructions for full site backup.
	 *
	 * @param array $customer Customer data.
	 * @return array Export instructions.
	 */
	private static function get_full_export_instructions( array $customer ): array {
		$domain = $customer['domain'];

		return array(
			'success'      => true,
			'format'       => 'full',
			'description'  => __( 'Full site backup including database, uploads, themes, and plugins', 'spawn' ),
			'instructions' => array(
				'step_1' => array(
					'description' => __( 'Create backup directory', 'spawn' ),
					'command'     => 'mkdir -p /tmp/site-backup',
				),
				'step_2' => array(
					'description' => __( 'Export database', 'spawn' ),
					'command'     => 'wp db export /tmp/site-backup/database.sql --allow-root',
				),
				'step_3' => array(
					'description' => __( 'Copy wp-content', 'spawn' ),
					'command'     => 'cp -r /var/www/html/wp-content /tmp/site-backup/',
				),
				'step_4' => array(
					'description' => __( 'Create ZIP archive', 'spawn' ),
					'command'     => 'cd /tmp && zip -r site-backup.zip site-backup/',
				),
				'step_5' => array(
					'description' => __( 'Move to accessible location', 'spawn' ),
					'command'     => 'mv /tmp/site-backup.zip /var/www/html/',
				),
			),
			'download_url' => sprintf( 'https://%s/site-backup.zip', $domain ),
			'cleanup'      => array(
				'description' => __( 'Remove backup files after download', 'spawn' ),
				'command'     => 'rm -rf /tmp/site-backup /var/www/html/site-backup.zip',
			),
			'notes'        => array(
				__( 'Download the backup file immediately - it will be removed on next cleanup', 'spawn' ),
				__( 'The ZIP contains your database and all wp-content files', 'spawn' ),
				__( 'To restore: import database, copy wp-content to new WordPress installation', 'spawn' ),
			),
		);
	}

	/**
	 * Get instructions for WordPress XML export.
	 *
	 * @param array $customer Customer data.
	 * @return array Export instructions.
	 */
	private static function get_xml_export_instructions( array $customer ): array {
		$domain = $customer['domain'];

		return array(
			'success'      => true,
			'format'       => 'xml',
			'description'  => __( 'WordPress standard XML export (posts, pages, media, menus)', 'spawn' ),
			'instructions' => array(
				'step_1' => array(
					'description' => __( 'Generate WordPress export file', 'spawn' ),
					'command'     => 'wp export --dir=/var/www/html/ --allow-root',
				),
			),
			'download_url' => sprintf( 'https://%s/ (look for .xml file)', $domain ),
			'notes'        => array(
				__( 'This creates a standard WordPress eXtended RSS (WXR) file', 'spawn' ),
				__( 'Import using Tools > Import > WordPress on any WordPress site', 'spawn' ),
				__( 'Includes posts, pages, comments, categories, tags, and media references', 'spawn' ),
				__( 'Does NOT include themes, plugins, or settings', 'spawn' ),
			),
		);
	}

	/**
	 * Get instructions for database-only export.
	 *
	 * @param array $customer Customer data.
	 * @return array Export instructions.
	 */
	private static function get_database_export_instructions( array $customer ): array {
		$domain = $customer['domain'];

		return array(
			'success'      => true,
			'format'       => 'database',
			'description'  => __( 'Database-only SQL dump', 'spawn' ),
			'instructions' => array(
				'step_1' => array(
					'description' => __( 'Export database to SQL file', 'spawn' ),
					'command'     => 'wp db export /var/www/html/database-backup.sql --allow-root',
				),
				'step_2' => array(
					'description' => __( 'Compress the SQL file', 'spawn' ),
					'command'     => 'gzip /var/www/html/database-backup.sql',
				),
			),
			'download_url' => sprintf( 'https://%s/database-backup.sql.gz', $domain ),
			'cleanup'      => array(
				'description' => __( 'Remove backup file after download', 'spawn' ),
				'command'     => 'rm /var/www/html/database-backup.sql.gz',
			),
			'notes'        => array(
				__( 'Contains all WordPress data: posts, users, settings, options', 'spawn' ),
				__( 'Import with: wp db import database-backup.sql', 'spawn' ),
				__( 'May need search-replace if domain changes: wp search-replace "old.com" "new.com"', 'spawn' ),
			),
		);
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
