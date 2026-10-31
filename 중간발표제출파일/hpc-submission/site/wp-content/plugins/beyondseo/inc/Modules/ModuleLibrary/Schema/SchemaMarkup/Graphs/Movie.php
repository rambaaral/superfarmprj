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
 * Movie graph class.
 */
class Movie extends Graphs\Graph {

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

		$data = [
			'@type'       => 'Movie',
			'@id'         => ! empty( $graphData->id ) ? $schema->context['url'] . $graphData->id : $schema->context['url'] . '#video',
			'name'        => ! empty( $graphData->properties->name ) ? $graphData->properties->name : get_the_title(),
			'description' => ! empty( $graphData->properties->description ) ? $graphData->properties->description : $schema->context['description'],
			'image'       => ! empty( $graphData->properties->image ) ? $this->image( $graphData->properties->image ) : $this->getFeaturedImage(),
			'director'    => ! empty( $graphData->properties->director ) ? $graphData->properties->director : '',
			'dateCreated' => ! empty( $graphData->properties->releaseDate ) ? mysql2date( DATE_W3C, $graphData->properties->releaseDate, false ) : ''
		];

		if (
			! empty( $graphData->properties->review->rating ) &&
			! empty( $graphData->properties->review->author ) &&
			! empty( $graphData->properties->rating->minimum ) &&
			! empty( $graphData->properties->rating->maximum )
		) {
			$data['review'] = [
				'@type'        => 'Review',
				'headline'     => $graphData->properties->review->headline,
				'reviewBody'   => $graphData->properties->review->content,
				'reviewRating' => [
					'@type'       => 'Rating',
					'ratingValue' => (float) $graphData->properties->review->rating,
					'worstRating' => (float) $graphData->properties->rating->minimum,
					'bestRating'  => (float) $graphData->properties->rating->maximum
				],
				'author'       => [
					'@type' => 'Person',
					'name'  => $graphData->properties->review->author
				]
			];

			$data['aggregateRating'] = [
				'@type'       => 'AggregateRating',
				'ratingValue' => (float) $graphData->properties->review->rating,
				'worstRating' => (float) $graphData->properties->rating->minimum,
				'bestRating'  => (float) $graphData->properties->rating->maximum,
				'reviewCount' => 1
			];
		}

		return $data;
	}
}