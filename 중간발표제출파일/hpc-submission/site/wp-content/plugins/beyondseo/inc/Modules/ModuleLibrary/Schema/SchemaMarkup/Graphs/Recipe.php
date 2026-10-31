<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs\Traits\Schema\ReviewRating;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\SchemaManager;
use RankingCoach\Inc\Modules\ModuleManager;
use WP_Post;
use WP_User;

/**
 * Recipe graph class.
 */
class Recipe extends Graphs\Graph {

	use ReviewRating;
    use Graphs\Traits\Image;
    
    /**
     * Temporary graph data storage.
     *
     * @var object|null
     */
    public $graphData;

    /**
     * Returns the graph data.
     *
     * @param object|null $graphData The graph data.
     * @return array             The parsed graph data.
     * @throws Exception
     *
     */
	public function get( $graphData = null ): array {
		try {
			$this->graphData = $graphData;

        /** @var ModuleManager $moduleManager */
        $moduleManager = ModuleManager::instance();
        /** @var SchemaManager $schema */
        $schema = $moduleManager->get_module('schemaMarkup')->schema;

		// Resolve the author from the post itself; the global $authordata used by
		// get_the_author_meta() without an ID is not set outside The Loop.
		$post       = WordpressHelpers::retrieve_post();
		$postAuthor = $post instanceof WP_Post ? get_userdata( (int) $post->post_author ) : false;

		$data = [
			'@type'              => 'Recipe',
			'@id'                => ! empty( $graphData->id ) ? $schema->context['url'] . $graphData->id : $schema->context['url'] . '#recipe',
			'name'               => ! empty( $graphData->properties->name ) ? $graphData->properties->name : get_the_title(),
			'description'        => ! empty( $graphData->properties->description ) ? $graphData->properties->description : '',
			'author'             => [
				'@type' => 'Person',
				'name'  => ! empty( $graphData->properties->author )
					? $graphData->properties->author
					: ( $postAuthor instanceof WP_User ? $postAuthor->display_name : '' )
			],
			'image'              => ! empty( $graphData->properties->image ) ? $this->image( $graphData->properties->image ) : $this->getFeaturedImage(),
			'recipeCategory'     => ! empty( $graphData->properties->dishType ) ? $graphData->properties->dishType : '',
			'recipeCuisine'      => ! empty( $graphData->properties->cuisineType ) ? $graphData->properties->cuisineType : '',
			'prepTime'           => '',
			'cookTime'           => '',
			'totalTime'          => '',
			'recipeYield'        => ! empty( $graphData->properties->nutrition->servings ) ? $graphData->properties->nutrition->servings : '',
			'nutrition'          => [],
			'recipeIngredient'   => [],
			'recipeInstructions' => [],
			'keywords'           => '',
			'review'             => $this->getReview(),
			'aggregateRating'    => $this->getAggregateRating()
		];

		if ( ! empty( $graphData->properties->timeRequired->preparation ) && ! empty( $graphData->properties->timeRequired->cooking ) ) {
			$data['prepTime']  = $this->timeToIso8601DurationFormat( 0, 0, $graphData->properties->timeRequired->preparation );
			$data['cookTime']  = $this->timeToIso8601DurationFormat( 0, 0, $graphData->properties->timeRequired->cooking );

			$totalTime         = (int) $graphData->properties->timeRequired->preparation + (int) $graphData->properties->timeRequired->cooking;
			$data['totalTime'] = $this->timeToIso8601DurationFormat( 0, 0, $totalTime );
		}

		if ( ! empty( $graphData->properties->nutrition->servings ) && ! empty( $graphData->properties->nutrition->calories ) ) {
			$data['nutrition'] = [
				'@type'    => 'NutritionInformation',
				'calories' => $graphData->properties->nutrition->calories . ' ' . __( 'Calories', 'beyondseo')
			];
		}

		if ( ! empty( $graphData->properties->keywords ) ) {
			$keywords = json_decode( $graphData->properties->keywords, true );
			$keywords = array_map( function ( $keywordObject ) {
				return $keywordObject['value'];
			}, $keywords );
			$data['keywords'] = implode( ', ', $keywords );
		}

		if ( ! empty( $graphData->properties->ingredients ) ) {
			$ingredients = json_decode( $graphData->properties->ingredients, true );
			$ingredients = array_map( function ( $ingredientObject ) {
				return $ingredientObject['value'];
			}, $ingredients );
			$data['recipeIngredient'] = $ingredients;
		}

		if ( ! empty( $graphData->properties->instructions ) ) {
			foreach ( $graphData->properties->instructions as $instructionData ) {
				if ( empty( $instructionData->text ) ) {
					continue;
				}

				$data['recipeInstructions'][] = [
					'@type' => 'HowToStep',
					'name'  => $instructionData->name,
					'text'  => $instructionData->text,
					'image' => $instructionData->image
				];
			}
		}

		return $data;
		} finally {
			// Clean up to prevent memory leaks
			$this->graphData = null;
		}
	}

    /**
     * @param $days
     * @param $hours
     * @param $minutes
     * @return string
     */
    public function timeToIso8601DurationFormat( $days, $hours, $minutes ) {
        $duration = 'P';
        if ( $days ) {
            $duration .= $days . 'D';
        }

        $duration .= 'T';
        if ( $hours ) {
            $duration .= $hours . 'H';
        }

        if ( $minutes ) {
            $duration .= $minutes . 'M';
        }

        return $duration;
    }
}
