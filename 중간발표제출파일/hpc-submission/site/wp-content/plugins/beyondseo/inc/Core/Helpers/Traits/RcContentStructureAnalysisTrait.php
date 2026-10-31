<?php
/** @noinspection PhpUndefinedFunctionInspection */
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
use DOMElement;
use DOMXPath;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Modules\ModuleLibrary\Technical\MetaTags\MetaTags;

/**
 * Trait RcContentStructureAnalysisTrait
 *
 * Provides utility methods for handling content structure analysis.
 */
trait RcContentStructureAnalysisTrait
{
    use RcLoggerTrait;

    /**
     * Get headings from content in document order
     * @param string $content
     * @return array
     */
    public function getHeadingsFromContent(string $content): array
    {
        $xpath = $this->loadHTMLInDomXPath($content);
        if (!$xpath) {
            return [];
        }

        $headings = [];

        // Query all heading elements in document order
        for ($i = 1; $i <= 6; $i++) {
            $headingNodes = $xpath->query("//h$i");
            foreach ($headingNodes as $node) {
                // Skip headings that are within excluded/common site parts
                if ($node instanceof DOMElement) {
                    if ($this->isExcludedContainer($node) || $this->isCommonSitePart($node)) {
                        continue;
                    }
                }

                $headings[] = [
                    'level' => strtolower($node->nodeName),
                    'text' => $node->textContent,
                ];
            }
        }

        return $headings;
    }

    /**
     * Match heading text by keyword
     * @param array $headings
     * @param string $keyword
     * @return array
     */
    public function matchHeadingTextByKeyword(array $headings, string $keyword): array
    {
        $keyword = strtolower($keyword);
        $keywordLower = mb_strtolower($keyword);
        $matches = [];

        foreach ($headings as $heading) {
            $text = trim($heading['text'] ?? '');
            if (mb_stripos($text, $keywordLower) !== false) {
                $matches[] = [
                    'level' => $heading['level'],
                    'text' => $text,
                    'matched' => true,
                ];
            }
        }

        return $matches;
    }

    /**
     * Analyze headings for local keyword presence
     *
     * @param DOMXPath $xpath XPath object for HTML parsing
     * @param array $localKeywords List of local keywords to check
     * @return array Analysis results
     */
    public function analyzeHeadings(DOMXPath $xpath, array $localKeywords): array
    {
        $headingResults = [];
        $totalHeadings = 0;
        $headingsWithKeywords = 0;

        // Check each heading level (h1-h6)
        for ($i = 1; $i <= 6; $i++) {
            $headings = $xpath->query("//h$i");
            if ($headings->length === 0) {
                continue;
            }

            $totalHeadings += $headings->length;
            $headingAnalysis = [];

            foreach ($headings as $heading) {
                $headingText = $heading->textContent;
                $normalizedHeading = strtolower($headingText);
                $keywordsFound = [];

                foreach ($localKeywords as $keyword) {
                    $normalizedKeyword = strtolower(trim($keyword));
                    if (str_contains($normalizedHeading, $normalizedKeyword)) {
                        $keywordsFound[] = $keyword;
                    }
                }

                $hasKeyword = count($keywordsFound) > 0;
                if ($hasKeyword) {
                    $headingsWithKeywords++;
                }

                $headingAnalysis[] = [
                    'text' => $headingText,
                    'level' => "h$i",
                    'keywords_found' => $keywordsFound,
                    'has_keyword' => $hasKeyword
                ];
            }

            $headingResults["h$i"] = $headingAnalysis;
        }

        // Calculate overall heading score
        $headingScore = $totalHeadings > 0 ? ($headingsWithKeywords / $totalHeadings) : 0;

        // Weight h1-h2 headings more heavily
        $h1h2WithKeywords = 0;
        $totalH1H2 = 0;

        if (isset($headingResults['h1'])) {
            $totalH1H2 += count($headingResults['h1']);
            foreach ($headingResults['h1'] as $h1) {
                if ($h1['has_keyword']) {
                    $h1h2WithKeywords++;
                }
            }
        }

        if (isset($headingResults['h2'])) {
            $totalH1H2 += count($headingResults['h2']);
            foreach ($headingResults['h2'] as $h2) {
                if ($h2['has_keyword']) {
                    $h1h2WithKeywords++;
                }
            }
        }

        $h1h2Score = $totalH1H2 > 0 ? ($h1h2WithKeywords / $totalH1H2) : 0;

        // Combined score: 70% from h1-h2, 30% from all headings
        $score = ($h1h2Score * 0.7) + ($headingScore * 0.3);
        $meetsThreshold = $score >= SeoOptimiserConfig::KEYWORD_CONTENT_AREAS['headings']['threshold'];

        return [
            'total_headings' => $totalHeadings,
            'headings_with_keywords' => $headingsWithKeywords,
            'heading_breakdown' => $headingResults,
            'score' => $score,
            'meets_threshold' => $meetsThreshold
        ];
    }

