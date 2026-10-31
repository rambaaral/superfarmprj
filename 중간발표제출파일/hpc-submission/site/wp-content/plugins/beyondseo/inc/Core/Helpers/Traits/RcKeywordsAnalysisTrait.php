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
use DOMNode;
use DOMXPath;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Modules\ModuleLibrary\Technical\MetaTags\MetaTags;

/**
 * Trait RcKeywordsAnalysisTrait
 *
 * Provides utility methods for handling keyword analysis.
 */
trait RcKeywordsAnalysisTrait
{
    use RcLoggerTrait;

    public static array $stopWords = ['the', 'and', 'or', 'a', 'an', 'in', 'on', 'at', 'to', 'for', 'with', 'by', 'of', 'that', 'this'];

    /**
     * Get the primary keyword for a post
     *
     * @param int $postId The post-ID
     * @return string The primary keyword
     */
    public function getPrimaryKeyword(int $postId): string
    {
        // Check our own meta-key first
        $keyword = get_post_meta($postId, BaseConstants::META_KEY_PRIMARY_KEYWORD, true);
        if (!empty($keyword)) {
            return strtolower($keyword);
        }

        // Fallback to our own saved keyword if we have one
        $keywords = get_post_meta($postId, BaseConstants::META_KEY_SEO_KEYWORDS, true) ?: '';
        if($keywords) {
            $keywords = explode(',', $keywords);
            return strtolower($keywords[0] ?? '');
        }

        // First, try with common meta-key names
        $commonMetaKeys = [
            '_yoast_wpseo_focuskw',
            '_aioseo_focus_keyword',
            '_rank_math_focus_keyword',
            'primary_keyword',
            'focus_keyword'
        ];

        foreach ($commonMetaKeys as $metaKey) {
            $keyword = get_post_meta($postId, $metaKey, true);
            if (!empty($keyword)) {
                return strtolower($keyword);
            }
        }

        return '';
    }

    /**
     * Get the secondary keywords for a post
     *
     * @param int $postId The post-ID
     * @return array The secondary keywords
     * @throws \JsonException
     */
    public function getSecondaryKeywords(int $postId): array
    {
        $secondaryKeywords = [];

        // Check our own meta-key first
        $keywords = get_post_meta($postId, MetaTags::META_SEO_SECONDARY_KEYWORDS, true);
        if (!empty($keywords)) {
            $secondaryKeywords = explode(',', strtolower($keywords));
            // return early if we have keywords
            if (is_array($secondaryKeywords) && count($secondaryKeywords) > 0) {
                $secondaryKeywords = array_map('trim', $secondaryKeywords);
                $secondaryKeywords = array_map('strtolower', $secondaryKeywords);
                return array_values(array_filter(array_unique($secondaryKeywords))); // Remove duplicates and empty values
            }
        }

        // Fallback to our own saved keywords if we have them
        $rcKeywords = get_post_meta($postId, BaseConstants::META_KEY_SEO_KEYWORDS, true);
        if (!empty($rcKeywords)) {
            $is_json = is_string($rcKeywords) && !empty($rcKeywords) && (str_starts_with(trim($rcKeywords), '{') || str_starts_with(trim($rcKeywords), '['));
            if ($is_json) {
                $decoded = json_decode(strtolower($rcKeywords), true);
                if (is_array($decoded)) {
                    $secondaryKeywords = array_merge($secondaryKeywords, $decoded);
                }
            } elseif (is_string($rcKeywords)) {
                $keywordArray = array_map('trim', explode(',', strtolower($rcKeywords)));
                // first keyword is the primary one, so we skip it
                if (count($keywordArray) > 0) {
                    array_shift($keywordArray);
                }
                $secondaryKeywords = array_merge($secondaryKeywords, $keywordArray);
            }
        }

        // Common meta-key names for secondary keywords
        $commonMetaKeys = [
            '_yoast_wpseo_keywordsynonyms', // Yoast stores as JSON
            '_aioseo_keywords', // AIOSEO sometimes stores as CSV
            '_rank_math_secondary_keyword', // Rank Math format
            'secondary_keywords',
        ];

        foreach ($commonMetaKeys as $metaKey) {
            $keywords = get_post_meta($postId, $metaKey, true);

            if (!empty($keywords)) {
                // Check if it's JSON encoded
                $is_json = is_string($keywords) && !empty($keywords) && (str_starts_with(trim($keywords), '{') || str_starts_with(trim($keywords), '['));
                if ($is_json) {
                    $decoded = json_decode(strtolower($keywords), true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        $secondaryKeywords = array_merge($secondaryKeywords, $decoded);
                        continue;
                    }
                }

                // Check if it's comma-separated
                if (is_string($keywords) && str_contains($keywords, ',')) {
                    $keywordArray = array_map('trim', explode(',', strtolower($keywords)));
                    $secondaryKeywords = array_merge($secondaryKeywords, $keywordArray);
                    continue;
                }

                // Must be a single keyword then
                if (is_string($keywords) && !empty(trim($keywords))) {
                    $secondaryKeywords[] = strtolower(trim($keywords));
                }
            }
        }

        // Remove duplicates and empty values
        $secondaryKeywords = array_filter(array_unique($secondaryKeywords));
        // Remove the first keyword. The secondary keywords are stored starting from the second one
        if (count($secondaryKeywords) > 1) {
            array_shift($secondaryKeywords);
        }

        return array_values($secondaryKeywords); // Reindex array
    }

    /**
     * Analyze keyword presence and usage in content
     *
     * @param string $keyword The keyword to analyze
     * @param string $content The full HTML content
     * @param string $cleanContent The cleaned text content
     * @return array Analysis results
     */
    public function analyzeKeywordInContent(string $keyword, string $content, string $cleanContent): array
    {
        $normalizedKeyword = strtolower(trim($keyword));

        // Create a single DOMXPath object for all operations
        $xpath = $this->loadHTMLInDomXPath($content);
        
        // Fallback to regex-based analysis if DOM parsing fails
        if (!$xpath) {
            return $this->analyzeKeywordInContentFallback($normalizedKeyword, $content, $cleanContent);
        }

        // Basic stats
        $count = substr_count(strtolower($cleanContent), $normalizedKeyword);
        $wordCount = $this->getWordCount($cleanContent);
        $density = $wordCount > 0 ? ($count / $wordCount) * 100 : 0;

        // Analyze document structure and keyword placement (pass xpath to avoid re-parsing)
        $structuralAnalysis = $this->analyzeStructureWithXPath($normalizedKeyword, $xpath);

        // Semantic analysis of keyword usage
        $semanticAnalysis = $this->analyzeSemanticUsage($normalizedKeyword, $cleanContent);

        // Competitive analysis
        $competitiveMetrics = $this->analyzeCompetitiveMetrics($density, $count, $wordCount);

        // Analyze readability in relation to keyword usage
        $readabilityMetrics = $this->analyzeReadability($cleanContent, $normalizedKeyword);

        // LSI keywords analysis
        $lsiKeywords = $this->findLSIKeywords($normalizedKeyword, $cleanContent);

        // Check for keyword cannibalization within the same content
        $cannibalization = $this->checkForCannibalization($normalizedKeyword, $cleanContent);

        // Optimized headings analysis using XPath
        $headingsAnalysis = $this->analyzeHeadingsWithXPath($normalizedKeyword, $xpath);
        $headingsCount = $headingsAnalysis['count'];

        // Improved first paragraph detection with fallback selectors
        $firstParagraphNode = $this->getFirstParagraphWithXPath($xpath);
        $inFirstParagraph = false;
        if ($firstParagraphNode) {
            $firstParaText = strtolower(wp_strip_all_tags($firstParagraphNode->textContent));
            $inFirstParagraph = str_contains($firstParaText, $normalizedKeyword);
        }

        // Variations (simple plural and suffix-based)
        $variations = $this->getKeywordVariations($normalizedKeyword);
        $variationCounts = [];
        foreach ($variations as $variation) {
            $variationCounts[$variation] = substr_count(strtolower($cleanContent), $variation);
        }

        // First occurrence position
        $firstOccurrence = stripos($cleanContent, $normalizedKeyword);
        $positionScore = $firstOccurrence !== false
            ? round(($firstOccurrence / max(strlen($cleanContent), 1)) * 100, 2)
            : null;

        // Keyword distribution
        $occurrencePositions = [];
        $offset = 0;
        while (($pos = stripos($cleanContent, $normalizedKeyword, $offset)) !== false) {
            $occurrencePositions[] = $pos;
            $offset = $pos + strlen($normalizedKeyword);
        }

        $distributionScore = count($occurrencePositions) > 1
            ? round((max($occurrencePositions) - min($occurrencePositions)) / max(strlen($cleanContent), 1), 2)
            : 0;

        // Contextual score (match in relevant context)
        $contextMatchScore = 0;
        if (preg_match_all('/\b' . preg_quote($normalizedKeyword, '/') . '\b.{0,50}(improve|ranking|optimi[sz]e|visibility)/i', $cleanContent, $matches)) {
            $contextMatchScore = count($matches[0]);
        }

        // Heuristic usage flag
        $hasSufficientUsage = $count > 0
            && $density >= 0.5
            && $density <= 3.0
            && $positionScore !== null && $positionScore < 30
            && $distributionScore > 0.1;

        return [
            'count' => $count,
            'density' => round($density, 2),
            'structure' => $structuralAnalysis,
            'in_headings' => $headingsCount,
            'in_first_paragraph' => $inFirstParagraph,
            'position_percent' => $positionScore,
            'distribution_score' => $distributionScore,
            'contextual_score' => $contextMatchScore,
            'variations' => $variationCounts,
            'has_sufficient_usage' => $hasSufficientUsage,
            // Semantic analysis
            'semantic' => $semanticAnalysis,

            // Competitive analysis
            'competitive' => $competitiveMetrics,

            // Readability metrics
            'readability' => $readabilityMetrics,

            // LSI keywords
            'lsi_keywords' => $lsiKeywords,

            // Keyword cannibalization
            'cannibalization' => $cannibalization,
        ];
    }

    /**
     * Get simple variations of a keyword for checking
     *
     * @param string $keyword
     * @return array
     */
    public function getKeywordVariations(string $keyword): array
    {
        $variations = [];

        // Plural form (very simplified)
        if (!str_ends_with($keyword, 's')) {
            $variations[] = $keyword . 's';
        }

        // Singular form
        if (str_ends_with($keyword, 's')) {
            $variations[] = substr($keyword, 0, -1);
        }

        return $variations;
    }

    /**
     * Check if the keyword is in the post title
     *
     * @param string $keyword
     * @param int $postId
     * @return bool
     */
    public function isKeywordInTitle(string $keyword, int $postId): bool
    {
        $title = get_the_title($postId);
        return stripos(strtolower($title), strtolower($keyword)) !== false;
    }

    /**
     * Check if the keyword is in the meta title
     *
     * @param string $keyword
     * @param int $postId
     * @return bool
     */
    public function isKeywordInMetaTitle(string $keyword, int $postId): bool
    {
        // Check common meta-title fields from popular SEO plugins
        $metaKeys = [
            MetaTags::META_SEO_TITLE,
            '_yoast_wpseo_title',
            '_aioseo_title',
            '_rank_math_title',
            '_seopress_titles_title',
        ];

        foreach ($metaKeys as $metaKey) {
            $title = get_post_meta($postId, $metaKey, true);
            if (!empty($title) && stripos($title, $keyword) !== false) {
                return true;
            }
        }

        return $this->isKeywordInTitle($keyword, $postId);
    }

