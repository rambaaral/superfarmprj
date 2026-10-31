<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser;

if (!defined('ABSPATH')) { exit; }

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Factors;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Models\Results\OptimiserResult;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\OptimiserContext;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\OptimiserContexts;
use RankingCoach\Inc\Core\Seo\Optimiser\Contexts\ContentOptimisationContext;
use RankingCoach\Inc\Core\Seo\Optimiser\Contexts\LinkingStrategyContext;
use RankingCoach\Inc\Core\Seo\Optimiser\Contexts\PerformanceAndSpeedContext;
use RankingCoach\Inc\Core\Seo\Optimiser\Contexts\TechnicalSeoContext;
use RankingCoach\Inc\Core\Seo\Repositories\SeoOptimiserRepository;
use RankingCoach\Inc\Core\Seo\Repositories\SeoFactorRepository;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use DateTime;
use Exception;
use ReflectionClass;
use ReflectionException;
use Throwable;

/**
 * Class SeoOptimiser
 *
 * This class is responsible for managing the SEO optimization analysis.
 */
class SeoOptimiser
{
    use RcLoggerTrait;

    /** @var int|null Database identifier for this optimizer */
    public ?int $id = null;

    /** @var int The ID of the post/page the SEO optimizer analyzes */
    public int $postId;
    
    /** @var float The overall SEO score for the post on a scale of 0-1, calculated from weighted context scores */
    public float $score = 0.0;
    
    /** @var DateTime The timestamp when the SEO analysis was last performed */
    public DateTime $analysisDate;
    
    /** @var OptimiserContexts Collection of different analysis contexts with their factors and operations */
    public OptimiserContexts $contexts;

    protected static array $contextClasses = [
        ContentOptimisationContext::class,
        TechnicalSeoContext::class,
        PerformanceAndSpeedContext::class,
        LinkingStrategyContext::class
    ];

    /**
     * Constructor for the SEO optimizer
     * 
     * @param int|null $postId The ID of the post to be optimized
     */
    public function __construct(?int $postId = null)
    {
        $this->contexts = new OptimiserContexts();
        $this->analysisDate = new DateTime();
        if ($postId) {
            $this->postId = $postId;
        }
    }

    /**
     * Initialize the SEO optimizer with context classes
     * @param array $params
     * @return void
     * @throws ReflectionException
     */
    public function initContexts(array $params = []): void
    {
        /** @var class-string<OptimiserContext> $optimiserContextClass */
        foreach (self::$contextClasses as $optimiserContextClass) {
            $attributes = (new ReflectionClass($optimiserContextClass))->getAttributes(SeoMeta::class);
            foreach ($attributes as $attribute) {
                /** @var SeoMeta $seoMeta */
                $seoMeta = $attribute->newInstance();
                if (isset($params['context']) && !in_array($seoMeta->getKey('context'), $params['context'], true)) {
                    continue;
                }
                $contextClass = new $optimiserContextClass(
                    $seoMeta->getLocalizedName(),
                    $seoMeta->getKey('context'),
                    $seoMeta->weight,
                    $this->id ?? 0,
                    $params,
                    true
                );
                $this->addContext($contextClass);
            }
        }
    }

    /**
     * Calculate the overall score for all contexts
     * 
     * Triggers score calculation in all contexts and compute weighted average
     * 
     * @return float The overall SEO score between 0-1
     */
    public function calculateScore(): float
    {
        $this->contexts->calculateAllScores();
        $this->score = $this->contexts->getWeightedScore();
        return $this->score;
    }

    /**
     * Add a context to the SEO optimizer
     * 
     * @param OptimiserContext $context The context to be added
     */
    public function addContext(OptimiserContext $context): void
    {
        $this->contexts->add($context);
    }

    /**
     * Analyzes the factors and contexts to determine an overall score
     *
     * Executes all operations in all factors across all contexts and calculates the score.
     *
     * A failure while executing a single factor is caught and logged instead of aborting the
     * whole analysis, so that factors already executed (and, when a factor repository is
     * provided, already persisted) are not lost because of one bad factor.
     *
     * When a factor repository is supplied, each factor is persisted immediately after it
     * finishes executing (successfully or not), so data is saved incrementally as the
     * context/factor loop progresses instead of only at the very end of the whole analysis.
     * Callers are expected to have already persisted the optimiser/context/factor/operation
     * skeleton (e.g. via SeoOptimiserRepository::save()) so that factor/operation IDs exist
     * and this incremental save performs an UPDATE rather than an INSERT.
     *
     * @param SeoFactorRepository|null $factorRepo Optional repository used to persist each
     *                                              factor immediately after it is executed.
     * @return float The calculated overall score after performing the analysis
     */
    public function analyze(?SeoFactorRepository $factorRepo = null): float
    {
        foreach ($this->contexts as $context) {
            foreach ($context->factors as $factor) {
                try {
                    $factor->execute([
                        'postId' => $this->postId,
                    ]);
                } catch (Throwable $e) {
                    $this->log(
                        sprintf(
                            "Factor execution failed for '%s' (context '%s'): %s",
                            $factor->factorKey,
                            $context->contextKey,
                            $e->getMessage()
                        ),
                        'ERROR'
                    );
                } finally {
                    if ($factorRepo !== null) {
                        try {
                            $factors = new Factors();
                            $factors->add($factor);
                            $factorRepo->save($factors);
                        } catch (Throwable $e) {
                            $this->log(
                                sprintf(
                                    "Failed to persist factor '%s' (context '%s'): %s",
                                    $factor->factorKey,
                                    $context->contextKey,
                                    $e->getMessage()
                                ),
                                'ERROR'
                            );
                        }
                    }
                }
            }
        }
        return $this->calculateScore();
    }

