<?php /** @noinspection RegExpRedundantEscape */
/** @noinspection PhpUsageOfSilenceOperatorInspection */
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
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use InvalidArgumentException;
use Throwable;

/**
 * Class MultimediaInclusionCheckOperation
 *
 * This operation validates the presence and optimization of multimedia elements in content.
 * It analyzes images, videos, and other media elements to ensure they are properly optimized
 * with alt tags, correct placement, and keyword relevance to improve SEO performance.
 */
#[SeoMeta(
    name: 'Multimedia Inclusion Check',
    weight: WeightConfiguration::WEIGHT_MULTIMEDIA_INCLUSION_CHECK_OPERATION,
    description: 'Analyzes multimedia elements (images, videos) in the content for proper optimization, including alt tags, captions, and keyword relevance. Provides suggestions for improving multimedia SEO.',
)]
class MultimediaInclusionCheckOperation extends Operation implements OperationInterface
{
    /**
     * Performs multimedia inclusion analysis for the given post-ID.
     *
     * @return array|null The check results or null if the post-ID is invalid.
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get content type and raw HTML content
        $postType = $this->contentProvider->getPostType($postId);
        $content = $this->contentProvider->getContent($postId);

        // Skip analysis if no content
        if (empty($content)) {
            return [
                'success' => false,
                'message' => __('No content found for analysis', 'beyondseo')
            ];
        }

        // Parse the HTML with DOM
        $dom = new DOMDocument();

        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_encode_numericentity($content, [0x80, 0x10FFFF, 0, ~0], 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $xpath = new DOMXPath($dom);

        // Get content details for calculation
        $cleanContent = $this->contentProvider->cleanContent($content);
        $wordCount = $this->contentProvider->getWordCount($cleanContent);

        // Get target keywords
        $primaryKeyword = $this->contentProvider->getPrimaryKeyword($postId);
        $secondaryKeywords = $this->contentProvider->getSecondaryKeywords($postId);

        // Perform comprehensive multimedia analysis
        $mediaAnalysis = $this->analyzeMultimediaElements($xpath, $wordCount);
        $keywordUsage = $this->analyzeKeywordInMedia($xpath, $primaryKeyword, $secondaryKeywords);
        $distributionAnalysis = $this->analyzeMediaDistribution($xpath, $mediaAnalysis['total_count']);

        // Calculate overall score based on different factors
        $overallScore = $this->calculateMultimediaScore($mediaAnalysis, $keywordUsage, $distributionAnalysis);

        // Prepare result data
        return [
            'success' => true,
            'message' => __('Multimedia analysis completed successfully', 'beyondseo'),
            'word_count' => $wordCount,
            'content_type' => $postType,
            'media_analysis' => $mediaAnalysis,
            'keyword_usage' => $keywordUsage,
            'distribution_analysis' => $distributionAnalysis,
            'overall_score' => $overallScore
        ];
    }

    /**
     * Analyze multimedia elements in the content
     *
     * @param DOMXPath $xpath XPath object for the DOM
     * @param int $wordCount Total word count of the content
     * @return array Detailed analysis of multimedia elements
     */
    private function analyzeMultimediaElements(DOMXPath $xpath, int $wordCount): array
    {
        // Initialize results structure
        $analysis = [
            'total_count' => 0,
            'media_to_text_ratio' => 0,
            'types' => [],
            'alt_tags' => [
                'total_images' => 0,
                'with_alt' => 0,
                'empty_alt' => 0,
                'missing_alt' => 0,
                'ratio' => 0
            ],
            'captions' => [
                'total_media_with_captions' => 0,
                'ratio' => 0
            ]
        ];

        // Initialize counters for each media type
        foreach (SeoOptimiserConfig::MEDIA_TYPES as $type => $selectors) {
            $analysis['types'][$type] = [
                'count' => 0,
                'details' => []
            ];
        }

        $totalCaptionEligibleMedia = 0;

        // Analyze each media type
        foreach (SeoOptimiserConfig::MEDIA_TYPES as $type => $selectors) {
            foreach ($selectors as $selector) {
                // Query for media elements
                $elements = $this->safeXPathQuery($xpath, '//' . $selector);

                if ($elements && $elements->length > 0) {
                    foreach ($elements as $element) {
                        // Increment counters
                        $analysis['types'][$type]['count']++;
                        $analysis['total_count']++;

                        // Store element details
                        $elementDetails = $this->extractMediaElementDetails($element, $type);
                        $analysis['types'][$type]['details'][] = $elementDetails;

                        // Check for alt tags on images
                        if ($type === 'image') {
                            $analysis['alt_tags']['total_images']++;

                            if ($element->hasAttribute('alt')) {
                                $alt = $element->getAttribute('alt');
                                if (trim($alt) !== '') {
                                    $analysis['alt_tags']['with_alt']++;
                                } else {
                                    $analysis['alt_tags']['empty_alt']++;
                                }
                            } else {
                                $analysis['alt_tags']['missing_alt']++;
                            }
                        }

                        // Check for captions (figure/figcaption or nearby text)
                        $hasCaption = $this->mediaElementHasCaption($xpath, $element);
                        if ($hasCaption) {
                            $analysis['captions']['total_media_with_captions']++;
                        }

                        // Not all media types traditionally have captions
                        if (in_array($type, ['image', 'video'])) {
                            $totalCaptionEligibleMedia++;
                        }
                    }
                }
            }
        }

        // Calculate ratios and scores
        if ($wordCount > 0) {
            $analysis['media_to_text_ratio'] = round(($analysis['total_count'] * 100) / $wordCount, 2);
        }

        if ($analysis['alt_tags']['total_images'] > 0) {
            $analysis['alt_tags']['ratio'] = $analysis['alt_tags']['with_alt'] / $analysis['alt_tags']['total_images'];
        }

        if ($totalCaptionEligibleMedia > 0) {
            $analysis['captions']['ratio'] = $analysis['captions']['total_media_with_captions'] / $totalCaptionEligibleMedia;
        }

        // Calculate optimization score (0-1) based on alt tags and captions
        $altScore = $analysis['alt_tags']['total_images'] > 0 ?
            $analysis['alt_tags']['with_alt'] / $analysis['alt_tags']['total_images'] : 0;

        $captionScore = $totalCaptionEligibleMedia > 0 ?
            $analysis['captions']['total_media_with_captions'] / $totalCaptionEligibleMedia : 0;

        // Alt tags are more important for SEO than captions (70/30 weight)
        $analysis['optimization_score'] = ($altScore * 0.7) + ($captionScore * 0.3);

        return $analysis;
    }

