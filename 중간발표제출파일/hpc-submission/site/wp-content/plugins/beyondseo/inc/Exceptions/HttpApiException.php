<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Exceptions;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class HttpApiException
 */
class HttpApiException extends Exception {

	/**
	 * HttpApiException constructor.
	 *
	 * @param string $message The exception message.
	 * @param int $code The exception code.
	 * @param Exception|null $previous The previous exception.
	 */
	public function __construct(string $message, int $code = 0, ?Exception $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}
}
