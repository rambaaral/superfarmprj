<?php
/** @noinspection PhpUnnecessaryCurlyVarSyntaxInspection */
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
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class AudienceTargetedAdjustmentsOperation
 *
 * Analyzes content reading level and tone compared to target audience requirements,
 * then provides recommendations for adjustments to better match the audience.
 * Helps create content that is appropriately tailored for specific education levels,
 * industries, and technical proficiency levels.
 */
#[SeoMeta(
    name: 'Audience Targeted Adjustments',
    weight: WeightConfiguration::WEIGHT_AUDIENCE_TARGETED_ADJUSTMENTS_OPERATION,
    description: 'Analyzes content readability and tone to ensure it matches the target audience`s expectations. Provides recommendations for adjustments to improve audience targeting.',
)]
class AudienceTargetedAdjustmentsOperation extends Operation implements OperationInterface
{
    /**
     * Performs audience-targeted adjustments analysis on the content of a post.
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
                'message' => __('Content is too short for meaningful audience targeting analysis', 'beyondseo'),
                'readability_metrics' => ['word_count' => str_word_count($cleanContent)],
                'audience_match' => []
            ];
        }

        // Get target audience settings from post-meta
        $targetAudience = $this->contentProvider->getTargetAudienceSettings($postId);

        // Get readability metrics and tone analysis
        $readabilityMetrics = $this->contentProvider->analyzeReadabilityMetrics($content, $language);
        $toneAnalysis = $this->contentProvider->analyzeTone($cleanContent);

        // Compare with target audience requirements
        $audienceMatch = $this->contentProvider->compareWithTargetAudience($readabilityMetrics, $toneAnalysis, $targetAudience);

        // Generate recommendations
        $recommendations = $this->generateRecommendations($audienceMatch, $readabilityMetrics);

        // Calculate overall match score
        $matchScore = $this->calculateAudienceMatchScore($audienceMatch);

        // Store the results
        return [
            'success' => true,
            'message' => __('Audience targeting analysis completed successfully', 'beyondseo'),
            'readability_metrics' => $readabilityMetrics,
            'tone_analysis' => $toneAnalysis,
            'target_audience' => $targetAudience,
            'audience_match' => $audienceMatch,
            'match_score' => $matchScore,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Evaluates the operation value based on audience targeting match.
     *
     * @return float A score based on how well the content matches target audience requirements (0-1 scale)
     */
    public function calculateScore(): float
    {
        // Get the audience match score from the analysis
        return $this->value['match_score'] ?? 0;
    }

    /**
     * Provides suggestions for improving audience targeting in content.
     *
     * @return array An array of suggestion issue types
     */
    public function suggestions(): array
    {
        $suggestions = [];
        $factorData = $this->value;

        if (empty($factorData) || empty($factorData['audience_match'])) {
            return $suggestions;
        }

        $audienceMatch = $factorData['audience_match'];
        $matchScore = $factorData['match_score'] ?? 0;

        // If the overall match is poor, suggest general content depth improvements
        if ($matchScore < 0.5) {
            $suggestions[] = Suggestion::LACKS_CONTENT_DEPTH;
        }

        // Reading level specific suggestions
        if (isset($audienceMatch['reading_level_match']) && $audienceMatch['reading_level_match']['score'] < 0.6) {
            $readingLevelSuggestion = $audienceMatch['reading_level_match']['too_complex']
                ? Suggestion::PARAGRAPHS_TOO_LONG
                : Suggestion::LACKS_CONTENT_DEPTH;

            if (!in_array($readingLevelSuggestion, $suggestions)) {
                $suggestions[] = $readingLevelSuggestion;
            }
        }

        // Tone specific suggestions
        if (isset($audienceMatch['tone_match']) && $audienceMatch['tone_match']['score'] < 0.6) {
            $suggestions[] = Suggestion::LACKS_CONTENT_DEPTH;
        }

        // Technical vocabulary suggestions
        if (isset($audienceMatch['technical_match']) && $audienceMatch['technical_match']['score'] < 0.6) {
            $suggestions[] = Suggestion::LACKS_CONTENT_DEPTH;
        }

        return $suggestions;
    }