    /**
     * Safely perform an XPath query with error handling
     *
     * @param DOMXPath $xpath The XPath object
     * @param string $query The XPath query to execute
     * @return DOMNodeList|false The node list or false on error
     */
    private function safeXPathQuery(DOMXPath $xpath, string $query): DOMNodeList|false
    {
        try {
            // Validate the query before execution
            if (!$this->isValidXPath($query)) {
                throw new InvalidArgumentException("Invalid XPath query: $query");
            }
            return $xpath->query($query);
        } catch (Throwable $e) {
            // Fallback to a simpler query if the complex one fails
            /** @noinspection RegExpRedundantEscape */
            $simpleQuery = preg_replace('/\[[^\]]+\]/', '', $query);
            if ($simpleQuery !== $query && $this->isValidXPath($simpleQuery)) {
                try {
                    libxml_use_internal_errors(true);
                    $result = $xpath->query($simpleQuery);
                    libxml_clear_errors();
                    libxml_use_internal_errors(false);
                    return $result;
                } catch (Throwable $e) {
                    libxml_clear_errors();
                    libxml_use_internal_errors(false);
                    return false;
                }
            }
            return false;
        }
    }

    /**
     * Validate an XPath query for basic syntax correctness
     *
     * @param string $query The XPath query to validate
     * @return bool True if the query is valid, false otherwise
     */
    private function isValidXPath(string $query): bool
    {
        // Basic validation to ensure the query is not empty and does not contain invalid characters
        return !empty($query) && preg_match('/^[a-zA-Z0-9_\-\/\[\]\@\*\(\)\.\:\s]+$/', $query);
    }

