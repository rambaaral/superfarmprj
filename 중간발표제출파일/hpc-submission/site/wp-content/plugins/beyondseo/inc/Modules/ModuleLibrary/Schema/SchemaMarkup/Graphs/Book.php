<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\SchemaManager;
use RankingCoach\Inc\Modules\ModuleManager;

/**
 * Book graph class.
 */
class Book extends Graphs\Graph {

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

        $options = SettingsManager::instance()->get_options();

        /** @var ModuleManager $moduleManager */
        $moduleManager = ModuleManager::instance();
        /** @var SchemaManager $schema */
        $schema = $moduleManager->get_module('schemaMarkup')->schema;

		$data = [
			'@type'       => 'Book',
			'@id'         => ! empty( $graphData->id ) ? $schema->context['url'] . $graphData->id : $schema->context['url'] . '#book',
			'name'        => $graphData->properties->name,
			'description' => ! empty( $graphData->properties->description ) ? $graphData->properties->description : '',
			'author'      => '',
			'url'         => $schema->context['url'],
			'image'       => ! empty( $graphData->properties->image ) ? $this->image( $graphData->properties->image ) : '',
			'inLanguage'  => null,
			'publisher'   => [ '@id' => trailingslashit( home_url() ) . '#' . $options['site_represents'] ],
			'hasPart'     => []
		];

		if ( ! empty( $graphData->properties->author ) ) {
			$data['author'] = [
				'@type' => 'Person',
				'name'  => $graphData->properties->author
			];
		}

		if ( ! empty( $graphData->properties->editions ) ) {
			foreach ( $graphData->properties->editions as $editionData ) {
				if ( empty( $editionData->name ) ) {
					continue;
				}

				$edition = [
					'@type'         => 'Book',
					'name'          => ! empty( $editionData->name ) ? $editionData->name : '',
					'bookEdition'   => ! empty( $editionData->bookEdition ) ? $editionData->bookEdition : '',
					'author'        => '',
					'isbn'          => ! empty( $editionData->isbn ) ? $editionData->isbn : '',
					'bookFormat'    => ! empty( $editionData->format ) ? $editionData->format : '',
					'datePublished' => ! empty( $editionData->datePublished ) ? mysql2date( DATE_W3C, $editionData->datePublished, false ) : ''
				];

				if ( ! empty( $editionData->author ) ) {
					$edition['author'] = [
						'@type' => 'Person',
						'name'  => $editionData->author
					];
				}

				$data['hasPart'][] = $edition;
			}
		}

		return $data;
	}
}