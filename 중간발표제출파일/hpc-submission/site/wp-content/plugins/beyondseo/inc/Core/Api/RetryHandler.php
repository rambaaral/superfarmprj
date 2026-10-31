<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Api;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Exceptions\HttpApiException;

/**
 * Class RetryHandler
 */
class RetryHandler {
	private int $maxRetries;
	private int $backoffFactor;

	/**
	 * RetryHandler constructor.
	 *
	 * @param int $maxRetries
	 * @param int $backoffFactor
	 */
	public function __construct( int $maxRetries = 3, int $backoffFactor = 5 ) {
		$this->maxRetries    = $maxRetries;
		$this->backoffFactor = $backoffFactor;
	}

	/**
	 * Execute a request function with retries.
	 *
	 * @param callable $requestFunction
	 *
	 * @return mixed
	 * @throws HttpApiException
	 */
	public function execute( callable $requestFunction ): mixed {
		$retries = $this->maxRetries;

		while ( $retries > 0 ) {
			try {
				return $requestFunction();
			} catch ( HttpApiException $e ) {
				if ( ! in_array( $e->getCode(), [ 0, 504 ], true ) ) {
					throw $e;
				}
				$retries --;
				if ( $retries === 0 ) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					throw new HttpApiException( esc_html__('Request failed after multiple retries: ', 'beyondseo') . $e->getMessage(), $e->getCode() );
				}
				sleep( $this->backoffFactor * 2 ** ( $this->maxRetries - $retries - 1 ) );
			}
		}

		return [];
	}
}
