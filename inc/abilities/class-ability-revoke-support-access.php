<?php
/**
 * Revoke Support Access ability.
 *
 * @package Spawn
 */

namespace Spawn\Abilities;

use WP_Error;

/**
 * Revokes Spawn support team SSH access from customer's server.
 */
class Ability_Revoke_Support_Access {

	/**
	 * Key identifier to match in authorized_keys.
	 */
	private const SUPPORT_KEY_IDENTIFIER = 'sarai@spawn-provisioner';

	/**
	 * Execute the ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error Result or error.
	 */
	public static function execute( array $input ): array|WP_Error {
		$customer_id = $input['customer_id'] ?? null;

		// Build the message for the customer's agent.
		$message = sprintf(
			"SYSTEM REQUEST: Revoke Spawn Support Access\n\n" .
			"The customer has requested to revoke support access. Please remove any SSH keys containing '%s' from /root/.ssh/authorized_keys.\n\n" .
			"To remove the key, run:\n" .
			"grep -v '%s' /root/.ssh/authorized_keys > /tmp/auth_keys_new && " .
			'mv /tmp/auth_keys_new /root/.ssh/authorized_keys && ' .
			"chmod 600 /root/.ssh/authorized_keys\n\n" .
			'Confirm when complete.',
			self::SUPPORT_KEY_IDENTIFIER,
			self::SUPPORT_KEY_IDENTIFIER
		);

		// Use send-message ability.
		$result = Ability_Send_Message::execute( array(
			'message'     => $message,
			'customer_id' => $customer_id,
			'system_note' => 'Support access revoke request',
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$customer_id_resolved = $result['customer_id'] ?? $customer_id;

		return array(
			'success'     => true,
			'customer_id' => $customer_id_resolved,
			'revoked_at'  => current_time( 'mysql' ),
			'agent_reply' => $result['reply'] ?? null,
		);
	}
}