    /**
     * Check if the keyword is in the meta description
     *
     * @param string $keyword
     * @param int $postId
     * @return bool
     */
    public function isKeywordInMetaDescription(string $keyword, int $postId): bool
    {
        // Check common meta-description fields from popular SEO plugins
        $metaKeys = [
            MetaTags::META_SEO_DESCRIPTION,
            '_yoast_wpseo_metadesc',
            '_aioseo_description',
            '_rank_math_description',
            '_seopress_titles_desc',
        ];

        foreach ($metaKeys as $metaKey) {
            $description = get_post_meta($postId, $metaKey, true);
            if (!empty($description) && stripos($description, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the meta-description for a post
     * @param $postId
     * @return string
     */
    public function getMetaDescription($postId): string
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

        return '';
    }

    /**
     * Check if the keyword is in the post slug
     *
     * @param string $keyword
     * @param int $postId
     * @return bool
     */
    public function isKeywordInSlug(string $keyword, int $postId): bool
    {
        $post = get_post($postId);
        if (!$post) {
            return false;
        }

        // Normalize keyword for slug comparison
        $normalizedKeyword = sanitize_title($keyword);

        return stripos($post->post_name, $normalizedKeyword) !== false;
    }

    /**
     * Detect if there are plugins that support keyword optimization
     *
     * @return bool
     */
    public function detectKeywordPluginSupport(): bool
    {
        // Check for common SEO plugins
        $seoPlugins = [
            'wordpress-seo/wp-seo.php', // Yoast SEO
            'all-in-one-seo-pack/all_in_one_seo_pack.php', // All In One SEO
            'seo-by-rank-math/rank-math.php', // Rank Math SEO
            'wp-seopress/seopress.php', // SEOPress
            'beyondseo/beyondseo.php', // BeyondSEO
        ];

        foreach ($seoPlugins as $plugin) {
            if (function_exists('is_plugin_active') && is_plugin_active($plugin)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Analyze the structure of the content for keyword presence
     * 
     * @deprecated Use analyzeStructureWithXPath() instead to avoid redundant DOM parsing
     * @param string $keyword The keyword to analyze
     * @param string $content The full HTML content
     * @return array Analysis results
     */
    public function analyzeStructure(string $keyword, string $content): array
    {
        // Create a new DOMXPath object (inefficient - creates redundant DOM parsing)
        $xpath = $this->loadHTMLInDomXPath($content);
        
        if (!$xpath) {
            return [
                'headings' => [],
                'paragraphs' => ['total' => 0, 'with_keyword' => 0, 'distribution_percentage' => 0],
                'first_paragraph' => ['has_keyword' => false],
                'meta' => ['title_has_keyword' => false, 'description_has_keyword' => false, 'url_has_keyword' => false],
                'images' => []
            ];
        }
        
        return $this->analyzeStructureWithXPath($keyword, $xpath);
    }

    /**
     * Optimized structure analysis using shared XPath instance
     *
     * @param string $keyword The keyword to analyze
     * @param DOMXPath $xpath Pre-created XPath instance
     * @return array Analysis results
     */
    public function analyzeStructureWithXPath(string $keyword, DOMXPath $xpath): array
    {
        // Check for keyword in different heading levels (exclude common site parts like header/footer/nav/etc.)
        $headings = [];
        for ($i = 1; $i <= 6; $i++) {
            $headingNodes = $xpath->query("//h$i");
            $headingMatches = 0;
            $headingTexts = [];
            $eligibleCount = 0;

            foreach ($headingNodes as $node) {
                // Skip headings that are within excluded/common site parts
                if ($node instanceof DOMElement) {
                    if ($this->isExcludedContainer($node) || $this->isCommonSitePart($node)) {
                        continue;
                    }
                }

                $headingText = strtolower($node->textContent);
                $headingTexts[] = $node->textContent;
                $eligibleCount++;
                if (str_contains($headingText, $keyword)) {
                    $headingMatches++;
                }
            }

            $headings["h$i"] = [
                'count' => $eligibleCount,
                'keyword_matches' => $headingMatches,
                'texts' => $headingTexts
            ];
        }

        // Extract only content paragraphs (exclude header/nav/aside/footer/widgets/comments etc.)
        $paragraphNodes = $this->getContentParagraphsWithXPath($xpath);
        $totalParagraphs = count($paragraphNodes);
        $paragraphsWithKeyword = 0;

        // First paragraph check within main content using unified source
        $firstParagraphHasKeyword = false;
        if ($totalParagraphs > 0) {
            $firstParagraphText = strtolower($paragraphNodes[0]->textContent);
            $firstParagraphHasKeyword = str_contains($firstParagraphText, $keyword);

            foreach ($paragraphNodes as $pNode) {
                $text = strtolower($pNode->textContent);
                if (str_contains($text, $keyword)) {
                    $paragraphsWithKeyword++;
                }
            }
        }

        // Check for keyword in meta-elements
        $metaTitle = $xpath->query('//title')->length > 0 ?
            strtolower($xpath->query('//title')->item(0)->textContent) : '';
        $metaDescription = '';
        $metaNodes = $xpath->query("//meta[@name='description']");
        if ($metaNodes->length > 0) {
            /** @var mixed $metaNodesItem */
            $metaNodesItem = $metaNodes->item(0);
            $metaDescription = strtolower($metaNodesItem->getAttribute('content'));
        }

        $request_uri = WordpressHelpers::sanitize_input('SERVER', 'REQUEST_URI');

        $keywordInURL = $request_uri !== '' && str_contains(strtolower($request_uri), strtolower($keyword));

        return [
            'headings' => $headings,
            'paragraphs' => [
                'total' => $totalParagraphs,
                'with_keyword' => $paragraphsWithKeyword,
                'distribution_percentage' => $totalParagraphs > 0 ?
                    round(($paragraphsWithKeyword / $totalParagraphs) * 100, 2) : 0
            ],
            'first_paragraph' => [
                'has_keyword' => $firstParagraphHasKeyword
            ],
            'meta' => [
                'title_has_keyword' => str_contains($metaTitle, $keyword),
                'description_has_keyword' => str_contains($metaDescription, $keyword),
                'url_has_keyword' => $keywordInURL
            ],
            'images' => $this->analyzeImageAttributes($keyword, $xpath)
        ];
    }

    /**
     * Optimized headings analysis using XPath
     *
     * @param string $keyword The keyword to analyze
     * @param DOMXPath $xpath Pre-created XPath instance
     * @return array Analysis results with count and level breakdown
     */
    private function analyzeHeadingsWithXPath(string $keyword, DOMXPath $xpath): array
    {
        $headingsCount = 0;
        $headingLevels = [];
        
        // Single XPath query for all headings
        $headingNodes = $xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6');
        
        foreach ($headingNodes as $node) {
            $headingText = strtolower($node->textContent);
            $level = $node->tagName;
            
            if (str_contains($headingText, $keyword)) {
                $headingsCount++;
                $headingLevels[$level] = ($headingLevels[$level] ?? 0) + 1;
            }
        }
        
        return [
            'count' => $headingsCount, 
            'levels' => $headingLevels,
            'total_headings' => $headingNodes->length
        ];
    }

    /**
     * Improved first paragraph detection with multiple fallback selectors
     *
     * @param DOMXPath $xpath Pre-created XPath instance
     * @return DOMElement|DOMNode|null First paragraph element or null if not found
     */
    public function getFirstParagraphWithXPath(DOMXPath $xpath): DOMElement|DOMNode|null
    {
        // Unified: delegate to getContentParagraphsWithXPath() as single source of truth
        $paragraphNodes = $this->getContentParagraphsWithXPath($xpath);
        return $paragraphNodes[0] ?? null;
    }

    /**
     * Get content paragraphs as html in document order.
     * Tries XPath first, otherwise falls back to regex-only extraction.
     *
     * @param string $html Full HTML content
     * @param DOMXPath|null $xpath Optional pre-built XPath
     * @param bool $forceFallback Force regex fallback (assume DOM parsing failed upstream)
     * @return string[] Array of paragraph texts
     */
    public function getContentParagraphsHtml(string $html, ?DOMXPath $xpath = null, bool $forceFallback = false): array
    {
        // If an XPath was provided, reuse the DOM-based extractor
        if (!$forceFallback && $xpath instanceof DOMXPath) {
            $nodes = $this->getContentParagraphsWithXPath($xpath);
            $texts = [];
            foreach ($nodes as $node) {
                $texts[] = trim($node->textContent);
            }
            return $texts;
        }

        // Attempt DOM parsing if not forced to fallback
        if (!$forceFallback) {
            $xp = $this->loadHTMLInDomXPath($html);
            if ($xp instanceof DOMXPath) {
                $nodes = $this->getContentParagraphsWithXPath($xp);
                $texts = [];
                foreach ($nodes as $node) {
                    $texts[] = trim($node->textContent);
                }
                return $texts;
            }
        }

        // Regex-only fallback
        $texts = [];
        if (preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is', $html, $matches)) {
            foreach ($matches[1] as $inner) {
                $text = trim($inner);
                if ($text !== '') {
                    $texts[] = $text;
                }
            }
        }

        return $texts;
    }

    /**
     * Get content paragraphs as plain text in document order.
     * Tries XPath first, otherwise falls back to regex-only extraction.
     *
     * @param string $html Full HTML content
     * @param DOMXPath|null $xpath Optional pre-built XPath
     * @param bool $forceFallback Force regex fallback (assume DOM parsing failed upstream)
     * @return string[] Array of paragraph texts
     */
    public function getContentParagraphsText(string $html, ?DOMXPath $xpath = null, bool $forceFallback = false): array
    {
        // If an XPath was provided, reuse the DOM-based extractor
        if (!$forceFallback && $xpath instanceof DOMXPath) {
            $nodes = $this->getContentParagraphsWithXPath($xpath);
            $texts = [];
            foreach ($nodes as $node) {
                $texts[] = trim(wp_strip_all_tags($node->textContent));
            }
            return $texts;
        }

        // Attempt DOM parsing if not forced to fallback
        if (!$forceFallback) {
            $xp = $this->loadHTMLInDomXPath($html);
            if ($xp instanceof DOMXPath) {
                $nodes = $this->getContentParagraphsWithXPath($xp);
                $texts = [];
                foreach ($nodes as $node) {
                    $texts[] = trim(wp_strip_all_tags($node->textContent));
                }
                return $texts;
            }
        }

        // Regex-only fallback
        return $this->extractParagraphsTextFallback($html);
    }

    /**
     * Regex-based fallback extractor for paragraph texts (keeps order).
     * Note: This ignores layout exclusions and simply extracts <p>...</p> content.
     *
     * @param string $html
     * @return string[]
     */
    private function extractParagraphsTextFallback(string $html): array
    {
        $texts = [];
        if (preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is', $html, $matches)) {
            foreach ($matches[1] as $inner) {
                $text = trim(wp_strip_all_tags($inner));
                if ($text !== '') {
                    $texts[] = $text;
                }
            }
        }
        return $texts;
    }

    /**
     * Extract all content paragraphs in document order, excluding non-content containers.
     *
     * Priority: <main>, <article>, .entry-content, .post-content, .content
     * Fallback: //p (only if nothing found in priority containers)
     *
     * @param DOMXPath $xpath
     * @return DOMElement[] Array of paragraph nodes
     */
    public function getContentParagraphsWithXPath(DOMXPath $xpath): array
    {
        $result = [];
        $seen = [];

        $pushIfEligible = function (DOMElement $node) use (&$result, &$seen) {
            $hash = spl_object_hash($node);
            if (!isset($seen[$hash]) && !$this->isExcludedContainer($node) && $this->isMeaningfulParagraph($node)) {
                $seen[$hash] = true;
                $result[] = $node;
            }
        };

        // Prioritize core content containers and avoid structural regions (aside/nav/header/footer)
        $prioritySelectors = [
            '//main//p',
            '//article//p',
            '//div[contains(@class, "entry-content")]//p',
            '//div[contains(@class, "post-content")]//p',
            '//div[contains(@class, "content")]//p',
            '//section[contains(@class, "content")]//p',
            '//div[@id="content"]//p',
            '//div[@id="primary"]//p',
            '//main//*[not(self::aside or self::nav or self::header or self::footer)]//p',
            '//article//*[not(self::aside or self::nav or self::header or self::footer)]//p',
            '//div[contains(concat(" ", normalize-space(@class), " "), " entry-content ")]//p',
            '//div[contains(concat(" ", normalize-space(@class), " "), " post-content ")]//p',
            '//div[contains(concat(" ", normalize-space(@class), " "), " content ")]//p',
        ];

        $found = 0;
        foreach ($prioritySelectors as $selector) {
            $nodes = $xpath->query($selector);
            foreach ($nodes as $node) {
                if ($node instanceof DOMElement) {
                    $pushIfEligible($node);
                    $found++;
                }
            }
        }

        // Fallback global, dar cu filtrare severă a containerelor excluse
        if ($found === 0) {
            $nodes = $xpath->query('//p');
            foreach ($nodes as $node) {
                if ($node instanceof DOMElement) {
                    $pushIfEligible($node);
                }
            }
        }

        return $result;
    }

    /**
     * Heuristic to decide if a <p> node is meaningful content (not empty, not only images/inline media, not only whitespace).
     *
     * Rules:
     * - Strip tags and decode entities; if resulting text length >= 1 (after trim), accept
     * - If text is empty but contains at least one <a> with readable text, accept
     * - Reject when the paragraph only contains <img>, <svg>, <figure>, <iframe>, <video>, <audio>, <canvas>, <br>,
     *   or punctuation-only/nbsp-only content
     */
    public function isMeaningfulParagraph(DOMElement $p): bool
    {
        // Defensive: ensure it is a paragraph element
        if (strtolower($p->tagName) !== 'p') {
            return false;
        }

        $raw = $p->textContent ?? '';
        $text = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5));

        // Quick textual check
        if ($text !== '' && preg_match('/\pL|\pN/u', $text)) { // has any letter or number
            return true;
        }

        // Allow anchor with readable label
        foreach ($p->getElementsByTagName('a') as $a) {
            $label = trim(html_entity_decode($a->textContent ?? '', ENT_QUOTES | ENT_HTML5));
            if ($label !== '' && preg_match('/\pL|\pN/u', $label)) {
                return true;
            }
        }

        // If there is any non-media inline text node with letters/numbers, accept
        for ($n = $p->firstChild; $n; $n = $n->nextSibling) {
            if ($n->nodeType === XML_TEXT_NODE) {
                $t = trim(html_entity_decode($n->nodeValue ?? '', ENT_QUOTES | ENT_HTML5));
                if ($t !== '' && preg_match('/\pL|\pN/u', $t)) {
                    return true;
                }
            }
        }

        // Reject when content is only media or breaks
        $mediaTags = ['img', 'svg', 'figure', 'iframe', 'video', 'audio', 'canvas'];
        $hasNonMediaElement = false;
        for ($child = $p->firstChild; $child; $child = $child->nextSibling) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (!in_array($tag, $mediaTags, true) && $tag !== 'br') {
                    $hasNonMediaElement = true;
                    break;
                }
            } elseif ($child->nodeType === XML_TEXT_NODE) {
                $t = trim(html_entity_decode($child->nodeValue ?? '', ENT_QUOTES | ENT_HTML5));
                if ($t !== '' && preg_match('/\pL|\pN/u', $t)) {
                    $hasNonMediaElement = true;
                    break;
                }
            }
        }

        return $hasNonMediaElement;
    }

    /**
     * Check whether a node is inside common site parts (header, footer, nav, sidebar, global UI, etc.).
     * Can be used to exclude elements from analysis when they live in boilerplate/layout areas.
     *
     * @param DOMNode $node The node to check
     * @param array{
     *   tags?: string[],
     *   roles?: string[],
     *   class_fragments?: string[],
     *   id_fragments?: string[],
     *   label_fragments?: string[],
     *   max_depth?: int
     * } $options Optional overrides/augmentations
     * @return bool True if the node is located in a common site part
     */
    public function isCommonSitePart(DOMNode $node, array $options = []): bool
    {
        $defaults = [
            'tags' => ['aside', 'nav', 'header', 'footer', 'form'],
            'roles' => ['navigation', 'complementary', 'contentinfo', 'banner', 'search'],
            'class_fragments' => [
                'site-header', 'header', 'top-bar', 'masthead', 'navbar', 'menu', 'navigation', 'nav', 'breadcrumbs',
                'sidebar', 'widget', 'widgets', 'footer', 'site-footer', 'bottom-bar', 'copyright',
                'comments', 'comment', 'reply', 'related', 'sharing', 'share', 'social',
                'pagination', 'pager', 'author-box',
                'modal', 'popup', 'notice', 'alert', 'announcement',
                'newsletter', 'subscribe', 'cookie', 'gdpr', 'consent',
                'promo', 'ads', 'ad-', 'advert', 'sponsor'
            ],
            'id_fragments' => [
                'header', 'masthead', 'top', 'nav', 'menu', 'footer', 'bottom', 'copyright',
                'breadcrumbs', 'cookie', 'gdpr', 'notice', 'modal', 'popup'
            ],
            'label_fragments' => ['menu', 'navigation', 'header', 'footer', 'breadcrumbs'],
            'max_depth' => 50,
        ];

        // Merge options (append arrays, override scalars)
        foreach ($options as $key => $value) {
            if (isset($defaults[$key]) && is_array($defaults[$key]) && is_array($value)) {
                $defaults[$key] = array_values(array_unique(array_merge($defaults[$key], $value)));
            } else {
                $defaults[$key] = $value;
            }
        }

        for ($p = $node; $p instanceof DOMElement; $p = $p->parentNode) {
            $defaults['max_depth']--;
            if ($defaults['max_depth'] < 0) {
                break;
            }

            $tag = strtolower($p->tagName);
            if (in_array($tag, $defaults['tags'], true)) {
                return true;
            }

            if ($p->hasAttribute('role') && in_array(strtolower($p->getAttribute('role')), $defaults['roles'], true)) {
                return true;
            }

            if ($p->hasAttribute('class')) {
                $cls = ' ' . strtolower($p->getAttribute('class')) . ' ';
                foreach ($defaults['class_fragments'] as $frag) {
                    if ($frag !== '' && str_contains($cls, $frag)) {
                        return true;
                    }
                }
            }

            if ($p->hasAttribute('id')) {
                $id = strtolower($p->getAttribute('id'));
                foreach ($defaults['id_fragments'] as $frag) {
                    if ($frag !== '' && str_contains($id, $frag)) {
                        return true;
                    }
                }
            }

            // aria-label or data-* labels indicating navigation-like regions
            foreach (['aria-label', 'data-label', 'data-component'] as $attr) {
                if ($p->hasAttribute($attr)) {
                    $val = strtolower($p->getAttribute($attr));
                    foreach ($defaults['label_fragments'] as $frag) {
                        if ($frag !== '' && str_contains($val, $frag)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Return true dacă nodul este într-un container non-content (sidebar/widget/nav/footer/header/comments etc.)
     *
     * @param DOMNode $node
     * @return bool
     */
    public function isExcludedContainer(DOMNode $node): bool
    {
        // Exclude common non-content tags
        $excludedTags = ['aside', 'nav', 'header', 'footer', 'form'];
        $excludedRole = ['navigation', 'complementary', 'contentinfo', 'banner', 'search'];
        // Exclude classes that indicate non-content areas
        $excludedClassFragments = [
            'sidebar', 'widget', 'widgets', 'menu', 'navigation', 'nav', 'header', 'footer',
            'comments', 'comment', 'reply', 'breadcrumb', 'modal', 'popup', 'newsletter', 'subscribe',
            'pagination', 'pager', 'author-box', 'related', 'sharing', 'share', 'social', 'ads', 'ad-',
            'promo', 'cookie', 'gdpr', 'notice', 'alert'
        ];

        for ($p = $node; $p instanceof DOMElement; $p = $p->parentNode) {
            $tag = strtolower($p->tagName);
            if (in_array($tag, $excludedTags, true)) {
                return true;
            }

            if ($p->hasAttribute('role') && in_array(strtolower($p->getAttribute('role')), $excludedRole, true)) {
                return true;
            }

            if ($p->hasAttribute('class')) {
                $cls = ' ' . strtolower($p->getAttribute('class')) . ' ';
                foreach ($excludedClassFragments as $frag) {
                    if (str_contains($cls, $frag)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }


    /**
     * Fallback analysis method when DOM parsing fails
     *
     * @param string $keyword The normalized keyword
     * @param string $content The full HTML content
     * @param string $cleanContent The cleaned text content
     * @return array Basic analysis results using regex
     */
    private function analyzeKeywordInContentFallback(string $keyword, string $content, string $cleanContent): array
    {
        // Basic stats
        $count = substr_count(strtolower($cleanContent), $keyword);
        $wordCount = $this->getWordCount($cleanContent);
        $density = $wordCount > 0 ? ($count / $wordCount) * 100 : 0;

        // Fallback headings count using regex
        $headingsCount = 0;
        if (preg_match_all('/<h[1-6][^>]*>.*?' . preg_quote($keyword, '/') . '.*?<\/h[1-6]>/i', $content, $matches)) {
            $headingsCount = count($matches[0]);
        }

        // Fallback first paragraph check using unified extractor
        $inFirstParagraph = false;
        $paragraphs = $this->getContentParagraphsText($content, null, true);
        if (!empty($paragraphs)) {
            $firstParaText = strtolower($paragraphs[0]);
            $inFirstParagraph = str_contains($firstParaText, $keyword);
        }

        // Basic structure analysis fallback
        $structuralAnalysis = [
            'headings' => [],
            'paragraphs' => ['total' => 0, 'with_keyword' => 0, 'distribution_percentage' => 0],
            'first_paragraph' => ['has_keyword' => $inFirstParagraph],
            'meta' => ['title_has_keyword' => false, 'description_has_keyword' => false, 'url_has_keyword' => false],
            'images' => []
        ];

        // Other analyses that don't require DOM
        $semanticAnalysis = $this->analyzeSemanticUsage($keyword, $cleanContent);
        $competitiveMetrics = $this->analyzeCompetitiveMetrics($density, $count, $wordCount);
        $readabilityMetrics = $this->analyzeReadability($cleanContent, $keyword);
        $lsiKeywords = $this->findLSIKeywords($keyword, $cleanContent);
        $cannibalization = $this->checkForCannibalization($keyword, $cleanContent);

        // Variations
        $variations = $this->getKeywordVariations($keyword);
        $variationCounts = [];
        foreach ($variations as $variation) {
            $variationCounts[$variation] = substr_count(strtolower($cleanContent), $variation);
        }

        // Position and distribution analysis
        $firstOccurrence = stripos($cleanContent, $keyword);
        $positionScore = $firstOccurrence !== false
            ? round(($firstOccurrence / max(strlen($cleanContent), 1)) * 100, 2)
            : null;

        $occurrencePositions = [];
        $offset = 0;
        while (($pos = stripos($cleanContent, $keyword, $offset)) !== false) {
            $occurrencePositions[] = $pos;
            $offset = $pos + strlen($keyword);
        }

        $distributionScore = count($occurrencePositions) > 1
            ? round((max($occurrencePositions) - min($occurrencePositions)) / max(strlen($cleanContent), 1), 2)
            : 0;

        $contextMatchScore = 0;
        if (preg_match_all('/\b' . preg_quote($keyword, '/') . '\b.{0,50}(improve|ranking|optimi[sz]e|visibility)/i', $cleanContent, $matches)) {
            $contextMatchScore = count($matches[0]);
        }

        $hasSufficientUsage = $count > 0
            && $density >= 0.5
            && $density <= 3.0
            && $positionScore !== null && $positionScore < 30
            && $distributionScore > 0.1;

        return [
            'count' => $count,
            'density' => round($density, 2),
            'structure' => $structuralAnalysis,
            'in_headings' => $headingsCount,
            'in_first_paragraph' => $inFirstParagraph,
            'position_percent' => $positionScore,
            'distribution_score' => $distributionScore,
            'contextual_score' => $contextMatchScore,
            'variations' => $variationCounts,
            'has_sufficient_usage' => $hasSufficientUsage,
            'semantic' => $semanticAnalysis,
            'competitive' => $competitiveMetrics,
            'readability' => $readabilityMetrics,
            'lsi_keywords' => $lsiKeywords,
            'cannibalization' => $cannibalization,
            'fallback_used' => true // Flag to indicate fallback was used
        ];
    }

    /**
     * Analyze semantic usage of the keyword in content
     *
     * @param string $keyword The keyword to analyze
     * @param string $cleanContent The cleaned text content
     * @return array Analysis results
     */
    public function analyzeSemanticUsage(string $keyword, string $cleanContent): array
    {
        // Get keyword variations
        $variations = $this->getKeywordVariations($keyword);
        $variationCounts = [];

        foreach ($variations as $variation) {
            $variationCounts[$variation] = substr_count($cleanContent, $variation);
        }

        // Analyze keyword phrase context
        $keywordContexts = [];
        $sentences = preg_split('/(?<=[.?!])\s+/', $cleanContent, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($sentences as $sentence) {
            if (str_contains($sentence, $keyword)) {
                $keywordContexts[] = $sentence;
            }
        }

        // Check if the keyword is used naturally
        $naturalUsage = $this->assessNaturalUsage($keyword, $keywordContexts);

        return [
            'variations' => $variationCounts,
            'contexts' => array_slice($keywordContexts, 0, 5), // Limit to the first 5 contexts for brevity
            'natural_usage' => $naturalUsage,
            'keyword_proximity' => $this->analyzeKeywordProximity($keyword, $cleanContent)
        ];
    }

    /**
     * Assess if the keyword is used naturally in the content
     *
     * @param string $keyword The keyword to analyze
     * @param array $contexts The contexts where the keyword appears
     * @return array Analysis results
     */
    public function assessNaturalUsage(string $keyword, array $contexts): array
    {
        // Check for keyword stuffing indicators
        $stuffingIndicators = 0;
        $unnaturalPatterns = [
            '/' . preg_quote($keyword, '/') . '.{0,10}' . preg_quote($keyword, '/') . '/i', // Too close repetition
            '/^' . preg_quote($keyword, '/') . '/i', // Sentence starting with the keyword unnaturally
            '/' . preg_quote($keyword, '/') . '\s*,\s*' . preg_quote($keyword, '/') . '/i' // Comma-separated repetition
        ];

        foreach ($contexts as $context) {
            foreach ($unnaturalPatterns as $pattern) {
                if (preg_match($pattern, $context)) {
                    $stuffingIndicators++;
                    break;
                }
            }
        }

        // Assess if the keyword is forced into content
        $forcedUsagePercentage = count($contexts) > 0 ?
            round(($stuffingIndicators / count($contexts)) * 100, 2) : 0;

        return [
            'stuffing_indicators' => $stuffingIndicators,
            'appears_natural' => $forcedUsagePercentage < 30,
            'forced_usage_percentage' => $forcedUsagePercentage
        ];
    }

    /**
     * Analyze keyword proximity in the content
     *
     * @param string $keyword The keyword to analyze
     * @param string $content The full HTML content
     * @return array Analysis results
     */
    public function analyzeKeywordProximity(string $keyword, string $content): array
    {
        // Find all positions of the keyword in content
        $positions = [];
        $offset = 0;
        while (($pos = strpos($content, $keyword, $offset)) !== false) {
            $positions[] = $pos;
            $offset = $pos + strlen($keyword);
        }

        // Calculate the average distance between occurrences
        $distances = [];
        for ($i = 1, $iMax = count($positions); $i < $iMax; $i++) {
            $distances[] = $positions[$i] - $positions[$i-1];
        }

        $avgDistance = count($distances) > 0 ?
            array_sum($distances) / count($distances) : 0;

        return [
            'occurrences' => count($positions),
            'average_distance' => round($avgDistance, 2),
            'distribution_score' => $this->calculateDistributionScore($positions, strlen($content))
        ];
    }

    /**
     * Calculate the distribution score based on keyword positions
     *
     * @param array $positions Array of positions where the keyword occurs
     * @param int $contentLength Length of the content
     * @return float Distribution score (0-10)
     */
    public function calculateDistributionScore(array $positions, int $contentLength): float
    {
        if (empty($positions) || $contentLength === 0) {
            return 0.0;
        }

        // Ideal distribution would have keywords evenly spaced
        $idealGap = $contentLength / (count($positions) + 1);
        $idealPositions = array_map(function($i) use ($idealGap) {
            return $idealGap * $i;
        }, range(1, count($positions)));

        // Calculate deviation from ideal distribution
        $deviations = [];
        for ($i = 0, $iMax = count($positions); $i < $iMax; $i++) {
            $deviations[] = abs($positions[$i] - $idealPositions[$i]);
        }

        $avgDeviation = array_sum($deviations) / count($deviations);
        $maxDeviation = $contentLength / 2;

        // Convert to a 0-10 score where 10 is a perfect distribution
        $distributionScore = 10 - (($avgDeviation / $maxDeviation) * 10);
        return max(0, min(10, round($distributionScore, 1)));
    }

    /**
     * Analyze competitive metrics for the keyword
     *
     * @param float $density Keyword density
     * @param int $count Keyword count
     * @param int $wordCount Total word count in content
     * @return array Analysis results
     */
    public function analyzeCompetitiveMetrics(float $density, int $count, int $wordCount): array
    {
        // Industry standards and recommendations
        $idealDensity = [
            'min' => 0.5,
            'max' => 2.5
        ];

        $recommendedCount = ceil($wordCount / 100); // Roughly 1 keyword per 100 words

        return [
            'density_assessment' => [
                'value' => round($density, 2),
                'ideal_range' => $idealDensity,
                'status' => $this->getDensityStatus($density, $idealDensity)
            ],
            'count_assessment' => [
                'value' => $count,
                'recommended' => $recommendedCount,
                'status' => $this->getCountStatus($count, (int)$recommendedCount)
            ]
        ];
    }

    /**
     * Get the density status based on the ideal range
     *
     * @param float $density The keyword density
     * @param array $idealRange The ideal range for density
     * @return string Status of the density
     */
    public function getDensityStatus(float $density, array $idealRange): string
    {
        if ($density < $idealRange['min']) {
            return 'underdensity';
        }

        if ($density > $idealRange['max']) {
            return 'overdensity';
        }

        return 'optimal';
    }

    /**
     * Get the count status based on the recommended count
     *
     * @param int $count The keyword count
     * @param int $recommended The recommended count
     * @return string Status of the count
     */
    public function getCountStatus(int $count, int $recommended): string
    {
        $ratio = $recommended > 0 ? $count / $recommended : 0;

        if ($ratio < 0.7) {
            return 'insufficient';
        }

        if ($ratio > 1.5) {
            return 'excessive';
        }

        return 'optimal';
    }

    /**
     * Find LSI keywords related to the primary keyword
     *
     * @param string $keyword The primary keyword
     * @param string $content The full HTML content
     * @return array List of LSI keywords
     */
    public function findLSIKeywords(string $keyword, string $content): array
    {
        // This would ideally use external APIs or databases
        // Simplified version uses common co-occurring terms
        $words = str_word_count($content, 1);
        $wordFrequency = array_count_values($words);

        // Remove common stop words
        foreach (self::$stopWords as $stopWord) {
            unset($wordFrequency[$stopWord]);
        }

        // Remove the keyword itself
        $keywordParts = explode(' ', $keyword);
        foreach ($keywordParts as $part) {
            unset($wordFrequency[$part]);
        }

        // Sort by frequency
        arsort($wordFrequency);

        // Return top-related terms
        return array_slice($wordFrequency, 0, 10, true);
    }

    /**
     * Check for keyword cannibalization
     *
     * @param string $keyword The primary keyword
     * @param string $content The full HTML content
     * @return array Analysis results
     */
    public function checkForCannibalization(string $keyword, string $content): array
    {
        // Find potential competing keywords
        $words = str_word_count($content, 1);
        $wordFrequency = array_count_values($words);
        arsort($wordFrequency);

        // Get top keywords that might compete
        $topKeywords = array_slice($wordFrequency, 0, 5, true);
        $keywordCount = substr_count(strtolower($content), $keyword);

        $competingKeywords = array_filter($topKeywords, function ($count, $word) use ($keywordCount) {
            return $count > $keywordCount && strlen($word) > 3;
        }, ARRAY_FILTER_USE_BOTH);

        return [
            'primary_keyword_count' => $keywordCount,
            'potential_competing_keywords' => $competingKeywords,
            'has_cannibalization_risk' => !empty($competingKeywords)
        ];
    }

    /**
     * Build a keyword map for all content
     *
     * @param array $allContent Array of post-objects
     * @return array Keyword mapping data
     */
    public function buildKeywordMap(array $allContent): array
    {
        $keywordMap = [];

        foreach ($allContent as $post) {
            $postId = $post->ID;
            $primaryKeyword = $this->getPrimaryKeyword($postId);
            $secondaryKeywords = $this->getSecondaryKeywords($postId);

            // Skip posts without keywords
            if (empty($primaryKeyword) && empty($secondaryKeywords)) {
                continue;
            }

            // Add to a keyword map
            $keywordMap[] = [
                'post_id' => $postId,
                'title' => $post->post_title,
                'url' => get_permalink($postId),
                'post_type' => $post->post_type,
                'primary_keyword' => $primaryKeyword,
                'secondary_keywords' => $secondaryKeywords,
                'categories' => wp_get_post_categories($postId, ['fields' => 'names']),
                'last_updated' => $post->post_modified
            ];
        }

        return $keywordMap;
    }

    /**
     * Normalize a keyword for comparison
     *
     * @param string $keyword Keyword to normalize
     * @return string Normalized keyword
     */
    public function normalizeKeyword(string $keyword): string
    {
        // Convert to lowercase
        $normalized = strtolower($keyword);

        // Remove stop words
        $words = explode(' ', $normalized);
        $stopWords = self::$stopWords;
        $filteredWords = array_filter($words, function($word) use ($stopWords) {
            return !in_array($word, $stopWords);
        });

        // Reassemble
        return implode(' ', $filteredWords);
    }

    /**
     * Calculate similarity between two keywords
     *
     * @param string $keyword1 First keyword
     * @param string $keyword2 Second keyword
     * @return float Similarity percentage (0-100)
     */
    public function calculateKeywordSimilarity(string $keyword1, string $keyword2): float
    {
        // Normalize keywords
        $keyword1 = $this->normalizeKeyword($keyword1);
        $keyword2 = $this->normalizeKeyword($keyword2);

        // Check for the exact match
        if ($keyword1 === $keyword2) {
            return 100.0;
        }

        // Check if one is contained within the other
        if (str_contains($keyword1, $keyword2)) {
            return 90.0;
        }

        if (str_contains($keyword2, $keyword1)) {
            return 90.0;
        }

        // Calculate Levenshtein distance
        $levenshtein = levenshtein($keyword1, $keyword2);
        $maxLength = max(strlen($keyword1), strlen($keyword2));

        if ($maxLength === 0) {
            return 0.0;
        }

        // Convert to similarity percentage
        $similarity = (1 - ($levenshtein / $maxLength)) * 100;

        // Check for word overlap
        $words1 = explode(' ', $keyword1);
        $words2 = explode(' ', $keyword2);

        $commonWords = array_intersect($words1, $words2);
        $totalWords = array_unique(array_merge($words1, $words2));

        $wordOverlapScore = count($commonWords) / count($totalWords) * 100;

        // Return the higher of the two scores
        return max($similarity, $wordOverlapScore);
    }

    /**
     * Identify potential keyword gaps based on current keyword usage
     *
     * @param array $keywordFrequency Keyword frequency data
     * @return array Potential keyword gaps
     */
    public function identifyKeywordGaps(array $keywordFrequency): array
    {
        // This would ideally use external API data for keyword suggestions
        // For now, we'll use a simplified approach based on existing keywords

        $gaps = [];
        $existingKeywords = array_keys($keywordFrequency);

        // Generate potential related keywords
        foreach ($existingKeywords as $keyword) {
            // Skip single-word keywords
            if (!str_contains($keyword, ' ')) {
                continue;
            }

            // Generate variations
            $words = explode(' ', $keyword);

            // Skip very short keywords
            if (count($words) < 2) {
                continue;
            }

            // Generate potential variations
            for ($i = 0, $iMax = count($words); $i < $iMax; $i++) {
                $variation = $words;
                array_splice($variation, $i, 1);
                $potentialKeyword = implode(' ', $variation);

                // Only add if it's not already in use
                if (!in_array($potentialKeyword, $existingKeywords) && strlen($potentialKeyword) > 5) {
                    $gaps[$potentialKeyword] = [
                        'derived_from' => $keyword,
                        'type' => 'variation'
                    ];
                }
            }
        }

        // Limit to the top 10 gaps
        return array_slice($gaps, 0, 10, true);
    }

    /**
     * Analyzes keyword usage in the page title.
     *
     * @param string $primaryKeyword The primary keyword to check
     * @param int $postId The post-ID
     * @return array Analysis results
     */
    public function analyzeTitleKeywordUsage(string $primaryKeyword, int $postId): array
    {
        // Get the post-title
        $title = get_the_title($postId);
        $normalizedKeyword = strtolower(trim($primaryKeyword));
        $normalizedTitle = strtolower(trim($title));

        // Check if the primary keyword is in the title
        $hasPrimaryKeyword = str_contains($normalizedTitle, $normalizedKeyword);

        // Check if the keyword is at the beginning of the title (ideal position)
        $isAtBeginning = str_starts_with($normalizedTitle, $normalizedKeyword);

        // Calculate position percentage (0% means at the beginning, 100% means at the end)
        $position = strpos($normalizedTitle, $normalizedKeyword);
        $positionPercentage = $position !== false ? ($position / strlen($normalizedTitle)) * 100 : null;

        // Calculate title score based on a threshold
        $titleScore = $hasPrimaryKeyword ? ($isAtBeginning ? 1.0 : 0.8) : 0;
        $titleThreshold = 0.8; // The TITLE_THRESHOLD constant from the operation
        $meetsThreshold = $titleScore >= $titleThreshold;

        return [
            'title' => $title,
            'has_primary_keyword' => $hasPrimaryKeyword,
            'is_at_beginning' => $isAtBeginning,
            'position_percentage' => $positionPercentage,
            'meets_threshold' => $meetsThreshold
        ];
    }

    /**
     * Analyzes keyword usage in headings (H1-H6).
     *
     * @param array $keywords The keywords to check
     * @param string $content The content to analyze
     * @return array Analysis results
     */
    public function analyzeHeadingsKeywordUsage(array $keywords, string $content): array
    {
        $headingResults = [];
        $totalHeadings = 0;
        $headingsWithKeywords = 0;

        // Analyze each heading level
        for ($i = 1; $i <= 6; $i++) {
            preg_match_all("/<h{$i}[^>]*>(.*?)<\/h{$i}>/is", $content, $matches);

            if (!empty($matches[1])) {
                $headings = $matches[1];
                $totalHeadings += count($headings);

                $headingInfo = [
                    'total' => count($headings),
                    'with_keywords' => 0,
                    'keyword_coverage' => [],
                    'headings' => []
                ];

                foreach ($headings as $heading) {
                    $cleanHeading = wp_strip_all_tags($heading);
                    $normalizedHeading = strtolower($cleanHeading);
                    $keywordsFound = [];
                    $hasKeyword = false;

                    foreach ($keywords as $keyword) {
                        if (empty($keyword)) continue;

                        $normalizedKeyword = strtolower(trim($keyword));
                        if (str_contains($normalizedHeading, $normalizedKeyword)) {
                            $keywordsFound[] = $keyword;
                            $hasKeyword = true;

                            // Track which headings have which keywords
                            if (!isset($headingInfo['keyword_coverage'][$keyword])) {
                                $headingInfo['keyword_coverage'][$keyword] = 0;
                            }
                            $headingInfo['keyword_coverage'][$keyword]++;
                        }
                    }

                    $headingInfo['headings'][] = [
                        'text' => $cleanHeading,
                        'has_keyword' => $hasKeyword,
                        'keywords_found' => $keywordsFound
                    ];

                    if ($hasKeyword) {
                        $headingInfo['with_keywords']++;
                        $headingsWithKeywords++;
                    }
                }

                $headingResults["h$i"] = $headingInfo;
            }
        }

        // Calculate coverage score (what percentage of headings has keywords)
        $coverageScore = $totalHeadings > 0 ? $headingsWithKeywords / $totalHeadings : 0;

        return [
            'total_headings' => $totalHeadings,
            'headings_with_keywords' => $headingsWithKeywords,
            'coverage_percentage' => $totalHeadings > 0 ? round(($headingsWithKeywords / $totalHeadings) * 100, 2) : 0,
            'coverage_score' => $coverageScore,
            'headings_breakdown' => $headingResults
        ];
    }

    /**
     * Analyzes keyword usage in the first paragraph.
     *
     * @param string $primaryKeyword The primary keyword to check
     * @param string $content The content to analyze
     * @return array Analysis results
     */
    public function analyzeFirstParagraphKeywordUsage(string $primaryKeyword, string $content): array
    {
        $normalizedKeyword = strtolower(trim($primaryKeyword));
        $firstParagraph = '';
        $hasPrimaryKeyword = false;

        // Extract the first paragraph
        if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $content, $matches)) {
            $firstParagraph = wp_strip_all_tags($matches[0]);
            $normalizedParagraph = strtolower($firstParagraph);
            $hasPrimaryKeyword = str_contains($normalizedParagraph, $normalizedKeyword);
        }

        // Calculate score based on presence and context
        $contextScore = $hasPrimaryKeyword ? 1.0 : 0;
        $firstParagraphThreshold = 0.7; // The FIRST_PARAGRAPH_THRESHOLD constant from the operation
        $meetsThreshold = $contextScore >= $firstParagraphThreshold;

        return [
            'first_paragraph_text' => $firstParagraph,
            'has_primary_keyword' => $hasPrimaryKeyword,
            'word_count' => str_word_count(wp_strip_all_tags($firstParagraph)),
            'meets_threshold' => $meetsThreshold
        ];
    }

    /**
     * Analyzes the distribution of keywords throughout the content.
     *
     * @param array $keywords The keywords to analyze
     * @param string $cleanContent The cleaned text content
     * @return array Analysis results
     */
    public function keywordsDistributionAnalyse(array $keywords, string $cleanContent): array
    {
        $contentLength = strlen($cleanContent);
        $keywordDistributions = [];
        $averageDistributionScore = 0;
        $keywordsAnalyzed = 0;

        foreach ($keywords as $keyword) {
            if (empty($keyword)) continue;

            $normalizedKeyword = strtolower(trim($keyword));
            $positions = [];
            $offset = 0;

            // Find all occurrences of the keyword
            while (($pos = strpos(strtolower($cleanContent), $normalizedKeyword, $offset)) !== false) {
                $positions[] = $pos;
                $offset = $pos + strlen($normalizedKeyword);
            }

            // Calculate distribution metrics
            $distributionMetrics = $this->calculateDistributionMetrics($positions, $contentLength);
            $keywordDistributions[$keyword] = array_merge(
                ['occurrences' => count($positions), 'positions' => $positions],
                $distributionMetrics
            );

            if (count($positions) > 0) {
                $averageDistributionScore += $distributionMetrics['distribution_score'];
                $keywordsAnalyzed++;
            }
        }

        // Calculate average distribution score
        $finalDistributionScore = $keywordsAnalyzed > 0 ?
            $averageDistributionScore / $keywordsAnalyzed : 0;

        return [
            'content_length' => $contentLength,
            'keyword_distributions' => $keywordDistributions,
            'distribution_score' => $finalDistributionScore
        ];
    }

    /**
     * Calculates distribution metrics for keyword positions.
     *
     * @param array $positions Positions of keyword occurrences
     * @param int $contentLength Total content length
     * @return array Distribution metrics
     */
    public function calculateDistributionMetrics(array $positions, int $contentLength): array
    {
        // If no occurrences or only one, distribution is poor
        if (count($positions) <= 1) {
            return [
                'distribution_score' => count($positions) === 1 ? 0.1 : 0,
                'distribution_quality' => 'poor',
                'coverage_percentage' => count($positions) === 1 ?
                    round(($positions[0] / $contentLength) * 100, 2) : 0
            ];
        }

        // Calculate ideal positions (evenly distributed)
        $occurrences = count($positions);
        $idealGap = $contentLength / ($occurrences + 1);
        $idealPositions = [];

        for ($i = 1; $i <= $occurrences; $i++) {
            $idealPositions[] = $idealGap * $i;
        }

        // Calculate variance from ideal positions
        $totalDeviation = 0;
        for ($i = 0; $i < $occurrences; $i++) {
            $totalDeviation += abs($positions[$i] - $idealPositions[$i]);
        }

        // Average deviation as a percentage of content length
        $averageDeviation = ($totalDeviation / $occurrences) / $contentLength;

        // Distribution score (1 - normalized deviation, higher is better)
        $distributionScore = 1 - min(1, $averageDeviation * 5); // Scale factor of 5 to make the score more sensitive

        // Calculate content coverage (first to last keyword occurrence)
        $coveragePercentage = 0;
        if ($occurrences >= 2) {
            $firstPos = $positions[0];
            $lastPos = $positions[$occurrences - 1];
            $coverage = $lastPos - $firstPos;
            $coveragePercentage = round(($coverage / $contentLength) * 100, 2);
        }

        // Determine distribution quality
        $distributionQuality = 'poor';
        if ($distributionScore > 0.7) {
            $distributionQuality = 'excellent';
        } elseif ($distributionScore > 0.5) {
            $distributionQuality = 'good';
        } elseif ($distributionScore > 0.3) {
            $distributionQuality = 'fair';
        }

        return [
            'distribution_score' => $distributionScore,
            'distribution_quality' => $distributionQuality,
            'coverage_percentage' => $coveragePercentage
        ];
    }

    /**
     * Analyzes keyword usage across different content sections (intro, body, conclusion).
     *
     * @param array $keywords The keywords to analyze
     * @param string $content The content to analyze
     * @return array Analysis results
     */
    public function analyzeContentSectionsKeywordUsage(array $keywords, string $content): array
    {
        // Extract paragraphs
        preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $content, $matches);
        $paragraphs = array_map('wp_strip_all_tags', $matches[0]);
        $totalParagraphs = count($paragraphs);

        // If no paragraphs, return empty analysis
        if ($totalParagraphs === 0) {
            return [
                'section_coverage_score' => 0,
                'sections' => []
            ];
        }

        // Define sections (introduction, body, conclusion)
        $introSize = max(1, min(2, floor($totalParagraphs * 0.2))); // ~20% or at least 1
        $conclusionSize = max(1, min(2, floor($totalParagraphs * 0.2))); // ~20% or at least 1
        $bodySize = $totalParagraphs - $introSize - $conclusionSize;

        $introduction = array_slice($paragraphs, 0, $introSize);
        $body = array_slice($paragraphs, $introSize, $bodySize);
        $conclusion = array_slice($paragraphs, $introSize + $bodySize);

        // Analyze each section
        $sections = [
            'introduction' => $this->analyzeSection($keywords, $introduction),
            'body' => $this->analyzeSection($keywords, $body),
            'conclusion' => $this->analyzeSection($keywords, $conclusion)
        ];

        // Calculate section coverage score
        $sectionsWithKeywords = 0;

        foreach ($sections as $section) {
            if ($section['keywords_found'] > 0) {
                $sectionsWithKeywords++;
            }
        }

        $sectionCoverageScore = count($sections) > 0 ?
            $sectionsWithKeywords / count($sections) : 0;

        return [
            'section_coverage_score' => $sectionCoverageScore,
            'sections' => $sections
        ];
    }

    /**
     * Analyzes keyword usage in a specific content section.
     *
     * @param array $keywords The keywords to analyze
     * @param array $paragraphs The paragraphs in the section
     * @return array Analysis results
     */
    public function analyzeSection(array $keywords, array $paragraphs): array
    {
        $combinedText = implode(' ', $paragraphs);
        $normalizedText = strtolower($combinedText);
        $keywordsFound = 0;
        $keywordBreakdown = [];

        foreach ($keywords as $keyword) {
            if (empty($keyword)) continue;

            $normalizedKeyword = strtolower(trim($keyword));
            $count = substr_count($normalizedText, $normalizedKeyword);

            if ($count > 0) {
                $keywordsFound++;
                $keywordBreakdown[$keyword] = $count;
            }
        }

        $density = $this->calculateKeywordDensity($keywordBreakdown, $combinedText);

        return [
            'paragraphs' => count($paragraphs),
            'words' => str_word_count($combinedText),
            'keywords_found' => $keywordsFound,
            'keyword_breakdown' => $keywordBreakdown,
            'keyword_density' => $density
        ];
    }

    /**
     * Calculates keyword density for a section.
     *
     * @param array $keywordCounts Counts of each keyword
     * @param string $text Text to analyze
     * @return float Keyword density percentage
     */
    public function calculateKeywordDensity(array $keywordCounts, string $text): float
    {
        $wordCount = str_word_count($text);

        if ($wordCount === 0) {
            return 0;
        }

        $totalKeywordOccurrences = array_sum($keywordCounts);
        return round(($totalKeywordOccurrences / $wordCount) * 100, 2);
    }

    /**
     * Check if the related keyword appears in important content elements
     *
     * @param string $keyword The keyword to check
     * @param string $content The full HTML content
     * @return array Results of important element checks
     */
    public function checkKeywordOnImportantElements(string $keyword, string $content): array
    {
        // Check headings (h1-h6)
        $inHeadings = false;
        if (preg_match('/<h[1-6][^>]*>.*?' . preg_quote($keyword, '/') . '.*?<\/h[1-6]>/i', $content)) {
            $inHeadings = true;
        }

        // Check the first paragraph
        $inFirstParagraph = false;
        if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $content, $firstPara)) {
            $firstParaText = strtolower(wp_strip_all_tags($firstPara[0]));
            $inFirstParagraph = stripos($firstParaText, $keyword) !== false;
        }

        // Check the last paragraph
        $inLastParagraph = false;
        if (preg_match('/<p[^>]*>(?!.*<p[^>]*>)(.*?)<\/p>/is', $content, $lastPara)) {
            $lastParaText = strtolower(wp_strip_all_tags($lastPara[0]));
            $inLastParagraph = stripos($lastParaText, $keyword) !== false;
        }

        return [
            'headings' => $inHeadings,
            'first_paragraph' => $inFirstParagraph,
            'last_paragraph' => $inLastParagraph
        ];
    }

    /**
     * Calculate the proximity of related keyword to primary keyword instances
     *
     * @param string $relatedKeyword The related keyword
     * @param string $primaryKeyword The primary keyword
     * @param string $content The content
     * @return float Proximity score from 0 to 1
     */
    public function calculateProximityToPrimary(
        string $relatedKeyword,
        string $primaryKeyword,
        string $content
    ): float
    {
        // Find positions of the primary keyword
        $primaryPositions = [];
        $offset = 0;
        while (($pos = stripos($content, $primaryKeyword, $offset)) !== false) {
            $primaryPositions[] = $pos;
            $offset = $pos + strlen($primaryKeyword);
        }

        // Find positions of related keyword
        $relatedPositions = [];
        $offset = 0;
        while (($pos = stripos($content, $relatedKeyword, $offset)) !== false) {
            $relatedPositions[] = $pos;
            $offset = $pos + strlen($relatedKeyword);
        }

        // If either keyword is missing, return 0
        if (empty($primaryPositions) || empty($relatedPositions)) {
            return 0;
        }

        // Calculate minimum distances between each related keyword and any primary keyword
        $minDistances = [];
        foreach ($relatedPositions as $relatedPos) {
            $distances = [];
            foreach ($primaryPositions as $primaryPos) {
                $distances[] = abs($relatedPos - $primaryPos);
            }
            $minDistances[] = min($distances);
        }

        // Calculate average minimum distance
        $avgMinDistance = array_sum($minDistances) / count($minDistances);

        // Convert to proximity score (closer = higher score)
        // Use content length as a reference for normalization
        $contentLength = strlen($content);
        return 1 - min(1, $avgMinDistance / ($contentLength / 4));
    }

    /**
     * Analyze the semantic context of a keyword in relation to the primary keyword
     *
     * @param string $relatedKeyword The related keyword
     * @param string $primaryKeyword The primary keyword
     * @param string $content The content
     * @return float Context score from 0 to 1
     */
    public function semanticContextScore(
        string $relatedKeyword,
        string $primaryKeyword,
        string $content
    ): float
    {
        // Split content into sentences
        $sentences = preg_split('/(?<=[.!?])\s+/', $content, -1, PREG_SPLIT_NO_EMPTY);

        // Count sentences containing related keyword
        $sentencesWithRelatedKeyword = 0;

        // Count sentences that contain both primary and related keyword
        $sentencesWithBothKeywords = 0;

        // Count sentences with related keyword in a meaningful context
        $sentencesWithMeaningfulContext = 0;

        // Words that suggest meaningful context when appearing near related keywords
        $contextSignalWords = [
            'relevant', 'important', 'significant', 'key', 'essential', 'crucial',
            'related', 'similar', 'also', 'additionally', 'furthermore', 'moreover',
            'example', 'instance', 'such as', 'like', 'including', 'includes',
            'because', 'therefore', 'thus', 'hence', 'accordingly', 'consequently'
        ];

        foreach ($sentences as $sentence) {
            $lowercaseSentence = strtolower($sentence);

            // Check if sentence contains related keyword
            if (stripos($lowercaseSentence, $relatedKeyword) !== false) {
                $sentencesWithRelatedKeyword++;

                // Check if sentence also contains primary keyword
                if (stripos($lowercaseSentence, $primaryKeyword) !== false) {
                    $sentencesWithBothKeywords++;
                }

                // Check for context signal words near the related keyword
                foreach ($contextSignalWords as $signalWord) {
                    // Context words should be within reasonable proximity to the related keyword
                    $keywordPos = stripos($lowercaseSentence, $relatedKeyword);
                    $signalPos = stripos($lowercaseSentence, $signalWord);

                    if ($signalPos !== false) {
                        // Check if the signal word is within 10 words of the related keyword
                        $distance = abs($keywordPos - $signalPos);
                        if ($distance < 50) { // Approximate character count for 10 words
                            $sentencesWithMeaningfulContext++;
                            break; // Count each sentence only once
                        }
                    }
                }
            }
        }

        // Calculate context score based on:
        // 1. Percentage of related keyword mentions that also contain the primary keyword
        // 2. Percentage of related keyword mentions that have meaningful context

        $primaryContextScore = $sentencesWithRelatedKeyword > 0 ?
            $sentencesWithBothKeywords / $sentencesWithRelatedKeyword : 0;

        $meaningfulContextScore = $sentencesWithRelatedKeyword > 0 ?
            $sentencesWithMeaningfulContext / $sentencesWithRelatedKeyword : 0;

        // Overall context score with weighted components
        return ($primaryContextScore * 0.6) + ($meaningfulContextScore * 0.4);
    }

    /**
     * Analyze how well a keyword is distributed throughout the content
     *
     * @param string $keyword The keyword to analyze
     * @param string $content The content to analyze
     * @return float Distribution score from 0 to 1
     */
    public function keywordDistributionScore(string $keyword, string $content): float
    {
        // Find all occurrences of the keyword
        $positions = [];
        $offset = 0;
        while (($pos = stripos($content, $keyword, $offset)) !== false) {
            $positions[] = $pos;
            $offset = $pos + strlen($keyword);
        }

        // If fewer than 2 occurrences, can't really analyze distribution
        if (count($positions) < 2) {
            return 0.5; // Neutral score
        }

        // Calculate content length
        $contentLength = strlen($content);

        // Ideal spacing would be even distribution throughout the content
        $idealSpacing = $contentLength / (count($positions) + 1);

        // Calculate the variance from ideal spacing
        $spacingVariances = [];
        for ($i = 0; $i < count($positions) - 1; $i++) {
            $actualSpacing = $positions[$i + 1] - $positions[$i];
            $variance = abs($actualSpacing - $idealSpacing) / $idealSpacing;
            $spacingVariances[] = $variance;
        }

        // Average variance (lower is better)
        $avgVariance = array_sum($spacingVariances) / count($spacingVariances);

        // Convert to a score where 0 = poor distribution, 1 = perfect distribution
        return max(0, 1 - min(1, $avgVariance));
    }

    /**
     * Analyze a single related keyword
     *
     * @param string $relatedKeyword The related keyword to analyze
     * @param string $primaryKeyword The primary keyword for context
     * @param string $fullContent The full HTML content
     * @param string $cleanContent The cleaned text content
     * @param int $totalWordCount Total word count
     * @return array Analysis results
     */
    public function analyzeRelatedKeyword(
        string $relatedKeyword,
        string $primaryKeyword,
        string $fullContent,
        string $cleanContent,
        int    $totalWordCount
    ): array
    {
        // Normalize keywords
        $normalizedRelatedKeyword = strtolower(trim($relatedKeyword));
        $normalizedPrimaryKeyword = strtolower(trim($primaryKeyword));

        // Count occurrences
        $count = substr_count(strtolower($cleanContent), $normalizedRelatedKeyword);
        $isPhrasePresent = $count > 0;

        // If the keyword isn't present, return basic analysis
        if (!$isPhrasePresent) {
            return [
                'keyword' => $relatedKeyword,
                'count' => 0,
                'is_present' => false,
                'density' => 0,
                'distribution_score' => 0,
                'context_score' => 0,
                'proximity_to_primary' => 0,
                'in_important_elements' => [
                    'headings' => false,
                    'first_paragraph' => false,
                    'last_paragraph' => false
                ]
            ];
        }

        // Calculate keyword density
        $density = $totalWordCount > 0 ? ($count / $totalWordCount) * 100 : 0;

        // Analyze distribution throughout the content
        $distributionScore = $this->keywordDistributionScore($normalizedRelatedKeyword, $cleanContent);

        // Analyze semantic context
        $contextScore = $this->semanticContextScore(
            $normalizedRelatedKeyword,
            $normalizedPrimaryKeyword,
            $cleanContent
        );

        // Check proximity to the primary keyword
        $proximityScore = $this->calculateProximityToPrimary(
            $normalizedRelatedKeyword,
            $normalizedPrimaryKeyword,
            $cleanContent
        );

        // Check if the related keyword appears in important elements
        $inImportantElements = $this->checkKeywordOnImportantElements(
            $normalizedRelatedKeyword,
            $fullContent
        );

        return [
            'keyword' => $relatedKeyword,
            'count' => $count,
            'is_present' => true,
            'density' => round($density, 2),
            'distribution_score' => round($distributionScore, 2),
            'context_score' => round($contextScore, 2),
            'proximity_to_primary' => round($proximityScore, 2),
            'in_important_elements' => $inImportantElements
        ];
    }

    /**
     * Determine the status of keyword density
     *
     * @param float $density The current density
     * @param float $optimalMin The minimum optimal density
     * @param float $optimalMax The maximum optimal density
     * @return string The status (optimal, overused, severely_overused, underused)
     */
    public function getKeywordDensityStatus(float $density, float $optimalMin, float $optimalMax): string
    {
        if ($density < $optimalMin) {
            if ($density < 0.1) {
                return 'severely_underused';
            }
            return 'underused';
        }

        if ($density > $optimalMax) {
            if ($density > 5.0) {
                return 'severely_overused';
            }
            return 'overused';
        }

        return 'optimal';
    }

    /**
     * Calculate a score based on how close the density is to optimal
     *
     * @param float $density The current density
     * @param float $optimalMin The minimum optimal density
     * @param float $optimalMax The maximum optimal density
     * @return float A score from 0-1
     */
    public function calculateKeywordDensityScore(float $density, float $optimalMin, float $optimalMax): float
    {
        // Start with a perfect score
        $score = 1.0;

        // If density is in the optimal range, keep the perfect score
        if ($density >= $optimalMin && $density <= $optimalMax) {
            return $score;
        }

        // If underused, calculate how far from the minimum
        if ($density < $optimalMin) {
            // If zero, the score is 0
            if ($density <= 0) {
                return 0;
            }

            // Calculate the ratio of actual to optimal
            $ratio = $density / $optimalMin;
            $score = $ratio; // Linear penalty
        } // If overused, calculate how far above the maximum
        else {
            // Calculate excess ratio
            $excess = ($density - $optimalMax) / $optimalMax;

            // More severe penalty for overuse
            $score = 1 - min(1, $excess * 1.5);
        }

        // Ensure the score is between 0 and 1
        return max(0, min(1, $score));
    }

    /**
     * Calculate the overall balance between primary and secondary keywords
     *
     * @param array $primaryAnalysis Primary keyword analysis
     * @param array $secondaryAnalyses Secondary keywords analysis
     * @return array Overall balance metrics
     */
    public function calculateKeywordsDensityOverallBalance(array $primaryAnalysis, array $secondaryAnalyses): array
    {
        // If no primary keyword or no secondary keywords, return basic info
        if (empty($primaryAnalysis) || empty($secondaryAnalyses)) {
            return [
                'status' => 'incomplete',
                'message' => __('Cannot calculate balance without both primary and secondary keywords', 'beyondseo'),
                'score' => 0.5
            ];
        }

        // Get primary keyword density
        $primaryDensity = $primaryAnalysis['density'] ?? 0;

        // Calculate average secondary keyword density
        $secondaryDensities = array_map(function ($analysis) {
            return $analysis['density'] ?? 0;
        }, $secondaryAnalyses);

        $avgSecondaryDensity = count($secondaryDensities) > 0 ?
            array_sum($secondaryDensities) / count($secondaryDensities) : 0;

        // Calculate a ratio of primary to secondary
        $ratio = $avgSecondaryDensity > 0 ? $primaryDensity / $avgSecondaryDensity : PHP_FLOAT_MAX;

        // Ideal ratio is around 1.5-3 (primary should be somewhat more prominent)
        if ($ratio < 1) {
            $status = 'secondary_dominant';
            $message = __('Secondary keywords are more prominent than your primary keyword. Consider rebalancing.', 'beyondseo');
            $score = 0.6; // Not terrible but not optimal
        } elseif ($ratio <= 3) {
            $status = 'well_balanced';
            $message = __('Good balance between primary and secondary keywords.', 'beyondseo');
            $score = 1.0; // Optimal
        } elseif ($ratio <= 5) {
            $status = 'primary_heavy';
            $message = __('Primary keyword is significantly more used than secondary keywords. Consider more topic diversity.', 'beyondseo');
            $score = 0.7; // Not optimal but still decent
        } else {
            $status = 'primary_dominant';
            $message = __('Content focuses too heavily on primary keyword at the expense of topic diversity.', 'beyondseo');
            $score = 0.4; // Problematic
        }

        return [
            'status' => $status,
            'message' => $message,
            'primary_density' => $primaryDensity,
            'avg_secondary_density' => $avgSecondaryDensity,
            'ratio' => min($ratio, 100), // Cap at 100 for readability
            'score' => $score
        ];
    }

    /**
     * Analyze the density of a keyword in the content
     *
     * @param string $keyword The keyword to analyze
     * @param string $content The full HTML content
     * @param string $cleanContent The cleaned text content
     * @param int $totalWordCount Total word count
     * @param bool $isShortContent Flag indicating if the content is short
     * @param bool $isPrimary Flag indicating if this is the primary keyword
     * @return array Analysis results
     */
    public function analyzeKeywordDensity(
        int $contentId,
        string $keyword,
        string $content,
        string $cleanContent,
        int    $totalWordCount,
        bool   $isShortContent = false,
        bool   $isPrimary = false
    ): array
    {
        // Use the existing keyword analysis function from the WordPress trait
        $keywordAnalysis = $this->analyzeKeywordInContent($keyword, $content, $cleanContent);

        // Get the density from the analysis
        $density = $keywordAnalysis['density'];

        // Determine the optimal range based on content length and whether it's a primary keyword
        $optimalMin = $isShortContent ? SeoOptimiserConfig::OPTIMAL_DENSITY_MIN * 0.8 : SeoOptimiserConfig::OPTIMAL_DENSITY_MIN;
        $optimalMax = $isShortContent ? SeoOptimiserConfig::OPTIMAL_DENSITY_MAX * 1.2 : SeoOptimiserConfig::OPTIMAL_DENSITY_MAX;

        // For secondary keywords, we generally want slightly lower density
        if (!$isPrimary) {
            $optimalMin *= 0.7;
            $optimalMax *= 0.7;
        }

        // Determine the status based on density
        $status = $this->getKeywordDensityStatus($density, $optimalMin, $optimalMax);

        // Calculate the keyword count
        $keywordCount = $keywordAnalysis['count'];

        // Expected count based on optimal density
        $expectedMinCount = ceil(($totalWordCount * $optimalMin) / 100);
        $expectedMaxCount = floor(($totalWordCount * $optimalMax) / 100);

        // Adjustment needed
        $adjustmentNeeded = 0;
        if ($status === 'underused') {
            $adjustmentNeeded = $expectedMinCount - $keywordCount;
        } elseif ($status === 'overused') {
            $adjustmentNeeded = $keywordCount - $expectedMaxCount;
        }

        // Score from 0-1 based on how close to optimal the density is
        $densityScore = $this->calculateKeywordDensityScore($density, $optimalMin, $optimalMax);

        // Check if the keyword is used in important places
        $structuralUsage = [
            'in_title' => $this->isKeywordInTitle($keyword, $contentId),
            'in_meta_description' => $this->isKeywordInMetaDescription($keyword, $contentId),
            'in_url' => $this->isKeywordInSlug($keyword, $contentId),
            'in_headings' => $keywordAnalysis['in_headings'] > 0,
            'in_first_paragraph' => $keywordAnalysis['in_first_paragraph']
        ];

        return [
            'density' => $density,
            'optimal_range' => [
                'min' => $optimalMin,
                'max' => $optimalMax
            ],
            'status' => $status,
            'count' => $keywordCount,
            'expected_range' => [
                'min' => $expectedMinCount,
                'max' => $expectedMaxCount
            ],
            'adjustment_needed' => $adjustmentNeeded,
            'score' => $densityScore,
            'structural_usage' => $structuralUsage,
            'distribution' => $keywordAnalysis['distribution_score'],
            'variations' => $keywordAnalysis['variations'],
            'detailed_analysis' => $keywordAnalysis
        ];
    }

    /**
     * Find conflicts for a specific post
     *
     * @param int $postId Post ID
     * @param array $cannibalizationIssues Cannibalization issues
     * @return array Conflicts for the post
     */
    public function findCannibalizationConflictsForPost(int $postId, array $cannibalizationIssues): array
    {
        $conflicts = [];

        foreach ($cannibalizationIssues as $issue) {
            // Check primary keyword conflicts
            if ($issue['type'] === 'primary_keyword_conflict') {
                foreach ($issue['conflicting_pages'] as $page) {
                    if ($page['post_id'] === $postId) {
                        $conflicts[] = [
                            'type' => 'primary_keyword_conflict',
                            'keyword' => $issue['keyword'],
                            'conflicting_pages' => array_filter($issue['conflicting_pages'], function ($p) use ($postId) {
                                return $p['post_id'] !== $postId;
                            }),
                            'recommendation' => $issue['recommendation']
                        ];
                        break;
                    }
                }
            }

            // Check keyword overuse
            if ($issue['type'] === 'keyword_overuse') {
                foreach ($issue['conflicting_pages'] as $page) {
                    if ($page['post_id'] === $postId) {
                        $conflicts[] = [
                            'type' => 'keyword_overuse',
                            'keyword' => $issue['keyword'],
                            'conflicting_pages' => array_filter($issue['conflicting_pages'], function ($p) use ($postId) {
                                return $p['post_id'] !== $postId;
                            }),
                            'recommendation' => $issue['recommendation']
                        ];
                        break;
                    }
                }
            }

            // Check semantic similarity
            if ($issue['type'] === 'semantic_similarity') {
                foreach ($issue['conflicting_pages'] as $page) {
                    if ($page['post_id'] === $postId) {
                        $conflicts[] = [
                            'type' => 'semantic_similarity',
                            'keywords' => $issue['keywords'],
                            'similarity' => $issue['similarity'],
                            'conflicting_pages' => array_filter($issue['conflicting_pages'], function ($p) use ($postId) {
                                return $p['post_id'] !== $postId;
                            }),
                            'recommendation' => $issue['recommendation']
                        ];
                        break;
                    }
                }
            }
        }

        return $conflicts;
    }

    /**
     * Detect cannibalization issues in the keyword map
     *
     * @param array $keywordMap Keyword mapping data
     * @return array Cannibalization issues
     */
    public function detectCannibalizationIssues(array $keywordMap): array
    {
        $issues = [];
        $keywordPostMap = [];

        // First, build a map of keywords to posts
        foreach ($keywordMap as $entry) {
            $postId = $entry['post_id'];

            // Add primary keyword
            if (!empty($entry['primary_keyword'])) {
                $normalizedKeyword = $this->normalizeKeyword($entry['primary_keyword']);
                $keywordPostMap[$normalizedKeyword][] = [
                    'post_id' => $postId,
                    'title' => $entry['title'],
                    'url' => $entry['url'],
                    'type' => 'primary'
                ];
            }

            // Add secondary keywords
            foreach ($entry['secondary_keywords'] as $secondaryKeyword) {
                $normalizedKeyword = $this->normalizeKeyword($secondaryKeyword);
                $keywordPostMap[$normalizedKeyword][] = [
                    'post_id' => $postId,
                    'title' => $entry['title'],
                    'url' => $entry['url'],
                    'type' => 'secondary'
                ];
            }
        }

        // Identify cannibalization issues (multiple posts targeting the same primary keyword)
        foreach ($keywordPostMap as $keyword => $posts) {
            $primaryTargets = array_filter($posts, function ($post) {
                return $post['type'] === 'primary';
            });

            if (count($primaryTargets) > 1) {
                $issues[] = [
                    'keyword' => $keyword,
                    'severity' => 'high',
                    'type' => 'primary_keyword_conflict',
                    'conflicting_pages' => $primaryTargets,
                    'recommendation' => 'Consolidate content or reassign primary keywords to prevent cannibalization'
                ];
            }

            // Check for secondary keywords that might be cannibalizing
            if (count($posts) > 2) {
                $issues[] = [
                    'keyword' => $keyword,
                    'severity' => 'medium',
                    'type' => 'keyword_overuse',
                    'conflicting_pages' => $posts,
                    'recommendation' => 'Consider consolidating content or creating a more focused topic cluster'
                ];
            }
        }

        // Detect semantic cannibalization (similar but not identical keywords)
        $issues = array_merge($issues, $this->detectSemanticCannibalization($keywordMap));

        return array_map(fn($a) => $a, $issues);
    }

    /**
     * Detect semantic cannibalization (similar but not identical keywords)
     *
     * @param array $keywordMap Keyword mapping data
     * @return array Semantic cannibalization issues
     */
    public function detectSemanticCannibalization(array $keywordMap): array
    {
        $issues = [];
        $primaryKeywords = [];

        // Extract all primary keywords
        foreach ($keywordMap as $entry) {
            if (!empty($entry['primary_keyword'])) {
                $primaryKeywords[$entry['post_id']] = $entry['primary_keyword'];
            }
        }

        // Compare keywords for similarity
        foreach ($primaryKeywords as $postId1 => $keyword1) {
            foreach ($primaryKeywords as $postId2 => $keyword2) {
                // Skip the same post
                if ($postId1 === $postId2) {
                    continue;
                }

                // Calculate similarity
                $similarity = $this->calculateKeywordSimilarity($keyword1, $keyword2);

                // If similarity is above threshold, it's a potential cannibalization issue
                if ($similarity >= SeoOptimiserConfig::CANNIBALIZATION_THRESHOLD) {
                    // Find the posts in the keyword map
                    $post1 = array_filter($keywordMap, function ($entry) use ($postId1) {
                        return $entry['post_id'] === $postId1;
                    });
                    $post1 = reset($post1);

                    $post2 = array_filter($keywordMap, function ($entry) use ($postId2) {
                        return $entry['post_id'] === $postId2;
                    });
                    $post2 = reset($post2);

                    $issues[] = [
                        'keywords' => [$keyword1, $keyword2],
                        'similarity' => $similarity,
                        'severity' => 'medium',
                        'type' => 'semantic_similarity',
                        'conflicting_pages' => [
                            [
                                'post_id' => $postId1,
                                'title' => $post1['title'],
                                'url' => $post1['url'],
                                'keyword' => $keyword1
                            ],
                            [
                                'post_id' => $postId2,
                                'title' => $post2['title'],
                                'url' => $post2['url'],
                                'keyword' => $keyword2
                            ]
                        ],
                        'recommendation' => 'Differentiate content focus or combine into a single comprehensive page'
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Analyze keyword coverage across the site
     *
     * @param array $keywordMap Keyword mapping data
     * @return array Coverage analysis results
     */
    public function analyzeKeywordCoverage(array $keywordMap): array
    {
        // Extract all keywords
        $allKeywords = [];
        foreach ($keywordMap as $entry) {
            if (!empty($entry['primary_keyword'])) {
                $allKeywords[] = $entry['primary_keyword'];
            }
            $allKeywords = array_merge($allKeywords, $entry['secondary_keywords']);
        }

        // Count keyword frequency
        $keywordFrequency = array_count_values($allKeywords);
        arsort($keywordFrequency);

        // Identify keyword gaps (important keywords not targeted)
        $keywordGaps = $this->identifyKeywordGaps($keywordFrequency);

        // Calculate keyword distribution metrics
        $totalKeywords = count($allKeywords);
        $uniqueKeywords = count($keywordFrequency);
        $keywordDiversity = $uniqueKeywords > 0 ? $totalKeywords / $uniqueKeywords : 0;

        // Identify overused and underused keywords
        $overusedKeywords = array_filter($keywordFrequency, function ($count) {
            return $count > 3; // More than 3 pages using the same keyword
        });

        $underusedKeywords = array_filter($keywordFrequency, function ($count) {
            return $count === 1; // Only one page using this keyword
        });

        return [
            'total_keywords' => $totalKeywords,
            'unique_keywords' => $uniqueKeywords,
            'keyword_diversity_score' => round($keywordDiversity, 2),
            'most_used_keywords' => array_slice($keywordFrequency, 0, 10, true),
            'overused_keywords' => $overusedKeywords,
            'underused_keywords' => $underusedKeywords,
            'keyword_gaps' => $keywordGaps
        ];
    }

    /**
     * Generate topic clusters based on keyword relationships
     *
     * @param array $keywordMap Keyword mapping data
     * @return array Topic clusters
     */
    public function generateTopicClusters(array $keywordMap): array
    {
        $clusters = [];
        $processedKeywords = [];

        // Process each entry in the keyword map
        foreach ($keywordMap as $entry) {
            $primaryKeyword = $entry['primary_keyword'];

            // Skip if no primary keyword or already processed
            if (empty($primaryKeyword) || in_array($primaryKeyword, $processedKeywords)) {
                continue;
            }

            // Start a new cluster
            $cluster = [
                'main_topic' => $primaryKeyword,
                'pillar_page' => [
                    'post_id' => $entry['post_id'],
                    'title' => $entry['title'],
                    'url' => $entry['url']
                ],
                'supporting_pages' => [],
                'related_keywords' => $entry['secondary_keywords']
            ];

            // Find supporting pages (pages with related keywords)
            foreach ($keywordMap as $supportingEntry) {
                // Skip the pillar page itself
                if ($supportingEntry['post_id'] === $entry['post_id']) {
                    continue;
                }

                // Check if this page has related keywords
                $isRelated = false;

                // Check primary keyword
                if (!empty($supportingEntry['primary_keyword'])) {
                    $similarity = $this->calculateKeywordSimilarity($primaryKeyword, $supportingEntry['primary_keyword']);
                    if ($similarity >= 40) { // 40% similarity threshold for related content
                        $isRelated = true;
                    }
                }

                // Check secondary keywords
                if (!$isRelated) {
                    foreach ($entry['secondary_keywords'] as $secondaryKeyword) {
                        if (in_array($secondaryKeyword, $supportingEntry['secondary_keywords'])) {
                            $isRelated = true;
                            break;
                        }
                    }
                }

                // Add to supporting pages if related
                if ($isRelated) {
                    $cluster['supporting_pages'][] = [
                        'post_id' => $supportingEntry['post_id'],
                        'title' => $supportingEntry['title'],
                        'url' => $supportingEntry['url'],
                        'primary_keyword' => $supportingEntry['primary_keyword']
                    ];
                }
            }

            // Only add clusters with supporting pages
            if (!empty($cluster['supporting_pages'])) {
                $clusters[] = $cluster;
            }

            // Mark as processed
            $processedKeywords[] = $primaryKeyword;
        }

        return $clusters;
    }

    /**
     * Check if secondary keywords are covered in headings
     *
     * @param string $content HTML content
     * @param array $secondaryKeywords Array of secondary keywords
     * @return array Covered keywords
     */
    public function checkKeywordCoverageInHeadings(string $content, array $secondaryKeywords): array
    {
        $headings = $this->getHeadingsFromContent($content);
        $covered = [];
        foreach ($secondaryKeywords as $keyword) {
            foreach ($headings as $heading) {
                if (stripos($heading['text'], $keyword) !== false) {
                    $covered[] = $keyword;
                    break;
                }
            }
        }
        return $covered;
    }

    /**
     * Check for the presence of keywords in a text string.
     *
     * @param string $text The text to check
     * @param array $keywords Keywords to look for
     * @return array Results of the keyword check
     */
    public function checkKeywordPresence(string $text, array $keywords): array
    {
        $text = trim($text);
        if (empty($text)) {
            return [
                'has_any_keyword' => false,
                'keywords_found' => 0,
                'keywords_missing' => count($keywords),
                'details' => []
            ];
        }

        $details = [];
        $keywordsFound = 0;

        foreach ($keywords as $keyword) {
            $found = false
                ? str_contains($text, $keyword)
                : stripos($text, $keyword) !== false;

            $details[$keyword] = $found;

            if ($found) {
                $keywordsFound++;
            }
        }

        return [
            'has_any_keyword' => $keywordsFound > 0,
            'keywords_found' => $keywordsFound,
            'keywords_missing' => count($keywords) - $keywordsFound,
            'details' => $details
        ];
    }
}
