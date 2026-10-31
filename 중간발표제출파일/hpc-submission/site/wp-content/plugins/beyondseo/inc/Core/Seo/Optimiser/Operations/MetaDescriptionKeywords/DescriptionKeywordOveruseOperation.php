<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\MetaDescriptionKeywords;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class DescriptionKeywordOveruseOperation
 *
 * This class is responsible for validating primary and secondary keywords.
 */
#[SeoMeta(
    name: 'Description Keyword Overuse',
    weight: WeightConfiguration::WEIGHT_KEYWORD_OVERUSE_OPERATION,
    description: 'Checks meta description for excessive repetition of target keywords. Calculates optimal ratio between primary and secondary terms, flagging when overuse occurs to prevent penalties from keyword stuffing.',
)]
class DescriptionKeywordOveruseOperation extends Operation implements OperationInterface
{
    /**
     * Validates primary and secondary keywords for the given post-ID.
     *
     * @return array|null The validation results or null if the post-ID is invalid.
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        $primaryKeyword = $this->contentProvider->getPrimaryKeyword($postId);
        $secondaryKeywords = $this->contentProvider->getSecondaryKeywords($postId);

        $hasPluginSupport = $this->contentProvider->detectKeywordPluginSupport();

        // Get post-content for analysis
        $metaDescription = $this->contentProvider->getMetaDescription($postId);

        if (empty($metaDescription)) {
            return [
                'success' => false,
                'message' => __('Meta description is empty', 'beyondseo'),
                'primary_keyword' => $primaryKeyword,
                'secondary_keywords' => $secondaryKeywords,
                'keyword_usage' => [],
                'has_plugin_support' => $hasPluginSupport,
            ];
        }

        $keywords = array_merge([$primaryKeyword], $secondaryKeywords);
        $maxKeywordWordCount = array_reduce($keywords, static fn ($maxKeywordWordCount, $keyword) => max($maxKeywordWordCount, str_word_count($keyword)), 0);

        return [
            'success' => true,
            'message' => __('Keywords validated successfully', 'beyondseo'),
            'primary_keyword' => $primaryKeyword,
            'secondary_keywords' => $secondaryKeywords,
            'keyword_usage' => $this->contentProvider->analyzeContentRepetition($metaDescription, 1, $maxKeywordWordCount),
            'has_plugin_support' => $hasPluginSupport,
        ];
    }

    /**
     * Evaluate the operation value based on primary and secondary keywords' validation.
     *
     * @return float A score based on the keyword validation
     */
    public function calculateScore(): float
    {
        $score = 1.0;
        $primaryKeyword = $this->value['primary_keyword'] ?? '';
        $secondaryKeywords = $this->value['secondary_keywords'] ?? [];
        $keywordUsage = $this->value['keyword_usage'] ?? [];
        if (array_key_exists($primaryKeyword, $keywordUsage)) {
            $score -= 0.5;
        }

        $overusedSecondaryCount = 0;
        foreach ($secondaryKeywords as $secondaryKeyword) {
            if (array_key_exists($secondaryKeyword, $keywordUsage)) {
                $overusedSecondaryCount++;
            }
        }

        // Calculate penalty for secondary keywords (if any exist)
        if (!empty($secondaryKeywords)) {
            $secondaryKeywordPenalty = ($overusedSecondaryCount / count($secondaryKeywords)) * 0.5;
            $score -= $secondaryKeywordPenalty;
        }

        return max(0, $score);
    }

    /**
     * Get suggestions for the operation.
     *
     * @return array An array of suggestions
     */
    public function suggestions(): array
    {
        $suggestions = [];

        // Check for keyword overuse (score < 1.0 indicates problems)
        if ($this->score < 1.0) {
            $suggestions[] = Suggestion::KEYWORD_REPETITION_IN_META_DESCRIPTION;
        }

        return $suggestions;
    }
}
