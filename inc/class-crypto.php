<?php
/**
 * Encryption utilities for sensitive data.
 *
 * @package Spawn
 */

namespace Spawn;

/**
 * Handles encryption/decryption of sensitive data using sodium.
 */
class Crypto {

	/**
	 * Derive a 32-byte encryption key from WordPress salts.
	 *
	 * @return string 32-byte key.
	 */
	private static function get_key(): string {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}

	/**
	 * Encrypt a plaintext string.
	 *
	 * @param string $plaintext The string to encrypt.
	 * @return string Base64-encoded nonce + ciphertext.
	 */
	public static function encrypt( string $plaintext ): string {
		$key   = self::get_key();
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$encrypted = sodium_crypto_secretbox( $plaintext, $nonce, $key );

		return base64_encode( $nonce . $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt an encrypted string.
	 *
	 * @param string $encrypted Base64-encoded nonce + ciphertext.
	 * @return string|false Decrypted plaintext or false on failure.
	 */
	public static function decrypt( string $encrypted ): string|false {
		$key     = self::get_key();
		$decoded = base64_decode( $encrypted, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $decoded || strlen( $decoded ) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return false;
		}

		$nonce      = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$ciphertext = substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

		$result = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );

		return false === $result ? false : $result;
	}

	/**
	 * Mask an API key for safe display.
	 *
	 * Returns first 7 characters + "..." + last 4 characters.
	 * Example: "sk-ant-...a1b2"
	 *
	 * @param string $key The API key to mask.
	 * @return string Masked key.
	 */
	public static function mask_key( string $key ): string {
		if ( strlen( $key ) <= 11 ) {
			return str_repeat( '*', strlen( $key ) );
		}

		return substr( $key, 0, 7 ) . '...' . substr( $key, -4 );
	}
}
