<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Services;

if (!defined('ABSPATH')) { exit; }

use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\PostEventsManager;
use RankingCoach\Inc\Traits\SingletonTrait;
use RankingCoach\Inc\Core\Seo\Adapters\WordPressProvider;
use RankingCoach\Inc\Core\Seo\Libs\ContentFetcher;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Factors;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operations;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\OptimiserContexts;
use RankingCoach\Inc\Core\Seo\Optimiser\SeoOptimiser;
use RankingCoach\Inc\Core\Seo\Repositories\SeoOptimiserRepository;
use RankingCoach\Inc\Core\Seo\Repositories\SeoContextRepository;
use RankingCoach\Inc\Core\Seo\Repositories\SeoFactorRepository;
use RankingCoach\Inc\Core\Seo\Repositories\SeoOperationRepository;
use DateTime;
use Throwable;
use Exception;
use WP_Query;

/**
 * Class SeoOptimiserService
 *
 * This class is responsible for managing the SEO optimizer service natively in WordPress.
 */
class SeoOptimiserService
{
    use RcLoggerTrait;
    use SingletonTrait;

    private SeoOptimiserRepository $optimiserRepo;
    private SeoContextRepository $contextRepo;
    private SeoFactorRepository $factorRepo;
    private SeoOperationRepository $operationRepo;

    /**
     * Protected constructor to prevent direct instantiation (Singleton pattern).
     */
    protected function __construct()
    {
        $this->optimiserRepo = new SeoOptimiserRepository();
        $this->contextRepo = new SeoContextRepository();
        $this->factorRepo = new SeoFactorRepository();
        $this->operationRepo = new SeoOperationRepository();
    }

    /**
     * Get the content provider for fetching content
     * @return WordPressProvider The content provider instance
     */
    public function getContentProvider(): WordPressProvider
    {
        return WordPressProvider::getInstance();
    }

