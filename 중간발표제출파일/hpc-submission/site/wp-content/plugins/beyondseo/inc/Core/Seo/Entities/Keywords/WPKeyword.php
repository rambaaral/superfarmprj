<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Entities\Keywords;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPKeyword extends Keyword {
	public ?int $id = null;
	public ?int $externalId = null;

	public static function createFromKeyword( Keyword $keyword ): WPKeyword {
		$wpKeyword             = new self();
		$wpKeyword->name       = $keyword->name;
		$wpKeyword->hash       = $keyword->hash;
		$wpKeyword->alias      = $keyword->alias;
		$wpKeyword->externalId = property_exists( $keyword, 'id' ) ? $keyword->id : null;

		return $wpKeyword;
	}

	public function uniqueKey(): string {
		return (string) ( $this->hash ?? parent::uniqueKey() );
	}
}