    /**
     * Get a specific context by its key
     * 
     * @param string $contextKey The unique identifier for the context
     * @return OptimiserContext|null The requested context or null if not found
     */
    public function getContext(string $contextKey): ?OptimiserContext
    {
        return $this->contexts->getContextByKey($contextKey);
    }

    /**
     * Load SEO analysis data for a post from the database
     *
     * @param SeoOptimiserRepository $repo
     * @return static
     */
    public function loadSeoOptimiserData(SeoOptimiserRepository $repo): static
    {
        $postId = $this->postId;
        if (!$postId) {
            return $this;
        }

        $optimiser = $repo->getByPostId($postId);
        if (!($optimiser instanceof SeoOptimiser) || !($optimiser->id ?? false)) {
            return $this;
        }

        // Map base properties
        $this->id = (int)$optimiser->id;
        $this->postId = (int)$optimiser->postId;
        $this->score = (float) $optimiser->score;
        $this->analysisDate = $optimiser->analysisDate;
        $this->contexts = $optimiser->contexts;

        return $this;
    }

    /**
     * Retrieves a unique key for the SEO optimizer
     * 
     * @return string A unique identifier based on entity ID or post-ID
     */
    public function uniqueKey(): string
    {
        return md5(static::class . '_' . ($this->id ?? $this->postId));
    }

    /**
     * Persists the calculated SEO score to the post's metadata
     *
     * @param self $seoOptimiser
     * @return bool True if the score was successfully saved, false otherwise
     * @throws Throwable
     */
    public function saveToPostMeta(self $seoOptimiser): bool
    {
        // Validate post ID
        if (empty($this->postId) || !is_numeric($this->postId) || $this->postId <= 0) {
            throw new Exception(esc_html__('Invalid post ID for SEO score persistence', 'beyondseo'));
        }

        // Ensure score is calculated and normalized
        if ($this->score < 0 || $this->score > 1) {
            $this->score = max(0.0, min(1.0, $this->score));
        }
        
        // Update analysis timestamp if not already set
        if (!isset($this->analysisDate)) {
            $this->analysisDate = new DateTime();
        }
        
        // Convert score to percentage for better readability in meta
        $scorePercentage = round($this->score * 100);
        // Calculate and set the total suggestions count
        $totalSuggestionsCount = OptimiserResult::getTotalSuggestionsCount($seoOptimiser);
        // Encode the score breakdown as JSON for structured storage
        $scoreBreakdown = wp_json_encode($this->getScoreBreakdown());

        // Generate content hash for future comparisons
        $contentHash = WordpressHelpers::generateContentHash($this->postId);

        // Persist data to WordPress post meta using native WordPress functions
        $scoreUpdated       = update_post_meta($this->postId, BaseConstants::OPTION_ANALYSIS_SEO_SCORE, $scorePercentage);
        $timestampUpdated   = update_post_meta($this->postId, BaseConstants::OPTION_ANALYSIS_DATE_TIMESTAMP, time());
        update_post_meta($this->postId, BaseConstants::OPTION_ANALYSIS_ISSUES_COUNT, 0);
        update_post_meta($this->postId, BaseConstants::OPTION_ANALYSIS_SUGGESTIONS_COUNT, $totalSuggestionsCount);
        update_post_meta($this->postId, BaseConstants::OPTION_ANALYSIS_SCORE_BREAKDOWN, $scoreBreakdown);
        
        // Update caching metadata
        update_post_meta($this->postId, BaseConstants::OPTION_ANALYSIS_CONTENT_HASH, $contentHash);

        return ($scoreUpdated !== false && $timestampUpdated !== false);
    }