    /**
     * Calculates an overall audience match score based on multiple factors.
     * Weights reading level most heavily, followed by tone and technical match.
     *
     * @param array $audienceMatch Audience match results
     * @return float Overall match score (0-1)
     */
    private function calculateAudienceMatchScore(array $audienceMatch): float
    {
        $readingLevelScore = $audienceMatch['reading_level_match']['score'] ?? 0;
        $toneScore = $audienceMatch['tone_match']['score'] ?? 0;
        $technicalScore = $audienceMatch['technical_match']['score'] ?? 0;

        // Weighted average of the scores (reading level is most important)
        $weightedScore = ($readingLevelScore * 0.5) + ($toneScore * 0.3) + ($technicalScore * 0.2);

        return round($weightedScore, 2);
    }

    /**
     * Generates specific recommendations based on audience match analysis.
     * Creates actionable suggestions for improving content to better target the audience.
     *
     * @param array $audienceMatch Audience match results
     * @param array $readabilityMetrics Content readability metrics
     * @return array Specific recommendations for improvement
     */
    private function generateRecommendations(array $audienceMatch, array $readabilityMetrics): array
    {
        $recommendations = [];

        // Reading level recommendations
        $readingLevelMatch = $audienceMatch['reading_level_match'];
        if ($readingLevelMatch['score'] < 0.7) {
            if ($readingLevelMatch['too_complex']) {
                $recommendations[] = [
                    'type' => 'reading_level',
                    'action' => 'simplify',
                    'importance' => 'high',
                    'description' => __('Simplify the language to better match your target audience\'s education level.', 'beyondseo'),
                    'details' => [
                        __('Reduce sentence length (aim for an average of 15-20 words per sentence)', 'beyondseo'),
                        __('Use simpler vocabulary where possible', 'beyondseo'),
                        __('Break down complex concepts into smaller, digestible points', 'beyondseo'),
                        __('Consider using more bullet points for complex information', 'beyondseo')
                    ]
                ];
            } elseif ($readingLevelMatch['too_simple']) {
                $recommendations[] = [
                    'type' => 'reading_level',
                    'action' => 'elevate',
                    'importance' => 'medium',
                    'description' => __('Elevate the language to better match your target audience\'s education level.', 'beyondseo'),
                    'details' => [
                        __('Use more precise terminology', 'beyondseo'),
                        __('Provide more technical details where appropriate', 'beyondseo'),
                        __('Develop ideas more thoroughly with supporting evidence', 'beyondseo'),
                        __('Include more complex analysis of concepts', 'beyondseo')
                    ]
                ];
            }
        }

        // Tone recommendations
        $toneMatch = $audienceMatch['tone_match'];
        if ($toneMatch['score'] < 0.7) {
            $missingTones = array_diff($toneMatch['preferred_tones'], $toneMatch['matching_tones']);

            if (!empty($missingTones)) {
                $toneRecommendation = [
                    'type' => 'tone',
                    'action' => 'adjust',
                    'importance' => 'medium',
                    'description' => __('Adjust your content tone to better match industry expectations.', 'beyondseo'),
                    'details' => []
                ];

                // Create specific suggestions for each missing tone
                foreach ($missingTones as $tone) {
                    switch ($tone) {
                        case 'professional':
                            $toneRecommendation['details'][] = __('Use more formal language and avoid casual expressions', 'beyondseo');
                            $toneRecommendation['details'][] = __('Focus on facts and evidence rather than opinions', 'beyondseo');
                            $toneRecommendation['details'][] = __('Avoid first-person pronouns (I, we) when possible', 'beyondseo');
                            break;

                        case 'casual':
                            $toneRecommendation['details'][] = __('Use more conversational language', 'beyondseo');
                            $toneRecommendation['details'][] = __('Incorporate personal pronouns (you, we) to engage readers', 'beyondseo');
                            $toneRecommendation['details'][] = __('Consider using questions to engage readers directly', 'beyondseo');
                            break;

                        case 'technical':
                            $toneRecommendation['details'][] = __('Incorporate more industry-specific terminology', 'beyondseo');
                            $toneRecommendation['details'][] = __('Provide more specific technical details', 'beyondseo');
                            $toneRecommendation['details'][] = __('Include data points and technical examples', 'beyondseo');
                            break;

                        case 'empathetic':
                            $toneRecommendation['details'][] = __('Acknowledge reader challenges or pain points', 'beyondseo');
                            $toneRecommendation['details'][] = __('Use supportive language that shows understanding', 'beyondseo');
                            $toneRecommendation['details'][] = __('Share relevant examples that readers can relate to', 'beyondseo');
                            break;

                        case 'authoritative':
                            $toneRecommendation['details'][] = __('Back claims with evidence, research or expert quotes', 'beyondseo');
                            $toneRecommendation['details'][] = __('Use confident, definitive language', 'beyondseo');
                            $toneRecommendation['details'][] = __('Provide clear, unambiguous guidance', 'beyondseo');
                            break;

                        case 'persuasive':
                            $toneRecommendation['details'][] = __('Emphasize benefits and value propositions', 'beyondseo');
                            $toneRecommendation['details'][] = __('Address and overcome potential objections', 'beyondseo');
                            $toneRecommendation['details'][] = __('Include calls-to-action throughout the content', 'beyondseo');
                            break;

                        case 'clear':
                            $toneRecommendation['details'][] = __('Use more straightforward explanations', 'beyondseo');
                            $toneRecommendation['details'][] = __('Avoid jargon or complex terminology without explanation', 'beyondseo');
                            $toneRecommendation['details'][] = __('Include examples to illustrate complex concepts', 'beyondseo');
                            break;

                        default:
                            /* translators: %s is the tone type (e.g., formal, casual, professional) */
                            $toneRecommendation['details'][] = sprintf(__('Adjust tone to be more %s', 'beyondseo'), $tone);
                    }
                }

                $recommendations[] = $toneRecommendation;
            }
        }

        // Technical level recommendations
        $technicalMatch = $audienceMatch['technical_match'];
        if ($technicalMatch['score'] < 0.7) {
            if ($technicalMatch['too_technical']) {
                $recommendations[] = [
                    'type' => 'technical_level',
                    'action' => 'simplify',
                    'importance' => 'medium',
                    'description' => __('Reduce the technical complexity to better match your audience\'s technical proficiency.', 'beyondseo'),
                    'details' => [
                        __('Define technical terms when first introduced', 'beyondseo'),
                        __('Replace specialized jargon with more widely understood terms', 'beyondseo'),
                        __('Provide more background context for complex concepts', 'beyondseo'),
                        __('Use analogies to explain technical concepts', 'beyondseo'),
                        __('Include visual aids to help explain complex ideas', 'beyondseo')
                    ]
                ];
            } elseif ($technicalMatch['not_technical_enough']) {
                $recommendations[] = [
                    'type' => 'technical_level',
                    'action' => 'enhance',
                    'importance' => 'medium',
                    'description' => __('Enhance the technical depth to better match your audience\'s technical proficiency.', 'beyondseo'),
                    'details' => [
                        __('Include more specific technical details and terminology', 'beyondseo'),
                        __('Provide deeper explanations of processes or concepts', 'beyondseo'),
                        __('Reference industry standards or best practices', 'beyondseo'),
                        __('Include more data-driven insights', 'beyondseo'),
                        __('Add technical examples or case studies', 'beyondseo')
                    ]
                ];
            }
        }

        // If content has very few words, recommend expanding it
        $wordCount = $readabilityMetrics['word_count'] ?? 0;
        if ($wordCount < 300) {
            $recommendations[] = [
                'type' => 'content_length',
                'action' => 'expand',
                'importance' => 'high',
                'description' => __('Expand your content to provide more value to your target audience.', 'beyondseo'),
                'details' => [
                    __('Add more detailed explanations', 'beyondseo'),
                    __('Include examples relevant to your audience', 'beyondseo'),
                    __('Address common questions your audience might have', 'beyondseo'),
                    __('Provide more comprehensive coverage of the topic', 'beyondseo')
                ]
            ];
        }

        return $recommendations;
    }
}