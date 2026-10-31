<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\Traits\RcSchemaAnalysisTrait;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use ReflectionException;
use Throwable;

/**
 * SchemaManager class
 */
class SchemaManager {
	
	use RcSchemaAnalysisTrait;

	/**
     * Schema graph data.
     *
     * @var array
     */
	public array $graphs = [];

	/**
	 * The context data.
	 *
	 * @var array
	 */
	public array $context = [];

	/**
	 * Graphs output on every page;
	 *
	 * @var array
	 */
	protected array $defaultGraphs = [];

	/**
	 * Graph names that were offered/stored before the option list matched the class names.
	 *
	 * @var array<string,string>
	 */
	private const LEGACY_GRAPH_NAMES = [
		'Website' => 'WebSite',
	];

	/**
	 * The subdirectories that contain graph classes.
	 *
	 * @var array
	 */
	protected array $graphSubDirectories = [
		'Article',
		'KnowledgeGraph',
        'Music',
        'Product',
		'WebPage'
	];

	/**
	 * All existing WebPage graphs.
	 *
	 * @var array
	 */
	public array $webPageGraphs = [
		'WebPage',
		'AboutPage',
		'CheckoutPage',
		'CollectionPage',
		'ContactPage',
		'FAQPage',
		'ItemPage',
		'MedicalWebPage',
        'PersonAuthor',
		'ProfilePage',
		'RealEstateListing',
		'SearchResultsPage'
	];

	/**
	 * Fields that can be 0 or null, which shouldn't be stripped when cleaning the data.
	 *
	 * @var array
	 */
	public array $nullableFields = [
		'price',
		'ratingValue',
		'value',
		'minValue',
		'maxValue'
	];

    /**
     * The list of keywords that strongly indicate a site represents an Organization.
     * Can be extended by child classes or modified via filters.
     * @var string[]
     */
    protected array $organizationKeywords = [
        'company',
        'corporation',
        'corp',
        'llc',
        'ltd',
        'inc',
        'group',
        'agency',
        'studio',
        'firm',
        'business',
        'services',
        'solutions',
        'consulting',
        'enterprises',
        'industries',
        'ventures',
        'technologies',
        'tech',
        'software',
        'development',
        'marketing',
        'design',
        'media',
        'digital',
        'creative',
        'professional',
        'law',
        'legal',
        'medical',
        'healthcare',
        'financial',
        'real estate',
        'construction',
        'manufacturing',
        'retail',
        'restaurant',
        'hotel',
        'shop',
        'store',
        'center',
        'centre',
        'institute',
        'foundation',
        'organization',
        'organisation',
        'association',
        'society',
        'union',
        'collective',
        'network',
        'platform',
        'system',
        'labs',
        'laboratory',
        'research',
        'university',
        'college',
        'school',
        'academy',
        'education',
        'learning'
    ];

    /**
     * The list of keywords that strongly indicate a site represents a Person.
     * Can be extended by child classes or modified via filters.
     * @var string[]
     */
    protected array $personKeywords = [
        'blog',
        'portfolio',
        'personal',
        'diary',
        'journal',
        'thoughts',
        'musings',
        'writings',
        'me',
        'myself',
        'freelancer',
        'consultant',
        'coach',
        'trainer',
        'speaker',
        'author',
        'writer',
        'photographer',
        'artist',
        'designer',
        'developer'
    ];

	/**
	 * List of mapped parents with properties that are allowed to contain a restricted set of HTML tags.
	 *
	 * @var array
	 */
	public array $htmlAllowedFields = [
		// FAQPage
		'acceptedAnswer' => [
			'text'
		]
	];

	/**
	 * Whether we are generating the validator output.
	 *
	 * @var bool
	 */
	public bool $generatingValidatorOutput = false;

	/**
	 * Whether to use schema caching.
	 *
	 * @var bool
	 */
	public bool $enableCaching = false;

	/**
	 * Whether to validate generated schemas.
	 *
	 * @var bool
	 */
	public bool $enableValidation = false;

	/**
	 * Last validation results.
	 *
	 * @var array|null
	 */
	public ?array $lastValidationResults = null;

	/**
     * Class constructor.
     */
	public function __construct() {
		$this->defaultGraphs = $this->getDefaultGraphs();
		$this->graphs        = $this->defaultGraphs;
    }