    /**
     * Analyze the first paragraph for local keyword presence
     *
     * @param DOMXPath $xpath XPath object for HTML parsing
     * @param array $localKeywords List of local keywords to check
     * @return array Analysis results
     */
    public function analyzeFirstParagraph(DOMXPath $xpath, array $localKeywords): array
    {
        $paragraphs = $xpath->query('//p');

        if ($paragraphs->length === 0) {
            return [
                'text' => '',
                'keywords_found' => [],
                'has_keyword' => false,
                'score' => 0,
                'meets_threshold' => false
            ];
        }

        // Get the first paragraph text
        $firstParagraphText = $paragraphs->item(0)->textContent;
        $normalizedParagraph = strtolower($firstParagraphText);
        $keywordsFound = [];

        foreach ($localKeywords as $keyword) {
            $normalizedKeyword = strtolower(trim($keyword));
            if (str_contains($normalizedParagraph, $normalizedKeyword)) {
                $keywordsFound[] = $keyword;
            }
        }

        $hasKeyword = count($keywordsFound) > 0;

        // For the first paragraph, check if any keyword is present
        $score = $hasKeyword ? 1.0 : 0;
        $meetsThreshold = $score >= SeoOptimiserConfig::KEYWORD_CONTENT_AREAS['first_paragraph']['threshold'];

        return [
            'text' => $firstParagraphText,
            'keywords_found' => $keywordsFound,
            'has_keyword' => $hasKeyword,
            'score' => $score,
            'meets_threshold' => $meetsThreshold
        ];
    }

    /**
     * @param $content
     * @param int $minWords
     * @param int $maxWords
     * @param int $minOccurrences
     * @param int $top
     * @return array
     */
    public function analyzeContentRepetition($content, int $minWords = 2, int $maxWords = 3, int $minOccurrences = 2, int $top = 10): array
    {
        $words = str_word_count($content, 1);
        $phraseCounts = [];
        $wordCount = count($words);

        for ($n = $minWords; $n <= $maxWords; $n++) {
            for ($i = 0; $i <= $wordCount - $n; $i++) {
                $phrase = implode(' ', array_slice($words, $i, $n));
                $phraseCounts[$phrase] = ($phraseCounts[$phrase] ?? 0) + 1;
            }
        }

        $repeated = array_filter($phraseCounts, static fn($count) => $count >= $minOccurrences);
        /**
         * remove stop-words
         */
        $repeated = array_filter($repeated, static fn($word) => !in_array($word, self::$stopWords, true), ARRAY_FILTER_USE_KEY);
        arsort($repeated);

        return array_slice($repeated, 0, $top);
    }

    /**
     * Analyze the body content for local keyword presence and distribution
     *
     * @param string $cleanContent The cleaned text content
     * @param array $localKeywords List of local keywords to check
     * @return array Analysis results
     */
    public function analyzeBodyContent(string $cleanContent, array $localKeywords): array
    {
        $contentLength = strlen($cleanContent);
        $keywordCounts = [];
        $keywordPositions = [];

        // Count occurrences and positions of each keyword
        foreach ($localKeywords as $keyword) {
            $normalizedKeyword = strtolower(trim($keyword));
            $count = 0;
            $positions = [];
            $offset = 0;

            while (($pos = stripos($cleanContent, $normalizedKeyword, $offset)) !== false) {
                $positions[] = $pos;
                $offset = $pos + strlen($normalizedKeyword);
                $count++;
            }

            if ($count > 0) {
                $keywordCounts[$keyword] = $count;
                $keywordPositions[$keyword] = $positions;
            }
        }

        // Calculate the distribution score for each keyword
        $distributionScores = [];
        foreach ($keywordPositions as $keyword => $positions) {
            if (count($positions) < 2) {
                // Can't calculate distribution with fewer than 2 occurrences
                $distributionScores[$keyword] = 0.5; // Neutral score
                continue;
            }

            // Calculate how well the keyword is distributed
            $idealGap = $contentLength / (count($positions) + 1);
            $actualGaps = [];

            for ($i = 1; $i < count($positions); $i++) {
                $actualGaps[] = $positions[$i] - $positions[$i-1];
            }

            $avgGap = array_sum($actualGaps) / count($actualGaps);
            $gapDeviation = abs($avgGap - $idealGap) / $idealGap;

            // Convert to a score (1 = perfect distribution, 0 = terrible)
            $distributionScores[$keyword] = max(0, min(1, 1 - ($gapDeviation * 0.5)));
        }

        // Calculate density for each keyword
        $wordCount = str_word_count($cleanContent);

        $densities = array_map(function ($count) use ($wordCount) {
            return ($wordCount > 0) ? ($count / $wordCount) * 100 : 0;
        }, $keywordCounts);

        // Calculate overall body score
        $hasKeywords = !empty($keywordCounts);
        $avgDensity = 0;
        $avgDistribution = 0;

        if ($hasKeywords) {
            $avgDensity = array_sum($densities) / count($densities);
            $avgDistribution = !empty($distributionScores) ? array_sum($distributionScores) / count($distributionScores) : 0;
        }

        // Optimal density is around 0.5% to 2% for local keywords
        $densityScore = 0;
        if ($avgDensity > 0) {
            if ($avgDensity < 0.2) {
                $densityScore = $avgDensity / 0.2; // Linear up to 0.2%
            } elseif ($avgDensity <= 2) {
                $densityScore = 1.0; // Optimal range
            } else {
                $densityScore = max(0, 1 - (($avgDensity - 2) / 3)); // Linear down from 2%
            }
        }

        // Combined score: 60% density, 40% distribution
        $score = $hasKeywords ? ($densityScore * 0.6) + ($avgDistribution * 0.4) : 0;
        $meetsThreshold = $score >= SeoOptimiserConfig::KEYWORD_CONTENT_AREAS['body']['threshold'];

        return [
            'keywords_found' => array_keys($keywordCounts),
            'keyword_counts' => $keywordCounts,
            'densities' => $densities,
            'avg_density' => $avgDensity,
            'distribution_scores' => $distributionScores,
            'avg_distribution' => $avgDistribution,
            'has_keyword' => $hasKeywords,
            'score' => $score,
            'meets_threshold' => $meetsThreshold
        ];
    }

