<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WP_REST_Response;
use WP_Error;
use WP_REST_Request;

/**
 * Class RestHelpers
 *
 * Provides helper methods for handling REST API operations in WordPress.
 */
class RestHelpers {

    public const TRANSIENT_KEY = 'rankingcoach_rest_use_fallback';
    public const TTL_SECONDS   = 12 * HOUR_IN_SECONDS;

	/**
	 * Validates if a given ID is a positive integer.
	 *
	 * @param mixed $id
	 * @return bool
	 */
	public static function validateId( mixed $id): bool {
		return is_numeric($id) && $id > 0;
	}

	/**
	 * Generates a JSON response for the REST API.
	 *
	 * @param array $data
	 * @param int $status_code
	 * @return WP_REST_Response
	 */
	public static function jsonResponse(array $data, int $status_code = 200): WP_REST_Response {
		return new WP_REST_Response($data, $status_code);
	}

	/**
	 * Generates an error response for the REST API.
	 *
	 * @param string $message
	 * @param int $status_code
	 * @return WP_Error
	 */
	public static function errorResponse(string $message, int $status_code = 400): WP_Error {
		return new WP_Error('rest_error', $message, ['status' => $status_code]);
	}

	/**
	 * Checks if the current user has the required capability.
	 *
	 * @param string $capability
	 * @return bool
	 */
	public static function currentUserCan(string $capability): bool {
		return current_user_can($capability);
	}

	/**
	 * Transforms a database object into an array suitable for REST API response.
	 *
	 * @param object $item
	 * @param WP_REST_Request $request
	 * @return array
	 */
	public static function prepareItemForResponse(object $item, WP_REST_Request $request): array {
		return [
			'id'    => $item->ID,
			'title' => $item->post_title,
			'date'  => $item->post_date,
		];
	}

	/**
	 * Registers a REST API route.
	 *
	 * @param string $namespace
	 * @param string $route
	 * @param array $args
	 * @return void
	 */
	public static function registerRoute(string $namespace, string $route, array $args): void {
		register_rest_route($namespace, $route, $args);
	}

    /**
     * Returns either the pretty root ".../wp-json/" or the fallback ".../index.php?rest_route=/".
     */
    public static function getRestRoot(): string
    {
        $useFallback = (bool) get_transient(self::TRANSIENT_KEY);
        if ($useFallback) {
            return trailingslashit(home_url('/index.php')) . '?rest_route=/';
        }
        return trailingslashit(rest_url());
    }

    /**
     * Build a full REST URL from a REST path like "/wp/v2/posts" or "rankingcoach/v1/download_logs".
     */
    public static function buildUrl(string $route): string
    {
        $route = '/' . ltrim($route, '/');
        $root  = self::getRestRoot();

        // If using fallback, root already ends with "?rest_route=/"
        if (str_contains($root, 'rest_route=')) {
            // Ensure we don't double slash after rest_route=/
            return rtrim($root, '/') . $route;
        }
        return rtrim($root, '/') . $route;
    }
}