	/**
	 * Maps legacy graph names (e.g. "Website" saved in post meta) to the real class name.
	 *
	 * @param string $graphName The stored graph name.
	 * @return string            The graph name matching an existing graph class.
	 */
	public static function normalizeGraphName( string $graphName ): string {
		return self::LEGACY_GRAPH_NAMES[ $graphName ] ?? $graphName;
	}

    /**
     * Returns the schema graph data.
     *
     * @return mixed The schema graph data.
     * @throws ReflectionException
     */
    public function getSchemaGraphData(): mixed
    {
        $json = $this->get();
        $decoded = json_decode($json);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
        return null;
    }

    /**
     * Validates the generated schema and returns validation results.
     *
     * @return array Validation results
     * @throws ReflectionException
     */
    public function validateGeneratedSchema(): array
    {
        $schemaData = $this->getSchemaGraphData();
        
        if (!$schemaData) {
            return [
                'valid' => false,
                'error' => 'Invalid JSON generated or schema generation failed',
                'issues' => ['JSON parsing failed'],
                'warnings' => [],
                'overall_score' => 0
            ];
        }
        
        if (!isset($schemaData->{'@graph'})) {
            return [
                'valid' => false,
                'error' => 'Missing @graph property in schema output',
                'issues' => ['Schema structure invalid - missing @graph'],
                'warnings' => [],
                'overall_score' => 0
            ];
        }
        
        // Convert to array for validation
        $schemas = json_decode(json_encode($schemaData->{'@graph'}), true);
        
        if (empty($schemas)) {
            return [
                'valid' => false,
                'error' => 'No schemas found in @graph',
                'issues' => ['Empty schema graph'],
                'warnings' => [],
                'overall_score' => 0
            ];
        }
        
        // Use existing validation trait
        $validationResults = $this->validateSchemas($schemas);
        
        // Store validation results
        $this->lastValidationResults = $validationResults;
        
        // Save validation results to post meta if we're on a singular page
        if (is_singular() && get_the_ID()) {
            update_post_meta(
                get_the_ID(), 
                BaseConstants::OPTION_SCHEMA_VALIDATION_RESULTS, 
                $validationResults
            );
        }
        
        return array_merge($validationResults, [
            'valid' => $validationResults['invalid_schemas'] === 0,
            'timestamp' => current_time('timestamp')
        ]);
    }

    /**
     * Generates a cache key based on current context and settings.
     *
     * @return string Cache key
     */
    private function generateCacheKey(): string
    {
        $factors = [
            'post_id' => get_the_ID() ?: 0,
            'is_home' => is_home(),
            'is_front_page' => is_front_page(),
            'is_singular' => is_singular(),
            'is_category' => is_category(),
            'is_tag' => is_tag(),
            'is_tax' => is_tax(),
            'is_author' => is_author(),
            'is_date' => is_date(),
            'is_search' => is_search(),
            'is_404' => is_404(),
            'queried_object_id' => get_queried_object_id(),
            'post_modified' => is_singular() ? get_post_modified_time() : 0,
        ];
        
        // Include settings that affect schema generation
        $options = SettingsManager::instance()->get_options();
        $factors['site_represents'] = $options['site_represents'] ?? '';
        $factors['default_schema_type'] = $options['default_schema_type'] ?? '';
        
        // Include post-specific schema type
        if (is_singular() && get_the_ID()) {
            $factors['post_schema_type'] = get_post_meta(get_the_ID(), BaseConstants::OPTION_SCHEMA_TYPE, true);
        }
        
        return md5(serialize($factors));
    }

    /**
     * Gets cached schema if available and valid.
     *
     * @return array|null Cached schema data or null if not available
     */
    private function getCachedSchema(): ?array
    {
        if (!$this->enableCaching) {
            return null;
        }
        
        $cacheKey = $this->generateCacheKey();
        $postId = get_the_ID();
        
        if ($postId) {
            // Post-level cache
            $cachedHash = get_post_meta($postId, BaseConstants::OPTION_SCHEMA_CACHE_HASH, true);
            $cachedSchema = get_post_meta($postId, BaseConstants::OPTION_SCHEMA_CACHE, true);

        } else {
            // Site-level cache for non-post pages
            $cachedHash = get_option(BaseConstants::OPTION_SCHEMA_CACHE_HASH . '_site');
            $cachedSchema = get_option(BaseConstants::OPTION_SCHEMA_CACHE . '_site');

        }
        if ($cachedHash === $cacheKey && !empty($cachedSchema)) {
            return json_decode($cachedSchema, true);
        }

        return null;
    }