    /**
     * Analyze content structure including headings, paragraphs, and media.
     *
     * @param string $content HTML content
     * @param int $wordCount Word count
     * @return array Structure analysis data
     */
    public function analyzeContentStructure(string $content, int $wordCount): array
    {
        // Count headings
        $headings = $this->getHeadingsFromContent($content);

        $headingsCounts = [];
        $totalHeadings = count($headings);

        foreach ($headings as $index => $heading) {
            $level = $heading['level'] ?? null;
            if( !$level || !preg_match('/^h[1-6]$/', $level)) {
                continue; // Skip invalid headings
            }
            // Count headings by level
            if (!isset($headingsCounts[$level])) {
                $headingsCounts[$level] = 0;
            }
            $headingsCounts[$level]++;
        }

        // Extract paragraphs
        $xp = $this->loadHTMLInDomXPath($content);
        $paragraphs = $this->getContentParagraphsWithXPath($xp);
        $paragraphCount = count($paragraphs);

        // Calculate average paragraph length
        $totalParagraphWords = 0;
        $paragraphLengths = [];

        foreach ($paragraphs as $paragraph) {
            $paragraphText = wp_strip_all_tags($paragraph->textContent);
            $wordCountInParagraph = str_word_count($paragraphText);
            $totalParagraphWords += $wordCountInParagraph;
            $paragraphLengths[] = $wordCountInParagraph;
        }

        $avgParagraphLength = $paragraphCount > 0 ? $totalParagraphWords / $paragraphCount : 0;

        // Check for images and other media
        $imgCount = substr_count($content, '<img');
        $videoCount = substr_count($content, '<video') + substr_count($content, '<iframe');

        // Calculate words per heading ratio (important for content structure)
        $wordsPerHeading = $totalHeadings > 0 ? $wordCount / $totalHeadings : $wordCount;

        // Ideal content should have a heading roughly every 300-350 words
        $headingRatioStatus = 'optimal';
        if ($wordsPerHeading > 500) {
            $headingRatioStatus = 'needs_more_headings';
        } elseif ($wordsPerHeading < 150 && $totalHeadings > 2) {
            $headingRatioStatus = 'too_many_headings';
        }

        return [
            'headings_count' => $totalHeadings,
            'headings_breakdown' => $headingsCounts,
            'paragraphs_count' => $paragraphCount,
            'avg_paragraph_length' => round($avgParagraphLength, 2),
            'paragraph_lengths' => $paragraphLengths,
            'words_per_heading' => round($wordsPerHeading, 2),
            'heading_ratio_status' => $headingRatioStatus,
            'images_count' => $imgCount,
            'videos_count' => $videoCount,
            'has_multimedia' => ($imgCount + $videoCount) > 0
        ];
    }

    /**
     * Extract headings from HTML content
     *
     * @param string $content HTML content
     * @return array Array of headings
     */
    public function extractHeadings(string $content): array
    {
        // Create a DOMDocument and load the content
        $doc = $this->loadHTMLInDomXPath($content, true);

        $headings = [];
        foreach (['h2', 'h3', 'h4', 'h5', 'h6'] as $tag) {
            foreach ($doc->getElementsByTagName($tag) as $node) {
                $headings[] = trim($node->textContent);
            }
        }
        return $headings;
    }

    /**
     * Build sections from content based on headings and following paragraphs, in document order.
     * Starts a new section at each H1–H6 and aggregates subsequent paragraph-like nodes until the next heading.
     *
     * @param string $content HTML content
     * @return array Sections with headings and their content
     */
    public function buildSections(string $content): array
    {
        $xpath = $this->loadHTMLInDomXPath($content);
        if (!$xpath) {
            return [];
        }

        // Query headings and paragraph-like nodes in document order
        $nodeList = $xpath->query(
            '//h1|//h2|//h3|//h4|//h5|//h6|//p|//div[contains(@class, "paragraph")]|//div[contains(@class, "text-block")]'
        );

        $sections = [];
        $currentSectionIndex = null;

        if ($nodeList) {
            foreach ($nodeList as $node) {
                // Skip nodes from excluded/common site parts
                if ($node instanceof \DOMElement) {
                    if ($this->isExcludedContainer($node) || $this->isCommonSitePart($node)) {
                        continue;
                    }
                }

                $nodeName = strtolower($node->nodeName);

                // When a heading is encountered, start a new section
                if (in_array($nodeName, ['h1','h2','h3','h4','h5','h6'], true)) {
                    $headingText = trim($node->textContent);
                    // Skip empty headings
                    if ($headingText === '') {
                        $currentSectionIndex = null;
                        continue;
                    }

                    $sections[] = [
                        'heading' => $headingText, // preserve original case for readability
                        'content' => '',
                        'word_count' => 0,
                    ];
                    $currentSectionIndex = array_key_last($sections);
                    continue;
                }

                // Aggregate paragraph-like content under the current section
                if ($currentSectionIndex !== null) {
                    $paragraphText = trim(wp_strip_all_tags($node->textContent));
                    if ($paragraphText !== '') {
                        // Space-separate concatenated paragraphs
                        if ($sections[$currentSectionIndex]['content'] !== '') {
                            $sections[$currentSectionIndex]['content'] .= ' ';
                        }
                        $sections[$currentSectionIndex]['content'] .= $paragraphText;
                        $sections[$currentSectionIndex]['word_count'] += str_word_count($paragraphText);
                    }
                }
            }
        }

        return $sections;
    }

