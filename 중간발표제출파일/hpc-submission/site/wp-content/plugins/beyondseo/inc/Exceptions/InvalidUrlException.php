<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;

/**
 * Class InvalidUrlException
 */
class InvalidUrlException extends Exception {

	protected $message = 'The provided URL is invalid or not set.';

}