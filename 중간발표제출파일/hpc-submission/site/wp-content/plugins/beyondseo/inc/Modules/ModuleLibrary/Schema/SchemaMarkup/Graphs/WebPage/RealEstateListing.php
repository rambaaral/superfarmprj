<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs\WebPage;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ReflectionException;


/**
 * RealEstateListing graph class.
 */
class RealEstateListing extends WebPage {
	/**
	 * The graph type.
	 *
	 * @var string
	 */
	protected $type = 'RealEstateListing';

    /**
     * Returns the graph data.
     *
     * @param object|null $graphData The graph data.
     * @return array $data The graph data.
     * @throws ReflectionException
     */
	public function get($graphData = null): array {
		$data = parent::get();
        $post = is_singular() ? get_post(get_the_ID()) : null;
		if ( ! $post ) {
			return $data;
		}

		$data['datePosted'] = mysql2date( DATE_W3C, $post->post_date, false );

		return $data;
	}
}