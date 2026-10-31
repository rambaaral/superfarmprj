<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Breadcrumbs\BreadcrumbsManager;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;

/**
 * Class SchemaGraphsContext
 */
class SchemaGraphsContext {
	/**
	 * Breadcrumb class instance.
	 *
	 * @var BreadcrumbsManager|null
	 */
	public ?BreadcrumbsManager $breadcrumb = null;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->breadcrumb = new BreadcrumbsManager();
	}

    /**
     * Returns the default context data.
     *
     * @return array The context data.
     * @throws Exception
     */
	public function defaults(): array {
		return [
			'name'        => WordpressHelpers::retrieve_title(),
			'description' => WordpressHelpers::retrieve_description(),
			'url'         => WordpressHelpers::retrieve_url(),
			'breadcrumb'  => []
		];
	}

    /**
     * Returns the context data for the homepage.
     *
     * @return array $context The context data.
     * @throws Exception
     */
	public function home(): array {
		$context = [
			'name'        => WordpressHelpers::retrieve_title(),
			'description' => WordpressHelpers::retrieve_description(),
            'url'         => WordpressHelpers::retrieve_url(),
            'breadcrumb'  => $this->breadcrumb->home(),
		];

		// Homepage set to show latest posts.
		if ( 'posts' === get_option( 'show_on_front' ) && is_home() ) {
			return $context;
		}

		// Homepage set to static page.
		$post = WordpressHelpers::retrieve_post();
		if ( ! $post ) {
			return [
				'name'        => '',
				'description' => '',
				'url'         => WordpressHelpers::retrieve_url(),
				'breadcrumb'  => [],
			];
		}

		$context['object'] = $post;

		return $context;
	}

    /**
     * Returns the context data for the requested post.
     *
     * @return array The context data.
     * @throws Exception
     */
	public function post(): array {
		$post = WordpressHelpers::retrieve_post();
		if ( ! $post ) {
			return [
				'name'        => '',
				'description' => '',
				'url'         => WordpressHelpers::retrieve_url(),
				'breadcrumb'  => [],
			];
		}

		return [
			'name'        => WordpressHelpers::retrieve_title($post),
			'description' => WordpressHelpers::retrieve_description($post),
			'url'         => WordpressHelpers::retrieve_url(),
			'breadcrumb'  => $this->breadcrumb->post( $post ),
			'object'      => $post,
		];
	}

    /**
     * Returns the context data for the requested term archive.
     *
     * @return array The context data.
     * @throws Exception
     */
	public function term(): array {
		$term = get_queried_object();
		if ( ! $term ) {
			return [
				'name'        => '',
				'description' => '',
				'url'         => WordpressHelpers::retrieve_url(),
				'breadcrumb'  => [],
			];
		}

		return [
			'name'        => WordpressHelpers::retrieve_title(),
			'description' => WordpressHelpers::retrieve_description(),
			'url'         => WordpressHelpers::retrieve_url(),
			'breadcrumb'  => $this->breadcrumb->term( $term )
		];
	}

    /**
     * Returns the context data for the requested author archive.
     *
     * @return array The context data.
     * @throws Exception
     */
	public function author(): array {
		$author = get_queried_object();
		if ( ! $author ) {
			return [
				'name'        => '',
				'description' => '',
				'url'         => WordpressHelpers::retrieve_url(),
				'breadcrumb'  => [],
			];
		}

		$title       = WordpressHelpers::retrieve_title();
		$description = WordpressHelpers::retrieve_description();
		$url         = WordpressHelpers::retrieve_url();

		if ( ! $description ) {
			$description = get_the_author_meta( 'description', $author->ID );
		}

		return [
			'name'        => $title,
			'description' => $description,
			'url'         => $url,
			'breadcrumb'  => $this->breadcrumb->setPositions( [
				'name'        => get_the_author_meta( 'display_name', $author->ID ),
				'description' => $description,
				'url'         => $url,
				'type'        => 'CollectionPage'
			] )
		];
	}

    /**
     * Returns the context data for the requested post archive.
     *
     * @return array The context data.
     * @throws Exception
     */
	public function postArchive(): array {
		$postType = get_queried_object();
		if ( ! $postType ) {
			return [
				'name'        => '',
				'description' => '',
				'url'         => WordpressHelpers::retrieve_url(),
				'breadcrumb'  => [],
			];
		}

		$title       = WordpressHelpers::retrieve_title();
		$description = WordpressHelpers::retrieve_description();
		$url         = WordpressHelpers::retrieve_url();

		return [
			'name'        => $title,
			'description' => $description,
			'url'         => $url,
			'breadcrumb'  => $this->breadcrumb->setPositions( [
				'name'        => $postType->label,
				'description' => $description,
				'url'         => $url,
				'type'        => 'CollectionPage'
			] )
		];
	}

    /**
     * Returns the context data for the requested data archive.
     *
     * @return array $context The context data.
     * @throws Exception
     */
	public function date(): array {
		$context = [
			'name'        => WordpressHelpers::retrieve_title(),
			'description' => WordpressHelpers::retrieve_description(),
			'url'         => WordpressHelpers::retrieve_url()
		];

		$context['breadcrumb'] = $this->breadcrumb->date();

		return $context;
	}

    /**
     * Returns the context data for the search page.
     *
     * @return array The context data.
     * @throws Exception
     */
	public function search(): array {
		global $s;
		$title       = WordpressHelpers::retrieve_title();
		$description = WordpressHelpers::retrieve_description();
		$url         = WordpressHelpers::retrieve_url();

		return [
			'name'        => $title,
			'description' => $description,
			'url'         => $url,
			'breadcrumb'  => $this->breadcrumb->setPositions( [
				'name'        => $s ?: $title,
				'description' => $description,
				'url'         => $url,
				'type'        => 'SearchResultsPage'
			] )
		];
	}

    /**
     * Returns the context data for the 404 Not Found page.
     *
     * @return array The context data.
     * @throws Exception
     */
	public function notFound(): array {
		$title       = WordpressHelpers::retrieve_title();
		$description = WordpressHelpers::retrieve_description();
		$url         = WordpressHelpers::retrieve_url();

		return [
			'name'        => $title,
			'description' => $description,
			'url'         => $url,
			'breadcrumb'  => $this->breadcrumb->setPositions( [
				'name'        => __( 'Not Found', 'beyondseo'),
				'description' => $description,
				'url'         => $url
			] )
		];
	}
}
