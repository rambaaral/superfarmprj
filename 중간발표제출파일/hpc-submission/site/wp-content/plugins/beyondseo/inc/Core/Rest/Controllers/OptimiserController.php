<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Rest\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use RankingCoach\Inc\Core\Api\Content\ContentApiManager;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;
use RankingCoach\Inc\Core\Helpers\Traits\RcApiTrait;
use RankingCoach\Inc\Core\Initializers\Hooks;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\FactorSuggestions;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Models\Results\OptimiserResult;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\OptimiserContexts;
use RankingCoach\Inc\Core\Seo\Optimiser\SeoOptimiser;
use RankingCoach\Inc\Core\Seo\Services\SeoOptimiserService;
use RankingCoach\Inc\Modules\ModuleManager;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;

class OptimiserController
{
    use RcApiTrait;
    use RcLoggerTrait;

    public function get(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $postId = (int) $request->get_param('postId');
            $analyseResult = $this->retrieveAnalysis($postId);

            return new WP_REST_Response(['analyseResult' => $analyseResult], 200);
        } catch (Throwable $e) {
            $this->log('Error in OptimiserController::get: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    public function getSeoIssues(array $suggestions = [], ?int $postId = null): array
    {
        global $wpdb;

        $suggestionFilter = array_fill_keys($suggestions, true);

        $query = DatabaseManager::getInstance()
            ->table(DatabaseTablesManager::DATABASE_SEO_OPERATIONS, 'operations')
            ->select([
                'operations.suggestions',
                'posts.ID AS id',
                'posts.post_title',
                'posts.post_excerpt',
                'posts.post_status',
                'posts.post_name',
                'posts.post_type',
            ])
            ->leftJoin(DatabaseTablesManager::DATABASE_SEO_FACTORS . ' AS factors', 'operations.factorId = factors.id')
            ->leftJoin(DatabaseTablesManager::DATABASE_SEO_CONTEXTS . ' AS contexts', 'factors.contextId = contexts.id')
            ->leftJoin(DatabaseTablesManager::DATABASE_SEO_OPTIMISERS . ' AS optimisers', 'contexts.analysisId = optimisers.id')
            ->leftJoin($wpdb->posts . ' AS posts', 'optimisers.postId = posts.ID')
            ->whereNotNull('posts.ID')
            ->where('posts.post_status', 'trash', '!=');

        if ($postId !== null) {
            $query->where('optimisers.postId', (int) $postId);
        }

        $wpdb->last_error = '';
        $rows = $query->get();
        if ($wpdb->last_error !== '' || !is_array($rows)) {
            throw new \RuntimeException('Failed to retrieve SEO issues from the database.');
        }

        $issues = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            if (!is_string($row['suggestions']) || $row['suggestions'] === '') {
                continue;
            }

            $suggestions = json_decode($row['suggestions']);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($suggestions)) {
                continue;
            }

            $post = [
                'id' => (int) $row['id'],
                'post_title' => $row['post_title'],
                'post_excerpt' => $row['post_excerpt'],
                'post_status' => $row['post_status'],
                'post_name' => $row['post_name'],
                'post_type' => $row['post_type'],
            ];

            foreach ($suggestions as $suggestion) {
                if (!is_string($suggestion) || $suggestion === '') {
                    continue;
                }
                if ($suggestionFilter !== [] && !isset($suggestionFilter[$suggestion])) {
                    continue;
                }

                if (!isset($issues[$suggestion])) {
                    $metadata = Suggestion::getDescription($suggestion);
                    $issues[$suggestion] = [
                        'description' => $metadata['description'],
                        'title' => $metadata['title'],
                        'posts' => [],
                    ];
                }

                $issues[$suggestion]['posts'][$post['id']] = $post;
            }
        }

        foreach ($issues as &$issue) {
            $issue['posts'] = array_values($issue['posts']);
        }
        unset($issue);

        return $issues;
    }

