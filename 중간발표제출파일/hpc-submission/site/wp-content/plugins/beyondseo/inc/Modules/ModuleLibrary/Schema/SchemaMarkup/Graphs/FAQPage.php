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
 * FAQPage graph class.
 */
class FAQPage extends Graph {
	/**
	 * Returns the subgraph(s)' data.
	 * We only return the sub-graphs since all FAQ pages need to be grouped under a single main entity.
	 * We'll group them later on right before we return the schema as JSON.
	 *
     * @param object|null $graphData The graph data.
     * @param bool $isBlock Whether the graph is for a block or not.
	 * @return array             The parsed graph data.
	 */
	public function get( $graphData = null, $isBlock = false ): array {
		if ( $isBlock ) {
			if ( $graphData && isset( $graphData->question, $graphData->answer ) && 
				 ! empty( $graphData->question ) && ! empty( $graphData->answer ) ) {
				return [
					'@type'          => 'Question',
					'name'           => wp_strip_all_tags( $graphData->question ),
					'acceptedAnswer' => [
						'@type' => 'Answer',
						'text'  => wp_kses_post( $graphData->answer )
					]
				];
			}

			return [];
		}

		$faqPages = [];
		if ( $graphData && isset( $graphData->properties->questions ) && ! empty( $graphData->properties->questions ) ) {
			foreach ( $graphData->properties->questions as $data ) {
				if ( ! isset( $data->question, $data->answer ) || 
					 empty( $data->question ) || empty( $data->answer ) ) {
					continue;
				}

				$faqPages[] = [
					'@type'          => 'Question',
					'name'           => wp_strip_all_tags( $data->question ),
					'acceptedAnswer' => [
						'@type' => 'Answer',
						'text'  => wp_kses_post( $data->answer )
					]
				];
			}
		}

		return $faqPages;
	}

    /**
     * Returns the main FAQ graph with all its subgraphs (questions/answers).
     *
     * @param array $subGraphs The subgraphs.
     * @param null $graphData The graph data (optional).
     * @return array             The main graph data.
     * @throws Exception
     *
     */
	public function getMainGraph( $subGraphs = [], $graphData = null ) {
		if ( empty( $subGraphs ) ) {
			return [];
		}

        /** @var ModuleManager $moduleManager */
        $moduleManager = ModuleManager::instance();
        /** @var SchemaManager $schema */
        $schema = $moduleManager->get_module('schemaMarkup')->schema;

		return [
			'@type'       => 'FAQPage',
			'@id'         => ( $graphData && isset( $graphData->id ) && ! empty( $graphData->id ) ) 
				? esc_url( $schema->context['url'] ) . sanitize_key( $graphData->id ) 
				: esc_url( $schema->context['url'] ) . '#faq',
			'name'        => ( $graphData && isset( $graphData->properties->name ) && ! empty( $graphData->properties->name ) ) 
				? sanitize_text_field( $graphData->properties->name ) 
				: get_the_title(),
			'description' => ( $graphData && isset( $graphData->properties->description ) && ! empty( $graphData->properties->description ) ) 
				? wp_strip_all_tags( $graphData->properties->description ) 
				: get_the_excerpt(),
			'url'         => esc_url( $schema->context['url'] ),
			'mainEntity'  => $subGraphs,
			'inLanguage'  => get_bloginfo( 'language' ),
			'isPartOf'    => empty( $graphData ) ? [ '@id' => trailingslashit( home_url() ) . '#website' ] : '',
			'breadcrumb'  => [ '@id' => esc_url( $schema->context['url'] ) . '#breadcrumblist' ]
		];
	}
}