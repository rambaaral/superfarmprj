<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs\WebPage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\SchemaManager;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;

/**
 * WebPage graph class.
 */
class WebPage extends Graphs\Graph {

    use Graphs\Traits\Image;

	/**
	 * The graph type.
	 *
	 * This value can be overridden by WebPage child graphs that are more specific.
	 *
	 * @var string
	 */
	protected $type = 'WebPage';

	/**
	 * Returns the graph data.
	 *
     * @param object|null $graphData The graph data.
	 * @return array $data The graph data.
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public function get($graphData = null): array
    {
        $options = SettingsManager::instance()->get_options();

        /** @var ModuleManager $moduleManager */
        $moduleManager = ModuleManager::instance();
		/** @var SchemaManager $schema */
		$schema = $moduleManager->get_module('schemaMarkup')->schema;

		$homeUrl = trailingslashit( home_url() );
		$data    = [
			'@type'       => $this->type,
			'@id'         => $schema->context['url'] . '#' . strtolower( $this->type ),
			'url'         => $schema->context['url'],
			'name'        => WordpressHelpers::retrieve_title(),
			'description' => $schema->context['description'],
			'inLanguage'  => WordpressHelpers::current_language_code_BCP47(),
			'isPartOf'    => [ '@id' => $homeUrl . '#website' ],
			'breadcrumb'  => [ '@id' => $schema->context['url'] . '#breadcrumblist' ]
		];

		if ( is_singular() && 'page' !== get_post_type() ) {
			$post = WordpressHelpers::retrieve_post();
			if ( is_a( $post, 'WP_Post' ) && post_type_supports( $post->post_type, 'author' ) ) {
				$author = get_author_posts_url( $post->post_author );
				if ( ! empty( $author ) ) {
					if ( ! in_array( 'PersonAuthor', $schema->graphs, true ) ) {
						$schema->graphs[] = 'PersonAuthor';
					}

					$data['author']  = [ '@id' => $author . '#author' ];
					$data['creator'] = [ '@id' => $author . '#author' ];
				}
			}
		}

		if ( isset( $schema->context['description'] ) && $schema->context['description'] ) {
			$data['description'] = $schema->context['description'];
		}

		if ( is_singular() ) {
			if ( ! isset( $schema->context['object'] ) || ! $schema->context['object'] ) {
				$data = $this->getAddonData( $data, 'webPage' );
				return $data;
			}

			$post = $schema->context['object'] ?? null;
			if ( has_post_thumbnail( $post ) ) {
				$image = $this->image( get_post_thumbnail_id(), 'mainImage' );
				if ( $image ) {
					$data['image']              = $image;
					$data['primaryImageOfPage'] = [
						'@id' => $schema->context['url'] . '#mainImage'
					];
				}
			}

			if($post) {
				$data['datePublished'] = mysql2date( DATE_W3C, $post->post_date, false );
				$data['dateModified']  = mysql2date( DATE_W3C, $post->post_modified, false );
			}

			return $data;
		}

		if ( is_front_page() ) {
			$data['about'] = [ '@id' => trailingslashit( home_url() ) . '#' . $options['site_represents'] ];
		}

		$data = $this->getAddonData( $data, 'webPage' );

		return $data;
	}
}