    /**
     * Analyze all SEO contexts and return the overall score.
     *
     * @param int $postId The ID of the WordPress post
     * @param array $params Additional parameters for analysis
     * @param bool $skipFullAnalysis
     * @return SeoOptimiser
     * @throws Throwable
     */
    public function analyzeFullOptimiser(int $postId, array $params = [], bool $skipFullAnalysis = false, bool $interactive = false, ?bool &$analysisCompleted = null): SeoOptimiser
    {
        $analysisCompleted = false;
        $startTime = microtime(true);
        // Initialize the optimizer
        $seoOptimiser = new SeoOptimiser($postId);

        try {
            if ($skipFullAnalysis) {
                try {
                    return $seoOptimiser->loadSeoOptimiserData($this->optimiserRepo);
                } catch (Throwable $e) {
                    $this->logAnalyserError('load_optimiser_data_failed', $postId, $params, $e, $startTime);
                    throw $e;
                }
            }

            try {
                $previous = $this->getOptimiserByPostId($postId);
                do_action('rankingcoach_seo_analysis_started', [
                    'scope' => 'post',
                    'postId' => $postId,
                    'previousScore' => $previous?->score,
                    'interactive' => $interactive,
                ]);
            } catch (Throwable $e) {
            }

            // ========== reset stuff ========== //
            try {
                // Set the score to 0
                $seoOptimiser->score = 0;
                // Reset the contexts
                $seoOptimiser->contexts = new OptimiserContexts();
                // Set the analysis date
                $seoOptimiser->analysisDate = new DateTime();
            } catch (Throwable $e) {
                $this->logAnalyserError('optimiser_initialization_failed', $postId, $params, $e, $startTime);
                throw $e;
            }

            // Add all contexts
            try {
                $seoOptimiser->initContexts($params);
            } catch (Throwable $e) {
                $this->logAnalyserError('context_initialization_failed', $postId, $params, $e, $startTime);
                throw $e;
            }

            $isPartialAnalysis = $this->isPartialAnalysis($params);

            if (!$isPartialAnalysis) {
                // Delete old postId analyze data before starting a fresh, fully-persisted run
                try {
                    $this->deleteOptimiser($postId);
                } catch (Throwable $e) {
                    $this->logAnalyserError('old_data_deletion_failed', $postId, $params, $e, $startTime);
                    throw $e;
                }

                // Persist the initial optimiser/context/factor/operation skeleton so every
                // entity has a real DB id before analysis starts. This allows each factor to
                // be saved incrementally as soon as it finishes executing (see
                // SeoOptimiser::analyze()), instead of only at the very end of the whole run,
                // so already-computed factor data isn't lost if a later factor/context fails.
                try {
                    $this->optimiserRepo->save($seoOptimiser);
                } catch (Throwable $e) {
                    $this->logAnalyserError('database_skeleton_save_failed', $postId, $params, $e, $startTime);
                    throw $e;
                }
            }

            // Run full analysis. When not a partial run, each factor is persisted immediately
            // after it finishes executing.
            try {
                $seoOptimiser->analyze($isPartialAnalysis ? null : $this->factorRepo);
            } catch (Throwable $e) {
                $this->logAnalyserError('analysis_execution_failed', $postId, $params, $e, $startTime);
                throw $e;
            }

            if ($isPartialAnalysis) {
                return $seoOptimiser;
            }

            // Save the final aggregate optimiser/context scores. Factors were already
            // persisted incrementally during analyze() above.
            try {
                $this->optimiserRepo->save($seoOptimiser);
            } catch (Throwable $e) {
                $this->logAnalyserError('database_save_failed', $postId, $params, $e, $startTime);
                throw $e;
            }

            $rawScore = $seoOptimiser->score;
            $metaSaved = false;
            // Save the score on the post meta (with intelligent caching)
            try {
                $seoOptimiser->saveToPostMeta($seoOptimiser);
                $breakdown = json_decode((string) get_post_meta($postId, BaseConstants::OPTION_ANALYSIS_SCORE_BREAKDOWN, true), true);
                $metaSaved = is_array($breakdown)
                    && isset($breakdown['score']) && is_numeric($breakdown['score'])
                    && (float) $breakdown['score'] === $rawScore
                    && (float) get_post_meta($postId, BaseConstants::OPTION_ANALYSIS_SEO_SCORE, true) === round($rawScore * 100)
                    && (int) get_post_meta($postId, BaseConstants::OPTION_ANALYSIS_DATE_TIMESTAMP, true) >= (int) $startTime;
            } catch (Throwable $e) {
                $this->logAnalyserError('post_meta_save_failed', $postId, $params, $e, $startTime);
            }
            $analysisCompleted = $metaSaved && ($seoOptimiser->id ?? 0) > 0
                && $seoOptimiser->contexts->count() > 0
                && $postId > 0 && get_post_status($postId) && get_post_status($postId) !== 'auto-draft'
                && is_finite($rawScore) && $rawScore >= 0 && $rawScore <= 1;
            if ($analysisCompleted) {
                try {
                    do_action('rankingcoach_seo_analysis_completed', [
                        'scope' => 'post',
                        'postId' => $postId,
                        'score' => $rawScore,
                        'interactive' => $interactive,
                    ]);
                } catch (Throwable $e) {
                }
            }
        } catch (Throwable $e) {
            // Final catch-all for any unhandled exceptions
            $this->logAnalyserError('unexpected_error', $postId, $params, $e, $startTime);
        }

        return $seoOptimiser;
    }

    /**
     * Log analyser errors with comprehensive context information
     *
     * @param string $errorType The type of error that occurred
     * @param int $postId The post ID being analyzed
     * @param array $params Analysis parameters
     * @param Throwable $exception The caught exception
     * @param float $startTime The analysis start time
     */
    private function logAnalyserError(string $errorType, int $postId, array $params, Throwable $exception, float $startTime): void
    {
        $executionTime = microtime(true) - $startTime;
        
        $this->log_json([
            'operation_type' => 'seo_analysis',
            'operation_status' => 'error',
            'error_type' => $errorType,
            'post_id' => $postId,
            'post_type' => get_post_type($postId) ?: 'unknown',
            'post_status' => get_post_status($postId) ?: 'unknown',
            'analysis_params' => $params,
            'execution_time' => round($executionTime, 4),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'error_details' => [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace_summary' => array_slice($exception->getTrace(), 0, 3)
            ],
            'context' => [
                'is_partial_analysis' => $this->isPartialAnalysis($params),
                'wordpress_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'timestamp' => gmdate('Y-m-d H:i:s')
            ]
        ], 'analyser_errors');
    }

