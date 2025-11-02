<?php
/**
 * Firebase JWT CachedKeySet
 *
 * @package Firebase\JWT
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName
 * phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 * phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
 * phpcs:disable WordPress.CodeAnalysis.AssignmentInCondition.Found
 */

namespace Firebase\JWT;

use ArrayAccess;
use InvalidArgumentException;
use LogicException;
use OutOfBoundsException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use RuntimeException;
use UnexpectedValueException;

/**
 * Caches JSON Web Key Sets (JWKS) retrieved from a remote URI.
 *
 * @implements ArrayAccess<string, Key>
 */
class CachedKeySet implements ArrayAccess {

	/**
	 * The URI of the JWKS endpoint.
	 *
	 * @var string
	 */
	private $jwksUri;
	/**
	 * The HTTP client used to fetch JWKS.
	 *
	 * @var ClientInterface
	 */
	private $httpClient;
	/**
	 * The HTTP request factory.
	 *
	 * @var RequestFactoryInterface
	 */
	private $httpFactory;
	/**
	 * The cache pool implementation.
	 *
	 * @var CacheItemPoolInterface
	 */
	private $cache;
	/**
	 * Cache expiration time in seconds.
	 *
	 * @var ?int
	 */
	private $expiresAfter;
	/**
	 * The cached item containing the key set.
	 *
	 * @var ?CacheItemInterface
	 */
	private $cacheItem;
	/**
	 * The cached key set data.
	 *
	 * @var array<string, array<mixed>>
	 */
	private $keySet;
	/**
	 * The cache key for storing JWKS.
	 *
	 * @var string
	 */
	private $cacheKey;
	/**
	 * The prefix for cache keys.
	 *
	 * @var string
	 */
	private $cacheKeyPrefix = 'jwks';
	/**
	 * Maximum allowed length for cache keys.
	 *
	 * @var int
	 */
	private $maxKeyLength = 64;
	/**
	 * Whether rate limiting is enabled.
	 *
	 * @var bool
	 */
	private $rateLimit;
	/**
	 * The cache key for rate limit tracking.
	 *
	 * @var string
	 */
	private $rateLimitCacheKey;
	/**
	 * Maximum HTTP requests allowed per minute.
	 *
	 * @var int
	 */
	private $maxCallsPerMinute = 10;
	/**
	 * Default algorithm to use if not specified in JWK.
	 *
	 * @var string|null
	 */
	private $defaultAlg;

	/**
	 * Create a cached key set from a JWKS URI.
	 *
	 * @param string                  $jwksUri     The JWKS URI to fetch keys from.
	 * @param ClientInterface         $httpClient  The HTTP client for fetching.
	 * @param RequestFactoryInterface $httpFactory The HTTP request factory.
	 * @param CacheItemPoolInterface  $cache       The cache pool implementation.
	 * @param int|null                $expiresAfter Cache expiration in seconds.
	 * @param bool                    $rateLimit   Whether to enable rate limiting.
	 * @param string|null             $defaultAlg  Default algorithm if not in JWK.
	 */
	public function __construct(
		string $jwksUri,
		ClientInterface $httpClient,
		RequestFactoryInterface $httpFactory,
		CacheItemPoolInterface $cache,
		?int $expiresAfter = null,
		bool $rateLimit = false,
		?string $defaultAlg = null
	) {
		$this->jwksUri      = $jwksUri;
		$this->httpClient   = $httpClient;
		$this->httpFactory  = $httpFactory;
		$this->cache        = $cache;
		$this->expiresAfter = $expiresAfter;
		$this->rateLimit    = $rateLimit;
		$this->defaultAlg   = $defaultAlg;
		$this->setCacheKeys();
	}

	/**
	 * Get a key from the key set by ID.
	 *
	 * @param string $keyId The key ID to retrieve.
	 * @return Key The key object.
	 * @throws OutOfBoundsException If key ID is not found.
	 */
	public function offsetGet( $keyId ): Key {
		if ( ! $this->keyIdExists( $keyId ) ) {
			throw new OutOfBoundsException( 'Key ID not found' );
		}
		return JWK::parseKey( $this->keySet[ $keyId ], $this->defaultAlg );
	}

	/**
	 * Check if a key ID exists in the key set.
	 *
	 * @param string $keyId The key ID to check.
	 * @return bool True if key exists, false otherwise.
	 */
	public function offsetExists( $keyId ): bool {
		return $this->keyIdExists( $keyId );
	}

	/**
	 * Set a key in the key set (not supported).
	 *
	 * @param string $offset The key ID.
	 * @param Key    $value  The key value.
	 * @return void
	 * @throws LogicException Always thrown as method is not supported.
	 */
	public function offsetSet( $offset, $value ): void {
		throw new LogicException( 'Method not implemented' );
	}

	/**
	 * Unset a key in the key set (not supported).
	 *
	 * @param string $offset The key ID.
	 * @return void
	 * @throws LogicException Always thrown as method is not supported.
	 */
	public function offsetUnset( $offset ): void {
		throw new LogicException( 'Method not implemented' );
	}

