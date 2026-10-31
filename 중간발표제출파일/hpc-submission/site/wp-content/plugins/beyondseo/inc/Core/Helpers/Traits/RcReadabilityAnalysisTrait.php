<?php
/** @noinspection PhpUnnecessaryCurlyVarSyntaxInspection */
/** @noinspection RegExpUnnecessaryNonCapturingGroup */
/** @noinspection PhpTooManyParametersInspection */
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Helpers\Traits;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Models\Configs\SeoOptimiserConfig;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;

/**
 * Trait RcReadabilityAnalysisTrait
 *
 * This trait is used to provide readability analysis functionality.
 */
trait RcReadabilityAnalysisTrait
{
    use RcLoggerTrait;

    /**
     * Analyze the readability of the content
     *
     * @param string $content The full HTML content
     * @param string $keyword The keyword to analyze
     * @return array Readability analysis results
     */
    public function analyzeReadability(string $content, string $keyword): array
    {
        // Extract sentences containing the keyword
        $sentences = preg_split('/(?<=[.?!])\s+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $keywordSentences = array_filter($sentences, function($sentence) use ($keyword) {
            return str_contains(strtolower($sentence), $keyword);
        });

        // Calculate average sentence length
        $sentenceLengths = array_map(function($sentence) {
            return str_word_count($sentence);
        }, $keywordSentences);

        $avgSentenceLength = count($sentenceLengths) > 0 ?
            array_sum($sentenceLengths) / count($sentenceLengths) : 0;

        // Simplified readability score (0-10)
        $readabilityScore = 10 - min(10, abs($avgSentenceLength - 15));

