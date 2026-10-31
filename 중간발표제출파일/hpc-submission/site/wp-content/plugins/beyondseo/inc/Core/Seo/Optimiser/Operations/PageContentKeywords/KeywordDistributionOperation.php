<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\PageContentKeywords;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;
use Throwable;

/**
 * Class KeywordDistributionOperation
 *
 * This class is responsible for analyzing the distribution of keywords across the content of a webpage.
 */
#[SeoMeta(
    name: 'Keyword Distribution',
    weight: WeightConfiguration::WEIGHT_KEYWORD_DISTRIBUTION_OPERATION,
    description: 'Assesses how keywords are spread throughout page headings, paragraphs, and meta data. Identifies imbalances like concentration in specific sections and recommends a more even distribution for improved relevance.',
)]
class KeywordDistributionOperation extends Operation implements OperationInterface
{
    /**
     * Performs keyword distribution analysis across page content.
     *
     * @return array|null The analysis results
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get the primary keyword
        $primaryKeyword = $this->contentProvider->getPrimaryKeyword($postId);

        // Get secondary keywords
        $secondaryKeywords = $this->contentProvider->getSecondaryKeywords($postId);

        // Combine for total keywords
        $allKeywords = array_merge([$primaryKeyword], $secondaryKeywords);
        $allKeywords = array_filter($allKeywords); // Remove empty values

        // Check if keywords are available
        if (empty($allKeywords)) {
            return [
                'success' => false,
                'message' => __('No keywords found for analysis', 'beyondseo'),
                'keyword_distribution_analysis' => []
            ];
        }

        // Get the content
        try {
            // Try to get the rendered content first (as it would appear in browser)
            $content = $this->contentProvider->getContent($postId, true);

            // Clean the content for text analysis
            $cleanContent = $this->contentProvider->cleanContent($content);

            // If still no content, return an error
            if (empty($cleanContent)) {
                return [
                    'success' => false,
                    'message' => __('No content found for analysis', 'beyondseo'),
                    'keyword_distribution_analysis' => []
                ];
            }
        } catch (Throwable $e) {
            return [
                'success' => false,
                /* translators: %s is the error message */
                'message' => sprintf(__('Error fetching content: %s', 'beyondseo'), $e->getMessage()),
                'keyword_distribution_analysis' => []
            ];
        }

        // Analyze title
        $titleAnalysis = $this->contentProvider->analyzeTitleKeywordUsage($primaryKeyword, $postId);

        // Analyze headings
        $headingsAnalysis = $this->contentProvider->analyzeHeadingsKeywordUsage($allKeywords, $content);

        // Analyze the first paragraph
        $firstParagraphAnalysis = $this->contentProvider->analyzeFirstParagraphKeywordUsage($primaryKeyword, $content);

        // Analyze keyword distribution throughout content
        $distributionAnalysis = $this->contentProvider->keywordsDistributionAnalyse($allKeywords, $cleanContent);

        // Analyze content sections (introduction, body, conclusion)
        $sectionAnalysis = $this->contentProvider->analyzeContentSectionsKeywordUsage($allKeywords, $content);

        // Prepare the results
        return [
            'success' => true,
            'message' => __('Keyword distribution analysis completed', 'beyondseo'),
            'keyword_distribution_analysis' => [
                'primary_keyword' => $primaryKeyword,
                'secondary_keywords' => $secondaryKeywords,
                'title_analysis' => $titleAnalysis,
                'headings_analysis' => $headingsAnalysis,
                'first_paragraph_analysis' => $firstParagraphAnalysis,
                'distribution_analysis' => $distributionAnalysis,
                'section_analysis' => $sectionAnalysis
            ]
        ];
    }

    /**
     * Score the operation based on the analysis results.
     *
     * @return float The score from 0 to 1
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;

        // Extract analysis data
        $analysis = $factorData['keyword_distribution_analysis'] ?? [];

        // If no analysis data, return 0
        if (empty($analysis)) {
            return 0;
        }

        // Extract individual scores from the analysis data
        $titleScore = isset($analysis['title_analysis']['has_primary_keyword']) &&
                     $analysis['title_analysis']['has_primary_keyword'] ? 1 : 0;

        $headingsScore = $analysis['headings_analysis']['coverage_score'] ?? 0;

        $firstParagraphScore = isset($analysis['first_paragraph_analysis']['has_primary_keyword']) &&
                              $analysis['first_paragraph_analysis']['has_primary_keyword'] ? 1 : 0;

        $distributionScore = $analysis['distribution_analysis']['distribution_score'] ?? 0;

        $sectionScore = $analysis['section_analysis']['section_coverage_score'] ?? 0;

        // Weight each component according to SEO importance
        $weights = [
            'title' => 0.25,
            'headings' => 0.20,
            'first_paragraph' => 0.15,
            'distribution' => 0.25,
            'sections' => 0.15
        ];

        // Calculate weighted score
        $overallScore =
            ($titleScore * $weights['title']) +
            ($headingsScore * $weights['headings']) +
            ($firstParagraphScore * $weights['first_paragraph']) +
            ($distributionScore * $weights['distribution']) +
            ($sectionScore * $weights['sections']);

        // Apply penalty if critical thresholds are not met
        if (isset($analysis['title_analysis']['meets_threshold']) &&
            !$analysis['title_analysis']['meets_threshold']) {
            $overallScore *= 0.8; // 20% penalty for missing title keyword
        }

        if (isset($analysis['first_paragraph_analysis']['meets_threshold']) &&
            !$analysis['first_paragraph_analysis']['meets_threshold']) {
            $overallScore *= 0.9; // 10% penalty for missing the first paragraph keyword
        }

        // Ensure the score is between 0 and 1
        return max(0, min(1, $overallScore));
    }

    /**
     * Generate suggestions based on the keyword distribution analysis
     *
     * @return array An array of suggestions based on the factor data
     * @noinspection PhpFunctionCyclomaticComplexityInspection
     */
    public function suggestions(): array
    {
        $activeSuggestions = []; // Will hold all identified issue types

        $factorData = $this->value;

        // Extract analysis data
        $analysis = $factorData['keyword_distribution_analysis'] ?? [];
        if (empty($analysis)) {
            return $activeSuggestions;
        }

        // Check if primary keyword is in title
        $titleAnalysis = $analysis['title_analysis'] ?? [];
        if (isset($titleAnalysis['has_primary_keyword']) && !$titleAnalysis['has_primary_keyword']) {
            $activeSuggestions[] = Suggestion::KEYWORD_MISSING_IN_POST_TITLE;
        } elseif (isset($titleAnalysis['meets_threshold']) && !$titleAnalysis['meets_threshold']) {
            // The title contains the keyword but doesn't meet the quality threshold (0.8)
            $activeSuggestions[] = Suggestion::KEYWORD_MISSING_IN_META_TITLE;
        }

        // Check if keywords are present in headings
        $headingsAnalysis = $analysis['headings_analysis'] ?? [];
        if (isset($headingsAnalysis['coverage_score']) && $headingsAnalysis['coverage_score'] < 0.5) {
            $activeSuggestions[] = Suggestion::KEYWORDS_MISSING_IN_HEADINGS;
        }

        // Check if the primary keyword is in the first paragraph
        $firstParagraphAnalysis = $analysis['first_paragraph_analysis'] ?? [];
        if (isset($firstParagraphAnalysis['has_primary_keyword']) && !$firstParagraphAnalysis['has_primary_keyword']) {
            $activeSuggestions[] = Suggestion::KEYWORD_MISSING_IN_FIRST_PARAGRAPH;
        } elseif (isset($firstParagraphAnalysis['meets_threshold']) && !$firstParagraphAnalysis['meets_threshold']) {
            // The first paragraph contains the keyword but doesn't meet the quality threshold (0.7)
            $activeSuggestions[] = Suggestion::KEYWORD_MISSING_IN_FIRST_PARAGRAPH;
        }

        // Check overall keyword distribution throughout content
        $distributionAnalysis = $analysis['distribution_analysis'] ?? [];
        if (isset($distributionAnalysis['distribution_score']) && $distributionAnalysis['distribution_score'] < 0.4) {
            $activeSuggestions[] = Suggestion::POOR_KEYWORD_DISTRIBUTION;
        }

        // Check the balance of keywords across different content sections
        $sectionAnalysis = $analysis['section_analysis'] ?? [];
        if (isset($sectionAnalysis['section_coverage_score']) && $sectionAnalysis['section_coverage_score'] < 0.5) {
            $activeSuggestions[] = Suggestion::UNBALANCED_KEYWORD_DISTRIBUTION
            ;
        }

        return $activeSuggestions;
    }
}