    /**
     * Get SeoOptimiser by post ID
     *
     * @param int $postId
     * @return SeoOptimiser|null
     */
    public function getOptimiserByPostId(int $postId): ?SeoOptimiser
    {
        return $this->optimiserRepo->getByPostId($postId);
    }

    /**
     * Generate a CSV line from an array of fields.
     *
     * @param array $fields The fields to include in the CSV line
     * @param string $separator The separator to use between fields
     * @return string The generated CSV line
     */
    private function csvLine(array $fields, string $separator): string
    {
        return implode(
            $separator,
            array_map(
                static function ($value): string {
                    $value = (string) $value;
                    return '"' . str_replace('"', '""', $value) . '"';
                },
                $fields
            )
        );
    }

    /**
     * Delete all SEO optimizer data for a given post ID cascadingly.
     *
     * @param int $postId The ID of the post whose SEO data should be deleted
     * @return void
     */
    public function deleteOptimiser(int $postId): void
    {
        $this->optimiserRepo->deleteByPostId($postId);
    }

    /**
     * Fetch the domain content for analysis
     *
     * @param string $url
     * @param bool $useCache
     * @return array
     */
    public static function fetchContent(string $url, bool $useCache = true): array
    {
        static $fetcher = null;

        if ($fetcher === null) {
            $fetcher = new ContentFetcher();
        }

        return $fetcher->fetchContent($url, useCache: $useCache);
    }

    /**
     * Get contexts by analysis ID
     * @param int|string|null $analysisId
     * @return OptimiserContexts|null
     */
    public function getContextsByAnalysisId(int|string|null $analysisId): ?OptimiserContexts
    {
        if ($analysisId === null) {
            return null;
        }
        return $this->contextRepo->getByAnalysisId((int)$analysisId);
    }

    /**
     * Get factors by context ID
     * @param int|string|null $contextId
     * @return Factors|null
     */
    public function getFactorsByContextId(int|string|null $contextId): ?Factors
    {
        if ($contextId === null) {
            return null;
        }
        return $this->factorRepo->getByContextId((int)$contextId);
    }

    /**
     * Get operations by factor ID
     * @param int|string|null $factorId
     * @return Operations|null
     */
    public function getOperationsByFactorId(int|string|null $factorId): ?Operations
    {
        if ($factorId === null) {
            return null;
        }
        return $this->operationRepo->getByFactorId((int)$factorId);
    }