        return [
            'avg_sentence_length' => round($avgSentenceLength, 1),
            'readability_score' => round($readabilityScore, 1),
            'status' => $this->getReadabilityStatus($readabilityScore)
        ];
    }

    /**
     * Get the readability status based on the score
     *
     * @param float $score The readability score
     * @return string Status of the readability
     */
    public function getReadabilityStatus(float $score): string
    {
        if ($score < 4) {
            return 'poor';
        } elseif ($score < 7) {
            return 'average';
        } else {
            return 'good';
        }
    }

    /**
     * Analyzes the readability metrics of content.
     *
     * @param string $content The content to analyze (can contain HTML)
     * @param string $language The language of the content (e.g., 'en', 'ro', 'de', 'zh', 'ja', 'ko')
     * @return array The readability metrics
     */
    public function analyzeReadabilityMetrics(string $content, string $language): array
    {
        // Clean content from HTML tags first for consistent analysis
        $cleanedContent = wp_strip_all_tags($content);
        $cleanedContent = trim($cleanedContent);

        // Determine if the language is CJK (Chinese, Japanese, Korean)
        $isCJK = $this->isCJKLanguage($language);

        // Count words based on language-type
        $wordCount = $this->countWordsBasedOnLanguage($cleanedContent, $language);

        // Split the content into sentences. Note: Sentence splitting is also language-dependent, and this regex is basic.
        $sentences = $this->splitIntoSentences($cleanedContent); // Use cleaned content for sentence splitting
        $sentenceCount = count($sentences);

        // --- Language-Specific Metrics (primarily for non-CJK, especially English) ---
        $syllableCount = 0;
        $avgSyllablesPerWord = 0;
        $complexWords = 0;
        $complexWordsPercentage = 0;
        $fleschKincaidScore = 0;
        $fleschKincaidGradeLevel = 'Analysis not applicable for this language';
        $smogIndex = 0;
        $passiveVoiceAnalysis = ['passive_sentences_count' => 0, 'passive_sentences_percentage' => 0, 'passive_sentences_examples' => [], 'exceeds_threshold' => false];
        $transitionWordsAnalysis = ['sentences_with_transitions_count' => 0, 'sentences_with_transitions_percentage' => 0, 'meets_threshold' => false];

        if (!$isCJK) {
            // These metrics are generally only applicable/meaningful for space-separated languages
            // and precisely calculated primarily for English with the current logic.

            $syllableCount = $this->countSyllables($cleanedContent, $language); // Returns 0 if language is not 'en'

            // Calculate average words per sentence (meaningful for space-separated)
            $avgWordsPerSentence = $sentenceCount > 0 ? $wordCount / $sentenceCount : 0;

            // Calculate average syllables per word (meaningful only if syllable-count is accurate)
            $avgSyllablesPerWord = $wordCount > 0 ? $syllableCount / $wordCount : 0;

            // Calculate complex words (meaningful only if syllable-count is accurate)
            $complexWords = $this->countComplexWords($cleanedContent, $language); // Returns 0 if language is not 'en'
            $complexWordsPercentage = $wordCount > 0 ? ($complexWords / $wordCount) * 100 : 0;

            // Calculate Readability Scores (depend on accurate word/sentence/syllable counts)
            $fleschKincaidScore = $this->calculateFleschKincaidReadingEase(
                $sentenceCount,
                $wordCount,
                $syllableCount
            );
            if ($syllableCount > 0) { // Only provide grade-level if syllable-count was calculated (i.e., English)
                $fleschKincaidGradeLevel = $this->fleschKincaidToGradeLevel($fleschKincaidScore);
            }

            $smogIndex = $this->calculateSmogIndex($sentenceCount, $complexWords); // Will be based on 0 complex words for non-English

            // Coleman-Liau Index uses character count, word count, and sentence count.
            $colemanLiauIndex = $this->calculateColemanLiauIndex($cleanedContent, $wordCount, $sentenceCount);

            // Analyze sentence length distribution (meaningful for space-separated)
            $sentenceLengthDistribution = $this->analyzeSentenceLengthDistribution($sentences);

            // Analyze passive voice usage (logic is English-centric)
            $passiveVoiceAnalysis = $this->analyzePassiveVoice($cleanedContent); // Note: Passive voice detection is language-dependent

            // Analyze transition words usage (depends on language-specific lists)
            $transitionWordsAnalysis = $this->analyzeTransitionWords($cleanedContent, $language); // Uses language-specific lists if available

        } else {
            // For CJK languages, word count is character count.
            // Most other metrics (syllables, complex words, scores, passive voice, transition words)
            // are not directly applicable or require entirely different logic.
            // Sentence splitting and paragraph analysis might still be relevant but need language-specific handling for accuracy.
            // For now, we return 0 or default values for non-applicable metrics.
            $avgWordsPerSentence = $sentenceCount > 0 ? $wordCount / $sentenceCount : 0;
            $sentenceLengthDistribution = $this->analyzeSentenceLengthDistribution($sentences); // Logic uses str_word_count, which will count 1 for CJK sentences
            // This distribution will show character counts per sentence, not word counts.
            // It might be better to return 0 or adapt this method for CJK. Let's return 0 for now for clarity.
            // $sentenceLengthDistribution = ['counts' => [], 'percentages' => [], 'total_sentences' => $sentenceCount, 'long_sentences' => []];

            // Coleman-Liau uses character count, which we have (as wordCount), but the formula is for English.
            // Let's calculate it but understand its meaning is different.
            $colemanLiauIndex = $this->calculateColemanLiauIndex($cleanedContent, $wordCount, $sentenceCount); // Calculated, but meaning is limited

            $fleschKincaidGradeLevel = 'N/A for CJK languages';
        }

        // Paragraph analysis might be relevant if the content uses HTML paragraphs, regardless of language script.
        $paragraphAnalysis = $this->analyzeParagraphStructure($content);

        return [
            'word_count' => $wordCount,
            'sentence_count' => $sentenceCount,
            'syllable_count' => $syllableCount,
            'avg_words_per_sentence' => round($avgWordsPerSentence, 2),
            'avg_syllables_per_word' => round($avgSyllablesPerWord, 2),
            'complex_words' => $complexWords,
            'complex_words_percentage' => round($complexWordsPercentage, 2),
            'flesch_kincaid_score' => round($fleschKincaidScore, 2),
            'flesch_kincaid_grade_level' => $fleschKincaidGradeLevel,
            'smog_index' => round($smogIndex, 2),
            'coleman_liau_index' => round($colemanLiauIndex, 2),
            'sentence_length_distribution' => $sentenceLengthDistribution,
            'paragraph_analysis' => $paragraphAnalysis,
            'passive_voice_analysis' => $passiveVoiceAnalysis,
            'transition_words_analysis' => $transitionWordsAnalysis,
        ];
    }

    /**
     * Checks if a language code corresponds to a CJK language.
     *
     * @param string $language The language code
     * @return bool True if it's a CJK language, false otherwise
     */
    protected function isCJKLanguage(string $language): bool
    {
        // Basic check based on common CJK language codes
        $cjkCodes = ['zh', 'ja', 'ko', 'zh-hans', 'zh-hant']; // Add variants as needed
        return in_array(strtolower($language), $cjkCodes, true);
    }

    /**
     * Counts words based on language type (characters for CJK, space-separated for others).
     *
     * @param string $text The cleaned text to count words in
     * @param string $language The language of the text
     * @return int The word count (or character count for CJK)
     */
    protected function countWordsBasedOnLanguage(string $text, string $language): int
    {
        $text = trim($text);

        if (empty($text)) {
            return 0;
        }

        // Check if it's a CJK language based on the provided language code
        if ($this->isCJKLanguage($language)) {
            // For CJK, count characters (excluding whitespace and potentially punctuation)
            // This regex removes common punctuation. Adjust if needed.
            $text = preg_replace('/\p{P}|\p{Zs}/u', '', $text); // Remove punctuation and spaces
            return mb_strlen($text, 'UTF-8');
        }

        // For other languages (space-separated), count words by splitting on whitespace
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return count($words);
    }

    /**
     * Splits text into sentences.
     *
     * @param string $text The text to split
     * @return array Array of sentences
     */
    public function splitIntoSentences(string $text): array
    {
        // Split text on sentence boundaries
        $sentences = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Filter out non-sentences (must have at least one word)
        return array_filter($sentences, function($sentence) {
            return str_word_count($sentence) > 0;
        });
    }

    /**
     * Counts the number of syllables in text.
     *
     * @param string $text The text to analyze
     * @param string $language The language of the text
     * @return int The total number of syllables
     */
    public function countSyllables(string $text, string $language): int
    {
        // If not English, use a simplified approach as syllable counting varies by language
        if ($language !== 'en') {
            // Simplified approach for non-English languages
            // Count vowels as approximate syllables
            return preg_match_all('/[aeiouyàáâäãåèéêëìíîïòóôöõùúûüæœ]/i', $text);
        }

        // For English, use a more accurate approach
        $words = str_word_count($text, 1);
        $totalSyllables = 0;

        foreach ($words as $word) {
            $totalSyllables += $this->countWordSyllables($word);
        }

        return $totalSyllables;
    }

    /**
     * Counts syllables in an English word.
     *
     * @param string $word The word to count syllables in
     * @return int The number of syllables
     */
    public function countWordSyllables(string $word): int
    {
        // Convert to lowercase
        $word = strtolower($word);

        // Remove punctuation
        $word = preg_replace('/[^a-z]/', '', $word);

        // Special cases
        if (empty($word)) {
            return 0;
        }

        // Words with 3 or fewer characters are typically 1 syllable
        if (strlen($word) <= 3) {
            return 1;
        }

        // Remove ending 'e', 'es', 'ed' which are often silent
        $word = preg_replace('/e$/', '', $word);
        $word = preg_replace('/es$/', '', $word);
        $word = preg_replace('/ed$/', '', $word);

        // Count vowel groups as syllables
        $syllables = preg_match_all('/[aeiouy]{1,3}/i', $word);

        // Make sure we count at least one syllable per word
        return max(1, $syllables);
    }

    /**
     * Counts complex words (words with 3+ syllables) in text.
     *
     * @param string $text The text to analyze
     * @param string $language The language of the text
     * @return int The number of complex words
     */
    public function countComplexWords(string $text, string $language): int
    {
        $words = str_word_count($text, 1);
        $complexWordCount = 0;

        foreach ($words as $word) {
            // Skip common non-complex words regardless of syllables
            if (in_array(strtolower($word), $this->getNonComplexWords($language))) {
                continue;
            }

            $syllables = $this->countWordSyllables($word);
            if ($syllables >= 3) {
                $complexWordCount++;
            }
        }

        return $complexWordCount;
    }

    /**
     * Gets a list of common words that should not be considered complex
     * despite having 3+ syllables.
     *
     * @param string $language The language
     * @return array List of words to exclude from complex word counting
     */
    public function getNonComplexWords(string $language): array
    {
        // These words might have 3+ syllables but are commonly understood
        if ($language === 'en') {
            return [
                'basically', 'actually', 'specifically', 'probably', 'generally',
                'usually', 'finally', 'eventually', 'especially', 'completely',
                'definitely', 'naturally', 'university', 'understanding', 'interesting',
                'education', 'experience', 'information', 'technology', 'development',
                'everything', 'environment', 'organization', 'constitutional', 'unfortunately'
            ];
        }

        // For other languages, return an empty array or language-specific words
        return [];
    }

    /**
     * Calculates the Flesch-Kincaid Reading Ease score.
     *
     * @param int $sentenceCount Number of sentences
     * @param int $wordCount Number of words
     * @param int $syllableCount Number of syllables
     * @return float Flesch-Kincaid Reading Ease score (0-100)
     */
    public function calculateFleschKincaidReadingEase(int $sentenceCount, int $wordCount, int $syllableCount): float
    {
        if ($wordCount === 0 || $sentenceCount === 0) {
            return 0;
        }

        $avgSentenceLength = $wordCount / $sentenceCount;
        $avgSyllablesPerWord = $syllableCount / $wordCount;

        // Flesch-Kincaid Reading Ease formula
        $score = 206.835 - (1.015 * $avgSentenceLength) - (84.6 * $avgSyllablesPerWord);

        // Ensure score is within 0-100 range
        return max(0, min(100, $score));
    }

    /**
     * Calculates the SMOG Index (Simple Measure of Gobbledygook).
     *
     * @param int $sentenceCount Number of sentences
     * @param int $complexWords Number of complex words
     * @return float SMOG Index value
     */
    public function calculateSmogIndex(int $sentenceCount, int $complexWords): float
    {
        if ($sentenceCount < 30) {
            // SMOG is designed for 30+ sentences, so provide an adjusted calculation
            $sentenceCount = max(1, $sentenceCount); // Avoid division by zero
            $adjustmentFactor = 30 / $sentenceCount;
            $complexWords = $complexWords * $adjustmentFactor;
        }

        // SMOG Index formula
        return 1.043 * sqrt($complexWords * (30 / $sentenceCount)) + 3.1291;
    }

    /**
     * Calculates the Coleman-Liau Index.
     *
     * @param string $text The text to analyze
     * @return float Coleman-Liau Index value
     */
    public function calculateColemanLiauIndex(string $text, int $wordCount, int $sentenceCount): float
    {
        if ($wordCount === 0 || $sentenceCount === 0) {
            return 0;
        }

        // Count characters (excluding spaces) - this still needs the original text
        $characterCount = mb_strlen(preg_replace('/\s+/', '', $text), 'UTF-8');

        // wordCount and sentenceCount are now received as parameters

        // Calculate L (average number of characters per 100 words)
        $L = ($characterCount / $wordCount) * 100;

        // Calculate S (average number of sentences per 100 words)
        // Ensure sentenceCount is at least 1 for the denominator, although the check above covers case 0
        $S = ($sentenceCount / $wordCount) * 100;

        // Coleman-Liau Index formula
        return 0.0588 * $L - 0.296 * $S - 15.8;
    }

    /**
     * Analyzes sentence length distribution.
     *
     * @param array $sentences Array of sentences
     * @return array Analysis of sentence length distribution
     */
    public function analyzeSentenceLengthDistribution(array $sentences): array
    {
        $distribution = [
            'very_short' => 0,  // 1-5 words
            'short' => 0,       // 6-10 words
            'medium' => 0,      // 11-15 words
            'long' => 0,        // 16-20 words
            'very_long' => 0,   // 21-25 words
            'extremely_long' => 0 // 26+ words
        ];

        $longSentences = [];

        foreach ($sentences as $sentence) {
            $wordCount = str_word_count($sentence);

            if ($wordCount <= 5) {
                $distribution['very_short']++;
            } elseif ($wordCount <= 10) {
                $distribution['short']++;
            } elseif ($wordCount <= 15) {
                $distribution['medium']++;
            } elseif ($wordCount <= 20) {
                $distribution['long']++;
            } elseif ($wordCount <= 25) {
                $distribution['very_long']++;
                $longSentences[] = $sentence;
            } else {
                $distribution['extremely_long']++;
                $longSentences[] = $sentence;
            }
        }

        // Calculate percentages
        $totalSentences = count($sentences);
        $percentages = [];

        if ($totalSentences > 0) {
            foreach ($distribution as $category => $count) {
                $percentages[$category] = round(($count / $totalSentences) * 100, 2);
            }
        }

        return [
            'counts' => $distribution,
            'percentages' => $percentages,
            'total_sentences' => $totalSentences,
            'long_sentences' => array_slice($longSentences, 0, 5) // Include up to 5 examples of long sentences
        ];
    }

    /**
     * Analyzes paragraph structure in content.
     *
     * @param string $content The content to analyze
     * @return array Paragraph structure analysis
     */
    public function analyzeParagraphStructure(string $content): array
    {
        $paragraphs = $this->getContentParagraphsText($content);

        $paragraphCount = count($paragraphs);
        $longParagraphs = [];
        $wordsByParagraph = [];

        // Analyze each paragraph
        foreach ($paragraphs as $paragraph) {
            $wordCount = str_word_count($paragraph);
            $wordsByParagraph[] = $wordCount;

            // Track long paragraphs (more than 100 words)
            if ($wordCount > 100) {
                $longParagraphs[] = substr($paragraph, 0, 150) . '...'; // Store a snippet
            }
        }

        // Calculate average words per paragraph
        $avgWordsPerParagraph = $paragraphCount > 0 ? array_sum($wordsByParagraph) / $paragraphCount : 0;

        return [
            'paragraph_count' => $paragraphCount,
            'avg_words_per_paragraph' => round($avgWordsPerParagraph, 2),
            'long_paragraphs_count' => count($longParagraphs),
            'long_paragraphs_examples' => array_slice($longParagraphs, 0, 3), // Include up to 3 examples
            'paragraph_length_distribution' => $this->analyzeArrayDistribution($wordsByParagraph)
        ];
    }

    /**
     * Analyzes distribution of values in an array across specified ranges.
     *
     * @param array $values Array of numeric values
     * @return array Distribution analysis
     */
    public function analyzeArrayDistribution(array $values): array
    {
        $thresholds = [20, 40, 60, 100];

        sort($thresholds); // Ensure thresholds are in ascending order

        // Initialize distribution buckets
        $distribution = [];
        $distribution['0-' . $thresholds[0]] = 0;

        for ($i = 0; $i < count($thresholds) - 1; $i++) {
            $distribution[$thresholds[$i] . '-' . $thresholds[$i+1]] = 0;
        }

        $distribution[$thresholds[count($thresholds) - 1] . '+'] = 0;

        // Count values in each range
        foreach ($values as $value) {
            if ($value <= $thresholds[0]) {
                $distribution['0-' . $thresholds[0]]++;
            } elseif ($value > $thresholds[count($thresholds) - 1]) {
                $distribution[$thresholds[count($thresholds) - 1] . '+']++;
            } else {
                for ($i = 0; $i < count($thresholds) - 1; $i++) {
                    if ($value > $thresholds[$i] && $value <= $thresholds[$i+1]) {
                        $distribution[$thresholds[$i] . '-' . $thresholds[$i+1]]++;
                        break;
                    }
                }
            }
        }

        // Calculate percentages
        $totalValues = count($values);
        $percentages = [];

        if ($totalValues > 0) {
            foreach ($distribution as $range => $count) {
                $percentages[$range] = round(($count / $totalValues) * 100, 2);
            }
        }

        return [
            'counts' => $distribution,
            'percentages' => $percentages
        ];
    }

    /**
     * Analyzes passive voice usage in content.
     *
     * @param string $content The content to analyze
     * @return array Passive voice analysis
     */
    public function analyzePassiveVoice(string $content): array
    {
        // Simplified passive voice pattern detection
        // Looks for forms of 'to be' followed by a past participle
        $passivePatterns = [
            '/\b(is|are|was|were|be|been|being)\s+(\w+ed)\b/i',  // is called, are taken
            '/\b(is|are|was|were|be|been|being)\s+(\w+en)\b/i',  // is spoken, were given
            '/\b(is|are|was|were|be|been|being)\s+(\w+t)\b/i',   // is built, were kept
        ];

        $sentences = $this->splitIntoSentences($content);
        $passiveSentences = [];

        foreach ($sentences as $sentence) {
            $isPassive = false;

            foreach ($passivePatterns as $pattern) {
                if (preg_match($pattern, $sentence)) {
                    $isPassive = true;
                    break;
                }
            }

            if ($isPassive) {
                $passiveSentences[] = $sentence;
            }
        }

        $passiveCount = count($passiveSentences);
        $totalSentences = count($sentences);
        $passivePercentage = $totalSentences > 0 ? ($passiveCount / $totalSentences) * 100 : 0;

        return [
            'passive_sentences_count' => $passiveCount,
            'passive_sentences_percentage' => round($passivePercentage, 2),
            'passive_sentences_examples' => array_slice($passiveSentences, 0, 3),
            'exceeds_threshold' => $passivePercentage > SeoOptimiserConfig::PASSIVE_VOICE_THRESHOLD
        ];
    }

    /**
     * Analyzes transition words usage in content.
     *
     * @param string $content The content to analyze
     * @param string $language The language of the content
     * @return array Transition words analysis
     */
    public function analyzeTransitionWords(string $content, string $language): array
    {
        $sentences = $this->splitIntoSentences($content);
        $transitionWords = $this->getTransitionWords($language);

        $sentencesWithTransitions = [];

        foreach ($sentences as $sentence) {
            $hasTransition = false;

            foreach ($transitionWords as $word) {
                if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $sentence)) {
                    $hasTransition = true;
                    break;
                }
            }

            if ($hasTransition) {
                $sentencesWithTransitions[] = $sentence;
            }
        }

        $transitionCount = count($sentencesWithTransitions);
        $totalSentences = count($sentences);
        $transitionPercentage = $totalSentences > 0 ? ($transitionCount / $totalSentences) * 100 : 0;

        return [
            'sentences_with_transitions_count' => $transitionCount,
            'sentences_with_transitions_percentage' => round($transitionPercentage, 2),
            'meets_threshold' => $transitionPercentage >= SeoOptimiserConfig::TRANSITION_WORDS_THRESHOLD
        ];
    }

    /**
     * Gets a list of common transition words for a language.
     *
     * @param string $language The language code
     * @return array List of transition words
     */
    public function getTransitionWords(string $language): array
    {
        // Basic transition words by language
        $transitionWordsByLanguage = [
            'en' => [
                'additionally', 'also', 'besides', 'furthermore', 'in addition',
                'likewise', 'moreover', 'similarly',
                'accordingly', 'as a result', 'consequently', 'hence', 'therefore', 'thus',
                'in contrast', 'conversely', 'however', 'nevertheless', 'nonetheless', 'on the contrary',
                'still', 'yet',
                'for example', 'for instance', 'indeed', 'in fact', 'namely', 'specifically',
                'such as', 'to illustrate',
                'afterward', 'before', 'currently', 'during', 'eventually', 'finally',
                'first', 'second', 'third', 'lastly', 'meanwhile', 'next', 'since', 'soon',
                'subsequently', 'then', 'ultimately', 'while'
            ],
            'de' => [
                'außerdem', 'auch', 'zusätzlich', 'ferner', 'weiterhin', 'überdies',
                'folglich', 'daher', 'deshalb', 'somit', 'demnach', 'infolgedessen',
                'im Gegensatz dazu', 'andererseits', 'jedoch', 'trotzdem', 'dennoch',
                'zum Beispiel', 'beispielsweise', 'nämlich', 'insbesondere',
                'zuerst', 'zunächst', 'dann', 'danach', 'schließlich', 'letztlich'
            ],
            'fr' => [
                'de plus', 'en outre', 'par ailleurs', 'aussi', 'également',
                'par conséquent', 'donc', 'ainsi', 'alors', 'c\'est pourquoi',
                'en revanche', 'au contraire', 'cependant', 'néanmoins', 'toutefois',
                'par exemple', 'notamment', 'en particulier', 'c\'est-à-dire',
                'd\'abord', 'ensuite', 'puis', 'enfin', 'finalement', 'en conclusion'
            ]
        ];

        // Default to English if language not supported
        return $transitionWordsByLanguage[$language] ?? $transitionWordsByLanguage['en'];
    }

    /**
     * Converts Flesch-Kincaid Reading Ease score to equivalent grade level.
     *
     * @param float $score Flesch-Kincaid Reading Ease score
     * @return string The grade level description
     */
    public function fleschKincaidToGradeLevel(float $score): string
    {
        if ($score >= 90) {
            return __('5th grade (Very easy to read)', 'beyondseo');
        } elseif ($score >= 80) {
            return __('6th grade (Easy to read)', 'beyondseo');
        } elseif ($score >= 70) {
            return __('7th grade (Fairly easy to read)', 'beyondseo');
        } elseif ($score >= 60) {
            return __('8th-9th grade (Plain English)', 'beyondseo');
        } elseif ($score >= 50) {
            return __('10th-12th grade (Fairly difficult)', 'beyondseo');
        } elseif ($score >= 30) {
            return __('College (Difficult)', 'beyondseo');
        } else {
            return __('College graduate (Very difficult)', 'beyondseo');
        }
    }
}
