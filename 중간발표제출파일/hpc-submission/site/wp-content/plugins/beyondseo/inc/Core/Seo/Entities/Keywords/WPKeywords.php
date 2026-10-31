<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Entities\Keywords;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPKeywords extends Keywords {
	public static function createFromKeywordArray( Keywords $keywordsArray ): WPKeywords {
		$keywords = new self();
		foreach ( $keywordsArray->getElements() as $keyword ) {
			$keywordObj = WPKeyword::createFromKeyword( $keyword );
			$keywords->add( $keywordObj );
		}

		return $keywords;
	}

	public static function addOnboardingKeywords( WPKeywords $currentOnboardingKeywords, Keywords $newKeywords ): WPKeywords {
		$wpKeywordHashes = [];
		foreach ( $currentOnboardingKeywords->getElements() as $wpKeyword ) {
			$wpKeywordHashes[] = $wpKeyword->hash;
		}

		foreach ( $newKeywords->getElements() as $keyword ) {
			if ( ! in_array( $keyword->hash, $wpKeywordHashes, true ) ) {
				$newWpKeyword = WPKeyword::createFromKeyword( $keyword );
				$currentOnboardingKeywords->add( $newWpKeyword );
			}
		}

		return $currentOnboardingKeywords;
	}
}
