<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\ContentReadability;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Models\Configs\SeoOptimiserConfig;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class ReadabilityScoreValidationOperation
 *
 * This class analyzes content readability and provides actionable improvement suggestions.
 * It evaluates multiple readability metrics including Flesch-Kincaid scores, sentence structure,
 * paragraph length, and writing complexity to generate an overall readability rating.
 */
#[SeoMeta(
    name: 'Readability Score Validation',
    weight: WeightConfiguration::WEIGHT_READABILITY_SCORE_VALIDATION_OPERATION,
    description: 'Analyzes content readability and provides suggestions for improvement based on multiple metrics.',
)]
class ReadabilityScoreValidationOperation extends Operation implements OperationInterface
{
    /**
     * Performs readability analysis on the content of a post.
     *
     * @return array|null Analysis results or null if the post-ID is invalid
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get content for analysis
        $content = $this->contentProvider->getContent($postId, true);

        // Clean the content for text analysis
        $cleanContent = $this->contentProvider->cleanContent($content);

        // Get the language from locale
        $language = $this->contentProvider->getLanguageFromLocale();

        // Skip analysis if the content is too short
        if (str_word_count($cleanContent) < 50) {
            return [
                'success' => false,
                'message' => __('Content is too short for meaningful readability analysis', 'beyondseo'),
                'readability_score' => 0,
                'readability_metrics' => ['word_count' => str_word_count($cleanContent)],
                'improvement_areas' => []
            ];
        }

        // Perform readability analysis
        $readabilityMetrics = $this->contentProvider->analyzeReadabilityMetrics($content, $language);

        // Calculate overall readability score
        $readabilityScore = $this->calculateOverallReadabilityScore($readabilityMetrics);

        // Identify areas for improvement
        $improvementAreas = $this->identifyImprovementAreas($readabilityMetrics);

        // Store the results
        return [
            'success' => true,
            'message' => __('Readability analysis completed successfully', 'beyondseo'),
            'readability_score' => $readabilityScore,
            'readability_metrics' => $readabilityMetrics,
            'improvement_areas' => $improvementAreas
        ];
    }

    /**
     * Evaluates the operation value based on readability analysis.
     *
     * @return float A score based on the readability analysis (0-1 scale)
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;
        // Get the readability score from the analysis (0-100 scale)
        $readabilityScore = $factorData['readability_score'] ?? 0;

        // Convert from 0-100 scale to 0-1 scale for our scoring system
        return $readabilityScore / 100;
    }

    /**
     * Get suggestions for improving readability based on the analysis results.
     *
     * @return array An array of suggestion issue types
     */
    public function suggestions(): array
    {
        $suggestions = [];
        $factorData = $this->value;

        $readabilityScore = $factorData['readability_score'] ?? 0;
        $improvementAreas = $factorData['improvement_areas'] ?? [];
        $metrics = $factorData['readability_metrics'] ?? [];

        // If overall readability is poor, suggest improving it
        if ($readabilityScore < 60) {
            $suggestions[] = Suggestion::LACKS_CONTENT_DEPTH;
        }

        // Look for specific issues in improvement areas
        foreach ($improvementAreas as $area) {
            $type = $area['type'] ?? '';

            switch ($type) {
                case 'long_sentences':
                case 'long_paragraphs':
                case 'high_avg_sentence_length':
                    $suggestions[] = Suggestion::PARAGRAPHS_TOO_LONG;
                    break;

                case 'low_readability_score':
                case 'complex_words':
                case 'passive_voice':
                    $suggestions[] = Suggestion::LACKS_CONTENT_DEPTH;
                    break;

                case 'insufficient_transition_words':
                    $suggestions[] = Suggestion::INSUFFICIENT_TRANSITION_WORDS;
                    break;

            }
        }

        // Check if Flesch-Kincaid grade level is too high (challenging content)
        if (isset($metrics['flesch_kincaid_grade_level']) &&
            str_contains($metrics['flesch_kincaid_grade_level'], 'College')) {
            $suggestions[] = Suggestion::LACKS_CONTENT_DEPTH;
        }

        // Check if paragraphs are properly structured
        if (($metrics['paragraph_analysis']['avg_words_per_paragraph'] ?? 0) > 100) {
            $suggestions[] = Suggestion::PARAGRAPHS_TOO_LONG;
        }

        // Remove duplicates
        return $suggestions;
    }

