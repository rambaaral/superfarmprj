<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;

/**
 * Class UnsupportedContentTypeException
 */
class UnsupportedContentTypeException extends Exception {

	protected $message = 'The provided HTTP method is not supported.';
}