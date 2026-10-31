<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\HeaderTagsStructure;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class HeaderHierarchyCheckOperation
 *
 * This class is responsible for checking the header hierarchy in a WordPress post.
 */
#[SeoMeta(
    name: 'Header Hierarchy Check',
    weight: WeightConfiguration::WEIGHT_HEADER_HIERARCHY_CHECK_OPERATION,
    description: 'Analyzes page headings to verify proper hierarchical structure, single H1 usage, and no missing levels. Computes a score based on nesting consistency and heading quality, offering guidance to improve SEO-friendly headers.',
)]
class HeaderHierarchyCheckOperation extends Operation implements OperationInterface
{
    /**
     * Function for running the operation
     * @return array|null
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        $content = $this->contentProvider->getContent($postId);

        if (empty($content)) {
            return [
                'success' => false,
                'message' => __('Unable to retrieve content', 'beyondseo'),
            ];
        }

        // Get clean content for word counting
        $cleanContent = $this->contentProvider->cleanContent($content);
        $totalWordCount = $this->contentProvider->getWordCount($cleanContent);
        $headingAnalysis = $this->contentProvider->analyzeHeadingStructure($content, $totalWordCount);

        return [
            'success' => true,
            'message' => __('Content formatting validated successfully', 'beyondseo'),
            'word_count' => $totalWordCount,
            'heading_analysis' => $headingAnalysis,
        ];


    }

    /**
     * Calculate operation score
     * @return float
     */
    public function calculateScore(): float {

        $analysis = $this->value['heading_analysis'];

        /**
         * Presence of a single <h1> (weight: 0.45)
         */
        $h1Count = $analysis['heading_counts']['h1'] ?? 0;
        if ($h1Count === 1) {
            $h1Score = 1.0;
        } elseif ($h1Count > 1) {
            $h1Score = 0.5;
        } else {
            $h1Score = 0.0;
        }

        /**
         * Proper heading structure (nesting) (weight: 0.30)
         */
        $hierarchyIssues = $analysis['hierarchy_issues'] ?? [];
        if (empty($hierarchyIssues)) {
            $nestingScore = 1.0;
        } elseif (count($hierarchyIssues) <= 2) {
            $nestingScore = 0.5;
        } else {
            $nestingScore = 0.0;
        }

        /**
         * No hierarchy issues based on document order (weight: 0.10)
         */
        $hierarchyIssuesCount = count($hierarchyIssues);
        if ($hierarchyIssuesCount === 0) {
            $missingScore = 1.0;
        } elseif ($hierarchyIssuesCount <= 2) {
            $missingScore = 0.5;
        } else {
            $missingScore = 0.0;
        }

        /**
         * Empty or very short headings (weight: 0.15)
         */
        $emptyHeadings = $analysis['empty_headings'] ?? [];
        if (empty($emptyHeadings)) {
            $emptyScore = 1.0;
        } elseif (count($emptyHeadings) <= 2) {
            $emptyScore = 0.5;
        } else {
            $emptyScore = 0.0;
        }

        /**
         * Calculate the final score
         */
        return round(
            ($h1Score * 0.45) +
            ($nestingScore * 0.30) +
            ($missingScore * 0.10) +
            ($emptyScore * 0.15),
            2
        );
    }

    /**
     * Return the operation's suggestions
     * @return Suggestion[]
     */
    public function suggestions(): array
    {
        $suggestions = [];

        $analysis = $this->value['heading_analysis'];
        /**
         * Presence of a single <h1>
         */
        $h1Count = $analysis['heading_counts']['h1'] ?? 0;
        if ($h1Count === 0) {
            $suggestions[] = Suggestion::MISSING_H1_TAG;
        } elseif ($h1Count > 1) {
            $suggestions[] = Suggestion::MULTIPLE_H1_TAGS_FOUND;
        }

        /**
         * Proper nesting
         */
        if (!empty($analysis['hierarchy_issues'])) {
            $suggestions[] = Suggestion::IMPROPER_HEADING_NESTING;
        }

        /**
         * No missing headings
         */
        if ($analysis['has_too_few_headings'] ?? false) {
            $suggestions[] = Suggestion::INSUFFICIENT_HEADINGS;
        }

        /**
         * No empty or malformed headings
         */
        if (!empty($analysis['empty_headings'])) {
            $suggestions[] = Suggestion::EMPTY_OR_SHORT_HEADINGS;
        }

        return $suggestions;
    }
}