    /**
     * Saves schema to cache.
     *
     * @param array $schemaData Schema data to cache
     */
    private function setCachedSchema(array $schemaData): void
    {
        if (!$this->enableCaching) {
            return;
        }
        
        $cacheKey = $this->generateCacheKey();
        $schemaJson = json_encode($schemaData);
        $postId = get_the_ID();
        
        if ($postId) {
            // Post-level cache
            update_post_meta($postId, BaseConstants::OPTION_SCHEMA_CACHE_HASH, $cacheKey);
            update_post_meta($postId, BaseConstants::OPTION_SCHEMA_CACHE, $schemaJson);
        } else {
            // Site-level cache for non-post pages
            update_option(BaseConstants::OPTION_SCHEMA_CACHE_HASH . '_site', $cacheKey);
            update_option(BaseConstants::OPTION_SCHEMA_CACHE . '_site', $schemaJson);
        }
    }

    /**
     * Clears cached schema for current context.
     */
    public function clearSchemaCache(): void
    {
        $postId = get_the_ID();
        
        if ($postId) {
            delete_post_meta($postId, BaseConstants::OPTION_SCHEMA_CACHE_HASH);
            delete_post_meta($postId, BaseConstants::OPTION_SCHEMA_CACHE);
            delete_post_meta($postId, BaseConstants::OPTION_SCHEMA_VALIDATION_RESULTS);
        } else {
            delete_option(BaseConstants::OPTION_SCHEMA_CACHE_HASH . '_site');
            delete_option(BaseConstants::OPTION_SCHEMA_CACHE . '_site');
        }
    }

    /**
     * Generates meta tags for the current page.
     *
     * @return string The meta tags for the current page.
     * @throws ReflectionException
     */
	public function generateMetaTags(): string {

        // Try to get cached schema first
        $cachedSchema = $this->getCachedSchema();
        
        if ($cachedSchema) {
            $schema = wp_json_encode($cachedSchema);
        } else {
            // Generate fresh schema
            $schemaData = $this->getSchemaGraphData();
            
            if ($schemaData) {
                // Validate if enabled
                if ($this->enableValidation) {
                    $validationResults = $this->validateGeneratedSchema();
                    
                    // Log validation issues if any
                    if (!$validationResults['valid']) {
                        $this->log('RankingCoach Schema Validation Failed: ' . json_encode([
                            'issues' => $validationResults['issues'],
                            'warnings' => $validationResults['warnings'],
                            'score' => $validationResults['overall_score']
                        ]), 'DEBUG');
                    }
                }
                
                // Cache the schema
                $schemaArray = json_decode(json_encode($schemaData), true);
                $this->setCachedSchema($schemaArray);
                
                $schema = wp_json_encode($schemaData);
            } else {
                $schema = '';
            }
        }
        
		return '<script class="dynamically-generated-schema" type="application/ld+json">' . "\n\t\t" . $schema . "\n\t" . '</script>';
	}

	/**
	 * @return string
	 * @throws ReflectionException
     * @throws Exception
	 */
	protected function get(): string {
		$this->graphs  = $this->defaultGraphs;
		$this->context = [];

		$this->determineGraphsAndContext();
		return $this->generateSchema();
	}

    /**
     * Determines the smart graphs that need to be output by default, as well as the current context for the breadcrumbs.
     *
     * @return void
     * @throws Exception
     */
	protected function determineGraphsAndContext(): void {

		$contextInstance = new SchemaGraphsContext();

		if ( WordpressHelpers::is_dynamic_home_page() ) {
			$this->graphs[] = 'CollectionPage';
			$this->context  = $contextInstance->home();

			return;
		}

		if ( is_home() ) {
			$this->graphs[] = 'CollectionPage';
			$this->context  = $contextInstance->post();

			return;
		}

		if ( is_singular() ) {
			$this->determineContextSingular( $contextInstance );

			return;
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$this->graphs[] = 'CollectionPage';
			$this->context  = $contextInstance->term();

			return;
		}

		if ( is_author() ) {
			$this->graphs[] = 'ProfilePage';
			$this->graphs[] = 'PersonAuthor';
			$this->context  = $contextInstance->author();

			return;
		}

		if ( is_post_type_archive() ) {
			$this->graphs[] = 'CollectionPage';
			$this->context  = $contextInstance->postArchive();

			return;
		}

		if ( is_date() ) {
			$this->graphs[] = 'CollectionPage';
			$this->context  = $contextInstance->date();

			return;
		}

		if ( is_search() ) {
			$this->graphs[] = 'SearchResultsPage';
			$this->context  = $contextInstance->search();

			return;
		}

		if ( is_404() ) {
			$this->context = $contextInstance->notFound();

            return;
		}

		$this->context   = $contextInstance->defaults();
	}

