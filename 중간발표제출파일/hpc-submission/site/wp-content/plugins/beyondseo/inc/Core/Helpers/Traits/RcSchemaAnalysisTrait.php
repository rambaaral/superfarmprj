<?php
/** @noinspection PhpConditionAlreadyCheckedInspection */
/** @noinspection PhpUnusedParameterInspection */
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection RegExpUnnecessaryNonCapturingGroup */
/** @noinspection RegExpRedundantEscape */
/** @noinspection PhpUsageOfSilenceOperatorInspection */
/** @noinspection PhpStatementHasEmptyBodyInspection */
/** @noinspection PhpInappropriateInheritDocUsageInspection */
/** @noinspection SqlNoDataSourceInspection */
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Helpers\Traits;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Seo\Optimiser\Base\Models\Configs\SeoOptimiserConfig;
use DOMNode;
use DOMXPath;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;

/**
 * Trait RcSchemaAnalysisTrait
 *
 * Provides utility methods for handling schema.org structured data analysis.
 */
trait RcSchemaAnalysisTrait
{
    use RcLoggerTrait;

    /**
     * Extracts schema.org data from HTML content
     *
     * @param string $content The HTML content
     * @return array Array of schema objects
     */
    public function extractSchemaData(string $content): array
    {
        $schemas = [];

        // Create a new XPath
        $xpath = $this->loadHTMLInDomXPath($content);

        // Find JSON-LD scripts - the most common format for schema
        $jsonLdNodes = $xpath->query('//script[@type="application/ld+json"]');

        foreach ($jsonLdNodes as $node) {
            $json = trim($node->textContent);
            $data = json_decode($json, true);

            if ($data) {
                // Handle both single schema and arrays/graphs of schemas
                if (isset($data['@graph']) && is_array($data['@graph'])) {
                    foreach ($data['@graph'] as $graphItem) {
                        $schemas[] = $graphItem;
                    }
                } else {
                    $schemas[] = $data;
                }
            }
        }

        // Find Microdata - an alternative format for schema
        $itemscopes = $xpath->query('//*[@itemscope]');

        foreach ($itemscopes as $node) {
            $type = $node->getAttribute('itemtype');

            if (str_contains($type, 'schema.org')) {
                $schemaType = str_replace('http://schema.org/', '', $type);
                $schemaType = str_replace('https://schema.org/', '', $schemaType);

                $properties = $this->extractMicrodataProperties($node, $xpath);
                $properties['@type'] = $schemaType;

                $schemas[] = $properties;
            }
        }

        // Also check for RDFa format
        $rdfaNodes = $xpath->query('//*[@typeof]');

        foreach ($rdfaNodes as $node) {
            $type = $node->getAttribute('typeof');

            if (str_contains($type, 'schema:') || str_contains($type, 'http://schema.org')) {
                $schemaType = str_replace('schema:', '', $type);
                $schemaType = str_replace('http://schema.org/', '', $schemaType);
                $schemaType = str_replace('https://schema.org/', '', $schemaType);

                $properties = $this->extractRdfaProperties($node, $xpath);
                $properties['@type'] = $schemaType;

                $schemas[] = $properties;
            }
        }

        return $schemas;
    }

    /**
     * Helper method to extract properties from a DOM node based on a given property attribute.
     * Handles recursion for nested items.
     *
     * @param DOMNode $node The current node to extract properties from.
     * @param DOMXPath $xpath The XPath object for the document.
     * @param string $propAttributeName The name of the attribute holding the property name (e.g., 'itemprop' or 'property').
     * @param string $nestedAttributeName The name of the attribute indicating a nested item (e.g., 'itemscope' or 'typeof').
     * @param bool $isRdfa Flag to indicate if processing RDFa (for name cleaning).
     * @return array Extracted properties.
     */
    protected function _extractPropertiesRecursive(DOMNode $node, DOMXPath $xpath, string $propAttributeName, string $nestedAttributeName, bool $isRdfa = false): array
    {
        $properties = [];

        // Query for descendant nodes with the specified property attribute
        $props = $xpath->query('.//*[@' . $propAttributeName . ']', $node);

        foreach ($props as $prop) {
            $name = $prop->getAttribute($propAttributeName);

            // Apply RDFa specific name cleaning if a flag is set
            if ($isRdfa) {
                $name = str_replace('schema:', '', $name);
            }

            $value = null; // Initialize value

            // Handle nested items based on the nested attribute name
            if ($prop->hasAttribute($nestedAttributeName)) {
                // Recursive call to extract properties from the nested item
                $value = $this->_extractPropertiesRecursive($prop, $xpath, $propAttributeName, $nestedAttributeName, $isRdfa);
            }
            // Extract value based on tag name or other attributes
            elseif ($prop->tagName === 'meta') {
                $value = $prop->getAttribute('content');
            } elseif ($prop->tagName === 'img' || $prop->tagName === 'link') {
                $value = $prop->getAttribute('href') ?: $prop->getAttribute('src');
            } elseif ($prop->tagName === 'time') {
                $value = $prop->getAttribute('datetime') ?: $prop->textContent;
            } elseif ($prop->tagName === 'a') {
                $value = $prop->getAttribute('href');
            }
            // Specific check for 'content' attribute used in RDFa or sometimes Microdata
            elseif ($prop->hasAttribute('content')) {
                $value = $prop->getAttribute('content');
            }
            else {
                $value = trim($prop->textContent);
            }

            // Add property if the name is not empty and the value is not null/empty string
            // Be cautious with empty() check if 0, false, etc. are valid values.
            // Using !== null and !== '' is safer for string/numeric values.
            // For booleans/zero, a more complex check might be needed depending on requirements.
            // Let's stick close to the original logic using !empty() for now, as it's simpler.
            if (!empty($name) && !empty($value)) {
                // Handle potential multiple values for the same property name
                if (isset($properties[$name])) {
                    if (!is_array($properties[$name])) {
                        $properties[$name] = [$properties[$name]];
                    }
                    $properties[$name][] = $value;
                } else {
                    $properties[$name] = $value;
                }
            }
        }

        return $properties;
    }

    /**
     * Extract Microdata properties from DOM nodes
     *
     * @param DOMNode $node The node with the itemscope
     * @param DOMXPath $xpath The XPath object
     * @return array Extracted properties
     */
    public function extractMicrodataProperties(DOMNode $node, DOMXPath $xpath): array
    {
        return $this->_extractPropertiesRecursive($node, $xpath, 'itemprop', 'itemscope');
    }

    /**
     * Extract RDFa properties from DOM nodes
     *
     * @param DOMNode $node The node with the typeof
     * @param DOMXPath $xpath The XPath object
     * @return array Extracted properties
     */
    public function extractRdfaProperties(DOMNode $node, DOMXPath $xpath): array
    {
        return $this->_extractPropertiesRecursive($node, $xpath, 'property', 'typeof', true);
    }

    /**
     * Find LocalBusiness schema in the list of schemas
     *
     * @param array $schemas List of schema objects
     * @return array|null LocalBusiness schema or null if not found
     */
    public function findLocalBusinessSchema(array $schemas): ?array
    {
        foreach ($schemas as $schema) {
            if(isset($schema['@type']) && is_array($schema['@type'])) {
                $schema['@type'] = $schema['@type'][0];
            }
            // Direct LocalBusiness type
            if (isset($schema['@type']) && ($schema['@type'] === 'LocalBusiness' || $this->isLocalBusinessSubtype($schema['@type']))) {
                return $schema;
            }

            // Organization with location or address
            if (isset($schema['@type']) && $schema['@type'] === 'Organization' &&
                (isset($schema['location']) || isset($schema['address']))) {
                return $schema;
            }

            // Place type
            if (isset($schema['@type']) && ($schema['@type'] === 'Place' && isset($schema['address']))) {
                return $schema;
            }
        }

        return null;
    }

    /**
     * Check if the type is a LocalBusiness subtype
     *
     * @param string $type Schema type to check
     * @return bool Whether it's a LocalBusiness subtype
     */
    public function isLocalBusinessSubtype(string $type): bool
    {
        return $this->_isTypeInAllowedList($type, SeoOptimiserConfig::LOCAL_BUSINESS_SUBTYPES);
    }

    /**
     * Helper method to check for missing properties in a schema object.
     *
     * @param array $schema The schema object to check.
     * @param array $propertiesToCheck List of property names to check for presence.
     * @param bool $strictEmptyCheck If true, also checks if non-empty values are considered 'empty' in a specific context (e.g., empty arrays).
     * @return array List of property names that are missing or considered empty.
     */
    protected function _findMissingProperties(array $schema, array $propertiesToCheck, bool $strictEmptyCheck = false): array
    {
        $missing = [];
        foreach ($propertiesToCheck as $property) {
            $value = $schema[$property] ?? null; // Use null coalescing to avoid undefined index warnings

            $isEmpty = false;
            if ($strictEmptyCheck) {
                // Add more complex empty value validation rules
                // Check for empty arrays, whitespace strings, and special values
                if (is_array($value)) {
                    $isEmpty = empty(array_filter($value)); // Check if an array contains only empty values
                } elseif (is_string($value)) {
                    $isEmpty = trim($value) === ''; // Check if a string contains only whitespace
                } elseif (is_object($value)) {
                    $isEmpty = empty(get_object_vars($value)); // Check if an object has no properties
                } elseif ($value === null) {
                    $isEmpty = true;
                } else {
                    $isEmpty = empty($value); // Fallback to standard empty() check
                }
            } elseif (empty($value)) {
                $isEmpty = true;
            }

            if ($isEmpty) {
                $missing[] = $property;
            }
        }
        return $missing;
    }

