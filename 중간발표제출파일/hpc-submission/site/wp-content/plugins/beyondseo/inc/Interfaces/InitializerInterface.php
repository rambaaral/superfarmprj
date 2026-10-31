<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Interface InitializerInterface
 */
interface InitializerInterface
{
	/**
	 * Initializes the object.
	 *
	 * @return void
	 */
    public function initialize(): void;
}