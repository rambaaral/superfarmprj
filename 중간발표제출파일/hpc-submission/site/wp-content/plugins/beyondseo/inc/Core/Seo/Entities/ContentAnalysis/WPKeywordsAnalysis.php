<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Entities\ContentAnalysis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use RankingCoach\Inc\Core\Seo\Entities\Keywords\Keywords;
use RankingCoach\Inc\Core\Seo\Entities\Keywords\WPAdditionalKeywords;
use RankingCoach\Inc\Core\Seo\Entities\Keywords\WPPrimaryKeyword;

class WPKeywordsAnalysis {
	public ?WPPrimaryKeyword $primaryKeywordFromExisting;
	public ?WPPrimaryKeyword $primaryKeywordFromContent;
	public ?WPAdditionalKeywords $additionalKeywordsFromExisting;
	public ?WPAdditionalKeywords $additionalKeywordsFromContent;
	public ?Keywords $existingKeywords;

	public function __construct(
		?WPPrimaryKeyword $matchPrimaryKeywordFromExisting = null,
		?WPPrimaryKeyword $matchPrimaryKeywordFromContent = null,
		?WPAdditionalKeywords $additionalKeywordsFromExisting = null,
		?WPAdditionalKeywords $additionalKeywordsFromContent = null,
		?Keywords $existingKeywords = null
	) {
		$this->primaryKeywordFromExisting    = $matchPrimaryKeywordFromExisting;
		$this->primaryKeywordFromContent     = $matchPrimaryKeywordFromContent;
		$this->additionalKeywordsFromExisting = $additionalKeywordsFromExisting;
		$this->additionalKeywordsFromContent  = $additionalKeywordsFromContent;
		$this->existingKeywords              = $existingKeywords;
	}
}
