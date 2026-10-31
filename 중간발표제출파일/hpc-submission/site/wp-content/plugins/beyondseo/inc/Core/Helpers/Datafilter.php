<?php

namespace RankingCoach\Inc\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Datafilter {
	public static function clean_keyword( string $string ): string {
		$string = mb_strtolower( $string );
		$string = html_entity_decode( $string );
		$string = str_replace( [ '&#39;', '’' ], "'", $string );

		return trim( $string );
	}

	public static function alias( string $string, string $spaceDelimiter = '-' ): string {
		$string = trim( mb_strtolower( $string ) );
		$string = str_replace( [ '&', ',', '.', '!', '?' ], '-', $string );
		$string = str_replace( ' ', $spaceDelimiter, $string );
		$string = str_replace( [ '“', '”' ], '"', $string );
		$string = preg_replace( '/[^@0-9' . $spaceDelimiter . '\-_\p{L}\'"’]/iu', '', $string );
		$string = preg_replace( '/[-]{2,}/', '-', $string );

		return $string;
	}
}