    /**
     * Calculates an overall readability score based on multiple metrics.
     *
     * @param array $metrics Readability metrics
     * @return float Overall readability score (0-100)
     */
    private function calculateOverallReadabilityScore(array $metrics): float
    {
        // Extract key metrics
        $fleschScore = $metrics['flesch_kincaid_score'] ?? 0;
        $smogIndex = $metrics['smog_index'] ?? 0;
        $colemanLiauIndex = $metrics['coleman_liau_index'] ?? 0;
        $avgWordsPerSentence = $metrics['avg_words_per_sentence'] ?? 0;
        $complexWordsPercentage = $metrics['complex_words_percentage'] ?? 0;

        // Normalize SMOG Index to 0-100 (where lower grade level = higher score)
        $smogScore = max(0, min(100, 100 - (($smogIndex - 5) * 10)));

        // Normalize Coleman-Liau to 0-100 (where lower grade level = higher score)
        $colemanLiauScore = max(0, min(100, 100 - (($colemanLiauIndex - 5) * 10)));

        // Sentence length score (optimal: 14-18 words)

        if ($avgWordsPerSentence < 10) {
            $sentenceLengthScore = 70 + ($avgWordsPerSentence * 3); // Short sentences, increasing score
        } elseif ($avgWordsPerSentence <= 18) {
            $sentenceLengthScore = 100; // Optimal range
        } elseif ($avgWordsPerSentence <= 25) {
            $sentenceLengthScore = 100 - (($avgWordsPerSentence - 18) * 5); // Longer sentences, decreasing score
        } else {
            $sentenceLengthScore = 65 - (($avgWordsPerSentence - 25) * 3); // Very long sentences, low score
        }
        $sentenceLengthScore = max(0, min(100, $sentenceLengthScore));

        // Complex words score (optimal: <10%)
        $complexWordsScore = max(0, min(100, 100 - ($complexWordsPercentage * 5)));

        // Calculate the overall score with weights
        $overallScore = (
            ($fleschScore * 0.35) +           // 35% weight to Flesch score
            ($smogScore * 0.15) +             // 15% weight to SMOG
            ($colemanLiauScore * 0.15) +      // 15% weight to Coleman-Liau
            ($sentenceLengthScore * 0.20) +   // 20% weight to sentence length
            ($complexWordsScore * 0.15)       // 15% weight to complex words
        );

        return round($overallScore, 2);
    }

