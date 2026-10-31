<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs\Music;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;

/**
 * Music Album class
 */
class MusicAlbum extends Music {
    /**
     * Returns the graph data.
     *
     * @param object|null $graphData The graph data.
     * @return array            The parsed graph data.
     * @throws Exception
     */
	public function get( $graphData = null ): array {
		$data          = parent::get( $graphData );
		$data['@type'] = 'MusicAlbum';

		return $data;
	}
}