<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;


/**
 * BreadcrumbList graph class.
 */
class BreadcrumbList extends Graph {
	/**
	 * Returns the graph data.
	 *
     * @param object|null $graphData The graph data.
	 * @return array The graph data.
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public function get($graphData = null): array {

        $moduleManager = ModuleManager::instance();
		$schema = $moduleManager->get_module('schemaMarkup')->schema;
        $options = SettingsManager::instance()->get_options();

		$breadcrumbs = $schema->context['breadcrumb'] ?? '';
		if ( ! $breadcrumbs ) {
			return [];
		}

		$trailLength = count( $breadcrumbs );
		if ( ! $trailLength ) {
			return [];
		}

		$listItems = [];
		foreach ( $breadcrumbs as $index => $breadcrumb ) {
			// Validate breadcrumb structure
			if ( ! isset( $breadcrumb['url'], $breadcrumb['position'] ) ) {
				continue;
			}
			
			$listItem = [
				'@type'    => 'ListItem',
				'@id'      => esc_url( $breadcrumb['url'] ) . '#listItem',
				'position' => (int) $breadcrumb['position'],
				'name'     => sanitize_text_field( $breadcrumb['name'] ?? '' ),
                'item'     => esc_url( $breadcrumb['url'] ),
			];

			// Don't add "item" prop for last crumb.
			if ( $trailLength !== $breadcrumb['position'] ) {
				$listItem['item'] = esc_url( $breadcrumb['url'] );
			}

			if ( 1 === $trailLength ) {
				$listItems[] = $listItem;
				continue;
			}

			// Safe array access for next item
			$nextIndex = $breadcrumb['position'];
			if ( $trailLength > $breadcrumb['position'] && isset( $breadcrumbs[ $nextIndex ] ) ) {
				$nextBreadcrumb = $breadcrumbs[ $nextIndex ];
				if ( isset( $nextBreadcrumb['url'], $nextBreadcrumb['name'] ) ) {
					$listItem['nextItem'] = [
						'@type' => 'ListItem',
						'@id'   => esc_url( $nextBreadcrumb['url'] ) . '#listItem',
						'name'  => sanitize_text_field( $nextBreadcrumb['name'] ),
					];
				}
			}

			// Safe array access for previous item
			$prevIndex = $breadcrumb['position'] - 2;
			if ( 1 < $breadcrumb['position'] && isset( $breadcrumbs[ $prevIndex ] ) ) {
				$prevBreadcrumb = $breadcrumbs[ $prevIndex ];
				if ( isset( $prevBreadcrumb['url'], $prevBreadcrumb['name'] ) ) {
					$listItem['previousItem'] = [
						'@type' => 'ListItem',
						'@id'   => esc_url( $prevBreadcrumb['url'] ) . '#listItem',
						'name'  => sanitize_text_field( $prevBreadcrumb['name'] ),
					];
				}
			}

			$listItems[] = $listItem;
		}

		$data = [
			'@type'           => 'BreadcrumbList',
			'@id'             => $schema->context['url'] . '#breadcrumblist',
			'itemListElement' => $listItems
		];

		return $data;
	}
}