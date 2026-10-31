<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs\Music;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\SchemaManager;
use RankingCoach\Inc\Modules\ModuleManager;

/**
 * Music class
 */
class Music extends Graphs\Graph {

    use Graphs\Traits\Image;

    /**
     * Returns the graph data.
     *
     * @param object|null $graphData The graph data.
     * @return array             The parsed graph data.
     * @throws Exception
     *
     */
	public function get( $graphData = null ): array {

        /** @var ModuleManager $moduleManager */
        $moduleManager = ModuleManager::instance();
        /** @var SchemaManager $schema */
        $schema = $moduleManager->get_module('schemaMarkup')->schema;

		return [
			'@type'       => '',
			'@id'         => $schema->context['url'] . $graphData->id,
			'name'        => $graphData->properties->name,
			'description' => $graphData->properties->description,
			'url'         => $schema->context['url'],
			'image'       => $graphData->properties->image ? $this->image( $graphData->properties->image ) : ''
		];
	}
}