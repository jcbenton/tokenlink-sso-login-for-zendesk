<?php
/**
 * Firebase JWT Key
 *
 * @package Firebase\JWT
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName
 * phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 * phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
 */

namespace Firebase\JWT;

use InvalidArgumentException;
use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use TypeError;

/**
 * Represents a cryptographic key for JWT signing and verification.
 */
class Key {

	/**
	 * Construct a new Key instance.
	 *
	 * @param string|resource|OpenSSLAsymmetricKey|OpenSSLCertificate $keyMaterial The key material (string, resource, or OpenSSL key object).
	 * @param string                                                  $algorithm   The algorithm to use with this key.
	 *
	 * @throws TypeError If key material is not a valid type.
	 * @throws InvalidArgumentException If key material or algorithm is empty.
	 */
	public function __construct(
		private $keyMaterial,
		private string $algorithm
	) {
		if (
			! \is_string( $keyMaterial )
			&& ! $keyMaterial instanceof OpenSSLAsymmetricKey
			&& ! $keyMaterial instanceof OpenSSLCertificate
			&& ! \is_resource( $keyMaterial )
		) {
			throw new TypeError( 'Key material must be a string, resource, or OpenSSLAsymmetricKey' );
		}

		if ( empty( $keyMaterial ) ) {
			throw new InvalidArgumentException( 'Key material must not be empty' );
		}

		if ( empty( $algorithm ) ) {
			throw new InvalidArgumentException( 'Algorithm must not be empty' );
		}
	}

	/**
	 * Return the algorithm valid for this key.
	 *
	 * @return string The algorithm name.
	 */
	public function getAlgorithm(): string {
		return $this->algorithm;
	}

	/**
	 * Return the key material.
	 *
	 * @return string|resource|OpenSSLAsymmetricKey|OpenSSLCertificate The key material.
	 */
	public function getKeyMaterial() {
		return $this->keyMaterial;
	}
}
