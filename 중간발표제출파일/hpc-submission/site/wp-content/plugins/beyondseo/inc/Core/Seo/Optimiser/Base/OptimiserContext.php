<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Base;

if (!defined('ABSPATH')) { exit; }

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use ReflectionClass;
use Throwable;

/**
 * Class OptimiserContext
 * 
 * Represents a major category of SEO analysis.
 */
class OptimiserContext
{
    // Persistent properties - stored in a database
    /** @var int|null $id Database identifier for this context */
    public ?int $id = null;
    /** @var int $analysisId Reference to the parent analysis run this context belongs to */
    public int $analysisId = 0;
    /** @var string|null $contextName Unique identifier key for this context (e.g., 'content_optimisation', 'technical_seo') */
    public ?string $contextName = null;
    /** @var string|null $contextKey Unique identifier key for this context (e.g., 'content_optimisation', 'technical_seo') */
    public ?string $contextKey = null;
    /** @var float $weight Relative importance of this context in the overall SEO score (higher = more important) */
    public float $weight = 1.0;
    /** @var float $score Calculated score for this context (0.0-1.0) representing the SEO performance for this area */
    public float $score = 0.0;

    // Additional runtime properties - not directly persisted
    /** @var Factors $factors Collection of SEO factors that contribute to this context's analysis */
    public Factors $factors;

    /** @var array $contextFactors List of SEO factors that are part of this context */
    protected static array $contextFactors = [];

    /**
     * Constructor for creating a new OptimiserContext
     *
     * @param string|null $contextName The name of this context
     * @param string|null $contextKey The key identifying this context type
     * @param float $weight The weight of this context in the overall score calculation
     * @param int $analysisId The ID of the analysis run this context belongs to
     * @param array $params Additional parameters for context initialization
     * @param bool $initFactors Whether to initialize factors for this context
     */
    public function __construct(
        ?string $contextName = null,
        ?string $contextKey = null,
        float $weight = 1.0,
        int $analysisId = 0,
        array $params = [],
        bool $initFactors = false
    ) {
        $this->contextName = $contextName;
        $this->contextKey = $contextKey;
        $this->weight = ($weight !== null && $weight >= 0) ? $weight : 1.0;
        $this->analysisId = $analysisId;
        $this->factors = new Factors();

        if ($initFactors) {
            $this->initFactors($params);
        }
    }

    /**
     * Initialize factors for this context
     *
     * @param array $params Additional parameters for factor initialization
     * @return void
     */
    protected function initFactors(array $params = []): void
    {
        /** @var class-string<Factor> $factorClass */
        foreach (static::$contextFactors as $factorClass) {
            $attributes = (new ReflectionClass($factorClass))->getAttributes(SeoMeta::class);
            foreach ($attributes as $attribute) {
                /** @var SeoMeta $seoMeta */
                $seoMeta = $attribute->newInstance();
                if (isset($params['factor']) && !in_array($seoMeta->getKey('factor'), $params['factor'], true)) {
                    continue;
                }
                $factor = new $factorClass(
                    $seoMeta->getLocalizedName(),
                    $seoMeta->getKey('factor'),
                    $seoMeta->weight,
                    $seoMeta->getLocalizedDescription(),
                    $params,
                    true
                );
                if ($factor->isAvailable()) {
                    $this->addFactor($factor);
                }
            }
        }
    }

    /**
     * Calculate the overall score for this context
     *
     * @return float The calculated score (0.0-1.0) representing performance in this SEO area
     */
    public function calculateScore(): float
    {
        $this->factors->calculateAllScores();
        $this->score = $this->factors->getWeightedScore();
        return $this->score;
    }

    /**
     * Add a factor to this context
     *
     * @param Factor $factor The SEO factor to add to this context
     * @return void
     */
    public function addFactor(Factor $factor): void
    {
        $factor->analysisId = $this->analysisId;
        $this->factors->add($factor);
    }

    /**
     * @throws Throwable
     */
    public function getContextSuggestions(): FactorSuggestions
    {
        $contextSuggestions = new FactorSuggestions();
        if (!isset($this->factors) || count($this->factors->getElements()) === 0) {
            return $contextSuggestions;
        }
        foreach ($this->factors->getElements() as $factor) {
            $factorSuggestions = $factor->getFactorSuggestions();
            /** @var FactorSuggestion $suggestion */
            foreach ($factorSuggestions as $suggestion) {
                if ($contextSuggestions->getByUniqueKey($suggestion->uniqueKey()) === $suggestion) {
                    continue;
                }
                $contextSuggestions->add($suggestion);
            }
        }
        return $contextSuggestions->orderBy('priority');
    }

    /**
     * Get a detailed score breakdown for this context
     *
     * Returns the context name, key, weight, calculated score and the list
     * of factor breakdowns that contributed to it.
     */
    public function getScoreBreakdown(): array
    {
        $factors = [];
        foreach ($this->factors as $factor) {
            $factors[] = $factor->getScoreBreakdown();
        }

        return [
            'name' => $this->contextName,
            'key' => $this->contextKey,
            'weight' => $this->weight,
            'score' => $this->score,
            'factors' => $factors,
        ];
    }

    /**
     * Generate a unique key for this context
     *
     * @return string Unique identifier string
     */
    public function uniqueKey(): string
    {
        return md5(static::class . '_' . $this->analysisId . '_' . $this->contextKey);
    }

    /**
     * Check if this context is available based on its factors
     * A context is considered available if it has at least one available factor
     *
     * @return bool True if the context has available factors, false otherwise
     */
    public function isAvailable(): bool
    {
        return true;
    }
}