    /**
     * Analyze section depth based on word count and headings
     *
     * @param string $content HTML content
     * @return array Section-depth analysis results
     */
    public function analyzeSectionDepth(string $content): array
    {
        $sections = $this->buildSections($content);

        $sectionDepthScores = [];
        $lowDepthSections = [];

        foreach ($sections as $index => $section) {
            $wordCount = $section['word_count'];

            if ($wordCount < 100) {
                $depthScore = 0.3;
                $lowDepthSections[] = $section['heading'];
            } elseif ($wordCount < 250) {
                $depthScore = 0.6;
            } else {
                $depthScore = 0.9;
            }

            $sectionDepthScores[$index] = $depthScore;
        }

        return [$sectionDepthScores, $lowDepthSections];
    }

    /**
     * Analyze subtopic coverage based on content structure and keywords
     *
     * @param string $content Raw HTML content
     * @param array $secondaryKeywords Secondary keywords
     * @return array Subtopic coverage analysis results
     */
    public function analyzeSubtopicCoverage(
        string $content,
        array $secondaryKeywords
    ): array {
        $headings = $this->getHeadingsFromContent($content);
        $subtopicsCount = count($headings);

        $coveredKeywords = $this->checkKeywordCoverageInHeadings($content, $secondaryKeywords);
        $keywordCoverageRatio = count($secondaryKeywords) > 0 ? count($coveredKeywords) / count($secondaryKeywords) : 1;

        $sections = $this->buildSections($content);

        [$sectionDepthScores, $lowDepthSections] = $this->analyzeSectionDepth($content);

        $avgSectionDepth = count($sectionDepthScores) > 0 ? array_sum($sectionDepthScores) / count($sectionDepthScores) : 0;
        $subtopicWeight = min(1, $subtopicsCount / 5);

        $coverageScore = ($avgSectionDepth * 0.6 + $keywordCoverageRatio * 0.4) * $subtopicWeight;

        return [
            'subtopics_count' => $subtopicsCount,
            'keywords_covered' => count($coveredKeywords),
            'total_secondary_keywords' => count($secondaryKeywords),
            'keyword_coverage_ratio' => $keywordCoverageRatio,
            'section_analysis' => array_map(function ($section, $score) {
                return [
                    'heading' => $section['heading'],
                    'word_count' => $section['word_count'],
                    'depth_score' => $score
                ];
            }, $sections, $sectionDepthScores),
            'low_depth_sections' => $lowDepthSections,
            'coverage_score' => $coverageScore
        ];
    }

