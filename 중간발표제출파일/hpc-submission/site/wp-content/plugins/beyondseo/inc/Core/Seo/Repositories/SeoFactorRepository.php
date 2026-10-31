<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Repositories;

if (!defined('ABSPATH')) { exit; }

use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Factors;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\Factor;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\GenericFactor;

/**
 * Class SeoFactorRepository
 * 
 * Manages database operations for SEO factors.
 */
class SeoFactorRepository
{
    private static array $classMap = [
        'assign_keywords' => \RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation\AssignKeywordsFactor::class,
        'content_quality_and_length' => \RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation\ContentQualityAndLengthFactor::class,
        'content_readability' => \RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation\ContentReadabilityFactor::class,
        'first_paragraph_keyword_usage' => \RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation\FirstParagraphKeywordUsageFactor::class,
        'header_tags_structure' => \RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation\HeaderTagsStructureFactor::class,
        'meta_description_format_optimization' => \RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation\MetaDescriptionFormatOptimizationFactor::class,
        'meta_description_keywords' => \RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation\MetaDescriptionKeywordsFactor::class,
        'meta_title_format_optimization' => \RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation\MetaTitleFormatOptimizationFactor::class,
        'meta_title_keywords' => \RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation\MetaTitleKeywordsFactor::class,
        'page_content_keywords' => \RankingCoach\Inc\Core\Seo\Optimiser\Factors\ContentOptimisation\PageContentKeywordsFactor::class,
        'fix_broken_links_on_page' => \RankingCoach\Inc\Core\Seo\Optimiser\Factors\LinkingStrategy\FixBrokenLinksOnPageFactor::class,
        'alt_text_to_images' => \RankingCoach\Inc\Core\Seo\Optimiser\Factors\PerformanceAndSpeed\AltTextToImagesFactor::class,
        'image_optimization' => \RankingCoach\Inc\Core\Seo\Optimiser\Factors\PerformanceAndSpeed\ImageOptimizationFactor::class,
        'optimize_url_structure' => \RankingCoach\Inc\Core\Seo\Optimiser\Factors\TechnicalSeo\OptimizeUrlStructureFactor::class,
        'schema_markup' => \RankingCoach\Inc\Core\Seo\Optimiser\Factors\TechnicalSeo\SchemaMarkupFactor::class,
        'search_engine_indexation' => \RankingCoach\Inc\Core\Seo\Optimiser\Factors\TechnicalSeo\SearchEngineIndexationFactor::class,
        'use_canonical_tags' => \RankingCoach\Inc\Core\Seo\Optimiser\Factors\TechnicalSeo\UseCanonicalTagsFactor::class,
    ];

    /**
     * Save/upsert factors to the database
     *
     * @param Factors $factors
     * @return void
     */
    public function save(Factors $factors): void
    {
        $db = DatabaseManager::getInstance();
        $opRepo = new SeoOperationRepository();

        foreach ($factors->elements as $factor) {
            $data = [
                'contextId' => $factor->contextId,
                'factorKey' => $factor->factorKey,
                'factorName' => $factor->factorName,
                'description' => $factor->description,
                'weight' => $factor->weight,
                'score' => $factor->score,
                'fetchedData' => wp_json_encode($factor->fetchedData),
            ];

            if ($factor->id === null || $factor->id === 0) {
                $insertedId = $db->insert('rankingcoach_seo_factors', $data);
                if ($insertedId) {
                    $factor->id = (int)$insertedId;
                }
            } else {
                $db->update('rankingcoach_seo_factors', $data, ['id' => $factor->id]);
            }

            if ($factor->id !== null) {
                foreach ($factor->operations->elements as $operation) {
                    $operation->factorId = $factor->id;
                }
                $opRepo->save($factor->operations);
            }
        }
    }

    /**
     * Get factors for a given context ID
     *
     * @param int $contextId
     * @return Factors
     */
    public function getByContextId(int $contextId): Factors
    {
        $db = DatabaseManager::getInstance();
        $rows = $db->getAll('rankingcoach_seo_factors', ['*'], ['contextId' => $contextId], 'id', 'ASC');

        $factors = new Factors();
        if (empty($rows)) {
            return $factors;
        }

        foreach ($rows as $row) {
            $factors->add($this->hydrateFromRow($row));
        }

        return $factors;
    }

    /**
     * Delete factors
     *
     * @param Factors $factors
     * @return bool
     */
    public function delete(Factors $factors): bool
    {
        $db = DatabaseManager::getInstance();
        $opRepo = new SeoOperationRepository();
        $success = true;

        foreach ($factors->elements as $factor) {
            if ($factor->id !== null) {
                // Delete child operations first
                $opRepo->delete($factor->operations);
                
                $deleted = $db->delete('rankingcoach_seo_factors', ['id' => $factor->id]);
                if ($deleted === false) {
                    $success = false;
                }
            }
        }
        return $success;
    }

    /**
     * Hydrate a factor object from a database row
     *
     * @param object $row
     * @return Factor
     */
    private function hydrateFromRow(object $row): Factor
    {
        $className = self::$classMap[$row->factorKey] ?? GenericFactor::class;
        
        /** @var Factor $factor */
        $factor = new $className($row->factorName, $row->factorKey, (float)$row->weight, $row->description);
        $factor->id = (int)$row->id;
        $factor->contextId = (int)$row->contextId;
        $factor->score = (float)$row->score;
        $factor->fetchedData = !empty($row->fetchedData) ? json_decode($row->fetchedData, true) ?: [] : [];

        // Load operations
        $opRepo = new SeoOperationRepository();
        $factor->operations = $opRepo->getByFactorId($factor->id);

        return $factor;
    }
}
