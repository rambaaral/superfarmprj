<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Interface MetaBuilderInterface
 */
interface MetaHeadBuilderInterface {

	/**
     * Builds the meta tags for the current page.
     *
     * @return string
     */
	public function generateMetaTags(): string;

    /**
     * Get the priority of the meta tags.
     *
     * @return int
     */
    public function getMetaTagsPriority(): int;
}