    /**
     * Helper method to validate Address structure within a schema.
     * Could be used to validateLocalBusinessSchema and potentially validateSchema if it checks Organization address.
     *
     * @param array|null $addressData The address array from the schema.
     * @return array List of issues found (strings). Empty array if valid.
     */
    protected function _validateAddressStructure(?array $addressData): array
    {
        $issues = [];
        if (empty($addressData)) {
            $issues[] = 'Address data is empty or missing.';
            return $issues;
        }
        if (!isset($addressData['@type']) || $addressData['@type'] !== 'PostalAddress') {
            $issues[] = 'Address is missing @type: PostalAddress or type is incorrect.';
        }
        $requiredAddressFields = ['streetAddress', 'addressLocality', 'addressRegion', 'postalCode'];
        foreach ($requiredAddressFields as $field) {
            if (empty($addressData[$field])) {
                $issues[] = "Address is missing required field: $field";
            }
        }
        return $issues;
    }

    /**
     * Helper method to validate GeoCoordinates structure within a schema.
     * Could be used to validateLocalBusinessSchema.
     *
     * @param array|null $geoData The geo array from the schema.
     * @return array List of issues found (strings). Empty array if valid.
     */
    protected function _validateGeoStructure(?array $geoData): array
    {
        $issues = [];
        if (empty($geoData)) {
            $issues[] = 'Geo data is empty or missing.';
            return $issues;
        }
        if (!isset($geoData['@type']) || $geoData['@type'] !== 'GeoCoordinates') {
            $issues[] = 'Geo is missing @type: GeoCoordinates or type is incorrect.';
        }
        $requiredGeoFields = ['latitude', 'longitude'];
        foreach ($requiredGeoFields as $field) {
            if (empty($geoData[$field]) && !is_numeric($geoData[$field] ?? null)) { // Check for empty AND non-numeric
                $issues[] = "Geo is missing required field or value is not numeric: $field";
            }
        }
        // Add latitude/longitude format validation (-90 to 90 for lat, -180 to 180 for lon)
        if (!empty($geoData['latitude'])) {
            $lat = $geoData['latitude'];

            // Check numeric and range (-90 to 90)
            if (!is_numeric($lat)) {
                $issues[] = 'Latitude must be a numeric value.';
            } else {
                $latFloat = (float)$lat;
                if ($latFloat < -90 || $latFloat > 90) {
                    $issues[] = 'Invalid latitude value: must be between -90 and 90.';
                }

                // Optional: Check for decimal format (no degrees/minutes/seconds)
                if (!preg_match('/^-?\d+(\.\d+)?$/', $lat)) {
                    $issues[] = 'Latitude must be in decimal format (e.g., 40.7128).';
                }
            }
        }
        if (!empty($geoData['longitude'])) {
            $lon = $geoData['longitude'];

            // Check numeric and range (-180 to 180)
            if (!is_numeric($lon)) {
                $issues[] = 'Longitude must be a numeric value.';
            } else {
                $lonFloat = (float)$lon;
                if ($lonFloat < -180 || $lonFloat > 180) {
                    $issues[] = 'Invalid longitude value: must be between -180 and 180.';
                }

                // Optional: Check for decimal format
                if (!preg_match('/^-?\d+(\.\d+)?$/', $lon)) {
                    $issues[] = 'Longitude must be in decimal format (e.g., -74.0060).';
                }
            }
        }
        return $issues;
    }
    
    /**
     * Validate LocalBusiness schema
     *
     * @param array $schema LocalBusiness schema
     * @return array Validation results
     */
    public function validateLocalBusinessSchema(array $schema): array
    {
        $incompleteProperties = []; // This will now include structure problems as well

        // Use helper to find missing/empty required properties
        $missingRequired = $this->_findMissingProperties($schema, SeoOptimiserConfig::SCHEMA_REQUIRED_PROPERTIES);
        
        // Validate address structure
        $addressIssues = $this->_validateAddressStructure($schema['address'] ?? null);
        if (!empty($addressIssues)) {
            $incompleteProperties = array_merge($incompleteProperties, $addressIssues);
        }

        // Validate geo structure
        $geoIssues = $this->_validateGeoStructure($schema['geo'] ?? null);
        if (!empty($geoIssues)) {
            $incompleteProperties = array_merge($incompleteProperties, $geoIssues);
        }

        // Use helper to find missing/empty recommended properties
        $missingRecommended = $this->_findMissingProperties($schema, SeoOptimiserConfig::SCHEMA_RECOMMENDED_PROPERTIES);

        $totalProperties = count(SeoOptimiserConfig::SCHEMA_REQUIRED_PROPERTIES) + count(SeoOptimiserConfig::SCHEMA_RECOMMENDED_PROPERTIES);
        $effectiveMissingCount = count($missingRequired) + count($missingRecommended) + count($incompleteProperties);

        // Prevents division by zero
        $completeness = $totalProperties > 0 ? round((($totalProperties - $effectiveMissingCount) / $totalProperties) * 100, 2) : 0;

        // Determine validity (all required properties must be present and correctly formatted)
        $isValid = empty($missingRequired) && empty($incompleteProperties);

        return [
            'valid' => $isValid,
            'completeness' => $completeness,
            'missing_required' => $missingRequired,
            'missing_recommended' => $missingRecommended,
            'incomplete_properties' => $incompleteProperties,
            'schema_type' => $schema['@type'] ?? 'Unknown'
        ];
    }