	/**
	 * Format JWKS JSON string for cache storage.
	 *
	 * @param string $jwks The JWKS JSON string.
	 * @return array<mixed> The formatted key set array.
	 * @throws UnexpectedValueException If keys member is missing.
	 * @throws InvalidArgumentException If key set is empty.
	 */
	private function formatJwksForCache( string $jwks ): array {
		$jwks = json_decode( $jwks, true );

		if ( ! isset( $jwks['keys'] ) ) {
			throw new UnexpectedValueException( '"keys" member must exist in the JWK Set' );
		}

		if ( empty( $jwks['keys'] ) ) {
			throw new InvalidArgumentException( 'JWK Set did not contain any keys' );
		}

		$keys = array();
		foreach ( $jwks['keys'] as $k => $v ) {
			$kid                   = isset( $v['kid'] ) ? $v['kid'] : $k;
			$keys[ (string) $kid ] = $v;
		}

		return $keys;
	}

	/**
	 * Check if a key ID exists, fetching from cache or remote if needed.
	 *
	 * @param string $keyId The key ID to check.
	 * @return bool True if key exists, false otherwise.
	 * @throws UnexpectedValueException If HTTP request fails.
	 */
	private function keyIdExists( string $keyId ): bool {
		if ( null === $this->keySet ) {
			$item = $this->getCacheItem();
			// Try to load keys from cache.
			if ( $item->isHit() ) {
				// Item found! retrieve it.
				$this->keySet = $item->get();
				// If the cached item is a string, the JWKS response was cached (previous behavior).
				// Parse this into expected format array<kid, jwk> instead.
				if ( \is_string( $this->keySet ) ) {
					$this->keySet = $this->formatJwksForCache( $this->keySet );
				}
			}
		}

		if ( ! isset( $this->keySet[ $keyId ] ) ) {
			if ( $this->rateLimitExceeded() ) {
				return false;
			}
			$request      = $this->httpFactory->createRequest( 'GET', $this->jwksUri );
			$jwksResponse = $this->httpClient->sendRequest( $request );

			// Rewritten by Mailborder to pass PHPCS.
			if ( $jwksResponse->getStatusCode() !== 200 ) {
				throw new UnexpectedValueException(
					'HTTP request to JWKS endpoint failed with a non-200 response.'
				);
			}

			// Rewritten by Mailborder to pass PHPCS.
			$this->keySet = $this->formatJwksForCache( (string) $jwksResponse->getBody() );

			if ( ! isset( $this->keySet[ $keyId ] ) ) {
				return false;
			}

			$item = $this->getCacheItem();
			$item->set( $this->keySet );
			if ( $this->expiresAfter ) {
				$item->expiresAfter( $this->expiresAfter );
			}
			$this->cache->save( $item );
		}

		return true;
	}

	/**
	 * Check if the rate limit has been exceeded.
	 *
	 * @return bool True if rate limit exceeded, false otherwise.
	 */
	private function rateLimitExceeded(): bool {
		if ( ! $this->rateLimit ) {
			return false;
		}

		$cacheItem = $this->cache->getItem( $this->rateLimitCacheKey );

		$cacheItemData = array();
		// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.Found, Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
		if ( $cacheItem->isHit() && \is_array( $data = $cacheItem->get() ) ) {
			$cacheItemData = $data;
		}

		$callsPerMinute = $cacheItemData['callsPerMinute'] ?? 0;
		$expiry         = $cacheItemData['expiry'] ?? new \DateTime( '+60 seconds', new \DateTimeZone( 'UTC' ) );

		if ( ++$callsPerMinute > $this->maxCallsPerMinute ) {
			return true;
		}

		$cacheItem->set(
			array(
				'expiry'         => $expiry,
				'callsPerMinute' => $callsPerMinute,
			)
		);
		$cacheItem->expiresAt( $expiry );
		$this->cache->save( $cacheItem );
		return false;
	}

	/**
	 * Get or create the cache item for the key set.
	 *
	 * @return CacheItemInterface The cache item.
	 */
	private function getCacheItem(): CacheItemInterface {
		if ( null === $this->cacheItem ) {
			$this->cacheItem = $this->cache->getItem( $this->cacheKey );
		}

		return $this->cacheItem;
	}

	/**
	 * Generate and set cache keys from the JWKS URI.
	 *
	 * @return void
	 * @throws RuntimeException If JWKS URI is empty.
	 */
	private function setCacheKeys(): void {
		if ( empty( $this->jwksUri ) ) {
			throw new RuntimeException( 'JWKS URI is empty' );
		}

		// Ensure we do not have illegal characters.
		$key = preg_replace( '|[^a-zA-Z0-9_\.!]|', '', $this->jwksUri );

		// Add prefix.
		$key = $this->cacheKeyPrefix . $key;

		// Hash keys if they exceed $maxKeyLength of 64.
		if ( \strlen( $key ) > $this->maxKeyLength ) {
			$key = substr( hash( 'sha256', $key ), 0, $this->maxKeyLength );
		}

		$this->cacheKey = $key;

		if ( $this->rateLimit ) {
			// Add prefix.
			$rateLimitKey = $this->cacheKeyPrefix . 'ratelimit' . $key;

			// Hash keys if they exceed $maxKeyLength of 64.
			if ( \strlen( $rateLimitKey ) > $this->maxKeyLength ) {
				$rateLimitKey = substr( hash( 'sha256', $rateLimitKey ), 0, $this->maxKeyLength );
			}

			$this->rateLimitCacheKey = $rateLimitKey;
		}
	}
}
