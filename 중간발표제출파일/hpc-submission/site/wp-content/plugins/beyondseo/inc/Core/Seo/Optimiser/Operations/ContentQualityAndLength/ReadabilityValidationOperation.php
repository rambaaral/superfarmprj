<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Operations\ContentQualityAndLength;

if (!defined('ABSPATH')) { exit; }

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Attributes\SeoMeta;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration\WeightConfiguration;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums\Suggestion;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Interfaces\OperationInterface;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Models\Configs\SeoOptimiserConfig;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;

/**
 * Class ReadabilityValidationOperation
 *
 * This class analyzes content readability using multiple metrics including Flesch-Kincaid
 * readability scores, sentence complexity, paragraph structure, passive voice usage,
 * and transition word frequency to determine how easy content is to read and understand.
 */
#[SeoMeta(
    name: 'Readability Validation',
    weight: WeightConfiguration::WEIGHT_READABILITY_VALIDATION_OPERATION,
    description: 'Analyzes content readability using Flesch-Kincaid scores, sentence complexity, paragraph structure, passive voice usage, and transition word frequency. Provides detailed metrics and suggestions for improving content readability.',
)]
class ReadabilityValidationOperation extends Operation implements OperationInterface
{
    /**
     * Performs readability analysis for the given post-ID.
     *
     * @return array|null The check results or null if the post-ID is invalid.
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get content
        $content = $this->contentProvider->getContent($postId);
        if (empty($content)) {
            return [
                'success' => false,
                'message' => __('No content found for this post', 'beyondseo')
            ];
        }

        // Clean the content for analysis
        $cleanText = $this->contentProvider->cleanContent($content);

        // Get language from locale
        $locale = $this->contentProvider->getLocale();
        $language = $this->contentProvider->getLanguageFromLocale($locale);

        // Get word count
        $wordCount = $this->contentProvider->getWordCount($cleanText);

        // Get content structure analysis
        $contentStructure = $this->contentProvider->analyzeContentStructure($content, $wordCount);

        // Perform readability analysis
        $readabilityScores = $this->analyzeReadability($cleanText, $content, $language, $contentStructure);

        // Prepare response data
        return [
            'success' => true,
            'message' => __('Readability analysis completed successfully', 'beyondseo'),
            'language' => $language,
            'word_count' => $wordCount,
            'content_structure' => $contentStructure,
            'readability_scores' => $readabilityScores,
        ];
    }

    /**
     * Analyzes the readability of content using various metrics.
     *
     * @param string $cleanText The cleaned text content
     * @param string $rawContent The raw HTML content
     * @param string $language The content language
     * @param array $contentStructure Content structure information from the content provider
     * @return array An array of readability analysis results
     */
    private function analyzeReadability(string $cleanText, string $rawContent, string $language, array $contentStructure = []): array
    {
        // Extract sentences
        $sentences = $this->extractSentences($cleanText);
        $sentenceCount = count($sentences);

        // Extract paragraphs
        $paragraphs = $this->extractParagraphs($rawContent);
        $paragraphCount = count($paragraphs);

        // Get word count
        $wordCount = $this->contentProvider->getWordCount($cleanText);

        // Syllable count
        $syllableCount = $this->countSyllables($cleanText, $language);

        // Calculate average sentence length
        $avgSentenceLength = $sentenceCount > 0 ? $wordCount / $sentenceCount : 0;

        // Use content structure data for paragraph metrics if available
        $avgSentencesPerParagraph = $contentStructure['avg_paragraph_length'] ?? 0;
        $avgWordsPerParagraph = $contentStructure['avg_paragraph_length'] ?? 0;

        if ($avgSentencesPerParagraph === 0 && $paragraphCount > 0) {
            // Calculate our own metrics if not available
            $sentencesPerParagraph = [];
            $wordsPerParagraph = [];

            foreach ($paragraphs as $paragraph) {
                $cleanParagraph = wp_strip_all_tags($paragraph);
                $paragraphSentences = $this->extractSentences($cleanParagraph);
                $paragraphWords = $this->contentProvider->getWordCount($cleanParagraph);

                $sentencesPerParagraph[] = count($paragraphSentences);
                $wordsPerParagraph[] = $paragraphWords;
            }

            $avgSentencesPerParagraph = $paragraphCount > 0 ? array_sum($sentencesPerParagraph) / $paragraphCount : 0;
            $avgWordsPerParagraph = $paragraphCount > 0 ? array_sum($wordsPerParagraph) / $paragraphCount : 0;
        }

        // Calculate Flesch Reading Ease score
        $fleschReadingEase = $this->calculateFleschReadingEase($wordCount, $sentenceCount, $syllableCount);

        // Calculate Flesch-Kincaid Grade Level
        $fleschKincaidGradeLevel = $this->calculateFleschKincaidGradeLevel($wordCount, $sentenceCount, $syllableCount);

        // Analyze sentence complexity
        $sentenceComplexity = $this->analyzeSentenceComplexity($sentences);

        // Check for passive voice
        $passiveVoiceAnalysis = $this->analyzePassiveVoice($sentences, $language);

        // Analyze transition words
        $transitionWordsAnalysis = $this->analyzeTransitionWords($sentences, $language);

        // Analyze paragraph structure (use contentStructure data if available)
        $paragraphStructure = !empty($contentStructure)
            ? $this->adaptContentStructureToParagraphAnalysis($contentStructure)
            : $this->analyzeParagraphStructure($paragraphs);

        // Analyze complex words
        $complexWordsAnalysis = $this->analyzeComplexWords($cleanText, $language);

        // Calculate overall readability score (0-100)
        $overallScore = $this->calculateOverallReadabilityScore(
            $fleschReadingEase,
            $fleschKincaidGradeLevel,
            $sentenceComplexity,
            $passiveVoiceAnalysis,
            $transitionWordsAnalysis,
            $paragraphStructure,
            $complexWordsAnalysis
        );

        return [
            'overall_score' => $overallScore,
            'readability_level' => $this->getReadabilityLevel($overallScore),
            'flesch_reading_ease' => [
                'score' => $fleschReadingEase,
                'interpretation' => $this->interpretFleschReadingEase($fleschReadingEase)
            ],
            'flesch_kincaid_grade_level' => [
                'score' => $fleschKincaidGradeLevel,
                'interpretation' => $this->interpretGradeLevel($fleschKincaidGradeLevel)
            ],
            'sentence_metrics' => [
                'count' => $sentenceCount,
                'avg_length' => round($avgSentenceLength, 2),
                'complexity' => $sentenceComplexity
            ],
            'paragraph_metrics' => [
                'count' => $paragraphCount,
                'avg_sentences' => round($avgSentencesPerParagraph, 2),
                'avg_words' => round($avgWordsPerParagraph, 2),
                'structure_analysis' => $paragraphStructure
            ],
            'linguistic_metrics' => [
                'passive_voice' => $passiveVoiceAnalysis,
                'transition_words' => $transitionWordsAnalysis,
                'complex_words' => $complexWordsAnalysis
            ],
            'word_count' => $wordCount,
            'syllable_count' => $syllableCount
        ];
    }

