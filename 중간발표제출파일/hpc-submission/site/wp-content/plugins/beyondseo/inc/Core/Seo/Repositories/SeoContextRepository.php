<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Repositories;

if (!defined('ABSPATH')) { exit; }

use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\OptimiserContexts;
use RankingCoach\Inc\Core\Seo\Optimiser\Base\OptimiserContext;

/**
 * Class SeoContextRepository
 * 
 * Manages database operations for SEO context entities.
 */
class SeoContextRepository
{
    private static array $classMap = [
        'content_optimisation' => \RankingCoach\Inc\Core\Seo\Optimiser\Contexts\ContentOptimisationContext::class,
        'linking_strategy' => \RankingCoach\Inc\Core\Seo\Optimiser\Contexts\LinkingStrategyContext::class,
        'performance_and_speed' => \RankingCoach\Inc\Core\Seo\Optimiser\Contexts\PerformanceAndSpeedContext::class,
        'technical_seo' => \RankingCoach\Inc\Core\Seo\Optimiser\Contexts\TechnicalSeoContext::class,
    ];

    /**
     * Save/upsert contexts to the database
     *
     * @param OptimiserContexts $contexts
     * @return void
     */
    public function save(OptimiserContexts $contexts): void
    {
        $db = DatabaseManager::getInstance();
        $factorRepo = new SeoFactorRepository();

        foreach ($contexts->elements as $context) {
            $data = [
                'analysisId' => $context->analysisId,
                'contextKey' => $context->contextKey,
                'contextName' => $context->contextName,
                'weight' => $context->weight,
                'score' => $context->score,
            ];

            if ($context->id === null || $context->id === 0) {
                $insertedId = $db->insert('rankingcoach_seo_contexts', $data);
                if ($insertedId) {
                    $context->id = (int)$insertedId;
                }
            } else {
                $db->update('rankingcoach_seo_contexts', $data, ['id' => $context->id]);
            }

            if ($context->id !== null) {
                foreach ($context->factors->elements as $factor) {
                    $factor->contextId = $context->id;
                }
                $factorRepo->save($context->factors);
            }
        }
    }

    /**
     * Get contexts for a given analysis/optimiser ID
     *
     * @param int $analysisId
     * @return OptimiserContexts
     */
    public function getByAnalysisId(int $analysisId): OptimiserContexts
    {
        $db = DatabaseManager::getInstance();
        $rows = $db->getAll('rankingcoach_seo_contexts', ['*'], ['analysisId' => $analysisId], 'id', 'ASC');

        $contexts = new OptimiserContexts();
        if (empty($rows)) {
            return $contexts;
        }

        foreach ($rows as $row) {
            $contexts->add($this->hydrateFromRow($row));
        }

        return $contexts;
    }

    /**
     * Delete contexts
     *
     * @param OptimiserContexts $contexts
     * @return bool
     */
    public function delete(OptimiserContexts $contexts): bool
    {
        $db = DatabaseManager::getInstance();
        $factorRepo = new SeoFactorRepository();
        $success = true;

        foreach ($contexts->elements as $context) {
            if ($context->id !== null) {
                // Delete child factors first
                $factorRepo->delete($context->factors);

                $deleted = $db->delete('rankingcoach_seo_contexts', ['id' => $context->id]);
                if ($deleted === false) {
                    $success = false;
                }
            }
        }
        return $success;
    }

    /**
     * Hydrate a context object from a database row
     *
     * @param object $row
     * @return OptimiserContext
     */
    private function hydrateFromRow(object $row): OptimiserContext
    {
        $className = self::$classMap[$row->contextKey] ?? OptimiserContext::class;

        /** @var OptimiserContext $context */
        $context = new $className($row->contextName, $row->contextKey, (float)$row->weight, (int)$row->analysisId);
        $context->id = (int)$row->id;
        $context->score = (float)$row->score;

        // Load factors
        $factorRepo = new SeoFactorRepository();
        $context->factors = $factorRepo->getByContextId($context->id);

        return $context;
    }
}
