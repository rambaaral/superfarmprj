<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\HeaderTagsStructure;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class KeywordsInHeaderCheckOperation
 *
 * This class is responsible for checking the presence of keywords in header tags in a WordPress post.
 */
#[SeoMeta(
    name: 'Keywords In Header Check',
    weight: WeightConfiguration::WEIGHT_KEYWORDS_IN_HEADER_CHECK_OPERATION,
    description: 'Examines heading elements for primary and secondary keyword usage, measuring frequency and placement. Highlights overuse or absence of keywords in headers to improve relevancy and SEO performance.',
)]
class KeywordsInHeaderCheckOperation extends Operation implements OperationInterface
{
    /**
     * Function for running the operation
     * @return array|null
     */
    public function run(): ?array
    {
        $content = $this->contentProvider->getContent($this->postId);

        if (empty($content)) {
            return [
                'success' => false,
                'message' => __('Unable to retrieve content', 'beyondseo'),
            ];
        }

        // Get keywords
        $primaryKeyword = $this->contentProvider->getPrimaryKeyword($this->postId);
        $secondaryKeywords = $this->contentProvider->getSecondaryKeywords($this->postId);

        // Check if keywords are present in the header tags
        $headings = $this->contentProvider->getHeadingsFromContent($content);

        // Check primary keyword
        $primaryMatches = $this->contentProvider->matchHeadingTextByKeyword($headings, $primaryKeyword);
        $foundInH1 = false;

        foreach ($primaryMatches as $match) {
            if (strtolower($match['level']) === 'h1') {
                $foundInH1 = true;
                break;
            }
        }

        // Check all secondary keywords
        $secondaryAnalysis = [];
        foreach ($secondaryKeywords as $keyword) {
            $matches = $this->contentProvider->matchHeadingTextByKeyword($headings, $keyword);
            $secondaryAnalysis[] = [
                'keyword' => $keyword,
                'found' => count($matches) > 0,
                'matches' => $matches,
            ];
        }

        return [
            'success' => true,
            'message' => __('Keyword in header check completed successfully', 'beyondseo'),
            'primary_keyword' => [
                'keyword' => $primaryKeyword,
                'found' => count($primaryMatches) > 0,
                'found_in_h1' => $foundInH1,
                'matches' => $primaryMatches,
            ],
            'secondary_keywords' => $secondaryAnalysis,
        ];
    }

    /**
     * Calculate operation score
     * @return float
     */
    public function calculateScore(): float {
        $score = 0.0;
        $analysis = $this->value;
        $primary = $analysis['primary_keyword'] ?? [];
        $secondary = $analysis['secondary_keywords'] ?? [];

        /**
         * Primary keyword found in any heading
         */
        if (!empty($primary) && $primary['found']) {
            $score += 0.4;
        }

        /**
         * Primary keyword found in H1
         */
        if (!empty($primary) && $primary['found_in_h1']) {
            $score += 0.3;
        }

        /**
         * At least one secondary keyword matched
         */
        $matchedSecondary = array_filter($secondary, static fn($s) => $s['found']);
        if (count($matchedSecondary) > 0) {
            $score += 0.2;
        }

        /**
         * Bonus for multiple secondary matches
         */
        if (count($matchedSecondary) > 1) {
            $score += 0.1;
        }

        return round(min($score, 1.0), 2);
    }

    /**
     * Return the operation's suggestions
     * @return Suggestion[]
     */
    public function suggestions(): array
    {
        $suggestions = [];

        $analysis = $this->value;
        /**
         * The primary keyword isn't found in any heading
         */
        if (empty($analysis['primary_keyword']['found'])) {
            $suggestions[] = Suggestion::KEYWORDS_MISSING_IN_HEADINGS;
        }

        /**
         * The primary keyword isn't found in H1
         */
        if (!empty($analysis['primary_keyword']['found']) && !$analysis['primary_keyword']['found_in_h1']) {
            $suggestions[] = Suggestion::PRIMARY_KEYWORD_MISSING_IN_HEADINGS;
        }

        /**
         * No secondary keywords present
         */
        $secondaryFound = array_filter($analysis['secondary_keywords'], static fn($item) => $item['found']);
        if (empty($secondaryFound)) {
            $suggestions[] = Suggestion::MISSING_SECONDARY_KEYWORDS;
            $suggestions[] = Suggestion::INSUFFICIENT_SECONDARY_KEYWORDS;
            $suggestions[] = Suggestion::KEYWORDS_MISSING_IN_HEADINGS;
        }

        /**
         * Only one secondary keyword found (could be improved)
         */
        if (count($secondaryFound) === 1) {
            $suggestions[] = Suggestion::INSUFFICIENT_SECONDARY_KEYWORDS;
        }

        return $suggestions;

    }
}
