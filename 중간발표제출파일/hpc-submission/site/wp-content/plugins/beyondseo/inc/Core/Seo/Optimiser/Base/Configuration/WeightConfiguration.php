<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Base\Configuration;

if (!defined('ABSPATH')) { exit; }

/**
 * Class WeightConfiguration
 *
 * This class provides a configuration for weights assigned to different contexts, factors, and operations
 * in the SEO optimisation process.
 */
class WeightConfiguration
{
    // Weight constants for use in attributes (PHP attributes require constant expressions)

    // Context weights as constants
    public const WEIGHT_CONTENT_OPTIMISATION_CONTEXT = 0.65;
    public const WEIGHT_TECHNICAL_SEO_CONTEXT = 0.15;
    public const WEIGHT_LINKING_STRATEGY_CONTEXT = 0.1;
    public const WEIGHT_PERFORMANCE_AND_SPEED_CONTEXT = 0.1;

    // Factor weights as constants
    public const WEIGHT_ASSIGN_KEYWORDS_FACTOR = 0.1;
    public const WEIGHT_CONTENT_QUALITY_AND_LENGTH_FACTOR = 0.15;
    public const WEIGHT_CONTENT_READABILITY_FACTOR = 0.1;
    public const WEIGHT_FIRST_PARAGRAPH_KEYWORD_USAGE_FACTOR = 0.08;
    public const WEIGHT_HEADER_TAGS_STRUCTURE_FACTOR = 0.08;
    public const WEIGHT_META_DESCRIPTION_FORMAT_OPTIMIZATION_FACTOR = 0.04;
    public const WEIGHT_META_DESCRIPTION_KEYWORDS_FACTOR = 0.04;
    public const WEIGHT_META_TITLE_FORMAT_OPTIMIZATION_FACTOR = 0.08;
    public const WEIGHT_META_TITLE_KEYWORDS_FACTOR = 0.11;
    public const WEIGHT_PAGE_CONTENT_KEYWORDS_FACTOR = 0.12;
    public const WEIGHT_OPTIMIZE_URL_STRUCTURE_FACTOR = 0.25;
    public const WEIGHT_SCHEMA_MARKUP_FACTOR = 0.25;
    public const WEIGHT_SEARCH_ENGINE_INDEXATION_FACTOR = 0.25;
    public const WEIGHT_USE_CANONICAL_TAGS_FACTOR = 0.25;
    public const WEIGHT_ALT_TEXT_TO_IMAGES_FACTOR = 0.4;
    public const WEIGHT_IMAGE_OPTIMIZATION_FACTOR = 0.6;
    public const WEIGHT_FIX_BROKEN_LINKS_ON_PAGE_FACTOR = 1.0;

    // Operation weights as constants - All operations from the $operationWeights array

    // AltTextToImages Operations
    public const WEIGHT_ALT_TEXT_PRESENCE_CHECK_OPERATION = 0.5;
    public const WEIGHT_DESCRIPTIVE_ALT_TEXT_OPERATION = 0.2;
    public const WEIGHT_PRIMARY_KEYWORD_IN_ALT_TEXT_OPERATION = 0.3;

    // AssignKeywords Operations
    public const WEIGHT_KEYWORD_MAPPING_CONTENT_OPERATION = 0.25;
    public const WEIGHT_PRIMARY_SECONDARY_KEYWORDS_VALIDATION_OPERATION = 0.5;

    // ContentQualityAndLength Operations
    public const WEIGHT_CONTENT_LENGTH_VALIDATION_OPERATION = 0.5;
    public const WEIGHT_MULTIMEDIA_INCLUSION_CHECK_OPERATION = 0.25;
    public const WEIGHT_READABILITY_VALIDATION_OPERATION = 0.25;

    // ContentReadability Operations
    public const WEIGHT_AUDIENCE_TARGETED_ADJUSTMENTS_OPERATION = 0.2;
    public const WEIGHT_CONTENT_FORMATTING_VALIDATION_OPERATION = 0.3;
    public const WEIGHT_READABILITY_SCORE_VALIDATION_OPERATION = 0.5;

    // FirstParagraphKeywordUsage Operations
    public const WEIGHT_FIRST_PARAGRAPH_KEYWORD_CHECK_OPERATION = 0.7;
    public const WEIGHT_FIRST_PARAGRAPH_KEYWORD_STUFFING_OPERATION = 0.3;

    // FixBrokenLinksOnPage Operations
    public const WEIGHT_BROKEN_LINKS_IDENTIFICATION_OPERATION = 1.0;

    // HeaderTagsStructure Operations
    public const WEIGHT_FIXING_HEADER_CONSISTENCY_OPERATION = 0.2;
    public const WEIGHT_HEADER_HIERARCHY_CHECK_OPERATION = 0.5;
    public const WEIGHT_KEYWORDS_IN_HEADER_CHECK_OPERATION = 0.3;

