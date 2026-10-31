<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Interfaces\MetaHeadBuilderInterface;
use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Dtos\SchemaMarkupGetDataResponseDto;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class SchemaMarkup
 */
class SchemaMarkup extends BaseModule implements MetaHeadBuilderInterface {

	public ?SchemaManager $schema = null;

    public array|null $settings = null;

    /**
     * SchemaMarkup constructor.
     *
     * @param ModuleManager $moduleManager
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public function __construct(ModuleManager $moduleManager) {
		$this->schema   = new SchemaManager();
        $this->settings = get_option(BaseConstants::OPTION_PLUGIN_SETTINGS, null);
        
        // Initialize schema cache management
        // SchemaCacheManager::init();

        $initialization = [
			'active' => true,
            'title' => 'Structured Data Markup',
            'description' => 'Adds structured data markup (using schema.org vocabulary) to pages and posts, enhancing SEO visibility by providing search engines with more context about the content. Supports various schema types, configurable through settings.',
            'version' => '1.2.0',
            'name' => 'schemaMarkup',
            'priority' => 12,
            'dependencies' => [],
            'settings' => [['key' => 'enable_article_schema', 'type' => 'boolean', 'default' => True, 'description' => 'Enable schema markup for articles and blog posts, providing information about author, publishing date, and other relevant details.'], ['key' => 'enable_product_schema', 'type' => 'boolean', 'default' => False, 'description' => 'Enable schema markup for product pages, including details like price, availability, and reviews.'], ['key' => 'default_schema_type', 'type' => 'string', 'default' => 'Article', 'description' => 'The default schema type to use for pages that don\'t have a specific schema type assigned. Options: \'Article\', \'WebPage\', etc. (See documentation for a full list).']],
            'explain' => 'For a blog post, this module adds JSON-LD structured data markup based on the Article schema. This markup includes the post title, author, publication date, and other relevant information, helping search engines understand the context of the post.  If the \'enable_product_schema\' setting is enabled, and it detects WooCommerce product data, it adds Product schema markup to product pages, including price and availability. For pages that don\'t have a specific schema assigned, it uses the default schema type (e.g., \'WebPage\') specified in the settings.',
        ];
        parent::__construct($moduleManager, $initialization);
    }

    /**
     * Builds and returns the meta tags for the current page.
     *
     * @inheritDoc MetaHeadBuilderInterface
     * @return string The meta tags for the current page.
     * @throws ReflectionException
     */
	public function generateMetaTags(): string {
		return $this->schema->generateMetaTags();
	}

	/**
     * Registers the hooks for the module.
     * @return void
     */
	public function initializeModule(): void {
		if(!$this->isActive()) {
			return;
		}

		parent::initializeModule();
    }

	/**
	 * Create necessary SQL tables if they don't already exist.
	 * @param string $table_name
	 * @param string $charset_collate
	 * @return string
	 * @noinspection SqlNoDataSourceInspection
	 */
	protected function getTableSchema(string $table_name, string $charset_collate): string {
		return '';
	}

