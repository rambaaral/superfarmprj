<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs\WebPage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;

/**
 * Person Author graph class.
 * This a secondary Person graph for post authors and BuddyPress profile pages.
 */
class PersonAuthor extends Graphs\Graph {

    use Graphs\Traits\Image;

	/**
	 * Returns the graph data.
	 *
	 * @param int|null $userId The user ID.
	 * @return array $data   The graph data.
	 * @throws ReflectionException
	 * @throws Exception
	 *
	 */
	public function get( $userId = null ): array {

        /** @var ModuleManager $moduleManager */
        $moduleManager = ModuleManager::instance();
		$schema = $moduleManager->get_module('schemaMarkup')->schema;

		$post         = WordpressHelpers::retrieve_post();

		$user         = get_queried_object();
		$isAuthorPage = is_author() && is_a( $user, 'WP_User' );
		if (
			(
				( ! is_singular() && ! $isAuthorPage ) ||
				( is_singular() && ! is_a( $post, 'WP_Post' ) )
			) &&
			! $userId
		) {
			return [];
		}

		// Dynamically determine the User ID.
		if ( ! $userId ) {
			$userId = $isAuthorPage ? $user->ID : $post->post_author;
		}
		if ( ! $userId ) {
			return [];
		}

		$authorUrl = get_author_posts_url( $userId );

		$data = [
			'@type' => 'Person',
			'@id'   => $authorUrl . '#author',
			'url'   => $authorUrl,
			'name'  => get_the_author_meta( 'display_name', $userId )
		];

		$avatar = $this->avatar( $userId, 'authorImage' );
		if ( $avatar ) {
			$data['image'] = $avatar;
		}

		if ( is_author() ) {
			$data['mainEntityOfPage'] = [
				'@id' => $schema->context['url'] . '#profilepage'
			];
		}

		return $data;
	}
}