    /**
     * Extract the first paragraph from HTML content.
     *
     * @param string $content The HTML content
     * @return string The extracted first paragraph text
     */
    public function extractFirstParagraph(string $content): string
    {
        // First, try to match the first paragraph tag
        if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $content, $matches)) {
            return wp_strip_all_tags($matches[0]);
        }

        // If all else fails, just get the first chunk of text
        return $this->cleanContent(substr($content, 0, 500));
    }

    /**
     * Extract approximately the first N words from the text.
     *
     * @param string $text The text to extract words from
     * @param int $wordCount The number of words to extract
     * @return string The extracted words
     */
    public function extractFirstWords(string $text, int $wordCount = 150): string
    {
        $words = str_word_count($text, 2);
        $keys = array_keys($words);

        if (count($keys) <= $wordCount) {
            return $text;
        }

        // Get position of the Nth word
        $position = $keys[$wordCount];

        // Extract text up to this position
        return substr($text, 0, $position);
    }

    /**
     * Extract meta-title from HTML.
     *
     * @param DOMXPath $xpath The XPath object for the HTML document
     * @return string The meta-title or empty string if not found
     */
    public function extractMetaTitle(DOMXPath $xpath): string
    {
        // Try the standard title tag first
        $titleNodes = $xpath->query('//title');
        if ($titleNodes->length > 0) {
            return trim($titleNodes->item(0)->textContent);
        }

        // Try Open Graph title as a fallback
        $ogTitleNodes = $xpath->query('//meta[@property="og:title"]');
        if ($ogTitleNodes->length > 0) {
            /* @var mixed $titleNodeItem */
            $titleNodeItem = $ogTitleNodes->item(0);
            return trim($titleNodeItem->getAttribute('content'));
        }

        return '';
    }

    /**
     * Extract meta description from HTML.
     *
     * @param DOMXPath $xpath The XPath object for the HTML document
     * @return string The meta-description or empty string if not found
     */
    public function extractMetaDescription(DOMXPath $xpath): string
    {
        // Try a standard meta-description first
        $descNodes = $xpath->query('//meta[@name="description"]');
        if ($descNodes->length > 0) {
            /* @var mixed $descNodeItem */
            $descNodeItem = $descNodes->item(0);
            return trim($descNodeItem->getAttribute('content'));
        }

        // Try Open Graph description as a fallback
        $ogDescNodes = $xpath->query('//meta[@property="og:description"]');
        if ($ogDescNodes->length > 0) {
            /* @var mixed $ogDescNodeItem */
            $ogDescNodeItem = $ogDescNodes->item(0);
            return trim($ogDescNodeItem->getAttribute('content'));
        }

        return '';
    }

    /**
     * Analyzes the heading structure and hierarchy in the content.
     *
     * @param string $htmlContent The HTML content to analyze
     * @param int $totalWordCount The total word count of the content
     * @return array Analysis of heading structure
     */
    public function analyzeHeadingStructure(string $htmlContent, int $totalWordCount): array
    {
        // Get headings in document order for proper hierarchy analysis
        $headingsInOrder = $this->getHeadingsFromContent($htmlContent);
        
        // Initialize counters and arrays
        $headingCounts = ['h1' => 0, 'h2' => 0, 'h3' => 0, 'h4' => 0, 'h5' => 0, 'h6' => 0];
        $headingTexts = ['h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => []];
        $totalHeadings = count($headingsInOrder);
        $hasH1 = false;
        $hierarchyIssues = [];
        $emptyHeadings = [];
        $longHeadings = [];

        // Process headings in document order for proper hierarchy validation
        $previousLevel = 0;
        foreach ($headingsInOrder as $index => $heading) {
            $level = $heading['level'];
            $text = trim($heading['text']);
            $levelNumber = (int) substr($level, 1);
            
            // Count headings by level
            $headingCounts[$level]++;
            $headingTexts[$level][] = $text;
            
            // Check for H1
            if ($levelNumber === 1) {
                $hasH1 = true;
            }
            
            // Check for empty or very short headings
            if (empty($text) || strlen($text) < 3) {
                $emptyHeadings[] = "Empty or very short {$level}";
            }
            
            // Check for overly long headings
            if (strlen($text) > SeoOptimiserConfig::CONTENT_MAX_OPTIMAL_HEADING_LENGTH) {
                $longHeadings[] = [
                    'level' => strtoupper($level),
                    'text' => $text,
                    'length' => strlen($text),
                ];
            }
            
            // Validate hierarchy in document order
            if ($previousLevel === 0) {
                // First heading - should ideally be H1, but not required
                $previousLevel = $levelNumber;
            } else {
                // Check for proper nesting
                if ($levelNumber > $previousLevel + 1) {
                    // Skipped levels (e.g., H1 -> H3)
                    $hierarchyIssues[] = "Heading level jumps from H{$previousLevel} to H{$levelNumber} at position " . ($index + 1);
                } elseif ($levelNumber <= $previousLevel) {
                    // Same level or going back up - this is acceptable
                    // Update previous level to current for next iteration
                    $previousLevel = $levelNumber;
                } else {
                    // Normal progression (e.g., H1 -> H2, H2 -> H3)
                    $previousLevel = $levelNumber;
                }
            }
        }

        // Calculate headings per 1000 words
        $headingsDensity = $totalWordCount > 0 ? ($totalHeadings / $totalWordCount) * 1000 : 0;

        // Determine if the heading structure is adequate
        $hasTooFewHeadings = $headingsDensity < SeoOptimiserConfig::CONTENT_MIN_RECOMMENDED_HEADINGS_PER_1000_WORDS;
        $hasTooManyHeadings = $headingsDensity > SeoOptimiserConfig::CONTENT_MAX_RECOMMENDED_HEADINGS_PER_1000_WORDS;

        // Content is likely well-structured based on headings
        $isWellStructured = $hasH1 && !$hasTooFewHeadings && !$hasTooManyHeadings && empty($hierarchyIssues);

        return [
            'total_headings' => $totalHeadings,
            'headings_per_1000_words' => round($headingsDensity, 2),
            'has_h1' => $hasH1,
            'heading_counts' => $headingCounts,
            'heading_texts' => $headingTexts,
            'hierarchy_issues' => $hierarchyIssues,
            'empty_headings' => $emptyHeadings,
            'long_headings' => $longHeadings,
            'has_too_few_headings' => $hasTooFewHeadings,
            'has_too_many_headings' => $hasTooManyHeadings,
            'is_well_structured' => $isWellStructured,
            'headings_sequence' => $headingsInOrder, // Added for debugging/analysis
        ];
    }

    /**
     * Analyzes paragraph structure and length.
     *
     * @param string $htmlContent The HTML content to analyze
     * @return array Analysis of paragraph structure
     */
    public function analyzeParagraphsStructure(string $htmlContent): array
    {
        // Create a DOMXPath object for querying the HTML
        $xpath = $this->loadHTMLInDomXPath($htmlContent);

        $paragraphNodes = $this->getContentParagraphsWithXPath($xpath);
        $paragraphCount = count($paragraphNodes);

        $wordCounts = [];
        $longParagraphs = [];
        $veryLongParagraphs = [];
        $totalWords = 0;

        // Analyze each paragraph
        foreach ($paragraphNodes as $paragraph) {
            $text = trim($paragraph->textContent);

            // Skip empty paragraphs
            if (empty($text)) {
                continue;
            }

            $wordCount = str_word_count($text);
            $wordCounts[] = $wordCount;
            $totalWords += $wordCount;

            // Track long and very long paragraphs
            if ($wordCount > SeoOptimiserConfig::CONTENT_MAX_ACCEPTABLE_PARAGRAPH_LENGTH) {
                $veryLongParagraphs[] = [
                    'text' => substr($text, 0, 100) . '...',
                    'word_count' => $wordCount,
                ];
            } elseif ($wordCount > SeoOptimiserConfig::CONTENT_MAX_OPTIMAL_PARAGRAPH_LENGTH) {
                $longParagraphs[] = [
                    'text' => substr($text, 0, 100) . '...',
                    'word_count' => $wordCount,
                ];
            }
        }

        // Calculate average paragraph length
        $avgParagraphLength = $paragraphCount > 0 ? $totalWords / $paragraphCount : 0;

        // Count paragraphs by length category
        $paragraphLengthCategories = [
            'very_short' => 0,  // 1-20 words
            'short' => 0,       // 21-40 words
            'medium' => 0,      // 41-75 words
            'long' => 0,        // 76-120 words
            'very_long' => 0,   // 120+ words
        ];

        foreach ($wordCounts as $count) {
            if ($count <= 20) {
                $paragraphLengthCategories['very_short']++;
            } elseif ($count <= 40) {
                $paragraphLengthCategories['short']++;
            } elseif ($count <= SeoOptimiserConfig::CONTENT_MAX_OPTIMAL_PARAGRAPH_LENGTH) {
                $paragraphLengthCategories['medium']++;
            } elseif ($count <= SeoOptimiserConfig::CONTENT_MAX_ACCEPTABLE_PARAGRAPH_LENGTH) {
                $paragraphLengthCategories['long']++;
            } else {
                $paragraphLengthCategories['very_long']++;
            }
        }

        // Define the ratio of long/very long paragraphs
        $longParagraphCount = count($longParagraphs);
        $veryLongParagraphCount = count($veryLongParagraphs);
        $totalLongParagraphs = $longParagraphCount + $veryLongParagraphCount;
        $longParagraphRatio = $paragraphCount > 0 ? $totalLongParagraphs / $paragraphCount : 0;

        return [
            'total_paragraphs' => $paragraphCount,
            'avg_paragraph_length' => round($avgParagraphLength, 2),
            'paragraph_length_categories' => $paragraphLengthCategories,
            'long_paragraphs' => $longParagraphs,
            'very_long_paragraphs' => $veryLongParagraphs,
            'long_paragraph_ratio' => round($longParagraphRatio * 100, 2),
            'has_too_many_long_paragraphs' => $longParagraphRatio > 0.3,
        ];
    }

    /**
     * Analyzes the use of bullet points (lists) in content.
     * Focus only on content-relevant lists and exclude common site parts
     * like header, navigation, footer, sidebars, widgets, etc.
     *
     * @param string $htmlContent The HTML content to analyze
     * @param int $totalWordCount The total word count of the content
     * @param string $cleanContent The cleaned text content for context analysis
     * @return array Analysis of bullet point usage
     */
    public function analyzeBulletPointsStructure(string $htmlContent, int $totalWordCount, string $cleanContent): array
    {
        $xpath = $this->loadHTMLInDomXPath($htmlContent);
        if (!$xpath) {
            return [
                'total_lists' => 0,
                'unordered_lists' => 0,
                'ordered_lists' => 0,
                'total_list_items' => 0,
                'avg_items_per_list' => 0,
                'list_opportunities_detected' => false,
                'list_opportunity_phrases' => [],
                'needs_more_lists' => false,
            ];
        }

        // Helper to detect common site part by attribute hints (class/id/role/aria-label)
        $hasCommonUiMarker = function (?\DOMElement $el): bool {
            if (!$el) return false;
            $attr = strtolower(trim(
                ($el->getAttribute('class') ?? '') . ' ' .
                ($el->getAttribute('id') ?? '') . ' ' .
                ($el->getAttribute('role') ?? '') . ' ' .
                ($el->getAttribute('aria-label') ?? '')
            ));
            if ($attr === '') return false;
            $markers = [
                'nav','menu','breadcrumb','breadcrumbs','pagination','pager','tags','tagcloud',
                'categories','category','archive','social','share','header','footer','sidebar',
                'widget','comment','comments','author','meta','toc','sitemap'
            ];
            foreach ($markers as $m) {
                if (str_contains($attr, $m)) {
                    return true;
                }
            }
            return false;
        };

        // Check if a node is inside excluded/common areas
        $isInExcludedContext = function (\DOMElement $node) use ($hasCommonUiMarker): bool {
            // Direct checks via existing helpers
            if ($this->isExcludedContainer($node) || $this->isCommonSitePart($node)) {
                return true;
            }
            // Ascend through ancestors and check structural and marker hints
            $el = $node;
            $depth = 0;
            while ($el && $el instanceof \DOMElement && $depth < 8) {
                $name = strtolower($el->nodeName);
                if (in_array($name, ['nav','header','footer','aside'], true)) {
                    return true;
                }
                if ($hasCommonUiMarker($el)) {
                    return true;
                }
                $el = $el->parentNode instanceof \DOMElement ? $el->parentNode : null;
                $depth++;
            }
            return false;
        };

        // Detect lists that are likely navigation/menus/breadcrumbs etc.
        $isNavLikeList = function (\DOMElement $list) use ($xpath): bool {
            // Only immediate LI children to avoid double counting nested lists
            $items = $xpath->query('./li', $list);
            if (!$items) return false;
            $liCount = $items->length;
            if ($liCount === 0) return false;

            $anchorOnly = 0;
            $totalTextWords = 0;
            foreach ($items as $li) {
                if (!$li instanceof \DOMElement) { continue; }
                $text = trim(wp_strip_all_tags($li->textContent));
                $totalTextWords += str_word_count($text);
                $anchors = $li->getElementsByTagName('a');
                if ($anchors->length >= 1 && str_word_count($text) <= 3) {
                    $anchorOnly++;
                }
            }

            $attr = strtolower(trim(
                ($list->getAttribute('class') ?? '') . ' ' .
                ($list->getAttribute('id') ?? '') . ' ' .
                ($list->getAttribute('role') ?? '')
            ));
            $looksLikeMenu = $attr !== '' && (
                str_contains($attr, 'nav') || str_contains($attr, 'menu') ||
                str_contains($attr, 'breadcrumb') || str_contains($attr, 'pagination') ||
                str_contains($attr, 'pager') || str_contains($attr, 'tags') ||
                str_contains($attr, 'social') || str_contains($attr, 'share')
            );

            if ($liCount >= 2 && ($anchorOnly / $liCount) >= 0.8) {
                return true;
            }
            if ($looksLikeMenu) {
                return true;
            }
            if ($liCount >= 2 && $totalTextWords < 5) {
                return true;
            }

            return false;
        };

        // Iterate all UL/OL but count only eligible ones
        $allLists = $xpath->query('//ul|//ol');
        $eligibleUnordered = 0;
        $eligibleOrdered = 0;
        $eligibleItems = 0;

        if ($allLists) {
            foreach ($allLists as $list) {
                if (!$list instanceof \DOMElement) { continue; }

                // Exclude lists in common site parts or excluded containers
                if ($isInExcludedContext($list)) {
                    continue;
                }

                // Exclude navigation-like lists
                if ($isNavLikeList($list)) {
                    continue;
                }

                // Count immediate LI children only
                $items = $xpath->query('./li', $list);
                $itemCount = $items ? $items->length : 0;
                if ($itemCount === 0) {
                    continue;
                }

                // Require at least 2 meaningful items (>= 2 words) to be content-relevant
                $meaningful = 0;
                foreach ($items as $li) {
                    $text = trim(wp_strip_all_tags($li->textContent));
                    if (str_word_count($text) >= 2) {
                        $meaningful++;
                    }
                }
                if ($meaningful < 2) {
                    continue;
                }

                $eligibleItems += $itemCount;
                $nodeName = strtolower($list->nodeName);
                if ($nodeName === 'ul') {
                    $eligibleUnordered++;
                } elseif ($nodeName === 'ol') {
                    $eligibleOrdered++;
                }
            }
        }

        $totalLists = $eligibleUnordered + $eligibleOrdered;
        $avgItemsPerList = $totalLists > 0 ? $eligibleItems / $totalLists : 0;

        // Contextual recommendations unchanged
        $listsRecommended = false;
        $listOpportunities = [];
        foreach (SeoOptimiserConfig::LIST_RECOMMENDATION_KEYWORDS_PATTERNS as $pattern) {
            if (preg_match($pattern, $cleanContent, $matches)) {
                $listsRecommended = true;
                $listOpportunities[] = $matches[0];
            }
        }

        $needsMoreLists = ($totalWordCount > 1000 && $totalLists < SeoOptimiserConfig::CONTENT_MIN_RECOMMENDED_BULLET_POINTS) ||
            ($listsRecommended && $totalLists === 0);

        return [
            'total_lists' => $totalLists,
            'unordered_lists' => $eligibleUnordered,
            'ordered_lists' => $eligibleOrdered,
            'total_list_items' => $eligibleItems,
            'avg_items_per_list' => round($avgItemsPerList, 2),
            'list_opportunities_detected' => $listsRecommended,
            'list_opportunity_phrases' => $listOpportunities,
            'needs_more_lists' => $needsMoreLists,
        ];
    }

    /**
     * Extracts the meta-title from HTML content.
     *
     * @param string $html The HTML content to extract the title from
     * @return string|null The extracted title or null if not found
     */
    public function extractMetaTitleFromHTML(string $html): ?string {

        // Create a DOMDocument and load the HTML
        $dom = $this->loadHTMLInDomXPath($html, true);

        // Try the < title > tag first
        $titleTags = $dom->getElementsByTagName('title');
        if ($titleTags->length > 0) {
            return trim($titleTags->item(0)->textContent);
        }

        // Fallback: <meta name="title">
        foreach ($dom->getElementsByTagName('meta') as $meta) {
            $name = $meta->getAttribute('name');
            if (strtolower($name) === 'title') {
                return trim($meta->getAttribute('content'));
            }
        }

        // Fallback: <meta property="og:title">
        foreach ($dom->getElementsByTagName('meta') as $meta) {
            $property = $meta->getAttribute('property');
            if (strtolower($property) === 'og:title') {
                return trim($meta->getAttribute('content'));
            }
        }

        return null;
    }

    /**
     * Extracts the meta-description from HTML content.
     *
     * @param string $html The HTML content to extract the description from
     * @return string|null The extracted description or null if not found
     */
    public function extractMetaDescriptionFromHTML(string $html): ?string
    {
        // Create a DOMDocument and load the HTML
        $dom = $this->loadHTMLInDomXPath($html, true);

        foreach ($dom->getElementsByTagName('meta') as $meta) {
            $name = strtolower($meta->getAttribute('name'));
            $property = strtolower($meta->getAttribute('property'));

            if ($name === 'description' || $property === 'og:description') {
                return trim($meta->getAttribute('content'));
            }
        }

        return null;
    }

    /**
     * Gets the meta-title from WordPress meta-data as a fallback
     * This checks various SEO plugin meta-fields and falls back to the post-title
     *
     * @param int $postId The post-ID
     * @return string The meta-title or empty string if not found
     */
    public function getFallbackMetaTitle(int $postId): string
    {
        // Check common meta-fields from SEO plugins
        $metaKeys = [
            MetaTags::META_SEO_TITLE,
            '_yoast_wpseo_title',
            '_aioseo_title',
            '_rank_math_title',
            '_seopress_titles_title',
        ];

        foreach ($metaKeys as $metaKey) {
            $title = get_post_meta($postId, $metaKey, true);
            if (!empty($title)) {
                return $title;
            }
        }

        // Fall back to the post-title
        return $this->getPostTitle($postId);
    }

    /**
     * Gets the meta-description from WordPress meta-data as a fallback.
     * Checks various SEO plugin fields, then falls back to post-excerpt or generated excerpt.
     *
     * @param int $postId The post-ID
     * @return string The meta-description or empty string if not found
     */
    public function getFallbackMetaDescription(int $postId): string
    {
        $metaKeys = [
            MetaTags::META_SEO_DESCRIPTION,
            '_yoast_wpseo_metadesc',
            '_aioseo_description',
            '_rank_math_description',
            '_seopress_titles_desc',
        ];

        foreach ($metaKeys as $metaKey) {
            $description = get_post_meta($postId, $metaKey, true);
            if (!empty($description)) {
                return $description;
            }
        }

        // Fallback to post excerpt or auto-generated content
        $excerpt = get_the_excerpt($postId);
        if (!empty($excerpt)) {
            return $excerpt;
        }

        $content = get_post_field('post_content', $postId);
        return wp_trim_words(wp_strip_all_tags($content), 30, '...');
    }

    /**
     * Extract all links from HTML content.
     *
     * @param string $html The HTML content
     * @return array Array of links with url, text, and is_internal flag
     */
    public function extractLinksFromHtml(string $html): array
    {
        $links = [];

        // Create a DOMDocument and load the HTML
        $xpath = $this->loadHTMLInDomXPath('<div>' . $html . '</div>');

        $linksElements = $xpath->query('//a[@href]');
        if ($linksElements) {
            foreach ($linksElements as $linkElement) {
                $href = $linkElement->getAttribute('href');
                if ($href) {
                    $text = $linkElement->textContent;

                    // Skip empty, javascript, or mailto links
                    if (empty($href) || str_starts_with($href, 'javascript:') ||
                        str_starts_with($href, 'mailto:') || $href === '#') {
                        continue;
                    }

                    // Normalize relative URLs
                    if (!str_starts_with($href, 'http') && !str_starts_with($href, '//')) {
                        // If it's a root-relative URL (starts with /)
                        if (str_starts_with($href, '/')) {
                            $siteUrl = $this->getSiteUrl();
                            $href = rtrim($siteUrl, '/') . $href;
                        } else {
                            continue;
                        }
                    }

                    $links[] = [
                        'url' => $href,
                        'text' => trim($text),
                        'is_internal' => $this->isInternalLink($href)
                    ];
                }
            }
        }

        return $links;
    }

    /**
     * Get domains to exclude from link checking.
     * Common social media and potentially dynamic sites are excluded by default.
     *
     * @return array List of domains to exclude
     */
    public function getCommonDomainExclusions(): array
    {
        // Common domains to exclude (social media, etc.)
        $default_exclusions = [
            'facebook.com',
            'twitter.com',
            'linkedin.com',
            'instagram.com',
            'youtube.com',
            'pinterest.com'
        ];

        // Allow customization via filter if in WordPress context
        if (function_exists('apply_filters')) {
            return apply_filters('rankingcoach_link_checker_exclusions', $default_exclusions);
        }

        return $default_exclusions;
    }
}
