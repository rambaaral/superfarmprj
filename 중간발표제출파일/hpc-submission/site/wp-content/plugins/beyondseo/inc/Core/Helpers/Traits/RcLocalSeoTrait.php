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
use DOMXPath;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Modules\ModuleLibrary\Technical\MetaTags\MetaTags;

/**
 * Trait RcReadabilityAnalysisTrait
 *
 * This trait provides methods for analyzing readability metrics of content.
 */
trait RcLocalSeoTrait
{
    use RcLoggerTrait;

    /**
     * Determines if a keyword likely refers to a location (i18n-aware).
     *
     * Heuristics:
     * - Locale-specific location cue words/phrases (from installed locales).
     * - Locale-specific postal/ZIP/CEP formats.
     * - Proper-noun city-like patterns using Unicode (supports diacritics).
     * - Short multi-word proper-noun sequences.
     *
     * @param string $keyword
     * @return bool
     */
    public function isProbablyLocation(string $keyword): bool
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return false;
        }

        // Determine current WP locale (e.g., en_US, de_DE, fr_FR, es_ES, it_IT, nl_NL, pl_PL, pt_BR)
        $locale = function_exists('get_locale') ? (string) get_locale() : 'en_US';

        // Normalize locale (ensure underscore variant)
        $locale = str_replace('-', '_', $locale);

        // Locale-specific cue words/phrases for locations
        $locationWords = [
            'en_US' => [
                'in', 'near', 'around', 'local', 'downtown', 'city of', 'county', 'district', 'region', 'area'
            ],
            'de_DE' => [
                'in der nähe', 'nahe', 'umgebung', 'lokal', 'innenstadt', 'stadt', 'stadt von', 'landkreis', 'kreis', 'bezirk', 'region', 'gebiet'
            ],
            'fr_FR' => [
                'à proximité', 'près de', 'autour de', 'local', 'centre-ville', 'ville de', 'département', 'arrondissement', 'région', 'zone'
            ],
            'es_ES' => [
                'cerca de', 'alrededor de', 'local', 'centro', 'centro de la ciudad', 'ciudad de', 'provincia', 'distrito', 'región', 'zona'
            ],
            'it_IT' => [
                'vicino a', 'intorno a', 'locale', 'centro', 'centro città', 'città di', 'provincia', 'distretto', 'regione', 'area'
            ],
            'nl_NL' => [
                'in de buurt van', 'omgeving', 'rondom', 'lokaal', 'binnenstad', 'stad', 'stad van', 'gemeente', 'district', 'regio', 'gebied'
            ],
            'pl_PL' => [
                'w pobliżu', 'w okolicy', 'blisko', 'lokalny', 'centrum', 'śródmieście', 'miasto', 'powiat', 'dzielnica', 'region', 'obszar'
            ],
            'pt_BR' => [
                'perto de', 'próximo de', 'ao redor de', 'local', 'centro', 'centro da cidade', 'cidade de', 'município', 'distrito', 'região', 'área', 'bairro'
            ],
        ];

        // Fallback logic: exact locale, then base language (e.g., pt), then English
        $baseLang = substr($locale, 0, 2);
        $words = $locationWords[$locale]
            ?? ($locationWords[$baseLang] ?? $locationWords['en_US']);

        // Build a robust Unicode regex for cue words/phrases (allow flexible whitespace)
        $escaped = array_map(static function ($w) {
            $w = preg_quote($w, '/');
            // allow flexible whitespace in multi-word phrases
            return str_replace('\ ', '\\s+', $w);
        }, $words);
        if (!empty($escaped)) {
            $pattern = '/(?:^|[\s,.;:()\-])(?:' . implode('|', $escaped) . ')(?:$|[\s,.;:()\-])/iu';
            if (preg_match($pattern, $keyword)) {
                return true;
            }
        }

        // Locale-specific postal code patterns
        $postalPatterns = [
            'en_US' => '/\b\d{5}(?:-\d{4})?\b/u',      // 12345 or 12345-6789
            'de_DE' => '/\b\d{5}\b/u',                  // 12345
            'fr_FR' => '/\b\d{5}\b/u',                  // 75001
            'es_ES' => '/\b\d{5}\b/u',                  // 28013
            'it_IT' => '/\b\d{5}\b/u',                  // 20121
            'nl_NL' => '/\b\d{4}\s?[A-Z]{2}\b/u',      // 1234 AB or 1234AB
            'pl_PL' => '/\b\d{2}-\d{3}\b/u',           // 00-001
            'pt_BR' => '/\b\d{5}-?\d{3}\b/u',          // 12345-678 or 12345678
        ];
        $postalPattern = $postalPatterns[$locale] ?? $postalPatterns[$baseLang] ?? $postalPatterns['en_US'];
        if (preg_match($postalPattern, $keyword)) {
            return true;
        }

        // City-like proper noun pattern (supports diacritics, hyphens, apostrophes)
        // Example matches: "São Paulo", "München", "'s-Hertogenbosch", "New York, NY"
        if (preg_match('/\b\p{Lu}[\p{L}\'-]+(?:\s+\p{Lu}[\p{L}\'-]+)*(?:,\s*\p{Lu}{2})?\b/u', $keyword)) {
            return true;
        }

        // Heuristic: short multi-word phrase with many capitalized words
        if (preg_match_all('/\b\p{L}[\p{L}\'-]*\b/u', $keyword, $wordsMatches)) {
            $wordCount = count($wordsMatches[0]);
            if ($wordCount > 1 && $wordCount <= 4) {
                preg_match_all('/\b\p{Lu}[\p{L}\'-]*/u', $keyword, $capsMatches);
                if (count($capsMatches[0]) >= $wordCount - 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get local keywords for the post from various sources
     *
     * @param int $postId The ID of the post
     * @return array List of local keywords
     */
    public function getLocalKeywords(int $postId): array
    {
        // Try to get local keywords from post-meta
        $localKeywordsFromMeta = get_post_meta($postId, BaseConstants::META_KEY_LOCAL_KEYWORDS, true);
        if (!empty($localKeywordsFromMeta)) {
            if (is_string($localKeywordsFromMeta)) {
                return array_map('trim', explode(',', $localKeywordsFromMeta));
            } elseif (is_array($localKeywordsFromMeta)) {
                return $localKeywordsFromMeta;
            }
        }

        // Fallback to site-wide local keywords from options
        $siteLocalKeywords = get_option(BaseConstants::META_KEY_LOCAL_KEYWORDS, '');
        if (!empty($siteLocalKeywords)) {
            if (is_string($siteLocalKeywords)) {
                return array_map('trim', explode(',', $siteLocalKeywords));
            } elseif (is_array($siteLocalKeywords)) {
                return $siteLocalKeywords;
            }
        }

        // If no explicit local keywords set, try to determine from business location
        $localLocation = get_option(MetaTags::META_SEO_BUSINESS_LOCATION, '');
        if (!empty($localLocation)) {
            $locationParts = array_map('trim', explode(',', $localLocation));
            return array_filter($locationParts);
        }

        // Last resort: Extract local keywords from primary and secondary keywords
        $allKeywords = [];
        $primaryKeyword = $this->getPrimaryKeyword($postId);
        $secondaryKeywords = $this->getSecondaryKeywords($postId);

        if (!empty($primaryKeyword)) {
            $allKeywords[] = $primaryKeyword;
        }

        if (!empty($secondaryKeywords)) {
            $allKeywords = array_merge($allKeywords, $secondaryKeywords);
        }

        // Filter to keep only likely location-based keywords
        $localKeywordCandidates = [];
        foreach ($allKeywords as $keyword) {
            if ($this->isProbablyLocation($keyword)) {
                $localKeywordCandidates[] = $keyword;
            }
        }

        return $localKeywordCandidates;
    }

    /**
     * Analyze the content for local business signals such as addresses, phone numbers, etc.
     *
     * @param string $content The full HTML content
     * @param array $localKeywords The local keywords to check for
     * @return array Analysis results containing local business signals
     */
    public function analyzeLocalBusinessSignals(string $content, array $localKeywords): array
    {
        $cleanContent = $this->cleanContent($content);

        // Check for address patterns
        $hasAddress = preg_match('/\b\d+\s+[A-Za-z]+\s+(?:Street|St|Avenue|Ave|Road|Rd|Boulevard|Blvd|Drive|Dr|Lane|Ln|Court|Ct)\b/i', $cleanContent);

        // Check for phone number patterns
        $hasPhone = preg_match('/(?:\+\d{1,2}\s)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}/i', $cleanContent);

        // Check for business hours patterns
        $hasBusinessHours = preg_match('/(?:open|hours|business hours|operating hours|we are open|opening times).*?(?:\d{1,2}(?::\d{2})?\s*(?:am|pm|a\.m\.|p\.m\.)|monday|tuesday|wednesday|thursday|friday|saturday|sunday)/is', $cleanContent);

        // Check for local keywords in the content
        $localKeywordMatches = [];
        $localKeywordMatchCount = 0;

        foreach ($localKeywords as $keyword) {
            $count = substr_count(strtolower($cleanContent), strtolower($keyword));
            if ($count > 0) {
                $localKeywordMatches[$keyword] = $count;
                $localKeywordMatchCount += $count;
            }
        }

        // Check for maps or location references
        $hasMap = str_contains($content, 'map') ||
            str_contains($content, 'google.com/maps') ||
            str_contains($content, 'maps.google.com');

        // Check for reviews or ratings
        $hasReviews = preg_match('/(?:review|rating|star|testimonial)/i', $cleanContent);

        // Calculate local signal strength (0-1)
        $signalCount = ($hasAddress ? 1 : 0) +
            ($hasPhone ? 1 : 0) +
            ($hasBusinessHours ? 1 : 0) +
            ($hasMap ? 1 : 0) +
            ($hasReviews ? 1 : 0) +
            min(1, $localKeywordMatchCount / 3); // Cap at 1 for keyword matches

        $signalStrength = min(1, $signalCount / 5); // Normalize to 0-1

        return [
            'has_address' => $hasAddress,
            'has_phone' => $hasPhone,
            'has_business_hours' => $hasBusinessHours,
            'has_map' => $hasMap,
            'has_reviews' => $hasReviews,
            'local_keyword_matches' => $localKeywordMatches,
            'local_keyword_match_count' => $localKeywordMatchCount,
            'signal_strength' => $signalStrength
        ];
    }

    /**
     * Analyze the presence of local keywords in meta-tags and alt attributes.
     *
     * @param string $htmlContent The HTML content to analyze
     * @param array $localKeywords Array of local keywords to check
     * @return array Analysis results
     */
    public function analyzeLocalKeywordsInMetaTags(string $htmlContent, array $localKeywords): array
    {
        // Create a DOMXPath object for querying the HTML
        $xpath = $this->loadHTMLInDomXPath($htmlContent);

        // Extract meta elements
        $metaTitle = $this->extractMetaTitle($xpath);
        $metaDescription = $this->extractMetaDescription($xpath);

        // Extract images with alt text
        $images = $this->extractImagesWithAlt($xpath);

        // Check local keyword presence in each element
        $titleResults = $this->checkKeywordPresence($metaTitle, $localKeywords);
        $descriptionResults = $this->checkKeywordPresence($metaDescription, $localKeywords);
        $imageAltResults = $this->checkKeywordsInImageAlt($images, $localKeywords);

        // Check for schema.org LocalBusiness data
        $schemaResults = $this->analyzeLocalBusinessSchema($htmlContent, $localKeywords);

        // Calculate overall coverage
        $elements = ['title', 'description', 'image_alt', 'schema'];
        $elementResults = [$titleResults, $descriptionResults, $imageAltResults, $schemaResults];

        $coverage = [
            'elements_analyzed' => count($elements),
            'elements_with_keywords' => 0,
            'keywords_analyzed' => count($localKeywords),
            'keywords_found' => 0,
            'coverage_percentage' => 0
        ];

        foreach ($elementResults as $result) {
            if ($result['has_any_keyword']) {
                $coverage['elements_with_keywords']++;
            }
            $coverage['keywords_found'] += $result['keywords_found'];
        }

        if ($coverage['elements_analyzed'] > 0) {
            $coverage['element_coverage_percentage'] = round(
                ($coverage['elements_with_keywords'] / $coverage['elements_analyzed']) * 100,
                2
            );
        }

        if ($coverage['keywords_analyzed'] > 0 && count($elements) > 0) {
            $maxPossibleInstances = $coverage['keywords_analyzed'] * count($elements);
            $coverage['keyword_coverage_percentage'] = round(
                ($coverage['keywords_found'] / $maxPossibleInstances) * 100,
                2
            );
        }

        return [
            'meta_title' => [
                'content' => $metaTitle,
                'analysis' => $titleResults
            ],
            'meta_description' => [
                'content' => $metaDescription,
                'analysis' => $descriptionResults
            ],
            'image_alt' => [
                'count' => count($images),
                'analysis' => $imageAltResults
            ],
            'schema_markup' => [
                'found' => $schemaResults['schema_found'],
                'analysis' => $schemaResults
            ],
            'coverage' => $coverage
        ];
    }

    /**
     * Analyze schema.org LocalBusiness markup for local keywords.
     *
     * This method finds relevant Local Business schema entities within the content,
     * extracts text from them, and checks for the presence of specified local keywords.
     * It relies on extractSchemaData and isLocalBusinessSchema helpers.
     *
     * @param string $content The full HTML content.
     * @param array $localKeywords Array of local keywords to check.
     * @return array Analysis results.
     */
    public function analyzeLocalBusinessSchema(string $content, array $localKeywords): array
    {
        // 1. Extract all schema data from the content using the dedicated method.
        // This handles parsing different formats (JSON-LD, Microdata, RDFa).
        $allSchemas = $this->extractSchemaData($content);

        $localBusinessSchemas = [];
        $schemaFound = false; // Flag to indicate if *any* Local Business schema was found

        // 2. Filter for schema objects that represent a Local Business or a local entity.
        foreach ($allSchemas as $schema) {
            // Use the helper method to check if the schema is relevant for local business analysis.
            if ($this->isLocalBusinessSchema($schema)) {
                $localBusinessSchemas[] = $schema;
                $schemaFound = true; // At least one relevant schema was found
            }
        }

        // 3. Combine text from all identified Local Business schemas.
        // Use the dedicated text extraction helper to ensure consistency across formats.
        $schemaText = '';
        foreach ($localBusinessSchemas as $lbSchema) {
            // extractTextFromSchema works correctly regardless of the original schema format
            $schemaText .= $this->extractTextFromSchema($lbSchema) . ' ';
        }
        $schemaText = trim($schemaText); // Clean up potential leading/trailing space

        // 4. Check for local keywords within the extracted schema text.
        // Use the checkKeywordPresence helper method.
        $keywordResults = $this->checkKeywordPresence($schemaText, $localKeywords);

        // 5. Return the analysis results.
        return [
            'schema_found' => $schemaFound,
            'has_any_keyword' => $keywordResults['has_any_keyword'],
            'keywords_found' => $keywordResults['keywords_found'],
            'keywords_missing' => $keywordResults['keywords_missing'],
            'details' => $keywordResults['details']
        ];
    }

    /**
     * Analyze the title for local keyword presence
     *
     * @param DOMXPath $xpath XPath object for HTML parsing
     * @param array $localKeywords List of local keywords to check
     * @param int $postId The ID of the post
     * @return array Analysis results
     */
    public function analyzeTitle(DOMXPath $xpath, array $localKeywords, int $postId): array
    {
        // Get the title from the DOM
        $titleNodes = $xpath->query('//title');

        if ($titleNodes->length > 0) {
            $title = $titleNodes->item(0)->textContent;
        } else {
            // Fallback to WordPress title
            $title = get_the_title($postId);
        }

        $normalizedTitle = strtolower($title);
        $keywordsFound = [];

        foreach ($localKeywords as $keyword) {
            $normalizedKeyword = strtolower(trim($keyword));
            if (str_contains($normalizedTitle, $normalizedKeyword)) {
                $keywordsFound[] = $keyword;
            }
        }

        // Calculate the score
        $score = count($keywordsFound) > 0 ? 1.0 : 0;

        // For titles, having the keyword at the beginning is better
        if (count($keywordsFound) > 0) {
            $firstKeywordPos = stripos($normalizedTitle, strtolower(trim($keywordsFound[0])));
            $titleLength = strlen($normalizedTitle);

            // If the keyword is in the first half of the title, give the full score
            // Otherwise, reduce score based on position
            if ($firstKeywordPos > ($titleLength / 2)) {
                $score *= 0.8;
            }
        }

        $hasKeyword = count($keywordsFound) > 0;
        $meetsThreshold = $score >= SeoOptimiserConfig::KEYWORD_CONTENT_AREAS['title']['threshold'];

        return [
            'text' => $title,
            'keywords_found' => $keywordsFound,
            'has_keyword' => $hasKeyword,
            'score' => $score,
            'meets_threshold' => $meetsThreshold
        ];
    }

    /**
     * Analyze the meta-description for local keyword presence
     *
     * @param DOMXPath $xpath XPath object for HTML parsing
     * @param array $localKeywords List of local keywords to check
     * @param int $postId The ID of the post
     * @return array Analysis results
     */
    public function analyzeMetaDescription(DOMXPath $xpath, array $localKeywords, int $postId): array
    {
        // Try to get meta-description from DOM
        $metaNodes = $xpath->query('//meta[@name="description"]');
        $metaDescription = '';

        if ($metaNodes->length > 0) {
            /** @var mixed $metaNodesItem */
            $metaNodesItem = $metaNodes->item(0);
            $metaDescription = $metaNodesItem->getAttribute('content');
        }

        // If not found in DOM, try to get from WordPress
        if (empty($metaDescription)) {
            $metaDescription = $this->getMetaDescription($postId);
        }

        if (empty($metaDescription)) {
            return [
                'text' => '',
                'keywords_found' => [],
                'has_keyword' => false,
                'score' => 0,
                'meets_threshold' => false
            ];
        }

        $normalizedDescription = strtolower($metaDescription);
        $keywordsFound = [];

        foreach ($localKeywords as $keyword) {
            $normalizedKeyword = strtolower(trim($keyword));
            if (str_contains($normalizedDescription, $normalizedKeyword)) {
                $keywordsFound[] = $keyword;
            }
        }

        $hasKeyword = count($keywordsFound) > 0;
        $score = $hasKeyword ? 1.0 : 0;
        $meetsThreshold = $score >= SeoOptimiserConfig::KEYWORD_CONTENT_AREAS['meta_description']['threshold'];

        return [
            'text' => $metaDescription,
            'keywords_found' => $keywordsFound,
            'has_keyword' => $hasKeyword,
            'score' => $score,
            'meets_threshold' => $meetsThreshold
        ];
    }
}
