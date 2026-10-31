<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Entities\Keywords;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAdditionalKeywords extends WPKeywords {
	/** @var WPKeyword[] */
	public array $elements = [];
}
