<?php
/**
 * Firebase JWT Exception With Payload Interface
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
 * Interface for JWT exceptions that include the payload.
 */
interface JWTExceptionWithPayloadInterface {

	/**
	 * Get the payload that caused this exception.
	 *
	 * @return object The JWT payload.
	 */
	public function getPayload(): object;

	/**
	 * Set the payload that caused this exception.
	 *
	 * @param object $payload The JWT payload object.
	 * @return void
	 */
	public function setPayload( object $payload ): void;
}
