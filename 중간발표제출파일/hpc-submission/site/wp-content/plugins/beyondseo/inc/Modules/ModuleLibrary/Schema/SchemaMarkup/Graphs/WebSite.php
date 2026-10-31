<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Settings\SettingsManager;

/**
 * WebSite graph class.
 */
class WebSite extends Graph {
	/**
	 * Returns the graph data.
	 *
     * @param object|null $graphData The graph data.
	 * @return array $data The graph data.
	 * @throws Exception
	 *
	 */
	public function get($graphData = null): array
    {
        $options = SettingsManager::instance()->get_options();
		$homeUrl = trailingslashit( home_url() );
		$data    = [
			'@type'         => 'WebSite',
			'@id'           => $homeUrl . '#website',
			'url'           => $homeUrl,
			'name'          => CoreHelper::decode_html_entities( get_bloginfo( 'name' ) ),
			'description'   => CoreHelper::decode_html_entities( get_bloginfo( 'description' ) ),
			'inLanguage'    => WordpressHelpers::current_language_code_BCP47(),
			'publisher'     => [ '@id' => $homeUrl . '#' . $options['site_represents'] ]
		];

		if ( is_front_page() && ($options['site_links'] ?? false) ) {
			$defaultSearchAction = [
				'@type'       => 'SearchAction',
				'target'      => [
					'@type'       => 'EntryPoint',
					'urlTemplate' => $homeUrl . '?s={search_term_string}'
				],
				'query-input' => 'required name=search_term_string',
			];

			$data['potentialAction'] = $defaultSearchAction;
		}

		return $data;
	}
}