<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Libs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UrlDomain provides utility functions for normalizing and comparing URL domains.
 * It handles IDN conversion, 'www.' prefix stripping, and domain comparison.
 */
final class UrlDomain {
	/**
	 * Normalize a URL host to a standard format.
	 *
	 * @param string|null $url The URL to normalize.
	 * @param bool $stripWww Whether to remove 'www.' prefix.
	 * @param bool $idnAscii Whether to convert IDN to ASCII.
	 * @return string|null Normalized host or null if invalid.
	 */
	public static function normalizeHost( ?string $url, bool $stripWww = true, bool $idnAscii = true ): ?string {
		if ( ! $url ) {
			return null;
		}

		$url = trim( $url );
		// Accept protocol-relative URLs.
		if ( str_starts_with( $url, '//' ) ) {
			$url = 'https:' . $url;
		}

		// Tolerant to URLs without scheme (wp_parse_url is more permissive).
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return null;
		}

		$host = rtrim( strtolower( $host ), '.' );

		if ( $idnAscii && function_exists( 'idn_to_ascii' ) ) {
			$ascii = @idn_to_ascii( $host, IDNA_DEFAULT );
			if ( $ascii ) {
				$host = strtolower( $ascii );
			}
		}

		if ( $stripWww && str_starts_with( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}

		return $host ?: null;
	}

	/**
	 * Get the current site domain, normalized.
	 *
	 * @param bool $stripWww Whether to remove 'www.' prefix.
	 * @return string|null Normalized site domain or null if invalid.
	 */
	public static function siteDomain( bool $stripWww = true ): ?string {
		$url = function_exists( 'is_multisite' ) && is_multisite() ? network_home_url() : home_url();

		return self::normalizeHost( $url, $stripWww );
	}

	/**
	 * Check if two URLs belong to the same domain.
	 *
	 * @param string|null $a First URL to compare.
	 * @param string|null $b Second URL to compare.
	 * @param bool $stripWww Whether to remove 'www.' prefix for comparison.
	 * @return bool True if both URLs are from the same domain, false otherwise.
	 */
	public static function sameDomain( ?string $a, ?string $b, bool $stripWww = true ): bool {
		$h1 = self::normalizeHost( $a, $stripWww );
		$h2 = self::normalizeHost( $b, $stripWww );

		return $h1 !== null && $h1 === $h2;
	}
}