    /**
     * Extract details from a media element
     *
     * @param DOMElement $element The DOM element representing media
     * @param string $type The type of media element
     * @return array Details of the media element
     */
    private function extractMediaElementDetails(DOMElement $element, string $type): array
    {
        $details = [
            'tag' => $element->tagName,
            'type' => $type,
            'src' => $element->getAttribute('src') ?: $element->getAttribute('data-src'),
            'alt' => $element->getAttribute('alt') ?? '',
            'title' => $element->getAttribute('title') ?? '',
            'width' => $element->getAttribute('width') ?? '',
            'height' => $element->getAttribute('height') ?? '',
            'class' => $element->getAttribute('class') ?? '',
            'id' => $element->getAttribute('id') ?? '',
            'has_lazy_loading' => $this->elementHasLazyLoading($element)
        ];

        // Check for responsive attributes specific to images
        if ($type === 'image') {
            $details['has_srcset'] = $element->hasAttribute('srcset');
            $details['has_sizes'] = $element->hasAttribute('sizes');
            $details['is_responsive'] = $details['has_srcset'] ||
                $details['has_sizes'] ||
                str_contains($details['class'], 'responsive') ||
                str_contains($details['class'], 'fluid');
        }

        // For videos, check autoplay and controls
        if ($type === 'video' && $element->tagName === 'video') {
            $details['has_controls'] = $element->hasAttribute('controls');
            $details['has_autoplay'] = $element->hasAttribute('autoplay');
        }

        return $details;
    }

    /**
     * Check if a media element has lazy loading implemented
     *
     * @param DOMElement $element The DOM element to check
     * @return bool True if the element has lazy loading
     */
    private function elementHasLazyLoading(DOMElement $element): bool
    {
        // Check for standard loading attribute
        if ($element->hasAttribute('loading') && $element->getAttribute('loading') === 'lazy') {
            return true;
        }

        // Check for common lazy loading classes
        $class = $element->getAttribute('class');
        if (str_contains($class, 'lazy') ||
            str_contains($class, 'lazyload')) {
            return true;
        }

        // Check for data-src attribute (common lazy loading pattern)
        if ($element->hasAttribute('data-src') &&
            (!$element->hasAttribute('src') ||
                $element->getAttribute('src') === '' ||
                str_starts_with($element->getAttribute('src'), 'data:image'))) {
            return true;
        }

        return false;
    }