    public function getSeoIssuesRest(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $suggestionsParam = $request->get_param('suggestions');
            $suggestions = $suggestionsParam === null ? [] : json_decode($suggestionsParam);
            $postId = $request->get_param('post_id');
            $issues = $this->getSeoIssues($suggestions, $postId === null ? null : (int) $postId);

            return new WP_REST_Response((object) $issues, 200);
        } catch (Throwable $e) {
            $this->log('Error in OptimiserController::getSeoIssuesRest: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    public function run(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $postId = (int) $request->get_param('postId');

            $shouldThrottle = apply_filters(Hooks::RANKINGCOACH_FILTER_SHOULD_THROTTLE_SEO_ANALYSIS, false, $postId);
            $review = ['postId' => $postId, 'completed' => false, 'score' => null, 'milestone' => false];

            if ($this->isUnsavedNewPost($postId)) {
                $analyseResult = $this->getAnalyseResult($postId);
            } elseif (!$shouldThrottle) {
                ModuleManager::instance()->initialize()->linkAnalyzer()->hooksComponent->analyzeLinks($postId);
                $reviewInteractive = $request->get_param('reviewContext') === 'editor';
                $analyseResult = $this->performAnalysis($postId, $reviewInteractive, $review);
            } else {
                $analyseResult = $this->getAnalyseResult($postId);
            }

            return new WP_REST_Response(['analyseResult' => $analyseResult, 'review' => $review], 200);
        } catch (Throwable $e) {
            $this->log('Error in OptimiserController::run: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }

    private function retrieveAnalysis(int $postId): array|OptimiserResult
    {
        $result = $this->getAnalyseResult($postId);

        // A read of a post that was never analyzed runs the first analysis on the spot.
        if ($result instanceof OptimiserResult && empty($result->analyzedAt) && !$this->isUnsavedNewPost($postId)) {
            return $this->performAnalysis($postId);
        }

        return $result;
    }

    /**
     * Whether the post is still the empty `auto-draft` WordPress inserts on every "Add New"
     * page view. The editor meta box mounts on it (WORDPRESS-986), but it must not be analyzed:
     * the content renderer flips a non-published post to `publish` in the database while it
     * renders it (RcContentRetrieveTrait::executeWithTemporaryPublishStatus()), which would
     * briefly expose an empty "Auto Draft" post on the live site, and every abandoned auto-draft
     * would leave orphaned optimiser rows behind. The first save turns the post into a draft,
     * and that save triggers the first analysis.
     */
    private function isUnsavedNewPost(int $postId): bool
    {
        return get_post_status($postId) === 'auto-draft';
    }

    private function performAnalysis(int $postId, bool $reviewInteractive = false, ?array &$review = null): OptimiserResult
    {
        $seoOptimiser = SeoOptimiserService::getInstance()->analyzeFullOptimiser($postId, [], false, $reviewInteractive, $analysisCompleted);
        $review = ['postId' => $postId, 'completed' => false, 'score' => null, 'milestone' => false];
        if ($reviewInteractive && $analysisCompleted) {
            try {
                $review = apply_filters('rankingcoach_review_analysis_result', $review, 'post', $postId);
            } catch (Throwable $e) {
            }
        }
        return OptimiserResult::fromOptimiser($seoOptimiser);
    }

    private function getAnalyseResult(int $postId): array|OptimiserResult
    {
        $seoOptimiser = SeoOptimiserService::getInstance()->getOptimiserByPostId($postId);

        if ($seoOptimiser === null) {
            return new OptimiserResult($postId, 0, new OptimiserContexts(), new FactorSuggestions(), '');
        }

        return OptimiserResult::fromOptimiser($seoOptimiser);
    }

    public function exportData(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $postId = (int) $request->get_param('postId');
            $export = sanitize_text_field($request->get_param('export') ?? 'json');

            $row = DatabaseManager::getInstance()
                ->table(DatabaseTablesManager::DATABASE_SEO_OPTIMISERS)
                ->select(['*'])
                ->where('postId', $postId)
                ->first();

            $row = is_object($row) ? (array) $row : ($row ?: []);

            if ($export === 'csv') {
                $csvLines = [];
                if (!empty($row)) {
                    $csvLines[] = implode(',', array_keys($row));
                    $csvLines[] = implode(',', array_map(fn($v) => '"' . str_replace('"', '""', (string) $v) . '"', array_values($row)));
                }
                $csv = implode("\n", $csvLines);

                $response = new WP_REST_Response($csv, 200);
                $response->header('Content-Type', 'text/csv');
                return $response;
            }

            return new WP_REST_Response(['format' => 'json', 'jsonData' => $row, 'csv' => ''], 200);
        } catch (Throwable $e) {
            $this->log('Error in OptimiserController::exportData: ' . $e->getMessage(), 'ERROR');
            return $this->generateErrorResponse($e, null, 500);
        }
    }
}
