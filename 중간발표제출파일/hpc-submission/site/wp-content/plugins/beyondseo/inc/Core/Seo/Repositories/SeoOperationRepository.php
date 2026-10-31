<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Repositories;

if (!defined('ABSPATH')) { exit; }

use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operations;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Operation;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\GenericOperation;

/**
 * Class SeoOperationRepository
 * 
 * Manages database operations for SEO checks.
 */
class SeoOperationRepository
{
    private static array $classMap = [
        'primary_keyword_in_alt_text' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\AltTextToImages\PrimaryKeywordInAltTextOperation::class,
        'keyword_mapping_content_validator' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\AssignKeywords\KeywordMappingContentOperation::class,
        'primary_secondary_keywords_validation' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\AssignKeywords\PrimarySecondaryKeywordsValidationOperation::class,
        'content_length_validation' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\ContentQualityAndLength\ContentLengthValidationOperation::class,
        'multimedia_inclusion_check' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\ContentQualityAndLength\MultimediaInclusionCheckOperation::class,
        'readability_validation' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\ContentQualityAndLength\ReadabilityValidationOperation::class,
        'audience_targeted_adjustments' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\ContentReadability\AudienceTargetedAdjustmentsOperation::class,
        'content_formatting_validation' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\ContentReadability\ContentFormattingValidationOperation::class,
        'readability_score_validation' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\ContentReadability\ReadabilityScoreValidationOperation::class,
        'first_paragraph_keyword_check' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\FirstParagraphKeywordUsage\FirstParagraphKeywordCheckOperation::class,
        'first_paragraph_keyword_stuffing' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\FirstParagraphKeywordUsage\FirstParagraphKeywordStuffingOperation::class,
        'broken_links_identification' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\FixBrokenLinksOnPage\BrokenLinksIdentificationOperation::class,
        'fixing_header_consistency' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\HeaderTagsStructure\FixingHeaderConsistencyOperation::class,
        'header_hierarchy_check' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\HeaderTagsStructure\HeaderHierarchyCheckOperation::class,
        'keywords_in_header_check' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\HeaderTagsStructure\KeywordsInHeaderCheckOperation::class,
        'image_compression_validation' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\ImageOptimization\ImageCompressionValidationOperation::class,
        'next_gen_image_format_validation' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\ImageOptimization\NextGenImageFormatValidationOperation::class,
        'responsive_image_sizing' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\ImageOptimization\ResponsiveImageSizingOperation::class,
        'meta_description_cta_validation' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\MetaDescriptionFormatOptimization\MetaDescriptionCtaValidationOperation::class,
        'meta_description_length_check' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\MetaDescriptionFormatOptimization\MetaDescriptionLengthCheckOperation::class,
        'description_keyword_overuse' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\MetaDescriptionKeywords\DescriptionKeywordOveruseOperation::class,
        'primary_secondary_keyword_check' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\MetaDescriptionKeywords\PrimarySecondaryKeywordCheckOperation::class,
        'meta_title_length_check' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\MetaTitleFormatOptimization\MetaTitleLengthCheckOperation::class,
        'meta_title_quality_analyzer' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\MetaTitleFormatOptimization\MetaTitleQualityAnalyzerOperation::class,
        'primary_keyword_check' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\MetaTitleKeywords\PrimaryKeywordCheckOperation::class,
        'secondary_keywords_check' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\MetaTitleKeywords\SecondaryKeywordsCheckOperation::class,
        'hyphens_instead_of_underscores' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\OptimizeUrlStructure\HyphensInsteadOfUnderscoresOperation::class,
        'primary_keyword_in_url' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\OptimizeUrlStructure\PrimaryKeywordInUrlOperation::class,
        'url_length_check' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\OptimizeUrlStructure\UrlLengthCheckOperation::class,
        'url_readability' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\OptimizeUrlStructure\UrlReadabilityOperation::class,
        'keyword_density_validation' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\PageContentKeywords\KeywordDensityValidationOperation::class,
        'keyword_distribution' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\PageContentKeywords\KeywordDistributionOperation::class,
        'related_keyword_inclusion' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\PageContentKeywords\RelatedKeywordInclusionOperation::class,
        'schema_markup_validation' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\SchemaMarkup\SchemaMarkupValidationOperation::class,
        'robots_txt_validation' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\SearchEngineIndexation\RobotsTxtValidationOperation::class,
        'canonical_tag_validation' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\UseCanonicalTags\CanonicalTagValidationOperation::class,
        'cross_domain_canonical_check' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\UseCanonicalTags\CrossDomainCanonicalCheckOperation::class,
        'duplicate_content_detection' => \RankingCoach\Inc\Core\Seo\Optimiser\Operations\UseCanonicalTags\DuplicateContentDetectionOperation::class,
    ];

    /**
     * Save/upsert operations to the database
     *
     * @param Operations $operations
     * @return void
     */
    public function save(Operations $operations): void
    {
        $db = DatabaseManager::getInstance();
        foreach ($operations->elements as $operation) {
            $data = [
                'factorId' => $operation->factorId,
                'operationKey' => $operation->operationKey,
                'operationName' => $operation->operationName,
                'score' => $operation->score,
                'weight' => $operation->weight,
                'value' => wp_json_encode($operation->value),
                'suggestions' => wp_json_encode($operation->suggestions),
            ];

            if ($operation->id === null || $operation->id === 0) {
                $insertedId = $db->insert('rankingcoach_seo_operations', $data);
                if ($insertedId) {
                    $operation->id = (int)$insertedId;
                }
            } else {
                $db->update('rankingcoach_seo_operations', $data, ['id' => $operation->id]);
            }
        }
    }

    /**
     * Get operations for a given factor ID
     *
     * @param int $factorId
     * @return Operations
     */
    public function getByFactorId(int $factorId): Operations
    {
        $db = DatabaseManager::getInstance();
        $rows = $db->getAll('rankingcoach_seo_operations', ['*'], ['factorId' => $factorId], 'id', 'ASC');

        $operations = new Operations();
        if (empty($rows)) {
            return $operations;
        }

        foreach ($rows as $row) {
            $operations->add($this->hydrateFromRow($row));
        }

        return $operations;
    }

    /**
     * Delete operations
     *
     * @param Operations $operations
     * @return bool
     */
    public function delete(Operations $operations): bool
    {
        $db = DatabaseManager::getInstance();
        $success = true;
        foreach ($operations->elements as $operation) {
            if ($operation->id !== null) {
                $deleted = $db->delete('rankingcoach_seo_operations', ['id' => $operation->id]);
                if ($deleted === false) {
                    $success = false;
                }
            }
        }
        return $success;
    }

    /**
     * Hydrate an operation object from a database row
     *
     * @param object $row
     * @return Operation
     */
    private function hydrateFromRow(object $row): Operation
    {
        $className = self::$classMap[$row->operationKey] ?? GenericOperation::class;
        
        /** @var Operation $operation */
        $operation = new $className($row->operationKey, $row->operationName, (float)$row->weight);
        $operation->id = (int)$row->id;
        $operation->factorId = (int)$row->factorId;
        $operation->score = (float)$row->score;
        $operation->value = !empty($row->value) ? json_decode($row->value, true) ?: [] : [];
        $operation->suggestions = !empty($row->suggestions) ? json_decode($row->suggestions, true) ?: [] : [];

        return $operation;
    }
}