    /**
     * Determines the smart graphs and context for singular pages.
     *
     * @param SchemaGraphsContext $contextInstance The Context class instance.
     * @return void
     * @throws Exception
     */
	protected function determineContextSingular( SchemaGraphsContext $contextInstance ): void {
		// If the current request is for the validator, we can't include the default graph here.
		// We need to include the default graph that the validator sent.
		// Don't do this if we're in Pro since we then need to get it from the post meta.
		if ( ! $this->generatingValidatorOutput ) {
			$this->graphs[] = $this->getDefaultPostGraph();
		}

		$this->context = $contextInstance->post();
	}

	/**
	 * Returns the default graph for the post type.
	 *
	 * @return string The default graph.
	 */
	public function getDefaultPostGraph(): string {
		return 'WebPage';
	}

    /**
     * Returns the default graphs that should be output on every page, regardless of its type.
     *
     * @return array The default graphs.
     */
	protected function getDefaultGraphs(): array {
        // Determine what the site represents based on intelligent analysis
        $siteRepresents = $this->determineWhatSiteRepresents(SettingsManager::instance()->site_represents);
		return [
			'BreadcrumbList',
			'Kg' . ucfirst($siteRepresents),
			'WebSite'
		];
	}

    /**
     * Determines whether the website represents an Organization or Person.
     * This is an orchestrator method that delegates checks to smaller, focused helpers.
     *
     * @param string $configuredValue The user-configured value from settings.
     * @return string 'organization' or 'person'.
     */
    private function determineWhatSiteRepresents(string $configuredValue): string {
        // If user has explicitly configured a value, respect it.
        if (!empty($configuredValue) && in_array($configuredValue, ['organization', 'person'], true)) {
            return $configuredValue;
        }

        $scores = [
            'organization' => 0,
            'person'       => 0,
        ];

        // A declarative list of all checks to perform.
        // This makes it easy to add, remove, or change the weight of any check.
        $checks = [
            // method to call                => [ 'type' => 'person|organization', 'weight' => int ]
            ['callback' => [$this, '_checkTitleForOrgKeywords'], 'type' => 'organization', 'weight' => 2],
            ['callback' => [$this, '_checkTitleForPersonKeywords'], 'type' => 'person', 'weight' => 2],
            ['callback' => [$this, '_checkTitleForNamePattern'], 'type' => 'person', 'weight' => 3],
            ['callback' => [$this, '_checkDescriptionForOrgTerms'], 'type' => 'organization', 'weight' => 2],
            ['callback' => [$this, '_checkDescriptionForPersonTerms'], 'type' => 'person', 'weight' => 2],
            ['callback' => [$this, '_checkAdminNameMatchesSiteTitle'], 'type' => 'person', 'weight' => 3],
            ['callback' => [$this, '_checkHasMultipleAuthors'], 'type' => 'organization', 'weight' => 2],
            ['callback' => [$this, '_checkHasBusinessPostTypes'], 'type' => 'organization', 'weight' => 1], // Lower weight per type
            ['callback' => [$this, '_checkIsMultisiteOrMultiAdmin'], 'type' => 'organization', 'weight' => 1],
        ];

        foreach ($checks as $check) {
            if (call_user_func($check['callback'])) {
                $scores[$check['type']] += $check['weight'];
            }
        }


        // Make final determination
        if ($scores['person'] > $scores['organization']) {
            return 'person';
        }

        // Default to organization if indicators are equal, organization is higher, or both are zero.
        return 'organization';
    }