    /**
     * Checks if the analysis is partial by verifying if any of the specified keys are present in the params array.
     *
     * @param array $params The parameters to check
     * @return bool Returns true if any of the partial analysis keys are present, false otherwise
     */
    private function isPartialAnalysis(array $params): bool
    {
        $partialAnalysisKeys = [
            'context',
            'factor',
            'operation',
        ];
        
        foreach ($partialAnalysisKeys as $key) {
            if (array_key_exists($key, $params)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Prepare query parameters for SEO Optimizer
     *
     * @param array $requestData
     * @return array
     */
    public function prepareSeoOptimiserQueryParams(array $requestData): array
    {
        $params = [];
        if (isset($requestData['context'])) {
            $params['context'] = explode(',', (string) $requestData['context']);
        }
        if (isset($requestData['factor'])) {
            $params['factor'] = explode(',', (string) $requestData['factor']);
        }
        if (isset($requestData['operation'])) {
            $params['operation'] = explode(',', (string) $requestData['operation']);
        }
        return $params;
    }

    /**
     * Get list of important pages and posts that need SEO optimization
     * @return array Array of post IDs to optimize
     */
    private function getImportantPagesAndPosts(): array
    {
        $posts = [];
        $maxPosts = 30; // Maximum number of posts to optimize

        // 1. Homepage and blog page (highest priority)
        $frontPageId = (int) get_option('page_on_front');
        $blogPageId = (int) get_option('page_for_posts');

        if ($frontPageId > 0) {
            $posts[] = $frontPageId;
        }

        if ($blogPageId > 0 && $blogPageId !== $frontPageId) {
            $posts[] = $blogPageId;
        }

        // 2. Important static pages (About, Contact, Services, etc.)
        $posts = array_merge($posts, $this->getImportantStaticPages());

        // 3. Most viewed posts (based on comment count as a proxy for engagement)
        $posts = array_merge($posts, $this->getMostViewedPosts());

        // 4. Recent posts with high engagement
        $posts = array_merge($posts, $this->getRecentHighEngagementPosts());

        // 5. Fill remaining slots with recent posts
        $currentCount = count(array_unique($posts));
        $remaining = max(0, $maxPosts - $currentCount);

        if ($remaining > 0) {
            $posts = array_merge($posts, $this->getRecentPosts($remaining, array_unique($posts)));
        }

        // Remove duplicates and invalid IDs
        $posts = array_unique(array_filter($posts, function($postId) {
            return $postId > 0 && get_post_status($postId) === 'publish';
        }));

        // Limit to max posts
        return array_slice($posts, 0, $maxPosts);
    }

    /**
     * Get important static pages
     * @return array Array of page IDs
     */
    private function getImportantStaticPages(): array
    {
        $pageIds = [];

        $importantSlugs = [
            'about',
            'about-us',
            'contact',
            'contact-us',
            'services',
            'products',
            'portfolio',
            'team',
            'faq',
            'pricing'
        ];

        foreach ($importantSlugs as $slug) {
            $page = get_page_by_path($slug);
            if ($page && $page->post_status === 'publish') {
                $pageIds[] = $page->ID;
            }
        }

        $menuPages = $this->getPagesFromMainMenu();
        $pageIds = array_merge($pageIds, $menuPages);

        return array_unique($pageIds);
    }

    /**
     * Get pages from the main navigation menu
     * @return array Array of page IDs
     */
    private function getPagesFromMainMenu(): array
    {
        $pageIds = [];

        $locations = get_nav_menu_locations();
        if (empty($locations)) {
            return $pageIds;
        }

        $menuLocationNames = ['primary', 'main', 'header', 'main-menu', 'primary-menu'];
        $menuId = null;

        foreach ($menuLocationNames as $locationName) {
            if (isset($locations[$locationName])) {
                $menuId = $locations[$locationName];
                break;
            }
        }

        if (!$menuId) {
            $menuId = reset($locations);
        }

        if (!$menuId) {
            return $pageIds;
        }

        $menuItems = wp_get_nav_menu_items($menuId);
        if (!$menuItems) {
            return $pageIds;
        }

        foreach ($menuItems as $item) {
            if ($item->object === 'page' && $item->object_id > 0) {
                $pageIds[] = (int) $item->object_id;
            }
        }

        return $pageIds;
    }

    /**
     * Get most viewed posts based on comment count
     * @param int $limit Number of posts to retrieve
     * @return array Array of post IDs
     */
    private function getMostViewedPosts(int $limit = 10): array
    {
        $args = [
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'orderby' => 'comment_count',
            'order' => 'DESC',
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        ];

        $query = new WP_Query($args);
        return $query->posts ?: [];
    }

    /**
     * Get recent posts with high engagement (comments)
     * @param int $limit Number of posts to retrieve
     * @return array Array of post IDs
     */
    private function getRecentHighEngagementPosts(int $limit = 10): array
    {
        $args = [
            'post_type' => ['post'],
            'post_status' => 'publish',
            'orderby' => [
                'comment_count' => 'DESC',
                'date' => 'DESC'
            ],
            'date_query' => [
                [
                    'after' => '3 months ago',
                ],
            ],
            'comment_count' => [
                'value' => 1,
                'compare' => '>='
            ],
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        ];

        $query = new WP_Query($args);
        return $query->posts ?: [];
    }

    /**
     * Get recent posts
     * @param int $limit Number of posts to retrieve
     * @param array $excludeIds Post IDs to exclude
     * @return array Array of post IDs
     */
    private function getRecentPosts(int $limit = 10, array $excludeIds = []): array
    {
        $args = [
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        ];

        $query = new WP_Query($args);
        $posts = $query->posts ?: [];

        if (!empty($excludeIds)) {
            $posts = array_diff($posts, $excludeIds);
        }

        return array_slice(array_values($posts), 0, $limit);
    }

    /**
     * Optimize a batch of posts
     * @param array $postIds Array of post IDs to optimize
     * @throws Throwable
     */
    private function optimizeBatch(array $postIds): void
    {
        foreach ($postIds as $postId) {
            try {
                if (get_post_status($postId) !== 'publish') {
                    continue;
                }

                $seoScore = get_post_meta($postId, BaseConstants::OPTION_ANALYSIS_SEO_SCORE, true);
                $lastSaveTimestamp = get_post_meta($postId, BaseConstants::OPTION_ANALYSIS_DATE_TIMESTAMP, true);
                
                if (!empty($seoScore) && !empty($lastSaveTimestamp)) {
                    $timeSinceLastSave = time() - (int)$lastSaveTimestamp;
                    if ($timeSinceLastSave < 10800) { // 3 hours
                        continue;
                    }
                }

                $this->analyzeFullOptimiser($postId);

                if (function_exists('sleep')) {
                    usleep(100000); // 0.1 second delay
                }
            } catch (Throwable $e) {
                $this->log("Batch SEOOptimizer: Error for post $postId: " . $e->getMessage() . ' (File: ' . $e->getFile() . ' Line: ' . $e->getLine() . ')', 'ERROR');
            }
        }
    }

    /**
     * Calculates the average score from all analysed posts and saves comprehensive metrics to options table
     *
     * @param int $limit Maximum number of records to process
     * @return void
     * @throws Throwable
     */
    public function calculateAndSaveAverageScore(int $limit = 10000, ?int $scanCount = null): void
    {
        try {
            do_action('rankingcoach_seo_analysis_started', ['scope' => 'site']);
        } catch (Throwable $e) {
        }
        try {
            $analysisResult = \RankingCoach\Inc\Core\PostEventsManager::calculateAverageScoreFromPostMeta($limit);
            update_option(BaseConstants::OPTION_ANALYSIS_WEBSITE_SCORE_AVERAGE, $analysisResult['average_score']);
            update_option(BaseConstants::OPTION_ANALYSIS_WEBSITE_PAGES_COUNT, $analysisResult['count']);
            update_option(BaseConstants::OPTION_ANALYSIS_SCORE_MIN, $analysisResult['min_score'] ?? null);
            update_option(BaseConstants::OPTION_ANALYSIS_SCORE_MAX, $analysisResult['max_score'] ?? null);
            try {
                $valid = true;
                foreach ([
                    BaseConstants::OPTION_ANALYSIS_WEBSITE_SCORE_AVERAGE => 'average_score',
                    BaseConstants::OPTION_ANALYSIS_WEBSITE_PAGES_COUNT => 'count',
                    BaseConstants::OPTION_ANALYSIS_SCORE_MIN => 'min_score',
                    BaseConstants::OPTION_ANALYSIS_SCORE_MAX => 'max_score',
                ] as $option => $key) {
                    $stored = get_option($option, false);
                    $valid = $valid && isset($analysisResult[$key]) && is_numeric($analysisResult[$key])
                        && is_finite((float) $analysisResult[$key]) && is_numeric($stored)
                        && (float) $stored === (float) $analysisResult[$key];
                }
                $valid = $valid && $analysisResult['count'] > 0
                    && (float) $analysisResult['count'] === (float) (int) $analysisResult['count']
                    && $analysisResult['min_score'] >= 0 && $analysisResult['max_score'] <= 100
                    && $analysisResult['min_score'] <= $analysisResult['average_score']
                    && $analysisResult['average_score'] <= $analysisResult['max_score'];
                if ($valid && ($scanCount === null || $scanCount > 0
                    && $scanCount === (int) $analysisResult['count'])) {
                    do_action('rankingcoach_seo_analysis_completed', [
                        'scope' => 'site',
                        'metrics' => $analysisResult,
                        'interactive' => $scanCount !== null,
                    ]);
                }
            } catch (Throwable $e) {
            }
        } catch (Throwable $e) {
            /* translators: %s: Error message returned while calculating the average SEO score. */
            throw new Exception(sprintf(esc_html__('Failed to calculate and save average SEO score: %s', 'beyondseo'), esc_html($e->getMessage())), $e->getCode(), $e);
        }
    }

    /**
     * Run SEO optimization for important pages and posts as a background process
     *
     * @throws Throwable
     */
    public function runSeoOptimizationForImportantPagesAndPosts(): void
    {
        $posts = $this->getImportantPagesAndPosts();
        $batches = array_chunk($posts, 5);

        foreach ($batches as $postIds) {
            $this->optimizeBatch($postIds);
        }

        $this->calculateAndSaveAverageScore();
    }
}