    /**
     * Get a detailed breakdown of the entire scoring hierarchy
     *
     * Returns the optimizer score with contexts, factors and operations along
     * with their individual weights and scores.
     */
    public function getScoreBreakdown(): array
    {
        $contexts = [];
        foreach ($this->contexts as $context) {
            $contexts[] = $context->getScoreBreakdown();
        }

        return [
            'postId' => $this->postId,
            'score' => $this->score,
            'contexts' => $contexts,
        ];
    }

    /**
     * Extracts meta information and suggestions from all context, factor, and operation classes in a structured format.
     *
     * @return array
     */
    public static function extractData(): array
    {
        $contexts = [];
        foreach (self::$contextClasses as $contextClass) {
            $reflectionContext = new \ReflectionClass($contextClass);
            $contextMeta = self::getSeoMeta($contextClass);
            $contextWeight = $contextMeta['weight'] ?? null;
            $contextData = [
                'class' => $reflectionContext->getShortName(),
                'name' => $contextMeta['name'] ?? null,
                'description' => $contextMeta['description'] ?? null,
                'weight' => $contextWeight,
                'factors' => [],
            ];
            // Use reflection to access private/protected static $contextFactors
            $contextFactors = [];
            if ($reflectionContext->hasProperty('contextFactors')) {
                $property = $reflectionContext->getProperty('contextFactors');
                if ($property->isStatic()) {
                    $property->setAccessible(true);
                    $contextFactors = $property->getValue();
                }
            }
            foreach ($contextFactors as $factorClass) {
                $reflectionFactor = new \ReflectionClass($factorClass);
                $factorMeta = self::getSeoMeta($factorClass);
                $factorWeight = $factorMeta['weight'] ?? null;
                $factorData = [
                    'class' => $reflectionFactor->getShortName(),
                    'name' => $factorMeta['name'] ?? null,
                    'description' => $factorMeta['description'] ?? null,
                    'weight' => $factorWeight,
                    'operations' => [],
                ];
                // Use reflection to access private/protected static $operationsClasses
                $operationsClasses = [];
                if ($reflectionFactor->hasProperty('operationsClasses')) {
                    $property = $reflectionFactor->getProperty('operationsClasses');
                    if ($property->isStatic()) {
                        $property->setAccessible(true);
                        $operationsClasses = $property->getValue();
                    }
                }
                foreach ($operationsClasses as $operationClass) {
                    $operationMeta = self::getSeoMeta($operationClass);
                    $operationWeight = $operationMeta['weight'] ?? null;
                    $reflectionOperation = new \ReflectionClass($operationClass);
                    $operationClassShort = $reflectionOperation->getShortName();
                    $operationData = [
                        'class' => $operationClassShort,
                        'name' => $operationMeta['name'] ?? null,
                        'description' => $operationMeta['description'] ?? null,
                        'weight' => $operationWeight,
                        'suggestions' => [],
                    ];
                    // Use reflection to extract suggestions statically if possible
                    if ($reflectionOperation->hasMethod('suggestions')) {
                        $method = $reflectionOperation->getMethod('suggestions');
                        $filename = $method->getFileName();
                        $startLine = $method->getStartLine();
                        $endLine = $method->getEndLine();
                        if ($filename && $startLine && $endLine) {
                            $lines = file($filename);
                            $methodCode = implode("", array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
                            // Find all Suggestion::SOMETHING occurrences
                            if (preg_match_all('/Suggestion::([A-Z0-9_]+)/', $methodCode, $matches)) {
                                foreach ($matches[1] as $enumCase) {
                                    $suggestion = null;
                                    if (defined(Suggestion::class . '::' . $enumCase)) {
                                        $suggestion = constant(Suggestion::class . '::' . $enumCase);
                                    } else {
                                        $suggestion = 'Suggestion::' . $enumCase;
                                    }
                                    $suggestionDescription = method_exists($suggestion, 'getDescription') ? $suggestion->getDescription() : null;
                                    $operationData['suggestions'][] = [
                                        'enum' => $enumCase,
                                        'title' => $suggestionDescription['title'] ?? ($enumCase ?? null),
                                        'description' => $suggestionDescription['description'] ?? null,
                                    ];
                                }
                            }
                        }
                    }
                    $factorData['operations'][] = $operationData;
                }
                $contextData['factors'][] = $factorData;
            }
            $contexts[] = $contextData;
        }
        return $contexts;
    }

    private static function getSeoMeta(string $class): array
    {
        if (!class_exists($class)) {
            return [];
        }
        $reflection = new \ReflectionClass($class);
        $attributes = $reflection->getAttributes(SeoMeta::class);
        foreach ($attributes as $attribute) {
            /** @var SeoMeta $instance */
            $instance = $attribute->newInstance();
            return [
                'name' => $instance->getLocalizedName(),
                'description' => $instance->getLocalizedDescription(),
                'weight' => $instance->weight,
            ];
        }
        return [];
    }
}