    /*
    |--------------------------------------------------------------------------
    | Private Helper Methods for Analysis
    |--------------------------------------------------------------------------
    | Each method has a single responsibility and returns a boolean.
    | This isolates WordPress calls and analysis logic, improving testability.
    */
    /**
     * @return bool
     */
    private function _checkTitleForOrgKeywords(): bool {
        $siteTitle = get_bloginfo('name');
        foreach ($this->organizationKeywords as $keyword) {
            if (stripos($siteTitle, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    private function _checkTitleForPersonKeywords(): bool {
        $siteTitle = get_bloginfo('name');
        foreach ($this->personKeywords as $keyword) {
            if (stripos($siteTitle, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    private function _checkTitleForNamePattern(): bool {
        $siteTitle = trim(get_bloginfo('name'));
        $titleWords = explode(' ', $siteTitle);
        if (count($titleWords) < 2 || count($titleWords) > 4) {
            return false;
        }

        $firstWord = $titleWords[0];
        $lastWord = end($titleWords);

        // Simple heuristic: if first and last words are capitalized and contain only letters.
        return ucfirst($firstWord) === $firstWord &&
            ucfirst($lastWord) === $lastWord &&
            strlen($firstWord) >= 2 && strlen($firstWord) <= 15 &&
            strlen($lastWord) >= 2 && strlen($lastWord) <= 15 &&
            ctype_alpha($firstWord) && ctype_alpha($lastWord);
    }

    /**
     * @return bool
     */
    private function _checkDescriptionForOrgTerms(): bool {
        $siteDescription = get_bloginfo('description');
        return !empty($siteDescription) && preg_match('/\b(we are|our company|business|service|organization|team|staff|employees|clients|customers)\b/i', $siteDescription);
    }

    /**
     * @return bool
     */
    private function _checkDescriptionForPersonTerms(): bool {
        $siteDescription = get_bloginfo('description');
        return !empty($siteDescription) && preg_match('/\b(i am|my name is|personal|blog|portfolio|freelance|individual)\b/i', $siteDescription);
    }

    /**
     * @return bool
     */
    private function _checkAdminNameMatchesSiteTitle(): bool {
        $admins = get_users(['role' => 'administrator', 'number' => 2]);
        if (count($admins) !== 1) {
            return false; // This check only applies to single-admin sites.
        }

        $admin = $admins[0];
        $adminName = trim($admin->display_name);
        $siteTitle = get_bloginfo('name');

        return !empty($adminName) && stripos($siteTitle, $adminName) !== false;
    }

    /**
     * @return bool
     */
    private function _checkHasMultipleAuthors(): bool {
        $authors = get_users(['who' => 'authors', 'has_published_posts' => true, 'number' => 4]);
        return count($authors) > 3;
    }

    /**
     * @return bool
     */
    private function _checkHasBusinessPostTypes(): bool {
        $postTypes = get_post_types(['public' => true]);
        $businessPostTypes = ['product', 'service', 'team', 'staff', 'employee', 'testimonial', 'client', 'case_study', 'portfolio_item', 'project'];

        return count(array_intersect($businessPostTypes, $postTypes)) > 0;
    }

    /**
     * @return bool
     */
    private function _checkIsMultisiteOrMultiAdmin(): bool {
        if (is_multisite()) {
            return true;
        }
        $admins = get_users(['role' => 'administrator', 'number' => 2]);
        return count($admins) > 1;
    }


    /**
     * @return string The JSON schema output.
     */
	public function generateSchema(): string
    {
        $options = SettingsManager::instance()->get_options();
        
        // Consolidate schema types logic to eliminate duplication
        $this->consolidateSchemaTypes($options);
        
        $this->graphs = array_unique(array_filter(array_values($this->graphs)));
        if (!$this->graphs) {
            return '';
        }

        // Check if a WebPage graph is included. Otherwise, add the default one.
        $this->ensureWebPageGraphExists();

        // Now that we've determined the graphs, start generating their data.
        $schema = [
            '@context' => 'https://schema.org',
            '@graph' => []
        ];

        // Process graphs with robust error handling
        $this->processGraphsWithErrorHandling($schema);

        return $this->getOutput( $schema );
	}

    /**
     * Consolidates schema types logic to eliminate duplication and ensure proper priority.
     * Post-specific schema types have highest priority, followed by default schema types.
     *
     * @param array $options Settings options array
     * @return void
     */
    private function consolidateSchemaTypes(array $options): void
    {
        $schemaTypes = [];
        
        if (is_singular()) {
            $postId = get_the_ID() ?: null;
            $post = $postId ? get_post($postId) : WordpressHelpers::retrieve_post();
            if ($post instanceof \WP_Post) {
                $postId = $post->ID;
                $postType = $post->post_type;
            } else {
                $postType = $postId ? get_post_type($postId) : get_post_type();
            }

            if ($postId) {
                $postSchemaType = self::normalizeGraphName((string) get_post_meta($postId, BaseConstants::OPTION_SCHEMA_TYPE, true));
                if (!empty($postSchemaType) && $this->validateGraphName($postSchemaType)) {
                    $schemaTypes[] = $postSchemaType;
                }
            }
            
            if (empty($schemaTypes)) {
                if ($postType === 'post') {
                    if (!empty($options['default_schema_type']) && $this->validateGraphName($options['default_schema_type'])) {
                        $schemaTypes[] = $options['default_schema_type'];
                    }
                } elseif (!empty($postType) && !in_array($postType, ['page', 'product', 'attachment'], true)) {
                    if (post_type_supports($postType, 'author') && !empty($options['default_schema_type']) && $this->validateGraphName($options['default_schema_type'])) {
                        if (in_array($options['default_schema_type'], ['Article', 'BlogPosting', 'NewsArticle'], true)) {
                            $schemaTypes[] = $options['default_schema_type'];
                        }
                    }
                }
            }
        }
        
        $this->graphs = array_merge($schemaTypes, $this->graphs);
    }

    /**
     * Ensures a WebPage graph exists in the graphs array.
     *
     * @return void
     */
    private function ensureWebPageGraphExists(): void
    {
        $webPageGraphFound = false;
        foreach ($this->graphs as $graphName) {
            if (in_array($graphName, $this->webPageGraphs, true)) {
                $webPageGraphFound = true;
                break;
            }
        }

        if (!$webPageGraphFound) {
            $this->graphs[] = 'WebPage';
        }
    }

    /**
     * Processes graphs with robust error handling to prevent failures from corrupting the entire schema.
     *
     * @param array $schema Schema array to populate
     * @return void
     */
    private function processGraphsWithErrorHandling(array &$schema): void
    {
        $processedGraphs = [];

        // Graphs can append further graphs to $this->graphs while being generated
        // (e.g. Article adds PersonAuthor to resolve the author @id reference),
        // so keep draining the list until no unprocessed graph names remain.
        while ($pendingGraphs = array_diff(array_values($this->graphs), $processedGraphs)) {
            foreach ($pendingGraphs as $graphName) {
                $processedGraphs[] = $graphName;

                if (!$this->validateGraphName($graphName)) {
                    $this->log("RankingCoach Schema: Invalid graph name: $graphName", 'DEBUG');
                    continue;
                }

                try {
                    $namespace = $this->getGraphNamespace($graphName);
                    if (!$namespace) {
                        $this->log("RankingCoach Schema: Namespace not found for graph: $graphName", 'DEBUG');
                        continue;
                    }

                    if (!class_exists($namespace)) {
                        $this->log("RankingCoach Schema: Class does not exist: $namespace", 'DEBUG');
                        continue;
                    }

                    $graphInstance = new $namespace();
                    if (!method_exists($graphInstance, 'get')) {
                        $this->log("RankingCoach Schema: Class missing get() method: $namespace", 'DEBUG');
                        continue;
                    }

                    $schemaElement = $graphInstance->get();
                    if (!empty($schemaElement)) {
                        $schema['@graph'][] = $schemaElement;
                    }

                } catch (Throwable $e) {
                    $this->log("RankingCoach Schema: Error generating schema for graph $graphName: " . $e->getMessage(), 'ERROR');
                    continue;
                }
            }
        }
    }

    /**
     * Validates a graph name to ensure it meets security and format requirements.
     *
     * @param string $graphName The graph name to validate
     * @return bool True if valid, false otherwise
     */
    private function validateGraphName(string $graphName): bool
    {
        return !empty($graphName) && 
               preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $graphName) &&
               strlen($graphName) <= 50;
    }

	/**
	 * Gets the relevant namespace for the given graph.
	 *
	 * @param string $graphName The graph name.
	 * @return string            The namespace.
	 */
	protected function getGraphNamespace(string $graphName ): string
    {
		$namespace = "\RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs\\$graphName";
		if ( class_exists( $namespace ) ) {
			return $namespace;
		}

		// If we can't find it in the root dir, check if we can find it in a sub dir.
		foreach ( $this->graphSubDirectories as $dirName ) {
			$namespace = "RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs\\$dirName\\$graphName";
			if ( class_exists( $namespace ) ) {
				return $namespace;
			}
		}
		return '';
	}

	/**
	 * Sorts the schema data and then returns it as JSON.
	 * We temporarily change the floating point precision in order to prevent rounding errors.
	 * Otherwise, e.g. 4.9 could be output as 4.90000004.
	 *
	 * @param array $schema      The schema data.
	 * @param bool $replaceTags Whether the smart tags should be replaced.
	 *
	 * @return string              The schema as JSON.
	 */
	public function getOutput( array $schema, bool $replaceTags = false ): string {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
		$schema['@graph'] = apply_filters( BaseConstants::OPTION_SCHEMA_OUTPUT, $schema['@graph'] );
        $schema['@graph'] = $this->cleanAndParseData( $schema['@graph'], '', $replaceTags );

        return CoreHelper::convert_to_json_string( $schema );
	}

	/**
	 * Strips HTML and removes all blank properties in each of our graphs.
	 * Also parses properties that might contain smart tags.
	 *
	 * @param array $data        The graph data.
	 * @param string $parentKey   The key of the group parent (optional).
	 * @param bool $replaceTags Whether the smart tags should be replaced.
	 *
	 * @return array               The cleaned graph data.
	 */
	public function cleanAndParseData( array $data, string $parentKey = '', bool $replaceTags = true ): array {
		foreach ( $data as $k => &$v ) {
            if (is_array($v)) {
                $v = $this->cleanAndParseData($v, (string)$k, $replaceTags);
            } elseif (!is_numeric($v) && !is_bool($v) && !is_null($v)) {
                $v = isset($this->htmlAllowedFields[$parentKey]) && in_array($k, $this->htmlAllowedFields[$parentKey], true)
                    ? trim(wp_kses_post($v))
                    : trim(wp_strip_all_tags($v));
            }

            if (empty($v) && !in_array($k, $this->nullableFields, true)) {
                unset($data[$k]);
            } else {
                $data[$k] = $v;
            }
		}

		return $data;
	}

	/**
	 * Gets the last validation results.
	 *
	 * @return array|null Last validation results or null if not available
	 */
	public function getLastValidationResults(): ?array
	{
		if ($this->lastValidationResults) {
			return $this->lastValidationResults;
		}
		
		// Try to get from post meta
		if (is_singular() && get_the_ID()) {
			$results = get_post_meta(get_the_ID(), BaseConstants::OPTION_SCHEMA_VALIDATION_RESULTS, true);
			return !empty($results) ? $results : null;
		}
		
		return null;
	}

	/**
	 * Enables or disables schema caching.
	 *
	 * @param bool $enable Whether to enable caching
	 */
	public function setCachingEnabled(bool $enable): void
	{
		$this->enableCaching = $enable;
	}

	/**
	 * Enables or disables schema validation.
	 *
	 * @param bool $enable Whether to enable validation
	 */
	public function setValidationEnabled(bool $enable): void
	{
		$this->enableValidation = $enable;
	}

	/**
	 * Forces regeneration of schema by clearing cache and generating fresh.
	 *
	 * @return string The meta tags for the current page
	 * @throws ReflectionException
	 */
	public function forceRegenerateSchema(): string
	{
		$this->clearSchemaCache();
		return $this->generateMetaTags();
	}

	/**
	 * Gets schema cache statistics.
	 *
	 * @return array Cache statistics
	 */
	public function getCacheStats(): array
	{
		$postId = get_the_ID();
		$stats = [
			'cache_enabled' => $this->enableCaching,
			'validation_enabled' => $this->enableValidation,
			'has_cached_schema' => false,
			'cache_key' => $this->generateCacheKey(),
			'last_validation' => null
		];
		
		if ($postId) {
			$cachedSchema = get_post_meta($postId, BaseConstants::OPTION_SCHEMA_CACHE, true);
			$stats['has_cached_schema'] = !empty($cachedSchema);
			$stats['last_validation'] = get_post_meta($postId, BaseConstants::OPTION_SCHEMA_VALIDATION_RESULTS, true);
		} else {
			$cachedSchema = get_option(BaseConstants::OPTION_SCHEMA_CACHE . '_site');
			$stats['has_cached_schema'] = !empty($cachedSchema);
		}
		
		return $stats;
	}
}