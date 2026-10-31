<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Entities\Keywords;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use RankingCoach\Inc\Core\Helpers\Datafilter;

class Keyword {
	public ?string $alias = null;
	public ?string $hash = null;
	public ?string $name = null;

	public function setName( ?string $name = null ): void {
		$this->name  = self::cleanName( (string) $name );
		$this->alias = self::getAliasFromName( (string) $name );
		$this->hash  = md5( (string) $this->name );
	}

	public static function cleanName( string $name ): string {
		return strtolower( Datafilter::clean_keyword( $name ) );
	}

	public function uniqueKey(): string {
		return $this->getAlias();
	}

	public static function getAliasFromName( string $keywordName ): string {
		return Datafilter::alias( $keywordName, '_' );
	}

	public function getAlias(): string {
		if ( ! isset( $this->alias ) && isset( $this->name ) ) {
			$this->setName( $this->name );
		}

		return (string) $this->alias;
	}
}
