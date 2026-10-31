<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs\WebPage;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ReflectionException;

/**
 * ProfilePage graph class.
 */
class ProfilePage extends WebPage {
	/**
	 * The graph type.
	 *
	 * @var string
	 */
	protected $type = 'ProfilePage';

    /**
     * Returns the graph data.
     *
     * @param object|null $graphData The graph data.
     * @return array The graph data.
     * @throws ReflectionException
     */
	public function get($graphData = null): array {
		$data = parent::get();

        $post = is_singular() ? get_post(get_the_ID()) : null;
		$author = get_queried_object();
		if (
			! is_a( $author, 'WP_User' ) &&
			( is_singular() && ! is_a( $post, 'WP_Post' ) )
		) {
			return [];
		}

		global $wp_query; // phpcs:ignore Squiz.NamingConventions.ValidVariableName
		$articles = [];
		$authorId = $author->ID ?? $post->post_author ?? 0;
		foreach ( $wp_query->posts as $post ) { // phpcs:ignore Squiz.NamingConventions.ValidVariableName
			if ( $post->post_author !== $authorId ) {
				continue;
			}

			$articles[] = [
				'@type'         => 'Article',
				'url'           => get_permalink( $post->ID ),
				'headline'      => $post->post_title,
				'datePublished' => mysql2date( DATE_W3C, $post->post_date, false ),
				'dateModified'  => mysql2date( DATE_W3C, $post->post_modified, false ),
				'author'        => [
					'@id' => get_author_posts_url( $authorId ) . '#author'
				]
			];
		}

		$data = array_merge( $data, [
			'dateCreated' => mysql2date( DATE_W3C, $author->user_registered, false ),
			'mainEntity'  => [
				'@id' => get_author_posts_url( $authorId ) . '#author'
			],
			'hasPart'     => $articles

		] );

		return $data;
	}
}