    // ImageOptimization Operations
    public const WEIGHT_IMAGE_COMPRESSION_VALIDATION_OPERATION = 0.5;
    public const WEIGHT_NEXT_GEN_IMAGE_FORMAT_VALIDATION_OPERATION = 0.3;
    public const WEIGHT_RESPONSIVE_IMAGE_SIZING_OPERATION = 0.2;

    // LocalKeywordsInContent Operations

    // MetaDescriptionFormatOptimization Operations
    public const WEIGHT_META_DESCRIPTION_LENGTH_CHECK_OPERATION = 0.5;
    public const WEIGHT_META_DESCRIPTION_CTA_VALIDATION_OPERATION = 0.5;

    // MetaDescriptionKeywords Operations
    public const WEIGHT_PRIMARY_SECONDARY_KEYWORD_CHECK_OPERATION = 0.7;
    public const WEIGHT_KEYWORD_OVERUSE_OPERATION = 0.3;

    // MetaTitleFormatOptimization Operations
    public const WEIGHT_META_TITLE_LENGTH_CHECK_OPERATION = 0.5;
    public const WEIGHT_META_TITLE_QUALITY_ANALYZER_OPERATION = 0.5;

    // MetaTitleKeywords Operations
    public const WEIGHT_PRIMARY_KEYWORD_CHECK_OPERATION = 0.7;
    public const WEIGHT_SECONDARY_KEYWORDS_CHECK_OPERATION = 0.3;

    // OptimizePageSpeed Operations

    // OptimizeUrlStructure Operations
    public const WEIGHT_HYPHENS_INSTEAD_OF_UNDERSCORES_OPERATION = 0.2;
    public const WEIGHT_PRIMARY_KEYWORD_IN_URL_OPERATION = 0.3;
    public const WEIGHT_URL_LENGTH_CHECK_OPERATION = 0.2;
    public const WEIGHT_URL_READABILITY_OPERATION = 0.3;

    // PageContentKeywords Operations
    public const WEIGHT_KEYWORD_DENSITY_VALIDATION_OPERATION = 0.4;
    public const WEIGHT_KEYWORD_DISTRIBUTION_OPERATION = 0.35;
    public const WEIGHT_RELATED_KEYWORD_INCLUSION_OPERATION = 0.25;

    // SchemaMarkup Operations
    public const WEIGHT_SCHEMA_MARKUP_VALIDATION_OPERATION = 0.4;

    // SearchEngineIndexation Operations
    public const WEIGHT_ROBOTS_META_TAG_VALIDATION_OPERATION = 0.5;
    public const WEIGHT_ROBOTS_TXT_VALIDATION_OPERATION = 0.5;

    // UseCanonicalTags Operations
    public const WEIGHT_CANONICAL_TAG_VALIDATION_OPERATION = 0.5;
    public const WEIGHT_CROSS_DOMAIN_CANONICAL_CHECK_OPERATION = 0.2;
    public const WEIGHT_DUPLICATE_CONTENT_DETECTION_OPERATION = 0.3;

    /**
     * Weights collections for contexts, factors, and operations.
     */
    private static array $contextWeights = [
        'ContentOptimisationContext' => self::WEIGHT_CONTENT_OPTIMISATION_CONTEXT,
        'TechnicalSeoContext' => self::WEIGHT_TECHNICAL_SEO_CONTEXT,
        'LinkingStrategyContext' => self::WEIGHT_LINKING_STRATEGY_CONTEXT,
        'PerformanceAndSpeedContext' => self::WEIGHT_PERFORMANCE_AND_SPEED_CONTEXT,
    ];