    /**
     * Identifies areas for improvement based on readability analysis.
     *
     * @param array $metrics Readability metrics
     * @return array Areas that need improvement
     */
    private function identifyImprovementAreas(array $metrics): array
    {
        $areas = [];

        // Check sentence length distribution
        if (isset($metrics['sentence_length_distribution'])) {
            $distribution = $metrics['sentence_length_distribution'];
            $longSentencesPercentage =
                ($distribution['percentages']['very_long'] ?? 0) +
                ($distribution['percentages']['extremely_long'] ?? 0);

            if ($longSentencesPercentage > 20) {
                $areas[] = [
                    'type' => 'long_sentences',
                    'severity' => 'high',
                    /* translators: %s is the percentage of long sentences */
                    'message' => sprintf(__('Too many long sentences. %s%% of your sentences are longer than 20 words.', 'beyondseo'), round($longSentencesPercentage, 2)),
                    'examples' => $distribution['long_sentences'] ?? []
                ];
            }
        }

        // Check average words per sentence
        if (($metrics['avg_words_per_sentence'] ?? 0) > SeoOptimiserConfig::SENTENCE_LENGTH_THRESHOLDS['acceptable_max']) {
            $areas[] = [
                'type' => 'high_avg_sentence_length',
                'severity' => 'medium',
                /* translators: %1$s is the current average sentence length, %2$s is the recommended maximum */
                'message' => sprintf(__('Your average sentence length (%1$s words) is above the recommended maximum (%2$s words).', 'beyondseo'), $metrics['avg_words_per_sentence'], SeoOptimiserConfig::SENTENCE_LENGTH_THRESHOLDS['acceptable_max']),
                'recommendation' => __('Break longer sentences into smaller ones to improve readability.', 'beyondseo')
            ];
        }

        // Check complex words percentage
        if (($metrics['complex_words_percentage'] ?? 0) > SeoOptimiserConfig::COMPLEX_WORDS_THRESHOLD) {
            $areas[] = [
                'type' => 'complex_words',
                'severity' => 'medium',
                /* translators: %s is the percentage of complex words */
                'message' => sprintf(__('Your content contains too many complex words (%s%% of total words).', 'beyondseo'), $metrics['complex_words_percentage']),
                'recommendation' => __('Replace complex words with simpler alternatives to improve readability.', 'beyondseo')
            ];
        }

        // Check paragraph structure
        if (isset($metrics['paragraph_analysis'])) {
            $analysis = $metrics['paragraph_analysis'];

            // Check long paragraphs
            if (($analysis['long_paragraphs_count'] ?? 0) > 0) {
                $longParagraphPercentage = $analysis['paragraph_count'] > 0
                    ? ($analysis['long_paragraphs_count'] / $analysis['paragraph_count']) * 100
                    : 0;

                if ($longParagraphPercentage > 20) {
                    $areas[] = [
                        'type' => 'long_paragraphs',
                        'severity' => 'medium',
                        /* translators: %s is the number of long paragraphs */
                        'message' => sprintf(__('Too many long paragraphs. %s paragraphs have more than 100 words.', 'beyondseo'), $analysis['long_paragraphs_count']),
                        'examples' => $analysis['long_paragraphs_examples'] ?? [],
                        'recommendation' => __('Break longer paragraphs into smaller ones of 2-3 sentences each.', 'beyondseo')
                    ];
                }
            }
        }

        // Check passive voice usage
        if (isset($metrics['passive_voice_analysis']) && ($metrics['passive_voice_analysis']['exceeds_threshold'] ?? false)) {
            $areas[] = [
                'type' => 'passive_voice',
                'severity' => 'low',
                /* translators: %s is the percentage of sentences using passive voice */
                'message' => sprintf(__('Your content uses too much passive voice (%s%% of sentences).', 'beyondseo'), $metrics['passive_voice_analysis']['passive_sentences_percentage']),
                'examples' => $metrics['passive_voice_analysis']['passive_sentences_examples'] ?? [],
                'recommendation' => __('Use active voice for more engaging and clearer content.', 'beyondseo')
            ];
        }

        // Check transition words usage
        if (isset($metrics['transition_words_analysis']) && !($metrics['transition_words_analysis']['meets_threshold'] ?? true)) {
            $areas[] = [
                'type' => 'insufficient_transition_words',
                'severity' => 'medium',
                /* translators: %s is the percentage of sentences containing transition words */
                'message' => sprintf(__('Your content could use more transition words. Only %s%% of sentences contain transition words.', 'beyondseo'), $metrics['transition_words_analysis']['sentences_with_transitions_percentage']),
                'recommendation' => __('Add transition words like "however," "furthermore," or "consequently" to improve flow.', 'beyondseo')
            ];
        }

        // Check the overall readability score
        $fleschScore = $metrics['flesch_kincaid_score'] ?? 0;
        if ($fleschScore < SeoOptimiserConfig::FLESCH_READING_EASE_THRESHOLDS['standard']) {
            $severity = 'medium';

            if ($fleschScore < SeoOptimiserConfig::FLESCH_READING_EASE_THRESHOLDS['difficult']) {
                $severity = 'high';
            }

            $areas[] = [
                'type' => 'low_readability_score',
                'severity' => $severity,
                /* translators: %s is the readability score */
                'message' => sprintf(__('Your content\'s readability score (%s) indicates it may be difficult to read for the average person.', 'beyondseo'), $fleschScore),
                'recommendation' => __('Simplify language, shorten sentences, and reduce complex terminology.', 'beyondseo')
            ];
        }

        return $areas;
    }
}