    /**
     * Check if a media element has a caption
     *
     * @param DOMXPath $xpath The XPath object
     * @param DOMElement $element The DOM element to check
     * @return bool True if the element has a caption
     */
    private function mediaElementHasCaption(DOMXPath $xpath, DOMElement $element): bool
    {
        // Check if inside a figure with a figcaption
        /** @var DOMElement|null $parent */
        $parent = $element->parentNode;
        if ($parent && $parent->nodeName === 'figure') {
            $figcaptions = $this->safeXPathQuery($xpath, './/figcaption');
            if ($figcaptions && $figcaptions->length > 0) {
                return true;
            }
        }

        // Check for caption in WordPress-style caption shortcode result
        if ($parent && ($parent->nodeName === 'div' || $parent->nodeName === 'figure')) {
            $class = $parent->getAttribute('class');
            if (str_contains($class, 'wp-caption') ||
                str_contains($class, 'caption')) {

                // Look for caption text in p or figcaption
                $captionText = $this->safeXPathQuery($xpath, './p[@class="wp-caption-text"] | ./figcaption');
                if ($captionText && $captionText->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Analyze keyword usage in media elements
     *
     * @param DOMXPath $xpath The XPath object
     * @param string $primaryKeyword The primary keyword
     * @param array $secondaryKeywords Array of secondary keywords
     * @return array Analysis of keyword usage in media
     */
    private function analyzeKeywordInMedia(
        DOMXPath $xpath,
        string $primaryKeyword,
        array $secondaryKeywords
    ): array
    {
        $analysis = [
            'primary_keyword' => [
                'in_alt_tags' => 0,
                'in_captions' => 0
            ],
            'secondary_keywords' => [
                'in_alt_tags' => 0,
                'in_captions' => 0
            ],
            'keyword_relevance_score' => 0
        ];

        // Skip if no keywords defined
        if (empty($primaryKeyword) && empty($secondaryKeywords)) {
            return $analysis;
        }

        // Check images with alt tags
        $images = $this->safeXPathQuery($xpath, '//img[@alt]');
        if ($images && $images->length > 0) {
            foreach ($images as $image) {
                $alt = strtolower($image->getAttribute('alt'));

                // Check primary keyword
                if (!empty($primaryKeyword) && str_contains($alt, strtolower($primaryKeyword))) {
                    $analysis['primary_keyword']['in_alt_tags']++;
                }

                // Check secondary keywords
                foreach ($secondaryKeywords as $keyword) {
                    if (!empty($keyword) && str_contains($alt, strtolower($keyword))) {
                        $analysis['secondary_keywords']['in_alt_tags']++;
                        break; // Count once per image for secondary keywords
                    }
                }
            }
        }

        // Check captions
        $captions = $this->safeXPathQuery($xpath, '//figcaption | //p[contains(@class, "wp-caption-text")]');
        if ($captions && $captions->length > 0) {
            foreach ($captions as $caption) {
                $captionText = strtolower($caption->textContent);

                // Check primary keyword
                if (!empty($primaryKeyword) && str_contains($captionText, strtolower($primaryKeyword))) {
                    $analysis['primary_keyword']['in_captions']++;
                }

                // Check secondary keywords
                foreach ($secondaryKeywords as $keyword) {
                    if (!empty($keyword) && str_contains($captionText, strtolower($keyword))) {
                        $analysis['secondary_keywords']['in_captions']++;
                        break; // Count once per caption for secondary keywords
                    }
                }
            }
        }

        // Calculate keyword relevance score
        $keywordFactors = 0;
        $relevancePoints = 0;

        // Primary keyword factors
        if (!empty($primaryKeyword)) {
            $keywordFactors += 2; // Alt tags and captions
            if ($analysis['primary_keyword']['in_alt_tags'] > 0) {
                $relevancePoints += 1;
            }
            if ($analysis['primary_keyword']['in_captions'] > 0) {
                $relevancePoints += 1;
            }
        }

        // Secondary keywords factors
        if (!empty($secondaryKeywords)) {
            $keywordFactors += 2; // Alt tags and captions
            if ($analysis['secondary_keywords']['in_alt_tags'] > 0) {
                $relevancePoints += 1;
            }
            if ($analysis['secondary_keywords']['in_captions'] > 0) {
                $relevancePoints += 1;
            }
        }

        // Calculate final score
        $analysis['keyword_relevance_score'] = $keywordFactors > 0 ?
            $relevancePoints / $keywordFactors : 0;

        return $analysis;
    }

    /**
     * Analyze the distribution of media elements throughout the content
     *
     * @param DOMXPath $xpath The XPath object
     * @param int $totalMediaCount Total count of media elements
     * @return array Analysis of media distribution
     */
    private function analyzeMediaDistribution(DOMXPath $xpath, int $totalMediaCount): array
    {
        $analysis = [
            'distribution_score' => 0,
            'distribution_pattern' => 'none',
            'section_coverage' => [
                'intro' => false,
                'body' => false,
                'conclusion' => false
            ]
        ];

        // If no media, return default analysis
        if ($totalMediaCount === 0) {
            return $analysis;
        }

        // Get all paragraphs to analyze content structure
        $paragraphs = $this->safeXPathQuery($xpath, '//p');
        $totalParagraphs = $paragraphs ? $paragraphs->length : 0;

        if ($totalParagraphs === 0) {
            return $analysis;
        }

        // Track media positions relative to content sections
        $mediaPositions = [];

        // Get paragraph positions of all media elements
        foreach (SeoOptimiserConfig::MEDIA_TYPES as $selectors) {
            foreach ($selectors as $selector) {
                $elements = $this->safeXPathQuery($xpath, '//' . $selector);

                if ($elements && $elements->length > 0) {
                    foreach ($elements as $element) {
                        // Find a related paragraph position
                        $position = $this->findElementPositionInContent($element, $paragraphs, $totalParagraphs);
                        if ($position !== null) {
                            $mediaPositions[] = $position;
                        }
                    }
                }
            }
        }

        // Sort positions for analysis
        if (!empty($mediaPositions)) {
            sort($mediaPositions);

            // Analyze distribution pattern
            $analysis['distribution_pattern'] = $this->determineDistributionPattern($mediaPositions);

            // Calculate distribution score based on evenness
            $analysis['distribution_score'] = $this->calculateDistributionScore($mediaPositions, $totalParagraphs);

            // Check section coverage
            $analysis['section_coverage'] = $this->analyzeSectionCoverage($mediaPositions);
        }

        return $analysis;
    }

    /**
     * Find the position of a media element relative to content paragraphs
     *
     * @param DOMElement $element The media element
     * @param DOMNodeList $paragraphs List of all paragraphs
     * @param int $totalParagraphs Total paragraph count
     * @return float|null Relative position (0-1) or null if not found
     */
    private function findElementPositionInContent(
        DOMElement $element,
        DOMNodeList $paragraphs,
        int $totalParagraphs
    ): ?float
    {
        if ($totalParagraphs === 0) {
            return null;
        }

        // Check if the element is inside a paragraph
        $parent = $element->parentNode;
        while ($parent && $parent->nodeType === XML_ELEMENT_NODE) {
            if ($parent->nodeName === 'p') {
                // Find this paragraph's position
                for ($i = 0; $i < $totalParagraphs; $i++) {
                    if ($paragraphs->item($i)->isSameNode($parent)) {
                        return $i / $totalParagraphs;
                    }
                }
            }
            $parent = $parent->parentNode;
        }

        // If not inside a paragraph, find the nearest paragraph
        $elementHTML = $element->ownerDocument->saveHTML($element);
        $fullHTML = $element->ownerDocument->saveHTML();
        $elementPos = strpos($fullHTML, $elementHTML);

        if ($elementPos === false) {
            return null;
        }

        // Find the closest paragraph by comparing positions
        $closestIndex = 0;
        $closestDistance = PHP_INT_MAX;

        for ($i = 0; $i < $totalParagraphs; $i++) {
            $paraHTML = $element->ownerDocument->saveHTML($paragraphs->item($i));
            $paraPos = strpos($fullHTML, $paraHTML);

            if ($paraPos !== false) {
                $distance = abs($paraPos - $elementPos);
                if ($distance < $closestDistance) {
                    $closestDistance = $distance;
                    $closestIndex = $i;
                }
            }
        }

        return $closestIndex / $totalParagraphs;
    }

    /**
     * Determine the distribution pattern of media elements
     *
     * @param array $positions Array of positions (0-1)
     * @return string Distribution pattern description
     */
    private function determineDistributionPattern(array $positions): string
    {
        // Count elements in each third of the content
        $firstThird = count(array_filter($positions, static function($pos) {
            return $pos <= 0.33;
        }));

        $middleThird = count(array_filter($positions, static function($pos) {
            return $pos > 0.33 && $pos <= 0.66;
        }));

        $lastThird = count(array_filter($positions, static function($pos) {
            return $pos > 0.66;
        }));

        $total = count($positions);

        // Determine a pattern based on distribution
        if ($firstThird > 0.6 * $total) {
            return 'top_heavy';
        } elseif ($lastThird > 0.6 * $total) {
            return 'bottom_heavy';
        } elseif ($middleThird > 0.6 * $total) {
            return 'middle_heavy';
        } elseif ($firstThird > 0 && $middleThird > 0 && $lastThird > 0) {
            // Check if relatively balanced
            $maxThird = max($firstThird, $middleThird, $lastThird);
            $minThird = min($firstThird, $middleThird, $lastThird);

            if ($maxThird - $minThird <= 0.3 * $total) {
                return 'well_balanced';
            }
        }

        return 'uneven';
    }

    /**
     * Calculate a score for how evenly media is distributed
     *
     * @param array $positions Media positions (0-1)
     * @param int $totalParagraphs Total paragraph count
     * @return float Distribution score (0-1)
     */
    private function calculateDistributionScore(array $positions, int $totalParagraphs): float
    {
        $count = count($positions);

        if ($count <= 1 || $totalParagraphs <= 1) {
            return 0.5; // Neutral score for just one media element
        }

        // Ideal distribution would have equal spacing
        $idealSpacing = 1.0 / ($count - 1);

        // Calculate actual spacings
        $actualSpacings = [];
        for ($i = 1; $i < $count; $i++) {
            $actualSpacings[] = $positions[$i] - $positions[$i-1];
        }

        // Calculate variance from ideal spacing
        $totalVariance = 0;
        foreach ($actualSpacings as $spacing) {
            $totalVariance += abs($spacing - $idealSpacing);
        }

        // Perfect distribution would have zero variance
        // Maximum variance would be approximately 1.0
        $normalizedVariance = min(1, $totalVariance / count($actualSpacings));

        // Convert to score (higher is better)
        return 1 - $normalizedVariance;
    }

    /**
     * Analyze which content sections contain media
     *
     * @param array $positions Media positions (0-1)
     * @return array Section coverage analysis
     */
    private function analyzeSectionCoverage(array $positions): array
    {
        $sectionCoverage = [
            'intro' => false,
            'body' => false,
            'conclusion' => false
        ];

        // Check introduction (first 15% of content)
        if (count(array_filter($positions, static function($pos) {
                return $pos <= 0.15;
            })) > 0) {
            $sectionCoverage['intro'] = true;
        }

        // Check body (middle 70% of content)
        if (count(array_filter($positions, static function($pos) {
                return $pos > 0.15 && $pos < 0.85;
            })) > 0) {
            $sectionCoverage['body'] = true;
        }

        // Check conclusion (last 15% of content)
        if (count(array_filter($positions, static function($pos) {
                return $pos >= 0.85;
            })) > 0) {
            $sectionCoverage['conclusion'] = true;
        }

        return $sectionCoverage;
    }

    /**
     * Calculate overall multimedia score based on all analyses
     *
     * @param array $mediaAnalysis Media element analysis
     * @param array $keywordUsage Keyword usage analysis
     * @param array $distributionAnalysis Distribution analysis
     * @return float Score between 0 and 1
     */
    private function calculateMultimediaScore(
        array $mediaAnalysis,
        array $keywordUsage,
        array $distributionAnalysis
    ): float
    {
        // Factor weights
        $weights = [
            'presence' => 0.3,      // Media presence and ratio
            'optimization' => 0.3,   // Alt tags and captions
            'distribution' => 0.2,   // Even distribution
            'relevance' => 0.2       // Keyword relevance
        ];

        // 1. Media presence score (0-1)
        $presenceScore = 0;
        if ($mediaAnalysis['total_count'] >= SeoOptimiserConfig::MIN_RECOMMENDED_MEDIA_COUNT) {
            // Cap at around 1 media per 100 words
            $idealRatio = SeoOptimiserConfig::OPTIMAL_MEDIA_RATIO;
            $actualRatio = $mediaAnalysis['media_to_text_ratio'] / 100;

            // Too few media elements
            if ($actualRatio < $idealRatio / 2) {
                $presenceScore = 0.5 * ($actualRatio / ($idealRatio / 2));
            }
            // Optimal range
            elseif ($actualRatio >= $idealRatio / 2 && $actualRatio <= $idealRatio * 2) {
                $presenceScore = 0.5 + (0.5 * (1 - abs($actualRatio - $idealRatio) / $idealRatio));
            }
            // Too many media elements
            else {
                $presenceScore = 0.5 * (1 - min(1, ($actualRatio - $idealRatio * 2) / ($idealRatio * 2)));
            }
        }

        // 2. Optimization score - already calculated in the analysis
        $optimizationScore = $mediaAnalysis['optimization_score'];

        // 3. Distribution score - already calculated in the analysis
        $distributionScore = $distributionAnalysis['distribution_score'];

        // Add bonus for good section coverage
        $sectionCoverage = array_sum(array_map('intval', $distributionAnalysis['section_coverage']));
        if ($sectionCoverage === 3) { // All sections covered
            $distributionScore = min(1, $distributionScore + 0.2);
        }

        // 4. Relevance score - already calculated in the analysis
        $relevanceScore = $keywordUsage['keyword_relevance_score'];

        // Calculate weighted score
        $weightedScore =
            ($presenceScore * $weights['presence']) +
            ($optimizationScore * $weights['optimization']) +
            ($distributionScore * $weights['distribution']) +
            ($relevanceScore * $weights['relevance']);

        // Ensure the score is between 0 and 1
        return max(0, min(1, $weightedScore));
    }

    /**
     * Calculate the score based on the performed analysis
     *
     * @return float A score based on the multimedia inclusion and optimization
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;
        // Return the overall score calculated during analysis
        return $factorData['overall_score'] ?? 0;
    }

    /**
     * Generate suggestions based on multimedia analysis
     *
     * @return array Active suggestions based on identified issues
     */
    public function suggestions(): array
    {
        $activeSuggestions = [];

        $factorData = $this->value;

        $mediaAnalysis = $factorData['media_analysis'] ?? [];
        $keywordUsage = $factorData['keyword_usage'] ?? [];
        $distributionAnalysis = $factorData['distribution_analysis'] ?? [];

        // Issue: No multimedia or insufficient multimedia elements
        if (($mediaAnalysis['total_count'] ?? 0) < SeoOptimiserConfig::MIN_RECOMMENDED_MEDIA_COUNT) {
            $activeSuggestions[] = Suggestion::CONTENT_EXPANSION;
        }

        // Issue: Poor alt tag optimization
        $altTagRatio = $mediaAnalysis['alt_tags']['ratio'] ?? 1;
        if ($altTagRatio < SeoOptimiserConfig::MEDIA_ALT_TAG_THRESHOLD && ($mediaAnalysis['alt_tags']['total_images'] ?? 0) > 0) {
            $activeSuggestions[] = Suggestion::LACKS_CONTENT_DEPTH;
        }

        // Issue: Poor distribution of media
        if (($distributionAnalysis['distribution_score'] ?? 1) < 0.5 && ($mediaAnalysis['total_count'] ?? 0) > 1) {
            $activeSuggestions[] = Suggestion::UNBALANCED_KEYWORD_DISTRIBUTION;
        }

        // Issue: Missing media in key sections (intro, conclusion)
        $sectionCoverage = $distributionAnalysis['section_coverage'] ?? [];
        if (isset($sectionCoverage['intro']) && !$sectionCoverage['intro']) {
            $activeSuggestions[] = Suggestion::MISSING_MULTIMEDIA_IN_INTRO_SECTION;
        }

        // Issue: Poor keyword relevance in media elements
        if (($keywordUsage['keyword_relevance_score'] ?? 1) < 0.3) {
            $activeSuggestions[] = Suggestion::MISSING_KEYWORD_COVERAGE;
        }

        return $activeSuggestions;
    }
}