    private static array $factorWeights = [
        // Content Optimisation Factors
        'AssignKeywordsFactor' => self::WEIGHT_ASSIGN_KEYWORDS_FACTOR,
        'ContentQualityAndLengthFactor' => self::WEIGHT_CONTENT_QUALITY_AND_LENGTH_FACTOR,
        'ContentReadabilityFactor' => self::WEIGHT_CONTENT_READABILITY_FACTOR,
        'FirstParagraphKeywordUsageFactor' => self::WEIGHT_FIRST_PARAGRAPH_KEYWORD_USAGE_FACTOR,
        'HeaderTagsStructureFactor' => self::WEIGHT_HEADER_TAGS_STRUCTURE_FACTOR,
        'MetaDescriptionFormatOptimizationFactor' => self::WEIGHT_META_DESCRIPTION_FORMAT_OPTIMIZATION_FACTOR,
        'MetaDescriptionKeywordsFactor' => self::WEIGHT_META_DESCRIPTION_KEYWORDS_FACTOR,
        'MetaTitleFormatOptimizationFactor' => self::WEIGHT_META_TITLE_FORMAT_OPTIMIZATION_FACTOR,
        'MetaTitleKeywordsFactor' => self::WEIGHT_META_TITLE_KEYWORDS_FACTOR,
        'PageContentKeywordsFactor' => self::WEIGHT_PAGE_CONTENT_KEYWORDS_FACTOR,

        // Technical SEO Factors
        'OptimizeUrlStructureFactor' => self::WEIGHT_OPTIMIZE_URL_STRUCTURE_FACTOR,
        'SchemaMarkupFactor' => self::WEIGHT_SCHEMA_MARKUP_FACTOR,
        'SearchEngineIndexationFactor' => self::WEIGHT_SEARCH_ENGINE_INDEXATION_FACTOR,
        'UseCanonicalTagsFactor' => self::WEIGHT_USE_CANONICAL_TAGS_FACTOR,

        // Performance And Speed Factors
        'AltTextToImagesFactor' => self::WEIGHT_ALT_TEXT_TO_IMAGES_FACTOR,
        'ImageOptimizationFactor' => self::WEIGHT_IMAGE_OPTIMIZATION_FACTOR,

        // Linking Strategy Factors
        'FixBrokenLinksOnPageFactor' => self::WEIGHT_FIX_BROKEN_LINKS_ON_PAGE_FACTOR,
    ];

    private static array $operationWeights = [

        // AltTextToImages Operations
        'AltTextPresenceCheckOperation' => self::WEIGHT_ALT_TEXT_PRESENCE_CHECK_OPERATION,
        'DescriptiveAltTextOperation' => self::WEIGHT_DESCRIPTIVE_ALT_TEXT_OPERATION,
        'PrimaryKeywordInAltTextOperation' => self::WEIGHT_PRIMARY_KEYWORD_IN_ALT_TEXT_OPERATION,

        // AnalyzeBacklinkProfile Operations

        // AssignKeywords Operations
        'KeywordMappingContentOperation' => self::WEIGHT_KEYWORD_MAPPING_CONTENT_OPERATION,
        'PrimarySecondaryKeywordsValidationOperation' => self::WEIGHT_PRIMARY_SECONDARY_KEYWORDS_VALIDATION_OPERATION,

        // ContentQualityAndLength Operations
        'ContentLengthValidationOperation' => self::WEIGHT_CONTENT_LENGTH_VALIDATION_OPERATION,
        'MultimediaInclusionCheckOperation' => self::WEIGHT_MULTIMEDIA_INCLUSION_CHECK_OPERATION,
        'ReadabilityValidationOperation' => self::WEIGHT_READABILITY_VALIDATION_OPERATION,

        // ContentReadability Operations
        'AudienceTargetedAdjustmentsOperation' => self::WEIGHT_AUDIENCE_TARGETED_ADJUSTMENTS_OPERATION,
        'ContentFormattingValidationOperation' => self::WEIGHT_CONTENT_FORMATTING_VALIDATION_OPERATION,
        'ReadabilityScoreValidationOperation' => self::WEIGHT_READABILITY_SCORE_VALIDATION_OPERATION,

        // FirstParagraphKeywordUsage Operations
        'FirstParagraphKeywordCheckOperation' => self::WEIGHT_FIRST_PARAGRAPH_KEYWORD_CHECK_OPERATION,
        'FirstParagraphKeywordStuffingOperation' => self::WEIGHT_FIRST_PARAGRAPH_KEYWORD_STUFFING_OPERATION,

        // FixBrokenLinksOnPage Operations
        'BrokenLinksIdentificationOperation' => self::WEIGHT_BROKEN_LINKS_IDENTIFICATION_OPERATION,

        // HeaderTagsStructure Operations
        'FixingHeaderConsistencyOperation' => self::WEIGHT_FIXING_HEADER_CONSISTENCY_OPERATION,
        'HeaderHierarchyCheckOperation' => self::WEIGHT_HEADER_HIERARCHY_CHECK_OPERATION,
        'KeywordsInHeaderCheckOperation' => self::WEIGHT_KEYWORDS_IN_HEADER_CHECK_OPERATION,

        // ImageOptimization Operations
        'ImageCompressionValidationOperation' => self::WEIGHT_IMAGE_COMPRESSION_VALIDATION_OPERATION,
        'NextGenImageFormatValidationOperation' => self::WEIGHT_NEXT_GEN_IMAGE_FORMAT_VALIDATION_OPERATION,
        'ResponsiveImageSizingOperation' => self::WEIGHT_RESPONSIVE_IMAGE_SIZING_OPERATION,

        // LocalKeywordsInContent Operations

        // MetaDescriptionFormatOptimization Operations
        'MetaDescriptionLengthCheckOperation' => self::WEIGHT_META_DESCRIPTION_LENGTH_CHECK_OPERATION,
        'MetaDescriptionCtaValidationOperation' => self::WEIGHT_META_DESCRIPTION_CTA_VALIDATION_OPERATION,

        // MetaDescriptionKeywords Operations
        'PrimarySecondaryKeywordCheckOperation' => self::WEIGHT_PRIMARY_SECONDARY_KEYWORD_CHECK_OPERATION,
        'KeywordOveruseOperation' => self::WEIGHT_KEYWORD_OVERUSE_OPERATION,

        // MetaTitleFormatOptimization Operations
        'MetaTitleLengthCheckOperation' => self::WEIGHT_META_TITLE_LENGTH_CHECK_OPERATION,
        'MetaTitleQualityAnalyzerOperation' => self::WEIGHT_META_TITLE_QUALITY_ANALYZER_OPERATION,

        // MetaTitleKeywords Operations
        'PrimaryKeywordCheckOperation' => self::WEIGHT_PRIMARY_KEYWORD_CHECK_OPERATION,
        'SecondaryKeywordsCheckOperation' => self::WEIGHT_SECONDARY_KEYWORDS_CHECK_OPERATION,

        // OptimizePageSpeed Operations

        // OptimizeUrlStructure Operations
        'HyphensInsteadOfUnderscoresOperation' => self::WEIGHT_HYPHENS_INSTEAD_OF_UNDERSCORES_OPERATION,
        'PrimaryKeywordInUrlOperation' => self::WEIGHT_PRIMARY_KEYWORD_IN_URL_OPERATION,
        'UrlLengthCheckOperation' => self::WEIGHT_URL_LENGTH_CHECK_OPERATION,
        'UrlReadabilityOperation' => self::WEIGHT_URL_READABILITY_OPERATION,

        // PageContentKeywords Operations
        'KeywordDensityValidationOperation' => self::WEIGHT_KEYWORD_DENSITY_VALIDATION_OPERATION,
        'KeywordDistributionOperation' => self::WEIGHT_KEYWORD_DISTRIBUTION_OPERATION,
        'RelatedKeywordInclusionOperation' => self::WEIGHT_RELATED_KEYWORD_INCLUSION_OPERATION,

        // SchemaMarkup Operations
        'SchemaMarkupValidationOperation' => self::WEIGHT_SCHEMA_MARKUP_VALIDATION_OPERATION,

        // SearchEngineIndexation Operations
        'RobotsMetaTagValidationOperation' => self::WEIGHT_ROBOTS_META_TAG_VALIDATION_OPERATION,
        'RobotsTxtValidationOperation' => self::WEIGHT_ROBOTS_TXT_VALIDATION_OPERATION,

        // UseCanonicalTags Operations
        'CanonicalTagValidationOperation' => self::WEIGHT_CANONICAL_TAG_VALIDATION_OPERATION,
        'CrossDomainCanonicalCheckOperation' => self::WEIGHT_CROSS_DOMAIN_CANONICAL_CHECK_OPERATION,
        'DuplicateContentDetectionOperation' => self::WEIGHT_DUPLICATE_CONTENT_DETECTION_OPERATION,
    ];

