<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Entities\Keywords;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPPrimaryKeyword extends WPKeyword {
	public ?string $relevance_score = null;
	public ?string $intent          = null;
	public ?string $density         = null;

	public function __construct( ?string $relevance_score = null, ?string $intent = null, ?string $density = null ) {
		$this->relevance_score = $relevance_score;
		$this->intent          = $intent;
		$this->density         = $density;
	}
}
