<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Base\Models\Results;

if (!defined('ABSPATH')) { exit; }

use RankingCoach\Inc\Core\Seo\Optimiser\Base\CategorizedSuggestions;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Factor;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\FactorSuggestions;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\OptimiserContext;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\OptimiserContexts;
use RankingCoach\Inc\Core\Seo\Optimiser\SeoOptimiser;
use RankingCoach\Inc\Core\Seo\Libs\ContentFetcher;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use Throwable;

/**
 * Class OptimiserResult
 *
 * This class represents the result of an SEO optimisation analysis.
 *
 */
class OptimiserResult
{
    /** @var float The overall score of the SEO analysis */
    public float $score;

    /** @var OptimiserContexts Contexts */
    public OptimiserContexts $contexts;

    /** @var FactorSuggestions The results of the operations performed */
    public FactorSuggestions $topSuggestions;

    /** @var CategorizedSuggestions The suggestions categorized by type */
    public CategorizedSuggestions $categorizedSuggestions;

    /** @var int The total number of unique suggestions */
    public int $totalSuggestionsCount = 0;

    /** @var string The date and time when the analysis was performed */
    public string $analyzedAt;

    /** @var array<array{url: string, duration: float|null}> The URLs that were consumed during the analysis */
    public array $urlsConsumed = [];

    /** @var float Total duration in seconds of all URL fetches */
    public float $totalFetchDuration = 0.0;

    /** @var float Total duration in seconds of all operation executions */
    public float $totalOperationsDuration = 0.0;

    /** @var array<array{name: string, key: string, duration: float}> Operations sorted by duration descending */
    public array $operationsProfile = [];

    /** @var array<string, mixed> The post data */
    public array $post = [];

    /** @var int Get maximum number of suggestions */
    private const TOP_SUGGESTIONS_LIMIT = 100;

