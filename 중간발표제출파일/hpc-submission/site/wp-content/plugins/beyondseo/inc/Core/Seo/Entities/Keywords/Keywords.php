<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Entities\Keywords;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Keywords {
	/** @var Keyword[] */
	public array $elements = [];

	public function add( Keyword $keyword ): void {
		$this->elements[ $keyword->uniqueKey() ] = $keyword;
	}

	public function first(): ?Keyword {
		return reset( $this->elements ) ?: null;
	}

	public function getByUniqueKey( string $uniqueKey ): ?Keyword {
		return $this->elements[ $uniqueKey ] ?? null;
	}

	public function getElements(): array {
		return array_values( $this->elements );
	}

	public function getKeywordByName( string $keywordName ): ?Keyword {
		$uniqueKey = Keyword::getAliasFromName( $keywordName );

		return $this->getByUniqueKey( $uniqueKey );
	}

	public function getCombinedKeywordsAsString(): string {
		$keywordsCombinedString = '';
		foreach ( $this->getElements() as $keyword ) {
			$keywordsCombinedString .= $keyword->name . ', ';
		}

		return substr( $keywordsCombinedString, 0, - 2 );
	}

	public function getKeywordsAsArray(): array {
		$return = [];
		foreach ( $this->getElements() as $keyword ) {
			$return[] = $keyword->name;
		}

		return $return;
	}

	public static function createFromArray( $keywordsArray ): Keywords {
		$keywords = new static();
		foreach ( $keywordsArray as $keyword ) {
			$keywordObj        = new Keyword();
			$keywordObj->name  = $keyword->name;
			$keywordObj->hash  = $keyword->hash;
			$keywordObj->alias = $keyword->alias;
			$keywords->add( $keywordObj );
		}

		return $keywords;
	}

	public function uniqueKey(): string {
		$key = '';
		foreach ( $this->elements as $keyword ) {
			$key .= $keyword->uniqueKey();
		}
		$key = md5( $key );

		return $key;
	}
}
