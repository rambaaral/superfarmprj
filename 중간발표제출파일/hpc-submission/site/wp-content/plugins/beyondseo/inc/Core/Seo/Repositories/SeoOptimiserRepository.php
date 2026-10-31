<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Repositories;

if (!defined('ABSPATH')) { exit; }

use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Core\Seo\Optimiser\SeoOptimiser;
use DateTime;

/**
 * Class SeoOptimiserRepository
 * 
 * Manages database operations for the SeoOptimiser aggregate root.
 */
class SeoOptimiserRepository
{
    /**
     * Save/upsert a SeoOptimiser aggregate to the database
     *
     * @param SeoOptimiser $optimiser
     * @return SeoOptimiser
     */
    public function save(SeoOptimiser $optimiser): SeoOptimiser
    {
        $db = DatabaseManager::getInstance();
        $contextRepo = new SeoContextRepository();

        $data = [
            'postId' => $optimiser->postId,
            'overallScore' => $optimiser->score,
            'analysisDate' => $optimiser->analysisDate->format('Y-m-d H:i:s'),
        ];

        if ($optimiser->id === null || $optimiser->id === 0) {
            $insertedId = $db->insert('rankingcoach_seo_optimisers', $data);
            if ($insertedId) {
                $optimiser->id = (int)$insertedId;
            }
        } else {
            $db->update('rankingcoach_seo_optimisers', $data, ['id' => $optimiser->id]);
        }

        if ($optimiser->id !== null) {
            foreach ($optimiser->contexts->elements as $context) {
                $context->analysisId = $optimiser->id;
            }
            $contextRepo->save($optimiser->contexts);
        }

        return $optimiser;
    }

    /**
     * Get a SeoOptimiser by its post ID
     *
     * @param int $postId
     * @return SeoOptimiser|null
     */
    public function getByPostId(int $postId): ?SeoOptimiser
    {
        $db = DatabaseManager::getInstance();
        $row = $db->getRow('rankingcoach_seo_optimisers', ['*'], ['postId' => $postId], 'id', 'DESC');

        if (!$row) {
            return null;
        }

        return $this->hydrateFromRow($row);
    }

    /**
     * Delete a SeoOptimiser cascadingly
     *
     * @param SeoOptimiser $optimiser
     * @return bool
     */
    public function delete(SeoOptimiser $optimiser): bool
    {
        if ($optimiser->id === null) {
            return false;
        }

        $db = DatabaseManager::getInstance();
        $contextRepo = new SeoContextRepository();

        // Delete contexts cascadingly
        $contextRepo->delete($optimiser->contexts);

        return (bool)$db->delete('rankingcoach_seo_optimisers', ['id' => $optimiser->id]);
    }

    /**
     * Delete a SeoOptimiser by post ID
     *
     * @param int $postId
     * @return bool
     */
    public function deleteByPostId(int $postId): bool
    {
        $optimiser = $this->getByPostId($postId);
        if ($optimiser === null) {
            return false;
        }

        return $this->delete($optimiser);
    }

    /**
     * Hydrate a SeoOptimiser object from a database row
     *
     * @param object $row
     * @return SeoOptimiser
     */
    private function hydrateFromRow(object $row): SeoOptimiser
    {
        $optimiser = new SeoOptimiser((int)$row->postId);
        $optimiser->id = (int)$row->id;
        $optimiser->score = (float)$row->overallScore;
        $optimiser->analysisDate = new DateTime($row->analysisDate);

        // Load child contexts
        $contextRepo = new SeoContextRepository();
        $optimiser->contexts = $contextRepo->getByAnalysisId($optimiser->id);

        return $optimiser;
    }
}
