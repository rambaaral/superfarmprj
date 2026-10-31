<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;

/**
 * Class UnsupportedHttpMethodException
 */
class UnsupportedHttpMethodException extends Exception {

	protected $message = 'The provided HTTP method is not supported.';
}