    /**
     * Extract sentences from text.
     *
     * @param string $text The text to extract sentences from
     * @return array Array of sentences
     */
    private function extractSentences(string $text): array
    {
        // Split text by a common sentence ending punctuation followed by a space or end of string
        $sentences = preg_split('/(?<=[.!?])\s+|(?<=[.!?])$/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Filter out empty sentences
        return array_filter($sentences, static function($sentence) {
            return trim($sentence) !== '';
        });
    }

    /**
     * Extract paragraphs from HTML content.
     *
     * @param string $content The HTML content
     * @return array Array of paragraphs
     */
    private function extractParagraphs(string $content): array
    {
        return $this->contentProvider->getContentParagraphsHtml($content) ?? [];
    }

    /**
     * Count syllables in text.
     *
     * @param string $text The text to count syllables in
     * @param string $language The language of the text
     * @return int Total syllable count
     */
    private function countSyllables(string $text, string $language): int
    {
        // Split text into words
        $words = preg_split('/\s+/', $text);
        $totalSyllables = 0;

        foreach ($words as $word) {
            $word = strtolower(trim($word));
            if (empty($word)) {
                continue;
            }

            // Language-specific syllable counting
            if ($language === 'en') {
                $syllables = $this->countEnglishSyllables($word);
            } else {
                // Fallback syllable counting method for other languages
                $syllables = $this->countGenericSyllables($word);
            }

            $totalSyllables += max(1, $syllables); // Ensure at least 1 syllable per word
        }

        return $totalSyllables;
    }

    /**
     * Count syllables in an English word.
     *
     * @param string $word The word to count syllables in
     * @return int Syllable count
     */
    private function countEnglishSyllables(string $word): int
    {
        $word = strtolower($word);

        // Remove non-word characters
        $word = preg_replace('/[^a-z]/', '', $word);

        if (empty($word)) {
            return 0;
        }

        // Words with 3 characters or fewer have 1 syllable
        if (strlen($word) <= 3) {
            return 1;
        }

        // Remove ending 'e' unless it's the only vowel
        $word = preg_replace('/e$/', '', $word);

        // Count vowel groups
        $vowelCount = preg_match_all('/[aeiouy]+/', $word, $matches);

        // Adjust for common patterns

        // Sequential vowels count as one
        $word = preg_replace('/[aeiouy]{2,}/', 'a', $word);

        // Count vowel groups again
        $adjustedVowelCount = preg_match_all('/[aeiouy]+/', $word, $matches);

        return max(1, $adjustedVowelCount);
    }

    /**
     * Generic syllable counting method for non-English languages.
     *
     * @param string $word The word to count syllables in
     * @return int Syllable count
     */
    private function countGenericSyllables(string $word): int
    {
        // Remove non-letter characters
        $word = preg_replace('/[^a-zA-Z\p{L}]/', '', $word);

        if (empty($word)) {
            return 0;
        }

        // Count vowel groups as a proxy for syllables
        // This works reasonably well for many languages
        $vowelGroups = preg_match_all('/[aeiouyäöüàáâãèéêëìíîïòóôõùúûăąćęłńśșțžа-яёїієґў]+/ui', $word, $matches);

        return max(1, $vowelGroups);
    }

    /**
     * Calculate Flesch Reading Ease score.
     *
     * @param int $wordCount Total number of words
     * @param int $sentenceCount Total number of sentences
     * @param int $syllableCount Total number of syllables
     * @return float Flesch Reading Ease score
     */
    private function calculateFleschReadingEase(int $wordCount, int $sentenceCount, int $syllableCount): float
    {
        if ($wordCount === 0 || $sentenceCount === 0) {
            return 0;
        }

        $asl = $wordCount / $sentenceCount; // Average Sentence Length
        $asw = $syllableCount / $wordCount; // Average Syllables per Word

        // Flesch Reading Ease formula: 206.835 - (1.015 * ASL) - (84.6 * ASW)
        $score = 206.835 - (1.015 * $asl) - (84.6 * $asw);

        // Ensure the score is within the 0-100 range
        return round(max(0, min(100, $score)), 2);
    }

    /**
     * Calculate Flesch-Kincaid Grade Level.
     *
     * @param int $wordCount Total number of words
     * @param int $sentenceCount Total number of sentences
     * @param int $syllableCount Total number of syllables
     * @return float Flesch-Kincaid Grade Level
     */
    private function calculateFleschKincaidGradeLevel(int $wordCount, int $sentenceCount, int $syllableCount): float
    {
        if ($wordCount === 0 || $sentenceCount === 0) {
            return 0;
        }

        $asl = $wordCount / $sentenceCount; // Average Sentence Length
        $asw = $syllableCount / $wordCount; // Average Syllables per Word

        // Flesch-Kincaid Grade Level formula: (0.39 * ASL) + (11.8 * ASW) - 15.59
        $score = (0.39 * $asl) + (11.8 * $asw) - 15.59;

        // Ensure the score is not negative
        return round(max(0, $score), 2);
    }

    /**
     * Analyze sentence complexity.
     *
     * @param array $sentences Array of sentences
     * @return array Sentence complexity analysis
     */
    private function analyzeSentenceComplexity(array $sentences): array
    {
        $sentenceLengths = [];
        $longSentencesCount = 0;
        $veryLongSentencesCount = 0;
        $complexSentencesCount = 0;

        foreach ($sentences as $sentence) {
            $words = preg_split('/\s+/', $sentence);
            $wordCount = count($words);
            $sentenceLengths[] = $wordCount;

            // Check for long sentences
            if ($wordCount > SeoOptimiserConfig::SENTENCE_LENGTH_THRESHOLDS['acceptable_max']) {
                $longSentencesCount++;

                if ($wordCount > SeoOptimiserConfig::SENTENCE_LENGTH_THRESHOLDS['too_long']) {
                    $veryLongSentencesCount++;
                }
            }

            // Check for complex sentence structures
            $complexityIndicators = [
                'however', 'although', 'nevertheless', 'consequently', 'furthermore',
                'notwithstanding', 'accordingly', 'subsequently', 'therefore'
            ];

            // Count clauses (approximation by counting commas plus 1)
            $clausesCount = substr_count(strtolower($sentence), ',') + 1;

            // Check for complex structures
            $hasComplexIndicator = false;
            foreach ($complexityIndicators as $indicator) {
                if (stripos($sentence, $indicator) !== false) {
                    $hasComplexIndicator = true;
                    break;
                }
            }

            // Define a complex sentence as having multiple clauses and complex indicator words
            if ($clausesCount > 2 && $hasComplexIndicator) {
                $complexSentencesCount++;
            }
        }

        $totalSentences = count($sentences);

        // Calculate percentages
        $longSentencesPercentage = $totalSentences > 0 ? ($longSentencesCount / $totalSentences) * 100 : 0;
        $veryLongSentencesPercentage = $totalSentences > 0 ? ($veryLongSentencesCount / $totalSentences) * 100 : 0;
        $complexSentencesPercentage = $totalSentences > 0 ? ($complexSentencesCount / $totalSentences) * 100 : 0;

        return [
            'sentence_lengths' => $sentenceLengths,
            'avg_length' => $totalSentences > 0 ? array_sum($sentenceLengths) / $totalSentences : 0,
            'long_sentences' => [
                'count' => $longSentencesCount,
                'percentage' => round($longSentencesPercentage, 2)
            ],
            'very_long_sentences' => [
                'count' => $veryLongSentencesCount,
                'percentage' => round($veryLongSentencesPercentage, 2)
            ],
            'complex_sentences' => [
                'count' => $complexSentencesCount,
                'percentage' => round($complexSentencesPercentage, 2)
            ],
            'complexity_score' => $this->calculateSentenceComplexityScore(
                $longSentencesPercentage,
                $veryLongSentencesPercentage,
                $complexSentencesPercentage
            )
        ];
    }

    /**
     * Calculate sentence complexity score (0-100, lower is better).
     *
     * @param float $longSentencesPercentage Percentage of long sentences
     * @param float $veryLongSentencesPercentage Percentage of very long sentences
     * @param float $complexSentencesPercentage Percentage of complex sentences
     * @return float Complexity score
     */
    private function calculateSentenceComplexityScore(
        float $longSentencesPercentage,
        float $veryLongSentencesPercentage,
        float $complexSentencesPercentage
    ): float
    {
        // Weighted sum of complexity factors (higher percentage = more complex = higher score)
        $weightedSum = ($longSentencesPercentage * 0.3) +
            ($veryLongSentencesPercentage * 0.4) +
            ($complexSentencesPercentage * 0.3);

        // Normalize to 0-100 scale (100 is the most complex)
        $normalizedScore = min(100, $weightedSum * 2);

        // Invert to make lower scores represent higher complexity (0 is the most complex, 100 is the least complex)
        return round(100 - $normalizedScore, 2);
    }

    /**
     * Analyze passive voice usage in sentences.
     *
     * @param array $sentences Array of sentences
     * @param string $language The language of the text
     * @return array Passive voice analysis
     */
    private function analyzePassiveVoice(array $sentences, string $language): array
    {
        $passiveSentencesCount = 0;
        $passiveSentences = [];

        // Simple passive voice detection patterns (English-centric)
        $passivePatterns = [
            // To be + past participle
            '/\b(?:is|are|was|were|be|been|being)\s+(\w+ed)\b/i',
            // Some common irregular past participles
            '/\b(?:is|are|was|were|be|been|being)\s+(?:built|broken|chosen|done|drawn|driven|eaten|fallen|forgotten|given|gone|known|made|seen|spoken|stolen|taken|written)\b/i'
        ];

        foreach ($sentences as $sentence) {
            $isPassive = false;

            foreach ($passivePatterns as $pattern) {
                if (preg_match($pattern, $sentence)) {
                    $isPassive = true;
                    $passiveSentences[] = $sentence;
                    break;
                }
            }

            if ($isPassive) {
                $passiveSentencesCount++;
            }
        }

        $totalSentences = count($sentences);
        $passiveVoicePercentage = $totalSentences > 0 ? ($passiveSentencesCount / $totalSentences) * 100 : 0;

        // Calculate passive voice score (100 is best - no passive, 0 is worst - all passive)
        $passiveVoiceScore = 100 - $passiveVoicePercentage;

        return [
            'passive_sentences_count' => $passiveSentencesCount,
            'percentage' => round($passiveVoicePercentage, 2),
            'score' => round($passiveVoiceScore, 2),
            'exceeds_threshold' => $passiveVoicePercentage > SeoOptimiserConfig::PASSIVE_VOICE_THRESHOLD,
            'samples' => array_slice($passiveSentences, 0, 3) // Include a few examples
        ];
    }

    /**
     * Analyze transition words usage in sentences.
     *
     * @param array $sentences Array of sentences
     * @param string $language The language of the text
     * @return array Transition words analysis
     */
    private function analyzeTransitionWords(array $sentences, string $language): array
    {
        $transitionWords = [
            // Addition transitions
            'also', 'furthermore', 'moreover', 'additionally', 'besides', 'in addition',
            // Contrast transitions
            'however', 'nevertheless', 'on the other hand', 'in contrast', 'conversely', 'still',
            // Cause and effect transitions
            'therefore', 'consequently', 'thus', 'as a result', 'hence', 'accordingly',
            // Time transitions
            'meanwhile', 'subsequently', 'previously', 'finally', 'afterward', 'then',
            // Example transitions
            'for example', 'for instance', 'specifically', 'namely', 'to illustrate',
            // Clarification transitions
            'in other words', 'that is', 'to clarify', 'put differently',
            // Conclusion transitions
            'in conclusion', 'to summarize', 'in summary', 'in brief', 'to conclude'
        ];

        $sentencesWithTransitionWords = 0;
        $transitionWordInstances = [];

        foreach ($sentences as $sentence) {
            $sentenceHasTransition = false;
            $sentenceLower = strtolower($sentence);

            foreach ($transitionWords as $transitionWord) {
                if (str_contains($sentenceLower, $transitionWord)) {
                    $sentenceHasTransition = true;

                    // Track the usage of each transition word
                    if (!isset($transitionWordInstances[$transitionWord])) {
                        $transitionWordInstances[$transitionWord] = 0;
                    }
                    $transitionWordInstances[$transitionWord]++;
                }
            }

            if ($sentenceHasTransition) {
                $sentencesWithTransitionWords++;
            }
        }

        $totalSentences = count($sentences);
        $transitionWordsPercentage = $totalSentences > 0 ? ($sentencesWithTransitionWords / $totalSentences) * 100 : 0;

        // Sort transition words by frequency
        arsort($transitionWordInstances);

        // Calculate a transition word score (closer to the threshold is better)
        $distanceFromThreshold = abs($transitionWordsPercentage - SeoOptimiserConfig::TRANSITION_WORDS_THRESHOLD);
        $transitionWordScore = max(0, 100 - ($distanceFromThreshold * 2));

        return [
            'sentences_with_transitions' => $sentencesWithTransitionWords,
            'percentage' => round($transitionWordsPercentage, 2),
            'score' => round($transitionWordScore, 2),
            'meets_threshold' => $transitionWordsPercentage >= SeoOptimiserConfig::TRANSITION_WORDS_THRESHOLD,
            'most_used' => array_slice($transitionWordInstances, 0, 5, true)
        ];
    }

    /**
     * Analyze paragraph structure.
     *
     * @param array $paragraphs Array of paragraphs
     * @return array Paragraph structure analysis
     */
    private function analyzeParagraphStructure(array $paragraphs): array
    {
        $paragraphLengths = [];
        $longParagraphsCount = 0;
        $veryLongParagraphsCount = 0;
        $shortParagraphsCount = 0;

        foreach ($paragraphs as $paragraph) {
            $cleanParagraph = wp_strip_all_tags($paragraph);
            $sentences = $this->extractSentences($cleanParagraph);
            $sentenceCount = count($sentences);
            $paragraphLengths[] = $sentenceCount;

            // Analyze paragraph length
            if ($sentenceCount > SeoOptimiserConfig::PARAGRAPH_LENGTH_THRESHOLDS['acceptable_max']) {
                $longParagraphsCount++;

                if ($sentenceCount > SeoOptimiserConfig::PARAGRAPH_LENGTH_THRESHOLDS['too_long']) {
                    $veryLongParagraphsCount++;
                }
            } elseif ($sentenceCount === 1) {
                $shortParagraphsCount++;
            }
        }

        $totalParagraphs = count($paragraphs);

        // Calculate percentages
        $longParagraphsPercentage = $totalParagraphs > 0 ? ($longParagraphsCount / $totalParagraphs) * 100 : 0;
        $veryLongParagraphsPercentage = $totalParagraphs > 0 ? ($veryLongParagraphsCount / $totalParagraphs) * 100 : 0;
        $shortParagraphsPercentage = $totalParagraphs > 0 ? ($shortParagraphsCount / $totalParagraphs) * 100 : 0;

        // Calculate paragraph structure score
        $structureScore = $this->calculateParagraphStructureScore(
            $longParagraphsPercentage,
            $veryLongParagraphsPercentage,
            $shortParagraphsPercentage
        );

        return [
            'paragraph_lengths' => $paragraphLengths,
            'avg_length' => $totalParagraphs > 0 ? array_sum($paragraphLengths) / $totalParagraphs : 0,
            'long_paragraphs' => [
                'count' => $longParagraphsCount,
                'percentage' => round($longParagraphsPercentage, 2)
            ],
            'very_long_paragraphs' => [
                'count' => $veryLongParagraphsCount,
                'percentage' => round($veryLongParagraphsPercentage, 2)
            ],
            'short_paragraphs' => [
                'count' => $shortParagraphsCount,
                'percentage' => round($shortParagraphsPercentage, 2)
            ],
            'structure_score' => $structureScore
        ];
    }

    /**
     * Adapt content structure data from the content provider to our paragraph analysis format.
     *
     * @param array $contentStructure Content structure data from the content provider
     * @return array Adapted paragraph structure analysis
     */
    private function adaptContentStructureToParagraphAnalysis(array $contentStructure): array
    {
        // Extract paragraph length metrics from the content structure
        $paragraphCount = $contentStructure['paragraphs_count'] ?? 0;
        $avgParagraphLength = $contentStructure['avg_paragraph_length'] ?? 0;

        // Estimate long paragraphs based on average length
        $longParagraphsCount = 0;
        $veryLongParagraphsCount = 0;
        $shortParagraphsCount = 0;

        if (isset($contentStructure['paragraph_lengths']) && is_array($contentStructure['paragraph_lengths'])) {
            foreach ($contentStructure['paragraph_lengths'] as $length) {
                if ($length > SeoOptimiserConfig::PARAGRAPH_LENGTH_THRESHOLDS['acceptable_max']) {
                    $longParagraphsCount++;

                    if ($length > SeoOptimiserConfig::PARAGRAPH_LENGTH_THRESHOLDS['too_long']) {
                        $veryLongParagraphsCount++;
                    }
                } elseif ($length === 1) {
                    $shortParagraphsCount++;
                }
            }
        } else {
            // Estimate based on available metrics
            $veryLongParagraphsCount = (int)($paragraphCount * 0.1); // Estimate 10% very long
            $longParagraphsCount = (int)($paragraphCount * 0.3);     // Estimate 30% long
            $shortParagraphsCount = (int)($paragraphCount * 0.2);    // Estimate 20% short
        }

        // Calculate percentages
        $longParagraphsPercentage = $paragraphCount > 0 ? ($longParagraphsCount / $paragraphCount) * 100 : 0;
        $veryLongParagraphsPercentage = $paragraphCount > 0 ? ($veryLongParagraphsCount / $paragraphCount) * 100 : 0;
        $shortParagraphsPercentage = $paragraphCount > 0 ? ($shortParagraphsCount / $paragraphCount) * 100 : 0;

        // Calculate paragraph structure score
        $structureScore = $this->calculateParagraphStructureScore(
            $longParagraphsPercentage,
            $veryLongParagraphsPercentage,
            $shortParagraphsPercentage
        );

        return [
            'paragraph_lengths' => $contentStructure['paragraph_lengths'] ?? [],
            'avg_length' => $avgParagraphLength,
            'long_paragraphs' => [
                'count' => $longParagraphsCount,
                'percentage' => round($longParagraphsPercentage, 2)
            ],
            'very_long_paragraphs' => [
                'count' => $veryLongParagraphsCount,
                'percentage' => round($veryLongParagraphsPercentage, 2)
            ],
            'short_paragraphs' => [
                'count' => $shortParagraphsCount,
                'percentage' => round($shortParagraphsPercentage, 2)
            ],
            'structure_score' => $structureScore
        ];
    }

    /**
     * Calculate paragraph structure score (0-100, higher is better).
     *
     * @param float $longParagraphsPercentage Percentage of long paragraphs
     * @param float $veryLongParagraphsPercentage Percentage of very long paragraphs
     * @param float $shortParagraphsPercentage Percentage of short paragraphs
     * @return float Structure score
     */
    private function calculateParagraphStructureScore(
        float $longParagraphsPercentage,
        float $veryLongParagraphsPercentage,
        float $shortParagraphsPercentage
    ): float
    {
        // Ideal content should have:
        // - Few very long paragraphs (they reduce readability)
        // - Some short paragraphs (they improve scalability)
        // - A moderate number of long paragraphs (some complexity is OK)

        // Start with a perfect score
        $score = 100;

        // Penalize for very long paragraphs
        $score -= $veryLongParagraphsPercentage * 1.5;

        // Penalize for too many long paragraphs
        if ($longParagraphsPercentage > 40) {
            $score -= ($longParagraphsPercentage - 40) * 0.5;
        }

        // Ideal percentage of short paragraphs is around 30-50%
        $idealShortPercentage = 40;
        $shortParagraphDeviation = abs($shortParagraphsPercentage - $idealShortPercentage);
        $score -= $shortParagraphDeviation * 0.3;

        // Ensure score is within 0-100 range
        return round(max(0, min(100, $score)), 2);
    }

    /**
     * Analyze complex words in content.
     *
     * @param string $text The content text
     * @param string $language The language of the text
     * @return array Complex words analysis
     */
    private function analyzeComplexWords(string $text, string $language): array
    {
        $words = preg_split('/\s+/', $text);
        $wordCount = count($words);

        $complexWords = [];
        $complexWordsCount = 0;

        foreach ($words as $word) {
            $word = strtolower(trim($word));
            $word = preg_replace('/[^a-z\p{L}]/u', '', $word);

            if (empty($word) || strlen($word) <= 3) {
                continue;
            }

            // For English, consider words with 3+ syllables as complex
            if ($language === 'en') {
                $syllableCount = $this->countEnglishSyllables($word);

                if ($syllableCount >= 3) {
                    $complexWordsCount++;

                    if (!isset($complexWords[$word])) {
                        $complexWords[$word] = 0;
                    }
                    $complexWords[$word]++;
                }
            } else if (mb_strlen($word) >= 12) {
                $complexWordsCount++;

                if (!isset($complexWords[$word])) {
                    $complexWords[$word] = 0;
                }
                $complexWords[$word]++;
            }
        }

        $complexWordsPercentage = $wordCount > 0 ? ($complexWordsCount / $wordCount) * 100 : 0;

        // Calculate a score where the lower complex word percentage is better
        $complexWordScore = 100 - min(100, $complexWordsPercentage * 5);

        // Sort complex words by frequency
        arsort($complexWords);

        return [
            'complex_words_count' => $complexWordsCount,
            'percentage' => round($complexWordsPercentage, 2),
            'score' => round($complexWordScore, 2),
            'exceeds_threshold' => $complexWordsPercentage > SeoOptimiserConfig::COMPLEX_WORDS_THRESHOLD,
            'most_frequent' => array_slice($complexWords, 0, 10, true)
        ];
    }

    /**
     * Calculate the overall readability score based on various metrics.
     *
     * @param float $fleschReadingEase Flesch Reading Ease score
     * @param float $fleschKincaidGradeLevel Flesch-Kincaid Grade Level
     * @param array $sentenceComplexity Sentence complexity analysis
     * @param array $passiveVoiceAnalysis Passive voice analysis
     * @param array $transitionWordsAnalysis Transition words analysis
     * @param array $paragraphStructure Paragraph structure analysis
     * @param array $complexWordsAnalysis Complex words analysis
     * @return float Overall readability score (0-100)
     */
    private function calculateOverallReadabilityScore(
        float $fleschReadingEase,
        float $fleschKincaidGradeLevel,
        array $sentenceComplexity,
        array $passiveVoiceAnalysis,
        array $transitionWordsAnalysis,
        array $paragraphStructure,
        array $complexWordsAnalysis
    ): float
    {
        // Normalize Flesch Reading Ease (higher is better, 0-100)
        $fleschReadingEaseScore = $fleschReadingEase;

        // Normalize Flesch-Kincaid Grade Level (lower is better)
        // Ideal is 7-9, acceptable up to 12
        $gradeLevel = min($fleschKincaidGradeLevel, 18); // Cap at 18 to avoid extreme values
        $gradeLevelScore = 100 - (($gradeLevel / 18) * 100);

        // Get pre-calculated scores from the analyses
        $sentenceComplexityScore = $sentenceComplexity['complexity_score'] ?? 50;
        $passiveVoiceScore = $passiveVoiceAnalysis['score'] ?? 50;
        $transitionWordsScore = $transitionWordsAnalysis['score'] ?? 50;
        $paragraphStructureScore = $paragraphStructure['structure_score'] ?? 50;
        $complexWordsScore = $complexWordsAnalysis['score'] ?? 50;

        // Calculate weighted average of all scores
        $weightedSum =
            ($fleschReadingEaseScore * 0.25) +
            ($gradeLevelScore * 0.15) +
            ($sentenceComplexityScore * 0.15) +
            ($passiveVoiceScore * 0.10) +
            ($transitionWordsScore * 0.10) +
            ($paragraphStructureScore * 0.15) +
            ($complexWordsScore * 0.10);

        // Ensure score is within 0-100 range
        return round(max(0, min(100, $weightedSum)), 2);
    }

    /**
     * Get a textual interpretation of the Flesch Reading Ease score.
     *
     * @param float $score Flesch Reading Ease score
     * @return string Interpretation of the score
     */
    private function interpretFleschReadingEase(float $score): string
    {
        if ($score >= SeoOptimiserConfig::FLESCH_READING_EASE_THRESHOLDS['easy']) {
            return __('Very Easy: Easily understood by an average 11-year-old student', 'beyondseo');
        } elseif ($score >= SeoOptimiserConfig::FLESCH_READING_EASE_THRESHOLDS['fairly_easy']) {
            return __('Easy: Conversational English for consumers', 'beyondseo');
        } elseif ($score >= SeoOptimiserConfig::FLESCH_READING_EASE_THRESHOLDS['standard']) {
            return __('Fairly Easy: Easily understood by 13-15 year old students', 'beyondseo');
        } elseif ($score >= SeoOptimiserConfig::FLESCH_READING_EASE_THRESHOLDS['fairly_difficult']) {
            return __('Standard: Easily understood by 13-15 year old students', 'beyondseo');
        } elseif ($score >= SeoOptimiserConfig::FLESCH_READING_EASE_THRESHOLDS['difficult']) {
            return __('Fairly Difficult: Easily understood by college students', 'beyondseo');
        } elseif ($score >= SeoOptimiserConfig::FLESCH_READING_EASE_THRESHOLDS['very_difficult']) {
            return __('Difficult: Best understood by university graduates', 'beyondseo');
        } else {
            return __('Very Difficult: Best understood by university graduates', 'beyondseo');
        }
    }

    /**
     * Get a textual interpretation of the grade level score.
     *
     * @param float $score Grade level score
     * @return string Interpretation of the score
     */
    private function interpretGradeLevel(float $score): string
    {
        if ($score <= 6) {
            return __('Elementary level: Very accessible content', 'beyondseo');
        } elseif ($score <= SeoOptimiserConfig::GRADE_LEVEL_THRESHOLDS['ideal_max']) {
            return __('Middle school level: Ideal for general audience', 'beyondseo');
        } elseif ($score <= SeoOptimiserConfig::GRADE_LEVEL_THRESHOLDS['acceptable_max']) {
            return __('High school level: Reasonably accessible for most readers', 'beyondseo');
        } elseif ($score <= 16) {
            return __('College level: May be challenging for some readers', 'beyondseo');
        } else {
            return __('Graduate level: Technical content that may be difficult for many readers', 'beyondseo');
        }
    }

    /**
     * Get the overall readability level based on the score.
     *
     * @param float $score The overall readability score
     * @return string The readability level
     */
    private function getReadabilityLevel(float $score): string
    {
        if ($score >= 90) {
            return __('Excellent', 'beyondseo');
        } elseif ($score >= 80) {
            return __('Very Good', 'beyondseo');
        } elseif ($score >= 70) {
            return __('Good', 'beyondseo');
        } elseif ($score >= 60) {
            return __('Acceptable', 'beyondseo');
        } elseif ($score >= 50) {
            return __('Poor', 'beyondseo');
        } else {
            return __('Very Poor', 'beyondseo');
        }
    }

    /**
     * Calculate the score based on the performed analysis
     *
     * @return float A score based on the readability metrics
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;

        // Get readability scores from the factor data
        $readabilityScores = $factorData['readability_scores'] ?? [];

        if (empty($readabilityScores)) {
            return 0;
        }

        // Use the pre-calculated overall score
        $overallScore = $readabilityScores['overall_score'] ?? 0;

        // Normalize the score to a 0-1 range
        return $overallScore / 100;
    }

    /**
     * Generate suggestions based on readability analysis
     *
     * @return array Active suggestions based on identified issues
     */
    public function suggestions(): array
    {
        $activeSuggestions = [];

        $factorData = $this->value;

        // Get readability scores from the factor data
        $readabilityScores = $factorData['readability_scores'] ?? [];

        if (empty($readabilityScores)) {
            return $activeSuggestions;
        }

        // Check for long paragraphs
        $paragraphMetrics = $readabilityScores['paragraph_metrics'] ?? [];
        if (!empty($paragraphMetrics) &&
            isset($paragraphMetrics['very_long_paragraphs']) &&
            $paragraphMetrics['very_long_paragraphs']['percentage'] > 10) {
            $activeSuggestions[] = Suggestion::PARAGRAPHS_TOO_LONG;
        }

        // Check if content lacks proper structure with headings
        if (!empty($paragraphMetrics) &&
            isset($paragraphMetrics['structure_score']) &&
            $paragraphMetrics['structure_score'] < 50) {
            $activeSuggestions[] = Suggestion::INSUFFICIENT_HEADINGS;
        }

        // Check Flesch Reading Ease for content depth/complexity issues
        $fleschReadingEase = $readabilityScores['flesch_reading_ease']['score'] ?? 0;
        if ($fleschReadingEase < 50) {
            $activeSuggestions[] = Suggestion::LACKS_CONTENT_DEPTH;
        }

        // Check for complex sentence structures affecting readability
        $sentenceMetrics = $readabilityScores['sentence_metrics'] ?? [];
        if (!empty($sentenceMetrics) &&
            isset($sentenceMetrics['complex_sentences']) &&
            $sentenceMetrics['complex_sentences']['percentage'] > 15) {
            // Use poor keyword distribution as a proxy for difficult-to-read content
            $activeSuggestions[] = Suggestion::POOR_KEYWORD_DISTRIBUTION;
        }

        return $activeSuggestions;
    }
}