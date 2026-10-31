<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;

/**
 * Class ResponseValidationException
 */
class ResponseValidationException extends Exception {

	/**
	 * ResponseValidationException constructor.
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