    /**
     * Get the weight configuration for contexts, factors, and operations.
     *
     * @param string $className
     * @return float
     */
    public static function getContextWeight(string $className): float
    {
        return self::$contextWeights[$className] ?? 0.0;
    }

    /**
     * Get the weight configuration for factors.
     *
     * @param string $className
     * @return float
     */
    public static function getFactorWeight(string $className): float
    {
        return self::$factorWeights[$className] ?? 0.0;
    }

    /**
     * Get the weight configuration for operations.
     *
     * @param string $className
     * @return float
     */
    public static function getOperationWeight(string $className): float
    {
        return self::$operationWeights[$className] ?? 0.0;
    }

    /**
     * Get all context weights.
     *
     * @return array
     */
    public static function getAllContextWeights(): array
    {
        return self::$contextWeights;
    }

    /**
     * Get all factor weights.
     *
     * @return array
     */
    public static function getAllFactorWeights(): array
    {
        return self::$factorWeights;
    }

    /**
     * Get all operation weights.
     *
     * @return array
     */
    public static function getAllOperationWeights(): array
    {
        return self::$operationWeights;
    }

    /**
     * Get the weight by type (context, factor, operation).
     *
     * @param string $className
     * @param string $type
     * @return float
     */
    public static function getWeightByType(string $className, string $type): float
    {
        return match ($type) {
            'context' => self::getContextWeight($className),
            'factor' => self::getFactorWeight($className),
            'operation' => self::getOperationWeight($className),
            default => 0.0
        };
    }
}