    /**
     * Save the schema settings data for the module.
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function saveSchemaDataForPost(WP_REST_Request $request): WP_REST_Response {

        // Verify nonce from REST request header
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return $this->generateErrorResponse(null, 'Invalid or missing nonce.', 403);
        }

        $postId = (int) $request->get_param('id');

        // Verify the post exists
        if (!get_post($postId)) {
            return $this->generateErrorResponse(null, 'Post not found.', 404);
        }

        // Verify the current user can edit this specific post
        if (!current_user_can('edit_post', $postId)) {
            return $this->generateErrorResponse(null, 'You do not have permission to edit this post.', 403);
        }

		$payload = $request->get_body();

		$payload = json_decode($payload, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			return $this->generateErrorResponse(null, 'Invalid JSON payload.');
		}

        $payload = WordpressHelpers::sanitize_recursive(
            $payload,
            [FILTER_SANITIZE_FULL_SPECIAL_CHARS]
        );

        if (empty($payload['selectedSchema']) || !is_string($payload['selectedSchema'])) {
            return $this->generateErrorResponse(null, 'Missing or invalid selectedSchema.');
        }

        $selectedSchemaType = SchemaManager::normalizeGraphName(sanitize_text_field($payload['selectedSchema']));
        update_post_meta($postId, BaseConstants::OPTION_SCHEMA_TYPE, $selectedSchemaType);

        $settings = $this->retrieveSchemaMarkupSettings($postId);

        return $this->generateSuccessResponse($settings);
    }

    /**
     * Return the schema settings data for the module.
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function getSchemaData(WP_REST_Request $request): WP_REST_Response {
        $postId = $request->get_param('id');
        $settings = $this->retrieveSchemaMarkupSettings($postId);

        return $this->generateSuccessResponse($settings);
    }

    /**
     * Retrieve the schema settings data for the module.
     * @param int|null $postId
     * @return array
     */
    public function retrieveSchemaMarkupSettings(?int $postId = null): array {
        $this->settings = get_option(BaseConstants::OPTION_PLUGIN_SETTINGS, null);
        
        $post = $postId ? get_post($postId) : null;
        $isPage = $post && $post->post_type === 'page';
        $defaultSchemaType = $isPage ? 'WebPage' : ($this->settings['default_schema_type'] ?? 'BlogPosting');
        
        $data = [
            'enableLocalSeo' => $this->settings['enable_local_seo'] ?? false,
            'defaultBusinessType' => $this->settings['default_business_type'] ?? null,
            'enableSchemaMarkup' => $this->settings['enable_schema_markup'] ?? true,
            'defaultSchemaType' => $defaultSchemaType,
            'currentPostSchemaType' => $postId ? SchemaManager::normalizeGraphName((string) get_post_meta($postId, BaseConstants::OPTION_SCHEMA_TYPE, true)) : null,
            'currentPostSchemaData' => $postId ? get_post_meta($postId, BaseConstants::OPTION_SCHEMA_CACHE, true) : null,
            'schemaTypes' => [
                'WebPage',
                'BlogPosting',
                'NewsArticle',
                'SocialMediaPosting',
                'Person',
                'PersonAuthor',
                'KgOrganization',
                'KgPerson',
                'MusicAlbum',
                'MusicGroup',
                'Product',
                'ProductReview',
                'WebSite',
                'AboutPage',
                'ContactPage',
                'FAQPage',
                'CheckoutPage',
                'CollectionPage',
                'MedicalWebPage',
                'ProfilePage',
                'RealEstateListing',
                'SearchResultsPage',
                'Book',
                'JobPosting',
                'Movie',
                'Recipe',
                'Video'
            ]
        ];
        
        // Add current post schema data if post ID is provided
        if ($postId) {
            try {
                // Check if schema cache exists for this post
                $cachedSchema = $this->getCachedSchemaForPost($postId);
                
                if (!$cachedSchema) {
                    // No cache exists, generate schema for the first time
                    $schemaData = $this->generateSchemaForPost($postId);
                    $data['currentPostSchemaData'] = $schemaData;
                    $data['schemaGenerated'] = true;
                    $data['fromCache'] = false;
                } else {
                    $data['currentPostSchemaData'] = $cachedSchema;
                    $data['schemaGenerated'] = true;
                    $data['fromCache'] = true;
                }
            } catch (Exception $e) {
                $this->log('Failed to generate schema for post ' . $postId . ': ' . $e->getMessage(), 'ERROR');
                $data['currentPostSchemaData'] = null;
                $data['schemaGenerated'] = false;
                $data['error'] = $e->getMessage();
            }
        }
        
        return $data;
    }

    /**
     * Get cached schema for a specific post.
     * 
     * @param int $postId
     * @return array|null
     */
    private function getCachedSchemaForPost(int $postId): ?array {
        $cachedSchema = get_post_meta($postId, BaseConstants::OPTION_SCHEMA_CACHE, true);
        
        if (!empty($cachedSchema)) {
            $decoded = json_decode($cachedSchema, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        
        return null;
    }

    /**
     * Generate schema for a specific post by setting up proper WordPress context.
     *
     * @param int $postId
     * @return array|null
     */
    private function generateSchemaForPost(int $postId): ?array {
        
        global $post, $wp_query;
        $originalPostId = $post ? $post->ID : null;
        $originalQuery = $wp_query;
        
        // Set up post context
        $post = get_post($postId);
        if (!$post) {
            return null;
        }
        
        $isPage = $post->post_type === 'page';
        $wp_query = new WP_Query([
            'p' => $postId,
            'post_type' => $post->post_type
        ]);
        $wp_query->queried_object = $post;
        $wp_query->queried_object_id = $postId;
        $wp_query->is_single = !$isPage;
        $wp_query->is_page = $isPage;
        $wp_query->is_singular = true;
        
        try {
            // Generate fresh schema
            $schemaData = $this->schema->getSchemaGraphData();
            
            if ($schemaData) {
                // Convert to array and cache
                $schemaArray = json_decode(json_encode($schemaData), true);
                
                // Cache the schema for this post
                update_post_meta($postId, BaseConstants::OPTION_SCHEMA_CACHE, json_encode($schemaArray));
                
                return $schemaArray;
            }
            
            return null;
            
        } finally {
            // Restore original context
            if ($originalPostId !== null) {
                $post = get_post($originalPostId);
                $wp_query = $originalQuery;
            }
        }
    }

    /**
     * Retrieves the priority of the meta tags.
     * @return int
     */
    public function getMetaTagsPriority(): int {
        return 9;
    }
}
