<?php
/**
 * Firebase JWT BeforeValidException
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

/**
 * Exception thrown when a JWT is being used before it's valid (nbf/iat check).
 */
class BeforeValidException extends \UnexpectedValueException implements JWTExceptionWithPayloadInterface {

	/**
	 * The JWT payload.
	 *
	 * @var object
	 */
	private object $payload;

	/**
	 * Set the JWT payload.
	 *
	 * @param object $payload The JWT payload object.
	 * @return void
	 */
	public function setPayload( object $payload ): void {
		$this->payload = $payload;
	}

	/**
	 * Get the JWT payload.
	 *
	 * @return object The JWT payload object.
	 */
	public function getPayload(): object {
		return $this->payload;
	}
}