    /**
     * Detects if the content is syndicated based on various signals.
     *
     * @param int $postId The post-ID
     * @param string $content The HTML content
     * @return bool Whether syndication is detected
     */
    public function detectSyndication(int $postId, string $content): bool
    {
        // Check for WordPress syndication plugin settings or meta-fields
        $syndicationPlugins = [
            'syndication' => 'is_syndicated',
            'content_syndication' => 'syndicated_content',
            'wp_syndicate' => 'wp_syndicate_status',
        ];

        foreach ($syndicationPlugins as $metaKey) {
            $value = get_post_meta($postId, $metaKey, true);
            if (!empty($value) && $value !== 'no' && $value !== '0' && $value !== 'false') {
                return true;
            }
        }

        // Look for RankingCoach specific syndication meta
        $rcSyndicated = get_post_meta($postId, BaseConstants::META_KEY_IS_SYNDICATED, true);
        if (!empty($rcSyndicated) && $rcSyndicated !== 'no' && $rcSyndicated !== '0' && $rcSyndicated !== 'false') {
            return true;
        }

        // Check for common syndication indicators in the content
        $syndicationIndicators = [
            'Originally published at',
            'First appeared on',
            'Originally appeared on',
            'republished from',
            'republished with permission',
            'cross-posted from',
            'cross-posted with permission',
        ];

        foreach ($syndicationIndicators as $indicator) {
            if (stripos($content, $indicator) !== false) {
                return true;
            }
        }

        $dom = $this->loadHTMLInDomXPath($content, true);

        $metas = $dom->getElementsByTagName('meta');
        foreach ($metas as $meta) {
            $name = $meta->getAttribute('name');
            if (in_array($name, ['syndication-source', 'original-source'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the schema has required properties based on its type
     *
     * @param array $schema The schema object
     * @param string $type The schema type
     * @return bool Whether the schema has required properties
     */
    public function hasRequiredSchemaProperties(array $schema, string $type): bool
    {
        // Define required properties for common schema types
        $requiredProperties = SeoOptimiserConfig::SCHEMA_REQUIRED_PROPERTIES_BY_TYPE[$type] ?? SeoOptimiserConfig::SCHEMA_REQUIRED_PROPERTIES;

        // If we don't have specific requirements for this type, return true
        if (!isset(SeoOptimiserConfig::SCHEMA_REQUIRED_PROPERTIES_BY_TYPE[$type]) && $type !== 'LocalBusiness') {
            return true;
        }

        // Check if all required properties are present
        $missing = $this->_findMissingProperties($schema, $requiredProperties);

        return empty($missing);
    }

    /**
     * Helper method to check if a given schema type is present in a list of allowed types.
     *
     * @param string $type The schema type to check.
     * @param array $allowedTypesList The list of types to check against.
     * @return bool True if the type is in the list, false otherwise.
     */
    protected function _isTypeInAllowedList(string $type, array $allowedTypesList): bool
    {
        return in_array($type, $allowedTypesList, true);
    }

    /**
     * Check if a schema type is a subtype of another
     *
     * @param string $type The schema type to check
     * @param string $parentType The potential parent type
     * @return bool Whether it's a subtype
     */
    public function belongsToSchemaType(string $type, string $parentType): bool
    {
        // Define known schema.org type hierarchies
        $typeHierarchy = [
            'LocalBusiness' => SeoOptimiserConfig::LOCAL_BUSINESS_SUBTYPES,
            'CreativeWork' => [
                'Article', 'BlogPosting', 'NewsArticle', 'WebPage', 'Book', 'Recipe', 'Movie',
                'TVSeries', 'SoftwareApplication', 'FAQPage', 'HowTo', 'Course', 'Review'
            ],
            'Organization' => [
                'Corporation', 'EducationalOrganization', 'GovernmentOrganization', 'MedicalOrganization',
                'NGO', 'School', 'SportsOrganization', 'LocalBusiness'
            ],
            'WebPage' => [
                'AboutPage', 'CheckoutPage', 'ContactPage', 'CollectionPage', 'FAQPage',
                'ItemPage', 'ProfilePage', 'SearchResultsPage'
            ],
            'Product' => [
                'IndividualProduct', 'ProductGroup', 'Vehicle', 'Offer'
            ]
        ];

        // Check if the parent type exists in the hierarchy and get the list of subtypes
        if (isset($typeHierarchy[$parentType])) {
            $allowedSubtypes = $typeHierarchy[$parentType];
            // Utilize the helper method to check if the type is in the list of subtypes
            return $this->_isTypeInAllowedList($type, $allowedSubtypes);
        }

        // The parent type does not exist in the defined hierarchy
        return false;
    }

    /**
     * Identify content-specific schemas based on page content and type
     *
     * @param string $htmlContent The HTML content
     * @param string $postType The post-type
     * @return array Additional suggested schema types
     */
    public function identifyContentSpecificSchemas(string $htmlContent, string $postType): array
    {
        $suggestedTypes = [];

        $xpath = $this->loadHTMLInDomXPath($htmlContent);

        // Check for article/blog content
        if (in_array($postType, ['post', 'article', 'blog']) || $this->hasArticleSchemaStructure($xpath)) {
            $suggestedTypes[] = 'Article';

            // Check if it's a news article
            if ($this->hasNewsArticleSchemaCharacteristics($xpath)) {
                $suggestedTypes[] = 'NewsArticle';
            }

            // Check if it's a blog post
            if ($postType === 'post' || $this->hasBlogPostSchemaCharacteristics($xpath)) {
                $suggestedTypes[] = 'BlogPosting';
            }
        }

        // Check for FAQ content
        if ($this->hasFAQSchemaStructure($xpath)) {
            $suggestedTypes[] = 'FAQPage';
        }

        // Check for breadcrumb navigation
        if ($this->hasBreadcrumbSchemaNavigation($xpath)) {
            $suggestedTypes[] = 'BreadcrumbList';
        }

        // Check for product content
        if ($postType === 'product' || $this->hasProductSchemaStructure($xpath)) {
            $suggestedTypes[] = 'Product';
        }

        // Check for recipe content
        if ($postType === 'recipe' || $this->hasRecipeSchemaStructure($xpath)) {
            $suggestedTypes[] = 'Recipe';
        }

        // Check for event content
        if ($postType === 'event' || $this->hasEventSchemaStructure($xpath)) {
            $suggestedTypes[] = 'Event';
        }

        // Check for how-to content
        if ($this->hasHowToSchemaStructure($xpath)) {
            $suggestedTypes[] = 'HowTo';
        }

        // Check for video content
        if ($this->hasVideoSchemaContent($xpath)) {
            $suggestedTypes[] = 'VideoObject';
        }

        // Check for review content
        if ($postType === 'review' || $this->hasReviewSchemaStructure($xpath)) {
            $suggestedTypes[] = 'Review';
        }

        return $suggestedTypes;
    }

    /**
     * Helper method to check if an XPath query returns any nodes.
     *
     * @param DOMXPath $xpath The XPath object.
     * @param string $query The XPath query string.
     * @param DOMNode|null $contextNode Optional. The context node for the query.
     * @return bool True if the query returns at least one node, false otherwise.
     */
    protected function _hasXPathNodes(DOMXPath $xpath, string $query, ?DOMNode $contextNode = null): bool
    {
        // Perform the query
        $nodes = $xpath->query($query, $contextNode);

        // Check if the query was successful and returned nodes
        return $nodes !== false && $nodes->length > 0;
    }

    /**
     * Check if the page has an article structure
     *
     * @param DOMXPath $xpath XPath object for the document
     * @return bool Whether the page has an article structure
     */
    public function hasArticleSchemaStructure(DOMXPath $xpath): bool
    {
        // Check if the content is wrapped in article tags using the helper
        if ($this->_hasXPathNodes($xpath, '//article')) {
            return true;
        }

        // Check for typical article structure (heading followed by paragraphs)
        $mainHeading = $this->_hasXPathNodes($xpath, '//h1');
        $paragraphs = $xpath->query('//p');

        $hasEnoughParagraphs = $paragraphs !== false && $paragraphs->length >= 3;
        return $mainHeading && $hasEnoughParagraphs;
    }

    /**
     * Check if the page has news article characteristics
     *
     * @param DOMXPath $xpath XPath object for the document
     * @return bool Whether the page has news article characteristics
     */
    public function hasNewsArticleSchemaCharacteristics(DOMXPath $xpath): bool
    {
        // Check for publication date in a news-like format
        $datePatterns = [
            '//time',
            '//*[contains(@class, "date")]',
            '//*[contains(@class, "published")]',
            '//*[contains(@class, "time")]',
        ];

        foreach ($datePatterns as $pattern) {
            if ($this->_hasXPathNodes($xpath, $pattern)) {
                return true;
            }
        }

        // Look for news-related keywords in the content
        $newsKeywords = ['news', 'breaking', 'report', 'announced', 'latest'];
        $bodyNode = $xpath->query('//body')->item(0);

        if ($bodyNode) {
            $bodyText = $bodyNode->textContent;
            $keywordResults = $this->checkKeywordPresence($bodyText, $newsKeywords);
            if ($keywordResults['has_any_keyword']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the page has blog post characteristics
     *
     * @param DOMXPath $xpath XPath object for the document
     * @return bool Whether the page has blog post characteristics
     */
    public function hasBlogPostSchemaCharacteristics(DOMXPath $xpath): bool
    {
        // Check for blog-related elements
        $blogPatterns = [
            '//*[contains(@class, "blog")]',
            '//*[contains(@class, "post")]',
            '//*[contains(@class, "author")]',
            '//*[contains(@class, "comments")]',
        ];

        foreach ($blogPatterns as $pattern) {
            if ($xpath->query($pattern)->length > 0) {
                return true;
            }
        }

        // Check for author bio, which is common in blog posts
        $authorPatterns = [
            '//*[contains(@class, "author-bio")]',
            '//*[contains(@class, "about-author")]',
            '//div[contains(@class, "author")]//img',
        ];

        foreach ($authorPatterns as $pattern) {
            if ($xpath->query($pattern)->length > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the page has an FAQ structure
     *
     * @param DOMXPath $xpath XPath object for the document
     * @return bool Whether the page has FAQ structure
     */
    public function hasFAQSchemaStructure(DOMXPath $xpath): bool
    {
        // Look for FAQ-related classes or IDs
        $faqPatterns = [
            '//*[contains(@class, "faq")]',
            '//*[@id="faq"]',
            '//*[contains(@class, "question")]',
            '//*[contains(@class, "answer")]',
            '//dt[..//dd]', // Definition lists are often used for Q&A
        ];

        foreach ($faqPatterns as $pattern) {
            if ($xpath->query($pattern)->length > 0) {
                return true;
            }
        }

        // Look for question-answer patterns
        $questions = $xpath->query('//h3[../p] | //h4[../p] | //strong[../p]');
        $questionsFound = 0;

        if ($questions !== false) {
            foreach ($questions as $question) {
                $text = $question->textContent;
                if (str_contains($text, '?')) {
                    $questionsFound++;
                }
            }
        }

        // If we found multiple questions, it's likely a FAQ
        return $questionsFound >= 3;
    }

    /**
     * Check if the page has breadcrumb navigation
     *
     * @param DOMXPath $xpath XPath object for the document
     * @return bool Whether the page has breadcrumb navigation
     */
    public function hasBreadcrumbSchemaNavigation(DOMXPath $xpath): bool
    {
        // Look for breadcrumb-related classes or structures
        $breadcrumbPatterns = [
            '//*[contains(@class, "breadcrumb")]',
            '//*[contains(@class, "breadcrumbs")]',
            '//*[@id="breadcrumbs"]',
            '//nav//ol/li/a', // Common breadcrumb structure
            '//nav//ul[count(./li/a) > 1]', // Another common breadcrumb structure
        ];

        foreach ($breadcrumbPatterns as $pattern) {
            if ($xpath->query($pattern)->length > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the page has a product structure
     *
     * @param DOMXPath $xpath XPath object for the document
     * @return bool Whether the page has a product structure
     */
    public function hasProductSchemaStructure(DOMXPath $xpath): bool
    {
        // Look for product-related elements
        $productPatterns = [
            '//*[contains(@class, "product")]',
            '//*[contains(@class, "price")]',
            '//*[contains(@class, "add-to-cart")]',
            '//*[contains(@class, "buy-now")]',
            '//*[contains(@class, "shop")]',
            '//*[contains(@class, "item")]//img',
        ];

        foreach ($productPatterns as $pattern) {
            if ($xpath->query($pattern)->length > 0) {
                return true;
            }
        }

        // Look for price patterns ($ or € followed by numbers)
        $pricePatternQuery = '//text()'; // Query for all text nodes
        $priceRegex = '/[\$€£]\s*\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?/u'; // More robust price regex for various formats
        $priceNodes = $xpath->query($pricePatternQuery);

        if ($priceNodes !== false && $priceNodes->length > 0) {
            foreach ($priceNodes as $node) {
                // Only check text not inside script or style tags
                if ($node->parentNode && !in_array($node->parentNode->tagName, ['script', 'style', 'noscript'])) {
                    if (preg_match($priceRegex, $node->textContent)) {
                        return true; // Found a price pattern
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if the page has a recipe structure
     *
     * @param DOMXPath $xpath XPath object for the document
     * @return bool Whether the page has a recipe structure
     */
    public function hasRecipeSchemaStructure(DOMXPath $xpath): bool
    {
        // Look for recipe-related elements using the helper
        $recipePatterns = [
            '//*[contains(@class, "recipe")]',
            '//*[contains(@class, "ingredients")]',
            '//*[contains(@class, "instructions")]',
            '//*[contains(@class, "cooking-time")]',
            '//*[contains(@class, "prep-time")]',
            // Case-insensitive search for ingredient headings using translate() in XPath
            '//h2[contains(translate(., "INGREDIENTS", "ingredients"), "ingredients")]',
            '//h3[contains(translate(., "INGREDIENTS", "ingredients"), "ingredients")]',
        ];

        foreach ($recipePatterns as $pattern) {
            // Use the _hasXPathNodes helper
            if ($this->_hasXPathNodes($xpath, $pattern)) {
                return true; // Return immediately if any general recipe pattern is found
            }
        }

        // Look for ingredient list patterns using the helper
        $listPatterns = [
            // Check for lists containing common ingredient units (case-insensitive text search within list items)
            '//ul[./li[contains(translate(., "CUPTBSPTSPGRAMOZ", "cuptbsptspgramoz"), "cup") or contains(translate(., "CUPTBSPTSPGRAMOZ", "cuptbsptspgramoz"), "tbsp") or contains(translate(., "CUPTBSPTSPGRAMOZ", "cuptbsptspgramoz"), "tsp") or contains(translate(., "CUPTBSPTSPGRAMOZ", "cuptbsptspgramoz"), "gram") or contains(translate(., "CUPTBSPTSPGRAMOZ", "cuptbsptspgramoz"), "oz")]]',
            // Check for ordered lists preceded by an element containing the "ingredients" heading
            '//ol[preceding::*[contains(translate(., "INGREDIENTS", "ingredients"), "ingredients")]]',
        ];

        foreach ($listPatterns as $pattern) {
            // Use the _hasXPathNodes helper
            if ($this->_hasXPathNodes($xpath, $pattern)) {
                return true; // Return immediately if any ingredient list pattern is found
            }
        }

        return false; // No clear recipe structure found
    }

    /**
     * Check if the page has event structure
     *
     * @param DOMXPath $xpath XPath object for the document
     * @return bool Whether the page has event structure
     */
    public function hasEventSchemaStructure(DOMXPath $xpath): bool
    {
        // Look for event-related elements using the helper
        $eventPatterns = [
            '//*[contains(@class, "event")]',
            '//*[contains(@class, "calendar")]',
            '//*[contains(@class, "schedule")]',
            '//*[contains(@class, "venue")]',
            '//*[contains(@class, "location")]',
            '//*[contains(@class, "date")]',
            '//*[contains(@class, "time")]',
        ];

        foreach ($eventPatterns as $pattern) {
            // Use helper _hasXPathNodes
            if ($this->_hasXPathNodes($xpath, $pattern)) {
                return true; // Return immediately if any general event element pattern is found
            }
        }

        // Look for date/time/location patterns in text nodes
        $datePatternQuery = '//text()[contains(., "Date:") or contains(., "Time:") or contains(., "Location:")]';
        // A more robust query might look like: //body//text()[... predicate ...]

        $dateNodes = $xpath->query($datePatternQuery);

        if ($dateNodes !== false && $dateNodes->length > 0) {
            foreach ($dateNodes as $node) {
                // Only check text not inside script or style tags
                if ($node->parentNode && !in_array($node->parentNode->tagName, ['script', 'style', 'noscript'])) {
                    // The XPath query already filters based on text content,
                    // so just checking if we found any relevant nodes within visible parts is enough.
                    return true; // Found a relevant text pattern
                }
            }
        }

        return false; // No event structure or clear text indicators found
    }

    /**
     * Check if the page has a how-to structure
     * (Uses _hasXPathNodes helper)
     *
     * @param DOMXPath $xpath XPath object for the document
     * @return bool Whether the page has a how-to structure
     */
    public function hasHowToSchemaStructure(DOMXPath $xpath): bool
    {
        // Check for "how to" in the title or headings using the helper
        $titlePattern = '//title[contains(translate(., "HOW TO", "how to"), "how to")]';
        $headingPattern = '//h1[contains(translate(., "HOW TO", "how to"), "how to")] | //h2[contains(translate(., "HOW TO", "how to"), "how to")]';

        // Use _hasXPathNodes for both title and heading checks
        if ($this->_hasXPathNodes($xpath, $titlePattern) || $this->_hasXPathNodes($xpath, $headingPattern)) {
            return true; // Found "how to" in title or main headings
        }

        // Look for step-by-step instructions by checking for specific classes or ordered lists using the helper
        $stepPatterns = [
            '//*[contains(@class, "step")]',
            '//*[contains(@class, "steps")]',
            '//*[contains(@class, "how-to")]',
            '//*[contains(@class, "instructions")]',
            '//ol[count(./li) >= 3]', // Ordered a list with at least 3 items - checked for presence
        ];

        foreach ($stepPatterns as $pattern) {
            // Use the _hasXPathNodes helper
            if ($this->_hasXPathNodes($xpath, $pattern)) {
                return true; // Return immediately if any step pattern is found
            }
        }

        // Look for numbered steps in headings using the helper
        $numberedStepPattern = '//h3[contains(., "Step 1") or contains(., "Step 2")] | //h4[contains(., "Step 1") or contains(., "Step 2")]';

        // Use the _hasXPathNodes helper
        if ($this->_hasXPathNodes($xpath, $numberedStepPattern)) {
            return true; // Found headings indicating numbered steps
        }

        return false; // No how-to structure found
    }

    /**
     * Check if the page has a review structure
     *
     * @param DOMXPath $xpath XPath object for the document
     * @return bool Whether the page has a review structure
     */
    public function hasReviewSchemaStructure(DOMXPath $xpath): bool
    {
        // Look for review-related elements
        $reviewPatterns = [
            '//*[contains(@class, "review")]',
            '//*[contains(@class, "rating")]',
            '//*[contains(@class, "stars")]',
            '//*[contains(@class, "testimonial")]',
            '//span[contains(@class, "star") or contains(@class, "rating")]',
        ];

        foreach ($reviewPatterns as $pattern) {
            if ($xpath->query($pattern)->length > 0) {
                return true;
            }
        }

        // Look for rating patterns (e.g., 4.5/5)
        $ratingPattern = '//text()[contains(., "/5") or contains(., "/10")]';
        if ($xpath->query($ratingPattern)->length > 0) {
            return true;
        }

        return false;
    }

    /**
     * Check if the page has video content
     *
     * @param DOMXPath $xpath XPath object for the document
     * @return bool Whether the page has video content
     */
    public function hasVideoSchemaContent(DOMXPath $xpath): bool
    {
        // Look for video elements
        $videoPatterns = [
            '//video',
            '//iframe[contains(@src, "youtube.com")]',
            '//iframe[contains(@src, "vimeo.com")]',
            '//iframe[contains(@src, "wistia.com")]',
            '//object[contains(@data, ".mp4") or contains(@data, ".webm")]',
            '//*[contains(@class, "video")]',
            '//*[contains(@class, "player")]',
        ];

        foreach ($videoPatterns as $pattern) {
            if ($xpath->query($pattern)->length > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if schema data represents a local business.
     *
     * @param array $schemaData Schema data to check
     * @return bool Whether it's a local business schema
     */
    public function isLocalBusinessSchema(array $schemaData): bool
    {
        // Local business types to check
        $localBusinessTypes = array_merge(['LocalBusiness'], SeoOptimiserConfig::LOCAL_BUSINESS_SUBTYPES);

        // Check for local business type
        if (isset($schemaData['@type'])) {
            $type = $schemaData['@type'];
            if (is_string($type) && in_array($type, $localBusinessTypes)) {
                return true;
            } elseif (is_array($type)) {
                foreach ($type as $t) {
                    if (in_array($t, $localBusinessTypes)) {
                        return true;
                    }
                }
            }
        }

        // Check for @graph with local business entities
        if (isset($schemaData['@graph']) && is_array($schemaData['@graph'])) {
            foreach ($schemaData['@graph'] as $entity) {
                if (isset($entity['@type'])) {
                    $type = $entity['@type'];
                    if (is_string($type) && in_array($type, $localBusinessTypes)) {
                        return true;
                    } elseif (is_array($type)) {
                        foreach ($type as $t) {
                            if (in_array($t, $localBusinessTypes)) {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        // Also check for address property which indicates a local entity
        if (isset($schemaData['address']) || isset($schemaData['location'])) {
            return true;
        }

        return false;
    }

    /**
     * Extract text content from schema data for keyword analysis.
     *
     * @param array $schemaData Schema data
     * @return string Extracted text
     */
    public function extractTextFromSchema(array $schemaData): string
    {
        $textParts = [];

        // Extract key fields that might contain location information
        $extractableFields = [
            'name', 'description', 'alternateName', 'areaServed',
            'addressLocality', 'addressRegion', 'postalCode', 'streetAddress'
        ];

        // Process schema fields
        foreach ($extractableFields as $field) {
            if (isset($schemaData[$field])) {
                if (is_string($schemaData[$field])) {
                    $textParts[] = $schemaData[$field];
                } elseif (is_array($schemaData[$field])) {
                    if (isset($schemaData[$field]['name'])) {
                        $textParts[] = $schemaData[$field]['name'];
                    } elseif (!empty($schemaData[$field])) {
                        $textParts[] = implode(' ', array_filter($schemaData[$field], 'is_string'));
                    }
                }
            }
        }

        // Also check an address object
        if (isset($schemaData['address']) && is_array($schemaData['address'])) {
            foreach (['addressLocality', 'addressRegion', 'postalCode', 'streetAddress'] as $field) {
                if (isset($schemaData['address'][$field])) {
                    $textParts[] = $schemaData['address'][$field];
                }
            }
        }

        return implode(' ', $textParts);
    }

    /**
     * Suggest the most appropriate schema type based on content analysis
     * (Uses centralized BUSINESS_TYPE_KEYWORDS and consistent keyword search)
     *
     * This method analyzes local signals and keywords to suggest a specific
     * LocalBusiness subtype or a related schema type like Place or Organization.
     *
     * @param bool $isRelevantPage Whether the page is relevant for local schema
     * @param array $localSignals Local business signals detected in the content
     * @param array $localKeywords Local keywords for the page
     * @return string Suggested a schema type
     */
    public function suggestSchemaType(bool $isRelevantPage, array $localSignals, array $localKeywords): string
    {
        // Default schema type if no specific indicators are strong enough
        $schemaType = 'LocalBusiness';

        // Content signals extracted from the local signals analysis array
        $hasAddress = $localSignals['has_address'];
        $hasPhone = $localSignals['has_phone'];
        $hasBusinessHours = $localSignals['has_business_hours'];
        $hasMap = $localSignals['has_map'];
        $hasReviews = $localSignals['has_reviews'];

        // Initial checks based on signal combinations for broader types (Place, ProfessionalService, Organization)
        // If a page has strong address signals but no business hours, it might be just a location
        if ($hasAddress && !$hasBusinessHours && $isRelevantPage) {
            return 'Place';
        }

        // If it has business hours and address, it's likely a physical business location.
        // No specific type returned here, proceed to check for subtypes.
        if ($hasBusinessHours && $hasAddress) {
            // Proceed to subtype detection
        } elseif ($isRelevantPage && ($hasPhone || $hasMap)) {
            // Likely a service-based business without a prominent physical location listed
            return 'ProfessionalService';
        } elseif (!$isRelevantPage && ($localSignals['signal_strength'] ?? 0) < 0.4) { // Use null coalescing for safety
            // If it's not a relevant page and signals are weak, suggest the more generic Organization
            return 'Organization';
        }

        // Try to detect more specific business types from content keywords and matched signal keys
        // Use the centralized constant for business types and their associated keywords.
        $businessTypes = SeoOptimiserConfig::BUSINESS_TYPE_KEYWORDS;

        // Combine input keywords and keys from detected keyword matches for analysis
        // Convert the combined text to lowercase once for case-insensitive comparison
        $combinedText = implode(' ', $localKeywords) . ' ' . implode(' ', array_keys($localSignals['local_keyword_matches'] ?? [])); // Use null coalescing for safety
        $combinedTextLower = strtolower($combinedText);

        $matchedTypes = [];

        // Iterate through defined business types and their keywords
        foreach ($businessTypes as $type => $keywords) {
            $matchCount = 0;
            // Check for the presence of each keyword from the type's list in the combined text
            foreach ($keywords as $keyword) {
                if (stripos($combinedTextLower, $keyword) !== false) {
                    $matchCount++; // Count how many keywords for this type were found
                }
            }

            if ($hasReviews && in_array($type, ['Restaurant', 'Hotel', 'Store'])) {
                $matchCount += 2;
            }

            if ($hasBusinessHours && in_array($type, ['Store', 'Restaurant', 'AutomotiveBusiness'])) {
                $matchCount += 1;
            }

            if ($matchCount > 0) {
                $matchedTypes[$type] = $matchCount;
            }
        }

        // If specific business types were matched, select the one with the highest score
        if (!empty($matchedTypes)) {
            arsort($matchedTypes);
            $schemaType = key($matchedTypes);
        }

        // Return the determined schema type
        return $schemaType;
    }

    /**
     * Helper method to check if any regex pattern from a list matches a given string.
     *
     * @param string $string The string to check against.
     * @param array $patterns The list of regex patterns.
     * @return bool True if any pattern matches, false otherwise.
     */
    protected function _matchesAnyPattern(string $string, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            // Use preg_match to check for a match.
            // If a match is found, return true immediately.
            if (preg_match($pattern, $string)) {
                return true;
            }
        }
        // No pattern matched
        return false;
    }

    /**
     * Determine if the page type is relevant for local business schema
     *
     * @param int $postId The post-ID
     * @param string $postType The post-type
     * @return bool Whether the page type is relevant for local schema
     */
    public function isRelevantPageType(int $postId, string $postType): bool
    {
        // 1. Check if the post-type is explicitly defined as local using the constant
        if ($this->_isTypeInAllowedList($postType, SeoOptimiserConfig::LOCAL_POST_TYPES)) { // Reuse _isTypeInAllowedList helper
            return true;
        }

        // 2. Check if the title or slug contains relevant keywords using the helper
        $title = get_the_title($postId);
        $slug = get_post_field('post_name', $postId);

        // Use the _matchesAnyPattern helper with the centralized patterns constant
        if ($this->_matchesAnyPattern($title, SeoOptimiserConfig::LOCAL_PAGE_PATTERNS) ||
            $this->_matchesAnyPattern($slug, SeoOptimiserConfig::LOCAL_PAGE_PATTERNS)) {
            return true;
        }

        // 3. If it's a 'page' post type, check if the content includes an address pattern
        // This is specific to pages that might not have a descriptive title/slug
        if ($postType === 'page') {
            // Get content - assumes getContent method is available
            $content = $this->getContent($postId);
            // Check for the address pattern using preg_match directly with the constant
            // No need for helper here, as it's one specific regex check.
            if (preg_match(SeoOptimiserConfig::STREET_ADDRESS_PATTERN, $content)) {
                return true;
            }
        }

        // If none of the criteria are met, the page type is not considered relevant for local schema
        return false;
    }

    /**
     * Extracts the schema types from the schema array.
     *
     * @param array $schemas The schemas to extract types from
     * @return array The extracted schema types
     */
    public function extractSchemaTypes(array $schemas): array
    {
        $types = [];

        foreach ($schemas as $schema) {
            if (isset($schema['@type'])) {
                if (is_array($schema['@type'])) {
                    foreach ($schema['@type'] as $type) {
                        $types[] = $type;
                    }
                } else {
                    $types[] = $schema['@type'];
                }
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * Validates schemas against Google's structured data guidelines.
     *
     * @param array $schemas The schemas to validate
     * @return array Validation results
     */
    public function validateSchemas(array $schemas): array
    {
        $results = [
            'valid_schemas' => 0,
            'invalid_schemas' => 0,
            'issues' => [],
            'warnings' => [],
        ];

        foreach ($schemas as $index => $schema) {
            $schemaValidation = $this->validateSchema($schema);

            if ($schemaValidation['valid']) {
                $results['valid_schemas']++;
            } else {
                $results['invalid_schemas']++;

                // Add issues for this schema
                foreach ($schemaValidation['issues'] as $issue) {
                    $results['issues'][] = [
                        'schema_index' => $index,
                        'schema_type' => $schema['@type'] ?? 'Unknown',
                        'issue' => $issue,
                    ];
                }
            }

            // Add warnings for this schema
            foreach ($schemaValidation['warnings'] as $warning) {
                $results['warnings'][] = [
                    'schema_index' => $index,
                    'schema_type' => $schema['@type'] ?? 'Unknown',
                    'warning' => $warning,
                ];
            }
        }

        // Calculate overall score
        $totalSchemas = count($schemas);
        $results['overall_score'] = $totalSchemas > 0 ?
            round(($results['valid_schemas'] / $totalSchemas) * 100, 2) : 0;

        return $results;
    }

    /**
     * Validates a single schema against Google's structured data guidelines.
     *
     * @param array $schema The schema to validate
     * @return array Validation results for the schema
     */
    public function validateSchema(array $schema): array
    {
        $issues = [];
        $warnings = [];

        // Check if the schema has a type (this part is unique to validateSchema's entry point)
        if (!isset($schema['@type'])) {
            $issues[] = 'Missing @type property';
            return [
                'valid' => false,
                'issues' => $issues,
                'warnings' => $warnings,
            ];
        }

        // Get the schema type
        $schemaType = is_array($schema['@type']) ? $schema['@type'][0] : $schema['@type'];
        
        switch ($schemaType) {
            case 'Article': 
            case 'BlogPosting': 
            case 'NewsArticle':
                $requiredProps = ['headline', 'author', 'datePublished', 'publisher'];
                $recommendedProps = ['image', 'dateModified', 'mainEntityOfPage'];
                break;
            case 'Product':
                $requiredProps = ['name', 'offers'];
                $recommendedProps = ['image', 'description', 'brand', 'aggregateRating', 'review'];
                break;
            case 'Organization':
                $requiredProps = ['name', 'url'];
                $recommendedProps = ['logo', 'contactPoint', 'sameAs', 'address'];
                break;
            case 'LocalBusiness':
                $requiredProps = SeoOptimiserConfig::SCHEMA_REQUIRED_PROPERTIES;
                $recommendedProps = SeoOptimiserConfig::SCHEMA_RECOMMENDED_PROPERTIES;
                break;
            case 'Person':
                $requiredProps = ['name'];
                $recommendedProps = ['image', 'jobTitle', 'worksFor', 'sameAs'];
                break;
            case 'Event':
                $requiredProps = ['name', 'startDate', 'location'];
                $recommendedProps = ['image', 'description', 'endDate', 'offers', 'performer'];
                break;
            case 'FAQPage':
                $requiredProps = ['mainEntity'];
                $recommendedProps = [];
                break;
            case 'HowTo':
                $requiredProps = ['name', 'step'];
                $recommendedProps = ['image', 'description', 'totalTime', 'supply', 'tool'];
                break;
            case 'BreadcrumbList':
                $requiredProps = ['itemListElement'];
                $recommendedProps = [];
                break;
            case 'VideoObject':
                $requiredProps = ['name', 'description', 'thumbnailUrl', 'uploadDate'];
                $recommendedProps = ['contentUrl', 'embedUrl', 'duration', 'interactionCount'];
                break;
            default:
                $requiredProps = ['name'];
                $recommendedProps = ['description'];
                if (!in_array($schemaType, ['Thing', 'CreativeWork', 'Place', 'Event', 'Organization', 'Person', 'Product'])) {
                    $warnings[] = "Schema type '$schemaType' is not specifically validated. Using generic validation rules.";
                }
                break;
        }

        // Check for required properties
        $missingRequiredProps = $this->_findMissingProperties($schema, $requiredProps);
        if (!empty($missingRequiredProps)) {
            $issues[] = 'Missing required properties: ' . implode(', ', $missingRequiredProps);
        }

        // Check for recommended properties
        $missingRecommendedProps = $this->_findMissingProperties($schema, $recommendedProps);
        if (!empty($missingRecommendedProps)) {
            $warnings[] = 'Missing recommended properties: ' . implode(', ', $missingRecommendedProps);
        }

        // Check for specific structured data types (delegating to dedicated methods)
        if ($schemaType === 'FAQPage' && isset($schema['mainEntity'])) {
            $this->validateFaqPageSchemaStructure($schema, $issues, $warnings);
        } elseif ($schemaType === 'HowTo' && isset($schema['step'])) {
            $this->validateHowToSchemaStructure($schema, $issues, $warnings);
        } elseif ($schemaType === 'BreadcrumbList' && isset($schema['itemListElement'])) {
            $this->validateBreadcrumbListSchemaStructure($schema, $issues, $warnings);
        } elseif ($schemaType === 'Product' && isset($schema['offers'])) {
            // Note: Product validation includes checks for Offers, Review, AggregateRating
            $this->validateProductSchemaStructure($schema, $issues, $warnings);
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'warnings' => $warnings,
        ];
    }

    /**
     * Helper method to ensure a schema property intended as a list is treated as an array.
     * Handles cases where a single item might be provided as a direct object (associative array with @type).
     *
     * @param mixed $propertyValue The value of the schema property (e.g., the value of 'mainEntity').
     * @return array The value as a sequential array, or an empty array if the input is not a valid array or object structure.
     */
    protected function _ensureArrayFromSchemaProperty(mixed $propertyValue): array
    {
        // If it's already a valid sequential array, return it
        if (is_array($propertyValue) && (empty($propertyValue) || isset($propertyValue[0]))) {
            return $propertyValue;
        }

        // If it's an associative array that looks like a single object (has @type), wrap it in an array
        if (is_array($propertyValue) && isset($propertyValue['@type']) && !isset($propertyValue[0])) {
            return [$propertyValue];
        }

        // If it's anything else (null, string, number, object not as array, etc.), return an empty array
        return [];
    }

    /**
     * Validates FAQPage schema structure.
     *
     * @param array $schema The FAQPage schema to validate
     * @param array &$issues Issues found during validation
     * @param array &$warnings Warnings found during validation
     */
    public function validateFaqPageSchemaStructure(array $schema, array &$issues, array &$warnings): void
    {
        $mainEntity = $this->_ensureArrayFromSchemaProperty($schema['mainEntity'] ?? null);

        if (empty($mainEntity)) {
            if (isset($schema['mainEntity']) && $this->_ensureArrayFromSchemaProperty($schema['mainEntity']) === []) {
                $issues[] = 'mainEntity must be an array of Question items or a single Question object.';
            }
            // Otherwise, if it was just missing, validateSchema handles it.
            return; // Return if no items to validate
        }

        // Check each item in the mainEntity array (each should be a Question)
        foreach ($mainEntity as $index => $question) {
            // Ensure each item is a valid array structure representing an object
            if (!is_array($question) || empty($question)) {
                $issues[] = "Item $index in mainEntity is not a valid object structure.";
                continue;
            }

            // Validate @type for the Question item
            if (!isset($question['@type']) || $question['@type'] !== 'Question') {
                $issues[] = "Item $index in mainEntity should have @type: Question (found '" . ($question['@type'] ?? 'N/A') . "').";
                continue; // Cannot validate properties without a correct type assumption
            }

            // Check required properties for Question using the helper
            // 'name' is required for Question
            $missingQuestionProps = $this->_findMissingProperties($question, ['name']);
            foreach ($missingQuestionProps as $prop) {
                $issues[] = "Question $index is missing the required '" . $prop . "' property.";
            }
            // Could add recommended properties check here if needed for warnings

            // Validate acceptedAnswer property
            if (empty($question['acceptedAnswer'])) { // Check if missing or empty
                $issues[] = "Question $index is missing the 'acceptedAnswer' property or it is empty.";
                continue; // Cannot validate answer structure if missing
            }

            // Ensure acceptedAnswer is a valid array structure representing an object (Answer)
            $answer = $question['acceptedAnswer'];
            if (!is_array($answer)) {
                $issues[] = "AcceptedAnswer for question $index is not a valid object structure.";
                continue;
            }

            // Validate @type for the Answer item
            if (!isset($answer['@type']) || $answer['@type'] !== 'Answer') {
                $issues[] = "AcceptedAnswer for question $index should have @type: Answer (found '" . ($answer['@type'] ?? 'N/A') . "').";
                // Continue validation for 'text' even if the type is wrong, if 'text' is expected universally
            }

            // Check required properties for Answer using the helper
            // 'text' is required for Answer
            $missingAnswerProps = $this->_findMissingProperties($answer, ['text']);
            foreach ($missingAnswerProps as $prop) {
                $issues[] = "Answer for question $index is missing the required '" . $prop . "' property.";
            }
        }
    }

    /**
     * Validates HowTo schema structure.
     * (Uses _ensureArrayFromSchemaProperty and _findMissingProperties helpers)
     *
     * Checks if 'step' is a list of valid HowToStep items with required properties.
     *
     * @param array $schema The HowTo schema to validate. Expected to have a 'step' key.
     * @param array &$issues Issues found during validation (passed by reference).
     * @param array &$warnings Warnings found during validation (passed by reference).
     */
    public function validateHowToSchemaStructure(array $schema, array &$issues, array &$warnings): void
    {
        // Ensure the 'step' property is treated as an array using the helper
        $steps = $this->_ensureArrayFromSchemaProperty($schema['step'] ?? null); // Use null coalescing for safety

        // If 'step' is empty after processing (was not a valid list or item), add an issue and return
        // Similar to FAQPage; this handles invalid structure if 'step' was set but not a valid list/object.
        if (empty($steps)) {
            if (isset($schema['step']) && $this->_ensureArrayFromSchemaProperty($schema['step']) === []) {
                $issues[] = 'step must be an array of HowToStep items or a single HowToStep object.';
            }
            return;
        }


        // Define required and recommended properties for each HowToStep item
        $requiredStepProps = ['text']; // text is the only explicit required one by Schema.org
        $recommendedStepProps = ['name']; // name is recommended


        // Iterate through each item in the step array (each should be a HowToStep)
        foreach ($steps as $index => $step) {
            // Ensure each item is a valid array structure representing an object
            if (!is_array($step) || empty($step)) {
                $issues[] = "Item $index in step list is not a valid object structure.";
                continue;
            }

            // Validate @type for the HowToStep item
            if (!isset($step['@type']) || $step['@type'] !== 'HowToStep') {
                $issues[] = "Item $index in step list should have @type: HowToStep (found '" . ($step['@type'] ?? 'N/A') . "').";
                continue; // Cannot validate properties without a correct type assumption
            }

            // Check required properties for HowToStep using the helper
            $missingRequiredStepProps = $this->_findMissingProperties($step, $requiredStepProps);
            foreach ($missingRequiredStepProps as $prop) {
                $issues[] = "HowToStep $index is missing the required '" . $prop . "' property.";
            }

            // Check recommended properties for HowToStep using the helper (for warnings)
            $missingRecommendedStepProps = $this->_findMissingProperties($step, $recommendedStepProps);
            foreach ($missingRecommendedStepProps as $prop) {
                $warnings[] = "HowToStep $index is missing the recommended '" . $prop . "' property.";
            }
        }
    }

    /**
     * Validates BreadcrumbList schema structure.
     * (Uses _ensureArrayFromSchemaProperty and _findMissingProperties helpers)
     *
     * Checks if 'itemListElement' is a list of valid ListItem items with required properties
     * and correct sequential positions.
     *
     * @param array $schema The BreadcrumbList schema to validate. Expected to have an 'itemListElement' key.
     * @param array &$issues Issues found during validation (passed by reference).
     * @param array &$warnings Warnings found during validation (passed by reference).
     */
    public function validateBreadcrumbListSchemaStructure(array $schema, array &$issues, array &$warnings): void
    {
        // Ensure the 'itemListElement' property is treated as an array using the helper
        $items = $this->_ensureArrayFromSchemaProperty($schema['itemListElement'] ?? null); // Use null coalescing for safety

        // If 'itemListElement' is empty after processing, add issue and return
        // Handles invalid structure if the property was set but not a valid list/object.
        if (empty($items)) {
            if (isset($schema['itemListElement']) && $this->_ensureArrayFromSchemaProperty($schema['itemListElement']) === []) {
                $issues[] = 'itemListElement must be an array of ListItem objects or a single ListItem object.';
            }
            return; // Return if no items to validate
        }

        // Define required properties for each ListItem item
        $requiredListItemProps = ['position', 'item'];


        // Iterate through each item in the itemListElement array (each should be a ListItem)
        $expectedPosition = 1; // Start the expected position from 1
        foreach ($items as $index => $item) {
            // Ensure each item is a valid array structure representing an object
            if (!is_array($item) || empty($item)) {
                $issues[] = "Item $index in itemListElement list is not a valid object structure.";
                $expectedPosition++; // Increment position even for invalid items to keep counter aligned with loop index
                continue;
            }

            // Validate @type for the ListItem item
            if (!isset($item['@type']) || $item['@type'] !== 'ListItem') {
                $issues[] = "Item $index in itemListElement list should have @type: ListItem (found '" . ($item['@type'] ?? 'N/A') . "').";
                $expectedPosition++;
                continue; // Cannot validate properties without a correct type assumption
            }

            // Check required properties for ListItem using the helper
            $missingRequiredItemProps = $this->_findMissingProperties($item, $requiredListItemProps);
            foreach ($missingRequiredItemProps as $prop) {
                $issues[] = "ListItem $index is missing the required '" . $prop . "' property.";
            }

            // Validate the 'position' property specifically
            if (isset($item['position'])) {
                // Check if the position is numeric and matches the expected sequential position
                // Using loose comparison for integer check as the position might be string "1", "2"
                if (!is_numeric($item['position']) || (int)$item['position'] !== $expectedPosition) {
                    $warnings[] = "ListItem $index has incorrect position. Expected position $expectedPosition but found " . ($item['position'] ?? 'N/A') . '. Positions should be sequential starting from 1.';
                }
            }
            $expectedPosition++;
        }
    }

    /**
     * Helper method to process a schema property that can be a single object, an array of objects, or null/empty.
     * Returns a structure that allows easy iteration or single object access.
     *
     * @param mixed $propertyValue The value of the schema property (e.g., the value of 'offers').
     * @return array Returns an array with 'type' ('list' or 'single' or 'empty') and 'data' (the array of items, the single item, or null).
     */
    protected function _processSchemaCollectionProperty(mixed $propertyValue): array
    {
        // If the value is null or empty, it's an empty collection
        if (empty($propertyValue)) {
            return ['type' => 'empty', 'data' => null];
        }

        // If it's already a sequential array, it's a list
        if (is_array($propertyValue) && isset($propertyValue[0])) {
            return ['type' => 'list', 'data' => $propertyValue];
        }

        // If it's an associative array that looks like a single object (has @type but no index 0)
        if (is_array($propertyValue) && isset($propertyValue['@type']) && !isset($propertyValue[0])) {
            // Could add a check here if it's ACTUALLY a valid expected type, e.g.; is it an Offer/AggregateOffer?
            // For now, treat any such array with @type as a potential single object.
            return ['type' => 'single', 'data' => $propertyValue];
        }

        // Any other structure (string, number, object not as array, etc.) is invalid/unhandled
        return ['type' => 'invalid', 'data' => $propertyValue];
    }

    /**
     * Validates Product schema structure.
     * (Uses _processSchemaCollectionProperty for offers, and delegates to specific helpers for substructures)
     *
     * Checks the structure of 'offers', 'review', and 'aggregateRating' properties if present.
     *
     * @param array $schema The Product schema to validate. Expected to potentially have 'offers', 'review', 'aggregateRating' keys.
     *                      Note: This method is often called with a partial schema containing just these keys.
     * @param array &$issues Issues found during validation (passed by reference).
     * @param array &$warnings Warnings found during validation (passed by reference).
     */
    public function validateProductSchemaStructure(array $schema, array &$issues, array &$warnings): void
    {
        if (isset($schema['offers'])) {
            $offersData = $schema['offers'];
            $offersCollection = $this->_processSchemaCollectionProperty($offersData);

            switch ($offersCollection['type']) {
                case 'empty':
                    // 'Offers' property exists but is empty. validateSchema (or _findMissingProperties)
                    $warnings[] = 'Product schema has an empty "offers" property.';
                    break;

                case 'invalid':
                    // 'Offers' property has an unexpected format (not array, or invalid array)
                    $issues[] = 'Product schema "offers" property has an invalid structure. Expected Offer, AggregateOffer, or array of Offers.';
                    break;

                case 'single':
                    // 'Offers' is a single object (Offer or AggregateOffer)
                    $singleOffer = $offersCollection['data'];
                    if (!is_array($singleOffer) || empty($singleOffer) || !isset($singleOffer['@type'])) {
                        $issues[] = 'Product schema "offers" single object is invalid (not array, empty, or missing @type).';
                        break;
                    }

                    $offerType = is_array($singleOffer['@type']) ? $singleOffer['@type'][0] : $singleOffer['@type'];

                    if ($offerType === 'AggregateOffer') {
                        $this->validateAggregateOfferSchemaStructure($singleOffer, $issues, $warnings);
                    } elseif ($offerType === 'Offer') {
                        $this->validateOfferSchemaStructure($singleOffer, 0, $issues, $warnings); // Index 0 indicates it's the first/only one
                    } else {
                        $issues[] = 'Product schema "offers" single object has an unexpected @type: ' . $offerType . '. Expected Offer or AggregateOffer.';
                    }
                    break;

                case 'list':
                    // 'offers' is an array of Offer objects
                    $offerList = $offersCollection['data'];
                    // It's generally expected to be a list of Offers, not AggregateOffers
                    foreach ($offerList as $index => $offerItem) {
                        // Ensure each item in the list is a valid array object
                        if (!is_array($offerItem) || empty($offerItem) || !isset($offerItem['@type'])) {
                            $issues[] = 'Product schema "offers" list item ' . $index . ' is invalid (not array, empty, or missing @type).';
                            continue; // Skip validation for this invalid item
                        }
                        $offerType = is_array($offerItem['@type']) ? $offerItem['@type'][0] : $offerItem['@type'];
                        if ($offerType === 'Offer') {
                            $this->validateOfferSchemaStructure($offerItem, $index, $issues, $warnings);
                        } else {
                            // Optionally validate AggregateOffer in a list, though less common
                            // Or report as an issue if only Offer is expected in a list
                            $issues[] = 'Product schema "offers" list item ' . $index . ' has an unexpected @type: ' . $offerType . '. Expected Offer.';
                        }
                    }
                    break;
            }
        }

        if (!empty($schema['review'])) { // Only validate if present and not empty
            // The helper validateReviewSchemaStructure handles its own input check and iteration
            $this->validateReviewSchemaStructure($schema['review'], $issues, $warnings);
        }

        if (!empty($schema['aggregateRating'])) { // Only validate if present and not empty
            // Ensure it's an array structure before validating
            if (!is_array($schema['aggregateRating']) || empty($schema['aggregateRating'])) {
                $issues[] = 'Product schema "aggregateRating" property is present but not a valid object structure.';
            } else {
                // The helper validateAggregateRatingSchemaStructure handles its own input check
                $this->validateAggregateRatingSchemaStructure($schema['aggregateRating'], $issues, $warnings);
            }
        }
    }

    /**
     * Validates Offer schema structure.
     * (Uses _findMissingProperties helper)
     *
     * Checks @type, required properties (price, priceCurrency), and recommended properties (availability).
     *
     * @param array $offer The Offer schema object (as an array) to validate.
     * @param int $index The index of the offer in a list (for context in messages).
     * @param array &$issues Issues found during validation (passed by reference).
     * @param array &$warnings Warnings found during validation (passed by reference).
     */
    public function validateOfferSchemaStructure(array $offer, int $index, array &$issues, array &$warnings): void
    {
        // Validate @type
        if (!isset($offer['@type']) || $offer['@type'] !== 'Offer') {
            $issues[] = "Offer $index should have @type: Offer (found '" . ($offer['@type'] ?? 'N/A') . "').";
        }

        // Define required and recommended properties for an Offer
        $requiredOfferProps = ['price', 'priceCurrency'];
        $recommendedOfferProps = ['availability']; // Google recommends availability

        // Check for missing required properties using the helper
        $missingRequired = $this->_findMissingProperties($offer, $requiredOfferProps);
        foreach ($missingRequired as $prop) {
            $issues[] = "Offer $index is missing the required '" . $prop . "' property.";
        }

        // Check for missing recommended properties using the helper (for warnings)
        $missingRecommended = $this->_findMissingProperties($offer, $recommendedOfferProps);
        foreach ($missingRecommended as $prop) {
            $warnings[] = "Offer $index is missing the recommended '" . $prop . "' property.";
        }

        // Validate the 'price' property's format
        if (!empty($offer['price'])) { // Check only if present and not empty (missing already reported)
            if (!is_numeric($offer['price'])) {
                $issues[] = "Offer $index price ('" . $offer['price'] . "') must be a numeric value.";
            }
        }

        // Validate the 'availability' property's value if it exists and is not empty
        if (!empty($offer['availability'])) {
            // Standard schema.org Availability strings/URIs
            $allowedAvailability = [
                'http://schema.org/InStock', 'https://schema.org/InStock', 'InStock',
                'http://schema.org/OutOfStock', 'https://schema.org/OutOfStock', 'OutOfStock',
                'http://schema.org/PreOrder', 'https://schema.org/PreOrder', 'PreOrder',
                'http://schema.org/SoldOut', 'https://schema.org/SoldOut', 'SoldOut',
                'http://schema.org/Discontinued', 'https://schema.org/Discontinued', 'Discontinued',
            ];

            $availabilityValue = $offer['availability'];

            // Availability can sometimes be an object or array, but the simple string/URI is most common and expected by Google
            if (is_string($availabilityValue)) {
                if (!in_array($availabilityValue, $allowedAvailability, true)) { // Strict comparison
                    $warnings[] = "Offer $index has an invalid availability value: '" . $availabilityValue . "'. Recommended to use standard schema.org values.";
                }
            } else {
                // If availability is present but not a string, issue a warning about the unexpected format
                $warnings[] = "Offer $index 'availability' property has an unexpected format (expected string).";
            }
        }
    }

    /**
     * Validates AggregateOffer schema structure.
     * (Uses _findMissingProperties helper)
     *
     * Checks @type, required properties (lowPrice, priceCurrency), recommended properties (highPrice, offerCount),
     * and format of price properties.
     *
     * @param array $aggregateOffer The AggregateOffer schema object (as an array) to validate.
     * @param array &$issues Issues found during validation (passed by reference).
     * @param array &$warnings Warnings found during validation (passed by reference).
     */
    public function validateAggregateOfferSchemaStructure(array $aggregateOffer, array &$issues, array &$warnings): void
    {
        // Validate @type
        if (!isset($aggregateOffer['@type']) || $aggregateOffer['@type'] !== 'AggregateOffer') {
            $issues[] = 'AggregateOffer should have @type: AggregateOffer (found "' . ($aggregateOffer['@type'] ?? 'N/A') . '").';
        }

        // Define required and recommended properties for an AggregateOffer
        $requiredAggregateOfferProps = ['lowPrice', 'priceCurrency'];
        $recommendedAggregateOfferProps = ['highPrice', 'offerCount'];


        // Check for missing required properties using the helper
        $missingRequired = $this->_findMissingProperties($aggregateOffer, $requiredAggregateOfferProps);
        foreach ($missingRequired as $prop) {
            $issues[] = "AggregateOffer is missing the required '" . $prop . "' property.";
        }

        // Check for missing recommended properties using the helper (for warnings)
        $missingRecommended = $this->_findMissingProperties($aggregateOffer, $recommendedAggregateOfferProps);
        foreach ($missingRecommended as $prop) {
            $warnings[] = "AggregateOffer is missing the recommended '" . $prop . "' property.";
        }

        // Validate 'lowPrice' format if present and not empty
        if (!empty($aggregateOffer['lowPrice'])) { // Check only if present and not empty (missing already reported)
            if (!is_numeric($aggregateOffer['lowPrice'])) {
                $issues[] = "AggregateOffer lowPrice ('" . $aggregateOffer['lowPrice'] . "') must be a numeric value.";
            }
        }

        // Validate 'highPrice' format if present and not empty
        if (!empty($aggregateOffer['highPrice'])) { // Check only if present and not empty (missing already reported)
            if (!is_numeric($aggregateOffer['highPrice'])) {
                $issues[] = "AggregateOffer highPrice ('" . $aggregateOffer['highPrice'] . "') must be a numeric value.";
            }
            if (isset($aggregateOffer['lowPrice']) && is_numeric($aggregateOffer['lowPrice']) && is_numeric($aggregateOffer['highPrice'])) {
                if ((float)$aggregateOffer['highPrice'] < (float)$aggregateOffer['lowPrice']) {
                    $warnings[] = 'AggregateOffer highPrice (' . $aggregateOffer['highPrice'] . ') is less than lowPrice (' . $aggregateOffer['lowPrice'] . ').';
                }
            }
        }

        // Validate the 'offerCount' format if present and not empty
        if (!empty($aggregateOffer['offerCount'])) {
            if (!is_numeric($aggregateOffer['offerCount']) || (int)$aggregateOffer['offerCount'] <= 0) {
                $warnings[] = "AggregateOffer offerCount ('" . $aggregateOffer['offerCount'] . "') must be a positive integer.";
            }
        }
    }

    /**
     * Validates Review schema structure.
     * (Uses _ensureArrayFromSchemaProperty and _findMissingProperties helpers)
     *
     * Checks if the input is a list of valid Review items with required properties
     * and correct nested Rating and Author structures.
     *
     * @param array|mixed $review The value of the 'review' property from a schema
     * @param array &$issues Issues found during validation (passed by reference).
     * @param array &$warnings Warnings found during validation (passed by reference).
     */
    public function validateReviewSchemaStructure(mixed $review, array &$issues, array &$warnings): void
    {
        // Ensure the input is treated as an array of items using the helper
        $reviewList = $this->_ensureArrayFromSchemaProperty($review);

        if (empty($reviewList)) {
            // Check if the original property existed but was invalid/empty
            if (isset($review) && $this->_ensureArrayFromSchemaProperty($review) === []) {
                $warnings[] = 'The "review" property is present but empty or has an invalid structure. Expected Review object(s).';
            }
            return;
        }

        // Define required properties for a Review item and a Rating item
        $requiredReviewProps = ['reviewRating', 'author'];
        $requiredRatingProps = ['ratingValue'];

        // Iterate through each item in the review list (each should be a Review)
        foreach ($reviewList as $index => $item) {
            if (!is_array($item) || empty($item)) {
                $issues[] = "Review item $index is not a valid object structure.";
                continue;
            }

            if (!isset($item['@type']) || $item['@type'] !== 'Review') {
                $issues[] = "Review item $index should have @type: Review (found '" . ($item['@type'] ?? 'N/A') . "').";
            }

            // Check required properties for Review using the helper
            $missingRequiredReviewProps = $this->_findMissingProperties($item, $requiredReviewProps);
            foreach ($missingRequiredReviewProps as $prop) {
                $issues[] = "Review item $index is missing the required '" . $prop . "' property.";
            }

            // Only proceed if the reviewRating property exists and is not empty (missing already reported)
            if (!empty($item['reviewRating'])) {
                $rating = $item['reviewRating'];
                // Ensure rating is a valid array structure representing a Rating object
                if (!is_array($rating) || empty($rating)) {
                    $issues[] = "Review item $index 'reviewRating' property is present but not a valid object structure.";
                } else {
                    // Validate @type for the Rating item
                    if (!isset($rating['@type']) || $rating['@type'] !== 'Rating') {
                        $issues[] = "Review item $index 'reviewRating' should have @type: Rating (found '" . ($rating['@type'] ?? 'N/A') . "').";
                    }

                    // Check required properties for Rating using the helper
                    $missingRequiredRatingProps = $this->_findMissingProperties($rating, $requiredRatingProps, true);
                    foreach ($missingRequiredRatingProps as $prop) {
                        $issues[] = "Review item $index 'reviewRating' is missing the required '" . $prop . "' property or it is empty/non-numeric.";
                    }
                    // Note: Numeric check for ratingValue is implicitly handled by _findMissingProperties
                    // if strictEmptyCheck is true AND it's not numeric. Could add explicit numeric check if needed.
                    if (!empty($rating['ratingValue']) && !is_numeric($rating['ratingValue'])) {
                        $issues[] = "Review item $index 'reviewRating' 'ratingValue' property ('" . $rating['ratingValue'] . "') must be a numeric value.";
                    }
                }
            }

            // Only proceed if the author property exists and is not empty (missing already reported)
            if (!empty($item['author'])) {
                $author = $item['author'];

                if (!is_array($author) || empty($author)) {
                    $issues[] = "Review item $index 'author' property is present but not a valid object structure.";
                } else {

                    if (!isset($author['@type']) || !in_array($author['@type'], ['Person', 'Organization'])) {
                        $issues[] = "Review item $index 'author' should have @type: Person or Organization (found '" . ($author['@type'] ?? 'N/A') . "').";
                    }

                    $missingRequiredAuthorProps = $this->_findMissingProperties($author, ['name']);
                    foreach ($missingRequiredAuthorProps as $prop) {
                        $issues[] = "Review item $index 'author' is missing the required '" . $prop . "' property.";
                    }

                }
            }

            if (isset($item['itemReviewed']) && empty($item['itemReviewed'])) {
                // It was present but empty
            } elseif (!isset($item['itemReviewed'])) {
                // Already covered if the itemReviewed was in requiredReviewProps for _findMissingProperties
            } else {
                // ItemReviewed is present and not empty. Validate its type/structure.
                $reviewedItem = $item['itemReviewed'];

                if (!is_array($reviewedItem) || empty($reviewedItem) || empty($reviewedItem['name']) || (empty($reviewedItem['id']) && empty($reviewedItem['@id']))) {
                    $issues[] = "Review item $index 'itemReviewed' property is present but invalid or missing recommended details (name, id/@id).";
                }
            }
        }
    }

    /**
     * Validates AggregateRating schema structure
     *
     * @param array $aggregateRating The AggregateRating schema object (as an array) to validate.
     * @param array &$issues Issues found during validation (passed by reference).
     * @param array &$warnings Warnings found during validation (passed by reference).
     */
    public function validateAggregateRatingSchemaStructure(array $aggregateRating, array &$issues, array &$warnings): void
    {
        // Validate @type
        if (!isset($aggregateRating['@type']) || $aggregateRating['@type'] !== 'AggregateRating') {
            $issues[] = 'AggregateRating should have @type: AggregateRating (found "' . ($aggregateRating['@type'] ?? 'N/A') . '").';
        }

        // Define required properties for an AggregateRating
        $requiredAggregateRatingProps = ['ratingValue'];

        // Check for missing required 'ratingValue' using the helper
        $missingRatingValue = $this->_findMissingProperties($aggregateRating, $requiredAggregateRatingProps, true); // Use strict check for potential 0 or empty string
        foreach ($missingRatingValue as $prop) {
            $issues[] = "AggregateRating is missing the required '" . $prop . "' property or it is empty/non-numeric.";
        }

        if (!empty($aggregateRating['ratingValue']) && !is_numeric($aggregateRating['ratingValue'])) {
            $issues[] = "AggregateRating 'ratingValue' ('" . $aggregateRating['ratingValue'] . "') must be a numeric value.";
        }

        $hasReviewCount = isset($aggregateRating['reviewCount']) && is_numeric($aggregateRating['reviewCount']); // Must be numeric
        $hasRatingCount = isset($aggregateRating['ratingCount']) && is_numeric($aggregateRating['ratingCount']); // Must be numeric

        // Check if *at least one* count property is present and numeric
        if (!$hasReviewCount && !$hasRatingCount) {
            $issues[] = 'AggregateRating must include either a numeric reviewCount or a numeric ratingCount property.';
        } else {
            if (isset($aggregateRating['reviewCount']) && !is_numeric($aggregateRating['reviewCount'])) {
                $issues[] = "AggregateRating 'reviewCount' ('" . $aggregateRating['reviewCount'] . "') must be a numeric value."; // Or integer?
            }
            if (isset($aggregateRating['ratingCount']) && !is_numeric($aggregateRating['ratingCount'])) {
                $issues[] = "AggregateRating 'ratingCount' ('" . $aggregateRating['ratingCount'] . "') must be a numeric value."; // Or integer?
            }
            if ($hasReviewCount && $hasRatingCount) {
                $warnings[] = 'AggregateRating includes both reviewCount and ratingCount. Google typically prefers reviewCount if both are present.';
            }
        }
    }
}