    /**
     * Constructor for initializing the object with overall score, operation results, factors, and analysis date.
     *
     * @param int $postId
     * @param float $overallScore The overall score of the SEO analysis.
     * @param OptimiserContexts $contexts The factors evaluated during the analysis.
     * @param FactorSuggestions $topSuggestions
     * @param string $analyzedAt The date and time when the analysis was performed.
     * @param int $totalSuggestionsCount
     * @param array $urlsConsumed The URLs that were consumed during the analysis.
     * @param CategorizedSuggestions|null $categorizedSuggestions The suggestions categorized by type.
     */
    public function __construct(
        int $postId,
        float $overallScore,
        OptimiserContexts $contexts,
        FactorSuggestions $topSuggestions,
        string $analyzedAt,
        int $totalSuggestionsCount = 0,
        array $urlsConsumed = [],
        ?CategorizedSuggestions $categorizedSuggestions = null
    ) {
        $this->score = $overallScore;
        $this->contexts = $contexts;
        $this->topSuggestions = $topSuggestions;
        $this->analyzedAt = $analyzedAt;
        $this->totalSuggestionsCount = $totalSuggestionsCount;
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->urlsConsumed = $urlsConsumed;
            $this->totalFetchDuration = round(array_sum(array_column($urlsConsumed, 'duration')), 4);
        }
        $this->categorizedSuggestions = $categorizedSuggestions ?? new CategorizedSuggestions();
        $this->post = WordpressHelpers::retrieve_post($postId, true);
    }

    /**
     * Create an OptimiserResult from a SeoOptimiser domain object
     *
     * @param SeoOptimiser $seoOptimiser
     * @return self
     * @throws Throwable
     */
    public static function fromOptimiser(SeoOptimiser $seoOptimiser): self
    {
        $allSuggestions = new FactorSuggestions();
        if($seoOptimiser->contexts instanceof OptimiserContexts) {
            foreach ($seoOptimiser->contexts->getElements() as $context) {
                if($context instanceof OptimiserContext) {
                    $contextSuggestions = $context->getContextSuggestions();
                    foreach ($contextSuggestions as $suggestion) {
                        if ($allSuggestions->getByUniqueKey($suggestion->uniqueKey()) !== null) {
                            continue;
                        }
                        $allSuggestions->add($suggestion);
                    }
                }
            }
        }

        $limitedTopSuggestions = self::getTopSuggestions($allSuggestions->orderBy('priority'));
        $score = round($seoOptimiser->score, 2);
        // Calculate and set the total suggestions count
        $totalSuggestionsCount = self::getTotalSuggestionsCount($seoOptimiser);

        // Generate categorized suggestions
        $categorizedSuggestions = self::categorizeSuggestions($allSuggestions);

        $urlsConsumed = (defined('WP_DEBUG') && WP_DEBUG) ? ContentFetcher::getUrlsFromCache() : [];

        $result = new self(
            $seoOptimiser->postId,
            $score,
            $seoOptimiser->contexts,
            $limitedTopSuggestions,
            gmdate('Y-m-d H:i:s', $seoOptimiser->analysisDate->getTimestamp()),
            $totalSuggestionsCount,
            $urlsConsumed,
            $categorizedSuggestions
        );

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $totalOperationsDuration = 0.0;
            $operationsProfile = [];
            foreach ($seoOptimiser->contexts->getElements() as $context) {
                if ($context instanceof OptimiserContext) {
                    foreach ($context->factors->getElements() as $factor) {
                        foreach ($factor->operations->getElements() as $operation) {
                            $totalOperationsDuration += $operation->executionDuration;
                            $operationsProfile[] = [
                                'name' => $operation->operationName,
                                'key' => $operation->operationKey,
                                'duration' => $operation->executionDuration,
                            ];
                        }
                    }
                }
            }
            usort($operationsProfile, static fn(array $a, array $b) => $b['duration'] <=> $a['duration']);
            $result->totalOperationsDuration = round($totalOperationsDuration, 4);
            $result->operationsProfile = $operationsProfile;
        }

        return $result;
    }

    /**
     * Get the top suggestions from the analysis suggestions
     * @param FactorSuggestions $analyzeSuggestions
     * @return FactorSuggestions
     */
    public static function getTopSuggestions(FactorSuggestions $analyzeSuggestions): FactorSuggestions
    {
        $topSuggestions = new FactorSuggestions();
        foreach ($analyzeSuggestions->getElements() as $suggestion) {
            if ($topSuggestions->count() >= self::TOP_SUGGESTIONS_LIMIT) {
                break;
            }
            $topSuggestions->add($suggestion);
        }
        return $topSuggestions;
    }

    /**
     * Categorize suggestions based on their issue type
     *
     * @param FactorSuggestions $suggestions The suggestions to categorize
     * @return CategorizedSuggestions Categorized suggestions
     */
    public static function categorizeSuggestions(FactorSuggestions $suggestions): CategorizedSuggestions
    {
        $categorizedSuggestions = new CategorizedSuggestions();

        foreach ($suggestions->getElements() as $suggestion) {
            $categorizedSuggestions->addSuggestion($suggestion);
        }

        // Sort all categories by priority
        $categorizedSuggestions->sortByPriority();

        return $categorizedSuggestions;
    }


    /**
     * Calculate the total number of suggestions from a page analysis
     *
     * This method counts all unique suggestions across all factors in all contexts
     *
     * @param SeoOptimiser $result The optimiser result to analyze
     * @return int The total number of unique suggestions
     * @throws Throwable
     */
    public static function getTotalSuggestionsCount(SeoOptimiser $result): int
    {
        $uniqueSuggestions = [];

        // Iterate through all contexts
        foreach ($result->contexts->getElements() as $context) {
            /** @var OptimiserContext $context */
            // Iterate through all factors in the context
            foreach ($context->factors->getElements() as $factor) {
                /** @var Factor $factor */
                // Get all suggestions for this factor
                $factorSuggestions = $factor->getFactorSuggestions();

                // Add each suggestion's unique key to our tracking array
                foreach ($factorSuggestions->getElements() as $suggestion) {
                    $uniqueSuggestions[$suggestion->uniqueKey()] = true;
                }
            }
        }

        // Return the count of unique suggestions
        return count($uniqueSuggestions);
    }
}
