<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\SchemaManager;
use RankingCoach\Inc\Modules\ModuleManager;

/**
 * Video graph class.
 */
class Video extends Graphs\Graph {
    /**
     * Returns the graph data.
     *
     * @param object|null $graphData The graph data.
     * @return array             The parsed graph data.
     * @throws Exception
     */
	public function get( $graphData = null ): array {

        /** @var ModuleManager $moduleManager */
        $moduleManager = ModuleManager::instance();
        /** @var SchemaManager $schema */
        $schema = $moduleManager->get_module('schemaMarkup')->schema;

		$data = [
			'@type'        => 'VideoObject',
			'@id'          => ! empty( $graphData->id ) ? $schema->context['url'] . $graphData->id : $schema->context['url'] . '#video',
			'name'         => ! empty( $graphData->properties->name ) ? $graphData->properties->name : get_the_title(),
			'description'  => ! empty( $graphData->properties->description ) ? $graphData->properties->description : $schema->context['description'],
			'contentUrl'   => ! empty( $graphData->properties->contentUrl ) ? $graphData->properties->contentUrl : '',
			'embedUrl'     => ! empty( $graphData->properties->embedUrl ) ? $graphData->properties->embedUrl : '',
			'thumbnailUrl' => ! empty( $graphData->properties->thumbnailUrl ) ? $graphData->properties->thumbnailUrl : '',
			'uploadDate'   => ! empty( $graphData->properties->uploadDate ) ? mysql2date( DATE_W3C, $graphData->properties->uploadDate, false ) : ''
		];

		if ( isset( $graphData->properties->familyFriendly ) ) {
			if ( ! empty( $graphData->properties->familyFriendly ) ) {
				$data['isFamilyFriendly'] = 'https://schema.org/True';
			} else {
				$data['isFamilyFriendly'] = 'https://schema.org/False';
			}
		}

		return $data;
	}
}